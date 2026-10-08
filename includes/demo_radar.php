<?php
/**
 * Metadados de radar 2D (calibração world → pixel).
 * pos_x / pos_y / scale = overview HLTV (demoinfocs TranslateScale).
 *
 * Arte DEVE ser overview 1024×1024 alinhado ao txt Valve.
 * Crop/PRACC (ex.: 644×686) deixa o meio “ok” e os cantos fora.
 *
 * A arte 1024 já inclui a borda escura — NÃO aplicar inset “pad”
 * em cima disso (empurrava/errava posições).
 *
 * Se a imagem for um CROP só da área jogável, use inset_* + inset_mode=crop.
 */

function demo_radar_configs(): array
{
    $none = [
        'inset_mode' => 'none',
        'inset_left' => 0.0,
        'inset_top' => 0.0,
        'inset_right' => 0.0,
        'inset_bottom' => 0.0,
        'offset_x' => 0.0,
        'offset_y' => 0.0,
    ];

    return [
        'de_inferno' => array_merge([
            'arquivo' => 'inferno.jpg',
            'label' => 'Inferno',
            'pos_x' => -2087.0,
            'pos_y' => 3870.0,
            'scale' => 4.9,
            'base_size' => 1024,
            'rotate' => 0,
        ], $none),
        'de_dust2' => array_merge([
            'arquivo' => 'dust2.jpg',
            'label' => 'Dust II',
            'pos_x' => -2476.0,
            'pos_y' => 3239.0,
            'scale' => 4.4,
            'base_size' => 1024,
            // Simple Radar / panorama já vêm na orientação final → rotate 0
            'rotate' => 0,
        ], $none),
        'de_mirage' => array_merge([
            'arquivo' => 'mirage.jpg',
            'label' => 'Mirage',
            'pos_x' => -3230.0,
            'pos_y' => 1713.0,
            'scale' => 5.0,
            'base_size' => 1024,
            'rotate' => 0,
        ], $none),
        'de_nuke' => array_merge([
            // Nível superior (default). Lower radar = futuro.
            'arquivo' => 'nuke.jpg',
            'label' => 'Nuke',
            'pos_x' => -3453.0,
            'pos_y' => 2887.0,
            'scale' => 7.0,
            'base_size' => 1024,
            'rotate' => 0,
        ], $none),
        'de_ancient' => array_merge([
            'arquivo' => 'ancient.jpg',
            'label' => 'Ancient',
            'pos_x' => -2953.0,
            'pos_y' => 2164.0,
            'scale' => 5.0,
            'base_size' => 1024,
            'rotate' => 0,
        ], $none),
        'de_anubis' => array_merge([
            'arquivo' => 'anubis.jpg',
            'label' => 'Anubis',
            'pos_x' => -2796.0,
            'pos_y' => 3328.0,
            'scale' => 5.22,
            'base_size' => 1024,
            'rotate' => 0,
        ], $none),
        'de_overpass' => array_merge([
            'arquivo' => 'overpass.jpg',
            'label' => 'Overpass',
            'pos_x' => -4831.0,
            'pos_y' => 1781.0,
            'scale' => 5.2,
            'base_size' => 1024,
            'rotate' => 0,
        ], $none),
        'de_cache' => array_merge([
            'arquivo' => 'cache.jpg',
            'label' => 'Cache',
            'pos_x' => -2000.0,
            'pos_y' => 3250.0,
            'scale' => 5.5,
            'base_size' => 1024,
            'rotate' => 0,
        ], $none),
    ];
}

/** Normaliza nome do mapa do parser (de_inferno / Inferno / etc). */
function demo_radar_resolver_mapa(?string $mapa): ?array
{
    if ($mapa === null || $mapa === '') {
        return null;
    }
    $key = strtolower(trim($mapa));
    $key = str_replace([' ', '-'], '', $key);
    if (strpos($key, 'de_') !== 0) {
        $key = 'de_' . $key;
    }
    $all = demo_radar_configs();
    if (isset($all[$key])) {
        $cfg = $all[$key];
        $cfg['map_key'] = $key;
        return $cfg;
    }
    foreach ($all as $k => $cfg) {
        if (strpos($key, str_replace('de_', '', $k)) !== false) {
            $cfg['map_key'] = $k;
            return $cfg;
        }
    }
    return null;
}

function demo_radar_url(array $cfg): string
{
    return rtrim(SITE_URL, '/') . '/mapas/' . ltrim($cfg['arquivo'], '/');
}
