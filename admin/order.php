<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

$orderId = (int) (is_string($_GET['id'] ?? null) ? $_GET['id'] : 0);
$order = find_order($orderId);
if ($order === null) {
    flash('error', 'That order could not be found.');
    redirect('admin/orders.php');
}
$ref = order_ref($orderId);
$remark = '';
$remarkError = '';

if (is_post()) {
    csrf_verify();
    switch (posted('action')) {
        case 'approve':
            try {
                applyStockTransition($orderId, 'pending', 'approved');
                flash('success', 'Order ' . $ref . ' was approved, and its parts were taken out of stock.');
            } catch (OrderRefused $e) {
                flash('error', $e->getMessage());
            }
            redirect('admin/order.php?id=' . $orderId);

        case 'reject':
            $remark = str_replace("\r\n", "\n", posted('remark'));
            if ($remark === '') {
                $remarkError = 'Write a remark: it tells the customer what to change.';
            } elseif (mb_strlen($remark) > REMARK_MAX) {
                $remarkError = 'Use no more than ' . REMARK_MAX . ' characters.';
            } else {
                try {
                    applyStockTransition($orderId, 'pending', 'rejected', $remark);
                    flash('success', 'Order ' . $ref . ' was rejected, and the parts it reserved were released.');
                } catch (OrderRefused $e) {
                    flash('error', $e->getMessage());
                }
                redirect('admin/order.php?id=' . $orderId);
            }
            break;

        default:
            flash('error', 'That action is not one this page can do.');
            redirect('admin/order.php?id=' . $orderId);
    }
}

$status = (string) $order['status'];
$pending = $status === 'pending';
$lines = order_lines($orderId);
$short = [];
foreach ($lines as $line) {
    if ((int) $line['stock_qty'] < (int) $line['quantity']) {
        $short[] = $line['name'] . ' (' . (int) $line['stock_qty'] . ' in stock, it needs ' . (int) $line['quantity'] . ')';
    }
}

$explained = [
    'pending'   => 'Waiting for your review. Its parts are reserved until you approve or reject it.',
    'approved'  => 'Approved: its parts were taken out of stock. An approved order is final.',
    'rejected'  => 'Rejected: the parts it reserved were released. The customer may revise it and submit it again, or close it.',
    'cancelled' => 'Cancelled by the customer. A cancelled order is final.',
];

render_header('Order ' . $ref, 'orders');
?>
<div class="container page">
    <a class="back-link" href="<?= e(url('admin/orders.php')) ?>">← Orders</a>
    <div class="page-head">
        <h1 class="page-title order-title">Order <?= e($ref) ?> <?= order_status_badge($status) ?></h1>
        <p class="page-lead" data-status-text><?= e($explained[$status] ?? '') ?></p>
    </div>

    <dl class="order-facts">
        <div><dt>Customer</dt><dd><?= e($order['username']) ?> <span class="muted"><?= e($order['email']) ?></span></dd></div>
        <div><dt>Configuration</dt><dd><?= e($order['build_name']) ?></dd></div>
        <div><dt>Placed</dt><dd><?= e(when_text((string) $order['created_at'])) ?></dd></div>
        <div><dt>Status last changed</dt><dd><?= e(when_text((string) $order['updated_at'])) ?></dd></div>
    </dl>

    <?php if ((string) $order['admin_remark'] !== ''): ?>
        <div class="remark" data-remark>
            <strong>Remark given when it was rejected</strong>
            <p><?= e('“' . $order['admin_remark'] . '”') ?></p>
        </div>
    <?php endif; ?>

    <?php render_order_lines($lines, (string) $order['total_price'], $pending); ?>
    <p class="muted table-note">
        The prices are those recorded when the order was placed.
        <?php if ($pending): ?>In stock counts every unit on the shelf, those reserved for this and other pending orders included.<?php endif; ?>
    </p>

    <?php if ($pending): ?>
        <div class="decisions">
            <section class="form-panel decision" aria-labelledby="approve-title">
                <h2 class="section-title" id="approve-title">Approve</h2>
                <p class="muted">Its parts are taken out of stock, and the order becomes final.</p>
                <?php if ($short !== []): ?>
                    <p class="decision-block" role="alert" data-short>Not enough in stock: <?= e(implode('; ', $short)) ?>. Add stock to approve it, or reject it.</p>
                <?php endif; ?>
                <form method="post" action="<?= e(url('admin/order.php?id=' . $orderId)) ?>"
                      data-confirm="<?= e('Its parts are taken out of stock. An approved order is final and cannot be changed.') ?>"
                      data-confirm-title="<?= e('Approve order ' . $ref . '?') ?>" data-confirm-button="Approve">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-primary" data-approve<?= $short !== [] ? ' disabled' : '' ?>>Approve order</button>
                </form>
            </section>
            <section class="form-panel decision" aria-labelledby="reject-title">
                <h2 class="section-title" id="reject-title">Reject</h2>
                <p class="muted">The parts it reserved are released. The customer reads your remark, and may revise the order and submit it again.</p>
                <form method="post" action="<?= e(url('admin/order.php?id=' . $orderId)) ?>" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <?= textarea_field('remark', 'Remark for the customer', [
                        'required' => true, 'maxlength' => REMARK_MAX, 'rows' => 3,
                        'data-error-required' => 'Write a remark: it tells the customer what to change.',
                        'data-error-maxlength' => 'Use no more than ' . REMARK_MAX . ' characters.',
                    ], $remark, $remarkError, 'For example: the power supply is out of stock, please choose another.') ?>
                    <button type="submit" class="btn btn-ghost btn-quiet-danger">Reject order</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer(['confirm.js']);
