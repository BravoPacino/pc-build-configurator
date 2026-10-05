<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

$categories = categories();
$components = all_components();

$brands = [];
$inactive = 0;
foreach ($components as $component) {
    $brands[strtolower((string) $component['brand'])] = (string) $component['brand'];
    if ((int) $component['is_active'] === 0) {
        $inactive++;
    }
}
ksort($brands);

$categoryOptions = [];
foreach ($categories as $category) {
    $categoryOptions[$category['category_id']] = $category['category_name'];
}

render_header('Components', 'components');
?>
<div class="container page">
    <div class="page-head page-head-actions">
        <div>
            <h1 class="page-title">Components</h1>
            <p class="page-lead"><?= count($components) ?> components, <?= $inactive ?> deactivated. Deactivated components are hidden from customers but stay on the orders that include them.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('admin/component-form.php')) ?>">Add component</a>
    </div>

    <form class="filter-bar" data-filter-for="component-list" role="search" aria-label="Filter the components">
        <div class="field">
            <label for="filter-category">Category</label>
            <select id="filter-category" data-filter="category">
                <option value="">All categories</option>
                <?php foreach ($categoryOptions as $id => $name): ?>
                    <option value="<?= e($id) ?>"><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="filter-brand">Brand</label>
            <select id="filter-brand" data-filter="brand">
                <option value="">All brands</option>
                <?php foreach ($brands as $key => $brand): ?>
                    <option value="<?= e($key) ?>"><?= e($brand) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="filter-status">Status</label>
            <select id="filter-status" data-filter="status">
                <option value="">Active and deactivated</option>
                <option value="active">Active</option>
                <option value="inactive">Deactivated</option>
            </select>
        </div>
        <div class="field field-grow">
            <label for="filter-search">Search</label>
            <input type="search" id="filter-search" data-filter="search" placeholder="Name or brand">
        </div>
        <button type="reset" class="btn btn-ghost">Clear</button>
    </form>

    <div id="component-list">
        <p class="results-count" data-filter-count data-noun="components" aria-live="polite">Showing <?= count($components) ?> of <?= count($components) ?> components</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Component</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="num">Price</th>
                        <th scope="col" class="num">Stock</th>
                        <th scope="col" class="num">Reserved</th>
                        <th scope="col" class="num">Available</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($components as $component): ?>
                        <?php
                        $active = (int) $component['is_active'] === 1;
                        [, $stockClass, $available] = availability($component);
                        ?>
                        <tr class="<?= $active ? '' : 'is-inactive' ?>" data-filter-item
                            data-category="<?= e($component['category_id']) ?>"
                            data-brand="<?= e(strtolower((string) $component['brand'])) ?>"
                            data-price="<?= e($component['price']) ?>"
                            data-status="<?= $active ? 'active' : 'inactive' ?>"
                            data-text="<?= e(strtolower($component['name'] . ' ' . $component['brand'])) ?>">
                            <td>
                                <div class="cell-part">
                                    <span class="cell-picture"><?= component_picture($component) ?></span>
                                    <span>
                                        <strong><?= e($component['name']) ?></strong>
                                        <span class="muted"><?= e($component['brand']) ?></span>
                                    </span>
                                </div>
                            </td>
                            <td><?= e($component['category_name']) ?></td>
                            <td class="num"><?= e(money($component['price'])) ?></td>
                            <td class="num"><?= (int) $component['stock_qty'] ?></td>
                            <td class="num"><?= (int) $component['reserved_qty'] ?></td>
                            <td class="num"><span class="stock-num stock-<?= e($stockClass) ?>"><?= $available ?></span></td>
                            <td><span class="status <?= $active ? 'status-on' : 'status-off' ?>"><?= $active ? 'Active' : 'Deactivated' ?></span></td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/component-form.php?id=' . (int) $component['component_id'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('admin/component-status.php')) ?>"
                                    <?php if ($active): ?>
                                      data-confirm="<?= e('“' . $component['name'] . '” will disappear from the catalogue and the configurator. Orders already placed keep it, and you can reactivate it at any time.') ?>"
                                      data-confirm-title="Deactivate this component?"
                                      data-confirm-button="Deactivate"
                                    <?php endif; ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $component['component_id'] ?>">
                                    <input type="hidden" name="action" value="<?= $active ? 'deactivate' : 'reactivate' ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm<?= $active ? ' btn-quiet-danger' : '' ?>"><?= $active ? 'Deactivate' : 'Reactivate' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="empty-state" data-filter-empty hidden>
            <p>No component matches these filters.</p>
            <button type="button" class="btn btn-ghost" data-filter-reset>Clear filters</button>
        </div>
    </div>
</div>
<?php
render_footer(['filter.js', 'confirm.js']);
