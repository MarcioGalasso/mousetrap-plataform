<?php
/**
 * Cria o registro da análise e devolve credenciais do parser
 * para o browser subir o .dem direto no Render (evita 403 na Locaweb).
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../../includes/functions.php';
exigir_login_api();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método inválido']);
    exit;
}

if (!csrf_valido($_POST['token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sessão expirada']);
    exit;
}

demo_garantir_schema();

$titulo = trim($_POST['titulo'] ?? '');
$timeNome = trim($_POST['time_nosso'] ?? 'MouseTrap') ?: 'MouseTrap';
$timeAdv = trim($_POST['time_adversario'] ?? '');
if ($timeAdv === '') {
    $timeAdv = null;
}

if ($titulo === '') {
    echo json_encode(['ok' => false, 'error' => 'Informe um título.']);
    exit;
}

$analyzerUrl = rtrim(get_setting('demo_analyzer_url', ''), '/');
$secret = get_setting('demo_analyzer_secret', 'mousetrap-demo-secret');

if ($analyzerUrl === '') {
    echo json_encode(['ok' => false, 'error' => 'Configure a URL do Demo Analyzer em Configurações.']);
    exit;
}

try {
    $stmt = db()->prepare(
        'INSERT INTO demos_analises (titulo, time_nosso, time_adversario, jogadores_nossos, status)
         VALUES (:t,:tn,:ta,:j,"processando")'
    );
    $stmt->execute([
        't' => $titulo,
        'tn' => $timeNome,
        'ta' => $timeAdv,
        'j' => json_encode([], JSON_UNESCAPED_UNICODE),
    ]);
    $demoId = (int) db()->lastInsertId();

    echo json_encode([
        'ok' => true,
        'demo_id' => $demoId,
        'analyzer_url' => $analyzerUrl,
        'analyzer_secret' => $secret,
        'token' => csrf_token(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // fallback se coluna time_adversario ainda não existir no meio do request
    try {
        $stmt = db()->prepare(
            'INSERT INTO demos_analises (titulo, time_nosso, jogadores_nossos, status)
             VALUES (:t,:tn,:j,"processando")'
        );
        $stmt->execute([
            't' => $titulo,
            'tn' => $timeNome,
            'j' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);
        $demoId = (int) db()->lastInsertId();
        echo json_encode([
            'ok' => true,
            'demo_id' => $demoId,
            'analyzer_url' => $analyzerUrl,
            'analyzer_secret' => $secret,
            'token' => csrf_token(),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e2) {
        echo json_encode(['ok' => false, 'error' => $e2->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
