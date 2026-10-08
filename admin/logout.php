<?php
require_once __DIR__ . '/includes/admin_auth.php';
$_SESSION = [];
session_destroy();
redirecionar(SITE_URL . '/admin/login.php');
