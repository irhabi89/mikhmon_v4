<?php

declare(strict_types=1);

namespace Mikhmon\Router;

use Mikhmon\Sync\ScanResult;

interface RouterClientInterface
{
    public function connect(): void;

    public function count(string $command, array $queries = array()): int;

    public function stream(string $command, array $attributes, callable $onRow, ?float $timeout = null): ScanResult;

    /**
     * @return array<string,mixed>|array<int,array<string,mixed>>
     */
    public function command(string $command, array $attributes = array()): array;

    public function close(): void;

    public function isConnected(): bool;

    public function lastError(): string;
}
