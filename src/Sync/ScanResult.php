<?php

declare(strict_types=1);

namespace Mikhmon\Sync;

/**
 * Result of a streamed scan/command.
 */
final class ScanResult
{
    /** @var bool */
    public $ok = true;

    /** @var int */
    public $expected = -1;

    /** @var int */
    public $seen = 0;

    /** @var int */
    public $records = 0;

    /** @var int */
    public $invalid = 0;

    /** @var bool */
    public $complete = false;

    /** @var string */
    public $error = '';

    /** @var mixed */
    public $ret = null;

    /**
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $result = new self();
        $result->ok = !empty($data['ok']);
        $result->seen = isset($data['count']) ? (int)$data['count'] : 0;
        $result->error = isset($data['error']) ? (string)$data['error'] : '';
        $result->ret = isset($data['ret']) ? $data['ret'] : null;
        return $result;
    }
}
