<?php
/**
 * instalador_unico.php — 1-CLICK DEPLOY do Sistema de Assembleias
 *
 * =====================================================================
 * COMO USAR (sao 3 passos):
 * =====================================================================
 *  1. Envie APENAS este arquivo (instalador_unico.php) para a pasta
 *     que voce quer o sistema no seu cPanel — ex:
 *        /public_html/
 *        /public_html/condominio/
 *        /public_html/assembleias/
 *     (Use FTP, File Manager do cPanel, etc)
 *
 *  2. Acesse no navegador:
 *        https://seu-dominio.com.br/condominio/instalador_unico.php
 *
 *  3. Clique em [ Iniciar Download e Extracao ]
 *     O script vai:
 *       [✓] Baixar o repositorio GitHub oficial em ZIP (~ 120 KB)
 *       [✓] Extrair para a pasta atual
 *       [✓] Corrigir permissoes (644 arquivos / 755 pastas)
 *       [✓] Apagar arquivos temporarios
 *       [✓] Redirecionar para o install.php (configuracao MySQL)
 *
 * =====================================================================
 * REQUISITOS NO PHP (99% dos cPanels ja tem):
 *   - ZipArchive (extensao zip)    OU    exec() + unzip
 *   - cURL                         OU    file_get_contents com URL fopen
 * =====================================================================
 */

declare(strict_types=1);
$temZip  = class_exists('ZipArchive');
$temCurl = function_exists('curl_init');
$temFGC  = ini_get('allow_url_fopen');
$pasta   = __DIR__;
$zipURL  = 'https://github.com/CristiamMatos-1/condominio/archive/refs/heads/main.zip';
$zipDest = $pasta . '/_update_repo.zip';
$extract = $pasta . '/_update_extracted';
$subPastaRepo = 'condominio-main'; // nome da pasta dentro do ZIP (GitHub main branch)

$msgOk = '';
$msgEr = '';
$log   = [];

// ================= EXECUTA DEPOIS DO POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar'])) {
    @set_time_limit(600);
    @ini_set('memory_limit', '512M');

    function addLog($s) { global $log; $log[] = '[ '.date('H:i:s').' ] '.$s; }

    try {
        // Passo 1: Baixar ZIP
        addLog("1/6 Baixando ZIP do GitHub ($zipURL) ...");
        $zipBin = null;
        if ($temCurl) {
            $ch = curl_init($zipURL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_USERAGENT      => 'CondominioDeploy/1.0',
            ]);
            $zipBin = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http != 200 || !$zipBin) throw new Exception("cURL retornou HTTP $http");
        } elseif ($temFGC) {
            $ctx = stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
            $zipBin = @file_get_contents($zipURL, false, $ctx);
            if (!$zipBin) throw new Exception("file_get_contents falhou");
        } else {
            throw new Exception("Nao tem cURL nem allow_url_fopen — peca ao suporte da hospedagem habilitar um dos dois.");
        }
        $bytes = strlen($zipBin);
        if ($bytes < 20000) throw new Exception("Arquivo ZIP muito pequeno ($bytes bytes) — provavel GitHub bloqueou. Tente novamente em 1 minuto.");
        file_put_contents($zipDest, $zipBin);
        unset($zipBin);
        addLog("    Baixado: $bytes bytes.");

        // Passo 2: Extrair ZIP
        addLog("2/6 Extraindo ZIP ...");
        $ok = false;
        if ($temZip) {
            $za = new ZipArchive;
            if ($za->open($zipDest) === true) {
                // Limpa extracao anterior se existir
                if (is_dir($extract)) { rrmdir($extract); }
                mkdir($extract, 0755, true);
                $za->extractTo($extract);
                $za->close();
                $ok = is_dir($extract . '/' . $subPastaRepo . '/app');
            }
        }
        // Fallback: shell unzip
        if (!$ok) {
            addLog("    ZipArchive indisponivel, tentando unix unzip...");
            @mkdir($extract, 0755, true);
            @exec('cd '.escapeshellarg($extract).' && unzip -o '.escapeshellarg($zipDest).' 2>&1', $out, $code);
            $ok = ($code == 0) && is_dir($extract . '/' . $subPastaRepo . '/app');
        }
        if (!$ok) throw new Exception("Nao consegui extrair o ZIP (nem ZipArchive nem unix unzip). Habilite a extensao Zip no PHP.");
        addLog("    Extraido OK.");

        // Passo 3: Mover arquivos da subpasta do GitHub para a pasta atual
        addLog("3/6 Movendo arquivos para '$pasta' ...");
        $src = $extract . '/' . $subPastaRepo;
        $copiados = mover_tudo($src, $pasta);
        addLog("    $copiados arquivos/pastas movidos.");

        // Passo 4: Corrigir permissoes (cPanel: 644 / 755)
        addLog("4/6 Corrigindo permissoes...");
        $perm = corrigir_permissoes($pasta);
        addLog("    $perm itens ajustados.");

        // Passo 5: Limpeza
        addLog("5/6 Limpando arquivos temporarios...");
        @unlink($zipDest);
        rrmdir($extract);
        // Limpa instalador unico? Deixa para o usuario apagar manual.
        addLog("    Limpeza concluida.");

        // Passo 6: Redireciona para install.php
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
        $uri   = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']));
        $url   = $proto.'://'.$_SERVER['HTTP_HOST'].$uri.'/install.php';
        $msgOk = "Sistema baixado com sucesso!";
        addLog("6/6 Pronto. Redirecionar para: install.php");
        header("Refresh: 3; url=$url");
    }
    catch (Throwable $e) {
        $msgEr = get_class($e).': '.$e->getMessage();
    }
}

