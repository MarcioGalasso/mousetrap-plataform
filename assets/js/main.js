// Fecha o menu mobile ao clicar em um link
document.querySelectorAll('.main-nav a').forEach(function (link) {
  link.addEventListener('click', function () {
    document.querySelector('.main-nav').classList.remove('is-open');
  });
});

// Confirmação padrão para ações de excluir no painel admin
document.querySelectorAll('[data-confirm]').forEach(function (el) {
  el.addEventListener('click', function (e) {
    if (!confirm(el.getAttribute('data-confirm') || 'Tem certeza?')) {
      e.preventDefault();
    }
  });
});

// Carrossel automático (usado na home para as fotos da gamehouse)
document.querySelectorAll('.carousel').forEach(function (carousel) {
  var slides = carousel.querySelectorAll('.carousel-slide');
  var dotsWrap = carousel.querySelector('.carousel-dots');
  if (!slides.length) return;

  var current = 0;
  var timer = null;

  slides.forEach(function (_, i) {
    var dot = document.createElement('button');
    if (i === 0) dot.classList.add('is-active');
    dot.setAttribute('aria-label', 'Ir para o slide ' + (i + 1));
    dot.addEventListener('click', function () {
      goTo(i);
      restart();
    });
    dotsWrap.appendChild(dot);
  });
  var dots = dotsWrap.querySelectorAll('button');

  function goTo(index) {
    slides[current].classList.remove('is-active');
    dots[current].classList.remove('is-active');
    current = index;
    slides[current].classList.add('is-active');
    dots[current].classList.add('is-active');
  }

  function next() {
    goTo((current + 1) % slides.length);
  }

  function restart() {
    if (timer) clearInterval(timer);
    timer = setInterval(next, 5000);
  }

  restart();
});
