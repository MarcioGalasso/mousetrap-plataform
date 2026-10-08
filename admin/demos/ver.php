<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/demo_metrics.php';
require_once __DIR__ . '/../../includes/demo_ai.php';
require_once __DIR__ . '/../../includes/demo_radar.php';
exigir_login();
demo_garantir_schema();
require_once __DIR__ . '/../../includes/demo_zones.php';
require_once __DIR__ . '/../../includes/demo_fase_c.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM demos_analises WHERE id = :id');
$stmt->execute(['id' => $id]);
$demo = $stmt->fetch();
if (!$demo) {
    redirecionar(SITE_URL . '/admin/demos/index.php');
}

$metricas = $demo['metricas_json'] ? json_decode($demo['metricas_json'], true) : [];
$killsRaw = $demo['kills_json'] ? json_decode($demo['kills_json'], true) : [];
if (!is_array($killsRaw)) {
    $killsRaw = [];
}
$roundsRaw = $demo['rounds_json'] ? json_decode($demo['rounds_json'], true) : [];
if (!is_array($roundsRaw)) {
    $roundsRaw = [];
}
$framesRaw = [];
$shotsRaw = [];
$grenadesRaw = [];
$bombsRaw = [];
if (!empty($demo['frames_json'])) {
    $decodedFrames = json_decode($demo['frames_json'], true) ?: [];
    if (is_array($decodedFrames) && isset($decodedFrames['frames']) && is_array($decodedFrames['frames'])) {
        $framesRaw = $decodedFrames['frames'];
        $shotsRaw = isset($decodedFrames['shots']) && is_array($decodedFrames['shots'])
            ? $decodedFrames['shots']
            : [];
        $grenadesRaw = isset($decodedFrames['grenades']) && is_array($decodedFrames['grenades'])
            ? $decodedFrames['grenades']
            : [];
        $bombsRaw = isset($decodedFrames['bomb_events']) && is_array($decodedFrames['bomb_events'])
            ? $decodedFrames['bomb_events']
            : [];
    } elseif (is_array($decodedFrames)) {
        $framesRaw = $decodedFrames; // legado: array puro de frames
    }
}
if (!is_array($framesRaw)) {
    $framesRaw = [];
}
if (!is_array($shotsRaw)) {
    $shotsRaw = [];
}
if (!is_array($grenadesRaw)) {
    $grenadesRaw = [];
}
if (!is_array($bombsRaw)) {
    $bombsRaw = [];
}
$nicksDetectados = $demo['jogadores_nossos'] ? json_decode($demo['jogadores_nossos'], true) : [];
if (!is_array($nicksDetectados)) {
    $nicksDetectados = [];
}

// Tickrate: mediana entre rounds longos (evita warmup/round curto distorcer)
$tickrate = 64;
$tickrateSamples = [];
foreach ($roundsRaw as $rm) {
    $ts = isset($rm['tick_start']) ? (int) $rm['tick_start'] : null;
    $te = isset($rm['tick_end']) ? (int) $rm['tick_end'] : null;
    $dur = isset($rm['duration_sec']) ? (float) $rm['duration_sec'] : 0.0;
    if ($ts !== null && $te !== null && $dur >= 20 && $te > $ts) {
        $tickrateSamples[] = ($te - $ts) / $dur;
    }
}
if ($tickrateSamples) {
    sort($tickrateSamples);
    $mid = $tickrateSamples[(int) floor(count($tickrateSamples) / 2)];
    $tickrate = max(32, min(128, (int) round($mid)));
    // CS2 quase sempre 64; se ficou perto, força 64
    if (abs($tickrate - 64) <= 8) {
        $tickrate = 64;
    } elseif (abs($tickrate - 128) <= 8) {
        $tickrate = 128;
    }
}

$radarCfg = demo_radar_resolver_mapa($demo['mapa'] ?? null);
$radarUrl = $radarCfg ? demo_radar_url($radarCfg) : null;

$chat = db()->prepare('SELECT * FROM demos_chat WHERE demo_id = :id ORDER BY id ASC');
$chat->execute(['id' => $id]);
$mensagens = $chat->fetchAll();

$tituloAdmin = 'Demo #' . $id;
$bodyClassExtra = 'admin-body--demo-replay';
require_once __DIR__ . '/../includes/admin_header.php';

$m = $metricas ?: [];
// Território (B) + Fase C: SEMPRE recalcula ao vivo
$isNossoLive = null;
if (!empty($nicksDetectados)) {
    $isNossoLive = static function (string $nick) use ($nicksDetectados): bool {
        return demo_match_nick($nick, $nicksDetectados) !== null;
    };
}
if (
    $radarCfg
    && !empty($framesRaw)
    && $isNossoLive
    && demo_zones_for_map($demo['mapa'] ?? null)
) {
    $m['territorio'] = demo_analisar_territorio(
        $framesRaw,
        $isNossoLive,
        $demo['mapa'] ?? null,
        $radarCfg,
        $roundsRaw,
        (float) $tickrate
    );
}
if ($isNossoLive && !empty($killsRaw)) {
    // Placar / pistol / opening / 5v4 ao vivo (corrige winner antigo sem reupar)
    $m = demo_recalcular_placar_metricas(
        $m,
        $killsRaw,
        $roundsRaw,
        $nicksDetectados,
        $bombsRaw
    );
    $m['fase_c'] = demo_analisar_fase_c(
        $killsRaw,
        $roundsRaw,
        $framesRaw,
        $grenadesRaw,
        $bombsRaw,
        $isNossoLive,
        $demo['mapa'] ?? null,
        $radarCfg,
        (float) $tickrate
    );
}
$card = static function (string $label, $value, string $hint = '') {
    echo '<div class="demo-metric-card">';
    echo '<div class="demo-metric-label">' . htmlspecialchars($label) . '</div>';
    echo '<div class="demo-metric-value">' . htmlspecialchars((string) $value) . '</div>';
    if ($hint !== '') {
        echo '<div class="demo-metric-hint">' . htmlspecialchars($hint) . '</div>';
    }
    echo '</div>';
};

$playerColors = ['#f5c542', '#a78bfa', '#34d399', '#60a5fa', '#fb923c', '#f472b6', '#2dd4bf', '#e879f9'];
?>

<div class="admin-topbar">
  <div>
    <h1><?= e($demo['titulo']) ?></h1>
    <p style="color:var(--muted); margin-top:4px;">
      <?= e($demo['mapa'] ?: 'Mapa n/d') ?> · <?= e($demo['time_nosso']) ?> · status: <?= e($demo['status']) ?>
      <?php if (!empty($nicksDetectados)): ?>
        <?php
          $origemFoco = $m['deteccao_time']['origem'] ?? '';
          $labelFoco = $origemFoco === 'manual' ? 'nomes manuais' : 'cadastro / foco';
        ?>
        · foco: <?= e(implode(', ', $nicksDetectados)) ?>
        <span style="color:var(--muted); font-size:0.9em;"> (<?= e($labelFoco) ?>)</span>
      <?php endif; ?>
    </p>
  </div>
  <a href="<?= SITE_URL ?>/admin/demos/index.php" class="btn btn-outline">&larr; Voltar</a>
</div>

<?php if (isset($_GET['ok'])): ?>
  <div class="alert alert-success">Análise pronta.</div>
<?php endif; ?>
<?php if ($demo['status'] === 'erro'): ?>
  <div class="alert alert-error"><?= e($demo['erro_msg'] ?: 'Erro no processamento') ?></div>
<?php endif; ?>

<nav class="demo-views" role="tablist" aria-label="Views da análise">
  <div class="demo-views-label">VIEWS</div>
  <div class="demo-views-tabs demo-views-tabs--6">
    <button type="button" class="demo-view-tab is-active" data-view="analise" role="tab" aria-selected="true">Análise</button>
    <button type="button" class="demo-view-tab" data-view="replay" role="tab" aria-selected="false">Replay</button>
    <button type="button" class="demo-view-tab" data-view="rounds" role="tab" aria-selected="false">Rounds</button>
    <button type="button" class="demo-view-tab" data-view="kills" role="tab" aria-selected="false">Kills</button>
    <button type="button" class="demo-view-tab" data-view="deaths" role="tab" aria-selected="false">Deaths</button>
    <button type="button" class="demo-view-tab" data-view="calor" role="tab" aria-selected="false">Heatmap</button>
  </div>
</nav>

<!-- ========== TAB REPLAY 2D (layout estilo PRACC) ========== -->
<div class="demo-view-panel" id="view-replay" data-view-panel="replay" hidden>
  <div class="demo-replay-shell">
    <div class="demo-replay-stage">
      <aside class="demo-replay-filters demo-panel">
        <div class="demo-filters-label">FILTERS</div>
        <div class="demo-side-toggle demo-side-toggle--stack" id="replay-side-filter">
          <button type="button" class="is-active" data-side="both">BOTH</button>
          <button type="button" data-side="TR">T</button>
          <button type="button" data-side="CT">CT</button>
        </div>
      </aside>

      <div class="demo-replay-center">
        <?php if ($radarUrl && $radarCfg): ?>
          <div class="demo-replay-map-frame">
            <div class="demo-radar" id="demo-replay-radar">
              <img src="<?= e($radarUrl) ?>" alt="Radar replay" id="demo-replay-img" draggable="false">
              <canvas id="demo-replay-fx" class="demo-replay-fx"></canvas>
              <div class="demo-radar-markers" id="demo-replay-markers"></div>
            </div>
          </div>
          <?php if (empty($framesRaw)): ?>
            <div class="alert alert-error">
              Sem <code>frames</code> nesta análise — o Replay fica vazio.
              Rode o parse de novo com frames ligados e reenvie o JSON.
            </div>
          <?php elseif (empty($grenadesRaw)): ?>
            <p class="form-hint" style="margin-top:8px;">
              Sem granadas nestes dados — reparse a demo (parser atualizado) para ver trajetória, smoke, molotov, flash, HE e decoy.
            </p>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert-error" style="margin:0;">
            Sem imagem de radar para o mapa <strong><?= e($demo['mapa'] ?: '?') ?></strong>.
          </div>
        <?php endif; ?>
      </div>

      <aside class="demo-replay-hud demo-panel">
        <div class="demo-replay-team-block">
          <div class="demo-replay-roster-title demo-replay-roster-title--ours"><?= e($demo['time_nosso'] ?: 'Nosso time') ?></div>
          <div id="replay-roster-ours"></div>
        </div>
        <div class="demo-replay-team-block">
          <div class="demo-replay-roster-title demo-replay-roster-title--opp"><?= e(!empty($demo['time_adversario']) ? $demo['time_adversario'] : 'Adversário') ?></div>
          <div id="replay-roster-opp"></div>
        </div>
      </aside>
    </div>

    <div class="demo-replay-controls">
      <div class="demo-replay-toolbar">
        <button type="button" class="btn btn-primary" id="replay-play" title="Play/Pause">▶</button>
        <span class="demo-replay-time" id="replay-time">0:00</span>
        <div class="demo-replay-speed" id="replay-speed">
          <button type="button" data-speed="1" class="is-active">1x</button>
          <button type="button" data-speed="2">2x</button>
          <button type="button" data-speed="4">4x</button>
          <button type="button" data-speed="8">8x</button>
        </div>
        <span class="form-hint" style="margin:0;" id="replay-round-label">Round 1</span>
      </div>
      <div class="demo-replay-timeline-wrap">
        <div class="demo-replay-kills-rail" id="replay-kills-rail"></div>
        <input type="range" class="demo-replay-timeline" id="replay-timeline" min="0" max="1000" value="0" step="1">
      </div>
      <div class="demo-replay-rounds" id="replay-rounds"></div>
    </div>
  </div>
</div>

<!-- ========== TAB ROUNDS (todos em paralelo) ========== -->
<div class="demo-view-panel" id="view-rounds" data-view-panel="rounds" hidden>
  <div class="demo-rounds-shell">
    <aside class="demo-rounds-sidebar demo-panel">
      <div class="demo-filters-label">FILTERS</div>
      <div class="demo-side-toggle" id="rounds-side-filter">
        <button type="button" class="is-active" data-side="both">BOTH SIDES</button>
        <button type="button" data-side="TR">T</button>
        <button type="button" data-side="CT">CT</button>
      </div>

      <div class="demo-team-card" style="margin-top:12px;">
        <div class="demo-team-card-title"><?= e($demo['time_nosso'] ?: 'MouseTrap') ?></div>
        <div class="demo-player-checks" id="rounds-player-filters">
          <?php foreach ($nicksDetectados as $i => $nick): ?>
            <?php $cor = $playerColors[$i % count($playerColors)]; ?>
            <label class="demo-player-check">
              <input type="checkbox" value="<?= e($nick) ?>" <?= $i === 0 ? 'checked' : '' ?> data-color="<?= e($cor) ?>">
              <span class="demo-player-dot" style="background:<?= e($cor) ?>"></span>
              <span><?= e($nick) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (empty($nicksDetectados)): ?>
            <p class="form-hint">Nenhum jogador focado nesta análise.</p>
          <?php endif; ?>
        </div>
        <?php if (count($nicksDetectados) > 1): ?>
          <div class="demo-heat-bulk" style="margin-top:8px;">
            <button type="button" class="btn btn-sm btn-outline" id="rounds-all">Todos</button>
            <button type="button" class="btn btn-sm btn-outline" id="rounds-none">Nenhum</button>
          </div>
        <?php endif; ?>
      </div>

      <p class="form-hint" style="margin-top:12px;">
        Todos os rounds no <strong>mesmo tempo</strong> do round.
        Cada gota = o jogador naquele round (CT/T filtrado).
      </p>
      <p class="form-hint" id="rounds-active-label">Rounds ativos: 0</p>
    </aside>

    <div class="demo-rounds-main">
      <?php if ($radarUrl && $radarCfg): ?>
        <div class="demo-rounds-map-wrap">
          <div class="demo-rounds-clock" id="rounds-clock">0:00</div>
          <div class="demo-radar" id="demo-rounds-radar">
            <img src="<?= e($radarUrl) ?>" alt="Radar rounds" id="demo-rounds-img" draggable="false">
            <div class="demo-radar-markers" id="demo-rounds-markers"></div>
          </div>
        </div>
        <div class="demo-replay-controls demo-rounds-controls">
          <div class="demo-replay-toolbar">
            <button type="button" class="btn btn-primary" id="rounds-play" title="Play/Pause">▶</button>
            <span class="demo-replay-time" id="rounds-time">0:00</span>
            <div class="demo-replay-speed" id="rounds-speed">
              <button type="button" data-speed="1" class="is-active">1x</button>
              <button type="button" data-speed="2">2x</button>
              <button type="button" data-speed="4">4x</button>
              <button type="button" data-speed="8">8x</button>
            </div>
            <span class="form-hint" style="margin:0;">tempo do round (todos juntos)</span>
          </div>
          <div class="demo-replay-timeline-wrap">
            <input type="range" class="demo-replay-timeline" id="rounds-timeline" min="0" max="1000" value="0" step="1">
          </div>
        </div>
        <?php if (empty($framesRaw)): ?>
          <div class="alert alert-error">Sem frames — Rounds precisa de parse com frames.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-error">Sem radar para <strong><?= e($demo['mapa'] ?: '?') ?></strong>.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ========== TAB ANÁLISE ========== -->
