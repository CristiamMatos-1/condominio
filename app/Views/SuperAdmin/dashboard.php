<?php
// Dashboard do Super Administrador (SaaS - todos condominios)
?>
<div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h1 class="page-title">🌐 Dashboard Global (Super Administração)</h1>
            <p class="page-subtitle">Visão geral de todos os condomínios da plataforma • <code>Perfil: super_admin</code></p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= base_url('?route=admin/condominio_novo') ?>" class="btn btn-ci btn-primary">＋ Cadastrar Novo Condomínio</a>
            <a href="<?= base_url('?route=admin/condominios') ?>" class="btn-ci btn-secondary">Gerenciar Condomínios</a>
            <a href="<?= base_url('?route=admin/index') ?>" class="btn-ci btn-outline">Painel Admin Geral</a>
        </div>
    </div>
</div>

<!-- Linha 1: KPIs do SaaS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">✅</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_condominios_ativos'] ?></div>
            <div class="stat-label">Condomínios ATIVOS</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-red">🚫</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_condominios_inativos'] ?></div>
            <div class="stat-label">Condomínios SUSPENSOS</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">👥</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_moradores'] ?></div>
            <div class="stat-label">Moradores / Proprietários</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange">🧑‍💼</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_admin_condominio'] ?></div>
            <div class="stat-label">Gestores (Admin Condomínio)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">🏠</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_unidades'] ?></div>
            <div class="stat-label">Unidades (Lotes/Casas)</div>
        </div>
    </div>
</div>

<!-- Linha 2: Assembleias por status -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">🟢</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['assembleias_abertas'] ?></div>
            <div class="stat-label">Abertas / em votação</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">🟡</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['assembleias_andamento'] ?></div>
            <div class="stat-label">Em Andamento</div>
        </div>
    </div>
    <div class="stat-card stat-highlight">
        <div class="stat-icon stat-icon-purple">✅</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['assembleias_encerradas'] ?></div>
            <div class="stat-label">Encerradas / Apuradas</div>
        </div>
    </div>
</div>

