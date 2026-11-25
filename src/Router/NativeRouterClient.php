<?php

declare(strict_types=1);

namespace Mikhmon\Router;

use Mikhmon\Sync\ScanResult;

require_once dirname(__DIR__, 2) . '/lib/routeros_api.class.php';

/**
 * Adapter over the hardened in-repo RouterosAPI client.
 */
final class NativeRouterClient implements RouterClientInterface
{
    /** @var RouterTarget */
    private $target;

    /** @var string */
    private $user;

    /** @var string */
    private $password;

    /** @var array<string,mixed> */
    private $opts;

    /** @var \RouterosAPI|null */
    private $api = null;

    public function __construct(RouterTarget $target, string $user, string $password, array $opts = array())
    {
        $this->target = $target;
        $this->user = $user;
        $this->password = $password;
        $this->opts = array_merge(array(
            'attempts' => 2,
            'delay' => 1,
            'timeout' => 5,
            'read_timeout' => 300,
            'debug' => false,
            'ssl' => false,
        ), $opts);
    }

    public function connect(): void
    {
        $api = new \RouterosAPI();
        $api->debug = !empty($this->opts['debug']);
        $api->ssl = !empty($this->opts['ssl']);
        $api->attempts = max(1, (int)$this->opts['attempts']);
        $api->delay = max(0, (int)$this->opts['delay']);
        $api->timeout = max(1, (int)$this->opts['timeout']);
        $api->read_timeout = max(5, (int)$this->opts['read_timeout']);
        $api->port = $this->target->port();
        if (!$api->connect($this->target->host(), $this->user, $this->password)) {
            throw new RouterException('router connection failed: ' . ($api->last_error !== '' ? $api->last_error : 'unknown error'));
        }
        $this->api = $api;
    }

    public function count(string $command, array $queries = array()): int
    {
        $this->assertConnected();
        return $this->api->countOnly($command, $queries);
    }

    public function stream(string $command, array $attributes, callable $onRow, ?float $timeout = null): ScanResult
    {
        $this->assertConnected();
        $result = $this->api->commStream($command, $attributes, $onRow, $timeout);
        return ScanResult::fromApi($result);
    }

    public function command(string $command, array $attributes = array()): array
    {
        $this->assertConnected();
        $result = $this->api->comm($command, $attributes);
        if (is_array($result)) {
            return $result;
        }
        return array('ret' => $result);
    }

    public function close(): void
    {
        if ($this->api !== null) {
            $this->api->disconnect();
            $this->api = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->api !== null && $this->api->connected;
    }

    public function lastError(): string
    {
        return $this->api !== null ? (string)$this->api->last_error : '';
    }

    private function assertConnected(): void
    {
        if ($this->api === null || !$this->api->connected) {
            throw new RouterException('router is not connected');
        }
    }
}
