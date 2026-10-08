<?php
/**
 * Integração OpenAI para análise tática de demos.
 * Fase A: template estruturado + meta pack + diagnóstico 🟢🟡🔴 + treino encorpado.
 * Foco: elenco MouseTrap (jogadores ativos do cadastro).
 */

require_once __DIR__ . '/demo_meta.php';

function demo_openai_configured(): bool
{
    $key = get_setting('openai_api_key', '');
    return $key !== '' && $key !== 'sua-chave-aqui';
}

function demo_openai_chat(array $messages, float $temperature = 0.4): string
{
    $key = get_setting('openai_api_key', '');
    if ($key === '') {
        throw new RuntimeException('Configure a OpenAI API Key em Configurações do painel.');
    }
    $model = get_setting('openai_model', 'gpt-4o-mini') ?: 'gpt-4o-mini';

    $payload = json_encode([
        'model' => $model,
        'temperature' => $temperature,
        'messages' => $messages,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Falha cURL OpenAI: ' . $err);
    }
    $json = json_decode($raw, true);
    if ($code >= 400) {
        $msg = $json['error']['message'] ?? $raw;
        throw new RuntimeException('OpenAI HTTP ' . $code . ': ' . $msg);
    }
    $text = $json['choices'][0]['message']['content'] ?? '';
    if ($text === '') {
        throw new RuntimeException('Resposta vazia da OpenAI.');
    }
    return $text;
}

/**
 * Compacta métricas p/ o prompt (evita estourar contexto).
 */
function demo_ai_payload_compacto(array $metricas, array $killsSample, string $timeNome): array
{
    $mapa = $metricas['mapa'] ?? null;
    $territorio = demo_meta_territorio_resumo($metricas['territorio'] ?? null);
    $meta = demo_meta_pack_for_map(is_string($mapa) ? $mapa : null);

    $jogadores = [];
    foreach ($metricas['jogadores'] ?? [] as $nick => $j) {
        if (!is_array($j)) {
            continue;
        }
        $jogadores[] = [
            'nick' => $j['nick_oficial'] ?? (is_string($nick) ? $nick : ($j['nick'] ?? '?')),
            'kills' => $j['kills'] ?? null,
            'deaths' => $j['deaths'] ?? null,
            'opening' => $j['opening'] ?? null,
            'hs' => $j['hs'] ?? null,
            'fundo_kills' => $j['fundo_kills'] ?? null,
            'fundo_deaths' => $j['fundo_deaths'] ?? null,
        ];
    }

    return [
        'time_analisado' => $timeNome,
        'mapa' => $mapa,
        'resumo' => $metricas['resumo'] ?? null,
        'elenco_oficial_foco' => $metricas['elenco_foco']
            ?? ($metricas['deteccao_time']['jogadores'] ?? []),
        'nicks_demo_para_oficial' => $metricas['deteccao_time']['mapa_nicks'] ?? [],
        'opening_kill' => $metricas['opening_kill'] ?? null,
        'vantagem_5v4' => $metricas['vantagem_5v4'] ?? null,
        'desvantagem_4v5' => $metricas['desvantagem_4v5'] ?? null,
        'economia' => $metricas['economia'] ?? null,
        'pistol' => $metricas['pistol'] ?? null,
        'traded_deaths' => $metricas['traded_deaths'] ?? null,
        'bomb_preference_tr' => $metricas['bomb_preference_tr'] ?? null,
        'espacial' => [
            'tem_posicoes_kill' => $metricas['espacial']['tem_posicoes_kill'] ?? null,
            'total_frames' => $metricas['espacial']['total_frames'] ?? null,
            'kills_fundo' => $metricas['espacial']['kills_fundo'] ?? null,
            'mortes_fundo' => $metricas['espacial']['mortes_fundo'] ?? null,
        ],
        'jogadores' => $jogadores,
        'adversarios_que_mais_puniram' => array_slice($metricas['adversarios_destaque'] ?? [], 0, 5),
        'territorio' => $territorio,
        'fase_c' => demo_ai_fase_c_resumo($metricas['fase_c'] ?? null),
        'meta_pack' => $meta,
        'amostra_kills' => array_slice($killsSample, 0, 60),
        'total_kills_partida' => count($killsSample),
    ];
}

/**
 * Compacta sinais Fase C p/ o prompt.
 */
function demo_ai_fase_c_resumo(?array $fc): ?array
{
    if (!$fc || empty($fc['disponivel'])) {
        return $fc ? ['disponivel' => false] : null;
    }
    $ma = $fc['man_advantage'] ?? [];
    $ab = $fc['abertura'] ?? [];
    $ut = $fc['util'] ?? [];

    $pack5 = $ma['5v4'] ?? [];
    $pack4 = $ma['4v5'] ?? [];

    return [
        'disponivel' => true,
        'man_advantage' => [
            '5v4' => [
                'situacoes' => $pack5['situacoes'] ?? 0,
                'win_rate' => $pack5['win_rate'] ?? null,
                'comportamentos_pct' => $pack5['comportamentos_pct'] ?? [],
                'por_lado' => $pack5['por_lado'] ?? [],
                'amostras' => array_slice($pack5['amostras'] ?? [], 0, 5),
            ],
            '4v5' => [
                'situacoes' => $pack4['situacoes'] ?? 0,
                'win_rate' => $pack4['win_rate'] ?? null,
                'comportamentos_pct' => $pack4['comportamentos_pct'] ?? [],
                'por_lado' => $pack4['por_lado'] ?? [],
                'amostras' => array_slice($pack4['amostras'] ?? [], 0, 5),
            ],
            'leitura' => $ma['leitura'] ?? [],
        ],
        'abertura' => [
            'por_economia' => $ab['por_economia'] ?? [],
            'zonas_tr_top' => $ab['zonas_tr_top'] ?? [],
            'previsivel' => $ab['previsivel'] ?? false,
            'leitura' => $ab['leitura'] ?? null,
        ],
        'util' => [
            'disponivel' => !empty($ut['disponivel']),
            'total_nossa_em_zona' => $ut['total_nossa_em_zona'] ?? 0,
            'por_tipo' => $ut['por_tipo'] ?? [],
            'por_zona_top' => array_slice($ut['por_zona_top'] ?? [], 0, 5),
            'leitura' => $ut['leitura'] ?? null,
        ],
    ];
}

function demo_system_prompt(array $metricas, array $killsSample, string $timeNome): string
{
    $payload = demo_ai_payload_compacto($metricas, $killsSample, $timeNome);
    $elenco = $payload['elenco_oficial_foco'] ?? [];
    $mapa = $payload['mapa'] ?? '(mapa desconhecido)';
    $temTerritorio = !empty($payload['territorio']['disponivel']);
    $temMeta = !empty($payload['meta_pack']);
    $temFaseC = !empty($payload['fase_c']['disponivel']);

    $resumo = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $elencoTxt = !empty($elenco)
        ? implode(', ', $elenco)
        : '(nenhum jogador do cadastro identificado na demo)';

    $territorioRegra = $temTerritorio
        ? 'Há sinais de território (controle %, progressão, abertura TR). USE esses números. Não invente Mid/Long/%.'
        : 'Sem sinais de território nesta demo. NÃO invente % de Mid/Long/progressão — diga que falta zona/frames.';

    $metaRegra = $temMeta
        ? 'Há meta_pack do mapa (inclui man_advantage: o que FAZER/EVITAR em 5v4 e 4v5). Compare comportamento real vs meta. Não invente meta fora do pack.'
        : 'Sem meta_pack para este mapa. Não invente defaults de pro scene.';

    $faseCRegra = $temFaseC
        ? 'Há fase_c: util→zona, abertura por economia, comportamentos 5v4 (acelerou/congelou/plantou/overpeek) e 4v5 (regroupou/forcou/salvou/info_play). OBRIGATÓRIO confrontar 4v5/5v4 com meta_pack.man_advantage.'
        : 'Sem fase_c. Não invente % de comportamento pós-opening.';

    return <<<PROMPT
Você é o Analista Tático da MouseTrap Esports (CS2). Português do Brasil, tom coach: direto, crítico, sem enrolação.

FOCO OBRIGATÓRIO:
- Analise APENAS o time {$timeNome} e o elenco oficial: {$elencoTxt}
- Use SEMPRE nicknames oficiais do cadastro (mapa demo→oficial está no JSON).
- Mapa: {$mapa}

ADVERSÁRIOS:
- Cite no máx. 1–2 só para contextualizar punição.
- NÃO faça relatório do time adversário.

DADOS:
- Use SOMENTE o JSON. Se faltar, diga o que falta.
- {$territorioRegra}
- {$metaRegra}
- {$faseCRegra}

FORMATO OBRIGATÓRIO (markdown, seções nesta ordem — sem monólogo):

## Diagnóstico do mapa
Uma linha: **Status:** 🟢 ou 🟡 ou 🔴 + frase curta (ex.: "🟡 Mid ok, 4v5 forçando demais").
Depois 4 bullets:
- **Forte:** …
- **Problema principal:** …
- **2º problema:** …
- **Decisão-mãe p/ próximo treino:** …

## Controle & progressão
Por região relevante (com 1 número âncora cada). Se tiver territorio no JSON, cite controle_ataque_pct / taxa de progressão. Compare com meta_pack quando existir.

## Aberturas & previsibilidade
Padrão de 1ª zona TR (fase_c.abertura) + por economia (Pistol/Eco/Forçado/Armado). Se previsível, diga.

## Util → objetivo
Se fase_c.util disponível: onde a util caiu (smoke/flash/molly) vs zonas de execute. Util sem progressão = problema.

## Vantagem & desvantagem numérica (Fase C)
Blocos obrigatórios quando houver dados:
1) **5v4:** win_rate + comportamento dominante (acelerou/congelou/plantou/overpeek) vs meta_pack.man_advantage.5v4 (o que deveriam fazer no lado TR/CT).
2) **4v5 (desvantagem):** win_rate + comportamento dominante (forcou/regroupou/salvou/info_play) vs meta_pack.man_advantage.4v5 — deixe explícito o GAP: o que fizeram vs o que o meta manda.
3) Economia/pistol se ajudar.

