<?php
/**
 * Zonas táticas por mapa (Fase B).
 * Bounds em fração do radar 0–1 (nx, ny) — alinhado ao overview 1024.
 * world = pos_x + nx*base*scale ;  pos_y - ny*base*scale
 */

require_once __DIR__ . '/demo_zones_maps.php';

/**
 * @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}>
 */
function demo_zones_dust2(): array
{
    /**
     * Conjunto compacto (diagrama do time):
     * B Site, Tunnels Upper/Lower, Top Mid, CT Mid, Long, Cat/Short, A Site.
     * Sem T/CT Spawn — presença quase sempre 100%, não é métrica útil.
     */
    // Bounds calibrados pelo enquadramento vermelho (heatmap Dust2)
    return [
        'b_site' => ['label' => 'B Site', 'nx0' => 0.12, 'ny0' => 0.05, 'nx1' => 0.30, 'ny1' => 0.22],
        'tunnels_upper' => ['label' => 'Tunnels Upper', 'nx0' => 0.12, 'ny0' => 0.38, 'nx1' => 0.26, 'ny1' => 0.52],
        'tunnels_lower' => ['label' => 'Tunnels Lower', 'nx0' => 0.30, 'ny0' => 0.34, 'nx1' => 0.44, 'ny1' => 0.48],
        'top_mid' => ['label' => 'Top Mid', 'nx0' => 0.38, 'ny0' => 0.48, 'nx1' => 0.54, 'ny1' => 0.70],
        'ct_mid' => ['label' => 'CT Mid', 'nx0' => 0.42, 'ny0' => 0.14, 'nx1' => 0.58, 'ny1' => 0.28],
        'long' => ['label' => 'Long', 'nx0' => 0.68, 'ny0' => 0.44, 'nx1' => 0.85, 'ny1' => 0.58],
        'cat' => ['label' => 'Cat / Short', 'nx0' => 0.54, 'ny0' => 0.24, 'nx1' => 0.70, 'ny1' => 0.42],
        'a_site' => ['label' => 'A Site', 'nx0' => 0.70, 'ny0' => 0.06, 'nx1' => 0.92, 'ny1' => 0.26],
    ];
}

/** Cadeias de progressão (sem spawn). */
function demo_zones_progressao_dust2(): array
{
    return [
        ['from' => 'top_mid', 'to' => 'tunnels_lower', 'label' => 'Top Mid → Tunnels Lower'],
        ['from' => 'tunnels_lower', 'to' => 'tunnels_upper', 'label' => 'Tunnels Lower → Upper'],
        ['from' => 'tunnels_upper', 'to' => 'b_site', 'label' => 'Tunnels Upper → B'],
        ['from' => 'top_mid', 'to' => 'cat', 'label' => 'Top Mid → Cat/Short'],
        ['from' => 'cat', 'to' => 'a_site', 'label' => 'Cat/Short → A'],
        ['from' => 'long', 'to' => 'a_site', 'label' => 'Long → A'],
        ['from' => 'top_mid', 'to' => 'ct_mid', 'label' => 'Top Mid → CT Mid'],
    ];
}

/**
 * @return array<string, array>|null
 */
function demo_zones_for_map(?string $mapa): ?array
{
    $key = demo_zones_map_key($mapa);
    if ($key === null) {
        return null;
    }
    switch ($key) {
        case 'de_dust2':
            return demo_zones_dust2();
        case 'de_mirage':
            return demo_zones_mirage();
        case 'de_nuke':
            return demo_zones_nuke();
        case 'de_ancient':
            return demo_zones_ancient();
        case 'de_anubis':
            return demo_zones_anubis();
        case 'de_cache':
            return demo_zones_cache();
        case 'de_inferno':
            return demo_zones_inferno();
        case 'de_overpass':
            return demo_zones_overpass();
        default:
            return null;
    }
}

function demo_zones_progressao_for_map(?string $mapa): array
{
    $key = demo_zones_map_key($mapa);
    if ($key === null) {
        return [];
    }
    switch ($key) {
        case 'de_dust2':
            return demo_zones_progressao_dust2();
        case 'de_mirage':
            return demo_zones_progressao_mirage();
        case 'de_nuke':
            return demo_zones_progressao_nuke();
        case 'de_ancient':
            return demo_zones_progressao_ancient();
        case 'de_anubis':
            return demo_zones_progressao_anubis();
        case 'de_cache':
            return demo_zones_progressao_cache();
        case 'de_inferno':
            return demo_zones_progressao_inferno();
        case 'de_overpass':
            return demo_zones_progressao_overpass();
        default:
            return [];
    }
}

