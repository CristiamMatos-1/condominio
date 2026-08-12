<div class="page-header">
    <h1 class="page-title"><?= $unidade ? 'Editar Unidade' : 'Nova Unidade' ?></h1>
    <a href="<?= base_url('?route=admin/unidades') ?>" class="btn btn-secondary">↩️ Voltar</a>
</div>

<div class="card">
    <form method="POST" class="form-grid">
        <div class="form-group col-full">
            <label class="form-label">Condomínio *</label>
            <select name="condominio_id" class="form-input" required>
                <option value="">Selecione...</option>
                <?php foreach ($condominios as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= ($unidade && $unidade['condominio_id'] == $c['id']) ? 'selected' : '' ?>>
                        <?= sanitize($c['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Lote *</label>
            <input type="text" name="lote" class="form-input" required
                   value="<?= sanitize($unidade['lote'] ?? '') ?>" placeholder="Ex: 05">
        </div>
        <div class="form-group">
            <label class="form-label">Casa *</label>
            <input type="text" name="casa" class="form-input" required
                   value="<?= sanitize($unidade['casa'] ?? '') ?>" placeholder="Ex: 12">
        </div>
        <div class="form-group col-full">
            <label class="form-label">Morador/Dono da Unidade *</label>
            <select name="usuario_id" class="form-input" required>
                <option value="">Selecione...</option>
                <?php foreach ($moradores as $m): ?>
                    <option value="<?= $m['id'] ?>"
                        <?= ($unidade && $unidade['usuario_id'] == $m['id']) ? 'selected' : '' ?>>
                        <?= sanitize($m['nome']) ?> - <?= sanitize($m['cpf']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="ativo" class="form-input">
                <option value="1" <?= (!$unidade || !empty($unidade['ativo'])) ? 'selected' : '' ?>>Ativa</option>
                <option value="0" <?= ($unidade && empty($unidade['ativo'])) ? 'selected' : '' ?>>Inativa</option>
            </select>
        </div>
        <div class="form-group col-full">
            <button type="submit" class="btn btn-primary btn-lg">💾 Salvar Unidade</button>
        </div>
    </form>
</div>
