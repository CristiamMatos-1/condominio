<?php
/**
 * install.php — Assistente WEB de instalacao do banco de dados
 * Use APENAS no primeiro deploy, depois APAGUE ESTE ARQUIVO por seguranca.
 *
 * Funciona em 4 passos:
 *   [1] Formulario de dados do MySQL
 *   [2] Teste de conexao + criar banco se nao existir
 *   [3] Executar o migration SQL
 *   [4] Gravar config.php automaticamente + criar admin padrao
 */

declare(strict_types=1);
$APP_ROOT = __DIR__;
$EXISTE_CONFIG = file_exists($APP_ROOT . '/config.php');
$CONFIG_EXEMPLO = $APP_ROOT . '/config.example.php';

// --- Carrega dados do config.example se existir como defaults ---
$defaults = [
    'DB_HOST'    => 'localhost',
    'DB_NAME'    => 'condominio_assembleias',
    'DB_USER'    => '',
    'DB_PASS'    => '',
    'DB_CHARSET' => 'utf8mb4',
    'ADMIN_NOME' => 'Administrador Padrao',
    'ADMIN_CPF'  => '000.000.000-00',
    'ADMIN_EMAIL'=> 'admin@condominio.local',
    'ADMIN_SENHA'=> 'admin123',
    'URL_BASE'   => '',
];
if ($EXISTE_CONFIG) {
    // Carrega valores ja existentes do config.php para editar
    include $APP_ROOT . '/config.php';
    $defaults['DB_HOST']    = DB_HOST;
    $defaults['DB_NAME']    = DB_NAME;
    $defaults['DB_USER']    = DB_USER;
    $defaults['DB_PASS']    = DB_PASS;
    $defaults['DB_CHARSET'] = DB_CHARSET;
    $defaults['URL_BASE']   = URL_BASE;
}

function detectarUrlBase() : string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || ($_SERVER['SERVER_PORT'] ?? null) == 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return $proto . '://' . $host . $uri;
}

