<?php
/**
 * Cálculo de métricas estilo Leetify/HLTV a partir do JSON de kills/rounds.
 */

require_once __DIR__ . '/demo_zones.php';
require_once __DIR__ . '/demo_radar.php';
require_once __DIR__ . '/demo_fase_c.php';

function demo_normalize_payload(array $payload): array
{
    // Aceita tanto o formato novo {kills, rounds, ...} quanto array puro de kills (parse.js antigo)
    if (isset($payload['kills']) && is_array($payload['kills'])) {
        $kills = $payload['kills'];
        $rounds = $payload['rounds'] ?? [];
        $bombs = $payload['bomb_events'] ?? [];
        $mapa = $payload['mapa'] ?? null;
        $players = $payload['players'] ?? [];
        $frames = $payload['frames'] ?? [];
        $shots = $payload['shots'] ?? [];
        $grenades = $payload['grenades'] ?? [];
    } else {
        $kills = $payload;
        $rounds = [];
        $bombs = [];
        $mapa = null;
        $players = [];
        $frames = [];
        $shots = [];
        $grenades = [];
    }
    if (!is_array($shots)) {
        $shots = [];
    }
    if (!is_array($grenades)) {
        $grenades = [];
    }

    foreach ($kills as &$k) {
        if (isset($k['headshot']) && is_string($k['headshot'])) {
            $k['headshot'] = in_array(mb_strtolower($k['headshot']), ['sim', 'true', '1', 'yes'], true);
        }
        if (!isset($k['time_sec']) && isset($k['tick'])) {
            $k['time_sec'] = round(((float) $k['tick']) / 64, 2);
        }
        if (!isset($k['time_sec'])) {
            $k['time_sec'] = null;
        }
        if (($k['round'] ?? null) === 'Desconhecido' || $k['round'] === '' || $k['round'] === null) {
            $k['round'] = null;
        } else {
            $k['round'] = (int) $k['round'];
        }
    }
    unset($k);

    // Preenche rounds faltantes sequencialmente se ainda null
    $inferred = 1;
    foreach ($kills as &$k) {
        if ($k['round'] === null) {
            $k['round'] = $inferred;
        } else {
            $inferred = max($inferred, (int) $k['round']);
        }
    }
    unset($k);

    if (empty($players)) {
        $set = [];
        foreach ($kills as $k) {
            $set[$k['assassino']] = true;
            $set[$k['vitima']] = true;
        }
        $players = array_keys($set);
    }

    return compact('kills', 'rounds', 'bombs', 'mapa', 'players', 'frames', 'shots', 'grenades');
}

/**
 * @param string[] $nossoTime nicks do nosso time
 */
