<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Admin {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'gb_abandoned_carts';

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_gbabandoned_delete_cart', [ $this, 'delete_cart' ] );
        add_action( 'admin_post_gbabandoned_save_settings', [ $this, 'save_settings' ] );
        add_action( 'admin_post_gbabandoned_save_email_admin', [ $this, 'save_email_admin' ] );
        add_action( 'admin_post_gbabandoned_save_email_customer', [ $this, 'save_email_customer' ] );
    }

    /**
     * Register submenu under WooCommerce for Abandoned Carts management
     */

    public function register_menu() {
        add_submenu_page(
            'woocommerce', // parent slug
            'Abandoned Carts', // page title
            'Abandoned Carts', // menu title
            'manage_woocommerce', // capability
            'grintbyte-abandoned', // slug
            [ $this, 'render_admin_page' ] // callback
        );
    }

    /**
     * Render the admin page with tab navigation (List / Settings)
     */
    public function render_admin_page() {
        $active_tab = $_GET['tab'] ?? 'list';
        ?>
        <div class="wrap">
            <h1>🛒 GrintByte Abandoned Carts</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=grintbyte-abandoned&tab=list" class="nav-tab <?php echo $active_tab == 'list' ? 'nav-tab-active' : ''; ?>">Abandoned Carts</a>
                <a href="?page=grintbyte-abandoned&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=grintbyte-abandoned&tab=email_admin" class="nav-tab <?php echo $active_tab == 'email_admin' ? 'nav-tab-active' : ''; ?>">Email Admin</a>
                <a href="?page=grintbyte-abandoned&tab=email_customer" class="nav-tab <?php echo $active_tab == 'email_customer' ? 'nav-tab-active' : ''; ?>">Email Customer</a>
                <a href="?page=grintbyte-abandoned&tab=howto" class="nav-tab <?php echo $active_tab == 'howto' ? 'nav-tab-active' : ''; ?>">How-To</a>
            </h2>
        <?php

        $view_path = plugin_dir_path( __FILE__ ) . '../views/';
        switch ( $active_tab ) {
            case 'settings':
                include $view_path . 'view-settings.php';
                break;
            case 'email_admin':
                include $view_path . 'view-email-admin.php';
                break;
            case 'email_customer':
                include $view_path . 'view-email-customer.php';
                break;
            case 'howto':
                include $view_path . 'view-howto.php';
                break;
            default:
                include $view_path . 'view-list.php';
                break;
        }

        echo "</div>";
    }

     /**
     * Handle saving settings from the admin form
     */
    public function save_settings() {
        check_admin_referer( 'gbabandoned_save_settings' );

        // Sanitize and store values
        update_option( 'gbabandoned_delay_minutes', intval($_POST['delay']) );
        update_option( 'gbabandoned_cleanup_days', intval($_POST['cleanup']) );
        update_option( 'gbabandoned_cron_interval', intval($_POST['cron_interval']) );
        update_option( 'gbabandoned_delete_recovered', isset( $_POST['gbabandoned_delete_recovered'] ) ? 1 : 0 );

        // Reschedule cron with new interval
        if ( class_exists( 'GrintByte_Cron' ) && method_exists( 'GrintByte_Cron', 'reschedule_cron' ) ) {
            GrintByte_Cron::reschedule_cron();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=grintbyte-abandoned&tab=settings&updated=true' ) );
        exit;
    }

    public function delete_cart() {
        check_admin_referer( 'gbabandoned_delete_cart' );

        global $wpdb;
        $id = intval( $_POST['id'] );

        if ( $id > 0 ) {
            $wpdb->delete( $this->table, [ 'id' => $id ] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=grintbyte-abandoned&tab=list&deleted=1' ) );
        exit;
    }

    /**
     * Handle saving settings from the email admin
     */

    public function save_email_admin() {
        check_admin_referer( 'gbabandoned_save_email_admin' );

        update_option( 'gbabandoned_admin_email', sanitize_email( $_POST['admin_email'] ?? '' ) );
        update_option( 'gbabandoned_admin_subject', sanitize_text_field( $_POST['admin_subject'] ?? '' ) );
        update_option( 'gbabandoned_admin_body', wp_kses_post( $_POST['admin_body'] ?? '' ) );

        wp_safe_redirect( admin_url( 'admin.php?page=grintbyte-abandoned&tab=email_admin&updated=true' ) );
        exit;
    }

    /**
     * Handle saving settings from the email customer
     */


    public function save_email_customer() {
        check_admin_referer( 'gbabandoned_save_email_customer' );

        update_option( 'gbabandoned_customer_subject', sanitize_text_field( $_POST['customer_subject'] ?? '' ) );
        update_option( 'gbabandoned_customer_body', wp_kses_post( $_POST['customer_body'] ?? '' ) );

        wp_safe_redirect( admin_url( 'admin.php?page=grintbyte-abandoned&tab=email_customer&updated=true' ) );
        exit;
    }

    public function admin_notices() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'grintbyte-abandoned' ) {
            return; // Only show notices on our plugin page
        }

        $tab = $_GET['tab'] ?? '';
        $interval = isset( $_GET['interval'] ) ? intval( $_GET['interval'] ) : null;

        if ( $tab === 'settings' && isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
            $message = 'Settings saved successfully.';
            if ( $interval ) {
                $message .= " Cron interval updated to {$interval} minutes.";
            }
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
        }

        if ( $tab === 'list' && isset( $_GET['deleted'] ) && $_GET['deleted'] == 1 ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Cart record deleted successfully.</strong></p></div>';
        }
    }

}
