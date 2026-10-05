<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();
$userId = (int) $user['user_id'];

$context = rule_context();
$categories = $context['categories'];
$first = (int) array_key_first($categories);
$firstName = (string) $categories[$first]['category_name'];

$candidates = [];
foreach (catalogue_components() as $part) {
    $candidates[(int) $part['category_id']][] = $part;
}

$buildId = (int) (is_post() ? posted('build') : (is_string($_GET['build'] ?? null) ? $_GET['build'] : 0));
$build = null;
if ($buildId > 0) {
    $build = find_own_build($buildId, $userId);
    if ($build === null) {
        flash('error', 'That configuration could not be found.');
        redirect('my-builds.php');
    }
}

$revising = null;
$orderId = (int) (is_post() ? posted('order') : (is_string($_GET['order'] ?? null) ? $_GET['order'] : 0));
if ($build !== null && $orderId > 0) {
    $order = find_own_order($orderId, $userId);
    if ($order !== null && $order['status'] === 'rejected' && (int) $order['build_id'] === $buildId) {
        $revising = $order;
    }
}
$again = $revising !== null ? '&order=' . (int) $revising['order_id'] : '';

$name = $build !== null ? (string) $build['build_name'] : '';
$selection = [];
$notes = [];
$problems = [];
$nameError = '';

if (is_post()) {
    csrf_verify();
    $name = posted('name');
    [$selection, $problems] = read_selection($_POST['c'] ?? [], $_POST['q'] ?? []);
    if ($problems === [] && $selection !== [] && !isset($selection[$first])) {
        $problems[] = "Choose a $firstName first: every other part is chosen to fit it.";
    }

    $action = posted('action');
    if ($action === 'save' || $action === 'order') {
        $nameError = build_name_problem($name, $userId, $buildId);
        if ($problems === [] && $selection === []) {
            $problems[] = "Choose at least a $firstName before saving.";
        }
        if ($nameError === '' && $problems === []) {
            $result = evaluate_selection($selection, $context);
            try {
                $savedId = save_build($userId, $build === null ? null : $buildId, $name, $selection, $result);
                if ($action === 'order' && $result['valid']) {
                    flash('success', '“' . $name . '” was saved. Check the order, then '
                        . ($revising !== null ? 'submit it again.' : 'place it.'));
                    redirect($revising !== null ? 'order-review.php?order=' . (int) $revising['order_id'] : 'order-review.php?build=' . $savedId);
                }
                flash('success', '“' . $name . '” was saved.'
                    . ($result['valid'] ? '' : ' It is not valid yet, so it cannot be ordered until the summary shows nothing wrong.'));
                redirect('configurator.php?build=' . $savedId . $again);
            } catch (PDOException $e) {
                if (!is_duplicate_key($e)) {
                    throw $e;
                }
                $nameError = 'You already have a configuration called “' . $name . '”.';
            }
        }
    }
} elseif ($build !== null) {
    [$selection, $notes] = build_selection($buildId);
}

$result = evaluate_selection($selection, $context);
$fit = fit_report($selection, $candidates, $context);

if ($build !== null && !is_post()) {
    $then = (int) round((float) $build['total_price'] * 100);
    $now = (int) round((float) $result['total_price'] * 100);
    if ($then !== $now) {
        $notes[] = 'Prices have changed since it was saved: it came to ' . money($build['total_price'])
                 . ' then, and comes to ' . money($result['total_price']) . ' at today’s prices.';
    }
}

$power = null;
foreach ($context['rules'] as $rule) {
    if ((int) $rule['is_active'] === 1 && $rule['rule_type'] === 'CAPACITY_CHECK' && $rule['attribute_key'] === 'wattage') {
        $power = ['slot' => (int) $rule['category_a'], 'share' => $rule['headroom_pct'] === null ? 100.0 : (float) $rule['headroom_pct']];
        break;
    }
}
$powerText = $result['total_wattage'] . ' W';
if ($power !== null && isset($selection[$power['slot']])) {
    $supply = $selection[$power['slot']];
    $powerText .= ' (safe up to ' . number_text((int) $supply['component']['wattage'] * $supply['quantity'] * $power['share'] / 100) . ' W)';
}

$reason = static fn (int $categoryId, int $partId, int $quantity): string => $fit[$categoryId][$partId][$quantity] ?? '';

$locked = !isset($selection[$first]);
$openSlot = null;
foreach ($categories as $categoryId => $category) {
    if ($locked && $categoryId !== $first) {
        break;
    }
    $chosen = $selection[$categoryId] ?? null;
    $clash = $chosen !== null && $categoryId !== $first
        && $reason($categoryId, (int) $chosen['component']['component_id'], $chosen['quantity']) !== '';
    if ($clash || ($chosen === null && (int) $category['is_required'] === 1)) {
        $openSlot = $categoryId;
        break;
    }
}

