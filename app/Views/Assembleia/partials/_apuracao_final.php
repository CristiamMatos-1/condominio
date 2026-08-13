<?php
/**
 * Partial de Apuração Final Oficial.
 * Mostrada SOMENTE quando assembleia.status = Encerrada.
 * Dados esperados compactados:
 *  - $assembleia (obj/array assembleia)
 *  - $resultadoPautas = ['pautas'=>[...], 'quorum'=>['total_unidades_condominio'=>X,
 *                                                     'total_presentes'=>Y, 'pct_presenca'=>Z]]
 *  - $resultadoChapas = array ordenado por votos DESC (indice 0 = vencedor)
 */
$quorum = $resultadoPautas['quorum'] ?? ['total_unidades_condominio'=>0,'total_presentes'=>0,'pct_presenca'=>0];
$pautasApuradas = $resultadoPautas['pautas'] ?? [];
?>
<div class="section-header mt-4">
    <h2 class="section-title" style="color:#1E3A8A;">📊 Resultado Final Oficial — Assembleia Encerrada</h2>
    <span class="badge badge-success" style="background:#065F46;">🔐 Votação encerrada · apuração definitiva</span>
</div>

<!-- 3 cards de QUORUM (válido para estatuto - presença >= 50% +1) -->
<div class="stats-grid mt-0">
    <div class="stat-card" style="border-top:3px solid #1E40AF;">
        <div class="stat-icon stat-icon-blue">🏘️</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$quorum['total_unidades_condominio'] ?></div>
            <div class="stat-label">Unidades elegíveis (condomínio)</div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #D97706;">
        <div class="stat-icon stat-icon-orange">👥</div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$quorum['total_presentes'] ?></div>
            <div class="stat-label">Unidades Presentes</div>
        </div>
    </div>
    <div class="stat-card stat-highlight" style="border-top:3px solid #065F46;">
        <div class="stat-icon" style="background:#D1FAE5;color:#065F46;">
            <?php if ((float)$quorum['pct_presenca'] >= 50): ?>✅<?php else: ?>⚠️<?php endif; ?>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= (float)$quorum['pct_presenca'] ?>%</div>
            <div class="stat-label">
                Presença (Quórum)
                <?php if ((float)$quorum['pct_presenca'] >= 50): ?>
                    <br><small style="color:#065F46;font-weight:600;">(atingiu mínimo legal 50% +1)</small>
                <?php else: ?>
                    <br><small style="color:#92400E;font-weight:600;">(abaixo do mínimo 50% +1)</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========== PAUTAS ========== -->
<?php if (count($pautasApuradas) > 0): ?>
<div class="section-header mt-6">
    <h3 class="section-title">📑 Resultado por Pauta (Matéria)</h3>
