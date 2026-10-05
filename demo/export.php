<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/catalogue.php';
require __DIR__ . '/../includes/rules.php';

echo json_encode([
    'categories' => categories(),
    'rules'      => all_rules(),
    'components' => catalogue_components(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), "\n";
