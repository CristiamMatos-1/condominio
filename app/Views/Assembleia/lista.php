<div class="page-header">
    <h1 class="page-title">Minhas Assembleias</h1>
</div>

<div class="card">
    <div class="alert alert-info">
        <strong>Condomínios vinculados:</strong>
        <?php if (count($condominiosUsuario) === 0): ?>
            Nenhum condomínio vinculado ao seu CPF. Contate o síndico.
        <?php else: ?>
            <?php foreach ($condominiosUsuario as $idx => $c): ?>
                <?= sanitize($c['nome']) ?><?= $idx < count($condominiosUsuario) - 1 ? ', ' : '' ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (count($assembleiasAbertas) > 0): ?>
    <div class="section-header">
        <h2 class="section-title">🔴 Assembleias Abertas Agora</h2>
    </div>
    <div class="cards-grid">
        <?php foreach ($assembleiasAbertas as $a): ?>
            <div class="card card-assembleia card-assembleia-aberta">
                <div class="card-header">
                    <span class="badge badge-success pulse">🔴 ABERTA</span>
                    <span class="badge badge-<?= $a['tipo'] === 'Ordinária' ? 'blue' : 'warning' ?>"><?= sanitize($a['tipo']) ?></span>
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?= sanitize($a['titulo']) ?></h3>
                    <p class="card-subtitle">🏢 <?= sanitize($a['condominio_nome']) ?></p>
                    <div class="card-meta">
                        <span>📅 <?= date('d/m/Y', strtotime($a['data_assembleia'])) ?></span>
                        <span>⏰ <?= substr($a['horario'], 0, 5) ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?= base_url('?route=assembleia/ver/' . $a['id']) ?>" class="btn btn-primary btn-block btn-lg">
                        🗳️ Entrar na Assembleia
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="section-header">
    <h2 class="section-title">📋 Todas as Assembleias</h2>
</div>

<?php if (count($assembleiasGeral) === 0): ?>
    <div class="card">
        <div class="empty-state">
            <p>Nenhuma assembleia encontrada para seu perfil.</p>
        </div>
    </div>
<?php else: ?>
    <div class="cards-grid">
        <?php foreach ($assembleiasGeral as $a): ?>
            <div class="card card-assembleia <?= $a['status'] === 'Aberta' ? 'card-assembleia-aberta' : '' ?>">
                <div class="card-header">
                    <?php if ($a['status'] === 'Aberta'): ?>
                        <span class="badge badge-success pulse">🔴 Aberta</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Fechada</span>
                    <?php endif; ?>
                    <span class="badge badge-<?= $a['tipo'] === 'Ordinária' ? 'blue' : 'warning' ?>"><?= sanitize($a['tipo']) ?></span>
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?= sanitize($a['titulo']) ?></h3>
                    <p class="card-subtitle">🏢 <?= sanitize($a['condominio_nome']) ?></p>
                    <div class="card-meta">
                        <span>📅 <?= date('d/m/Y', strtotime($a['data_assembleia'])) ?></span>
                        <span>⏰ <?= substr($a['horario'], 0, 5) ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <?php if ($a['status'] === 'Aberta'): ?>
                        <a href="<?= base_url('?route=assembleia/ver/' . $a['id']) ?>" class="btn btn-primary btn-block">
                            🗳️ Votar Agora
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-block" disabled>Encerrada</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
