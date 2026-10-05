<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

const HEADROOM_PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';

const RULE_FORM = [
    'ATTRIBUTE_MATCH' => [
        'a' => 'First part', 'b' => 'Second part', 'attribute' => 'Both must have the same',
        'second' => true, 'headroom' => false,
    ],
    'CAPACITY_CHECK' => [
        'a' => 'Part that sets the limit', 'b' => '', 'attribute' => 'Figure added up',
        'second' => false, 'headroom' => true,
    ],
    'REQUIRES_CATEGORY' => [
        'a' => 'Part that is looked at', 'b' => 'Then the build needs a', 'attribute' => 'When it has no',
        'second' => true, 'headroom' => false,
    ],
];

$categories = categories_by_id();

$id = is_post() ? (int) posted('id') : (int) (is_string($_GET['id'] ?? null) ? $_GET['id'] : 0);
$existing = null;
if ($id > 0) {
    $existing = find_rule($id);
    if ($existing === null) {
        flash('error', 'That rule no longer exists.');
        redirect('admin/rules.php');
    }
}
$editing = $existing !== null;

$values = [
    'rule_name'     => $editing ? (string) $existing['rule_name'] : '',
    'rule_type'     => $editing ? (string) $existing['rule_type'] : '',
    'category_a'    => $editing ? (string) $existing['category_a'] : '',
    'category_b'    => $editing ? (string) ($existing['category_b'] ?? '') : '',
    'attribute_key' => $editing ? (string) $existing['attribute_key'] : '',
    'headroom_pct'  => $editing && $existing['headroom_pct'] !== null
        ? rtrim(rtrim(number_format((float) $existing['headroom_pct'], 2, '.', ''), '0'), '.') : '',
    'error_message' => $editing ? (string) $existing['error_message'] : '',
    'is_active'     => $editing ? (string) $existing['is_active'] : '1',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (array_keys($values) as $field) {
        $values[$field] = posted($field);
    }
    $values['is_active'] = $values['is_active'] === '1' ? '1' : '0';

    if ($values['rule_name'] === '') {
        $errors['rule_name'] = 'Enter a name for the rule.';
    } elseif (mb_strlen($values['rule_name']) > 100) {
        $errors['rule_name'] = 'Use no more than 100 characters.';
    }

    $type = $values['rule_type'];
    $form = RULE_FORM[$type] ?? null;
    if ($form === null) {
        $errors['rule_type'] = 'Choose what kind of rule this is.';
    }

    $a = $categories[(int) $values['category_a']] ?? null;
    if ($a === null) {
        $errors['category_a'] = 'Choose a category.';
    }

    $b = null;
    if ($form !== null && $form['second']) {
        $b = $categories[(int) $values['category_b']] ?? null;
        if ($b === null) {
            $errors['category_b'] = 'Choose a category.';
        } elseif ($a !== null && (int) $a['category_id'] === (int) $b['category_id']) {
            $errors['category_b'] = 'Choose a category other than the first.';
        }
    } else {
        $values['category_b'] = '';
    }

    $key = $values['attribute_key'];
    if ($form !== null) {
        if (!in_array($key, RULE_ATTRIBUTES[$type], true)) {
            $errors['attribute_key'] = 'Choose what the rule checks.';
        } else {
            $readFrom = $type === 'ATTRIBUTE_MATCH' ? ['category_a' => $a, 'category_b' => $b] : ['category_a' => $a];
            foreach ($readFrom as $field => $category) {
                if ($category !== null && !isset($errors[$field]) && !isset(category_fields($category['category_name'])[$key])) {
                    $errors[$field] = $category['category_name'] . ' parts do not record ' . ATTRIBUTE_LABELS[$key] . '.';
                }
            }
        }
    }

    if ($form !== null && $form['headroom']) {
        $share = $values['headroom_pct'];
        if ($share !== '' && (!preg_match(HEADROOM_PATTERN, $share) || (float) $share <= 0 || (float) $share > 100)) {
            $errors['headroom_pct'] = 'Enter a share above 0 and up to 100, with at most two decimal places, such as 80.';
        }
    } else {
        $values['headroom_pct'] = '';
    }

    if ($values['error_message'] === '') {
        $errors['error_message'] = 'Enter the message a customer sees when the rule fails.';
    } elseif (mb_strlen($values['error_message']) > 255) {
        $errors['error_message'] = 'Use no more than 255 characters.';
    }

    if ($errors === []) {
        $mine = [(int) $a['category_id'], $b === null ? null : (int) $b['category_id']];
        foreach (all_rules() as $other) {
            if ((int) $other['rule_id'] === $id || $other['rule_type'] !== $type || $other['attribute_key'] !== $key) {
                continue;
            }
            $theirs = [(int) $other['category_a'], $other['category_b'] === null ? null : (int) $other['category_b']];
            if ($theirs === $mine || ($type === 'ATTRIBUTE_MATCH' && $theirs === array_reverse($mine))) {
                $errors['category_a'] = 'The rule “' . $other['rule_name'] . '” already checks exactly this.';
                break;
            }
        }
    }

    if ($errors === []) {
        $row = [
            $values['rule_name'],
            $type,
            (int) $a['category_id'],
            $b === null ? null : (int) $b['category_id'],
            $key,
            $values['headroom_pct'] === '' ? null : $values['headroom_pct'],
            $values['error_message'],
            (int) $values['is_active'],
        ];
        if ($editing) {
            $stmt = db()->prepare(
                'UPDATE compatibility_rule SET rule_name = ?, rule_type = ?, category_a = ?, category_b = ?, attribute_key = ?,
                        headroom_pct = ?, error_message = ?, is_active = ?
                 WHERE rule_id = ?'
            );
            $stmt->execute([...$row, $id]);
            flash('success', '“' . $values['rule_name'] . '” was saved. It applies from the next check.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO compatibility_rule (rule_name, rule_type, category_a, category_b, attribute_key, headroom_pct, error_message, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute($row);
            flash('success', '“' . $values['rule_name'] . '” was added. It applies from the next check.');
        }
        redirect('admin/rules.php');
    }
}

$form = RULE_FORM[$values['rule_type']] ?? null;

$typeOptions = [];
foreach (RULE_TYPES as $value => $label) {
    $typeOptions[$value] = ['label' => $label, 'attributes' => [
        'data-label-a'         => RULE_FORM[$value]['a'],
        'data-label-b'         => RULE_FORM[$value]['b'],
        'data-label-attribute' => RULE_FORM[$value]['attribute'],
        'data-second'          => RULE_FORM[$value]['second'] ? 'yes' : 'no',
        'data-headroom'        => RULE_FORM[$value]['headroom'] ? 'yes' : 'no',
    ]];
}
$categoryOptions = [];
foreach ($categories as $categoryId => $category) {
    $categoryOptions[$categoryId] = $category['category_name'];
}
$attributeOptions = [];
foreach (RULE_ATTRIBUTES as $ruleType => $keys) {
    foreach ($keys as $key) {
        $attributeOptions[$key] = ['label' => ucfirst(ATTRIBUTE_LABELS[$key]), 'attributes' => ['data-type' => $ruleType]];
    }
}
$showSecond = $form === null || $form['second'];
$showHeadroom = $form === null || $form['headroom'];

$title = $editing ? 'Edit rule' : 'Add a rule';
render_header($title, 'rules');
?>
<div class="container page">
    <a class="back-link" href="<?= e(url('admin/rules.php')) ?>">← All rules</a>
    <div class="page-head">
        <h1 class="page-title"><?= e($title) ?></h1>
        <p class="page-lead">A rule is a row in the database, not code: once saved it applies to every configuration from the next check. Choose the kind first; the form then asks only for what that kind needs.</p>
    </div>

    <form class="form-panel" method="post" action="<?= e(url('admin/rule-form.php')) ?>" data-validate>
        <?= csrf_field() ?>
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
        <?php endif; ?>

        <div class="form-grid">
            <?= input_field('rule_name', 'Name', [
                'required' => true, 'maxlength' => 100, 'data-error-required' => 'Enter a name for the rule.',
            ], $values['rule_name'], $errors['rule_name'] ?? '', 'Shown in this list, and to visitors on the landing page.', ['class' => 'span-2']) ?>

            <?= select_field('rule_type', 'Kind of rule', $typeOptions, $values['rule_type'], 'Choose a kind of rule', [
                'required' => true, 'data-error-required' => 'Choose what kind of rule this is.',
            ], $errors['rule_type'] ?? '', '', ['class' => 'span-2']) ?>

            <?= select_field('category_a', $form['a'] ?? 'First part', $categoryOptions, $values['category_a'], 'Choose a category', [
                'required' => true, 'data-error-required' => 'Choose a category.',
            ], $errors['category_a'] ?? '') ?>

            <?= select_field('attribute_key', $form['attribute'] ?? 'What it checks', $attributeOptions, $values['attribute_key'], 'Choose', [
                'required' => true, 'data-error-required' => 'Choose what the rule checks.',
            ], $errors['attribute_key'] ?? '') ?>

            <?= select_field('category_b', $form['b'] ?? 'Second part', $categoryOptions, $values['category_b'], 'Choose a category', [
                'required' => true, 'disabled' => !$showSecond, 'data-error-required' => 'Choose a category.',
            ], $errors['category_b'] ?? '', '', ['data-rule-field' => 'second', 'hidden' => !$showSecond]) ?>

            <?= input_field('headroom_pct', 'Share of the limit a build may use (%)', [
                'type' => 'number', 'min' => '0.01', 'max' => 100, 'step' => '0.01', 'inputmode' => 'decimal',
                'disabled' => !$showHeadroom,
                'data-error-min' => 'Enter a share above 0.', 'data-error-max' => 'The share cannot be more than 100.',
                'data-error-step' => 'Use at most two decimal places.',
            ], $values['headroom_pct'], $errors['headroom_pct'] ?? '', 'Leave empty to allow the whole of it. 80 is the usual planning ceiling for a power supply.', ['data-rule-field' => 'headroom', 'hidden' => !$showHeadroom]) ?>

            <?= input_field('error_message', 'Message when it fails', [
                'required' => true, 'maxlength' => 255, 'data-error-required' => 'Enter the message a customer sees when the rule fails.',
            ], $values['error_message'], $errors['error_message'] ?? '', 'What a customer reads when a configuration breaks this rule.', ['class' => 'span-2']) ?>

            <div class="field span-2">
                <label class="check">
                    <input type="checkbox" id="f-is_active" name="is_active" value="1"<?= $values['is_active'] === '1' ? ' checked' : '' ?>>
                    <span>Switched on: configurations are checked against it</span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-ghost" href="<?= e(url('admin/rules.php')) ?>">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add rule' ?></button>
        </div>
    </form>
</div>
<?php
render_footer(['rule-form.js']);
