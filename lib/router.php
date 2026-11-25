<?php
/*
 * Central RouterOS connection helpers.
 *
 * The old code called new RouterosAPI() + connect($iphost, ...) in dozens of
 * places and treated "host:port" as a plain host. This helper normalises the
 * target, fails fast for interactive pages and keeps one connection per
 * request when a page needs several commands.
 *
 * PHP 7.4 compatible.
 */

require_once __DIR__ . '/routeros_api.class.php';
require_once __DIR__ . '/mikhmon_db.php';
require_once __DIR__ . '/sessions_store.php';

/**
 * Return ['host' => string, 'port' => int] for a session row or session name.
 * Accepts host, host:port, [ipv6] and [ipv6]:port. A session row may carry an
 * explicit port_host (new schema) which wins over a port embedded in ip_host.
 *
 * @param array|string $sessionOrRow
 * @return array{host:string,port:int}
 */
function mikhmon_router_target($sessionOrRow)
{
    $row = is_array($sessionOrRow) ? $sessionOrRow : mikhmon_session_get((string)$sessionOrRow);
    if (!is_array($row)) {
        return array('host' => '', 'port' => 8728);
    }
    $parser = new RouterosAPI();
    $target = $parser->parseTarget(isset($row['ip_host']) ? $row['ip_host'] : '');
    if (isset($row['port_host']) && (int)$row['port_host'] > 0) {
        $target['port'] = (int)$row['port_host'];
    }
    return $target;
}

/**
 * Connect to the router for a session. Returns a connected RouterosAPI object
 * or false. Options: attempts, delay, timeout, read_timeout, debug, ssl.
 *
 * @param array|string $sessionOrRow
 * @param array        $opts
 * @return RouterosAPI|false
 */
function mikhmon_router_connect($sessionOrRow, array $opts = array())
{
    $row = is_array($sessionOrRow) ? $sessionOrRow : mikhmon_session_get((string)$sessionOrRow);
    if (!is_array($row)) {
        return false;
    }
    $host = isset($row['ip_host']) ? trim((string)$row['ip_host']) : '';
    $user = isset($row['user_host']) ? (string)$row['user_host'] : '';
    if ($host === '' || $user === '') {
        return false;
    }

    $opts = array_merge(array(
        'attempts' => 1,
        'delay' => 1,
        'timeout' => 2,
        'read_timeout' => 8,
        'debug' => false,
        'ssl' => false,
    ), $opts);

    $api = new RouterosAPI();
    $api->debug = !empty($opts['debug']);
    $api->ssl = !empty($opts['ssl']);
    $api->attempts = max(1, (int)$opts['attempts']);
    $api->delay = max(0, (int)$opts['delay']);
    $api->timeout = max(1, (int)$opts['timeout']);
    $api->read_timeout = max(2, (int)$opts['read_timeout']);

    $target = mikhmon_router_target($row);
    if ($target['host'] === '') {
        return false;
    }
    $api->port = $target['port'];

    if (!$api->connect($target['host'], $user, decrypt(isset($row['pass_host']) ? $row['pass_host'] : ''))) {
        return false;
    }
    return $api;
}

/**
 * Request-scoped connection cache. Returns false when the router cannot be
 * reached; callers should render an offline state instead of a blank page.
 *
 * @param string $session
 * @param array  $opts
 * @return RouterosAPI|false
 */
function mikhmon_router_api($session, array $opts = array())
{
    if (!isset($GLOBALS['mikhmon_router_connections']) || !is_array($GLOBALS['mikhmon_router_connections'])) {
        $GLOBALS['mikhmon_router_connections'] = array();
    }
    $key = (string)$session;
    if (isset($GLOBALS['mikhmon_router_connections'][$key]) && $GLOBALS['mikhmon_router_connections'][$key] instanceof RouterosAPI) {
        return $GLOBALS['mikhmon_router_connections'][$key];
    }
    $api = mikhmon_router_connect($session, $opts);
    if ($api === false) {
        return false;
    }
    $GLOBALS['mikhmon_router_connections'][$key] = $api;
    return $api;
}

/**
 * Close every cached connection (request shutdown / worker cleanup).
 *
 * @return void
 */
function mikhmon_router_close_all()
{
    if (!empty($GLOBALS['mikhmon_router_connections']) && is_array($GLOBALS['mikhmon_router_connections'])) {
        foreach ($GLOBALS['mikhmon_router_connections'] as $api) {
            if ($api instanceof RouterosAPI) {
                $api->disconnect();
            }
        }
        $GLOBALS['mikhmon_router_connections'] = array();
    }
}

/**
 * Last connection/protocol error from a RouterosAPI instance.
 *
 * @param RouterosAPI|false $api
 * @return string
 */
function mikhmon_router_error($api)
{
    if ($api instanceof RouterosAPI) {
        if ($api->last_error !== '') {
            return (string)$api->last_error;
        }
        if ($api->error_str !== '') {
            return (string)$api->error_str;
        }
    }
    return '';
}

/**
 * Read a complete RouterOS result through the short-lived result cache.
 * Returns ResultCache metadata: data, cached, created_at, stale, error.
 * The producer refuses to cache a timeout/partial response.
 *
 * @param RouterosAPI $api
 * @param string      $session
 * @param string      $command
 * @param array       $params
 * @param int|null    $ttl
 * @return array{data:mixed,cached:bool,created_at:int,stale:bool,error:string}
 */
function mikhmon_router_cached($api, $session, $command, array $params = array(), $ttl = null)
{
    if (!$api instanceof RouterosAPI) {
        throw new RuntimeException('Router is not connected');
    }
    $ttl = $ttl === null ? \Mikhmon\Support\ResultCache::ttl(15) : (int)$ttl;
    $key = $command . '|' . json_encode($params);
    return \Mikhmon\Support\ResultCache::remember((string)$session, $key, $ttl, function () use ($api, $command, $params) {
        $result = $api->comm($command, $params);
        if (property_exists($api, 'last_complete') && !$api->last_complete) {
            throw new RuntimeException($api->last_error !== '' ? $api->last_error : 'incomplete router response');
        }
        return $result;
    });
}

/**
 * Invalidate all cached read results for one router session.
 */
function mikhmon_cache_flush_session($session)
{
    if (class_exists('\\Mikhmon\\Support\\ResultCache')) {
        \Mikhmon\Support\ResultCache::flushSession((string)$session);
    }
}