function e($s) { echo htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$step = $_GET['step'] ?? '1';
$erro = null; $info = null;

// =================== TRATA FORMULARIOS ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($defaults) as $k) {
        if (isset($_POST[$k])) $defaults[$k] = trim($_POST[$k]);
    }
    if (empty($defaults['URL_BASE'])) $defaults['URL_BASE'] = detectarUrlBase();

    try {
        if ($step === '1') {
            // testa conexao MySQL usando o usuario informado
            $pdo = new PDO(
                "mysql:host={$defaults['DB_HOST']};charset={$defaults['DB_CHARSET']}",
                $defaults['DB_USER'],
                $defaults['DB_PASS'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // tenta criar banco se nao existir
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$defaults['DB_NAME']}`
                        DEFAULT CHARACTER SET {$defaults['DB_CHARSET']}
                        DEFAULT COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$defaults['DB_NAME']}`");
            $step = '2';
            $info = "Conexao MySQL OK. Banco {$defaults['DB_NAME']} pronto.";
        }
        elseif ($step === '2') {
            $pdo = new PDO(
                "mysql:host={$defaults['DB_HOST']};dbname={$defaults['DB_NAME']};charset={$defaults['DB_CHARSET']}",
                $defaults['DB_USER'],
                $defaults['DB_PASS'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Le migration SQL
            $sqlPath = $APP_ROOT . '/database/migrations/001_criar_todas_tabelas.sql';
            if (!file_exists($sqlPath)) throw new Exception('Arquivo SQL nao encontrado em ' . $sqlPath);
            $sql = file_get_contents($sqlPath);
            // Divide em statements (multi-query via separador ;)
            $statements = array_filter(array_map('trim', explode(";\n", $sql)));
            $executados = 0;
            foreach ($statements as $stmt) {
                if ($stmt === '' || strpos($stmt, '--') === 0) continue;
                try { $pdo->exec($stmt); $executados++; }
                catch (PDOException $e) {
                    // ignora erro "table already exists" caso re-rodando
                    if (strpos($e->getMessage(), 'already exists') === false) throw $e;
                }
            }
            $step = '3';
            $info = "Migration executada. $executados comandos SQL rodados.";
        }
        elseif ($step === '3') {
            $pdo = new PDO(
                "mysql:host={$defaults['DB_HOST']};dbname={$defaults['DB_NAME']};charset={$defaults['DB_CHARSET']}",
                $defaults['DB_USER'],
                $defaults['DB_PASS'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Grava ou atualiza usuario Admin
            $cpfLimpo = preg_replace('/\D/', '', $defaults['ADMIN_CPF']);
            $hash = password_hash($defaults['ADMIN_SENHA'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = ? LIMIT 1");
            $stmt->execute([$cpfLimpo]);
            $existe = $stmt->fetchColumn();
            if ($existe) {
                $up = $pdo->prepare("UPDATE usuarios SET nome=?,email=?,senha=?,tipo='admin',status='ativo' WHERE id=?");
                $up->execute([$defaults['ADMIN_NOME'], $defaults['ADMIN_EMAIL'], $hash, $existe]);
            } else {
                $ins = $pdo->prepare("INSERT INTO usuarios (nome,cpf,email,senha,tipo,status,condominio_id,created_at) VALUES (?,?,?,?,?,'ativo',NULL,NOW())");
                $ins->execute([$defaults['ADMIN_NOME'], $defaults['ADMIN_CPF'], $defaults['ADMIN_EMAIL'], $hash, 'admin']);
            }
            // Escreve o config.php
            $conteudoConfig = "<?php\n"
                . "// Configuracao gerada automaticamente por install.php em " . date('d/m/Y H:i:s') . "\n"
                . "define('DB_HOST',    '" . addslashes($defaults['DB_HOST']) . "');\n"
                . "define('DB_NAME',    '" . addslashes($defaults['DB_NAME']) . "');\n"
                . "define('DB_USER',    '" . addslashes($defaults['DB_USER']) . "');\n"
                . "define('DB_PASS',    '" . addslashes($defaults['DB_PASS']) . "');\n"
                . "define('DB_CHARSET', '" . addslashes($defaults['DB_CHARSET']) . "');\n"
                . "define('URL_BASE',   '" . addslashes($defaults['URL_BASE']) . "');\n"
                . "define('SESSION_NAME', 'sessao_condominio');\n"
                . "define('ADMIN_CPF',  '" . addslashes($defaults['ADMIN_CPF']) . "');\n"
                . "\n// ========= FIM GERADO AUTOMATICAMENTE =========\n"
                . "// O restante e carregado dinamicamente pelo sistema.\n"
                . "// Requerimentos padroes do projeto:\n"
                . "date_default_timezone_set('America/Sao_Paulo');\n"
                . "ini_set('display_errors', 0);\n"
                . "ini_set('log_errors', 1);\n"
                . "ini_set('error_log', __DIR__ . '/error_php.log');\n"
                . "error_reporting(E_ALL);\n"
                . "if (session_status() === PHP_SESSION_NONE) { session_name(SESSION_NAME); session_start(); }\n"
                . "define('APP_ROOT', __DIR__);\n"
                . "require_once APP_ROOT . '/app/Models/Database.php';\n"
                . "foreach (glob(APP_ROOT . '/app/Models/*.php') as \$_f) require_once \$_f;\n"
                . "foreach (glob(APP_ROOT . '/app/Controllers/*.php') as \$_f) require_once \$_f;\n"
                . "unset(\$_f);\n";
            // Cola os helpers no final
            $helpers = file_get_contents(__DIR__ . '/app/Helpers/_helpers.txt');
            $helpers || $helpers = '';
            // Carrega helpers diretamente do config antigo se existir
            if (file_exists($CONFIG_EXEMPLO)) {
                $helpers = file_get_contents($CONFIG_EXEMPLO);
                // extrai apenas a parte de funcoes (depois de AUTOLOAD)
                if ($pos = strpos($helpers, 'function base_url')) $helpers = substr($helpers, $pos);
            }
            // Caso contrario insere helpers inline
            if (!$helpers || trim($helpers) === '' || strpos($helpers, 'function base_url') === false) {
                $helpers = <<<'PHP'

function base_url($path = '') { return rtrim(URL_BASE,'/').'/'.ltrim($path,'/'); }
function redirect($url) { header('Location: '.$url); exit(); }
function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
function formatCpf($cpf) {
    $c = preg_replace('/\D/','',$cpf);
    return substr($c,0,3).'.'.substr($c,3,3).'.'.substr($c,6,3).'-'.substr($c,9,2);
}
function validateCpf($cpf) {
    $c = preg_replace('/\D/','',$cpf);
    if (strlen($c)!==11 || preg_match('/(\d)\1{10}/',$c)) return false;
    for ($t=9;$t<11;$t++){
        for ($d=0,$x=0;$x<$t;$x++) $d += $c[$x]* (($t+1)-$x);
        $d = ((10*$d)%11)%10;
        if ($c[$t] != $d) return false;
    }
    return true;
}
function isLoggedIn(){ return isset($_SESSION['usuario_id']); }
function isAdmin(){ return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo']==='admin'; }
function requireLogin(){ if(!isLoggedIn()) redirect(base_url('?route=auth/login')); }
function requireAdmin(){ requireLogin(); if(!isAdmin()) redirect(base_url('?route=assembleia/index')); }
function flashMessage($message=null, $type='info'){
    if ($message!==null) $_SESSION['flash'] = ['message'=>$message,'type'=>$type];
    elseif (isset($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}
PHP;
            }
            $conteudoConfig .= "\n" . $helpers . "\n";
            // Gravacao (tenta escrever direto, senao da download pro usuario)
            $escreveu = false;
            if (is_writable($APP_ROOT) || (!$EXISTE_CONFIG && is_writable($APP_ROOT))) {
                $escreveu = @file_put_contents($APP_ROOT . '/config.php', $conteudoConfig) !== false;
            }
            if ($EXISTE_CONFIG && is_writable($APP_ROOT . '/config.php')) {
                $escreveu = @file_put_contents($APP_ROOT . '/config.php', $conteudoConfig) !== false;
            }

            $step = '4';
            if (!$escreveu) {
                // Download automatico
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="config.php"');
                echo $conteudoConfig;
                exit;
            }
            $info = "Configuracao gravada em config.php com sucesso!";
        }
    }
    catch (Exception $e) {
        $erro = get_class($e) . ': ' . $e->getMessage();
    }
}

// =================== RENDER TELA ===================
?><!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Instalador — Sistema de Assembleias</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{ --azul:#2563EB; --bg:#F8F9FA; --card:#fff; --text:#212529; --ok:#16A34A; --er:#DC2626; }
*{box-sizing:border-box}
body{margin:0;padding:2rem;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.wrap{max-width:680px;margin:0 auto}
.card{background:var(--card);padding:2rem;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.06)}
h1{font-size:1.6rem;margin:0 0 4px}
.sub{color:#6B7280;margin:0 0 1.5rem}
.steps{display:flex;gap:8px;margin-bottom:1.5rem;flex-wrap:wrap}
.badge{flex:1;min-width:80px;padding:10px 12px;text-align:center;border-radius:10px;background:#E5E7EB;color:#6B7280;font-weight:600;font-size:.9rem}
.badge.on{background:var(--azul);color:#fff}
label{display:block;font-weight:600;margin:14px 0 6px;font-size:.95rem;color:#374151}
input{width:100%;padding:12px 14px;border:1px solid #D1D5DB;border-radius:10px;font-size:1rem;outline:none}
input:focus{border-color:var(--azul);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{display:block;width:100%;margin-top:22px;padding:14px;border:0;border-radius:10px;background:var(--azul);color:#fff;font-size:1.1rem;font-weight:700;cursor:pointer;transition:.15s}
.btn:hover{filter:brightness(1.05)}
.btn.sec{background:#6B7280}
.alerta{padding:14px 16px;border-radius:10px;margin-top:18px;font-weight:500}
.alerta.erro{background:#FEE2E2;color:var(--er);border-left:4px solid var(--er)}
.alerta.ok{background:#DCFCE7;color:var(--ok);border-left:4px solid var(--ok)}
.rodape{text-align:center;margin-top:2rem;color:#6B7280;font-size:.85rem}
code{background:#F3F4F6;padding:2px 6px;border-radius:4px;font-family:ui-monospace,Menlo,monospace}
@media (max-width:560px){ .grid{grid-template-columns:1fr} body{padding:1rem} .card{padding:1.25rem} }
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<h1>🛠️ Instalador do Sistema</h1>
<p class="sub">Sistema de Eleição e Gestão de Assembleias para Condomínios</p>

<div class="steps">
<div class="badge <?= in_array($step,['1','2','3','4']) ? 'on' : '' ?>">1 · Dados MySQL</div>
<div class="badge <?= in_array($step,['2','3','4']) ? 'on' : '' ?>">2 · Criar BD</div>
<div class="badge <?= in_array($step,['3','4']) ? 'on' : '' ?>">3 · Importar SQL</div>
<div class="badge <?= $step==='4' ? 'on' : '' ?>">4 · Concluir</div>
</div>

<?php if ($erro): ?><div class="alerta erro"><strong>Erro:</strong> <?= e($erro) ?></div><?php endif ?>
<?php if ($info): ?><div class="alerta ok"><?= e($info) ?></div><?php endif ?>

<form method="post" action="?step=<?= e($step) ?>">
<?php if ($step === '1'): ?>
<label>Dados de conexão MySQL (pegue no cPanel → Bancos de Dados)</label>
<div class="grid">
<div><label>Host</label><input name="DB_HOST" value="<?= e($defaults['DB_HOST']) ?>" required placeholder="localhost"></div>
<div><label>Charset</label><input name="DB_CHARSET" value="<?= e($defaults['DB_CHARSET']) ?>" readonly></div>
<div><label>Banco de Dados (cria automático se não existir)</label><input name="DB_NAME" value="<?= e($defaults['DB_NAME']) ?>" required></div>
</div>
<div class="grid">
<div><label>Usuário MySQL</label><input name="DB_USER" value="<?= e($defaults['DB_USER']) ?>" required></div>
<div><label>Senha MySQL</label><input type="password" name="DB_PASS" value="<?= e($defaults['DB_PASS']) ?>"></div>
</div>

<hr style="border:0;border-top:1px dashed #E5E7EB;margin:22px 0">
<label>Dados do Administrador Padrão</label>
<div class="grid">
<div><label>Nome Completo</label><input name="ADMIN_NOME" value="<?= e($defaults['ADMIN_NOME']) ?>" required></div>
<div><label>CPF Admin (formato 000.000.000-00)</label><input name="ADMIN_CPF" value="<?= e($defaults['ADMIN_CPF']) ?>" required pattern="[\d\.\-]{14}"></div>
</div>
<div class="grid">
<div><label>E-mail</label><input type="email" name="ADMIN_EMAIL" value="<?= e($defaults['ADMIN_EMAIL']) ?>" required></div>
<div><label>Senha inicial</label><input name="ADMIN_SENHA" value="<?= e($defaults['ADMIN_SENHA']) ?>" required></div>
</div>

<label>URL Base do projeto (auto-detectado — pode ajustar)</label>
<input name="URL_BASE" value="<?= e($defaults['URL_BASE'] ?: detectarUrlBase()) ?>" placeholder="https://coninfoms.com.br/condominio">

<button class="btn" type="submit">▶ Próximo passo — Testar Conexão</button>

<?php elseif ($step === '2'): ?>
<p>Conexão ao banco <strong><?= e($defaults['DB_NAME']) ?></strong> realizada com sucesso.</p>
<p>Agora vamos importar o arquivo de migration <code>database/migrations/001_criar_todas_tabelas.sql</code> com todas as tabelas + dados iniciais.</p>
<!-- campos hidden para persistir entre passos -->
<?php foreach ($defaults as $k=>$v): ?>
<input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
<?php endforeach ?>
<button class="btn" type="submit">▶ Próximo passo — Importar SQL e Cria Tabelas</button>

<?php elseif ($step === '3'): ?>
<p>Tabelas criadas. Agora vamos:</p>
<ul>
<li>✅ Gravar usuário Administrador (CPF <strong><?= e($defaults['ADMIN_CPF']) ?></strong>)</li>
<li>✅ Gerar o arquivo <strong>config.php</strong> completo</li>
<li>✅ Seu sistema estará ONLINE.</li>
</ul>
<?php foreach ($defaults as $k=>$v): ?>
<input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
<?php endforeach ?>
<button class="btn" type="submit">▶ Concluir Instalação</button>

<?php else: ?>
<h2 style="margin-top:0;color:var(--ok)">🎉 Instalação Concluída!</h2>
<p>Seu sistema de assembleias está no ar. Tudo pronto para usar.</p>
<div class="alerta ok">
<p style="margin:0 0 6px"><strong>Credenciais do Administrador:</strong></p>
<p style="margin:0">CPF: <code><?= e($defaults['ADMIN_CPF']) ?></code> · Senha: <code><?= e($defaults['ADMIN_SENHA']) ?></code></p>
</div>
<p style="margin-top:16px"><strong>Por segurança:</strong> APAGUE os arquivos:</p>
<ul>
<li><code>/install.php</code> (este assistente)</li>
<li><code>/deploy_ftp.php</code> (script de deploy, caso exista)</li>
</ul>
<a class="btn" href="<?= e(rtrim($defaults['URL_BASE'],'/')) ?>/?route=auth/login" style="text-align:center;text-decoration:none">📋 Ir para Tela de Login</a>
<?php endif ?>
</form>
</div>
<div class="rodape">
Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong> &copy; <?= date('Y') ?>
</div>
</div>
</body>
</html>
