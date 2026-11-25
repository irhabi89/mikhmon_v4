<?php

declare(strict_types=1);

namespace Mikhmon\Support;

final class Pagination
{
    /** @var array<int,int> */
    public const SIZES = array(25, 50, 100, 200);

    public static function perPage($requested, int $default = 50): int
    {
        $value = (int)$requested;
        return in_array($value, self::SIZES, true) ? $value : $default;
    }

    public static function page($requested): int
    {
        return max(1, (int)$requested);
    }

    public static function pages(int $total, int $perPage): int
    {
        return max(1, (int)ceil($total / max(1, $perPage)));
    }

    /**
     * @return array<int,int|string>
     */
    public static function window(int $page, int $pages, int $span = 2): array
    {
        if ($pages <= 1) {
            return array(1);
        }
        $start = max(1, $page - $span);
        $end = min($pages, $page + $span);
        $items = array();
        if ($start > 1) {
            $items[] = 1;
            if ($start > 2) {
                $items[] = '...';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $items[] = $i;
        }
        if ($end < $pages) {
            if ($end < $pages - 1) {
                $items[] = '...';
            }
            $items[] = $pages;
        }
        return $items;
    }
}
