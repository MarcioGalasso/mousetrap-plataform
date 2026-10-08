<?php
/**
 * Zonas táticas — Mirage, Nuke, Ancient, Anubis, Cache.
 * Bounds em fração radar 0–1 (nx, ny). 1ª passagem — calibrar no heatmap overlay.
 * Sem T/CT Spawn (sempre ~100%).
 */

/** @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}> */
function demo_zones_mirage(): array
{
    // Overview: B topo-esq, A topo-dir, T embaixo, Mid centro
    return [
        'b_site' => ['label' => 'B Site', 'nx0' => 0.18, 'ny0' => 0.06, 'nx1' => 0.38, 'ny1' => 0.26],
        'apps' => ['label' => 'Apartments / Apps', 'nx0' => 0.08, 'ny0' => 0.28, 'nx1' => 0.28, 'ny1' => 0.48],
        'catwalk' => ['label' => 'Catwalk / Short', 'nx0' => 0.32, 'ny0' => 0.22, 'nx1' => 0.48, 'ny1' => 0.40],
        'mid_control' => ['label' => 'Mid Control / Connector', 'nx0' => 0.40, 'ny0' => 0.34, 'nx1' => 0.58, 'ny1' => 0.52],
        'top_mid' => ['label' => 'Top Mid / Underpass', 'nx0' => 0.38, 'ny0' => 0.52, 'nx1' => 0.58, 'ny1' => 0.70],
        'a_main' => ['label' => 'A Main / Palace', 'nx0' => 0.60, 'ny0' => 0.32, 'nx1' => 0.82, 'ny1' => 0.55],
        'a_site' => ['label' => 'A Site', 'nx0' => 0.58, 'ny0' => 0.06, 'nx1' => 0.84, 'ny1' => 0.30],
    ];
}

function demo_zones_progressao_mirage(): array
{
    return [
        ['from' => 'top_mid', 'to' => 'mid_control', 'label' => 'Top Mid → Mid Control'],
        ['from' => 'mid_control', 'to' => 'catwalk', 'label' => 'Mid Control → Catwalk'],
        ['from' => 'catwalk', 'to' => 'b_site', 'label' => 'Catwalk → B'],
        ['from' => 'apps', 'to' => 'b_site', 'label' => 'Apps → B'],
        ['from' => 'mid_control', 'to' => 'a_main', 'label' => 'Mid Control → A Main'],
        ['from' => 'a_main', 'to' => 'a_site', 'label' => 'A Main → A'],
        ['from' => 'top_mid', 'to' => 'a_main', 'label' => 'Top Mid → A Main'],
    ];
}

/** @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}> */
function demo_zones_nuke(): array
{
    // Upper radar: Outside esq, A cima, Lobby/Ramp centro-dir, T baixo-dir
    return [
        'outside' => ['label' => 'Outside / Yard', 'nx0' => 0.08, 'ny0' => 0.28, 'nx1' => 0.32, 'ny1' => 0.72],
        'garage' => ['label' => 'Garage / Red Box', 'nx0' => 0.22, 'ny0' => 0.38, 'nx1' => 0.40, 'ny1' => 0.58],
        'secret' => ['label' => 'Secret', 'nx0' => 0.28, 'ny0' => 0.58, 'nx1' => 0.46, 'ny1' => 0.78],
        'lobby' => ['label' => 'Lobby', 'nx0' => 0.48, 'ny0' => 0.42, 'nx1' => 0.70, 'ny1' => 0.68],
        'ramp' => ['label' => 'Ramp', 'nx0' => 0.42, 'ny0' => 0.28, 'nx1' => 0.62, 'ny1' => 0.48],
        'a_site' => ['label' => 'A Site (Upper)', 'nx0' => 0.38, 'ny0' => 0.06, 'nx1' => 0.62, 'ny1' => 0.28],
        'b_site' => ['label' => 'B Site (Lower)', 'nx0' => 0.34, 'ny0' => 0.72, 'nx1' => 0.58, 'ny1' => 0.92],
    ];
}

