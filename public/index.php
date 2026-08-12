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
