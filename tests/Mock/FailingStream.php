<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\Mock;

final class FailingStream
{
    public mixed $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): false
    {
        return false;
    }

    public function stream_write(string $data): false
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /** @return array<string, int> */
    public function stream_stat(): array
    {
        return ['size' => 1];
    }
}
