<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

require_visitor();

const USERNAME_PATTERN = '[\p{L}\p{N} ._\-]{3,50}';
const EMAIL_TAKEN      = 'An account with this email address already exists. Log in instead.';

$username = '';
$email    = '';
$errors   = [];

if (is_post()) {
    csrf_verify();

    $username = posted('username');
    $email    = posted('email');
    $password = posted('password', false);
    $confirm  = posted('password_confirm', false);

    if ($username === '') {
        $errors['username'] = 'Enter a username.';
    } elseif (!preg_match('/^' . USERNAME_PATTERN . '$/u', $username)) {
        $errors['username'] = 'Use 3 to 50 letters, numbers, spaces, dots, dashes or underscores.';
    }

    if ($email === '') {
        $errors['email'] = 'Enter your email address.';
    } elseif (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address, such as name@example.com.';
    }

    if ($password === '') {
        $errors['password'] = 'Choose a password.';
    } elseif (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (strlen($password) > PASSWORD_MAX_BYTES) {
        $errors['password'] = 'Use no more than ' . PASSWORD_MAX_BYTES . ' characters.';
    } elseif ($confirm !== $password) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT 1 FROM `user` WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() !== false) {
            $errors['email'] = EMAIL_TAKEN;
        }
    }

    if ($errors === []) {
        try {
            $stmt = db()->prepare(
                "INSERT INTO `user` (username, email, password_hash, role) VALUES (?, ?, ?, 'customer')"
            );
            $stmt->execute([$username, $email, hash_password($password)]);

            log_in((int) db()->lastInsertId());
            flash('success', 'Welcome, ' . $username . '. Your account is ready.');
            redirect('catalogue.php');
        } catch (PDOException $e) {
            if (!is_duplicate_key($e)) {
                throw $e;
            }
            $errors['email'] = EMAIL_TAKEN;
        }
    }
}

render_header('Register');
?>
<div class="container">
    <div class="form-card">
        <h1>Create an account</h1>
        <p class="lead">Save your configurations and order when you are ready.</p>

        <form method="post" action="<?= e(url('register.php')) ?>" data-validate>
            <?= csrf_field() ?>
            <?= input_field('username', 'Username', [
                'required'             => true,
                'minlength'            => 3,
                'maxlength'            => 50,
                'pattern'              => USERNAME_PATTERN,
                'autocomplete'         => 'username',
                'autofocus'            => true,
                'data-error-required'  => 'Enter a username.',
                'data-error-minlength' => 'Use at least 3 characters.',
                'data-error-pattern'   => 'Use letters, numbers, spaces, dots, dashes or underscores.',
            ], $username, $errors['username'] ?? '') ?>
            <?= input_field('email', 'Email address', [
                'type'                => 'email',
                'required'            => true,
                'maxlength'           => 100,
                'autocomplete'        => 'email',
                'data-error-required' => 'Enter your email address.',
                'data-error-type'     => 'Enter a valid email address, such as name@example.com.',
            ], $email, $errors['email'] ?? '') ?>
            <?= input_field('password', 'Password', [
                'type'                 => 'password',
                'required'             => true,
                'minlength'            => PASSWORD_MIN_LENGTH,
                'maxlength'            => PASSWORD_MAX_BYTES,
                'autocomplete'         => 'new-password',
                'data-error-required'  => 'Choose a password.',
                'data-error-minlength' => 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters.',
            ], '', $errors['password'] ?? '', 'At least ' . PASSWORD_MIN_LENGTH . ' characters.') ?>
            <?= input_field('password_confirm', 'Confirm password', [
                'type'                => 'password',
                'required'            => true,
                'autocomplete'        => 'new-password',
                'data-match'          => 'password',
                'data-error-required' => 'Type the password again.',
                'data-error-match'    => 'The two passwords do not match.',
            ], '', $errors['password_confirm'] ?? '') ?>
            <button type="submit" class="btn btn-primary btn-lg btn-block">Create account</button>
        </form>

        <p class="form-foot">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </div>
</div>
<?php
render_footer();
