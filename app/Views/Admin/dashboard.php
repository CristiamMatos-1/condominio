<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Visão geral do sistema de assembleias</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">🏢</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_condominios'] ?></div>
            <div class="stat-label">Condomínios</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">👥</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_usuarios'] ?></div>
            <div class="stat-label">Usuários Ativos</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">🏠</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_unidades'] ?></div>
            <div class="stat-label">Unidades (Lotes/Casas)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange">📋</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['total_assembleias'] ?></div>
            <div class="stat-label">Total de Assembleias</div>
        </div>
    </div>
    <div class="stat-card stat-highlight">
        <div class="stat-icon stat-icon-red">🔴</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['assembleias_abertas'] ?></div>
            <div class="stat-label">Assembleias Abertas</div>
        </div>
    </div>
</div>

<div class="section-header">
    <h2 class="section-title">Últimas Assembleias</h2>
</div>

<div class="card">
    <?php if (count($ultimasAssembleias) === 0): ?>
        <div class="empty-state">
            <p>Nenhuma assembleia cadastrada ainda.</p>
            <a href="<?= base_url('?route=admin/assembleia_nova') ?>" class="btn btn-primary">Criar Primeira Assembleia</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Condomínio</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($ultimasAssembleias, 0, 10) as $a): ?>
                        <tr>
                            <td><?= sanitize($a['titulo']) ?></td>
                            <td><?= sanitize($a['condominio_nome']) ?></td>
                            <td>
                                <span class="badge badge-info"><?= sanitize($a['tipo']) ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($a['data_assembleia'])) ?></td>
                            <td><?= substr($a['horario'], 0, 5) ?></td>
                            <td>
                                <?php if ($a['status'] === 'Aberta'): ?>
                                    <span class="badge badge-success">Aberta</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Fechada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= base_url('?route=admin/assembleia_gerenciar/' . $a['id']) ?>" class="btn btn-info btn-sm">Gerenciar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
