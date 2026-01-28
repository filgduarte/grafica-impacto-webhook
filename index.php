<?php

// 1. Verificações iniciais de segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$config = '../secrets/webhook.php';
require __DIR__ . '/http_client.php';

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

$event = $data['event'] ?? null;

if ($event !== 'ITEM_STATUS_UPDATE') {
    http_response_code(200);
    exit('Evento ignorado');
}

$item = $data['data'][0] ?? null;

if (!$item) {
    http_response_code(422);
    exit('Dados do item ausentes');
}

// 4. Extrair dados necessários
$orderId   = $item['id'] ?? null;
$orderItemNumber = $item['ftp'] ?? null;
$orderItemStatus  = $item['status'] ?? null;
$customerId = $item['cliente'] ?? null;

if (!$orderId || !$customerId) {
    http_response_code(422);
    exit('Dados incompletos');
}

// 5. Buscar dados do pedido e do cliente no e-commerce
try {
    $orderRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/pedido/' . $orderId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Erro ao buscar pedido');
}

try {
    $customer = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/cliente/' . $customerId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Erro ao buscar cliente');
}

$order = $orderRaw['registros'][0] ?? null;

if (!$order) {
    http_response_code(422);
    exit('Pedido não encontrado');
}

$orderItems = $order['itens'] ?? [];

if (empty($orderItems)) {
    http_response_code(422);
    exit('Pedido sem itens');
}

$orderItem = null;

foreach ($orderItems as $itm) {
    if ( ($itm['ftp'] ?? null) === $orderItemNumber) {
        $orderItem = $itm;
        break;
    }
}  

if (!$orderItem) {
    http_response_code(422);
    exit('Item não encontrado no pedido');
}

// 6. Obter dados relevantes


// 7. Montar payload normalizado
/*
    ✅ Nome completo do cliente
    ✅ Telefone
    ✅ Celular
    ✅ Nome fantasia
    ✅ Tipo de cliente (Física ou Jurídica)
    ✅ Revendedor (Sim ou Não)
    ✅ Número do pedido (Id)
    ✅ Número do item
    Nome do produto
    Descrição do produto
    Variação do item
    Nome do arquivo
    ✅ Entrega ou Retirada
    ✅ Tipo de entrega/retirada
    ✅ Endereço de entrega/retirada
    ✅ Link de rastreio
*/
$phone = normalizePhone($customer['telefone'] ?? '');
$cell = normalizePhone($customer['celular'] ?? '');

$payload = [
    'event' => 'order_status_update',
    'cliente' => [
        'nome'              => $customer['nome'] ?? '',
        'nome_fantasia'     => $customer['fantasia'] ?? '',
        'sobrenome'         => $customer['sobrenome'] ?? '',
        'celular'           => $cell,
        'telefone'          => $phone,
        'revendedor'        => ($customer['revendedor'] == 0) ? 'Não' : 'Sim',
        'tipo'              => $customer['tipo'] ?? '',
        'valor_total'       => $order['total'] ?? '',
        'acrescimo'         => $order['acrescimo'] ?? '',
        'desconto'          => $order['desconto'] ?? '',
        'frete_valor'       => $order['frete_valor'] ?? '',
    ],
    'entrega' => [
        'endereco'          => $order['frete_endereco'] ?? '',
        'entrega_retirada'  => ($order['frete_balcao'] == 0) ? 'Entrega' : 'Retirada',
        'rastreio'          => $order['frete_rastreio'] ?? '',
        'tipo'              => $order['frete_tipo'] ?? '',
    ],
    'pedido' => [
        'arquivo'           => $orderItem['arte_nome'] ?? '',
        'arte_status'       => $orderItem['arte_status'] ?? '',
        'arte_tipo'         => $orderItem['arte_tipo'] ?? '',
        'copias'            => $orderItem['copias'] ?? '',
        'descricao'         => $orderItem['descricao'] ?? '',
        'formato'           => $orderItem['formato'] ?? '',
        'formato_detalhes'  => $orderItem['formato_detalhes'] ?? '',
        'item'              => $orderItemNumber,
        'numero'            => $orderId,
        'previsao_entrega'  => $orderItem['previsao_entrega'] ?? '',
        'previsao_producao' => $orderItem['previsao_producao'] ?? '',
        'produto'           => $orderItem['produto'] ?? '',
        'quantidade'        => $orderItem['qtde'] ?? '',
        'status'            => $orderItem['status'] ?? '',
        'valor'             => $orderItem['valor'] ?? '',
        'variacao'          => $orderItem['vars'] ?? '',
    ],
];

// 7. Chamar webhook do WhatsApp
try {
    httpPost(
        $config['messaging_webhook']['endpoint'],
        $payload,
        $config['messaging_webhook']['token'] ?? null
    );
} catch (Exception $e) {
    error_log('Erro ao enviar webhook de mensagem: ' . $e->getMessage());
}

// 8. Resposta para o e-commerce
http_response_code(200);
echo 'OK';


// -------------------------
// Helpers
// -------------------------

function normalizePhone(string $phone): string
{
    // Remove tudo que não é número
    $phone = preg_replace('/\D/', '', $phone);

    // Adiciona DDI 55 se não existir
    if (strlen($phone) === 11) {
        $phone = '55' . $phone;
    }

    return $phone;
}
