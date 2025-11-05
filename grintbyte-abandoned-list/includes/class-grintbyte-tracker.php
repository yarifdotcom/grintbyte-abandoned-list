<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Tracker {

    const CACHE_EXPIRY = 300;       // Cache cart hash for 5 minutes
    const MIN_UPDATE_DELAY = 60;    // Minimum 1 minute between DB writes

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'gb_abandoned_carts';

        /**
         * Cart event listeners
         * These run whenever cart changes — but DB insert is skipped for guests without email
         */
        add_action( 'woocommerce_cart_updated', [ $this, 'record_cart' ] );
        add_action( 'woocommerce_add_to_cart', [ $this, 'record_cart' ], 20, 6 );
        add_action( 'woocommerce_cart_item_removed', [ $this, 'record_cart' ], 20, 1 );

        /**
         * Capture guest email during classic checkout AJAX (?wc-ajax=update_order_review)
         */
        add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_guest_email' ] );

        /**
         * Capture guest email during block checkout (Store API)
         * Uncomment if using WooCommerce Blocks
         */
        // add_action( 'woocommerce_store_api_cart_update_customer_from_request', [ $this, 'capture_guest_email_from_rest' ], 10, 2 );

        /**
         * Cart recovery links
         */
        add_action( 'template_redirect', [ $this, 'handle_recovery' ] );

        /**
         * Cleanup after successful checkout
         */
        add_action( 'woocommerce_thankyou', [ $this, 'handle_order_complete' ], 10, 1 );
    }

    /**
     * Main cart tracking logic.
     * Will NOT insert into DB unless email is available (lazy insert).
     */
    public function record_cart() {
        // Skip if in admin area or AJAX from admin
        if ( is_admin() && !( defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['wc-ajax']) ) ) {
            return;
        }

        // Skip REST API calls from wp-json endpoints
        if ( defined('REST_REQUEST') && REST_REQUEST && current_user_can('manage_options') ) {
            return;
        }
        
        if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) return;

        // GrintByte_Logger::info("CAPTURE ACTIVITY");

        $session = WC()->session ? WC()->session->get_session_cookie() : null;
        $session_id = $session[0] ?? session_id();
        if ( ! $session_id ) return;

        $cart = WC()->cart->get_cart();
        if ( empty( $cart ) ) return;

        // Prevent too frequent DB writes
        $cart_hash = md5( wp_json_encode( $cart ) );
        $cache_key = 'gbabandoned_' . md5( $session_id );
        $cached = get_transient( $cache_key );
        if ( $cached && $cached['hash'] === $cart_hash && time() - $cached['time'] < self::MIN_UPDATE_DELAY ) {
            return;
        }
        set_transient( $cache_key, [ 'hash' => $cart_hash, 'time' => time() ], self::CACHE_EXPIRY );

        // Identify user / guest email
        $user_id = get_current_user_id() ?: null;
        $email = null;
        if ( $user_id ) {
            $u = get_userdata( $user_id );
            $email = $u ? $u->user_email : null;

            // check if user login change billing email
            if ( isset( $_POST['billing_email'] ) && is_email( $_POST['billing_email'] ) ) {
                $checkout_email = sanitize_email( $_POST['billing_email'] );
                if ( $checkout_email && $checkout_email !== $email ) {
                    $email = $checkout_email;
                    GrintByte_Logger::info("User {$user_id} changed checkout email to {$email}");
                }
            }
            
        } else {
            $c = WC()->session->get('customer');
            $email = $c['email'] ?? null;
        }

        // Skip if no email yet (guest browsing only)
        if ( ! $email ) {
            GrintByte_Logger::info("Guest without email, skip DB insert ({$session_id})");
            return;
        }

        // Prepare serialized cart data
        $items = [];
        foreach ( $cart as $it ) {
            $items[] = [
                'product_id' => $it['product_id'],
                'quantity'   => $it['quantity'],
                'data'       => [
                    'name'  => $it['data']->get_name(),
                    'price' => wc_get_price_to_display( $it['data'] ),
                ],
            ];
        }

        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $json = maybe_serialize($items);

        // Insert or update abandoned cart
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$this->table} WHERE session_id=%s", $session_id) );
        if ( $exists ) {
            $wpdb->update( $this->table, [
                'user_id'        => $user_id,
                'email'          => $email,
                'items'          => $json,
                'last_activity'  => $now,
            ], [ 'id' => $exists ] );
            GrintByte_Logger::info("Updated cart {$session_id} ({$email})");
        } else {
            $token = wp_generate_password(20, false);
            $wpdb->insert( $this->table, [
                'session_id'   => $session_id,
                'user_id'      => $user_id,
                'email'        => $email,
                'items'        => $json,
                'created_at'   => $now,
                'last_activity'=> $now,
                'token'        => $token,
            ] );
            GrintByte_Logger::info("New cart recorded {$session_id} (email: {$email})");
        }
    }

    /**
     * Universal logic for updating guest email in DB.
     * Called from both classic and block checkout flows.
     */
    private function update_guest_email( $email ) {
        if ( ! is_email( $email ) ) return;

        $session = WC()->session ? WC()->session->get_session_cookie() : null;
        $session_id = $session[0] ?? session_id();
        if ( ! $session_id ) return;

        global $wpdb;

        // Avoid duplicates — remove any other carts using same email but different session
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table} WHERE email = %s AND session_id != %s",
            $email,
            $session_id
        ) );

        // Check if this session already exists
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$this->table} WHERE session_id=%s", $session_id) );

        if ( $exists ) {
            // If exists, just update email
            $wpdb->update( $this->table, [ 'email' => $email ], [ 'id' => $exists ] );
            GrintByte_Logger::info("Updated guest email {$email} for session {$session_id}");
        } else {
            // Lazy insert — guest filled email for the first time at checkout
            $token = wp_generate_password(20, false);
            $wpdb->insert( $this->table, [
                'session_id'   => $session_id,
                'email'        => $email,
                'items'        => maybe_serialize( WC()->cart->get_cart() ),
                'created_at'   => gmdate('Y-m-d H:i:s'),
                'last_activity'=> gmdate('Y-m-d H:i:s'),
                'token'        => $token,
            ] );
            GrintByte_Logger::info("New guest cart created after email capture {$email}");
        }
    }

    /**
     * Capture guest email from classic checkout (AJAX-based checkout)
     */
    public function capture_guest_email( $post_data ) {
        if ( empty( $post_data ) ) return;

        parse_str( $post_data, $data );
        $email = $data['billing_email'] ?? null;
        if ( ! $email ) return;

        GrintByte_Logger::info("capture_guest_email AJAX: {$email}");
        $this->update_guest_email( sanitize_email( $email ) );
    }

    /**
     * Capture guest email from modern WooCommerce block checkout (Store API)
     */
    public function capture_guest_email_from_rest( $order, $request ) {
        $data  = $request->get_json_params();
        $email = $data['email'] ?? ( $data['billing_address']['email'] ?? null );
        if ( ! $email ) return;

        GrintByte_Logger::info("capture_guest_email REST: {$email}");
        $this->update_guest_email( sanitize_email( $email ) );
    }

    /**
     * Handle cart recovery via token links
     */
    public function handle_recovery() {
        if ( empty($_GET['gb_recover_token']) ) return;

        global $wpdb;
        $token = sanitize_text_field($_GET['gb_recover_token']);
        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->table} WHERE token=%s", $token) );
        if ( ! $row ) return;

        $items = maybe_unserialize($row->items);
        WC()->cart->empty_cart();

        if ( is_array($items) ) {
            foreach ( $items as $it ) {
                $product_id = isset($it['product_id']) ? (int) $it['product_id'] : 0;
                $qty = isset($it['quantity']) ? (int) $it['quantity'] : 1;
                if ( $product_id > 0 ) {
                    WC()->cart->add_to_cart( $product_id, $qty );
                }
            }
        }

        // update status
        $wpdb->update(
            $this->table,
            [
                'recovered'     => 1,
                'recovered_at'  => gmdate('Y-m-d H:i:s'),
            ],
            [ 'id' => $row->id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        // save flag at session agar checkout berikutnya tahu ini hasil recovery
        if ( WC()->session ) {
            WC()->session->set( 'gb_recovered_token', $token );
        }
        
        GrintByte_Logger::info("Cart recovered successfully for token {$token} ({$row->email})");

        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    /**
     * Remove abandoned cart after successful order (cleanup)
     */
    public function handle_order_complete( $order_id ) {
        if ( ! $order_id ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $user_id = $order->get_user_id();
        $email   = $order->get_billing_email();

        global $wpdb;
        $table = $this->table;
        $now = gmdate('Y-m-d H:i:s');

         $from_recovery = WC()->session && WC()->session->get('gb_recovered_token');

        if ( $from_recovery ) {
            // checkout dari recovery email → update timestamp statistik
            if ( $user_id ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table} SET recovered_at = %s WHERE user_id = %d AND recovered = 1",
                    $now,
                    $user_id
                ) );
            } elseif ( $email ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table} SET recovered_at = %s WHERE email = %s AND recovered = 1",
                    $now,
                    $email
                ) );
            }

            GrintByte_Logger::info("Order from recovery link → kept for stats ({$email})");
        } else {
            // checkout normal → hapus record abandoned cart
            if ( $user_id ) {
                $wpdb->query( $wpdb->prepare(
                    "DELETE FROM {$table} WHERE user_id = %d",
                    $user_id
                ) );
            } elseif ( $email ) {
                $wpdb->query( $wpdb->prepare(
                    "DELETE FROM {$table} WHERE email = %s",
                    $email
                ) );
            }

            GrintByte_Logger::info("Order completed normally → abandoned cart removed ({$email})");
        }

        // bersihkan flag session
        if ( WC()->session ) {
            WC()->session->__unset( 'gb_recovered_token' );
        }
    }
}
