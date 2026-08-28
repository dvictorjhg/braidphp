<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit;

use dvictorjhg\braidphp\Core\Attributes\Module;
use dvictorjhg\braidphp\Core\Scanners\ModuleScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\tests\Mock\AppModule;

#[CoversClass(ModuleScanner::class)]
class ModuleScannerTest extends TestCase
{
    public function testScanReturnsModuleAttributeInstance(): void
    {
        $module = ModuleScanner::scan(new AppModule());

        $this->assertInstanceOf(Module::class, $module);
    }

    public function testScanReturnsNullWhenModuleAttributeIsMissing(): void
    {
        $module = ModuleScanner::scan(new class () {
        });

        $this->assertNull($module);
    }
}
