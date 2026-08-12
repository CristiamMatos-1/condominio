<div class="page-header">
    <h1 class="page-title"><?= $assembleia ? 'Editar Assembleia' : 'Nova Assembleia' ?></h1>
    <a href="<?= base_url('?route=admin/assembleias') ?>" class="btn btn-secondary">↩️ Voltar</a>
</div>

<div class="card">
    <form method="POST" class="form-grid">
        <div class="form-group col-full">
            <label class="form-label">Título da Assembleia *</label>
            <input type="text" name="titulo" class="form-input" required
                   value="<?= sanitize($assembleia['titulo'] ?? '') ?>"
                   placeholder="Ex: Assembleia Geral Ordinária - 1º Semestre 2026">
        </div>
        <div class="form-group">
            <label class="form-label">Condomínio *</label>
            <select name="condominio_id" class="form-input" required>
                <option value="">Selecione...</option>
                <?php foreach ($condominios as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= ($assembleia && $assembleia['condominio_id'] == $c['id']) ? 'selected' : '' ?>>
                        <?= sanitize($c['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Tipo *</label>
            <select name="tipo" class="form-input" required>
                <option value="Ordinária" <?= (!$assembleia || $assembleia['tipo'] === 'Ordinária') ? 'selected' : '' ?>>Ordinária</option>
                <option value="Extraordinária" <?= ($assembleia && $assembleia['tipo'] === 'Extraordinária') ? 'selected' : '' ?>>Extraordinária</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Data *</label>
            <input type="date" name="data_assembleia" class="form-input" required
                   value="<?= sanitize($assembleia['data_assembleia'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Horário *</label>
            <input type="time" name="horario" class="form-input" required
                   value="<?= sanitize($assembleia['horario'] ?? '19:00') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-input">
                <option value="Fechada" <?= (!$assembleia || $assembleia['status'] === 'Fechada') ? 'selected' : '' ?>>Fechada</option>
                <option value="Aberta" <?= ($assembleia && $assembleia['status'] === 'Aberta') ? 'selected' : '' ?>>Aberta</option>
            </select>
        </div>
        <div class="form-group col-full">
            <label class="form-label">Observações</label>
            <textarea name="observacoes" rows="4" class="form-input"
                      placeholder="Informações adicionais sobre a assembleia..."><?= sanitize($assembleia['observacoes'] ?? '') ?></textarea>
        </div>
        <div class="form-group col-full">
            <button type="submit" class="btn btn-primary btn-lg">💾 Salvar Assembleia</button>
        </div>
    </form>
</div>