function demo_zones_progressao_nuke(): array
{
    return [
        ['from' => 'outside', 'to' => 'garage', 'label' => 'Outside → Garage'],
        ['from' => 'garage', 'to' => 'secret', 'label' => 'Garage → Secret'],
        ['from' => 'secret', 'to' => 'b_site', 'label' => 'Secret → B'],
        ['from' => 'lobby', 'to' => 'ramp', 'label' => 'Lobby → Ramp'],
        ['from' => 'ramp', 'to' => 'b_site', 'label' => 'Ramp → B'],
        ['from' => 'lobby', 'to' => 'a_site', 'label' => 'Lobby → A'],
        ['from' => 'outside', 'to' => 'secret', 'label' => 'Outside → Secret'],
    ];
}

/** @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}> */
function demo_zones_ancient(): array
{
    // B esq, A dir, Mid centro, T baixo
    return [
        'b_site' => ['label' => 'B Site', 'nx0' => 0.10, 'ny0' => 0.18, 'nx1' => 0.32, 'ny1' => 0.42],
        'b_main' => ['label' => 'B Main / Ramp', 'nx0' => 0.18, 'ny0' => 0.42, 'nx1' => 0.38, 'ny1' => 0.62],
        'cheetah' => ['label' => 'Cheetah / Cubby', 'nx0' => 0.34, 'ny0' => 0.32, 'nx1' => 0.48, 'ny1' => 0.48],
        'mid' => ['label' => 'Mid Control / Top Mid', 'nx0' => 0.40, 'ny0' => 0.38, 'nx1' => 0.58, 'ny1' => 0.62],
        'donut' => ['label' => 'Donut', 'nx0' => 0.52, 'ny0' => 0.28, 'nx1' => 0.68, 'ny1' => 0.46],
        'a_main' => ['label' => 'A Main', 'nx0' => 0.62, 'ny0' => 0.42, 'nx1' => 0.82, 'ny1' => 0.66],
        'a_site' => ['label' => 'A Site', 'nx0' => 0.62, 'ny0' => 0.12, 'nx1' => 0.88, 'ny1' => 0.36],
    ];
}

function demo_zones_progressao_ancient(): array
{
    return [
        ['from' => 'mid', 'to' => 'donut', 'label' => 'Mid → Donut'],
        ['from' => 'donut', 'to' => 'a_site', 'label' => 'Donut → A'],
        ['from' => 'a_main', 'to' => 'a_site', 'label' => 'A Main → A'],
        ['from' => 'mid', 'to' => 'cheetah', 'label' => 'Mid → Cheetah'],
        ['from' => 'cheetah', 'to' => 'b_site', 'label' => 'Cheetah → B'],
        ['from' => 'b_main', 'to' => 'b_site', 'label' => 'B Main → B'],
        ['from' => 'mid', 'to' => 'a_main', 'label' => 'Mid → A Main'],
    ];
}

/** @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}> */
function demo_zones_anubis(): array
{
    // Canal baixo-centro, Mid centro, A dir, B esq
    return [
        'b_site' => ['label' => 'B Site', 'nx0' => 0.10, 'ny0' => 0.12, 'nx1' => 0.34, 'ny1' => 0.34],
        'b_main' => ['label' => 'B Main', 'nx0' => 0.14, 'ny0' => 0.34, 'nx1' => 0.36, 'ny1' => 0.54],
        'canal' => ['label' => 'Canal / Water', 'nx0' => 0.28, 'ny0' => 0.52, 'nx1' => 0.52, 'ny1' => 0.78],
        'bridge' => ['label' => 'Bridge', 'nx0' => 0.36, 'ny0' => 0.40, 'nx1' => 0.54, 'ny1' => 0.56],
        'mid' => ['label' => 'Mid Control', 'nx0' => 0.42, 'ny0' => 0.28, 'nx1' => 0.62, 'ny1' => 0.48],
        'a_main' => ['label' => 'A Main', 'nx0' => 0.58, 'ny0' => 0.42, 'nx1' => 0.80, 'ny1' => 0.68],
        'a_site' => ['label' => 'A Site', 'nx0' => 0.62, 'ny0' => 0.10, 'nx1' => 0.88, 'ny1' => 0.34],
    ];
}

