<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

if (!is_post()) {
    redirect('admin/rules.php');
}

csrf_verify();

$rule = find_rule((int) posted('id'));
$action = posted('action');

if ($rule === null) {
    flash('error', 'That rule no longer exists.');
} elseif ($action === 'activate' || $action === 'deactivate') {
    $on = $action === 'activate' ? 1 : 0;
    $stmt = db()->prepare('UPDATE compatibility_rule SET is_active = ? WHERE rule_id = ?');
    $stmt->execute([$on, $rule['rule_id']]);
    flash('success', $on === 1
        ? '“' . $rule['rule_name'] . '” is switched on again.'
        : '“' . $rule['rule_name'] . '” was switched off. Configurations are no longer checked against it.');
} elseif ($action === 'delete') {
    $stmt = db()->prepare('DELETE FROM compatibility_rule WHERE rule_id = ?');
    $stmt->execute([$rule['rule_id']]);
    flash('success', '“' . $rule['rule_name'] . '” was deleted.');
} else {
    flash('error', 'Nothing was changed: the action was not recognised.');
}

redirect('admin/rules.php');