</div>
<div class="card-ci" style="margin-bottom:16px;">
    <div class="card-body-ci">
    <?php foreach ($pautasApuradas as $p):
        $sim   = (int)($p['votos_sim']     ?? 0);
        $nao   = (int)($p['votos_nao']     ?? 0);
        $abst  = (int)($p['abstencoes']    ?? 0);
        $pctS  = (float)($p['pct_sim']     ?? 0);
        $pctN  = (float)($p['pct_nao']     ?? 0);
        $pctA  = (float)($p['pct_abstencao'] ?? 0);
        $res   = $p['resultado'] ?? 'Empate';
    ?>
        <div class="pauta-votacao-item mt-2
                    <?php if ($res==='Aprovada') echo 'pauta-aprovada';
                          elseif ($res==='Rejeitada') echo 'pauta-rejeitada';
                          else echo 'pauta-pendente'; ?>">
            <div class="pauta-votacao-head">
                <div>
                    <span class="pauta-ordem">#<?= (int)$p['ordem'] ?></span>
                    <strong class="pauta-titulo-v"><?= sanitize($p['titulo']) ?></strong>
                </div>
                <span class="badge badge-lg
                    <?php if ($res==='Aprovada') echo 'badge-success';
                          elseif ($res==='Rejeitada') echo 'badge-danger';
                          else echo 'badge-warning'; ?>">
                    <?= $res === 'Aprovada' ? '✅ Aprovada' :
                        ($res === 'Rejeitada' ? '❌ Rejeitada' : '🤝 Empate') ?>
                </span>
            </div>
            <?php if (!empty($p['descricao'])): ?>
                <p class="pauta-desc mt-2"><?= sanitize($p['descricao']) ?></p>
            <?php endif; ?>

            <!-- Barra 3 faixas Sim / Não / Abstenção -->
            <div class="resultado-barra mt-2">
                <div class="barra-container" style="height:28px;border-radius:8px;overflow:hidden;">
                    <div class="barra barra-sim"
                         style="width:<?= number_format($sim > 0 ? ($sim / max(1,max($sim,$nao,$abst))) * 100 * (min(1,$sim+$nao+$abst ? 1 : 0)) : 0,1,'','') ?>0%;
                                display:<?= $sim<=0 && $nao<=0 && $abst<=0 ? 'none' : 'inline-block' ?>;
                                width:<?= $tot = max(1,$sim+$nao+$abst); ?>
                                width:<?= $sim * 100 / $tot ?>%;"></div>
                    <div class="barra barra-nao"
                         style="width:<?= $nao * 100 / $tot ?>%;"></div>
                    <div class="barra"
                         style="width:<?= $abst * 100 / $tot ?>%;
                                background:#E5E7EB;color:#374151;"></div>
                </div>
                <div class="resultado-numeros mt-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                    <div style="padding:10px 14px;background:#D1FAE5;border-radius:8px;color:#065F46;font-weight:600;">
                        ✅ Sim: <?= $sim ?> votos · <?= number_format($pctS,1,',','.') ?>%
                    </div>
                    <div style="padding:10px 14px;background:#FEE2E2;border-radius:8px;color:#991B1B;font-weight:600;">
                        ❌ Não: <?= $nao ?> votos · <?= number_format($pctN,1,',','.') ?>%
                    </div>
                    <div style="padding:10px 14px;background:#F3F4F6;border-radius:8px;color:#374151;font-weight:600;">
                        🤚 Abstenções: <?= $abst ?> · <?= number_format($pctA,1,',','.') ?>%
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ========== CHAPAS ========== -->
<?php if (is_array($resultadoChapas) && count($resultadoChapas) > 0):
    $totalVotosChapa = 0;
    foreach ($resultadoChapas as $rcx) { $totalVotosChapa += (int)($rcx['total_votos'] ?? 0); }
    $totalVotosChapa = max(1, $totalVotosChapa);
    $vencedor = null;
    if ((int)($resultadoChapas[0]['total_votos'] ?? 0) > 0) {
        $vencedor = $resultadoChapas[0]['id'];
        // Critério desempate: 1º lugar com mais votos E >= 50%+1 = vencedor
        if ((int)($resultadoChapas[0]['total_votos'] ?? 0) === (int)($resultadoChapas[1]['total_votos'] ?? 0)) {
            $vencedor = null; // empate técnico
        }
    }
?>
<div class="section-header mt-6">
    <h3 class="section-title">🗳️ Resultado Eleição da Diretoria (Chapas)</h3>
</div>
<div class="card-ci">
    <div class="card-body-ci">
        <?php foreach ($resultadoChapas as $ordem => $ch):
            $qtdVotos = (int)($ch['total_votos'] ?? 0);
            $pctVotos = round(($qtdVotos * 100) / $totalVotosChapa, 1);
            $estaVencendo = $vencedor !== null && (int)$ch['id'] === (int)$vencedor;
        ?>
        <div class="chapa-votacao-item mt-2
                    <?= $estaVencendo ? 'chapa-vencedora' : '' ?>"
             style="<?= $estaVencendo
                ? 'border:2px solid #D97706;background:linear-gradient(90deg,#FFFBEB 0%,#FEF3C7 100%);'
                : 'border:1px solid #E5E7EB;background:#FFFFFF;' ?>">
            <div class="chapa-head">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <?php if ($estaVencendo): ?>
                        <span class="badge badge-lg" style="background:#92400E;color:#FFFBEB;border-radius:999px;">
                            🏆 ELEITA · VENCEDORA
                        </span>
                    <?php elseif ($vencedor === null && $ordem === 0 && $qtdVotos > 0): ?>
                        <span class="badge badge-lg badge-warning" style="border-radius:999px;">🤝 EMPATE TÉCNICO</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Colocação <?= (int)$ordem + 1 ?>º</span>
                    <?php endif; ?>
                    <strong style="font-size:18px;">
                        <?= sanitize($ch['nome_chapa']) ?>
                    </strong>
                </div>
                <span class="votos-count" style="font-size:16px;font-weight:700;">
                    <?= $qtdVotos ?> votos · <?= number_format($pctVotos,1,',','.') ?>%
                </span>
            </div>
            <?php if (!empty($ch['integrantes'])): ?>
                <div class="chapa-integrantes mt-2">
                    <?= nl2br(sanitize($ch['integrantes'])) ?>
                </div>
            <?php endif; ?>
            <div class="barra-container barra-chapa mt-3" style="height:18px;border-radius:999px;overflow:hidden;">
                <div class="barra barra-chapa-fill" style="width:<?= $pctVotos ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ========== RODAPÉ APURAÇÃO ========== -->
<hr style="margin:32px 0 16px;border:0;border-top:1px solid #E5E7EB;">
<p class="hint" style="text-align:center;color:#6B7280;">
    🔐 Esta apuração é <strong>definitiva e auditável</strong> — todos os votos são vinculados a
    uma unidade, com procuração e data/hora de registro. Não é possível alterar ou excluir votos
    de assembleia encerrada.
</p>