function demo_zones_progressao_anubis(): array
{
    return [
        ['from' => 'canal', 'to' => 'bridge', 'label' => 'Canal → Bridge'],
        ['from' => 'bridge', 'to' => 'mid', 'label' => 'Bridge → Mid'],
        ['from' => 'canal', 'to' => 'b_main', 'label' => 'Canal → B Main'],
        ['from' => 'b_main', 'to' => 'b_site', 'label' => 'B Main → B'],
        ['from' => 'mid', 'to' => 'a_main', 'label' => 'Mid → A Main'],
        ['from' => 'a_main', 'to' => 'a_site', 'label' => 'A Main → A'],
        ['from' => 'mid', 'to' => 'b_main', 'label' => 'Mid → B Main'],
    ];
}

/** @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}> */
function demo_zones_cache(): array
{
    // B esq, A dir, Mid/Garage centro, T baixo
    return [
        'b_site' => ['label' => 'B Site', 'nx0' => 0.08, 'ny0' => 0.18, 'nx1' => 0.30, 'ny1' => 0.40],
        'checkers' => ['label' => 'Checkers', 'nx0' => 0.18, 'ny0' => 0.36, 'nx1' => 0.36, 'ny1' => 0.52],
        'b_halls' => ['label' => 'B Halls / B Main', 'nx0' => 0.12, 'ny0' => 0.50, 'nx1' => 0.34, 'ny1' => 0.70],
        'vents' => ['label' => 'Vents', 'nx0' => 0.34, 'ny0' => 0.34, 'nx1' => 0.48, 'ny1' => 0.50],
        'mid' => ['label' => 'Mid Control / Garage', 'nx0' => 0.40, 'ny0' => 0.40, 'nx1' => 0.60, 'ny1' => 0.62],
        'highway' => ['label' => 'Highway', 'nx0' => 0.52, 'ny0' => 0.28, 'nx1' => 0.70, 'ny1' => 0.46],
        'a_main' => ['label' => 'A Main', 'nx0' => 0.62, 'ny0' => 0.40, 'nx1' => 0.84, 'ny1' => 0.64],
        'a_site' => ['label' => 'A Site', 'nx0' => 0.64, 'ny0' => 0.12, 'nx1' => 0.90, 'ny1' => 0.36],
    ];
}

function demo_zones_progressao_cache(): array
{
    return [
        ['from' => 'mid', 'to' => 'highway', 'label' => 'Mid → Highway'],
        ['from' => 'highway', 'to' => 'a_site', 'label' => 'Highway → A'],
        ['from' => 'a_main', 'to' => 'a_site', 'label' => 'A Main → A'],
        ['from' => 'mid', 'to' => 'vents', 'label' => 'Mid → Vents'],
        ['from' => 'vents', 'to' => 'checkers', 'label' => 'Vents → Checkers'],
        ['from' => 'checkers', 'to' => 'b_site', 'label' => 'Checkers → B'],
        ['from' => 'b_halls', 'to' => 'checkers', 'label' => 'B Halls → Checkers'],
        ['from' => 'b_halls', 'to' => 'b_site', 'label' => 'B Halls → B'],
    ];
}

