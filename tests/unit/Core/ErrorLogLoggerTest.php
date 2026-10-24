<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use dvictorjhg\braidphp\Core\AppException;
use dvictorjhg\braidphp\Core\ErrorLogLogger;

#[CoversClass(ErrorLogLogger::class)]
final class ErrorLogLoggerTest extends TestCase
{
    public function testAcceptsAllLevelsAndArbitraryContext(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'braid-log-');
        self::assertIsString($path);
        $previous = \ini_set('error_log', $path);
        self::assertIsString($previous);
        try {
            $logger = new ErrorLogLogger();
            foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
                $logger->log($level, 'message', ['anything' => new \stdClass(), 'exception' => ['not throwable']]);
            }
            $logger->error(new class implements \Stringable {
                public function __toString(): string
                {
                    return 'failure';
                }
            }, ['exception' => new AppException('private detail')]);
            $output = \file_get_contents($path);
            self::assertIsString($output);
            self::assertSame(8, \substr_count($output, 'message'));
            self::assertStringContainsString('failure:', $output);
            self::assertStringContainsString('private detail', $output);
        } finally {
            \ini_set('error_log', $previous);
            \unlink($path);
        }
    }

    public function testRejectsUnknownLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ErrorLogLogger())->log('invalid', 'message');
    }
}
