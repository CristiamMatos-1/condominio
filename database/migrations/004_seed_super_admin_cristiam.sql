-- =====================================================================
-- MIGRATION 004: Seed Super Admin — Cristiam Matos (CPF 922.633.991-00)
-- =====================================================================
-- Idempotente: faz UPDATE se usuario existe, INSERT se nao existe.
-- Nao executa password_hash via SQL (evita variacoes de algoritmo/OPTION
-- entre versoes MySQL). Em vez disso usa HASH pre-calculado com PHP
-- password_hash(PASSWORD_DEFAULT, bcrypt cost 12) — 100% compativel com
-- UsuarioModel.verificaSenha() (password_verify).
-- =====================================================================

-- (1) Garante que coluna "perfil" ENUM exista (caso migration 003 nao
-- tenha sido rodada ainda; evita erro de coluna inexistente).
SET @col_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'usuarios'
      AND COLUMN_NAME  = 'perfil'
);
SET @sql_add_perfil = IF(@col_existe = 0,
    "ALTER TABLE usuarios
        ADD COLUMN perfil ENUM('super_admin','admin_condominio','morador')
        NOT NULL DEFAULT 'morador' AFTER tipo",
    "SELECT 1"
);
PREPARE stmt_add_perfil FROM @sql_add_perfil; EXECUTE stmt_add_perfil; DEALLOCATE PREPARE stmt_add_perfil;

-- (2) Garante que coluna condominio_id exista (caso instalacao seja muito
-- antiga; normalmente criada em install.php passo 3 e migration 002).
SET @col_cond_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'usuarios'
      AND COLUMN_NAME  = 'condominio_id'
);
SET @sql_add_cond = IF(@col_cond_existe = 0,
    "ALTER TABLE usuarios
        ADD COLUMN condominio_id INT UNSIGNED DEFAULT NULL AFTER tipo",
    "SELECT 1"
);
PREPARE stmt_add_cond FROM @sql_add_cond; EXECUTE stmt_add_cond; DEALLOCATE PREPARE stmt_add_cond;

-- (3) ====== CRIA / ATUALIZA SUPER ADMIN Cristiam Matos ======
-- Hash calculado com PHP password_hash("!010775@He#", PASSWORD_DEFAULT).
SET @SUPER_ADMIN_CPF_RAW = '92263399100';
SET @SUPER_ADMIN_CPF_FMT = '922.633.991-00';
SET @SUPER_ADMIN_NOME    = 'Cristiam Matos';
SET @SUPER_ADMIN_EMAIL   = 'cristiam@coninfoms.com.br';
SET @SUPER_ADMIN_SENHA   = '$2y$12$viuQSinjD7H/AgTNd2V9petyPdj8zIDGE3IEeICjwFWb5zdf6uALq';

SET @existe_id = (
    SELECT id FROM usuarios
    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = @SUPER_ADMIN_CPF_RAW
    LIMIT 1
);

SET @sql_upd = IF(@existe_id IS NOT NULL,
    CONCAT(
        "UPDATE usuarios SET ",
        "  nome            = ", QUOTE(@SUPER_ADMIN_NOME), ",",
        "  email           = ", QUOTE(@SUPER_ADMIN_EMAIL), ",",
        "  senha           = ", QUOTE(@SUPER_ADMIN_SENHA), ",",
        "  tipo            = 'admin',",
        "  perfil          = 'super_admin',",
        "  ativo           = 1,",
        "  condominio_id   = NULL ",
        "WHERE id = ", @existe_id
    ),
    CONCAT(
        "INSERT INTO usuarios (nome,cpf,email,senha,tipo,perfil,ativo,condominio_id,created_at) VALUES (",
        QUOTE(@SUPER_ADMIN_NOME), ",",
        QUOTE(@SUPER_ADMIN_CPF_FMT), ",",
        QUOTE(@SUPER_ADMIN_EMAIL), ",",
        QUOTE(@SUPER_ADMIN_SENHA), ",",
        "'admin',",
        "'super_admin',",
        "1,",
        "NULL,",
        "NOW())"
    )
);
PREPARE stmt_seed FROM @sql_upd; EXECUTE stmt_seed; DEALLOCATE PREPARE stmt_seed;

-- (4) Atualiza tambem o CPF legado 000.000.000-00 para "super_admin" (se
-- existe em alguma instalacao antiga) e atribui a mesma senha, mantendo
-- retrocompatibilidade.
SET @legacy_id = (
    SELECT id FROM usuarios
    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = '00000000000'
    LIMIT 1
);
SET @sql_legacy_upd = IF(@legacy_id IS NOT NULL,
    CONCAT(
        "UPDATE usuarios SET ",
        "  tipo            = 'admin',",
        "  perfil          = 'super_admin',",
        "  senha           = ", QUOTE(@SUPER_ADMIN_SENHA), ",",
        "  ativo           = 1,",
        "  condominio_id   = NULL ",
        "WHERE id = ", @legacy_id
    ),
    "SELECT 1"
);
PREPARE stmt_legacy FROM @sql_legacy_upd; EXECUTE stmt_legacy; DEALLOCATE PREPARE stmt_legacy;
