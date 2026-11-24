<?php
require __DIR__ . '/../lib/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoice = db()->query("SELECT i.*, cl.name AS client_name, cl.inn
    FROM invoices i
    JOIN clients cl ON cl.id = i.client_id
    WHERE i.id = $id")->fetch();
if (!$invoice) {
    http_response_code(404);
    die('Счёт не найден');
}

$lines = db()->query("SELECT l.*, t.number, t.departure_date
    FROM invoice_lines l
    JOIN trips t ON t.id = l.trip_id
    WHERE l.invoice_id = $id
    ORDER BY t.departure_date")->fetchAll();

page_header('Счёт № ' . $invoice['id']);
?>
<p><a href="invoices.php?period=<?= h($invoice['period']) ?>">← Счета</a></p>
<h1>Счёт № <?= $invoice['id'] ?></h1>

<table class="info">
    <tr><th>Клиент</th><td><?= h($invoice['client_name']) ?>, ИНН <?= h($invoice['inn']) ?></td></tr>
    <tr><th>Месяц</th><td><?= h($invoice['period']) ?></td></tr>
    <tr><th>Статус</th><td><?= h(invoice_status_name($invoice['status'])) ?></td></tr>
    <tr><th>Создан</th><td><?= h(format_dt($invoice['created_at'])) ?></td></tr>
    <tr><th>Отправлен</th><td><?= h(format_dt($invoice['sent_at'])) ?></td></tr>
    <tr><th>Выгружен в 1С</th><td><?= h(format_dt($invoice['exported_at'])) ?></td></tr>
</table>

<h2>Рейсы</h2>
<table class="grid">
    <thead>
    <tr>
        <th>Рейс</th>
        <th>Отправление</th>
        <th class="r">Сумма, ₽</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($lines as $line): ?>
        <tr>
            <td><a href="trip.php?id=<?= $line['trip_id'] ?>"><?= h($line['number']) ?></a></td>
            <td><?= h(format_dt($line['departure_date'])) ?></td>
            <td class="r"><?= money($line['amount']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="total">
        <td colspan="2">Итого</td>
        <td class="r"><?= money($invoice['total']) ?></td>
    </tr>
    </tbody>
</table>
<?php page_footer();