function demo_calcular_metricas(array $payload, array $nossoTime): array
{
    $data = demo_normalize_payload($payload);
    $kills = $data['kills'];
    $roundsMeta = $data['rounds'];
    $bombs = $data['bombs'];
    $nosso = array_map('strval', $nossoTime);
    // Match flexível: nick da demo ↔ nickname oficial do cadastro
    $isNosso = static function (string $nick) use ($nosso): bool {
        return demo_match_nick($nick, $nosso) !== null;
    };

    // Agrupa kills por round (ordem cronológica p/ opening / 5v4)
    $byRound = [];
    foreach ($kills as $k) {
        $r = (int) $k['round'];
        $byRound[$r][] = $k;
    }
    ksort($byRound);
    if (function_exists('demo_fase_c_sort_kills_chrono')) {
        demo_fase_c_sort_kills_chrono($byRound);
    } else {
        foreach ($byRound as &$ksSort) {
            usort($ksSort, static function ($a, $b) {
                $ta = isset($a['tick']) ? (int) $a['tick'] : null;
                $tb = isset($b['tick']) ? (int) $b['tick'] : null;
                if ($ta !== null && $tb !== null && $ta !== $tb) {
                    return $ta <=> $tb;
                }
                return ((float) ($a['time_sec'] ?? 0)) <=> ((float) ($b['time_sec'] ?? 0));
            });
        }
        unset($ksSort);
    }

    // Vencedores: wipe de kills > winner oficial > plant+heurística
    // (NÃO usar “último kill = vencedor” — CT pode fraggar e ainda perder no plant/wipe)
    $bombsByRound = [];
    foreach ($bombs as $b) {
        $br = (int) ($b['round'] ?? 0);
        if ($br > 0) {
            $bombsByRound[$br][] = $b;
        }
    }
    $winners = demo_resolver_vencedores_rounds($byRound, $roundsMeta, $bombsByRound);

    // Lado nosso: maioria das aparições (assassino/vítima), não só o 1º evento
    $nossoLado = demo_resolver_nosso_lado_rounds($byRound, $isNosso);

    $won = static function (int $r) use ($winners, $nossoLado): ?bool {
        if (!isset($winners[$r], $nossoLado[$r])) return null;
        if ($winners[$r] === 'UNK') return null;
        return $winners[$r] === $nossoLado[$r];
    };

    // ---- 5v4 / 4v5 ----
    $adv5v4 = ['total' => 0, 'wins' => 0];
    $des4v5 = ['total' => 0, 'wins' => 0];

    foreach ($byRound as $r => $ks) {
        if (!isset($nossoLado[$r])) continue;
        $aliveCT = 5;
        $aliveTR = 5;
        $firstAdv = null; // '5v4' | '4v5' | null
        foreach ($ks as $k) {
            if (($k['time_vitima'] ?? '') === 'CT') $aliveCT = max(0, $aliveCT - 1);
            if (($k['time_vitima'] ?? '') === 'TR') $aliveTR = max(0, $aliveTR - 1);

            $nossoAlive = $nossoLado[$r] === 'CT' ? $aliveCT : $aliveTR;
            $advAlive = $nossoLado[$r] === 'CT' ? $aliveTR : $aliveCT;

            if ($firstAdv === null) {
                if ($nossoAlive === 5 && $advAlive === 4) $firstAdv = '5v4';
                elseif ($nossoAlive === 4 && $advAlive === 5) $firstAdv = '4v5';
            }
        }
        $w = $won($r);
        if ($firstAdv === '5v4') {
            $adv5v4['total']++;
            if ($w === true) $adv5v4['wins']++;
        } elseif ($firstAdv === '4v5') {
            $des4v5['total']++;
            if ($w === true) $des4v5['wins']++;
        }
    }

    // ---- Pistol rounds (1 e 13) ----
    $pistol = [
        'CT' => ['total' => 0, 'wins' => 0],
        'TR' => ['total' => 0, 'wins' => 0],
    ];
    foreach ([1, 13] as $pr) {
        if (!isset($byRound[$pr]) || !isset($nossoLado[$pr])) continue;
        $lado = $nossoLado[$pr];
        if (!isset($pistol[$lado])) continue;
        $pistol[$lado]['total']++;
        if ($won($pr) === true) $pistol[$lado]['wins']++;
    }

    // ---- Opening kills (1ª kill por tick; nossos separado por lado) ----
    $opening = [
        'total' => 0,
        'nossos' => 0,
        'por_jogador' => [],
        'por_lado' => ['CT' => 0, 'TR' => 0], // lado do assassino da 1ª kill (qualquer time)
        'nossos_por_lado' => ['CT' => ['rounds' => 0, 'nossos' => 0], 'TR' => ['rounds' => 0, 'nossos' => 0]],
    ];
    foreach ($byRound as $r => $ks) {
        if (empty($ks) || !isset($nossoLado[$r])) {
            continue;
        }
        $first = $ks[0];
        $lado = $nossoLado[$r];
        $opening['total']++;
        $sideKiller = $first['time_assassino'] ?? '';
        if ($sideKiller === 'CT' || $sideKiller === 'TR') {
            $opening['por_lado'][$sideKiller] = ($opening['por_lado'][$sideKiller] ?? 0) + 1;
        }
        if ($lado === 'CT' || $lado === 'TR') {
            $opening['nossos_por_lado'][$lado]['rounds']++;
        }
        if ($isNosso($first['assassino'])) {
            $opening['nossos']++;
            $pj = $first['assassino'];
            $opening['por_jogador'][$pj] = ($opening['por_jogador'][$pj] ?? 0) + 1;
            if ($lado === 'CT' || $lado === 'TR') {
                $opening['nossos_por_lado'][$lado]['nossos']++;
            }
        }
    }

    // ---- Traded deaths (≤ 5s por tick; fallback time_sec / índice) ----
    $trades = ['mortes_nossas' => 0, 'trocadas' => 0];
    $tradeWindowSec = 5.0;
    foreach ($byRound as $r => $ks) {
        for ($i = 0; $i < count($ks); $i++) {
            $k = $ks[$i];
            if (!$isNosso($k['vitima'])) {
                continue;
            }
            $trades['mortes_nossas']++;
            $killer = (string) ($k['assassino'] ?? '');
            $tick0 = isset($k['tick']) ? (int) $k['tick'] : null;
            $t0 = $k['time_sec'] ?? null;
            for ($j = $i + 1; $j < count($ks); $j++) {
                $k2 = $ks[$j];
                if (strcasecmp((string) ($k2['vitima'] ?? ''), $killer) !== 0) {
                    continue;
                }
                if (!$isNosso($k2['assassino'] ?? '')) {
                    continue;
                }
                $okTrade = false;
                if ($tick0 !== null && isset($k2['tick'])) {
                    $dt = ((int) $k2['tick'] - $tick0) / 64.0;
                    $okTrade = $dt >= 0 && $dt <= $tradeWindowSec;
                } elseif ($t0 !== null && $k2['time_sec'] !== null) {
                    $okTrade = ((float) $k2['time_sec'] - (float) $t0) <= $tradeWindowSec;
                } else {
                    $okTrade = $j <= $i + 2;
                }
                if ($okTrade) {
                    $trades['trocadas']++;
                    break;
                }
            }
        }
    }

    // ---- Economia por round (heurística por armas) ----
    $econ = [
        'Eco' => ['total' => 0, 'wins' => 0],
        'Forçado' => ['total' => 0, 'wins' => 0],
        'Armado' => ['total' => 0, 'wins' => 0],
        'Pistol' => ['total' => 0, 'wins' => 0],
    ];
    foreach ($byRound as $r => $ks) {
        if (!isset($nossoLado[$r])) continue;
        $tipo = demo_classificar_economia($ks, $nossoLado[$r], $isNosso, $r);
        $econ[$tipo]['total']++;
        if ($won($r) === true) $econ[$tipo]['wins']++;
    }

    // ---- Bomb preference TR (via bomb_planted ou heurística) ----
    $bombPref = ['A' => 0, 'B' => 0, 'desconhecido' => 0];
    foreach ($bombs as $b) {
        $r = (int) ($b['round'] ?? 0);
        if (!isset($nossoLado[$r]) || $nossoLado[$r] !== 'TR') continue;
        $site = strtoupper((string) ($b['site'] ?? ''));
        // site pode vir como "A"/"B" ou id numérico do parser
        if (strpos($site, 'A') !== false || $site === '0') $bombPref['A']++;
        elseif (strpos($site, 'B') !== false || $site === '1') $bombPref['B']++;
        else $bombPref['desconhecido']++;
    }

    // Stats por jogador nosso
    $playerStats = [];
    foreach ($nosso as $nick) {
        $playerStats[$nick] = ['kills' => 0, 'deaths' => 0, 'opening' => 0, 'hs' => 0];
    }
    foreach ($kills as $k) {
        if ($isNosso($k['assassino'])) {
            $n = demo_match_nick($k['assassino'], $nosso);
            if ($n) {
                $playerStats[$n]['kills']++;
                if (!empty($k['headshot'])) $playerStats[$n]['hs']++;
            }
        }
        if ($isNosso($k['vitima'])) {
            $n = demo_match_nick($k['vitima'], $nosso);
            if ($n) $playerStats[$n]['deaths']++;
        }
    }
    foreach ($opening['por_jogador'] as $nick => $qtd) {
        $n = demo_match_nick($nick, $nosso);
        if ($n) $playerStats[$n]['opening'] = $qtd;
    }

    $pct = static function (int $wins, int $total): float {
        return $total > 0 ? round(($wins / $total) * 100, 1) : 0.0;
    };

    $roundsWon = 0;
    $roundsTotal = 0;
    foreach ($byRound as $r => $_) {
        if (!isset($nossoLado[$r])) continue;
        $roundsTotal++;
        if ($won($r) === true) $roundsWon++;
    }

    $out = [
        'mapa' => $data['mapa'],
        'resumo' => [
            'rounds_total' => $roundsTotal,
            'rounds_won' => $roundsWon,
            'win_rate_geral' => $pct($roundsWon, $roundsTotal),
            'total_kills' => count($kills),
        ],
        'vantagem_5v4' => [
            'situacoes' => $adv5v4['total'],
            'vitorias' => $adv5v4['wins'],
            'win_rate' => $pct($adv5v4['wins'], $adv5v4['total']),
        ],
        'desvantagem_4v5' => [
            'situacoes' => $des4v5['total'],
            'vitorias' => $des4v5['wins'],
            'win_rate' => $pct($des4v5['wins'], $des4v5['total']),
        ],
        'pistol' => [
            'CT' => [
                'jogados' => $pistol['CT']['total'],
                'vitorias' => $pistol['CT']['wins'],
                'win_rate' => $pct($pistol['CT']['wins'], $pistol['CT']['total']),
            ],
            'TR' => [
                'jogados' => $pistol['TR']['total'],
                'vitorias' => $pistol['TR']['wins'],
                'win_rate' => $pct($pistol['TR']['wins'], $pistol['TR']['total']),
            ],
        ],
        'opening_kill' => [
            'total_rounds' => $opening['total'],
            'nossos' => $opening['nossos'],
            'rate' => $pct($opening['nossos'], $opening['total']),
            'por_jogador' => $opening['por_jogador'],
            'por_lado' => $opening['por_lado'],
            'nossos_ct' => $opening['nossos_por_lado']['CT']['nossos'],
            'rounds_ct' => $opening['nossos_por_lado']['CT']['rounds'],
            'rate_ct' => $pct(
                $opening['nossos_por_lado']['CT']['nossos'],
                $opening['nossos_por_lado']['CT']['rounds']
            ),
            'nossos_tr' => $opening['nossos_por_lado']['TR']['nossos'],
            'rounds_tr' => $opening['nossos_por_lado']['TR']['rounds'],
            'rate_tr' => $pct(
                $opening['nossos_por_lado']['TR']['nossos'],
                $opening['nossos_por_lado']['TR']['rounds']
            ),
        ],
        'traded_deaths' => [
            'mortes_nossas' => $trades['mortes_nossas'],
            'trocadas' => $trades['trocadas'],
            'rate' => $pct($trades['trocadas'], $trades['mortes_nossas']),
        ],
        'economia' => array_map(static function ($v) use ($pct) {
            return [
                'rounds' => $v['total'],
                'vitorias' => $v['wins'],
                'win_rate' => $pct($v['wins'], $v['total']),
            ];
        }, $econ),
        'bomb_preference_tr' => $bombPref,
        'adversarios_destaque' => demo_adversarios_que_puniram($kills, $isNosso),
        'espacial' => [
            'tem_posicoes_kill' => demo_tem_posicoes_kill($kills),
            'total_frames' => count($data['frames'] ?? []),
            'fase' => !empty($data['frames']) ? 1 : 0,
        ],
        'jogadores' => $playerStats,
        'elenco_foco' => $nosso,
        'players_detectados' => $data['players'],
    ];

    // Território / zonas (Fase B) — Dust2 primeiro
    $mapaNome = $data['mapa'] ?? ($payload['mapa'] ?? null);
    $radarCfg = function_exists('demo_radar_resolver_mapa')
        ? demo_radar_resolver_mapa(is_string($mapaNome) ? $mapaNome : null)
        : null;
    $tickrateEst = 64.0;
    if (!empty($payload['tickrate'])) {
        $tickrateEst = (float) $payload['tickrate'];
    }
    $mapaKey = is_string($mapaNome) ? $mapaNome : ($radarCfg['map_key'] ?? null);
    if ($radarCfg) {
        $out['territorio'] = demo_analisar_territorio(
            $data['frames'] ?? [],
            $isNosso,
            $mapaKey,
            $radarCfg,
            $roundsMeta,
            $tickrateEst
        );
    } else {
        $out['territorio'] = [
            'disponivel' => false,
            'motivo' => 'mapa_sem_radar',
            'mapa' => $mapaNome,
        ];
    }

    // Fase C — util / abertura / 5v4–4v5 comportamento
    $out['fase_c'] = demo_analisar_fase_c(
        $kills,
        $roundsMeta,
        $data['frames'] ?? [],
        $data['grenades'] ?? [],
        $bombs,
        $isNosso,
        $mapaKey,
        $radarCfg,
        $tickrateEst
    );

    return $out;
}

