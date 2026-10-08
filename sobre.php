<?php
require_once __DIR__ . '/includes/functions.php';
$tituloPagina = 'Sobre';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <span class="section-tag">Sobre</span>
    <h1>Nossa Jornada e Visão</h1>
  </div>
</section>

<section>
  <div class="container about-block">
    <img src="<?= SITE_URL ?>/assets/img/carrocel3.jpg" onerror="this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'" alt="Estrutura MouseTrap Esports">
    <div>
      <p>A <strong>MouseTrap Esports</strong> nasceu com a missão de transformar talentos em campeões. Acreditamos no poder da disciplina, do treino e da inovação para construir não apenas jogadores, mas <strong>atletas digitais de alto desempenho</strong>.</p>
      <p>Nosso projeto é focado em revelar jovens promessas, oferecer estrutura, suporte técnico e visibilidade para que cada player possa alcançar o seu máximo potencial dentro do competitivo.</p>
      <p>Mais do que um time, somos uma comunidade movida pela <strong>paixão pelo Counter-Strike</strong> e pelo desejo de colocar o Brasil em evidência no cenário mundial. Aqui, cada partida é um passo em direção a um futuro maior — e cada vitória, uma armadilha bem montada.</p>
      <p style="font-family: var(--font-display); text-transform: uppercase; color: var(--gold); font-size: 1.1rem; margin-top: 24px;">MouseTrap Esports — Uma armadilha para gigantes</p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
