<?php

// 1. Validar dados de entrada obrigatórios
$customerId = $data['cliente'] ?? null;
$orderId = $data['pedido'] ?? null;
$itemNumber = $data['item_pedido'] ?? null;
$productId = $data['produto'] ?? null;
$arteName = $data['arte'] ?? null;

if (!$orderId || !$customerId || !$itemNumber || !$productId) {
    http_response_code(400);
    error_log('Incomplete data in item status update. Payload: ' . json_encode($data));
    exit(json_encode(['error' => 'Invalid payload', 'status' => 400]));
}

// 2. Buscar dados no e-commerce
try {
    $orderRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/pedido/' . $orderId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log('Error fetching order: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Failed to fetch order data', 'status' => 500]));
}

try {
    $productRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/produto/' . $productId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log('Error fetching product: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Failed to fetch product data', 'status' => 500]));
}

try {
    $customerRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/cliente/' . $customerId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log('Error fetching customer: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Failed to fetch customer data', 'status' => 500]));
}

$order = $orderRaw['registros'][0] ?? null;
$product = $productRaw['registros'][0] ?? null;
$customer = $customerRaw['registros'][0] ?? null;

if (!$order) {
    http_response_code(400);
    error_log('Order not found: ' . $orderId);
    exit(json_encode(['error' => 'Order not found', 'status' => 400]));
}

$orderItems = $order['itens'] ?? [];
if (empty($orderItems)) {
    http_response_code(400);
    error_log('Order has no items: ' . $orderId);
    exit(json_encode(['error' => 'Order has no items', 'status' => 400]));
}

// Encontrar o item específico
$orderItem = null;
foreach ($orderItems as $itm) {
    if (($itm['ftp'] ?? null) === $itemNumber) {
        $orderItem = $itm;
        break;
    }
}

if (!$orderItem) {
    http_response_code(400);
    error_log('Order item not found: Order ID ' . $orderId . ', Item Number ' . $itemNumber);
    exit(json_encode(['error' => 'Item not found in order', 'status' => 400]));
}

if (!$customer) {
    http_response_code(400);
    error_log('Customer not found: ' . $customerId);
    exit(json_encode(['error' => 'Customer not found', 'status' => 400]));
}

// 3. Validar telefone
$phone = normalizePhone($customer['telefone'] ?? '');
$cell = normalizePhone($customer['celular'] ?? '');

if (empty($phone) && empty($cell)) {
    http_response_code(400);
    error_log('Customer has no phone or cell: ' . $customerId);
    exit(json_encode(['error' => 'Customer has no valid phone number', 'status' => 400]));
}

// 4. Preparar informações do produto e status
$productTitle = sanitizeString($product ? $product['titulo'] : ($orderItem['descricao'] ?? ''));
$defaultStatuses = [
    'Aguardando confirm. pagto',    // 0
    'Pendente',                     // 1
    'Em produção',                  // 2
    'Em impressão',                 // 3
    'Em acabamento',                // 4
    'Disponível para retirada',     // 5
    'Material retirado',            // 6
    'Cancelado',                    // 7
    'Cancelado pelo cliente',       // 8
    'Aguardando envio',             // 9
    'Em transporte',                // 10
    'Entregue',                     // 11
    'Despachado',                   // 12
];

// Obter o status atual do item
$orderItemStatus = $orderItem['status'] ?? null;
$statusText = isset($defaultStatuses[$orderItemStatus]) ? $defaultStatuses[$orderItemStatus] : 'Não mapeado';
$firstName = trim($customer['nome'] ?? '');
$lastName = trim($customer['sobrenome'] ?? '');

// 5. Montar payload para envio ao webhook
$payload = [
    'event' => 'STATUS_UPDATE',
    'cliente' => [
        'id'                => $customerId,
        'nome'              => $firstName,
        'sobrenome'         => $lastName,
        'nome_completo'     => trim($firstName . ' ' . $lastName),
        'nome_fantasia'     => $customer['fantasia'] ?? '',
        'celular'           => $cell,
        'email'             => $customer['email_log'] ?? '',
        'telefone'          => $phone,
        'revendedor'        => $customer['revendedor'] ?? 0,
        'tipo'              => $customer['tipo'] ?? '',
    ],
    'entrega' => [
        'endereco'          => $order['frete_endereco'] ?? '',
        'entrega_retirada'  => ($order['frete_balcao'] == 0) ? 'Entrega' : 'Retirada',
        'rastreio'          => $order['frete_rastreio'] ?? '',
        'tipo'              => $order['frete_tipo'] ?? '',
    ],
    'pedido' => [
        'arte_status'       => $orderItem['arte_status'] ?? '',
        'arte_tipo'         => $orderItem['arte_tipo'] ?? '',
        'copias'            => $orderItem['copias'] ?? '',
        'descricao'         => $orderItem['descricao'] ?? '',
        'formato'           => $orderItem['formato'] ?? '',
        'formato_detalhes'  => $orderItem['formato_detalhes'] ?? '',
        'item'              => $itemNumber,
        'numero'            => $orderId,
        'previsao_entrega'  => $orderItem['previsao_entrega'] ?? '',
        'previsao_producao' => $orderItem['previsao_producao'] ?? '',
        'produto'           => $arteName ?? $productTitle,
        'quantidade'        => $orderItem['qtde'] ?? '',
        'status'            => $statusText,
        'status_code'       => $orderItemStatus,
        'variacao'          => $orderItem['vars'] ?? '',
        'valor'             => $orderItem['valor'] ?? '',
        'acrescimo'         => $order['acrescimo'] ?? '',
        'desconto'          => $order['desconto'] ?? '',
        'frete_valor'       => $order['frete_valor'] ?? '',
        'valor_total'       => $order['total'] ?? '',
    ],
];

// 6. Enviar para webhook (se configurado)
$webhook_endpoint = $config['statuschange_webhook']['endpoint'] ?? null;
$webhook_token = $config['statuschange_webhook']['token'] ?? null;

if ($webhook_endpoint && $webhook_token) {
    try {
        httpPost($webhook_endpoint, json_encode($payload), $webhook_token);
    } catch (Exception $e) {
        error_log('Webhook notification failed: ' . $e->getMessage());
        // Não interromper a requisição se o webhook falhar
    }
}