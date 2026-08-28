<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    private const string DETACHED_MESSAGE = 'Stream is detached';

    /** @var resource|null */
    private $stream;
    private ?int $size;
    private bool $seekable;
    private bool $writable;
    private bool $readable;

    /** @var list<string> */
    private static array $readModes = [
        'r',
        'w+',
        'r+',
        'x+',
        'c+',
        'rb',
        'w+b',
        'r+b',
        'x+b',
        'c+b',
        'rt',
        'w+t',
        'r+t',
        'x+t',
        'c+t',
        'a+',
    ];

    /** @var list<string> */
    private static array $writeModes = [
        'w',
        'w+',
        'rw',
        'r+',
        'x+',
        'c+',
        'wb',
        'w+b',
        'r+b',
        'x+b',
        'c+b',
        'w+t',
        'r+t',
        'x+t',
        'c+t',
        'a',
        'a+',
    ];

    /**
     * @param resource $stream
     * @param int|null $size Stream size in bytes, or null if unknown.
     */
    public function __construct(mixed $stream, ?int $size = null)
    {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new \InvalidArgumentException('Stream is not a valid resource.');
        }
        $this->stream = $stream;
        $this->size = $size;

        $meta = \stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = \in_array($meta['mode'], self::$readModes, true);
        $this->writable = \in_array($meta['mode'], self::$writeModes, true);
    }

    public function __destruct()
    {
        $this->close();
    }

    #[\Override]
    public function __toString(): string
    {
        try {
            if (isset($this->stream)) {
                $this->seek(0);
                return $this->getContents();
            }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Creates a stream from the supplied value.
     *
     * @param bool|float|int|object|resource|string|null $resource Stream content.
     * @param int|null $size Stream size in bytes, if known.
     *
     * @return StreamInterface
     * @throws \InvalidArgumentException If $resource is invalid.
     * @throws StreamException If a temporary stream cannot be created.
     */
    public static function of(mixed $resource, ?int $size = null): StreamInterface
    {
        return \dvictorjhg\braidphp\Router\Http\StreamFactory::create($resource, $size);
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    #[\Override]
    public function close(): void
    {
        if (isset($this->stream)) {
            if (\is_resource($this->stream)) {
                \fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    #[\Override]
    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }
        $rest = $this->stream;
        unset($this->stream);
        $this->size = null;
        $this->readable = $this->writable = $this->seekable = false;
        return $rest;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    #[\Override]
    public function getSize(): ?int
    {
        $size = null;
        if (isset($this->stream)) {
            if ($this->size !== null) {
                $size = $this->size;
            } else {
                $stats = \fstat($this->stream);
                if (isset($stats['size'])) {
                    $this->size = $stats['size'];
                    $size = $this->size;
                }
            }
        }
        return $size;
    }

    /**
     * Returns the current position of the file read/write pointer.
     *
     * @return int Position of the file pointer
     * @throws StreamException on error.
     */
    #[\Override]
    public function tell(): int
    {
        if (!isset($this->stream)) {
            throw new StreamException(self::DETACHED_MESSAGE);
        }
        $result = \ftell($this->stream);
        if ($result === false) {
            throw new StreamException('Unable to determine stream position');
        }
        return $result;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    #[\Override]
    public function eof(): bool
    {
        if (!isset($this->stream)) {
            throw new StreamException(self::DETACHED_MESSAGE);
        }
        return \feof($this->stream);
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    #[\Override]
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link https://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws StreamException on failure.
     */
    #[\Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!isset($this->stream)) {
            throw new StreamException(self::DETACHED_MESSAGE);
        }
        if (!$this->seekable) {
            throw new StreamException('Stream is not seekable');
        }
        if (\fseek($this->stream, $offset, $whence) === -1) {
            throw new StreamException('Unable to seek to stream position '
                . $offset . ' with whence ' . \var_export($whence, true));
        }
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link https://www.php.net/manual/en/function.fseek.php
     * @throws StreamException on failure.
     */
    #[\Override]
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    #[\Override]
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws StreamException on failure.
     */
    #[\Override]
    public function write(string $string): int
    {
        if (!isset($this->stream)) {
            throw new StreamException(self::DETACHED_MESSAGE);
        }
        if (!$this->writable) {
            throw new StreamException('Cannot write to a non-writable stream');
        }
        // We can't know the size after writing anything
        $this->size = null;
        $result = \fwrite($this->stream, $string);
        if ($result === false) {
            throw new StreamException('Unable to write to stream');
        }
        return $result;
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    #[\Override]
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws StreamException if an error occurs.
     */
    #[\Override]
    public function read(int $length): string
    {
        if (!isset($this->stream)) {
            throw new StreamException(self::DETACHED_MESSAGE);
        }
        if (!$this->readable) {
            throw new StreamException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new StreamException('Length parameter cannot be negative');
        }
        if (0 === $length) {
            return '';
        }
        $string = \fread($this->stream, $length);
        if (false === $string) {
            throw new StreamException('Unable to read from stream');
        }
        return $string;
    }

    /**
     * Returns the remaining contents in a string.
     *
     * @return string
     * @throws StreamException if unable to read or an error occurs while
     *     reading.
     */
    #[\Override]
    public function getContents(): string
    {
        if (!isset($this->stream)) {
            throw new StreamException(self::DETACHED_MESSAGE);
        }
        $contents = \stream_get_contents($this->stream);
        if ($contents === false) {
            throw new StreamException('Unable to read stream contents');
        }
        return $contents;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link https://www.php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return mixed Returns an associative array if no key is provided. Returns
     *     a specific key value if a key is provided, or null if the key is not found.
     */
    #[\Override]
    public function getMetadata(?string $key = null)
    {
        if (!isset($this->stream)) {
            return $key ? null : [];
        } elseif (!$key) {
            return \stream_get_meta_data($this->stream);
        }
        $meta = \stream_get_meta_data($this->stream);
        return $meta[$key] ?? null;
    }

    /**
     * Prepares the object for serialization.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        // We can't serialize resources, so we save the content and metadata
        $metadata = isset($this->stream) ? \stream_get_meta_data($this->stream) : [];
        $content = (string) $this;

        return [
            'content' => $content,
            'size' => $this->size,
            'seekable' => $this->seekable,
            'writable' => $this->writable,
            'readable' => $this->readable,
            'metadata' => $metadata
        ];
    }

    /**
     * Restores the object state from serialized data.
     *
     * @param array<string, mixed> $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $content = $data['content'] ?? '';
        $size = $data['size'] ?? null;
        $seekable = $data['seekable'] ?? false;
        $writable = $data['writable'] ?? false;
        $readable = $data['readable'] ?? false;

        if (
            !\is_string($content)
            || ($size !== null && !\is_int($size))
            || !\is_bool($seekable)
            || !\is_bool($writable)
            || !\is_bool($readable)
        ) {
            throw new \UnexpectedValueException('Invalid serialized stream data.');
        }

        // Create a new stream with the saved content
        $stream = \dvictorjhg\braidphp\Router\Http\StreamFactory::createTemporaryStream(
            'Failed to create stream during unserialization'
        );

        if ($content !== '') {
            \fwrite($stream, $content);
            \fseek($stream, 0);
        }

        $this->stream = $stream;
        $this->size = $size;
        $this->seekable = $seekable;
        $this->writable = $writable;
        $this->readable = $readable;
    }
}
