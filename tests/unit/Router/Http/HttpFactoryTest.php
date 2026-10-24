<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use dvictorjhg\braidphp\Router\Http\HttpFactory;

#[CoversClass(HttpFactory::class)]
final class HttpFactoryTest extends TestCase
{
    public function testImplementsAllSixFactoryInterfaces(): void
    {
        $factory = new HttpFactory();
        foreach (
            [
            RequestFactoryInterface::class,
            ResponseFactoryInterface::class,
            ServerRequestFactoryInterface::class,
            StreamFactoryInterface::class,
            UploadedFileFactoryInterface::class,
            UriFactoryInterface::class
            ] as $interface
        ) {
            self::assertInstanceOf($interface, $factory);
        }
        self::assertSame('', (string) $factory->createUri());
        self::assertSame('https://example.test/path', (string) $factory->createUri('https://example.test/path'));
        self::assertSame(201, $factory->createResponse(201)->getStatusCode());
        self::assertSame('Custom', $factory->createResponse(202, 'Custom')->getReasonPhrase());
    }

    public function testRequestFactoriesDoNotReadGlobalsOrInferFromServerParameters(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/wrong';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/2.0';
        $_SERVER['HTTP_AUTHORIZATION'] = 'private';
        $_COOKIE = ['private' => 'cookie'];
        $_POST = ['private' => 'body'];
        $factory = new HttpFactory();
        $request = $factory->createRequest('PROPFIND', 'https://example.test/path');
        self::assertSame('PROPFIND', $request->getMethod());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame(['Host' => ['example.test']], $request->getHeaders());
        self::assertSame('', (string) $request->getBody());

        $serverParams = ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/wrong', 'custom' => ['value']];
        $serverRequest = $factory->createServerRequest('POST', '/chosen', $serverParams);
        self::assertSame($serverParams, $serverRequest->getServerParams());
        self::assertSame('POST', $serverRequest->getMethod());
        self::assertSame('/chosen', $serverRequest->getUri()->getPath());
        self::assertSame([], $serverRequest->getCookieParams());
        self::assertSame([], $serverRequest->getHeaders());
        self::assertNull($serverRequest->getParsedBody());
        self::assertSame([], $factory->createServerRequest('GET', '/')->getServerParams());
        self::assertSame('CONNECT', $factory->createRequest('CONNECT', 'https://example.test')->getMethod());
    }

    #[DataProvider('requestUris')]
    public function testRequestFactoriesDeriveOriginTargets(string $uri, string $target): void
    {
        $factory = new HttpFactory();
        foreach ([$factory->createRequest('GET', $uri), $factory->createServerRequest('GET', $uri)] as $request) {
            self::assertSame($target, $request->getRequestTarget());
            self::assertSame($uri, (string) $request->getUri());
        }
    }

    /** @return array<string, array{string, string}> */
    public static function requestUris(): array
    {
        return [
            'empty' => ['', '/'],
            'authority only' => ['https://example.test', '/'],
            'fragment' => ['https://example.test/path?query=1#fragment', '/path?query=1'],
            'query without path' => ['https://example.test?0#fragment', '/?0'],
            'relative fragment' => ['/path#fragment', '/path'],
        ];
    }

    public function testCreatesReadableStreamsAndUploadedFiles(): void
    {
        $factory = new HttpFactory();
        $stream = $factory->createStream('content');
        self::assertSame(0, $stream->tell());
        self::assertSame('content', (string) $stream);
        $file = $factory->createUploadedFile($stream, clientFilename: 'file.txt', clientMediaType: 'text/plain');
        self::assertSame(7, $file->getSize());
        self::assertSame('file.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame($stream, $file->getStream());
        $resource = \fopen('php://temp', 'w+');
        self::assertIsResource($resource);
        $wrapped = $factory->createStreamFromResource($resource);
        $wrapped->write('resource');
        self::assertSame('resource', (string) $wrapped);
        $wrapped->close();
        $fileStream = $factory->createStreamFromFile(__FILE__, 'rb');
        self::assertStringStartsWith('<?php', $fileStream->read(5));
        $fileStream->close();
    }

    #[DataProvider('invalidStatus')]
    public function testRejectsInvalidResponseStatus(int $status, string $phrase): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new HttpFactory())->createResponse($status, $phrase);
    }

    /** @return array<string, array{int, string}> */
    public static function invalidStatus(): array
    {
        return ['low' => [99, ''], 'high' => [600, ''], 'injection' => [200, "OK\r\nX: bad"]];
    }

    #[DataProvider('readModes')]
    public function testAcceptsNativeStreamModes(string $mode): void
    {
        $stream = (new HttpFactory())->createStreamFromFile(__FILE__, $mode);
        try {
            self::assertTrue($stream->isReadable());
            self::assertStringStartsWith('<?php', $stream->read(5));
            self::assertSame(\str_contains($mode, '+'), $stream->isWritable());
        } finally {
            $stream->close();
        }
    }

    /** @return array<string, array{string}> */
    public static function readModes(): array
    {
        return [
            'read' => ['r'],
            'binary' => ['rb'],
            'text' => ['rt'],
            'update' => ['r+'],
            'binary update' => ['r+b'],
            'update binary' => ['rb+'],
            'close on exec' => ['re'],
            'binary close on exec' => ['rbe'],
            'update close on exec binary' => ['r+eb'],
            'nonblocking' => ['rn'],
            'nonblocking close on exec' => ['rne'],
        ];
    }

    #[DataProvider('invalidModes')]
    public function testRejectsInvalidStreamMode(string $mode): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new HttpFactory())->createStreamFromFile(__FILE__, $mode);
    }

    /** @return array<string, array{string}> */
    public static function invalidModes(): array
    {
        return ['unknown' => ['invalid'], 'empty' => [''], 'nul' => ["r\0"], 'unknown flag' => ['r?']];
    }

    public function testRejectsMissingStreamFile(): void
    {
        $this->expectException(\RuntimeException::class);
        (new HttpFactory())->createStreamFromFile(__DIR__ . '/missing-' . \bin2hex(\random_bytes(8)));
    }

    public function testRejectsNonStreamResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $method = new \ReflectionMethod(HttpFactory::class, 'createStreamFromResource');
        $method->invoke(new HttpFactory(), null);
    }

    public function testRejectsWriteOnlyResourceWithoutClosingIt(): void
    {
        $resource = \fopen('/dev/null', 'w');
        self::assertIsResource($resource);
        try {
            $this->expectException(\InvalidArgumentException::class);
            (new HttpFactory())->createStreamFromResource($resource);
        } finally {
            \fclose($resource);
        }
    }
}
