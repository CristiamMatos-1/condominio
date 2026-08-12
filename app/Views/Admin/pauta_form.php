<div class="page-header">
    <h1 class="page-title"><?= $pauta ? 'Editar Pauta' : 'Nova Pauta' ?></h1>
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
                   value="<?= sanitize($pauta['ordem'] ?? '1') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Status Inicial</label>
            <select name="status" class="form-input">
                <option value="Pendente" <?= (!$pauta || $pauta['status'] === 'Pendente') ? 'selected' : '' ?>>Pendente</option>
                <option value="Em votação" <?= ($pauta && $pauta['status'] === 'Em votação') ? 'selected' : '' ?>>Em votação</option>
                <option value="Aprovada" <?= ($pauta && $pauta['status'] === 'Aprovada') ? 'selected' : '' ?>>Aprovada</option>
                <option value="Rejeitada" <?= ($pauta && $pauta['status'] === 'Rejeitada') ? 'selected' : '' ?>>Rejeitada</option>
            </select>
        </div>
        <div class="form-group col-full">
            <label class="form-label">Título da Matéria/Pauta *</label>
            <input type="text" name="titulo" class="form-input" required
                   value="<?= sanitize($pauta['titulo'] ?? '') ?>"
                   placeholder="Ex: Aprovação do orçamento anual 2026">
        </div>
        <div class="form-group col-full">
            <label class="form-label">Descrição Detalhada</label>
            <textarea name="descricao" rows="6" class="form-input"
                      placeholder="Descreva detalhadamente a matéria a ser votada..."><?= sanitize($pauta['descricao'] ?? '') ?></textarea>
        </div>
        <div class="form-group col-full">
            <button type="submit" class="btn btn-primary btn-lg">💾 Salvar Pauta</button>
        </div>
    </form>
</div>
