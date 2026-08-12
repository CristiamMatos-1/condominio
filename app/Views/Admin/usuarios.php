<div class="page-header">
    <h1 class="page-title">Gestão de Usuários</h1>
    <a href="<?= base_url('?route=admin/usuario_novo') ?>" class="btn btn-primary">
        ➕ Novo Usuário
    </a>
</div>

<div class="card">
    <?php if (count($usuarios) === 0): ?>
        <div class="empty-state"><p>Nenhum usuário cadastrado.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= sanitize($u['nome']) ?></td>
                            <td><?= sanitize($u['cpf']) ?></td>
                            <td><?= sanitize($u['email'] ?? '-') ?></td>
                            <td><?= sanitize($u['telefone'] ?? '-') ?></td>
                            <td>
                                <?php if ($u['tipo'] === 'admin'): ?>
                                    <span class="badge badge-danger">Administrador</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Morador</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($u['ativo'])): ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= base_url('?route=admin/usuario_editar/' . $u['id']) ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <a href="<?= base_url('?route=admin/usuario_excluir/' . $u['id']) ?>"
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
