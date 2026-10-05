<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_login_json();

[$selection, $problems] = read_selection($_GET['c'] ?? [], $_GET['q'] ?? []);
if ($problems !== []) {
    send_json(['errors' => $problems], 422);
}

$context = rule_context();
$result = evaluate_selection($selection, $context);
if (($_GET['view'] ?? '') !== 'configurator') {
    send_json($result + ['html' => render_verdicts($result)]);
}

$candidates = [];
foreach (catalogue_components() as $part) {
    $candidates[(int) $part['category_id']][] = $part;
}
send_json($result + [
    'html' => render_verdicts($result, false),
    'fit'  => (object) fit_report($selection, $candidates, $context),
]);
