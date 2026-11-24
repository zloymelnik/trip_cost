<?php
require __DIR__ . '/../../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_out(['error' => 'Используйте метод GET.'], 405);
}

$tripId = filter_var($_GET['trip_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($tripId === false) {
    json_out(['error' => 'Укажите положительный целочисленный trip_id.'], 400);
}

try {
    $split = calculate_trip_split(db(), $tripId);
} catch (DomainException $error) {
    json_out(['error' => $error->getMessage()], 422);
}

if ($split === null) {
    json_out(['error' => 'Рейс не найден.'], 404);
}

$clients = [];
foreach ($split['clients'] as $client) {
    $client['amount'] = format_money_cents($client['amount_cents']);
    unset($client['amount_cents']);
    $clients[] = $client;
}

json_out([
    'trip_id' => $split['trip_id'],
    'number' => $split['number'],
    'cost' => format_money_cents($split['cost_cents']),
    'basis' => $split['basis'],
    'clients' => $clients,
]);
