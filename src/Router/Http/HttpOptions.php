<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

final readonly class HttpOptions
{
    public function __construct(
        public int $maxHeaderBytes = 65_536,
        public int $maxBodyBytes = 8_388_608,
        public float $readTimeout = 5.0,
        public float $writeTimeout = 5.0,
        public int $backlog = 128
    ) {
        if ($maxHeaderBytes < 4 || $maxHeaderBytes > PHP_INT_MAX - 2) {
            throw new \InvalidArgumentException('maxHeaderBytes must be between 4 and PHP_INT_MAX - 2.');
        }
        if ($maxBodyBytes < 0) {
            throw new \InvalidArgumentException('maxBodyBytes must be non-negative.');
        }
        if (!\is_finite($readTimeout) || $readTimeout <= 0) {
            throw new \InvalidArgumentException('readTimeout must be finite and greater than zero.');
        }
        if (!\is_finite($writeTimeout) || $writeTimeout <= 0) {
            throw new \InvalidArgumentException('writeTimeout must be finite and greater than zero.');
        }
        if ($backlog < 1) {
            throw new \InvalidArgumentException('backlog must be greater than zero.');
        }
    }
}
