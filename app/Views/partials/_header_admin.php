<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Sistema de Assembleias</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="admin-body">
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
