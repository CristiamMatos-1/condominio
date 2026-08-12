<div class="page-header">
    <h1 class="page-title">Gestão de Unidades</h1>
    <a href="<?= base_url('?route=admin/unidade_nova') ?>" class="btn btn-primary">➕ Nova Unidade</a>
</div>

<div class="card">
    <?php if (count($unidades) === 0): ?>
        <div class="empty-state"><p>Nenhuma unidade cadastrada.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Condomínio</th>
                        <th>Lote</th>
                        <th>Casa</th>
                        <th>Morador/Dono</th>
                        <th>CPF</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unidades as $u): ?>
                        <tr>
                            <td><?= sanitize($u['condominio_nome']) ?></td>
                            <td><span class="badge badge-info"><?= sanitize($u['lote']) ?></span></td>
                            <td><span class="badge badge-purple"><?= sanitize($u['casa']) ?></span></td>
                            <td><?= sanitize($u['dono_nome']) ?></td>
                            <td><?= sanitize($u['dono_cpf']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= base_url('?route=admin/unidade_editar/' . $u['id']) ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <a href="<?= base_url('?route=admin/unidade_excluir/' . $u['id']) ?>"
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
