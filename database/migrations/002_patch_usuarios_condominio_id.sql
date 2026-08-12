-- ============================================================
-- MIGRATION 002: PATCH NA TABELA usuarios
-- CENARIO DE USO: quando a migration 001 ja foi rodada
-- (bancos ja criados ANTES desta data) e precisa incluir:
--   1. Coluna condominio_id na tabela usuarios
--   2. Foreign key para condominios
-- ============================================================
-- Use no cPanel -> phpMyAdmin -> SQL -> Execute

-- 1) Cria coluna condominio_id se nao existir
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'condominio_id');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN condominio_id INT UNSIGNED DEFAULT NULL AFTER tipo',
    'SELECT ''Coluna condominio_id ja existe, pulando.'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Cria indice se nao existir
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_condominio');
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE usuarios ADD KEY idx_usuarios_condominio (condominio_id)',
    'SELECT ''Indice ja existe, pulando.'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Cria foreign key se nao existir
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND CONSTRAINT_NAME = 'fk_usuarios_condominio' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_condominio
        FOREIGN KEY (condominio_id) REFERENCES condominios(id)
        ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''Foreign key ja existe, pulando.'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
