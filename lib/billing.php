<?php

/**
 * Расчёт по весу для текущей карточки рейса и формирования счетов.
 */
function calc_share($trip_id)
{
    $trip_id = (int)$trip_id;
    $trip = db()->query("SELECT cost FROM trips WHERE id = $trip_id")->fetch();
    $shares = trip_weight_shares(load_trip_cargo_totals(db(), $trip_id));

    $result = array();
    foreach ($shares as $clientId => $client) {
        $result[$clientId] = array(
            'name' => $client['name'],
            'weight_kg' => $client['weight_kg'],
            'share' => $client['share'],
            'amount' => round($trip['cost'] * $client['share'], 2),
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
