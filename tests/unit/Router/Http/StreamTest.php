<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\Stream;
use dvictorjhg\braidphp\Router\Http\StreamException;

#[CoversClass(Stream::class)]
final class StreamTest extends TestCase
{
    public function testConstructsReadableWritableStream(): void
    {
        $resource = $this->resourceFrom('hello');
        $stream = new Stream($resource);

        self::assertTrue($stream->isSeekable());
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertSame(5, $stream->getSize());
        self::assertSame(0, $stream->tell());
        self::assertFalse($stream->eof());
        self::assertSame('he', $stream->read(2));
        self::assertSame(2, $stream->tell());
        self::assertSame('llo', $stream->read(10));
        self::assertTrue($stream->eof());
        self::assertSame('', $stream->read(0));

        $stream->close();
    }

    public function testWritesAndReadsStreamContents(): void
    {
        $resource = \fopen('php://temp', 'w+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        self::assertSame(5, $stream->write('hello'));
        self::assertSame(5, $stream->getSize());
        $stream->seek(0);
        self::assertSame('hello', $stream->getContents());
        $stream->rewind();
        self::assertSame('hello', (string) $stream);

        $stream->close();
    }

    public function testUsesExplicitSizeAndExposesMetadata(): void
    {
        $resource = $this->resourceFrom('hello');
        $stream = new Stream($resource, 99);

        self::assertSame(99, $stream->getSize());
        self::assertIsArray($stream->getMetadata());
        self::assertSame('w+b', $stream->getMetadata('mode'));
        self::assertNull($stream->getMetadata('missing'));

        $stream->close();
    }

    public function testRejectsInvalidResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream is not a valid resource.');

        $class = new \ReflectionClass(Stream::class);
        $class->newInstance('invalid');
    }

    public function testRejectsWritesToReadOnlyStream(): void
    {
        $resource = \fopen('php://temp', 'r');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Cannot write to a non-writable stream');
        $stream->write('content');
    }

    public function testRejectsReadsFromWriteOnlyStream(): void
    {
        $resource = \fopen('php://output', 'w');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        try {
            $stream->read(1);
            self::fail('Expected read to fail for a write-only stream.');
        } catch (StreamException $exception) {
            self::assertSame('Cannot read from non-readable stream', $exception->getMessage());
        } finally {
            $stream->detach();
        }
    }

    public function testRejectsNegativeReadLength(): void
    {
        $stream = Stream::of('content');

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');
        $stream->read(-1);
    }

    public function testRejectsInvalidSeekWhence(): void
    {
        $stream = Stream::of('content');

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Unable to seek to stream position');
        $stream->seek(0, 999);
    }

    public function testRejectsSeekOnNonSeekableStream(): void
    {
        $resource = \fopen('php://stdin', 'r');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        self::assertFalse($stream->isSeekable());
        self::assertSame('', (string) $stream);

        try {
            $stream->seek(0);
            self::fail('Expected seek to fail for a non-seekable stream.');
        } catch (StreamException $exception) {
            self::assertSame('Stream is not seekable', $exception->getMessage());
        } finally {
            $stream->detach();
        }
    }

    public function testDetachedStreamHasNoResourceOperations(): void
    {
        $stream = Stream::of('content');
        $detached = $stream->detach();

        self::assertIsResource($detached);
        self::assertNull($stream->detach());
        self::assertNull($stream->getSize());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('mode'));

        foreach (['tell', 'eof', 'rewind', 'getContents'] as $method) {
            try {
                $stream->{$method}();
                self::fail("Expected detached stream method '$method' to fail.");
            } catch (StreamException $exception) {
                self::assertSame('Stream is detached', $exception->getMessage());
            }
        }

        try {
            $stream->seek(0);
            self::fail('Expected detached stream seek to fail.');
        } catch (StreamException $exception) {
            self::assertSame('Stream is detached', $exception->getMessage());
        }

        try {
            $stream->write('content');
            self::fail('Expected detached stream write to fail.');
        } catch (StreamException $exception) {
            self::assertSame('Stream is detached', $exception->getMessage());
        }

        try {
            $stream->read(1);
            self::fail('Expected detached stream read to fail.');
        } catch (StreamException $exception) {
            self::assertSame('Stream is detached', $exception->getMessage());
        }

        self::assertSame('', (string) $stream);
        $stream->close();
    }

    public function testSerializesAndRestoresStream(): void
    {
        $stream = Stream::of('content', 7);

        $serialized = \serialize($stream);
        $restored = \unserialize($serialized);

        self::assertInstanceOf(Stream::class, $restored);
        self::assertSame('content', (string) $restored);
        self::assertSame(7, $restored->getSize());
        $restored->close();
        $stream->close();
    }

    public function testSerializesAndRestoresDetachedStream(): void
    {
        $stream = Stream::of('content');
        $stream->detach();

        $restored = \unserialize(\serialize($stream));

        self::assertInstanceOf(Stream::class, $restored);
        self::assertFalse($restored->isSeekable());
        self::assertFalse($restored->isReadable());
        self::assertFalse($restored->isWritable());
        self::assertSame('', (string) $restored);
        $restored->close();
        $stream->close();
    }

    public function testRejectsInvalidSerializedStreamData(): void
    {
        $stream = Stream::of('content');
        $method = new \ReflectionMethod(Stream::class, '__unserialize');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid serialized stream data.');
        $method->invoke($stream, ['content' => 123]);
    }

    /** @return resource */
    private function resourceFrom(string $contents)
    {
        $resource = \fopen('php://temp', 'w+');
        self::assertIsResource($resource);
        \fwrite($resource, $contents);
        \rewind($resource);

        return $resource;
    }
}
