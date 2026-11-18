<?php

declare(strict_types=1);
require_once __DIR__.'/functions.php';
require_once __DIR__.'/../config/admin_config.php';
function is_admin(): bool
{
    return !empty($_SESSION['studio_user']) && ($_SESSION['studio_seen'] ?? 0) > time() - 1800;
}
function require_admin(): void
{
    if (!is_admin()) {
        unset($_SESSION['studio_user'],$_SESSION['studio_seen']);
        go('admin/login.php');
    }
    $_SESSION['studio_seen'] = time();
}
function sign_in(string $name, string $password): bool
{
    [$user,$hash] = admin_credentials();
    if (!$user || !$hash) {
        return false;
    }
    $fn = private_dir().'/login-'.hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local').'.json';
    $fh = fopen($fn, 'c+');
    if (!$fh || !flock($fh, LOCK_EX)) {
        throw new RuntimeException('Cannot read login limits');
    }
    try {
        $arr = json_decode(stream_get_contents($fh) ?: '[]', true) ?: [];
        $arr = array_filter($arr, fn ($t) => $t > time() - 600);
        if (count($arr) >= 8) {
            throw new InputError('Too many attempts. Please try again in ten minutes.');
        }
        $ok = password_verify($password, $hash) && hash_equals($user, $name);
        if ($ok) {
            $arr = [];
        } else {
            $arr[] = time();
        }
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode(array_values($arr)));
        fflush($fh);
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
    if ($ok) {
        session_regenerate_id(true);
        refresh_preview_cookie();
        $_SESSION['studio_user'] = $user;
        $_SESSION['studio_seen'] = time();
        unset($_SESSION['csrf']);
    }
    return $ok;
}
