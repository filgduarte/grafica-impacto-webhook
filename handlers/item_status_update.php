<?php

// 1. Validar dados de entrada
$item = $data['data'][0] ?? null;

if (!$item) {
    http_response_code(200);
    error_log('No item data found in payload: ' . json_encode($data));
    exit('Dados do item ausentes');
}

// 2. Extrair dados necessários
$orderId = $item['pedido'] ?? null;
$orderItemNumber = $item['ftp'] ?? null;
$orderItemStatus = $item['status'] ?? null;
$orderItemProduct = $item['produto'] ?? null;
$customerId = $item['cliente'] ?? null;

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
// 3.2 Dados do produto
try {
    $productRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/produto/' . $orderItemProduct,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(200);
    exit('Erro ao buscar produto');
}
// 3.3 Dados do cliente
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
$product = $productRaw['registros'][0] ?? null;
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

$orderItem = null;

foreach ($orderItems as $itm) {
    if ( ($itm['ftp'] ?? null) === $orderItemNumber) {
        $orderItem = $itm;
        break;
    }
}  

if (!$orderItem) {
    http_response_code(200);
    error_log('Order item not found: Order ID ' . $orderId . ', Item Number ' . $orderItemNumber);
    exit('Item não encontrado no pedido');
}

$webhook_endpoint = $config['statuschange_webhook']['endpoint'];
$webhook_token = $config['statuschange_webhook']['token'];

// 5. Montar payload
$phone = normalizePhone($customer['telefone'] ?? '');
$cell = normalizePhone($customer['celular'] ?? '');
$status = $orderItemStatus;
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

if ($orderItemStatus < count($defaultStatuses)) {
    $status = $defaultStatuses[$orderItemStatus];
}

if (empty($phone) && empty($cell)) {
    http_response_code(200);
    error_log('Customer has no phone or cell: ' . $customerId);
    exit('Cliente sem telefone ou celular');
}

$payload = [
    'event' => 'ITEM_STATUS_UPDATE',
    'cliente' => [
        'nome'              => $customer['nome'] ?? '',
        'sobrenome'         => $customer['sobrenome'] ?? '',
        'nome_completo'     => trim(($customer['nome'] ?? '') . ' ' . ($customer['sobrenome'] ?? '')),
        'nome_fantasia'     => $customer['fantasia'] ?? '',
        'celular'           => $cell,
        'email'             => $customer['email_log'] ?? '',
        'telefone'          => $phone,
        'revendedor'        => ($customer['revendedor'] == 0) ? 'Não' : 'Sim',
        'tipo'              => $customer['tipo'] ?? '',
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
        'produto'           => $product['titulo'] ?? '',
        'quantidade'        => $orderItem['qtde'] ?? '',
        'status'            => $status ?? '',
        'variacao'          => $orderItem['vars'] ?? '',
        'valor'             => $orderItem['valor'] ?? '',
        'acrescimo'         => $order['acrescimo'] ?? '',
        'desconto'          => $order['desconto'] ?? '',
        'frete_valor'       => $order['frete_valor'] ?? '',
        'valor_total'       => $order['total'] ?? '',
    ],
];