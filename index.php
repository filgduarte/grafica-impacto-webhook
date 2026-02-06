<?php

// 1. Verificações iniciais de segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    error_log('Method Not Allowed: ' . $_SERVER['REQUEST_METHOD']);
    exit('Method Not Allowed');
}

$config = require __DIR__ . '/secrets/webhook.php';
require __DIR__ . '/helpers/http_client.php';
require __DIR__ . '/helpers/pause_resume.php';
require __DIR__ . '/helpers/utils.php';

if (($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '') !== $config['ecommerce_webhook']['token']) {
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $headers[$key] = $value;
        }
    }
    http_response_code(200);
    error_log('Unauthorized access attempt.' . print_r($headers, true));
    exit('Unauthorized');
}

if (isWebhookPaused()) {
    http_response_code(200);
    error_log('Webhook pausado - envio ignorado.');
    exit('Webhook pausado');
}

// 2. Ler o corpo da requisição
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

// 3. Validar JSON
if (!$data) {
    http_response_code(200);
    error_log('Invalid JSON received: ' . $rawBody);
    exit('Invalid JSON');
}

// 4. Roteamento baseado no evento
$event = $data['event'] ?? null;
$event = preg_replace('/[^a-zA-Z0-9_]/', '', $event);
$handler = null;

switch ($event) {
    case 'ITEM_STATUS_UPDATE':
    case 'ORDER_NEW':
        $handler = __DIR__ . '/handlers/' . strtolower($event) . '.php';
        break;

    default:
        http_response_code(200);
        error_log('Unsupported event type: ' . $event);
        exit('Evento não suportado');
}

if (!file_exists($handler)) {
    http_response_code(200);
    error_log('Handler not found for event: ' . $event);
    exit('Handler não encontrado');
}

require $handler;

// 5. Chamar webhook do WhatsApp
$webhook_endpoint = $config['messaging_webhook']['endpoint'];
$webhook_token = $config['messaging_webhook']['token'] ?? null;
try {
    httpPost(
        $webhook_endpoint,
        $payload,
        $webhook_token
    );
} catch (Exception $e) {
    error_log('Erro ao enviar webhook de mensagem para o webhook ' . $webhook_endpoint . ': ' . $e->getMessage());
}

// 6. Retornar resposta para o e-commerce
http_response_code(200);
echo 'OK';
