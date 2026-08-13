<?php
// ==========================================================================
// 🔧 CONSTANTE GLOBAL DE DEBUG (PRODUÇÃO == 0)
//
// Tem que ficar ANTES de qualquer coisa para:
//   - Layout header_admin.php não imprimir o banner DEBUG RBAC laranja.
//   - Front controller não exibir stack trace de Exception pro usuário final.
// Obs: mantemos log_errors=1, então tudo continua gravado em error_php.log
// para análise posterior (não perdemos visibilidade nenhuma).
// ==========================================================================
if (!defined('CONDOMINIO_DEBUG')) define('CONDOMINIO_DEBUG', 0);

// ---- Forca exibicao de erros APENAS para LOG (display_errors = 0 em prod) ----
if (CONDOMINIO_DEBUG) {
    @ini_set('display_errors',        1);
    @ini_set('display_startup_errors',1);
    @ini_set('html_errors',         1);
    error_reporting(E_ALL);
} else {
    @ini_set('display_errors',        0);
    @ini_set('display_startup_errors',0);
    @ini_set('html_errors',           0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}
@ini_set('log_errors', 1);
@ini_set('error_log',  dirname(__DIR__) . '/error_php.log');

// ==========================================================================
// 🚨 CAMADA 0: SESSÃO + SYNC RBAC (ANTES DE TUDO)
//
// Objetivo: garantimos que $_SESSION estah disponivel e consistente MESMO
// antes de incluir o vendor/config.php do servidor. Resolve definitivamente
// o bug em que "todo clique do menu redireciona para Minha Assembleia"
// causado por helpers RBAC antigos (em config.php servidor) lendo a sessao
// ANTES de nós termos normalizado usuario_tipo / usuario_perfil.
// ==========================================================================
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
// Sync RBAC HARDENED (normaliza usuario_perfil e usuario_tipo em AMBAS chaves).
// Tambem define flag rbac_helper_familia = 'core_v3' para o debug banner.
if (!isset($GLOBALS['RBAC_CORE_V3_SINCRONIZADO'])) {
    $GLOBALS['RBAC_CORE_V3_SINCRONIZADO'] = 1;
    if (!empty($_SESSION['usuario_id'])) {
        $pPerfil = (string)($_SESSION['usuario_perfil'] ?? '');
        $pTipo   = (string)($_SESSION['usuario_tipo']   ?? '');
        // Normaliza 'admin' legado para 'super_admin'
        if ($pPerfil === 'admin') $pPerfil = 'super_admin';
        if ($pTipo   === 'admin') $pTipo   = 'super_admin';
        // Resolve lado vazio (usamos o que tiver valor)
        if ($pPerfil === '' && $pTipo !== '')   $pPerfil = $pTipo;
        if ($pTipo   === '' && $pPerfil !== '') $pTipo   = $pPerfil;
        // Se ainda assim for vazio, verifica raw_db e snapshot de login
        if ($pPerfil === '') {
            $raw1 = (string)($_SESSION['usuario_perfil_raw_db'] ?? '');
            $raw2 = (string)($_SESSION['usuario_tipo_raw_db']   ?? '');
            if ($raw1 === 'admin') $raw1 = 'super_admin';
            if ($raw2 === 'admin') $raw2 = 'super_admin';
            $pPerfil = $raw1 !== '' ? $raw1 : ($raw2 !== '' ? $raw2 : 'morador');
            $pTipo   = $pPerfil;
        }
        $_SESSION['usuario_perfil'] = $pPerfil;
        $_SESSION['usuario_tipo']   = $pTipo;
        $_SESSION['rbac_sync_versao'] = 3;
    }
    $_SESSION['rbac_helper_familia'] = 'core_v3';
}

// ==========================================================================
// 🚨 CAMADA 0.5: DEFINIR RBAC HELPERS COMO GLOBAIS (VERSÃO AUTAL) VIA
//               $GLOBALS['RBAC_FNS']. Depois do config.php carregado,
//               se o PHP permitir sobrescrever, nao faremos redeclare.
//               Em vez disso: wrappers de funcoes internas do roteador
//               vao chamar a $GLOBALS versao core_v3, nao a do config.
//
// Obs.: NAO definimos base_url()/asset_url()/etc. antes pois config.php do
//       servidor pode declara-los SEM if(!function_exists) → Fatal.
// ==========================================================================
$GLOBALS['RBAC_CORE_V3'] = [
    'isLoggedIn' => static function(): bool {
        return isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0;
    },
    'perfilUsuario' => static function(): string {
        if (empty($_SESSION['usuario_id'])) return '';
        $p = (string)($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_tipo'] ?? 'morador'));
        if ($p === 'admin') $p = 'super_admin';
        return $p;
    },
    'isSuperAdmin' => static function(): bool {
        return ($GLOBALS['RBAC_CORE_V3']['perfilUsuario'])() === 'super_admin';
    },
    'isAdminCondominio' => static function(): bool {
        $p = ($GLOBALS['RBAC_CORE_V3']['perfilUsuario'])();
        return $p === 'admin_condominio' || $p === 'super_admin';
    },
    'isAdmin' => static function(): bool {
        return ($GLOBALS['RBAC_CORE_V3']['isSuperAdmin'])()
            || ($GLOBALS['RBAC_CORE_V3']['isAdminCondominio'])();
    },
    'condominioIdSessao' => static function(): ?int {
        if (!($GLOBALS['RBAC_CORE_V3']['isLoggedIn'])()) return null;
        if (($GLOBALS['RBAC_CORE_V3']['isSuperAdmin'])()) return null;
        $cid = $_SESSION['condominio_id'] ?? null;
        return $cid === null ? null : (int)$cid;
    }
];

require_once __DIR__ . '/../config.php';

// ==========================================================================
// 🔧 CAMADA 2: FALLBACK HELPERS GERAIS (URL, VIEW, REDIRECT, FLASH)
//
// Primeiro os que são usados DIRETAMENTE pelas views (asset_url em login.php
// linha 21, sanitize em todo lugar). Se config.php declarou eles, estes
// blocos pulam (if!fn_exists).
// ==========================================================================
if (!defined('URL_BASE')) {
    $s  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $sn   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php')), '/');
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
     * Resolve caminho absoluto para /public/assets/{$relPath}.
     * Exemplo: asset_url('css/style.css') → /condominio/public/assets/css/style.css
     */
    function asset_url(string $relPath): string {
        $relPath = ltrim($relPath, '/');
        if (strpos($relPath, 'assets/') === 0) return rtrim(URL_BASE, '/') . '/public/' . $relPath;
        return rtrim(URL_BASE, '/') . '/public/assets/' . $relPath;
    }
}
if (!function_exists('sanitize')) {
    function sanitize($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('flashMessage')) {
    function flashMessage(?string $msg = null, string $type = 'info') {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) @session_start();
        if ($msg === null) {
            $m = $_SESSION['_flash']['message'] ?? null;
            $t = $_SESSION['_flash']['type']    ?? 'info';
            unset($_SESSION['_flash']);
            return $m ? ['message' => $m, 'type' => $t] : null;
        }
        $_SESSION['_flash'] = ['message' => $msg, 'type' => $type];
        return null;
    }
}
if (!function_exists('redirect')) {
    function redirect(string $url, int $code = 302): void {
        if (!headers_sent()) { header('Location: ' . $url, true, $code); exit; }
        echo '<script>window.location.href="', sanitize($url), '";</script>';
        exit;
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

// ==========================================================================
// 🔧 CAMADA 2.75: CARREGAR RBAC CORE V3 HELPER ÚNICO
//
// Este helper:
//   - Define funções PREFIXADAS rbac_* (nunca conflitam).
//   - Tenta sobrescrever funções globais ANTIGAS (isSuperAdmin, requireAdmin,
//     tenant_guard etc.) via runkit7/uopz/runkit se extensões estiverem lá.
//   - Seta $_SESSION['RBAC_CORE_V3_APLICADO'] e família.
//
// Precisa carregar ANTES dos controllers (pois __construct deles chama
// rbac_require_admin() e todas chamadas internas agora usam rbac_*).
// ==========================================================================
$rbacCoreV3Helper = __DIR__ . '/../app/Helpers/rbac_core_v3.php';
if (is_file($rbacCoreV3Helper)) require_once $rbacCoreV3Helper;
unset($rbacCoreV3Helper);

// ==========================================================================
// 🔧 CAMADA RBAC OVERRIDE (POST-CONFIG, ZERO RISCO de Fatal)
//
// Problema: config.php do servidor pode ter declarado isAdmin/perfilUsuario/
// requireAdmin (direto, sem if(!function_exists) → redeclara Fatal OU via
// wrapper mas com leitura incorreta (tipo='admin' != 'super_admin').
// Solucao: sobrescrever COMPORTAMENTO nao atraves de redeclaracao de funcao,
// mas reescrevendo $_SESSION com perfil consistente e adicionando 2 guards
// FORCADOS ANTES de instanciar o controller.
// ==========================================================================
if (!empty($_SESSION['usuario_id'])) {
    $pPerfil = (string)($_SESSION['usuario_perfil'] ?? '');
    $pTipo   = (string)($_SESSION['usuario_tipo']   ?? '');
    if ($pPerfil === 'admin') $pPerfil = 'super_admin';
    if ($pTipo   === 'admin') $pTipo   = 'super_admin';
    if ($pPerfil === '' && $pTipo !== '')   $pPerfil = $pTipo;
    if ($pTipo   === '' && $pPerfil !== '') $pTipo   = $pPerfil;
    $_SESSION['usuario_perfil'] = $pPerfil;
    $_SESSION['usuario_tipo']   = $pTipo;
    $_SESSION['rbac_sync_versao'] = 3;
}
// ------------------------------------------------------------------
// GUARD ANTES DO DISPATCH:
//   Se a rota eh admin/* ou superadmin/*, CHECAGEM MANUAL DE PERMISSAO
//   (ignora completamente requireAdmin do config.php).
// ------------------------------------------------------------------
$_ROTA_ATUAL = $_GET['route'] ?? 'auth/login';
$_ROTA_ATUAL = trim((string)$_ROTA_ATUAL, '/');
$_prefixo = strtolower(explode('/', $_ROTA_ATUAL)[0] ?? '');
if (in_array($_prefixo, ['admin', 'superadmin'], true)) {
    $_logado  = isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0;
    $_perfil  = (string)($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_tipo'] ?? ''));
    if ($_perfil === 'admin') $_perfil = 'super_admin';
    $_isAdminOrSuper = $_perfil === 'super_admin' || $_perfil === 'admin_condominio';
    $_isSuperOnly    = $_perfil === 'super_admin';

    if (!$_logado) {
        $_url = function_exists('base_url') ? base_url('?route=auth/login') : '?route=auth/login';
        header('Location: ' . $_url, true, 302); exit;
    }
    if (!$_isAdminOrSuper) {
        if (function_exists('flashMessage')) {
            flashMessage('Você não tem permissão para acessar o painel administrativo.', 'error');
        }
        $_url = function_exists('base_url') ? base_url('?route=assembleia/index') : '?route=assembleia/index';
        header('Location: ' . $_url, true, 302); exit;
    }
    if ($_prefixo === 'superadmin' && !$_isSuperOnly) {
        if (function_exists('flashMessage')) {
            flashMessage('Acesso restrito ao Super Administrador da plataforma.', 'error');
        }
        $_url = function_exists('base_url') ? base_url('?route=assembleia/index') : '?route=assembleia/index';
        header('Location: ' . $_url, true, 302); exit;
    }
}
unset($_ROTA_ATUAL, $_prefixo, $_logado, $_perfil, $_isAdminOrSuper, $_isSuperOnly, $_url);

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
// DUPLICADO da CAMADA 0 — redundancia segura (alguns proxies resetam sessao).
// ==========================================================================
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
if (!empty($_SESSION['usuario_id'])) {
    $pPerfil = (string)($_SESSION['usuario_perfil'] ?? '');
    $pTipo   = (string)($_SESSION['usuario_tipo']   ?? '');
    if ($pPerfil === 'admin') $pPerfil = 'super_admin';
    if ($pTipo   === 'admin') $pTipo   = 'super_admin';
    if ($pPerfil === '' && $pTipo !== '')   $pPerfil = $pTipo;
    if ($pTipo   === '' && $pPerfil !== '') $pTipo   = $pPerfil;
    $_SESSION['usuario_perfil'] = $pPerfil;
    $_SESSION['usuario_tipo']   = $pTipo;
    if (!isset($_SESSION['rbac_sync_versao'])) $_SESSION['rbac_sync_versao'] = 3;
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
