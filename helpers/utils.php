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

function sanitizeString(string $str): string
{
    // Remove qualquer caractere que NÃO seja:
    // letras, números, espaço, parênteses ou traço
    $sanitized = preg_replace('/[^a-zA-Z0-9 \(\)\-]/', '', $str);

    // Normaliza espaços múltiplos
    $sanitized = preg_replace('/\s+/', ' ', $sanitized);

    return trim($sanitized);
}