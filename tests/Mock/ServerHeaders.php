<?php

declare(strict_types=1);

/** @return array<int|string, mixed> */
function getallheaders(): array
{
    return ['X-Trace' => 'sapi-header', 0 => 'invalid-name', 'X-Invalid' => ['invalid-value']];
}
