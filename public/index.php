<?php

declare(strict_types=1);

// Autoload simples
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Roteamento simples
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/api/evaluate' && $method === 'POST') {
    $controller = new \App\Controller\ResponseController();
    $controller->evaluate();
    exit;
}

if ($uri === '/health' && $method === 'GET') {
    echo json_encode([
        'status' => 'ok',
        'service' => 'balthasar',
        'timestamp' => date('c'),
    ]);
    exit;
}

// 404
http_response_code(404);
echo json_encode([
    'error' => 'Rota não encontrada',
    'path' => $uri,
]);
