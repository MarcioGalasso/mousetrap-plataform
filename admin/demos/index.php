<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/demo_metrics.php';
exigir_login();

$demos = [];
try {
    $demos = db()->query('SELECT id, titulo, mapa, status, criado_em, metricas_json FROM demos_analises ORDER BY id DESC')->fetchAll();
} catch (Throwable $e) {
    $erroTabela = true;
}

$tituloAdmin = 'Análise de Demos';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Análise de Demos CS2</h1>
  <a href="<?= SITE_URL ?>/admin/demos/nova.php" class="btn btn-primary">+ Upar demo e analisar</a>
</div>

<p style="color:var(--muted); margin:-12px 0 24px; max-width:720px;">
  Fluxo: você envia só o arquivo <strong>.dem</strong> → o sistema faz o parse → calcula métricas (5v4, pistol, opening…) → a IA gera o relatório e libera o chat.
</p>

<?php if (!empty($erroTabela)): ?>
  <div class="alert alert-error">
    Tabelas de demos ainda não existem. Rode o SQL de <code>database/demos.sql</code> no phpMyAdmin.
  </div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="alert alert-success">Demo processada com sucesso.</div>
<?php endif; ?>
<?php if (isset($_GET['erro'])): ?>
  <div class="alert alert-error"><?= e($_GET['erro']) ?></div>
<?php endif; ?>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Título</th>
        <th>Mapa</th>
        <th>Status</th>
        <th>Win Rate</th>
        <th>Data</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($demos)): ?>
        <tr><td colspan="7" class="empty-state">Nenhuma demo analisada ainda.</td></tr>
      <?php endif; ?>
      <?php foreach ($demos as $d): ?>
        <?php
          $m = $d['metricas_json'] ? json_decode($d['metricas_json'], true) : null;
          $wr = $m['resumo']['win_rate_geral'] ?? null;
        ?>
        <tr>
          <td>#<?= (int) $d['id'] ?></td>
          <td><?= e($d['titulo']) ?></td>
          <td><?= e($d['mapa'] ?: '—') ?></td>
          <td>
            <span class="badge <?= $d['status'] === 'pronto' ? 'badge-finalizada' : ($d['status'] === 'erro' ? 'badge-agendada' : 'badge-agendada') ?>">
              <?= e($d['status']) ?>
            </span>
          </td>
          <td><?= $wr !== null ? e($wr) . '%' : '—' ?></td>
          <td><?= formatar_data($d['criado_em']) ?></td>
          <td class="table-actions">
            <a class="btn btn-sm btn-outline" href="<?= SITE_URL ?>/admin/demos/ver.php?id=<?= (int) $d['id'] ?>">Abrir</a>
            <a class="btn btn-sm btn-danger" href="<?= SITE_URL ?>/admin/demos/excluir.php?id=<?= (int) $d['id'] ?>&token=<?= csrf_token() ?>" data-confirm="Excluir esta análise?">Excluir</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
