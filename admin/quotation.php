<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

$requestId = (int) (is_string($_GET['id'] ?? null) ? $_GET['id'] : 0);
$request = find_request($requestId);
if ($request === null) {
    flash('error', 'That request could not be found.');
    redirect('admin/quotations.php');
}
$ref = request_ref($requestId);
$reply = (string) ($request['admin_reply'] ?? '');
$replyError = '';

if (is_post()) {
    csrf_verify();
    switch (posted('action')) {
        case 'reply':
            $reply = str_replace("\r\n", "\n", posted('reply'));
            $replyError = request_text_problem($reply, 'Write the reply the customer will read.');
            if ($replyError === '') {
                try {
                    answer_request($requestId, $reply);
                    flash('success', 'Your reply to request ' . $ref . ' was saved. The customer reads it under My Requests.');
                } catch (RequestRefused $e) {
                    flash('error', $e->getMessage());
                }
                redirect('admin/quotation.php?id=' . $requestId);
            }
            break;

        case 'close':
            try {
                close_request($requestId);
                flash('success', 'Request ' . $ref . ' was closed.');
            } catch (RequestRefused $e) {
                flash('error', $e->getMessage());
            }
            redirect('admin/quotation.php?id=' . $requestId);

        default:
            flash('error', 'That action is not one this page can do.');
            redirect('admin/quotation.php?id=' . $requestId);
    }
}

$status = (string) $request['status'];
$answered = (string) ($request['admin_reply'] ?? '') !== '';
$quote = $request['build_id'] !== null ? order_quote((int) $request['build_id']) : null;

$explained = [
    'new'      => 'Waiting for your reply. The customer reads it under My Requests.',
    'answered' => 'Answered. A new reply replaces the one the customer reads now.',
    'closed'   => 'Closed. A closed request is final.',
];

render_header('Request ' . $ref, 'quotations');
?>
<div class="container page">
    <a class="back-link" href="<?= e(url('admin/quotations.php')) ?>">← Quotations</a>
    <div class="page-head">
        <h1 class="page-title order-title">Request <?= e($ref) ?> <?= request_status_badge($status) ?></h1>
        <p class="page-lead" data-status-text><?= e($explained[$status] ?? '') ?></p>
    </div>

    <dl class="order-facts">
        <div><dt>Customer</dt><dd><?= e($request['username']) ?> <span class="muted"><?= e($request['email']) ?></span></dd></div>
        <div><dt>Telephone</dt><dd><a href="<?= e('tel:' . preg_replace('/[^0-9+]/', '', (string) $request['contact_phone'])) ?>"><?= e($request['contact_phone']) ?></a></dd></div>
        <div><dt>Sent</dt><dd><?= e(when_text((string) $request['created_at'])) ?></dd></div>
        <div><dt>Configuration</dt><dd><?= $request['build_id'] !== null ? e($request['build_name']) : '<span class="muted">None</span>' ?></dd></div>
    </dl>

    <div class="request-text request-message" data-message>
        <span class="request-label">The customer wrote</span>
        <p><?= e($request['message']) ?></p>
    </div>

    <?php if ($quote !== null): ?>
        <h2 class="section-title">Their configuration, at today’s prices</h2>
        <?php if ($quote['problems'] !== []): ?>
            <div class="flash flash-info notice" data-problems>
                <strong>As it stands it cannot be ordered:</strong>
                <ul class="form-errors">
                    <?php foreach ($quote['problems'] as $problem): ?><li><?= e($problem) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php render_quote_lines($quote, 'request-lines'); ?>
    <?php endif; ?>

    <?php if ($status === 'closed'): ?>
        <?php if ($answered): ?>
            <div class="request-text request-reply" data-reply>
                <span class="request-label">Your reply</span>
                <p><?= e($request['admin_reply']) ?></p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="decisions">
            <section class="form-panel decision" aria-labelledby="reply-title">
                <h2 class="section-title" id="reply-title"><?= $answered ? 'Change the reply' : 'Reply' ?></h2>
                <p class="muted">The customer reads it under My Requests<?= $answered ? '; this replaces what they read now' : '' ?>.</p>
                <form method="post" action="<?= e(url('admin/quotation.php?id=' . $requestId)) ?>" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reply">
                    <?= textarea_field('reply', 'Reply to the customer', [
                        'required' => true, 'maxlength' => REQUEST_TEXT_MAX, 'rows' => 6,
                        'data-error-required' => 'Write the reply the customer will read.',
                        'data-error-maxlength' => 'Use no more than ' . REQUEST_TEXT_MAX . ' characters.',
                    ], $reply, $replyError) ?>
                    <button type="submit" class="btn btn-primary" data-send-reply><?= $answered ? 'Save the new reply' : 'Send reply' ?></button>
                </form>
            </section>
            <section class="form-panel decision" aria-labelledby="close-title">
                <h2 class="section-title" id="close-title">Close</h2>
                <p class="muted">When the conversation is over. A closed request is final: it can no longer be answered or withdrawn.</p>
                <form method="post" action="<?= e(url('admin/quotation.php?id=' . $requestId)) ?>"
                      data-confirm="<?= e('It can no longer be answered, and the customer sees it as closed.') ?>"
                      data-confirm-title="<?= e('Close request ' . $ref . '?') ?>" data-confirm-button="Close request">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="close">
                    <button type="submit" class="btn btn-ghost" data-close-request>Close request</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer(['confirm.js']);
