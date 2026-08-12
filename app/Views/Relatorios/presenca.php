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
            <div class="logo-relatorio">🏛️</div>
            <h1 class="relatorio-titulo"><?= sanitize($titulo) ?></h1>
            <h2 class="relatorio-subtitulo"><?= sanitize($subtitle) ?></h2>
            <div class="relatorio-info">
                <p><strong>Condomínio:</strong> <?= sanitize($assembleia['condominio_nome']) ?></p>
                <p><strong>Título:</strong> <?= sanitize($assembleia['titulo']) ?></p>
                <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($assembleia['data_assembleia'])) ?></p>
                <p><strong>Horário:</strong> <?= substr($assembleia['horario'], 0, 5) ?></p>
                <?php if (!empty($assembleia['endereco'])): ?>
                    <p><strong>Local:</strong> <?= sanitize($assembleia['endereco']) ?>, <?= sanitize($assembleia['cidade'] ?? '') ?> - <?= sanitize($assembleia['estado'] ?? '') ?></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="resumo-presenca">
            <div class="resumo-item">
                <span class="resumo-num"><?= count($presencas) ?></span>
                <span class="resumo-lbl">Presentes</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-num"><?= (int)$totalUnidades ?></span>
                <span class="resumo-lbl">Unidades no Condomínio</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-num">
                    <?= $totalUnidades > 0 ? round((count($presencas) / $totalUnidades) * 100, 1) : 0 ?>%
                </span>
                <span class="resumo-lbl">Presença</span>
            </div>
        </div>

        <div class="tabela-container">
            <table class="tabela-impressao">
                <thead>
                    <tr>
                        <th style="width: 5%">Nº</th>
                        <th style="width: 28%">Nome do Condômino</th>
                        <th style="width: 10%">Lote</th>
                        <th style="width: 10%">Casa</th>
                        <th style="width: 20%">Representante (se houver)</th>
                        <th style="width: 12%">Nº Procuração</th>
                        <th style="width: 15%">Assinatura / Check-in</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($presencas) === 0): ?>
                        <tr>
                            <td colspan="7" class="empty-row">Nenhuma presença registrada</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($presencas as $idx => $p): ?>
                            <tr>
                                <td class="text-center"><?= $idx + 1 ?></td>
                                <td>
                                    <strong><?= sanitize($p['dono_nome']) ?></strong><br>
                                    <small>CPF: <?= sanitize($p['dono_cpf']) ?></small>
                                </td>
                                <td class="text-center"><?= sanitize($p['lote']) ?></td>
                                <td class="text-center"><?= sanitize($p['casa']) ?></td>
                                <td>
                                    <?php if (!empty($p['via_procuracao'])): ?>
                                        <strong><?= sanitize($p['usuario_presente_nome']) ?></strong><br>
                                        <small>CPF: <?= sanitize($p['usuario_presente_cpf']) ?></small>
                                    <?php else: ?>
                                        <em class="text-muted">— Presente (Titular) —</em>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($p['procuracao_num'])): ?>
                                        <code><?= sanitize($p['procuracao_num']) ?></code>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-center assinatura-col">
                                    <div class="checkin-info">
                                        <div class="checkin-ok">✅ CHECK-IN DIGITAL</div>
                                        <div class="checkin-data"><?= date('d/m/Y H:i', strtotime($p['data_checkin'])) ?></div>
                                    </div>
                                    <div class="linha-assinatura">
                                        <small>ou Assinatura:</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <footer class="relatorio-footer">
            <div class="assinatura-sindico">
                <div class="linha"></div>
                <p>Síndico / Responsável pela Assembleia</p>
            </div>
            <div class="data-geracao">
                <p>Documento gerado em: <?= date('d/m/Y H:i:s') ?></p>
                <p class="sistema-info">Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong></p>
            </div>
        </footer>
    </div>
</body>
</html>
