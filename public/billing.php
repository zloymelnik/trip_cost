<?php
require __DIR__ . '/../lib/bootstrap.php';

$period = $_GET['period'] ?? null;
if (!is_billing_period($period)) {
    $period = db()->query('SELECT MAX(period) FROM (
        SELECT period FROM invoices
        UNION ALL SELECT substr(departure_date, 1, 7) FROM trips
    )')->fetchColumn();
    if (!is_billing_period($period)) {
        $period = date('Y-m');
    }
}

page_header('Счета за месяц');
?>
<h1>Счета за месяц</h1>

<form id="billing-form" class="actions">
    <label for="billing-period">Месяц</label>
    <input type="month" id="billing-period" name="period" value="<?= h($period) ?>" required>
    <button type="submit" id="billing-show">Показать</button>
    <button type="button" id="billing-apply" disabled>Применить</button>
</form>

<p id="billing-status" role="status"></p>
<p id="billing-weight-note" hidden></p>
<div id="billing-results" class="billing-grid" hidden>
    <table class="grid">
        <thead>
        <tr>
            <th scope="col">Клиент</th>
            <th scope="col">Счёт</th>
            <th scope="col" class="r">Было, ₽</th>
            <th scope="col" class="r">Стало, ₽</th>
            <th scope="col" class="r">Разница, ₽</th>
            <th scope="col">Изменение</th>
        </tr>
        </thead>
        <tbody id="billing-clients"></tbody>
        <tfoot>
        <tr class="total">
            <td colspan="2">Итого за месяц</td>
            <td class="r" id="billing-total-before"></td>
            <td class="r" id="billing-total-after"></td>
            <td class="r" id="billing-total-diff"></td>
            <td></td>
        </tr>
        </tfoot>
    </table>
</div>

<script src="js/billing.js"></script>
<?php page_footer();
