<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

abstract class ServerRequestState extends Message implements ServerRequestInterface
{
    /** @var array<string|int, mixed> */
    protected array $queryParams = [];
    /** @var array<string|int, mixed> */
    protected array $serverParams;
    /** @var array<string|int, mixed> */
    private array $cookieParams;
    /** @var array<string|int, mixed> */
    private array $uploadedFiles = [];
    /** @var array<string|int, mixed>|object|null */
    private array|object|null $parsedBody = null;
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @param array<string|int, mixed>|null $serverParams */
    protected function initializeServerParams(?array $serverParams): void
    {
        $this->serverParams = $serverParams ?? $_SERVER;
        $this->cookieParams = $serverParams === null ? $_COOKIE : [];
    }

    protected function initializeParsedBody(string $method, bool $useGlobals): void
    {
        $contentType = \strtolower(\trim(\explode(';', $this->getHeaderLine('Content-Type'), 2)[0]));
        if (
            $method === 'POST'
            && \in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true)
        ) {
            $this->parsedBody = $useGlobals ? $_POST : [];
        }
    }

    /** @return array<string|int, mixed> */
    #[\Override]
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /** @return array<string|int, mixed> */
    #[\Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /** @return array<string|int, mixed> */
    #[\Override]
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /** @param array<string|int, mixed> $cookies */
    #[\Override]
    public function withCookieParams(array $cookies): static
    {
        $newInstance = clone $this;
        $newInstance->cookieParams = $cookies;
        return $newInstance;
    }

    /** @param array<string|int, mixed> $query */
    #[\Override]
    public function withQueryParams(array $query): static
    {
        $newInstance = clone $this;
        $newInstance->queryParams = $query;
        return $newInstance;
    }

    /** @return array<string|int, mixed> */
    #[\Override]
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /** @param array<string|int, mixed> $uploadedFiles */
    #[\Override]
    public function withUploadedFiles(array $uploadedFiles): static
    {
        \array_walk_recursive($uploadedFiles, static function (mixed $file): void {
            if (!$file instanceof UploadedFileInterface) {
                throw new \InvalidArgumentException('Uploaded files must implement UploadedFileInterface.');
            }
        });
        $newInstance = clone $this;
        $newInstance->uploadedFiles = $uploadedFiles;
        return $newInstance;
    }

    /** @return array<string|int, mixed>|object|null */
    #[\Override]
    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    /** @param mixed $data */
    #[\Override]
    public function withParsedBody(mixed $data): static
    {
        if ($data !== null && !\is_array($data) && !\is_object($data)) {
            throw new \InvalidArgumentException('Parsed body must be an array, object or null.');
        }
        $newInstance = clone $this;
        $newInstance->parsedBody = $data;
        return $newInstance;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    #[\Override]
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    #[\Override]
    public function withAttribute(string $name, mixed $value): static
    {
        $newInstance = clone $this;
        $newInstance->attributes[$name] = $value;
        return $newInstance;
    }

    #[\Override]
    public function withoutAttribute(string $name): static
    {
        $newInstance = clone $this;
        unset($newInstance->attributes[$name]);
        return $newInstance;
    }
}
