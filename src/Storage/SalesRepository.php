<?php

declare(strict_types=1);

namespace Mikhmon\Storage;

use Mikhmon\Support\RecordParser;

/**
 * Read/write access to the sales_records table.
 */
final class SalesRepository
{
    /** @var Database */
    private $db;

    /** @var array<int,string> */
    private static $sortColumns = array(
        'date' => 'date_key',
        'username' => 'username',
        'price' => 'price',
        'profile' => 'profile',
    );

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,mixed> $parsed
     */
    public function insertParsed(string $session, string $name, array $parsed, string $rosScriptId = '', string $scriptComment = ''): bool
    {
        $sql = 'INSERT OR IGNORE INTO sales_records
            (session, name, ros_script_id, server_name, source_date, date_key, month_key, time_key,
             username, profile, sale_comment, address, mac, validity, price, price_raw, script_comment)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $params = array(
            $session,
            $name,
            $rosScriptId,
            isset($parsed['server_name']) ? (string)$parsed['server_name'] : '',
            isset($parsed['source_date']) ? $parsed['source_date'] : '',
            $parsed['date_key'],
            $parsed['month_key'],
            isset($parsed['time_key']) ? $parsed['time_key'] : '',
            isset($parsed['username']) ? $parsed['username'] : '',
            isset($parsed['profile']) ? $parsed['profile'] : '',
            isset($parsed['sale_comment']) ? $parsed['sale_comment'] : '',
            isset($parsed['address']) ? $parsed['address'] : '',
            isset($parsed['mac']) ? $parsed['mac'] : '',
            isset($parsed['validity']) ? $parsed['validity'] : '',
            isset($parsed['price']) ? (float)$parsed['price'] : 0.0,
            isset($parsed['price_raw']) ? $parsed['price_raw'] : '',
            $scriptComment,
        );
        return $this->db->execute($sql, $params) > 0;
    }

    public function exists(string $session, string $name): bool
    {
        return (bool)$this->db->value(
            'SELECT 1 FROM sales_records WHERE session = ? AND name = ? LIMIT 1',
            array($session, $name)
        );
    }

    public function updateScriptId(string $session, string $name, string $rosScriptId): void
    {
        if ($rosScriptId === '') {
            return;
        }
        $this->db->execute(
            'UPDATE sales_records SET ros_script_id = ? WHERE session = ? AND name = ? AND ros_script_id = ""',
            array($rosScriptId, $session, $name)
        );
    }

    public function count(string $session): int
    {
        return (int)$this->db->value('SELECT COUNT(*) FROM sales_records WHERE session = ?', array($session));
    }

