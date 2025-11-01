<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Deactivator {
    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'gbabandoned_cron_check' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'gbabandoned_cron_check' );
            GrintByte_Logger::log("Cron event unscheduled on plugin deactivation");
        }

        // (Opsional) delete tabel from database
        global $wpdb;
        $table = $wpdb->prefix . 'gb_abandoned_carts';
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );

        // delete option
        delete_option( 'gbabandoned_delay_minutes' );
        delete_option( 'gbabandoned_cleanup_days' );
        delete_option( 'gbabandoned_cron_interval' );

        GrintByte_Logger::info("GrintByte Abandoned plugin deactivated & table dropped");
        file_put_contents( WP_CONTENT_DIR . '/debug-abandoned.log', "[".gmdate('Y-m-d H:i:s')."] Plugin deactivated & table dropped\n", FILE_APPEND );
    }
}
