<?php
require_once __DIR__ . '/includes/functions.php';
$tituloPagina = 'Notícias';

$porPagina = 9;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * $porPagina;

$categoriaFiltro = trim($_GET['categoria'] ?? '');

$where = "WHERE status = 'publicado'";
$params = [];
if ($categoriaFiltro !== '') {
    $where .= ' AND categoria = :categoria';
    $params['categoria'] = $categoriaFiltro;
}

$total = db()->prepare("SELECT COUNT(*) FROM noticias {$where}");
$total->execute($params);
$totalNoticias = (int) $total->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalNoticias / $porPagina));

$stmt = db()->prepare("SELECT * FROM noticias {$where} ORDER BY criado_em DESC LIMIT :limite OFFSET :offset");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$noticias = $stmt->fetchAll();

$categorias = db()->query("SELECT DISTINCT categoria FROM noticias WHERE status = 'publicado' ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <span class="section-tag">Fique por dentro</span>
    <h1>Notícias</h1>
    <p>Últimas novidades, resultados e bastidores da MouseTrap Esports.</p>
  </div>
</section>

<section>
  <div class="container">

    <?php if (!empty($categorias)): ?>
      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:32px;">
        <a href="<?= SITE_URL ?>/noticias.php" class="btn btn-sm <?= $categoriaFiltro === '' ? 'btn-primary' : 'btn-outline' ?>">Todas</a>
        <?php foreach ($categorias as $cat): ?>
          <a href="<?= SITE_URL ?>/noticias.php?categoria=<?= urlencode($cat) ?>" class="btn btn-sm <?= $categoriaFiltro === $cat ? 'btn-primary' : 'btn-outline' ?>"><?= e($cat) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($noticias)): ?>
      <div class="empty-state">Nenhuma notícia encontrada.</div>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($noticias as $n): ?>
          <a href="<?= SITE_URL ?>/noticia.php?slug=<?= e($n['slug']) ?>" class="card news-card">
            <img src="<?= url_upload($n['imagem_capa'], 'noticias') ?>" alt="<?= e($n['titulo']) ?>">
            <div class="card-body">
              <span class="news-tag"><?= e($n['categoria']) ?></span>
              <h3><?= e($n['titulo']) ?></h3>
              <p style="color:var(--muted); font-size:0.9rem; margin-bottom:10px;"><?= e($n['resumo']) ?></p>
              <span class="news-date"><?= formatar_data($n['criado_em']) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPaginas > 1): ?>
        <div style="display:flex; justify-content:center; gap:8px; margin-top:40px;">
          <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <a href="<?= SITE_URL ?>/noticias.php?pagina=<?= $p ?><?= $categoriaFiltro ? '&categoria=' . urlencode($categoriaFiltro) : '' ?>"
               class="btn btn-sm <?= $p === $pagina ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
