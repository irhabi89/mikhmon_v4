<?php

declare(strict_types=1);

namespace Mikhmon\Router;

use Mikhmon\Storage\Database;
use Mikhmon\Support\Config;
use Mikhmon\Support\Log;

require_once dirname(__DIR__, 2) . '/lib/routeros_api.class.php';

final class RouterClientFactory
{
    /** @var Database */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function forSession(string $session): RouterClientInterface
    {
        $row = $this->db->fetchOne('SELECT * FROM mikhmon_sessions WHERE name = ?', array($session));
        if (!is_array($row)) {
            throw new RouterException('router session not found: ' . $session);
        }

        $target = RouterTarget::fromString(
            isset($row['ip_host']) ? (string)$row['ip_host'] : '',
            isset($row['port_host']) ? (int)$row['port_host'] : 8728
        );
        $password = \decrypt(isset($row['pass_host']) ? (string)$row['pass_host'] : '');
        $transport = (string)Config::get('MIKHMON_ROUTER_TRANSPORT', 'native');

        if ($transport === 'pear2' && class_exists('Mikhmon\\Router\\Pear2RouterClient')) {
            return new Pear2RouterClient($target, isset($row['user_host']) ? (string)$row['user_host'] : '', $password, $this->options());
        }
        if ($transport === 'pear2') {
            Log::warning('pear2 transport requested but Pear2RouterClient is not installed; using native');
        }

        return new NativeRouterClient($target, isset($row['user_host']) ? (string)$row['user_host'] : '', $password, $this->options());
    }

    public function targetForSession(string $session): RouterTarget
    {
        $row = $this->db->fetchOne('SELECT ip_host, port_host FROM mikhmon_sessions WHERE name = ?', array($session));
        if (!is_array($row)) {
            throw new RouterException('router session not found: ' . $session);
        }
        return RouterTarget::fromString(
            isset($row['ip_host']) ? (string)$row['ip_host'] : '',
            isset($row['port_host']) ? (int)$row['port_host'] : 8728
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function options(): array
    {
        return array(
            'attempts' => 2,
            'delay' => 1,
            'timeout' => 5,
            'read_timeout' => 300,
            'debug' => false,
        );
    }
}
