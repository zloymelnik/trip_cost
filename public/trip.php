<?php
require __DIR__ . '/../lib/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$trip = db()->query("SELECT * FROM trips WHERE id = $id")->fetch();
if (!$trip) {
    http_response_code(404);
    die('Рейс не найден');
}

$cargo = db()->query("SELECT c.*, cl.name AS client_name
    FROM cargo c
    JOIN clients cl ON cl.id = c.client_id
    WHERE c.trip_id = $id
    ORDER BY cl.name, c.id")->fetchAll();

$shares = calc_share($id);
$shares_total = 0;
foreach ($shares as $share) {
    $shares_total += $share['amount'];
}

page_header('Рейс ' . $trip['number']);
?>
<p><a href="index.php">← Рейсы</a></p>
<h1>Рейс <?= h($trip['number']) ?></h1>

<table class="info">
    <tr><th>Отправление</th><td><?= h(format_dt($trip['departure_date'])) ?></td></tr>
    <tr><th>Машина</th><td><?= h($trip['vehicle_plate']) ?>, грузоподъёмность <?= num($trip['capacity_kg']) ?> кг</td></tr>
    <tr><th>Статус</th><td><?= h(trip_status_name($trip['status'])) ?></td></tr>
    <tr><th>Стоимость рейса</th><td><?= money($trip['cost']) ?> ₽</td></tr>
</table>

<h2>Грузы</h2>
<table class="grid">
    <thead>
    <tr>
        <th>Клиент</th>
        <th>Груз</th>
        <th class="r">Вес, кг</th>
        <th class="r">Объём, м³</th>
        <th class="r">Паллет</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($cargo as $c): ?>
        <tr>
            <td><?= h($c['client_name']) ?></td>
            <td><?= h($c['description']) ?></td>
            <td class="r"><?= num($c['weight_kg']) ?></td>
            <td class="r"><?= $c['volume_m3'] !== null ? num($c['volume_m3'], 1) : '' ?></td>
            <td class="r"><?= $c['pallets'] !== null ? $c['pallets'] : '' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$cargo): ?>
        <tr><td colspan="5" class="empty">Грузов нет</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h2>Доли клиентов</h2>
<table class="grid">
    <thead>
    <tr>
        <th>Клиент</th>
        <th class="r">Вес, кг</th>
        <th class="r">Доля, %</th>
        <th class="r">Сумма, ₽</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($shares as $share): ?>
        <tr>
            <td><?= h($share['name']) ?></td>
            <td class="r"><?= num($share['weight_kg']) ?></td>
            <td class="r"><?= num($share['share'] * 100, 1) ?></td>
            <td class="r"><?= money($share['amount']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="total">
        <td colspan="3">Итого</td>
        <td class="r"><?= money($shares_total) ?></td>
    </tr>
    </tbody>
</table>
<?php page_footer();
