<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table = $wpdb->prefix . 'gb_abandoned_carts';
$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY last_activity DESC LIMIT 100" );

if ( ! $rows ) {
    echo "<p>No abandoned carts recorded yet.</p>";
    return;
}
?>

<style>
.badge-secondary { background:#ccc; color:#333; }
.badge-info { background:#007cba; color:#fff; }
.badge-error { background:#d63638; color:#fff; }
.badge-success { background:#46b450; color:#fff; }
</style>


<table class="widefat striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Email</th>
            <th>Items</th>
            <th>Status</th>
            <th>Last Activity</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $rows as $row ) : ?>
        <tr>
            <td><?php echo esc_html( $row->id ); ?></td>
            <td><?php echo $row->user_id ? esc_html( get_userdata( $row->user_id )->user_login ) : '<em>Guest</em>'; ?></td>
            <td><?php echo esc_html( $row->email ); ?></td>
            <td>
                <?php
                $items = maybe_unserialize( $row->items );
                if ( is_array( $items ) ) {
                    foreach ( $items as $item ) {
                        $name = '';

                        if ( isset( $item['data'] ) ) {
                            if ( is_object( $item['data'] ) && method_exists( $item['data'], 'get_name' ) ) {
                                // WooCommerce object (WC_Product / WC_Product_Variation)
                                $name = $item['data']->get_name();
                            } elseif ( is_array( $item['data'] ) && isset( $item['data']['name'] ) ) {
                                // serialized data array
                                $name = $item['data']['name'];
                            }
                        } elseif ( isset( $item['product_id'] ) ) {
                            // fallback jika data tidak lengkap
                            $product = wc_get_product( $item['product_id'] );
                            if ( $product ) {
                                $name = $product->get_name();
                            }
                        }

                        echo esc_html( $name ) . ' × ' . esc_html( $item['quantity'] ?? 1 ) . '<br>';
                    }
                }

                ?>
            </td>
            <td>
                <?php
                // --- STATUS BADGE ---
                $status_label = 'Waiting';
                $status_class = 'secondary';

                if ( $row->recovered == 1 ) {
                    $status_label = 'Recovered';
                    $status_class = 'success';
                } elseif ( $row->notified == 1 ) {
                    $status_label = 'Emailed';
                    $status_class = 'info';
                } elseif ( $row->notified == -1 ) {
                    $status_label = 'Email Failed';
                    $status_class = 'error';
                }

                echo '<span class="badge badge-' . esc_attr( $status_class ) . '" style="padding:4px 8px;border-radius:4px;font-size:12px;">' . esc_html( $status_label ) . '</span>';
                ?>
            </td>
            <td><?php echo esc_html( get_date_from_gmt($row->last_activity , 'Y-m-d H:i:s')); ?></td>
            <td><?php echo esc_html( get_date_from_gmt($row->created_at , 'Y-m-d H:i:s')); ?></td>
            <td>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'gbabandoned_delete_cart' ); ?>
                    <input type="hidden" name="action" value="gbabandoned_delete_cart">
                    <input type="hidden" name="id" value="<?php echo esc_attr( $row->id ); ?>">
                    <button class="button button-small" onclick="return confirm('Delete this record?');">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>