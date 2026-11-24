<?php
require __DIR__ . '/../lib/bootstrap.php';

$periods = db()->query("SELECT DISTINCT period FROM invoices ORDER BY period DESC")->fetchAll(PDO::FETCH_COLUMN);
$period = isset($_GET['period']) ? $_GET['period'] : '';
if (!in_array($period, $periods) && $periods) {
    $period = $periods[0];
}

$st = db()->prepare("SELECT i.*, cl.name AS client_name
    FROM invoices i
    JOIN clients cl ON cl.id = i.client_id
    WHERE i.period = ?
    ORDER BY cl.name");
$st->execute(array($period));
$invoices = $st->fetchAll();

$total = 0;
foreach ($invoices as $invoice) {
    $total += $invoice['total'];
}

page_header('Счета');
?>
<h1>Счета клиентов</h1>

<?php if (!empty($_GET['msg'])): ?>
    <div class="msg"><?= h($_GET['msg']) ?></div>
<?php endif; ?>

<form class="filter" method="get">
    Месяц:
    <select name="period" onchange="this.form.submit()">
        <?php foreach ($periods as $p): ?>
            <option value="<?= h($p) ?>"<?= $p == $period ? ' selected' : '' ?>><?= h($p) ?></option>
        <?php endforeach; ?>
    </select>
    <a href="export_1c.php">Выгрузить отправленные счета в 1С</a>
</form>

<table class="grid">
    <thead>
    <tr>
        <th>Счёт</th>
        <th>Клиент</th>
        <th>Статус</th>
        <th class="r">Сумма, ₽</th>
        <th>Отправлен</th>
        <th>Выгружен в 1С</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($invoices as $invoice): ?>
        <tr>
            <td><a href="invoice.php?id=<?= $invoice['id'] ?>">№ <?= $invoice['id'] ?></a></td>
            <td><?= h($invoice['client_name']) ?></td>
            <td><?= h(invoice_status_name($invoice['status'])) ?></td>
            <td class="r"><?= money($invoice['total']) ?></td>
            <td><?= h(format_dt($invoice['sent_at'])) ?></td>
            <td><?= h(format_dt($invoice['exported_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$invoices): ?>
        <tr><td colspan="6" class="empty">Счетов нет</td></tr>
    <?php endif; ?>
    <tr class="total">
        <td colspan="3">Итого</td>
        <td class="r"><?= money($total) ?></td>
        <td colspan="2"></td>
    </tr>
    </tbody>
</table>

<h2>Сформировать счета за месяц</h2>
<form class="actions" method="post" action="billing_run.php">
    <input type="month" name="period" value="<?= h($period) ?>">
    <button type="submit">Сформировать</button>
</form>
<?php page_footer();
