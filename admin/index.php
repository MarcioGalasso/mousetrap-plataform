<?php
require_once __DIR__ . '/includes/admin_header.php';

$totalNoticias = (int) db()->query('SELECT COUNT(*) FROM noticias')->fetchColumn();
$totalPublicadas = (int) db()->query("SELECT COUNT(*) FROM noticias WHERE status = 'publicado'")->fetchColumn();
$totalJogadores = (int) db()->query('SELECT COUNT(*) FROM jogadores WHERE ativo = 1')->fetchColumn();
$totalProximas = (int) db()->query("SELECT COUNT(*) FROM partidas WHERE status = 'agendada'")->fetchColumn();
?>

<div class="admin-topbar">
  <h1>Dashboard</h1>
</div>

<div class="admin-cards">
  <div class="admin-card"><div class="num"><?= $totalPublicadas ?></div><div class="label">Notícias publicadas</div></div>
  <div class="admin-card"><div class="num"><?= $totalNoticias - $totalPublicadas ?></div><div class="label">Rascunhos</div></div>
  <div class="admin-card"><div class="num"><?= $totalJogadores ?></div><div class="label">Jogadores ativos</div></div>
  <div class="admin-card"><div class="num"><?= $totalProximas ?></div><div class="label">Próximas partidas</div></div>
</div>

<div class="admin-form-card" style="max-width:none;">
  <h3 style="margin-bottom:16px;">Acesso rápido</h3>
  <div style="display:flex; gap:12px; flex-wrap:wrap;">
    <a href="<?= SITE_URL ?>/admin/noticias/form.php" class="btn btn-primary">+ Nova notícia</a>
    <a href="<?= SITE_URL ?>/admin/jogadores/form.php" class="btn btn-outline">+ Novo jogador</a>
    <a href="<?= SITE_URL ?>/admin/partidas/form.php" class="btn btn-outline">+ Nova partida</a>
    <a href="<?= SITE_URL ?>/admin/configuracoes.php" class="btn btn-outline">Editar configurações do site</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
