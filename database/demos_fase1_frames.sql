-- FASE 1: posições por segundo para calor / fundo / view 2D futura
-- Rode no phpMyAdmin se a tabela demos_analises já existir

ALTER TABLE demos_analises
  ADD COLUMN frames_json LONGTEXT NULL
  COMMENT 'FASE 1: posições por segundo (calor / view 2D)'
  AFTER metricas_json;
