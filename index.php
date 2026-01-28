<?php

// 1. Verificações iniciais de segurança
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     http_response_code(405);
//     echo 'Method Not Allowed';
//     exit;
// }

$config = require __DIR__ . '/secrets/webhook.php';
require __DIR__ . '/helpers/http_client.php';
require __DIR__ . '/helpers/utils.php';

// if (($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '') !== $config['ecommerce_webhook']['token']) {
//     http_response_code(401);
//     exit('Unauthorized');
// }

// 2. Ler o corpo da requisição
// $rawBody = file_get_contents('php://input'); // Comentado para teste local
// $data = json_decode($rawBody, true); // Comentado para teste local
$data = json_decode('{"event":"ITEM_STATUS_UPDATE","timestamp":"2026-01-27 17:26:39","data":[{"id":"35835","ftp":"773-002","pedido":"23832","cliente":"773","status":"11"}]}', true);

// 3. Validar JSON
if (!$data) {
    http_response_code(200);
    exit('Invalid JSON');
}

// 4. Roteamento baseado no evento
$event = $data['event'] ?? null;
$event = preg_replace('/[^a-zA-Z0-9_]/', '', $event);
$handler = null;
$webhook_endpoint = null;
$webhook_token = null;

switch ($event) {
    case 'ITEM_STATUS_UPDATE':
    case 'ORDER_NEW':
        $handler = __DIR__ . '/handlers/' . strtolower($event) . '.php';
        break;

    default:
        http_response_code(200);
        exit('Evento não suportado');
}

if (!file_exists($handler)) {
    http_response_code(200);
    exit('Handler não encontrado');
}

require $handler;

// 5. Chamar webhook do WhatsApp
try {
    httpPost(
        $webhook_endpoint,
        $payload,
        $webhook_token ?? null
    );
} catch (Exception $e) {
    error_log('Erro ao enviar webhook de mensagem: ' . $e->getMessage());
}

// 6. Retornar resposta para o e-commerce
http_response_code(200);
echo 'OK';
