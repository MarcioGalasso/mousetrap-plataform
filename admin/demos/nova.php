<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/demo_ai.php';
exigir_login();

$tituloAdmin = 'Nova análise de demo';

function demo_analyzer_online(): array
{
    $base = rtrim(get_setting('demo_analyzer_url', 'http://127.0.0.1:5055'), '/');
    $ch = curl_init($base . '/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code !== 200) {
        return ['ok' => false, 'url' => $base];
    }
    return ['ok' => true, 'url' => $base];
}

$analyzer = demo_analyzer_online();
$openaiOk = demo_openai_configured();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Nova análise de demo</h1>
  <a href="<?= SITE_URL ?>/admin/demos/index.php" class="btn btn-outline">&larr; Voltar</a>
</div>

<div class="demo-flow-steps">
  <div class="demo-flow-step">1. Envie <strong>.dem</strong> ou JSON local</div>
  <div class="demo-flow-step">2. Parse (Render ou já pronto)</div>
  <div class="demo-flow-step">3. Detecta o <strong>time</strong></div>
  <div class="demo-flow-step">4. Métricas + <strong>IA</strong></div>
</div>

<div class="demo-status-row">
  <span class="demo-status-pill <?= $analyzer['ok'] ? 'is-ok' : 'is-off' ?>">
    Parser: <?= $analyzer['ok'] ? 'online' : 'offline' ?>
  </span>
  <span class="demo-status-pill <?= $openaiOk ? 'is-ok' : 'is-off' ?>">
    IA: <?= $openaiOk ? 'configurada' : 'sem API key' ?>
  </span>
  <span class="form-hint" style="margin:0;"><?= e($analyzer['url']) ?></span>
</div>

