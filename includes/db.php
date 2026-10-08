<?php
require_once __DIR__ . '/config.php';

/**
 * PDO singleton. Em operações longas (parse/IA) a Locaweb corta wait_timeout
 * → "MySQL server has gone away". Use db_reconnect() antes de gravar de novo.
 */
function db(bool $reconnect = false): PDO
{
    static $pdo = null;

    if ($reconnect) {
        $pdo = null;
    }

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Shared hosting: best-effort (pode falhar sem quebrar)
            try {
                $pdo->exec('SET SESSION wait_timeout=600');
                $pdo->exec('SET SESSION interactive_timeout=600');
            } catch (Throwable $ignore) {
                // ignore
            }
        } catch (PDOException $e) {
            http_response_code(500);
            die('Não foi possível conectar ao banco de dados. Verifique includes/config.php.');
        }
    }

    return $pdo;
}

/** Força nova conexão (após OpenAI / parse longo). */
function db_reconnect(): PDO
{
    return db(true);
}

/** true se a mensagem for “server has gone away” / conexão perdida. */
function db_is_gone_away(Throwable $e): bool
{
    $m = $e->getMessage();
    return stripos($m, '2006') !== false
        || stripos($m, 'gone away') !== false
        || stripos($m, 'Lost connection') !== false
        || stripos($m, 'server has gone away') !== false;
}

/**
 * Executa callback; se MySQL caiu, reconecta 1× e tenta de novo.
 *
 * @param callable():mixed $fn
 * @return mixed
 */
function db_with_retry(callable $fn)
{
    try {
        return $fn();
    } catch (Throwable $e) {
        if (!db_is_gone_away($e)) {
            throw $e;
        }
        db_reconnect();
        return $fn();
    }
}
