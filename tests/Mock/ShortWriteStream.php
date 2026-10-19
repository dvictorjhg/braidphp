<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\Mock;

final class ShortWriteStream
{
    public mixed $context;
    public static string $written = '';
    public static bool $stall = false;
    public static bool $pause = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        if (self::$stall) {
            return 0;
        }
        if (self::$pause) {
            self::$pause = false;
            return 0;
        }
        self::$pause = true;
        $chunk = \substr($data, 0, 7);
        self::$written .= $chunk;
        return \strlen($chunk);
    }

    public function stream_eof(): bool
    {
        return false;
    }
}
