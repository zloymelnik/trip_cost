<?php
require __DIR__ . '/../../lib/bootstrap.php';

$where = array('1 = 1');
$params = array();

if (!empty($_GET['month'])) {
    $where[] = 't.departure_date LIKE ?';
    $params[] = $_GET['month'] . '%';
}
if (!empty($_GET['client_id'])) {
    $where[] = 't.id IN (SELECT trip_id FROM cargo WHERE client_id = ?)';
    $params[] = (int)$_GET['client_id'];
}

$st = db()->prepare("SELECT t.id, t.number, t.departure_date, t.vehicle_plate, t.status, t.cost,
        COUNT(c.id) AS cargo_count, SUM(c.weight_kg) AS weight_kg
    FROM trips t
    LEFT JOIN cargo c ON c.trip_id = t.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY t.id
    ORDER BY t.departure_date DESC");
$st->execute($params);
$trips = $st->fetchAll();

foreach ($trips as &$trip) {
    $trip['status_name'] = trip_status_name($trip['status']);
    $trip['departure'] = format_dt($trip['departure_date']);
}
unset($trip);

json_out(array('trips' => $trips));
