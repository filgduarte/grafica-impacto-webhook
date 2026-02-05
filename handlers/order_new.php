<?php

// 1. Validar dados de entrada obrigatórios
$customerId = $data['cliente'] ?? null;
$orderId = $data['pedido'] ?? null;

if (!$orderId || !$customerId) {
    http_response_code(400);
    error_log('Incomplete data: orderId or customerId missing. Payload: ' . json_encode($data));
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

$firstName = trim($customer['nome'] ?? '');
$lastName = trim($customer['sobrenome'] ?? '');
// 4. Montar payload para envio ao webhook
$payload = [
    'event' => 'NEW_ORDER',
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
        'numero'            => $orderId,
        'numero_de_itens'   => count($orderItems),
        'valor_total'       => $order['total'] ?? '',
        'acrescimo'         => $order['acrescimo'] ?? '',
        'desconto'          => $order['desconto'] ?? '',
        'frete_valor'       => $order['frete_valor'] ?? '',
    ],
];

// 5. Enviar para webhook (se configurado)
$webhook_endpoint = $config['ordernew_webhook']['endpoint'] ?? null;
$webhook_token = $config['ordernew_webhook']['token'] ?? null;

if ($webhook_endpoint && $webhook_token) {
    try {
        httpPost($webhook_endpoint, json_encode($payload), $webhook_token);
    } catch (Exception $e) {
        error_log('Webhook notification failed: ' . $e->getMessage());
        // Não interromper a requisição se o webhook falhar
    }
}