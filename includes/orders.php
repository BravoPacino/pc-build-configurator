<?php

declare(strict_types=1);

const ORDER_STATUSES = [
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'cancelled' => 'Cancelled',
];

const ORDER_TRANSITIONS = [
    'new'      => ['pending' => 'reserve'],
    'pending'  => ['approved' => 'deduct', 'rejected' => 'release', 'cancelled' => 'release'],
    'rejected' => ['pending' => 'reserve', 'cancelled' => 'none'],
];

const ORDER_STANDING = ['pending', 'approved', 'rejected'];

const REMARK_MAX = 255;

class OrderRefused extends RuntimeException
{
}

final class SameOrderRefused extends OrderRefused
{
}

function order_ref(int $orderId): string
{
    return '#' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
}

function order_status_badge(string $status): string
{
    return '<span class="status status-' . e($status) . '">' . e(ORDER_STATUSES[$status] ?? $status) . '</span>';
}

function amount_cents(mixed $amount): int
{
    return (int) round((float) $amount * 100);
}

function cents_amount(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

function moved_on(int $orderId, ?string $now, string $expected): string
{
    if ($now === null) {
        return 'Order ' . order_ref($orderId) . ' could not be found.';
    }
    return 'Order ' . order_ref($orderId) . ' is ' . strtolower(ORDER_STATUSES[$now] ?? $now) . ' now, not '
         . strtolower(ORDER_STATUSES[$expected] ?? $expected) . ', so nothing was changed.';
}

function applyStockTransition(int $orderId, ?string $from, string $to, ?string $remark = null): void
{
    $move = ORDER_TRANSITIONS[$from ?? 'new'][$to] ?? null;
    if ($move === null) {
        throw new LogicException('An order cannot move from ' . ($from ?? 'new') . ' to ' . $to . '.');
    }
    $remark = trim((string) $remark);
    if ($to === 'rejected' && $remark === '') {
        throw new LogicException('A rejection needs a remark.');
    }

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($from === null && $own) {
        throw new LogicException('An order is placed inside the transaction that inserts it.');
    }
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        if ($from !== null) {
            $set = 'status = ?, updated_at = CURRENT_TIMESTAMP';
            $values = [$to];
            if ($to === 'rejected') {
                $set .= ', admin_remark = ?';
                $values[] = $remark;
            } elseif ($to === 'pending') {
                $set .= ', admin_remark = NULL';
            }
            $stmt = $pdo->prepare("UPDATE orders SET $set WHERE order_id = ? AND status = ?");
            $stmt->execute([...$values, $orderId, $from]);
            if ($stmt->rowCount() !== 1) {
                $now = $pdo->prepare('SELECT status FROM orders WHERE order_id = ?' . for_update());
                $now->execute([$orderId]);
                throw new OrderRefused(moved_on($orderId, $now->fetchColumn() ?: null, $from));
            }
        }

        if ($move !== 'none') {
            $stmt = $pdo->prepare(
                'SELECT oi.component_id, oi.quantity, c.name, c.stock_qty, c.reserved_qty
                 FROM order_item oi JOIN component c ON c.component_id = oi.component_id
                 WHERE oi.order_id = ?
                 ORDER BY oi.component_id' . for_update()
            );
            $stmt->execute([$orderId]);
            $lines = $stmt->fetchAll();
            if ($lines === []) {
                throw new LogicException('Order ' . order_ref($orderId) . ' holds no parts.');
            }

            $short = [];
            foreach ($lines as $line) {
                $n = (int) $line['quantity'];
                $stock = (int) $line['stock_qty'];
                $reserved = (int) $line['reserved_qty'];
                if ($move === 'reserve') {
                    if ($stock - $reserved < $n) {
                        $short[] = $line['name'] . ' (' . max(0, $stock - $reserved) . ' available, it needs ' . $n . ')';
                    }
                    continue;
                }
                if ($reserved < $n) {
                    throw new OrderRefused('The reserved quantity of the ' . $line['name'] . ' does not add up: ' . $reserved
                        . ' reserved in all, but this order alone holds ' . $n . '. Nothing was changed.');
                }
                if ($move === 'deduct' && $stock < $n) {
                    $short[] = $line['name'] . ' (' . $stock . ' in stock, it needs ' . $n . ')';
                }
            }
            if ($short !== []) {
                throw new OrderRefused($move === 'deduct'
                    ? 'Not enough in stock to approve order ' . order_ref($orderId) . ': ' . implode('; ', $short) . '. It stays pending.'
                    : 'Not enough in stock for this order: ' . implode('; ', $short) . '. Nothing was reserved.');
            }

            $update = $pdo->prepare(match ($move) {
                'reserve' => 'UPDATE component SET reserved_qty = reserved_qty + ? WHERE component_id = ?',
                'release' => 'UPDATE component SET reserved_qty = reserved_qty - ? WHERE component_id = ?',
                'deduct'  => 'UPDATE component SET reserved_qty = reserved_qty - ?, stock_qty = stock_qty - ? WHERE component_id = ?',
            });
            foreach ($lines as $line) {
                $n = (int) $line['quantity'];
                $update->execute($move === 'deduct' ? [$n, $n, $line['component_id']] : [$n, $line['component_id']]);
            }
        }

        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function order_quote(int $buildId): array
{
    [$selection, $problems] = build_selection($buildId);
    $result = evaluate_selection($selection);
    $lines = [];
    $total = 0;
    foreach ($selection as $item) {
        $part = $item['component'];
        $n = $item['quantity'];
        $unit = amount_cents($part['price']);
        $available = max(0, (int) $part['stock_qty'] - (int) $part['reserved_qty']);
        $lines[] = ['component' => $part, 'quantity' => $n, 'unit_cents' => $unit, 'available' => $available];
        $total += $unit * $n;
        if ($available < $n) {
            $problems[] = $available === 0
                ? 'The ' . $part['name'] . ' is out of stock at the moment.'
                : 'Only ' . $available . ' of the ' . $part['name'] . ' can be ordered at the moment, and this configuration holds ' . $n . '.';
        }
    }
    if ($result['missing'] !== []) {
        $problems[] = 'It is not complete: it still needs ' . implode(', ', $result['missing']) . '.';
    }
    foreach ($result['rules'] as $verdict) {
        if ($verdict['status'] === 'fail' || $verdict['status'] === 'error') {
            $problems[] = trim($verdict['message'] . ' ' . $verdict['detail']);
        }
    }
    $signature = hash('sha256', implode('|', array_map(
        static fn (array $line): string => $line['component']['component_id'] . ':' . $line['quantity'] . ':' . $line['unit_cents'],
        $lines
    )));
    return ['lines' => $lines, 'total_cents' => $total, 'problems' => $problems, 'signature' => $signature];
}

function order_once(): string
{
    $once = bin2hex(random_bytes(16));
    $_SESSION['order_once'][$once] = true;
    $_SESSION['order_once'] = array_slice($_SESSION['order_once'], -20, null, true);
    return $once;
}

function use_order_once(string $once): bool
{
    if ($once === '' || !isset($_SESSION['order_once'][$once])) {
        return false;
    }
    unset($_SESSION['order_once'][$once]);
    return true;
}

function refuse_unless_orderable(array $quote, string $reviewed): void
{
    if (!hash_equals($quote['signature'], $reviewed)) {
        throw new OrderRefused('The configuration or a price changed while you were reviewing the order, so nothing was ordered. Here it is as it stands now.');
    }
    if ($quote['problems'] !== []) {
        throw new OrderRefused(implode(' ', $quote['problems']));
    }
}

function same_order(int $userId, array $lines, ?int $except = null): ?array
{
    $wanted = [];
    foreach ($lines as $line) {
        $wanted[(int) $line['component']['component_id']] = (int) $line['quantity'];
    }
    ksort($wanted);

    $stmt = db()->prepare(
        'SELECT o.order_id, o.status, oi.component_id, oi.quantity
         FROM orders o JOIN order_item oi ON oi.order_id = o.order_id
         WHERE o.user_id = ? AND o.order_id <> ? AND o.status IN (' . implode(', ', array_fill(0, count(ORDER_STANDING), '?')) . ')
         ORDER BY o.order_id DESC'
    );
    $stmt->execute([$userId, $except ?? 0, ...ORDER_STANDING]);
    $orders = [];
    foreach ($stmt->fetchAll() as $row) {
        $orders[(int) $row['order_id']]['status'] = (string) $row['status'];
        $orders[(int) $row['order_id']]['parts'][(int) $row['component_id']] = (int) $row['quantity'];
    }
    foreach ($orders as $orderId => $order) {
        ksort($order['parts']);
        if ($order['parts'] === $wanted) {
            return ['order_id' => $orderId, 'status' => $order['status']];
        }
    }
    return null;
}

function same_order_message(array $same): string
{
    $ref = order_ref((int) $same['order_id']);
    $once = ' The same parts are ordered once; for a second set, talk to a consultant or call the shop on ' . SHOP_PHONE . '.';
    return match ($same['status']) {
        'pending'  => 'You already have an order for these exact parts: order ' . $ref . ', waiting for the shop.' . $once,
        'approved' => 'You already have an order for these exact parts: order ' . $ref . ', approved by the shop.' . $once,
        default    => 'You already have an order for these exact parts: order ' . $ref . ', rejected by the shop. Revise and resubmit it, or close it first.',
    };
}

function lock_customer(int $userId): void
{
    db()->prepare('SELECT user_id FROM `user` WHERE user_id = ?' . for_update())->execute([$userId]);
}

function refuse_same_order(int $userId, array $lines, ?int $except = null): void
{
    $same = same_order($userId, $lines, $except);
    if ($same !== null) {
        throw new SameOrderRefused(same_order_message($same));
    }
}

function write_order_lines(int $orderId, array $lines): void
{
    $stmt = db()->prepare('INSERT INTO order_item (order_id, component_id, quantity, unit_price_snapshot) VALUES (?, ?, ?, ?)');
    foreach ($lines as $line) {
        $stmt->execute([$orderId, (int) $line['component']['component_id'], $line['quantity'], cents_amount($line['unit_cents'])]);
    }
}

function place_order(int $userId, int $buildId, string $reviewed): int
{
    if (find_own_build($buildId, $userId) === null) {
        throw new OrderRefused('That configuration could not be found.');
    }
    $quote = order_quote($buildId);
    refuse_unless_orderable($quote, $reviewed);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        lock_customer($userId);
        refuse_same_order($userId, $quote['lines']);
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, build_id, status, total_price) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$userId, $buildId, cents_amount($quote['total_cents'])]);
        $orderId = (int) $pdo->lastInsertId();
        write_order_lines($orderId, $quote['lines']);
        applyStockTransition($orderId, null, 'pending');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $orderId;
}

