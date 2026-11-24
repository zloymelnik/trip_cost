<?php

function is_billing_period(mixed $period): bool
{
    return is_string($period) && preg_match('/\A(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])\z/', $period) === 1;
}

function stored_billing_cents(float $amount): int
{
    $cents = round($amount * 100);
    if (!is_finite($cents) || abs($cents) >= PHP_INT_MAX) {
        throw new DomainException('Сумма в сохранённом счёте выходит за пределы расчёта.');
    }

    return (int)$cents;
}

/** Собирает будущие строки и сравнивает их со счетами периода. Работает только на чтение. */
function build_billing_plan(PDO $db, string $period): array
{
    $invoiceQuery = $db->prepare('SELECT i.id, i.client_id, i.status, i.sent_at, i.exported_at, i.total, cl.name
        FROM invoices i JOIN clients cl ON cl.id = i.client_id
        WHERE i.period = ? ORDER BY i.id');
    $invoiceQuery->execute([$period]);
    $invoices = [];
    foreach ($invoiceQuery->fetchAll(PDO::FETCH_ASSOC) as $invoice) {
        $clientId = $invoice['client_id'];
        if (isset($invoices[$clientId])) {
            throw new DomainException("У клиента {$invoice['name']} несколько счетов за $period.");
        }
        $invoices[$clientId] = $invoice;
    }

    $lineQuery = $db->prepare('SELECT i.client_id, l.trip_id, l.amount
        FROM invoice_lines l JOIN invoices i ON i.id = l.invoice_id
        WHERE i.period = ? ORDER BY l.trip_id, l.id');
    $lineQuery->execute([$period]);
    foreach ($lineQuery->fetchAll(PDO::FETCH_ASSOC) as $line) {
        $invoices[$line['client_id']]['lines'][] = [
            'trip_id' => $line['trip_id'],
            'amount_cents' => stored_billing_cents($line['amount']),
        ];
    }

    $tripQuery = $db->prepare("SELECT id FROM trips
        WHERE departure_date LIKE ? AND status <> 'cancelled' ORDER BY id");
    $tripQuery->execute([$period . '-%']);
    $newLines = [];
    $clientNames = [];
    $weightBasedTrips = [];
    foreach ($tripQuery->fetchAll(PDO::FETCH_COLUMN) as $tripId) {
        $split = calculate_trip_split($db, $tripId);
        if ($split['basis'] === 'weight_kg') {
            $weightBasedTrips[] = ['trip_id' => $tripId, 'number' => $split['number']];
        }
        foreach ($split['clients'] as $client) {
            $clientId = $client['client_id'];
            $clientNames[$clientId] = $client['name'];
            $newLines[$clientId][] = ['trip_id' => $tripId, 'amount_cents' => $client['amount_cents']];
        }
    }

    $clientIds = array_keys($invoices + $newLines);
    sort($clientIds, SORT_NUMERIC);
    $clients = [];
    $totalBefore = 0;
    $totalAfter = 0;
    foreach ($clientIds as $clientId) {
        $invoice = $invoices[$clientId] ?? null;
        $before = stored_billing_cents($invoice['total'] ?? 0);
        $lines = $newLines[$clientId] ?? [];
        $after = array_sum(array_column($lines, 'amount_cents'));
        $diff = $after - $before;
        if (!is_int($after) || !is_int($diff)) {
            throw new DomainException("Сумма счёта за $period выходит за пределы расчёта.");
        }
        $linesChanged = ($invoice['lines'] ?? []) !== $lines;
        $change = 'unchanged';
        if ($invoice === null) {
            $change = 'create';
        } elseif ($linesChanged || $before !== $after) {
            $change = 'update';
        }

        $clients[] = [
            'client_id' => $clientId,
            'name' => $invoice['name'] ?? $clientNames[$clientId],
            'invoice_id' => $invoice['id'] ?? null,
            'status' => $invoice['status'] ?? null,
            'sent_at' => $invoice['sent_at'] ?? null,
            'exported_at' => $invoice['exported_at'] ?? null,
            'before_cents' => $before,
            'after_cents' => $after,
            'diff_cents' => $diff,
            'change' => $change,
            'lines_changed' => $linesChanged,
            'lines' => $lines,
        ];
        $totalBefore += $before;
        $totalAfter += $after;
    }

    $totalDiff = $totalAfter - $totalBefore;
    if (!is_int($totalBefore) || !is_int($totalAfter) || !is_int($totalDiff)) {
        throw new DomainException("Итог за $period выходит за пределы расчёта.");
    }

    return [
        'period' => $period,
        'clients' => $clients,
        'total_before_cents' => $totalBefore,
        'total_after_cents' => $totalAfter,
        'total_diff_cents' => $totalDiff,
        'weight_based_trips' => $weightBasedTrips,
    ];
}
