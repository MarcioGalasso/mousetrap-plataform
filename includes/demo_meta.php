<?php
/**
 * Meta packs curados por mapa (Fase A/C).
 * Conhecimento estático — NÃO scraping. Atualizar a cada patch/mês.
 */

/**
 * @return array|null
 */
function demo_meta_pack_for_map(?string $mapa): ?array
{
    if ($mapa === null || $mapa === '') {
        return null;
    }
    $key = strtolower(trim($mapa));
    $key = str_replace([' ', '-'], '', $key);
    if (strpos($key, 'dust2') !== false) {
        return demo_meta_pack_dust2();
    }
    if (strpos($key, 'mirage') !== false) {
        return demo_meta_pack_mirage();
    }
    if (strpos($key, 'nuke') !== false) {
        return demo_meta_pack_nuke();
    }
    if (strpos($key, 'ancient') !== false) {
        return demo_meta_pack_ancient();
    }
    if (strpos($key, 'anubis') !== false) {
        return demo_meta_pack_anubis();
    }
    if (strpos($key, 'cache') !== false) {
        return demo_meta_pack_cache();
    }
    if (strpos($key, 'inferno') !== false) {
        return demo_meta_pack_inferno();
    }
    if (strpos($key, 'overpass') !== false) {
        return demo_meta_pack_overpass();
    }
    return null;
}

/**
 * Bloco man_advantage genérico, com eixos do mapa injetados no texto.
 *
 * @param string[] $eixosEx TR eixos típicos (ex.: Mid, Apps, A Main)
 */
function demo_meta_man_advantage_padrao(array $eixosEx): array
{
    $eixos = implode('/', array_slice($eixosEx, 0, 3));
    return [
        '5v4' => [
            'TR' => [
                'fazer' => [
                    "Acelerar execute no eixo já aberto ({$eixos}) em ≤8–10s",
                    'Usar util para commit (smoke CT / flash deep), não resetar o round',
                    'Trade imediato: 2º e 3º jogador entram juntos',
                    'Priorizar plant — vantagem morre se congelar no Mid/controle',
                ],
                'evitar' => [
                    'Congelar no controle esperando “pick perfeito”',
                    'Solo peek em outro eixo depois da opening',
                    'Overpeek atrás de vantagem (doar o 5v4)',
                ],
            ],
            'CT' => [
                'fazer' => [
                    'Hold o ângulo da opening; não chase o frag',
                    'Jogar por info e crossfire; forçar o TR a commit util',
                    'Cair para site/âncora e negar plant',
                ],
                'evitar' => [
                    'Wide peek hunting second kill',
                    'Rotate cedo demais e abrir o site oposto',
                ],
            ],
        ],
        '4v5' => [
            'TR' => [
                'fazer' => [
                    'Regroup: juntar 3+ no mesmo eixo com util, ou save consciente',
                    'Info play (flash/smoke) sem doar segundo corpo',
                    'Se forçar, só com util + trade — nunca 1v1 isolado',
                    'Eco/Forçado: priorizar save de armas se round já perdido',
                ],
                'evitar' => [
                    'Forçar 1v1 no Mid/eixo logo após perder opening',
                    'Spread no mapa (4 solos em 4 zonas)',
                    'Plant desesperado sem util e sem trade',
                ],
            ],
            'CT' => [
                'fazer' => [
                    'Delay: cair para site, negar tempo, pedir rotate',
                    'Jogar por retake stack se plant veio cedo',
                    'Não retake 1–2 homens sozinho',
                ],
                'evitar' => [
                    'Peek agressivo Mid após already down',
                    'Over-rotate e deixar B/A aberto ao fake',
                ],
            ],
        ],
        'diagnostico_guia' => [
            '5v4 + congelou/overpeek = problema de conversão (meta: acelerar TR / hold CT)',
            '4v5 + forcou = problema de desvantagem (meta: regroup/save TR / delay CT)',
            '4v5 + salvou/info_play = alinhado ao meta se winrate baixo era esperado',
            'Compare comportamentos_pct do JSON com este bloco — diga gap vs meta',
        ],
    ];
}

