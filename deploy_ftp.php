<?php
/**
 * deploy_ftp.php — Sobe TODO o projeto para o cPanel via FTP em 2 passos
 * EXECUTE NO SEU COMPUTADOR (terminal):   php deploy_ftp.php
 *
 * - Nao precisa GitHub Actions
 * - Nao precisa Composer
 * - Usa extensao ftp_nativa do PHP (presente em 99% dos ambientes)
 * - Envia apenas os arquivos necessarios
 * - Ignora .git, config.php, etc
 *
 * Basta preencher as 5 VARIAVEIS ABAIXO e rodar.
 */

// ======================== PREENCHA AQUI ========================
$FTP_HOST = 'ftp.coninfoms.com.br';     // seu host FTP (cPanel -> Contas FTP)
$FTP_USER = 'deploy@coninfoms.com.br';  // usuario FTP
$FTP_PASS = '';                         // senha FTP
$FTP_PORT = 21;                         // 21 = FTP  /  990 = FTPS
$FTP_DIR  = '/public_html/condominio';  // pasta de destino no servidor. Exemplos:
                                        // /public_html            -> raiz do site
                                        // /public_html/condominio -> subpasta
                                        // /assembleias            -> subdominio
// ======================== FIM CONFIGURACAO ====================

set_time_limit(300);
ini_set('display_errors', 1);

$IGNORAR = [
    '.git', '.gitignore', '.github', '.DS_Store',
    'config.php', 'error_php.log', 'vendor', 'node_modules',
    basename(__FILE__),            // nao envia o deploy_ftp.php em si
    '.dev_check_rotas.php',
];

echo "\n==========================================\n";
echo "  DEPLOY FTP para cPanel\n";
echo "==========================================\n";
echo "HOST : $FTP_HOST\n";
echo "USER : $FTP_USER\n";
echo "PORT : $FTP_PORT\n";
echo "DIR  : $FTP_DIR\n\n";

// 1) Conectar
echo "[1/4] Conectando via FTP... ";
$ftp = ($FTP_PORT == 990)
    ? @ftp_ssl_connect($FTP_HOST, $FTP_PORT, 10)
    : @ftp_connect($FTP_HOST, $FTP_PORT, 10);
if (!$ftp) { echo "FALHOU. Nao consegui abrir conexao TCP.\n"; exit(1); }
echo "TCP OK. ";

echo "Efetuando login... ";
if (!@ftp_login($ftp, $FTP_USER, $FTP_PASS)) {
    echo "FALHOU. Usuario/senha incorretos.\n"; exit(1);
}
echo "OK.\n";
ftp_pasv($ftp, true);

// 2) Garantir pasta destino
echo "[2/4] Garantindo pasta destino $FTP_DIR ... ";
$partes = array_values(array_filter(explode('/', $FTP_DIR)));
$caminho = '';
foreach ($partes as $p) {
    $caminho .= '/' . $p;
    if (@ftp_chdir($ftp, $caminho)) continue;
    if (!@ftp_mkdir($ftp, $caminho)) {
        echo "FALHOU ao criar $caminho. Verifique permissoes.\n"; exit(1);
    }
    // Tenta aplicar 755 em pastas novas
    @ftp_chmod($ftp, 0755, $caminho);
}
if (!@ftp_chdir($ftp, $FTP_DIR)) {
    echo "FALHOU ao entrar em $FTP_DIR.\n"; exit(1);
}
echo "OK.\n";

// 3) Listar arquivos locais para upload
echo "[3/4] Escaneando arquivos locais... ";
$RAIZ = __DIR__;
$arquivos = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($RAIZ, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$total = 0; $totalBytes = 0;
foreach ($it as $obj) {
    $path = $obj->getPathname();
    $rel  = str_replace($RAIZ . DIRECTORY_SEPARATOR, '', $path);
    $rel  = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    // Ignora pastas/arquivos da lista
    $ignora = false;
    foreach ($IGNORAR as $ig) {
        if (stripos($rel, $ig) === 0 || basename($rel) === $ig) { $ignora = true; break; }
    }
    if ($ignora) continue;
    if ($obj->isDir()) {
        // Garante pasta no remoto
        if (!@ftp_chdir($ftp, $FTP_DIR . '/' . $rel)) {
            @ftp_mkdir($ftp, $FTP_DIR . '/' . $rel);
            @ftp_chmod($ftp, 0755, $FTP_DIR . '/' . $rel);
        }
        continue;
    }
    $arquivos[] = $rel;
    $totalBytes += $obj->getSize();
    $total++;
}
echo "$total arquivos (" . round($totalBytes/1024, 1) . " KB).\n";

// 4) Upload em lote
echo "[4/4] Upload (" . count($arquivos) . " arquivos)...\n";
$ok = 0; $fail = 0;
$ultimoPrint = 0;
foreach ($arquivos as $i => $rel) {
    $local = $RAIZ . '/' . $rel;
    $remoto = $FTP_DIR . '/' . $rel;
    // Chmod por extensao
    $perms = is_dir($local) ? 0755 : 0644;
    if (pathinfo($rel, PATHINFO_EXTENSION) === 'php') $perms = 0644;
    if (substr($rel, 0, 10) === 'public/.ht') $perms = 0644;
    $ret = @ftp_put($ftp, $remoto, $local, FTP_BINARY);
    if ($ret) {
        @ftp_chmod($ftp, $perms, $remoto);
        $ok++;
    } else {
        $fail++;
        echo "   FALHOU: $rel\n";
    }
    $pct = (int)(($i + 1) / count($arquivos) * 100);
    if ($pct - $ultimoPrint >= 10) {
        echo "   ... $pct% ($ok ok / $fail erro)\n";
        $ultimoPrint = $pct;
    }
}
ftp_close($ftp);

echo "\n==========================================\n";
echo "  DEPLOY FINALIZADO\n";
echo "  Enviados com sucesso: $ok\n";
echo "  Falhas: $fail\n";
echo "==========================================\n";
echo "\nPROXIMOS PASSOS:\n";
echo "  1) Acesse: https://coninfoms.com.br/condominio/install.php\n";
echo "     (ajuste a URL conforme a pasta de destino)\n";
echo "  2) Siga o assistente de instalacao do banco de dados.\n";
echo "  3) Apos instalar, DELETE o arquivo install.php por seguranca.\n\n";
