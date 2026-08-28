<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

final class UriNormalizer
{
    public static function normalizeScheme(string $scheme): string
    {
        return \strtolower($scheme);
    }

    public static function normalizeUserInfo(string $user = '', ?string $password = null): string
    {
        $userInfo = '';
        if (!empty($user)) {
            $userInfo = $user;
            if (!empty($password)) {
                $userInfo .= ":$password";
            }
        }
        return $userInfo;
    }

    public static function normalizeHost(string $host): string
    {
        $host = \strtolower($host);
        if (\filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === $host) {
            return "[$host]";
        }
        return $host;
    }

    public static function normalizePath(string $path): string
    {
        $decodedPath = \rawurldecode($path);

        return \preg_replace_callback('/[^A-Za-z0-9\-._~!$&\'()*+,;=:@\/]/', function ($matches): string {
            return \rawurlencode($matches[0]);
        }, $decodedPath) ?? '';
    }

    public static function normalizeQuery(string $query): string
    {
        $decodedQuery = \rawurldecode($query);

        return \preg_replace_callback('/[^A-Za-z0-9\-._~!$&\'()*+,;=:@\/?]/', function ($matches): string {
            return \rawurlencode($matches[0]);
        }, $decodedQuery) ?? '';
    }

    public static function normalizeFragment(string $fragment): string
    {
        return $fragment;
    }

    /** @return list<string> */
    public static function splitPath(string $path): array
    {
        return $path === '' ? [] : \explode('/', \ltrim($path, '/'));
    }
}
