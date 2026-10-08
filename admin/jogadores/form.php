<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();

demo_garantir_schema();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$jogador = [
    'nome_real' => '',
    'nickname' => '',
    'funcao' => '',
    'bio' => '',
    'instagram' => '',
    'twitter' => '',
    'twitch' => '',
    'ordem' => 0,
    'ativo' => 1,
    'analisar_na_demo' => 1,
    'foto' => null,
];
$erro = '';

if ($id) {
    $stmt = db()->prepare('SELECT * FROM jogadores WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $encontrado = $stmt->fetch();
    if (!$encontrado) {
        redirecionar(SITE_URL . '/admin/jogadores/index.php');
    }
    $jogador = $encontrado;
    if (!isset($jogador['analisar_na_demo'])) {
        $jogador['analisar_na_demo'] = (int) ($jogador['ativo'] ?? 0);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jogador['nome_real'] = trim($_POST['nome_real'] ?? '');
    $jogador['nickname'] = trim($_POST['nickname'] ?? '');
    $jogador['funcao'] = trim($_POST['funcao'] ?? '');
    $jogador['bio'] = trim($_POST['bio'] ?? '');
    $jogador['instagram'] = trim($_POST['instagram'] ?? '');
    $jogador['twitter'] = trim($_POST['twitter'] ?? '');
    $jogador['twitch'] = trim($_POST['twitch'] ?? '');
    $jogador['ordem'] = (int) ($_POST['ordem'] ?? 0);
    $jogador['ativo'] = isset($_POST['ativo']) ? 1 : 0;
    $jogador['analisar_na_demo'] = isset($_POST['analisar_na_demo']) ? 1 : 0;

    if (!csrf_valido($_POST['token'] ?? null)) {
        $erro = 'Sessão expirada, tente novamente.';
    } elseif ($jogador['nickname'] === '') {
        $erro = 'O nickname é obrigatório.';
    } else {
        try {
            $novoUpload = salvar_upload_imagem($_FILES['foto'] ?? [], 'jogadores');
            if ($novoUpload) {
                $jogador['foto'] = $novoUpload;
            }

            if ($id) {
                $stmt = db()->prepare(
                    'UPDATE jogadores SET nome_real=:nome_real, nickname=:nickname, funcao=:funcao, bio=:bio,
                     instagram=:instagram, twitter=:twitter, twitch=:twitch, ordem=:ordem,
                     ativo=:ativo, analisar_na_demo=:analisar, foto=:foto WHERE id=:id'
                );
                $stmt->execute([
                    'nome_real' => $jogador['nome_real'],
                    'nickname' => $jogador['nickname'],
                    'funcao' => $jogador['funcao'],
                    'bio' => $jogador['bio'],
                    'instagram' => $jogador['instagram'],
                    'twitter' => $jogador['twitter'],
                    'twitch' => $jogador['twitch'],
                    'ordem' => $jogador['ordem'],
                    'ativo' => $jogador['ativo'],
                    'analisar' => $jogador['analisar_na_demo'],
                    'foto' => $jogador['foto'],
                    'id' => $id,
                ]);
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO jogadores (nome_real, nickname, funcao, bio, instagram, twitter, twitch, ordem, ativo, analisar_na_demo, foto)
                     VALUES (:nome_real,:nickname,:funcao,:bio,:instagram,:twitter,:twitch,:ordem,:ativo,:analisar,:foto)'
                );
                $stmt->execute([
                    'nome_real' => $jogador['nome_real'],
                    'nickname' => $jogador['nickname'],
                    'funcao' => $jogador['funcao'],
                    'bio' => $jogador['bio'],
                    'instagram' => $jogador['instagram'],
                    'twitter' => $jogador['twitter'],
                    'twitch' => $jogador['twitch'],
                    'ordem' => $jogador['ordem'],
                    'ativo' => $jogador['ativo'],
                    'analisar' => $jogador['analisar_na_demo'],
                    'foto' => $jogador['foto'],
                ]);
            }

            redirecionar(SITE_URL . '/admin/jogadores/index.php?salvo=1');
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        }
    }
}

$tituloAdmin = $id ? 'Editar jogador' : 'Novo jogador';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1><?= $id ? 'Editar Jogador' : 'Novo Jogador' ?></h1>
  <a href="<?= SITE_URL ?>/admin/jogadores/index.php" class="btn btn-outline">&larr; Voltar</a>
</div>

<?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

<div class="admin-form-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?= csrf_token() ?>">

    <div class="form-row">
      <div class="form-group">
        <label>Nickname</label>
        <input class="form-control" type="text" name="nickname" value="<?= e($jogador['nickname']) ?>" required>
      </div>
      <div class="form-group">
        <label>Função (IGL, AWPer, Rifler...)</label>
        <input class="form-control" type="text" name="funcao" value="<?= e($jogador['funcao']) ?>">
      </div>
    </div>

    <div class="form-group">
      <label>Nome real (opcional)</label>
      <input class="form-control" type="text" name="nome_real" value="<?= e($jogador['nome_real']) ?>">
    </div>

    <div class="form-group">
      <label>Bio curta (opcional)</label>
      <textarea class="form-control" name="bio" rows="3"><?= e($jogador['bio']) ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Instagram (link)</label><input class="form-control" type="url" name="instagram" value="<?= e($jogador['instagram']) ?>"></div>
      <div class="form-group"><label>X/Twitter (link)</label><input class="form-control" type="url" name="twitter" value="<?= e($jogador['twitter']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Twitch (link)</label><input class="form-control" type="url" name="twitch" value="<?= e($jogador['twitch']) ?>"></div>
      <div class="form-group"><label>Ordem de exibição</label><input class="form-control" type="number" name="ordem" value="<?= (int) $jogador['ordem'] ?>"></div>
    </div>

    <div class="form-group">
      <label>Foto</label>
      <?php if (!empty($jogador['foto'])): ?>
        <div class="current-image"><img src="<?= url_upload($jogador['foto'], 'jogadores') ?>" alt=""><span class="form-hint">Foto atual — envie uma nova para substituir.</span></div>
      <?php endif; ?>
      <input class="form-control" type="file" name="foto" accept="image/png,image/jpeg,image/webp">
      <div class="form-hint">JPG, PNG ou WEBP, até 4MB. Recomendado: foto vertical (retrato).</div>
    </div>

    <div class="form-group">
      <label><input type="checkbox" name="ativo" <?= !empty($jogador['ativo']) ? 'checked' : '' ?>> Exibir no site</label>
      <div class="form-hint">Controla a página do time / home — não afeta análise de demos.</div>
    </div>

    <div class="form-group">
      <label><input type="checkbox" name="analisar_na_demo" <?= !empty($jogador['analisar_na_demo']) ? 'checked' : '' ?>> Analisar na demo</label>
      <div class="form-hint">Inclui este nick ao cruzar demos (modo cadastro). Independente de “Exibir no site”.</div>
    </div>

    <button type="submit" class="btn btn-primary">Salvar jogador</button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
