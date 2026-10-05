<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

$rules = all_rules();
$categories = categories();
$byCategory = [];
foreach (catalogue_components() as $component) {
    $byCategory[(int) $component['category_id']][] = $component;
}

$chosen = is_array($_GET['c'] ?? null) ? $_GET['c'] : [];
$quantities = is_array($_GET['q'] ?? null) ? $_GET['q'] : [];
$verdicts = '';
$problems = [];
if (array_filter($chosen, fn ($id): bool => $id !== '') !== []) {
    [$selection, $problems] = read_selection($chosen, $quantities);
    if ($problems === []) {
        $verdicts = render_verdicts(evaluate_selection($selection));
    }
}

$switchedOn = count(array_filter($rules, fn (array $rule): bool => (int) $rule['is_active'] === 1));

render_header('Rules', 'rules');
?>
<div class="container page">
    <div class="page-head page-head-actions">
        <div>
            <h1 class="page-title">Rules</h1>
            <p class="page-lead"><?= count($rules) ?> rules, <?= $switchedOn ?> switched on. Every configuration is checked against the rules that are switched on, and a change here applies at the next check, with no change to the code.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('admin/rule-form.php')) ?>">Add rule</a>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="rule-list">
            <thead>
                <tr>
                    <th scope="col">Rule</th>
                    <th scope="col">Kind</th>
                    <th scope="col">What it checks</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                    <?php
                    $on = (int) $rule['is_active'] === 1;
                    $broken = !in_array($rule['attribute_key'], RULE_ATTRIBUTES[$rule['rule_type']] ?? [], true);
                    ?>
                    <tr class="<?= $on ? '' : 'is-inactive' ?>" data-rule="<?= (int) $rule['rule_id'] ?>">
                        <td class="cell-rule">
                            <strong><?= e($rule['rule_name']) ?></strong>
                            <span class="muted"><?= e($rule['error_message']) ?></span>
                        </td>
                        <td><?= e(RULE_TYPES[$rule['rule_type']] ?? $rule['rule_type']) ?></td>
                        <td><?= e(rule_summary($rule)) ?></td>
                        <td>
                            <span class="status <?= $on ? 'status-on' : 'status-off' ?>"><?= $on ? 'Switched on' : 'Switched off' ?></span>
                            <?php if ($broken): ?>
                                <span class="status status-broken" title="It names something a rule of this kind cannot check. Edit it to fix it.">Broken</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/rule-form.php?id=' . (int) $rule['rule_id'])) ?>">Edit</a>
                            <form method="post" action="<?= e(url('admin/rule-action.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $rule['rule_id'] ?>">
                                <input type="hidden" name="action" value="<?= $on ? 'deactivate' : 'activate' ?>">
                                <button type="submit" class="btn btn-ghost btn-sm"><?= $on ? 'Switch off' : 'Switch on' ?></button>
                            </form>
                            <form method="post" action="<?= e(url('admin/rule-action.php')) ?>"
                                  data-confirm="<?= e('“' . $rule['rule_name'] . '” will be removed for good, and configurations will no longer be checked against it. To stop it for a while instead, switch it off.') ?>"
                                  data-confirm-title="Delete this rule?"
                                  data-confirm-button="Delete">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $rule['rule_id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-ghost btn-sm btn-quiet-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($rules === []): ?>
        <div class="empty-state"><p>There are no rules. Every complete configuration is valid until one is added.</p></div>
    <?php endif; ?>

    <section class="tester" id="tester" aria-labelledby="tester-title">
        <h2 class="section-title" id="tester-title">Try the rules</h2>
        <p class="page-lead">Choose parts as a customer would. The verdicts update as you choose, using the rules exactly as they stand above; nothing is saved.</p>

        <div class="tester-grid">
            <form class="form-panel tester-form" method="get" action="<?= e(url('admin/rules.php')) ?>#tester"
                  data-rule-tester data-check-url="<?= e(url('api/check.php')) ?>">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $id = (int) $category['category_id'];
                    $items = $byCategory[$id] ?? [];
                    $max = (int) $category['max_quantity'];
                    $picked = is_string($chosen[$id] ?? null) ? $chosen[$id] : '';
                    $count = is_string($quantities[$id] ?? null) ? $quantities[$id] : '1';
                    ?>
                    <div class="field tester-slot">
                        <label for="t-<?= $id ?>"><?= e($category['category_name']) ?><?php if ((int) $category['is_required'] !== 1): ?> <span class="muted">(only when needed)</span><?php endif; ?></label>
                        <div class="tester-pick">
                            <select id="t-<?= $id ?>" name="c[<?= $id ?>]">
                                <option value=""><?= $items === [] ? 'Nothing on sale yet' : 'None' ?></option>
                                <?php foreach ($items as $component): ?>
                                    <option value="<?= (int) $component['component_id'] ?>"<?= (string) $component['component_id'] === $picked ? ' selected' : '' ?>>
                                        <?= e($component['name'] . ' · ' . money($component['price'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($max > 1): ?>
                                <select name="q[<?= $id ?>]" aria-label="How many <?= e($category['category_name']) ?>" class="tester-count">
                                    <?php for ($n = 1; $n <= $max; $n++): ?>
                                        <option value="<?= $n ?>"<?= (string) $n === $count ? ' selected' : '' ?>>× <?= $n ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="form-actions">
                    <a class="btn btn-ghost" href="<?= e(url('admin/rules.php')) ?>#tester" data-tester-clear>Clear</a>
                    <button type="submit" class="btn btn-primary">Check</button>
                </div>
            </form>

            <div class="tester-results" data-tester-results aria-live="polite">
                <?php if ($problems !== []): ?>
                    <ul class="form-errors">
                        <?php foreach ($problems as $problem): ?><li><?= e($problem) ?></li><?php endforeach; ?>
                    </ul>
                <?php elseif ($verdicts !== ''): ?>
                    <?= $verdicts ?>
                <?php else: ?>
                    <p class="tester-empty">Choose a part to see how each rule judges it.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php
render_footer(['confirm.js', 'rule-tester.js']);
