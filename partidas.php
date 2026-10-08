<?php
require_once __DIR__ . '/includes/functions.php';
$tituloPagina = 'Partidas';
$settings = get_settings();

$proximasPartidas = db()->query(
    "SELECT * FROM partidas WHERE status = 'agendada' ORDER BY data_hora ASC"
)->fetchAll();

$resultados = db()->query(
    "SELECT * FROM partidas WHERE status = 'finalizada' ORDER BY data_hora DESC"
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <span class="section-tag">Calendário</span>
    <h1>Partidas</h1>
    <p>Próximos jogos, transmissões ao vivo e histórico de resultados da MouseTrap.</p>
  </div>
</section>

<section id="ao-vivo">
  <div class="container">
    <div class="live-section">
      <div class="live-embed">
        <?php if ($settings['livestream_tipo'] === 'twitch' && !empty($settings['livestream_id'])): ?>
          <span class="live-badge">&#9679; LIVE</span>
          <iframe src="https://player.twitch.tv/?channel=<?= e($settings['livestream_id']) ?>&parent=<?= e(parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') ?>&muted=false" allowfullscreen></iframe>
        <?php elseif ($settings['livestream_tipo'] === 'youtube' && !empty($settings['livestream_id'])): ?>
          <span class="live-badge">&#9679; LIVE</span>
          <iframe src="https://www.youtube.com/embed/<?= e($settings['livestream_id']) ?>?autoplay=0" allowfullscreen></iframe>
        <?php else: ?>
          <div class="live-placeholder">
            <span style="font-size:2rem;">&#128250;</span>
            <p>Nenhuma transmissão ao vivo agora.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="upcoming-list">
        <h3 style="font-size:1.1rem; margin-bottom:6px;">Próximos Jogos</h3>
        <?php if (empty($proximasPartidas)): ?>
          <div class="empty-state">Nenhum jogo agendado no momento.</div>
        <?php endif; ?>
        <?php foreach ($proximasPartidas as $p): ?>
          <div class="match-card">
            <div class="match-teams">
              <img src="<?= url_upload($p['adversario_logo'], 'adversarios') ?>" alt="">
              <span>MOUSETRAP vs <?= e($p['adversario']) ?></span>
            </div>
            <div class="match-meta">
              <span class="match-competition"><?= e($p['campeonato']) ?></span>
              <div class="match-date"><?= formatar_data_hora($p['data_hora']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <h2 class="section-title">Últimos Resultados</h2>
    </div>
    <?php if (empty($resultados)): ?>
      <div class="empty-state">Ainda não há resultados registrados.</div>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($resultados as $r): ?>
          <div class="result-card">
            <div class="match-teams">
              <img src="<?= SITE_URL ?>/assets/img/logo.png" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="MouseTrap">
              <span class="team-name" title="MouseTrap Esports">MOUSETRAP</span>
            </div>
            <div>
              <div class="score"><?= (int) $r['placar_mousetrap'] ?> - <?= (int) $r['placar_adversario'] ?></div>
              <div class="match-date text-center"><?= formatar_data($r['data_hora']) ?></div>
            </div>
            <div class="match-teams">
              <img src="<?= url_upload($r['adversario_logo'], 'adversarios') ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="<?= e($r['adversario']) ?>">
              <span class="team-name" title="<?= e($r['adversario']) ?>"><?= e($r['adversario']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
