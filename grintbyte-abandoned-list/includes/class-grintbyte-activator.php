<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Activator {
    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . 'gb_abandoned_carts';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(191) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(191) DEFAULT NULL,
            items longtext DEFAULT NULL,
            last_activity datetime NOT NULL,
            notified tinyint(1) DEFAULT 0,
            recovered tinyint(1) DEFAULT 0,
            last_notified_at datetime DEFAULT NULL,
            recovered_at datetime DEFAULT NULL,
            token varchar(191) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        add_option( 'gbabandoned_delay_minutes', 60 );
        add_option( 'gbabandoned_cleanup_days', 30 );
        add_option( 'gbabandoned_cron_interval', 60 );

        // set cron job firstly
        if ( ! wp_next_scheduled( 'gbabandoned_cron_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'gbabandoned_cron_check' );
            GrintByte_Logger::log("Cron event scheduled with default hourly interval");
        }

        GrintByte_Logger::info("GrintByte Abandoned plugin activated");
        file_put_contents( WP_CONTENT_DIR . '/debug-abandoned.log', "[".gmdate('Y-m-d H:i:s')."] Plugin activated\n", FILE_APPEND );
    }
}