/**
 * Top adversários que mais mataram o nosso elenco (contexto pontual p/ IA).
 * @return array<int, array{nick: string, kills_em_nos: int, vitimas: array<string,int>}>
 */
function demo_adversarios_que_puniram(array $kills, callable $isNosso): array
{
    $agg = [];
    foreach ($kills as $k) {
        if ($isNosso($k['assassino']) || !$isNosso($k['vitima'])) {
            continue;
        }
        $adv = (string) $k['assassino'];
        if (!isset($agg[$adv])) {
            $agg[$adv] = ['nick' => $adv, 'kills_em_nos' => 0, 'vitimas' => [], 'armas' => []];
        }
        $agg[$adv]['kills_em_nos']++;
        $vit = (string) $k['vitima'];
        $agg[$adv]['vitimas'][$vit] = ($agg[$adv]['vitimas'][$vit] ?? 0) + 1;
        $arma = (string) ($k['arma'] ?? 'unknown');
        $agg[$adv]['armas'][$arma] = ($agg[$adv]['armas'][$arma] ?? 0) + 1;
    }
    usort($agg, static function ($a, $b) {
        return $b['kills_em_nos'] <=> $a['kills_em_nos'];
    });
    return array_slice(array_values($agg), 0, 5);
}

/**
 * "Fundo" por tempo (fase 1 — sem zonas de mapa ainda):
 * - kill após FUNDO_SEC segundos do round_start
 * - ou kill após bomb plant no mesmo round
 */
