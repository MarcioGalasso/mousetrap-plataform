-- Análise de demos CS2 (MouseTrap)
-- Rode no phpMyAdmin uma vez

CREATE TABLE IF NOT EXISTS demos_analises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  arquivo_demo VARCHAR(255) DEFAULT NULL,
  mapa VARCHAR(100) DEFAULT NULL,
  time_nosso VARCHAR(100) DEFAULT 'MouseTrap',
  time_adversario VARCHAR(100) DEFAULT NULL COMMENT 'Nome do time adversário',
  jogadores_nossos TEXT COMMENT 'JSON array de nicks do nosso time',
  status ENUM('pendente','processando','pronto','erro') NOT NULL DEFAULT 'pendente',
  erro_msg TEXT,
  kills_json LONGTEXT COMMENT 'JSON bruto de kills',
  rounds_json LONGTEXT COMMENT 'JSON de rounds (vencedor etc)',
  metricas_json LONGTEXT COMMENT 'JSON das métricas calculadas',
  frames_json LONGTEXT COMMENT 'FASE 1: posições por segundo (calor / view 2D)',
  relatorio_ia LONGTEXT COMMENT 'Primeira análise automática da IA',
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

INSERT INTO configuracoes (chave, valor) VALUES
  ('openai_api_key', ''),
  ('openai_model', 'gpt-4o-mini'),
  ('demo_analyzer_url', 'http://127.0.0.1:5055'),
  ('demo_analyzer_secret', 'mousetrap-demo-secret')
ON DUPLICATE KEY UPDATE chave = chave;