<?php if (!$analyzer['ok']): ?>
  <div class="alert alert-error">
    O parser está offline. Confira a URL em Configurações
    (<code>https://mousetrap-demo-analyzer.onrender.com</code>).
  </div>
<?php endif; ?>

<div id="demo-erro" class="alert alert-error" style="display:none;"></div>
<div id="demo-progresso" class="alert alert-success" style="display:none;"></div>

<div class="admin-form-card" style="max-width:820px;">
  <form id="form-nova-demo">
    <input type="hidden" name="token" id="csrf-token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label>Título da análise</label>
      <input class="form-control" type="text" name="titulo" id="campo-titulo" required placeholder="Ex: MouseTrap vs Oddik — Mirage">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Nome do nosso time</label>
        <input class="form-control" type="text" name="time_nosso" id="campo-time" value="MouseTrap">
        <div class="form-hint">Ex.: MouseTrap — aparece no HUD / dashboard.</div>
      </div>
      <div class="form-group">
        <label>Nome do adversário</label>
        <input class="form-control" type="text" name="time_adversario" id="campo-adversario" placeholder="Ex: Arkhadia Gaming">
        <div class="form-hint">Opcional. Se vazio, tenta clan da demo ou o título (… vs Nome).</div>
      </div>
    </div>

    <div class="form-group">
      <label>Identificar jogadores na análise</label>
      <div class="demo-foco-modo">
        <label class="demo-foco-opcao">
          <input type="radio" name="foco_modo" value="cadastro" checked>
          <span>Usar jogadores marcados p/ análise de demo</span>
        </label>
        <label class="demo-foco-opcao">
          <input type="radio" name="foco_modo" value="manual">
          <span>Informar nomes manualmente</span>
        </label>
      </div>
      <div class="form-hint" id="foco-hint-cadastro">
        Cruza a demo com nicks que têm <strong>Analisar na demo</strong> em Admin → Jogadores
        (separado de “Exibir no site”).
      </div>
      <div id="foco-manual-wrap" hidden style="margin-top:10px;">
        <textarea class="form-control" id="campo-jogadores-foco" name="jogadores_foco" rows="6" placeholder="Um nick por linha&#10;s1mple&#10;donk&#10;ZywOo"></textarea>
        <div class="form-hint">
          Um nick por linha. Precisa bater com o nome na demo (match flexível). Ideal para demos de times terceiros / pro.
        </div>
      </div>
    </div>

    <div class="form-group">
      <label>Arquivo da partida (.dem)</label>
      <input class="form-control" type="file" name="demo" id="campo-demo" accept=".dem,application/octet-stream">
      <div class="form-hint">
        Sobe direto no Render. Demos &gt; ~300&nbsp;MB no plano Free costumam falhar — use o JSON local abaixo.
      </div>
    </div>

    <div class="form-group">
      <label>Ou JSON do parse local (<code>dados_partida.json</code>)</label>
      <input class="form-control" type="file" name="json_local" id="campo-json" accept=".json,application/json">
      <div class="form-hint">
        No PC: <code>node src/parse-cli.js sua.dem</code> → sobe o <code>dados_partida.json</code>.
        CLI grava frames (~0.5s), shots, granadas e loadout — ideal p/ Replay 2D.
      </div>
    </div>

    <button type="submit" class="btn btn-primary" id="btn-processar-demo">
      Upar e analisar
    </button>
  </form>
</div>

<script>
(function () {
  var form = document.getElementById('form-nova-demo');
  var btn = document.getElementById('btn-processar-demo');
  var erroEl = document.getElementById('demo-erro');
  var progEl = document.getElementById('demo-progresso');
  var siteUrl = <?= json_encode(SITE_URL) ?>;
  // Caminhos relativos evitam erro http/https na Locaweb
  var apiIniciar = '/admin/demos/api_iniciar.php';
  var apiFinalizar = '/admin/demos/api_finalizar.php';

  function showErro(msg) {
    erroEl.style.display = 'block';
    erroEl.textContent = msg;
    progEl.style.display = 'none';
    btn.disabled = false;
    btn.textContent = 'Upar e analisar';
  }

  function showProg(msg) {
    progEl.style.display = 'block';
    progEl.textContent = msg;
    erroEl.style.display = 'none';
  }

  async function lerJson(res, passo) {
    var text = await res.text();
    var data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      var preview = (text || '').replace(/\s+/g, ' ').slice(0, 160);
      throw new Error(
        passo + ' retornou HTML em vez de JSON (HTTP ' + res.status + '). ' +
        'Provável causa: arquivo PHP não enviado no FileZilla ou sessão expirada. ' +
        'Detalhe: ' + preview
      );
    }
    return data;
  }

  /** Fase 1: mantém posições + frames compactos; tira só o que a Locaweb não precisa. */
  function minimizarPayload(data) {
    return {
      fase: data.fase || 1,
      mapa: data.mapa || null,
      tickrate: data.tickrate || 64,
      total_kills: data.total_kills || 0,
      total_rounds: data.total_rounds || 0,
      players: data.players || [],
      kills: (data.kills || []).map(function (k) {
        return {
          round: k.round,
          tick: k.tick,
          time_sec: k.time_sec,
          round_time_sec: k.round_time_sec != null ? k.round_time_sec : null,
          assassino: k.assassino,
          time_assassino: k.time_assassino,
          vitima: k.vitima,
          time_vitima: k.time_vitima,
          arma: k.arma,
          headshot: !!k.headshot,
          pos_vitima: k.pos_vitima || null,
          pos_assassino: k.pos_assassino || null
        };
      }),
      rounds: (data.rounds || []).map(function (r) {
        return {
          round: r.round,
          winner: r.winner,
          winner_num: r.winner_num,
          reason: r.reason,
          tick_start: r.tick_start != null ? r.tick_start : null,
          tick_end: r.tick_end,
          duration_sec: r.duration_sec != null ? r.duration_sec : null,
          time_sec: r.time_sec
        };
      }),
      bomb_events: (data.bomb_events || []).map(function (b) {
        return {
          round: b.round,
          site: b.site,
          planter: b.planter,
          tick: b.tick,
          time_sec: b.time_sec,
          pos: b.pos || null
        };
      }),
      // frames + shots + grenades — replay 2D / calor / tracers / utility
      frames: data.frames || [],
      shots: data.shots || [],
      grenades: (data.grenades || []).map(function (g) {
        return {
          type: g.type,
          r: g.r,
          n: g.n,
          team: g.team,
          t0: g.t0,
          t1: g.t1,
          t2: g.t2,
          x0: g.x0,
          y0: g.y0,
          x1: g.x1,
          y1: g.y1
        };
      }),
      player_clans: data.player_clans || {}
    };
  }

  /** Acorda o Render free (cold start) antes do upload grande. */
  async function acordarParser(baseUrl) {
    var url = baseUrl.replace(/\/$/, '') + '/health';
    var deadline = Date.now() + 90000;
    showProg('2/3 — Acordando parser (Render free pode demorar ~1 min)…');
    while (Date.now() < deadline) {
      try {
        var r = await fetch(url, { method: 'GET', mode: 'cors' });
        if (r.ok) {
          var j = await r.json().catch(function () { return {}; });
          if (j && j.ok) return j;
        }
      } catch (e) { /* dormindo */ }
      await new Promise(function (resolve) { setTimeout(resolve, 2500); });
    }
    throw new Error('Parser não respondeu a tempo. Abra ' + url + ' no navegador e tente de novo.');
  }

  function focoModoAtual() {
    var el = document.querySelector('input[name="foco_modo"]:checked');
    return el ? el.value : 'cadastro';
  }

  function syncFocoUi() {
    var manual = focoModoAtual() === 'manual';
    var wrap = document.getElementById('foco-manual-wrap');
    var hintCad = document.getElementById('foco-hint-cadastro');
    if (wrap) wrap.hidden = !manual;
    if (hintCad) hintCad.hidden = manual;
  }

  document.querySelectorAll('input[name="foco_modo"]').forEach(function (el) {
    el.addEventListener('change', syncFocoUi);
  });
  syncFocoUi();

  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    var titulo = document.getElementById('campo-titulo').value.trim();
    var time = document.getElementById('campo-time').value.trim() || 'MouseTrap';
    var adversario = (document.getElementById('campo-adversario').value || '').trim();
    var fileDem = document.getElementById('campo-demo').files[0];
    var fileJson = document.getElementById('campo-json').files[0];
    var token = document.getElementById('csrf-token').value;
    var focoModo = focoModoAtual();
    var jogadoresFoco = (document.getElementById('campo-jogadores-foco').value || '').trim();

    if (!titulo) return showErro('Informe um título.');
    if (!fileDem && !fileJson) {
      return showErro('Selecione o .dem OU o dados_partida.json do parse local.');
    }
    if (focoModo === 'manual' && !jogadoresFoco) {
      return showErro('Informe pelo menos um nick (um por linha) ou escolha o cadastro do time.');
    }

    btn.disabled = true;
    btn.textContent = 'Processando…';

    try {
      showProg('1/3 — Preparando análise…');
      var fd = new FormData();
      fd.append('token', token);
      fd.append('titulo', titulo);
      fd.append('time_nosso', time);
      fd.append('time_adversario', adversario);

      var r1 = await fetch(apiIniciar, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      var j1 = await lerJson(r1, 'Passo 1 (api_iniciar.php)');
      if (!j1.ok) throw new Error(j1.error || 'Falha ao iniciar');

      var parseData = null;

      if (fileJson) {
        showProg('2/3 — Lendo JSON do parse local…');
        var rawTxt = await fileJson.text();
        var parsed = JSON.parse(rawTxt);
        // CLI grava o objeto direto; API Render grava { ok, data }
        parseData = parsed && parsed.data ? parsed.data : parsed;
        if (!parseData || !parseData.kills) {
          throw new Error('JSON inválido — use o dados_partida.json gerado pelo parse-cli.');
        }
      } else {
        var health = await acordarParser(j1.analyzer_url);
        if (health.max_upload_mb && fileDem.size > health.max_upload_mb * 1024 * 1024) {
          throw new Error(
            'Demo tem ' + (fileDem.size / 1024 / 1024).toFixed(0) + ' MB e o parser aceita até ' +
            health.max_upload_mb + ' MB. Faça o parse local e envie o dados_partida.json.'
          );
        }
        if (health.max_upload_mb == null) {
          console.warn('health sem max_upload_mb — redeploy do demo-analyzer pendente');
        }

        showProg('2/3 — Enviando .dem para o Render…');
        var fdDem = new FormData();
        fdDem.append('demo', fileDem, fileDem.name);

        var r2 = await fetch(j1.analyzer_url.replace(/\/$/, '') + '/parse', {
          method: 'POST',
          headers: { 'X-Demo-Secret': j1.analyzer_secret },
          body: fdDem
        });
        var j2 = await lerJson(r2, 'Passo 2 (parser Render)');
        if (!r2.ok || !j2.ok) {
          var errMsg = (j2 && (j2.error || j2.detail)) || ('Erro no parser HTTP ' + r2.status);
          if (/too large|file too large|LIMIT_FILE_SIZE/i.test(errMsg)) {
            errMsg += ' — use o parse local e envie o dados_partida.json (campo abaixo do .dem).';
          }
          throw new Error(errMsg);
        }
        parseData = j2.data;
      }

      var nFramesIn = (parseData.frames && parseData.frames.length) || 0;
      if (nFramesIn === 0) {
        console.warn('Parse veio SEM frames — mapa de calor ficará vazio.');
      }
      showProg('3/3 — Métricas + IA (' + nFramesIn + ' frames)…');
      var mini = minimizarPayload(parseData);
      var fdFin = new FormData();
      fdFin.append('token', j1.token || token);
      fdFin.append('demo_id', String(j1.demo_id));
      fdFin.append('foco_modo', focoModo);
      if (focoModo === 'manual') {
        fdFin.append('jogadores_foco', jogadoresFoco);
      }
      var blob = new Blob([JSON.stringify(mini)], { type: 'application/json' });
      fdFin.append('payload_file', blob, 'demo.json');

      var r3 = await fetch(apiFinalizar, {
        method: 'POST',
        credentials: 'same-origin',
        body: fdFin
      });
      var j3 = await lerJson(r3, 'Passo 3 (api_finalizar.php)');
      if (!j3.ok) throw new Error(j3.error || 'Falha ao finalizar');

      if (!j3.frames_count) {
        showProg(
          'Análise salva, mas SEM frames (calor vazio). ' +
          'No PC: node src/parse-cli.js sua.dem — confira "Frames: N" > 0 — e envie o JSON. ' +
          'No Render: Environment FRAME_INTERVAL_SEC=1 + redeploy.'
        );
        await new Promise(function (r) { setTimeout(r, 4500); });
      }

      showProg('Concluído! Abrindo dashboard…');
      window.location.href = j3.redirect;
    } catch (err) {
      showErro(err.message || String(err));
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
