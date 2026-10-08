<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$noticia = ['titulo' => '', 'categoria' => 'Geral', 'resumo' => '', 'conteudo' => '', 'status' => 'rascunho', 'imagem_capa' => null];
$erro = '';
$galeria = [];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM noticias WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $encontrada = $stmt->fetch();
    if (!$encontrada) {
        redirecionar(SITE_URL . '/admin/noticias/index.php');
    }
    $noticia = $encontrada;

    $stmtGaleria = db()->prepare('SELECT * FROM noticias_galeria WHERE noticia_id = :id ORDER BY ordem ASC, id ASC');
    $stmtGaleria->execute(['id' => $id]);
    $galeria = $stmtGaleria->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $categoria = trim($_POST['categoria'] ?? 'Geral');
    $resumo = trim($_POST['resumo'] ?? '');
    $conteudo = $_POST['conteudo'] ?? '';
    $status = ($_POST['status'] ?? 'rascunho') === 'publicado' ? 'publicado' : 'rascunho';

    if (!csrf_valido($_POST['token'] ?? null)) {
        $erro = 'Sessão expirada, tente novamente.';
    } else {
        if ($titulo === '') {
            $erro = 'O título é obrigatório.';
        } else {
            try {
                $imagemCapa = $noticia['imagem_capa'] ?? null;
                $novoUpload = salvar_upload_imagem($_FILES['imagem_capa'] ?? [], 'noticias');
                if ($novoUpload) {
                    $imagemCapa = $novoUpload;
                }

                if ($id) {
                    $slug = slug_unico('noticias', gerar_slug($titulo), $id);
                    $stmt = db()->prepare(
                        'UPDATE noticias SET titulo=:titulo, slug=:slug, categoria=:categoria, resumo=:resumo, conteudo=:conteudo, imagem_capa=:imagem, status=:status WHERE id=:id'
                    );
                    $stmt->execute([
                        'titulo' => $titulo, 'slug' => $slug, 'categoria' => $categoria,
                        'resumo' => $resumo, 'conteudo' => $conteudo, 'imagem' => $imagemCapa,
                        'status' => $status, 'id' => $id,
                    ]);
                    $noticiaId = $id;
                } else {
                    $slug = slug_unico('noticias', gerar_slug($titulo));
                    $stmt = db()->prepare(
                        'INSERT INTO noticias (titulo, slug, categoria, resumo, conteudo, imagem_capa, status) VALUES (:titulo,:slug,:categoria,:resumo,:conteudo,:imagem,:status)'
                    );
                    $stmt->execute([
                        'titulo' => $titulo, 'slug' => $slug, 'categoria' => $categoria,
                        'resumo' => $resumo, 'conteudo' => $conteudo, 'imagem' => $imagemCapa,
                        'status' => $status,
                    ]);
                    $noticiaId = (int) db()->lastInsertId();
                }

                // Remove imagens de galeria marcadas para exclusão
                $remover = array_map('intval', $_POST['remover_galeria'] ?? []);
                if (!empty($remover)) {
                    $placeholders = implode(',', array_fill(0, count($remover), '?'));
                    $stmtBuscar = db()->prepare("SELECT * FROM noticias_galeria WHERE id IN ({$placeholders}) AND noticia_id = ?");
                    $stmtBuscar->execute(array_merge($remover, [$noticiaId]));
                    foreach ($stmtBuscar->fetchAll() as $img) {
                        $caminho = UPLOADS_DIR . '/noticias/' . $img['imagem'];
                        if (is_file($caminho)) {
                            @unlink($caminho);
                        }
                    }
                    $stmtExcluir = db()->prepare("DELETE FROM noticias_galeria WHERE id IN ({$placeholders}) AND noticia_id = ?");
                    $stmtExcluir->execute(array_merge($remover, [$noticiaId]));
                }

                // Adiciona novas imagens à galeria
                $novasImagens = salvar_uploads_multiplos($_FILES['galeria'] ?? [], 'noticias');
                if (!empty($novasImagens)) {
                    $maxOrdem = (int) db()->query("SELECT COALESCE(MAX(ordem), -1) FROM noticias_galeria WHERE noticia_id = {$noticiaId}")->fetchColumn();
                    $stmtGaleriaInsert = db()->prepare('INSERT INTO noticias_galeria (noticia_id, imagem, ordem) VALUES (:noticia_id, :imagem, :ordem)');
                    foreach ($novasImagens as $imagem) {
                        $maxOrdem++;
                        $stmtGaleriaInsert->execute(['noticia_id' => $noticiaId, 'imagem' => $imagem, 'ordem' => $maxOrdem]);
                    }
                }

                redirecionar(SITE_URL . '/admin/noticias/index.php?salvo=1');
            } catch (RuntimeException $e) {
                $erro = $e->getMessage();
            }
        }
    }

    $noticia = array_merge($noticia, ['titulo' => $titulo, 'categoria' => $categoria, 'resumo' => $resumo, 'conteudo' => $conteudo, 'status' => $status]);
}

