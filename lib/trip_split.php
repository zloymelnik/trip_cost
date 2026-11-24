<?php

function load_trip_cargo_totals(PDO $db, int $tripId): array
{
    $query = $db->prepare('SELECT c.client_id, cl.name, COUNT(*) AS cargo_count,
            SUM(c.weight_kg) AS weight_kg,
            SUM(CASE WHEN c.volume_m3 > 0 THEN c.volume_m3 ELSE 0 END) AS volume_m3
        FROM cargo c
        JOIN clients cl ON cl.id = c.client_id
        WHERE c.trip_id = ?
        GROUP BY c.client_id, cl.name
        ORDER BY c.client_id');
    $query->execute([$tripId]);

    return array_column($query->fetchAll(PDO::FETCH_ASSOC), null, 'client_id');
}

function trip_weight_shares(array $clients): array
{
    $totalWeight = array_sum(array_column($clients, 'weight_kg'));
    foreach ($clients as $clientId => $client) {
        $clients[$clientId]['share'] = $totalWeight > 0 ? $client['weight_kg'] / $totalWeight : 0;
    }

    return $clients;
}

/** Возвращает суммы в копейках и данные клиентов по ключу client_id. */
function calculate_trip_split(PDO $db, int $tripId): ?array
{
    $query = $db->prepare('SELECT number, cost FROM trips WHERE id = ?');
    $query->execute([$tripId]);
    $trip = $query->fetch(PDO::FETCH_ASSOC);
    if ($trip === false) {
        return null;
    }

    $clients = load_trip_cargo_totals($db, $tripId);
    if (!$clients) {
        throw new DomainException("У рейса {$trip['number']} нет грузов.");
    }

    $basis = 'volume_m3';
    $quantities = array_map(static fn(array $client) => $client['volume_m3'], $clients);
    $totalQuantity = array_sum($quantities);
    if ($totalQuantity <= 0) {
        $basis = 'weight_kg';
        $quantities = array_map(static fn(array $client) => max(0, $client['weight_kg']), $clients);
        $totalQuantity = array_sum($quantities);
    }
    if ($totalQuantity <= 0 || !is_finite($totalQuantity)) {
        throw new DomainException("У рейса {$trip['number']} нет пригодных данных об объёме или весе.");
    }

    $cost = round($trip['cost'] * 100);
    if (!is_finite($cost) || abs($cost) >= PHP_INT_MAX) {
        throw new DomainException("Стоимость рейса {$trip['number']} выходит за пределы расчёта.");
    }

    $costCents = (int)$cost;
    $absoluteCost = abs($costCents);
    $sign = $costCents < 0 ? -1 : 1;
    $remainingCents = $absoluteCost;
    $remainders = [];
    foreach ($quantities as $clientId => $quantity) {
        $share = $quantity / $totalQuantity;
        $quota = $absoluteCost * $share;
        $baseAmount = (int)floor($quota);
        $clients[$clientId]['share'] = $share;
        $clients[$clientId]['amount_cents'] = $sign * $baseAmount;
        $remainingCents -= $baseAmount;

        if ($quantity > 0) {
            // Сглаживаем погрешность REAL при сравнении дробных остатков.
            $remainders[$clientId] = round($quota - $baseAmount, 9);
        }
    }

    uksort($remainders, static function (int $left, int $right) use ($remainders): int {
        return ($remainders[$right] <=> $remainders[$left]) ?: ($left <=> $right);
    });
    if ($remainingCents < 0 || $remainingCents > count($remainders)) {
        throw new DomainException("Не удалось точно распределить стоимость рейса {$trip['number']}.");
    }
    foreach (array_slice(array_keys($remainders), 0, $remainingCents) as $clientId) {
        $clients[$clientId]['amount_cents'] += $sign;
    }

    return [
        'trip_id' => $tripId,
        'number' => $trip['number'],
        'cost_cents' => $costCents,
        'basis' => $basis,
        'clients' => $clients,
    ];
}
