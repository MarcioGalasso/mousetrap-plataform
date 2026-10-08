<?php
/**
 * Fase C — util → zona, abertura por economia, comportamento 5v4 / 4v5.
 */

require_once __DIR__ . '/demo_zones.php';

/**
 * Ordena kills de cada round por tick / tempo (1ª kill real).
 *
 * @param array<int, array> $byRound
 */
function demo_fase_c_sort_kills_chrono(array &$byRound): void
{
    foreach ($byRound as &$ks) {
        usort($ks, static function ($a, $b) {
            $ta = isset($a['tick']) ? (int) $a['tick'] : null;
            $tb = isset($b['tick']) ? (int) $b['tick'] : null;
            if ($ta !== null && $tb !== null && $ta !== $tb) {
                return $ta <=> $tb;
            }
            $sa = $a['round_time_sec'] ?? $a['time_sec'] ?? null;
            $sb = $b['round_time_sec'] ?? $b['time_sec'] ?? null;
            if ($sa !== null && $sb !== null) {
                return ((float) $sa) <=> ((float) $sb);
            }
            return 0;
        });
    }
    unset($ks);
}

/**
 * Análise completa Fase C.
 *
 * @param callable(string):bool $isNosso
 * @return array
 */
function demo_analisar_fase_c(
    array $kills,
    array $roundsMeta,
    array $frames,
    array $grenades,
    array $bombs,
    callable $isNosso,
    ?string $mapa,
    ?array $radarCfg,
    float $tickrate = 64.0
): array {
    $byRound = [];
    foreach ($kills as $k) {
        $r = (int) ($k['round'] ?? 0);
        if ($r < 1) {
            continue;
        }
        $byRound[$r][] = $k;
    }
    ksort($byRound);
    // Opening / 5v4 exigem ordem cronológica (tick), não ordem do JSON
    demo_fase_c_sort_kills_chrono($byRound);

    $nossoLado = function_exists('demo_resolver_nosso_lado_rounds')
        ? demo_resolver_nosso_lado_rounds($byRound, $isNosso)
        : demo_fase_c_nosso_lado($byRound, $isNosso);
    $winners = demo_fase_c_winners($roundsMeta, $byRound, $bombs);
    $won = static function (int $r) use ($winners, $nossoLado): ?bool {
        if (!isset($winners[$r], $nossoLado[$r]) || $winners[$r] === 'UNK') {
            return null;
        }
        return $winners[$r] === $nossoLado[$r];
    };

    $econByRound = [];
    foreach ($byRound as $r => $ks) {
        if (!isset($nossoLado[$r])) {
            continue;
        }
        $econByRound[$r] = demo_classificar_economia($ks, $nossoLado[$r], $isNosso, $r);
    }

    $manAdv = demo_fase_c_man_advantage(
        $byRound,
        $nossoLado,
        $won,
        $frames,
        $bombs,
        $isNosso,
        $mapa,
        $radarCfg,
        $tickrate
    );

    $abertura = demo_fase_c_abertura(
        $byRound,
        $nossoLado,
        $econByRound,
        $frames,
        $isNosso,
        $mapa,
        $radarCfg,
        $tickrate
    );

    $util = demo_fase_c_util(
        $grenades,
        $isNosso,
        $mapa,
        $radarCfg
    );

    return [
        'disponivel' => true,
        'man_advantage' => $manAdv,
        'abertura' => $abertura,
        'util' => $util,
    ];
}

/**
 * @return array<int,string> round => CT|TR
 */
function demo_fase_c_nosso_lado(array $byRound, callable $isNosso): array
{
    $nossoLado = [];
    foreach ($byRound as $r => $ks) {
        foreach ($ks as $k) {
            if ($isNosso($k['assassino'] ?? '')) {
                $nossoLado[$r] = $k['time_assassino'] ?? null;
                break;
            }
            if ($isNosso($k['vitima'] ?? '')) {
                $nossoLado[$r] = $k['time_vitima'] ?? null;
                break;
            }
        }
        if (isset($nossoLado[$r]) && !in_array($nossoLado[$r], ['CT', 'TR'], true)) {
            unset($nossoLado[$r]);
        }
    }
    return $nossoLado;
}

/**
 * @return array<int,string>
 */
