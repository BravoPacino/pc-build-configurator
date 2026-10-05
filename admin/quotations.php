<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_admin();

$status = status_filter(REQUEST_STATUSES);
$counts = request_counts();
$requests = all_requests($status);

render_header('Quotations', 'quotations');
?>
<div class="container page">
    <div class="page-head">
        <h1 class="page-title">Quotations</h1>
        <p class="page-lead">
            <?= $counts['new'] === 1 ? '1 request is' : $counts['new'] . ' requests are' ?> waiting for a reply.
            The customer reads your reply under My Requests. A request reserves nothing: it is a customer asking for advice.
        </p>
    </div>

    <?php if (array_sum($counts) === 0): ?>
        <div class="empty-state"><p>No customer has asked for a consultant yet.</p></div>
    <?php else: ?>
        <?php render_status_filter('admin/quotations.php', $status, $counts, [], REQUEST_STATUSES); ?>
        <?php if ($requests === []): ?>
            <div class="empty-state"><p>No request is <?= e(strtolower(REQUEST_STATUSES[$status])) ?> at the moment.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" id="request-list">
                    <thead>
                        <tr>
                            <th scope="col">Request</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Configuration</th>
                            <th scope="col">Telephone</th>
                            <th scope="col">Sent</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                            $id = (int) $request['request_id'];
                            $message = (string) $request['message'];
                            $excerpt = mb_strlen($message) > 160 ? rtrim(mb_substr($message, 0, 159)) . '…' : $message;
                            ?>
                            <tr data-request="<?= $id ?>" class="has-remark">
                                <td class="cell-order"><a href="<?= e(url('admin/quotation.php?id=' . $id)) ?>"><strong><?= e(request_ref($id)) ?></strong></a></td>
                                <td class="cell-customer"><?= e($request['username']) ?><span class="muted"><?= e($request['email']) ?></span></td>
                                <td><?= $request['build_id'] !== null ? e($request['build_name']) : '<span class="muted">None</span>' ?></td>
                                <td class="nowrap"><?= e($request['contact_phone']) ?></td>
                                <td><?= when_html((string) $request['created_at']) ?></td>
                                <td><?= request_status_badge((string) $request['status']) ?></td>
                                <td class="actions">
                                    <a class="btn btn-sm <?= $request['status'] === 'new' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/quotation.php?id=' . $id)) ?>">
                                        <?= $request['status'] === 'new' ? 'Reply' : 'View' ?>
                                    </a>
                                </td>
                            </tr>
                            <tr class="remark-row" data-message-for="<?= $id ?>">
                                <td colspan="7"><?= e('“' . $excerpt . '”') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
render_footer();
