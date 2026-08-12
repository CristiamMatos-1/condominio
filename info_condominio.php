<?php
/**
 * info_condominio.php — DIAGNOSTICO RAPIDO de 403 / Ambiente
 *
 * Use esse arquivo QUANDO aparecer Forbidden 403.
 * Basta enviar por FTP / File Manager para a pasta do projeto
 * e acessar: https://seu-dominio.com.br/condominio/info_condominio.php
 *
 * Depois de usar, APAGUE esse arquivo por seguranca.
 */

$titulo = 'Diagnóstico do Ambiente — Sistema de Assembleias';
?><!DOCTYPE html>
<html lang="pt-br"><head>
<meta charset="utf-8"><title><?= $titulo ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:system-ui,sans-serif;background:#F8F9FA;margin:0;padding:24px;color:#212529}
.w{max-width:880px;margin:auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,.05)}
h1{margin:0 0 4px;font-size:22px}h2{font-size:16px;margin:22px 0 10px;color:#2563EB}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px}
.l{font-weight:600;color:#4B5563}
.ok{color:#16A34A;font-weight:700}.bad{color:#DC2626;font-weight:700}.warn{color:#D97706;font-weight:700}
pre{background:#111827;color:#D1FAE5;padding:14px;border-radius:8px;overflow:auto;font-size:12px;margin:0}
.card{border:1px solid #E5E7EB;border-radius:10px;padding:14px;margin-top:10px}
code{background:#F3F4F6;padding:2px 6px;border-radius:4px;font-size:12.5px}
</style></head><body><div class="w">
<h1>🛠️ <?= $titulo ?></h1>
<p style="color:#6B7280;margin:0">Gere esse resultado e envie para o Eng. Cristiam Matos se houver erro 403.</p>

<h2>1. Ambiente PHP / Apache</h2>
<div class="card grid">
<div class="l">PHP versão</div><div class="ok"><?= phpversion() ?></div>
<div class="l">SAPI (tipo de servidor)</div><div><?= PHP_SAPI ?></div>
<div class="l">Sistema Operacional</div><div><?= PHP_OS ?></div>
<div class="l">Usuário dono do processo</div><div><?= get_current_user() ?></div>
<div class="l">Document Root</div><div><code><?= $_SERVER['DOCUMENT_ROOT'] ?? 'N/A' ?></code></div>
<div class="l">Pasta do arquivo atual</div><div><code><?= __DIR__ ?></code></div>
<div class="l">URL Base (auto-detect)</div><div><code id="ub">-</code></div>
<div class="l">HTTPS ativo?</div><div><?=
  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'
   || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')==='https')
  ? '<span class="ok">SIM</span>' : '<span class="warn">NAO (HTTP puro)</span>'
?></div>
</div>

<h2>2. Módulos Apache e Permissões</h2>
<div class="card grid">
<?php
function tem($ext, $m = null) {
    $ok = $m ? extension_loaded($ext) && $m : extension_loaded($ext);
    echo '<div class="l">Ext. ' . htmlspecialchars($ext) . '</div><div>'
         . ($ok ? '<span class="ok">OK</span>' : '<span class="bad">FALTA</span>') . '</div>';
}
tem('pdo'); tem('pdo_mysql'); tem('mysqli');
tem('ftp'); tem('json'); tem('mbstring');
tem('curl'); tem('zip');
?>
<div class="l">AllowOverride / .htaccess lido?</div>
<div>
<?php
// Testa se .htaccess esta sendo processado
$me = __DIR__ . '/.htaccess';
if (file_exists($me)) {
    $t = file_get_contents($me);
    $bytes = strlen($t);
    echo '<span class="ok">ARQUIVO EXISTE (' . $bytes . ' bytes)</span>';
} else {
    echo '<span class="bad">.htaccess NAO EXISTE nesta pasta</span>';
}
?>
</div>
<div class="l">Pasta gravavel?</div>
<div><?= is_writable(__DIR__)
    ? '<span class="ok">SIM (bom para install.php)</span>'
    : '<span class="warn">NAO (install.php vai fazer download do config.php)</span>'
?></div>
</div>

<h2>3. Teste de Conexão PDO MySQL (preencha manualmente)</h2>
<div class="card">
<form method="post">
<div class="grid">
<div><label class="l">Host</label><input style="width:100%;padding:8px;border:1px solid #D1D5DB;border-radius:6px" name="h" value="<?= htmlspecialchars($_POST['h'] ?? 'localhost') ?>"></div>
<div><label class="l">Banco</label><input style="width:100%;padding:8px;border:1px solid #D1D5DB;border-radius:6px" name="n" value="<?= htmlspecialchars($_POST['n'] ?? '') ?>"></div>
<div><label class="l">Usuario</label><input style="width:100%;padding:8px;border:1px solid #D1D5DB;border-radius:6px" name="u" value="<?= htmlspecialchars($_POST['u'] ?? '') ?>"></div>
<div><label class="l">Senha</label><input type="password" style="width:100%;padding:8px;border:1px solid #D1D5DB;border-radius:6px" name="p" value="<?= htmlspecialchars($_POST['p'] ?? '') ?>"></div>
</div>
<button style="margin-top:14px;padding:10px 18px;border:0;background:#2563EB;color:#fff;border-radius:8px;font-weight:700;cursor:pointer" type="submit">▶ Testar Conexão MySQL</button>
</form>
<?php if ($_SERVER['REQUEST_METHOD']==='POST'): ?>
<pre style="margin-top:14px"><?php
try {
    $h = $_POST['h']; $n = $_POST['n']; $u = $_POST['u']; $p = $_POST['p'];
    $pdo = new PDO("mysql:host=$h;dbname=$n;charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $r = $pdo->query("SELECT VERSION() as v")->fetch();
    echo "CONEXAO MYSQL OK!\nVersão do servidor: {$r['v']}\n";
    // Verifica tabelas do sistema
    $tem = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTabelas encontradas (".count($tem)."):\n  ".implode("\n  ", $tem);
    $necessarias = ['condominios','usuarios','unidades','procuracoes','assembleias','pautas','chapas'];
    $falta = array_diff($necessarias, $tem);
    echo $falta ? "\n\nFALTAM TABELAS:\n  ".implode("\n  ",$falta)."\n\nRodar install.php para importar." : "\n\nTodas tabelas essenciais existem. ✅";
} catch (Throwable $e) {
    echo "FALHOU: ".get_class($e)."\n".$e->getMessage();
}
?></pre>
<?php endif; ?>
</div>

<h2>4. Resumo Final Recomendado</h2>
<div class="card" id="resumo">
<ul>
<li>Se houver 403 Forbidden: <strong>RENAME o arquivo .htaccess para .htaccess.bak</strong> e recarregue a página. Se carregar, problema é 100% o htaccess.</li>
<li>Se PDO MySQL faltar: habilite a extensão no cPanel → Select PHP Version.</li>
<li>Por fim, acesse <code>install.php</code> para concluir a instalação.</li>
</ul>
</div>
<p style="text-align:center;color:#6B7280;margin-top:30px;font-size:13px">
Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong> · <?= date('Y') ?>
</p>
</div>
<script>
const u = location.protocol + '//' + location.host + location.pathname.replace(/[^/]*\.php$/,'').replace(/\/$/,'');
document.getElementById('ub').textContent = u;
</script>
</body></html>
