<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();
$userId = (int) $user['user_id'];

$tab = in_array($_GET['tab'] ?? '', ['orders', 'requests'], true) ? $_GET['tab'] : 'configurations';

function render_tabs(string $current): void
{
    $tabs = [
        'configurations' => ['Saved configurations', 'my-builds.php'],
        'orders'         => ['My orders', 'my-builds.php?tab=orders'],
        'requests'       => ['My requests', 'my-builds.php?tab=requests'],
    ];
    echo '<nav class="tabs" aria-label="Configurations, orders and requests">';
    foreach ($tabs as $key => [$label, $page]) {
        echo '<a href="' . e(url($page)) . '"' . ($key === $current ? ' class="is-current" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    echo '</nav>';
}

if ($tab === 'requests') {
    $requests = own_requests($userId);

    render_header('My requests', 'orders');
    ?>
    <div class="container page">
        <div class="page-head page-head-actions">
            <div>
                <h1 class="page-title">My requests</h1>
                <p class="page-lead">What you asked a consultant, and the replies. A request reserves nothing: to buy, order a configuration as usual.</p>
            </div>
            <a class="btn btn-primary" href="<?= e(url('consult.php')) ?>">Talk to a consultant</a>
        </div>
        <?php render_tabs('requests'); ?>

        <?php if ($requests === []): ?>
            <div class="empty-state">
                <p>You have not asked a consultant anything yet. When the configurator cannot give you what you want, a consultant can.</p>
                <a class="btn btn-primary" href="<?= e(url('consult.php')) ?>">Talk to a consultant</a>
            </div>
        <?php else: ?>
            <div class="request-list" id="request-list">
                <?php foreach ($requests as $request): ?>
                    <?php
                    $id = (int) $request['request_id'];
                    $status = (string) $request['status'];
                    $reply = (string) ($request['admin_reply'] ?? '');
                    ?>
                    <article class="request-card" data-request="<?= $id ?>">
                        <header class="request-head">
                            <strong class="request-ref"><?= e(request_ref($id)) ?></strong>
                            <?= request_status_badge($status) ?>
                            <span class="muted request-when">Sent <?= when_html((string) $request['created_at']) ?></span>
                        </header>
                        <p class="request-about">
                            Configuration:
                            <?php if ($request['build_id'] !== null): ?>
                                <a href="<?= e(url('configurator.php?build=' . (int) $request['build_id'])) ?>"><?= e($request['build_name']) ?></a>
                            <?php else: ?>
                                <span class="muted">none</span>
                            <?php endif; ?>
                            · Telephone <?= e($request['contact_phone']) ?>
                        </p>
                        <div class="request-text" data-message>
                            <span class="request-label">You wrote</span>
                            <p><?= e($request['message']) ?></p>
                        </div>
                        <?php if ($reply !== ''): ?>
                            <div class="request-text request-reply" data-reply>
                                <span class="request-label">The consultant’s reply</span>
                                <p><?= e($reply) ?></p>
                            </div>
                        <?php elseif ($status === 'new'): ?>
                            <p class="muted request-waiting" data-waiting>Waiting for a consultant. The reply appears here.</p>
                        <?php else: ?>
                            <p class="muted request-waiting">Closed by the shop.</p>
                        <?php endif; ?>
                        <?php if ($status === 'new'): ?>
                            <form method="post" action="<?= e(url('request-action.php')) ?>" class="request-actions"
                                  data-confirm="<?= e('The request is removed, and no consultant will reply to it.') ?>"
                                  data-confirm-title="<?= e('Withdraw request ' . request_ref($id) . '?') ?>"
                                  data-confirm-button="Withdraw" data-confirm-dismiss="Keep it">
                                <?= csrf_field() ?>
                                <input type="hidden" name="request" value="<?= $id ?>">
                                <input type="hidden" name="action" value="withdraw">
                                <button type="submit" class="btn btn-ghost btn-sm btn-quiet-danger">Withdraw</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    render_footer(['confirm.js']);
    exit;
}

if ($tab === 'orders') {
    $status = status_filter();
    $counts = order_counts($userId);
    $orders = own_orders($userId, $status);

    render_header('My orders', 'orders');
    ?>
    <div class="container page">
        <div class="page-head">
            <h1 class="page-title">My orders</h1>
            <p class="page-lead">Each order keeps the parts and prices it was placed with. The shop reviews every order; until then its parts are reserved for you.</p>
        </div>
        <?php render_tabs('orders'); ?>

        <?php if (array_sum($counts) === 0): ?>
            <div class="empty-state">
                <p>You have not placed an order yet. Order a valid configuration from My Configurations.</p>
                <a class="btn btn-primary" href="<?= e(url('my-builds.php')) ?>">My configurations</a>
            </div>
        <?php else: ?>
            <?php render_status_filter('my-builds.php', $status, $counts, ['tab' => 'orders']); ?>
            <?php if ($orders === []): ?>
                <div class="empty-state"><p>No order is <?= e(strtolower(ORDER_STATUSES[$status])) ?> at the moment.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table" id="order-list">
                        <thead>
                            <tr>
                                <th scope="col">Order</th>
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
                                <tr data-order="<?= $id ?>"<?= (string) $order['admin_remark'] !== '' ? ' class="has-remark"' : '' ?>>
                                    <td class="cell-order"><a href="<?= e(url('order.php?id=' . $id)) ?>"><strong><?= e(order_ref($id)) ?></strong></a></td>
                                    <td><?= e($order['build_name']) ?></td>
                                    <td class="num"><?= e(money($order['total_price'])) ?></td>
                                    <td><?= order_status_badge((string) $order['status']) ?></td>
                                    <td><?= when_html((string) $order['created_at']) ?></td>
                                    <td><?= when_html((string) $order['updated_at']) ?></td>
                                    <td class="actions">
                                        <a class="btn btn-ghost btn-sm" href="<?= e(url('order.php?id=' . $id)) ?>">View</a>
                                        <?php render_order_actions($order, $status); ?>
                                    </td>
                                </tr>
                                <?php if ((string) $order['admin_remark'] !== ''): ?>
                                    <tr class="remark-row" data-remark-for="<?= $id ?>">
                                        <td colspan="7">The shop’s remark: <?= e('“' . $order['admin_remark'] . '”') ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    render_footer(['confirm.js']);
    exit;
}

$builds = own_builds($userId);

render_header('My configurations', 'configurations');
?>
<div class="container page">
    <div class="page-head page-head-actions">
        <div>
            <h1 class="page-title">My configurations</h1>
            <p class="page-lead">The totals are as they were when each was saved. Opening one checks it again against today’s prices and rules.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('configurator.php')) ?>">New configuration</a>
    </div>
    <?php render_tabs('configurations'); ?>

    <?php if ($builds === []): ?>
        <div class="empty-state">
            <p>You have not saved a configuration yet.</p>
            <a class="btn btn-primary" href="<?= e(url('configurator.php')) ?>">Open the configurator</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" id="build-list">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col" class="num">Power</th>
                        <th scope="col">Valid</th>
                        <th scope="col">Last order</th>
                        <th scope="col">Created</th>
                        <th scope="col">Last edited</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($builds as $build): ?>
                        <?php $id = (int) $build['build_id']; ?>
                        <tr data-build="<?= $id ?>">
                            <td class="cell-build">
                                <a href="<?= e(url('configurator.php?build=' . $id)) ?>"><strong><?= e($build['build_name']) ?></strong></a>
                            </td>
                            <td class="num"><?= e(money($build['total_price'])) ?></td>
                            <td class="num"><?= (int) $build['total_wattage'] ?> W</td>
                            <td>
                                <?php if ((int) $build['is_valid'] === 1): ?>
                                    <span class="status status-on">Valid</span>
                                <?php else: ?>
                                    <span class="status status-off">Not valid</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-last-order">
                                <?php if ($build['last_order_id'] !== null): ?>
                                    <?php $orderId = (int) $build['last_order_id']; ?>
                                    <a href="<?= e(url('order.php?id=' . $orderId)) ?>"><?= e(order_ref($orderId)) ?></a>
                                    <?= order_status_badge((string) $build['last_order_status']) ?>
                                <?php else: ?>
                                    <span class="muted">Not ordered</span>
                                <?php endif; ?>
                            </td>
                            <td><?= when_html((string) $build['created_at']) ?></td>
                            <td><?= when_html((string) $build['updated_at']) ?></td>
                            <td class="actions">
                                <?php if ((int) $build['is_valid'] === 1): ?>
                                    <a class="btn btn-primary btn-sm" href="<?= e(url('order-review.php?build=' . $id)) ?>">Order</a>
                                <?php endif; ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('configurator.php?build=' . $id)) ?>">Open</a>
                                <details class="rename">
                                    <summary class="btn btn-ghost btn-sm">Rename</summary>
                                    <form class="rename-form" method="post" action="<?= e(url('build-action.php')) ?>" data-validate>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <input type="hidden" name="action" value="rename">
                                        <?= input_field('name', 'New name', [
                                            'id' => 'rename-' . $id, 'required' => true, 'maxlength' => 100, 'autocomplete' => 'off',
                                            'data-error-required' => 'Give the configuration a name.',
                                        ], (string) $build['build_name']) ?>
                                        <button type="submit" class="btn btn-primary btn-sm">Save name</button>
                                    </form>
                                </details>
                                <form method="post" action="<?= e(url('build-action.php')) ?>"
                                      data-confirm="<?= e('“' . $build['build_name'] . '” will be removed for good. Configurations already ordered are kept, since their orders refer to them.') ?>"
                                      data-confirm-title="Delete this configuration?"
                                      data-confirm-button="Delete">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-ghost btn-sm btn-quiet-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer(['confirm.js']);