## Por jogador (elenco)
Um bloco curto por nick oficial: forte / fraco / opening / fundo. Sem inventar.

## Ajustes para o próximo treino
5 a 7 itens ENCORPADOS, neste formato exato por item:
1. **O quê:** …
   **Por quê:** … (cite 1 número ou sinal do JSON — preferir fase_c / território)
   **Drill:** … (exercício concreto de 5–15 min; pode adaptar treino_templates / man_advantage do meta_pack)

Regras de tom:
- Cada seção: parágrafos curtos + bullets. Sem enrolação.
- 1 número âncora por bloco sempre que o JSON tiver.
- Chat futuro: sempre perspectiva MouseTrap / elenco oficial.

Dados da partida (JSON):
{$resumo}
PROMPT;
}

function demo_gerar_relatorio_automatico(array $metricas, array $kills, string $timeNome): string
{
    $messages = [
        ['role' => 'system', 'content' => demo_system_prompt($metricas, $kills, $timeNome)],
        ['role' => 'user', 'content' =>
            'Gere o relatório tático no formato obrigatório (Diagnóstico com 🟢/🟡/🔴 + seções + 5–7 ajustes encorpados). ' .
            'Foque só no elenco oficial da MouseTrap. Cite adversário só se explicar punição.'],
    ];
    return demo_openai_chat($messages, 0.35);
}

