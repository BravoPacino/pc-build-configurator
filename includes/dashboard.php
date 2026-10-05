<?php

declare(strict_types=1);

function dashboard_figures(): array
{
    $count = static function (string $sql, array $values = []): int {
        $stmt = db()->prepare($sql);
        $stmt->execute($values);
        return (int) $stmt->fetchColumn();
    };
    $monthStart = substr((string) db()->query('SELECT CURRENT_TIMESTAMP')->fetchColumn(), 0, 7) . '-01 00:00:00';
    return [
        'components' => $count('SELECT COUNT(*) FROM component WHERE is_active = 1'),
        'customers'  => $count("SELECT COUNT(*) FROM `user` WHERE role = 'customer'"),
        'pending'    => $count("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
        'approved'   => $count("SELECT COUNT(*) FROM orders WHERE status = 'approved' AND updated_at >= ?", [$monthStart]),
        'builds'     => $count('SELECT COUNT(*) FROM build'),
        'unanswered' => $count("SELECT COUNT(*) FROM quotation_request WHERE status = 'new'"),
    ];
}

function low_stock(): array
{
    $stmt = db()->prepare(
        'SELECT c.component_id, c.name, c.stock_qty, c.reserved_qty, c.stock_qty - c.reserved_qty AS available, cat.category_name
         FROM component c
         JOIN category cat ON cat.category_id = c.category_id
         WHERE c.is_active = 1 AND c.stock_qty - c.reserved_qty < ?
         ORDER BY available, cat.display_order, c.name'
    );
    $stmt->execute([LOW_STOCK_BELOW]);
    return $stmt->fetchAll();
}

function reservation_mismatches(): array
{
    return db()->query(
        "SELECT c.component_id, c.name, cat.category_name, c.reserved_qty,
                COALESCE(SUM(CASE WHEN o.status = 'pending' THEN oi.quantity END), 0) AS actual_reserved
         FROM component c
         JOIN category cat ON cat.category_id = c.category_id
         LEFT JOIN order_item oi ON oi.component_id = c.component_id
         LEFT JOIN orders o ON o.order_id = oi.order_id
         GROUP BY c.component_id, c.name, cat.category_name, cat.display_order, c.reserved_qty
         HAVING c.reserved_qty <> actual_reserved
         ORDER BY cat.display_order, c.name"
    )->fetchAll();
}
