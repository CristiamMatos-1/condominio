<div class="page-header">
    <h1 class="page-title">Gestão de Procurações</h1>
    <a href="<?= base_url('?route=admin/procuracao_nova') ?>" class="btn btn-primary">➕ Nova Procuração</a>
</div>

<div class="card">
    <?php if (count($procuracoes) === 0): ?>
        <div class="empty-state"><p>Nenhuma procuração cadastrada.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Condomínio</th>
                        <th>Unidade (Lote/Casa)</th>
                        <th>Dono da Unidade</th>
                        <th>Representante</th>
                        <th>Nº Documento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procuracoes as $p): ?>
                        <tr>
                            <td><?= sanitize($p['condominio_nome']) ?></td>
                            <td>
                                <span class="badge badge-info">Lote <?= sanitize($p['lote']) ?></span>
                                <span class="badge badge-purple">Casa <?= sanitize($p['casa']) ?></span>
                            </td>
                            <td><?= sanitize($p['dono_nome']) ?></td>
                            <td>
                                <strong><?= sanitize($p['representante_nome']) ?></strong><br>
                                <small><?= sanitize($p['representante_cpf']) ?></small>
                            </td>
                            <td><code><?= sanitize($p['num_documento']) ?></code></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= base_url('?route=admin/procuracao_editar/' . $p['id']) ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <a href="<?= base_url('?route=admin/procuracao_excluir/' . $p['id']) ?>"
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
