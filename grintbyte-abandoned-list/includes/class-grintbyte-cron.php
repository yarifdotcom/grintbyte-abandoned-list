<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Cron {
    public function __construct() {
        add_filter( 'cron_schedules', [ $this, 'add_custom_schedule' ] );
        add_action( 'gbabandoned_cron_check', [ $this, 'run' ] );
    }

    /**
     * Register custom cron interval (e.g. every X minutes)
     */

    public function add_custom_schedule( $schedules ) {
        $custom_interval = (int)get_option( 'gbabandoned_cron_interval', 60 ); // default 60 min
        $schedules['gbabandoned_custom'] = [
            'interval' => $custom_interval * 60, // detik
            'display'  => "Every {$custom_interval} minutes"
        ];
        return $schedules;
    }

    /**
     * Reschedule cron job when user changes the interval
     */
    public static function reschedule_cron() {
        $hook = 'gbabandoned_cron_check';
        $custom_interval = (int)get_option( 'gbabandoned_cron_interval', 60 );

        // delete old default cron
        $timestamp = wp_next_scheduled( $hook );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook );
            GrintByte_Logger::info("Old cron event unscheduled");
        }

        // set with new interval
        wp_schedule_event( time(), 'gbabandoned_custom', $hook );
        GrintByte_Logger::info("Cron rescheduled using custom interval: {$custom_interval} minutes");
    }

    /**
     * Main cron task — check for abandoned carts & send recovery emails
     */
    public function run() {
        global $wpdb;
        $table = $wpdb->prefix . 'gb_abandoned_carts';
        $delay = (int)get_option('gbabandoned_delay_minutes', 60);
        $custom_interval = (int)get_option( 'gbabandoned_cron_interval', 60 );

        GrintByte_Logger::info("Running abandoned cart cron check... (interval: {$custom_interval} min)");

        // Find abandoned carts
        $threshold = gmdate('Y-m-d H:i:s', strtotime("-{$delay} minutes"));

        GrintByte_Logger::log("Server time: " . gmdate('Y-m-d H:i:s'));
        GrintByte_Logger::log("Threshold : " . $threshold);

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE last_activity <= %s AND notified=0", $threshold)
        );

        if ( $rows ) {
            GrintByte_Logger::info("Found " . count($rows) . " abandoned carts to notify");
            foreach ( $rows as $r ) {
                if ( $r->email ) {
                    GrintByte_Email::send_recovery($r);
                }
            }
        } else {
            GrintByte_Logger::info("No abandoned carts found at this time");
        }

        // cleanup
        $cleanup = (int)get_option('gbabandoned_cleanup_days', 30);
        $cleanup_ts = gmdate('Y-m-d H:i:s', strtotime("-{$cleanup} days"));
        $delete_recovered = get_option( 'gbabandoned_delete_recovered', false ); // default: false (unchecked)
        
        if ( $delete_recovered ) {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE last_activity <= %s",
                    $cleanup_ts
                )
            );
            GrintByte_Logger::info("Cleanup: deleted {$deleted} old carts (including recovered)");
        } else {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE last_activity <= %s AND recovered = 0",
                    $cleanup_ts
                )
            );
            GrintByte_Logger::info("Cleanup: deleted {$deleted} old non-recovered carts");
        }
        
        if ( $deleted ) {
            GrintByte_Logger::info("Cleaned up {$deleted} old abandoned carts");
        }

        GrintByte_Logger::info("Cron check completed.\n");
    }
}
