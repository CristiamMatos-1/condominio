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
                    <?php foreach ($usuarios as $u):
                        $_nome     = (string)($u['nome']     ?? '');
                        $_cpf      = (string)($u['cpf']      ?? '—');
                        $_email    = (string)($u['email']    ?? '—');
                        $_telefone = (string)($u['telefone'] ?? '—');
                        $_tipo     = (string)($u['tipo']     ?? ($u['perfil'] ?? 'morador'));
                        $_perfil   = (string)($u['perfil']   ?? $_tipo);
                        $_ativo    = !empty($u['ativo']);
                        $_id       = (int)($u['id'] ?? 0);
                    ?>
                        <tr>
                            <td><?= sanitize($_nome) ?></td>
                            <td><?= sanitize($_cpf) ?></td>
                            <td><?= sanitize($_email) ?></td>
                            <td><?= sanitize($_telefone) ?></td>
                            <td>
                                <?php if ($_tipo === 'admin' || $_perfil === 'super_admin'): ?>
                                    <span class="badge badge-danger">Super Administrador</span>
                                <?php elseif ($_tipo === 'admin_condominio' || $_perfil === 'admin_condominio'): ?>
                                    <span class="badge badge-warning">Gestor / Síndico</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Morador</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($_ativo): ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($_id > 0): ?>
                                <div class="btn-group">
                                    <a href="<?= base_url('?route=admin/usuario_editar/' . $_id) ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <a href="<?= base_url('?route=admin/usuario_excluir/' . $_id) ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Deseja realmente excluir?');">
                                        Excluir
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
