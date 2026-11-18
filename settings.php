<?php

declare(strict_types=1);
const STORE_ROOT = __DIR__.'/..';
function settings(): array
{
    static $s;
    if ($s !== null) {
        return $s;
    }
    $configuredRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $doc = $configuredRoot !== '' ? (realpath($configuredRoot) ?: realpath(STORE_ROOT)) : dirname(realpath(STORE_ROOT));
    $file = getenv('DIF_CONFIG') ?: dirname($doc).DIRECTORY_SEPARATOR.'dif-private'.DIRECTORY_SEPARATOR.'config.php';
    $private = is_file($file) ? require $file : [];
    if (!is_array($private)) {
        throw new RuntimeException('Invalid private configuration');
    }
    return $s = $private + ['db_host' => '127.0.0.1','db_port' => 3306,'db_user' => 'root','db_pass' => '','db_socket' => '','admin_user' => '','admin_hash' => '','storage_dir' => dirname($file),'embed_preview' => false];
}
