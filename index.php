<?php

// 1. Verificações iniciais de segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    error_log('Method Not Allowed: ' . $_SERVER['REQUEST_METHOD']);
    exit(json_encode(['error' => 'Method Not Allowed', 'status' => 405]));
}

$config = require __DIR__ . '/secrets/webhook.php';
require __DIR__ . '/helpers/http_client.php';
require __DIR__ . '/helpers/pause_resume.php';
require __DIR__ . '/helpers/utils.php';

// 2. Validar autenticação
$authHeader = $_SERVER['X-Auth-Token'] ?? getallheaders()['X-Auth-Token'] ?? getallheaders()['x-auth-token'] ?? '';

if (!hash_equals($config['impacto_restapi']['token'], $authHeader)) {
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $headers[$key] = $value;
        }
    }
    http_response_code(401);
    error_log('Unauthorized access attempt.' . print_r($headers, true));
    exit(json_encode(['error' => 'Unauthorized', 'status' => 401]));
}

// 3. Verificar se a API está pausada
if (isApiPaused()) {
    http_response_code(200);
    error_log('API pausada - envio ignorado.');
    exit(json_encode(['error' => 'API pausada', 'status' => 200]));
}

// 4. Ler e validar a requsição
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!$data) {
    http_response_code(400);
    error_log('Invalid JSON received: ' . $rawBody);
    exit(json_encode(['error' => 'Invalid JSON', 'status' => 400]));
}

// 5. Extrair e validar o evento
$event = $data['event'] ?? null;

if (!$event) {
    http_response_code(400);
    error_log('Event property is required in request');
    exit(json_encode(['error' => 'Invalid payload', 'status' => 400]));
}

$event = preg_replace('/[^a-zA-Z0-9_]/', '', $event);

// 6. Mapear eventos para handler
$eventHandlers = ['ITEM_STATUS_UPDATE','ORDER_NEW'];

if (!in_array($event, $eventHandlers)) {
    http_response_code(400);
    error_log('Unsupported event type: ' . $event);
    exit(json_encode(['error' => 'Invalid payload', 'status' => 400]));
}

$handlerFile = __DIR__ . '/handlers/' . strtolower($event) . '.php';

if (!file_exists($handlerFile)) {
    http_response_code(500);
    error_log('Handler not found for event: ' . $event . ' at ' . $handlerFile);
    exit(json_encode(['error' => 'Handler not found', 'status' => 500]));
}

require $handlerFile;

// 7. Retornar resposta
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'event' => $event,
    'data' => $payload ?? null,
    'status' => 200
]);
