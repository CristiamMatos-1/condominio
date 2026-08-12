<?php
/**
 * index.php na RAIZ do projeto — FALLBACK BOOTSTRAP
 *
 * Motivo: Muitos cPanels / hospedagens tem AllowOverride = None e o
 * .htaccess eh completamente ignorado. Nesse caso:
 *   - Nao ha rewrite
 *   - "Index of /condominio" eh exibido
 *   - Qualquer rota interna retorna 404
 *
 * Este arquivo resolve TUDO:
 *   1. Se o usuario acessar a pasta diretamente (sem rota), reescreve
 *      a URL para que a aplicacao use a mesma logica do public/.htaccess
 *   2. Inclui o public/index.php normalmente (o Front Controller real)
 *   3. Injeta a varivel correta $_GET['route'] a partir de REQUEST_URI
 * =================================================================
 * Nota: Este arquivo NUNCA conflita com o public/index.php original.
 * =================================================================
 */

define('CONDOMINIO_BOOTSTRAP', true);

// ---- (1) Descobre a rota a partir da URL, de forma segura ----
if (!isset($_GET['route']) || trim((string)$_GET['route']) === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $scriptDir = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']));
    $scriptDir = rtrim($scriptDir,'/');
    if ($scriptDir && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $uri = ltrim($uri,'/');
    // Remove /public/ prefixo se o usuario digitou manualmente
    if (strpos($uri, 'public/') === 0) {
        $uri = substr($uri, 7);
    }
    // Remove arquivo real se existir (ex: index.html)
    if ($uri === '' || $uri === 'index.php' || $uri === 'index.html') {
        $_GET['route'] = 'auth/login';
    } else {
        $_GET['route'] = $uri;
    }
}
else {
    $_GET['route'] = (string)$_GET['route'];
}

// Limpa PATH_INFO para nao conflitar
$_SERVER['PATH_INFO'] = '/' . ltrim($_GET['route'], '/');

// ---- (2) Roda o Front Controller verdadeiro (public/index.php) ----
require __DIR__ . '/public/index.php';
