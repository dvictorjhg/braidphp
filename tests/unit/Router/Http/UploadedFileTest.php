<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\HttpFactory;
use dvictorjhg\braidphp\Router\Http\UploadedFile;
use dvictorjhg\braidphp\Router\Http\UploadedFileException;

#[CoversClass(UploadedFile::class)]
final class UploadedFileTest extends TestCase
{
    public function testMovesEntireStreamAndRejectsSubsequentAccess(): void
    {
        $stream = (new HttpFactory())->createStream(\str_repeat('data', 4096));
        $stream->seek(100);
        $file = new UploadedFile($stream);
        $path = \tempnam(\sys_get_temp_dir(), 'braid-upload-');
        self::assertIsString($path);
        try {
            $file->moveTo($path);
            self::assertSame(\str_repeat('data', 4096), \file_get_contents($path));
            self::assertFalse($stream->isReadable());
            self::assertSame(16384, $file->getSize());
            $this->expectException(\RuntimeException::class);
            $file->getStream();
        } finally {
            \unlink($path);
        }
    }

    #[DataProvider('sourcePrefixes')]
    public function testMovesFileBackedUploadAndRemovesSource(string $prefix): void
    {
        $source = \tempnam(\sys_get_temp_dir(), 'braid-upload-source-');
        $target = \tempnam(\sys_get_temp_dir(), 'braid-upload-target-');
        self::assertIsString($source);
        self::assertIsString($target);
        \file_put_contents($source, 'complete upload');
        $stream = (new HttpFactory())->createStreamFromFile($prefix . $source);
        try {
            $stream->seek(4);
            (new UploadedFile($stream))->moveTo($target);
            self::assertFileDoesNotExist($source);
            self::assertSame('complete upload', \file_get_contents($target));
            self::assertFalse($stream->isReadable());
        } finally {
            $stream->close();
            if (\is_file($source)) {
                \unlink($source);
            }
            \unlink($target);
        }
    }

    /** @return array<string, array{string}> */
    public static function sourcePrefixes(): array
    {
        return ['local path' => [''], 'file URI' => ['file://']];
    }

    public function testFailedFileMoveRetainsSourceAndAllowsRetry(): void
    {
        $source = \tempnam(\sys_get_temp_dir(), 'braid-upload-source-');
        $target = \tempnam(\sys_get_temp_dir(), 'braid-upload-target-');
        self::assertIsString($source);
        self::assertIsString($target);
        \file_put_contents($source, 'keep');
        $stream = (new HttpFactory())->createStreamFromFile($source);
        $file = new UploadedFile($stream);
        try {
            try {
                $file->moveTo($target . '/missing');
                self::fail('An invalid destination must fail.');
            } catch (UploadedFileException) {
                self::assertSame('keep', \file_get_contents($source));
                self::assertSame($stream, $file->getStream());
            }
            $file->moveTo($target);
            self::assertFileDoesNotExist($source);
            self::assertSame('keep', \file_get_contents($target));
        } finally {
            $stream->close();
            if (\is_file($source)) {
                \unlink($source);
            }
            \unlink($target);
        }
    }

    public function testCannotMoveTwice(): void
    {
        $file = new UploadedFile((new HttpFactory())->createStream('data'));
        $path = \tempnam(\sys_get_temp_dir(), 'braid-upload-');
        self::assertIsString($path);
        try {
            $file->moveTo($path);
            $this->expectException(\RuntimeException::class);
            $file->moveTo($path);
        } finally {
            \unlink($path);
        }
    }

    #[DataProvider('sourcePrefixes')]
    public function testRejectsMovingOntoItsSourceWithoutTruncation(string $prefix): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'braid-upload-');
        self::assertIsString($path);
        \file_put_contents($path, 'keep');
        $stream = (new HttpFactory())->createStreamFromFile($prefix . $path);
        try {
            $this->expectException(\InvalidArgumentException::class);
            (new UploadedFile($stream))->moveTo($path);
        } finally {
            self::assertSame('keep', \file_get_contents($path));
            $stream->close();
            \unlink($path);
        }
    }

    public function testFailedDestinationDoesNotMarkFileAsMoved(): void
    {
        $file = new UploadedFile((new HttpFactory())->createStream('data'), size: 4);
        try {
            $file->moveTo(\sys_get_temp_dir() . '/missing-' . \bin2hex(\random_bytes(8)) . '/file');
            self::fail('A missing destination directory must fail.');
        } catch (\RuntimeException $exception) {
            self::assertInstanceOf(UploadedFileException::class, $exception);
            self::assertSame('data', (string) $file->getStream());
        }
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsInvalidDestinations(string $path): void
    {
        $file = new UploadedFile((new HttpFactory())->createStream('data'));
        $this->expectException(\InvalidArgumentException::class);
        $file->moveTo($path);
    }

    /** @return array<string, array{string}> */
    public static function invalidPaths(): array
    {
        return ['empty' => [''], 'nul' => ["invalid\0path"], 'wrapper' => ['php://temp']];
    }

    public function testUploadErrorPreventsStreamAccess(): void
    {
        $file = new UploadedFile((new HttpFactory())->createStream(), error: \UPLOAD_ERR_NO_FILE);
        self::assertSame(\UPLOAD_ERR_NO_FILE, $file->getError());
        self::assertNull($file->getClientFilename());
        self::assertNull($file->getClientMediaType());
        $this->expectException(\RuntimeException::class);
        $file->getStream();
    }

    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(?int $size, int $error): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::assertInstanceOf(
            UploadedFile::class,
            new UploadedFile((new HttpFactory())->createStream(), $size, $error)
        );
    }

    /** @return array<string, array{int|null, int}> */
    public static function invalidMetadata(): array
    {
        return ['size' => [-1, 0], 'negative error' => [null, -1], 'unused error' => [null, 5]];
    }
}
