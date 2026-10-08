<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();

$id = (int) ($_GET['id'] ?? 0);

if ($id && csrf_valido($_GET['token'] ?? null)) {
    $stmt = db()->prepare('SELECT adversario_logo FROM partidas WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $partida = $stmt->fetch();

    db()->prepare('DELETE FROM partidas WHERE id = :id')->execute(['id' => $id]);

    if ($partida && !empty($partida['adversario_logo'])) {
        $caminho = UPLOADS_DIR . '/adversarios/' . $partida['adversario_logo'];
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }
}

redirecionar(SITE_URL . '/admin/partidas/index.php?excluido=1');
