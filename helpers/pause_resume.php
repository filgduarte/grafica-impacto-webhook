<?php

function pauseApisFor(int $seconds): void
{
    $pauseFile = __DIR__ . '/pause_api.txt';
    file_put_contents($pauseFile, time() + $seconds);
}

function resumeApis(): void
{
    $pauseFile = __DIR__ . '/pause_api.txt';

    if (file_exists($pauseFile)) {
        unlink($pauseFile);
    }
}

function getApiPauseStatus(): array
{
    $pauseFile = __DIR__ . '/pause_api.txt';

    if (!file_exists($pauseFile)) {
        return [
            'paused' => false,
            'until'  => null,
            'remaining' => 0
        ];
    }

    $until = (int) file_get_contents($pauseFile);

    if (time() >= $until) {
        unlink($pauseFile);
        return [
            'paused' => false,
            'until'  => null,
            'remaining' => 0
        ];
    }

    return [
        'paused' => true,
        'until'  => $until,
        'remaining' => max(0, $until - time())
    ];
}

function isApiPaused(): bool
{
    $status = getApiPauseStatus();
    return $status['paused'];
}
