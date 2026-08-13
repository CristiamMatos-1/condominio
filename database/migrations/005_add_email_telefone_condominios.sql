-- ============================================================
-- Migration 005 — adiciona colunas email e telefone na tabela condominios
--
-- Motivo: na refatoração de UX do CRUD de condomínios (Super Admin SaaS),
-- a view condominio_form.php e o CondominioModel passaram a aceitar/salvar
-- campos de contato do condomínio (e-mail e telefone). A migration 001
-- não previa estas colunas, causando PDOException:
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email'
--
-- Segurança: usa escrita segura (copiar coluna antiga para nova,
-- e informa que NULLs são permitidos — condomínios antigos continuam
-- válidos sem contato cadastrado).
--
-- Execução manual (cPanel / phpMyAdmin): copie TODO este arquivo
-- para a aba SQL do seu banco e clique em Executar.
-- ============================================================

START TRANSACTION;

-- 1) E-mail de contato do condomínio (ex.: secretaria@condominio.com.br)
SET @ddl = (SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `condominios` ADD COLUMN `email` VARCHAR(150) NULL DEFAULT NULL AFTER `cep`',
    'SELECT ''COLUNA email JA EXISTE — pulando.'' AS msg'
) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'condominios'
      AND COLUMN_NAME  = 'email');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Telefone de contato do condomínio (com DDD)
SET @ddl2 = (SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `condominios` ADD COLUMN `telefone` VARCHAR(20) NULL DEFAULT NULL AFTER `email`',
    'SELECT ''COLUNA telefone JA EXISTE — pulando.'' AS msg'
) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'condominios'
      AND COLUMN_NAME  = 'telefone');
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

COMMIT;
