<?php

require __DIR__ . '/../lib/trip_split.php';
require __DIR__ . '/../lib/billing_plan.php';

function expect_same(mixed $expected, mixed $actual, string $description): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($description . "\nОжидалось " . var_export($expected, true)
            . "\nПолучено " . var_export($actual, true));
    }
}

$db = new PDO('sqlite::memory:');
$db->exec('PRAGMA foreign_keys = ON');
foreach (['schema.sql', 'seed.sql'] as $file) {
    $db->exec(file_get_contents(__DIR__ . '/../data/' . $file));
}

expect_same(true, is_billing_period('2026-10'), 'Корректный месяц');
foreach ([null, ['2026-10'], '0000-10', '2026-00', '2026-13', '2026-2', "2026-10\n"] as $invalid) {
    expect_same(false, is_billing_period($invalid), 'Некорректный месяц');
}

$db->exec('PRAGMA query_only = ON');
$october = build_billing_plan($db, '2026-10');
expect_same(45_461_078, $october['total_before_cents'], 'Итог октября до пересчёта');
expect_same(45_461_080, $october['total_after_cents'], 'Итог октября после пересчёта');
expect_same(2, $october['total_diff_cents'], 'Разница итогов октября');
expect_same([['trip_id' => 8, 'number' => 'НН-1108']], $october['weight_based_trips'], 'Рейсы по весу');
expect_same(6, count($october['clients']), 'Число счетов за октябрь');
$updated = array_filter($october['clients'], static fn(array $client) => $client['change'] === 'update');
expect_same(6, count($updated), 'Октябрьские счета, требующие обновления');
expect_same(3, count(array_filter($updated, static fn(array $client) => $client['status'] === 'sent')),
    'Затронутые отправленные счета');
expect_same(2, count(array_filter($updated, static fn(array $client) => $client['exported_at'] !== null)),
    'Затронутые выгруженные счета');
expect_same($october, build_billing_plan($db, '2026-10'), 'Повторный предпросмотр');

$empty = build_billing_plan($db, '2026-08');
expect_same([], $empty['clients'], 'Пустой месяц');
expect_same([0, 0, 0], [$empty['total_before_cents'], $empty['total_after_cents'], $empty['total_diff_cents']],
    'Итоги пустого месяца');

$db->exec('PRAGMA query_only = OFF');
$db->exec("INSERT INTO trips (id, number, departure_date, cost, status) VALUES
    (101, 'Тест-101', '2026-12-01', 100, 'delivered'),
    (102, 'Тест-102', '2026-12-01', 100, 'cancelled'),
    (103, 'Тест-103', '2026-12-31 23:59', 50, 'delivered'),
    (104, 'Тест-104', '2026-12-14', 30, 'delivered'),
    (105, 'Тест-105', '2027-01-01', 40, 'delivered'),
    (106, 'Тест-106', '2026-12-15', 10, 'cancelled'),
    (107, 'Тест-107', '2026-12-16', 50, 'cancelled'),
    (108, 'Тест-108', '2027-02-01', 20, 'delivered')");
$db->exec('INSERT INTO cargo (trip_id, client_id, weight_kg, volume_m3) VALUES
    (101, 1, 1, 1), (102, 1, 1, 1), (103, 2, 1, 1),
    (104, 4, 1, 1), (105, 2, 1, 1), (107, 3, 1, 1)');
$db->exec("INSERT INTO invoices (id, client_id, period, status, total) VALUES
    (101, 1, '2026-12', 'draft', 100),
    (103, 3, '2026-12', 'sent', 50),
    (104, 4, '2026-12', 'draft', 30)");
$db->exec('INSERT INTO invoice_lines (invoice_id, trip_id, amount) VALUES
    (101, 102, 100), (103, 107, 50), (104, 104, 30)');

$db->exec('PRAGMA query_only = ON');
$december = build_billing_plan($db, '2026-12');
$clients = array_column($december['clients'], null, 'client_id');
expect_same(['update', 'create', 'update', 'unchanged'], array_column($clients, 'change'), 'Виды изменений');
expect_same(0, $clients[1]['diff_cents'], 'Сумма не изменилась при замене рейса');
expect_same(true, $clients[1]['lines_changed'], 'Замена рейса обнаружена');
expect_same([['trip_id' => 101, 'amount_cents' => 10_000]], $clients[1]['lines'], 'Отменённый рейс исключён');
expect_same(null, $clients[2]['invoice_id'], 'ID будущего счёта');
expect_same(0, $clients[2]['before_cents'], 'Исходная сумма будущего счёта');
expect_same([['trip_id' => 103, 'amount_cents' => 5_000]], $clients[2]['lines'], 'Рейс следующего месяца исключён');
expect_same(0, $clients[3]['after_cents'], 'Итог счёта без подходящих рейсов');
expect_same([], $clients[3]['lines'], 'Строки счёта без подходящих рейсов');
expect_same(false, $clients[4]['lines_changed'], 'Неизменившиеся строки');

try {
    build_billing_plan($db, '2027-02');
    throw new RuntimeException('Ожидалась ошибка рейса без грузов');
} catch (DomainException $error) {
    expect_same('У рейса Тест-108 нет грузов.', $error->getMessage(), 'Причина ошибки расчёта');
}

echo "Проверки предпросмотра счетов прошли.\n";
