<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\Router\Http\Request;
use dvictorjhg\braidphp\Router\Http\RequestParser;
use dvictorjhg\braidphp\Router\Http\RequestException;
use dvictorjhg\braidphp\Router\Http\Uri;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    public function testReportsFailureToOpenInputStream(): void
    {
        self::assertTrue(\stream_wrapper_unregister('php'));
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Failed to open input stream from php://input');

        try {
            self::assertSame('', (string) (new Request())->getBody());
        } finally {
            self::assertTrue(\stream_wrapper_restore('php'));
        }
    }

    public function testParsesQueryParameters(): void
    {
        $request = new Request(
            method: HttpMethod::GET,
            uri: '/hello?name=Victor&tag[]=php&tag[]=ng'
        );

        self::assertSame('Victor', $request->getQueryParams()['name']);
        self::assertSame(['php', 'ng'], $request->getQueryParams()['tag']);
        self::assertSame([], $request->getAttributes());
    }

    public function testRequestAttributesAreImmutable(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/hello');
        $routedRequest = $request->withAttribute('name', 'Victor');

        self::assertSame([], $request->getAttributes());
        self::assertSame(['name' => 'Victor'], $routedRequest->getAttributes());
        self::assertSame('Victor', $routedRequest->getAttribute('name'));
    }

    public function testEncodedQueryDelimitersRemainWithinTheirValues(): void
    {
        $request = new Request(uri: '/?q=a%26role%3Dadmin&plus=%2B&literal=a+b', body: '');
        self::assertSame(['q' => 'a&role=admin', 'plus' => '+', 'literal' => 'a b'], $request->getQueryParams());
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
            $request = RequestParser::fromResource($resource);
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
            $request = RequestParser::fromResource($resource);
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

        $changedRequest = $request->withRequestTarget('/different');

        self::assertSame('/hello', $request->getRequestTarget());
        self::assertSame('/different', $changedRequest->getRequestTarget());
        self::assertSame($changedRequest, $changedRequest->withRequestTarget('/different'));
        self::assertSame((string) $request->getUri(), (string) $changedRequest->getUri());
        self::assertSame('/different', $changedRequest->withUri(new Uri('https://other.test/new'))->getRequestTarget());
    }

    #[DataProvider('invalidRequestTargets')]
    public function testRejectsInvalidRequestTarget(string $target): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/hello');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request target.');
        $request->withRequestTarget($target);
    }

    /** @return array<string, array{string}> */
    public static function invalidRequestTargets(): array
    {
        return ['empty' => [''], 'space' => ['/invalid target'], 'crlf' => ["/\r\nInjected"], 'nul' => ["/\0"]];
    }

    #[DataProvider('wireTargets')]
    public function testPreservesWireTargets(string $method, string $target): void
    {
        $resource = \fopen('php://temp', 'w+');
        self::assertIsResource($resource);
        \fwrite($resource, "$method $target HTTP/1.1\r\n\r\n");
        \rewind($resource);
        try {
            $request = RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }

        self::assertInstanceOf(Request::class, $request);
        self::assertSame($method, $request->getMethod());
        self::assertSame($target, $request->getRequestTarget());
    }

    public function testExplicitAsteriskTargetSurvivesUriChanges(): void
    {
        $request = (new Request(uri: '/'))
            ->withMethod('OPTIONS')
            ->withRequestTarget('*')
            ->withUri(new Uri('https://example.test/'));

        self::assertSame('*', $request->getRequestTarget());
        self::assertSame('https://example.test/', (string) $request->getUri());
    }

    public function testExplicitDefaultTargetSurvivesUriChanges(): void
    {
        $request = (new Request(uri: '/old'))->withRequestTarget('/old');
        self::assertSame('/old', $request->withUri(new Uri('/new'))->getRequestTarget());
        self::assertSame('/new?0', (new Request(uri: '/old'))->withUri(new Uri('/new?0#fragment'))->getRequestTarget());
    }

    /** @return array<string, array{string, string}> */
    public static function wireTargets(): array
    {
        return [
            'OPTIONS root' => ['OPTIONS', '/'],
            'OPTIONS resource' => ['OPTIONS', '/resource?name=value'],
            'OPTIONS absolute' => ['OPTIONS', 'http://example.test/resource'],
            'OPTIONS asterisk' => ['OPTIONS', '*'],
            'GET absolute' => ['GET', 'https://example.test/resource'],
            'GET zero query' => ['GET', '/resource?0'],
            'CONNECT authority' => ['CONNECT', 'example.test:443'],
        ];
    }

    #[DataProvider('invalidWireTargets')]
    public function testRejectsInvalidWireTargets(string $method, string $target): void
    {
        $resource = \fopen('php://temp', 'w+');
        self::assertIsResource($resource);
        \fwrite($resource, "$method $target HTTP/1.1\r\n\r\n");
        \rewind($resource);
        try {
            $this->expectException(\InvalidArgumentException::class);
            RequestParser::fromResource($resource);
        } finally {
            \fclose($resource);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function invalidWireTargets(): array
    {
        return [
            'fragment' => ['GET', '/resource#fragment'],
            'CONNECT origin' => ['CONNECT', '/resource'],
            'empty' => ['GET', ''],
        ];
    }

    public function testChangesMethodImmutablyAndRejectsInvalidMethods(): void
    {
        $request = new Request(method: HttpMethod::GET, uri: '/hello');

        self::assertSame($request, $request->withMethod('GET'));
        $changedRequest = $request->withMethod('POST');
        self::assertSame('GET', $request->getMethod());
        self::assertSame('POST', $changedRequest->getMethod());
        self::assertSame('PROPFIND', $request->withMethod('PROPFIND')->getMethod());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP method token.');
        $request->withMethod("GET\r\nInjected");
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

    #[DataProvider('hostUpdates')]
    public function testUriHostUpdatesRespectMissingEmptyAndPreservedHeaders(
        ?string $host,
        string $uri,
        bool $preserveHost,
        string $expected
    ): void {
        $request = new Request(uri: '/', headers: $host === null ? [] : ['Host' => $host], serverParams: []);
        $changedRequest = $request->withUri(new Uri($uri), $preserveHost);

        self::assertSame($expected, $changedRequest->getHeaderLine('Host'));
        self::assertSame($host ?? '', $request->getHeaderLine('Host'));
    }

    /** @return array<string, array{string|null, string, bool, string}> */
    public static function hostUpdates(): array
    {
        return [
            'missing preserved' => [null, 'https://example.test:8443/', true, 'example.test:8443'],
            'empty preserved' => ['', 'https://example.test/', true, 'example.test'],
            'nonempty preserved' => ['original.test', 'https://example.test/', true, 'original.test'],
            'nonempty replaced' => ['original.test', 'https://example.test/', false, 'example.test'],
            'relative preserves existing' => ['original.test', '/new', false, 'original.test'],
            'relative keeps missing' => [null, '/new', true, ''],
        ];
    }

    public function testSameUriStillInitializesMissingHostHeader(): void
    {
        $request = (new Request(uri: 'https://example.test/', serverParams: []))->withoutHeader('Host');
        $changedRequest = $request->withUri($request->getUri(), true);

        self::assertSame('example.test', $changedRequest->getHeaderLine('Host'));
        self::assertFalse($request->hasHeader('Host'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReadsAndValidatesSapiHeaders(): void
    {
        if (\function_exists('getallheaders')) {
            self::markTestSkipped('The SAPI already defines getallheaders().');
        }
        require_once __DIR__ . '/../../../Mock/ServerHeaders.php';

        $request = new Request(method: HttpMethod::GET, uri: '/', body: '');

        self::assertSame(['sapi-header'], $request->getHeader('X-Trace'));
        self::assertFalse($request->hasHeader('X-Invalid'));
        self::assertFalse($request->hasHeader('0'));
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

        self::assertNull($request->getAttribute('missing'));
    }

    private function invalidHeaderValue(): mixed
    {
        return ['valid', 1];
    }
}
