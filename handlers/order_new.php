<?php

// 1. Validar dados de entrada
$itens = $data['data'][0]['itens'] ?? null;

if (!$itens) {
    http_response_code(200);
    error_log('No items data found in payload: ' . json_encode($data));
    exit('Dados dos itens ausentes');
}

// 2. Extrair dados necessários
$orderId = $itens[0]['pedido'] ?? null;
$customerId = $itens[0]['cliente'] ?? null;

if (!$orderId || !$customerId) {
    http_response_code(200);
    error_log('Incomplete data: orderId or customerId missing. Payload: ' . json_encode($data));
    exit('Dados incompletos');
}

// 3. Buscar dados no e-commerce
// 3.1 Dados do pedido
try {
    $orderRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/pedido/' . $orderId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(200);
    exit('Erro ao buscar pedido');
}
// 3.1 Dados do cliente
try {
    $customerRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/cliente/' . $customerId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(200);
    exit('Erro ao buscar cliente');
}

$order = $orderRaw['registros'][0] ?? null;
$customer = $customerRaw['registros'][0] ?? null;

if (!$order) {
    http_response_code(200);
    error_log('Order not found: ' . $orderId);
    exit('Pedido não encontrado');
}

$orderItems = $order['itens'] ?? [];

if (empty($orderItems)) {
    http_response_code(200);
    error_log('Order has no items: ' . $orderId);
    exit('Pedido sem itens');
}

$orderItemsCount = count($orderItems);

// 5. Montar payload
$phone = normalizePhone($customer['telefone'] ?? '');
$cell = normalizePhone($customer['celular'] ?? '');

if (empty($phone) && empty($cell)) {
    http_response_code(200);
    error_log('Customer has no phone or cell: ' . $customerId);
    exit('Cliente sem telefone ou celular');
}

$payload = [
    'event' => 'novo_pedido',
    'cliente' => [
        'nome'              => $customer['nome'] ?? '',
        'sobrenome'         => $customer['sobrenome'] ?? '',
        'nome_completo'     => trim(($customer['nome'] ?? '') . ' ' . ($customer['sobrenome'] ?? '')),
        'nome_fantasia'     => $customer['fantasia'] ?? '',
        'celular'           => $cell,
        'email'             => $customer['email_log'] ?? '',
        'telefone'          => $phone,
        'revendedor'        => ($customer['revendedor'] == 1) ? 'Sim' : 'Não',
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
        'numero_de_itens'   => $orderItemsCount,
        'produto'           => $order['produto'] ?? '',
        'previsao_entrega'  => $orderItem['previsao_entrega'] ?? '',
        'previsao_producao' => $orderItem['previsao_producao'] ?? '',
        'valor_total'       => $order['total'] ?? '',
        'acrescimo'         => $order['acrescimo'] ?? '',
        'desconto'          => $order['desconto'] ?? '',
        'frete_valor'       => $order['frete_valor'] ?? '',
    ],
];