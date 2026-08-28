<?php

declare(strict_types=1);

namespace dvictorjhg\braidphp\Router\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response extends Message implements ResponseInterface
{
    /** @var array<int, string> HTTP status code phrases */
    private static array $statusPhrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required'
    ];

    /**
     * @param int $statusCode
     * @param array<string, string|array<int, string>> $headers
     * @param bool|float|int|object|resource|StreamInterface|string|null $body
     * @param string $protocolVersion
     * @param string $reasonPhrase
     */
    public function __construct(
        protected int $statusCode = 200,
        array $headers = [],
        mixed $body = null,
        string $protocolVersion = '1.1',
        protected string $reasonPhrase = ''
    ) {
        parent::__construct($headers, $body, $protocolVersion);

        if ($reasonPhrase === '' && isset(self::$statusPhrases[$this->statusCode])) {
            $reasonPhrase = self::$statusPhrases[$this->statusCode];
        }
        $this->reasonPhrase = $reasonPhrase;
    }

    #[\Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    #[\Override]
    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        if ($code === $this->statusCode && $reasonPhrase === $this->reasonPhrase) {
            return $this;
        }

        $static = clone $this;
        $static->statusCode = (int) $code;
        if ($reasonPhrase === '' && isset(self::$statusPhrases[$static->statusCode])) {
            $reasonPhrase = $static->reasonPhrase = self::$statusPhrases[$static->statusCode];
        }
        $static->reasonPhrase = $reasonPhrase;
        return $static;
    }

    #[\Override]
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function __toString(): string
    {
        $responseStr = "HTTP/{$this->getProtocolVersion()} {$this->getStatusCode()} {$this->getReasonPhrase()}\r\n";

        // Add headers
        foreach ($this->getHeaders() as $name => $values) {
            foreach ((array) $values as $value) {
                $responseStr .= "{$name}: {$value}\r\n";
            }
        }

        // Ensure Content-Type and Content-Length
        if (!$this->hasHeader('Content-Type')) {
            $responseStr .= "Content-Type: text/plain\r\n";
        }

        $body = $this->getBody();
        $bodyContent = (string) $body;

        if (!$this->hasHeader('Content-Length')) {
            $responseStr .= "Content-Length: " . \strlen($bodyContent) . "\r\n";
        }
        $responseStr .= "\r\n";
        $responseStr .= $bodyContent;

        return $responseStr;
    }
}
