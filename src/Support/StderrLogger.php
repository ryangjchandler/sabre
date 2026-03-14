<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Support;

use Psr\Log\AbstractLogger;

final class StderrLogger extends AbstractLogger
{
    public function __construct(private readonly bool $debugEnabled = false) {}

    /**
     * @param  mixed  $message
     * @param  array<string, mixed>  $context
     */
    public function log($level, $message, array $context = []): void
    {
        if ($level === 'debug' && ! $this->debugEnabled) {
            return;
        }

        $payload = [
            'time' => date('c'),
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];

        fwrite(STDERR, '[sabre] '.json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
