<?php
/**
 * Recebe o JSON já parseado (minimizado) e finaliza a análise.
 * Aceita application/json OU multipart/form-data (mais compatível com a Locaweb).
 *
 * Importante: grava métricas ANTES da chamada à OpenAI.
 * Se a IA estourar o timeout da hospedagem, o dashboard já fica pronto.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Sempre devolve JSON, inclusive em fatal/erro
register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatais = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatais, true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Fatal PHP: ' . $err['message'] . ' em ' . basename($err['file']) . ':' . $err['line'],
    ], JSON_UNESCAPED_UNICODE);
});

try {
    require_once __DIR__ . '/../includes/admin_auth.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../includes/demo_metrics.php';
    require_once __DIR__ . '/../../includes/demo_ai.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Falha ao carregar includes: ' . $e->getMessage() .
            ' — confira se enviou includes/demo_metrics.php, includes/demo_ai.php e admin/includes/admin_auth.php no FileZilla.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(180);
@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '256M');

// Fallback se o admin_auth.php na Locaweb estiver desatualizado (sem exigir_login_api)
if (!function_exists('exigir_login_api')) {
    function exigir_login_api(): void
    {
        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Sessão expirada. Faça login de novo.']);
            exit;
        }
    }
}
if (!function_exists('csrf_valido')) {
    function csrf_valido(?string $token): bool
    {
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

exigir_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método inválido']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$body = [];

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
} else {
    $body = [
        'token' => $_POST['token'] ?? null,
        'demo_id' => $_POST['demo_id'] ?? null,
        'payload' => null,
    ];
    if (!empty($_POST['payload'])) {
        $body['payload'] = json_decode($_POST['payload'], true);
    } elseif (!empty($_FILES['payload_file']['tmp_name'])) {
        $raw = file_get_contents($_FILES['payload_file']['tmp_name']);
        $body['payload'] = json_decode($raw, true);
    }
}

if (!csrf_valido($body['token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sessão expirada']);
    exit;
}

$demoId = (int) ($body['demo_id'] ?? 0);
$payload = $body['payload'] ?? null;
if ($demoId <= 0 || !is_array($payload)) {
    echo json_encode([
        'ok' => false,
        'error' => 'Dados incompletos (demo_id/payload). upload_err=' .
            (string) ($_FILES['payload_file']['error'] ?? 'sem_arquivo'),
    ]);
    exit;
}

try {
    $stmt = db()->prepare('SELECT * FROM demos_analises WHERE id = :id');
    $stmt->execute(['id' => $demoId]);
    $demo = $stmt->fetch();
    if (!$demo) {
        echo json_encode(['ok' => false, 'error' => 'Análise não encontrada']);
        exit;
    }

    $focoModo = trim((string) ($body['foco_modo'] ?? $_POST['foco_modo'] ?? 'cadastro'));
    $listaManual = null;
    if ($focoModo === 'manual') {
        $rawFoco = $body['jogadores_foco'] ?? $_POST['jogadores_foco'] ?? '';
        if (is_array($rawFoco)) {
            $listaManual = $rawFoco;
        } else {
            $listaManual = preg_split('/\r\n|\r|\n/', (string) $rawFoco) ?: [];
        }
        $listaManual = array_values(array_filter(array_map('trim', $listaManual), static function ($n) {
            return $n !== '';
        }));
        if (empty($listaManual)) {
            throw new RuntimeException('Informe pelo menos um nick (um por linha) no modo manual.');
        }
    }

    $detect = demo_detectar_nosso_time($payload, $listaManual);
    $jogadores = $detect['jogadores'];
    if (empty($jogadores)) {
        $elenco = $detect['elenco_ativo'] ?? [];
        $demoNicks = $detect['nicks_demo_sem_match'] ?? [];
        if ($focoModo === 'manual') {
            throw new RuntimeException(
                'Nenhum dos nicks informados foi encontrado na demo. ' .
                'Lista: [' . implode(', ', $elenco) . ']. ' .
                'Nicks na demo: [' . implode(', ', array_slice($demoNicks, 0, 20)) . '].'
            );
        }
        throw new RuntimeException(
            'Nenhum jogador com “Analisar na demo” foi encontrado. ' .
            'Marque em Admin → Jogadores (campo Analisar na demo). ' .
            'Elenco análise: [' . implode(', ', $elenco) . ']. ' .
            'Nicks na demo: [' . implode(', ', array_slice($demoNicks, 0, 16)) . '].'
        );
    }

    $norm = demo_normalize_payload($payload);
    $metricas = demo_calcular_metricas($payload, $jogadores);
    $metricas['deteccao_time'] = [
        'origem' => $detect['origem'],
        'jogadores' => $jogadores,
        'mapa_nicks' => $detect['mapa_nicks'] ?? [],
        'elenco_ativo' => $detect['elenco_ativo'] ?? [],
        'lineups' => $detect['lineups'],
    ];
    $metricas['elenco_foco'] = $jogadores;
    $mapa = $norm['mapa'] ?? ($metricas['mapa'] ?? null);
    $timeNome = $demo['time_nosso'] ?: 'MouseTrap';

    demo_garantir_schema();
    $timeAdv = trim((string) ($demo['time_adversario'] ?? ''));
    if ($timeAdv === '') {
        $inferido = demo_inferir_time_adversario(
            $payload,
            $jogadores,
            $timeNome,
            (string) ($demo['titulo'] ?? '')
        );
        if ($inferido) {
            $timeAdv = $inferido;
        }
    }

    // 1) Grava métricas imediatamente — dashboard já fica utilizável
    $relatorioPendente = demo_openai_configured()
        ? 'Gerando relatório da IA… Recarregue esta página em alguns segundos se ainda aparecer esta mensagem.'
        : "Configure a OpenAI API Key em Configurações para o relatório tático automático.\n\nAs métricas já estão no dashboard.";

    $framesList = $norm['frames'] ?? [];
    if (!is_array($framesList)) {
        $framesList = [];
    }
    $shotsList = $norm['shots'] ?? [];
    if (!is_array($shotsList)) {
        $shotsList = [];
    }
    $grenadesList = $norm['grenades'] ?? [];
    if (!is_array($grenadesList)) {
        $grenadesList = [];
    }
    $bombsList = $norm['bombs'] ?? [];
    if (!is_array($bombsList)) {
        $bombsList = [];
    }
    $framesCount = count($framesList);
    // v4: frames + shots + grenades + bomb_events (legado v2/v3 ok)
    $framesJson = json_encode([
        'v' => 4,
        'frames' => $framesList,
        'shots' => $shotsList,
        'grenades' => $grenadesList,
        'bomb_events' => $bombsList,
    ], JSON_UNESCAPED_UNICODE);
    $killsJson = json_encode($norm['kills'], JSON_UNESCAPED_UNICODE);
    $roundsJson = json_encode($norm['rounds'] ?? [], JSON_UNESCAPED_UNICODE);
    $metricas['espacial'] = array_merge($metricas['espacial'] ?? [], [
        'total_frames' => $framesCount,
        'total_shots' => count($shotsList),
        'total_grenades' => count($grenadesList),
        'fase' => $framesCount > 0 ? 1 : 0,
    ]);
    $metricasJson = json_encode($metricas, JSON_UNESCAPED_UNICODE);

    $framesSaved = false;
    $metricasSalvas = false;

    // Reconecta antes do UPDATE pesado (parse/métricas podem ter estourado wait_timeout)
    db_reconnect();

    // Garante coluna frames_json (phpMyAdmin às vezes não rodou o SQL)
    try {
        db()->exec(
            'ALTER TABLE demos_analises
             ADD COLUMN frames_json LONGTEXT NULL
             COMMENT \'FASE 1: posições por segundo\' AFTER metricas_json'
        );
    } catch (Throwable $e) {
        // já existe — ok
    }

    try {
        db_with_retry(static function () use (
            $mapa,
            $jogadores,
            $timeAdv,
            $killsJson,
            $roundsJson,
            $metricasJson,
            $framesJson,
            $relatorioPendente,
            $demoId
        ) {
            $upd = db()->prepare(
                'UPDATE demos_analises SET
                    mapa=:mapa,
                    jogadores_nossos=:j,
                    time_adversario=COALESCE(NULLIF(:ta, ""), time_adversario),
                    status="pronto",
                    kills_json=:kills,
                    rounds_json=:rounds,
                    metricas_json=:metricas,
                    frames_json=:frames,
                    relatorio_ia=:relatorio,
                    erro_msg=NULL
                 WHERE id=:id'
            );
            $upd->execute([
                'mapa' => $mapa,
                'j' => json_encode($jogadores, JSON_UNESCAPED_UNICODE),
                'ta' => $timeAdv,
                'kills' => $killsJson,
                'rounds' => $roundsJson,
                'metricas' => $metricasJson,
                'frames' => $framesJson,
                'relatorio' => $relatorioPendente,
                'id' => $demoId,
            ]);
        });
        $framesSaved = true;
        $metricasSalvas = true;
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'frames_json') === false && stripos($e->getMessage(), 'time_adversario') === false) {
            throw $e;
        }
        db_reconnect();
        db_with_retry(static function () use (
            $mapa,
            $jogadores,
            $killsJson,
            $roundsJson,
            $metricasJson,
            $relatorioPendente,
            $demoId
        ) {
            $upd = db()->prepare(
                'UPDATE demos_analises SET
                    mapa=:mapa,
                    jogadores_nossos=:j,
                    status="pronto",
                    kills_json=:kills,
                    rounds_json=:rounds,
                    metricas_json=:metricas,
                    relatorio_ia=:relatorio,
                    erro_msg=NULL
                 WHERE id=:id'
            );
            $upd->execute([
                'mapa' => $mapa,
                'j' => json_encode($jogadores, JSON_UNESCAPED_UNICODE),
                'kills' => $killsJson,
                'rounds' => $roundsJson,
                'metricas' => $metricasJson,
                'relatorio' => $relatorioPendente,
                'id' => $demoId,
            ]);
        });
        $framesSaved = false;
        $metricasSalvas = true;
    }

    // 2) IA por último — MySQL costuma cair durante a espera da OpenAI
    $relatorio = $relatorioPendente;
    $erroIa = null;
    if (demo_openai_configured()) {
        try {
            $relatorio = demo_gerar_relatorio_automatico($metricas, $norm['kills'], $timeNome);
            db_reconnect();
            db_with_retry(static function () use ($relatorio, $demoId) {
                db()->prepare(
                    'UPDATE demos_analises SET relatorio_ia=:r, erro_msg=NULL WHERE id=:id'
                )->execute(['r' => $relatorio, 'id' => $demoId]);
                db()->prepare('INSERT INTO demos_chat (demo_id, papel, mensagem) VALUES (:d,"assistant",:m)')
                    ->execute(['d' => $demoId, 'm' => $relatorio]);
            });
        } catch (Throwable $e) {
            $erroIa = $e->getMessage();
            $relatorio = 'Métricas ok. A IA falhou: ' . $erroIa;
            // Não derruba o finalize se métricas já estão salvas
            try {
                db_reconnect();
                db_with_retry(static function () use ($relatorio, $erroIa, $demoId) {
                    db()->prepare(
                        'UPDATE demos_analises SET relatorio_ia=:r, erro_msg=:e WHERE id=:id'
                    )->execute(['r' => $relatorio, 'e' => $erroIa, 'id' => $demoId]);
                });
            } catch (Throwable $ignore) {
                // métricas já prontas — usuário regenera relatório depois
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'redirect' => SITE_URL . '/admin/demos/ver.php?id=' . $demoId . '&ok=1',
        'ia_ok' => $erroIa === null && demo_openai_configured(),
        'ia_erro' => $erroIa,
        'frames_count' => $framesCount,
        'frames_saved' => $framesSaved,
        'metricas_salvas' => $metricasSalvas,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    try {
        db_reconnect();
        db()->prepare('UPDATE demos_analises SET status="erro", erro_msg=:e WHERE id=:id')
            ->execute(['e' => $e->getMessage(), 'id' => $demoId]);
    } catch (Throwable $ignore) {
        // ignore
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'where' => basename($e->getFile()) . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
}
