<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use dvictorjhg\braidphp\Router\Http\StreamFactory;

#[CoversClass(StreamFactory::class)]
final class StreamFactoryTest extends TestCase
{
    public function testCreatesStreamsFromScalarValues(): void
    {
        $values = [
            'text' => ['value' => 'text', 'content' => 'text'],
            'empty string' => ['value' => '', 'content' => ''],
            'true' => ['value' => true, 'content' => 'true'],
            'false' => ['value' => false, 'content' => 'false'],
            'integer' => ['value' => 42, 'content' => '42'],
            'float' => ['value' => 1.5, 'content' => '1.5'],
        ];

        foreach ($values as $case) {
            $stream = StreamFactory::create($case['value']);

            self::assertInstanceOf(StreamInterface::class, $stream);
            self::assertSame($case['content'], (string) $stream);
            $stream->close();
        }
    }

    public function testCreatesStreamFromResource(): void
    {
        $resource = \fopen('php://temp', 'w+');
        self::assertIsResource($resource);
        \fwrite($resource, 'resource');

        $stream = StreamFactory::create($resource);

        self::assertSame('resource', (string) $stream);
        $stream->close();
    }

    public function testReturnsExistingStream(): void
    {
        $stream = StreamFactory::create('content');

        self::assertSame($stream, StreamFactory::create($stream));
        $stream->close();
    }

    public function testCreatesStreamFromStringableObject(): void
    {
        $object = new class {
            public function __toString(): string
            {
                return 'object';
            }
        };

        $stream = StreamFactory::create($object);

        self::assertSame('object', (string) $stream);
        $stream->close();
    }

    public function testCreatesEmptyStreamFromNull(): void
    {
        $stream = StreamFactory::create(null);

        self::assertSame('', (string) $stream);
        $stream->close();
    }

    public function testRejectsUnsupportedObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Object must be stringable or implement StreamInterface');

        StreamFactory::create(new \stdClass());
    }

    public function testRejectsUnsupportedValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid resource type: array');

        StreamFactory::create([]);
    }

    public function testCreatesTemporaryStream(): void
    {
        $resource = StreamFactory::createTemporaryStream();

        self::assertSame('stream', \get_resource_type($resource));
        \fclose($resource);
    }
}
