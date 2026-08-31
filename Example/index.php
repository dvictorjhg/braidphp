<?php

declare(strict_types=1);

// Load Composer's autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

use dvictorjhg\braidphp\Core\App;
use dvictorjhg\braidphp\Example\AppModule;

$app = new App();
$app->bootstrapModule(new AppModule());

$address = (string) getenv('SERVER_ADDRESS') ?: '0.0.0.0';
$port = (string) getenv('SERVER_PORT') ?: '8000';

$app->listen(address: $address, port: $port);
