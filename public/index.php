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
