<?php

declare(strict_types=1);

namespace Mikhmon\Router;

/**
 * Normalises a RouterOS target: host, host:port, [ipv6], [ipv6]:port.
 */
final class RouterTarget
{
    /** @var string */
    private $host;

    /** @var int */
    private $port;

    public function __construct(string $host, int $port = 8728)
    {
        $this->host = $host;
        $this->port = ($port >= 1 && $port <= 65535) ? $port : 8728;
    }

    public static function fromString(string $input, ?int $fallbackPort = null): self
    {
        $input = trim($input);
        $input = preg_replace('/\s+/', '', $input);
        $input = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', (string)$input);
        $input = preg_replace('#/.*$#', '', (string)$input);

        $host = (string)$input;
        $port = $fallbackPort !== null ? $fallbackPort : 8728;

        if (preg_match('/^\[([^\]]+)\](?::([0-9]+))?$/', (string)$input, $m)) {
            $host = $m[1];
            if (isset($m[2]) && $m[2] !== '') {
                $port = (int)$m[2];
            }
        } elseif (substr_count((string)$input, ':') === 1) {
            $parts = explode(':', (string)$input, 2);
            if ($parts[0] !== '' && $parts[1] !== '' && ctype_digit($parts[1])) {
                $host = $parts[0];
                $port = (int)$parts[1];
            }
        }

        return new self($host, $port);
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function __toString(): string
    {
        return $this->host . ':' . $this->port;
    }
}
