-- Banco de dados do site MouseTrap Esports
-- Compatível com MySQL 5.7+/8.0 (padrão Locaweb)

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configuracoes (
  chave VARCHAR(100) PRIMARY KEY,
  valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS noticias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  resumo TEXT,
  conteudo LONGTEXT,
  categoria VARCHAR(100) DEFAULT 'Geral',
  imagem_capa VARCHAR(255),
  status ENUM('publicado','rascunho') NOT NULL DEFAULT 'rascunho',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS noticias_galeria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  noticia_id INT NOT NULL,
  imagem VARCHAR(255) NOT NULL,
  ordem INT DEFAULT 0,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jogadores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_real VARCHAR(150),
  nickname VARCHAR(100) NOT NULL,
  funcao VARCHAR(100),
  foto VARCHAR(255),
  bio TEXT,
  instagram VARCHAR(255),
  twitter VARCHAR(255),
  twitch VARCHAR(255),
  ordem INT DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  analisar_na_demo TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = incluir na análise de demos',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  adversario VARCHAR(150) NOT NULL,
  adversario_logo VARCHAR(255),
  campeonato VARCHAR(150),
  data_hora DATETIME NOT NULL,
  status ENUM('agendada','finalizada') NOT NULL DEFAULT 'agendada',
  placar_mousetrap INT DEFAULT NULL,
  placar_adversario INT DEFAULT NULL,
  link_transmissao VARCHAR(255),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configurações padrão do site (editáveis pelo painel admin em /admin/configuracoes.php)
INSERT INTO configuracoes (chave, valor) VALUES
  ('hero_titulo', 'UMA ARMADILHA PARA GIGANTES'),
  ('hero_subtitulo', 'Foco. Disciplina. Evolução.'),
  ('stat_titulos', '0'),
  ('stat_fundacao', '2024'),
  ('livestream_tipo', 'nenhuma'), -- 'twitch', 'youtube' ou 'nenhuma'
  ('livestream_id', ''),          -- canal da Twitch ou ID do vídeo/canal do YouTube
  ('contato_email', 'contato@mousetrap.com.br'),
  ('contato_telefone', ''),
  ('contato_endereco', 'Rua Netuno, 123, Florianópolis-SC'),
  ('social_instagram', ''),
  ('social_twitter', ''),
  ('social_twitch', ''),
  ('social_youtube', ''),
  ('openai_api_key', ''),
  ('openai_model', 'gpt-4o-mini'),
  ('demo_analyzer_url', 'http://127.0.0.1:5055'),
  ('demo_analyzer_secret', 'mousetrap-demo-secret')
ON DUPLICATE KEY UPDATE chave = chave;

CREATE TABLE IF NOT EXISTS demos_analises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  arquivo_demo VARCHAR(255) DEFAULT NULL,
  mapa VARCHAR(100) DEFAULT NULL,
  time_nosso VARCHAR(100) DEFAULT 'MouseTrap',
  time_adversario VARCHAR(100) DEFAULT NULL,
  jogadores_nossos TEXT,
  status ENUM('pendente','processando','pronto','erro') NOT NULL DEFAULT 'pendente',
  erro_msg TEXT,
  kills_json LONGTEXT,
  rounds_json LONGTEXT,
  metricas_json LONGTEXT,
  relatorio_ia LONGTEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demos_chat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  demo_id INT NOT NULL,
  papel ENUM('user','assistant','system') NOT NULL DEFAULT 'user',
  mensagem LONGTEXT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (demo_id) REFERENCES demos_analises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nenhum usuário admin é criado aqui de propósito.
-- Após importar este arquivo, acesse /admin/setup.php pelo navegador
-- para criar o primeiro usuário e senha do painel de forma segura.
-- Esse arquivo se bloqueia automaticamente depois do primeiro uso.
