<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use PHPUnit\Framework\TestCase;
use RouterosAPI;

require_once dirname(__DIR__) . '/lib/routeros_api.class.php';

final class RouterApiTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the socket test');
        }
    }

    private static function encode(string $word): string
    {
        $len = strlen($word);
        if ($len < 0x80) { return chr($len) . $word; }
        if ($len < 0x4000) { return chr(($len >> 8) | 0x80) . chr($len & 0xFF) . $word; }
        return chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF) . $word;
    }

    private static function sentence($socket, array $words): void
    {
        foreach ($words as $word) { fwrite($socket, self::encode($word)); }
        fwrite($socket, chr(0));
    }

    private static function readWord($socket)
    {
        $byte = fread($socket, 1);
        if ($byte === '' || $byte === false) { return null; }
        $n = ord($byte);
        if ($n & 0x80) {
            if (($n & 0xC0) === 0x80) { $n = (($n & 0x3F) << 8) + ord(fread($socket, 1)); }
            elseif (($n & 0xE0) === 0xC0) { $n = (($n & 0x1F) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1)); }
            else { $n = (ord(fread($socket, 1)) << 24) + (ord(fread($socket, 1)) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1)); }
        }
        if ($n === 0) { return ''; }
        $data = '';
        while (strlen($data) < $n) {
            $chunk = fread($socket, $n - strlen($data));
            if ($chunk === '' || $chunk === false) { break; }
            $data .= $chunk;
        }
        return $data;
    }

    private static function readSentence($socket): array
    {
        $words = array();
        while (($word = self::readWord($socket)) !== null) {
            if ($word === '') { break; }
            $words[] = $word;
        }
        return $words;
    }

    public function testEmptyReplyDoesNotDesyncNextCommand(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server);
        $address = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $address);

        $pid = pcntl_fork();
        if ($pid === 0) {
            $conn = @stream_socket_accept($server, 5);
            if ($conn) {
                self::readSentence($conn);
                self::sentence($conn, array('!done'));

                self::readSentence($conn);
                self::sentence($conn, array('!empty'));
                self::sentence($conn, array('!done'));

                self::readSentence($conn);
                self::sentence($conn, array('!re', '=.id=*1', '=name=nov/19/2026-|-10:00-|-u-|-5000-|-a-|-m-|-30d-|-p-|-c', '=comment=mikhmon'));
                self::sentence($conn, array('!done'));
                fclose($conn);
            }
            fclose($server);
            exit(0);
        }
        fclose($server);
        usleep(100000);

        $api = new RouterosAPI();
        $api->attempts = 1;
        $api->read_timeout = 3;
        $this->assertTrue($api->connect($host . ':' . $port, 'admin', 'x'));

        $empty = $api->comm('/system/script/print', array('count-only' => ''));
        $this->assertSame(array(), $empty);
        // The next command must read the following reply, not the leftover
        // !done from the empty response.
        $this->assertSame(1, $api->countOnly('/system/script/print'));

        // The server only has one command left; close and reconnect is not
        // needed because countOnly above consumed the second command.
        $api->disconnect();
        pcntl_wait($status);
    }
}
