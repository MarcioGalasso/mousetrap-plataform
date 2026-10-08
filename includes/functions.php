<?php
require_once __DIR__ . '/db.php';

/** Lê todas as configurações do site (tabela `configuracoes`) em cache estático. */
function get_settings(): array
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        $stmt = db()->query('SELECT chave, valor FROM configuracoes');
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['chave']] = $row['valor'];
        }
    }

    return $settings;
}

/** Lê uma configuração específica, com valor padrão de fallback. */
function get_setting(string $chave, string $default = ''): string
{
    $settings = get_settings();
    return $settings[$chave] ?? $default;
}

/** Salva/atualiza uma configuração (usado pelo painel admin). */
function set_setting(string $chave, string $valor): void
{
    $stmt = db()->prepare(
        'INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)
         ON DUPLICATE KEY UPDATE valor = :valor2'
    );
    $stmt->execute(['chave' => $chave, 'valor' => $valor, 'valor2' => $valor]);
}

/** Gera um slug amigável de URL a partir de um título (ex: "Notícia Nova!" -> "noticia-nova"). */
function gerar_slug(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = preg_replace('/[áàãâä]/u', 'a', $texto);
    $texto = preg_replace('/[éèêë]/u', 'e', $texto);
    $texto = preg_replace('/[íìîï]/u', 'i', $texto);
    $texto = preg_replace('/[óòõôö]/u', 'o', $texto);
    $texto = preg_replace('/[úùûü]/u', 'u', $texto);
    $texto = preg_replace('/[ç]/u', 'c', $texto);
    $texto = preg_replace('/[ñ]/u', 'n', $texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-');
}

/** Garante que o slug seja único numa tabela, adicionando um sufixo numérico se preciso. */
function slug_unico(string $tabela, string $slug, ?int $ignorarId = null): string
{
    $base = $slug;
    $i = 2;

    while (true) {
        $sql = "SELECT id FROM {$tabela} WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($ignorarId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignorarId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function formatar_data(string $dataMysql): string
{
    $ts = strtotime($dataMysql);
    return $ts ? date('d/m/Y', $ts) : '';
}

function formatar_data_hora(string $dataMysql): string
{
    $ts = strtotime($dataMysql);
    return $ts ? date('d/m/Y \à\s H:i', $ts) : '';
}

function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Salva um arquivo de imagem enviado por upload dentro de uploads/{subpasta}. Retorna o nome do arquivo salvo ou null. */
function salvar_upload_imagem(array $arquivo, string $subpasta): ?string
{
    if (!isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio do arquivo (código ' . $arquivo['error'] . ').');
    }
    if ($arquivo['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Imagem maior que o limite permitido (4MB).');
    }

    $tipo = mime_content_type($arquivo['tmp_name']);
    if (!in_array($tipo, UPLOAD_TIPOS_PERMITIDOS, true)) {
        throw new RuntimeException('Formato de imagem não permitido. Use JPG, PNG ou WEBP.');
    }

    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $extensao = $extensoes[$tipo];
    $nomeArquivo = bin2hex(random_bytes(8)) . '.' . $extensao;

    $pastaDestino = UPLOADS_DIR . '/' . $subpasta;
    if (!is_dir($pastaDestino)) {
        mkdir($pastaDestino, 0755, true);
    }

    if (!move_uploaded_file($arquivo['tmp_name'], $pastaDestino . '/' . $nomeArquivo)) {
        throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
    }

    return $nomeArquivo;
}

/**
 * Salva vários arquivos enviados por um único input <input type="file" name="campo[]" multiple>.
 * Retorna a lista de nomes de arquivos salvos (ignora silenciosamente slots vazios).
 */
function salvar_uploads_multiplos(array $arquivos, string $subpasta): array
{
    $salvos = [];
    if (empty($arquivos['name']) || !is_array($arquivos['name'])) {
        return $salvos;
    }

    foreach ($arquivos['name'] as $i => $nome) {
        if ($nome === '' || $arquivos['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $arquivoUnico = [
            'name' => $arquivos['name'][$i],
            'type' => $arquivos['type'][$i],
            'tmp_name' => $arquivos['tmp_name'][$i],
            'error' => $arquivos['error'][$i],
            'size' => $arquivos['size'][$i],
        ];
        $salvo = salvar_upload_imagem($arquivoUnico, $subpasta);
        if ($salvo) {
            $salvos[] = $salvo;
        }
    }

    return $salvos;
}

function url_upload(?string $arquivo, string $subpasta): string
{
    if (empty($arquivo)) {
        return SITE_URL . '/assets/img/placeholder.svg';
    }
    return UPLOADS_URL . '/' . $subpasta . '/' . $arquivo;
}

/**
 * Garante colunas novas (Locaweb / phpMyAdmin às vezes não rodou o SQL).
 * Seguro chamar várias vezes.
 */
function demo_garantir_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "ALTER TABLE jogadores
             ADD COLUMN analisar_na_demo TINYINT(1) NOT NULL DEFAULT 0
             COMMENT '1 = incluir na análise de demos' AFTER ativo"
        );
        // só na criação da coluna: copia quem estava ativo no site
        db()->exec('UPDATE jogadores SET analisar_na_demo = 1 WHERE ativo = 1');
    } catch (Throwable $e) {
        // coluna já existe — não mexer de novo
    }
    try {
        db()->exec(
            "ALTER TABLE demos_analises
             ADD COLUMN time_adversario VARCHAR(100) NULL
             COMMENT 'Nome do time adversário (HUD / relatório)' AFTER time_nosso"
        );
    } catch (Throwable $e) {
        // já existe
    }
}
