<?php

declare(strict_types=1);

function find_own_build(int $buildId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM build WHERE build_id = ? AND user_id = ?');
    $stmt->execute([$buildId, $userId]);
    return $stmt->fetch() ?: null;
}

function own_builds(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT b.*,
                (SELECT o.order_id FROM orders o WHERE o.build_id = b.build_id ORDER BY o.order_id DESC LIMIT 1) AS last_order_id,
                (SELECT o.status FROM orders o WHERE o.build_id = b.build_id ORDER BY o.order_id DESC LIMIT 1) AS last_order_status
         FROM build b
         WHERE b.user_id = ?
         ORDER BY b.updated_at DESC, b.build_id DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function build_selection(int $buildId): array
{
    $stmt = db()->prepare(
        'SELECT bi.quantity AS saved_quantity, cat.max_quantity AS category_max, c.*, cat.category_name
         FROM build_item bi
         JOIN component c ON c.component_id = bi.component_id
         JOIN category cat ON cat.category_id = c.category_id
         WHERE bi.build_id = ?
         ORDER BY cat.display_order'
    );
    $stmt->execute([$buildId]);
    $selection = [];
    $notes = [];
    foreach ($stmt->fetchAll() as $row) {
        $quantity = (int) $row['saved_quantity'];
        $max = (int) $row['category_max'];
        unset($row['saved_quantity'], $row['category_max']);
        if ((int) $row['is_active'] !== 1) {
            $notes[] = 'The ' . $row['name'] . ' saved in it is no longer sold: choose another ' . $row['category_name'] . '.';
            continue;
        }
        if ($quantity > $max) {
            $notes[] = 'At most ' . $max . ' of a ' . $row['category_name'] . ' can be chosen now, so the ' . $row['name']
                     . ' is down from ' . $quantity . ' to ' . $max . '.';
            $quantity = $max;
        }
        $selection[(int) $row['category_id']] = ['component' => $row, 'quantity' => $quantity];
    }
    return [$selection, $notes];
}

function build_name_taken(int $userId, string $name, int $exceptId = 0): bool
{
    $stmt = db()->prepare('SELECT 1 FROM build WHERE user_id = ? AND build_name = ? AND build_id <> ?');
    $stmt->execute([$userId, $name, $exceptId]);
    return $stmt->fetchColumn() !== false;
}

function build_has_orders(int $buildId): bool
{
    $stmt = db()->prepare('SELECT EXISTS (SELECT 1 FROM orders WHERE build_id = ?)');
    $stmt->execute([$buildId]);
    return (bool) $stmt->fetchColumn();
}

function build_name_problem(string $name, int $userId, int $exceptId = 0): string
{
    if ($name === '') {
        return 'Give the configuration a name.';
    }
    if (mb_strlen($name) > 100) {
        return 'Use no more than 100 characters.';
    }
    if (build_name_taken($userId, $name, $exceptId)) {
        return 'You already have a configuration called “' . $name . '”.';
    }
    return '';
}

function save_build(int $userId, ?int $buildId, string $name, array $selection, array $result): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($buildId === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO build (user_id, build_name, total_price, total_wattage, is_valid) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $name, $result['total_price'], $result['total_wattage'], $result['valid'] ? 1 : 0]);
            $buildId = (int) $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare(
                'UPDATE build SET build_name = ?, total_price = ?, total_wattage = ?, is_valid = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE build_id = ? AND user_id = ?'
            );
            $stmt->execute([$name, $result['total_price'], $result['total_wattage'], $result['valid'] ? 1 : 0, $buildId, $userId]);
            $pdo->prepare('DELETE FROM build_item WHERE build_id = ?')->execute([$buildId]);
        }
        $item = $pdo->prepare('INSERT INTO build_item (build_id, component_id, quantity) VALUES (?, ?, ?)');
        foreach ($selection as $chosen) {
            $item->execute([$buildId, (int) $chosen['component']['component_id'], $chosen['quantity']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $buildId;
}

function when_text(string $timestamp): string
{
    $time = strtotime($timestamp);
    return $time === false ? $timestamp : date('j M Y, H:i', $time);
}

function when_html(string $timestamp): string
{
    $time = strtotime($timestamp);
    if ($time === false) {
        return e($timestamp);
    }
    return '<span class="nowrap">' . e(date('j M Y', $time)) . ',</span> <span class="nowrap">' . e(date('H:i', $time)) . '</span>';
}
