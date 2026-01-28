<?php

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