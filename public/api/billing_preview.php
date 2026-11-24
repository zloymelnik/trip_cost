<?php
require __DIR__ . '/../../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_out(['error' => 'Используйте метод GET.'], 405);
}

$period = $_GET['period'] ?? null;
if (!is_billing_period($period)) {
    json_out(['error' => 'Укажите месяц в формате ГГГГ-ММ.'], 400);
}

$db = db();
$db->beginTransaction();
try {
    $plan = build_billing_plan($db, $period);
    $db->commit();
} catch (DomainException $error) {
    $db->rollBack();
    json_out(['error' => $error->getMessage()], 422);
}

$clients = [];
foreach ($plan['clients'] as $client) {
    $clients[] = [
        'client_id' => $client['client_id'],
        'name' => $client['name'],
        'invoice_id' => $client['invoice_id'],
        'status' => $client['status'],
        'sent_at' => $client['sent_at'],
        'exported_at' => $client['exported_at'],
        'before' => format_money_cents($client['before_cents']),
        'after' => format_money_cents($client['after_cents']),
        'diff' => format_money_cents($client['diff_cents']),
        'change' => $client['change'],
        'lines_changed' => $client['lines_changed'],
    ];
}

json_out([
    'period' => $plan['period'],
    'clients' => $clients,
    'total_before' => format_money_cents($plan['total_before_cents']),
    'total_after' => format_money_cents($plan['total_after_cents']),
    'total_diff' => format_money_cents($plan['total_diff_cents']),
    'weight_based_trips' => $plan['weight_based_trips'],
]);
