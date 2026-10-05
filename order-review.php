<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();
$userId = (int) $user['user_id'];

$order = null;
if (isset($_GET['order'])) {
    $order = find_own_order((int) (is_string($_GET['order']) ? $_GET['order'] : 0), $userId);
    if ($order === null) {
        flash('error', 'That order could not be found.');
        redirect('my-builds.php?tab=orders');
    }
    if ($order['status'] !== 'rejected') {
        flash('error', 'Order ' . order_ref((int) $order['order_id']) . ' is ' . strtolower(ORDER_STATUSES[$order['status']])
            . ': only a rejected order can be submitted again.');
        redirect('order.php?id=' . (int) $order['order_id']);
    }
    $buildId = (int) $order['build_id'];
} else {
    $buildId = (int) (is_string($_GET['build'] ?? null) ? $_GET['build'] : 0);
}

$build = find_own_build($buildId, $userId);
if ($build === null) {
    flash('error', 'That configuration could not be found.');
    redirect('my-builds.php');
}

$quote = order_quote($buildId);
$same = same_order($userId, $quote['lines'], $order !== null ? (int) $order['order_id'] : null);
$orderable = $quote['problems'] === [] && $same === null;
$earlier = $order === null && $same === null ? pending_orders_of($buildId, $userId) : [];
$ref = $order !== null ? order_ref((int) $order['order_id']) : '';
$change = 'configurator.php?build=' . $buildId . ($order !== null ? '&order=' . (int) $order['order_id'] : '');
$title = $order !== null ? 'Submit order ' . $ref . ' again' : 'Review your order';

render_header($title, $order !== null ? 'orders' : 'configurations');
?>
<div class="container page">
    <?php if ($order !== null): ?>
        <a class="back-link" href="<?= e(url('order.php?id=' . (int) $order['order_id'])) ?>">← Order <?= e($ref) ?></a>
    <?php else: ?>
        <a class="back-link" href="<?= e(url('my-builds.php')) ?>">← My configurations</a>
    <?php endif; ?>
    <div class="page-head">
        <h1 class="page-title"><?= e($title) ?></h1>
        <p class="page-lead">
            “<?= e($build['build_name']) ?>” as it is saved, at today’s prices.
            <?= $order !== null ? 'Submitting it again' : 'Placing the order' ?> reserves these parts for you until the shop reviews it.
        </p>
    </div>

    <?php if ($order !== null && (string) $order['admin_remark'] !== ''): ?>
        <div class="remark" data-remark>
            <strong>Why the shop rejected it</strong>
            <p><?= e('“' . $order['admin_remark'] . '”') ?></p>
        </div>
    <?php endif; ?>

    <?php if ($same !== null): ?>
        <div class="flash flash-error notice" role="alert" data-same>
            <p class="notice-text"><?= e(same_order_message($same)) ?></p>
            <a class="notice-link" href="<?= e(url('order.php?id=' . (int) $same['order_id'])) ?>">View order <?= e(order_ref((int) $same['order_id'])) ?></a>
            <?php if ($same['status'] !== 'rejected'): ?>
                <a class="notice-link" href="<?= e(url('consult.php?build=' . $buildId)) ?>" data-consult-link>Talk to a consultant</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($earlier !== []): ?>
        <?php $refs = implode(', ', array_map('order_ref', $earlier)); ?>
        <div class="flash flash-info notice" data-already>
            <?= e(count($earlier) === 1
                ? 'Order ' . $refs . ' was placed from an earlier version of this configuration and is still waiting for the shop. Placing this one makes a second order: cancel ' . $refs . ' if this one replaces it.'
                : 'Orders ' . $refs . ' were placed from earlier versions of this configuration and are still waiting for the shop. Placing this one makes another order: cancel them if this one replaces them.') ?>
        </div>
    <?php endif; ?>
    <?php if ($quote['problems'] !== []): ?>
        <div class="flash flash-error notice" role="alert" data-problems>
            <strong>This configuration cannot be ordered as it stands.</strong>
            <ul class="form-errors">
                <?php foreach ($quote['problems'] as $problem): ?><li><?= e($problem) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php render_quote_lines($quote, 'review-lines'); ?>
    <p class="muted table-note">These prices are recorded against the order when it is placed, so a later change of price does not alter it.</p>

    <div class="order-actions">
        <a class="btn btn-ghost" href="<?= e(url($change)) ?>">Change the configuration</a>
        <form method="post" action="<?= e(url('order-action.php')) ?>">
            <?= csrf_field() ?>
            <?php if ($order !== null): ?>
                <input type="hidden" name="action" value="resubmit">
                <input type="hidden" name="order" value="<?= (int) $order['order_id'] ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="place">
                <input type="hidden" name="build" value="<?= $buildId ?>">
                <input type="hidden" name="once" value="<?= e(order_once()) ?>">
            <?php endif; ?>
            <input type="hidden" name="reviewed" value="<?= e($quote['signature']) ?>">
            <button type="submit" class="btn btn-primary" data-place<?= $orderable ? '' : ' disabled' ?>>
                <?= e(($order !== null ? 'Submit order ' . $ref . ' again' : 'Place order') . ' · ' . money(cents_amount($quote['total_cents']))) ?>
            </button>
        </form>
    </div>
</div>
<?php
render_footer();
