<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
date_default_timezone_set('Asia/Vientiane');
class InputError extends RuntimeException
{
}
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function base_path(): string
{
    $n = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    if (basename($n) === 'admin') {
        $n = dirname($n);
    } return rtrim($n, '/.');
}
function u(string $path = ''): string
{
    return base_path().'/'.ltrim($path, '/');
}
function go(string $path): void
{
    header('Location: '.u($path), true, 303);
    exit;
}
function fail_page(int $code, string $message): void
{
    http_response_code($code);
    echo '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8"><title>DIF Shop · Notice</title><link rel="stylesheet" href="'.h(u('assets/css/style.css')).'"><main class="notice-page"><p class="eyebrow">DIF SHOP / '.$code.'</p><h1>A note from the studio.</h1><p>'.h($message).'</p><a class="btn dark" href="'.h(u('index.php')).'">Return to the store →</a></main></html>';
    exit;
}
set_exception_handler(static function (Throwable $e) {
    error_log('[DIF] '.$e::class.' '.$e->getMessage());
    fail_page(503, 'The store is temporarily unavailable. Please try again shortly. For a local installation, check the database settings in README.md.');
});
function embedded_preview(): bool
{
    return (bool)settings()['embed_preview'] && (str_contains($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') || str_ends_with(preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''), '.e2b.app'));
}
function refresh_preview_cookie(): void
{
    if (embedded_preview()) {
        header_remove('Set-Cookie');
        header('Set-Cookie: '.session_name().'='.session_id().'; Path='.base_path().'/; Secure; HttpOnly; SameSite=None; Partitioned');
    }
}
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('dif_studio');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || embedded_preview();
    session_set_cookie_params(['path' => base_path().'/','httponly' => true,'secure' => $secure,'samesite' => embedded_preview() ? 'None' : 'Lax']);
    session_start();
    refresh_preview_cookie();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: private, no-store');
}
const ORDER_STATES = ['pending','confirmed','shipped','completed','cancelled'];
const PAY_STATES = ['pending','paid','failed'];
const METHODS = ['bank_transfer' => 'Bank transfer','qr' => 'QR payment','cash_on_delivery' => 'Cash on delivery'];
const SHIPPING = ['standard' => '15000.00','priority' => '25000.00'];
const PRODS = 'SELECT p.*,c.category_name,c.description AS category_description FROM products p JOIN categories c ON c.category_id=p.category_id';
const ORDER_TOTALS = 'SELECT o.*,c.full_name,c.email,c.phone,COALESCE(d.items_total,0) AS subtotal,COALESCE(d.items_total,0)+o.shipping_fee AS grand_total,COALESCE(v.paid_total,0) AS paid_total FROM orders o JOIN customers c ON c.customer_id=o.customer_id LEFT JOIN (SELECT order_id,SUM(quantity*unit_price) AS items_total FROM order_details GROUP BY order_id) d ON d.order_id=o.order_id LEFT JOIN (SELECT order_id,SUM(amount) AS paid_total FROM payments WHERE payment_status=\'paid\' GROUP BY order_id) v ON v.order_id=o.order_id';
function stmt(string $sql, array $args = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute($args);
    return $s;
}
function rows(string $sql, array $args = []): array
{
    return stmt($sql, $args)->fetchAll();
}
function row(string $sql, array $args = []): ?array
{
    return stmt($sql, $args)->fetch() ?: null;
}
function scalar(string $sql, array $args = [])
{
    return stmt($sql, $args)->fetchColumn();
}
function strval_input(array $source, string $name, string $default = ''): string
{
    return isset($source[$name]) && is_string($source[$name]) ? trim($source[$name]) : $default;
}
function field(string $key, int $max, bool $required = true, ?array $source = null): string
{
    $v = strval_input($source ?? $_POST, $key);
    if (($required && $v === '') || mb_strlen($v) > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u', $v)) {
        throw new InputError('Please check the '.str_replace('_', ' ', $key).' field (up to '.$max.' characters).');
    }
    return $v;
}
function positive($v, int $max = 4294967295, int $min = 1): int
{
    if (!is_scalar($v) || !preg_match('/^\d{1,10}$/D', (string)$v) || (int)$v < $min || (int)$v > $max) {
        throw new InputError('Please enter a whole number between '.$min.' and '.$max.'.');
    }
    return (int)$v;
}
function rid(): int
{
    try {
        return positive($_GET['id'] ?? '');
    } catch (InputError) {
        fail_page(404, 'This record could not be found.');
    }
}
function phone_field(string $k): string
{
    $v = field($k, 25);
    if (!preg_match('/^[+0-9][0-9\s()\-]{5,24}$/D', $v)) {
        throw new InputError('Please enter a valid phone number, including any leading zero.');
    }
    return $v;
}
function email_field(string $k, bool $required = false): string
{
    $v = field($k, 254, $required);
    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
        throw new InputError('Please enter a valid email address.');
    }
    return $v;
}
function choice(string $name, array $allowed): string
{
    $v = strval_input($_POST, $name);
    if (!in_array($v, $allowed, true)) {
        throw new InputError('Please choose a valid '.str_replace('_', ' ', $name).'.');
    }
    return $v;
}
function cents($v): int
{
    $v = (string)$v;
    if (!preg_match('/^(\d{1,15})(?:\.(\d{1,2}))?$/D', $v, $m)) {
        throw new InputError('The monetary amount is invalid.');
    }
    return ((int)$m[1]) * 100 + (int)str_pad($m[2] ?? '', 2, '0');
}
function dec(int $ct): string
{
    return intdiv($ct, 100).'.'.str_pad((string)($ct % 100), 2, '0', STR_PAD_LEFT);
}
function amount_field(string $name, bool $zero = false): string
{
    $v = field($name, 16);
    if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $v) || (!$zero && cents($v) === 0)) {
        throw new InputError('Enter a valid amount in Lao Kip.');
    }
    return dec(cents($v));
}
function money($v): string
{
    [$a,$b] = array_pad(explode('.', (string)$v, 2), 2, '00');
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $a) . ((int)$b > 0 ? '.'.str_pad($b, 2, '0') : '').' ₭';
}
function token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf(): string
{
    return '<input type="hidden" name="csrf" value="'.h(token()).'">';
}
function check_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        fail_page(405, 'This action needs a form submission.');
    }
    if (!hash_equals(token(), strval_input($_POST, 'csrf'))) {
        fail_page(403, 'Your form has expired. Reload the page and try again.');
    }
}
function flash(string $msg, string $type = 'info'): void
{
    $_SESSION['flash'] = [$msg,$type];
}
function notices(): void
{
    if (!empty($_SESSION['flash'])) {
        [$msg,$type] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="toast '.h($type).'" role="status">'.h($msg).'<button type="button" aria-label="Dismiss message" data-dismiss>×</button></div>';
    }
}
function cats(): array
{
    static $r;
    return $r ??= rows('SELECT * FROM categories ORDER BY category_id');
}
function cat_title(string $n): string
{
    return ['ເສື້ອຍືດ' => 'T-shirts','ໂສ້ງ' => 'Pants','ສິ້ນ' => 'Skirts'][$n] ?? $n;
}
function cat_image(string $n): string
{
    return ['ເສື້ອຍືດ' => 'tee-white.jpg','ໂສ້ງ' => 'denim.jpg','ສິ້ນ' => 'sinh.jpg'][$n] ?? 'placeholder.svg';
}
function image_for(array $p): string
{
    $id = (int)$p['product_id'];
    $f = STORE_ROOT.'/uploads/product-'.$id.'.jpg';
    if (is_file($f)) {
        return u('uploads/product-'.$id.'.jpg?v='.filemtime($f));
    }
    $bySku = ['TEE-WHT-M' => 'tee-white.jpg','TEE-WHT-L' => 'tee-white.jpg','TEE-BLK-M' => 'tee-black.jpg','JEAN-BLU-30' => 'denim.jpg','SIN-IND-M' => 'sinh.jpg'];
    return u('assets/images/'.($bySku[$p['sku']] ?? 'placeholder.svg'));
}
function bag(): array
{
    return $_SESSION['cart'] ?? [];
}
function bag_count(): int
{
    return array_sum(bag());
}
function cart_rows(): array
{
    $b = bag();
    if (!$b) {
        return [];
    } $ids = array_keys($b);
    $rs = rows(PRODS.' WHERE p.product_id IN ('.implode(',', array_fill(0, count($ids), '?')).') ORDER BY p.product_id', $ids);
    foreach ($rs as &$p) {
        $p['qty'] = $b[$p['product_id']];
        $p['line_total'] = dec($p['qty'] * cents($p['price']));
    }
    return $rs;
}
function cart_total(array $items): int
{
    return array_sum(array_map(fn ($p) => cents($p['line_total']), $items));
}
function products_url(array $q = []): string
{
    return u('products.php'.($q ? '?'.http_build_query($q) : ''));
}
function product_card(array $p): void
{
    $url = u('product-detail.php?id='.(int)$p['product_id']);?>
<article class="product-card"><a class="product-photo" href="<?=h($url)?>"><img loading="lazy" src="<?=h(image_for($p))?>" alt="<?=h($p['product_name'].' · '.$p['color'])?>"><span class="photo-label"><?=h(cat_title($p['category_name']))?></span><span class="photo-action"><?=icon('arrow')?></span></a><div class="product-meta"><span><?=h($p['color'])?> <i> / </i> <?=h($p['size_label'])?></span><span><?=((int)$p['stock_qty'] > 0) ? (int)$p['stock_qty'].' available' : 'Sold out'?></span></div><div class="product-line"><h3><a href="<?=h($url)?>"><?=h($p['product_name'])?></a></h3><span><?=h(money($p['price']))?></span></div><a class="mini-link" href="<?=h($url)?>">View details <span>↗</span></a></article><?php }
function icon(string $k): string
{
    $p = ['bag' => '<path d="M5 7.5h14l1 13H4l1-13z"/><path d="M8.5 8V6a3.5 3.5 0 0 1 7 0v2"/>','search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>','arrow' => '<path d="M3 12h17m-6-6 6 6-6 6"/>','up' => '<path d="M5 19 19 5M6 5h13v13"/>','menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>','close' => '<path d="m5 5 14 14M19 5 5 19"/>','left' => '<path d="m14 5-7 7 7 7"/>','right' => '<path d="m10 5 7 7-7 7"/>','check' => '<path d="m4 12 5 5 11-11"/>','minus' => '<path d="M5 12h14"/>','plus' => '<path d="M5 12h14M12 5v14"/>','trash' => '<path d="M4 6h16M9 6V3h6v3m-9 1 1 14h10l1-14"/>','chev' => '<path d="m6 9 6 6 6-6"/>'];
    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.45" stroke-linecap="round" stroke-linejoin="round">'.($p[$k] ?? $p['arrow']).'</svg>';
}
function input_box(string $label, string $name, string $value = '', string $type = 'text', bool $req = true, int $max = 255): void
{
    echo '<label class="field"><span>'.h($label).'</span><input type="'.h($type).'" name="'.h($name).'" value="'.h($value).'" maxlength="'.$max.'" '.($req ? 'required' : '').'></label>';
}
function status_tag(string $v): string
{
    return '<span class="status s-'.h($v).'">'.h(ucfirst($v)).'</span>';
}
function summary(int $sub, int $fee): void
{
    echo '<dl class="summary-lines"><div><dt>Subtotal</dt><dd>'.h(money(dec($sub))).'</dd></div><div><dt>Shipping</dt><dd data-fee>'.h(money(dec($fee))).'</dd></div><div class="total"><dt>Total <small>LAK</small></dt><dd data-grand data-sub="'.$sub.'">'.h(money(dec($sub + $fee))).'</dd></div></dl>';
}
function private_dir(): string
{
    $p = settings()['storage_dir'];
    if (!is_dir($p) && !mkdir($p, 0700, true) && !is_dir($p)) {
        throw new RuntimeException('Private storage unavailable');
    }
    $real = realpath($p);
    $root = realpath(($_SERVER['DOCUMENT_ROOT'] ?? '') ?: STORE_ROOT);
    if (!$real || ($root && ($real === $root || str_starts_with($real.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)))) {
        throw new RuntimeException('Storage must be outside the document root');
    }
    return $real;
}
