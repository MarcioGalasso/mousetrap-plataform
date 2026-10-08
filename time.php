<?php
require_once __DIR__ . '/includes/functions.php';
$tituloPagina = 'Time';

$jogadores = db()->query(
    "SELECT * FROM jogadores WHERE ativo = 1 ORDER BY ordem ASC, id ASC"
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <span class="section-tag">Elenco</span>
    <h1>Nosso Time</h1>
    <p>Os atletas que representam a MouseTrap Esports dentro e fora dos servidores.</p>
  </div>
</section>

<section>
  <div class="container">
    <?php if (empty($jogadores)): ?>
      <div class="empty-state">O elenco será cadastrado em breve pelo painel administrativo.</div>
    <?php else: ?>
      <div class="grid grid-5">
        <?php foreach ($jogadores as $j): ?>
          <div class="card player-card">
            <img src="<?= url_upload($j['foto'], 'jogadores') ?>" alt="<?= e($j['nickname']) ?>">
            <div class="nickname"><?= e($j['nickname']) ?></div>
            <div class="role"><?= e($j['funcao']) ?></div>
            <?php if (!empty($j['nome_real'])): ?><div class="match-date"><?= e($j['nome_real']) ?></div><?php endif; ?>
            <div class="player-social">
              <?php if (!empty($j['instagram'])): ?><a href="<?= e($j['instagram']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
              <?php if (!empty($j['twitter'])): ?><a href="<?= e($j['twitter']) ?>" target="_blank" rel="noopener">X</a><?php endif; ?>
              <?php if (!empty($j['twitch'])): ?><a href="<?= e($j['twitch']) ?>" target="_blank" rel="noopener">Twitch</a><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
