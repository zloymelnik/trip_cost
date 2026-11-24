<?php
require __DIR__ . '/../lib/bootstrap.php';

$months = db()->query("SELECT DISTINCT substr(departure_date, 1, 7) FROM trips ORDER BY 1 DESC")->fetchAll(PDO::FETCH_COLUMN);

page_header('Рейсы');
?>
<h1>Рейсы</h1>

<form id="filter" class="filter">
    <select name="month">
        <option value="">Все месяцы</option>
        <?php foreach ($months as $m): ?>
            <option value="<?= h($m) ?>"><?= h($m) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="client_id">
        <option value="">Все клиенты</option>
        <?php foreach (get_clients() as $client): ?>
            <option value="<?= $client['id'] ?>"><?= h($client['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<table class="grid" id="trips">
    <thead>
    <tr>
        <th>Рейс</th>
        <th>Отправление</th>
        <th>Машина</th>
        <th>Статус</th>
        <th class="r">Грузов</th>
        <th class="r">Вес, кг</th>
        <th class="r">Стоимость, ₽</th>
    </tr>
    </thead>
    <tbody></tbody>
</table>

<script src="js/trips.js"></script>
<?php page_footer();