function demo_fase_c_winners(array $roundsMeta, array $byRound, array $bombs = []): array
{
    $bombsByRound = [];
    foreach ($bombs as $b) {
        $br = (int) ($b['round'] ?? 0);
        if ($br > 0) {
            $bombsByRound[$br][] = $b;
        }
    }
    if (function_exists('demo_resolver_vencedores_rounds')) {
        return demo_resolver_vencedores_rounds($byRound, $roundsMeta, $bombsByRound);
    }
    // fallback legado
    $winners = [];
    foreach ($roundsMeta as $rm) {
        $rn = (int) ($rm['round'] ?? 0);
        $w = function_exists('demo_normalize_winner_side')
            ? demo_normalize_winner_side($rm['winner'] ?? null)
            : (($rm['winner'] ?? null) === 'CT' || ($rm['winner'] ?? null) === 'TR' ? $rm['winner'] : null);
        if ($rn > 0 && $w) {
            $winners[$rn] = $w;
        }
    }
    return $winners;
}

/**
 * Comportamento após 5v4 (vantagem) e 4v5 (desvantagem).
 */
function demo_fase_c_man_advantage(
    array $byRound,
    array $nossoLado,
    callable $won,
    array $frames,
    array $bombs,
    callable $isNosso,
    ?string $mapa,
    ?array $radarCfg,
    float $tickrate
): array {
    $windowSec = 10.0;
    $windowTicks = (int) round($windowSec * max(1.0, $tickrate));

    $boxes = null;
    $zonesRadar = demo_zones_for_map($mapa);
    if ($zonesRadar && $radarCfg) {
        $boxes = demo_zones_world_boxes($zonesRadar, $radarCfg);
    }

    $framesByRound = [];
    foreach ($frames as $f) {
        $r = (int) ($f['r'] ?? $f['round'] ?? 0);
        if ($r < 1) {
            continue;
        }
        $framesByRound[$r][] = $f;
    }
    foreach ($framesByRound as &$flistMa) {
        usort($flistMa, static function ($a, $b) {
            return ((int) ($a['t'] ?? 0)) <=> ((int) ($b['t'] ?? 0));
        });
    }
    unset($flistMa);

    $plantTicks = []; // round => first plant tick (bomb_events = planted)
    foreach ($bombs as $b) {
        $r = (int) ($b['round'] ?? 0);
        $t = isset($b['tick']) ? (int) $b['tick'] : null;
        if ($r < 1 || $t === null) {
            continue;
        }
        if (!isset($plantTicks[$r]) || $t < $plantTicks[$r]) {
            $plantTicks[$r] = $t;
        }
    }

    $agg = [
        '5v4' => [
            'situacoes' => 0,
            'wins' => 0,
            'comportamentos' => ['acelerou' => 0, 'congelou' => 0, 'plantou' => 0, 'overpeek' => 0],
            'por_lado' => ['CT' => ['n' => 0, 'wins' => 0], 'TR' => ['n' => 0, 'wins' => 0]],
            'amostras' => [],
        ],
        '4v5' => [
            'situacoes' => 0,
            'wins' => 0,
            'comportamentos' => ['regroupou' => 0, 'forcou' => 0, 'salvou' => 0, 'info_play' => 0],
            'por_lado' => ['CT' => ['n' => 0, 'wins' => 0], 'TR' => ['n' => 0, 'wins' => 0]],
            'amostras' => [],
        ],
    ];

    $siteIds = ['a_site' => true, 'b_site' => true];

    foreach ($byRound as $r => $ks) {
        if (!isset($nossoLado[$r])) {
            continue;
        }
        $lado = $nossoLado[$r];
        $aliveCT = 5;
        $aliveTR = 5;
        $first = null; // type, tick, idx
        foreach ($ks as $idx => $k) {
            if (($k['time_vitima'] ?? '') === 'CT') {
                $aliveCT = max(0, $aliveCT - 1);
            }
            if (($k['time_vitima'] ?? '') === 'TR') {
                $aliveTR = max(0, $aliveTR - 1);
            }
            $nossoAlive = $lado === 'CT' ? $aliveCT : $aliveTR;
            $advAlive = $lado === 'CT' ? $aliveTR : $aliveCT;
            if ($first === null) {
                if ($nossoAlive === 5 && $advAlive === 4) {
                    $tickEv = isset($k['tick']) ? (int) $k['tick'] : 0;
                    if ($tickEv <= 0 && isset($k['round_time_sec'])) {
                        $tickEv = (int) round((float) $k['round_time_sec'] * $tickrate);
                    }
                    $first = ['tipo' => '5v4', 'tick' => $tickEv, 'idx' => $idx, 'rt' => $k['round_time_sec'] ?? null];
                } elseif ($nossoAlive === 4 && $advAlive === 5) {
                    $tickEv = isset($k['tick']) ? (int) $k['tick'] : 0;
                    if ($tickEv <= 0 && isset($k['round_time_sec'])) {
                        $tickEv = (int) round((float) $k['round_time_sec'] * $tickrate);
                    }
                    $first = ['tipo' => '4v5', 'tick' => $tickEv, 'idx' => $idx, 'rt' => $k['round_time_sec'] ?? null];
                }
            }
        }
        if ($first === null) {
            continue;
        }
        // Sem tick absoluto: ainda classifica comportamento via índice de kills
        if (($first['tick'] ?? 0) <= 0) {
            $first['tick'] = max(1, $first['idx'] + 1);
        }

        $tipo = $first['tipo'];
        $tick0 = $first['tick'];
        $w = $won($r);
        $agg[$tipo]['situacoes']++;
        if ($w === true) {
            $agg[$tipo]['wins']++;
        }
        $agg[$tipo]['por_lado'][$lado]['n']++;
        if ($w === true) {
            $agg[$tipo]['por_lado'][$lado]['wins']++;
        }

        // Mortes nossas logo após o evento (forçou / overpeek)
        $mortesApos = 0;
        $killsApos = 0;
        foreach ($ks as $j => $k2) {
            if ($j <= $first['idx']) {
                continue;
            }
            $tj = (int) ($k2['tick'] ?? 0);
            if ($tj > $tick0 + $windowTicks) {
                break;
            }
            if ($isNosso($k2['vitima'] ?? '')) {
                $mortesApos++;
            }
            if ($isNosso($k2['assassino'] ?? '')) {
                $killsApos++;
            }
        }

        $plantou = isset($plantTicks[$r]) && $plantTicks[$r] >= $tick0 && $plantTicks[$r] <= $tick0 + (int) round(20 * $tickrate);

        // Zonas no início vs janela (frames)
        $zonesStart = [];
        $zonesEnd = [];
        $entrouSite = false;
        $flist = $framesByRound[$r] ?? [];
        if ($boxes && $flist) {
            foreach ($flist as $f) {
                $t = (int) ($f['t'] ?? 0);
                if ($t < $tick0 - (int) round(0.5 * $tickrate)) {
                    continue;
                }
                if ($t > $tick0 + $windowTicks) {
                    break;
                }
                $bucket = ($t <= $tick0 + (int) round(1.5 * $tickrate)) ? 'start' : 'end';
                foreach ($f['p'] ?? [] as $pl) {
                    $nick = (string) ($pl['n'] ?? '');
                    if ($nick === '' || !$isNosso($nick) || ($pl['a'] ?? true) === false) {
                        continue;
                    }
                    if (($pl['t'] ?? '') !== $lado) {
                        continue;
                    }
                    if ($pl['x'] === null || $pl['y'] === null) {
                        continue;
                    }
                    $zid = demo_zone_at((float) $pl['x'], (float) $pl['y'], $boxes);
                    if ($zid === null) {
                        continue;
                    }
                    if ($bucket === 'start') {
                        $zonesStart[$zid] = true;
                    } else {
                        $zonesEnd[$zid] = true;
                    }
                    if (isset($siteIds[$zid])) {
                        $entrouSite = true;
                    }
                }
            }
        }

        $novasZonas = 0;
        foreach ($zonesEnd as $zid => $_) {
            if (!isset($zonesStart[$zid])) {
                $novasZonas++;
            }
        }
        $progrediu = $novasZonas > 0 || $entrouSite || $plantou;

        $comportamento = 'congelou';
        if ($tipo === '5v4') {
            if ($mortesApos >= 2 && $killsApos < $mortesApos) {
                $comportamento = 'overpeek';
            } elseif ($plantou || $entrouSite || ($lado === 'TR' && $progrediu)) {
                $comportamento = $plantou ? 'plantou' : 'acelerou';
            } elseif ($lado === 'CT' && !$progrediu && $mortesApos === 0) {
                $comportamento = 'congelou'; // hold = ok no meta CT
            } elseif ($progrediu) {
                $comportamento = 'acelerou';
            } else {
                $comportamento = 'congelou';
            }
        } else { // 4v5
            if ($mortesApos >= 1 && !$plantou && $killsApos === 0) {
                $comportamento = 'forcou';
            } elseif ($mortesApos >= 2) {
                $comportamento = 'forcou';
            } elseif ($w === false && $mortesApos === 0 && !$progrediu) {
                $comportamento = 'salvou';
            } elseif (!$progrediu && $mortesApos === 0) {
                $comportamento = 'info_play';
            } elseif ($progrediu && $mortesApos <= 1) {
                // Tentativa coordenada / regroup para site
                $comportamento = 'regroupou';
            } else {
                $comportamento = 'forcou';
            }
        }

        if (!isset($agg[$tipo]['comportamentos'][$comportamento])) {
            $agg[$tipo]['comportamentos'][$comportamento] = 0;
        }
        $agg[$tipo]['comportamentos'][$comportamento]++;

        if (count($agg[$tipo]['amostras']) < 8) {
            $agg[$tipo]['amostras'][] = [
                'round' => $r,
                'lado' => $lado,
                'comportamento' => $comportamento,
                'win' => $w,
                'round_time_sec' => $first['rt'],
                'mortes_janela' => $mortesApos,
                'plantou' => $plantou,
                'entrou_site' => $entrouSite,
            ];
        }
    }

    foreach (['5v4', '4v5'] as $t) {
        $n = $agg[$t]['situacoes'];
        $agg[$t]['win_rate'] = $n > 0 ? round(100 * $agg[$t]['wins'] / $n, 1) : null;
        $compPct = [];
        foreach ($agg[$t]['comportamentos'] as $c => $q) {
            $compPct[$c] = $n > 0 ? round(100 * $q / $n, 1) : 0.0;
        }
        $agg[$t]['comportamentos_pct'] = $compPct;
        foreach (['CT', 'TR'] as $lado) {
            $ln = $agg[$t]['por_lado'][$lado]['n'];
            $agg[$t]['por_lado'][$lado]['win_rate'] = $ln > 0
                ? round(100 * $agg[$t]['por_lado'][$lado]['wins'] / $ln, 1)
                : null;
        }
    }

    // Leitura coach curta (determinística)
    $agg['leitura'] = demo_fase_c_leitura_man_adv($agg);

    return $agg;
}

