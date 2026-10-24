<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\ResponseInterface;

final class ResponseEmitter
{
    private const int WRITE_CHUNK_BYTES = 8192;

    /** @param resource $client */
    public static function write(
        mixed $client,
        ResponseInterface $response,
        HttpOptions $options = new HttpOptions()
    ): void {
        $version = $response->getProtocolVersion();
        $status = $response->getStatusCode();
        $reason = $response->getReasonPhrase();
        HttpSyntax::validateProtocolVersion($version);
        HttpSyntax::validateStatus($status, $reason);
        $payload = \sprintf(
            "HTTP/%s %d %s\r\n",
            $version,
            $status,
            $reason
        );
        foreach ($response->getHeaders() as $name => $values) {
            HttpSyntax::validateHeaderName($name);
            foreach (HttpSyntax::normalizeHeaderValues($values) as $value) {
                $payload .= "{$name}: {$value}\r\n";
            }
        }
        if (!$response->hasHeader('Content-Type')) {
            $payload .= "Content-Type: text/plain\r\n";
        }
        $body = (string) $response->getBody();
        if (!$response->hasHeader('Content-Length')) {
            $payload .= 'Content-Length: ' . \strlen($body) . "\r\n";
        }
        $payload .= "\r\n" . $body;

        $offset = 0;
        $length = \strlen($payload);
        $deadline = \microtime(true) + $options->writeTimeout;
        $socket = \str_contains(\stream_get_meta_data($client)['stream_type'], 'socket');
        while ($offset < $length) {
            $remaining = $deadline - \microtime(true);
            if ($remaining <= 0) {
                throw new StreamException('Response write timed out.');
            }
            if ($socket) {
                $seconds = (int) $remaining;
                \stream_set_timeout($client, $seconds, (int) (($remaining - $seconds) * 1_000_000));
            }
            $written = @\fwrite($client, \substr($payload, $offset, self::WRITE_CHUNK_BYTES));
            if ($written === false || $written === 0) {
                throw new StreamException('Failed to write response to client.');
            }
            $offset += $written;
        }
    }
}
