<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    $root   = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $script = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($root === '' || !str_starts_with($script, $root . '/')) {
        return '';
    }
    $relative = substr($script, strlen($root));
    $path     = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $length   = strlen($path) - strlen($relative);
    return $length > 0 ? rtrim(substr($path, 0, $length), '/') : '';
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = __DIR__ . '/../assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return url('assets/' . $path) . '?v=' . $version;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function money(mixed $amount): string
{
    return 'RM ' . number_format((float) $amount, 2);
}

function send_json(array $data, int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function posted(string $name, bool $trim = true): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}