function demo_fase_c_leitura_man_adv(array $agg): array
{
    $out = [];
    $a = $agg['5v4'];
    if ($a['situacoes'] > 0) {
        $dom = demo_fase_c_comportamento_dominante($a['comportamentos']);
        $out['vantagem_5v4'] = sprintf(
            '%d situações, %.0f%% win. Comportamento dominante: %s (%.0f%%).',
            $a['situacoes'],
            $a['win_rate'] ?? 0,
            $dom,
            $a['comportamentos_pct'][$dom] ?? 0
        );
    }
    $d = $agg['4v5'];
    if ($d['situacoes'] > 0) {
        $dom = demo_fase_c_comportamento_dominante($d['comportamentos']);
        $out['desvantagem_4v5'] = sprintf(
            '%d situações, %.0f%% win. Comportamento dominante: %s (%.0f%%).',
            $d['situacoes'],
            $d['win_rate'] ?? 0,
            $dom,
            $d['comportamentos_pct'][$dom] ?? 0
        );
    }
    return $out;
}

function demo_fase_c_comportamento_dominante(array $comp): string
{
    $best = 'congelou';
    $n = -1;
    foreach ($comp as $k => $v) {
        if ($v > $n) {
            $n = $v;
            $best = $k;
        }
    }
    return $best;
}

