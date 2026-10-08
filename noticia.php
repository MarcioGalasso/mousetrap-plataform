<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$stmt = db()->prepare("SELECT * FROM noticias WHERE slug = :slug AND status = 'publicado'");
$stmt->execute(['slug' => $slug]);
$noticia = $stmt->fetch();

if (!$noticia) {
    http_response_code(404);
    $tituloPagina = 'Notícia não encontrada';
    require_once __DIR__ . '/includes/header.php';
    echo '<section><div class="container empty-state"><h2>Notícia não encontrada</h2><p><a href="' . SITE_URL . '/noticias.php">Voltar para notícias</a></p></div></section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$tituloPagina = $noticia['titulo'];
$descricaoPagina = $noticia['resumo'];

$stmtGaleria = db()->prepare('SELECT * FROM noticias_galeria WHERE noticia_id = :id ORDER BY ordem ASC, id ASC');
$stmtGaleria->execute(['id' => $noticia['id']]);
$galeria = $stmtGaleria->fetchAll();

$relacionadas = db()->prepare(
    "SELECT * FROM noticias WHERE status = 'publicado' AND id != :id ORDER BY criado_em DESC LIMIT 3"
);
$relacionadas->execute(['id' => $noticia['id']]);
$relacionadas = $relacionadas->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <span class="section-tag"><?= e($noticia['categoria']) ?></span>
    <h1 style="font-size:2rem;"><?= e($noticia['titulo']) ?></h1>
    <p>Publicado em <?= formatar_data($noticia['criado_em']) ?></p>
  </div>
</section>

<section>
  <div class="container article-body">
    <?php if (!empty($galeria)): ?>
      <div class="carousel article-gallery" id="carousel-noticia">
        <div class="carousel-track">
          <div class="carousel-slide is-active">
            <img src="<?= url_upload($noticia['imagem_capa'], 'noticias') ?>" alt="<?= e($noticia['titulo']) ?>">
          </div>
          <?php foreach ($galeria as $img): ?>
            <div class="carousel-slide">
              <img src="<?= url_upload($img['imagem'], 'noticias') ?>" alt="<?= e($noticia['titulo']) ?>">
            </div>
          <?php endforeach; ?>
        </div>
        <div class="carousel-dots"></div>
      </div>
    <?php else: ?>
      <img class="cover" src="<?= url_upload($noticia['imagem_capa'], 'noticias') ?>" alt="<?= e($noticia['titulo']) ?>">
    <?php endif; ?>
    <div class="content"><?= $noticia['conteudo'] ?></div>
  </div>
</section>

<?php if (!empty($relacionadas)): ?>
<section style="padding-top:0;">
  <div class="container">
    <div class="section-head"><h2 class="section-title">Leia também</h2></div>
    <div class="grid grid-3">
      <?php foreach ($relacionadas as $n): ?>
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
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
