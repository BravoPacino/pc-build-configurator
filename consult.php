<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = require_customer();
$userId = (int) $user['user_id'];

$choices = [];
foreach (own_builds($userId) as $build) {
    $choices[(string) $build['build_id']] = (string) $build['build_name'];
}

$buildChoice = '';
$phone = last_phone($userId);
$message = '';
$errors = ['build' => '', 'phone' => '', 'message' => ''];
$back = 'my-builds.php?tab=requests';

if (is_post()) {
    csrf_verify();
    $buildChoice = posted('build');
    $phone = posted('phone');
    $message = str_replace("\r\n", "\n", posted('message'));
    if ($buildChoice !== '' && !isset($choices[$buildChoice])) {
        $errors['build'] = 'Choose one of your own configurations, or none.';
        $buildChoice = '';
    }
    $errors['phone'] = phone_problem($phone);
    $errors['message'] = request_text_problem($message, 'Tell the consultant what you are looking for.');
    if (array_filter($errors) === []) {
        $requestId = create_request($userId, $buildChoice !== '' ? (int) $buildChoice : null, $phone, $message);
        flash('success', 'Request ' . request_ref($requestId) . ' was sent. A consultant replies here, and may call you on ' . $phone . '.');
        redirect('my-builds.php?tab=requests');
    }
} else {
    $asked = is_string($_GET['build'] ?? null) ? $_GET['build'] : '';
    if (isset($choices[$asked])) {
        $buildChoice = $asked;
        $back = 'configurator.php?build=' . $asked;
    }
    $order = find_own_order((int) (is_string($_GET['order'] ?? null) ? $_GET['order'] : 0), $userId);
    if ($order !== null) {
        $message = 'About order ' . order_ref((int) $order['order_id']) . ': ';
        $back = 'order.php?id=' . (int) $order['order_id'];
        if ($buildChoice === '' && isset($choices[(string) $order['build_id']])) {
            $buildChoice = (string) $order['build_id'];
        }
    }
}

render_header('Talk to a consultant', 'orders');
?>
<div class="container page">
    <a class="back-link" href="<?= e(url($back)) ?>">← Back</a>
    <div class="page-head">
        <h1 class="page-title">Talk to a consultant</h1>
        <p class="page-lead">
            Tell us what the configurator could not give you: a part we do not list, a second set of parts you ordered before,
            a budget to work to. A consultant replies under My Requests, and may call you. A request reserves nothing.
        </p>
    </div>

    <form class="form-panel consult-form" method="post" action="<?= e(url('consult.php')) ?>" data-validate>
        <?= csrf_field() ?>
        <?= select_field('build', 'Configuration', $choices, $buildChoice, 'None', [], $errors['build'],
            $choices === [] ? 'You have not saved a configuration: describe what you are after in the message.'
                            : 'The consultant sees its parts as they are saved, at today’s prices.') ?>
        <?= input_field('phone', 'Telephone', [
            'type' => 'tel', 'required' => true, 'maxlength' => PHONE_MAX, 'pattern' => PHONE_PATTERN, 'autocomplete' => 'tel',
            'data-error-required' => 'Give a number the consultant can call you on.',
            'data-error-pattern' => 'Enter a telephone number: 7 to 15 digits, with spaces or dashes if you like.',
            'data-error-maxlength' => 'Use no more than ' . PHONE_MAX . ' characters.',
        ], $phone, $errors['phone'], 'For example +60 12-345 6789.') ?>
        <?= textarea_field('message', 'Message', [
            'required' => true, 'maxlength' => REQUEST_TEXT_MAX, 'rows' => 6,
            'data-error-required' => 'Tell the consultant what you are looking for.',
            'data-error-maxlength' => 'Use no more than ' . REQUEST_TEXT_MAX . ' characters.',
        ], $message, $errors['message']) ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Send request</button>
            <a class="btn btn-ghost" href="<?= e(url($back)) ?>">Cancel</a>
        </div>
    </form>
    <p class="muted consult-call">Or call the shop on <?= e(SHOP_PHONE) ?> (<?= e(SHOP_HOURS) ?>).</p>
</div>
<?php
render_footer();
