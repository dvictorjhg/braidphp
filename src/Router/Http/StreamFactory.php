<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\StreamInterface;

final class StreamFactory
{
    private const string TEMP_STREAM_URI = 'php://temp';

    public static function create(mixed $resource, ?int $size = null): StreamInterface
    {
        return match (true) {
            \is_scalar($resource) => self::fromScalar($resource, $size),
            \is_resource($resource) => new Stream($resource, $size),
            \is_object($resource) => self::fromObject($resource, $size),
            $resource === null => self::fromNull($size),
            default => throw new \InvalidArgumentException(
                'Invalid resource type: ' . \gettype($resource)
            ),
        };
    }

    /**
     * @param bool|float|int|string $resource
     */
    private static function fromScalar(bool|float|int|string $resource, ?int $size): StreamInterface
    {
        $stream = self::createTemporaryStream();
        if ($resource !== '') {
            if (\is_bool($resource)) {
                $resource = $resource ? 'true' : 'false';
            }
            \fwrite($stream, (string) $resource);
            \fseek($stream, 0);
        }

        return new Stream($stream, $size);
    }

    private static function fromObject(object $resource, ?int $size): StreamInterface
    {
        if ($resource instanceof StreamInterface) {
            return $resource;
        }

        if (\method_exists($resource, '__toString')) {
            return self::create((string) $resource, $size);
        }

        throw new \InvalidArgumentException('Object must be stringable or implement StreamInterface');
    }

    private static function fromNull(?int $size): StreamInterface
    {
        return new Stream(self::createTemporaryStream(), $size);
    }

    /**
     * @return resource
     */
    public static function createTemporaryStream(string $errorMessage = 'Failed to create stream'): mixed
    {
        $stream = \fopen(self::TEMP_STREAM_URI, 'r+');
        if ($stream === false) {
            throw new StreamException($errorMessage);
        }

        return $stream;
    }
}
