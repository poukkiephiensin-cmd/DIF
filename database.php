<?php

declare(strict_types=1);
require_once __DIR__.'/settings.php';
function db(): PDO
{
    static $db;
    if ($db instanceof PDO) {
        return $db;
    }
    $s = settings();
    $where = $s['db_socket'] ? 'unix_socket='.$s['db_socket'] : 'host='.$s['db_host'].';port='.(int)$s['db_port'];
    $db = new PDO('mysql:'.$where.';dbname=online_clothing_store;charset=utf8mb4', $s['db_user'], $s['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES => false,PDO::ATTR_STRINGIFY_FETCHES => false]);
    $db->exec("SET time_zone = '+07:00'");
    return $db;
}
