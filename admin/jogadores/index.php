<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();
demo_garantir_schema();
require_once __DIR__ . '/../includes/admin_header.php';

$jogadores = db()->query('SELECT * FROM jogadores ORDER BY ordem ASC, id ASC')->fetchAll();
?>

<div class="admin-topbar">
  <h1>Jogadores</h1>
  <a href="<?= SITE_URL ?>/admin/jogadores/form.php" class="btn btn-primary">+ Novo jogador</a>
</div>

<?php if (isset($_GET['excluido'])): ?><div class="alert alert-success">Jogador removido.</div><?php endif; ?>
<?php if (isset($_GET['salvo'])): ?><div class="alert alert-success">Jogador salvo com sucesso.</div><?php endif; ?>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th></th><th>Nickname</th><th>Função</th><th>Ordem</th><th>Site</th><th>Demo</th><th>Ações</th></tr>
    </thead>
    <tbody>
      <?php if (empty($jogadores)): ?>
        <tr><td colspan="7" class="empty-state">Nenhum jogador cadastrado ainda.</td></tr>
      <?php endif; ?>
      <?php foreach ($jogadores as $j): ?>
        <?php $naDemo = !empty($j['analisar_na_demo']); ?>
        <tr>
          <td><img class="thumb" src="<?= url_upload($j['foto'], 'jogadores') ?>" alt=""></td>
          <td><?= e($j['nickname']) ?></td>
          <td><?= e($j['funcao']) ?></td>
          <td><?= (int) $j['ordem'] ?></td>
          <td><span class="badge <?= $j['ativo'] ? 'badge-finalizada' : 'badge-agendada' ?>"><?= $j['ativo'] ? 'Site' : '—' ?></span></td>
          <td><span class="badge <?= $naDemo ? 'badge-finalizada' : 'badge-agendada' ?>"><?= $naDemo ? 'Analisar' : '—' ?></span></td>
          <td class="table-actions">
            <a href="<?= SITE_URL ?>/admin/jogadores/form.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-outline">Editar</a>
            <a href="<?= SITE_URL ?>/admin/jogadores/excluir.php?id=<?= $j['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-danger" data-confirm="Remover este jogador?">Excluir</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
