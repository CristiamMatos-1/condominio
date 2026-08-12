<div class="page-header">
    <h1 class="page-title">Gerenciar Assembleia</h1>
    <div>
        <a href="<?= base_url('?route=admin/assembleias') ?>" class="btn btn-secondary">↩️ Voltar</a>
        <?php if ($assembleia['status'] === 'Fechada'): ?>
            <a href="<?= base_url('?route=admin/assembleia_abrir/' . $assembleia['id']) ?>"
               class="btn btn-success"
               onclick="return confirm('Abrir assembleia para votação?');">
               🔓 Abrir Assembleia
            </a>
        <?php else: ?>
            <a href="<?= base_url('?route=admin/assembleia_fechar/' . $assembleia['id']) ?>"
               class="btn btn-danger"
               onclick="return confirm('Fechar assembleia e apurar resultados? Esta ação é definitiva.');">
               🔒 Fechar & Apurar
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card assembleia-header-card">
    <div class="assembleia-info">
        <h2 class="assembleia-titulo"><?= sanitize($assembleia['titulo']) ?></h2>
        <div class="assembleia-meta">
            <span class="badge badge-<?= $assembleia['tipo'] === 'Ordinária' ? 'blue' : 'warning' ?>">
                <?= sanitize($assembleia['tipo']) ?>
            </span>
            <span class="meta-item">🏢 <?= sanitize($assembleia['condominio_nome']) ?></span>
            <span class="meta-item">📅 <?= date('d/m/Y', strtotime($assembleia['data_assembleia'])) ?></span>
            <span class="meta-item">⏰ <?= substr($assembleia['horario'], 0, 5) ?></span>
            <?php if ($assembleia['status'] === 'Aberta'): ?>
                <span class="badge badge-success pulse">🔴 Aberta</span>
            <?php else: ?>
                <span class="badge badge-secondary">Fechada</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($assembleia['observacoes'])): ?>
            <p class="obs"><strong>Observações:</strong> <?= sanitize($assembleia['observacoes']) ?></p>
        <?php endif; ?>
    </div>
    <div class="presenca-box">
        <div class="presenca-numero"><?= (int)$countPresencas ?></div>
        <div class="presenca-label">Presentes</div>
        <a href="<?= base_url('?route=relatorios/presenca/' . $assembleia['id']) ?>" class="btn btn-outline btn-sm mt-2" target="_blank">
            📄 Ver lista
        </a>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3>📑 Pautas (Matérias)</h3>
            <a href="<?= base_url('?route=admin/pauta_nova/' . $assembleia['id']) ?>" class="btn btn-primary btn-sm">➕ Pauta</a>
        </div>
        <?php if (count($pautas) === 0): ?>
            <div class="empty-state-sm"><p>Nenhuma pauta cadastrada.</p></div>
        <?php else: ?>
            <div class="pauta-list">
                <?php foreach ($pautas as $p): ?>
                    <div class="pauta-item">
                        <div class="pauta-head">
                            <div>
                                <span class="pauta-ordem">#<?= (int)$p['ordem'] ?></span>
                                <strong><?= sanitize($p['titulo']) ?></strong>
                                <?php
                                    $statusClass = 'badge-secondary';
                                    if ($p['status'] === 'Em votação') $statusClass = 'badge-warning pulse';
                                    elseif ($p['status'] === 'Aprovada') $statusClass = 'badge-success';
                                    elseif ($p['status'] === 'Rejeitada') $statusClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= sanitize($p['status']) ?></span>
                            </div>
                            <div class="btn-group">
                                <?php if ($p['status'] !== 'Em votação' && $p['status'] !== 'Aprovada' && $p['status'] !== 'Rejeitada'): ?>
                                    <a href="<?= base_url('?route=admin/pauta_ativar/' . $p['id']) ?>" class="btn btn-outline btn-sm">▶️ Ativar</a>
                                <?php endif; ?>
                                <a href="<?= base_url('?route=admin/pauta_editar/' . $p['id']) ?>" class="btn btn-warning btn-sm">✏️</a>
                                <a href="<?= base_url('?route=admin/pauta_excluir/' . $p['id']) ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Excluir pauta?');">🗑️</a>
                            </div>
                        </div>
                        <?php if (!empty($p['descricao'])): ?>
                            <p class="pauta-desc"><?= sanitize($p['descricao']) ?></p>
                        <?php endif; ?>
                        <div class="resultado-barra">
                            <?php
                                $total = max(1, (int)$p['total_votos']);
                                $pctSim = round((((int)$p['votos_sim']) / $total) * 100);
                                $pctNao = 100 - $pctSim;
                            ?>
                            <div class="barra-container">
                                <div class="barra barra-sim" style="width: <?= $pctSim ?>%"></div>
                                <div class="barra barra-nao" style="width: <?= $pctNao ?>%"></div>
                            </div>
                            <div class="resultado-numeros">
                                <span class="sim">✅ Sim: <?= (int)$p['votos_sim'] ?> (<?= $pctSim ?>%)</span>
                                <span class="nao">❌ Não: <?= (int)$p['votos_nao'] ?> (<?= $pctNao ?>%)</span>
                                <span class="total">Total: <?= (int)$p['total_votos'] ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🗳️ Chapas (Diretoria)</h3>
            <a href="<?= base_url('?route=admin/chapa_nova/' . $assembleia['id']) ?>" class="btn btn-primary btn-sm">➕ Chapa</a>
        </div>
        <?php if (count($chapas) === 0): ?>
            <div class="empty-state-sm"><p>Nenhuma chapa cadastrada.</p></div>
        <?php else: ?>
            <?php
                $totalVotosChapa = 0;
                foreach ($resultadoChapas as $rc) { $totalVotosChapa += (int)$rc['total_votos']; }
                $totalVotosChapa = max(1, $totalVotosChapa);
            ?>
            <div class="chapa-list">
                <?php foreach ($resultadoChapas as $idx => $ch): ?>
                    <?php $pct = round(((int)$ch['total_votos'] / $totalVotosChapa) * 100); ?>
                    <div class="chapa-item <?= $idx === 0 && (int)$ch['total_votos'] > 0 ? 'chapa-vencedora' : '' ?>">
                        <div class="chapa-head">
                            <strong><?= $idx === 0 && (int)$ch['total_votos'] > 0 ? '🏆 ' : '' ?><?= sanitize($ch['nome_chapa']) ?></strong>
                            <span class="votos-count"><?= (int)$ch['total_votos'] ?> votos (<?= $pct ?>%)</span>
                        </div>
                        <?php if (!empty($ch['integrantes'])): ?>
                            <div class="chapa-integrantes">
                                <?= nl2br(sanitize($ch['integrantes'])) ?>
                            </div>
                        <?php endif; ?>
                        <div class="barra-container barra-chapa">
                            <div class="barra barra-chapa-fill" style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="chapa-actions">
                            <?php
                                $chapaOriginal = null;
                                foreach ($chapas as $c) { if ($c['id'] == $ch['id']) { $chapaOriginal = $c; break; } }
                            ?>
                            <?php if ($chapaOriginal): ?>
                                <a href="<?= base_url('?route=admin/chapa_editar/' . $chapaOriginal['id']) ?>" class="btn btn-warning btn-sm">✏️ Editar</a>
                                <a href="<?= base_url('?route=admin/chapa_excluir/' . $chapaOriginal['id']) ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Excluir chapa?');">🗑️</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>👥 Presenças (<?= (int)$countPresencas ?>)</h3>
        <div>
            <a href="<?= base_url('?route=relatorios/presenca/' . $assembleia['id']) ?>" class="btn btn-outline btn-sm" target="_blank">📄 Lista de Presença</a>
            <a href="<?= base_url('?route=relatorios/resultados/' . $assembleia['id']) ?>" class="btn btn-primary btn-sm" target="_blank">📊 Relatório Completo</a>
        </div>
    </div>
    <?php if (count($presencas) === 0): ?>
        <div class="empty-state-sm"><p>Nenhuma presença registrada.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table table-sm">
                <thead>
                    <tr>
                        <th>Unidade</th>
                        <th>Condômino/Dono</th>
                        <th>Presente como</th>
                        <th>Representante</th>
                        <th>Procuração</th>
                        <th>Check-in</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presencas as $p): ?>
                        <tr>
                            <td>
                                <span class="badge badge-info">Lote <?= sanitize($p['lote']) ?></span>
                                <span class="badge badge-purple">Casa <?= sanitize($p['casa']) ?></span>
                            </td>
                            <td><strong><?= sanitize($p['dono_nome']) ?></strong><br><small><?= sanitize($p['dono_cpf']) ?></small></td>
                            <td>
                                <?php if (empty($p['via_procuracao'])): ?>
                                    <span class="badge badge-success">Titular</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Procuração</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($p['usuario_presente_nome']) ?></td>
                            <td>
                                <?php if (!empty($p['procuracao_num'])): ?>
                                    <code><?= sanitize($p['procuracao_num']) ?></code>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('d/m/Y H:i', strtotime($p['data_checkin'])) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
