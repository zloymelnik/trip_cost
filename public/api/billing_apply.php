<?php
require __DIR__ . '/../../lib/bootstrap.php';
require __DIR__ . '/../../lib/billing_apply.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_out(['error' => 'Используйте метод POST.'], 405);
}

$period = $_POST['period'] ?? null;
if (!is_billing_period($period)) {
    json_out(['error' => 'Укажите месяц в формате ГГГГ-ММ.'], 400);
}

try {
    $result = apply_billing_plan(db(), $period);
} catch (DomainException $error) {
    json_out(['error' => $error->getMessage()], 422);
} catch (PDOException $error) {
    $sqliteCode = ($error->errorInfo[1] ?? 0) & 0xff;
    if ($sqliteCode === 5 || $sqliteCode === 6) {
        json_out(['error' => 'В базе уже выполняется запись. Повторите запрос.'], 503);
    }
    error_log($error->getMessage());
    json_out(['error' => 'Не удалось сохранить счета.'], 500);
}

json_out(billing_plan_payload($result) + [
    'created' => $result['created'],
    'updated' => $result['updated'],
    'unchanged' => $result['unchanged'],
]);
