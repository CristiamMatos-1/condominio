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
            <label class="form-label">CPF *</label>
            <input type="text" name="cpf" class="form-input" id="cpf-input" required
                   value="<?= sanitize($usuario['cpf'] ?? '') ?>" placeholder="000.000.000-00">
        </div>
        <div class="form-group">
            <label class="form-label">Tipo de Usuário</label>
            <select name="tipo" class="form-input">
                <option value="morador" <?= (!$usuario || $usuario['tipo'] === 'morador') ? 'selected' : '' ?>>Morador</option>
                <option value="admin" <?= ($usuario && $usuario['tipo'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
            </select>
        </div>
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
            <label class="form-label">Senha <?= $usuario ? '(deixe vazio para manter)' : '' ?></label>
            <input type="password" name="senha" class="form-input"
                   placeholder="<?= $usuario ? 'Nova senha' : 'Somente para admin' ?>">
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