function mover_tudo($src, $dst) : int {
    $count = 0;
    if (!is_dir($src)) return 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $pastasCriadas = [];
    foreach ($it as $obj) {
        $rel = substr($obj->getPathname(), strlen($src) + 1);
        $target = $dst . '/' . $rel;
        if ($obj->isDir()) {
            if (!is_dir($target)) { @mkdir($target, 0755, true); $pastasCriadas[] = $target; $count++; }
        } else {
            $dirT = dirname($target);
            if (!is_dir($dirT)) @mkdir($dirT, 0755, true);
            // Nao sobrescreve config.php se o usuario ja editaram
            if (basename($target)==='config.php' && file_exists($target)) continue;
            if (@copy($obj->getPathname(), $target)) $count++;
        }
    }
    return $count;
}
function corrigir_permissoes($root) : int {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $c = 0;
    foreach ($it as $o) {
        $p = $o->getPathname();
        // ignora links e arquivos do sistema
        if (strpos($p,'/.git')!==false || strpos($p,'/node_modules')!==false) continue;
        if ($o->isDir()) { @chmod($p, 0755); }
        else {
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            $mod = ($ext === 'php' || $ext === 'html') ? 0644 : 0644;
            if (in_array(basename($p), ['.htaccess'])) $mod = 0644;
            @chmod($p, $mod);
        }
        $c++;
    }
    // Pasta raiz
    @chmod($root, 0755);
    return $c;
}
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $o) $o->isDir() ? @rmdir($o->getPathname()) : @unlink($o->getPathname());
    @rmdir($dir);
}

