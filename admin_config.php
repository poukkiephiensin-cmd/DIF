<?php

declare(strict_types=1);
require_once __DIR__.'/settings.php';
function admin_credentials(): array
{
    return [getenv('DIF_ADMIN_USER') ?: settings()['admin_user'],getenv('DIF_ADMIN_HASH') ?: settings()['admin_hash']];
}
