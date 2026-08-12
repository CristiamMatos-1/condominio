<div class="page-header">
    <h1 class="page-title"><?= $chapa ? 'Editar Chapa' : 'Nova Chapa' ?></h1>
    <a href="<?= base_url('?route=admin/assembleia_gerenciar/' . $assembleia['id']) ?>" class="btn btn-secondary">↩️ Voltar para Assembleia</a>
</div>

<div class="card">
    <div class="alert alert-info">
        <strong>Assembleia:</strong> <?= sanitize($assembleia['titulo']) ?> (<?= sanitize($assembleia['condominio_nome']) ?>)
    </div>
    <form method="POST" class="form-grid">
        <input type="hidden" name="assembleia_id" value="<?= $assembleia['id'] ?>">
        <div class="form-group">
            <label class="form-label">Ordem</label>
            <input type="number" name="ordem" class="form-input" min="1"
                   value="<?= sanitize($chapa['ordem'] ?? '1') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Nome da Chapa *</label>
            <input type="text" name="nome_chapa" class="form-input" required
                   value="<?= sanitize($chapa['nome_chapa'] ?? '') ?>"
                   placeholder="Ex: Chapa União e Progresso">
        </div>
        <div class="form-group col-full">
            <label class="form-label">Integrantes da Chapa</label>
            <textarea name="integrantes" rows="6" class="form-input"
                      placeholder="Presidente: Nome Completo
Vice-Presidente: Nome Completo
Secretário: Nome Completo
Tesoureiro: Nome Completo"><?= sanitize($chapa['integrantes'] ?? '') ?></textarea>
            <small class="form-hint">Um cargo por linha, no formato: Cargo: Nome do membro</small>
        </div>
        <div class="form-group col-full">
            <button type="submit" class="btn btn-primary btn-lg">💾 Salvar Chapa</button>
        </div>
    </form>
</div>
