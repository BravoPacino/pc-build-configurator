<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

if (!is_post()) {
    redirect('admin/components.php');
}

csrf_verify();

$component = find_component((int) posted('id'));
$action = posted('action');

if ($component === null) {
    flash('error', 'That component no longer exists.');
} elseif ($action !== 'deactivate' && $action !== 'reactivate') {
    flash('error', 'Nothing was changed: the action was not recognised.');
} else {
    $active = $action === 'reactivate' ? 1 : 0;
    $stmt = db()->prepare('UPDATE component SET is_active = ? WHERE component_id = ?');
    $stmt->execute([$active, $component['component_id']]);
    flash('success', $active === 1
        ? '“' . $component['name'] . '” is on sale again.'
        : '“' . $component['name'] . '” was deactivated. Customers no longer see it.');
}

redirect('admin/components.php');