function resubmit_order(int $userId, int $orderId, string $reviewed): void
{
    $order = find_own_order($orderId, $userId);
    if ($order === null || $order['status'] !== 'rejected') {
        throw new OrderRefused(moved_on($orderId, $order['status'] ?? null, 'rejected'));
    }
    $quote = order_quote((int) $order['build_id']);
    refuse_unless_orderable($quote, $reviewed);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        lock_customer($userId);
        refuse_same_order($userId, $quote['lines'], $orderId);
        $pdo->prepare('DELETE FROM order_item WHERE order_id = ?')->execute([$orderId]);
        write_order_lines($orderId, $quote['lines']);
        $pdo->prepare('UPDATE orders SET total_price = ? WHERE order_id = ?')->execute([cents_amount($quote['total_cents']), $orderId]);
        applyStockTransition($orderId, 'rejected', 'pending');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

const ORDER_COLUMNS = 'SELECT o.*, b.build_name, u.username, u.email
     FROM orders o
     JOIN build b ON b.build_id = o.build_id
     JOIN `user` u ON u.user_id = o.user_id';

function find_order(int $orderId): ?array
{
    $stmt = db()->prepare(ORDER_COLUMNS . ' WHERE o.order_id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetch() ?: null;
}

function find_own_order(int $orderId, int $userId): ?array
{
    $stmt = db()->prepare(ORDER_COLUMNS . ' WHERE o.order_id = ? AND o.user_id = ?');
    $stmt->execute([$orderId, $userId]);
    return $stmt->fetch() ?: null;
}

function pending_orders_of(int $buildId, int $userId): array
{
    $stmt = db()->prepare("SELECT order_id FROM orders WHERE build_id = ? AND user_id = ? AND status = 'pending' ORDER BY order_id");
    $stmt->execute([$buildId, $userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function own_orders(int $userId, string $status = ''): array
{
    $stmt = db()->prepare(ORDER_COLUMNS . ' WHERE o.user_id = ?' . ($status !== '' ? ' AND o.status = ?' : '')
        . ' ORDER BY o.created_at DESC, o.order_id DESC');
    $stmt->execute($status !== '' ? [$userId, $status] : [$userId]);
    return $stmt->fetchAll();
}

function all_orders(string $status = ''): array
{
    $stmt = db()->prepare(ORDER_COLUMNS . ($status !== '' ? ' WHERE o.status = ?' : '')
        . " ORDER BY CASE WHEN o.status = 'pending' THEN 0 ELSE 1 END,
                   CASE WHEN o.status = 'pending' THEN o.updated_at END ASC,
                   CASE WHEN o.status = 'pending' THEN o.order_id END ASC,
                   o.updated_at DESC, o.order_id DESC");
    $stmt->execute($status !== '' ? [$status] : []);
    return $stmt->fetchAll();
}

function order_counts(?int $userId = null): array
{
    $stmt = db()->prepare('SELECT status, COUNT(*) FROM orders' . ($userId !== null ? ' WHERE user_id = ?' : '') . ' GROUP BY status');
    $stmt->execute($userId !== null ? [$userId] : []);
    $counts = array_fill_keys(array_keys(ORDER_STATUSES), 0);
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $status => $count) {
        $counts[$status] = (int) $count;
    }
    return $counts;
}

function order_lines(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT oi.quantity, oi.unit_price_snapshot, c.*, cat.category_name
         FROM order_item oi
         JOIN component c ON c.component_id = oi.component_id
         JOIN category cat ON cat.category_id = c.category_id
         WHERE oi.order_id = ?
         ORDER BY cat.display_order, c.name'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function status_filter(array $statuses = ORDER_STATUSES): string
{
    $status = $_GET['status'] ?? '';
    return is_string($status) && isset($statuses[$status]) ? $status : '';
}

function render_status_filter(string $action, string $current, array $counts, array $keep = [], array $statuses = ORDER_STATUSES): void
{
    $options = ['' => 'All (' . array_sum($counts) . ')'];
    foreach ($statuses as $status => $label) {
        $options[$status] = $label . ' (' . $counts[$status] . ')';
    }
    ?>
    <form class="filter-bar order-filter" method="get" action="<?= e(url($action)) ?>">
        <?php foreach ($keep as $name => $value): ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>
        <?= select_field('status', 'Status', $options, $current) ?>
        <button type="submit" class="btn btn-ghost">Show</button>
    </form>
    <?php
}

function render_order_actions(array $order, ?string $listFilter = null): void
{
    $id = (int) $order['order_id'];
    $ref = order_ref($id);
    $size = $listFilter !== null ? ' btn-sm' : '';
    $fields = static function (string $action) use ($id, $listFilter): string {
        return csrf_field()
            . '<input type="hidden" name="order" value="' . $id . '">'
            . '<input type="hidden" name="action" value="' . e($action) . '">'
            . ($listFilter !== null
                ? '<input type="hidden" name="back" value="list"><input type="hidden" name="status" value="' . e($listFilter) . '">'
                : '');
    };
    if ($order['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('order-action.php')) ?>"
              data-confirm="<?= e('The parts it reserved are released, and a cancelled order cannot be reopened.') ?>"
              data-confirm-title="<?= e('Cancel order ' . $ref . '?') ?>"
              data-confirm-button="Cancel order" data-confirm-dismiss="Keep the order">
            <?= $fields('cancel') ?>
            <button type="submit" class="btn btn-ghost btn-quiet-danger<?= $size ?>"><?= $listFilter !== null ? 'Cancel' : 'Cancel order' ?></button>
        </form>
    <?php elseif ($order['status'] === 'rejected'): ?>
        <a class="btn btn-primary<?= $size ?>" href="<?= e(url('order-review.php?order=' . $id)) ?>"><?= $listFilter !== null ? 'Revise &amp; resubmit' : 'Revise and resubmit' ?></a>
        <form method="post" action="<?= e(url('order-action.php')) ?>"
              data-confirm="<?= e('It is cancelled for good. Its configuration stays under My Configurations.') ?>"
              data-confirm-title="<?= e('Close order ' . $ref . '?') ?>"
              data-confirm-button="Close order" data-confirm-dismiss="Keep the order">
            <?= $fields('close') ?>
            <button type="submit" class="btn btn-ghost btn-quiet-danger<?= $size ?>"><?= $listFilter !== null ? 'Close' : 'Close order' ?></button>
        </form>
    <?php endif;
}

function part_cell(array $part): string
{
    return '<div class="cell-part">'
         . '<span class="cell-picture">' . component_picture($part) . '</span>'
         . '<span><strong>' . e($part['name']) . '</strong>'
         . '<span class="muted">' . e($part['category_name'] . ' · ' . $part['brand']) . '</span></span>'
         . '</div>';
}

function render_order_lines(array $lines, string $total, bool $stock = false): void
{
    ?>
    <div class="table-wrap">
        <table class="data-table order-lines">
            <thead>
                <tr>
                    <th scope="col">Part</th>
                    <th scope="col" class="num">Quantity</th>
                    <th scope="col" class="num">Unit price</th>
                    <th scope="col" class="num">Amount</th>
                    <?php if ($stock): ?><th scope="col" class="num">In stock</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <?php
                    $n = (int) $line['quantity'];
                    $short = $stock && (int) $line['stock_qty'] < $n;
                    ?>
                    <tr data-line="<?= (int) $line['component_id'] ?>"<?= $short ? ' class="is-short"' : '' ?>>
                        <td class="line-part"><?= part_cell($line) ?></td>
                        <td class="num line-qty">× <?= $n ?></td>
                        <td class="num line-unit"><?= e(money($line['unit_price_snapshot'])) ?></td>
                        <td class="num line-amount"><?= e(money(cents_amount(amount_cents($line['unit_price_snapshot']) * $n))) ?></td>
                        <?php if ($stock): ?>
                            <td class="num line-extra" data-stock><span class="line-extra-label">In stock: </span><?= (int) $line['stock_qty'] ?><?= $short ? ' <span class="short-note">(needs ' . $n . ')</span>' : '' ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="3">Total</th>
                    <td class="num" data-order-total><?= e(money($total)) ?></td>
                    <?php if ($stock): ?><td></td><?php endif; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php
}

function render_quote_lines(array $quote, string $tableId): void
{
    ?>
    <div class="table-wrap">
        <table class="data-table order-lines" id="<?= e($tableId) ?>">
            <thead>
                <tr>
                    <th scope="col">Part</th>
                    <th scope="col" class="num">Quantity</th>
                    <th scope="col" class="num">Unit price</th>
                    <th scope="col" class="num">Amount</th>
                    <th scope="col">Availability</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quote['lines'] as $line): ?>
                    <?php
                    $part = $line['component'];
                    $n = $line['quantity'];
                    $enough = $line['available'] >= $n;
                    ?>
                    <tr data-line="<?= (int) $part['component_id'] ?>"<?= $enough ? '' : ' class="is-short"' ?>>
                        <td class="line-part"><?= part_cell($part) ?></td>
                        <td class="num line-qty">× <?= $n ?></td>
                        <td class="num line-unit"><?= e(money(cents_amount($line['unit_cents']))) ?></td>
                        <td class="num line-amount"><?= e(money(cents_amount($line['unit_cents'] * $n))) ?></td>
                        <td class="line-extra" data-available>
                            <?php if (!$enough): ?>
                                <span class="short-note"><?= $line['available'] === 0 ? 'Out of stock' : 'Only ' . $line['available'] . ' available' ?></span>
                            <?php else: ?>
                                <?php [$label, $modifier] = availability($part); ?>
                                <span class="stock stock-<?= e($modifier) ?>"><?= e($label) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="3">Total</th>
                    <td class="num" data-order-total><?= e(money(cents_amount($quote['total_cents']))) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php
}
