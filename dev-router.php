<?php

// Only for PHP's local development server. Apache uses .htaccess instead.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$root = realpath(__DIR__.'/..');
$real = realpath($root.$path);
$public = ['/','/index.php','/products.php','/product-detail.php','/cart.php','/checkout.php','/order-success.php','/about.php','/newsletter.php','/admin/','/admin'];
foreach (['index','login','logout','dashboard','products','product-add','product-edit','categories','customers','orders','order-detail','payments'] as $page) {
    $public[] = '/admin/'.$page.'.php';
}
if (in_array($path, $public, true)) {
    return false;
}
if ($real && str_starts_with($real, $root.DIRECTORY_SEPARATOR) && preg_match('~^/(?:assets/(?:images|css|js|fonts)/[\w.-]+\.(?:jpg|png|svg|css|js|ttf|txt)|uploads/product-[0-9]+\.jpg)$~D', $path)) {
    return false;
}
http_response_code(404);
echo 'Not found';
return true;