?><!DOCTYPE html>
<html lang="pt-br"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalador Único · Sistema de Assembleias</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#F8F9FA;color:#212529;padding:28px 16px 64px}
.w{max-width:780px;margin:0 auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 6px 24px rgba(0,0,0,.06)}
h1{font-size:1.5rem;margin:0 0 4px}h2{font-size:1.05rem;margin:24px 0 10px;color:#2563EB}
.sub{color:#6B7280;margin:0 0 8px;font-size:.92rem}
.labels{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;margin:12px 0 0}
.badge{display:inline-block;padding:4px 10px;border-radius:8px;font-weight:700;font-size:.82rem}
.ok{background:#DCFCE7;color:#16A34A}.bad{background:#FEE2E2;color:#DC2626}
.btn{display:block;width:100%;margin-top:22px;padding:16px;border:0;border-radius:12px;background:#2563EB;color:#fff;font-weight:800;font-size:1.05rem;cursor:pointer}
.btn[disabled]{filter:grayscale(.4) brightness(.85);cursor:not-allowed}
.msg{padding:14px 16px;border-radius:10px;margin:18px 0 0;font-weight:500}
.msg.ok{background:#DCFCE7;color:#15803D;border-left:4px solid #22C55E}
.msg.er{background:#FEE2E2;color:#B91C1C;border-left:4px solid #EF4444}
pre{background:#111827;color:#A5F3FC;padding:14px;border-radius:10px;font-size:12.5px;overflow:auto;margin:14px 0 0}
.pasta{background:#F3F4F6;padding:10px 12px;border-radius:8px;font-family:ui-monospace,Menlo,monospace;font-size:.85rem}
hr{border:0;border-top:1px dashed #E5E7EB;margin:18px 0}
</style></head>
<body>
<div class="w">
<h1>🚀 Instalador Único — Sistema de Assembleias</h1>
<p class="sub">Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong>.</p>
<div class="pasta">📁 Pasta destino: <code><?= htmlspecialchars($pasta) ?></code></div>

<h2>1. Verificação do Ambiente</h2>
<div class="labels">
  <div><strong>cURL (download):</strong></div><div><?php if($temCurl):?><span class="badge ok">INSTALADO</span><?php else:?><span class="badge bad">FALTA</span><?php endif?></div>
  <div><strong>ZipArchive (extração):</strong></div><div><?php if($temZip):?><span class="badge ok">INSTALADO</span><?php else:?><span class="badge bad">FALTA (tentaremos unix unzip)</span><?php endif?></div>
  <div><strong>allow_url_fopen:</strong></div><div><?php if($temFGC):?><span class="badge ok">HABILITADO</span><?php else:?><span class="badge bad">DESABILITADO</span><?php endif?></div>
  <div><strong>Pasta é gravável?</strong></div><div><?php if(is_writable($pasta)):?><span class="badge ok">SIM</span><?php else:?><span class="badge bad">NAO — mude permissões para 755</span><?php endif?></div>
  <div><strong>PHP versão:</strong></div><div><?= phpversion() ?></div>
  <div><strong>Tamanho do ZIP:</strong></div><div>~ 120 KB</div>
</div>

<?php if ($msgOk): ?><div class="msg ok"><?= htmlspecialchars($msgOk) ?><br><small>Redirecionando para install.php em 3 segundos...</small></div><?php endif ?>
<?php if ($msgEr): ?><div class="msg er"><?= htmlspecialchars($msgEr) ?></div><?php endif ?>

<?php if (!empty($log)): ?>
<h2>2. Log da Execução</h2>
<pre><?= htmlspecialchars(implode("\n",$log)) ?></pre>
<?php endif ?>

<hr>
<h2>3. Iniciar Deploy</h2>
<p style="color:#6B7280;margin:0;font-size:.9rem">Clique abaixo para baixar a versão mais recente do GitHub e extrair nesta pasta.</p>
<form method="post">
  <button class="btn" type="submit" name="iniciar" value="1"
    <?= (is_writable($pasta) && ($temCurl || $temFGC)) ? '' : 'disabled' ?>>
      ▶ Baixar e Extrair Sistema Agora
  </button>
</form>
<p style="color:#6B7280;margin-top:16px;font-size:.82rem">
  <strong>Observações:</strong><br>
  • Seu arquivo <code>config.php</code>, se já existir com credenciais válidas, NÃO será sobrescrito.<br>
  • Ao concluir, você cairá no assistente de banco de dados (install.php).<br>
  • Após tudo funcionar, <strong>APAGUE</strong> este arquivo (<code>instalador_unico.php</code>) e <code>install.php</code> por segurança.
</p>
</div>
</body></html>
