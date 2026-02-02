<?php
    $config = require __DIR__ . '/../secrets/plugin.php';
    $authSecret = $_GET['auth_secret'] ?? '';

    if (!$authSecret || !hash_equals($config['auth_secret'], $authSecret)) {
        http_response_code(403);
        error_log('QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? ''));
error_log('GET: ' . print_r($_GET, true));
        exit('Acesso negado');
    }

    $darkMode = filter_var($_GET['dark_mode'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="stylesheet" href="style.css?version=202631012245">
    <script
        type="text/javascript"
        src="https://andrody.github.io/kinbox-lib/kinboxjs.js"
    ></script>
    <script
        type="text/javascript"
        src="plugin.js?version=1"
        data-endpoint=<?php echo $config['api_endpoint'] ?>
        data-secret=<?php echo $config['api_secret'] ?>
    ></script>
</head>
<body class="<?php echo $darkMode ? 'dark-mode' : 'light-mode'; ?>">
<div class="container">
    <div id="status" class="status">Carregando...</div>
    <div id="timer" class="timer"></div>
    
    <div id="controls" class="controls">
        <div id="pause-control" class="pause-control hidden">
            <button id="pauseButton" onClick="pause()">⏸️ Pausar</button>
            <div>por <span id="pauseSeconds" contenteditable="true">30</span> segundos.</div>
        </div>
        <div id="resume-control" class="resume-control hidden">
            <button id="resumeButton" onClick="resume()">▶️ Retomar agora</button>
        </div>
    </div>
</div>
</body>
</html>