function demo_calcular_fundo(array $kills, array $roundsMeta, array $bombs, callable $isNosso, array $nosso): array
{
    $fundoSec = 35.0; // meta típica: mid/late round

    $roundStartTick = [];
    $tickrateGuess = 64;
    foreach ($roundsMeta as $rm) {
        $rn = (int) ($rm['round'] ?? 0);
        if ($rn < 1) continue;
        if (isset($rm['tick_start'])) {
            $roundStartTick[$rn] = (int) $rm['tick_start'];
        }
        if (!empty($rm['duration_sec']) && !empty($rm['tick_end']) && !empty($rm['tick_start'])) {
            $dur = (float) $rm['duration_sec'];
            if ($dur > 0) {
                $tickrateGuess = max(1, (int) round(((int) $rm['tick_end'] - (int) $rm['tick_start']) / $dur));
            }
        }
    }

    $plantTickByRound = [];
    foreach ($bombs as $b) {
        $rn = (int) ($b['round'] ?? 0);
        if ($rn > 0 && isset($b['tick'])) {
            $plantTickByRound[$rn] = (int) $b['tick'];
        }
    }

    $out = [
        'definicao' => "Kills após {$fundoSec}s do round OU após plant",
        'fundo_sec' => $fundoSec,
        'kills_fundo' => 0,
        'kills_fundo_nossas' => 0,
        'mortes_fundo_nossas' => 0,
        'rounds_com_fundo' => 0,
        'pos_plant' => [
            'kills' => 0,
            'kills_nossas' => 0,
            'mortes_nossas' => 0,
        ],
        'por_jogador' => [],
    ];
    foreach ($nosso as $n) {
        $out['por_jogador'][$n] = ['kills_fundo' => 0, 'mortes_fundo' => 0, 'kills_pos_plant' => 0];
    }

    $roundsComFundo = [];

    foreach ($kills as $k) {
        $rn = (int) ($k['round'] ?? 0);
        if ($rn < 1) continue;

        $isFundo = false;
        $isPosPlant = false;

        // Preferir round_time_sec do parser
        if (isset($k['round_time_sec']) && $k['round_time_sec'] !== null) {
            if ((float) $k['round_time_sec'] >= $fundoSec) $isFundo = true;
        } elseif (isset($roundStartTick[$rn], $k['tick'])) {
            $elapsed = ((int) $k['tick'] - $roundStartTick[$rn]) / $tickrateGuess;
            if ($elapsed >= $fundoSec) $isFundo = true;
        }

        if (isset($plantTickByRound[$rn], $k['tick']) && (int) $k['tick'] >= $plantTickByRound[$rn]) {
            $isPosPlant = true;
            $isFundo = true;
        }

        if (!$isFundo) continue;

        $out['kills_fundo']++;
        $roundsComFundo[$rn] = true;

        if ($isNosso($k['assassino'])) {
            $out['kills_fundo_nossas']++;
            $n = demo_match_nick($k['assassino'], $nosso);
            if ($n) $out['por_jogador'][$n]['kills_fundo']++;
        }
        if ($isNosso($k['vitima'])) {
            $out['mortes_fundo_nossas']++;
            $n = demo_match_nick($k['vitima'], $nosso);
            if ($n) $out['por_jogador'][$n]['mortes_fundo']++;
        }

        if ($isPosPlant) {
            $out['pos_plant']['kills']++;
            if ($isNosso($k['assassino'])) {
                $out['pos_plant']['kills_nossas']++;
                $n = demo_match_nick($k['assassino'], $nosso);
                if ($n) $out['por_jogador'][$n]['kills_pos_plant']++;
            }
            if ($isNosso($k['vitima'])) {
                $out['pos_plant']['mortes_nossas']++;
            }
        }
    }

    $out['rounds_com_fundo'] = count($roundsComFundo);
    return $out;
}

function demo_tem_posicoes_kill(array $kills): bool
{
    foreach ($kills as $k) {
        if (!empty($k['pos_vitima']['x']) || !empty($k['pos_assassino']['x'])) {
            return true;
        }
    }
    return false;
}

/** Normaliza nick p/ comparação (ignora tag de clã, pontuação, case). */
function demo_normalizar_nick(string $nick): string
{
    $n = trim($nick);
    // Remove prefixos tipo "[MT]" ou "MT |"
    $n = preg_replace('/^\[[^\]]*\]\s*/u', '', $n) ?? $n;
    if (strpos($n, '|') !== false) {
        $parts = array_map('trim', explode('|', $n));
        $n = (string) end($parts);
    }
    $n = mb_strtolower($n, 'UTF-8');
    $n = preg_replace('/[^a-z0-9]/u', '', $n) ?? $n;
    return $n;
}

/**
 * Cruza nick da demo com lista oficial (cadastro).
 * Retorna o nickname CANÔNICO do cadastro, ou null.
 */
