<?php

require __DIR__ . '/../lib/trip_split.php';

function expect_same(mixed $expected, mixed $actual, string $description): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($description . "\nОжидалось " . var_export($expected, true)
            . "\nПолучено " . var_export($actual, true));
    }
}

function expect_split_error(PDO $db, int $tripId, string $reason): void
{
    try {
        calculate_trip_split($db, $tripId);
    } catch (DomainException $error) {
        expect_same(true, str_contains($error->getMessage(), $reason), "Причина ошибки рейса $tripId");
        return;
    }

    throw new RuntimeException("Ожидалась ошибка расчёта рейса $tripId");
}

$db = new PDO('sqlite::memory:');
$db->exec('PRAGMA foreign_keys = ON');
foreach (['schema.sql', 'seed.sql'] as $file) {
    $db->exec(file_get_contents(__DIR__ . '/../data/' . $file));
}

foreach ($db->query('SELECT id, cost FROM trips') as $trip) {
    $split = calculate_trip_split($db, $trip['id']);
    expect_same((int)round($trip['cost'] * 100), array_sum(array_column($split['clients'], 'amount_cents')),
        "Итог рейса {$trip['id']}");
    expect_same($split, calculate_trip_split($db, $trip['id']), "Повторный расчёт рейса {$trip['id']}");
}

$trip11 = calculate_trip_split($db, 11);
expect_same('volume_m3', $trip11['basis'], 'Рейс 11 распределяется по объёму');
expect_same([
    'client_id' => 1, 'name' => 'ООО «Хрустим»', 'cargo_count' => 2, 'weight_kg' => 120.0,
    'volume_m3' => 8.0, 'share' => 0.8, 'amount_cents' => 3_900_040,
], $trip11['clients'][1], 'Грузы клиента 1 агрегируются');
expect_same(975_010, $trip11['clients'][2]['amount_cents'], 'Сумма клиента 2 рейса 11');

$trip6 = calculate_trip_split($db, 6);
expect_same([3 => 1_666_667, 4 => 1_666_667, 5 => 1_666_666],
    array_column($trip6['clients'], 'amount_cents', 'client_id'), 'Остаток при равных долях');

$trip5 = calculate_trip_split($db, 5);
expect_same(0, $trip5['clients'][5]['amount_cents'], 'Нулевой объём на рейсе с положительным объёмом');
$trip9 = calculate_trip_split($db, 9);
expect_same(0, $trip9['clients'][2]['amount_cents'], 'Отсутствующий объём на рейсе с положительным объёмом');

$trip8 = calculate_trip_split($db, 8);
expect_same('weight_kg', $trip8['basis'], 'Рейс 8 распределяется по весу');
expect_same([2 => 2_870_968, 5 => 172_258, 6 => 2_296_774],
    array_column($trip8['clients'], 'amount_cents', 'client_id'), 'Суммы по весу для рейса 8');

$weightShares = trip_weight_shares(load_trip_cargo_totals($db, 11));
expect_same(120 / 2120, $weightShares[1]['share'], 'Доля первого клиента в весе рейса 11');
expect_same(2000 / 2120, $weightShares[2]['share'], 'Доля второго клиента в весе рейса 11');

expect_same(null, calculate_trip_split($db, 99), 'Несуществующий рейс');
$db->exec("INSERT INTO trips (id, number, departure_date, cost, status) VALUES
    (13, 'Тест-13', '2026-10-30', 100, 'delivered'),
    (14, 'Тест-14', '2026-10-30', 100, 'delivered'),
    (15, 'Тест-15', '2026-10-30', 0.01, 'delivered'),
    (16, 'Тест-16', '2026-10-30', 0, 'delivered'),
    (17, 'Тест-17', '2026-10-30', -0.01, 'delivered')");
$db->exec('INSERT INTO cargo (trip_id, client_id, weight_kg, volume_m3) VALUES
    (14, 1, 0, 0), (14, 2, 0, NULL),
    (15, 1, 1, 0.3), (15, 2, 1, 0.1), (15, 2, 1, 0.2),
    (16, 1, 1, 1), (16, 2, 1, 2),
    (17, 1, 1, 1), (17, 2, 1, 2)');
expect_split_error($db, 13, 'нет грузов');
expect_split_error($db, 14, 'нет пригодных данных');
expect_same([1 => 1, 2 => 0], array_column(calculate_trip_split($db, 15)['clients'], 'amount_cents', 'client_id'),
    'Равные суммарные объёмы с разным числом грузов');
expect_same([1 => 0, 2 => 0], array_column(calculate_trip_split($db, 16)['clients'], 'amount_cents', 'client_id'),
    'Рейс с нулевой стоимостью');
expect_same([1 => 0, 2 => -1], array_column(calculate_trip_split($db, 17)['clients'], 'amount_cents', 'client_id'),
    'При разном остатке копейку получает больший остаток');

echo "Проверки распределения стоимости рейсов прошли.\n";
