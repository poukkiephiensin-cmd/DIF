<?php

declare(strict_types=1);
// Local CLI only. No web-accessible setup endpoint or shipped admin password.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
function ask(string $prompt, string $default = ''): string
{
    echo $prompt.($default !== '' ? " [$default]" : '').': ';
    $v = fgets(STDIN);
    if ($v === false) {
        fwrite(STDERR, "Input ended. No configuration was saved.\n");
        exit(1);
    }
    return rtrim($v, "\r\n") === '' ? $default : rtrim($v, "\r\n");
}
$root = realpath(__DIR__.'/..');
$default = dirname(dirname($root)).DIRECTORY_SEPARATOR.'dif-private'.DIRECTORY_SEPARATOR.'config.php';
$file = $argv[1] ?? $default;
$dir = dirname($file);
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "Cannot create private directory.\n");
    exit(1);
}
$dir = realpath($dir);
$doc = realpath(dirname($root));
if (str_starts_with(strtolower($dir.DIRECTORY_SEPARATOR), strtolower($doc.DIRECTORY_SEPARATOR))) {
    fwrite(STDERR, "Choose a directory OUTSIDE the web document root.\n");
    exit(1);
}
if (file_exists($file)) {
    fwrite(STDERR, "Configuration already exists. Edit the private file instead; nothing was overwritten.\n");
    exit(1);
}
echo "DIF SHOP · Local configuration\nPrivate file: $file\nRun this privately: input is visible in this terminal.\n";
$host = ask('MySQL host', '127.0.0.1');
$port = ask('MySQL port', '3306');
if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
    fwrite(STDERR, "Invalid port.\n");
    exit(1);
}
$user = ask('MySQL username', 'root');
$pass = ask('MySQL password (Enter if blank)');
$admin = ask('Studio username', 'curator');
$pw = ask('New studio password (at least 12 characters)');
if (strlen($pw) < 12 || strlen($pw) > 72) {
    fwrite(STDERR, "Use a password between 12 and 72 bytes. Nothing was written.\n");
    exit(1);
}
if (!hash_equals($pw, ask('Confirm studio password'))) {
    fwrite(STDERR, "Passwords do not match. Nothing was saved.\n");
    exit(1);
}
$cfg = ['db_host' => $host,'db_port' => (int)$port,'db_user' => $user,'db_pass' => $pass,'admin_user' => $admin,'admin_hash' => password_hash($pw, PASSWORD_DEFAULT),'storage_dir' => $dir,'embed_preview' => false];
umask(0077);
$f = fopen($file, 'x');
if (!$f) {
    fwrite(STDERR, "Cannot write configuration.\n");
    exit(1);
}
fwrite($f, "<?php\nreturn ".var_export($cfg, true).";\n");
fclose($f);
echo "Configuration saved. The studio password is stored only as a hash.\nIf using a custom path, set DIF_CONFIG to this full filename for PHP.\n";
