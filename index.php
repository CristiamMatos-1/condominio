<?php
/**
 * index.php na RAIZ — FALLBACK UNIVERSAL (versao segura, SEM WSOD)
 *
 * Motivo da reescrita: versao anterior crashava com WSOD (página em branco)
 * quando o PHP encontrava um erro fatal, pois display_errors estava Off.
 * Esta versao:
 * - FORCA exibicao de erros (para o deploy)
 * - try/catch em TUDO que pode falhar
 * - Serve estaticos de /public/assets/ mesmo sem rewrite
 * - Encaminha para o MVC public/index.php
 */

// ============== [ COLOCAR 0 PARA DESATIVAR DEBUG DEPOIS ] ==============
if (!defined('CONDOMINIO_DEBUG')) define('CONDOMINIO_DEBUG', 1);

// ---- Forca exibicao de erros (elimina pagina em branco!) ----
if (CONDOMINIO_DEBUG) {
    @ini_set('display_errors',        1);
    @ini_set('display_startup_errors',1);
    @ini_set('html_errors',         1);
    @ini_set('log_errors',          1);
    @ini_set('error_log',           __DIR__ . '/error_php.log');
    error_reporting(E_ALL);
    if (!headers_sent()) {
        header('X-Debug-Condominio: enabled');
    }
}
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        if (CONDOMINIO_DEBUG && !headers_sent()) {
            header_remove('Content-Type');
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(500);
        }
        echo "<div style=\"font-family:system-ui;max-width:860px;margin:2rem auto;padding:1.25rem 1.5rem;border:1px solid #FCA5A5;background:#FEF2F2;border-radius:12px;color:#991B1B\">";
        echo "<p style=\"margin:0 0 .4rem;font-weight:800;font-size:1.05rem\">🔥 Erro Fatal do PHP</p>";
        echo "<p style=\"margin:.2rem 0\"><b>Arquivo:</b> ".htmlspecialchars($err['file']).":{$err['line']}</p>";
        echo "<p style=\"margin:.2rem 0\"><b>Mensagem:</b> ".htmlspecialchars($err['message'])."</p>";
        echo "<p style=\"font-size:.85rem;color:#6B7280\">Envie esta mensagem para o Engenheiro de Software Cristiam Matos.</p>";
        echo "</div>";
    }
});

// ============================================================
// Resolve subpasta e URI da requisicao
// ============================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptDir  = $_SERVER['SCRIPT_NAME'] ?? '/';
if (is_string($requestUri) && false !== ($p = strpos($requestUri, '?'))) {
    $requestUri = substr($requestUri, 0, $p);
}
$requestUri = '/' . ltrim((string)$requestUri, '/');
$scriptDir  = rtrim(str_replace('\\', '/', dirname((string)$scriptDir)), '/');
if ($scriptDir !== '' && strpos($requestUri, $scriptDir) === 0) {
    $requestUri = substr($requestUri, strlen($scriptDir));
}
$requestUri = '/' . ltrim($requestUri, '/');

// ============================================================
// (A) Servir arquivo estatico de /public/ se existir
// ============================================================
if (preg_match('#\.(css|js|png|jpe?g|gif|svg|ico|webp|pdf|woff2?|ttf|eot|map|txt|xml|html)$#i', $requestUri, $mExt)) {
    $ext = strtolower($mExt[1]);
    $candidato = __DIR__ . '/public' . $requestUri;

    if (is_file($candidato) && is_readable($candidato)) {
        $types = [
            'css'=>'text/css; charset=utf-8',
            'js'=>'application/javascript; charset=utf-8',
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
        if (!headers_sent()) {
            header_remove('Cache-Control');
            header('Cache-Control: public, max-age=2592000');
            header_remove('Pragma');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
            header('Content-Length: ' . filesize($candidato));
            header('Content-Type: ' . $ctype);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($candidato)) . ' GMT');
            http_response_code(200);
        }
        readfile($candidato);
        exit;
    }

    if ($ext !== 'html') {
        // Asset nao existe. Nao retorna MVC (risco de loop). Mostra 404 simples.
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Asset nao encontrado: " . htmlspecialchars($requestUri) . "\n";
        echo "Caminho verificado: " . htmlspecialchars($candidato);
        exit;
    }
}