/**
 * Abertura: first kill + 1ª zona, por tipo de economia.
 */
function demo_fase_c_abertura(
    array $byRound,
    array $nossoLado,
    array $econByRound,
    array $frames,
    callable $isNosso,
    ?string $mapa,
    ?array $radarCfg,
    float $tickrate
): array {
    $boxes = null;
    $zonesRadar = demo_zones_for_map($mapa);
    if ($zonesRadar && $radarCfg) {
        $boxes = demo_zones_world_boxes($zonesRadar, $radarCfg);
    }

    $framesByRound = [];
    foreach ($frames as $f) {
        $r = (int) ($f['r'] ?? $f['round'] ?? 0);
        if ($r < 1) {
            continue;
        }
        $framesByRound[$r][] = $f;
    }
    foreach ($framesByRound as &$flistAb) {
        usort($flistAb, static function ($a, $b) {
            return ((int) ($a['t'] ?? 0)) <=> ((int) ($b['t'] ?? 0));
        });
    }
    unset($flistAb);

    $porEcon = []; // econ => stats
    $zonaGeral = [];

    foreach ($byRound as $r => $ks) {
        if (!isset($nossoLado[$r]) || empty($ks)) {
            continue;
        }
        $lado = $nossoLado[$r];
        $econ = $econByRound[$r] ?? 'Armado';
        if (!isset($porEcon[$econ])) {
            $porEcon[$econ] = [
                'rounds' => 0,
                'opening_nosso' => 0,
                'opening_ct' => 0,
                'rounds_ct' => 0,
                'opening_tr' => 0,
                'rounds_tr' => 0,
                'zonas' => [],
            ];
        }
        $porEcon[$econ]['rounds']++;
        // 1ª kill cronológica (array já ordenado por tick)
        $first = $ks[0];
        $nossoAbriu = $isNosso((string) ($first['assassino'] ?? ''));
        if ($nossoAbriu) {
            $porEcon[$econ]['opening_nosso']++;
        }
        if ($lado === 'CT') {
            $porEcon[$econ]['rounds_ct']++;
            if ($nossoAbriu) {
                $porEcon[$econ]['opening_ct']++;
            }
        } elseif ($lado === 'TR') {
            $porEcon[$econ]['rounds_tr']++;
            if ($nossoAbriu) {
                $porEcon[$econ]['opening_tr']++;
            }
        }

        // 1ª zona TR (ataque) nos primeiros ~12s
        if ($lado !== 'TR' || !$boxes) {
            continue;
        }
        $tickStart = null;
        foreach ($framesByRound[$r] ?? [] as $f) {
            $t = (int) ($f['t'] ?? 0);
            if ($tickStart === null) {
                $tickStart = $t;
            }
            if ($t > $tickStart + (int) round(12 * $tickrate)) {
                break;
            }
            foreach ($f['p'] ?? [] as $pl) {
                $nick = (string) ($pl['n'] ?? '');
                if ($nick === '' || !$isNosso($nick) || ($pl['a'] ?? true) === false) {
                    continue;
                }
                if (($pl['t'] ?? '') !== 'TR') {
                    continue;
                }
                if ($pl['x'] === null || $pl['y'] === null) {
                    continue;
                }
                $zid = demo_zone_at((float) $pl['x'], (float) $pl['y'], $boxes);
                if ($zid === null) {
                    continue;
                }
                $label = $boxes[$zid]['label'] ?? $zid;
                $porEcon[$econ]['zonas'][$label] = ($porEcon[$econ]['zonas'][$label] ?? 0) + 1;
                $zonaGeral[$label] = ($zonaGeral[$label] ?? 0) + 1;
                break 2; // primeira zona do round
            }
        }
    }

    $outEcon = [];
    foreach ($porEcon as $econ => $st) {
        $n = max(1, $st['rounds']);
        $nCt = (int) ($st['rounds_ct'] ?? 0);
        $nTr = (int) ($st['rounds_tr'] ?? 0);
        $zonas = $st['zonas'];
        arsort($zonas);
        $top = [];
        foreach (array_slice($zonas, 0, 3, true) as $lab => $q) {
            $top[] = ['zona' => $lab, 'rounds' => $q, 'pct' => round(100 * $q / $n, 1)];
        }
        $outEcon[] = [
            'economia' => $econ,
            'rounds' => $st['rounds'],
            'opening_nosso' => $st['opening_nosso'],
            'opening_rate' => round(100 * $st['opening_nosso'] / $n, 1),
            'rounds_ct' => $nCt,
            'opening_ct' => (int) ($st['opening_ct'] ?? 0),
            'opening_ct_rate' => $nCt > 0 ? round(100 * $st['opening_ct'] / $nCt, 1) : null,
            'rounds_tr' => $nTr,
            'opening_tr' => (int) ($st['opening_tr'] ?? 0),
            'opening_tr_rate' => $nTr > 0 ? round(100 * $st['opening_tr'] / $nTr, 1) : null,
            'zonas_top' => $top,
        ];
    }
    usort($outEcon, static function ($a, $b) {
        return ($b['rounds'] <=> $a['rounds']);
    });

    arsort($zonaGeral);
    $aberturaTop = [];
    $totalZ = array_sum($zonaGeral) ?: 1;
    foreach (array_slice($zonaGeral, 0, 5, true) as $lab => $q) {
        $aberturaTop[] = ['zona' => $lab, 'rounds' => $q, 'pct' => round(100 * $q / $totalZ, 1)];
    }

    $dominante = $aberturaTop[0]['zona'] ?? null;
    $domPct = $aberturaTop[0]['pct'] ?? 0;
    $previsivel = $dominante !== null && $domPct >= 45.0;

    return [
        'por_economia' => $outEcon,
        'zonas_tr_top' => $aberturaTop,
        'previsivel' => $previsivel,
        'leitura' => $dominante
            ? sprintf(
                'Abertura TR dominante: %s (%.0f%%%s).',
                $dominante,
                $domPct,
                $previsivel ? ' — previsível' : ''
            )
            : 'Sem dados de abertura por zona (faltam frames).',
    ];
}

