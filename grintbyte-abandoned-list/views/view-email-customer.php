<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$subject = get_option( 'gbabandoned_customer_subject', 'You left items in your cart!' );
$body = get_option( 'gbabandoned_customer_body', "Hi {customer_email},\n\nYou left some items in your cart.\nClick below to restore your cart:\n{restore_link}" );
?>

<div class="wrap">
    <h2>Email Template for Customer</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'gbabandoned_save_email_customer' ); ?>
        <input type="hidden" name="action" value="gbabandoned_save_email_customer" />

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="customer_subject">Email Subject</label></th>
                    <td>
                        <input type="text" name="customer_subject" id="customer_subject" class="regular-text"
                               value="<?php echo esc_attr( $subject ); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="customer_body">Email Body</label></th>
                    <td>
                        <textarea name="customer_body" id="customer_body" rows="10" class="large-text code"><?php echo esc_textarea( $body ); ?></textarea>
                        <p class="description">
                            Available placeholders:<br>
                            <code>{customer_email}</code>, <code>{restore_link}</code>, <code>{store_name}</code>, <code>{items}</code>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button( 'Save Changes' ); ?>
    </form>
</div>
