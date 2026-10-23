<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('invalidStatuses')]
    public function testRejectsInvalidStatuses(int $code, string $reasonPhrase, bool $construct): void
    {
        $response = new Response();
        $this->expectException(\InvalidArgumentException::class);

        if ($construct) {
            self::assertSame($code, (new Response(statusCode: $code, reasonPhrase: $reasonPhrase))->getStatusCode());
        } else {
            $response->withStatus($code, $reasonPhrase);
        }
    }

    /** @return iterable<string, array{int, string, bool}> */
    public static function invalidStatuses(): iterable
    {
        $statuses = [
            'below range' => [99, ''],
            'above range' => [600, ''],
            'header injection' => [200, "OK\r\nX-Injected: yes"],
            'newline' => [200, "OK\n"],
            'null byte' => [200, "OK\0"],
            'control byte' => [200, "OK\x1F"],
            'delete byte' => [200, "OK\x7F"],
        ];
        foreach ($statuses as $label => [$code, $reasonPhrase]) {
            foreach ([true, false] as $construct) {
                yield ($construct ? 'construct: ' : 'replace: ') . $label => [$code, $reasonPhrase, $construct];
            }
        }
    }

    public function testAcceptsStatusBoundariesAndValidReasonPhraseBytes(): void
    {
        self::assertSame(100, (new Response(100))->getStatusCode());
        $response = (new Response())->withStatus(599, "Custom\t\x80\xFF");

        self::assertSame(599, $response->getStatusCode());
        self::assertSame("Custom\t\x80\xFF", $response->getReasonPhrase());
    }
}