function demo_match_nick(string $nickDemo, array $listaOficial): ?string
{
    foreach ($listaOficial as $oficial) {
        if (strcasecmp((string) $oficial, $nickDemo) === 0) {
            return (string) $oficial;
        }
    }

    $normDemo = demo_normalizar_nick($nickDemo);
    if ($normDemo === '') {
        return null;
    }

    $melhor = null;
    $melhorLen = 0;
    foreach ($listaOficial as $oficial) {
        $oficial = (string) $oficial;
        $normOf = demo_normalizar_nick($oficial);
        if ($normOf === '') {
            continue;
        }
        if ($normDemo === $normOf) {
            return $oficial;
        }
        // Contém (ex.: "vargao123" ↔ "vargao") se o pedaço tiver ≥ 3 chars
        if (strlen($normDemo) >= 3 && strlen($normOf) >= 3) {
            if (strpos($normDemo, $normOf) !== false || strpos($normOf, $normDemo) !== false) {
                $len = min(strlen($normDemo), strlen($normOf));
                if ($len > $melhorLen) {
                    $melhor = $oficial;
                    $melhorLen = $len;
                }
            }
        }
    }
    return $melhor;
}

/**
 * Separa os dois times da partida pelos lados do 1º half (rounds 1–12).
 * @return array{time_a: string[], time_b: string[]}
 */
function demo_extrair_lineups(array $payload): array
{
    $data = demo_normalize_payload($payload);
    $ct = [];
    $tr = [];

    foreach ($data['kills'] as $k) {
        $r = (int) ($k['round'] ?? 0);
        if ($r < 1 || $r > 12) continue;

        if (($k['time_assassino'] ?? '') === 'CT') $ct[$k['assassino']] = true;
        if (($k['time_assassino'] ?? '') === 'TR') $tr[$k['assassino']] = true;
        if (($k['time_vitima'] ?? '') === 'CT') $ct[$k['vitima']] = true;
        if (($k['time_vitima'] ?? '') === 'TR') $tr[$k['vitima']] = true;
    }

    // Fallback: usa todos os rounds se o 1º half veio vazio
    if (count($ct) < 2 || count($tr) < 2) {
        $ct = [];
        $tr = [];
        foreach ($data['kills'] as $k) {
            if (($k['time_assassino'] ?? '') === 'CT') $ct[$k['assassino']] = true;
            if (($k['time_assassino'] ?? '') === 'TR') $tr[$k['assassino']] = true;
            if (($k['time_vitima'] ?? '') === 'CT') $ct[$k['vitima']] = true;
            if (($k['time_vitima'] ?? '') === 'TR') $tr[$k['vitima']] = true;
        }
    }

    return [
        'time_a' => array_values(array_keys($ct)),
        'time_b' => array_values(array_keys($tr)),
    ];
}

/**
 * Foco da análise: cruza nicks da demo com uma lista oficial.
 * Se $listaFoco for informada (nomes manuais), usa ela.
 * Senão, usa jogadores com analisar_na_demo = 1 (não confundir com ativo/site).
 *
 * @param string[]|null $listaFoco Nicks manuais (um por linha no form), ou null = cadastro
 * @return array{
 *   jogadores: string[],
 *   origem: string,
 *   lineups: array,
 *   mapa_nicks: array<string,string>,
 *   elenco_ativo: string[],
 *   nicks_demo_sem_match: string[]
 * }
 */
