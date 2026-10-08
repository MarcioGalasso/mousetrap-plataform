<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();

$id = (int) ($_GET['id'] ?? 0);

if ($id && csrf_valido($_GET['token'] ?? null)) {
    $stmt = db()->prepare('SELECT imagem_capa FROM noticias WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $noticia = $stmt->fetch();

    $stmtGaleria = db()->prepare('SELECT imagem FROM noticias_galeria WHERE noticia_id = :id');
    $stmtGaleria->execute(['id' => $id]);
    $imagensGaleria = $stmtGaleria->fetchAll(PDO::FETCH_COLUMN);

    db()->prepare('DELETE FROM noticias WHERE id = :id')->execute(['id' => $id]);

    if ($noticia && !empty($noticia['imagem_capa'])) {
        $caminho = UPLOADS_DIR . '/noticias/' . $noticia['imagem_capa'];
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }

    foreach ($imagensGaleria as $imagem) {
        $caminho = UPLOADS_DIR . '/noticias/' . $imagem;
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }
}

redirecionar(SITE_URL . '/admin/noticias/index.php?excluido=1');
