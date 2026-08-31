<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

abstract class Message implements MessageInterface
{
    /** @var array<string, array{name: string, values: list<string>}> */
    protected array $headers;
    protected StreamInterface $body;

    /**
     * @param array<string, string|array<int, string>> $headers
     * @param bool|float|int|object|resource|StreamInterface|string|null $body
     * @param string $protocolVersion
     */
    public function __construct(
        array $headers = [],
        mixed $body = null,
        protected string $protocolVersion = '1.1'
    ) {
        $this->headers = [];
        foreach ($headers as $name => $values) {
            $this->headers[\strtolower($name)] = [
                'name' => $name,
                'values' => self::normalizeHeaderValues($values)
            ];
        }

        $this->body = $body instanceof StreamInterface ? $body : Stream::of($body);
    }

    #[\Override]
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    #[\Override]
    public function withProtocolVersion(string $version): MessageInterface
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $newInstance = clone $this;
        $newInstance->protocolVersion = $version;
        return $newInstance;
    }

    /**
     * @return array<string, list<string>>
     */
    #[\Override]
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $nameAndValues) {
            $headers[$nameAndValues['name']] = $nameAndValues['values'];
        }
        return $headers;
    }

    #[\Override]
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[\strtolower($name)]);
    }

    #[\Override]
    public function getHeader(string $name): array
    {
        $name = \strtolower($name);

        if (!isset($this->headers[$name])) {
            return [];
        }

        return $this->headers[$name]['values'];
    }

    #[\Override]
    public function getHeaderLine(string $name): string
    {
        return \implode(',', $this->getHeader($name));
    }

    /**
     * @param mixed $value Valid values are a string or an array of strings.
     */
    #[\Override]
    public function withHeader(string $name, mixed $value): MessageInterface
    {
        $lowercaseName = \strtolower($name);

        $newInstance = clone $this;
        $newInstance->headers[$lowercaseName] = [
            'name' => $name,
            'values' => self::normalizeHeaderValues($value)
        ];
        return $newInstance;
    }

    /**
     * @param mixed $value Valid values are a string or an array of strings.
     */
    #[\Override]
    public function withAddedHeader(string $name, mixed $value): MessageInterface
    {
        $lowercaseName = \strtolower($name);
        $newInstance = clone $this;

        if (isset($newInstance->headers[$lowercaseName])) {
            $newInstance->headers[$lowercaseName]['values'] = \array_merge(
                $newInstance->headers[$lowercaseName]['values'],
                self::normalizeHeaderValues($value)
            );
        } else {
            $newInstance->headers[$lowercaseName] = [
                'name' => $name,
                'values' => self::normalizeHeaderValues($value)
            ];
        }

        return $newInstance;
    }

    #[\Override]
    public function withoutHeader(string $name): MessageInterface
    {
        $name = \strtolower($name);
        if (!isset($this->headers[$name])) {
            return $this;
        }

        $newInstance = clone $this;
        unset($newInstance->headers[$name]);

        return $newInstance;
    }

    #[\Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    #[\Override]
    public function withBody(StreamInterface $body): MessageInterface
    {
        $newInstance = clone $this;
        $newInstance->body = $body;

        return $newInstance;
    }

    /**
     * @param mixed $values Valid values are a string or an array of strings.
     * @return list<string>
     */
    private static function normalizeHeaderValues(mixed $values): array
    {
        $values = \is_array($values) ? $values : [$values];
        $normalizedValues = [];
        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('Header values must be strings.');
            }
            $normalizedValues[] = $value;
        }

        return $normalizedValues;
    }
}
