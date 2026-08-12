<div class="page-header">
    <h1 class="page-title">Assembleia em Andamento</h1>
    <a href="<?= base_url('?route=assembleia/index') ?>" class="btn btn-secondary">↩️ Voltar</a>
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
                <span class="badge badge-success pulse">🔴 Aberta para Votação</span>
            <?php else: ?>
                <span class="badge badge-secondary">Fechada</span>
            <?php endif; ?>
            <span class="meta-item">👥 <?= (int)$presencasCount ?> presentes</span>
        </div>
    </div>
    <div class="presenca-box">
        <div class="presenca-numero"><?= count($unidades) ?></div>
        <div class="presenca-label">Unidades que você representa</div>
    </div>
</div>

<?php if (count($unidades) > 1): ?>
    <div class="card mt-2">
        <div class="card-header">
            <h3>🏘️ Unidades que você pode votar</h3>
        </div>
        <div class="unidades-list">
            <?php foreach ($unidades as $un): ?>
                <div class="unidade-item <?= !empty($un['via_procuracao']) ? 'unidade-procuracao' : 'unidade-titular' ?>">
                    <span class="badge badge-<?= !empty($un['via_procuracao']) ? 'warning' : 'success' ?>">
                        <?= !empty($un['via_procuracao']) ? '📄 Procuração' : '👤 Titular' ?>
                    </span>
                    <strong>Lote <?= sanitize($un['lote']) ?> / Casa <?= sanitize($un['casa']) ?></strong>
                    <small><?= sanitize($un['dono_nome']) ?></small>
                    <?php if (!empty($un['num_documento'])): ?>
                        <small class="proc-num">Proc.: <?= sanitize($un['num_documento']) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-hint mt-2">
            <strong>Importante:</strong> Você tem direito a votar em cada unidade acima separadamente.
            Ao votar, selecione qual unidade está representando naquele momento.
        </p>
    </div>
<?php endif; ?>

<?php if (count($pautas) > 0): ?>
<div class="section-header mt-4">
    <h2 class="section-title">📑 Votação de Matérias (Pautas)</h2>
</div>
<div class="card pautas-votacao">
    <?php foreach ($pautas as $p): ?>
        <?php
            $todasVotadas = true;
            foreach ($unidades as $un) {
                if (!in_array($un['id'], $votosPautasUsuario[$p['id']] ?? [])) {
                    $todasVotadas = false;
                    break;
                }
            }
            $statusClass = 'pauta-pendente';
            if ($p['status'] === 'Em votação') $statusClass = 'pauta-em-votacao';
            elseif ($p['status'] === 'Aprovada') $statusClass = 'pauta-aprovada';
            elseif ($p['status'] === 'Rejeitada') $statusClass = 'pauta-rejeitada';
        ?>
        <div class="pauta-votacao-item <?= $statusClass ?>">
            <div class="pauta-votacao-head">
                <div>
                    <span class="pauta-ordem">#<?= (int)$p['ordem'] ?></span>
                    <strong class="pauta-titulo-v"><?= sanitize($p['titulo']) ?></strong>
                    <span class="badge badge-sm 
                        <?= $p['status'] === 'Em votação' ? 'badge-warning pulse' : 
                           ($p['status'] === 'Aprovada' ? 'badge-success' : 
                           ($p['status'] === 'Rejeitada' ? 'badge-danger' : 'badge-secondary')) ?>">
                        <?= sanitize($p['status']) ?>
                    </span>
                </div>
                <?php if ($todasVotadas): ?>
                    <span class="badge badge-success">✅ Votado</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($p['descricao'])): ?>
                <p class="pauta-desc"><?= sanitize($p['descricao']) ?></p>
            <?php endif; ?>

            <?php if ($assembleia['status'] === 'Aberta' && ($p['status'] === 'Em votação' || $p['status'] === 'Pendente')): ?>
                <?php foreach ($unidades as $un): ?>
                    <?php $jaVotou = in_array($un['id'], $votosPautasUsuario[$p['id']] ?? []); ?>
                    <form method="POST" action="<?= base_url('?route=assembleia/votar_pauta/' . $p['id']) ?>"
                          class="voto-form <?= $jaVotou ? 'votado' : '' ?>"
                          <?= $jaVotou ? '' : 'onsubmit="return confirm(\'Confirma voto nesta unidade? O voto é irreversível!\');"' ?>>
                        <input type="hidden" name="unidade_id" value="<?= $un['id'] ?>">
                        <div class="voto-unidade-info">
                            <span class="badge badge-sm <?= !empty($un['via_procuracao']) ? 'badge-warning' : 'badge-success' ?>">
                                <?= !empty($un['via_procuracao']) ? 'Procuração' : 'Titular' ?>
                            </span>
                            <span>Lote <?= sanitize($un['lote']) ?> / Casa <?= sanitize($un['casa']) ?></span>
                            <small>(<?= sanitize($un['dono_nome']) ?>)</small>
                            <?php if ($jaVotou): ?>
                                <span class="badge badge-success">✅ Voto registrado</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$jaVotou): ?>
                            <div class="voto-buttons">
                                <button type="submit" name="voto" value="Sim" class="btn btn-voto btn-sim btn-lg">
                                    ✅ SIM
                                </button>
                                <button type="submit" name="voto" value="Não" class="btn btn-voto btn-nao btn-lg">
                                    ❌ NÃO
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php
                $total = max(1, (int)$p['total_votos']);
                $pctSim = round((((int)$p['votos_sim']) / $total) * 100);
                $pctNao = 100 - $pctSim;
            ?>
            <div class="resultado-barra mt-2">
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

