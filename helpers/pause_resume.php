<?php

function pauseWebhooksFor(int $seconds): void
{
    $pauseFile = __DIR__ . '/pause_webhook.txt';
    file_put_contents($pauseFile, time() + $seconds);
}

function resumeWebhooks(): void
{
    $pauseFile = __DIR__ . '/pause_webhook.txt';

    if (file_exists($pauseFile)) {
        unlink($pauseFile);
    }
}

function getWebhookPauseStatus(): array
{
    $pauseFile = __DIR__ . '/pause_webhook.txt';

    if (!file_exists($pauseFile)) {
        return [
            'paused' => false,
            'until'  => null
        ];
    }

    $until = (int) file_get_contents($pauseFile);

    if (time() >= $until) {
        unlink($pauseFile);
        return [
            'paused' => false,
            'until'  => null
        ];
    }

    return [
        'paused' => true,
        'until'  => $until
    ];
}

function isWebhookPaused(): bool
{
    $status = getWebhookPauseStatus();
    return $status['paused'];
}
