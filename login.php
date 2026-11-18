<?php require_once __DIR__.'/../includes/auth.php';
if (is_admin()) {
    go('admin/dashboard.php');
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    try {
        if (sign_in(field('username', 100), (is_string($_POST['password'] ?? null) ? $_POST['password'] : ''))) {
            go('admin/dashboard.php');
        }
        $err = 'The sign-in details are not valid.';
    } catch (InputError $e) {
        $err = $e->getMessage();
    }
}
$title = 'Studio sign-in';
require __DIR__.'/../includes/header.php';?>
<main class="wrap login-page" id="main"><div class="login-box"><p class="eyebrow"><b></b> DIF / STUDIO ACCESS</p><h1>Welcome back.</h1><p class="muted">Sign in to manage the wardrobe and orders.</p><?php if ($err):?><p class="error-box" role="alert"><?=h($err)?></p><?php endif;
if (!admin_credentials()[1]):?><p class="error-box">Studio access is not configured. Run the local setup described in README.md.</p><?php endif;?><form method="post"><?=csrf()?><label class="field">Studio username<input name="username" autocomplete="username" maxlength="100" required></label><label class="field">Password<input type="password" name="password" autocomplete="current-password" maxlength="250" required></label><button class="btn dark full">Sign in <?=icon('arrow')?></button></form><a class="mini-link" href="<?=u('index.php')?>">← Return to the store</a></div></main><?php require __DIR__.'/../includes/footer.php';?>
