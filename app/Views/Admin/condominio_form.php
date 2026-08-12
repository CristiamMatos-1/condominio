<div class="page-header">
    <h1 class="page-title"><?= $condominio ? 'Editar Condomínio' : 'Novo Condomínio' ?></h1>
    <a href="<?= base_url('?route=admin/condominios') ?>" class="btn btn-secondary">↩️ Voltar</a>
</div>

<div class="card">
    <form method="POST" class="form-grid">
        <div class="form-group col-full">
            <label class="form-label">Nome do Condomínio *</label>
            <input type="text" name="nome" class="form-input" required
                   value="<?= sanitize($condominio['nome'] ?? '') ?>" placeholder="Ex: Residencial Jardim Primavera">
        </div>
        <div class="form-group">
            <label class="form-label">CNPJ</label>
            <input type="text" name="cnpj" class="form-input" id="cnpj-input"
                   value="<?= sanitize($condominio['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00">
        </div>
        <div class="form-group">
            <label class="form-label">CEP</label>
            <input type="text" name="cep" class="form-input" id="cep-input"
                   value="<?= sanitize($condominio['cep'] ?? '') ?>" placeholder="00000-000">
        </div>
        <div class="form-group col-full">
            <label class="form-label">Endereço</label>
            <input type="text" name="endereco" class="form-input"
                   value="<?= sanitize($condominio['endereco'] ?? '') ?>" placeholder="Rua, número, bairro">
        </div>
        <div class="form-group">
            <label class="form-label">Cidade</label>
            <input type="text" name="cidade" class="form-input"
                   value="<?= sanitize($condominio['cidade'] ?? '') ?>" placeholder="São Paulo">
        </div>
        <div class="form-group">
            <label class="form-label">Estado (UF)</label>
            <input type="text" name="estado" class="form-input" maxlength="2"
                   value="<?= sanitize($condominio['estado'] ?? '') ?>" placeholder="SP">
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="ativo" class="form-input">
                <option value="1" <?= (!$condominio || !empty($condominio['ativo'])) ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($condominio && empty($condominio['ativo'])) ? 'selected' : '' ?>>Inativo</option>
            </select>
        </div>
        <div class="form-group col-full">
            <button type="submit" class="btn btn-primary btn-lg">💾 Salvar Condomínio</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cnpj = document.getElementById('cnpj-input');
    if (cnpj) aplicarMascaraCnpj(cnpj);
    const cep = document.getElementById('cep-input');
    if (cep) aplicarMascaraCep(cep);
});
</script>
