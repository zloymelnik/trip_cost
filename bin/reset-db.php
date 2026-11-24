<?php
// Пересоздать базу из data/schema.sql и data/seed.sql.

$dir = __DIR__ . '/../data';
$file = $dir . '/app.sqlite';

if (file_exists($file)) {
    unlink($file);
}

$pdo = new PDO('sqlite:' . $file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents($dir . '/schema.sql'));
$pdo->exec(file_get_contents($dir . '/seed.sql'));

echo "База пересоздана: data/app.sqlite\n";
