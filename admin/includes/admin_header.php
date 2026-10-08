<?php
require_once __DIR__ . '/admin_auth.php';
exigir_login();
$admin = admin_atual();
$rota = basename($_SERVER['SCRIPT_NAME']);
$secao = basename(dirname($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($tituloAdmin) ? e($tituloAdmin) . ' – ' : '' ?>Painel MouseTrap</title>
<link rel="icon" href="<?= SITE_URL ?>/assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/admin/admin.css">
</head>
<body class="admin-body<?= !empty($bodyClassExtra) ? ' ' . e($bodyClassExtra) : '' ?>">

<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="<?= SITE_URL ?>/admin/index.php" class="logo" style="margin-bottom:30px;">
      <img src="<?= SITE_URL ?>/assets/img/logo.png" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="">
      <span class="logo-text">MOUSE<strong>TRAP</strong><small>PAINEL</small></span>
    </a>
    <nav class="admin-nav">
      <a href="<?= SITE_URL ?>/admin/index.php" class="<?= $rota === 'index.php' && $secao === 'admin' ? 'active' : '' ?>">&#128202; Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/noticias/index.php" class="<?= $secao === 'noticias' ? 'active' : '' ?>">&#128240; Notícias</a>
      <a href="<?= SITE_URL ?>/admin/jogadores/index.php" class="<?= $secao === 'jogadores' ? 'active' : '' ?>">&#127918; Jogadores</a>
      <a href="<?= SITE_URL ?>/admin/partidas/index.php" class="<?= $secao === 'partidas' ? 'active' : '' ?>">&#127942; Partidas</a>
      <a href="<?= SITE_URL ?>/admin/demos/index.php" class="<?= $secao === 'demos' ? 'active' : '' ?>">&#128202; Análise Demos</a>
      <a href="<?= SITE_URL ?>/admin/configuracoes.php" class="<?= $rota === 'configuracoes.php' ? 'active' : '' ?>">&#9881; Configurações</a>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="<?= SITE_URL ?>/index.php" target="_blank">&larr; Ver site</a>
      <a href="<?= SITE_URL ?>/admin/logout.php">Sair (<?= e($admin['username'] ?? '') ?>)</a>
    </div>
  </aside>

  <main class="admin-content">
