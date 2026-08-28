<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Classes;

final readonly class UrlMatcherResult
{
    /**
     * @param list<string> $consumed Path segments consumed by the matcher.
     * @param array<string, string> $params Parameters captured by the matcher.
     */
    public function __construct(
        public array $consumed = [],
        public array $params = []
    ) {
    }
}
