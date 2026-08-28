<?php

namespace dvictorjhg\braidphp\Router\Attributes;

use Attribute;
use dvictorjhg\braidphp\Router\Http\HttpMethod;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class Trace extends Route
{
    use HttpMethodRoute;

    protected const HttpMethod HTTP_METHOD = HttpMethod::TRACE;
}
