<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

$status = status_filter();
$counts = order_counts();
$orders = all_orders($status);

render_header('Orders', 'orders');
?>
<div class="container page">
    <div class="page-head">
        <h1 class="page-title">Orders</h1>
        <p class="page-lead">
            <?= $counts['pending'] === 1 ? '1 order is' : $counts['pending'] . ' orders are' ?> waiting for review.
            Approving an order takes its parts out of stock; rejecting it, or the customer cancelling it, releases what it reserved.
        </p>
    </div>

    <?php if (array_sum($counts) === 0): ?>
        <div class="empty-state"><p>No order has been placed yet.</p></div>
    <?php else: ?>
        <?php render_status_filter('admin/orders.php', $status, $counts); ?>
        <?php if ($orders === []): ?>
            <div class="empty-state"><p>No order is <?= e(strtolower(ORDER_STATUSES[$status])) ?> at the moment.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" id="order-list">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Configuration</th>
                            <th scope="col" class="num">Total</th>
                            <th scope="col">Status</th>
                            <th scope="col">Placed</th>
                            <th scope="col">Status last changed</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php $id = (int) $order['order_id']; ?>
                            <tr data-order="<?= $id ?>">
                                <td class="cell-order"><a href="<?= e(url('admin/order.php?id=' . $id)) ?>"><strong><?= e(order_ref($id)) ?></strong></a></td>
                                <td class="cell-customer"><?= e($order['username']) ?><span class="muted"><?= e($order['email']) ?></span></td>
                                <td><?= e($order['build_name']) ?></td>
                                <td class="num"><?= e(money($order['total_price'])) ?></td>
                                <td><?= order_status_badge((string) $order['status']) ?></td>
                                <td><?= when_html((string) $order['created_at']) ?></td>
                                <td><?= when_html((string) $order['updated_at']) ?></td>
                                <td class="actions">
                                    <a class="btn btn-sm <?= $order['status'] === 'pending' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/order.php?id=' . $id)) ?>">
                                        <?= $order['status'] === 'pending' ? 'Review' : 'View' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
render_footer();
