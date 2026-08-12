<?php

require_once __DIR__ . '/../config.php';

$modelsPath = __DIR__ . '/../app/Models/';
foreach (glob($modelsPath . '*.php') as $file) {
    require_once $file;
}

$controllersPath = __DIR__ . '/../app/Controllers/';
foreach (glob($controllersPath . '*.php') as $file) {
    require_once $file;
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
