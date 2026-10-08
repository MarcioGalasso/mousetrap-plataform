<?php
require_once __DIR__ . '/../includes/admin_header.php';

$noticias = db()->query('SELECT * FROM noticias ORDER BY criado_em DESC')->fetchAll();
?>

<div class="admin-topbar">
  <h1>Notícias</h1>
  <a href="<?= SITE_URL ?>/admin/noticias/form.php" class="btn btn-primary">+ Nova notícia</a>
</div>

<?php if (isset($_GET['excluido'])): ?><div class="alert alert-success">Notícia excluída.</div><?php endif; ?>
<?php if (isset($_GET['salvo'])): ?><div class="alert alert-success">Notícia salva com sucesso.</div><?php endif; ?>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th></th><th>Título</th><th>Categoria</th><th>Status</th><th>Data</th><th>Ações</th></tr>
    </thead>
    <tbody>
      <?php if (empty($noticias)): ?>
        <tr><td colspan="6" class="empty-state">Nenhuma notícia cadastrada ainda.</td></tr>
      <?php endif; ?>
      <?php foreach ($noticias as $n): ?>
        <tr>
          <td><img class="thumb" src="<?= url_upload($n['imagem_capa'], 'noticias') ?>" alt=""></td>
          <td><?= e($n['titulo']) ?></td>
          <td><?= e($n['categoria']) ?></td>
          <td><span class="badge <?= $n['status'] === 'publicado' ? 'badge-finalizada' : 'badge-agendada' ?>"><?= $n['status'] === 'publicado' ? 'Publicado' : 'Rascunho' ?></span></td>
          <td><?= formatar_data($n['criado_em']) ?></td>
          <td class="table-actions">
            <a href="<?= SITE_URL ?>/admin/noticias/form.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline">Editar</a>
            <a href="<?= SITE_URL ?>/admin/noticias/excluir.php?id=<?= $n['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-danger" data-confirm="Excluir esta notícia?">Excluir</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
