<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Http\Response;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testSerializesContentLengthWhenItIsMissing(): void
    {
        $serialized = (string) new Response(body: 'hello');

        self::assertSame(1, \substr_count($serialized, 'Content-Length:'));
        self::assertStringContainsString("Content-Length: 5\r\n", $serialized);
    }

    public function testDoesNotDuplicateExplicitContentLength(): void
    {
        $serialized = (string) new Response(
            headers: ['Content-Length' => '99'],
            body: 'hello'
        );

        self::assertSame(1, \substr_count($serialized, 'Content-Length:'));
        self::assertStringContainsString("Content-Length: 99\r\n", $serialized);
    }

    public function testChangesStatusImmutably(): void
    {
        $response = new Response();

        self::assertSame($response, $response->withStatus(200, 'OK'));

        $created = $response->withStatus(201);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('Created', $created->getReasonPhrase());

        $custom = $response->withStatus(299, 'Custom');
        self::assertSame(299, $custom->getStatusCode());
        self::assertSame('Custom', $custom->getReasonPhrase());
    }
}
