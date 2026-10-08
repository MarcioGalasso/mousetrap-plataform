<?php
require_once __DIR__ . '/includes/functions.php';

$tituloPagina = null; // usa o título padrão do site na home

$settings = get_settings();

$proximasPartidas = db()->query(
    "SELECT * FROM partidas WHERE status = 'agendada' ORDER BY data_hora ASC LIMIT 3"
)->fetchAll();

$ultimosResultados = db()->query(
    "SELECT * FROM partidas WHERE status = 'finalizada' ORDER BY data_hora DESC LIMIT 3"
)->fetchAll();

$ultimasNoticias = db()->query(
    "SELECT * FROM noticias WHERE status = 'publicado' ORDER BY criado_em DESC LIMIT 3"
)->fetchAll();

$jogadores = db()->query(
    "SELECT * FROM jogadores WHERE ativo = 1 ORDER BY ordem ASC, id ASC LIMIT 5"
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-inner">
    <div>
      <span class="hero-tag">&#9733; CS2</span>
      <h1>
        <?php
          $heroTitulo = get_setting('hero_titulo', 'UMA ARMADILHA PARA GIGANTES');
          $palavras = explode(' ', $heroTitulo);
          $meio = (int) ceil(count($palavras) / 2);
          echo e(implode(' ', array_slice($palavras, 0, $meio)));
          echo '<span class="accent">' . e(implode(' ', array_slice($palavras, $meio))) . '</span>';
        ?>
      </h1>
      <p class="lead"><?= e(get_setting('hero_subtitulo', 'Foco. Disciplina. Evolução.')) ?></p>
      <div class="hero-actions">
        <a href="#ao-vivo" class="btn btn-primary">&#9654; Assista aos Jogos</a>
        <a href="<?= SITE_URL ?>/time.php" class="btn btn-outline">Conheça o Time</a>
      </div>

      <div class="stats-bar">
        <div class="stat-card">
          <span class="stat-icon">&#127942;</span>
          <div>
            <div class="stat-label">Títulos</div>
            <div class="stat-value"><?= e(get_setting('stat_titulos', '0')) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon">&#128197;</span>
          <div>
            <div class="stat-label">Fundação</div>
            <div class="stat-value"><?= e(get_setting('stat_fundacao', '2024')) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="hero-media">
      <div class="carousel" id="carousel-gamehouse">
        <div class="carousel-track">
          <div class="carousel-slide is-active">
            <img src="<?= SITE_URL ?>/assets/img/gamehouse_exterior.jpg" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="Fachada da futura gamehouse MouseTrap">
            <div class="carousel-caption">Fachada da gamehouse</div>
          </div>
          <div class="carousel-slide">
            <img src="<?= SITE_URL ?>/assets/img/carrocel1.jpg" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="Nossa Jersey">
            <div class="carousel-caption">Nossa Jersey</div>
          </div>
          <div class="carousel-slide">
            <img src="<?= SITE_URL ?>/assets/img/carrocel2.jpg" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="Estações de treino MouseTrap">
            <div class="carousel-caption">Estações de treino</div>
          </div>
          <div class="carousel-slide">
            <img src="<?= SITE_URL ?>/assets/img/carrocel4.jpg" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="Refeitório da gamehouse MouseTrap">
            <div class="carousel-caption">Foco</div>
          </div>
          <div class="carousel-slide">
            <img src="<?= SITE_URL ?>/assets/img/carrocel5.jpg" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="Espaço gamer da gamehouse MouseTrap">
            <div class="carousel-caption">Jersey</div>
          </div>
          <div class="carousel-slide">
            <img src="<?= SITE_URL ?>/assets/img/carrocel6.jpg" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="Ambiente da gamehouse MouseTrap">
            <div class="carousel-caption">Rato</div>
          </div>
        </div>
        <div class="carousel-dots"></div>
      </div>
    </div>
  </div>
</section>

<section id="ao-vivo">
  <div class="container">
    <span class="section-tag">Ao vivo e próximos jogos</span>
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
            <p>Nenhuma transmissão ao vivo agora.<br>Confira os próximos jogos ao lado.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="upcoming-list">
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
        <a href="<?= SITE_URL ?>/partidas.php" class="btn btn-outline btn-sm text-center">Ver calendário completo</a>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($ultimosResultados)): ?>
<section style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <h2 class="section-title">Últimos Resultados</h2>
      <a href="<?= SITE_URL ?>/partidas.php">Ver todos os resultados &rarr;</a>
    </div>
    <div class="grid grid-3">
      <?php foreach ($ultimosResultados as $r): ?>
        <div class="result-card">
          <div class="match-teams">
            <img src="<?= SITE_URL ?>/assets/img/logo.png" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="MouseTrap">
            <span class="team-name" title="MouseTrap Esports">MOUSETRAP</span>
          </div>
          <div class="score"><?= (int) $r['placar_mousetrap'] ?> - <?= (int) $r['placar_adversario'] ?></div>
          <div class="match-teams">
            <img src="<?= url_upload($r['adversario_logo'], 'adversarios') ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="<?= e($r['adversario']) ?>">
            <span class="team-name" title="<?= e($r['adversario']) ?>"><?= e($r['adversario']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section>
  <div class="container">
    <div class="section-head">
      <h2 class="section-title">Últimas Notícias</h2>
      <a href="<?= SITE_URL ?>/noticias.php">Ver todas as notícias &rarr;</a>
    </div>

    <?php if (empty($ultimasNoticias)): ?>
      <div class="empty-state">Nenhuma notícia publicada ainda. Assim que publicarmos algo pelo painel, aparece aqui!</div>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($ultimasNoticias as $n): ?>
          <a href="<?= SITE_URL ?>/noticia.php?slug=<?= e($n['slug']) ?>" class="card news-card">
            <img src="<?= url_upload($n['imagem_capa'], 'noticias') ?>" alt="<?= e($n['titulo']) ?>">
            <div class="card-body">
              <span class="news-tag"><?= e($n['categoria']) ?></span>
              <h3><?= e($n['titulo']) ?></h3>
              <span class="news-date"><?= formatar_data($n['criado_em']) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section>
  <div class="container">
    <div class="section-head">
      <h2 class="section-title">Nosso Time</h2>
      <a href="<?= SITE_URL ?>/time.php">Ver elenco completo &rarr;</a>
    </div>

    <?php if (empty($jogadores)): ?>
      <div class="empty-state">O elenco será cadastrado em breve pelo painel administrativo.</div>
    <?php else: ?>
      <div class="grid grid-5">
        <?php foreach ($jogadores as $j): ?>
          <div class="card player-card">
            <img src="<?= url_upload($j['foto'], 'jogadores') ?>" alt="<?= e($j['nickname']) ?>">
            <div class="nickname"><?= e($j['nickname']) ?></div>
            <div class="role"><?= e($j['funcao']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section style="padding-top:0;">
  <div class="container">
    <div class="section-head"><h2 class="section-title">Parceiros</h2></div>
    <div class="partners-strip">
          <img src="<?= SITE_URL ?>/assets/img/patrocinio1.png" onerror="this.style.display='none'" alt="Engenharia da Ilha Construtora">
          <img src="<?= SITE_URL ?>/assets/img/patrocinio2.png" onerror="this.style.display='none'" alt="MAP Outmind Technology">
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
