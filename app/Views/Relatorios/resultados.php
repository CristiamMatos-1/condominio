<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($titulo) ?> - <?= sanitize($assembleia['titulo']) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/print.css') ?>">
</head>
<body class="relatorio-body">
    <div class="print-controls no-print">
        <button onclick="window.print()" class="btn btn-primary btn-lg">🖨️ Imprimir / Salvar PDF</button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg">❌ Fechar</button>
    </div>

    <div class="relatorio-container">
        <header class="relatorio-header">
            <div class="logo-relatorio">📊</div>
            <h1 class="relatorio-titulo"><?= sanitize($titulo) ?></h1>
            <h2 class="relatorio-subtitulo"><?= sanitize($subtitle) ?></h2>
            <div class="relatorio-info">
                <p><strong>Condomínio:</strong> <?= sanitize($assembleia['condominio_nome']) ?></p>
                <p><strong>Título:</strong> <?= sanitize($assembleia['titulo']) ?></p>
                <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($assembleia['data_assembleia'])) ?></p>
                <p><strong>Horário:</strong> <?= substr($assembleia['horario'], 0, 5) ?></p>
                <p><strong>Status Final:</strong> 
                    <span class="badge badge-secondary"><?= sanitize($assembleia['status']) ?></span>
                </p>
            </div>
        </header>

        <?php if (count($pautas) > 0): ?>
        <section class="relatorio-secao">
            <h3 class="secao-titulo">📑 RESULTADO DAS MATÉRIAS / PAUTAS</h3>
            <?php foreach ($pautas as $p): ?>
                <?php
                    $total = max(1, (int)$p['total_votos']);
                    $pctSim = round((((int)$p['votos_sim']) / $total) * 100);
                    $pctNao = 100 - $pctSim;
                ?>
                <div class="pauta-resultado">
                    <div class="pauta-resultado-head">
                        <span class="pauta-ordem">#<?= (int)$p['ordem'] ?></span>
                        <strong><?= sanitize($p['titulo']) ?></strong>
                        <span class="badge badge-lg 
                            <?= $p['status'] === 'Aprovada' ? 'badge-success' : 
                               ($p['status'] === 'Rejeitada' ? 'badge-danger' : 'badge-warning') ?>">
                            <?= strtoupper(sanitize($p['status'])) ?>
                        </span>
                    </div>
                    <?php if (!empty($p['descricao'])): ?>
                        <p class="pauta-desc-rel"><?= sanitize($p['descricao']) ?></p>
                    <?php endif; ?>
                    <div class="resultado-barra mt-2">
                        <div class="barra-container barra-grande">
                            <div class="barra barra-sim" style="width: <?= $pctSim ?>%"></div>
                            <div class="barra barra-nao" style="width: <?= $pctNao ?>%"></div>
                        </div>
                        <div class="resultado-numeros resultado-grande">
                            <span class="sim">✅ SIM: <?= (int)$p['votos_sim'] ?> (<?= $pctSim ?>%)</span>
                            <span class="nao">❌ NÃO: <?= (int)$p['votos_nao'] ?> (<?= $pctNao ?>%)</span>
                            <span class="total">TOTAL: <?= (int)$p['total_votos'] ?> votos</span>
                        </div>
                    </div>

                    <?php if (count($p['detalhes_votos']) > 0): ?>
                        <details class="detalhes-votos">
                            <summary>Ver detalhamento de votos (<?= count($p['detalhes_votos']) ?>)</summary>
                            <table class="tabela-impressao tabela-sm mt-2">
                                <thead>
                                    <tr>
                                        <th>Lote/Casa</th>
                                        <th>Condômino</th>
                                        <th>Voto</th>
                                        <th>Votante</th>
                                        <th>Procuração</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($p['detalhes_votos'] as $v): ?>
                                        <tr>
                                            <td>Lote <?= sanitize($v['lote']) ?> / Casa <?= sanitize($v['casa']) ?></td>
                                            <td><?= sanitize($v['dono_nome']) ?></td>
                                            <td class="text-center">
                                                <span class="badge badge-sm <?= $v['voto'] === 'Sim' ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= $v['voto'] === 'Sim' ? '✅ SIM' : '❌ NÃO' ?>
                                                </span>
                                            </td>
                                            <td><?= sanitize($v['votante_nome']) ?></td>
                                            <td><?= !empty($v['via_procuracao']) ? 'Sim' : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if (count($resultadoChapas) > 0): ?>
        <section class="relatorio-secao">
            <h3 class="secao-titulo">🗳️ RESULTADO DA ELEIÇÃO DE DIRETORIA</h3>
            <?php
                $totalVotosChapa = 0;
                foreach ($resultadoChapas as $rc) { $totalVotosChapa += (int)$rc['total_votos']; }
                $totalVotosChapa = max(1, $totalVotosChapa);
            ?>
            <?php foreach ($resultadoChapas as $idx => $ch): ?>
                <?php $pct = round(((int)$ch['total_votos'] / $totalVotosChapa) * 100); ?>
                <div class="chapa-resultado <?= $idx === 0 && (int)$ch['total_votos'] > 0 ? 'chapa-vencedora-borda' : '' ?>">
                    <div class="chapa-head-grande">
                        <div class="colocacao">
                            <?= $idx === 0 && (int)$ch['total_votos'] > 0 ? '🏆 1º LUGAR (VENCEDORA)' : ($idx + 1) . 'º lugar' ?>
                        </div>
                        <strong class="chapa-nome-grande"><?= sanitize($ch['nome_chapa']) ?></strong>
                        <span class="votos-count-grande"><?= (int)$ch['total_votos'] ?> votos — <?= $pct ?>%</span>
                    </div>
                    <?php if (!empty($ch['integrantes'])): ?>
                        <div class="chapa-integrantes-grande">
                            <h5>Integrantes:</h5>
                            <?= nl2br(sanitize($ch['integrantes'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="barra-container barra-grande barra-chapa mt-2">
                        <div class="barra barra-chapa-fill" style="width: <?= $pct ?>%"></div>
                    </div>

                    <?php if (!empty($detalhesVotosChapas[$ch['id']]) && count($detalhesVotosChapas[$ch['id']]) > 0): ?>
                        <details class="detalhes-votos mt-2">
                            <summary>Votos recebidos (<?= count($detalhesVotosChapas[$ch['id']]) ?>)</summary>
                            <table class="tabela-impressao tabela-sm mt-2">
                                <thead>
                                    <tr>
                                        <th>Lote/Casa</th>
                                        <th>Condômino</th>
                                        <th>Votante</th>
                                        <th>Procuração</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalhesVotosChapas[$ch['id']] as $v): ?>
                                        <tr>
                                            <td>Lote <?= sanitize($v['lote']) ?> / Casa <?= sanitize($v['casa']) ?></td>
                                            <td><?= sanitize($v['dono_nome']) ?></td>
                                            <td><?= sanitize($v['votante_nome']) ?></td>
                                            <td><?= !empty($v['via_procuracao']) ? 'Sim' : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="total-geral mt-2">
                <strong>TOTAL DE VOTOS VÁLIDOS: <?= $totalVotosChapa ?></strong>
            </div>
        </section>
        <?php endif; ?>

        <footer class="relatorio-footer mt-4">
            <div class="assinatura-sindico">
                <div class="linha"></div>
                <p>Síndico / Presidente da Assembleia</p>
            </div>
            <div class="data-geracao">
                <p>Documento gerado em: <?= date('d/m/Y H:i:s') ?></p>
                <p class="sistema-info">Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong></p>
            </div>
        </footer>
    </div>
</body>
</html>
