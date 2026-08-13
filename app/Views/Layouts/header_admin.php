<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Sistema de Assembleias</title>
    <link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body class="admin-body">
    <?php if (defined('CONDOMINIO_DEBUG') && CONDOMINIO_DEBUG === 1 && !empty($_SESSION['usuario_id'])): ?>
        <div style="background:#FFF7ED;border:1px solid #FDBA74;color:#92400E;padding:8px 14px;font-size:12px;font-family:ui-monospace,monospace;">
            🛠️ DEBUG (RBAC) · Sessão ID=<?= (int)$_SESSION['usuario_id'] ?> ·
            usuario_perfil = <b><?= htmlspecialchars((string)($_SESSION['usuario_perfil'] ?? 'NULL')) ?></b> ·
            usuario_tipo = <b><?= htmlspecialchars((string)($_SESSION['usuario_tipo'] ?? 'NULL')) ?></b> ·
            condominio_id = <b><?= htmlspecialchars(var_export($_SESSION['condominio_id'] ?? null, true)) ?></b> ·
            perfil_raw_db = <b><?= htmlspecialchars((string)($_SESSION['usuario_perfil_raw_db'] ?? 'NULL')) ?></b> ·
            tipo_raw_db = <b><?= htmlspecialchars((string)($_SESSION['usuario_tipo_raw_db'] ?? 'NULL')) ?></b>
        </div>
    <?php endif; ?>
    <header class="admin-header">
        <div class="container">
            <div class="header-content">
                <a href="<?= base_url('?route=admin/index') ?>" class="logo">
                    <span class="logo-icon">🏢</span>
                    <span class="logo-text">Condomínio Admin</span>
                </a>
                <nav class="nav-main">
                    <a href="<?= base_url('?route=admin/index') ?>" class="nav-link">Dashboard</a>
                    <a href="<?= base_url('?route=admin/condominios') ?>" class="nav-link">Condomínios</a>
                    <a href="<?= base_url('?route=admin/usuarios') ?>" class="nav-link">Usuários</a>
                    <a href="<?= base_url('?route=admin/unidades') ?>" class="nav-link">Unidades</a>
                    <a href="<?= base_url('?route=admin/procuracoes') ?>" class="nav-link">Procurações</a>
                    <a href="<?= base_url('?route=admin/assembleias') ?>" class="nav-link">Assembleias</a>
                </nav>
                <div class="user-area">
                    <span class="user-name">Olá, <?= $_SESSION['usuario_nome'] ?? 'Admin' ?></span>
                    <a href="<?= base_url('?route=auth/logout') ?>" class="btn btn-logout btn-sm">Sair</a>
                </div>
            </div>
        </div>
    </header>
    <main class="main-content">
        <div class="container">
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= sanitize($flash['message']) ?>
                </div>
            <?php endif; ?>
