<?php

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$config = require __DIR__ . '/secrets/plugin.php';
require __DIR__ . '/helpers/pause_resume.php';

$authHeader = $_SERVER['X-Auth-Token'] ?? getallheaders()['X-Auth-Token'] ?? getallheaders()['x-auth-token'] ?? '';

if (!hash_equals($config['api_secret'], $authHeader)) {
    http_response_code(401);
    exit('Unauthorized: ' . print_r(getallheaders(), true));
}

// Lê payload
$data = json_decode(file_get_contents('php://input'), true) ?? [];

// Ação solicitada
$action = $data['action'] ?? null;

switch ($action) {
    case 'pause':
        $seconds = (int) ($data['seconds'] ?? 0);

        if ($seconds <= 0) {
            http_response_code(400);
            exit('Invalid seconds');
        }

        pauseApisFor($seconds);

        echo json_encode([
            'status'  => 'paused',
            'seconds' => $seconds
        ]);
        break;

    case 'resume':
        resumeApis();

        echo json_encode([
            'status' => 'resumed'
        ]);
        break;

    case 'status':
        echo json_encode(getApiPauseStatus());
        break;

    default:
        http_response_code(400);
        exit('Invalid action');
}
