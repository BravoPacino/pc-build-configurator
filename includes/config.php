<?php

declare(strict_types=1);

function env(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

define('DB_DSN',  env('DB_DSN',  'mysql:host=localhost;dbname=pc_build_configurator;charset=utf8mb4'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

define('APP_DEBUG', in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));

define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_BYTES', 72);

define('SHOP_NAME',    'PC Build Configurator');
define('SHOP_ADDRESS', 'Penang, Malaysia');
define('SHOP_PHONE',   '+60 4-000 0000');
define('SHOP_HOURS',   'Monday to Saturday, 10am to 8pm');
