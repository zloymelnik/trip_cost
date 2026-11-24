<?php

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($x)
{
    return number_format((float)$x, 2, ',', ' ');
}

function num($x, $decimals = 0)
{
    return number_format((float)$x, $decimals, ',', ' ');
}

function get_clients()
{
    return db()->query("SELECT * FROM clients ORDER BY name")->fetchAll();
}

function trip_status_name($status)
{
    $names = array(
        'planned' => 'Запланирован',
        'shipped' => 'В пути',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменён',
    );
    return isset($names[$status]) ? $names[$status] : $status;
}

function invoice_status_name($status)
{
    $names = array(
        'draft' => 'Черновик',
        'sent' => 'Отправлен',
    );
    return isset($names[$status]) ? $names[$status] : $status;
}

function format_dt($dt)
{
    if (!$dt) {
        return '';
    }
    return date('d.m.Y H:i', strtotime($dt));
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function page_header($title)
{
    $menu = array(
        'index.php' => 'Рейсы',
        'invoices.php' => 'Счета',
        'load_report.php' => 'Загрузка машин',
    );
    $current = basename($_SERVER['SCRIPT_NAME']);

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="css/app.css">';
    echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
    echo '<script src="js/app.js"></script>';
    echo '</head><body>';
    echo '<div class="top"><span class="logo">Рейсы и счета</span>';
    foreach ($menu as $url => $name) {
        echo '<a href="' . $url . '"' . ($url == $current ? ' class="active"' : '') . '>' . h($name) . '</a>';
    }
    echo '</div><div class="page">';
}

function page_footer()
{
    echo '</div></body></html>';
}
