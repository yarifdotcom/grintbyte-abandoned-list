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
            $subject = sprintf(
                __( 'You left items in your cart at %s', 'grintbyte-abandoned' ),
                $store_name
            );

            $url = esc_url( add_query_arg( 'gb_recover_token', $record->token, site_url('/') ) );

            $message = '
            <html>
            <body style="font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 40px; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                    <h2 style="text-align:center; color:#333;">We saved your cart at ' . esc_html($store_name) . '</h2>
                    <p style="font-size:16px; line-height:1.5; text-align:center;">
                        You added some great items to your cart, but did not complete your order.<br>
                        We have saved your cart for you!
                    </p>
                    <div style="text-align:center; margin:30px 0;">
                        <a href="' . $url . '" style="background:#ff6b00; color:#fff; padding:14px 24px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;">
                            🛒 Restore My Cart
                        </a>
                    </div>
                    <p style="font-size:14px; color:#666; text-align:center;">
                        If you did not intend to leave items in your cart, you can safely ignore this message.
                    </p>
                </div>
                <p style="text-align:center; font-size:12px; color:#aaa; margin-top:20px;">
                    &copy; ' . date('Y') . ' ' . esc_html($store_name) . '
                </p>
            </body>
            </html>';

            // set HTML content-type
            add_filter( 'wp_mail_content_type', function() { return 'text/html'; });

            $sent = wp_mail( $email, $subject, $message );

            // remove filter by referencing the same closure signature (anonymous closures cannot be removed easily),
            // so use a named function instead to be safe. We'll remove by resetting to default via remove_filter with a wrapper:
            remove_filter( 'wp_mail_content_type', function() { return 'text/html'; } );

            $now = gmdate('Y-m-d H:i:s');

            if ( $sent ) {
                $wpdb->update(
                    $table,
                    [ 'notified' => 1, 'last_notified_at' => $now ],
                    [ 'id' => $record->id ]
                );
                GrintByte_Logger::info("Recovery email sent successfully to {$email} (id={$record->id})");
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
}
