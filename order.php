<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();

$order = find_own_order((int) (is_string($_GET['id'] ?? null) ? $_GET['id'] : 0), (int) $user['user_id']);
if ($order === null) {
    flash('error', 'That order could not be found.');
    redirect('my-builds.php?tab=orders');
}
$orderId = (int) $order['order_id'];
$ref = order_ref($orderId);
$status = (string) $order['status'];

$explained = [
    'pending'   => 'Waiting for the shop to review it. Its parts are reserved for you until then.',
    'approved'  => 'Approved by the shop. An approved order is final.',
    'rejected'  => 'Rejected by the shop. Revise the configuration and submit the order again, or close it.',
    'cancelled' => 'Cancelled. A cancelled order is final.',
];

render_header('Order ' . $ref, 'orders');
?>
<div class="container page">
    <a class="back-link" href="<?= e(url('my-builds.php?tab=orders')) ?>">← My orders</a>
    <div class="page-head">
        <h1 class="page-title order-title">Order <?= e($ref) ?> <?= order_status_badge($status) ?></h1>
        <p class="page-lead" data-status-text><?= e($explained[$status] ?? '') ?></p>
    </div>

    <dl class="order-facts">
        <div><dt>Configuration</dt><dd><?= e($order['build_name']) ?></dd></div>
        <div><dt>Placed</dt><dd><?= e(when_text((string) $order['created_at'])) ?></dd></div>
        <div><dt>Status last changed</dt><dd><?= e(when_text((string) $order['updated_at'])) ?></dd></div>
    </dl>

    <?php if ($order['admin_remark'] !== null && $order['admin_remark'] !== ''): ?>
        <div class="remark" data-remark>
            <strong>The shop’s remark when it rejected the order</strong>
            <p><?= e('“' . $order['admin_remark'] . '”') ?></p>
        </div>
    <?php endif; ?>

    <?php render_order_lines(order_lines($orderId), (string) $order['total_price']); ?>
    <p class="muted table-note">The prices are those recorded when the order was placed. A later change of price does not alter it.</p>

    <div class="order-actions">
        <?php render_order_actions($order); ?>
    </div>

    <?php render_consult_invitation((int) $order['build_id'], $orderId, $status === 'rejected'
        ? 'A consultant can help you choose what to change. Send us a note, and we will come back to you.'
        : 'Send us a note about this order, and a consultant will come back to you.'); ?>
</div>
<?php
render_footer(['confirm.js']);