$title = $build !== null ? 'Edit “' . $build['build_name'] . '”' : 'Configurator';
render_header($build !== null ? 'Edit configuration' : 'Configurator', 'configurator');
?>
<div class="container page">
    <div class="page-head page-head-actions">
        <div>
            <h1 class="page-title"><?= e($title) ?></h1>
            <p class="page-lead">Start with the <?= e(strtolower($firstName)) ?>: every other part is then offered only if it fits. A part that does not fit is greyed out, with the reason under it.</p>
        </div>
        <?php if ($build !== null): ?>
            <a class="btn btn-ghost" href="<?= e(url('configurator.php')) ?>">Start a new configuration</a>
        <?php endif; ?>
    </div>

    <?php if ($revising !== null): ?>
        <div class="remark notice" data-revising>
            <strong>Revising order <?= e(order_ref((int) $revising['order_id'])) ?>, which the shop rejected</strong>
            <p><?= e('“' . $revising['admin_remark'] . '”') ?></p>
            <p class="muted">Save and resubmit when it is ready: the order then goes back to the shop with these parts, at today’s prices.</p>
        </div>
    <?php endif; ?>
    <?php foreach ($notes as $note): ?>
        <div class="flash flash-info notice"><?= e($note) ?></div>
    <?php endforeach; ?>
    <?php if ($problems !== []): ?>
        <div class="flash flash-error notice" role="alert">
            <ul class="form-errors">
                <?php foreach ($problems as $problem): ?><li><?= e($problem) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="configurator" method="post" action="<?= e(url('configurator.php')) ?>" data-validate
          data-configurator data-check-url="<?= e(url('api/check.php')) ?>" data-first-slot="<?= $first ?>"
          data-first-name="<?= e($firstName) ?>"
          data-fit="<?= e(json_encode((object) $fit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) ?>">
        <?= csrf_field() ?>
        <?php if ($build !== null): ?>
            <input type="hidden" name="build" value="<?= (int) $buildId ?>">
        <?php endif; ?>
        <?php if ($revising !== null): ?>
            <input type="hidden" name="order" value="<?= (int) $revising['order_id'] ?>">
        <?php endif; ?>

        <div class="configurator-grid">
            <div class="slots">
                <?php $number = 0; ?>
                <?php foreach ($categories as $categoryId => $category): ?>
                    <?php
                    $number++;
                    $categoryName = (string) $category['category_name'];
                    $max = (int) $category['max_quantity'];
                    $required = (int) $category['is_required'] === 1;
                    $chosen = $selection[$categoryId] ?? null;
                    $chosenId = $chosen !== null ? (int) $chosen['component']['component_id'] : 0;
                    $quantity = $chosen !== null ? $chosen['quantity'] : 1;
                    $slotLocked = $locked && $categoryId !== $first;
                    $clash = $chosen !== null && $categoryId !== $first ? $reason($categoryId, $chosenId, $quantity) : '';
                    if ($slotLocked) {
                        $choice = "Choose a $firstName first";
                    } elseif ($chosen !== null) {
                        $choice = $chosen['component']['name'] . ' · ' . money($chosen['component']['price']) . ($quantity > 1 ? ' × ' . $quantity : '');
                    } else {
                        $choice = 'Nothing chosen yet';
                    }
                    $classes = ['slot'];
                    if ($slotLocked) { $classes[] = 'is-locked'; }
                    if ($chosen !== null) { $classes[] = 'is-filled'; }
                    if ($clash !== '') { $classes[] = 'is-clash'; }
                    ?>
                    <section class="<?= e(implode(' ', $classes)) ?>" data-slot="<?= (int) $categoryId ?>"
                             data-category="<?= e($categoryName) ?>" data-max="<?= $max ?>" data-required="<?= $required ? 'yes' : 'no' ?>"
                             <?= isset($context['limits']['wattage'][$categoryId]) ? 'data-power-limit="yes"' : '' ?>
                             <?= $power !== null && $power['slot'] === $categoryId ? 'data-power-share="' . e(number_text($power['share'])) . '"' : '' ?>
                             <?= $max > 1 ? 'data-capacity-total="yes"' : '' ?>>
                        <details class="slot-box"<?= $openSlot === $categoryId ? ' open' : '' ?>>
                            <summary class="slot-head">
                                <span class="slot-icon"><?= category_icon($categoryName) ?></span>
                                <span class="slot-title">
                                    <span class="slot-name"><?= e($categoryName) ?></span>
                                    <span class="slot-tag">Step <?= $number ?> · <?= $required ? 'Required' : 'Only when needed' ?></span>
                                </span>
                                <span class="slot-choice" data-slot-choice><?= e($choice) ?></span>
                            </summary>
                            <div class="slot-body">
                                <?php if (empty($candidates[$categoryId])): ?>
                                    <p class="muted slot-empty">Nothing is on sale in this category at the moment.</p>
                                <?php endif; ?>
                                <ul class="options">
                                    <?php if ($categoryId !== $first && !empty($candidates[$categoryId])): ?>
                                        <li class="option option-none">
                                            <label class="option-label">
                                                <input type="radio" name="c[<?= (int) $categoryId ?>]" value=""<?= $chosen === null ? ' checked' : '' ?><?= $slotLocked ? ' disabled' : '' ?>>
                                                <span class="option-picture is-empty" aria-hidden="true"></span>
                                                <span class="option-main"><span class="option-name">None</span></span>
                                            </label>
                                        </li>
                                    <?php endif; ?>
                                    <?php foreach ($candidates[$categoryId] ?? [] as $part): ?>
                                        <?php
                                        $partId = (int) $part['component_id'];
                                        $isChosen = $partId === $chosenId;
                                        $why = $isChosen ? $clash : $reason($categoryId, $partId, 1);
                                        $unfit = !$isChosen && $why !== '' && $categoryId !== $first;
                                        $optionClasses = ['option'];
                                        if ($unfit) { $optionClasses[] = 'is-unfit'; }
                                        if ($isChosen) { $optionClasses[] = 'is-chosen'; }
                                        if ($isChosen && $why !== '') { $optionClasses[] = 'is-clash'; }
                                        ?>
                                        <li class="<?= e(implode(' ', $optionClasses)) ?>" data-part="<?= $partId ?>"
                                            data-name="<?= e($part['name']) ?>" data-price="<?= e($part['price']) ?>"
                                            data-wattage="<?= (int) $part['wattage'] ?>" data-capacity="<?= (int) ($part['capacity_gb'] ?? 0) ?>">
                                            <label class="option-label">
                                                <input type="radio" name="c[<?= (int) $categoryId ?>]" value="<?= $partId ?>"<?= $isChosen ? ' checked' : '' ?><?= $slotLocked || $unfit ? ' disabled' : '' ?>>
                                                <span class="option-picture"><?= component_picture($part) ?></span>
                                                <span class="option-main">
                                                    <span class="option-name"><?= e($part['name']) ?></span>
                                                    <span class="option-meta"><?= implode(' · ', array_map(
                                                        static fn (string $fact): string => '<span>' . e($fact) . '</span>',
                                                        [$part['brand'], ...component_specs($part)]
                                                    )) ?></span>
                                                    <span class="option-reason" data-reason<?= $why === '' ? ' hidden' : '' ?>><?= $why === '' ? '' : e('(' . $why . ')') ?></span>
                                                </span>
                                                <span class="option-price"><?= e(money($part['price'])) ?></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($max > 1 && !empty($candidates[$categoryId])): ?>
                                    <div class="field slot-quantity">
                                        <label for="q-<?= (int) $categoryId ?>">How many</label>
                                        <select id="q-<?= (int) $categoryId ?>" name="q[<?= (int) $categoryId ?>]" data-quantity<?= $slotLocked || $chosen === null ? ' disabled' : '' ?>>
                                            <?php for ($n = 1; $n <= $max; $n++): ?>
                                                <?php $tooMany = $chosen !== null && $n !== $quantity ? $reason($categoryId, $chosenId, $n) : ''; ?>
                                                <option value="<?= $n ?>"<?= $n === $quantity ? ' selected' : '' ?><?= $tooMany !== '' ? ' disabled' : '' ?>><?= e('× ' . $n . ($tooMany !== '' ? ' (' . $tooMany . ')' : '')) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                    </section>
                <?php endforeach; ?>
            </div>

            <aside class="build-summary" aria-labelledby="summary-title">
                <h2 class="summary-title" id="summary-title">Summary</h2>
                <dl class="summary-totals" data-totals>
                    <div><dt>Total price</dt><dd data-total="price"><?= e(money($result['total_price'])) ?></dd></div>
                    <div><dt>Power</dt><dd data-total="power"><?= e($powerText) ?></dd></div>
                    <?php foreach ($categories as $categoryId => $category): ?>
                        <?php if ((int) $category['max_quantity'] > 1): ?>
                            <?php $total = $result['capacity'][$category['category_name']] ?? 0; ?>
                            <div><dt><?= e($category['category_name']) ?> in total</dt><dd data-total="capacity-<?= (int) $categoryId ?>"><?= $total > 0 ? e(capacity_text((int) $total)) : '–' ?></dd></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
                <div class="summary-verdicts" data-verdicts aria-live="polite">
                    <?= render_verdicts($result, false) ?>
                </div>
                <div class="summary-save">
                    <?= input_field('name', 'Name this configuration', [
                        'required' => true, 'maxlength' => 100, 'autocomplete' => 'off',
                        'data-error-required' => 'Give the configuration a name.',
                    ], $name, $nameError, 'Saved configurations are listed under My Configurations.') ?>
                    <div class="form-actions">
                        <button type="submit" name="action" value="check" class="btn btn-ghost" formnovalidate data-no-script>Check</button>
                        <button type="submit" name="action" value="save" class="btn btn-ghost"><?= $build !== null ? 'Save changes' : 'Save configuration' ?></button>
                        <?php ?>
                        <button type="submit" name="action" value="order" class="btn btn-primary" data-order-button<?= $result['valid'] ? '' : ' disabled' ?>>
                            <?= e($revising !== null ? 'Save and resubmit ' . order_ref((int) $revising['order_id']) : 'Save and order') ?>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </form>

    <?php render_consult_invitation($build !== null ? $buildId : null, null, $build !== null
        ? 'Send it to us with a note, and a consultant will come back to you.'
        : 'Save it first and the consultant sees the parts you chose, or just send us a note.'); ?>
</div>
<?php
render_footer(['configurator.js']);