$tituloAdmin = $id ? 'Editar notícia' : 'Nova notícia';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1><?= $id ? 'Editar Notícia' : 'Nova Notícia' ?></h1>
  <a href="<?= SITE_URL ?>/admin/noticias/index.php" class="btn btn-outline">&larr; Voltar</a>
</div>

<?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

<div class="admin-form-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label>Título</label>
      <input class="form-control" type="text" name="titulo" value="<?= e($noticia['titulo']) ?>" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Categoria</label>
        <input class="form-control" type="text" name="categoria" value="<?= e($noticia['categoria']) ?>" placeholder="Ex: Competição, Equipe, Organização">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select class="form-control" name="status">
          <option value="rascunho" <?= $noticia['status'] === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
          <option value="publicado" <?= $noticia['status'] === 'publicado' ? 'selected' : '' ?>>Publicado</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Resumo (aparece nos cards e nas prévias)</label>
      <textarea class="form-control" name="resumo" rows="2"><?= e($noticia['resumo']) ?></textarea>
    </div>

    <div class="form-group">
      <label>Imagem de capa</label>
      <?php if (!empty($noticia['imagem_capa'])): ?>
        <div class="current-image"><img src="<?= url_upload($noticia['imagem_capa'], 'noticias') ?>" alt=""><span class="form-hint">Imagem atual — envie uma nova para substituir.</span></div>
      <?php endif; ?>
      <input class="form-control" type="file" name="imagem_capa" accept="image/png,image/jpeg,image/webp">
      <div class="form-hint">JPG, PNG ou WEBP, até 4MB.</div>
    </div>

    <div class="form-group">
      <label>Galeria de fotos (carrossel na página da notícia)</label>
      <?php if (!empty($galeria)): ?>
        <div class="gallery-grid" style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
          <?php foreach ($galeria as $img): ?>
            <label style="position:relative; display:block; width:110px; cursor:pointer;">
              <img src="<?= url_upload($img['imagem'], 'noticias') ?>" alt="" style="width:110px; height:80px; object-fit:cover; border-radius:6px; border:1px solid var(--card-border, #333);">
              <span style="display:flex; align-items:center; gap:4px; font-size:0.75rem; margin-top:4px; color:#c0392b;">
                <input type="checkbox" name="remover_galeria[]" value="<?= $img['id'] ?>"> Remover
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <input class="form-control" type="file" name="galeria[]" accept="image/png,image/jpeg,image/webp" multiple>
      <div class="form-hint">Selecione uma ou mais fotos (JPG, PNG ou WEBP, até 4MB cada). Elas aparecem como um carrossel na página da notícia, além da capa.</div>
    </div>

    <div class="form-group">
      <label>Conteúdo</label>
      <div id="editor" style="background:#fff; color:#111; border-radius:6px;"><?= $noticia['conteudo'] ?></div>
      <textarea name="conteudo" id="conteudo-hidden" style="display:none;"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Salvar notícia</button>
  </form>
</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
  var quill = new Quill('#editor', { theme: 'snow' });
  document.querySelector('form').addEventListener('submit', function () {
    document.getElementById('conteudo-hidden').value = quill.root.innerHTML;
  });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