/** Normaliza nome do mapa → de_* conhecido. */
function demo_zones_map_key(?string $mapa): ?string
{
    if ($mapa === null || $mapa === '') {
        return null;
    }
    $key = strtolower(trim($mapa));
    $key = str_replace([' ', '-'], '', $key);
    if (strpos($key, 'de_') !== 0) {
        $key = 'de_' . $key;
    }
    $aliases = [
        'de_dust2' => 'de_dust2',
        'de_dustii' => 'de_dust2',
        'de_mirage' => 'de_mirage',
        'de_nuke' => 'de_nuke',
        'de_ancient' => 'de_ancient',
        'de_anubis' => 'de_anubis',
        'de_cache' => 'de_cache',
        'de_inferno' => 'de_inferno',
        'de_overpass' => 'de_overpass',
    ];
    if (isset($aliases[$key])) {
        return $aliases[$key];
    }
    foreach (['dust2', 'mirage', 'nuke', 'ancient', 'anubis', 'cache', 'inferno', 'overpass'] as $needle) {
        if (strpos($key, $needle) !== false) {
            return $needle === 'dust2' ? 'de_dust2' : ('de_' . $needle);
        }
    }
    return null;
}

/** Converte fração radar → world. */
function demo_zone_radar_to_world(float $nx, float $ny, array $radar): array
{
    $base = (float) ($radar['base_size'] ?? 1024);
    $scale = (float) ($radar['scale'] ?? 4.4);
    $x = (float) $radar['pos_x'] + $nx * $base * $scale;
    $y = (float) $radar['pos_y'] - $ny * $base * $scale;
    return ['x' => $x, 'y' => $y];
}

/**
 * Pré-calcula AABB world por zona.
 *
 * @return array<string, array{label:string,x0:float,y0:float,x1:float,y1:float}>
 */
function demo_zones_world_boxes(array $zonesRadar, array $radar): array
{
    $out = [];
    foreach ($zonesRadar as $id => $z) {
        $a = demo_zone_radar_to_world((float) $z['nx0'], (float) $z['ny0'], $radar);
        $b = demo_zone_radar_to_world((float) $z['nx1'], (float) $z['ny1'], $radar);
        $out[$id] = [
            'label' => $z['label'],
            'x0' => min($a['x'], $b['x']),
            'x1' => max($a['x'], $b['x']),
            'y0' => min($a['y'], $b['y']),
            'y1' => max($a['y'], $b['y']),
        ];
    }
    return $out;
}

/** Retorna id da zona (primeira match) ou null. Prioriza zonas menores se overlap. */
function demo_zone_at(float $x, float $y, array $boxes): ?string
{
    $hits = [];
    foreach ($boxes as $id => $b) {
        if ($x >= $b['x0'] && $x <= $b['x1'] && $y >= $b['y0'] && $y <= $b['y1']) {
            $area = ($b['x1'] - $b['x0']) * ($b['y1'] - $b['y0']);
            $hits[$id] = $area;
        }
    }
    if (!$hits) {
        return null;
    }
    asort($hits); // menor área = mais específica (xbox dentro de mid)
    return (string) array_key_first($hits);
}

/**
 * Análise territorial a partir dos frames.
 *
 * @param array $frames frames do parse
 * @param callable(string):bool $isNosso
 * @param array|null $roundsMeta rounds com winner etc
 * @return array
 */
