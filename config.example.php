<?php
/**
 * Arquivo de EXEMPLO de configurações
 * Copie este arquivo para config.php e ajuste com suas credenciais reais
 * 
 * NUNCA commite o arquivo config.php no Git
 */

define('DB_HOST',     'localhost');
define('DB_NAME',     'seu_banco_de_dados');
define('DB_USER',     'seu_usuario');
define('DB_PASS',     'sua_senha');
define('DB_CHARSET',  'utf8mb4');

define('URL_BASE',    'https://seu-dominio.com.br');
define('SESSION_NAME','sessao_condominio');

define('ADMIN_CPF',   '000.000.000-00');

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_name(SESSION_NAME);
session_start();

require_once __DIR__ . '/app/Models/Database.php';

function base_url($path = '')
{
    return rtrim(URL_BASE, '/') . '/' . ltrim($path, '/');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit();
}

function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatCpf($cpf)
{
    $cpf = preg_replace('/\D/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

function validateCpf($cpf)
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

function isLoggedIn()
{
    return isset($_SESSION['usuario_id']);
}

function isAdmin()
{
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
}

function requireLogin()
{
    if (!isLoggedIn()) {
        redirect(base_url('?route=auth/login'));
    }
}

function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        redirect(base_url('?route=assembleia/index'));
    }
}

function flashMessage($message = null, $type = 'info')
{
    if ($message !== null) {
        $_SESSION['flash'] = [
            'message' => $message,
            'type'    => $type
        ];
    } else if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
