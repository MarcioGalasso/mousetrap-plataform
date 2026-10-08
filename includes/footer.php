<?php $settings = get_settings(); ?>
</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <a href="<?= SITE_URL ?>/index.php" class="logo">
        <img src="<?= SITE_URL ?>/assets/img/logo.png" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="MouseTrap Esports">
        <span class="logo-text">MOUSE<strong>TRAP</strong><small>ESPORTS</small></span>
      </a>
      <p class="footer-tagline">"<?= e(get_setting('hero_titulo', 'Uma armadilha para gigantes')) ?>"</p>
      <div class="social-icons">
        <?php if (!empty($settings['social_instagram'])): ?><a href="<?= e($settings['social_instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram">IG</a><?php endif; ?>
        <?php if (!empty($settings['social_twitter'])): ?><a href="<?= e($settings['social_twitter']) ?>" target="_blank" rel="noopener" aria-label="X/Twitter">X</a><?php endif; ?>
        <?php if (!empty($settings['social_twitch'])): ?><a href="<?= e($settings['social_twitch']) ?>" target="_blank" rel="noopener" aria-label="Twitch">TV</a><?php endif; ?>
        <?php if (!empty($settings['social_youtube'])): ?><a href="<?= e($settings['social_youtube']) ?>" target="_blank" rel="noopener" aria-label="YouTube">YT</a><?php endif; ?>
      </div>
    </div>

    <div class="footer-col">
      <h4>Navegação</h4>
      <a href="<?= SITE_URL ?>/index.php">Home</a>
      <a href="<?= SITE_URL ?>/sobre.php">Sobre</a>
      <a href="<?= SITE_URL ?>/time.php">Time</a>
      <a href="<?= SITE_URL ?>/partidas.php">Partidas</a>
    </div>

    <div class="footer-col">
      <h4>Institucional</h4>
      <a href="<?= SITE_URL ?>/noticias.php">Notícias</a>
      <a href="<?= SITE_URL ?>/contato.php">Contato</a>
    </div>

    <div class="footer-col">
      <h4>Contato</h4>
      <p><?= e($settings['contato_endereco'] ?? '') ?></p>
      <p><a href="mailto:<?= e($settings['contato_email'] ?? '') ?>"><?= e($settings['contato_email'] ?? '') ?></a></p>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= SITE_NOME ?>. Todos os direitos reservados.</p>
  </div>
</footer>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
