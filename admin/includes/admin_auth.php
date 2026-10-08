<?php
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(ADMIN_SESSION_NOME);
    session_start();
}

function admin_logado(): bool
{
    return !empty($_SESSION['admin_id']);
}

function exigir_login(): void
{
    if (!admin_logado()) {
        redirecionar(SITE_URL . '/admin/login.php');
    }
}

/** Para endpoints AJAX/fetch: devolve JSON 401 em vez de redirect HTML. */
function exigir_login_api(): void
{
    if (!admin_logado()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sessão expirada. Faça login de novo.']);
        exit;
    }
}

function admin_atual(): ?array
{
    if (!admin_logado()) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username FROM admins WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

/** Token CSRF simples para formulários do painel. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valido(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
