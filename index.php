<?php

// 1. Verificações iniciais de segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$config = require '../secrets/webhook.php';
require __DIR__ . '/helpers/http_client.php';
require __DIR__ . '/helpers/utils.php';

if (($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '') !== $config['ecommerce_webhook']['token']) {
    http_response_code(401);
    exit('Unauthorized');
}

// 2. Ler o corpo da requisição
// $rawBody = file_get_contents('php://input'); // Comentado para teste local
// $data = json_decode($rawBody, true); // Comentado para teste local
$data = json_decode('{"event":"ITEM_STATUS_UPDATE","timestamp":"2026-01-27 17:26:39","data":[{"id":"35835","ftp":"1835-257","pedido":"23794","cliente":"1835","produto":"6","formato":"6x6cm","formato_detalhes":"{\"preco_metro_final\":43,\"total_metro\":15.480000000000000426325641456060111522674560546875,\"formato_metros\":{\"area_quadrada_total\":0.35999999999999998667732370449812151491641998291015625,\"largura\":0.059999999999999997779553950749686919152736663818359375,\"altura\":0.059999999999999997779553950749686919152736663818359375},\"formato_centimetros\":{\"largura\":6,\"altura\":6},\"formato\":\"6x6cm\"}","descricao":"","status":"11","copias":"0","qtde":"100","valor":"15.48","custo":"0","arte_valor":"0","arte_tipo":"enviar","arte_status":"0","arte_arquivo":"","arte_data":"2026-01-26 18:07:55","arte_nome":"","arte_previa":"","data":"2026-01-26 18:07:55","vars":"Leitoso Brilho Corte Eletrônico","previsao_producao":"2026-01-28 00:00:00","previsao_entrega":"0000-00-00 00:00:00"}]}', true);

// 3. Validar JSON
if (!$data) {
    http_response_code(400);
    exit('Invalid JSON');
}

// 4. Roteamento baseado no evento
$event = $data['event'] ?? null;
$handler = '';

switch($event) {
    case 'ITEM_STATUS_UPDATE':
        $handler = 'item_status_update';
        break;
    case 'ORDER_NEW':
        $handler = 'order_new';
        break;
    default:
        http_response_code(200);
        exit('Evento ignorado');
}

require __DIR__ . '/handlers/' . $handler . '.php';

// 5. Chamar webhook do WhatsApp
try {
    httpPost(
        $config['messaging_webhook']['endpoint'],
        $payload,
        $config['messaging_webhook']['token'] ?? null
    );
} catch (Exception $e) {
    error_log('Erro ao enviar webhook de mensagem: ' . $e->getMessage());
}

// 6. Retornar resposta para o e-commerce
http_response_code(200);
echo 'OK';
