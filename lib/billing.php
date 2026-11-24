<?php

/**
 * Доли клиентов в стоимости рейса.
 *
 * Стоимость рейса делится между клиентами пропорционально весу их груза.
 * Возвращает [client_id => [name, weight_kg, share, amount]], share: доля от 0 до 1.
 */
function calc_share($trip_id)
{
    $trip_id = (int)$trip_id;
    $trip = db()->query("SELECT cost FROM trips WHERE id = $trip_id")->fetch();
    $rows = db()->query("SELECT c.client_id, cl.name, SUM(c.weight_kg) AS weight_kg
        FROM cargo c
        JOIN clients cl ON cl.id = c.client_id
        WHERE c.trip_id = $trip_id
        GROUP BY c.client_id, cl.name")->fetchAll();

    $total_weight = 0;
    foreach ($rows as $row) {
        $total_weight += $row['weight_kg'];
    }

    $result = array();
    foreach ($rows as $row) {
        $share = $total_weight > 0 ? $row['weight_kg'] / $total_weight : 0;
        $result[$row['client_id']] = array(
            'name' => $row['name'],
            'weight_kg' => $row['weight_kg'],
            'share' => $share,
            'amount' => round($trip['cost'] * $share, 2),
        );
    }

    return $result;
}

/**
 * Сформировать счета клиентов за месяц.
 *
 * Для каждого клиента берутся рейсы месяца (по дате отправления, кроме отменённых) и его доля в каждом из них.
 * Если счёт за месяц уже есть, его строки пересчитываются заново.
 */
function billing_run_period($period)
{
    $db = db();
    $created = 0;
    $updated = 0;

    $db->beginTransaction();
    foreach (get_clients() as $client) {
        $trips = $db->query("SELECT DISTINCT t.id FROM trips t
            JOIN cargo c ON c.trip_id = t.id
            WHERE c.client_id = {$client['id']}
              AND t.departure_date LIKE '{$period}%'
              AND t.status <> 'cancelled'
            ORDER BY t.departure_date")->fetchAll(PDO::FETCH_COLUMN);
        if (!$trips) {
            continue;
        }

        $invoice = $db->query("SELECT * FROM invoices WHERE client_id = {$client['id']} AND period = '{$period}'")->fetch();
        if ($invoice) {
            $invoice_id = $invoice['id'];
            $db->exec("DELETE FROM invoice_lines WHERE invoice_id = $invoice_id");
            $updated++;
        } else {
            $db->exec("INSERT INTO invoices (client_id, period, status, total, created_at)
                VALUES ({$client['id']}, '{$period}', 'draft', 0, datetime('now', 'localtime'))");
            $invoice_id = $db->lastInsertId();
            $created++;
        }

        $total = 0;
        foreach ($trips as $trip_id) {
            $shares = calc_share($trip_id);
            $amount = $shares[$client['id']]['amount'];
            $db->exec("INSERT INTO invoice_lines (invoice_id, trip_id, amount) VALUES ($invoice_id, $trip_id, $amount)");
            $total += $amount;
        }
        $db->exec("UPDATE invoices SET total = $total WHERE id = $invoice_id");
    }
    $db->commit();

    return array('created' => $created, 'updated' => $updated);
}
