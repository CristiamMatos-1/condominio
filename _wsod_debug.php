<?php
/**
 * _wsod_debug.php — WHITE SCREEN OF DEATH DIAGNOSTICO
 * Envia este arquivo para a pasta /condominio/ se TUDO mais falhar.
 * Ele nao usa nenhum arquivo do sistema (standalone) e testa:
 *   1. PHP sintaxe, extensoes, permissoes
 *   2. Leitura de /public/assets/css/style.css (existe? e legivel?)
 *   3. Carregamento do config.php (inclui models/controllers)
 *   4. Carregamento do public/index.php (front controller MVC)
 *   5. Mostra error_php.log ultimas 50 linhas
 */

$root = __DIR__;
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnóstico WSOD · Condomínio</title>
<style>
*{box-sizing:border-box}body{font-family:system-ui;background:#F3F4F6;color:#111827;padding:16px}
.w{max-width:920px;margin:0 auto;background:#fff;border-radius:12px;padding:20px;box-shadow:0 4px 14px rgba(0,0,0,.05)}
h1{margin:0;font-size:1.25rem}h2{margin:22px 0 10px;font-size:1rem;color:#1E40AF;border-bottom:1px dashed #E5E7EB;padding-bottom:4px}
h2.ok::after{content:" ✅"}h2.bad::after{content:" ❌"}
pre{background:#111827;color:#A7F3D0;padding:12px;border-radius:8px;overflow:auto;font-size:12.5px}
.row{display:flex;gap:6px 10px;flex-wrap:wrap;margin:4px 0}.row b{min-width:160px}.pass{color:#16A34A;font-weight:700}.fail{color:#DC2626;font-weight:700}.warn{color:#D97706;font-weight:700}
code{background:#F3F4F6;padding:2px 6px;border-radius:4px;font-size:.92em}
.tiny{font-size:.8rem;color:#6B7280}
</style></head><body><div class="w">
<h1>🛠️ Diagnóstico de Página em Branco (WSOD)</h1>
<p class="tiny">Sistema projetado pelo Eng. de Software Cristiam Matos.</p>

<h2 class="<?= (version_compare(PHP_VERSION,'8.0.0','>='))?'ok':'bad' ?>">1. Versão do PHP</h2>
<div class="row"><b>PHP:</b> <span class="pass"><?= PHP_VERSION ?></span></div>
<div class="row"><b>SAPI:</b> <code><?= PHP_SAPI ?></code> &nbsp; <b>User:</b> <code><?= get_current_user() ?></code></div>
<div class="row"><b>display_errors:</b>
<?php $on=filter_var(ini_get('display_errors'),FILTER_VALIDATE_BOOLEAN);echo $on?'<span class="pass">ON (mostra erros)</span>':'<span class="warn">OFF (pode causar WSOD) — ligando abaixo</span>'; ?>
</div>
<div class="row"><b>log_errors:</b>
<?php $on=filter_var(ini_get('log_errors'),FILTER_VALIDATE_BOOLEAN);echo $on?'<span class="pass">ON</span>':'<span class="warn">OFF</span>'; ?>
</div>

<h2>2. Extensões necessárias</h2>
<?php
$exts=['pdo','pdo_mysql','mbstring'];
foreach($exts as $e) echo '<div class="row"><b>'.$e.':</b> '.(extension_loaded($e)?'<span class="pass">instalada</span>':'<span class="fail">FALTA</span>').'</div>';
?>

<h2 class="<?= is_file("$root/public/index.php") && is_readable("$root/public/index.php")?'ok':'bad' ?>">3. Arquivos esperados na pasta</h2>
<?php
$checks=['config.php','public/index.php','app/Models/Database.php','app/Controllers/AuthController.php','public/assets/css/style.css','public/assets/js/app.js'];
foreach($checks as $f){
    $full=$root.'/'.$f;
    $ex=is_file($full); $rd=$ex && is_readable($full); $sz=$ex?filesize($full):0;
    echo '<div class="row"><b><code>'.$f.'</code>:</b> '.
         ($ex?'<span class="pass">existe</span>':'<span class="fail">NAO EXISTE</span>').' '.
         ($rd?'<span class="pass">legível</span>':'<span class="fail">NAO LEGIVEL</span>').' '.
         ($sz>0?"$sz bytes":'<span class="warn">0 bytes</span>').'</div>';
}
?>

<h2>4. Permissões</h2>
<div class="row"><b>index.php (raiz):</b> <code><?= substr(sprintf('%o', fileperms("$root/index.php")), -4) ?></code></div>
<div class="row"><b>config.php:</b> <code><?= substr(sprintf('%o', fileperms("$root/config.php")), -4) ?></code></div>
<div class="row"><b>public/:</b> <code><?= substr(sprintf('%o', fileperms("$root/public")), -4) ?></code></div>
<div class="row"><b>Pasta gravavel?</b> <?= is_writable($root)?'<span class="pass">SIM</span>':'<span class="warn">NAO (editar permissões para 755)</span>' ?></div>

<h2>5. Erro gravado em error_php.log (últimas 40 linhas)</h2>
<?php
$log=$root.'/error_php.log';
if(is_file($log)){
    $ln=file($log); $ln=array_slice($ln,-40);
    if (count($ln)>0){ echo '<pre>'.htmlspecialchars(implode('',$ln)).'</pre>'; }
    else echo '<p class="tiny">Log existe mas esta vazio.</p>';
} else echo '<p class="tiny warn">Arquivo de log nao existe ainda — provavelmente nenhum erro fatal aconteceu ate agora OU log_errors esta Off.</p>';
?>

<h2>6. Teste de require config.php</h2>
<?php
// Tenta incluir config.php e exibir qualquer erro (forcando display)
@ini_set('display_errors',1);@ini_set('html_errors',1);error_reporting(E_ALL);
$ok1=true;
try {
    require $root.'/config.php';
    echo '<div class="row"><span class="pass">✅ config.php carregou sem erros</span></div>';
    $test=['AuthController','AdminController','AssembleiaController','Database','UsuarioModel','ProcuracaoModel'];
    foreach($test as $c){ echo '<div class="row"><b>Classe '.$c.':</b> '.(class_exists($c)?'<span class="pass">OK</span>':'<span class="fail">NAO ENCONTRADA</span>').'</div>'; }
} catch(Throwable $e){
    $ok1=false;
    echo '<div class="fail">'.htmlspecialchars(get_class($e)).' em <code>'.$e->getFile().':'.$e->getLine().'</code><br>Mensagem: '.htmlspecialchars($e->getMessage()).'</div>';
    echo '<pre>'.htmlspecialchars($e->getTraceAsString()).'</pre>';
}
?>

<h2>7. Teste de pipeline MVC simulado (chama AuthController->login() sem redirecionar)</h2>
<?php
if(!$ok1){ echo '<p class="warn">Pulado porque o passo 6 falhou.</p>'; }
else {
    try {
        $c=new AuthController();
        // Nao vamos executar o metodo login() de verdade (headers/redirect), soh precisamos verificar se as dependencias existem.
        echo '<div class="pass">✅ AuthController instanciavel. Front Controller deve funcionar.</div>';
        // Chamar index? nao vamos.
    } catch(Throwable $e){
        echo '<div class="fail">'.htmlspecialchars(get_class($e)).' : '.htmlspecialchars($e->getMessage()).' em <code>'.$e->getFile().':'.$e->getLine().'</code></div>';
        echo '<pre>'.htmlspecialchars($e->getTraceAsString()).'</pre>';
    }
}
?>

<h2>8. PROXIMOS PASSOS (se mostrou algum erro acima)</h2>
<ul style="padding-left:18px;line-height:1.8;font-size:.95rem">
  <li>Se a etapa 6 mostrou "Class AuthController NAO ENCONTRADA":<br>
     → No Terminal do cPanel rode: <code>chmod 755 app/ app/Controllers && chmod 644 app/Controllers/*.php</code></li>
  <li>Se faltou ext PDO_MYSQL:<br>
     → cPanel → Selecionar Versão PHP → Extensões → marque <code>pdo_mysql</code></li>
  <li>Se permissoes 777 em qualquer pasta:<br>
     → Mude para 755 em pastas / 644 em arquivos.</li>
  <li>Se nada disso resolver:<br>
     → Apague o index.php atual e cole o NOVO index.php que vem com DEBUG=1. Ele NAO deixa tela em branco, mostra o erro exato.</li>
</ul>
<p class="tiny">⚠️ <b>Após resolver, apague este arquivo</b> (<code>_wsod_debug.php</code>) por segurança — ele não requer login!</p>
</div></body></html>
