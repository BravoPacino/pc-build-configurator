<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();
$userId = (int) $user['user_id'];

if (!is_post()) {
    redirect('my-builds.php');
}
csrf_verify();

$build = find_own_build((int) posted('id'), $userId);
if ($build === null) {
    flash('error', 'That configuration could not be found.');
    redirect('my-builds.php');
}
$buildId = (int) $build['build_id'];
$oldName = (string) $build['build_name'];

switch (posted('action')) {
    case 'rename':
        $name = posted('name');
        $problem = build_name_problem($name, $userId, $buildId);
        if ($problem !== '') {
            flash('error', $problem);
            break;
        }
        try {
            $stmt = db()->prepare('UPDATE build SET build_name = ?, updated_at = CURRENT_TIMESTAMP WHERE build_id = ? AND user_id = ?');
            $stmt->execute([$name, $buildId, $userId]);
            flash('success', $name === $oldName ? '“' . $name . '” keeps its name.' : '“' . $oldName . '” is now called “' . $name . '”.');
        } catch (PDOException $e) {
            if (!is_duplicate_key($e)) {
                throw $e;
            }
            flash('error', 'You already have a configuration called “' . $name . '”.');
        }
        break;

    case 'delete':
        if (build_has_orders($buildId)) {
            flash('error', '“' . $oldName . '” has been submitted as an order, so it is kept: the order still refers to it.');
            break;
        }
        $stmt = db()->prepare('DELETE FROM build WHERE build_id = ? AND user_id = ?');
        $stmt->execute([$buildId, $userId]);
        flash('success', '“' . $oldName . '” was deleted.');
        break;

    default:
        flash('error', 'That action is not one this page can do.');
}

redirect('my-builds.php');
