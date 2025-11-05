<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GrintByte_Logger {

    private static $log_file;

    public static function init() {
         if ( ! self::$log_file ) {
            self::$log_file = WP_CONTENT_DIR . '/debug-abandoned.log';

            if ( ! file_exists( self::$log_file ) ) {
                @file_put_contents( self::$log_file, '' );
            }
        }
    }

     /**
     * Method utama untuk mencatat log
     *
     * @param string $msg - Pesan log
     * @param string $level - Level log (INFO, WARN, ERROR)
     */

    public static function log( $message , $level = 'INFO') {
        $enabled = get_option( 'gbabandoned_enable_log', 0 ); // default: false (unchecked)
        if(!$enabled) return true;
        
        self::init();

        $timestamp = gmdate('Y-m-d H:i:s');
        $entry = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents( self::$log_file, $entry, FILE_APPEND | LOCK_EX  );
    }

    public static function separator() {
        self::log( "----------------------------------------" );
    }

     /** Shortcut helpers **/
    public static function info( $msg ) {
        self::log( $msg, 'INFO' );
    }

    public static function warn( $msg ) {
        self::log( $msg, 'WARN' );
    }

    public static function error( $msg ) {
        self::log( $msg, 'ERROR' );
    }
}
