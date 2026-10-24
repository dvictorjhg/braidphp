<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

final class HttpSyntax
{
    private const string INVALID_FIELD_VALUE = '/[^\x09\x20-\x7E\x80-\xFF]/';

    public static function validateProtocolVersion(string $version): void
    {
        if (\preg_match('/^\d+(?:\.\d+)?$/D', $version) !== 1) {
            throw new \InvalidArgumentException('Invalid HTTP protocol version.');
        }
    }

    public static function validateHeaderName(string $name): void
    {
        if (\preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid header name.');
        }
    }

    /**
     * @param mixed $values Valid values are a string or an array of strings.
     * @return list<string>
     */
    public static function normalizeHeaderValues(mixed $values): array
    {
        $values = \is_array($values) ? $values : [$values];
        $normalizedValues = [];
        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('Header values must be strings.');
            }
            if (\preg_match(self::INVALID_FIELD_VALUE, $value) === 1) {
                throw new \InvalidArgumentException('Invalid header value.');
            }
            $normalizedValues[] = $value;
        }
        return $normalizedValues;
    }

    public static function validateStatus(int $code, string $reasonPhrase): void
    {
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException('HTTP status code must be between 100 and 599.');
        }
        if (\preg_match(self::INVALID_FIELD_VALUE, $reasonPhrase) === 1) {
            throw new \InvalidArgumentException('Invalid HTTP reason phrase.');
        }
    }
}
