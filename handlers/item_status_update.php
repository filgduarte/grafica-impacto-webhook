<?php

// 1. Validar dados de entrada
$item = $data['data'][0] ?? null;

if (!$item) {
    http_response_code(422);
    exit('Dados do item ausentes');
}

// 2. Extrair dados necessários
$orderId   = $item['pedido'] ?? null;
$orderItemNumber = $item['ftp'] ?? null;
$orderItemStatus  = $item['status'] ?? null;
$customerId = $item['cliente'] ?? null;

if (!$orderId || !$customerId) {
    http_response_code(422);
    exit('Dados incompletos');
}

// 3. Buscar dados do pedido e do cliente no e-commerce
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
    $customerRaw = httpGet(
        $config['ecommerce_restapi']['endpoint'] . '/cliente/' . $customerId,
        $config['ecommerce_restapi']['token']
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Erro ao buscar cliente');
}


$order = $orderRaw['registros'][0] ?? null;
$customer = $customerRaw['registros'][0] ?? null;

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

// 4. Obter dados relevantes


// 5. Montar payload
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
    ✅ Variação do item
    ✅ Nome do arquivo
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