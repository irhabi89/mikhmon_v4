<?php

declare(strict_types=1);

use Mikhmon\Http\Csrf;
use Mikhmon\Support\Pagination;

/**
 * Shared view helpers. Deliberately dependency-free so they also work on
 * pages that have not been migrated to the new classes yet.
 */

if (!function_exists('mikhmon_e')) {
    function mikhmon_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('mikhmon_money')) {
    function mikhmon_money($value, string $currency = 'Rp', int $decimals = 0): string
    {
        return $currency . ' ' . number_format((float)$value, $decimals, ',', '.');
    }
}

if (!function_exists('mikhmon_url')) {
    /**
     * Merge query parameters into a relative URL, preserving the path.
     *
     * @param array<string,mixed> $params
     */
    function mikhmon_url(string $base, array $params = array()): string
    {
        $pos = strpos($base, '?');
        if ($pos === false) {
            $path = $base;
            $query = array();
        } else {
            $path = substr($base, 0, $pos);
            $query = array();
            parse_str(substr($base, $pos + 1), $query);
        }
        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }
        $qs = http_build_query($query);
        return $path . ($qs !== '' ? '?' . $qs : '');
    }
}

if (!function_exists('mikhmon_pager')) {
    function mikhmon_pager(int $total, int $page, int $perPage, string $baseUrl): string
    {
        $pages = Pagination::pages($total, $perPage);
        $page = min(max(1, $page), $pages);
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = min($total, $page * $perPage);
        $out = '<div class="mm-pagination-wrap">';
        $out .= '<span class="mm-page-info">Menampilkan <b>' . number_format($from, 0, ',', '.') . '–' . number_format($to, 0, ',', '.') . '</b> dari <b>' . number_format($total, 0, ',', '.') . '</b></span>';
        $out .= '<nav class="mm-pagination" aria-label="Pagination">';
        if ($page > 1) {
            $out .= '<a class="mm-page-btn" href="' . mikhmon_e(mikhmon_url($baseUrl, array('page' => 1, 'per_page' => $perPage))) . '" aria-label="Halaman pertama">&laquo;</a>';
            $out .= '<a class="mm-page-btn" href="' . mikhmon_e(mikhmon_url($baseUrl, array('page' => $page - 1, 'per_page' => $perPage))) . '" aria-label="Halaman sebelumnya">&lsaquo;</a>';
        } else {
            $out .= '<span class="mm-page-btn is-disabled">&laquo;</span><span class="mm-page-btn is-disabled">&lsaquo;</span>';
        }
        foreach (Pagination::window($page, $pages) as $item) {
            if ($item === '...') {
                $out .= '<span class="mm-page-ellipsis">…</span>';
                continue;
            }
            $number = (int)$item;
            $active = $number === $page;
            $out .= '<a class="mm-page-btn' . ($active ? ' is-active' : '') . '"'
                . ($active ? ' aria-current="page"' : '')
                . ' href="' . mikhmon_e(mikhmon_url($baseUrl, array('page' => $number, 'per_page' => $perPage))) . '">'
                . $number . '</a>';
        }
        if ($page < $pages) {
            $out .= '<a class="mm-page-btn" href="' . mikhmon_e(mikhmon_url($baseUrl, array('page' => $page + 1, 'per_page' => $perPage))) . '" aria-label="Halaman berikutnya">&rsaquo;</a>';
            $out .= '<a class="mm-page-btn" href="' . mikhmon_e(mikhmon_url($baseUrl, array('page' => $pages, 'per_page' => $perPage))) . '" aria-label="Halaman terakhir">&raquo;</a>';
        } else {
            $out .= '<span class="mm-page-btn is-disabled">&rsaquo;</span><span class="mm-page-btn is-disabled">&raquo;</span>';
        }
        return $out . '</nav></div>';
    }
}

if (!function_exists('mikhmon_per_page')) {
    function mikhmon_per_page(int $perPage, string $baseUrl): string
    {
        $out = '<label class="mm-per-page">Baris <select onchange="if(this.value){location.href=this.value;}">';
        foreach (Pagination::SIZES as $size) {
            $url = mikhmon_url($baseUrl, array('per_page' => $size, 'page' => 1));
            $out .= '<option value="' . mikhmon_e($url) . '"' . ($size === $perPage ? ' selected' : '') . '>' . $size . '</option>';
        }
        return $out . '</select></label>';
    }
}

if (!function_exists('mikhmon_source_badge')) {
    /**
     * @param array<string,mixed> $status
     */
    function mikhmon_source_badge(array $status): string
    {
        if (!empty($status['empty'])) {
            return '<span class="mm-badge bg-grey text-white">Belum ada data</span>';
        }
        if (!empty($status['in_sync'])) {
            return '<span class="mm-badge bg-green text-white"><i class="fa fa-check"></i> 100% Sinkron</span>';
        }
        $parts = array();
        if ($status['coverage'] !== null) {
            $parts[] = 'Tercakup ' . number_format((float)$status['coverage'], 1, ',', '.') . '%';
        }
        $missing = isset($status['db']['missing']) ? (int)$status['db']['missing'] : 0;
        $extra = isset($status['db']['extra']) && $status['db']['extra'] !== null ? (int)$status['db']['extra'] : 0;
        if ($missing > 0) {
            $parts[] = number_format($missing, 0, ',', '.') . ' belum diimpor';
        }
        if ($extra > 0) {
            $parts[] = number_format($extra, 0, ',', '.') . ' tidak ada di MikroTik';
        }
        if (empty($status['router']['complete'])) {
            $parts[] = 'belum diverifikasi penuh';
        }
        $class = 'bg-yellow text-dark';
        if ($missing === 0 && $extra === 0 && !empty($status['router']['complete'])) {
            $class = 'bg-blue text-white';
        } elseif ($missing > 0) {
            $class = 'bg-orange text-white';
        }
        return '<span class="mm-badge ' . $class . '"><i class="fa fa-refresh"></i> ' . mikhmon_e(implode(' · ', $parts)) . '</span>';
    }
}

if (!function_exists('mikhmon_page_header')) {
    function mikhmon_page_header(string $title, string $icon = 'fa-money', string $actions = '', string $subtitle = ''): string
    {
        $out = '<header class="mm-page-head"><div class="mm-page-title"><h1><i class="fa ' . mikhmon_e($icon) . '"></i> ' . mikhmon_e($title) . '</h1>';
        if ($subtitle !== '') {
            $out .= '<p class="mm-page-sub">' . $subtitle . '</p>';
        }
        $out .= '</div><div class="mm-page-actions">' . $actions . '</div></header>';
        return $out;
    }
}

if (!function_exists('mikhmon_empty_state')) {
    function mikhmon_empty_state(string $title, string $message, string $action = ''): string
    {
        return '<div class="mm-empty"><i class="fa fa-database"></i><h3>' . mikhmon_e($title) . '</h3>'
            . '<p>' . mikhmon_e($message) . '</p>' . $action . '</div>';
    }
}

if (!function_exists('mikhmon_alert')) {
    function mikhmon_alert(string $type, string $message): string
    {
        $class = $type === 'danger' ? 'mm-alert-danger' : ($type === 'warning' ? 'mm-alert-warning' : 'mm-alert-info');
        return '<div class="mm-alert ' . $class . '">' . $message . '</div>';
    }
}

if (!function_exists('mikhmon_csrf_field')) {
    function mikhmon_csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="' . mikhmon_e(Csrf::token()) . '">';
    }
}
