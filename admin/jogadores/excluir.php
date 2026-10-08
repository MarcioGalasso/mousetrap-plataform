<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();

$id = (int) ($_GET['id'] ?? 0);

if ($id && csrf_valido($_GET['token'] ?? null)) {
    $stmt = db()->prepare('SELECT foto FROM jogadores WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $jogador = $stmt->fetch();

    db()->prepare('DELETE FROM jogadores WHERE id = :id')->execute(['id' => $id]);

    if ($jogador && !empty($jogador['foto'])) {
        $caminho = UPLOADS_DIR . '/jogadores/' . $jogador['foto'];
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }
}

redirecionar(SITE_URL . '/admin/jogadores/index.php?excluido=1');
