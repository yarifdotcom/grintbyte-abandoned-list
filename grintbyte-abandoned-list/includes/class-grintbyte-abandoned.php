<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Abandoned {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private — singleton)
     */
    private function __construct() {
        // Pastikan WooCommerce aktif
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
            add_action( 'admin_init', [ $this, 'deactivate_self' ] );
            return;
        }

        // Inisialisasi modul utama
        $this->init_modules();
    }

    /**
     * Load semua komponen utama plugin
     */
    private function init_modules() {
        // Core components
        new GrintByte_Tracker();
        new GrintByte_Cron();
        
        // Hook email once
        GrintByte_Email::init();

        // Admin panel (hanya backend)
        if ( is_admin() ) {
            new GrintByte_Admin();
        }
    }

    /**
     * Cek apakah WooCommerce aktif
     */
    private function is_woocommerce_active() {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        return is_plugin_active( 'woocommerce/woocommerce.php' );
    }

    /**
     * Tampilkan admin notice jika WooCommerce tidak aktif
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>GrintByte Abandoned List</strong> membutuhkan WooCommerce agar berfungsi. Plugin dinonaktifkan otomatis.</p></div>';
    }

    /**
     * Auto deactivate plugin ini
     */
    public function deactivate_self() {
        deactivate_plugins( plugin_basename( GB_ABANDONED_PATH . 'main.php' ) );
    }
}
