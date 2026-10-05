<?php

declare(strict_types=1);

ob_start();

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/session.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/forms.php';
require __DIR__ . '/icons.php';
require __DIR__ . '/catalogue.php';
require __DIR__ . '/rules.php';
require __DIR__ . '/builds.php';
require __DIR__ . '/orders.php';
require __DIR__ . '/requests.php';
require __DIR__ . '/dashboard.php';
require __DIR__ . '/layout.php';

set_exception_handler('render_failure');

define('BASE_URL', base_url());

start_session();
