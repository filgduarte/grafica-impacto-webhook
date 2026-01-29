# Webhook Gráfica Impacto

## 📌 Visão Geral

Este projeto é um **serviço intermediário de webhooks** que recebe eventos enviados pelo e-commerce da Gráfica Impacto, enriquece os dados recebidos consultando a API do próprio e-commerce e **reencaminha um novo webhook normalizado** para uma plataforma de integração com Whatsapp.

O sistema não possui frontend e atua exclusivamente como backend de integração.
<br><br>

## 📂 Estrutura do Projeto

```
/grafica-impacto-webhook
├─ index.php
├─ handlers/
│ ├─ item_status_update.php
│ └─ order_new.php
└─ helpers/
  ├─ http_client.php
  └─ utils.php

../secrets
└─ webhook.php
```
<br><br>

## 📥 Eventos Recebidos do E-commerce

### 1️⃣ Evento: `ITEM_STATUS_UPDATE`

Disparado quando o status de um item do pedido é alterado.

**Exemplo de JSON recebido:**

```
{
    "event":"ITEM_STATUS_UPDATE",
    "timestamp":"2026-01-29 08:40:53",
    "data":[
        {
            "id":"123456",
            "ftp":"123-001",
            "pedido":"123456",
            "cliente":"123",
            "produto":"123",
            "formato":"9x5 cm ‣",
            "formato_detalhes":"",
            "descricao":"",
            "status":"1",
            "copias":"0",
            "qtde":"500",
            "valor":"200",
            "custo":"0",
            "arte_valor":"0",
            "arte_tipo":"enviar",
            "arte_status":"1",
            "arte_arquivo":"[12345]",
            "arte_data":"2026-01-01 09:00:00",
            "arte_nome":"nome da arte",
            "arte_previa":"",
            "data":"2026-01-01 09:00:00",
            "vars":"4 Cantos",
            "previsao_producao":"2026-01-15 09:00:00",
            "previsao_entrega":"0000-00-00 00:00:00"
        }
    ]
}
```

### 2️⃣ Evento: `ORDER_NEW`

Disparado quando um novo pedido é criado.

**Exemplo de JSON recebido:**

```
{
    "event":"ORDER_NEW",
    "timestamp":"2026-01-29 09:26:14",
    "data":[
        {
            "id":"123456",
            "cliente":"123",
            "total":"123.45",
            "acrescimo":"0",
            "desconto":"0",
            "frete_valor":"0",
            "frete_tipo":"Correios",
            "frete_rastreio":"",
            "frete_balcao":"0",
            "frete_endereco":"0",
            "data":"2026-01-01 09:00:00",
            "origem":"1",
            "obs":"",
            "obs_interna":"",
            "nf":"",
            "cupom":"",
            "usuario":"1",
            "pdv":"1",
            "caixa":"0",
            "devolucao_completa":"0",
            "bling_id":"",
            "itens":[
                {
                    "id":"789012",
                    "ftp":"123-001",
                    "pedido":"123456",
                    "cliente":"123",
                    "produto":"0",
                    "formato":"",
                    "formato_detalhes":"",
                    "descricao":"",
                    "status":"1",
                    "copias":"0",
                    "qtde":"1",
                    "valor":"0.01",
                    "custo":"0",
                    "arte_valor":"0",
                    "arte_tipo":"sem",
                    "arte_status":"0",
                    "arte_arquivo":"",
                    "arte_data":"0000-00-00 00:00:00",
                    "arte_nome":"",
                    "arte_previa":"",
                    "data":"2026-01-29 09:26:13",
                    "vars":"",
                    "previsao_producao":"2026-01-30 00:00:00",
                    "previsao_entrega":"0000-00-00 00:00:00"
                }
            ],
            "pagamentos":[
                {
                    "id":"34806",
                    "cliente":"773",
                    "pedido":"23876",
                    "forma":"Pix",
                    "condicao":"",
                    "valor":"0.01",
                    "status":"1",
                    "link":"",
                    "data":"2026-01-29 09:26:13",
                    "saldo_anterior":"0",
                    "saldo_atual":"0",
                    "usuario":"19",
                    "obs":"",
                    "vencimento":"0000-00-00",
                    "data_pagto":"0000-00-00",
                    "bandeira":"",
                    "parcelas":"1",
                    "origem":"painel"
                }
            ]
        }
    ]
}
```


## 📡 Chamadas adicionais ao E-commerce
Dependendo do evento, o sistema pode consultar:

- `/pedido/{id}` → para obter dados do pedido
- `/cliente/{id}` → para obter dados do cliente

Essas chamadas são feitas via REST API utilizando token de autenticação.
<br><br><br>

# 📤 Webhook Enviado para a Plataforma de Mensagens

Após o tratamento dos dados, o sistema envia um webhook com payload normalizado.

### 1️⃣ Evento: `ITEM_STATUS_UPDATE`

Disparado quando o status de um item do pedido é alterado.

**Exemplo de JSON enviado:**
```
{
    "event":"ITEM_STATUS_UPDATE",
    "cliente":{
        "nome":"João",
        "sobrenome":"Silva",
        "nome_completo":"João Silva",
        "nome_fantasia":"",
        "celular":"",
        "email":"joaosilva@email.com.br",
        "telefone":"5521999999999",
        "revendedor":"Não",
        "tipo":"fisica",
    },
    "entrega":{
        "endereco":0,
        "entrega_retirada":"Retirada",
        "rastreio":"",
        "tipo":"Retirar na loja"
    },
    "pedido":{
        "arquivo":"nome do arquivo",
        "arte_status":1,
        "arte_tipo":"enviar",
        "copias":0,
        "descricao":"",
        "formato":"10 x 15cm",
        "formato_detalhes":"",
        "item":"123-001",
        "numero":"12345",
        "previsao_entrega":"0000-00-00 00:00:00",
        "previsao_producao":"2026-01-01 09:00:00",
        "produto":"Panfleto",
        "quantidade":500,
        "status":"Entregue",
        "variacao":""
        "valor":260,
        "acrescimo":0,
        "desconto":13,
        "frete_valor":0,
        "valor_total":247
    }
}
```

### 1️⃣ Evento: `ORDER_NEW`

Disparado quando um novo pedido é criado.

**Exemplo de JSON enviado:**
```
{
    "event":"ORDER_NEW",
    "cliente":{
        "nome":"João",
        "sobrenome":"Silva",
        "nome_completo":"João Silva",
        "nome_fantasia":"",
        "celular":"",
        "email":"joaosilva@email.com.br",
        "telefone":"5521999999999",
        "revendedor":"Não",
        "tipo":"fisica",
    },
    "entrega":{
        "endereco":0,
        "entrega_retirada":"Retirada",
        "rastreio":"",
        "tipo":"Retirar na loja"
    },
    "pedido":{
        "numero":"12345",
        "numero_de_itens":1,
        "valor":260,
        "acrescimo":0,
        "desconto":13,
        "frete_valor":0,
        "valor_total":247
    }
}
```