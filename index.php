<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = current_user();

$checks = db()->query(
    'SELECT rule_name FROM compatibility_rule WHERE is_active = 1 ORDER BY rule_id'
)->fetchAll(PDO::FETCH_COLUMN);

if ($user === null) {
    [$actionLabel, $actionPage] = ['Get started', 'register.php'];
} elseif ($user['role'] === 'admin') {
    [$actionLabel, $actionPage] = ['Go to the dashboard', 'admin/dashboard.php'];
} else {
    [$actionLabel, $actionPage] = ['Start a configuration', 'configurator.php'];
}

render_header('Home');
?>
<section class="hero">
    <div class="container">
        <div class="hero-inner">
            <span class="eyebrow">Custom-built desktop PCs</span>
            <h1>Build a PC that actually fits together</h1>
            <p>Choose your parts and the system checks them against each other as you go, so a machine that cannot work is caught before you order it, not after.</p>
            <a class="btn btn-primary btn-lg" href="<?= e(url($actionPage)) ?>"><?= e($actionLabel) ?></a>
        </div>
    </div>
</section>

<div class="container">
    <section class="features" aria-label="What the system does for you">
        <article class="feature">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.2-7 9.5C8 19.2 5 15.5 5 11V6l7-3z"/><path d="M9 11.5l2 2 4-4"/></svg>
            </div>
            <h2>Compatibility checked for you</h2>
            <p>Every choice is checked against the rules the shop maintains, before you order rather than after.</p>
        </article>
        <article class="feature">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></svg>
            </div>
            <h2>Price and power as you build</h2>
            <p>The total and the power draw update with every change, so nothing comes as a surprise.</p>
        </article>
        <article class="feature">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4V3z"/></svg>
            </div>
            <h2>Configurations you can come back to</h2>
            <p>Save a build, reopen it later, and see what its price has done since.</p>
        </article>
    </section>

    <section class="checks" aria-labelledby="checks-heading">
        <h2 id="checks-heading">What gets checked</h2>
        <?php if ($checks === []): ?>
            <p>No checks are switched on at the moment.</p>
        <?php else: ?>
            <p>The rules the shop applies to every configuration today:</p>
            <ul class="check-list">
                <?php foreach ($checks as $check): ?>
                    <li><?= e($check) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php
render_footer();
