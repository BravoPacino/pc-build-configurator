<?php

declare(strict_types=1);

const REQUEST_STATUSES = [
    'new'      => 'New',
    'answered' => 'Answered',
    'closed'   => 'Closed',
];

const REQUEST_TEXT_MAX = 2000;

const PHONE_MAX = 20;

const PHONE_PATTERN = '\+?(?:[ \-\(\)]*[0-9]){7,15}[ \-\(\)]*';

final class RequestRefused extends RuntimeException
{
}

function request_ref(int $requestId): string
{
    return 'Q-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
}

function request_status_badge(string $status): string
{
    return '<span class="status status-' . e($status) . '">' . e(REQUEST_STATUSES[$status] ?? $status) . '</span>';
}

function phone_problem(string $phone): string
{
    if ($phone === '') {
        return 'Give a number the consultant can call you on.';
    }
    if (mb_strlen($phone) > PHONE_MAX) {
        return 'Use no more than ' . PHONE_MAX . ' characters.';
    }
    if (preg_match('/^' . PHONE_PATTERN . '$/', $phone) !== 1) {
        return 'Enter a telephone number: 7 to 15 digits, with spaces or dashes if you like.';
    }
    return '';
}

function request_text_problem(string $text, string $empty): string
{
    if ($text === '') {
        return $empty;
    }
    if (mb_strlen($text) > REQUEST_TEXT_MAX) {
        return 'Use no more than ' . REQUEST_TEXT_MAX . ' characters.';
    }
    return '';
}

const REQUEST_COLUMNS = 'SELECT r.*, u.username, u.email, b.build_name
     FROM quotation_request r
     JOIN `user` u ON u.user_id = r.user_id
     LEFT JOIN build b ON b.build_id = r.build_id';

function find_request(int $requestId): ?array
{
    $stmt = db()->prepare(REQUEST_COLUMNS . ' WHERE r.request_id = ?');
    $stmt->execute([$requestId]);
    return $stmt->fetch() ?: null;
}

function find_own_request(int $requestId, int $userId): ?array
{
    $stmt = db()->prepare(REQUEST_COLUMNS . ' WHERE r.request_id = ? AND r.user_id = ?');
    $stmt->execute([$requestId, $userId]);
    return $stmt->fetch() ?: null;
}

function own_requests(int $userId): array
{
    $stmt = db()->prepare(REQUEST_COLUMNS . ' WHERE r.user_id = ? ORDER BY r.created_at DESC, r.request_id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function all_requests(string $status = ''): array
{
    $stmt = db()->prepare(REQUEST_COLUMNS . ($status !== '' ? ' WHERE r.status = ?' : '')
        . ' ORDER BY r.created_at DESC, r.request_id DESC');
    $stmt->execute($status !== '' ? [$status] : []);
    return $stmt->fetchAll();
}

function request_counts(): array
{
    $counts = array_fill_keys(array_keys(REQUEST_STATUSES), 0);
    foreach (db()->query('SELECT status, COUNT(*) FROM quotation_request GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR) as $status => $count) {
        $counts[$status] = (int) $count;
    }
    return $counts;
}

function last_phone(int $userId): string
{
    $stmt = db()->prepare('SELECT contact_phone FROM quotation_request WHERE user_id = ? ORDER BY request_id DESC LIMIT 1');
    $stmt->execute([$userId]);
    return (string) ($stmt->fetchColumn() ?: '');
}

function create_request(int $userId, ?int $buildId, string $phone, string $message): int
{
    db()->prepare('INSERT INTO quotation_request (user_id, build_id, contact_phone, message) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $buildId, $phone, $message]);
    return (int) db()->lastInsertId();
}

function answer_request(int $requestId, string $reply): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status FROM quotation_request WHERE request_id = ?' . for_update());
        $stmt->execute([$requestId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            throw new RequestRefused('That request could not be found.');
        }
        if ($status === 'closed') {
            throw new RequestRefused('Request ' . request_ref($requestId) . ' is closed, so nothing was changed.');
        }
        $pdo->prepare("UPDATE quotation_request SET admin_reply = ?, status = 'answered' WHERE request_id = ?")
            ->execute([$reply, $requestId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function close_request(int $requestId): void
{
    $stmt = db()->prepare("UPDATE quotation_request SET status = 'closed' WHERE request_id = ? AND status IN ('new', 'answered')");
    $stmt->execute([$requestId]);
    if ($stmt->rowCount() !== 1) {
        throw new RequestRefused('Request ' . request_ref($requestId) . ' is closed already, or no longer there, so nothing was changed.');
    }
}

function withdraw_request(int $requestId, int $userId): void
{
    $stmt = db()->prepare("DELETE FROM quotation_request WHERE request_id = ? AND user_id = ? AND status = 'new'");
    $stmt->execute([$requestId, $userId]);
    if ($stmt->rowCount() !== 1) {
        throw new RequestRefused('Request ' . request_ref($requestId) . ' has been answered or closed already, so it was kept.');
    }
}

function render_consult_invitation(?int $buildId, ?int $orderId = null, string $lead = 'Send it to us with a note, and a consultant will come back to you.'): void
{
    $query = http_build_query(array_filter(['build' => $buildId, 'order' => $orderId]));
    ?>
    <aside class="consult" data-consult aria-labelledby="consult-title">
        <div class="consult-text">
            <strong id="consult-title">Not satisfied with this build?</strong>
            <span><?= e($lead) ?></span>
        </div>
        <a class="btn btn-primary" href="<?= e(url('consult.php' . ($query !== '' ? '?' . $query : ''))) ?>">Talk to a consultant</a>
    </aside>
    <?php
}