function demo_analisar_territorio(
    array $frames,
    callable $isNosso,
    ?string $mapa,
    array $radarCfg,
    ?array $roundsMeta = null,
    float $tickrate = 64.0
): array {
    $zonesRadar = demo_zones_for_map($mapa);
    if (!$zonesRadar || empty($frames)) {
        return [
            'mapa' => $mapa,
            'disponivel' => false,
            'motivo' => empty($frames) ? 'sem_frames' : 'mapa_sem_zonas',
        ];
    }

    $boxes = demo_zones_world_boxes($zonesRadar, $radarCfg);
    $chains = demo_zones_progressao_for_map($mapa);

    // Agrupa frames por round
    $byRound = [];
    foreach ($frames as $f) {
        $r = (int) ($f['r'] ?? $f['round'] ?? 0);
        if ($r < 1) {
            continue;
        }
        if (!isset($byRound[$r])) {
            $byRound[$r] = [];
        }
        $byRound[$r][] = $f;
    }
    foreach ($byRound as &$list) {
        usort($list, static function ($a, $b) {
            return ((int) ($a['t'] ?? 0)) <=> ((int) ($b['t'] ?? 0));
        });
    }
    unset($list);

    // Lado nosso por round (maioria nos frames vivos)
    $nossoLadoRound = [];
    foreach ($byRound as $r => $flist) {
        $ct = 0;
        $tr = 0;
        foreach ($flist as $f) {
            foreach ($f['p'] ?? [] as $pl) {
                $nick = (string) ($pl['n'] ?? '');
                if ($nick === '' || !$isNosso($nick)) {
                    continue;
                }
                if (($pl['a'] ?? true) === false) {
                    continue;
                }
                if (($pl['t'] ?? '') === 'CT') {
                    $ct++;
                } elseif (($pl['t'] ?? '') === 'TR') {
                    $tr++;
                }
            }
        }
        $nossoLadoRound[$r] = $tr >= $ct ? 'TR' : 'CT';
    }

    // Stats por zona
    $zonaStats = [];
    foreach ($boxes as $id => $b) {
        $zonaStats[$id] = [
            'id' => $id,
            'label' => $b['label'],
            'player_ticks' => 0,       // soma jogador-frames nossos vivos
            'rounds_presente' => 0,       // rounds com ≥1 nosso na zona
            'rounds_controle' => 0,       // rounds com controle (CT+TR)
            'rounds_controle_tr' => 0,    // rounds TR com controle (ataque)
            'rounds_ataque' => 0,         // rounds TR nossos com presença
            'max_jogadores' => 0,
        ];
    }

    $roundsComPresenca = []; // zone => set of rounds
    $controleRounds = []; // zone => set (todos os sides)
    $controleRoundsTR = []; // zone => set (só TR / ataque)
    $conquistaOrdem = []; // round => [zone ids in first-seen order for TR attack]

    $intervalSec = 0.5; // default frame spacing; refine from data
    if (count($frames) >= 2) {
        $dt = [];
        for ($i = 1; $i < min(40, count($frames)); $i++) {
            $d = ((int) ($frames[$i]['t'] ?? 0)) - ((int) ($frames[$i - 1]['t'] ?? 0));
            if ($d > 0) {
                $dt[] = $d / max(1.0, $tickrate);
            }
        }
        if ($dt) {
            sort($dt);
            $intervalSec = $dt[(int) floor(count($dt) / 2)];
            $intervalSec = max(0.25, min(2.0, $intervalSec));
        }
    }

    foreach ($byRound as $r => $flist) {
        $lado = $nossoLadoRound[$r] ?? 'TR';
        $seenOrder = [];
        $zonePresenceTicks = []; // zone => ticks with someone
        $zoneMaxPlayers = [];
        $zonePlayerTicks = [];

        foreach ($flist as $f) {
            $inZoneCount = []; // zone => n nossos vivos
            foreach ($f['p'] ?? [] as $pl) {
                $nick = (string) ($pl['n'] ?? '');
                if ($nick === '' || !$isNosso($nick)) {
                    continue;
                }
                if (($pl['a'] ?? true) === false) {
                    continue;
                }
                if ($pl['x'] === null || $pl['y'] === null) {
                    continue;
                }
                $zid = demo_zone_at((float) $pl['x'], (float) $pl['y'], $boxes);
                if ($zid === null) {
                    continue;
                }
                $inZoneCount[$zid] = ($inZoneCount[$zid] ?? 0) + 1;
                $zonePlayerTicks[$zid] = ($zonePlayerTicks[$zid] ?? 0) + 1;
                if ($lado === 'TR' && !isset($seenOrder[$zid])) {
                    $seenOrder[$zid] = count($seenOrder);
                }
            }
            foreach ($inZoneCount as $zid => $n) {
                $zonePresenceTicks[$zid] = ($zonePresenceTicks[$zid] ?? 0) + 1;
                $zoneMaxPlayers[$zid] = max($zoneMaxPlayers[$zid] ?? 0, $n);
            }
        }

        if ($lado === 'TR' && $seenOrder) {
            asort($seenOrder);
            $conquistaOrdem[$r] = array_keys($seenOrder);
        }

        foreach ($zonePlayerTicks as $zid => $pt) {
            if (!isset($zonaStats[$zid])) {
                continue;
            }
            $zonaStats[$zid]['player_ticks'] += $pt;
            $zonaStats[$zid]['max_jogadores'] = max(
                $zonaStats[$zid]['max_jogadores'],
                (int) ($zoneMaxPlayers[$zid] ?? 0)
            );
            if (!isset($roundsComPresenca[$zid])) {
                $roundsComPresenca[$zid] = [];
            }
            $roundsComPresenca[$zid][$r] = true;
            if ($lado === 'TR') {
                $zonaStats[$zid]['rounds_ataque']++;
            }

            // Controle: ≥2 nossos OU ≥3s de presença aproximada
            $framesPresente = (int) ($zonePresenceTicks[$zid] ?? 0);
            $segPresente = $framesPresente * $intervalSec;
            $maxP = (int) ($zoneMaxPlayers[$zid] ?? 0);
            if ($maxP >= 2 || $segPresente >= 3.0) {
                if (!isset($controleRounds[$zid])) {
                    $controleRounds[$zid] = [];
                }
                $controleRounds[$zid][$r] = true;
                // Controle de ataque = só rounds em que somos TR
                if ($lado === 'TR') {
                    if (!isset($controleRoundsTR[$zid])) {
                        $controleRoundsTR[$zid] = [];
                    }
                    $controleRoundsTR[$zid][$r] = true;
                }
            }
        }
    }

    $totalRounds = max(1, count($byRound));
    $roundsTR = 0;
    foreach ($nossoLadoRound as $lado) {
        if ($lado === 'TR') {
            $roundsTR++;
        }
    }
    $roundsTR = max(1, $roundsTR);

    foreach ($zonaStats as $zid => &$st) {
        $st['rounds_presente'] = isset($roundsComPresenca[$zid]) ? count($roundsComPresenca[$zid]) : 0;
        $st['rounds_controle'] = isset($controleRounds[$zid]) ? count($controleRounds[$zid]) : 0;
        $st['rounds_controle_tr'] = isset($controleRoundsTR[$zid]) ? count($controleRoundsTR[$zid]) : 0;
        $st['player_seconds'] = round($st['player_ticks'] * $intervalSec, 1);
        $st['presenca_pct'] = round(100 * $st['rounds_presente'] / $totalRounds, 1);
        $st['controle_pct'] = round(100 * $st['rounds_controle'] / $totalRounds, 1);
        // Ataque: % dos rounds TR em que controlou (nunca > 100%)
        $st['controle_ataque_pct'] = round(100 * $st['rounds_controle_tr'] / $roundsTR, 1);
        unset($st['player_ticks']);
    }
    unset($st);

    // Progressão: rounds TR com controle em FROM → também controle em TO
    $progressao = [];
    foreach ($chains as $ch) {
        $from = $ch['from'];
        $to = $ch['to'];
        $base = 0;
        $ok = 0;
        foreach ($conquistaOrdem as $r => $order) {
            if (($nossoLadoRound[$r] ?? '') !== 'TR') {
                continue;
            }
            if (!isset($controleRoundsTR[$from][$r])) {
                continue;
            }
            $base++;
            $iFrom = array_search($from, $order, true);
            $iTo = array_search($to, $order, true);
            if (
                isset($controleRoundsTR[$to][$r])
                && $iFrom !== false
                && $iTo !== false
                && $iTo > $iFrom
            ) {
                $ok++;
            }
        }
        $progressao[] = [
            'from' => $from,
            'to' => $to,
            'label' => $ch['label'],
            'rounds_base' => $base,
            'rounds_convertidos' => $ok,
            'taxa_pct' => $base > 0 ? round(100 * $ok / $base, 1) : null,
        ];
    }

    // Abertura TR: primeira zona tática do round
    $abertura = [];
    foreach ($conquistaOrdem as $r => $order) {
        foreach ($order as $zid) {
            $abertura[$zid] = ($abertura[$zid] ?? 0) + 1;
            break;
        }
    }
    arsort($abertura);
    $aberturaList = [];
    foreach ($abertura as $zid => $n) {
        $aberturaList[] = [
            'zona' => $zid,
            'label' => $boxes[$zid]['label'] ?? $zid,
            'rounds' => $n,
            'pct' => round(100 * $n / $roundsTR, 1),
        ];
    }

    // Ranking zonas por controle em ataque
    $ranking = array_values($zonaStats);
    usort($ranking, static function ($a, $b) {
        return ($b['controle_ataque_pct'] <=> $a['controle_ataque_pct']);
    });

    return [
        'mapa' => $mapa,
        'disponivel' => true,
        'total_rounds' => $totalRounds,
        'rounds_tr' => $roundsTR,
        'frame_interval_sec' => round($intervalSec, 2),
        'zonas' => $zonaStats,
        'ranking_controle_ataque' => array_slice($ranking, 0, 8),
        'progressao' => $progressao,
        'abertura_tr' => $aberturaList,
        'zonas_radar' => $zonesRadar, // p/ overlay futuro
    ];
}
