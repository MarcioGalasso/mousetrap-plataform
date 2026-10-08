-- Campo separado: site (ativo) vs análise de demos (analisar_na_demo)
-- + nome do adversário nas análises

ALTER TABLE jogadores
  ADD COLUMN analisar_na_demo TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = incluir na análise de demos' AFTER ativo;

UPDATE jogadores SET analisar_na_demo = 1 WHERE ativo = 1;

ALTER TABLE demos_analises
  ADD COLUMN time_adversario VARCHAR(100) NULL
  COMMENT 'Nome do time adversário (HUD / relatório)' AFTER time_nosso;
