<?php
/**
 * Configurações gerais do site MouseTrap Esports.
 *
 * Este arquivo detecta automaticamente se está rodando em localhost (desenvolvimento)
 * ou no domínio real (produção na Locaweb) e usa o conjunto de credenciais certo,
 * assim você não precisa editar nada ao subir alterações para o ar.
 */

$host = $_SERVER['HTTP_HOST'] ?? '';
$ehLocal = in_array($host, ['localhost', '127.0.0.1'], true)
    || strpos($host, 'localhost:') === 0;

if ($ehLocal) {
    // ---- Banco de dados (desenvolvimento local — Laragon/XAMPP) ----
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'mousetrap');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    define('SITE_URL', 'http://localhost/mousetrap');
} else {
    // ---- Banco de dados (produção — Locaweb) ----
    define('DB_HOST', 'mousetrapdb2.mysql.dbaas.com.br'); // confirme no painel se o host é este mesmo
    define('DB_NAME', 'mousetrapdb2');
    define('DB_USER', 'mousetrapdb2'); // confirme no painel se o usuário é este mesmo
    define('DB_PASS', 'Maori342#');

    // Detecta automaticamente se a requisição atual é http ou https,
    // evitando que o site tente carregar CSS/JS/imagens no protocolo errado
    // enquanto o certificado SSL do domínio ainda não estiver 100% ativo.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $protocolo = $https ? 'https://' : 'http://';

    // Usa o host real da requisição (em vez de fixar "mousetrap.com.br"),
    // assim o site também funciona corretamente quando acessado pelo
    // endereço temporário/SSL compartilhado da Locaweb (ex.: mousetrapX.websiteseguro.com)
    // sem quebrar CSS/JS/imagens por causa de domínio ou protocolo diferente.
    define('SITE_URL', $protocolo . $host);
}

// ---- Site ----
define('SITE_NOME', 'MouseTrap Esports');

// ---- Uploads ----
define('UPLOADS_DIR', __DIR__ . '/../uploads');
define('UPLOADS_URL', SITE_URL . '/uploads');
define('UPLOAD_MAX_BYTES', 4 * 1024 * 1024); // 4MB por imagem
define('UPLOAD_TIPOS_PERMITIDOS', ['image/jpeg', 'image/png', 'image/webp']);

// ---- Sessão / segurança do painel admin ----
define('ADMIN_SESSION_NOME', 'mousetrap_admin');

date_default_timezone_set('America/Sao_Paulo');
