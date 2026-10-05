<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_visitor();

$email = '';
$error = '';

if (is_post()) {
    csrf_verify();

    $email    = posted('email');
    $password = posted('password', false);

    if ($email === '' || $password === '') {
        $error = 'Enter your email address and your password.';
    } else {
        $stmt = db()->prepare('SELECT user_id, username, password_hash, role FROM `user` WHERE email = ?');
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account !== false && password_verify($password, $account['password_hash'])) {
            log_in((int) $account['user_id']);
            flash('success', 'Welcome back, ' . $account['username'] . '.');

            $target = take_page_after_login() ?? url(home_for($account));
            header('Location: ' . $target, true, 303);
            exit;
        }

        $error = 'The email address or password is incorrect.';
    }
}

render_header('Log in');
?>
<div class="container">
    <div class="form-card">
        <h1>Log in</h1>
        <p class="lead">Welcome back. Log in to carry on with your configurations.</p>

        <?php if ($error !== ''): ?>
            <div class="flash flash-error form-alert" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" data-validate>
            <?= csrf_field() ?>
            <?= input_field('email', 'Email address', [
                'type'                => 'email',
                'required'            => true,
                'maxlength'           => 100,
                'autocomplete'        => 'email',
                'autofocus'           => $email === '',
                'data-error-required' => 'Enter your email address.',
            ], $email) ?>
            <?= input_field('password', 'Password', [
                'type'                => 'password',
                'required'            => true,
                'autocomplete'        => 'current-password',
                'autofocus'           => $email !== '',
                'data-error-required' => 'Enter your password.',
            ]) ?>
            <button type="submit" class="btn btn-primary btn-lg btn-block">Log in</button>
        </form>

        <p class="form-foot">No account yet? <a href="<?= e(url('register.php')) ?>">Register</a></p>
    </div>
</div>
<?php
render_footer();
