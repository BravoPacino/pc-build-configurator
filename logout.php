<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}

csrf_verify();
log_out();
flash('success', 'You have been logged out.');
redirect('index.php');
