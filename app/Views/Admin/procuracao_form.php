<div class="page-header">
    <h1 class="page-title"><?= $procuracao ? 'Editar Procuração' : 'Nova Procuração' ?></h1>
    <a href="<?= base_url('?route=admin/procuracoes') ?>" class="btn btn-secondary">↩️ Voltar</a>
</div>

<div class="card">
    <form method="POST" class="form-grid">
        <div class="form-group col-full">
            <label class="form-label">Unidade a ser Representada *</label>
            <select name="unidade_id" class="form-input" required>
                <option value="">Selecione a Unidade...</option>
                <?php foreach ($unidades as $u): ?>
                    <option value="<?= $u['id'] ?>"
                        <?= ($procuracao && $procuracao['unidade_id'] == $u['id']) ? 'selected' : '' ?>>
                        <?= sanitize($u['condominio_nome']) ?> - Lote: <?= sanitize($u['lote']) ?> / Casa: <?= sanitize($u['casa']) ?> (Dono: <?= sanitize($u['dono_nome']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-full">
            <label class="form-label">Representante (quem receberá a procuração) *</label>
            <select name="representante_id" class="form-input" required>
                <option value="">Selecione o Representante...</option>
                <?php foreach ($moradores as $m): ?>
                    <option value="<?= $m['id'] ?>"
                        <?= ($procuracao && $procuracao['representante_id'] == $m['id']) ? 'selected' : '' ?>>
                        <?= sanitize($m['nome']) ?> - <?= sanitize($m['cpf']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Número do Documento (Procuração) *</label>
            <input type="text" name="num_documento" class="form-input" required
                   value="<?= sanitize($procuracao['num_documento'] ?? '') ?>" placeholder="Nº do registro/escritura">
        </div>
        <div class="form-group">
            <label class="form-label">Data de Outorgação</label>
            <input type="date" name="data_outorgacao" class="form-input"
                   value="<?= sanitize($procuracao['data_outorgacao'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="ativo" class="form-input">
                <option value="1" <?= (!$procuracao || !empty($procuracao['ativo'])) ? 'selected' : '' ?>>Ativa</option>
                <option value="0" <?= ($procuracao && empty($procuracao['ativo'])) ? 'selected' : '' ?>>Inativa</option>
            </select>
        </div>
        <div class="form-group col-full">
            <button type="submit" class="btn btn-primary btn-lg">💾 Salvar Procuração</button>
        </div>
    </form>
</div>
