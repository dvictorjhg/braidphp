<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\unit;

use PHPInjector\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use dvictorjhg\braidphp\Core\Attributes\Module;

#[CoversClass(Module::class)]
class ModuleTest extends TestCase
{
    public function testConstructor(): Module
    {
        $module = new Module(
            bootstrap: ['bootstrap'],
            controllers: ['controllers'],
            imports: ['imports'],
            providers: ['providers']
        );

        $this->assertInstanceOf(Module::class, $module);

        return $module;
    }

    #[Depends("testConstructor")]
    public function testBootstrapInstanceOfContainer(Module $module): void
    {
        $this->assertInstanceOf(Container::class, $module->bootstrap);
    }

    #[Depends("testConstructor")]
    public function testControllersInstanceOfContainer(Module $module): void
    {
        $this->assertInstanceOf(Container::class, $module->controllers);
    }

    #[Depends("testConstructor")]
    public function testImportsInstanceOfContainer(Module $module): void
    {
        $this->assertInstanceOf(Container::class, $module->imports);
    }

    #[Depends("testConstructor")]
    public function testProvidersInstanceOfContainer(Module $module): void
    {
        $this->assertInstanceOf(Container::class, $module->providers);
    }
}
