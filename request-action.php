<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();

if (!is_post()) {
    redirect('my-builds.php?tab=requests');
}
csrf_verify();

$requestId = (int) posted('request');
if (find_own_request($requestId, (int) $user['user_id']) === null) {
    flash('error', 'That request could not be found.');
    redirect('my-builds.php?tab=requests');
}

if (posted('action') === 'withdraw') {
    try {
        withdraw_request($requestId, (int) $user['user_id']);
        flash('success', 'Request ' . request_ref($requestId) . ' was withdrawn.');
    } catch (RequestRefused $e) {
        flash('error', $e->getMessage());
    }
} else {
    flash('error', 'That action is not one this page can do.');
}
redirect('my-builds.php?tab=requests');
