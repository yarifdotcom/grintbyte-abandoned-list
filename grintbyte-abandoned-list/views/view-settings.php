<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$delay = get_option( 'gbabandoned_delay_minutes', 60 );
$cleanup = get_option( 'gbabandoned_cleanup_days', 30 );
$cron_interval = get_option( 'gbabandoned_cron_interval', 60 );

?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'gbabandoned_save_settings' ); ?>
    <input type="hidden" name="action" value="gbabandoned_save_settings">

    <table class="form-table">
        <tr>
            <th scope="row">Delay before sending email (minutes)</th>
            <td>
                <input type="number" name="delay" value="<?php echo esc_attr( $delay ); ?>" min="5" /> minutes
            </td>
        </tr>
        <tr>
            <th scope="row">Auto cleanup after (days)</th>
            <td>
                <input type="number" name="cleanup" value="<?php echo esc_attr( $cleanup ); ?>" min="1" /> days
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Cleanup Recovered Carts', 'grintbyte-abandoned'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="gbabandoned_delete_recovered" value="1"
                        <?php checked( get_option('gbabandoned_delete_recovered', 0), 1 ); ?>>
                    <?php _e('Also delete recovered carts during cleanup', 'grintbyte-abandoned'); ?>
                </label>
                <p class="description">
                    <?php _e('If checked, recovered carts will also be permanently deleted after the cleanup period.', 'grintbyte-abandoned'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">Cron job interval</th>
            <td>
                <select name="cron_interval">
                    <option value="15" <?php selected( $cron_interval, 15 ); ?>>Every 15 minutes</option>
                    <option value="30" <?php selected( $cron_interval, 30 ); ?>>Every 30 minutes</option>
                    <option value="60" <?php selected( $cron_interval, 60 ); ?>>Every 60 minutes</option>
                </select>
                 <p class="description">
                    Select how often the plugin should check for abandoned carts and send reminder emails.
                </p>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="submit" class="button-primary">Save Changes</button>
    </p>
</form>
