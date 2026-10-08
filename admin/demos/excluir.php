<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id && csrf_valido($_GET['token'] ?? null)) {
    $stmt = db()->prepare('SELECT arquivo_demo FROM demos_analises WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    db()->prepare('DELETE FROM demos_analises WHERE id = :id')->execute(['id' => $id]);

    if ($row && !empty($row['arquivo_demo'])) {
        $path = UPLOADS_DIR . '/demos/' . $row['arquivo_demo'];
        if (is_file($path)) @unlink($path);
    }
}

redirecionar(SITE_URL . '/admin/demos/index.php?excluido=1');
