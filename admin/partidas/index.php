<?php
require_once __DIR__ . '/../includes/admin_header.php';

$partidas = db()->query('SELECT * FROM partidas ORDER BY data_hora DESC')->fetchAll();
?>

<div class="admin-topbar">
  <h1>Partidas</h1>
  <a href="<?= SITE_URL ?>/admin/partidas/form.php" class="btn btn-primary">+ Nova partida</a>
</div>

<?php if (isset($_GET['excluido'])): ?><div class="alert alert-success">Partida removida.</div><?php endif; ?>
<?php if (isset($_GET['salvo'])): ?><div class="alert alert-success">Partida salva com sucesso.</div><?php endif; ?>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th>Adversário</th><th>Campeonato</th><th>Data/Hora</th><th>Status</th><th>Placar</th><th>Ações</th></tr>
    </thead>
    <tbody>
      <?php if (empty($partidas)): ?>
        <tr><td colspan="6" class="empty-state">Nenhuma partida cadastrada ainda.</td></tr>
      <?php endif; ?>
      <?php foreach ($partidas as $p): ?>
        <tr>
          <td>MouseTrap vs <?= e($p['adversario']) ?></td>
          <td><?= e($p['campeonato']) ?></td>
          <td><?= formatar_data_hora($p['data_hora']) ?></td>
          <td><span class="badge <?= $p['status'] === 'finalizada' ? 'badge-finalizada' : 'badge-agendada' ?>"><?= $p['status'] === 'finalizada' ? 'Finalizada' : 'Agendada' ?></span></td>
          <td><?= $p['status'] === 'finalizada' ? ((int)$p['placar_mousetrap'] . ' - ' . (int)$p['placar_adversario']) : '—' ?></td>
          <td class="table-actions">
            <a href="<?= SITE_URL ?>/admin/partidas/form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Editar</a>
            <a href="<?= SITE_URL ?>/admin/partidas/excluir.php?id=<?= $p['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-danger" data-confirm="Excluir esta partida?">Excluir</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
