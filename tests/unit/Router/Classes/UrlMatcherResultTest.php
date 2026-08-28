<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Router\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Router\Classes\UrlMatcherResult;

#[CoversClass(UrlMatcherResult::class)]
final class UrlMatcherResultTest extends TestCase
{
    public function testStoresConsumedPathAndParameters(): void
    {
        $result = new UrlMatcherResult(['hello'], ['name' => 'Victor']);

        self::assertSame(['hello'], $result->consumed);
        self::assertSame(['name' => 'Victor'], $result->params);
    }
}
