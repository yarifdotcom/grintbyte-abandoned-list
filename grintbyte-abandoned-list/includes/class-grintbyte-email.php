<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Email {

    public static function init() {
        add_action( 'wp_mail_failed', [ __CLASS__, 'on_mail_failed' ] );
    }

    public static function on_mail_failed( $wp_error ) {
        if ( is_wp_error( $wp_error ) ) {
            GrintByte_Logger::warn("Mailer error: " . $wp_error->get_error_message());
        }
    }

    /**
     * Send recovery email for a single record.
     * Returns true on success, false on failure.
     * Ensures no exception bubbles up.
     */
    public static function send_recovery( $record ) {
        global $wpdb;

        try {
            $table = $wpdb->prefix . 'gb_abandoned_carts';

            $email = sanitize_email( $record->email );
            if ( ! is_email( $email ) ) {
                GrintByte_Logger::warn("Invalid email skipped: " . $record->email);
                // mark as failed and set timestamp to avoid infinite retry (optional)
                $now = gmdate('Y-m-d H:i:s');
                $wpdb->update( $table, [ 'notified' => -1, 'last_notified_at' => $now ], [ 'id' => $record->id ] );
                return false;
            }

            $store_name = get_bloginfo('name');
            $restore_link = esc_url( add_query_arg( 'gb_recover_token', $record->token, site_url('/') ) );
            $now = gmdate('Y-m-d H:i:s');
            
            $formatted_items = self::format_cart_items( $record->items );

            $customer_subject_template = get_option( 'gbabandoned_customer_subject', 'You left items in your cart at {store_name}' );
            $customer_body_template = get_option( 'gbabandoned_customer_body',  '<p>Hi {customer_email}, restore your cart: <a href="{restore_link}">Click here</a></p>' );

            $placeholders = [
                '{customer_email}' => $email,
                '{store_name}'     => esc_html( $store_name ),
                '{items}'          => $formatted_items,
                '{restore_link}'   => $restore_link
            ];
            
            $customer_subject = str_replace(
                array_keys( $placeholders ),
                array_values( $placeholders ),
                $customer_subject_template
            );
            
            $customer_message = str_replace(
                array_keys( $placeholders ),
                array_values( $placeholders ),
                $customer_body_template
            );

            // set HTML content-type
            add_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );
            
            $sent = wp_mail( $email, $customer_subject, $customer_message );
            
            // remove filter by referencing the same closure signature (anonymous closures cannot be removed easily),
            // so use a named function instead to be safe. We'll remove by resetting to default via remove_filter with a wrapper:

            remove_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );

            if ( $sent ) {
                $wpdb->update(
                    $table,
                    [ 'notified' => 1, 'last_notified_at' => $now ],
                    [ 'id' => $record->id ]
                );
                GrintByte_Logger::info("Recovery email sent successfully to {$email} (id={$record->id})");

                // Sent successfull email to customer also to admin
                $admin_email = get_option( 'gbabandoned_admin_email', get_option( 'admin_email' ) );
                
                if ( is_email( $admin_email ) ) {
                    $admin_subject_template = get_option( 'gbabandoned_admin_subject', 'Customer abandoned a cart' );
                    $admin_body_template = get_option( 'gbabandoned_admin_body', 'Customer {customer_email} abandoned a cart on {date}' );

                    $admin_placeholders = [
                        '{customer_email}' => $email,
                        '{items}'          => $formatted_items,
                        '{date}'           => date_i18n('Y-m-d H:i:s'),
                    ];

                    $admin_subject = str_replace(
                        array_keys( $admin_placeholders ),
                        array_values( $admin_placeholders ),
                        $admin_subject_template
                    );

                    $admin_body = str_replace(
                        array_keys( $admin_placeholders ),
                        array_values( $admin_placeholders ),
                        $admin_body_template
                    );

                    add_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );
                    $sent_admin = wp_mail( $admin_email, $admin_subject, $admin_body );
                    remove_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );

                    if ( $sent_admin ) {
                        GrintByte_Logger::info("Admin notified about abandoned cart from {$email} (id={$record->id})");
                    } else {
                        GrintByte_Logger::warn("Failed to notify admin about abandoned cart from {$email} (id={$record->id})");
                    }
                }

                return true;

            } else {
                $wpdb->update(
                    $table,
                    [ 'notified' => -1, 'last_notified_at' => $now ],
                    [ 'id' => $record->id ]
                );
                GrintByte_Logger::warn("Failed to send recovery email to {$email} (id={$record->id})");
                return false;
            }
        } catch ( Exception $e ) {
            // Catch any unexpected exception and convert into a failed send without rethrowing.
            $now = gmdate('Y-m-d H:i:s');
            if ( isset( $table ) && isset( $record->id ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'gb_abandoned_carts',
                    [ 'notified' => -1, 'last_notified_at' => $now ],
                    [ 'id' => $record->id ],
                    [ '%d', '%s' ],
                    [ '%d' ]
                );
            }
            GrintByte_Logger::error("Exception in send_recovery for id={$record->id}: " . $e->getMessage());
            return false;
        }
    }

    public static function set_html_content_type() {
        return 'text/html';
    }

    private static function format_cart_items( $items_raw ) {
        // JSON decode?
        $items = json_decode( $items_raw, true );

        // If not JSON decode, try unserialize
        if ( ! $items ) {
            $items = maybe_unserialize( $items_raw );
        }

        if ( ! is_array( $items ) || empty( $items ) ) {
            return "(No cart items)";
        }

        $lines = [];

        foreach ( $items as $item ) {

            $name = '';

            // WC Product Object
            if ( isset( $item['data'] ) ) {
                if ( is_object( $item['data'] ) && method_exists( $item['data'], 'get_name' ) ) {
                    $name = $item['data']->get_name();
                }
                elseif ( is_array( $item['data'] ) && isset( $item['data']['name'] ) ) {
                    $name = $item['data']['name'];
                }
            }

            // Fallback if product ID exists
            if ( ! $name && isset( $item['product_id'] ) ) {
                $product = wc_get_product( $item['product_id'] );
                if ( $product ) {
                    $name = $product->get_name();
                }
            }

            if ( ! $name ) {
                $name = 'Unknown Product';
            }

            $qty = isset($item['quantity']) ? $item['quantity'] : ( $item['qty'] ?? 1 );

            $lines[] = "- {$name} × {$qty}";
        }

        return implode("\n", $lines);
    }

}
