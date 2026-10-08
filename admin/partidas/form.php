<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$partida = [
    'adversario' => '', 'campeonato' => '', 'data_hora' => '', 'status' => 'agendada',
    'placar_mousetrap' => '', 'placar_adversario' => '', 'link_transmissao' => '', 'adversario_logo' => null,
];
$erro = '';

if ($id) {
    $stmt = db()->prepare('SELECT * FROM partidas WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $encontrada = $stmt->fetch();
    if (!$encontrada) {
        redirecionar(SITE_URL . '/admin/partidas/index.php');
    }
    $partida = $encontrada;
    if ($partida['data_hora']) {
        $partida['data_hora'] = str_replace(' ', 'T', substr($partida['data_hora'], 0, 16));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partida['adversario'] = trim($_POST['adversario'] ?? '');
    $partida['campeonato'] = trim($_POST['campeonato'] ?? '');
    $partida['data_hora'] = trim($_POST['data_hora'] ?? '');
    $partida['status'] = ($_POST['status'] ?? 'agendada') === 'finalizada' ? 'finalizada' : 'agendada';
    $partida['placar_mousetrap'] = $_POST['placar_mousetrap'] !== '' ? (int) $_POST['placar_mousetrap'] : null;
    $partida['placar_adversario'] = $_POST['placar_adversario'] !== '' ? (int) $_POST['placar_adversario'] : null;
    $partida['link_transmissao'] = trim($_POST['link_transmissao'] ?? '');

    if (!csrf_valido($_POST['token'] ?? null)) {
        $erro = 'Sessão expirada, tente novamente.';
    } elseif ($partida['adversario'] === '' || $partida['data_hora'] === '') {
        $erro = 'Informe pelo menos o adversário e a data/hora.';
    } else {
        try {
            $novoUpload = salvar_upload_imagem($_FILES['adversario_logo'] ?? [], 'adversarios');
            if ($novoUpload) {
                $partida['adversario_logo'] = $novoUpload;
            }

            $dataHoraSql = str_replace('T', ' ', $partida['data_hora']) . ':00';

            $params = [
                'adversario' => $partida['adversario'], 'campeonato' => $partida['campeonato'],
                'data_hora' => $dataHoraSql, 'status' => $partida['status'],
                'placar_mousetrap' => $partida['placar_mousetrap'], 'placar_adversario' => $partida['placar_adversario'],
                'link_transmissao' => $partida['link_transmissao'], 'adversario_logo' => $partida['adversario_logo'],
            ];

            if ($id) {
                $params['id'] = $id;
                $stmt = db()->prepare(
                    'UPDATE partidas SET adversario=:adversario, campeonato=:campeonato, data_hora=:data_hora, status=:status, placar_mousetrap=:placar_mousetrap, placar_adversario=:placar_adversario, link_transmissao=:link_transmissao, adversario_logo=:adversario_logo WHERE id=:id'
                );
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO partidas (adversario, campeonato, data_hora, status, placar_mousetrap, placar_adversario, link_transmissao, adversario_logo) VALUES (:adversario,:campeonato,:data_hora,:status,:placar_mousetrap,:placar_adversario,:link_transmissao,:adversario_logo)'
                );
            }
            $stmt->execute($params);

            redirecionar(SITE_URL . '/admin/partidas/index.php?salvo=1');
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        }
    }
}

$tituloAdmin = $id ? 'Editar partida' : 'Nova partida';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1><?= $id ? 'Editar Partida' : 'Nova Partida' ?></h1>
  <a href="<?= SITE_URL ?>/admin/partidas/index.php" class="btn btn-outline">&larr; Voltar</a>
</div>

<?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

<div class="admin-form-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?= csrf_token() ?>">

    <div class="form-row">
      <div class="form-group">
        <label>Adversário</label>
        <input class="form-control" type="text" name="adversario" value="<?= e($partida['adversario']) ?>" required>
      </div>
      <div class="form-group">
        <label>Campeonato/Competição</label>
        <input class="form-control" type="text" name="campeonato" value="<?= e($partida['campeonato']) ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Data e hora</label>
        <input class="form-control" type="datetime-local" name="data_hora" value="<?= e($partida['data_hora']) ?>" required>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select class="form-control" name="status" id="status-select">
          <option value="agendada" <?= $partida['status'] === 'agendada' ? 'selected' : '' ?>>Agendada</option>
          <option value="finalizada" <?= $partida['status'] === 'finalizada' ? 'selected' : '' ?>>Finalizada (com placar)</option>
        </select>
      </div>
    </div>

    <div class="form-row" id="placar-row" style="<?= $partida['status'] === 'finalizada' ? '' : 'display:none;' ?>">
      <div class="form-group">
        <label>Placar MouseTrap</label>
        <input class="form-control" type="number" min="0" name="placar_mousetrap" value="<?= e((string) $partida['placar_mousetrap']) ?>">
      </div>
      <div class="form-group">
        <label>Placar Adversário</label>
        <input class="form-control" type="number" min="0" name="placar_adversario" value="<?= e((string) $partida['placar_adversario']) ?>">
      </div>
    </div>

    <div class="form-group">
      <label>Link da transmissão (opcional, ex: link direto da partida na Twitch/YouTube)</label>
      <input class="form-control" type="url" name="link_transmissao" value="<?= e($partida['link_transmissao']) ?>">
    </div>

    <div class="form-group">
      <label>Logo do adversário (opcional)</label>
      <?php if (!empty($partida['adversario_logo'])): ?>
        <div class="current-image"><img src="<?= url_upload($partida['adversario_logo'], 'adversarios') ?>" alt=""><span class="form-hint">Logo atual — envie um novo para substituir.</span></div>
      <?php endif; ?>
      <input class="form-control" type="file" name="adversario_logo" accept="image/png,image/jpeg,image/webp">
    </div>

    <button type="submit" class="btn btn-primary">Salvar partida</button>
  </form>
</div>

<script>
  document.getElementById('status-select').addEventListener('change', function () {
    document.getElementById('placar-row').style.display = this.value === 'finalizada' ? 'grid' : 'none';
  });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
