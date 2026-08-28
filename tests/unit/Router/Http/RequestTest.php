<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\Uri;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    public function testParsesQueryParameters(): void
    {
        $request = new Request(
            method: HttpMethod::GET,
            uri: '/hello?name=Victor&tag[]=php&tag[]=ng'
        );

        self::assertSame('Victor', $request->getQueryParams()['name']);
        self::assertSame(['php', 'ng'], $request->getQueryParams()['tag']);
        self::assertSame([], $request->getRouteParams());
    }

    public function testRouteParametersAreImmutable(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/hello');
        $routedRequest = $request->withRouteParams(['name' => 'Victor']);

        self::assertSame([], $request->getRouteParams());
        self::assertSame(['name' => 'Victor'], $routedRequest->getRouteParams());
        self::assertSame('Victor', $routedRequest->getRouteParam('name'));
    }

    public function testChangingUriRefreshesQueryParameters(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/old?name=old');
        $changedRequest = $request->withUri(new Uri('/new?name=new'));

        self::assertSame('/old', $request->getUri()->getPath());
        self::assertSame('old', $request->getQueryParams()['name']);
        self::assertSame('/new', $changedRequest->getUri()->getPath());
        self::assertSame('new', $changedRequest->getQueryParams()['name']);
    }

    public function testParsesRawRequestFromResource(): void
    {
        $resource = \fopen('php://temp', 'w+');
        self::assertIsResource($resource);
        \fwrite(
            $resource,
            "POST /hello?name=Victor HTTP/1.1\r\n"
            . "Host: example.test\r\n"
            . "X-Trace: request-1\r\n"
            . "Content-Length: 7\r\n"
            . "\r\n"
            . "payload"
        );
        \rewind($resource);

        try {
            $request = Request::fromResource($resource);
        } finally {
            \fclose($resource);
        }

        self::assertInstanceOf(Request::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/hello?name=Victor', $request->getRequestTarget());
        self::assertSame('Victor', $request->getQueryParams()['name']);
        self::assertSame(['example.test'], $request->getHeader('host'));
        self::assertSame(['request-1'], $request->getHeader('X-Trace'));
        self::assertSame('payload', (string) $request->getBody());
    }

    public function testEmptyResourceReturnsNoRequest(): void
    {
        $resource = \fopen('php://temp', 'r');
        self::assertIsResource($resource);

        try {
            $request = Request::fromResource($resource);
        } finally {
            \fclose($resource);
        }

        self::assertNull($request);
    }

    public function testHeaderOperationsAreImmutableAndNormalizeValues(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/');
        $withHeader = $request->withHeader('X-Trace', 'one');
        $withAddedHeader = $withHeader->withAddedHeader('X-Trace', ['two', 'three']);
        $withoutHeader = $withAddedHeader->withoutHeader('x-trace');

        self::assertFalse($request->hasHeader('X-Trace'));
        self::assertSame(['one', 'two', 'three'], $withAddedHeader->getHeader('x-trace'));
        self::assertSame('one,two,three', $withAddedHeader->getHeaderLine('X-Trace'));
        self::assertFalse($withoutHeader->hasHeader('X-Trace'));
    }

    public function testHeaderOperationsRejectNonStringValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Request(method: HttpMethod::GET, uri: '/'))->withHeader(
            'X-Trace',
            $this->invalidHeaderValue()
        );
    }

    public function testUsesServerDefaultsWhenValuesAreMissing(): void
    {
        $server = $_SERVER;
        unset($_SERVER['SERVER_PROTOCOL'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        try {
            $request = new Request(headers: ['Host' => 'example.test'], body: '');
        } finally {
            $_SERVER = $server;
        }

        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
    }

    public function testUsesServerProtocolMethodAndUriWhenAvailable(): void
    {
        $server = $_SERVER;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/2.0';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/server';

        try {
            $request = new Request(headers: ['Host' => 'example.test'], body: '');
        } finally {
            $_SERVER = $server;
        }

        self::assertSame('2.0', $request->getProtocolVersion());
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/server', $request->getRequestTarget());
    }

    public function testChangesRequestTargetImmutably(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: 'http://example.test/hello');

        self::assertSame($request, $request->withRequestTarget('http://example.test/hello'));
        $changedRequest = $request->withRequestTarget('/hello');

        self::assertSame('http://example.test/hello', $request->getRequestTarget());
        self::assertSame('/hello', $changedRequest->getRequestTarget());
    }

    public function testRejectsInvalidRequestTarget(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/hello');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid target '/invalid' for 'GET' method.");
        $request->withRequestTarget('/invalid');
    }

    public function testChangesMethodImmutablyAndRejectsUnsupportedMethods(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/hello');

        self::assertSame($request, $request->withMethod('GET'));
        $changedRequest = $request->withMethod('POST');
        self::assertSame('GET', $request->getMethod());
        self::assertSame('POST', $changedRequest->getMethod());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported method given (INVALID).');
        $request->withMethod('INVALID');
    }

    public function testUpdatesHostHeaderWhenUriChanges(): void
    {
        $request = new Request(
            method: HttpMethod::GET,
            uri: 'http://old.example/old?name=old',
            headers: ['Host' => 'old.example'],
            body: ''
        );
        $newUri = new Uri('http://new.example:8443/new?name=new');

        self::assertSame($request, $request->withUri($request->getUri()));

        $changedRequest = $request->withUri($newUri);
        self::assertSame(['new.example:8443'], $changedRequest->getHeader('Host'));
        self::assertSame('new', $changedRequest->getQueryParams()['name']);
        self::assertSame(['old.example'], $request->getHeader('Host'));

        $preservedHostRequest = $request->withUri(new Uri('http://other.example/new'), true);
        self::assertSame(['old.example'], $preservedHostRequest->getHeader('Host'));
    }

    public function testAddsHostHeaderForUriWithAuthority(): void
    {
        $request = new Request(
            method: HttpMethod::GET,
            uri: 'http://example.test:8080/hello',
            headers: ['X-Trace' => 'request'],
            body: ''
        );

        self::assertSame(['example.test:8080'], $request->getHeader('Host'));
    }

    public function testReadsHttpHeadersFromServerVariables(): void
    {
        $server = $_SERVER;
        $_SERVER['HTTP_X_TRACE'] = 'request-1';
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
        $_SERVER['IGNORED_VALUE'] = 'ignored';
        $_SERVER['HTTP_INVALID_VALUE'] = ['invalid'];

        try {
            $request = new Request(method: HttpMethod::GET, uri: '/', body: '');
        } finally {
            $_SERVER = $server;
        }

        self::assertSame(['request-1'], $request->getHeader('X-Trace'));
        self::assertSame(['gzip'], $request->getHeader('Accept-Encoding'));
        self::assertFalse($request->hasHeader('Ignored-Value'));
        self::assertFalse($request->hasHeader('Invalid-Value'));
    }

    public function testRouteParameterAccessSupportsMissingAndSameValues(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/', body: '');

        self::assertNull($request->getRouteParam('missing'));
        self::assertSame($request, $request->withRouteParams([]));
    }

    private function invalidHeaderValue(): mixed
    {
        return ['valid', 1];
    }
}
