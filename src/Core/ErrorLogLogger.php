<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Core;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

final class ErrorLogLogger extends AbstractLogger
{
    /** @param array<string, mixed> $context */
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (
            !\in_array($level, [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG
            ], true)
        ) {
            throw new InvalidArgumentException('Unsupported log level.');
        }

        $exception = $context['exception'] ?? null;
        \error_log((string) $message . ($exception instanceof \Throwable ? ': ' . $exception : ''));
    }
}
