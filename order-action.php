<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();
$userId = (int) $user['user_id'];

if (!is_post()) {
    redirect('my-builds.php?tab=orders');
}
csrf_verify();

$action = posted('action');

if ($action === 'place') {
    $buildId = (int) posted('build');
    if (find_own_build($buildId, $userId) === null) {
        flash('error', 'That configuration could not be found.');
        redirect('my-builds.php');
    }
    if (!use_order_once(posted('once'))) {
        flash('info', 'That order was already sent: it is listed under My orders.');
        redirect('my-builds.php?tab=orders');
    }
    try {
        $orderId = place_order($userId, $buildId, posted('reviewed'));
    } catch (SameOrderRefused) {
        redirect('order-review.php?build=' . $buildId);
    } catch (OrderRefused $e) {
        flash('error', $e->getMessage());
        redirect('order-review.php?build=' . $buildId);
    }
    flash('success', 'Order ' . order_ref($orderId) . ' was placed. Its parts are reserved for you until the shop reviews it.');
    redirect('order.php?id=' . $orderId);
}

$order = find_own_order((int) posted('order'), $userId);
if ($order === null) {
    flash('error', 'That order could not be found.');
    redirect('my-builds.php?tab=orders');
}
$orderId = (int) $order['order_id'];
$ref = order_ref($orderId);

$status = posted('status');
$back = posted('back') === 'list'
    ? 'my-builds.php?tab=orders' . (isset(ORDER_STATUSES[$status]) ? '&status=' . $status : '')
    : 'order.php?id=' . $orderId;

try {
    switch ($action) {
        case 'resubmit':
            resubmit_order($userId, $orderId, posted('reviewed'));
            flash('success', 'Order ' . $ref . ' was submitted again. Its parts are reserved for you until the shop reviews it.');
            redirect('order.php?id=' . $orderId);

        case 'cancel':
            applyStockTransition($orderId, 'pending', 'cancelled');
            flash('success', 'Order ' . $ref . ' was cancelled, and the parts it reserved were released.');
            redirect($back);

        case 'close':
            applyStockTransition($orderId, 'rejected', 'cancelled');
            flash('success', 'Order ' . $ref . ' was closed. Its configuration is still under My Configurations.');
            redirect($back);

        default:
            flash('error', 'That action is not one this page can do.');
            redirect($back);
    }
} catch (SameOrderRefused) {
    redirect('order-review.php?order=' . $orderId);
} catch (OrderRefused $e) {
    flash('error', $e->getMessage());
    $still = find_own_order($orderId, $userId);
    redirect($action === 'resubmit' && $still !== null && $still['status'] === 'rejected' ? 'order-review.php?order=' . $orderId : $back);
}
