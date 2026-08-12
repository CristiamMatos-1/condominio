-- ============================================================
-- MIGRATION 003 — REFATORAÇÃO MULTI-TENANT + RBAC
-- Projetado pelo Eng. de Software Cristiam Matos
-- Idempotente: roda quantas vezes quiser sem erro (não duplica colunas/índices/UK).
-- ============================================================

DELIMITER //

DROP PROCEDURE IF EXISTS condominio_migrate_003 //
CREATE PROCEDURE condominio_migrate_003()
BEGIN
    DECLARE v INT;

    -- --------------------------------------------------------
    -- 1) EXPANDIR usuarios.tipo ENUM (hoje 'admin'/'morador')
    --    P/ 3 níveis: super_admin | admin_condominio | morador
    -- --------------------------------------------------------
    SET @col_def = NULL;
    SELECT COLUMN_TYPE INTO @col_def
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'usuarios'
       AND COLUMN_NAME  = 'tipo';

    IF (@col_def IS NOT NULL AND @col_def <> "enum('super_admin','admin_condominio','morador')") THEN
        SET @sql = "ALTER TABLE usuarios MODIFY COLUMN tipo
                    ENUM('super_admin','admin_condominio','morador')
                    NOT NULL DEFAULT 'morador'
                    COMMENT 'Legado: admin = super_admin agora'";
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;

    -- MIGRA DADO: todo usuario que era 'admin' hoje vira 'super_admin'
    UPDATE usuarios SET tipo = 'super_admin' WHERE tipo = 'admin';

    -- --------------------------------------------------------
    -- 2) ADICIONAR coluna perfil (alias, nao dependemos mais de renomeacao tipo)
    -- --------------------------------------------------------
    SELECT COUNT(*) INTO v
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'usuarios'
       AND COLUMN_NAME  = 'perfil';
    IF (v = 0) THEN
        SET @sql = "ALTER TABLE usuarios ADD COLUMN perfil
                    ENUM('super_admin','admin_condominio','morador')
                    NOT NULL DEFAULT 'morador' AFTER tipo";
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
        -- Sincroniza inicialmente com 'tipo'
        UPDATE usuarios SET perfil = CASE WHEN tipo = 'super_admin' THEN 'super_admin'
                                          WHEN tipo = 'admin_condominio' THEN 'admin_condominio'
                                          ELSE 'morador' END;
    END IF;

    -- Garante: usuario CPF 000.000.000-00 = super_admin sempre
    UPDATE usuarios SET perfil='super_admin', tipo='super_admin', ativo=1
     WHERE REPLACE(REPLACE(cpf,'.',''),'-','') = '00000000000';

    -- --------------------------------------------------------
    -- 3) ADICIONAR colunas condominio_id NOT NULL default em ADMIN do condominio
    --    (garante: admin_condominio nao nasce sem condominio)
    -- --------------------------------------------------------
    -- (coluna ja existe, checagem apenas p/ confirmar)
    SELECT COUNT(*) INTO v
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='condominio_id';
    IF (v = 0) THEN
        SET @sql = "ALTER TABLE usuarios ADD COLUMN condominio_id INT UNSIGNED DEFAULT NULL AFTER tipo";
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;

    -- Índice novo tenant (perf com 100+ condominios):
    SELECT COUNT(*) INTO v
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='usuarios'
       AND INDEX_NAME='idx_usuarios_condominio_ativo_perfil';
    IF (v = 0) THEN
        SET @sql = "CREATE INDEX idx_usuarios_condominio_ativo_perfil
                    ON usuarios(condominio_id, ativo, perfil)";
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;

    -- --------------------------------------------------------
    -- 4) unidades.usuario_id PODE SER NULL agora (morador sem dono formal =
    --    admin pode dar presença manual depois, e unidade existe no condomínio)
    -- --------------------------------------------------------
    SET @col_def = NULL;
    SELECT COLUMN_TYPE INTO @col_def
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='unidades' AND COLUMN_NAME='usuario_id';

    IF (@col_def IS NOT NULL AND @col_def = "int unsigned") THEN
        -- Verifica se é NOT NULL (IS_NULLABLE = 'NO')
        SELECT IS_NULLABLE INTO @is_null
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='unidades' AND COLUMN_NAME='usuario_id';
        IF (@is_null = 'NO') THEN
            SET @sql = "ALTER TABLE unidades
                        DROP FOREIGN KEY fk_unidades_usuario,
                        MODIFY usuario_id INT UNSIGNED DEFAULT NULL,
                        ADD CONSTRAINT fk_unidades_usuario
                            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                            ON DELETE SET NULL ON UPDATE CASCADE";
            PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
        END IF;
    END IF;

    -- Índice novo performance:
    SELECT COUNT(*) INTO v
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='unidades'
       AND INDEX_NAME='idx_unidades_condominio_ativo';
    IF (v = 0) THEN
        SET @sql = "CREATE INDEX idx_unidades_condominio_ativo
                    ON unidades(condominio_id, ativo)";
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;

    -- --------------------------------------------------------
    -- 5) assembleias.status ENUM expandida:
    --    'Aberta' | 'Em Andamento' | 'Encerrada'
    --    (mantemos 'Fechada' como alias compat; novo padrao Encerrada)
    -- --------------------------------------------------------
    SET @col_def = NULL;
    SELECT COLUMN_TYPE INTO @col_def
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='assembleias' AND COLUMN_NAME='status';

    IF (@col_def IS NOT NULL AND @col_def <> "enum('Aberta','Em Andamento','Encerrada','Fechada')") THEN
        SET @sql = "ALTER TABLE assembleias MODIFY status
                    ENUM('Aberta','Em Andamento','Encerrada','Fechada')
                    NOT NULL DEFAULT 'Fechada'";
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;

    -- Migra DADO: assembleias antigas status='Fechada' agora = 'Encerrada'
    UPDATE assembleias SET status='Encerrada' WHERE status='Fechada';

    -- Índice tenant:
    SELECT COUNT(*) INTO v
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='assembleias'
       AND INDEX_NAME='idx_assembleias_condominio_status';
    IF (v = 0) THEN
        SET @sql = "CREATE INDEX idx_assembleias_condominio_status
                    ON assembleias(condominio_id, status)";
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;

    -- --------------------------------------------------------
    -- 6) Trava PROCURAÇÕES: 1 unidade só pode ter 1 ATIVA (nao importa
    --    representante) — evita 2 pessoas receberem procuração e votarem.
    --    Implementamos via TRIGGER (MySQL 5.x nao suporta unique parcial).
    -- --------------------------------------------------------
    DROP TRIGGER IF EXISTS trg_procuracoes_before_insert_check_ativa;
    SET @trg = "CREATE TRIGGER trg_procuracoes_before_insert_check_ativa
                BEFORE INSERT ON procuracoes
                FOR EACH ROW
                BEGIN
                    IF NEW.ativo = 1 AND EXISTS(
                        SELECT 1 FROM procuracoes
                         WHERE unidade_id = NEW.unidade_id AND ativo = 1
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Procuração ATIVA já cadastrada para esta unidade. Desative a anterior antes de criar uma nova.';
                    END IF;
                END";
    PREPARE st FROM @trg; EXECUTE st; DEALLOCATE PREPARE st;

    DROP TRIGGER IF EXISTS trg_procuracoes_before_update_check_ativa;
    SET @trg = "CREATE TRIGGER trg_procuracoes_before_update_check_ativa
                BEFORE UPDATE ON procuracoes
                FOR EACH ROW
                BEGIN
                    IF NEW.ativo = 1 AND OLD.ativo <> 1 AND EXISTS(
                        SELECT 1 FROM procuracoes
                         WHERE unidade_id = NEW.unidade_id AND ativo = 1
                           AND id <> OLD.id
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Procuração ATIVA já cadastrada para esta unidade. Desative a anterior antes de reativar.';
                    END IF;
                END";
    PREPARE st FROM @trg; EXECUTE st; DEALLOCATE PREPARE st;

    -- --------------------------------------------------------
    -- 7) Audit: colunas criado_em/por nas procuracoes (ja tem created_at)
    --    (opcional) — sem mudanca se ja tiver.
    -- --------------------------------------------------------

END //
DELIMITER ;

CALL condominio_migrate_003();
DROP PROCEDURE IF EXISTS condominio_migrate_003;
