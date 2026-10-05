<?php

declare(strict_types=1);

function nav_items(?array $user): array
{
    if ($user === null) {
        return [];
    }
    if ($user['role'] === 'admin') {
        return [
            'dashboard'  => ['Dashboard',  'admin/dashboard.php'],
            'components' => ['Components', 'admin/components.php'],
            'rules'      => ['Rules',      'admin/rules.php'],
            'orders'     => ['Orders',     'admin/orders.php'],
            'quotations' => ['Quotations', 'admin/quotations.php'],
        ];
    }
    return [
        'catalogue'      => ['Catalogue',         'catalogue.php'],
        'configurator'   => ['Configurator',      'configurator.php'],
        'configurations' => ['My Configurations', 'my-builds.php'],
        'orders'         => ['My Orders',         'my-builds.php?tab=orders'],
    ];
}

function render_header(string $title, string $current = ''): void
{
    $user = current_user();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(SHOP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container header-bar">
        <a class="brand" href="<?= e(url(home_for($user))) ?>">
            <span class="brand-mark" aria-hidden="true"></span>
            <span><?= e(SHOP_NAME) ?></span>
        </a>
        <?php if ($user === null): ?>
            <div class="header-actions visitor-actions">
                <a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">Log in</a>
                <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">Register</a>
            </div>
        <?php else: ?>
            <nav class="main-nav" aria-label="Main">
                <?php foreach (nav_items($user) as $key => [$label, $page]): ?>
                    <a href="<?= e(url($page)) ?>"<?= $key === $current ? ' class="is-current" aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="header-actions">
                <span class="user-chip" title="<?= e($user['email']) ?>">
                    <span class="user-name"><?= e($user['username']) ?></span>
                    <span class="role-tag"><?= $user['role'] === 'admin' ? 'Admin' : 'Customer' ?></span>
                </span>
                <form method="post" action="<?= e(url('logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost">Log out</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>
<main id="main" class="site-main">
    <?php render_flashes(); ?>
<?php
}

function render_flashes(): void
{
    $messages = take_flashes();
    if ($messages === []) {
        return;
    }
    echo '<div class="container flash-stack">';
    foreach ($messages as $message) {
        $type = in_array($message['type'], ['success', 'error', 'info'], true) ? $message['type'] : 'info';
        $role = $type === 'error' ? 'alert' : 'status';
        echo '<div class="flash flash-' . $type . '" role="' . $role . '">' . e($message['message']) . '</div>';
    }
    echo '</div>';
}

function render_footer(array $scripts = []): void
{
    ?>
</main>
<footer class="site-footer">
    <div class="container footer-bar">
        <strong><?= e(SHOP_NAME) ?></strong>
        <span><?= e(SHOP_ADDRESS) ?></span>
        <span><?= e(SHOP_PHONE) ?></span>
        <span><?= e(SHOP_HOURS) ?></span>
    </div>
</footer>
<script src="<?= e(asset('js/validate.js')) ?>" defer></script>
<?php foreach ($scripts as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

function render_failure(Throwable $e): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    error_log((string) $e);

    [$heading, $advice] = explain_failure($e);
    $stylesheet = defined('BASE_URL') ? asset('css/style.css') : '';
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($heading) ?></title>
    <?php if ($stylesheet !== ''): ?><link rel="stylesheet" href="<?= e($stylesheet) ?>"><?php endif; ?>
</head>
<body>
<main class="site-main">
    <div class="container page narrow">
        <div class="card">
            <h1 class="page-title"><?= e($heading) ?></h1>
            <p><?= e($advice) ?></p>
            <?php if (APP_DEBUG): ?>
                <pre class="failure-detail"><?= e(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) ?></pre>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
<?php
}

function explain_failure(Throwable $e): array
{
    if ($e instanceof PDOException) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 0 && preg_match('/\[(\d{4})\]/', $e->getMessage(), $match)) {
            $code = (int) $match[1];
        }
        switch ($code) {
            case 2002:
                return ['The database is not running',
                        'Start MySQL in the XAMPP Control Panel, then reload this page.'];
            case 1049:
                return ['The database has not been created yet',
                        'In phpMyAdmin, import sql/schema.sql and then sql/components.sql, then reload this page.'];
            case 1045:
                return ['The database refused the login',
                        'Check DB_USER and DB_PASS in includes/config.php.'];
            case 1146:
                return ['The database is incomplete',
                        'A table is missing. In phpMyAdmin, import sql/schema.sql and then sql/components.sql again. This replaces everything in the database.'];
        }
    }
    return ['Something went wrong',
            'The page could not be completed. Please go back and try again.'];
}
