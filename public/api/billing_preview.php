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

try {
    $plan = preview_billing_plan(db(), $period);
} catch (DomainException $error) {
    json_out(['error' => $error->getMessage()], 422);
}

json_out(billing_plan_payload($plan));
