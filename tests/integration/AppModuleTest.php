<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\tests\integration;

use PHPUnit\Framework\TestCase;
use Zalas\PHPUnit\Globals\Attribute\Server;
use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Router\Http\HttpMethod;
use dvictorjhg\braidphp\tests\Mock\AppModule;

class AppModuleTest extends TestCase
{
    #[Server('REQUEST_METHOD', 'GET')]
    #[Server('REQUEST_URI', '/request-consumer/get')]
    public function testAppModuleBootstrap(): void
    {
        $appModule = new AppModule();

        $app = new App();
        $app->bootstrapModule($appModule);

        $this->assertInstanceOf(App::class, $app);
    }
}
