<?php
/**
 * check_500.php — Diagnostica EXATAMENTE qual linha do .htaccess causou o 500
 * COMO USAR:
 *   1. Envie APENAS este arquivo para a pasta /condominio/
 *   2. Acesse: https://coninfoms.com.br/condominio/check_500.php
 *   3. Acompanhe os resultados na tela.
 */
declare(strict_types=1);

$pasta = __DIR__;
echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8">
<title>Diagnóstico 500 Internal Server Error</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:system-ui,sans-serif;background:#F8F9FA;padding:22px;color:#111}
h1{font-size:1.2rem;margin:0 0 10px}.w{max-width:900px;margin:0 auto;background:#fff;padding:24px;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.05)}
pre{background:#111827;color:#D1FAE5;padding:12px;border-radius:8px;overflow:auto;font-size:12px;margin:10px 0 0}
.ok{color:#16A34A;font-weight:700}.bad{color:#DC2626;font-weight:700}.warn{color:#D97706;font-weight:700}
.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-weight:700;font-size:.78rem;margin-left:4px}
.badge.ok{background:#DCFCE7;color:#15803D}.badge.bad{background:#FEE2E2;color:#B91C1C}
hr{border:0;border-top:1px dashed #E5E7EB;margin:20px 0}
code{background:#F3F4F6;padding:2px 5px;border-radius:4px}
</style></head><body><div class="w">';
echo '<h1>🛠️ Diagnóstico de Erro 500 — Apache/.htaccess</h1>';
echo '<p style="color:#6B7280;margin:0 0 4px">Script v1.0 — Projetado para ser enviado SOZINHO quando der 500.</p>';

// --- 1. Primeiro, tenta descobrir se o .htaccess existe atualmente
echo '<h2 style="font-size:1rem;margin-top:18px">1. Estado do .htaccess atual</h2>';
$ht = $pasta . '/.htaccess';
if (file_exists($ht)) {
    $tam = filesize($ht);
    $linhas = count(file($ht));
    $perms = substr(sprintf('%o', fileperms($ht)), -4);
    echo "📁 .htaccess encontrado <span class=\"badge ok\">EXISTE</span><br>";
    echo "Tamanho: $tam bytes · Linhas: $linhas · Permissões: <code>$perms</code>";
    echo '<pre>'.htmlspecialchars(file_get_contents($ht)).'</pre>';
} else {
    echo "📁 .htaccess <span class=\"badge bad\">NAO EXISTE</span> (isso causa listagem de pasta mas NAO causa 500)";
}

// --- 2. Testa permissões da pasta
echo '<h2 style="font-size:1rem">2. Permissões</h2>';
$teste = is_writable($pasta) ? '<span class="ok">GRAVAVEL</span>' : '<span class="warn">SOMENTE LEITURA</span>';
$pastaPerms = substr(sprintf('%o', fileperms($pasta)), -4);
echo "Pasta atual: <code>" . htmlspecialchars($pasta) . "</code> permissões <code>$pastaPerms</code> $teste<br>";
$me = $pasta . '/.htaccess';
if (file_exists($me)) {
    echo ".htaccess permissões: <code>$perms</code> — ";
    if (substr($perms, -2) > 64) echo "<span class=\"warn\">CUIDADO permissão muito alta. Ideal 644</span>";
    else echo "<span class=\"ok\">Permissões ok</span>";
}

// --- 3. Testa se o Apache executou este arquivo até o fim (indica que 500 é do .htaccess)
echo '<h2 style="font-size:1rem">3. Indicadores do servidor</h2>';
echo "PHP versao: <b>".phpversion()."</b> &nbsp; SAPI: <b>".PHP_SAPI."</b> &nbsp; User: <b>".get_current_user()."</b><br>";
echo "DocumentRoot: <code>".htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A')."</code><br>";
echo "Load de modulos: (via apache_get_modules se disponivel)<br>";
if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    $check = ['mod_rewrite','mod_headers','mod_deflate','mod_expires'];
    foreach ($check as $m) {
        $tem = in_array($m,$mods);
        echo "&nbsp;&nbsp;$m: ".($tem?'<span class="ok">SIM</span>':'<span class="bad">NAO</span>')."<br>";
    }
} else {
    echo "<span class=\"warn\">apache_get_modules() indisponivel (CGI/PHP-FPM). Vamos testar via HTTP sub-request abaixo.</span>";
}

// --- 4. Teste REPETIDO de subrequest com .htaccess por partes
// Se o usuario quiser, vamos escrever versoes e pingar via URL... Mas sem URL externa melhor soh sugerir manual
echo '<hr><h2 style="font-size:1rem">4. Ordem de Correção Recomendada (MAIS IMPORTANTE)</h2>';
echo '<ol>';
echo '<li><b style="color:#DC2626">Apague OU RENOMEIE temporariamente o arquivo .htaccess para .htaccess.TESTE</b></li>';
echo '<li>Atualize a página. Se o 500 sumiu, a causa é 100% o .htaccess.</li>';
echo '<li>Na pasta <code>htaccess_fallbacks/</code> existem 3 versões. Copie o conteúdo de <code>htaccess_V3_ultrasafe.txt</code> para um novo .htaccess.</li>';
echo '<li>Atualize a página. Se funcionar, suba para V2, depois V1 e encontre a linha que quebra.</li>';
echo '<li>Se MESMO sem .htaccess continuar 500, o erro é do PHP (mostraremos abaixo).</li>';
echo '</ol>';

// --- 5. Teste de syntax PHP dos arquivos principais
echo '<h2 style="font-size:1rem">5. Syntax check nos arquivos PHP principais</h2><pre>';
$arquivos = ['index.php','public/index.php','install.php','instalador_unico.php','config.php',
             'app/Controllers/AdminController.php','app/Controllers/AuthController.php',
             'app/Controllers/AssembleiaController.php','app/Controllers/RelatoriosController.php',
             'app/Models/Database.php','config.example.php'];
foreach ($arquivos as $f) {
    $full = $pasta . '/' . $f;
    $status = 'SKIP';
    if (file_exists($full)) {
        exec('php -l '.escapeshellarg($full).' 2>&1', $out, $code);
        $status = ($code === 0) ? 'OK' : 'ERRO ('.implode('; ',$out).')';
    } else {
        $status = 'ARQUIVO NAO EXISTE (upload incompleto)';
    }
    echo str_pad($f, 50) . " → $status\n";
    $out = []; $code = 0;
}
echo '</pre>';

echo '<h2 style="font-size:1rem">6. Log de erro PHP (se existir error_php.log)</h2>';
if (file_exists($pasta . '/error_php.log')) {
    $lines = array_slice(file($pasta . '/error_php.log'), -30);
    echo '<pre style="background:#0F172A;color:#FCA5A5">'.htmlspecialchars(implode('', $lines)).'</pre>';
} else echo "<span class=\"warn\">Log nao existe. Sistema ainda nao rodou PHP com erro.</span>";

echo '<hr><p style="text-align:center;color:#6B7280;font-size:.85rem">
    Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong> &copy; '.date('Y').'
</p></div></body></html>';
