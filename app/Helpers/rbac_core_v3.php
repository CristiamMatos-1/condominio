<?php
// ============================================================================
// RBAC CORE V3 — Helper Global Único (0 conflitos com config.php servidor)
//
// Dois problemas que este arquivo resolve DEFINITIVAMENTE:
//
//   PROBLEMA 1: config.php do servidor declara requireAdmin() e
//   requireSuperAdmin() DIRETAMENTE (sem if(!function_exists)) e a
//   versão dele NÃO conhece o perfil 'super_admin' (apenas 'admin' legado e
//   'admin_condominio'). Resultado: requireAdmin() retorna false para usuários
//   super_admin → redirect forçado para assembleia/index ("Minha Assembleia").
//
//   PROBLEMA 2: Nossos fallbacks com if(!function_exists) NUNCA são ativados
//   porque a função JÁ EXISTE (declarada no config.php servidor).
//
// SOLUÇÃO — 3 camadas de fallback (0 fatal):
//   (CAMADA A) Funções independentes com prefixo rbac_* — estas NUNCA
//              conflitam. Todos os controllers novos DEVEM usar as rbac_*.
//   (CAMADA B) Se runkit7 ou uopz estiverem carregados, remove as funções
//              globais ANTIGAS (do config.php) e REDCLARA como versões V3.
//   (CAMADA C) Se runkit NÃO estiver disponível (servidor padrão Hostgator/
//              Locaweb), guardamos em $_SESSION['RBAC_CORE_V3_APLICADO'] e
//              sobrescrevemos o comportamento por sessão (GUARD FORÇADO no
//              roteador bloqueia/permite ANTES do __construct ser chamado).
//
// ============================================================================

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// ----------------------------------------------------------------------------
// PASSO 0: Definir $GLOBALS['RBAC_CORE_V3'] com closures (caso ainda nao
//          tenha sido definido na CAMADA 0 do roteador). Redundancia segura.
// ----------------------------------------------------------------------------
if (!is_array($GLOBALS['RBAC_CORE_V3'] ?? null)) {
    $GLOBALS['RBAC_CORE_V3'] = [
        'isLoggedIn' => static function (): bool {
            return isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0;
        },
        'perfilUsuario' => static function (): string {
            if (empty($_SESSION['usuario_id'])) return '';
            $p = (string)($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_tipo'] ?? 'morador'));
            if ($p === 'admin') $p = 'super_admin';
            return $p;
        },
        'isSuperAdmin' => static function (): bool {
            return ($GLOBALS['RBAC_CORE_V3']['perfilUsuario'])() === 'super_admin';
        },
        'isAdminCondominio' => static function (): bool {
            $p = ($GLOBALS['RBAC_CORE_V3']['perfilUsuario'])();
            return $p === 'admin_condominio' || $p === 'super_admin';
        },
        'isAdmin' => static function (): bool {
            return ($GLOBALS['RBAC_CORE_V3']['isSuperAdmin'])()
                || ($GLOBALS['RBAC_CORE_V3']['isAdminCondominio'])();
        },
        'condominioIdSessao' => static function (): ?int {
            if (!($GLOBALS['RBAC_CORE_V3']['isLoggedIn'])()) return null;
            if (($GLOBALS['RBAC_CORE_V3']['isSuperAdmin'])()) return null;
            $cid = $_SESSION['condominio_id'] ?? null;
            return $cid === null ? null : (int)$cid;
        },
    ];
}

// ----------------------------------------------------------------------------
// (CAMADA A) Funções PREFIXADAS rbac_* — NUNCA conflitam. Use estas.
// ----------------------------------------------------------------------------
function rbac_perfil_usuario(): string { return ($GLOBALS['RBAC_CORE_V3']['perfilUsuario'])(); }
function rbac_is_logged_in(): bool     { return ($GLOBALS['RBAC_CORE_V3']['isLoggedIn'])(); }
function rbac_is_super_admin(): bool   { return ($GLOBALS['RBAC_CORE_V3']['isSuperAdmin'])(); }
function rbac_is_admin_condominio(): bool { return ($GLOBALS['RBAC_CORE_V3']['isAdminCondominio'])(); }
function rbac_is_admin(): bool         { return ($GLOBALS['RBAC_CORE_V3']['isAdmin'])(); }
function rbac_condominio_id(): ?int    { return ($GLOBALS['RBAC_CORE_V3']['condominioIdSessao'])(); }
function rbac_url_base(string $path = ''): string {
    if (function_exists('base_url')) return base_url($path);
    static $cacheUrl = null;
    if ($cacheUrl === null) {
        $s  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $sn   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        if (substr($sn, -7) === '/public') $sn = substr($sn, 0, -7);
        $cacheUrl = rtrim($s . '://' . $host . ($sn ?: ''), '/');
    }
    return $cacheUrl . '/' . ltrim($path, '/');
}
function rbac_flash(?string $msg = null, string $type = 'info') {
    if (function_exists('flashMessage')) {
        return flashMessage(...func_get_args());
    }
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
function rbac_redirect(string $url, int $code = 302): void {
    if (!headers_sent()) { header('Location: ' . $url, true, $code); exit; }
    $esc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<script>window.location.href="', $esc, '";</script>';
    exit;
}
function rbac_require_login(): void {
    if (!rbac_is_logged_in()) {
        rbac_flash('Por favor, faça login para continuar.', 'error');
        rbac_redirect(rbac_url_base('?route=auth/login'));
    }
}
function rbac_require_admin(): void {
    rbac_require_login();
    if (!rbac_is_admin()) {
        rbac_flash('Você não tem permissão para acessar o painel administrativo.', 'error');
        rbac_redirect(rbac_url_base('?route=assembleia/index'));
    }
}
function rbac_require_super_admin(): void {
    rbac_require_login();
    if (!rbac_is_super_admin()) {
        rbac_flash('Acesso restrito ao Super Administrador da plataforma.', 'error');
        rbac_redirect(rbac_url_base('?route=assembleia/index'));
    }
}
function rbac_tenant_guard($condominioIdSolicitado): void {
    $condominioIdSolicitado = (int)$condominioIdSolicitado;
    if ($condominioIdSolicitado <= 0) return;
    if (rbac_is_super_admin()) return;
    $sess = (int)rbac_condominio_id();
    if ($sess <= 0) {
        rbac_flash('Seu perfil não tem condomínio associado. Contate o suporte.', 'error');
        rbac_redirect(rbac_url_base('?route=auth/logout'));
    }
    if ($sess !== $condominioIdSolicitado) {
        rbac_flash('Você não tem permissão para acessar dados de outro condomínio.', 'error');
        rbac_redirect(rbac_url_base('?route=assembleia/index'));
    }
}

// ----------------------------------------------------------------------------
// (CAMADA B) Tentativa de sobrescrever as funções GLOBAIS ANTIGAS do config.php
//            usando runkit7 / uopz (se instalado).
//
// Se der certo: AdminController::__construct() chama requireAdmin() e a versão
//               NOVA (V3) é usada (com perfil super_admin reconhecido).
// Se der errado: NÃO FATAL — a CAMADA C (guards por sessão no roteador) ainda
//                bloqueia/permite corretamente ANTES do __construct().
// ----------------------------------------------------------------------------
$RBAC_OVERRIDE_LOG = [];
$RBAC_FNS_TO_REPLACE = [
    'isLoggedIn'         => ['args' => '', 'body' => 'return ($GLOBALS[\'RBAC_CORE_V3\'][\'isLoggedIn\'])();'],
    'perfilUsuario'      => ['args' => '', 'body' => 'return ($GLOBALS[\'RBAC_CORE_V3\'][\'perfilUsuario\'])();'],
    'isSuperAdmin'       => ['args' => '', 'body' => 'return ($GLOBALS[\'RBAC_CORE_V3\'][\'isSuperAdmin\'])();'],
    'isAdminCondominio'  => ['args' => '', 'body' => 'return ($GLOBALS[\'RBAC_CORE_V3\'][\'isAdminCondominio\'])();'],
    'isAdmin'            => ['args' => '', 'body' => 'return ($GLOBALS[\'RBAC_CORE_V3\'][\'isAdmin\'])();'],
    'condominioIdSessao' => ['args' => '', 'body' => 'return ($GLOBALS[\'RBAC_CORE_V3\'][\'condominioIdSessao\'])();'],
    'tenant_guard'       => ['args' => '$condominioIdSolicitado', 'body' => 'return rbac_tenant_guard($condominioIdSolicitado);'],
    'requireLogin'       => ['args' => '', 'body' => 'return rbac_require_login();'],
    'requireAdmin'       => ['args' => '', 'body' => 'return rbac_require_admin();'],
    'requireSuperAdmin'  => ['args' => '', 'body' => 'return rbac_require_super_admin();'],
];

function _rbac_v3_try_redefine_global(string $nome, string $args, string $body): bool {
    // Tentativa 1: runkit7 (PECL runkit7, versao moderna, PHP 7.4+)
    if (function_exists('runkit7_function_remove') && function_exists('runkit7_function_add')) {
        try {
            if (function_exists($nome)) {
                $rem = @runkit7_function_remove($nome);
                if (!$rem) return false;
            }
            return runkit7_function_add($nome, $args, $body);
        } catch (Throwable $e) { @error_log('[RBAC-V3] runkit7 falhou '.$nome.': '.$e->getMessage()); }
    }
    // Tentativa 2: runkit (PECL runkit, versao antiga)
    if (function_exists('runkit_function_remove') && function_exists('runkit_function_add')) {
        try {
            if (function_exists($nome)) {
                $rem = @runkit_function_remove($nome);
                if (!$rem) return false;
            }
            return runkit_function_add($nome, $args, $body);
        } catch (Throwable $e) { @error_log('[RBAC-V3] runkit falhou '.$nome.': '.$e->getMessage()); }
    }
    // Tentativa 3: uopz (PECL uopz — ext mais agressiva)
    if (function_exists('uopz_unset_return') && function_exists('uopz_set_return')) {
        try {
            if (function_exists($nome)) {
                uopz_unset_return($nome);
                $closure = eval('return function('.$args.') { '.$body.' };');
                uopz_set_return($nome, $closure, true);
                return true;
            }
        } catch (Throwable $e) { @error_log('[RBAC-V3] uopz falhou '.$nome.': '.$e->getMessage()); }
    }
    return false;
}

$conseguiuRunkit = 0;
foreach ($RBAC_FNS_TO_REPLACE as $fnNome => $fnSpec) {
    if (_rbac_v3_try_redefine_global($fnNome, $fnSpec['args'], $fnSpec['body'])) {
        $conseguiuRunkit++;
        $RBAC_OVERRIDE_LOG[] = $fnNome . ': OK (override global)';
    } else {
        $RBAC_OVERRIDE_LOG[] = $fnNome . ': SKIP (ext runkit/uopz nao disponivel)';
    }
}

$_SESSION['RBAC_CORE_V3_APLICADO'] = 1;
$_SESSION['RBAC_CORE_V3_TIMESTAMP'] = time();
$_SESSION['RBAC_CORE_V3_OVERRIDES'] = $conseguiuRunkit;
if ($conseguiuRunkit > 0) {
    $_SESSION['RBAC_HELPER_FAMILIA'] = 'core_v3_runkit';
} elseif (!empty($_SESSION['RBAC_HELPER_FAMILIA']) && $_SESSION['RBAC_HELPER_FAMILIA'] === 'config_desatualizado') {
    $_SESSION['RBAC_HELPER_FAMILIA'] = 'core_v3_guard_only';
}

// Debug leve (apenas log, nao mostra na tela)
@error_log('[RBAC-V3] Overrides globais aplicados: ' . $conseguiuRunkit
    . '/' . count($RBAC_FNS_TO_REPLACE) . '. Log: ' . implode(', ', $RBAC_OVERRIDE_LOG));
unset($RBAC_FNS_TO_REPLACE, $RBAC_OVERRIDE_LOG, $conseguiuRunkit);
