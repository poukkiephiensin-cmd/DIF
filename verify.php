<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// This test only reads the database; use --sample immediately after importing the supplied workbook fixture.
require_once __DIR__.'/../config/database.php';
function ck(bool $pass, string $name): void
{
    echo ($pass ? 'PASS' : 'FAIL').' · '.$name.PHP_EOL;
    if (!$pass) {
        exit(1);
    }
}
try {
    $d = db();
    $version = $d->query('SELECT VERSION()')->fetchColumn();
    echo 'Connected: '.$version.PHP_EOL;
    ck(stripos($version, 'MariaDB') === false && version_compare(explode('-', $version)[0], '8.0.16', '>='), 'MySQL 8.0.16 or newer');
    ck($d->query('SELECT DATABASE()')->fetchColumn() === 'online_clothing_store', 'Original database selected');
    $tables = $d->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    ck($tables === ['categories','customers','order_details','orders','payments','products'], 'Exactly six original tables');
    ck((int)$d->query('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE()')->fetchColumn() === 38, '38 original columns');
    ck((int)$d->query('SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE()')->fetchColumn() === 5, 'Five foreign-key relationships');
    ck((int)$d->query("SELECT COUNT(*) FROM payments WHERE (payment_status='paid')<>(paid_at IS NOT NULL)")->fetchColumn() === 0, 'Payment timestamps agree with status');
    if (in_array('--sample', $argv, true)) {
        foreach (['products' => 5,'customers' => 3,'categories' => 3,'orders' => 3,'order_details' => 5,'payments' => 4] as $t => $n) {
            ck((int)$d->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn() === $n, $t.' sample row count');
        }
        $v = $d->query('SELECT SUM(d.quantity*d.unit_price)+o.shipping_fee FROM orders o JOIN order_details d ON d.order_id=o.order_id WHERE o.order_id=1001 GROUP BY o.order_id,o.shipping_fee')->fetchColumn();
        ck((string)$v === '370000.00', 'Order 1001 includes historic prices and one delivery fee');
        ck((string)$d->query("SELECT SUM(amount) FROM payments WHERE payment_status='paid'")->fetchColumn() === '635000.00', 'Verified sample sales: 635,000 LAK');
    }
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL · Connection or query error. Check DIF_CONFIG, MySQL service, grants and imports.\n");
    exit(1);
}