<!-- 2 COLUNAS: Onboarding + Admins cadastrados -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(520px,1fr));gap:24px;margin-top:12px;">
    <div class="card-ci">
        <div class="card-header-ci">
            <h3 class="card-title-ci">🚀 Onboarding: Criar Gestor(a) de Condomínio</h3>
            <span class="badge badge-info">Admin do Condomínio</span>
        </div>
        <div class="card-body-ci">
            <form method="POST" action="<?= base_url('?route=superadmin/onboarding') ?>"
                  onsubmit="document.getElementById('btn-onboard').disabled=true;document.getElementById('btn-onboard').textContent='⌛ Enviando...';">
                <div class="row-grid-2">
                    <label class="field-ci">
                        <span class="label-ci">Nome completo *</span>
                        <input required type="text" name="nome" class="input-ci"
                               placeholder="Ex.: João Oliveira da Silva" maxlength="120">
                    </label>
                    <label class="field-ci">
                        <span class="label-ci">CPF * (11 dígitos)</span>
                        <input required type="text" name="cpf" class="input-ci" id="cpf-admin"
                               inputmode="numeric" maxlength="14"
                               placeholder="000.000.000-00" oninput="window.__mask_cpf && __mask_cpf(this)">
                    </label>
                    <label class="field-ci">
                        <span class="label-ci">E-mail profissional</span>
                        <input type="email" name="email" class="input-ci"
                               placeholder="gestor@meucondominio.com.br">
                    </label>
                    <label class="field-ci">
                        <span class="label-ci">Telefone / WhatsApp</span>
                        <input type="tel" name="telefone" class="input-ci"
                               placeholder="(11) 90000-0000">
                    </label>
                    <label class="field-ci">
                        <span class="label-ci">Vincular ao Condomínio *</span>
                        <select required class="input-ci" name="condominio_id" style="padding:12px;">
                            <option value="">-- Selecione um condomínio --</option>
                            <?php foreach ($condominios as $c): ?>
                                <option value="<?= (int)$c['id'] ?>">
                                    <?= htmlspecialchars($c['nome']) ?>
                                    <?php if ((int)($c['ativo'] ?? 1) !== 1) echo ' (SUSPENSO)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field-ci">
                        <span class="label-ci">Senha inicial * (mínimo 6)</span>
                        <input required type="password" name="senha" class="input-ci"
                               autocomplete="new-password"
                               placeholder="SenhaForte#2026" minlength="6">
                    </label>
                </div>
                <div style="margin-top:18px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="btn-ci btn-primary" id="btn-onboard">
                        ✅ Criar Conta de Administrador
                    </button>
                    <small class="hint">
                        O gestor(a) receberá o acesso pelo CPF + senha acima.
                        No primeiro login pode redefinir a senha.
                    </small>
                </div>
            </form>
        </div>
    </div>

    <div class="card-ci">
        <div class="card-header-ci">
            <h3 class="card-title-ci">🧑‍💼 Contas de Administradores de Condomínio</h3>
            <span class="badge badge-info"><?= count($listaUsuariosAdminsCondominio) ?> cadastrados</span>
        </div>
        <div class="card-body-ci">
            <?php if (empty($listaUsuariosAdminsCondominio)): ?>
                <div class="empty-state">
                    <p>Nenhum(a) gestor(a) de condomínio cadastrado(a) ainda.</p>
                    <p class="hint">Use o formulário ao lado para criar a primeira conta.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Condomínio Vinc.</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listaUsuariosAdminsCondominio as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['nome']) ?></td>
                                <td><code><?= htmlspecialchars($u['cpf']) ?></code></td>
                                <td>
                                    <?php if (!empty($u['condominio_id'])): ?>
                                        <?php
                                          $cMatch = null;
                                          foreach ($condominios as $cCand) {
                                              if ((int)$cCand['id'] === (int)$u['condominio_id']) { $cMatch = $cCand; break; }
                                          }
                                          if ($cMatch):
                                        ?>
                                            <span class="badge badge-success">
                                                <?= htmlspecialchars($cMatch['nome']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-info">ID <?= (int)$u['condominio_id'] ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Sem condomínio</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('?route=admin/usuario_editar/' . (int)$u['id']) ?>"
                                       class="btn-ci btn-sm btn-outline" style="padding:8px 12px;">Editar</a>
                                    <a  href="<?= base_url('?route=superadmin/admin_condominio_remover/' . (int)$u['id']) ?>"
                                        onclick="return confirm('Confirma REMOVER a conta de <?= htmlspecialchars($u['nome']) ?>?\n\n(Essa ação apaga o usuário e quebra o vínculo com condominio_id, NÃO apaga assembleias/moradores do condomínio.)');"
                                        class="btn-ci btn-sm btn-danger" style="padding:8px 12px;margin-left:6px;">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lista COMPLETA de Condomínios + Toggle Ativo/Suspenso -->
<div class="section-header" style="margin-top:28px;">
    <h2 class="section-title">🏢 Condomínios da Plataforma</h2>
    <span class="badge badge-secondary" style="font-size:12px;"><?= count($condominios) ?> cadastrados</span>
</div>

<div class="card-ci">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:32px;">ID</th>
                    <th>Nome do Condomínio</th>
                    <th>Endereço</th>
                    <th>CNPJ</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($condominios)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <p>Nenhum condomínio cadastrado ainda.</p>
                            <a href="<?= base_url('?route=admin/condominio_novo') ?>" class="btn-ci btn-primary">＋ Cadastrar Primeiro Condomínio</a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($condominios as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                    <td><?= htmlspecialchars($c['endereco'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['cnpj'] ?? '—') ?></td>
                    <td>
                        <?php if ((int)($c['ativo'] ?? 1) === 1): ?>
                            <span class="badge badge-success">✅ Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-danger">🚫 Suspenso</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="<?= base_url('?route=admin/condominio_editar/' . (int)$c['id']) ?>"
                           class="btn-ci btn-sm btn-outline" style="padding:8px 12px;">Editar</a>
                        <a href="<?= base_url('?route=superadmin/condominio_toggle/' . (int)$c['id']) ?>"
                           class="btn-ci btn-sm <?= ((int)($c['ativo'] ?? 1) === 1) ? 'btn-warning' : 'btn-success' ?>"
                           style="padding:8px 12px;margin-left:6px;">
                            <?= ((int)($c['ativo'] ?? 1) === 1) ? '⛔ Suspender' : '✅ Reativar' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Máscara CPF (inline, leve)
window.__mask_cpf = function(el) {
  let v = el.value.replace(/\D/g,'').slice(0,11);
  if (v.length>9)  v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2})/,'$1.$2.$3-$4');
  else if (v.length>6) v = v.replace(/^(\d{3})(\d{3})(\d{0,3})/,'$1.$2.$3');
  else if (v.length>3) v = v.replace(/^(\d{3})(\d{0,3})/,'$1.$2');
  el.value = v;
};
document.getElementById('cpf-admin') && __mask_cpf(document.getElementById('cpf-admin'));
</script>
