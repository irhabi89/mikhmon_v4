<?php

declare(strict_types=1);

namespace Mikhmon\Router;

use Mikhmon\Support\Config;
use Mikhmon\Support\Log;

/**
 * Safely adds the server lookup to Mikhmon-generated hotspot profile
 * on-login scripts. Custom scripts are never touched.
 */
final class OnloginUpgrader
{
    /** @var RouterClientFactory */
    private $factory;

    public function __construct(RouterClientFactory $factory)
    {
        $this->factory = $factory;
    }

    public static function serverLookupSnippet(): string
    {
        return ':local mmsrv ""; '
            . ':foreach mma in=[/ip/hotspot/active/find where user=$user] do={ :set mmsrv [ /ip/hotspot/active/get $mma server ] }; '
            . ':if ($mmsrv = "") do={ :local mmu [/ip/hotspot/user/find where name=$user]; :if ([:len $mmu] > 0) do={ :set mmsrv [/ip/hotspot/user/get $mmu server] } }; '
            . ':if ($mmsrv = "") do={ :local mmh [/ip/hotspot/find where interface=$interface]; :if ([:len $mmh] > 0) do={ :set mmsrv [/ip/hotspot/get $mmh name] } }; ';
    }

    /**
     * Restore on-login strings from a backup JSON produced by upgrade().
     *
     * @return array{ok:bool,restored:int,errors:array<int,string>}
     */
    public function rollback(string $session, string $file): array
    {
        $result = array('ok' => true, 'restored' => 0, 'errors' => array());
        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            return array('ok' => false, 'restored' => 0, 'errors' => array('backup file tidak valid'));
        }
        $client = $this->factory->forSession($session);
        $client->connect();
        try {
            foreach ($data as $row) {
                if (!is_array($row) || !isset($row['name']) || !isset($row['on-login'])) {
                    continue;
                }
                $found = $client->command('/ip/hotspot/user/profile/print', array('?name' => (string)$row['name'], '.proplist' => '.id'));
                if (!is_array($found) || !isset($found[0]['.id'])) {
                    $result['errors'][] = 'profile not found: ' . $row['name'];
                    continue;
                }
                $client->command('/ip/hotspot/user/profile/set', array(
                    '.id' => (string)$found[0]['.id'],
                    'on-login' => (string)$row['on-login'],
                ));
                $result['restored']++;
            }
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $result['errors'][] = $e->getMessage();
        } finally {
            $client->close();
        }
        return $result;
    }

    /**
     * @return array{ok:bool,profiles:int,upgraded:int,skipped:int,errors:array<int,string>,backup:string}
     */
    public function upgrade(string $session, bool $apply = false): array
    {
        $client = $this->factory->forSession($session);
        $client->connect();
        $result = array(
            'ok' => true,
            'profiles' => 0,
            'upgraded' => 0,
            'skipped' => 0,
            'errors' => array(),
            'backup' => '',
        );
        $backup = array();
        try {
            $profiles = $client->command('/ip/hotspot/user/profile/print', array('.proplist' => '.id,name,on-login'));
            foreach ((array)$profiles as $profile) {
                if (!is_array($profile) || !isset($profile['name'])) {
                    continue;
                }
                $result['profiles']++;
                $name = (string)$profile['name'];
                $onlogin = isset($profile['on-login']) ? (string)$profile['on-login'] : '';
                if ($onlogin === '' || strpos($onlogin, '|#MMSRV1#|') !== false) {
                    $result['skipped']++;
                    continue;
                }

                $newOnlogin = null;

                // Newer generator: :local sname "..."; /system script add ...
                if (strpos($onlogin, ':local sname') !== false) {
                    $pattern = '/:local sname\s+"([^"]*)"\s*;/';
                    if (preg_match($pattern, $onlogin, $m) === 1) {
                        $oldRecord = ':local sname "' . $m[1] . '";';
                        $newRecord = self::serverLookupSnippet()
                            . ':local sname "' . $m[1] . '|#MMSRV1#|$mmsrv";';
                        $newOnlogin = str_replace($oldRecord, $newRecord, $onlogin);
                    }
                }

                // Older Mikhmon generator: inline /system script add name="...";
                if ($newOnlogin === null) {
                    $pattern = '/\/system script add\s+name="([^"]*)"\s+source="([^"]*)"\s+comment="mikhmon"/';
                    if (preg_match($pattern, $onlogin, $m) === 1) {
                        $newRecord = self::serverLookupSnippet()
                            . '/system script add name="' . $m[1] . '|#MMSRV1#|$mmsrv" source="' . $m[2] . '" comment="mikhmon"';
                        $newOnlogin = preg_replace($pattern, $newRecord, $onlogin, 1);
                    }
                }

                if (!is_string($newOnlogin) || $newOnlogin === $onlogin) {
                    $result['skipped']++;
                    continue;
                }
                $backup[] = array('name' => $name, 'on-login' => $onlogin);
                if (!$apply) {
                    $result['upgraded']++;
                    continue;
                }
                $params = array();
                if (!empty($profile['.id'])) {
                    $params['.id'] = (string)$profile['.id'];
                } else {
                    $result['skipped']++;
                    continue;
                }
                $params['on-login'] = $newOnlogin;
                $client->command('/ip/hotspot/user/profile/set', $params);
                $result['upgraded']++;
            }

            if ($apply && $backup) {
                $dir = Config::baseDir() . '/data/backups';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $path = $dir . '/onlogin-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $session) . '-' . date('Ymd-His') . '.json';
                file_put_contents($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $result['backup'] = $path;
            }
        } catch (\Throwable $e) {
            Log::error('on-login upgrade failed', array('session' => $session, 'error' => $e->getMessage()));
            $result['ok'] = false;
            $result['errors'][] = $e->getMessage();
        } finally {
            $client->close();
        }
        return $result;
    }
}
