<?php
require __DIR__ . '/../lib/bootstrap.php';

$period = $_POST['period'] ?? $_GET['period'] ?? null;
$location = is_billing_period($period) ? 'billing.php?period=' . $period : 'billing.php';
header('Location: ' . $location, true, 303);
