<?php
/**
 * RESET TEMPORÁRIO DE SENHA DO ADMIN
 * 1) Suba este arquivo via FileZilla para /admin/reset-senha.php
 * 2) Abra no navegador (veja a URL abaixo)
 * 3) APAGUE este arquivo depois de usar (obrigatório por segurança)
 */
require_once __DIR__ . '/../includes/functions.php';

// Troque estes valores se quiser
$usuarioNovo = 'admin';
$senhaNova   = 'MouseTrap2026!';

$mensagem = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirmar'] ?? '') === '1') {
    $hash = password_hash($senhaNova, PASSWORD_DEFAULT);

    $stmt = db()->query('SELECT id, username FROM admins ORDER BY id ASC LIMIT 1');
    $admin = $stmt->fetch();

    if ($admin) {
        $upd = db()->prepare('UPDATE admins SET username = :u, password_hash = :p WHERE id = :id');
        $upd->execute([
            'u' => $usuarioNovo,
            'p' => $hash,
            'id' => $admin['id'],
        ]);
        $mensagem = 'Senha redefinida com sucesso para o usuário existente.';
    } else {
        $ins = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (:u, :p)');
        $ins->execute(['u' => $usuarioNovo, 'p' => $hash]);
        $mensagem = 'Nenhum admin existia. Conta criada com sucesso.';
    }
    $ok = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset senha admin – MouseTrap</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/admin/admin.css">
</head>
<body class="admin-body">
<div class="login-wrap">
  <div class="login-card">
    <h2 style="margin-bottom:12px;">Reset de senha</h2>

    <?php if ($ok): ?>
      <div class="alert alert-success"><?= e($mensagem) ?></div>
      <p style="margin:16px 0; line-height:1.6;">
        <strong>Usuário:</strong> <?= e($usuarioNovo) ?><br>
        <strong>Senha:</strong> <?= e($senhaNova) ?>
      </p>
      <p style="color:#e5484d; font-weight:700; margin-bottom:16px;">
        APAGUE o arquivo admin/reset-senha.php no FileZilla agora.
      </p>
      <a class="btn btn-primary" href="<?= SITE_URL ?>/admin/login.php">Ir para o login</a>
    <?php else: ?>
      <p style="color:var(--muted); margin-bottom:18px;">
        Isso vai definir o login do painel como:<br>
        <strong>Usuário:</strong> <?= e($usuarioNovo) ?><br>
        <strong>Senha:</strong> <?= e($senhaNova) ?>
      </p>
      <form method="post">
        <input type="hidden" name="confirmar" value="1">
        <button type="submit" class="btn btn-primary">Redefinir senha agora</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
