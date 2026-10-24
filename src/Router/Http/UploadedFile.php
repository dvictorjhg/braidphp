<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

final class UploadedFile implements UploadedFileInterface
{
    private const int COPY_CHUNK_BYTES = 8192;

    private bool $moved = false;
    private readonly ?int $size;

    public function __construct(
        private readonly StreamInterface $stream,
        ?int $size = null,
        private readonly int $error = \UPLOAD_ERR_OK,
        private readonly ?string $clientFilename = null,
        private readonly ?string $clientMediaType = null
    ) {
        if (
            ($size !== null && $size < 0)
            || !\in_array($error, [
                UPLOAD_ERR_OK,
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE,
                UPLOAD_ERR_PARTIAL,
                UPLOAD_ERR_NO_FILE,
                UPLOAD_ERR_NO_TMP_DIR,
                UPLOAD_ERR_CANT_WRITE,
                UPLOAD_ERR_EXTENSION
            ], true)
            || !$stream->isReadable()
        ) {
            throw new \InvalidArgumentException('Invalid uploaded file stream, size or error code.');
        }
        $this->size = $size ?? $stream->getSize();
    }

    #[\Override]
    public function getStream(): StreamInterface
    {
        if ($this->moved || $this->error !== \UPLOAD_ERR_OK) {
            throw new UploadedFileException('Uploaded file is unavailable or has already been moved.');
        }
        return $this->stream;
    }

    #[\Override]
    public function moveTo(string $targetPath): void
    {
        if ($targetPath === '' || \str_contains($targetPath, "\0") || \str_contains($targetPath, '://')) {
            throw new \InvalidArgumentException('Upload destination must be a nonempty local file path.');
        }
        $stream = $this->getStream();
        $source = $stream->getMetadata('uri');
        if (\is_string($source) && \str_starts_with($source, 'file://')) {
            $source = \parse_url($source, PHP_URL_PATH);
        }
        if (\is_string($source) && \realpath($source) !== false && \realpath($source) === \realpath($targetPath)) {
            throw new \InvalidArgumentException('Upload destination must differ from the source file.');
        }
        if (\is_string($source) && \is_uploaded_file($source)) {
            if (!@\move_uploaded_file($source, $targetPath)) {
                throw new UploadedFileException('Unable to move uploaded file.');
            }
        } else {
            $this->copyTo($stream, $targetPath);
            if (\is_string($source) && \is_file($source) && !@\unlink($source)) {
                throw new UploadedFileException('Unable to remove upload source file.');
            }
        }
        $this->moved = true;
        $stream->close();
    }

    private function copyTo(StreamInterface $stream, string $targetPath): void
    {
        $target = @\fopen($targetPath, 'wb');
        if ($target === false) {
            throw new UploadedFileException('Unable to open upload destination.');
        }
        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            while (true) {
                $content = $stream->read(self::COPY_CHUNK_BYTES);
                if ($content === '') {
                    if ($stream->eof()) {
                        break;
                    }
                    throw new UploadedFileException('Upload stream stopped before reaching its end.');
                }
                $this->writeChunk($target, $content);
            }
            if (!\fflush($target)) {
                throw new UploadedFileException('Unable to flush uploaded file.');
            }
        } finally {
            \fclose($target);
        }
    }

    /** @param resource $target */
    private function writeChunk(mixed $target, string $content): void
    {
        $offset = 0;
        $length = \strlen($content);
        while ($offset < $length) {
            $written = @\fwrite($target, \substr($content, $offset));
            if ($written === false || $written === 0) {
                throw new UploadedFileException('Unable to write uploaded file.');
            }
            $offset += $written;
        }
    }

    #[\Override]
    public function getSize(): ?int
    {
        return $this->size;
    }

    #[\Override]
    public function getError(): int
    {
        return $this->error;
    }

    #[\Override]
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    #[\Override]
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
