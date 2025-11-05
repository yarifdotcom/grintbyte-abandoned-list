<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$admin_email = get_option( 'gbabandoned_admin_email', get_option( 'admin_email' ) );
$subject = get_option( 'gbabandoned_admin_subject', 'Customer abandoned a cart' );
$body = get_option( 'gbabandoned_admin_body', "A customer ({customer_email}) has an abandoned cart.\n\nCart contents:\n{cart_items}\n\nVisit admin dashboard for details." );
?>

<div class="wrap">
    <h2>Email Notification to Admin</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'gbabandoned_save_email_admin' ); ?>
        <input type="hidden" name="action" value="gbabandoned_save_email_admin" />

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="admin_email">Admin Email</label></th>
                    <td>
                        <input type="email" name="admin_email" id="admin_email" class="regular-text"
                               value="<?php echo esc_attr( $admin_email ); ?>">
                        <p class="description">Address that receives notification when recovery email is sent to a customer.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="admin_subject">Email Subject</label></th>
                    <td>
                        <input type="text" name="admin_subject" id="admin_subject" class="regular-text"
                               value="<?php echo esc_attr( $subject ); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="admin_body">Email Body</label></th>
                    <td>
                        <textarea name="admin_body" id="admin_body" rows="8" class="large-text code"><?php echo esc_textarea( $body ); ?></textarea>
                        <p class="description">
                            Available placeholders:<br>
                            <code>{customer_email}</code>, <code>{date}</code>, <code>{items}</code>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button( 'Save Changes' ); ?>
    </form>
</div>
