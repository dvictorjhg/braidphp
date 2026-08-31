<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    private string $scheme = '';

    private string $userInfo = '';

    private string $host = '';

    private ?int $port = null;

    private string $path = '';

    private string $query = '';

    private string $fragment = '';
    /** @var list<string> */
    private array $pathParts = [];

    public function __construct(string|UriInterface $uri)
    {
        if (\is_string($uri)) {
            $parts = \parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException("The URI '$uri' could not be parsed.");
            }
            $this->scheme = UriNormalizer::normalizeScheme($parts['scheme'] ?? '');
            $this->userInfo = isset($parts['user'])
                ? UriNormalizer::normalizeUserInfo($parts['user'], $parts['pass'] ?? null)
                : '';
            $this->host = UriNormalizer::normalizeHost($parts['host'] ?? '');
            $this->port = $parts['port'] ?? null;
            $this->path = UriNormalizer::normalizePath($parts['path'] ?? '');
            $this->query = UriNormalizer::normalizeQuery($parts['query'] ?? '');
            $this->fragment = isset($parts['fragment']) ? UriNormalizer::normalizeFragment($parts['fragment']) : '';
        } else {
            $this->scheme = $uri->getScheme();
            $this->userInfo = $uri->getUserInfo();
            $this->host = $uri->getHost();
            $this->port = $uri->getPort();
            $this->path = $uri->getPath();
            $this->query = $uri->getQuery();
            $this->fragment = $uri->getFragment();
        }

        $this->pathParts = UriNormalizer::splitPath($this->path);
    }

    #[\Override]
    public function getScheme(): string
    {
        return $this->scheme;
    }

    #[\Override]
    public function getAuthority(): string
    {
        $authority = '';
        if (!empty($this->getHost())) {
            if (!empty($this->getUserInfo())) {
                $authority .= $this->getUserInfo() . '@';
            }

            $authority .= $this->getHost();

            if ($this->getPort() !== null) {
                $authority .= ":{$this->port}";
            }
        }
        return $authority;
    }

    #[\Override]
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    #[\Override]
    public function getHost(): string
    {
        return $this->host;
    }

    #[\Override]
    public function getPort(): ?int
    {
        return $this->port;
    }

    #[\Override]
    public function getPath(): string
    {
        return $this->path;
    }

    #[\Override]
    public function getQuery(): string
    {
        return $this->query;
    }

    #[\Override]
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /** @return list<string> */
    public function getPathParts(): array
    {
        return $this->pathParts;
    }

    #[\Override]
    public function withScheme(string $scheme): self
    {
        $normalizedScheme = UriNormalizer::normalizeScheme($scheme);
        if ($normalizedScheme === $this->scheme) {
            return $this;
        }

        $static = clone $this;
        $static->scheme = $normalizedScheme;
        return $static;
    }

    #[\Override]
    public function withUserInfo(string $user, ?string $password = null): self
    {
        $normalizedUserInfo = UriNormalizer::normalizeUserInfo($user, $password);
        if ($normalizedUserInfo === $this->userInfo) {
            return $this;
        }

        $static = clone $this;
        $static->userInfo = $normalizedUserInfo;
        return $static;
    }

    #[\Override]
    public function withHost(string $host): self
    {
        $normalizedHost = UriNormalizer::normalizeHost($host);
        if ($normalizedHost === $this->host) {
            return $this;
        }

        $static = clone $this;
        $static->host = $normalizedHost;
        return $static;
    }

    #[\Override]
    public function withPort(?int $port): self
    {
        if ($port === $this->port) {
            return $this;
        }

        $static = clone $this;
        $static->port = $port;
        return $static;
    }

    #[\Override]
    public function withPath(string $path): self
    {
        $filteredPath = UriNormalizer::normalizePath($path);
        if ($filteredPath === $this->path) {
            return $this;
        }

        $static = clone $this;
        $static->path = $filteredPath;
        $static->pathParts = UriNormalizer::splitPath($filteredPath);
        return $static;
    }

    #[\Override]
    public function withQuery(string $query): self
    {
        $filteredQuery = UriNormalizer::normalizeQuery($query);
        if ($filteredQuery === $this->query) {
            return $this;
        }

        $static = clone $this;
        $static->query = $filteredQuery;
        return $static;
    }

    #[\Override]
    public function withFragment(string $fragment): self
    {
        $normalizedFragment = UriNormalizer::normalizeFragment($fragment);
        if ($normalizedFragment === $this->fragment) {
            return $this;
        }

        $static = clone $this;
        $static->fragment = $normalizedFragment;
        return $static;
    }

    #[\Override]
    public function __toString(): string
    {
        $uriStr = ($this->scheme !== '') ? $this->scheme . ':' : '';
        $uriStr .= ($this->getAuthority() !== '') ? '//' . $this->getAuthority() : '';
        if ($this->path !== '' && \substr($this->path, 0, 1) !== '/' && $this->getAuthority() !== '') {
            $uriStr .= "/{$this->path}";
        } elseif ($this->path !== '' && \preg_match('/^\/{2,}/', $this->path) && $this->getAuthority() === '') {
            $uriStr .= \preg_replace('/^\/{2,}(.*)/', '/${1}', $this->path);
        } else {
            $uriStr .= $this->path;
        }
        $uriStr .= ($this->query !== '') ? "?{$this->query}" : '';
        $uriStr .= ($this->fragment !== '') ? "#{$this->fragment}" : '';
        return $uriStr;
    }
}