// ============================================================
// (B) Encaminhar para o MVC public/index.php
// ============================================================
try {
    /**
     * 👇 ORDEM CORRETA para evitar "Cannot redeclare base_url():
     *    1) Primeiro CARREGAMOS config.php (pode definir as funcoes
     *       SEM !function_exists — é o caso do config do servidor).
     *    2) DEPOIS definimos fallbacks com if (!function_exists)
     *       — estes soh entram em ação se config nao tiver a fn.
     *
     * (Nao usamos $mvc ainda, apenas carregamos config cedo!)
     */
    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException("Arquivo config.php nao encontrado em " . htmlspecialchars($configPath));
    }
    require_once $configPath;

    /**
     * FALLBACK helpers — caso config.php do servidor esteja desatualizado e
     * nao defina asset_url()/base_url()/sanitize() — definimos aqui TAMBEM
     * (dupla camada de seguranca).
     *
     * Observacao: se config.php jah os definiu (mesmo sem checagem),
     * os blocos abaixo NAO executam — nunca mais vai dar "redeclare".
     */
    if (!defined('URL_BASE')) {
        $s  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $sn   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        define('URL_BASE', $s . '://' . $host . ($sn ?: ''));
    }
    if (!function_exists('base_url')) {
        function base_url($path = ''): string {
            return rtrim(URL_BASE, '/') . '/' . ltrim((string)$path, '/');
        }
    }
    if (!function_exists('asset_url')) {
        function asset_url(string $relPath): string {
            $relPath = ltrim($relPath, '/');
            if (strpos($relPath, 'assets/') === 0) return rtrim(URL_BASE, '/') . '/public/' . $relPath;
            return rtrim(URL_BASE, '/') . '/public/assets/' . $relPath;
        }
    }
    if (!function_exists('sanitize')) {
        function sanitize($v): string {
            return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        }
    }

    // Garante que $_GET['route'] existe
    if (empty($_GET['route']) || !is_string($_GET['route'])) {
        $rota = $requestUri;
        $rota = ltrim($rota, '/');
        if (strpos($rota, 'public/') === 0) $rota = substr($rota, 7);
        if ($rota === '' || $rota === 'index.php' || $rota === 'index.html') {
            $_GET['route'] = 'auth/login';
        } else {
            $_GET['route'] = $rota;
        }
    }
    $_SERVER['PATH_INFO'] = '/' . ltrim((string)$_GET['route'], '/');
    $_SERVER['PHP_SELF']  = '/' . ltrim((string)$_GET['route'], '/');

    if (!defined('CONDOMINIO_BOOTSTRAP')) define('CONDOMINIO_BOOTSTRAP', true);

    $mvc = __DIR__ . '/public/index.php';
    if (!is_file($mvc)) {
        throw new RuntimeException("Arquivo public/index.php nao encontrado em " . htmlspecialchars($mvc));
    }
    require $mvc;
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (CONDOMINIO_DEBUG) {
        echo "<div style=\"font-family:system-ui;max-width:860px;margin:2rem auto;padding:1.25rem 1.5rem;border:1px solid #FDBA74;background:#FFF7ED;border-radius:12px;color:#92400E\">";
        echo "<p style=\"margin:0 0 .4rem;font-weight:800;font-size:1.05rem\">⚠️ Exceção do Sistema</p>";
        echo "<p style=\"margin:.2rem 0\"><b>Tipo:</b> ".htmlspecialchars(get_class($e))."</p>";
        echo "<p style=\"margin:.2rem 0\"><b>Arquivo:</b> ".htmlspecialchars($e->getFile()).":{$e->getLine()}</p>";
        echo "<p style=\"margin:.2rem 0\"><b>Mensagem:</b> ".htmlspecialchars($e->getMessage())."</p>";
        echo "<details style=\"margin-top:.8rem;\"><summary style=\"cursor:pointer;font-weight:700\">Stack trace</summary><pre style=\"font-size:12px;overflow:auto;background:#111827;color:#A7F3D0;padding:12px;border-radius:8px\">".
             htmlspecialchars($e->getTraceAsString())."</pre></details>";
        echo "<p style=\"font-size:.85rem;color:#6B7280;margin-top:1rem\">Mostrando erro porque DEBUG=1. Depois de resolvido, defina CONDOMINIO_DEBUG=0 no topo do index.php.</p>";
        echo "</div>";
    } else {
        echo "<div style=\"font-family:system-ui;max-width:480px;margin:3rem auto;padding:1.5rem;border:1px solid #E5E7EB;background:#fff;border-radius:12px;text-align:center\">";
        echo "<p style=\"font-weight:800;margin:0 0 .5rem;font-size:1.1rem;color:#111827\">Ops, algo deu errado.</p>";
        echo "<p style=\"color:#6B7280;margin:0 0 1rem;font-size:.92rem\">Ocorreu um erro interno ao carregar o sistema.</p>";
        echo "<a href=\"?route=auth/login\" style=\"display:inline-block;padding:.6rem 1.1rem;background:#1E40AF;color:#fff;text-decoration:none;border-radius:8px;font-weight:700\">Tentar novamente</a>";
        echo "</div>";
    }
}
