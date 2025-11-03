<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wrap">
    <h1>📘 How to Use GrintByte Abandoned Cart</h1>
    <p>
        This guide explains how the plugin works and how you can extend it as a developer.
    </p>

    <hr />

    <h2>🚀 Overview</h2>
    <p>
        The plugin detects when WooCommerce customers abandon their carts, stores the cart data,
        and automatically sends recovery emails.
    </p>

    <h3>📦 Workflow</h3>
    <ol>
        <li>When a user adds products to their cart and enters an email (checkout or AJAX capture), data is saved into <code>wp_gb_abandoned_carts</code>.</li>
        <li>A WP Cron job checks periodically for carts marked as abandoned.</li>
        <li>If found, recovery emails are sent to customers and optionally to admins.</li>
    </ol>

    <h3>⚙️ Main Tables</h3>
    <pre><code>Table: wp_gb_abandoned_carts
- id
- email
- cart_contents (JSON)
- last_activity (datetime)
- notified (int)
- last_notified_at (datetime)
- token (varchar)
</code></pre>

    <h3>📨 Email Templates</h3>
    <p>
        Go to <strong>WooCommerce → Abandoned Carts → Emails</strong> to modify:
    </p>
    <ul>
        <li><strong>Customer Email:</strong> Sent to the buyer with restore link.</li>
        <li><strong>Admin Email:</strong> Notifies store owner about abandoned carts.</li>
    </ul>

    <p>Available placeholders in templates:</p>
    <ul>
        <li><code>{customer_email}</code> — Customer’s email address</li>
        <li><code>{store_name}</code> — Your store’s name</li>
        <li><code>{restore_link}</code> — Link to restore the abandoned cart</li>
    </ul>

    <h3>🪄 Customization Tips</h3>
    <ul>
        <li>To modify styles of the mini cart, override <code>mini-cart.php</code> in your theme.</li>
        <li>To adjust email frequency, use the <code>gbabandoned_cron_interval</code> filter.</li>
        <li>Use the plugin logger in <code>/wp-content/uploads/grintbyte-abandoned/logs/</code> for debugging.</li>
    </ul>

    <h3>🧰 Troubleshooting</h3>
    <ul>
        <li><strong>Emails not sending?</strong> Check if WP Cron is enabled.</li>
        <li><strong>No carts recorded?</strong> Ensure the checkout email field is being captured.</li>
        <li><strong>Debugging?</strong> Enable logging and check logs folder.</li>
    </ul>

    <hr />
    <p><em>Plugin Version:</em> <?php echo esc_html( GB_ABANDONED_VERSION ?? '1.0.0' ); ?></p>
    <p><em>Last Updated:</em> <?php echo esc_html( date('Y-m-d') ); ?></p>
</div>
