<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

final class HttpFactory implements
    RequestFactoryInterface,
    \Psr\Http\Message\ResponseFactoryInterface,
    \Psr\Http\Message\ServerRequestFactoryInterface,
    \Psr\Http\Message\StreamFactoryInterface,
    \Psr\Http\Message\UploadedFileFactoryInterface,
    \Psr\Http\Message\UriFactoryInterface
{
    #[\Override]
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request(protocolVersion: '1.1', method: $method, uri: $uri, body: '', serverParams: []);
    }

    #[\Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response(statusCode: $code, reasonPhrase: $reasonPhrase);
    }

    /** @param array<string|int, mixed> $serverParams */
    #[\Override]
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new Request(protocolVersion: '1.1', method: $method, uri: $uri, body: '', serverParams: $serverParams);
    }

    #[\Override]
    public function createStream(string $content = ''): StreamInterface
    {
        return StreamFactory::create($content);
    }

    #[\Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if (\preg_match('/^[rwaxc][bten+]*$/D', $mode) !== 1 || \str_contains($filename, "\0")) {
            throw new \InvalidArgumentException('Invalid file name or stream mode.');
        }
        $resource = @\fopen($filename, $mode);
        if ($resource === false) {
            throw new StreamException('Unable to open stream file.');
        }
        return new Stream($resource);
    }

    /** @param mixed $resource */
    #[\Override]
    public function createStreamFromResource($resource): StreamInterface
    {
        if (!\is_resource($resource) || \get_resource_type($resource) !== 'stream') {
            throw new \InvalidArgumentException('Stream is not a valid resource.');
        }
        $stream = new Stream($resource);
        if (!$stream->isReadable()) {
            $stream->detach();
            throw new \InvalidArgumentException('Stream resource must be readable.');
        }
        return $stream;
    }

    #[\Override]
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    #[\Override]
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