/** @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}> */
function demo_zones_inferno(): array
{
    // Overview típico: B/Banana esq, A/Apps topo-dir, Mid centro, T baixo
    return [
        'b_site' => ['label' => 'B Site', 'nx0' => 0.08, 'ny0' => 0.28, 'nx1' => 0.28, 'ny1' => 0.52],
        'banana' => ['label' => 'Banana (Top / Car)', 'nx0' => 0.18, 'ny0' => 0.48, 'nx1' => 0.40, 'ny1' => 0.72],
        'mid' => ['label' => 'Main Mid (Boiler & Short)', 'nx0' => 0.40, 'ny0' => 0.32, 'nx1' => 0.58, 'ny1' => 0.55],
        'second_mid' => ['label' => 'Second Mid', 'nx0' => 0.48, 'ny0' => 0.48, 'nx1' => 0.66, 'ny1' => 0.68],
        'apps' => ['label' => 'Apartments / Apps', 'nx0' => 0.58, 'ny0' => 0.18, 'nx1' => 0.80, 'ny1' => 0.42],
        'a_site' => ['label' => 'A Site', 'nx0' => 0.62, 'ny0' => 0.02, 'nx1' => 0.88, 'ny1' => 0.24],
    ];
}

function demo_zones_progressao_inferno(): array
{
    return [
        ['from' => 'banana', 'to' => 'b_site', 'label' => 'Banana → B'],
        ['from' => 'mid', 'to' => 'banana', 'label' => 'Mid → Banana'],
        ['from' => 'mid', 'to' => 'second_mid', 'label' => 'Mid → Second Mid'],
        ['from' => 'second_mid', 'to' => 'apps', 'label' => 'Second Mid → Apps'],
        ['from' => 'apps', 'to' => 'a_site', 'label' => 'Apps → A'],
        ['from' => 'mid', 'to' => 'apps', 'label' => 'Mid → Apps'],
    ];
}

/** @return array<string, array{label:string,nx0:float,ny0:float,nx1:float,ny1:float}> */
function demo_zones_overpass(): array
{
    // A cima-dir (long/park), B baixo (monster), toilets/connector centro
    return [
        'a_site' => ['label' => 'A Site', 'nx0' => 0.55, 'ny0' => 0.05, 'nx1' => 0.82, 'ny1' => 0.28],
        'a_long' => ['label' => 'A Long', 'nx0' => 0.62, 'ny0' => 0.28, 'nx1' => 0.88, 'ny1' => 0.52],
        'playground' => ['label' => 'Playground / Fountain', 'nx0' => 0.42, 'ny0' => 0.42, 'nx1' => 0.64, 'ny1' => 0.64],
        'toilets' => ['label' => 'Restrooms / Toilets', 'nx0' => 0.38, 'ny0' => 0.28, 'nx1' => 0.56, 'ny1' => 0.46],
        'connector' => ['label' => 'Connector', 'nx0' => 0.28, 'ny0' => 0.36, 'nx1' => 0.44, 'ny1' => 0.54],
        'monster' => ['label' => 'Monster / Short Pipe', 'nx0' => 0.12, 'ny0' => 0.48, 'nx1' => 0.36, 'ny1' => 0.72],
        'b_site' => ['label' => 'B Site', 'nx0' => 0.08, 'ny0' => 0.68, 'nx1' => 0.36, 'ny1' => 0.92],
    ];
}

function demo_zones_progressao_overpass(): array
{
    return [
        ['from' => 'playground', 'to' => 'toilets', 'label' => 'Playground → Toilets'],
        ['from' => 'toilets', 'to' => 'a_site', 'label' => 'Toilets → A'],
        ['from' => 'a_long', 'to' => 'a_site', 'label' => 'A Long → A'],
        ['from' => 'playground', 'to' => 'a_long', 'label' => 'Playground → A Long'],
        ['from' => 'toilets', 'to' => 'connector', 'label' => 'Toilets → Connector'],
        ['from' => 'connector', 'to' => 'monster', 'label' => 'Connector → Monster'],
        ['from' => 'monster', 'to' => 'b_site', 'label' => 'Monster → B'],
        ['from' => 'playground', 'to' => 'monster', 'label' => 'Playground → Monster'],
    ];
}