<div class="demo-view-panel is-active" id="view-analise" data-view-panel="analise">
<?php if ($m): ?>
<section class="demo-metrics-grid">
  <?php
    $card('Win Rate geral', ($m['resumo']['win_rate_geral'] ?? 0) . '%', ($m['resumo']['rounds_won'] ?? 0) . '/' . ($m['resumo']['rounds_total'] ?? 0) . ' rounds');
    $card(
      '5v4 (conversão)',
      ($m['vantagem_5v4']['win_rate'] ?? 0) . '%',
      ($m['vantagem_5v4']['vitorias'] ?? 0) . '/' . ($m['vantagem_5v4']['situacoes'] ?? 0) . ' · após 1ª vantagem (kills por tick)'
    );
    $card('4v5 (recuperação)', ($m['desvantagem_4v5']['win_rate'] ?? 0) . '%', ($m['desvantagem_4v5']['vitorias'] ?? 0) . '/' . ($m['desvantagem_4v5']['situacoes'] ?? 0) . ' situações');
    $okHint = ($m['opening_kill']['nossos'] ?? 0) . '/' . ($m['opening_kill']['total_rounds'] ?? 0)
      . ' · CT ' . ($m['opening_kill']['rate_ct'] ?? 0) . '% · TR ' . ($m['opening_kill']['rate_tr'] ?? 0) . '%';
    $card('Opening Kill Rate', ($m['opening_kill']['rate'] ?? 0) . '%', $okHint);
    $card('Traded Deaths', ($m['traded_deaths']['rate'] ?? 0) . '%', ($m['traded_deaths']['trocadas'] ?? 0) . '/' . ($m['traded_deaths']['mortes_nossas'] ?? 0));
    $card('Pistol CT', ($m['pistol']['CT']['win_rate'] ?? 0) . '%', ($m['pistol']['CT']['vitorias'] ?? 0) . '/' . ($m['pistol']['CT']['jogados'] ?? 0));
    $card('Pistol TR', ($m['pistol']['TR']['win_rate'] ?? 0) . '%', ($m['pistol']['TR']['vitorias'] ?? 0) . '/' . ($m['pistol']['TR']['jogados'] ?? 0));
  ?>
</section>

<section class="demo-panel">
  <h2 class="demo-panel-title">Economia (win rate)</h2>
  <div class="demo-metrics-grid demo-metrics-grid--4">
    <?php foreach (($m['economia'] ?? []) as $tipo => $eco): ?>
      <?php $card($tipo, ($eco['win_rate'] ?? 0) . '%', ($eco['vitorias'] ?? 0) . '/' . ($eco['rounds'] ?? 0) . ' rounds'); ?>
    <?php endforeach; ?>
  </div>
  <p class="form-hint" style="margin:0;">
    Heurística por armas nas kills do round (rifle/AWP = armado; 1–2 rifles = forçado; só pistola/SMG = eco).
    Reanalise a demo para recalcular Forçado com a regra nova.
  </p>
</section>

<?php if (!empty($m['adversarios_destaque'])): ?>
<section class="demo-panel">
  <h2 class="demo-panel-title">Quem mais puniu a MouseTrap</h2>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Adversário</th><th>Kills em nós</th><th>Principais vítimas</th><th>Armas</th></tr>
      </thead>
      <tbody>
        <?php foreach ($m['adversarios_destaque'] as $adv): ?>
          <tr>
            <td><?= e($adv['nick'] ?? '') ?></td>
            <td><?= (int) ($adv['kills_em_nos'] ?? 0) ?></td>
            <td><?php
              $vits = $adv['vitimas'] ?? [];
              arsort($vits);
              $bits = [];
              foreach (array_slice($vits, 0, 3, true) as $vn => $vq) {
                  $bits[] = $vn . ' (' . $vq . ')';
              }
              echo e(implode(', ', $bits));
            ?></td>
            <td><?php
              $arms = $adv['armas'] ?? [];
              arsort($arms);
              echo e(implode(', ', array_slice(array_keys($arms), 0, 3)));
            ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="demo-panel">
  <h2 class="demo-panel-title">Jogadores do foco (analisar na demo)</h2>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Nick</th><th>Kills</th><th>Deaths</th><th>HS</th><th>Opening</th></tr>
      </thead>
      <tbody>
        <?php foreach (($m['jogadores'] ?? []) as $nick => $st): ?>
          <tr>
            <td><?= e($nick) ?></td>
            <td><?= (int) ($st['kills'] ?? 0) ?></td>
            <td><?= (int) ($st['deaths'] ?? 0) ?></td>
            <td><?= (int) ($st['hs'] ?? 0) ?></td>
            <td><?= (int) ($st['opening'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php
  $terr = $m['territorio'] ?? [];
  if (!empty($terr['disponivel'])):
?>
<section class="demo-panel">
  <h2 class="demo-panel-title">Território (zonas) — <?= e($terr['mapa'] ?? 'mapa') ?></h2>
  <p class="form-hint" style="margin-top:0;">
    Controle = ≥2 jogadores nossos na zona ou ≥3s de presença.
    Progressão = controlou origem e depois o destino no mesmo round (TR).
    Reanalise a demo se esta seção estiver vazia em análises antigas.
  </p>
  <div class="admin-table-wrap" style="margin-bottom:14px;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Zona</th>
          <th>Controle (TR)</th>
          <th>Rounds controle</th>
          <th>Presença</th>
          <th>Player-seg</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($terr['ranking_controle_ataque'] ?? []) as $z): ?>
          <tr>
            <td><?= e($z['label'] ?? $z['id'] ?? '') ?></td>
            <td><strong><?= e((string) ($z['controle_ataque_pct'] ?? 0)) ?>%</strong></td>
            <td><?= (int) ($z['rounds_controle_tr'] ?? $z['rounds_controle'] ?? 0) ?>/<?= (int) ($terr['rounds_tr'] ?? 0) ?></td>
            <td><?= e((string) ($z['presenca_pct'] ?? 0)) ?>%</td>
            <td><?= e((string) ($z['player_seconds'] ?? 0)) ?>s</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($terr['progressao'])): ?>
  <h3 class="demo-panel-title" style="font-size:1rem;">Progressão</h3>
  <div class="admin-table-wrap" style="margin-bottom:14px;">
    <table class="admin-table">
      <thead>
        <tr><th>Cadeia</th><th>Convertidos</th><th>Taxa</th></tr>
      </thead>
      <tbody>
        <?php foreach ($terr['progressao'] as $p): ?>
          <?php if (($p['rounds_base'] ?? 0) < 1) continue; ?>
          <tr>
            <td><?= e($p['label'] ?? '') ?></td>
            <td><?= (int) ($p['rounds_convertidos'] ?? 0) ?>/<?= (int) ($p['rounds_base'] ?? 0) ?></td>
            <td><strong><?= $p['taxa_pct'] !== null ? e((string) $p['taxa_pct']) . '%' : '—' ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php if (!empty($terr['abertura_tr'])): ?>
  <h3 class="demo-panel-title" style="font-size:1rem;">Abertura TR (1ª zona)</h3>
  <div class="demo-metrics-grid demo-metrics-grid--4">
    <?php foreach (array_slice($terr['abertura_tr'], 0, 4) as $ab): ?>
      <?php $card($ab['label'] ?? $ab['zona'], ($ab['pct'] ?? 0) . '%', ($ab['rounds'] ?? 0) . ' rounds'); ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php
  $fc = $m['fase_c'] ?? [];
  if (!empty($fc['disponivel'])):
    $ma5 = $fc['man_advantage']['5v4'] ?? [];
    $ma4 = $fc['man_advantage']['4v5'] ?? [];
    $abFc = $fc['abertura'] ?? [];
    $utFc = $fc['util'] ?? [];
