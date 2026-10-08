<?php
/**
 * Configuração inicial do painel — cria o PRIMEIRO usuário administrador.
 * Por segurança, este script se bloqueia automaticamente depois que já
 * existir pelo menos um admin cadastrado no banco.
 */
require_once __DIR__ . '/../includes/functions.php';

$existeAdmin = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;

$erro = '';

if (!$existeAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($username === '' || $senha === '') {
        $erro = 'Preencha usuário e senha.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $stmt = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (:u, :p)');
        $stmt->execute(['u' => $username, 'p' => password_hash($senha, PASSWORD_DEFAULT)]);
        redirecionar(SITE_URL . '/admin/login.php?criado=1');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuração inicial – Painel MouseTrap</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/admin/admin.css">
</head>
<body class="admin-body">
<div class="login-wrap">
  <div class="login-card">
    <img src="<?= SITE_URL ?>/assets/img/logo.png" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="">
    <h2 style="margin-bottom:6px;">Configuração inicial</h2>

    <?php if ($existeAdmin): ?>
      <p style="color:var(--muted); margin:16px 0;">Já existe um administrador cadastrado. Por segurança, esta página está desabilitada.</p>
      <a href="<?= SITE_URL ?>/admin/login.php" class="btn btn-primary">Ir para o login</a>
    <?php else: ?>
      <p style="color:var(--muted); margin-bottom:20px;">Crie o primeiro usuário do painel administrativo.</p>
      <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>
      <form method="post" style="text-align:left;">
        <div class="form-group">
          <label>Usuário</label>
          <input class="form-control" type="text" name="username" required>
        </div>
        <div class="form-group">
          <label>Senha (mín. 8 caracteres)</label>
          <input class="form-control" type="password" name="senha" required>
        </div>
        <div class="form-group">
          <label>Confirmar senha</label>
          <input class="form-control" type="password" name="confirmar" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Criar usuário</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
