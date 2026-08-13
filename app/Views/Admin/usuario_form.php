<div class="page-header">
    <h1 class="page-title"><?= $usuario ? 'Editar Usuário' : 'Novo Usuário' ?></h1>
    <a href="<?= base_url('?route=admin/usuarios') ?>" class="btn btn-secondary">↩️ Voltar</a>
</div>

<div class="card">
    <form method="POST" class="form-grid">
        <div class="form-group col-full">
            <label class="form-label">Nome Completo *</label>
            <input type="text" name="nome" class="form-input" required
                   value="<?= sanitize($usuario['nome'] ?? '') ?>" placeholder="Nome completo do condômino">
        </div>
        <div class="form-group">
            <label class="form-label">
                CPF *
                <?php if ($usuario): ?>
                    <small class="hint" style="font-weight:400;">
                        (Por segurança e privacidade LGPD, deixamos em branco. Digite apenas se precisar alterar.)
                    </small>
                <?php endif; ?>
            </label>
            <input type="text" name="cpf" class="form-input" id="cpf-input"
                   <?php if (!$usuario): ?>required<?php endif; ?>
                   value="" placeholder="000.000.000-00">
        </div>
        <?php if (!empty($condominios)): ?>
            <div class="form-group">
                <label class="form-label">Perfil *</label>
                <select name="perfil" class="form-input" required>
                    <option value="morador"
                        <?= (!$usuario || ($usuario['perfil'] ?? 'morador') === 'morador') ? 'selected' : '' ?>>
                        Morador
                    </option>
                    <option value="admin_condominio"
                        <?= ($usuario && ($usuario['perfil'] ?? '') === 'admin_condominio') ? 'selected' : '' ?>>
                        Administrador do Condomínio (Síndico/Gestor)
                    </option>
                    <option value="super_admin"
                        <?= ($usuario && ($usuario['perfil'] ?? '') === 'super_admin') ? 'selected' : '' ?>>
                        Super Administrador
                    </option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Condomínio Vinculado</label>
                <select name="condominio_id" class="form-input">
                    <option value="">-- Sem vínculo (apenas para Super Admin) --</option>
                    <?php foreach ($condominios as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= ($usuario && (int)($usuario['condominio_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= sanitize($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-input"
                   value="<?= sanitize($usuario['email'] ?? '') ?>" placeholder="usuario@email.com">
        </div>
        <div class="form-group">
            <label class="form-label">Telefone</label>
            <input type="text" name="telefone" class="form-input" id="tel-input"
                   value="<?= sanitize($usuario['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
        </div>
        <div class="form-group">
            <label class="form-label">Senha <?= $usuario ? '(deixe vazio para manter)' : '(obrigatório para admin)' ?></label>
            <input type="password" name="senha" class="form-input"
                   placeholder="<?= $usuario ? 'Nova senha (opcional)' : 'Senha de acesso' ?>"
                   autocomplete="new-password">
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="ativo" class="form-input">
                <option value="1" <?= (!$usuario || !empty($usuario['ativo'])) ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($usuario && empty($usuario['ativo'])) ? 'selected' : '' ?>>Inativo</option>
            </select>
        </div>
        <div class="form-group col-full">
            <button type="submit" class="btn btn-primary btn-lg">💾 Salvar Usuário</button>
        </div>
        <?= csrf_field() ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cpf = document.getElementById('cpf-input');
    if (cpf) aplicarMascaraCpf(cpf);
    const tel = document.getElementById('tel-input');
    if (tel) aplicarMascaraTelefone(tel);
});
</script>
