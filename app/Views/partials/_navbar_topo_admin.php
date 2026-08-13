<?php
/**
 * Partial: navbar_topo_admin.php
 * Barra de navegação superior responsiva com menu hamburguer em mobile (<768px)
 * Usada em todas páginas do admin em conjunto com _header_admin.php
 */
$isAdmin = rbac_is_admin();
$userNome = $_SESSION['usuario_nome'] ?? 'Visitante';
$userCpf  = formatCpf($_SESSION['usuario_cpf'] ?? '00000000000');
?>
<nav class="navbar-topo" aria-label="Navegação Principal">
  <div class="nav-inner">
    <a class="nav-brand" href="<?= base_url('?route=admin/dashboard') ?>">
      <div class="logo-circle" aria-hidden="true">C</div>
      <div>
        <strong class="brand-title">CONINFOMS</strong>
        <span class="brand-sub">Assembleias &amp; Condomínios</span>
      </div>
    </a>
    <button class="nav-toggle" aria-controls="nav-menu" aria-expanded="false"
      onclick="document.getElementById('nav-menu').classList.toggle('aberto');this.setAttribute('aria-expanded', document.getElementById('nav-menu').classList.contains('aberto'))">
      <span></span><span></span><span></span>
    </button>
    <ul id="nav-menu" class="nav-menu">
      <li><a href="<?= base_url('?route=admin/dashboard') ?>">Início</a></li>
      <?php if ($isAdmin): ?>
      <li class="has-dropdown">
        <a>Cadastros ▾</a>
        <ul class="dropdown">
          <li><a href="<?= base_url('?route=admin/condominios') ?>">Condomínios</a></li>
          <li><a href="<?= base_url('?route=admin/usuarios') ?>">Usuários</a></li>
          <li><a href="<?= base_url('?route=admin/unidades') ?>">Unidades (Lotes/Casas)</a></li>
          <li><a href="<?= base_url('?route=admin/procuracoes') ?>">Procurações</a></li>
        </ul>
      </li>
      <li class="has-dropdown">
        <a>Assembleias ▾</a>
        <ul class="dropdown">
          <li><a href="<?= base_url('?route=admin/assembleias') ?>">Listagem</a></li>
          <li><a href="<?= base_url('?route=admin/assembleia_novo') ?>">Nova Assembleia</a></li>
        </ul>
      </li>
      <li class="has-dropdown">
        <a>Relatórios ▾</a>
        <ul class="dropdown">
          <li><a href="<?= base_url('?route=relatorios/presenca_lista') ?>">Lista de Presença</a></li>
          <li><a href="<?= base_url('?route=relatorios/resultados_lista') ?>">Resultados de Votação</a></li>
        </ul>
      </li>
      <?php else: ?>
      <li><a href="<?= base_url('?route=assembleia/index') ?>">Minhas Assembleias</a></li>
      <?php endif; ?>
      <li class="nav-user">
        <div class="user-avatar" aria-hidden="true"><?= mb_substr($userNome,0,1,'UTF-8') ?></div>
        <div class="user-info">
          <strong><?= sanitize($userNome) ?></strong>
          <small><?= sanitize($userCpf) ?> · <?= $isAdmin ? 'Administrador' : 'Condômino' ?></small>
        </div>
        <a class="logout" href="<?= base_url('?route=auth/logout') ?>" aria-label="Sair do sistema">↩ Sair</a>
      </li>
    </ul>
  </div>
</nav>