<?php if (count($chapas) > 0): ?>
<div class="section-header mt-4">
    <h2 class="section-title">🗳️ Eleição - Diretoria</h2>
</div>

<?php if (count($resultadoChapas) > 0):
    $totalV = 0;
    foreach ($resultadoChapas as $rc) { $totalV += (int)$rc['total_votos']; }
    $totalV = max(1, $totalV);
?>
<div class="card chapas-votacao">
    <?php foreach ($resultadoChapas as $idx => $ch): ?>
        <?php
            $pct = round(((int)$ch['total_votos'] / $totalV) * 100);
            $chapaId = $ch['id'];
        ?>
        <div class="chapa-votacao-item <?= $idx === 0 && (int)$ch['total_votos'] > 0 && $assembleia['status'] === 'Fechada' ? 'chapa-vencedora' : '' ?>">
            <div class="chapa-head">
                <strong>
                    <?= $idx === 0 && (int)$ch['total_votos'] > 0 && $assembleia['status'] === 'Fechada' ? '🏆 ' : '' ?>
                    <?= sanitize($ch['nome_chapa']) ?>
                </strong>
                <span class="votos-count"><?= (int)$ch['total_votos'] ?> votos (<?= $pct ?>%)</span>
            </div>
            <?php if (!empty($ch['integrantes'])): ?>
                <div class="chapa-integrantes"><?= nl2br(sanitize($ch['integrantes'])) ?></div>
            <?php endif; ?>
            <div class="barra-container barra-chapa mt-2">
                <div class="barra barra-chapa-fill" style="width: <?= $pct ?>%"></div>
            </div>

            <?php if ($assembleia['status'] === 'Aberta'): ?>
                <?php foreach ($unidades as $un): ?>
                    <?php $jaVotou = !empty($votosChapasUsuario[$un['id']]); ?>
                    <form method="POST" action="<?= base_url('?route=assembleia/votar_chapa/' . $chapaId) ?>"
                          class="voto-form <?= $jaVotou ? 'votado' : '' ?>"
                          <?= $jaVotou ? '' : 'onsubmit="return confirm(\'Confirma voto nesta chapa para a unidade Lote ' . sanitize($un['lote']) . ' / Casa ' . sanitize($un['casa']) . '? O voto é irreversível!\');"' ?>>
                        <input type="hidden" name="unidade_id" value="<?= $un['id'] ?>">
                        <div class="voto-unidade-info">
                            <span class="badge badge-sm <?= !empty($un['via_procuracao']) ? 'badge-warning' : 'badge-success' ?>">
                                <?= !empty($un['via_procuracao']) ? 'Procuração' : 'Titular' ?>
                            </span>
                            <span>Lote <?= sanitize($un['lote']) ?> / Casa <?= sanitize($un['casa']) ?></span>
                            <small>(<?= sanitize($un['dono_nome']) ?>)</small>
                            <?php if ($jaVotou): ?>
                                <span class="badge badge-success">✅ Já votou</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$jaVotou): ?>
                            <div class="voto-buttons">
                                <button type="submit" class="btn btn-voto btn-chapa btn-lg">
                                    🗳️ Votar nesta chapa
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
