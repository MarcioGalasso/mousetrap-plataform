<?php
/**
 * Regenera o relatório IA (Fase A) sem reupar a demo.
 * Recalcula território ao vivo antes de chamar a OpenAI.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/demo_metrics.php';
require_once __DIR__ . '/../../includes/demo_ai.php';
require_once __DIR__ . '/../../includes/demo_radar.php';
require_once __DIR__ . '/../../includes/demo_zones.php';
require_once __DIR__ . '/../../includes/demo_fase_c.php';
exigir_login_api();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método inválido']);
    exit;
}

if (!csrf_valido($_POST['token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
    exit;
}

if (!demo_openai_configured()) {
    echo json_encode(['ok' => false, 'error' => 'Configure a OpenAI API Key em Configurações.']);
    exit;
}

$demoId = (int) ($_POST['demo_id'] ?? 0);
if ($demoId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Demo inválida']);
    exit;
}

$stmt = db()->prepare('SELECT * FROM demos_analises WHERE id = :id');
$stmt->execute(['id' => $demoId]);
$demo = $stmt->fetch();
if (!$demo || $demo['status'] !== 'pronto') {
    echo json_encode(['ok' => false, 'error' => 'Demo não encontrada ou não pronta']);
    exit;
}

try {
    $metricas = json_decode($demo['metricas_json'] ?: '{}', true) ?: [];
    $kills = json_decode($demo['kills_json'] ?: '[]', true) ?: [];
    $rounds = json_decode($demo['rounds_json'] ?: '[]', true) ?: [];
    $nicks = json_decode($demo['jogadores_nossos'] ?: '[]', true) ?: [];
    if (!is_array($nicks)) {
        $nicks = [];
    }

    $frames = [];
    $grenades = [];
    $bombs = [];
    if (!empty($demo['frames_json'])) {
        $decoded = json_decode($demo['frames_json'], true) ?: [];
        if (isset($decoded['frames']) && is_array($decoded['frames'])) {
            $frames = $decoded['frames'];
            $grenades = isset($decoded['grenades']) && is_array($decoded['grenades']) ? $decoded['grenades'] : [];
            $bombs = isset($decoded['bomb_events']) && is_array($decoded['bomb_events']) ? $decoded['bomb_events'] : [];
        } elseif (is_array($decoded)) {
            $frames = $decoded;
        }
    }

    $radarCfg = demo_radar_resolver_mapa($demo['mapa'] ?? null);
    if ($nicks && is_array($kills)) {
        $isNosso = static function (string $nick) use ($nicks): bool {
            return demo_match_nick($nick, $nicks) !== null;
        };
        if ($radarCfg && $frames && demo_zones_for_map($demo['mapa'] ?? null)) {
            $metricas['territorio'] = demo_analisar_territorio(
                $frames,
                $isNosso,
                $demo['mapa'] ?? null,
                $radarCfg,
                is_array($rounds) ? $rounds : [],
                64.0
            );
        }
        $metricas['fase_c'] = demo_analisar_fase_c(
            $kills,
            is_array($rounds) ? $rounds : [],
            $frames,
            $grenades,
            $bombs,
            $isNosso,
            $demo['mapa'] ?? null,
            $radarCfg,
            64.0
        );
        db()->prepare('UPDATE demos_analises SET metricas_json=:m WHERE id=:id')->execute([
            'm' => json_encode($metricas, JSON_UNESCAPED_UNICODE),
            'id' => $demoId,
        ]);
    }

    $timeNome = $demo['time_nosso'] ?: 'MouseTrap';
    $relatorio = demo_gerar_relatorio_automatico($metricas, $kills, $timeNome);

    db()->prepare(
        'UPDATE demos_analises SET relatorio_ia=:r, erro_msg=NULL WHERE id=:id'
    )->execute(['r' => $relatorio, 'id' => $demoId]);

    db()->prepare('INSERT INTO demos_chat (demo_id, papel, mensagem) VALUES (:d,"assistant",:m)')
        ->execute(['d' => $demoId, 'm' => "[Relatório regenerado]\n\n" . $relatorio]);

    echo json_encode([
        'ok' => true,
        'relatorio' => $relatorio,
        'html' => demo_formatar_relatorio_html($relatorio),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
