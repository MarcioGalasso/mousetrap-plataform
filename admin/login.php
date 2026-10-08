<?php
require_once __DIR__ . '/includes/admin_auth.php';

if (admin_logado()) {
    redirecionar(SITE_URL . '/admin/index.php');
}

$erro = '';
$criado = isset($_GET['criado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = db()->prepare('SELECT * FROM admins WHERE username = :u');
    $stmt->execute(['u' => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($senha, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        redirecionar(SITE_URL . '/admin/index.php');
    } else {
        $erro = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – Painel MouseTrap</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/admin/admin.css">
</head>
<body class="admin-body">
<div class="login-wrap">
  <div class="login-card">
    <img src="<?= SITE_URL ?>/assets/img/logo.png" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="">
    <h2 style="margin-bottom:20px;">Painel MouseTrap</h2>

    <?php if ($criado): ?><div class="alert alert-success">Usuário criado! Faça login abaixo.</div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>

    <form method="post" style="text-align:left;">
      <div class="form-group">
        <label>Usuário</label>
        <input class="form-control" type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input class="form-control" type="password" name="senha" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Entrar</button>
    </form>
  </div>
</div>
</body>
</html>
