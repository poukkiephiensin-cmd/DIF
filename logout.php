<?php

require_once __DIR__.'/../includes/auth.php';
require_admin();
check_post();
unset($_SESSION['studio_user'],$_SESSION['studio_seen'],$_SESSION['csrf']);
session_regenerate_id(true);
refresh_preview_cookie();
flash('You have signed out of the studio.');
go('admin/login.php');