/**
 * Util lançada pelo nosso time → zona de pouso.
 */
function demo_fase_c_util(
    array $grenades,
    callable $isNosso,
    ?string $mapa,
    ?array $radarCfg
): array {
    $zonesRadar = demo_zones_for_map($mapa);
    if (!$zonesRadar || !$radarCfg || empty($grenades)) {
        return [
            'disponivel' => false,
            'motivo' => empty($grenades) ? 'sem_grenades' : 'sem_zonas',
            'por_tipo' => [],
            'por_zona' => [],
            'leitura' => 'Sem dados de util espacial.',
        ];
    }
    $boxes = demo_zones_world_boxes($zonesRadar, $radarCfg);

    $porTipo = ['smoke' => 0, 'flash' => 0, 'molotov' => 0, 'he' => 0, 'decoy' => 0];
    $porZona = []; // label => [smoke=>, flash=>, ...]
    $totalNossa = 0;

    foreach ($grenades as $g) {
        $nick = (string) ($g['n'] ?? '');
        if ($nick === '' || !$isNosso($nick)) {
            continue;
        }
        $type = strtolower((string) ($g['type'] ?? ''));
        if ($type === '' || !isset($porTipo[$type])) {
            continue;
        }
        $x = $g['x1'] ?? $g['x0'] ?? null;
        $y = $g['y1'] ?? $g['y0'] ?? null;
        if ($x === null || $y === null) {
            continue;
        }
        $zid = demo_zone_at((float) $x, (float) $y, $boxes);
        if ($zid === null) {
            continue;
        }
        $label = $boxes[$zid]['label'] ?? $zid;
        $porTipo[$type]++;
        $totalNossa++;
        if (!isset($porZona[$label])) {
            $porZona[$label] = ['smoke' => 0, 'flash' => 0, 'molotov' => 0, 'he' => 0, 'decoy' => 0, 'total' => 0];
        }
        $porZona[$label][$type]++;
        $porZona[$label]['total']++;
    }

    uasort($porZona, static function ($a, $b) {
        return ($b['total'] <=> $a['total']);
    });

    $top = [];
    foreach (array_slice($porZona, 0, 6, true) as $lab => $st) {
        $top[] = [
            'zona' => $lab,
            'total' => $st['total'],
            'smoke' => $st['smoke'],
            'flash' => $st['flash'],
            'molotov' => $st['molotov'],
            'he' => $st['he'],
        ];
    }

    $leitura = $totalNossa > 0
        ? sprintf('%d granadas nossas com pouso em zona. Top: %s.', $totalNossa, $top[0]['zona'] ?? '—')
        : 'Poucas granadas nossas com posição de pouso.';

    return [
        'disponivel' => $totalNossa > 0,
        'total_nossa_em_zona' => $totalNossa,
        'por_tipo' => $porTipo,
        'por_zona_top' => $top,
        'leitura' => $leitura,
    ];
}
