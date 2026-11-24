<?php

function billing_amount_for_storage(int $cents): string
{
    $amount = format_money_cents($cents);
    if (stored_billing_cents((float)$amount) !== $cents) {
        throw new DomainException('Сумму счёта нельзя сохранить с точностью до копейки.');
    }

    return $amount;
}

function ensure_unique_billing_invoices(PDO $db): void
{
    try {
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS invoices_client_period ON invoices(client_id, period)');
    } catch (PDOException $error) {
        if ((($error->errorInfo[1] ?? 0) & 0xff) === 19) {
            throw new DomainException('В базе есть повторяющиеся счета одного клиента за месяц.', previous: $error);
        }
        throw $error;
    }
}

function update_billing_lines(PDO $db, int $invoiceId, array $previousLines, array $lines): void
{
    $toSave = array_column($lines, 'amount_cents', 'trip_id');
    $delete = $db->prepare('DELETE FROM invoice_lines WHERE id = ?');
    $update = $db->prepare('UPDATE invoice_lines SET amount = ? WHERE id = ?');
    $insert = $db->prepare('INSERT INTO invoice_lines (invoice_id, trip_id, amount) VALUES (?, ?, ?)');

    foreach ($previousLines as $previous) {
        $tripId = $previous['trip_id'];
        if (!isset($toSave[$tripId])) {
            $delete->execute([$previous['id']]);
            continue;
        }
        if ($previous['amount_cents'] !== $toSave[$tripId]) {
            $update->execute([billing_amount_for_storage($toSave[$tripId]), $previous['id']]);
        }
        unset($toSave[$tripId]);
    }

    foreach ($toSave as $tripId => $cents) {
        $insert->execute([$invoiceId, $tripId, billing_amount_for_storage($cents)]);
    }
}

function apply_billing_plan(PDO $db, string $period): array
{
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    // Получаем блокировку записи до чтения счетов, чтобы параллельный запрос увидел применённые изменения.
    $db->exec('BEGIN IMMEDIATE');
    try {
        $plan = build_billing_plan($db, $period);
        ensure_unique_billing_invoices($db);
        $insertInvoice = $db->prepare("INSERT INTO invoices (client_id, period, status, total, created_at)
            VALUES (?, ?, 'draft', ?, datetime('now', 'localtime'))");
        $updateInvoice = $db->prepare('UPDATE invoices SET total = ? WHERE id = ?');
        $counts = ['create' => 0, 'update' => 0, 'unchanged' => 0];
        $changedClients = [];
        foreach ($plan['clients'] as $client) {
            $change = $client['change'];
            $counts[$change]++;
            if ($change === 'unchanged') {
                continue;
            }

            if ($change === 'create') {
                $insertInvoice->execute([$client['client_id'], $period, billing_amount_for_storage($client['after_cents'])]);
                $client['invoice_id'] = (int)$db->lastInsertId();
                $client['status'] = 'draft';
            } elseif ($client['before_cents'] !== $client['after_cents']) {
                $updateInvoice->execute([billing_amount_for_storage($client['after_cents']), $client['invoice_id']]);
            }
            if ($client['lines_changed']) {
                update_billing_lines($db, $client['invoice_id'], $client['previous_lines'], $client['lines']);
            }
            $changedClients[] = $client;
        }

        $db->exec('COMMIT');
        $plan['clients'] = $changedClients;
        return $plan + [
            'created' => $counts['create'],
            'updated' => $counts['update'],
            'unchanged' => $counts['unchanged'],
        ];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->exec('ROLLBACK');
        }
        throw $error;
    }
}
