<?php

require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/trip_split.php';
require __DIR__ . '/../lib/billing_plan.php';
require __DIR__ . '/../lib/billing_apply.php';

function expect_same(mixed $expected, mixed $actual, string $description): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($description . "\nОжидалось " . var_export($expected, true)
            . "\nПолучено " . var_export($actual, true));
    }
}

function invoice_snapshot(PDO $db): array
{
    return [
        $db->query('SELECT * FROM invoices ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM invoice_lines ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'invoices' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN),
    ];
}

$db = new PDO('sqlite::memory:');
$db->exec('PRAGMA foreign_keys = ON');
foreach (['schema.sql', 'seed.sql'] as $file) {
    $db->exec(file_get_contents(__DIR__ . '/../data/' . $file));
}
$db->exec('DROP INDEX invoices_client_period');

$before = invoice_snapshot($db);
$db->exec("CREATE TRIGGER reject_invoice_update BEFORE UPDATE OF amount ON invoice_lines
    WHEN NEW.invoice_id = 10 BEGIN SELECT RAISE(ABORT, 'Ошибка записи строки'); END");
try {
    apply_billing_plan($db, '2026-10');
    throw new RuntimeException('Ожидалась ошибка записи');
} catch (PDOException $error) {
    expect_same(true, str_contains($error->getMessage(), 'Ошибка записи строки'), 'Причина отката');
}
expect_same(false, $db->inTransaction(), 'Транзакция завершена после ошибки');
expect_same($before, invoice_snapshot($db), 'Откат счетов, строк и индекса');
$db->exec('DROP TRIGGER reject_invoice_update');

$db->exec("INSERT INTO invoices (id, client_id, period, status, total) VALUES (90, 1, '2026-09', 'draft', 0)");
$withDuplicate = invoice_snapshot($db);
try {
    apply_billing_plan($db, '2026-10');
    throw new RuntimeException('Ожидалась ошибка при старом дубликате');
} catch (DomainException $error) {
    expect_same(true, str_contains($error->getMessage(), 'повторяющиеся счета'), 'Причина отказа при дубликате');
}
expect_same($withDuplicate, invoice_snapshot($db), 'Старый дубликат сохранён для проверки');
$db->exec('DELETE FROM invoices WHERE id = 90');

$preview = build_billing_plan($db, '2026-10');
$result = apply_billing_plan($db, '2026-10');
expect_same([0, 6, 0], [$result['created'], $result['updated'], $result['unchanged']], 'Результат первого применения');
expect_same(billing_plan_payload($preview), billing_plan_payload($result), 'Совпадение с предпросмотром');
foreach ($preview['clients'] as $client) {
    $query = $db->prepare('SELECT trip_id, amount FROM invoice_lines WHERE invoice_id = ? ORDER BY trip_id');
    $query->execute([$client['invoice_id']]);
    $savedLines = array_map(static fn(array $line) => [
        'trip_id' => $line['trip_id'],
        'amount_cents' => stored_billing_cents($line['amount']),
    ], $query->fetchAll(PDO::FETCH_ASSOC));
    expect_same($client['lines'], $savedLines, "Сохранённые строки счёта {$client['invoice_id']}");
}

$after = invoice_snapshot($db);
expect_same(1, count($after[2]), 'Уникальный индекс установлен на старой базе');
$metadata = static function (array $rows): array {
    return array_map(static function (array $invoice): array {
        unset($invoice['total']);
        return $invoice;
    }, $rows);
};
expect_same($metadata($before[0]), $metadata($after[0]), 'Номера, статусы и даты счетов');
$beforeLineIds = array_column($before[1], 'id');
expect_same($beforeLineIds, array_column($after[1], 'id'), 'Идентификаторы существующих строк октября');
$repeat = apply_billing_plan($db, '2026-10');
expect_same([0, 0, 6, []], [$repeat['created'], $repeat['updated'], $repeat['unchanged'], $repeat['clients']],
    'Повторное применение');
expect_same($after, invoice_snapshot($db), 'Снимок после повтора');

$db->exec("INSERT INTO trips (id, number, departure_date, cost, status) VALUES
    (101, 'Тест-101', '2026-12-01', 100, 'delivered'),
    (102, 'Тест-102', '2026-12-01', 50, 'cancelled')");
$db->exec('INSERT INTO cargo (trip_id, client_id, weight_kg, volume_m3) VALUES (101, 1, 1, 1), (102, 2, 1, 1)');
$db->exec("INSERT INTO invoices (id, client_id, period, status, total) VALUES (103, 2, '2026-12', 'sent', 50)");
$db->exec('INSERT INTO invoice_lines (invoice_id, trip_id, amount) VALUES (103, 102, 50)');
$december = apply_billing_plan($db, '2026-12');
expect_same([1, 1, 0], [$december['created'], $december['updated'], $december['unchanged']], 'Изменения декабря');
expect_same('draft', $december['clients'][0]['status'], 'Статус созданного счёта');
expect_same(100.0, $db->query('SELECT total FROM invoices WHERE id = ' . $december['clients'][0]['invoice_id'])->fetchColumn(),
    'Итог созданного счёта');
expect_same(0.0, $db->query('SELECT total FROM invoices WHERE id = 103')->fetchColumn(), 'Обнуление существующего счёта');
expect_same(0, $db->query('SELECT COUNT(*) FROM invoice_lines WHERE invoice_id = 103')->fetchColumn(), 'Удаление старой строки');

echo "Проверки применения счетов прошли.\n";
