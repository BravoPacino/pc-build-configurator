<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_customer();

$categories = categories();
$components = catalogue_components();

$byCategory = [];
$brands = [];
$highest = 0.0;
foreach ($components as $component) {
    $byCategory[$component['category_id']][] = $component;
    $brands[strtolower((string) $component['brand'])] = (string) $component['brand'];
    $highest = max($highest, (float) $component['price']);
}
ksort($brands);

render_header('Catalogue', 'catalogue');
?>
<div class="container page">
    <div class="page-head">
        <h1 class="page-title">Catalogue</h1>
        <p class="page-lead">Every part the shop sells today. Whether parts fit each other is checked in the configurator, once you put them together.</p>
    </div>

    <div class="catalogue">
        <form class="filters" data-filter-for="catalogue-results" role="search" aria-label="Filter the catalogue">
            <fieldset class="filter-group">
                <legend>Category</legend>
                <?php foreach ($categories as $category): ?>
                    <?php if (empty($byCategory[$category['category_id']])) continue; ?>
                    <label class="check">
                        <input type="checkbox" data-filter="category" value="<?= e($category['category_id']) ?>" checked>
                        <span><?= e($category['category_name']) ?></span>
                        <span class="check-count"><?= count($byCategory[$category['category_id']]) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="field">
                <label for="filter-brand">Brand</label>
                <select id="filter-brand" data-filter="brand">
                    <option value="">All brands</option>
                    <?php foreach ($brands as $key => $brand): ?>
                        <option value="<?= e($key) ?>"><?= e($brand) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <fieldset class="filter-group">
                <legend>Price (RM)</legend>
                <div class="range">
                    <input type="number" id="filter-min" data-filter="min" min="0" step="1" inputmode="numeric" placeholder="Min" aria-label="Lowest price">
                    <span aria-hidden="true">to</span>
                    <input type="number" id="filter-max" data-filter="max" min="0" step="1" inputmode="numeric" placeholder="<?= e((string) ceil($highest)) ?>" aria-label="Highest price">
                </div>
            </fieldset>

            <div class="field">
                <label for="filter-search">Search</label>
                <input type="search" id="filter-search" data-filter="search" placeholder="Name or brand, e.g. DDR5">
            </div>

            <button type="reset" class="btn btn-ghost btn-block">Clear filters</button>
        </form>

        <div class="results" id="catalogue-results">
            <p class="results-count" data-filter-count data-noun="components" aria-live="polite">Showing <?= count($components) ?> of <?= count($components) ?> components</p>

            <?php foreach ($categories as $category): ?>
                <?php $items = $byCategory[$category['category_id']] ?? []; ?>
                <?php if ($items === []) continue; ?>
                <section class="cat-section" data-filter-group aria-labelledby="cat-<?= e($category['category_id']) ?>">
                    <h2 class="cat-title" id="cat-<?= e($category['category_id']) ?>">
                        <span class="cat-icon"><?= category_icon((string) $category['category_name']) ?></span>
                        <?= e($category['category_name']) ?>
                        <span class="cat-count" data-group-count><?= count($items) ?></span>
                    </h2>
                    <ul class="parts">
                        <?php foreach ($items as $component): ?>
                            <?php [$stockLabel, $stockClass, $available] = availability($component); ?>
                            <li class="part" data-filter-item
                                data-category="<?= e($component['category_id']) ?>"
                                data-brand="<?= e(strtolower((string) $component['brand'])) ?>"
                                data-price="<?= e($component['price']) ?>"
                                data-text="<?= e(strtolower($component['name'] . ' ' . $component['brand'] . ' ' . implode(' ', component_specs($component)))) ?>">
                                <div class="part-picture"><?= component_picture($component) ?></div>
                                <div class="part-main">
                                    <h3 class="part-name"><?= e($component['name']) ?></h3>
                                    <p class="part-brand"><?= e($component['brand']) ?></p>
                                    <p class="part-specs"><?= e(implode(' · ', component_specs($component))) ?></p>
                                </div>
                                <div class="part-side">
                                    <p class="part-price"><?= e(money($component['price'])) ?></p>
                                    <span class="stock stock-<?= e($stockClass) ?>"<?= $stockClass === 'low' ? ' title="' . e($available . ' left') . '"' : '' ?>><?= e($stockLabel) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>

            <div class="empty-state" data-filter-empty hidden>
                <p>No component matches these filters.</p>
                <button type="button" class="btn btn-ghost" data-filter-reset>Clear filters</button>
            </div>
        </div>
    </div>
</div>
<?php
render_footer(['filter.js']);
