<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assembleia - Sistema de Condomínio</title>
    <link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body class="morador-body">
    <header class="morador-header">
        <div class="container">
            <div class="header-content">
                <a href="<?= base_url('?route=assembleia/index') ?>" class="logo">
                    <span class="logo-icon">🏛️</span>
                    <span class="logo-text">Minha Assembleia</span>
                </a>
                <nav class="nav-main">
                    <a href="<?= base_url('?route=assembleia/index') ?>" class="nav-link">Minhas Assembleias</a>
                </nav>
                <div class="user-area">
                    <span class="user-name">Olá, <?= sanitize($_SESSION['usuario_nome'] ?? 'Morador') ?></span>
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