?>
<section class="demo-panel">
  <h2 class="demo-panel-title">Fase C — Util / Abertura / 5v4–4v5</h2>
  <p class="form-hint" style="margin-top:0;">
    Após opening: 5v4 = acelerou / congelou / plantou / overpeek.
    4v5 = regroupou / forçou / salvou / info play. Compare com o meta no relatório IA.
  </p>

  <div class="demo-metrics-grid demo-metrics-grid--4" style="margin-bottom:16px;">
    <?php
      $card(
        '5v4 win',
        ($ma5['win_rate'] ?? '—') . (isset($ma5['win_rate']) ? '%' : ''),
        ($ma5['vitorias'] ?? $ma5['wins'] ?? 0) . '/' . ($ma5['situacoes'] ?? 0)
      );
      $dom5 = '—';
      $best5 = -1;
      foreach (($ma5['comportamentos'] ?? []) as $k => $v) {
          if ((int) $v > $best5) { $best5 = (int) $v; $dom5 = $k; }
      }
      $card('5v4 comportamento', $dom5, ($ma5['comportamentos_pct'][$dom5] ?? 0) . '% das situações');
      $card(
        '4v5 win',
        ($ma4['win_rate'] ?? '—') . (isset($ma4['win_rate']) ? '%' : ''),
        ($ma4['wins'] ?? 0) . '/' . ($ma4['situacoes'] ?? 0)
      );
      $dom4 = '—';
      $best4 = -1;
      foreach (($ma4['comportamentos'] ?? []) as $k => $v) {
          if ((int) $v > $best4) { $best4 = (int) $v; $dom4 = $k; }
      }
      $card('4v5 comportamento', $dom4, ($ma4['comportamentos_pct'][$dom4] ?? 0) . '% das situações');
    ?>
  </div>

  <?php if (!empty($ma5['situacoes']) || !empty($ma4['situacoes'])): ?>
  <div class="admin-table-wrap" style="margin-bottom:14px;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Situação</th>
          <th>Comportamento</th>
          <th>Qtd</th>
          <th>%</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (['5v4' => $ma5, '4v5' => $ma4] as $labelSit => $pack): ?>
          <?php foreach (($pack['comportamentos'] ?? []) as $comp => $qtd): ?>
            <?php if ((int) $qtd < 1) continue; ?>
            <tr>
              <td><?= e($labelSit) ?></td>
              <td><?= e((string) $comp) ?></td>
              <td><?= (int) $qtd ?></td>
              <td><strong><?= e((string) ($pack['comportamentos_pct'][$comp] ?? 0)) ?>%</strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if (!empty($abFc['por_economia'])): ?>
  <h3 class="demo-panel-title" style="font-size:1rem;">Abertura por economia</h3>
  <p class="form-hint" style="margin-top:0;">1ª kill do round (por tick). Separado CT/TR — pistol costuma ser 1 de cada lado.</p>
  <div class="admin-table-wrap" style="margin-bottom:14px;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Economia</th>
          <th>Rounds</th>
          <th>Opening geral</th>
          <th>Opening CT</th>
          <th>Opening TR</th>
          <th>Zona top (TR)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($abFc['por_economia'] as $row): ?>
          <?php
            $zTop = $row['zonas_top'][0] ?? null;
            $zTxt = $zTop
              ? (($zTop['zona'] ?? '') . ' (' . ($zTop['pct'] ?? 0) . '%)')
              : '—';
            $ctTxt = isset($row['opening_ct_rate'])
              ? (($row['opening_ct_rate']) . '% (' . (int) ($row['opening_ct'] ?? 0) . '/' . (int) ($row['rounds_ct'] ?? 0) . ')')
              : '—';
            $trTxt = isset($row['opening_tr_rate'])
              ? (($row['opening_tr_rate']) . '% (' . (int) ($row['opening_tr'] ?? 0) . '/' . (int) ($row['rounds_tr'] ?? 0) . ')')
              : '—';
          ?>
          <tr>
            <td><?= e((string) ($row['economia'] ?? '')) ?></td>
            <td><?= (int) ($row['rounds'] ?? 0) ?></td>
            <td><?= e((string) ($row['opening_rate'] ?? 0)) ?>% (<?= (int) ($row['opening_nosso'] ?? 0) ?>/<?= (int) ($row['rounds'] ?? 0) ?>)</td>
            <td><?= e($ctTxt) ?></td>
            <td><?= e($trTxt) ?></td>
            <td><?= e($zTxt) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($abFc['leitura'])): ?>
    <p class="form-hint"><?= e((string) $abFc['leitura']) ?></p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($utFc['disponivel']) && !empty($utFc['por_zona_top'])): ?>
  <h3 class="demo-panel-title" style="font-size:1rem;">Util → zona</h3>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Zona</th><th>Total</th><th>Smoke</th><th>Flash</th><th>Molly</th><th>HE</th></tr>
      </thead>
      <tbody>
        <?php foreach ($utFc['por_zona_top'] as $uz): ?>
          <tr>
            <td><?= e((string) ($uz['zona'] ?? '')) ?></td>
            <td><strong><?= (int) ($uz['total'] ?? 0) ?></strong></td>
            <td><?= (int) ($uz['smoke'] ?? 0) ?></td>
            <td><?= (int) ($uz['flash'] ?? 0) ?></td>
            <td><?= (int) ($uz['molotov'] ?? 0) ?></td>
            <td><?= (int) ($uz['he'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php endif; ?>

<section class="demo-ai-layout">
  <div class="demo-panel demo-report">
    <div class="demo-report-head">
      <h2 class="demo-panel-title" style="margin:0;">Relatório tático (IA)</h2>
      <?php if (demo_openai_configured() && ($demo['status'] ?? '') === 'pronto'): ?>
      <button type="button" class="btn btn-sm btn-secondary" id="demo-regen-report" data-demo-id="<?= (int) $id ?>" data-token="<?= e(csrf_token()) ?>">
        Regenerar relatório
      </button>
      <?php endif; ?>
    </div>
    <p class="form-hint" id="demo-regen-status" style="margin:8px 0 12px;"></p>
    <div class="demo-report-body" id="demo-report-body"><?php
      $relTxt = $demo['relatorio_ia'] ?: '';
      if ($relTxt === '') {
          echo 'Sem relatório ainda.';
      } else {
          echo demo_formatar_relatorio_html($relTxt);
      }
    ?></div>
  </div>

  <div class="demo-panel demo-chat">
    <h2 class="demo-panel-title">Chat analista</h2>
    <div class="demo-chat-log" id="demo-chat-log">
      <?php foreach ($mensagens as $msg): ?>
        <div class="demo-chat-msg demo-chat-msg--<?= e($msg['papel']) ?>">
          <strong><?= $msg['papel'] === 'assistant' ? 'Analista' : 'Você' ?>:</strong>
          <div><?= nl2br(e($msg['mensagem'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <form id="demo-chat-form" class="demo-chat-form">
      <input type="hidden" name="demo_id" value="<?= (int) $id ?>">
      <input type="hidden" name="token" value="<?= csrf_token() ?>">
      <textarea name="pergunta" rows="3" class="form-control" placeholder="Ex: Quem mais entregou First Blood? Qual bomb atacamos mais de TR?" required></textarea>
      <button type="submit" class="btn btn-primary" style="margin-top:10px;">Perguntar</button>
      <div class="form-hint" id="demo-chat-status"></div>
    </form>
  </div>
</section>
</div>

<!-- ========== TAB KILLS ========== -->
<div class="demo-view-panel" id="view-kills" data-view-panel="kills" hidden>
  <div class="demo-kills-layout">
    <aside class="demo-kills-sidebar demo-panel">
      <div class="demo-filters-label">FILTERS</div>
      <div class="demo-side-toggle" id="kills-side-filter">
        <button type="button" class="is-active" data-side="both">BOTH SIDES</button>
        <button type="button" data-side="TR">T</button>
        <button type="button" data-side="CT">CT</button>
      </div>

      <div class="demo-team-card">
        <div class="demo-team-card-title"><?= e($demo['time_nosso'] ?: 'MouseTrap') ?></div>
        <div class="demo-player-checks" id="kills-player-filters">
          <?php foreach ($nicksDetectados as $i => $nick): ?>
            <?php $cor = $playerColors[$i % count($playerColors)]; ?>
            <label class="demo-player-check">
              <input type="checkbox" value="<?= e($nick) ?>" checked data-color="<?= e($cor) ?>">
              <span class="demo-player-dot" style="background:<?= e($cor) ?>"></span>
              <span><?= e($nick) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (empty($nicksDetectados)): ?>
            <p class="form-hint">Nenhum jogador do cadastro nesta análise.</p>
          <?php endif; ?>
        </div>
      </div>

      <p class="form-hint" id="kills-map-hint" style="margin-top:14px;">
        Caveiras = onde a vítima morreu (kill do jogador filtrado).
        Tempo = decorrido desde o início do round (pode passar de 1:55 se houve plant / overtimes do timer).
      </p>
    </aside>

    <div class="demo-kills-map-wrap demo-panel">
      <?php if ($radarUrl && $radarCfg): ?>
        <div class="demo-radar" id="demo-radar">
          <img src="<?= e($radarUrl) ?>" alt="Radar <?= e($radarCfg['label']) ?>" id="demo-radar-img" draggable="false">
          <div class="demo-radar-markers" id="demo-radar-markers"></div>
        </div>
        <div class="demo-radar-meta">
          <span id="kills-count-label">0 kills</span>
          · <?= e($radarCfg['label']) ?>
        </div>
      <?php else: ?>
        <div class="alert alert-error" style="margin:0;">
          Sem imagem de radar para o mapa <strong><?= e($demo['mapa'] ?: '?') ?></strong>.
          Coloque o arquivo em <code>/mapas/</code> e cadastre em <code>demo_radar.php</code>.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ========== TAB DEATHS ========== -->
<div class="demo-view-panel" id="view-deaths" data-view-panel="deaths" hidden>
  <div class="demo-kills-layout">
    <aside class="demo-kills-sidebar demo-panel">
      <div class="demo-filters-label">FILTERS</div>
      <div class="demo-side-toggle" id="deaths-side-filter">
        <button type="button" class="is-active" data-side="both">BOTH SIDES</button>
        <button type="button" data-side="TR">T</button>
        <button type="button" data-side="CT">CT</button>
      </div>

      <div class="demo-team-card">
        <div class="demo-team-card-title"><?= e($demo['time_nosso'] ?: 'MouseTrap') ?></div>
        <div class="demo-player-checks" id="deaths-player-filters">
          <?php foreach ($nicksDetectados as $i => $nick): ?>
            <?php $cor = $playerColors[$i % count($playerColors)]; ?>
            <label class="demo-player-check">
              <input type="checkbox" value="<?= e($nick) ?>" checked data-color="<?= e($cor) ?>">
              <span class="demo-player-dot" style="background:<?= e($cor) ?>"></span>
              <span><?= e($nick) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (empty($nicksDetectados)): ?>
            <p class="form-hint">Nenhum jogador do cadastro nesta análise.</p>
          <?php endif; ?>
        </div>
      </div>

      <p class="form-hint" style="margin-top:14px;">
        Caveiras = onde o jogador filtrado <strong>morreu</strong> (<code>pos_vitima</code>).
        Tempo = decorrido no round.
      </p>
    </aside>

    <div class="demo-kills-map-wrap demo-panel">
      <?php if ($radarUrl && $radarCfg): ?>
        <div class="demo-radar" id="demo-deaths-radar">
          <img src="<?= e($radarUrl) ?>" alt="Radar deaths" id="demo-deaths-img" draggable="false">
          <div class="demo-radar-markers" id="demo-deaths-markers"></div>
        </div>
        <div class="demo-radar-meta">
          <span id="deaths-count-label">0 deaths</span>
          · <?= e($radarCfg['label']) ?>
        </div>
      <?php else: ?>
        <div class="alert alert-error" style="margin:0;">
          Sem imagem de radar para o mapa <strong><?= e($demo['mapa'] ?: '?') ?></strong>.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ========== TAB CALOR ========== -->
<div class="demo-view-panel" id="view-calor" data-view-panel="calor" hidden>
  <div class="demo-kills-layout">
    <aside class="demo-kills-sidebar demo-panel">
      <div class="demo-filters-label">FILTERS</div>
      <div class="demo-side-toggle" id="heat-side-filter">
        <button type="button" class="is-active" data-side="both">BOTH SIDES</button>
        <button type="button" data-side="TR">T</button>
        <button type="button" data-side="CT">CT</button>
      </div>

      <div class="demo-team-card">
        <div class="demo-team-card-title"><?= e($demo['time_nosso'] ?: 'MouseTrap') ?></div>
        <div class="demo-player-checks" id="heat-player-filters">
          <?php foreach ($nicksDetectados as $i => $nick): ?>
            <?php $cor = $playerColors[$i % count($playerColors)]; ?>
            <label class="demo-player-check">
              <input type="checkbox" value="<?= e($nick) ?>" checked data-color="<?= e($cor) ?>">
              <span class="demo-player-dot" style="background:<?= e($cor) ?>"></span>
              <span><?= e($nick) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (empty($nicksDetectados)): ?>
            <p class="form-hint">Nenhum jogador focado nesta análise.</p>
          <?php endif; ?>
        </div>
        <?php if (count($nicksDetectados) > 1): ?>
          <div class="demo-heat-bulk" style="margin-top:8px;">
            <button type="button" class="btn btn-sm btn-outline" id="heat-all">Todos</button>
            <button type="button" class="btn btn-sm btn-outline" id="heat-none">Nenhum</button>
          </div>
        <?php endif; ?>
      </div>

      <p class="form-hint" style="margin-top:14px;">
        Agrega <strong>todos os rounds</strong> dos jogadores marcados (densidade do time se marcar todos).
        Vermelho = onde mais ficou; azul claro = passou pouco.
      </p>
      <p class="form-hint" id="heat-frames-hint">
        Amostras: <?= (int) count($framesRaw) ?> frames
      </p>
      <?php if (!empty($m['territorio']['zonas_radar'])): ?>
        <label class="form-hint" style="display:flex;align-items:center;gap:6px;margin-top:10px;cursor:pointer;">
          <input type="checkbox" id="heat-show-zones"> Mostrar zonas táticas (validar bounds)
        </label>
      <?php endif; ?>
    </aside>

    <div class="demo-kills-map-wrap demo-panel">
      <?php if ($radarUrl && $radarCfg): ?>
        <div class="demo-radar" id="demo-heat-radar">
          <img src="<?= e($radarUrl) ?>" alt="Radar calor" id="demo-heat-img" draggable="false">
          <canvas id="demo-heat-canvas" class="demo-heat-canvas"></canvas>
        </div>
        <div class="demo-radar-meta">
          <span id="heat-count-label">0 amostras</span>
          · <?= e($radarCfg['label']) ?>
          · todos os rounds
        </div>
        <?php if (empty($framesRaw)): ?>
          <div class="alert alert-error" style="margin-top:12px;">
            Esta análise não tem <code>frames</code> (posições por segundo).
            Rode de novo o parse local (<code>node src/parse-cli.js demo.dem</code> — frames ligados)
            e envie o JSON novo. Sem frames o calor não aparece.
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-error" style="margin:0;">
          Sem imagem de radar para o mapa <strong><?= e($demo['mapa'] ?: '?') ?></strong>.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
window.DEMO_MAP_DATA = <?= json_encode([
    'kills' => $killsRaw,
    'rounds' => $roundsRaw,
    'frames' => $framesRaw,
    'shots' => $shotsRaw,
    'grenades' => $grenadesRaw,
    'tickrate' => $tickrate,
    'elenco' => $nicksDetectados,
    'time_nosso' => $demo['time_nosso'] ?: 'MouseTrap',
    'time_adversario' => $demo['time_adversario'] ?? '',
    'zonas_radar' => $m['territorio']['zonas_radar'] ?? null,
    'radar' => $radarCfg ? [
        'pos_x' => $radarCfg['pos_x'],
        'pos_y' => $radarCfg['pos_y'],
        'scale' => $radarCfg['scale'],
        'base_size' => $radarCfg['base_size'],
        'rotate' => (int) ($radarCfg['rotate'] ?? 0),
        'inset_mode' => $radarCfg['inset_mode'] ?? 'none',
        'inset_left' => (float) ($radarCfg['inset_left'] ?? 0),
        'inset_top' => (float) ($radarCfg['inset_top'] ?? 0),
        'inset_right' => (float) ($radarCfg['inset_right'] ?? 0),
        'inset_bottom' => (float) ($radarCfg['inset_bottom'] ?? 0),
        'offset_x' => (float) ($radarCfg['offset_x'] ?? 0),
        'offset_y' => (float) ($radarCfg['offset_y'] ?? 0),
        'label' => $radarCfg['label'],
    ] : null,
], JSON_UNESCAPED_UNICODE) ?>;
window.DEMO_KILLS_DATA = window.DEMO_MAP_DATA;
</script>
<script>
(function () {
  /* ---- Tabs ---- */
  var tabs = document.querySelectorAll('.demo-view-tab');
  var panels = document.querySelectorAll('[data-view-panel]');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var view = tab.getAttribute('data-view');
      tabs.forEach(function (t) {
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });
      panels.forEach(function (p) {
        var on = p.getAttribute('data-view-panel') === view;
        p.classList.toggle('is-active', on);
        p.hidden = !on;
      });
      if (view === 'kills') renderKills();
      if (view === 'deaths') renderDeaths();
      if (view === 'calor') renderHeat();
      if (view === 'replay') {
        if (typeof replayEnter === 'function') replayEnter();
      } else if (typeof replayPause === 'function') {
        replayPause();
      }
      if (view === 'rounds') {
        if (typeof roundsEnter === 'function') roundsEnter();
      } else if (typeof roundsPause === 'function') {
        roundsPause();
      }
    });
  });

  /* ---- Chat (só na análise) ---- */
  var form = document.getElementById('demo-chat-form');
  var log = document.getElementById('demo-chat-log');
  var status = document.getElementById('demo-chat-status');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      status.textContent = 'Pensando...';
      fetch('<?= SITE_URL ?>/admin/demos/api_chat.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) throw new Error(data.error || 'Erro');
          function add(papel, texto) {
            var div = document.createElement('div');
            div.className = 'demo-chat-msg demo-chat-msg--' + papel;
            div.innerHTML = '<strong>' + (papel === 'assistant' ? 'Analista' : 'Você') + ':</strong><div></div>';
            div.querySelector('div').textContent = texto;
            log.appendChild(div);
          }
          add('user', fd.get('pergunta'));
          add('assistant', data.resposta);
          form.pergunta.value = '';
          status.textContent = '';
          log.scrollTop = log.scrollHeight;
        })
        .catch(function (err) {
          status.textContent = err.message;
        });
    });
  }

  /* ---- Regenerar relatório Fase A ---- */
  var regenBtn = document.getElementById('demo-regen-report');
  var regenStatus = document.getElementById('demo-regen-status');
  var reportBody = document.getElementById('demo-report-body');
  if (regenBtn && reportBody) {
    regenBtn.addEventListener('click', function () {
      if (regenBtn.disabled) return;
      if (!confirm('Regenerar o relatório com o template Fase A (usa OpenAI)?')) return;
      regenBtn.disabled = true;
      if (regenStatus) regenStatus.textContent = 'Gerando relatório… (pode levar ~20–40s)';
      var fd = new FormData();
      fd.append('demo_id', regenBtn.getAttribute('data-demo-id'));
      fd.append('token', regenBtn.getAttribute('data-token'));
      fetch('<?= SITE_URL ?>/admin/demos/api_regenerar_relatorio.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) throw new Error(data.error || 'Erro');
          reportBody.innerHTML = data.html || '';
          if (regenStatus) regenStatus.textContent = 'Relatório atualizado.';
        })
        .catch(function (err) {
          if (regenStatus) regenStatus.textContent = err.message;
        })
        .finally(function () {
          regenBtn.disabled = false;
        });
    });
  }

  /* ---- Radar / kills / deaths ---- */
  var DATA = window.DEMO_KILLS_DATA || { kills: [], rounds: [], tickrate: 64, elenco: [], radar: null };
  var sideFilter = 'both';
  var deathsSideFilter = 'both';
  var ROUND_START = {};
  var ROUND_FIRST_KILL_TICK = {};
  (function buildRoundStarts() {
    (DATA.rounds || []).forEach(function (r) {
      var rn = Number(r.round);
      if (!rn || r.tick_start == null) return;
      var ts = Number(r.tick_start);
      if (ROUND_START[rn] == null || ts > ROUND_START[rn]) {
        ROUND_START[rn] = ts;
      }
    });
    // Fallback: 1ª kill do round como âncora se tick_start faltar / estiver errado
    (DATA.kills || []).forEach(function (k) {
      var rn = Number(k.round);
      if (!rn || k.tick == null) return;
      var tk = Number(k.tick);
      if (ROUND_FIRST_KILL_TICK[rn] == null || tk < ROUND_FIRST_KILL_TICK[rn]) {
        ROUND_FIRST_KILL_TICK[rn] = tk;
      }
    });
  })();

  function nickMatch(demoNick, oficial) {
    if (!demoNick || !oficial) return false;
    if (String(demoNick).toLowerCase() === String(oficial).toLowerCase()) return true;
    var a = String(demoNick).toLowerCase().replace(/[^a-z0-9]/g, '');
    var b = String(oficial).toLowerCase().replace(/[^a-z0-9]/g, '');
    if (!a || !b) return false;
    return a === b || a.indexOf(b) !== -1 || b.indexOf(a) !== -1;
  }

  function isElencoKill(assassino, selected) {
    for (var i = 0; i < selected.length; i++) {
      if (nickMatch(assassino, selected[i].nick)) return selected[i];
    }
    return null;
  }

  function worldToPercent(x, y, radar, imgW, imgH) {
    var base = radar.base_size || 1024;
    var px = (x - radar.pos_x) / radar.scale;
    var py = (radar.pos_y - y) / radar.scale;
    if (Number(radar.rotate) === 1) {
      var tmp = px;
      px = py;
      py = base - tmp;
    }
    var nx = px / base;
    var ny = py / base;
    var mode = radar.inset_mode || 'none';
    var il = Number(radar.inset_left) || 0;
    var it = Number(radar.inset_top) || 0;
    var ir = Number(radar.inset_right) || 0;
    var ib = Number(radar.inset_bottom) || 0;
    var left;
    var top;
    if (mode === 'crop') {
      // Arte = só área jogável; coords overview incluem borda → remove inset
      var aw = Math.max(0.2, 1 - il - ir);
      var ah = Math.max(0.2, 1 - it - ib);
      left = ((nx - il) / aw) * 100;
      top = ((ny - it) / ah) * 100;
    } else if (mode === 'pad') {
      // Coords na área jogável; imagem tem borda → empurra pra dentro
      left = (il + nx * (1 - il - ir)) * 100;
      top = (it + ny * (1 - it - ib)) * 100;
    } else {
      left = nx * 100;
      top = ny * 100;
    }
    left += Number(radar.offset_x) || 0;
    top += Number(radar.offset_y) || 0;
    return { left: left, top: top };
  }

  function isPlausibleRoundSec(t) {
    return t != null && !isNaN(t) && t >= 0 && t <= 210;
  }

  /** Tempo decorrido no round (nunca usa tempo absoluto da demo). */
  function roundElapsedSec(k) {
    var tickrate = Number(DATA.tickrate) || 64;
    var rn = Number(k.round);
    var tick = k.tick != null ? Number(k.tick) : null;

    function fromStart(startTick) {
      if (tick == null || startTick == null) return null;
      return (tick - Number(startTick)) / tickrate;
    }

    // 1) tick_start oficial do round
    var t = fromStart(ROUND_START[rn]);
    if (isPlausibleRoundSec(t)) return t;

    // 2) round_time_sec do parser, se fizer sentido
    if (k.round_time_sec != null) {
      var rt = Number(k.round_time_sec);
      if (isPlausibleRoundSec(rt)) return rt;
    }

    // 3) Âncora = primeira kill do mesmo round (relativo)
    //    A 1ª kill fica ~0s; as seguintes mostram gap — melhor que ficar sem label
    var first = ROUND_FIRST_KILL_TICK[rn];
    t = fromStart(first);
    if (isPlausibleRoundSec(t)) return t;

    return null;
  }

  function formatTempo(k) {
    var t = roundElapsedSec(k);
    if (!isPlausibleRoundSec(t)) return '—';
    var m = Math.floor(t / 60);
    var s = Math.floor(t % 60);
    if (m > 0) return m + ':' + String(s).padStart(2, '0');
    return s + 's';
  }

  function selectedPlayers(selector) {
    var out = [];
    document.querySelectorAll(selector + ' input[type=checkbox]:checked').forEach(function (cb) {
      out.push({ nick: cb.value, color: cb.getAttribute('data-color') || '#f5c542' });
    });
    return out;
  }

  function placeMarker(layer, pct, color, title, tempo) {
    var marker = document.createElement('div');
    marker.className = 'demo-kill-marker';
    marker.style.left = pct.left + '%';
    marker.style.top = pct.top + '%';
    marker.style.setProperty('--mk', color);
    marker.title = title;

    var skull = document.createElement('span');
    skull.className = 'demo-kill-skull';
    skull.textContent = '☠';
    marker.appendChild(skull);

    if (tempo) {
      var tEl = document.createElement('span');
      tEl.className = 'demo-kill-time';
      tEl.textContent = tempo;
      marker.appendChild(tEl);
    }
    layer.appendChild(marker);
  }

  function renderKills() {
    var layer = document.getElementById('demo-radar-markers');
    var img = document.getElementById('demo-radar-img');
    var countEl = document.getElementById('kills-count-label');
    if (!layer || !img || !DATA.radar) return;

    var selected = selectedPlayers('#kills-player-filters');
    var radar = DATA.radar;
    layer.innerHTML = '';

    var placed = 0;
    (DATA.kills || []).forEach(function (k) {
      var who = isElencoKill(k.assassino, selected);
      if (!who) return;
      if (sideFilter === 'TR' && k.time_assassino !== 'TR') return;
      if (sideFilter === 'CT' && k.time_assassino !== 'CT') return;

      var pos = k.pos_vitima;
      if (!pos || pos.x == null || pos.y == null) return;

      var pct = worldToPercent(Number(pos.x), Number(pos.y), radar, img.clientWidth, img.clientHeight);
      if (pct.left < -5 || pct.left > 105 || pct.top < -5 || pct.top > 105) return;

      placeMarker(
        layer,
        pct,
        who.color,
        who.nick + ' → ' + (k.vitima || '?') + ' · R' + (k.round || '?') + ' · ' + (k.arma || ''),
        formatTempo(k)
      );
      placed++;
    });

    if (countEl) countEl.textContent = placed + ' kill' + (placed === 1 ? '' : 's');
  }

  function renderDeaths() {
    var layer = document.getElementById('demo-deaths-markers');
    var img = document.getElementById('demo-deaths-img');
    var countEl = document.getElementById('deaths-count-label');
    if (!layer || !img || !DATA.radar) return;

    var selected = selectedPlayers('#deaths-player-filters');
    var radar = DATA.radar;
    layer.innerHTML = '';

    var placed = 0;
    (DATA.kills || []).forEach(function (k) {
      // Deaths: o jogador filtrado é a VÍTIMA
      var who = isElencoKill(k.vitima, selected);
      if (!who) return;
      if (deathsSideFilter === 'TR' && k.time_vitima !== 'TR') return;
      if (deathsSideFilter === 'CT' && k.time_vitima !== 'CT') return;

      var pos = k.pos_vitima;
      if (!pos || pos.x == null || pos.y == null) return;

      var pct = worldToPercent(Number(pos.x), Number(pos.y), radar, img.clientWidth, img.clientHeight);
      if (pct.left < -5 || pct.left > 105 || pct.top < -5 || pct.top > 105) return;

      placeMarker(
        layer,
        pct,
        who.color,
        who.nick + ' morto por ' + (k.assassino || '?') + ' · R' + (k.round || '?') + ' · ' + (k.arma || ''),
        formatTempo(k)
      );
      placed++;
    });

    if (countEl) countEl.textContent = placed + ' death' + (placed === 1 ? '' : 's');
  }

  document.querySelectorAll('#kills-player-filters input').forEach(function (cb) {
    cb.addEventListener('change', renderKills);
  });
  document.querySelectorAll('#deaths-player-filters input').forEach(function (cb) {
    cb.addEventListener('change', renderDeaths);
  });

  var sideBtns = document.querySelectorAll('#kills-side-filter button');
  sideBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      sideBtns.forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      sideFilter = btn.getAttribute('data-side') || 'both';
      renderKills();
    });
  });

  var deathsSideBtns = document.querySelectorAll('#deaths-side-filter button');
  deathsSideBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      deathsSideBtns.forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      deathsSideFilter = btn.getAttribute('data-side') || 'both';
      renderDeaths();
    });
  });

  /* ---- Heatmap ---- */
  var heatSide = 'both';

  function selectedHeatPlayers() {
    var out = [];
    document.querySelectorAll('#heat-player-filters input[type="checkbox"]:checked').forEach(function (cb) {
      out.push(cb.value);
    });
    return out;
  }

  function heatNickMatch(demoNick, nicks) {
    for (var i = 0; i < nicks.length; i++) {
      if (nickMatch(demoNick, nicks[i])) return true;
    }
    return false;
  }

  function blurGrid(grid, gw, gh, passes) {
    var tmp = new Float32Array(grid.length);
    for (var p = 0; p < passes; p++) {
      for (var y = 0; y < gh; y++) {
        for (var x = 0; x < gw; x++) {
          var sum = 0;
          var n = 0;
          for (var dy = -1; dy <= 1; dy++) {
            for (var dx = -1; dx <= 1; dx++) {
              var xx = x + dx;
              var yy = y + dy;
              if (xx < 0 || yy < 0 || xx >= gw || yy >= gh) continue;
              sum += grid[yy * gw + xx];
              n++;
            }
          }
          tmp[y * gw + x] = sum / n;
        }
      }
      for (var i = 0; i < grid.length; i++) grid[i] = tmp[i];
    }
  }

  function renderHeat() {
    var canvas = document.getElementById('demo-heat-canvas');
    var img = document.getElementById('demo-heat-img');
    var countEl = document.getElementById('heat-count-label');
    if (!canvas || !img || !DATA.radar) return;

    var w = img.clientWidth || img.naturalWidth;
    var h = img.clientHeight || img.naturalHeight;
    if (!w || !h) return;

    // bitmap = tamanho da imagem; CSS mantém o canvas por cima do radar
    canvas.width = w;
    canvas.height = h;
    canvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;pointer-events:none;mix-blend-mode:normal;opacity:0.92;margin:0;padding:0;border:0;display:block;';
    var ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);

    var nicks = selectedHeatPlayers();
    var frames = DATA.frames || [];
    var zoneToggleEarly = document.getElementById('heat-show-zones');
    if (!nicks.length || !frames.length) {
      if (countEl) countEl.textContent = '0 amostras';
      if (zoneToggleEarly && zoneToggleEarly.checked) {
        drawZonesOverlay(ctx, w, h);
      }
      return;
    }

    var radar = DATA.radar;
    var gw = 128;
    var gh = 128;
    var grid = new Float32Array(gw * gh);
    var samples = 0;

    frames.forEach(function (f) {
      var plist = f.p || [];
      for (var i = 0; i < plist.length; i++) {
        var pl = plist[i];
        if (!pl || pl.a === false) continue;
        if (!heatNickMatch(pl.n, nicks)) continue;
        if (heatSide === 'TR' && pl.t !== 'TR') continue;
        if (heatSide === 'CT' && pl.t !== 'CT') continue;
        if (pl.x == null || pl.y == null) continue;

        var pct = worldToPercent(Number(pl.x), Number(pl.y), radar, w, h);
        if (pct.left < 0 || pct.left > 100 || pct.top < 0 || pct.top > 100) continue;

        var gx = Math.min(gw - 1, Math.max(0, Math.floor((pct.left / 100) * gw)));
        var gy = Math.min(gh - 1, Math.max(0, Math.floor((pct.top / 100) * gh)));
        // carimbo maior = manchas mais visíveis
        for (var dy = -2; dy <= 2; dy++) {
          for (var dx = -2; dx <= 2; dx++) {
            var xx = gx + dx;
            var yy = gy + dy;
            if (xx < 0 || yy < 0 || xx >= gw || yy >= gh) continue;
            var dist = Math.abs(dx) + Math.abs(dy);
            var add = dist === 0 ? 1.4 : dist === 1 ? 0.7 : 0.25;
            grid[yy * gw + xx] += add;
          }
        }
        samples++;
      }
    });

    blurGrid(grid, gw, gh, 2);

    var max = 0;
    for (var i = 0; i < grid.length; i++) {
      if (grid[i] > max) max = grid[i];
    }
    if (max <= 0) {
      if (countEl) countEl.textContent = '0 amostras';
      return;
    }

    // percentil alto como “quente” — evita 1 pico único apagar o resto
    var sorted = Array.prototype.slice.call(grid).filter(function (v) { return v > 0; });
    sorted.sort(function (a, b) { return a - b; });
    var hot = sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * 0.92))] || max;
    if (hot < max * 0.35) hot = max * 0.35;

    function heatColor(t) {
      // azul → ciano → verde → amarelo → vermelho (vermelho chega cedo)
      var r, g, b;
      if (t < 0.2) {
        var u = t / 0.2;
        r = 0;
        g = Math.floor(80 + 140 * u);
        b = 255;
      } else if (t < 0.4) {
        var u2 = (t - 0.2) / 0.2;
        r = 0;
        g = Math.floor(220 + 35 * u2);
        b = Math.floor(255 * (1 - u2));
      } else if (t < 0.6) {
        var u3 = (t - 0.4) / 0.2;
        r = Math.floor(255 * u3);
        g = 255;
        b = 0;
      } else if (t < 0.8) {
        var u4 = (t - 0.6) / 0.2;
        r = 255;
        g = Math.floor(255 * (1 - u4 * 0.65));
        b = 0;
      } else {
        var u5 = (t - 0.8) / 0.2;
        r = 255;
        g = Math.floor(90 * (1 - u5));
        b = Math.floor(40 * u5);
      }
      return [r, g, b];
    }

    var off = document.createElement('canvas');
    off.width = gw;
    off.height = gh;
    var octx = off.getContext('2d');
    var imgData = octx.createImageData(gw, gh);
    for (var y = 0; y < gh; y++) {
      for (var x = 0; x < gw; x++) {
        var raw = grid[y * gw + x];
        var v = raw / hot;
        if (v < 0.04) continue;
        if (v > 1) v = 1;
        // curva suave: mid-range sobe rápido → mais amarelo/vermelho
        v = Math.pow(v, 0.55);
        var rgb = heatColor(v);
        var idx = (y * gw + x) * 4;
        // passagem (azul/verde): bem clara; hotspots (amarelo/vermelho): fortes
        var a;
        if (v < 0.35) {
          a = 0.08 + v * 0.45;
        } else if (v < 0.65) {
          a = 0.28 + (v - 0.35) * 1.1;
        } else {
          a = 0.62 + (v - 0.65) * 1.05;
        }
        imgData.data[idx] = rgb[0];
        imgData.data[idx + 1] = rgb[1];
        imgData.data[idx + 2] = rgb[2];
        imgData.data[idx + 3] = Math.floor(Math.min(1, a) * 255);
      }
    }
    octx.putImageData(imgData, 0, 0);

    // glow suave + núcleo nítido (sem diluir o vermelho)
    ctx.save();
    ctx.imageSmoothingEnabled = true;
    ctx.globalAlpha = 0.45;
    ctx.filter = 'blur(10px)';
    ctx.drawImage(off, 0, 0, w, h);
    ctx.filter = 'none';
    ctx.globalAlpha = 1;
    ctx.drawImage(off, 0, 0, w, h);
    ctx.restore();

    var labelNicks = nicks.length <= 2
      ? nicks.join(', ')
      : (nicks.length + ' jogadores');
    if (countEl) countEl.textContent = samples + ' amostras · ' + labelNicks;

    // Overlay de zonas (validação Fase B)
    var zoneToggle = document.getElementById('heat-show-zones');
    if (zoneToggle && zoneToggle.checked) {
      drawZonesOverlay(ctx, w, h);
    }
  }

  function drawZonesOverlay(ctx, w, h) {
    var zones = DATA.zonas_radar;
    if (!zones || !DATA.radar) return;
    ctx.save();
    ctx.lineWidth = 1.5;
    ctx.font = 'bold 10px system-ui,sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    Object.keys(zones).forEach(function (id) {
      var z = zones[id];
      if (!z) return;
      var x0 = (Number(z.nx0) || 0) * w;
      var y0 = (Number(z.ny0) || 0) * h;
      var x1 = (Number(z.nx1) || 0) * w;
      var y1 = (Number(z.ny1) || 0) * h;
      var rw = x1 - x0;
      var rh = y1 - y0;
      ctx.strokeStyle = 'rgba(255, 220, 100, 0.85)';
      ctx.fillStyle = 'rgba(255, 200, 60, 0.08)';
      ctx.fillRect(x0, y0, rw, rh);
      ctx.strokeRect(x0, y0, rw, rh);
      ctx.fillStyle = 'rgba(0,0,0,0.55)';
      var label = z.label || id;
      var tw = ctx.measureText(label).width + 8;
      ctx.fillRect(x0 + rw / 2 - tw / 2, y0 + rh / 2 - 7, tw, 14);
      ctx.fillStyle = '#ffe08a';
      ctx.fillText(label, x0 + rw / 2, y0 + rh / 2);
    });
    ctx.restore();
  }

  document.querySelectorAll('#heat-player-filters input').forEach(function (el) {
    el.addEventListener('change', renderHeat);
  });
  var heatAllBtn = document.getElementById('heat-all');
  var heatNoneBtn = document.getElementById('heat-none');
  if (heatAllBtn) {
    heatAllBtn.addEventListener('click', function () {
      document.querySelectorAll('#heat-player-filters input[type="checkbox"]').forEach(function (cb) {
        cb.checked = true;
      });
      renderHeat();
    });
  }
  if (heatNoneBtn) {
    heatNoneBtn.addEventListener('click', function () {
      document.querySelectorAll('#heat-player-filters input[type="checkbox"]').forEach(function (cb) {
        cb.checked = false;
      });
      renderHeat();
    });
  }
  var heatSideBtns = document.querySelectorAll('#heat-side-filter button');
  heatSideBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      heatSideBtns.forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      heatSide = btn.getAttribute('data-side') || 'both';
      renderHeat();
    });
  });
  var heatZonesCb = document.getElementById('heat-show-zones');
  if (heatZonesCb) {
    heatZonesCb.addEventListener('change', renderHeat);
  }

  var img = document.getElementById('demo-radar-img');
  if (img) {
    if (img.complete) { /* ok */ }
    else img.addEventListener('load', function () {
      if (!document.getElementById('view-kills').hidden) renderKills();
    });
  }
  var heatImg = document.getElementById('demo-heat-img');
  if (heatImg) {
    heatImg.addEventListener('load', function () {
      if (!document.getElementById('view-calor').hidden) renderHeat();
    });
  }
  var deathsImg = document.getElementById('demo-deaths-img');
  if (deathsImg) {
    deathsImg.addEventListener('load', function () {
      if (!document.getElementById('view-deaths').hidden) renderDeaths();
    });
  }

  window.addEventListener('resize', function () {
    if (!document.getElementById('view-kills').hidden) renderKills();
    if (!document.getElementById('view-deaths').hidden) renderDeaths();
    if (!document.getElementById('view-calor').hidden) renderHeat();
    if (!document.getElementById('view-replay').hidden && typeof replayRender === 'function') replayRender();
  });

  /* ---- Replay 2D ---- */
  var replaySide = 'both';
  var replayRound = 1;
  var replayTick = 0;
  var replayPlaying = false;
  var replaySpeed = 1;
  var replayRaf = 0;
  var replayLastTs = 0;
  var replayFramesByRound = {};
  var replayShotsByRound = {};
  var replayGrenadesByRound = {};
  var replayRoundMeta = {};
  var replayReady = false;
  var DROP_SVG =
    '<svg viewBox="0 0 24 28" xmlns="http://www.w3.org/2000/svg">' +
    '<path d="M12 1.5C12 1.5 22 12.2 22 18.2a10 10 0 1 1-20 0C2 12.2 12 1.5 12 1.5z" fill="VAR_FILL" stroke="#111" stroke-width="1.4"/>' +
    '<circle cx="12" cy="18.2" r="3.1" fill="#111" opacity="0.35"/>' +
    '</svg>';

  function replayIsOurs(nick) {
    var elenco = DATA.elenco || [];
    for (var i = 0; i < elenco.length; i++) {
      if (nickMatch(nick, elenco[i])) return true;
    }
    return false;
  }

  function replayFmtTime(sec) {
    sec = Math.max(0, Math.floor(sec || 0));
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function replayBuildIndex() {
    replayFramesByRound = {};
    (DATA.frames || []).forEach(function (f) {
      var r = Number(f.r || f.round || 0);
      if (!r) return;
      if (!replayFramesByRound[r]) replayFramesByRound[r] = [];
      replayFramesByRound[r].push(f);
    });
    Object.keys(replayFramesByRound).forEach(function (r) {
      replayFramesByRound[r].sort(function (a, b) { return (a.t || 0) - (b.t || 0); });
    });

    replayShotsByRound = {};
    (DATA.shots || []).forEach(function (s) {
      var r = Number(s.r || s.round || 0);
      if (!r) return;
      if (!replayShotsByRound[r]) replayShotsByRound[r] = [];
      replayShotsByRound[r].push(s);
    });
    Object.keys(replayShotsByRound).forEach(function (r) {
      replayShotsByRound[r].sort(function (a, b) { return (a.t || 0) - (b.t || 0); });
    });

    replayGrenadesByRound = {};
    (DATA.grenades || []).forEach(function (g) {
      var r = Number(g.r || g.round || 0);
      if (!r) return;
      if (!replayGrenadesByRound[r]) replayGrenadesByRound[r] = [];
      replayGrenadesByRound[r].push(g);
    });
    Object.keys(replayGrenadesByRound).forEach(function (r) {
      replayGrenadesByRound[r].sort(function (a, b) { return (a.t0 || 0) - (b.t0 || 0); });
    });

    replayRoundMeta = {};
    var tr = DATA.tickrate || 64;
    (DATA.rounds || []).forEach(function (rm) {
      var rn = Number(rm.round);
      if (!rn) return;
      var ts = rm.tick_start != null ? Number(rm.tick_start) : null;
      var te = rm.tick_end != null ? Number(rm.tick_end) : null;
      var frames = replayFramesByRound[rn] || [];
      if (ts == null && frames.length) ts = Number(frames[0].t);
      if (te == null && frames.length) te = Number(frames[frames.length - 1].t);
      if (ts == null) ts = 0;
      if (te == null || te <= ts) te = ts + Math.round(tr * 100);
      // frames podem ir além do round_end (decisão) — usa o último frame
      if (frames.length) {
        var lastFt = Number(frames[frames.length - 1].t);
        if (lastFt > te) te = lastFt;
      }
      replayRoundMeta[rn] = {
        tickStart: ts,
        tickEnd: te,
        winner: rm.winner || 'UNK',
        durationSec: (te - ts) / tr
      };
    });

    Object.keys(replayFramesByRound).forEach(function (r) {
      r = Number(r);
      if (replayRoundMeta[r]) return;
      var frames = replayFramesByRound[r];
      var ts = Number(frames[0].t);
      var te = Number(frames[frames.length - 1].t);
      if (te <= ts) te = ts + Math.round(tr * 60);
      replayRoundMeta[r] = { tickStart: ts, tickEnd: te, winner: 'UNK', durationSec: (te - ts) / tr };
    });

    // Estende cada round até o início do próximo (evita corte no round_end precoce)
    var roundNumsSorted = Object.keys(replayRoundMeta).map(Number).sort(function (a, b) { return a - b; });
    for (var ri = 0; ri < roundNumsSorted.length; ri++) {
      var cur = replayRoundMeta[roundNumsSorted[ri]];
      if (!cur) continue;
      if (ri + 1 < roundNumsSorted.length) {
        var nxt = replayRoundMeta[roundNumsSorted[ri + 1]];
        if (nxt && nxt.tickStart > cur.tickEnd) {
          cur.tickEnd = nxt.tickStart;
        }
      }
      cur.durationSec = (cur.tickEnd - cur.tickStart) / tr;
    }
  }

  function replayRoundList() {
    var nums = Object.keys(replayRoundMeta).map(Number).sort(function (a, b) { return a - b; });
    return nums;
  }

  function replayLerp(a, b, t) {
    return a + (b - a) * t;
  }

  function replayLerpAngle(a, b, t) {
    if (a == null || isNaN(a)) return b;
    if (b == null || isNaN(b)) return a;
    var d = ((b - a + 540) % 360) - 180;
    return a + d * t;
  }

  function replayPlayerMap(list) {
    var m = {};
    (list || []).forEach(function (pl) {
      if (pl && pl.n) m[pl.n] = pl;
    });
    return m;
  }

  /** Interpola entre 2 frames → movimento fluido (não “PowerPoint”). */
  function replaySamplePlayers(round, tick) {
    var frames = replayFramesByRound[round] || [];
    if (!frames.length) return [];
    if (tick <= (frames[0].t || 0)) return (frames[0].p || []).slice();
    var last = frames[frames.length - 1];
    if (tick >= (last.t || 0)) return (last.p || []).slice();

    var lo = 0;
    var hi = frames.length - 1;
    while (lo <= hi) {
      var mid = (lo + hi) >> 1;
      var mt = frames[mid].t || 0;
      if (mt < tick) lo = mid + 1;
      else hi = mid - 1;
    }
    var b = frames[Math.min(frames.length - 1, Math.max(0, lo))];
    var a = frames[Math.max(0, lo - 1)];
    if (!a) return (b.p || []).slice();
    if (!b || a === b) return (a.p || []).slice();

    var ta = a.t || 0;
    var tb = b.t || 0;
    var u = tb > ta ? (tick - ta) / (tb - ta) : 0;
    if (u < 0) u = 0;
    if (u > 1) u = 1;

    var mapA = replayPlayerMap(a.p);
    var mapB = replayPlayerMap(b.p);
    var names = {};
    Object.keys(mapA).forEach(function (n) { names[n] = true; });
    Object.keys(mapB).forEach(function (n) { names[n] = true; });

    var out = [];
    Object.keys(names).forEach(function (n) {
      var pa = mapA[n];
      var pb = mapB[n];
      var src = pb || pa;
      if (!src) return;
      // morto: congela na posição (não desliza pra parede)
      if (src.a === false || (pa && pa.a === false) || (pb && pb.a === false)) {
        var dead = (pb && pb.a === false) ? pb : (pa && pa.a === false ? pa : src);
        out.push({
          n: n,
          t: dead.t,
          a: false,
          x: dead.x,
          y: dead.y,
          yaw: dead.yaw,
          hp: 0,
          w: dead.w || null,
          u: dead.u || null,
          $: dead.$ != null ? dead.$ : null,
          ar: dead.ar != null ? dead.ar : null,
          hel: dead.hel || 0,
          kit: dead.kit || 0
        });
        return;
      }
      if (!pa || pa.x == null || pa.y == null) {
        out.push(Object.assign({}, pb));
        return;
      }
      if (!pb || pb.x == null || pb.y == null) {
        out.push(Object.assign({}, pa));
        return;
      }
      out.push({
        n: n,
        t: pb.t || pa.t,
        a: true,
        x: replayLerp(Number(pa.x), Number(pb.x), u),
        y: replayLerp(Number(pa.y), Number(pb.y), u),
        yaw: replayLerpAngle(
          pa.yaw != null ? Number(pa.yaw) : null,
          pb.yaw != null ? Number(pb.yaw) : null,
          u
        ),
        hp: pb.hp != null ? pb.hp : pa.hp,
        w: pb.w || pa.w || null,
        u: pb.u || pa.u || null,
        $: pb.$ != null ? pb.$ : pa.$,
        ar: pb.ar != null ? pb.ar : pa.ar,
        hel: pb.hel != null ? pb.hel : pa.hel,
        kit: pb.kit != null ? pb.kit : pa.kit
      });
    });
    return out;
  }

  /** Ícones SVG inline (nítidos, sem sprite embassado). */
  var UTIL_SVG = {
    armor: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l8 3.2v6.1C20 17 16.2 21.2 12 22.5 7.8 21.2 4 17 4 11.3V5.2L12 2zm0 2.4L6 6.6v4.7c0 4.2 2.5 7.8 6 9 3.5-1.2 6-4.8 6-9V6.6l-6-2.2z"/><path fill="currentColor" d="M9 10.5h6v1.6h-1.7V17h-2.6v-4.9H9z"/></svg>',
    helmet: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3.5 13.2c0-4.7 3.8-8.5 8.5-8.5s8.5 3.8 8.5 8.5V16H3.5v-2.8zm2.3.2h12.4A6.2 6.2 0 0 0 12 6.9a6.2 6.2 0 0 0-6.2 6.5z"/><path fill="currentColor" d="M2.5 16.5h19V19H2.5z"/></svg>',
    kit: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.8 3.2h4.4l1.7 3.3H20v2.8h-1.3l-1.5 11.2H6.8L5.3 9.3H4V6.5h4.1L9.8 3.2zm1.2 2.1l-.7 1.2h3.4l-.7-1.2h-2zM8 9.3l1.2 9.2h5.6L16 9.3H8z"/></svg>',
    he: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10.6 3.2h2.8v3.4h-2.8z"/><path fill="currentColor" d="M12 8.2a7 7 0 1 1 0 14 7 7 0 0 1 0-14zm-1.2 3.2h2.4v2.2h2.1v2.3h-2.1V18h-2.4v-2.1H8.7v-2.3h2.1v-2.2z"/></svg>',
    flash: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.2 4.2h5.6c1.4 0 2.5 1.1 2.5 2.5v10.6c0 1.4-1.1 2.5-2.5 2.5H9.2c-1.4 0-2.5-1.1-2.5-2.5V6.7c0-1.4 1.1-2.5 2.5-2.5zm1.3 2.4v2.4h3v-2.4h-3zm0 4.2v2.6h3v-2.6h-3z"/><path fill="currentColor" d="M11 20.2h2V22h-2z"/></svg>',
    smoke: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 3.8h6c1.7 0 3 1.3 3 3v11.4c0 1.7-1.3 3-3 3H9c-1.7 0-3-1.3-3-3V6.8c0-1.7 1.3-3 3-3zm1.4 2.6v2.2h3.2V6.4H10.4zm0 4v2.2h3.2v-2.2h-3.2z"/><circle cx="12" cy="17.6" r="1.3" fill="#111"/></svg>',
    molotov: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.2 2.8h1.6c.6 1.4 1.4 2.6 2.2 3.6.9 1.2 1.6 2.5 1.6 4.1 0 2.8-1.4 4.5-3.6 4.5S9.4 13.3 9.4 10.5c0-1.6.7-2.9 1.6-4.1.8-1 1.6-2.2 2.2-3.6z"/><path fill="currentColor" d="M9.6 14.8h4.8v5.4c0 1-.8 1.8-1.8 1.8h-1.2c-1 0-1.8-.8-1.8-1.8v-5.4z"/></svg>',
    decoy: '<svg class="demo-util-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 3.8h6c1.7 0 3 1.3 3 3v11.4c0 1.7-1.3 3-3 3H9c-1.7 0-3-1.3-3-3V6.8c0-1.7 1.3-3 3-3zm1.4 2.6v2.2h3.2V6.4H10.4zm0 4v2.2h3.2v-2.2h-3.2z"/><circle cx="12" cy="17.6" r="1.3" fill="#111"/><path fill="currentColor" d="M16.5 4.2l1.2 1.2-1.8 1.8-1.2-1.2 1.8-1.8z"/></svg>'
  };

  function replayIcon(key, title) {
    var svg = UTIL_SVG[key] || '';
    return '<span class="demo-util-wrap" title="' + title + '">' + svg + '</span>';
  }

  function replayArmorHtml(pl) {
    if (pl && pl.ar != null && Number(pl.ar) > 0) {
      return replayIcon(pl.hel ? 'helmet' : 'armor', pl.hel ? 'Capacete' : 'Colete');
    }
    return '';
  }

  /** Só ícones que tem — o espaço fixo fica no CSS (.demo-replay-gear). */
  function replayUtilHtml(pl) {
    var u = pl && pl.u ? pl.u : {};
    var bits = [];
    var i;
    if (pl && pl.kit) bits.push(replayIcon('kit', 'Kit'));
    if (u.mo) bits.push(replayIcon('molotov', 'Molotov'));
    if (u.fl) {
      for (i = 0; i < Math.min(2, Number(u.fl) || 0); i++) bits.push(replayIcon('flash', 'Flash'));
    }
    if (u.sm) bits.push(replayIcon('smoke', 'Smoke'));
    if (u.he) bits.push(replayIcon('he', 'HE'));
    if (!bits.length) return '';
    return '<span class="demo-replay-util">' + bits.join('') + '</span>';
  }

  /** Só nome + HP no mapa (loadout fica na lateral). */
  function replayMapLabelHtml(pl, ours) {
    var hp = pl.hp != null ? Number(pl.hp) : (pl.a === false ? 0 : 100);
    return (
      '<div class="demo-replay-plabel">' +
        '<div class="demo-replay-label' + (ours ? ' is-ours' : '') + '">' + String(pl.n || '') + '</div>' +
        '<div class="demo-replay-hpbar' + (hp <= 30 ? ' is-low' : '') + '"><span style="width:' + Math.max(0, Math.min(100, hp)) + '%"></span></div>' +
      '</div>'
    );
  }

  function replayFmtMoney(v) {
    if (v == null || isNaN(Number(v))) return '—';
    return '$' + Number(v).toLocaleString('en-US');
  }

  function replayKillsInRound(round) {
    var meta = replayRoundMeta[round];
    if (!meta) return [];
    return (DATA.kills || []).filter(function (k) {
      return Number(k.round) === Number(round);
    });
  }

  function replayPause() {
    replayPlaying = false;
    var btn = document.getElementById('replay-play');
    if (btn) btn.textContent = '▶';
    if (replayRaf) {
      cancelAnimationFrame(replayRaf);
      replayRaf = 0;
    }
  }

  function replayPlay() {
    var meta = replayRoundMeta[replayRound];
    if (!meta) return;
    replayPlaying = true;
    var btn = document.getElementById('replay-play');
    if (btn) btn.textContent = '❚❚';
    replayLastTs = 0;
    function loop(ts) {
      if (!replayPlaying) return;
      if (!replayLastTs) replayLastTs = ts;
      var dt = (ts - replayLastTs) / 1000;
      replayLastTs = ts;
      var tr = DATA.tickrate || 64;
      replayTick += dt * replaySpeed * tr;
      if (replayTick >= meta.tickEnd) {
        replayTick = meta.tickEnd;
        replayRender();
        replayPause();
        return;
      }
      replayRender();
      replayRaf = requestAnimationFrame(loop);
    }
    replayRaf = requestAnimationFrame(loop);
  }

  function replaySetRound(rn) {
    replayPause();
    replayRound = rn;
    var meta = replayRoundMeta[rn];
    if (meta) replayTick = meta.tickStart;
    var label = document.getElementById('replay-round-label');
    if (label) label.textContent = 'Round ' + rn;
    document.querySelectorAll('#replay-rounds .demo-replay-round-btn').forEach(function (b) {
      b.classList.toggle('is-active', Number(b.getAttribute('data-round')) === rn);
    });
    replayBuildKillRail();
    replayRender();
  }

  function replayBuildRoundsUi() {
    var wrap = document.getElementById('replay-rounds');
    if (!wrap) return;
    wrap.innerHTML = '';
    replayRoundList().forEach(function (rn) {
      var meta = replayRoundMeta[rn] || {};
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'demo-replay-round-btn' + (rn === replayRound ? ' is-active' : '');
      btn.setAttribute('data-round', String(rn));
      btn.setAttribute('data-winner', meta.winner || 'UNK');
      btn.textContent = String(rn);
      btn.addEventListener('click', function () { replaySetRound(rn); });
      wrap.appendChild(btn);
    });
  }

  function replayBuildKillRail() {
    var rail = document.getElementById('replay-kills-rail');
    if (!rail) return;
    rail.innerHTML = '';
    var meta = replayRoundMeta[replayRound];
    if (!meta) return;
    var span = meta.tickEnd - meta.tickStart;
    if (span <= 0) return;
    replayKillsInRound(replayRound).forEach(function (k) {
      var kt = k.tick != null ? Number(k.tick) : null;
      if (kt == null && k.round_time_sec != null) {
        kt = meta.tickStart + Number(k.round_time_sec) * (DATA.tickrate || 64);
      }
      if (kt == null) return;
      var pct = ((kt - meta.tickStart) / span) * 100;
      if (pct < 0 || pct > 100) return;
      var mark = document.createElement('span');
      mark.className = 'demo-replay-kill-mark';
      mark.style.left = pct + '%';
      mark.title = (k.assassino || '?') + ' → ' + (k.vitima || '?');
      rail.appendChild(mark);
    });
  }

  function replayRenderRoster(players) {
    var oursEl = document.getElementById('replay-roster-ours');
    var oppEl = document.getElementById('replay-roster-opp');
    if (!oursEl || !oppEl) return;
    oursEl.innerHTML = '';
    oppEl.innerHTML = '';

    var list = (players || []).slice().sort(function (a, b) {
      var ao = replayIsOurs(a.n) ? 0 : 1;
      var bo = replayIsOurs(b.n) ? 0 : 1;
      if (ao !== bo) return ao - bo;
      return String(a.n).localeCompare(String(b.n));
    });

    list.forEach(function (pl) {
      if (replaySide === 'TR' && pl.t !== 'TR') return;
      if (replaySide === 'CT' && pl.t !== 'CT') return;
      var ours = replayIsOurs(pl.n);
      var row = document.createElement('div');
      row.className = 'demo-replay-player-card' + (pl.a === false ? ' is-dead' : '') + (ours ? ' is-ours' : '');
      var color = pl.t === 'TR' ? '#f59e0b' : '#60a5fa';
      var hp = pl.hp != null ? Number(pl.hp) : (pl.a === false ? 0 : 100);
      var weapon = pl.a === false ? 'DEAD' : (pl.w ? String(pl.w) : '—');
      row.innerHTML =
        '<div class="demo-replay-hp' + (hp <= 30 ? ' is-low' : '') + '"><span style="width:' + Math.max(0, Math.min(100, hp)) + '%"></span></div>' +
        '<div class="demo-replay-card-row">' +
          '<div class="demo-replay-card-left">' +
            '<span class="demo-player-dot" style="background:' + color + '"></span>' +
            '<div class="demo-replay-card-id">' +
              '<span class="demo-replay-pname">' + String(pl.n || '?') + '</span>' +
              '<span class="demo-replay-money">' + replayFmtMoney(pl.$) + '</span>' +
            '</div>' +
          '</div>' +
          '<div class="demo-replay-card-right">' +
            '<span class="demo-replay-weapon">' + weapon + '</span>' +
            '<span class="demo-replay-gear">' + replayArmorHtml(pl) + replayUtilHtml(pl) + '</span>' +
          '</div>' +
        '</div>';
      (ours ? oursEl : oppEl).appendChild(row);
    });

    if (!oursEl.children.length) {
      oursEl.innerHTML = '<p class="form-hint">—</p>';
    }
    if (!oppEl.children.length) {
      oppEl.innerHTML = '<p class="form-hint">—</p>';
    }
  }

  function replayFiringNicks(round, tick) {
    var tr = DATA.tickrate || 64;
    var windowTicks = Math.round(tr * 0.22); // ~220ms de rastro
    var set = {};
    var list = replayShotsByRound[round] || [];
    for (var i = 0; i < list.length; i++) {
      var s = list[i];
      var dt = tick - (s.t || 0);
      if (dt < 0) break;
      if (dt <= windowTicks) set[s.n] = true;
    }
    return set;
  }

  function replayIsPistol(weapon) {
    var w = String(weapon || '').toLowerCase();
    return (
      w.indexOf('usp') >= 0 || w.indexOf('glock') >= 0 || w.indexOf('p250') >= 0 ||
      w.indexOf('five') >= 0 || w.indexOf('tec') >= 0 || w.indexOf('cz') >= 0 ||
      w.indexOf('dual') >= 0 || w.indexOf('elite') >= 0 || w.indexOf('deagle') >= 0 ||
      w.indexOf('r8') >= 0 || w.indexOf('revolver') >= 0 || w === 'p2000'
    );
  }

  /** Intervalo mínimo entre balas visíveis (seg) — pistola não vira metralhadora. */
  function replayShotGapSec(weapon) {
    var w = String(weapon || '').toLowerCase();
    if (w.indexOf('awp') >= 0 || w.indexOf('ssg') >= 0 || w.indexOf('scout') >= 0) return 1.1;
    if (w.indexOf('scar') >= 0 || w.indexOf('g3sg') >= 0) return 0.35;
    if (w.indexOf('deagle') >= 0 || w.indexOf('r8') >= 0 || w.indexOf('revolver') >= 0) return 0.22;
    if (replayIsPistol(weapon)) return 0.16;
    if (w.indexOf('ak') >= 0 || w.indexOf('m4') >= 0 || w.indexOf('galil') >= 0 || w.indexOf('famas') >= 0) return 0.09;
    return 0.11;
  }

  /** Tempo de voo da bala (seg). */
  function replayBulletFlightSec(weapon) {
    if (replayIsPistol(weapon)) return 0.26;
    var w = String(weapon || '').toLowerCase();
    if (w.indexOf('awp') >= 0 || w.indexOf('ssg') >= 0 || w.indexOf('scout') >= 0) return 0.28;
    if (w.indexOf('ak') >= 0 || w.indexOf('m4') >= 0) return 0.13;
    return 0.16;
  }

  function replayGrenadeColor(team) {
    return team === 'TR' ? { hex: '#f5a623', rgb: '245,166,35' } : { hex: '#6eb6ff', rgb: '110,182,255' };
  }

  function replayWorldXY(x, y, radar, w, h) {
    var pct = worldToPercent(Number(x), Number(y), radar, w, h);
    return { x: (pct.left / 100) * w, y: (pct.top / 100) * h };
  }

  function replayDrawDashedLine(ctx, x0, y0, x1, y1, color, alpha) {
    ctx.save();
    ctx.strokeStyle = 'rgba(' + color.rgb + ',' + (alpha != null ? alpha : 0.85) + ')';
    ctx.lineWidth = 2;
    ctx.setLineDash([6, 5]);
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(x0, y0);
    ctx.lineTo(x1, y1);
    ctx.stroke();
    ctx.setLineDash([]);
    // ponto no pouso
    ctx.fillStyle = 'rgba(' + color.rgb + ',0.9)';
    ctx.beginPath();
    ctx.arc(x1, y1, 2.4, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  function replayDrawGrenadeIcon(ctx, x, y, type, color) {
    var r = 9;
    ctx.save();
    ctx.fillStyle = 'rgba(12,16,24,0.78)';
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = 'rgba(' + color.rgb + ',0.55)';
    ctx.lineWidth = 1.2;
    ctx.stroke();
    ctx.fillStyle = '#fff';
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 1.3;
    ctx.lineCap = 'round';
    // silhueta simples por tipo
    if (type === 'molotov') {
      ctx.beginPath();
      ctx.moveTo(x, y - 5);
      ctx.quadraticCurveTo(x + 4, y - 1, x + 2.5, y + 3);
      ctx.lineTo(x - 2.5, y + 3);
      ctx.quadraticCurveTo(x - 4, y - 1, x, y - 5);
      ctx.fill();
      ctx.fillRect(x - 2.2, y + 2.5, 4.4, 4);
    } else if (type === 'smoke' || type === 'decoy') {
      ctx.beginPath();
      ctx.roundRect ? ctx.roundRect(x - 3.2, y - 5.5, 6.4, 11, 3) : ctx.rect(x - 3.2, y - 5.5, 6.4, 11);
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(x, y + 4.2, 1.2, 0, Math.PI * 2);
      ctx.fill();
    } else if (type === 'flash') {
      ctx.beginPath();
      ctx.roundRect ? ctx.roundRect(x - 2.8, y - 5.5, 5.6, 11, 2.5) : ctx.rect(x - 2.8, y - 5.5, 5.6, 11);
      ctx.stroke();
      ctx.fillRect(x - 1.6, y - 3.2, 3.2, 2);
      ctx.fillRect(x - 1.6, y + 0.5, 3.2, 2);
    } else {
      // HE
      ctx.beginPath();
      ctx.arc(x, y + 1, 4.2, 0, Math.PI * 2);
      ctx.stroke();
      ctx.fillRect(x - 1.4, y - 5.5, 2.8, 3);
      ctx.fillRect(x - 0.7, y - 1, 1.4, 5);
      ctx.fillRect(x - 2.6, y + 0.8, 5.2, 1.3);
    }
    ctx.restore();
  }

  function replayDrawTimerBadge(ctx, x, y, secLeft) {
    var label = String(Math.max(0, Math.ceil(secLeft)));
    ctx.save();
    ctx.font = 'bold 11px system-ui,Segoe UI,sans-serif';
    var tw = ctx.measureText(label).width;
    var bw = Math.max(16, tw + 8);
    var bh = 14;
    ctx.fillStyle = 'rgba(8,10,14,0.88)';
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(x - bw / 2, y - bh / 2, bw, bh, 7);
    else {
      ctx.rect(x - bw / 2, y - bh / 2, bw, bh);
    }
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, x, y + 0.5);
    ctx.restore();
  }

  function replayDrawCloud(ctx, x, y, color, baseR, alpha) {
    // smoke: nuvem compacta (lobos sobrepostos, escala menor)
    var offs = [
      [0, 0, 0.95], [0.38, -0.2, 0.62], [-0.34, 0.24, 0.6],
      [0.24, 0.34, 0.55], [-0.28, -0.28, 0.58], [0.44, 0.1, 0.48]
    ];
    ctx.save();
    // halo suave bem curto
    ctx.beginPath();
    ctx.fillStyle = 'rgba(' + color.rgb + ',' + (0.12 * alpha) + ')';
    ctx.arc(x, y, baseR * 1.15, 0, Math.PI * 2);
    ctx.fill();
    for (var i = 0; i < offs.length; i++) {
      var o = offs[i];
      ctx.beginPath();
      ctx.fillStyle = 'rgba(' + color.rgb + ',' + (alpha * (0.2 + o[2] * 0.22)) + ')';
      ctx.arc(x + o[0] * baseR, y + o[1] * baseR, baseR * o[2], 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.restore();
  }

  /** Molotov: várias bolinhas juntas + pisca (diferente da smoke). */
  function replayDrawMollyBalls(ctx, x, y, color, baseR, alpha, ageSec) {
    var balls = [
      [0, 0, 0.42], [0.55, -0.15, 0.34], [-0.5, 0.2, 0.36],
      [0.28, 0.52, 0.32], [-0.38, -0.42, 0.3], [0.62, 0.38, 0.28],
      [-0.15, 0.58, 0.26], [0.12, -0.55, 0.27]
    ];
    // pisca geral ~4x/s + fase por bolinha
    var blink = 0.55 + 0.45 * (0.5 + 0.5 * Math.sin(ageSec * Math.PI * 8));
    ctx.save();
    for (var i = 0; i < balls.length; i++) {
      var b = balls[i];
      var phase = 0.65 + 0.35 * (0.5 + 0.5 * Math.sin(ageSec * Math.PI * 10 + i * 1.1));
      var a = alpha * blink * phase;
      var r = baseR * b[2];
      var bx = x + b[0] * baseR * 0.85;
      var by = y + b[1] * baseR * 0.85;
      ctx.beginPath();
      ctx.fillStyle = 'rgba(' + color.rgb + ',' + (0.55 * a) + ')';
      ctx.arc(bx, by, r, 0, Math.PI * 2);
      ctx.fill();
      // núcleo mais claro (brilho)
      ctx.beginPath();
      ctx.fillStyle = 'rgba(255, 230, 180,' + (0.35 * a) + ')';
      ctx.arc(bx - r * 0.15, by - r * 0.15, r * 0.35, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.restore();
  }

  function replayDrawStarburst(ctx, x, y, color, radius, alpha) {
    var rays = 16;
    ctx.save();
    ctx.strokeStyle = 'rgba(' + color.rgb + ',' + (0.85 * alpha) + ')';
    ctx.fillStyle = 'rgba(' + color.rgb + ',' + (0.95 * alpha) + ')';
    ctx.lineWidth = 1.5;
    ctx.lineCap = 'round';
    for (var i = 0; i < rays; i++) {
      var a = (i / rays) * Math.PI * 2;
      var x2 = x + Math.cos(a) * radius;
      var y2 = y + Math.sin(a) * radius;
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.lineTo(x2, y2);
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(x2, y2, 2.1, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.beginPath();
    ctx.fillStyle = 'rgba(' + color.rgb + ',' + (0.55 * alpha) + ')';
    ctx.arc(x, y, radius * 0.28, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  function replayDrawGrenades(ctx, round, tick, w, h, radar) {
    var tr = DATA.tickrate || 64;
    var list = replayGrenadesByRound[round] || [];
    if (!list.length) return;
    var smokeR = Math.min(w, h) * 0.032; // smoke menor
    var mollyR = Math.min(w, h) * 0.038;
    var fxR = Math.min(w, h) * 0.05;

    for (var i = 0; i < list.length; i++) {
      var g = list[i];
      var t0 = Number(g.t0) || 0;
      var t1 = Number(g.t1) || t0;
      var t2 = Number(g.t2) || (t1 + Math.round(tr * 0.5));
      var type = g.type || 'he';
      // smoke = 20s (sobe parse antigo que gravou ~18s de default)
      if (type === 'smoke') {
        var smokeDur = (t2 - t1) / tr;
        if (smokeDur >= 16.5 && smokeDur <= 19.5) {
          t2 = t1 + Math.round(20 * tr);
        } else if (smokeDur <= 0) {
          t2 = t1 + Math.round(20 * tr);
        }
      }
      if (tick < t0 - 2 || tick > t2 + 2) continue;
      if (replaySide === 'TR' && g.team !== 'TR') continue;
      if (replaySide === 'CT' && g.team !== 'CT') continue;
      if (g.x0 == null || g.y0 == null) continue;

      var color = replayGrenadeColor(g.team);
      var p0 = replayWorldXY(g.x0, g.y0, radar, w, h);
      var p1 = g.x1 != null && g.y1 != null
        ? replayWorldXY(g.x1, g.y1, radar, w, h)
        : p0;

      var inFlight = tick >= t0 && tick < t1;
      var active = tick >= t1 && tick <= t2;

      // trajetória tracejada (voo + um pouco após pouso)
      if (tick >= t0 && tick <= Math.min(t2, t1 + Math.round(tr * 2.5))) {
        var fadePath = active ? Math.max(0.25, 1 - (tick - t1) / (tr * 2.5)) : 0.9;
        replayDrawDashedLine(ctx, p0.x, p0.y, p1.x, p1.y, color, fadePath);
      }

      if (inFlight) {
        var u = t1 > t0 ? Math.max(0, Math.min(1, (tick - t0) / (t1 - t0))) : 1;
        var fx = replayLerp(p0.x, p1.x, u);
        var fy = replayLerp(p0.y, p1.y, u);
        replayDrawGrenadeIcon(ctx, fx, fy, type, color);
        continue;
      }

      if (!active) continue;

      var life = Math.max(0.05, (t2 - t1) / tr);
      var age = (tick - t1) / tr;
      var remain = Math.max(0, (t2 - tick) / tr);
      var fadeOut = remain < 0.45 ? remain / 0.45 : 1;

      if (type === 'smoke') {
        replayDrawCloud(ctx, p1.x, p1.y, color, smokeR, 0.95 * fadeOut);
        replayDrawTimerBadge(ctx, p1.x, p1.y, remain);
      } else if (type === 'molotov') {
        replayDrawMollyBalls(ctx, p1.x, p1.y, color, mollyR, fadeOut, age);
        replayDrawTimerBadge(ctx, p1.x, p1.y, remain);
      } else if (type === 'decoy') {
        var pulse = 0.7 + 0.3 * Math.sin(age * 8);
        replayDrawCloud(ctx, p1.x, p1.y, color, smokeR * 0.85 * pulse, 0.7 * fadeOut);
        replayDrawGrenadeIcon(ctx, p1.x, p1.y, 'decoy', color);
        if (remain > 0.4) replayDrawTimerBadge(ctx, p1.x, p1.y + smokeR * 0.9, remain);
      } else {
        // flash / HE — starburst
        var blast = Math.min(1, age / 0.08);
        var expand = fxR * (0.85 + 0.55 * Math.min(1, age / Math.min(0.35, life)));
        replayDrawStarburst(ctx, p1.x, p1.y, color, expand, fadeOut * (0.55 + 0.45 * (1 - blast * 0.2)));
        if (age < 0.35) {
          replayDrawGrenadeIcon(ctx, p1.x, p1.y, type, color);
        }
      }
    }
  }

  function replayDrawTracers(round, tick, w, h, radar) {
    var canvas = document.getElementById('demo-replay-fx');
    if (!canvas) return;
    canvas.width = w;
    canvas.height = h;
    canvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:2;pointer-events:none;';
    var ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);

    // granadas por baixo dos tracers
    replayDrawGrenades(ctx, round, tick, w, h, radar);

    var tr = DATA.tickrate || 64;
    var list = replayShotsByRound[round] || [];
    if (!list.length) return;

    // throttle por jogador conforme cadência da arma
    var lastShown = {};
    var maxRange = Math.min(w, h) * 0.22;

    for (var i = 0; i < list.length; i++) {
      var s = list[i];
      var ageTicks = tick - (s.t || 0);
      if (ageTicks < -1) continue;
      var flight = replayBulletFlightSec(s.w);
      var ageSec = ageTicks / tr;
      if (ageSec > flight + 0.06) continue;
      if (s.x == null || s.y == null || s.yaw == null) continue;

      var nick = s.n || '?';
      var gap = replayShotGapSec(s.w);
      var shotSec = (s.t || 0) / tr;
      if (lastShown[nick] != null && shotSec - lastShown[nick] < gap * 0.92) {
        continue;
      }
      // só registra gap p/ tiros “atuais” (ainda voando)
      if (ageSec >= 0 && ageSec <= flight + 0.06) {
        lastShown[nick] = shotSec;
      }

      var pct = worldToPercent(Number(s.x), Number(s.y), radar, w, h);
      var x0 = (pct.left / 100) * w;
      var y0 = (pct.top / 100) * h;
      var rad = (-Number(s.yaw) * Math.PI) / 180;

      var progress = Math.max(0, Math.min(1.12, ageSec / flight));
      var streak = Math.max(5, maxRange * (replayIsPistol(s.w) ? 0.16 : 0.11));
      var dist = Math.min(maxRange, progress * maxRange);
      var x1 = x0 + Math.cos(rad) * Math.max(0, dist - streak);
      var y1 = y0 + Math.sin(rad) * Math.max(0, dist - streak);
      var x2 = x0 + Math.cos(rad) * dist;
      var y2 = y0 + Math.sin(rad) * dist;
      var fade = progress > 1 ? 1 - (progress - 1) / 0.12 : 1;
      fade = Math.max(0, Math.min(1, fade));

      ctx.save();
      ctx.strokeStyle = 'rgba(255, 230, 160, ' + (0.6 * fade) + ')';
      ctx.lineWidth = replayIsPistol(s.w) ? 2.4 : 2;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(x1, y1);
      ctx.lineTo(x2, y2);
      ctx.stroke();
      ctx.fillStyle = 'rgba(255, 250, 220, ' + (0.95 * fade) + ')';
      ctx.beginPath();
      ctx.arc(x2, y2, replayIsPistol(s.w) ? 2.6 : 2.1, 0, Math.PI * 2);
      ctx.fill();
      if (ageSec < 0.035) {
        ctx.fillStyle = 'rgba(255, 240, 180, ' + (0.85 * (1 - ageSec / 0.035)) + ')';
        ctx.beginPath();
        ctx.arc(x0, y0, 3.2, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.restore();
    }
  }

  function replayRender() {
    var markers = document.getElementById('demo-replay-markers');
    var img = document.getElementById('demo-replay-img');
    var timeline = document.getElementById('replay-timeline');
    var timeEl = document.getElementById('replay-time');
    if (!markers || !img || !DATA.radar) return;

    var meta = replayRoundMeta[replayRound];
    if (!meta) return;

    var w = img.clientWidth || img.naturalWidth;
    var h = img.clientHeight || img.naturalHeight;
    if (!w || !h) return;

    var span = meta.tickEnd - meta.tickStart;
    var progress = span > 0 ? (replayTick - meta.tickStart) / span : 0;
    progress = Math.max(0, Math.min(1, progress));
    if (timeline) timeline.value = String(Math.round(progress * 1000));

    var sec = (replayTick - meta.tickStart) / (DATA.tickrate || 64);
    if (timeEl) timeEl.textContent = replayFmtTime(sec);

    var players = replaySamplePlayers(replayRound, replayTick);
    replayRenderRoster(players);
    replayDrawTracers(replayRound, replayTick, w, h, DATA.radar);

    var firing = replayFiringNicks(replayRound, replayTick);
    markers.innerHTML = '';
    var radar = DATA.radar;
    players.forEach(function (pl) {
      if (!pl || pl.x == null || pl.y == null) return;
      if (replaySide === 'TR' && pl.t !== 'TR') return;
      if (replaySide === 'CT' && pl.t !== 'CT') return;

      var pct = worldToPercent(Number(pl.x), Number(pl.y), radar, w, h);
      if (pct.left < -5 || pct.left > 105 || pct.top < -5 || pct.top > 105) return;

      var ours = replayIsOurs(pl.n);
      var color = pl.t === 'TR' ? '#f5a623' : '#6eb6ff';
      var el = document.createElement('div');
      el.className = 'demo-replay-marker';
      el.style.left = pct.left + '%';
      el.style.top = pct.top + '%';

      if (pl.a === false) {
        el.innerHTML =
          '<div class="demo-replay-skull">☠</div>' +
          replayMapLabelHtml(pl, ours);
      } else {
        var yaw = pl.yaw != null && !isNaN(Number(pl.yaw)) ? Number(pl.yaw) : null;
        var isFire = !!firing[pl.n];
        if (yaw != null) {
          var rot = 90 - yaw;
          var svg = DROP_SVG.replace('VAR_FILL', color);
          el.innerHTML =
            '<div class="demo-replay-drop' + (ours ? ' is-ours' : '') + (isFire ? ' is-firing' : '') +
            '" style="--rot:' + rot + 'deg">' + svg + '</div>' +
            replayMapLabelHtml(pl, ours);
        } else {
          el.innerHTML =
            '<div class="demo-replay-dot' + (ours ? ' is-ours' : '') + '" style="background:' + color + '"></div>' +
            replayMapLabelHtml(pl, ours);
        }
      }
      markers.appendChild(el);
    });
  }

  function replayEnter() {
    if (!replayReady) {
      replayBuildIndex();
      var rounds = replayRoundList();
      replayRound = rounds.length ? rounds[0] : 1;
      replayBuildRoundsUi();
      replayReady = true;
    }
    if (replayRoundMeta[replayRound]) {
      if (replayTick < replayRoundMeta[replayRound].tickStart ||
          replayTick > replayRoundMeta[replayRound].tickEnd) {
        replayTick = replayRoundMeta[replayRound].tickStart;
      }
    }
    replayBuildKillRail();
    var label = document.getElementById('replay-round-label');
    if (label) label.textContent = 'Round ' + replayRound;
    replayRender();
  }

  var replayPlayBtn = document.getElementById('replay-play');
  if (replayPlayBtn) {
    replayPlayBtn.addEventListener('click', function () {
      if (replayPlaying) replayPause();
      else replayPlay();
    });
  }

  var replayTimeline = document.getElementById('replay-timeline');
  if (replayTimeline) {
    replayTimeline.addEventListener('input', function () {
      var meta = replayRoundMeta[replayRound];
      if (!meta) return;
      var v = Number(replayTimeline.value) / 1000;
      replayTick = meta.tickStart + v * (meta.tickEnd - meta.tickStart);
      replayRender();
    });
  }

  document.querySelectorAll('#replay-speed button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#replay-speed button').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      replaySpeed = Number(btn.getAttribute('data-speed')) || 1;
    });
  });

  document.querySelectorAll('#replay-side-filter button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#replay-side-filter button').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      replaySide = btn.getAttribute('data-side') || 'both';
      replayRender();
    });
  });

  var replayImg = document.getElementById('demo-replay-img');
  if (replayImg) {
    replayImg.addEventListener('load', function () {
      if (!document.getElementById('view-replay').hidden) replayRender();
    });
  }

  /* ---- Rounds (paralelo) ---- */
  var roundsSide = 'both';
  var roundsElapsed = 0; // segundos desde o início do round
  var roundsMaxSec = 115;
  var roundsPlaying = false;
  var roundsSpeed = 1;
  var roundsRaf = 0;
  var roundsLastTs = 0;
  var roundsReady = false;

  function roundsSelectedPlayers() {
    var out = [];
    document.querySelectorAll('#rounds-player-filters input[type="checkbox"]:checked').forEach(function (cb) {
      out.push({
        nick: cb.value,
        color: cb.getAttribute('data-color') || '#f5c542'
      });
    });
    return out;
  }

  function roundsEnsureIndex() {
    if (!replayReady) {
      replayBuildIndex();
      replayReady = true;
    }
    var maxDur = 0;
    Object.keys(replayRoundMeta).forEach(function (rn) {
      var d = Number(replayRoundMeta[rn].durationSec) || 0;
      if (d > maxDur) maxDur = d;
    });
    roundsMaxSec = Math.max(90, Math.min(210, maxDur || 115));
  }

  function roundsPause() {
    roundsPlaying = false;
    var btn = document.getElementById('rounds-play');
    if (btn) btn.textContent = '▶';
    if (roundsRaf) {
      cancelAnimationFrame(roundsRaf);
      roundsRaf = 0;
    }
  }

  function roundsPlay() {
    roundsPlaying = true;
    var btn = document.getElementById('rounds-play');
    if (btn) btn.textContent = '❚❚';
    roundsLastTs = 0;
    function loop(ts) {
      if (!roundsPlaying) return;
      if (!roundsLastTs) roundsLastTs = ts;
      var dt = (ts - roundsLastTs) / 1000;
      roundsLastTs = ts;
      roundsElapsed += dt * roundsSpeed;
      if (roundsElapsed >= roundsMaxSec) {
        roundsElapsed = roundsMaxSec;
        roundsRender();
        roundsPause();
        return;
      }
      roundsRender();
      roundsRaf = requestAnimationFrame(loop);
    }
    roundsRaf = requestAnimationFrame(loop);
  }

  function roundsRender() {
    var markers = document.getElementById('demo-rounds-markers');
    var img = document.getElementById('demo-rounds-img');
    var timeline = document.getElementById('rounds-timeline');
    var timeEl = document.getElementById('rounds-time');
    var clockEl = document.getElementById('rounds-clock');
    var activeEl = document.getElementById('rounds-active-label');
    if (!markers || !img || !DATA.radar) return;

    var w = img.clientWidth || img.naturalWidth;
    var h = img.clientHeight || img.naturalHeight;
    if (!w || !h) return;

    var progress = roundsMaxSec > 0 ? roundsElapsed / roundsMaxSec : 0;
    progress = Math.max(0, Math.min(1, progress));
    if (timeline) timeline.value = String(Math.round(progress * 1000));
    var tTxt = replayFmtTime(roundsElapsed);
    if (timeEl) timeEl.textContent = tTxt;
    if (clockEl) clockEl.textContent = tTxt;

    var selected = roundsSelectedPlayers();
    markers.innerHTML = '';
    if (!selected.length) {
      if (activeEl) activeEl.textContent = 'Rounds ativos: 0 · selecione um jogador';
      return;
    }

    var tr = DATA.tickrate || 64;
    var radar = DATA.radar;
    var activeRounds = 0;
    var roundNums = Object.keys(replayRoundMeta).map(Number).sort(function (a, b) { return a - b; });

    roundNums.forEach(function (rn) {
      var meta = replayRoundMeta[rn];
      if (!meta) return;
      var tick = meta.tickStart + roundsElapsed * tr;
      if (tick < meta.tickStart) return;
      // além do fim do round: não desenha (round já acabou nesse tempo)
      if (tick > meta.tickEnd + tr * 0.5) return;

      var players = replaySamplePlayers(rn, tick);
      if (!players.length) return;
      var drew = false;

      selected.forEach(function (sel) {
        for (var i = 0; i < players.length; i++) {
          var pl = players[i];
          if (!pl || !nickMatch(pl.n, sel.nick)) continue;
          if (roundsSide === 'TR' && pl.t !== 'TR') continue;
          if (roundsSide === 'CT' && pl.t !== 'CT') continue;
          if (pl.x == null || pl.y == null) continue;

          var pct = worldToPercent(Number(pl.x), Number(pl.y), radar, w, h);
          if (pct.left < -8 || pct.left > 108 || pct.top < -8 || pct.top > 108) continue;

          var color = sel.color || (pl.t === 'TR' ? '#f5a623' : '#6eb6ff');
          var el = document.createElement('div');
          el.className = 'demo-replay-marker demo-rounds-marker';
          el.style.left = pct.left + '%';
          el.style.top = pct.top + '%';
          el.title = sel.nick + ' · R' + rn;

          if (pl.a === false) {
            el.innerHTML =
              '<div class="demo-replay-skull">☠</div>' +
              '<div class="demo-rounds-badge">R' + rn + '</div>';
          } else {
            var yaw = pl.yaw != null && !isNaN(Number(pl.yaw)) ? Number(pl.yaw) : null;
            if (yaw != null) {
              var rot = 90 - yaw;
              var svg = DROP_SVG.replace('VAR_FILL', color);
              el.innerHTML =
                '<div class="demo-replay-drop" style="--rot:' + rot + 'deg">' + svg + '</div>' +
                '<div class="demo-rounds-badge">R' + rn + '</div>';
            } else {
              el.innerHTML =
                '<div class="demo-replay-dot" style="background:' + color + '"></div>' +
                '<div class="demo-rounds-badge">R' + rn + '</div>';
            }
          }
          markers.appendChild(el);
          drew = true;
        }
      });
      if (drew) activeRounds++;
    });

    if (activeEl) {
      activeEl.textContent =
        'Rounds ativos: ' + activeRounds +
        ' · ' + selected.map(function (s) { return s.nick; }).join(', ');
    }
  }

  function roundsEnter() {
    roundsEnsureIndex();
    if (roundsElapsed > roundsMaxSec) roundsElapsed = 0;
    roundsRender();
  }

  var roundsPlayBtn = document.getElementById('rounds-play');
  if (roundsPlayBtn) {
    roundsPlayBtn.addEventListener('click', function () {
      if (roundsPlaying) roundsPause();
      else roundsPlay();
    });
  }

  var roundsTimeline = document.getElementById('rounds-timeline');
  if (roundsTimeline) {
    roundsTimeline.addEventListener('input', function () {
      roundsElapsed = (Number(roundsTimeline.value) / 1000) * roundsMaxSec;
      roundsRender();
    });
  }

  document.querySelectorAll('#rounds-speed button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#rounds-speed button').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      roundsSpeed = Number(btn.getAttribute('data-speed')) || 1;
    });
  });

  document.querySelectorAll('#rounds-side-filter button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#rounds-side-filter button').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      roundsSide = btn.getAttribute('data-side') || 'both';
      roundsRender();
    });
  });

  document.querySelectorAll('#rounds-player-filters input').forEach(function (el) {
    el.addEventListener('change', roundsRender);
  });
  var roundsAllBtn = document.getElementById('rounds-all');
  var roundsNoneBtn = document.getElementById('rounds-none');
  if (roundsAllBtn) {
    roundsAllBtn.addEventListener('click', function () {
      document.querySelectorAll('#rounds-player-filters input[type="checkbox"]').forEach(function (cb) {
        cb.checked = true;
      });
      roundsRender();
    });
  }
  if (roundsNoneBtn) {
    roundsNoneBtn.addEventListener('click', function () {
      document.querySelectorAll('#rounds-player-filters input[type="checkbox"]').forEach(function (cb) {
        cb.checked = false;
      });
      roundsRender();
    });
  }

  var roundsImg = document.getElementById('demo-rounds-img');
  if (roundsImg) {
    roundsImg.addEventListener('load', function () {
      if (!document.getElementById('view-rounds').hidden) roundsRender();
    });
  }

  window.addEventListener('resize', function () {
    if (!document.getElementById('view-rounds').hidden) roundsRender();
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
