<div class="page-header">
    <h1 class="page-title">Gestão de Condomínios</h1>
    <a href="<?= base_url('?route=admin/condominio_novo') ?>" class="btn btn-primary">
        ➕ Novo Condomínio
    </a>
</div>

<div class="card">
    <?php if (count($condominios) === 0): ?>
        <div class="empty-state">
            <p>Nenhum condomínio cadastrado.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CNPJ</th>
                        <th>Cidade/UF</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($condominios as $c): ?>
                        <tr>
                            <td><?= sanitize($c['nome']) ?></td>
                            <td><?= sanitize($c['cnpj'] ?? '-') ?></td>
                            <td><?= sanitize($c['cidade'] ?? '-') ?> / <?= sanitize($c['estado'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($c['ativo'])): ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= base_url('?route=admin/condominio_editar/' . $c['id']) ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <a href="<?= base_url('?route=admin/condominio_excluir/' . $c['id']) ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Deseja realmente excluir este condomínio?');">
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
