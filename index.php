<?php
/**
 * index.php na RAIZ do projeto — FALLBACK UNIVERSAL (bootsrap)
 *
 * Resolve:
 *   - Serve assets ESTÁTICOS que existem em /public/ (css, js, img) mesmo sem rewrite
 *   - Senão, encaminha a rota para o Front Controller MVC em public/index.php
 *
 * 100% independente de .htaccess ou mod_rewrite.
 */
declare(strict_types=1);

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptDir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

// Remove a subpasta (ex: /condominio) da URI
if ($scriptDir && strpos($requestUri, $scriptDir) === 0) {
    $requestUri = substr($requestUri, strlen($scriptDir));
}
$requestUri = '/' . ltrim($requestUri, '/');

// ============================================================
// (A) TENTAR SERVIR ARQUIVO ESTATICO DENTRO DE /public/
// ============================================================
if (preg_match('#\.(css|js|png|jpe?g|gif|svg|ico|webp|pdf|woff2?|ttf|eot|map|txt|xml)$#i', $requestUri, $mExt)) {
    $candidato = __DIR__ . '/public' . $requestUri;
    if (is_file($candidato) && is_readable($candidato)) {
        // Servir corretamente com Content-Type + Cache (30 dias)
        $ext = strtolower($mExt[1]);
        $types = [
            'css'=>'text/css; charset=utf-8',
            'js' =>'application/javascript; charset=utf-8',
            'json'=>'application/json; charset=utf-8',
            'svg'=>'image/svg+xml',
            'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
            'gif'=>'image/gif','webp'=>'image/webp','ico'=>'image/x-icon',
            'pdf'=>'application/pdf','txt'=>'text/plain; charset=utf-8',
            'xml'=>'application/xml; charset=utf-8',
            'html'=>'text/html; charset=utf-8',
            'woff'=>'font/woff','woff2'=>'font/woff2',
            'ttf'=>'font/ttf','eot'=>'application/vnd.ms-fontobject',
            'map'=>'application/json; charset=utf-8',
        ];
        $ctype = $types[$ext] ?? 'application/octet-stream';
        header_remove('Cache-Control');
        header('Cache-Control: public, max-age=2592000');
        header_remove('Pragma');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time()+2592000).' GMT');
        header('Content-Length: ' . filesize($candidato));
        header('Content-Type: ' . $ctype);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($candidato)).' GMT');
        http_response_code(200);
        readfile($candidato);
        exit;
    }
    // Se chegou aqui e tem extensao de asset = 404 claro (nao existe em lugar nenhum)
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Asset não encontrado: " . htmlspecialchars($requestUri));
}

// ============================================================
// (B) SENAO: PASSAR PARA O CONTROLADOR MVC — public/index.php
// ============================================================
define('CONDOMINIO_BOOTSTRAP', true);

// Extrai a rota de REQUEST_URI ou $_GET['route']
if (empty($_GET['route'])) {
    $rota = $requestUri;
    // Remove leading barras, /public/ etc
    $rota = ltrim($rota, '/');
    if (strpos($rota, 'public/') === 0) $rota = substr($rota, 7);
    if ($rota === '' || $rota === 'index.php' || $rota === 'index.html') {
        $_GET['route'] = 'auth/login';
    } else {
        $_GET['route'] = $rota;
    }
}

$_SERVER['PATH_INFO'] = '/' . ltrim((string)$_GET['route'], '/');

require __DIR__ . '/public/index.php';
