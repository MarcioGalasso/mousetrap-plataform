<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

$campos = [
    'hero_titulo', 'hero_subtitulo', 'stat_titulos', 'stat_fundacao',
    'livestream_tipo', 'livestream_id',
    'contato_email', 'contato_telefone', 'contato_endereco',
    'social_instagram', 'social_twitter', 'social_twitch', 'social_youtube',
    'openai_api_key', 'openai_model', 'demo_analyzer_url', 'demo_analyzer_secret',
];

$salvo = false;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido($_POST['token'] ?? null)) {
        $erro = 'Sessão expirada, tente novamente.';
    } else {
        foreach ($campos as $campo) {
            set_setting($campo, trim($_POST[$campo] ?? ''));
        }
        $salvo = true;
    }
}

$settings = [];
$stmt = db()->query('SELECT chave, valor FROM configuracoes');
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['chave']] = $row['valor'];
}

$tituloAdmin = 'Configurações';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Configurações do Site</h1>
</div>

<?php if ($salvo): ?><div class="alert alert-success">Configurações salvas! As alterações já estão no ar.</div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="token" value="<?= csrf_token() ?>">

  <div class="admin-form-card" style="max-width:none; margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Hero / Home</h3>
    <div class="form-group">
      <label>Frase de impacto</label>
      <input class="form-control" type="text" name="hero_titulo" value="<?= e($settings['hero_titulo'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Subtítulo</label>
      <input class="form-control" type="text" name="hero_subtitulo" value="<?= e($settings['hero_subtitulo'] ?? '') ?>">
    </div>
    <div class="form-row">
      <div class="form-group"><label>Número de títulos</label><input class="form-control" type="number" min="0" name="stat_titulos" value="<?= e($settings['stat_titulos'] ?? '0') ?>"></div>
      <div class="form-group"><label>Ano de fundação</label><input class="form-control" type="text" name="stat_fundacao" value="<?= e($settings['stat_fundacao'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="admin-form-card" style="max-width:none; margin-bottom:24px;">
    <h3 style="margin-bottom:6px;">Transmissão ao vivo</h3>
    <p class="form-hint" style="margin-bottom:16px;">Configure aqui o vídeo/canal que aparece na seção "Ao Vivo e Próximos Jogos" da home e da página de Partidas.</p>
    <div class="form-row">
      <div class="form-group">
        <label>Plataforma</label>
        <select class="form-control" name="livestream_tipo">
          <option value="nenhuma" <?= ($settings['livestream_tipo'] ?? '') === 'nenhuma' ? 'selected' : '' ?>>Nenhuma (sem transmissão agora)</option>
          <option value="twitch" <?= ($settings['livestream_tipo'] ?? '') === 'twitch' ? 'selected' : '' ?>>Twitch</option>
          <option value="youtube" <?= ($settings['livestream_tipo'] ?? '') === 'youtube' ? 'selected' : '' ?>>YouTube</option>
        </select>
      </div>
      <div class="form-group">
        <label>Nome do canal (Twitch) ou ID do vídeo (YouTube)</label>
        <input class="form-control" type="text" name="livestream_id" value="<?= e($settings['livestream_id'] ?? '') ?>" placeholder="ex: mousetrapesports  ou  dQw4w9WgXcQ">
      </div>
    </div>
    <p class="form-hint">Twitch: use apenas o nome do canal (ex: "mousetrapesports"). YouTube: use o ID do vídeo (os 11 caracteres depois de "v=" no link).</p>
  </div>

  <div class="admin-form-card" style="max-width:none; margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Contato</h3>
    <div class="form-row">
      <div class="form-group"><label>E-mail de contato</label><input class="form-control" type="email" name="contato_email" value="<?= e($settings['contato_email'] ?? '') ?>"></div>
      <div class="form-group"><label>Telefone</label><input class="form-control" type="text" name="contato_telefone" value="<?= e($settings['contato_telefone'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Endereço</label><input class="form-control" type="text" name="contato_endereco" value="<?= e($settings['contato_endereco'] ?? '') ?>"></div>
  </div>

  <div class="admin-form-card" style="max-width:none; margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Redes sociais</h3>
    <div class="form-row">
      <div class="form-group"><label>Instagram (link)</label><input class="form-control" type="url" name="social_instagram" value="<?= e($settings['social_instagram'] ?? '') ?>"></div>
      <div class="form-group"><label>X/Twitter (link)</label><input class="form-control" type="url" name="social_twitter" value="<?= e($settings['social_twitter'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Twitch (link)</label><input class="form-control" type="url" name="social_twitch" value="<?= e($settings['social_twitch'] ?? '') ?>"></div>
      <div class="form-group"><label>YouTube (link)</label><input class="form-control" type="url" name="social_youtube" value="<?= e($settings['social_youtube'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="admin-form-card" style="max-width:none; margin-bottom:24px;">
    <h3 style="margin-bottom:6px;">Análise de Demos / IA</h3>
    <p class="form-hint" style="margin-bottom:16px;">
      A Locaweb não roda Node. O parser <code>cs2parser</code> fica no worker <code>demo-analyzer</code>
      (PC/VPS). A chave OpenAI gera o relatório tático e o chat.
    </p>
    <div class="form-group">
      <label>OpenAI API Key</label>
      <input class="form-control" type="password" name="openai_api_key" value="<?= e($settings['openai_api_key'] ?? '') ?>" placeholder="sk-..." autocomplete="off">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Modelo OpenAI</label>
        <input class="form-control" type="text" name="openai_model" value="<?= e($settings['openai_model'] ?? 'gpt-4o-mini') ?>">
      </div>
      <div class="form-group">
        <label>URL do Demo Analyzer (Node)</label>
        <input class="form-control" type="url" name="demo_analyzer_url" value="<?= e($settings['demo_analyzer_url'] ?? 'http://127.0.0.1:5055') ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Secret do Demo Analyzer</label>
      <input class="form-control" type="text" name="demo_analyzer_secret" value="<?= e($settings['demo_analyzer_secret'] ?? 'mousetrap-demo-secret') ?>">
      <div class="form-hint">Deve ser igual ao <code>DEMO_ANALYZER_SECRET</code> do worker Node.</div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Salvar configurações</button>
</form>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
