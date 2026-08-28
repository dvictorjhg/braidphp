<?php

namespace dvictorjhg\braidphp\Router\Http;

/**
 * Backed enum with all HTTP methods.
 */
enum HttpMethod: int
{
    case GET = 0b000000001;
    case HEAD = 0b000000010;
    case POST = 0b000000100;
    case PUT = 0b000001000;
    case DELETE = 0b000010000;
    case CONNECT = 0b000100000;
    case OPTIONS = 0b001000000;
    case TRACE = 0b010000000;
    case PATCH = 0b100000000;
}