function demo_meta_pack_dust2(): array
{
    return [
        'mapa' => 'de_dust2',
        'versao' => '2025-26-q1',
        'nota' => 'Use SOMENTE este pack como referência de meta. Se o time divergir, diga se parece escolha consciente ou erro.',
        't_side' => [
            'defaults' => [
                'Mid/Top Mid early = prioridade de informação e espaço',
                'Long = rota A clássica; precisa util + trade, não só peek solo',
                'Cat/Short = conversão de Mid controlado (não force sem Mid)',
                'B via Tunnels = commit com smoke/flash; Upper/Lower limpos antes do site',
            ],
            'progressao_esperada' => [
                'Top Mid → Cat/Short → A',
                'Long → A',
                'Top Mid → Tunnels Lower → Upper → B',
            ],
            'sinais_problema' => [
                'Presença em Mid sem progressão para Cat/Long/Tunnels',
                'Long peek sem segundo/terceiro e sem util',
                'Abertura sempre a mesma zona (>55% rounds) = previsível',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Mid + Long = eixos de informação',
                'Cat precisa coverage rápido se Mid cair',
                'Não over-rotate B cedo; confirmação de commit em Tunnels',
            ],
            'sinais_problema' => [
                'Perder Mid early e não recuperar Cat',
                'Long 1v1 isolado sem backup',
            ],
        ],
        'treino_templates' => [
            'Drill Mid control 90s (smoke Xbox + 2 peeks + hold)',
            'Long execute 5 rounds (flash deep + trade + plant A)',
            'Tunnels clear Upper→B com molotov + smoke CT',
            '4v5: delay + save (não force 1v1 Mid/Long)',
            '5v4 TR: hit site em ≤10s após opening',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Long', 'Cat', 'Tunnels']),
    ];
}

function demo_meta_pack_mirage(): array
{
    return [
        'mapa' => 'de_mirage',
        'versao' => '2025-26-q1',
        'nota' => 'Use SOMENTE este pack como referência de meta. Se o time divergir, diga se parece escolha consciente ou erro.',
        't_side' => [
            'defaults' => [
                'Top Mid / Underpass = info e espaço; Mid Control (Window/Connector) é o coração',
                'Catwalk = conversão Mid→B; Apps = rota B fechada com util',
                'A Main / Palace = prepare util antes de commit A',
                'Não force Connector sem Mid limpo',
            ],
            'progressao_esperada' => [
                'Top Mid → Mid Control → Catwalk → B',
                'Apps → B',
                'Top Mid / Mid Control → A Main → A',
            ],
            'sinais_problema' => [
                'Mid Control sem progressão Catwalk/A Main',
                'Apps solo sem trade',
                'Palace peek sem util + segundo',
                'Abertura >55% sempre o mesmo eixo',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Negar Mid Control / Window; Connector é âncora de rotate',
                'Catwalk fall-off rápido se Mid cair',
                'Apps stack vs mid-to-B comum',
            ],
            'sinais_problema' => [
                'Perder Mid e não recuperar Connector/Cat',
                'A Main 1v1 isolado',
            ],
        ],
        'treino_templates' => [
            'Mid Control 90s (smoke Window + Connector hold)',
            'Apps execute → B (flash bench + trade)',
            'Palace/A Main util → plant A',
            'Catwalk convert após Mid (5 rounds)',
            '5v4: hit site ≤10s; 4v5: regroup ou save',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Mid Control', 'Apps', 'A Main']),
    ];
}

function demo_meta_pack_nuke(): array
{
    return [
        'mapa' => 'de_nuke',
        'versao' => '2025-26-q1',
        'nota' => 'Nuke é vertical: Outside substitui “Mid”. Use SOMENTE este pack.',
        't_side' => [
            'defaults' => [
                'Outside / Yard = zona mais importante; smokes são obrigatórias para passar',
                'Garage / Red = Outside avançado; trava CT e abre Secret',
                'Lobby = hub antes de A (hut) ou Ramp→B',
                'Secret e Ramp = rotas B; não misturar sozinho',
            ],
            'progressao_esperada' => [
                'Outside → Garage → Secret → B',
                'Lobby → Ramp → B',
                'Lobby → A (Upper)',
            ],
            'sinais_problema' => [
                'Outside sem smoke/progressão (congelar Yard)',
                'Ramp peek solo após Lobby',
                'Secret sem Garage/Outside control',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Negar Outside early; Red/Garage é linha de defesa',
                'Ramp hold vs Lobby rush; Secret info antes de rotate B',
                'A Upper: hut/heaven crossfire',
            ],
            'sinais_problema' => [
                'Doar Outside free e over-rotate Ramp',
                'Retake B 1–2 sem stack',
            ],
        ],
        'treino_templates' => [
            'Outside smoke execute (5 rounds) → Garage',
            'Secret clear → B plant',
            'Lobby → Ramp trade drill',
            'Hut/A hold vs Lobby',
            '5v4 Outside: commit B/A rápido; 4v5: save Ramp/Outside',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Outside', 'Ramp', 'Secret']),
    ];
}

function demo_meta_pack_ancient(): array
{
    return [
        'mapa' => 'de_ancient',
        'versao' => '2025-26-q1',
        'nota' => 'Use SOMENTE este pack como referência de meta.',
        't_side' => [
            'defaults' => [
                'Mid Control / Top Mid = espaço + Xbox; Donut = Mid→A',
                'Cheetah / Cubby = Mid→B',
                'A Main e B Main / Ramp = eixos diretos com util',
            ],
            'progressao_esperada' => [
                'Mid → Donut → A',
                'A Main → A',
                'Mid → Cheetah → B',
                'B Main → B',
            ],
            'sinais_problema' => [
                'Mid sem Donut/Cheetah',
                'Donut freeze sem plant A',
                'B Main force sem util',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Negar Mid early; Donut e Cheetah são alarmas de rotate',
                'A/B Main holds com trade',
            ],
            'sinais_problema' => [
                'Perder Mid e não cobrir Donut',
                'Cheetah free → B plant fácil',
            ],
        ],
        'treino_templates' => [
            'Mid control → Donut hit A',
            'Cheetah convert → B',
            'A Main util execute',
            'B Ramp trade drill',
            '5v4/4v5: acelerar vs regroup',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Mid', 'Donut', 'B Main']),
    ];
}

function demo_meta_pack_anubis(): array
{
    return [
        'mapa' => 'de_anubis',
        'versao' => '2025-26-q1',
        'nota' => 'Use SOMENTE este pack como referência de meta.',
        't_side' => [
            'defaults' => [
                'Canal / Water = conexão T→Mid/B; Bridge = atalho Mid',
                'Mid Control corta rotações CT (Ebox)',
                'A Main = calçadão; B Main = gargalo pedra',
            ],
            'progressao_esperada' => [
                'Canal → Bridge → Mid',
                'Canal → B Main → B',
                'Mid → A Main → A',
            ],
            'sinais_problema' => [
                'Canal sem Bridge/B Main',
                'Mid sem progressão A Main',
                'B Main force sem util',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Negar Mid/Ebox; Canal info antes de rotate B',
                'A Main long hold; B Main stack vs canal-to-B',
            ],
            'sinais_problema' => [
                'Doar Canal free',
                'Over-rotate A e abrir B',
            ],
        ],
        'treino_templates' => [
            'Canal → Bridge Mid control',
            'B Main util execute',
            'A Main smoke/flash plant',
            'Mid deny CT rotate',
            '5v4 hit site; 4v5 regroup Canal',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Canal', 'Mid', 'A Main']),
    ];
}

function demo_meta_pack_cache(): array
{
    return [
        'mapa' => 'de_cache',
        'versao' => '2025-26-q1',
        'nota' => 'Use SOMENTE este pack como referência de meta.',
        't_side' => [
            'defaults' => [
                'Mid Control / Garage = disputa central (Window / White box)',
                'Highway = Mid→A rápido',
                'Vents = Mid→B surpresa; Checkers = chave do B',
                'A Main e B Halls = eixos diretos',
            ],
            'progressao_esperada' => [
                'Mid → Highway → A',
                'A Main → A',
                'Mid → Vents → Checkers → B',
                'B Halls → Checkers → B',
            ],
            'sinais_problema' => [
                'Mid sem Highway/Vents',
                'Checkers perdido = B quase perdido',
                'Vents solo sem trade',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Negar Mid/Garage; Checkers é âncora B',
                'Highway alarm → A rotate; Vents sound = B stack',
            ],
            'sinais_problema' => [
                'Doar Checkers',
                'Highway free plant A',
            ],
        ],
        'treino_templates' => [
            'Mid Garage control → Highway A',
            'Vents → Checkers → B plant',
            'B Halls clear Checkers',
            'A Main execute',
            '5v4/4v5 drills',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Mid', 'Highway', 'Checkers']),
    ];
}

function demo_meta_pack_inferno(): array
{
    return [
        'mapa' => 'de_inferno',
        'versao' => '2025-26-q1',
        'nota' => 'Use SOMENTE este pack como referência de meta.',
        't_side' => [
            'defaults' => [
                'Banana (Top/Car) = eixo B; domínio do carro é o fight early',
                'Main Mid (Boiler & Short) = hub; Second Mid = passagem pro Apps',
                'Apps / Balcony = rota A; não force Apps sem Mid/Second Mid',
            ],
            'progressao_esperada' => [
                'Banana → B',
                'Mid → Banana → B',
                'Mid → Second Mid → Apps → A',
                'Apps → A',
            ],
            'sinais_problema' => [
                'Banana freeze no Car sem plant B',
                'Apps solo sem trade / balcony',
                'Mid sem Second Mid nem Banana',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Negar Top Banana / Car; Mid hold Boiler-Short',
                'Apps alarm → A stack; Banana commit → B rotate',
            ],
            'sinais_problema' => [
                'Doar Car free',
                'Over-rotate B e Apps free plant A',
            ],
        ],
        'treino_templates' => [
            'Banana control até Car → B execute',
            'Mid → Second Mid → Apps A',
            'Apps balcony flash + trade',
            'Boiler/Short hold drill',
            '5v4 hit site; 4v5 regroup Banana/Apps',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Banana', 'Apps', 'Mid']),
    ];
}

function demo_meta_pack_overpass(): array
{
    return [
        'mapa' => 'de_overpass',
        'versao' => '2025-26-q1',
        'nota' => 'Use SOMENTE este pack como referência de meta.',
        't_side' => [
            'defaults' => [
                'Playground / Fountain = hub early TR',
                'Toilets = chave do A; A Long = fundo A via park',
                'Connector liga Mid/Toilets → Monster/B',
                'Monster / Short Pipe = rota B baixa',
            ],
            'progressao_esperada' => [
                'Playground → Toilets → A',
                'Playground → A Long → A',
                'Toilets → Connector → Monster → B',
                'Monster → B',
            ],
            'sinais_problema' => [
                'Toilets sem progressão A/Connector',
                'Monster solo sem util',
                'A Long freeze sem plant',
            ],
        ],
        'ct_side' => [
            'defaults' => [
                'Negar Toilets; Connector é alarme Mid→B',
                'Monster hold vs short pipe; A Long info antes de rotate',
            ],
            'sinais_problema' => [
                'Doar Toilets free',
                'Over-rotate A e Monster plant B',
            ],
        ],
        'treino_templates' => [
            'Playground → Toilets control → A',
            'A Long execute',
            'Connector → Monster → B',
            'Toilets deny CT',
            '5v4/4v5 drills',
        ],
        'man_advantage' => demo_meta_man_advantage_padrao(['Toilets', 'Monster', 'A Long']),
    ];
}

/**
 * Resumo compacto do território p/ o prompt (sem boxes de radar).
 *
 * @param array|null $territorio
 * @return array|null
 */
function demo_meta_territorio_resumo(?array $territorio): ?array
{
    if (!$territorio || empty($territorio['disponivel'])) {
        return $territorio ? [
            'disponivel' => false,
            'motivo' => $territorio['motivo'] ?? 'indisponivel',
        ] : null;
    }

    $zonas = [];
    foreach ($territorio['ranking_controle_ataque'] ?? [] as $z) {
        $zonas[] = [
            'zona' => $z['label'] ?? $z['id'],
            'controle_ataque_pct' => $z['controle_ataque_pct'] ?? 0,
            'presenca_pct' => $z['presenca_pct'] ?? 0,
            'player_seconds' => $z['player_seconds'] ?? 0,
        ];
    }

    $prog = [];
    foreach ($territorio['progressao'] ?? [] as $p) {
        if (($p['rounds_base'] ?? 0) < 1) {
            continue;
        }
        $prog[] = [
            'cadeia' => $p['label'],
            'taxa_pct' => $p['taxa_pct'],
            'base' => $p['rounds_base'],
            'ok' => $p['rounds_convertidos'],
        ];
    }

    $abertura = [];
    foreach (array_slice($territorio['abertura_tr'] ?? [], 0, 5) as $a) {
        $abertura[] = [
            'zona' => $a['label'] ?? $a['zona'],
            'pct' => $a['pct'] ?? 0,
            'rounds' => $a['rounds'] ?? 0,
        ];
    }

    return [
        'disponivel' => true,
        'rounds_tr' => $territorio['rounds_tr'] ?? null,
        'ranking_controle_ataque' => $zonas,
        'progressao' => $prog,
        'abertura_tr' => $abertura,
    ];
}
