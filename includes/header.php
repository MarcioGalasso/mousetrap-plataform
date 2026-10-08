<?php
require_once __DIR__ . '/functions.php';
$paginaAtual = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($tituloPagina) ? e($tituloPagina) . ' – ' . SITE_NOME : SITE_NOME . ' – Uma armadilha para gigantes' ?></title>
<meta name="description" content="<?= e($descricaoPagina ?? 'MouseTrap Esports - Organização brasileira de Counter-Strike 2. Notícias, elenco, partidas e resultados.') ?>">
<link rel="icon" href="<?= SITE_URL ?>/assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="container header-inner">
    <a href="<?= SITE_URL ?>/index.php" class="logo">
      <img src="<?= SITE_URL ?>/assets/img/logo.png" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="MouseTrap Esports">
      <span class="logo-text">MOUSE<strong>TRAP</strong><small>ESPORTS</small></span>
    </a>

    <button class="nav-toggle" aria-label="Abrir menu" onclick="document.querySelector('.main-nav').classList.toggle('is-open')">
      <span></span><span></span><span></span>
    </button>

    <nav class="main-nav">
      <a href="<?= SITE_URL ?>/index.php" class="<?= $paginaAtual === 'index.php' ? 'active' : '' ?>">Home</a>
      <a href="<?= SITE_URL ?>/sobre.php" class="<?= $paginaAtual === 'sobre.php' ? 'active' : '' ?>">Sobre</a>
      <a href="<?= SITE_URL ?>/time.php" class="<?= $paginaAtual === 'time.php' ? 'active' : '' ?>">Time</a>
      <a href="<?= SITE_URL ?>/partidas.php" class="<?= $paginaAtual === 'partidas.php' ? 'active' : '' ?>">Partidas</a>
      <a href="<?= SITE_URL ?>/noticias.php" class="<?= in_array($paginaAtual, ['noticias.php', 'noticia.php']) ? 'active' : '' ?>">Notícias</a>
      <a href="<?= SITE_URL ?>/contato.php" class="<?= $paginaAtual === 'contato.php' ? 'active' : '' ?>">Contato</a>
      <a href="<?= SITE_URL ?>/contato.php" class="btn btn-primary btn-nav">Junte-se <span aria-hidden="true">&rarr;</span></a>
    </nav>
  </div>
</header>
<main>
