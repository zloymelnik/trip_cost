<?php
// Выгрузка отправленных счетов для 1С.
// Формат файла согласован с бухгалтерией: его разбирает обработка загрузки счетов в 1С. Не менять без согласования.
require __DIR__ . '/../lib/bootstrap.php';

$db = db();
$invoices = $db->query("SELECT i.*, cl.name AS client_name, cl.inn
    FROM invoices i
    JOIN clients cl ON cl.id = i.client_id
    WHERE i.status = 'sent' AND i.exported_at IS NULL
    ORDER BY i.id")->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="invoices_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, array('invoice', 'inn', 'client', 'period', 'trip', 'amount'), ';', '"', '');

$ids = array();
foreach ($invoices as $invoice) {
    $lines = $db->query("SELECT l.amount, t.number
        FROM invoice_lines l
        JOIN trips t ON t.id = l.trip_id
        WHERE l.invoice_id = {$invoice['id']}
        ORDER BY l.id")->fetchAll();
    foreach ($lines as $line) {
        fputcsv($out, array(
            $invoice['id'],
            $invoice['inn'],
            $invoice['client_name'],
            $invoice['period'],
            $line['number'],
            number_format($line['amount'], 2, ',', ''),
        ), ';', '"', '');
    }
    $ids[] = (int)$invoice['id'];
}
fclose($out);

if ($ids) {
    $db->exec("UPDATE invoices SET exported_at = datetime('now', 'localtime') WHERE id IN (" . implode(',', $ids) . ")");
}