function demo_responder_pergunta(array $metricas, array $kills, string $timeNome, array $historicoChat, string $pergunta): string
{
    $messages = [
        ['role' => 'system', 'content' => demo_system_prompt($metricas, $kills, $timeNome)],
    ];
    foreach ($historicoChat as $msg) {
        $role = $msg['papel'] === 'assistant' ? 'assistant' : 'user';
        if ($msg['papel'] === 'system') {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => $msg['mensagem']];
    }
    $messages[] = ['role' => 'user', 'content' => $pergunta];
    return demo_openai_chat($messages, 0.3);
}

/**
 * Renderiza markdown leve do relatório (Fase A) em HTML seguro.
 */
function demo_formatar_relatorio_html(string $texto): string
{
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $lines = explode("\n", $texto);
    $html = [];
    $inList = false;

    $closeList = static function () use (&$html, &$inList): void {
        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }
    };

    $inline = static function (string $s): string {
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '<em>$1</em>', $s) ?? $s;
        // Status chips
        $s = str_replace(
            ['🟢', '🟡', '🔴'],
            [
                '<span class="demo-diag demo-diag--ok">🟢</span>',
                '<span class="demo-diag demo-diag--warn">🟡</span>',
                '<span class="demo-diag demo-diag--bad">🔴</span>',
            ],
            $s
        );
        return $s;
    };

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            $closeList();
            continue;
        }
        if (preg_match('/^##\s+(.+)$/u', $trim, $m)) {
            $closeList();
            $html[] = '<h3 class="demo-report-h">' . $inline($m[1]) . '</h3>';
            continue;
        }
        if (preg_match('/^###\s+(.+)$/u', $trim, $m)) {
            $closeList();
            $html[] = '<h4 class="demo-report-h4">' . $inline($m[1]) . '</h4>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/u', $trim, $m) || preg_match('/^\d+\.\s+(.+)$/u', $trim, $m)) {
            if (!$inList) {
                $html[] = '<ul class="demo-report-list">';
                $inList = true;
            }
            $html[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        $closeList();
        $html[] = '<p class="demo-report-p">' . $inline($trim) . '</p>';
    }
    $closeList();

    return implode("\n", $html);
}

/**
 * Envia .dem ao worker Node (cs2parser).
 * @return array payload parseado
 */
function demo_chamar_parser_node(string $caminhoDem): array
{
    $base = rtrim(get_setting('demo_analyzer_url', 'http://127.0.0.1:5055'), '/');
    $secret = get_setting('demo_analyzer_secret', 'mousetrap-demo-secret');

    if (!is_file($caminhoDem)) {
        throw new RuntimeException('Arquivo .dem não encontrado no servidor.');
    }

    $cfile = new CURLFile($caminhoDem, 'application/octet-stream', basename($caminhoDem));
    $ch = curl_init($base . '/parse');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-Demo-Secret: ' . $secret],
        CURLOPT_POSTFIELDS => ['demo' => $cfile],
        CURLOPT_TIMEOUT => 600,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException(
            'Não foi possível falar com o Demo Analyzer Node em ' . $base . '. ' .
            'Suba o serviço (pasta demo-analyzer) ou importe um JSON. Detalhe: ' . $err
        );
    }
    $json = json_decode($raw, true);
    if ($code >= 400 || empty($json['ok'])) {
        throw new RuntimeException($json['error'] ?? ('Parser HTTP ' . $code . ': ' . substr($raw, 0, 300)));
    }
    return $json['data'];
}
