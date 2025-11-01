<?php
/**
 * Plugin Name: GrintByte Abandoned List
 * Description: Simple abandoned cart recorder for WooCommerce with smart delay, recovery link, and cron system.
 * Version: 0.6
 * Author: GrintByte
 * Text Domain: grintbyte-abandoned
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// WooCommerce must active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>GrintByte Abandoned List</strong> membutuhkan WooCommerce agar bisa dijalankan.</p></div>';
    });
    return;
}

define( 'GB_ABANDONED_PATH', plugin_dir_path( __FILE__ ) );
define( 'GB_ABANDONED_URL', plugin_dir_url( __FILE__ ) );
define( 'GB_ABANDONED_VER', '0.6' );

require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-logger.php';
require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-activator.php';
require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-deactivator.php';
require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-admin.php';
require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-tracker.php';
require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-cron.php';
require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-email.php';
require_once GB_ABANDONED_PATH . 'includes/class-grintbyte-abandoned.php';

register_activation_hook( __FILE__, [ 'GrintByte_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'GrintByte_Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', function() {
    GrintByte_Abandoned::instance();
});
