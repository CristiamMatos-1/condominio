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

// ==========================================================================
// 🔧 AUTO-PATCH DE SCHEMA (idempotente, 0 risco)
// Objetivo: evita o erro PDOException "Unknown column 'email' em condominios"
// SEM precisar rodar SQL manual no phpMyAdmin.
// Como funciona: verifica se colunas email/telefone existem. Se NAO existirem,
// executa ALTER TABLE via INFORMATION_SCHEMA + PREPARE (seguro, funciona mesmo
// que a migration 005 nao tenha sido rodada).
// Performance: $_SESSION['patch_005_aplicado'] evita consultar o schema
// a cada request (1 consulta por sessão apenas).
// ==========================================================================
if (!function_exists('condominio_aplicar_patch_005_email_telefone')) {
    function condominio_aplicar_patch_005_email_telefone(): void {
        $CHAVE_SESSAO = 'patch_005_email_telefone_aplicado';
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION[$CHAVE_SESSAO])) return;
        try {
            // Usa PDO direto (evita depender de Database::getInstance() que ainda
            // pode nao ter sido carregado em alguns cenarios do bootstrap raiz).
            $dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo  = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // 1) email ?
            $stmt = $pdo->prepare("SELECT COUNT(*) AS tem
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'condominios'
                  AND COLUMN_NAME  = 'email'");
            $stmt->execute();
            $temEmail = (int)($stmt->fetchColumn() ?? 0);
            if ($temEmail <= 0) {
                $pdo->exec("ALTER TABLE `condominios`
                    ADD COLUMN `email` VARCHAR(150) NULL DEFAULT NULL AFTER `cep`");
            }

            // 2) telefone ?
            $stmt2 = $pdo->prepare("SELECT COUNT(*) AS tem
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'condominios'
                  AND COLUMN_NAME  = 'telefone'");
            $stmt2->execute();
            $temTelefone = (int)($stmt2->fetchColumn() ?? 0);
            if ($temTelefone <= 0) {
                $pdo->exec("ALTER TABLE `condominios`
                    ADD COLUMN `telefone` VARCHAR(20) NULL DEFAULT NULL AFTER `email`");
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION[$CHAVE_SESSAO] = 1;
        } catch (Throwable $e) {
            // Se falhar por qualquer motivo (ex.: permissoes de ALTER), NAO
            // quebra a aplicacao — apenas loga silenciosamente. O erro de
            // coluna desconhecida ainda vai aparecer se as colunas realmente
            // nao existirem (e entao o usuario pode rodar a migration manual).
            if (function_exists('security_log_exception')) {
                security_log_exception($e, 'auto-patch-005');
            } else {
                @error_log('[PATCH-005-FALHA] ' . $e->getMessage());
            }
        }
    }
}
try {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        condominio_aplicar_patch_005_email_telefone();
    }
} catch (Throwable $eIgnoradoPatch005) {
    // Nao faz nada — evita qualquer possibilidade de WSOD.
}

// ==========================================================================
// 🔧 RBAC — SYNC FORÇADO DE PERFIL NA SESSÃO (anti-redirect bug "Minha Assembleia")
//
// Problema histórico: helpers em config.php podem estar desatualizados e lerem
// apenas $_SESSION['usuario_tipo'], enquanto a aplicação grava
// $_SESSION['usuario_perfil']. Em bancos migrados da migration 001/003,
// usuários admin legados tinham tipo='admin' e perfil=NULL. O resultado:
// requireAdmin() falhava silenciosamente e redirecionava TODAS as rotas de
// gestão para ?route=assembleia/index ("Minha Assembleia").
//
// Resolução: executado a 100% dos requests, corrige divergências na sessão.
// ==========================================================================
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
if (!empty($_SESSION['usuario_id'])) {
    $pPerfil = (string)($_SESSION['usuario_perfil'] ?? '');
    $pTipo   = (string)($_SESSION['usuario_tipo']   ?? '');
    // Normaliza 'admin' (legado) para 'super_admin' em QUALQUER lado.
    if ($pPerfil === 'admin') $pPerfil = 'super_admin';
    if ($pTipo   === 'admin') $pTipo   = 'super_admin';
    // Resolve lado vazio: se perfil=='' e tipo!='', usa tipo. E vice versa.
    if ($pPerfil === '' && $pTipo !== '')   $pPerfil = $pTipo;
    if ($pTipo   === '' && $pPerfil !== '') $pTipo   = $pPerfil;
    // Garante consistência DUPLICANDO o valor em ambas chaves (redundância segura).
    $_SESSION['usuario_perfil'] = $pPerfil;
    $_SESSION['usuario_tipo']   = $pTipo;
    // Marcador de sync rodou (debug):
    if (!isset($_SESSION['rbac_sync_versao'])) $_SESSION['rbac_sync_versao'] = 2;
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
