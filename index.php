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
    // ======================================================================
    // 🛡️ FALLBACK RBAC + TENANT GUARD (camada dupla! se config.php do
    // servidor nao foi atualizado — definimos TUDO aqui. Nunca mais vai dar
    // "Call to undefined function tenant_guard()/isSuperAdmin()").
    // ======================================================================
    if (!function_exists('isLoggedIn')) {
        function isLoggedIn(): bool { return isset($_SESSION['usuario_id']); }
    }
    if (!function_exists('perfilUsuario')) {
        function perfilUsuario(): string {
            if (!isLoggedIn()) return '';
            $p = $_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_tipo'] ?? 'morador');
            if ($p === 'admin') $p = 'super_admin';
            return (string)$p;
        }
    }
    if (!function_exists('isSuperAdmin')) {
        function isSuperAdmin(): bool { return perfilUsuario() === 'super_admin'; }
    }
    if (!function_exists('isAdminCondominio')) {
        function isAdminCondominio(): bool {
            $p = perfilUsuario();
            return $p === 'admin_condominio' || $p === 'super_admin';
        }
    }
    if (!function_exists('isAdmin')) {
        function isAdmin(): bool { return isSuperAdmin() || isAdminCondominio(); }
    }
    if (!function_exists('condominioIdSessao')) {
        function condominioIdSessao(): ?int {
            if (!isLoggedIn()) return null;
            if (isSuperAdmin()) return null;
            $cid = $_SESSION['condominio_id'] ?? null;
            return $cid === null ? null : (int)$cid;
        }
    }
    if (!function_exists('tenant_guard')) {
        function tenant_guard($condominioIdSolicitado): void {
            $condominioIdSolicitado = (int)$condominioIdSolicitado;
            if ($condominioIdSolicitado <= 0) return;
            if (isSuperAdmin()) return;
            $sess = (int)condominioIdSessao();
            if ($sess <= 0) {
                if (function_exists('flashMessage')) {
                    flashMessage('Seu perfil não tem condomínio associado. Contate o suporte.', 'error');
                }
                $url = function_exists('base_url') ? base_url('?route=auth/logout') : '?route=auth/logout';
                header('Location: ' . $url, true, 302); exit;
            }
            if ($sess !== $condominioIdSolicitado) {
                if (function_exists('flashMessage')) {
                    flashMessage('Você não tem permissão para acessar dados de outro condomínio.', 'error');
                }
                $url = function_exists('base_url') ? base_url('?route=assembleia/index') : '?route=assembleia/index';
                header('Location: ' . $url, true, 302); exit;
            }
        }
    }
    if (!function_exists('requireLogin')) {
        function requireLogin(): void {
            if (!isLoggedIn()) {
                $url = function_exists('base_url') ? base_url('?route=auth/login') : '?route=auth/login';
                header('Location: ' . $url, true, 302); exit;
            }
        }
    }
    if (!function_exists('requireSuperAdmin')) {
        function requireSuperAdmin(): void {
            requireLogin();
            if (!isSuperAdmin()) {
                if (function_exists('flashMessage')) {
                    flashMessage('Acesso restrito ao Super Administrador.', 'error');
                }
                $url = function_exists('base_url') ? base_url('?route=assembleia/index') : '?route=assembleia/index';
                header('Location: ' . $url, true, 302); exit;
            }
        }
    }
    if (!function_exists('requireAdmin')) {
        function requireAdmin(): void {
            requireLogin();
            if (!isAdmin()) {
                $url = function_exists('base_url') ? base_url('?route=assembleia/index') : '?route=assembleia/index';
                header('Location: ' . $url, true, 302); exit;
            }
        }
    }

    // ======================================================================
    // 🔐 CAMADA SEGURANÇA — CSRF + Logger estruturado (ISO 27001 / LGPD)
    // Implementado no front controller: DISPONIVEL MESMO SEM config.php atualizado
    // ======================================================================
    if (!function_exists('security_log_exception')) {
        /**
         * Logger estruturado: escreve no error_log (apenas servidor vê).
         * NUNCA exibe stack trace / mensagem raw ao usuário final (LGPD art. 42).
         *
         * @param Throwable $e       Exceção
         * @param string    $contexto Texto curto descrevendo onde ocorreu (ex.: "SuperAdmin->onboarding")
         * @return string UUID curto para repassar ao usuário (para suporte rastrear)
         */
        function security_log_exception(Throwable $e, string $contexto = ''): string {
            $idCurto = substr(bin2hex(random_bytes(6)), 0, 12);
            $log = json_encode([
                'evento'     => 'exception_segurada',
                'ticket'     => $idCurto,
                'contexto'   => $contexto,
                'classe'     => get_class($e),
                'mensagem'   => $e->getMessage(),
                'arquivo'    => $e->getFile(),
                'linha'      => $e->getLine(),
                'uri'        => $_SERVER['REQUEST_URI'] ?? '',
                'usuario_id' => $_SESSION['usuario_id'] ?? null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
                'timestamp'  => date('c'),
                'trace'      => $e->getTraceAsString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            error_log('[SEG-' . $idCurto . '] ' . $log);
            return $idCurto;
        }
    }
    if (!function_exists('security_mensagem_amigavel')) {
        /**
         * Mensagem que pode ser mostrada ao usuário final: nunca traz detalhes internos.
         */
        function security_mensagem_amigavel(string $ticket = ''): string {
            $sufixo = $ticket !== '' ? ' Código de atendimento: <b>' . sanitize($ticket) . '</b>.' : '';
            return 'Ocorreu um erro ao processar sua solicitação. Entre em contato com o suporte.' . $sufixo;
        }
    }
    if (!function_exists('csrf_token_generate')) {
        /**
         * Gera token CSRF por sessão (reutilizável, duração da sessão).
         * OWASP CSRF Prevention Cheat Sheet: Synchronizer Token Pattern (por sessão).
         */
        function csrf_token_generate(): string {
            if (session_status() !== PHP_SESSION_ACTIVE) return '';
            $chave = 'csrf_token_principal';
            $expira = 'csrf_token_principal_expira';
            $agora = time();
            if (empty($_SESSION[$chave]) || empty($_SESSION[$expira]) || (int)$_SESSION[$expira] < $agora) {
                $_SESSION[$chave]  = bin2hex(random_bytes(32));
                $_SESSION[$expira] = $agora + (60 * 60 * 12); // 12h
            }
            return (string)$_SESSION[$chave];
        }
    }
    if (!function_exists('csrf_token')) {
        function csrf_token(): string { return csrf_token_generate(); }
    }
    if (!function_exists('csrf_field')) {
        /**
         * Retorna HTML <input hidden name="csrf_token" value="xxx">.
         * Usar em TODOS os formulários com method POST.
         */
        function csrf_field(): string {
            return '<input type="hidden" name="csrf_token" value="' . sanitize(csrf_token_generate()) . '">';
        }
    }
    if (!function_exists('csrf_token_verify')) {
        /**
         * Valida token CSRF em requisições POST. Falha = redirect + flash erro.
         *
         * @param bool $strict Se true: SEMPRE exige token (use em cadastros, edição, exclusão, alteração de senha).
         *                     Se false: só valida se o token foi enviado.
         * @param string $urlFallback URL destino após erro (default volta para página anterior via HTTP_REFERER ou auth/login).
         * @return void
         */
        function csrf_token_verify(bool $strict = true, string $urlFallback = ''): void {
            $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            // Para métodos de alteração: POST, PUT, PATCH, DELETE exigimos validação (strict padrão).
            $exigir = $strict || in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            if (!$exigir) return;

            $enviado = (string)($_POST['csrf_token'] ?? '');
            $valido  = hash_equals(csrf_token_generate(), $enviado);
            if (!$valido) {
                error_log('[SEG-CSRF] CSRF invalido ou ausente. IP=' . ($_SERVER['REMOTE_ADDR'] ?? '')
                    . ' URI=' . ($_SERVER['REQUEST_URI'] ?? '')
                    . ' Usuario_id=' . ($_SESSION['usuario_id'] ?? 'deslogado'));
                if (function_exists('flashMessage')) {
                    flashMessage('Sua sessão expirou ou a requisição não pôde ser validada. Por favor, recarregue a página e tente novamente.', 'error');
                }
                $fallback = $urlFallback !== ''
                    ? $urlFallback
                    : ($_SERVER['HTTP_REFERER'] ?? (function_exists('base_url') ? base_url('?route=auth/login') : '?route=auth/login'));
                if (!headers_sent()) {
                    header('Location: ' . $fallback, true, 302);
                } else {
                    echo '<script>window.location.href="', sanitize($fallback), '";</script>';
                }
                exit;
            }
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