function demo_detectar_nosso_time(array $payload, ?array $listaFoco = null): array
{
    if (function_exists('demo_garantir_schema')) {
        demo_garantir_schema();
    }
    $lineups = demo_extrair_lineups($payload);
    $todosDemo = array_values(array_unique(array_merge($lineups['time_a'], $lineups['time_b'])));

    $manual = false;
    $listaOficial = [];

    if (is_array($listaFoco) && !empty($listaFoco)) {
        $manual = true;
        $seen = [];
        foreach ($listaFoco as $n) {
            $n = trim((string) $n);
            if ($n === '') {
                continue;
            }
            $key = mb_strtolower($n, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $listaOficial[] = $n;
        }
    } else {
        try {
            $listaOficial = db()->query(
                "SELECT nickname FROM jogadores
                 WHERE analisar_na_demo = 1 AND nickname IS NOT NULL AND nickname != ''
                 ORDER BY ordem ASC, nickname ASC"
            )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            // coluna antiga: fallback para ativo
            try {
                $listaOficial = db()->query(
                    "SELECT nickname FROM jogadores WHERE ativo = 1 AND nickname IS NOT NULL AND nickname != '' ORDER BY ordem ASC, nickname ASC"
                )->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } catch (Throwable $e2) {
                $listaOficial = [];
            }
        }
    }

    $mapaNicks = []; // nick_na_demo => nickname_oficial
    $oficiais = [];
    $semMatch = [];

    foreach ($todosDemo as $nickDemo) {
        $oficial = demo_match_nick((string) $nickDemo, $listaOficial);
        if ($oficial) {
            $mapaNicks[(string) $nickDemo] = $oficial;
            $oficiais[$oficial] = true;
        } else {
            $semMatch[] = (string) $nickDemo;
        }
    }

    $jogadores = array_keys($oficiais);

    // Ordem da lista oficial (cadastro ou manual)
    usort($jogadores, static function ($a, $b) use ($listaOficial) {
        $ia = array_search($a, $listaOficial, true);
        $ib = array_search($b, $listaOficial, true);
        $ia = $ia === false ? 999 : $ia;
        $ib = $ib === false ? 999 : $ib;
        return $ia <=> $ib;
    });

    $origem = empty($jogadores)
        ? ($manual ? 'sem_match_manual' : 'sem_match_cadastro')
        : ($manual ? 'manual' : 'cadastro_analisar_demo');

    return [
        'jogadores' => $jogadores,
        'origem' => $origem,
        'lineups' => $lineups,
        'mapa_nicks' => $mapaNicks,
        'elenco_ativo' => array_values($listaOficial),
        'nicks_demo_sem_match' => $semMatch,
    ];
}

/**
 * Infere nome do adversário: clans no parse (excluindo nosso time) ou título "A vs B".
 *
 * @param string[] $nossosNicks
 */
function demo_inferir_time_adversario(array $payload, array $nossosNicks, string $timeNosso = '', string $titulo = ''): ?string
{
    $isNosso = static function (string $nick) use ($nossosNicks): bool {
        foreach ($nossosNicks as $n) {
            if (demo_match_nick($nick, [$n])) {
                return true;
            }
        }
        return false;
    };

    // 1) Clans dos adversários na demo
    $clans = $payload['player_clans'] ?? [];
    if (is_array($clans) && !empty($clans)) {
        $counts = [];
        foreach ($clans as $nick => $info) {
            $nick = (string) $nick;
            if ($isNosso($nick)) {
                continue;
            }
            $clan = '';
            if (is_array($info)) {
                $clan = trim((string) ($info['clan'] ?? $info['c'] ?? ''));
            } else {
                $clan = trim((string) $info);
            }
            if ($clan === '' || mb_strlen($clan) < 2) {
                continue;
            }
            // ignora se for o nosso time
            if ($timeNosso !== '' && mb_stripos($clan, $timeNosso) !== false) {
                continue;
            }
            $key = mb_strtolower($clan, 'UTF-8');
            if (!isset($counts[$key])) {
                $counts[$key] = ['name' => $clan, 'n' => 0];
            }
            $counts[$key]['n']++;
        }
        if (!empty($counts)) {
            uasort($counts, static function ($a, $b) {
                return $b['n'] <=> $a['n'];
            });
            $top = reset($counts);
            if ($top && $top['n'] >= 2) {
                return $top['name'];
            }
            if ($top && $top['n'] >= 1 && count($counts) === 1) {
                return $top['name'];
            }
        }
    }

    // 2) Título: "MouseTrap vs Oddik", "A x B", "A X B"
    $titulo = trim($titulo);
    if ($titulo !== '') {
        if (preg_match('/\s+(?:vs\.?|x)\s+(.+)$/iu', $titulo, $m)) {
            $adv = trim($m[1]);
            // remove mapa no fim: "Oddik — Mirage" / "Oddik Dust2"
            $adv = preg_replace('/\s*[—\-|:]\s*.+$/u', '', $adv) ?? $adv;
            $adv = trim((string) $adv);
            if ($adv !== '' && mb_strlen($adv) <= 80) {
                return $adv;
            }
        }
    }

    return null;
}

/**
 * Recalcula placar / pistol / opening / 5v4 em cima de kills+rounds (ao vivo no ver.php).
 * Corrige demos antigas sem reupar.
 *
 * @param array $metricas métricas salvas (merge)
 * @param string[] $nossoTime
 * @return array
 */
function demo_recalcular_placar_metricas(array $metricas, array $kills, array $roundsMeta, array $nossoTime, array $bombs = []): array
{
    if (empty($kills) || empty($nossoTime)) {
        return $metricas;
    }
    $isNosso = static function (string $nick) use ($nossoTime): bool {
        return demo_match_nick($nick, $nossoTime) !== null;
    };

    $byRound = [];
    foreach ($kills as $k) {
        $r = (int) ($k['round'] ?? 0);
        if ($r < 1) {
            continue;
        }
        $byRound[$r][] = $k;
    }
    ksort($byRound);
    if (function_exists('demo_fase_c_sort_kills_chrono')) {
        demo_fase_c_sort_kills_chrono($byRound);
    }

    $bombsByRound = [];
    foreach ($bombs as $b) {
        $br = (int) ($b['round'] ?? 0);
        if ($br > 0) {
            $bombsByRound[$br][] = $b;
        }
    }

    $winners = demo_resolver_vencedores_rounds($byRound, $roundsMeta, $bombsByRound);
    $nossoLado = demo_resolver_nosso_lado_rounds($byRound, $isNosso);

    $pct = static function (int $wins, int $total): float {
        return $total > 0 ? round(($wins / $total) * 100, 1) : 0.0;
    };

    $won = static function (int $r) use ($winners, $nossoLado): ?bool {
        if (!isset($winners[$r], $nossoLado[$r])) {
            return null;
        }
        if ($winners[$r] === 'UNK') {
            return null;
        }
        return $winners[$r] === $nossoLado[$r];
    };

    $adv5v4 = ['total' => 0, 'wins' => 0];
    $des4v5 = ['total' => 0, 'wins' => 0];
    foreach ($byRound as $r => $ks) {
        if (!isset($nossoLado[$r])) {
            continue;
        }
        $aliveCT = 5;
        $aliveTR = 5;
        $firstAdv = null;
        foreach ($ks as $k) {
            if (($k['time_vitima'] ?? '') === 'CT') {
                $aliveCT = max(0, $aliveCT - 1);
            }
            if (($k['time_vitima'] ?? '') === 'TR') {
                $aliveTR = max(0, $aliveTR - 1);
            }
            $nossoAlive = $nossoLado[$r] === 'CT' ? $aliveCT : $aliveTR;
            $advAlive = $nossoLado[$r] === 'CT' ? $aliveTR : $aliveCT;
            if ($firstAdv === null) {
                if ($nossoAlive === 5 && $advAlive === 4) {
                    $firstAdv = '5v4';
                } elseif ($nossoAlive === 4 && $advAlive === 5) {
                    $firstAdv = '4v5';
                }
            }
        }
        $w = $won($r);
        if ($firstAdv === '5v4') {
            $adv5v4['total']++;
            if ($w === true) {
                $adv5v4['wins']++;
            }
        } elseif ($firstAdv === '4v5') {
            $des4v5['total']++;
            if ($w === true) {
                $des4v5['wins']++;
            }
        }
    }

    $pistol = ['CT' => ['total' => 0, 'wins' => 0], 'TR' => ['total' => 0, 'wins' => 0]];
    foreach ([1, 13] as $pr) {
        if (!isset($byRound[$pr]) || !isset($nossoLado[$pr])) {
            continue;
        }
        $lado = $nossoLado[$pr];
        if (!isset($pistol[$lado])) {
            continue;
        }
        $pistol[$lado]['total']++;
        if ($won($pr) === true) {
            $pistol[$lado]['wins']++;
        }
    }

    $opening = [
        'total' => 0,
        'nossos' => 0,
        'por_jogador' => [],
        'por_lado' => ['CT' => 0, 'TR' => 0],
        'nossos_por_lado' => ['CT' => ['rounds' => 0, 'nossos' => 0], 'TR' => ['rounds' => 0, 'nossos' => 0]],
    ];
    foreach ($byRound as $r => $ks) {
        if (empty($ks) || !isset($nossoLado[$r])) {
            continue;
        }
        $first = $ks[0];
        $lado = $nossoLado[$r];
        $opening['total']++;
        $sideKiller = $first['time_assassino'] ?? '';
        if ($sideKiller === 'CT' || $sideKiller === 'TR') {
            $opening['por_lado'][$sideKiller] = ($opening['por_lado'][$sideKiller] ?? 0) + 1;
        }
        if ($lado === 'CT' || $lado === 'TR') {
            $opening['nossos_por_lado'][$lado]['rounds']++;
        }
        if ($isNosso($first['assassino'] ?? '')) {
            $opening['nossos']++;
            $pj = (string) $first['assassino'];
            $opening['por_jogador'][$pj] = ($opening['por_jogador'][$pj] ?? 0) + 1;
            if ($lado === 'CT' || $lado === 'TR') {
                $opening['nossos_por_lado'][$lado]['nossos']++;
            }
        }
    }

    $roundsWon = 0;
    $roundsTotal = 0;
    foreach ($byRound as $r => $_) {
        if (!isset($nossoLado[$r])) {
            continue;
        }
        $roundsTotal++;
        if ($won($r) === true) {
            $roundsWon++;
        }
    }

    $metricas['resumo'] = array_merge($metricas['resumo'] ?? [], [
        'rounds_total' => $roundsTotal,
        'rounds_won' => $roundsWon,
        'win_rate_geral' => $pct($roundsWon, $roundsTotal),
    ]);
    $metricas['vantagem_5v4'] = [
        'situacoes' => $adv5v4['total'],
        'vitorias' => $adv5v4['wins'],
        'win_rate' => $pct($adv5v4['wins'], $adv5v4['total']),
    ];
    $metricas['desvantagem_4v5'] = [
        'situacoes' => $des4v5['total'],
        'vitorias' => $des4v5['wins'],
        'win_rate' => $pct($des4v5['wins'], $des4v5['total']),
    ];
    $metricas['pistol'] = [
        'CT' => [
            'jogados' => $pistol['CT']['total'],
            'vitorias' => $pistol['CT']['wins'],
            'win_rate' => $pct($pistol['CT']['wins'], $pistol['CT']['total']),
        ],
        'TR' => [
            'jogados' => $pistol['TR']['total'],
            'vitorias' => $pistol['TR']['wins'],
            'win_rate' => $pct($pistol['TR']['wins'], $pistol['TR']['total']),
        ],
    ];
    $metricas['opening_kill'] = array_merge($metricas['opening_kill'] ?? [], [
        'total_rounds' => $opening['total'],
        'nossos' => $opening['nossos'],
        'rate' => $pct($opening['nossos'], $opening['total']),
        'por_jogador' => $opening['por_jogador'],
        'por_lado' => $opening['por_lado'],
        'nossos_ct' => $opening['nossos_por_lado']['CT']['nossos'],
        'rounds_ct' => $opening['nossos_por_lado']['CT']['rounds'],
        'rate_ct' => $pct($opening['nossos_por_lado']['CT']['nossos'], $opening['nossos_por_lado']['CT']['rounds']),
        'nossos_tr' => $opening['nossos_por_lado']['TR']['nossos'],
        'rounds_tr' => $opening['nossos_por_lado']['TR']['rounds'],
        'rate_tr' => $pct($opening['nossos_por_lado']['TR']['nossos'], $opening['nossos_por_lado']['TR']['rounds']),
    ]);
    $metricas['_vencedores'] = $winners;
    $metricas['_nosso_lado'] = $nossoLado;

    return $metricas;
}

/**
 * Normaliza winner do parser (CT/TR/2/3/T/…).
 */
function demo_normalize_winner_side($winner): ?string
{
    if ($winner === null || $winner === '') {
        return null;
    }
    if (is_int($winner) || (is_string($winner) && ctype_digit($winner))) {
        $n = (int) $winner;
        if ($n === 2) {
            return 'TR';
        }
        if ($n === 3) {
            return 'CT';
        }
        return null;
    }
    $w = strtoupper(trim((string) $winner));
    if ($w === 'CT' || $w === 'COUNTER-TERRORIST' || $w === 'COUNTERTERRORIST') {
        return 'CT';
    }
    if ($w === 'TR' || $w === 'T' || $w === 'TERRORIST') {
        return 'TR';
    }
    if ($w === 'UNK' || $w === 'UNKNOWN') {
        return null;
    }
    return null;
}

/**
 * Vencedor pelo wipe (kills ordenadas). null se inconclusivo.
 */
function demo_vencedor_por_wipe(array $ks): ?string
{
    $aliveCT = 5;
    $aliveTR = 5;
    foreach ($ks as $k) {
        $tv = $k['time_vitima'] ?? '';
        if ($tv === 'CT') {
            $aliveCT = max(0, $aliveCT - 1);
        } elseif ($tv === 'TR') {
            $aliveTR = max(0, $aliveTR - 1);
        }
    }
    if ($aliveCT <= 0 && $aliveTR > 0) {
        return 'TR';
    }
    if ($aliveTR <= 0 && $aliveCT > 0) {
        return 'CT';
    }
    return null;
}

/**
 * Resolve vencedor de um round.
 * Prioridade: wipe claro > winner oficial > plant sem CT vivo no feed > último recurso UNK.
 *
 * @param array|null $rm meta do round (winner, reason…)
 * @param array $bombsRound bomb events do round
 */
function demo_resolver_vencedor_round(array $ks, ?array $rm, array $bombsRound = []): string
{
    $wipe = demo_vencedor_por_wipe($ks);
    $official = demo_normalize_winner_side($rm['winner'] ?? ($rm['winner_num'] ?? null));

    // Wipe é evidência forte (ex.: CT fragga e depois leva 5x0 — último kill mentiria)
    if ($wipe !== null) {
        // Se oficial discorda do wipe, confia no wipe
        return $wipe;
    }

    if ($official !== null) {
        return $official;
    }

    // Plant + ainda havia TR vivo no fim do feed e CT não wipeou → provavelmente TR (explode)
    $planted = !empty($bombsRound);
    if ($planted) {
        $aliveCT = 5;
        $aliveTR = 5;
        foreach ($ks as $k) {
            $tv = $k['time_vitima'] ?? '';
            if ($tv === 'CT') {
                $aliveCT = max(0, $aliveCT - 1);
            } elseif ($tv === 'TR') {
                $aliveTR = max(0, $aliveTR - 1);
            }
        }
        if ($aliveTR > 0 && $aliveCT > 0) {
            // inconclusivo sem defuse; reason do parser
            $reason = isset($rm['reason']) ? (int) $rm['reason'] : null;
            // CS reason: 1=bomb exploded often varies — se plant e sem wipe, não chute
            return 'UNK';
        }
    }

    return 'UNK';
}

/**
 * @param array<int,array> $byRound
 * @param array $roundsMeta
 * @param array<int,array> $bombsByRound
 * @return array<int,string>
 */
function demo_resolver_vencedores_rounds(array $byRound, array $roundsMeta, array $bombsByRound = []): array
{
    $metaByRound = [];
    $metaTick = [];
    foreach ($roundsMeta as $rm) {
        if (empty($rm['round'])) {
            continue;
        }
        $rn = (int) $rm['round'];
        $tick = (int) ($rm['tick_end'] ?? $rm['tick_decision'] ?? 0);
        if (!isset($metaByRound[$rn]) || $tick >= ($metaTick[$rn] ?? 0)) {
            $metaByRound[$rn] = $rm;
            $metaTick[$rn] = $tick;
        }
    }

    $winners = [];
    $allRounds = array_unique(array_merge(array_keys($byRound), array_keys($metaByRound)));
    foreach ($allRounds as $r) {
        $r = (int) $r;
        $winners[$r] = demo_resolver_vencedor_round(
            $byRound[$r] ?? [],
            $metaByRound[$r] ?? null,
            $bombsByRound[$r] ?? []
        );
    }
    return $winners;
}

/**
 * Lado do nosso time por round (voto por eventos, não só o 1º).
 *
 * @param array<int,array> $byRound
 * @param callable(string):bool $isNosso
 * @return array<int,string>
 */
function demo_resolver_nosso_lado_rounds(array $byRound, callable $isNosso): array
{
    $nossoLado = [];
    foreach ($byRound as $r => $ks) {
        $ct = 0;
        $tr = 0;
        foreach ($ks as $k) {
            if ($isNosso($k['assassino'] ?? '')) {
                if (($k['time_assassino'] ?? '') === 'CT') {
                    $ct++;
                } elseif (($k['time_assassino'] ?? '') === 'TR') {
                    $tr++;
                }
            }
            if ($isNosso($k['vitima'] ?? '')) {
                if (($k['time_vitima'] ?? '') === 'CT') {
                    $ct++;
                } elseif (($k['time_vitima'] ?? '') === 'TR') {
                    $tr++;
                }
            }
        }
        if ($ct === 0 && $tr === 0) {
            continue;
        }
        $nossoLado[(int) $r] = $tr > $ct ? 'TR' : 'CT';
    }
    return $nossoLado;
}

function demo_classificar_economia(array $killsRound, string $nossoLado, callable $isNosso, int $roundNum): string
{
    if ($roundNum === 1 || $roundNum === 13) {
        return 'Pistol';
    }

    $armas = [];
    foreach ($killsRound as $k) {
        if ($isNosso($k['assassino']) && ($k['time_assassino'] ?? '') === $nossoLado) {
            $armas[] = mb_strtolower((string) ($k['arma'] ?? ''));
        }
    }
    // Sem kill nossa no round: não dá para inferir buy — conta como Eco leve
    if (empty($armas)) {
        return 'Eco';
    }

    $rifles = 0;
    $awp = 0;
    $smg = 0;
    $pistol = 0;
    foreach ($armas as $a) {
        if (strpos($a, 'awp') !== false) {
            $awp++;
        } elseif (preg_match('/ak47|m4a|famas|galil|sg556|aug|scar|ssg08/', $a)) {
            $rifles++;
        } elseif (preg_match('/mp9|mac10|ump|p90|bizon|mp7|mp5|mp5sd/', $a)) {
            $smg++;
        } elseif (preg_match('/glock|usp|p250|elite|tec9|cz75|deagle|revolver|fiveseven|hkp2000/', $a)) {
            $pistol++;
        } else {
            // utilitário / world / inferno: ignora no buy
        }
    }

    $n = max(1, $rifles + $awp + $smg + $pistol);
    $riflePower = $rifles + $awp;

    // Full buy: maioria rifle/AWP
    if ($riflePower >= max(2, (int) ceil($n * 0.5))) {
        return 'Armado';
    }
    // Force: tem rifle/AWP mas não é full buy (ex.: 1–2 rifles + pistolas)
    if ($riflePower >= 1) {
        return 'Forçado';
    }
    // SMG + pistola sem rifle → eco / half — trata SMG puro como Eco
    if ($smg > 0 && $pistol > 0 && $smg >= $pistol) {
        return 'Forçado';
    }
    return 'Eco';
}
