<?php
require_once __DIR__ . '/includes/functions.php';
$tituloPagina = 'Contato';
$settings = get_settings();

$sucesso = false;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // honeypot simples anti-spam: campo invisível que bots tendem a preencher
    if (!empty($_POST['website'])) {
        $sucesso = true; // finge sucesso para o bot, mas não envia nada
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');

        if ($nome === '' || $email === '' || $mensagem === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Preencha nome, e-mail válido e mensagem.';
        } else {
            $destino = $settings['contato_email'] ?? '';
            $assunto = 'Novo contato pelo site - ' . $nome;
            $corpo = "Nome: {$nome}\nE-mail: {$email}\n\nMensagem:\n{$mensagem}";
            $headers = 'From: contato@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') . "\r\nReply-To: {$email}";

            if ($destino && mail($destino, $assunto, $corpo, $headers)) {
                $sucesso = true;
            } else {
                $erro = 'Não foi possível enviar sua mensagem agora. Tente novamente mais tarde ou fale com a gente pelas redes sociais.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <span class="section-tag">Fale com a gente</span>
    <h1>Contato</h1>
  </div>
</section>

<section>
  <div class="container" style="max-width:640px;">

    <?php if ($sucesso): ?>
      <div class="alert alert-success">Mensagem enviada com sucesso! Vamos responder em breve.</div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert alert-error"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">

      <div class="form-group">
        <label for="nome">Nome</label>
        <input class="form-control" type="text" id="nome" name="nome" required>
      </div>
      <div class="form-group">
        <label for="email">E-mail</label>
        <input class="form-control" type="email" id="email" name="email" required>
      </div>
      <div class="form-group">
        <label for="mensagem">Mensagem</label>
        <textarea class="form-control" id="mensagem" name="mensagem" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Enviar mensagem</button>
    </form>

    <div class="mt-40">
      <p style="color:var(--muted)"><?= e($settings['contato_endereco'] ?? '') ?></p>
      <p><a href="mailto:<?= e($settings['contato_email'] ?? '') ?>" style="color:var(--gold)"><?= e($settings['contato_email'] ?? '') ?></a></p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
