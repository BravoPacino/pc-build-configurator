<?php

declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (isset($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT user_id, username, email, role FROM `user` WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']);
            }
        }
    }

    return $user;
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function log_in(int $userId): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['user_id'] = $userId;
}

function log_out(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function home_for(?array $user): string
{
    if ($user === null) {
        return 'index.php';
    }
    return $user['role'] === 'admin' ? 'admin/dashboard.php' : 'catalogue.php';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        if (!is_post()) {
            $_SESSION['after_login'] = (string) ($_SERVER['REQUEST_URI'] ?? '');
        }
        flash('info', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    return require_role('admin', 'That page is for administrators only.');
}

function require_customer(): array
{
    return require_role('customer', 'That page is for customers only.');
}

function require_role(string $role, string $refusal): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        flash('error', $refusal);
        redirect(home_for($user));
    }
    return $user;
}

function require_login_json(): array
{
    $user = current_user();
    if ($user === null) {
        send_json(['error' => 'Please log in to continue.'], 401);
    }
    return $user;
}

function require_visitor(): void
{
    $user = current_user();
    if ($user !== null) {
        redirect(home_for($user));
    }
}

function take_page_after_login(): ?string
{
    $target = $_SESSION['after_login'] ?? '';
    unset($_SESSION['after_login']);

    $inside = is_string($target)
        && str_starts_with($target, BASE_URL . '/')
        && !str_starts_with($target, '//');

    return $inside ? $target : null;
}
