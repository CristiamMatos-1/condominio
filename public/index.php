<?php

require_once __DIR__ . '/../config.php';

/**
 * FALLBACK de helpers — garante que funcoes existam MESMO que o config.php
 * do servidor esteja desatualizado (credenciais de banco nao podem ser
 * sobrescritas, entao nao forçamos upload de config.php!)
 *
 * Definidos apenas SE NAO existirem (if !function_exists / !defined).
 */
if (!defined('URL_BASE')) {
    $s  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $sn   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php'), '/');
    $sn   = rtrim(str_replace('\\', '/', $sn), '/');
    // Remove "/public" do final pois URL_BASE é raiz do sistema
    if (substr($sn, -7) === '/public') $sn = substr($sn, 0, -7);
    define('URL_BASE', $s . '://' . $host . ($sn ?: ''));
}
if (!function_exists('base_url')) {
    function base_url($path = ''): string {
        return rtrim(URL_BASE, '/') . '/' . ltrim((string)$path, '/');
    }
}
if (!function_exists('asset_url')) {
    /**
     * Resolve caminho SEMPRE para /public/assets/ mesmo sem rewrite.
     */
    function asset_url(string $relPath): string {
        $relPath = ltrim($relPath, '/');
        if (strpos($relPath, 'assets/') === 0) {
            return rtrim(URL_BASE, '/') . '/public/' . $relPath;
        }
        return rtrim(URL_BASE, '/') . '/public/assets/' . $relPath;
    }
}
if (!function_exists('sanitize')) {
    function sanitize($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
// ==========================================================================
// 🛡️ FALLBACK RBAC + TENANT GUARD (camada dupla! se config.php do servidor
// nao foi atualizado manualmente — definimos TUDO aqui para evitar
// "Call to undefined function tenant_guard() / isSuperAdmin()" etc.
// Obs.: Chamamos flashMessage()/base_url() só se elas existirem.
// ==========================================================================
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
// DUPLICADO no public/index.php: garante que mesmo acessando por
// /public/index.php diretamente (sem passar pelo bootstrap raiz),
// os helpers de segurança ESTARAO LÁ. 100% fallbacks.
// ======================================================================
if (!function_exists('security_log_exception')) {
    function security_log_exception(Throwable $e, string $contexto = ''): string {
        try { $idCurto = substr(bin2hex(random_bytes(6)), 0, 12); }
        catch (Throwable $t) { $idCurto = substr(uniqid('seg', true), 0, 12); }
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
        @error_log('[SEG-' . $idCurto . '] ' . ($log ?: 'json_encode falhou'));
        return $idCurto;
    }
}
if (!function_exists('security_mensagem_amigavel')) {
    function security_mensagem_amigavel(string $ticket = ''): string {
        $sufixo = $ticket !== '' ? ' Código de atendimento: <b>' . sanitize($ticket) . '</b>.' : '';
        return 'Ocorreu um erro ao processar sua solicitação. Entre em contato com o suporte.' . $sufixo;
    }
}
if (!function_exists('csrf_token_generate')) {
    function csrf_token_generate(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) return '';
        $chave  = 'csrf_token_principal';
        $expira = 'csrf_token_principal_expira';
        $agora  = time();
        if (empty($_SESSION[$chave]) || empty($_SESSION[$expira]) || (int)$_SESSION[$expira] < $agora) {
            try { $token = bin2hex(random_bytes(32)); }
            catch (Throwable $t) { $token = hash('sha256', uniqid((string)mt_rand(), true)); }
            $_SESSION[$chave]  = $token;
            $_SESSION[$expira] = $agora + (60 * 60 * 12);
        }
        return (string)$_SESSION[$chave];
    }
}
if (!function_exists('csrf_token')) {
    function csrf_token(): string { return csrf_token_generate(); }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . sanitize(csrf_token_generate()) . '">';
    }
}
if (!function_exists('csrf_token_verify')) {
    function csrf_token_verify(bool $strict = true, string $urlFallback = ''): void {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $exigir = $strict || in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if (!$exigir) return;

        $enviado = (string)($_POST['csrf_token'] ?? '');
        $valido  = hash_equals(csrf_token_generate(), $enviado);
        if (!$valido) {
            @error_log('[SEG-CSRF] CSRF invalido ou ausente. IP=' . ($_SERVER['REMOTE_ADDR'] ?? '')
                . ' URI=' . ($_SERVER['REQUEST_URI'] ?? '')
                . ' Usuario_id=' . ($_SESSION['usuario_id'] ?? 'deslogado'));
            if (function_exists('flashMessage')) {
                flashMessage('Sua sessão expirou ou a requisição não pôde ser validada. Por favor, recarregue a página e tente novamente.', 'error');
            }
            $fallback = $urlFallback !== ''
                ? $urlFallback
                : ($_SERVER['HTTP_REFERER'] ?? (function_exists('base_url') ? base_url('?route=auth/login') : '?route=auth/login'));
            if (!headers_sent()) header('Location: ' . $fallback, true, 302);
            else echo '<script>window.location.href="', sanitize($fallback), '";</script>';
            exit;
        }
    }
}

$route = $_GET['route'] ?? 'auth/login';
$route = trim($route, '/');

$parts = explode('/', $route);
$controllerName = ucfirst($parts[0] ?? 'auth') . 'Controller';
$actionName = $parts[1] ?? 'index';
$params = array_slice($parts, 2);

if (!class_exists($controllerName)) {
    http_response_code(404);
    die('Página não encontrada: Controller ' . $controllerName);
}

$controller = new $controllerName();

if (!method_exists($controller, $actionName)) {
    http_response_code(404);
    die('Página não encontrada: Action ' . $actionName);
}

call_user_func_array([$controller, $actionName], $params);
