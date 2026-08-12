<div class="page-header">
    <h1 class="page-title">Gestão de Assembleias</h1>
    <a href="<?= base_url('?route=admin/assembleia_nova') ?>" class="btn btn-primary">➕ Nova Assembleia</a>
</div>

<div class="card">
    <?php if (count($assembleias) === 0): ?>
        <div class="empty-state"><p>Nenhuma assembleia cadastrada.</p></div>
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
                    <?php foreach ($assembleias as $a): ?>
                        <tr>
                            <td><strong><?= sanitize($a['titulo']) ?></strong></td>
                            <td><?= sanitize($a['condominio_nome']) ?></td>
                            <td>
                                <span class="badge badge-<?= $a['tipo'] === 'Ordinária' ? 'blue' : 'warning' ?>">
                                    <?= sanitize($a['tipo']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($a['data_assembleia'])) ?></td>
                            <td><?= substr($a['horario'], 0, 5) ?></td>
                            <td>
                                <?php if ($a['status'] === 'Aberta'): ?>
                                    <span class="badge badge-success pulse">🔴 Aberta</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Fechada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= base_url('?route=admin/assembleia_gerenciar/' . $a['id']) ?>" class="btn btn-info btn-sm">Gerenciar</a>
                                    <a href="<?= base_url('?route=admin/assembleia_editar/' . $a['id']) ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <a href="<?= base_url('?route=admin/assembleia_excluir/' . $a['id']) ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Deseja realmente excluir?');">
                                        Excluir
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