    /**
     * @param array<string,mixed> $filter
     * @return array<string,mixed>
     */
    public function normalizeFilter(array $filter): array
    {
        $out = array(
            'date_from' => null,
            'date_to' => null,
            'month_key' => null,
            'q' => null,
            'username_prefix' => null,
            'server' => null,
        );
        if (!empty($filter['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filter['date_from'])) {
            $out['date_from'] = (string)$filter['date_from'];
        }
        if (!empty($filter['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filter['date_to'])) {
            $out['date_to'] = (string)$filter['date_to'];
        }
        if (!empty($filter['month_key']) && preg_match('/^\d{4}-\d{2}$/', (string)$filter['month_key'])) {
            $out['month_key'] = (string)$filter['month_key'];
        }
        if (isset($filter['q'])) {
            $q = trim((string)$filter['q']);
            if ($q !== '') {
                $out['q'] = substr($q, 0, 100);
            }
        }
        if (!empty($filter['username_prefix'])) {
            $out['username_prefix'] = trim((string)$filter['username_prefix']);
        }
        if (isset($filter['server']) && $filter['server'] !== '') {
            $server = trim((string)$filter['server']);
            $out['server'] = substr($server, 0, 128);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $filter
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(string $session, array $filter): array
    {
        $where = 'session = ?';
        $params = array($session);
        if (!empty($filter['server'])) {
            if ($filter['server'] === '__none__') {
                $where .= " AND server_name = ''";
            } else {
                $where .= ' AND server_name = ?';
                $params[] = $filter['server'];
            }
        }
        if (!empty($filter['date_from'])) {
            $where .= ' AND date_key >= ?';
            $params[] = $filter['date_from'];
        }
        if (!empty($filter['date_to'])) {
            $where .= ' AND date_key <= ?';
            $params[] = $filter['date_to'];
        }
        if (!empty($filter['month_key'])) {
            $where .= ' AND month_key = ?';
            $params[] = $filter['month_key'];
        }
        if (!empty($filter['username_prefix'])) {
            $where .= " AND username LIKE ? ESCAPE '\\'";
            $params[] = $this->likePrefix((string)$filter['username_prefix']);
        }
        if (!empty($filter['q'])) {
            $like = '%' . $this->escapeLike((string)$filter['q']) . '%';
            $where .= " AND (username LIKE ? ESCAPE '\\' OR sale_comment LIKE ? ESCAPE '\\' OR profile LIKE ? ESCAPE '\\')";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        return array($where, $params);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $value);
    }

    private function likePrefix(string $value): string
    {
        return $this->escapeLike($value) . '%';
    }

    /**
     * @param array<string,mixed> $filter
     */
    public function countFiltered(string $session, array $filter): int
    {
        $filter = $this->normalizeFilter($filter);
        list($where, $params) = $this->buildWhere($session, $filter);
        return (int)$this->db->value('SELECT COUNT(*) FROM sales_records WHERE ' . $where, $params);
    }

    /**
     * @param array<string,mixed> $filter
     */
    public function sumFiltered(string $session, array $filter): float
    {
        $filter = $this->normalizeFilter($filter);
        list($where, $params) = $this->buildWhere($session, $filter);
        return (float)$this->db->value('SELECT COALESCE(SUM(price),0) FROM sales_records WHERE ' . $where, $params);
    }

    /**
     * @param array<string,mixed> $filter
     * @return array{rows:array<int,array<string,mixed>>,total:int,sum:float,page:int,pages:int,perPage:int}
     */
    public function paginate(string $session, array $filter, int $page, int $perPage, string $sort = 'date', string $dir = 'desc'): array
    {
        $filter = $this->normalizeFilter($filter);
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        list($where, $params) = $this->buildWhere($session, $filter);

        $total = (int)$this->db->value('SELECT COUNT(*) FROM sales_records WHERE ' . $where, $params);
        $sum = (float)$this->db->value('SELECT COALESCE(SUM(price),0) FROM sales_records WHERE ' . $where, $params);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $column = isset(self::$sortColumns[$sort]) ? self::$sortColumns[$sort] : 'date_key';
        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $order = $column . ' ' . $direction . ', id ' . $direction;
        if ($column === 'date_key') {
            $order = 'date_key ' . $direction . ', time_key ' . $direction . ', id ' . $direction;
        }

        $sql = 'SELECT id, session, name, ros_script_id, server_name, source_date, date_key, month_key, time_key,
                    username, profile, sale_comment, address, mac, validity, price, price_raw,
                    script_comment, imported_at
                FROM sales_records WHERE ' . $where . ' ORDER BY ' . $order
                . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage);

        return array(
            'rows' => $this->db->fetchAll($sql, $params),
            'total' => $total,
            'sum' => $sum,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        );
    }

    /**
     * @param array<string,mixed> $filter
     * @return \PDOStatement
     */
    public function exportStatement(string $session, array $filter): \PDOStatement
    {
        $filter = $this->normalizeFilter($filter);
        list($where, $params) = $this->buildWhere($session, $filter);
        $st = $this->db->pdo()->prepare(
            'SELECT date_key, time_key, username, profile, sale_comment, address, mac, validity, price, server_name, name
             FROM sales_records WHERE ' . $where . ' ORDER BY date_key DESC, time_key DESC, id DESC'
        );
        $st->execute($params);
        return $st;
    }

    /**
     * @return array<string,mixed>
     */
    public function stats(string $session, ?string $server = null): array
    {
        $thisMonth = date('Y-m');
        $prevMonth = date('Y-m', strtotime(date('Y-m-01') . ' -1 month'));
        $today = date('Y-m-d');
        $serverWhere = '';
        $params = array($thisMonth, $thisMonth, $prevMonth, $today, $today, $session);
        if ($server !== null && $server !== '') {
            if ($server === '__none__') {
                $serverWhere = " AND server_name = ''";
            } else {
                $serverWhere = ' AND server_name = ?';
                $params[] = $server;
            }
        }
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total_count,
                    COALESCE(SUM(price),0) AS total_sum,
                    COALESCE(SUM(CASE WHEN month_key = ? THEN 1 ELSE 0 END),0) AS month_count,
                    COALESCE(SUM(CASE WHEN month_key = ? THEN price ELSE 0 END),0) AS month_sum,
                    COALESCE(SUM(CASE WHEN month_key = ? THEN price ELSE 0 END),0) AS prev_sum,
                    COALESCE(SUM(CASE WHEN date_key = ? THEN 1 ELSE 0 END),0) AS today_count,
                    COALESCE(SUM(CASE WHEN date_key = ? THEN price ELSE 0 END),0) AS today_sum
             FROM sales_records WHERE session = ?' . $serverWhere,
            $params
        );
        if (!is_array($row)) {
            $row = array();
        }
        return array(
            'total_count' => (int)(isset($row['total_count']) ? $row['total_count'] : 0),
            'total_sum' => (float)(isset($row['total_sum']) ? $row['total_sum'] : 0),
            'month_count' => (int)(isset($row['month_count']) ? $row['month_count'] : 0),
            'month_sum' => (float)(isset($row['month_sum']) ? $row['month_sum'] : 0),
            'prev_sum' => (float)(isset($row['prev_sum']) ? $row['prev_sum'] : 0),
            'today_count' => (int)(isset($row['today_count']) ? $row['today_count'] : 0),
            'today_sum' => (float)(isset($row['today_sum']) ? $row['today_sum'] : 0),
            'month_key' => $thisMonth,
            'month_bucket' => (string)RecordParser::bucketFromMonthKey($thisMonth),
        );
    }

    /**
     * @return array<int,array{date_key:string,count:int,sum:float}>
     */
    public function daily(string $session, string $monthKey): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $monthKey) !== 1) {
            return array();
        }
        $rows = $this->db->fetchAll(
            'SELECT date_key, COUNT(*) AS c, COALESCE(SUM(price),0) AS s
             FROM sales_records WHERE session = ? AND month_key = ? GROUP BY date_key ORDER BY date_key',
            array($session, $monthKey)
        );
        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'date_key' => (string)$row['date_key'],
                'count' => (int)$row['c'],
                'sum' => (float)$row['s'],
            );
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    public function monthKeys(string $session): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT month_key FROM sales_records WHERE session = ? AND month_key != "" ORDER BY month_key DESC',
            array($session)
        );
        $out = array();
        foreach ($rows as $row) {
            $out[] = (string)$row['month_key'];
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    public function serverNames(string $session): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT server_name FROM sales_records WHERE session = ? ORDER BY server_name',
            array($session)
        );
        $out = array();
        foreach ($rows as $row) {
            $out[] = (string)$row['server_name'];
        }
        return $out;
    }

    /**
     * Today/month sales grouped by server.
     *
     * @return array<int,array<string,mixed>>
     */
    public function serverStats(string $session): array
    {
        $today = date('Y-m-d');
        $month = date('Y-m');
        $rows = $this->db->fetchAll(
            "SELECT CASE WHEN server_name = '' THEN '__none__' ELSE server_name END AS server_name,
                    COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN date_key = ? THEN 1 ELSE 0 END),0) AS today_count,
                    COALESCE(SUM(CASE WHEN date_key = ? THEN price ELSE 0 END),0) AS today_sum,
                    COALESCE(SUM(CASE WHEN month_key = ? THEN 1 ELSE 0 END),0) AS month_count,
                    COALESCE(SUM(CASE WHEN month_key = ? THEN price ELSE 0 END),0) AS month_sum
             FROM sales_records WHERE session = ?
             GROUP BY server_name ORDER BY server_name",
            array($today, $today, $month, $month, $session)
        );
        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'server_name' => (string)$row['server_name'],
                'total_count' => (int)$row['total_count'],
                'today_count' => (int)$row['today_count'],
                'today_sum' => (float)$row['today_sum'],
                'month_count' => (int)$row['month_count'],
                'month_sum' => (float)$row['month_sum'],
            );
        }
        return $out;
    }

    public function deleteAll(string $session): int
    {
        return $this->db->execute('DELETE FROM sales_records WHERE session = ?', array($session));
    }

    /**
     * @param array<string,mixed> $filter
     */
    public function deleteFiltered(string $session, array $filter): int
    {
        $filter = $this->normalizeFilter($filter);
        list($where, $params) = $this->buildWhere($session, $filter);
        return $this->db->execute('DELETE FROM sales_records WHERE ' . $where, $params);
    }

    public function pruneNotInSnapshot(string $session): int
    {
        return $this->db->execute(
            'DELETE FROM sales_records
             WHERE session = ?
               AND NOT EXISTS (
                 SELECT 1 FROM sales_router_records r
                 WHERE r.session = sales_records.session AND r.name = sales_records.name
               )',
            array($session)
        );
    }
}
