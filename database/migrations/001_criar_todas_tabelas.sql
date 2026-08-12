-- ============================================================
-- SISTEMA DE ELEIÇÃO E GESTÃO DE ASSEMBLEIAS PARA CONDOMÍNIOS
-- Projetado pelo Eng. de Software Cristiam Matos
-- Migration: Criação de todas as tabelas
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

-- --------------------------------------------------------
-- TABELA: condominios
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `condominios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NOT NULL,
  `cnpj` VARCHAR(18) DEFAULT NULL,
  `endereco` VARCHAR(255) DEFAULT NULL,
  `cidade` VARCHAR(100) DEFAULT NULL,
  `estado` VARCHAR(2) DEFAULT NULL,
  `cep` VARCHAR(9) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_condominios_cnpj` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: usuarios
-- Tipos: admin = Administrador do Sistema, morador = Condômino
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NOT NULL,
  `cpf` VARCHAR(14) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `telefone` VARCHAR(20) DEFAULT NULL,
  `tipo` ENUM('admin','morador') NOT NULL DEFAULT 'morador',
  `senha` VARCHAR(255) DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_usuarios_cpf` (`cpf`),
  KEY `idx_usuarios_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: unidades
-- Vincula lote/casa ao condomínio e ao dono (usuário morador)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `unidades` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `condominio_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `lote` VARCHAR(20) NOT NULL,
  `casa` VARCHAR(20) NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unidades_condominio_lote_casa` (`condominio_id`,`lote`,`casa`),
  KEY `idx_unidades_usuario` (`usuario_id`),
  KEY `idx_unidades_condominio` (`condominio_id`),
  CONSTRAINT `fk_unidades_condominio` FOREIGN KEY (`condominio_id`) REFERENCES `condominios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_unidades_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: procuracoes
-- Um usuário (representante) pode representar várias unidades
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `procuracoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unidade_id` INT UNSIGNED NOT NULL,
  `representante_id` INT UNSIGNED NOT NULL,
  `num_documento` VARCHAR(100) NOT NULL,
  `data_outorgacao` DATE DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_procuracoes_unidade_representante` (`unidade_id`,`representante_id`),
  KEY `idx_procuracoes_representante` (`representante_id`),
  KEY `idx_procuracoes_unidade` (`unidade_id`),
  CONSTRAINT `fk_procuracoes_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_procuracoes_representante` FOREIGN KEY (`representante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: assembleias
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assembleias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `condominio_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `tipo` ENUM('Ordinária','Extraordinária') NOT NULL,
  `data_assembleia` DATE NOT NULL,
  `horario` TIME NOT NULL,
  `status` ENUM('Aberta','Fechada') NOT NULL DEFAULT 'Fechada',
  `observacoes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assembleias_condominio` (`condominio_id`),
  KEY `idx_assembleias_status` (`status`),
  KEY `idx_assembleias_data` (`data_assembleia`),
  CONSTRAINT `fk_assembleias_condominio` FOREIGN KEY (`condominio_id`) REFERENCES `condominios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: presencas_assembleia
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `presencas_assembleia` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assembleia_id` INT UNSIGNED NOT NULL,
  `unidade_id` INT UNSIGNED NOT NULL,
  `usuario_presente_id` INT UNSIGNED NOT NULL,
  `via_procuracao` TINYINT(1) NOT NULL DEFAULT 0,
  `procuracao_id` INT UNSIGNED DEFAULT NULL,
  `data_checkin` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_presencas_assembleia_unidade` (`assembleia_id`,`unidade_id`),
  KEY `idx_presencas_usuario` (`usuario_presente_id`),
  KEY `idx_presencas_unidade` (`unidade_id`),
  CONSTRAINT `fk_presencas_assembleia` FOREIGN KEY (`assembleia_id`) REFERENCES `assembleias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presencas_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presencas_usuario` FOREIGN KEY (`usuario_presente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presencas_procuracao` FOREIGN KEY (`procuracao_id`) REFERENCES `procuracoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: pautas (Matérias para votação Sim/Não)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pautas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assembleia_id` INT UNSIGNED NOT NULL,
  `ordem` INT UNSIGNED NOT NULL DEFAULT 1,
  `titulo` VARCHAR(200) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `status` ENUM('Pendente','Em votação','Aprovada','Rejeitada') NOT NULL DEFAULT 'Pendente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pautas_assembleia` (`assembleia_id`),
  CONSTRAINT `fk_pautas_assembleia` FOREIGN KEY (`assembleia_id`) REFERENCES `assembleias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: votos_pautas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `votos_pautas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pauta_id` INT UNSIGNED NOT NULL,
  `unidade_id` INT UNSIGNED NOT NULL,
  `usuario_votante_id` INT UNSIGNED NOT NULL,
  `voto` ENUM('Sim','Não') NOT NULL,
  `via_procuracao` TINYINT(1) NOT NULL DEFAULT 0,
  `procuracao_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_votos_pautas_unidade` (`pauta_id`,`unidade_id`),
  KEY `idx_votos_pautas_usuario` (`usuario_votante_id`),
  CONSTRAINT `fk_votos_pauta` FOREIGN KEY (`pauta_id`) REFERENCES `pautas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votos_pautas_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votos_pautas_usuario` FOREIGN KEY (`usuario_votante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: chapas (Eleições para diretoria)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chapas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assembleia_id` INT UNSIGNED NOT NULL,
  `nome_chapa` VARCHAR(200) NOT NULL,
  `integrantes` TEXT DEFAULT NULL,
  `ordem` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chapas_assembleia` (`assembleia_id`),
  CONSTRAINT `fk_chapas_assembleia` FOREIGN KEY (`assembleia_id`) REFERENCES `assembleias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABELA: votos_chapas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `votos_chapas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chapa_id` INT UNSIGNED NOT NULL,
  `unidade_id` INT UNSIGNED NOT NULL,
  `usuario_votante_id` INT UNSIGNED NOT NULL,
  `via_procuracao` TINYINT(1) NOT NULL DEFAULT 0,
  `procuracao_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_votos_chapas_unidade` (`chapa_id`,`unidade_id`),
  KEY `idx_votos_chapas_usuario` (`usuario_votante_id`),
  CONSTRAINT `fk_votos_chapa` FOREIGN KEY (`chapa_id`) REFERENCES `chapas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votos_chapas_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votos_chapas_usuario` FOREIGN KEY (`usuario_votante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DADOS INICIAIS (Seed)
-- ============================================================

-- Usuário Admin padrão (senha: admin123 - gerada com password_hash)
INSERT INTO `usuarios` (`nome`, `cpf`, `email`, `tipo`, `senha`, `ativo`) VALUES
('Administrador Sistema', '000.000.000-00', 'admin@sistema.com', 'admin', '$2y$12$ly4LYwk83s5F1xcaRhuPreVlEf30mxY94P68LsiySnk4cpZZgTBQa', 1);

-- Condomínio de exemplo
INSERT INTO `condominios` (`nome`, `cnpj`, `endereco`, `cidade`, `estado`, `cep`) VALUES
('Residencial Jardins', '00.000.000/0001-00', 'Rua das Flores, 100', 'São Paulo', 'SP', '01001-000');

COMMIT;
