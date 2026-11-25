<?php
/*****************************
 *
 * RouterOS PHP API class v1.6
 * Author: Denis Basta
 * Contributors:
 *    Nick Barnes
 *    Ben Menking (ben [at] infotechsc [dot] com)
 *    Jeremy Jefferson (http://jeremyj.com)
 *    Cristian Deluxe (djcristiandeluxe [at] gmail [dot] com)
 *    Mikhail Moskalev (mmv.rus [at] gmail [dot] com)
 *
 * http://www.mikrotik.com
 * http://wiki.mikrotik.com/wiki/API_PHP_class
 *
 ******************************/

class RouterosAPI
{
    var $debug     = false; //  Show debug information
    var $connected = false; //  Connection state
    var $port      = 8728;  //  Port to connect to (default 8729 for ssl)
    var $ssl       = false; //  Connect using SSL (must enable api-ssl in IP/Services)
    var $timeout   = 3;     //  Connection attempt timeout
    var $read_timeout = 30; //  Default deadline for one complete API response
    var $attempts  = 1;     //  Connection attempt count (fail fast by default)
    var $delay     = 1;     //  Delay between connection attempts in seconds

    var $socket;            //  Variable for storing socket resource
    var $error_no;          //  Variable for storing connection error number, if any
    var $error_str;         //  Variable for storing connection error text, if any
    var $last_error = '';   //  Last socket/protocol error (human readable)
    var $last_ret   = null; //  Last "ret" value returned by the router
    var $last_complete = true; //  Last response reached !done without timeout/EOF
    private $empty_reads = 0;

    /* Check, can be var used in foreach  */
    public function isIterable($var)
    {
        return $var !== null
                && (is_array($var)
                || $var instanceof Traversable
                || $var instanceof Iterator
                || $var instanceof IteratorAggregate
                );
    }

    /**
     * Print text for debug purposes
     *
     * @param string      $text       Text to print
     *
     * @return void
     */
    public function debug($text)
    {
        if ($this->debug) {
            echo $text . "\n";
        }
    }


    /**
     *
     *
     * @param string        $length
     *
     * @return void
     */
    public function encodeLength($length)
    {
        if ($length < 0x80) {
            $length = chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $length = chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $length = chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $length = chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length >= 0x10000000) {
            $length = chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        return $length;
    }


    /**
     * Login to RouterOS
     *
     * @param string      $ip         Hostname (IP or domain) of the RouterOS server
     * @param string      $login      The RouterOS username
     * @param string      $password   The RouterOS password
     *
     * @return boolean                If we are connected or not
     */
    public function connect($ip, $login, $password)
    {
        $target = $this->parseTarget($ip);
        $this->port = $target['port'];
        $this->last_error = '';
        $this->last_ret = null;

        for ($ATTEMPT = 1; $ATTEMPT <= $this->attempts; $ATTEMPT++) {
            $this->connected = false;
            $PROTOCOL = ($this->ssl ? 'ssl://' : '' );
            $context = stream_context_create(array('ssl' => array('ciphers' => 'ADH:ALL', 'verify_peer' => false, 'verify_peer_name' => false)));
            $this->debug('Connection attempt #' . $ATTEMPT . ' to ' . $PROTOCOL . $target['host'] . ':' . $this->port . '...');
            $this->error_no = 0;
            $this->error_str = '';
            $this->socket = @stream_socket_client($PROTOCOL . $target['host'] . ':' . $this->port, $this->error_no, $this->error_str, $this->timeout, STREAM_CLIENT_CONNECT, $context);
            if ($this->socket) {
                stream_set_timeout($this->socket, $this->timeout);
                $this->write('/login', false);
                $this->write('=name=' . $login, false);
                $this->write('=password=' . $password);
                $RESPONSE = $this->readRawWords();
                if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                    if (!isset($RESPONSE[1])) {
                        // Login method post-v6.43
                        $this->connected = true;
                    } else {
                        // Login method pre-v6.43
                        $MATCHES = array();
                        if (preg_match_all('/[^=]+/i', $RESPONSE[1], $MATCHES)) {
                            if ($MATCHES[0][0] == 'ret' && isset($MATCHES[0][1]) && strlen($MATCHES[0][1]) == 32) {
                                $this->write('/login', false);
                                $this->write('=name=' . $login, false);
                                $this->write('=response=00' . md5(chr(0) . $password . pack('H*', $MATCHES[0][1])));
                                $RESPONSE = $this->readRawWords();
                                if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                                    $this->connected = true;
                                }
                            }
                        }
                    }
                }
                if ($this->connected) {
                    stream_set_timeout($this->socket, $this->read_timeout);
                    break;
                }
                $this->last_error = isset($RESPONSE[1]) ? 'login failed' : 'login failed';
                @fclose($this->socket);
                $this->socket = null;
            } else {
                $this->last_error = $this->error_str !== '' ? $this->error_str : 'connection failed';
            }
            if ($ATTEMPT < $this->attempts) {
                sleep($this->delay);
            }
        }

        if ($this->connected) {
            $this->debug('Connected...');
        } else {
            if ($this->last_error === '') {
                $this->last_error = 'connection failed';
            }
            $this->debug('Error: ' . $this->last_error);
        }
        return $this->connected;
    }

    /**
     * Accept "host", "host:port", "[ipv6]" and "[ipv6]:port".
     * Strips a scheme/path in case a URL was pasted into the IP field.
     *
     * @param string $input
     * @return array{host:string,port:int}
     */
    public function parseTarget($input)
    {
        $input = trim((string)$input);
        $input = preg_replace('/\s+/', '', $input);
        $input = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $input);
        $input = preg_replace('#/.*$#', '', $input);

        $host = $input;
        $port = (int)$this->port;
        if ($port < 1 || $port > 65535) {
            $port = 8728;
        }

        if (preg_match('/^\[([^\]]+)\](?::([0-9]+))?$/', $input, $m)) {
            $host = $m[1];
            if (isset($m[2]) && $m[2] !== '') {
                $port = (int)$m[2];
            }
        } elseif (substr_count($input, ':') === 1) {
            list($h, $p) = explode(':', $input, 2);
            if ($h !== '' && $p !== '' && ctype_digit($p)) {
                $host = $h;
                $port = (int)$p;
            }
        }

        return array('host' => $host, 'port' => $port);
    }


    /**
     * Disconnect from RouterOS
     *
     * @return void
     */
    public function disconnect()
    {
        // let's make sure this socket is still valid.  it may have been closed by something else
        if( is_resource($this->socket) ) {
            fclose($this->socket);
        }
        $this->connected = false;
        $this->debug('Disconnected...');
    }


    /**
     * Parse response from Router OS
     *
     * @param array       $response   Response data
     *
     * @return array                  Array with parsed data
     */
    public function parseResponse($response)
    {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = array();
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }

            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }

            return $PARSED;
        } else {
            return array();
        }
    }


    /**
     * Parse response from Router OS
     *
     * @param array       $response   Response data
     *
     * @return array                  Array with parsed data
     */
    public function parseResponse4Smarty($response)
    {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = array();
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }
            foreach ($PARSED as $key => $value) {
                $PARSED[$key] = $this->arrayChangeKeyName($value);
            }
            return $PARSED;
            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }
        } else {
            return array();
        }
    }


    /**
     * Change "-" and "/" from array key to "_"
     *
     * @param array       $array      Input array
     *
     * @return array                  Array with changed key names
     */
    public function arrayChangeKeyName(&$array)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                $tmp = str_replace("-", "_", $k);
                $tmp = str_replace("/", "_", $tmp);
                if ($tmp) {
                    $array_new[$tmp] = $v;
                } else {
                    $array_new[$k] = $v;
                }
            }
            return $array_new;
        } else {
            return $array;
        }
    }


    /**
     * Read one length-prefixed word. Returns null on EOF/timeout.
     *
     * @param string $firstByte
     * @param float  $deadline
     * @return int|null
     */
    private function readLength($firstByte, $deadline)
    {
        $b = ord($firstByte);
        if (($b & 0x80) === 0) {
            return $b;
        }
        if (($b & 0xC0) === 0x80) {
            $extra = $this->readBytes(1, $deadline);
            return ($extra === null) ? null : (($b & 0x3F) << 8) + ord($extra);
        }
        if (($b & 0xE0) === 0xC0) {
            $extra = $this->readBytes(2, $deadline);
            if ($extra === null) {
                return null;
            }
            return (($b & 0x1F) << 16) + (ord($extra[0]) << 8) + ord($extra[1]);
        }
        if (($b & 0xF0) === 0xE0) {
            $extra = $this->readBytes(3, $deadline);
            if ($extra === null) {
                return null;
            }
            return (($b & 0x0F) << 24) + (ord($extra[0]) << 16) + (ord($extra[1]) << 8) + ord($extra[2]);
        }
        // RouterOS reserves 0xF0 + four bytes for lengths above 0xFFFFFFF.
        $extra = $this->readBytes(4, $deadline);
        if ($extra === null) {
            return null;
        }
        return (ord($extra[0]) << 24) + (ord($extra[1]) << 16) + (ord($extra[2]) << 8) + ord($extra[3]);
    }

    /**
     * Read exactly $len bytes with an EOF/timeout guard.
     *
     * @param int   $len
     * @param float $deadline
     * @return string|null
     */
    private function readBytes($len, $deadline)
    {
        $len = (int)$len;
        if ($len <= 0) {
            return '';
        }
        $data = '';
        while (strlen($data) < $len) {
            if (microtime(true) > $deadline) {
                $this->last_error = 'read timeout';
                return null;
            }
            $chunk = @fread($this->socket, $len - strlen($data));
            if ($chunk === false || $chunk === '') {
                if (!is_resource($this->socket) || feof($this->socket)) {
                    $this->last_error = 'connection closed';
                    return null;
                }
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out'])) {
                    $this->last_error = 'read timeout';
                    return null;
                }
                $this->empty_reads++;
                if ($this->empty_reads > 500) {
                    $this->last_error = 'no data from router';
                    return null;
                }
                usleep(1000);
                continue;
            }
            $this->empty_reads = 0;
            $data .= $chunk;
        }
        return $data;
    }

    /**
     * Read a complete API sentence (words terminated by a zero-length word).
     * Returns null on EOF/timeout.
     *
     * @param float $deadline
     * @return array|null
     */
    private function readSentence($deadline)
    {
        $words = array();
        while (true) {
            if (microtime(true) > $deadline) {
                $this->last_error = 'read timeout';
                return null;
            }
            $byte = @fread($this->socket, 1);
            if ($byte === false || $byte === '') {
                if (!is_resource($this->socket) || feof($this->socket)) {
                    $this->last_error = 'connection closed';
                    return null;
                }
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out'])) {
                    $this->last_error = 'read timeout';
                    return null;
                }
                $this->empty_reads++;
                if ($this->empty_reads > 500) {
                    $this->last_error = 'no data from router';
                    return null;
                }
                usleep(1000);
                continue;
            }
            $this->empty_reads = 0;
            $length = $this->readLength($byte, $deadline);
            if ($length === null) {
                return null;
            }
            if ($length === 0) {
                return $words;
            }
            $word = $this->readBytes($length, $deadline);
            if ($word === null) {
                return null;
            }
            $words[] = $word;
            $this->debug('>>> [' . $length . '] ' . $word);
        }
    }

    /**
     * Parse word list into an associative row. Leading "=" is stripped.
     *
     * @param array $words
     * @return array
     */
    private function parseWords($words)
    {
        $row = array();
        $count = count($words);
        for ($i = 1; $i < $count; $i++) {
            $w = (string)$words[$i];
            if ($w === '') {
                continue;
            }
            if ($w[0] === '=') {
                $w = substr($w, 1);
            }
            $pos = strpos($w, '=');
            if ($pos === false) {
                $row[$w] = '';
            } else {
                $row[substr($w, 0, $pos)] = substr($w, $pos + 1);
            }
        }
        return $row;
    }

    /**
     * Read all sentences until a terminal reply; returns flat raw words.
     * Used by the login handshake (compatible with pre/post 6.43).
     *
     * @param float|null $timeout
     * @return array
     */
    public function readRawWords($timeout = null)
    {
        if (!is_resource($this->socket)) {
            $this->last_error = 'not connected';
            return array();
        }
        $deadline = microtime(true) + (($timeout === null) ? $this->read_timeout : (float)$timeout);
        $all = array();
        while (true) {
            $words = $this->readSentence($deadline);
            if ($words === null) {
                break;
            }
            if (empty($words)) {
                continue;
            }
            foreach ($words as $w) {
                $all[] = $w;
            }
            if (in_array($words[0], array('!done', '!fatal'), true)) {
                break;
            }
        }
        return $all;
    }

    /**
     * Read one complete response without collecting rows when a callback is used.
     *
     * @param callable|null $onRow
     * @param float         $deadline
     * @param bool          $collect
     * @return array
     */
    private function readResponse($onRow, $deadline, $collect)
    {
        $rows = array();
        $trap = array();
        $fatal = array();
        $ret = null;
        $count = 0;
        $ok = true;
        $error = '';

        while (true) {
            $words = $this->readSentence($deadline);
            if ($words === null) {
                $ok = false;
                $error = $this->last_error !== '' ? $this->last_error : 'read failed';
                break;
            }
            if (empty($words)) {
                continue;
            }
            $type = $words[0];
            if ($type === '!re') {
                $row = $this->parseWords($words);
                $count++;
                if ($collect) {
                    $rows[] = $row;
                }
                if ($onRow !== null) {
                    $stop = call_user_func($onRow, $row);
                    if ($stop === false) {
                        $ok = false;
                        $error = 'cancelled';
                        break;
                    }
                }
            } elseif ($type === '!trap') {
                $trap[] = $this->parseWords($words);
            } elseif ($type === '!fatal') {
                $fatal = $this->parseWords($words);
                $ok = false;
                $error = 'fatal: ' . (isset($fatal['message']) ? $fatal['message'] : 'router closed the API connection');
                break;
            } elseif ($type === '!done') {
                $attrs = $this->parseWords($words);
                if (array_key_exists('ret', $attrs)) {
                    $ret = $attrs['ret'];
                }
                break;
            } elseif ($type === '!empty') {
                // RouterOS 7.18+: an empty result set is followed by !done.
                // Do not break here or the next command reads that !done and
                // the connection goes out of sync.
                continue;
            } else {
                // Unknown reply type: ignore instead of hanging.
                $this->debug('Unknown API reply: ' . $type);
            }
        }

        if (!empty($trap)) {
            $ok = false;
            if ($error === '') {
                $error = 'trap';
            }
        }
        $this->last_error = $error;
        $this->last_ret = $ret;

        return array(
            'ok' => $ok,
            'rows' => $rows,
            'count' => $count,
            'ret' => $ret,
            'trap' => $trap,
            'fatal' => $fatal,
            'error' => $error,
        );
    }

    /**
     * Read data from Router OS.
     *
     * @param boolean $parse Parse rows (true) or return flat raw words (false).
     * @return array|string
     */
    public function read($parse = true)
    {
        if (!is_resource($this->socket)) {
            $this->last_error = 'not connected';
            return $parse ? array('!error' => 'not connected') : array();
        }
        if ($parse === false) {
            return $this->readRawWords();
        }
        $res = $this->readResponse(null, microtime(true) + $this->read_timeout, true);
        $this->last_complete = !empty($res['ok']) && empty($res['fatal']) && empty($res['trap']);

        if (!empty($res['fatal'])) {
            return array('!fatal' => $res['fatal']);
        }
        if (!empty($res['trap'])) {
            $out = $res['rows'];
            $out['!trap'] = $res['trap'];
            return $out;
        }
        if (!$res['ok'] && empty($res['rows'])) {
            return array('!error' => $res['error']);
        }
        if ($res['ret'] !== null && empty($res['rows'])) {
            return $res['ret'];
        }
        return $res['rows'];
    }

    /**
     * Stream rows to a callback without keeping them in memory.
     *
     * @param callable   $onRow
     * @param float|null $timeout
     * @return array
     */
    public function readStream($onRow, $timeout = null)
    {
        if (!is_resource($this->socket)) {
            $this->last_error = 'not connected';
            return array('ok' => false, 'rows' => array(), 'count' => 0, 'ret' => null, 'trap' => array(), 'fatal' => array(), 'error' => 'not connected');
        }
        $deadline = microtime(true) + (($timeout === null) ? $this->read_timeout : (float)$timeout);
        return $this->readResponse($onRow, $deadline, false);
    }


    /**
     * Write (send) data to Router OS
     *
     * @param string      $command    A string with the command to send
     * @param mixed       $param2     If we set an integer, the command will send this data as a "tag"
     *                                If we set it to boolean true, the funcion will send the comand and finish
     *                                If we set it to boolean false, the funcion will send the comand and wait for next command
     *                                Default: true
     *
     * @return boolean                Return false if no command especified
     */
    public function write($command, $param2 = true)
    {
        if ($command) {
            if (!is_resource($this->socket)) {
                $this->last_error = 'not connected';
                return false;
            }
            $data = explode("\n", $command);
            foreach ($data as $com) {
                $com = trim($com);
                @fwrite($this->socket, $this->encodeLength(strlen($com)) . $com);
                $this->debug('<<< [' . strlen($com) . '] ' . $com);
            }

            if (gettype($param2) == 'integer') {
                @fwrite($this->socket, $this->encodeLength(strlen('.tag=' . $param2)) . '.tag=' . $param2 . chr(0));
                $this->debug('<<< [' . strlen('.tag=' . $param2) . '] .tag=' . $param2);
            } elseif (gettype($param2) == 'boolean') {
                @fwrite($this->socket, ($param2 ? chr(0) : ''));
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Send a command and its attributes. Shared by comm()/commStream().
     *
     * @param string $com
     * @param array  $arr
     * @return void
     */
    private function sendCommand($com, $arr)
    {
        $count = count($arr);
        $this->write($com, !$arr);
        $i = 0;
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                switch ($k[0]) {
                    case "?":
                        $el = "$k=$v";
                        break;
                    case "~":
                        $el = "$k~$v";
                        break;
                    default:
                        $el = "=$k=$v";
                        break;
                }

                $last = ($i++ == $count - 1);
                $this->write($el, $last);
            }
        }
    }


    /**
     * Write (send) data to Router OS
     *
     * @param string      $com        A string with the command to send
     * @param array       $arr        An array with arguments or queries
     *
     * @return array                  Array with parsed
     */
    public function comm($com, $arr = array())
    {
        $this->last_complete = true;
        $this->last_error = '';
        $this->sendCommand($com, $arr);
        $result = $this->read();
        return $result;
    }

    /**
     * Send a command and stream each !re row to a callback.
     * Memory stays bounded even for 100k+ rows.
     *
     * @param string        $com
     * @param array         $arr
     * @param callable      $onRow
     * @param float|null    $timeout
     * @return array{ok:bool,count:int,ret:mixed,trap:array,fatal:array,error:string}
     */
    public function commStream($com, $arr, $onRow, $timeout = null)
    {
        $this->sendCommand($com, $arr);
        return $this->readStream($onRow, $timeout);
    }

    /**
     * Run a count-only print query and return the integer count (-1 on error).
     *
     * @param string $com
     * @param array  $arr
     * @return int
     */
    public function countOnly($com, $arr = array())
    {
        $arr['count-only'] = '';
        $res = $this->comm($com, $arr);
        if (is_array($res)) {
            if (isset($res['!trap']) || isset($res['!fatal']) || isset($res['!error'])) {
                return -1;
            }
            return count($res);
        }
        return is_numeric($res) ? (int)$res : -1;
    }

    /**
     * Standard destructor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}

// encrypt decript

function encrypt($string, $key=128) {
	$result = '';
	for($i=0, $k= strlen($string); $i<$k; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)+ord($keychar));
		$result .= $char;
	}
	return base64_encode($result);
}
function decrypt($string, $key=128) {
	$result = '';
	$string = base64_decode($string);
	for($i=0, $k=strlen($string); $i< $k ; $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)-ord($keychar));
		$result .= $char;
	}
	return $result;
}

// Reformat date time MikroTik
// by Laksamadi Guko

function formatInterval($dtm){
$val_convert = $dtm;
$new_format = str_replace("s", "", str_replace("m", "m ", str_replace("h", "h ", str_replace("d", "d ", str_replace("w", "w ", $val_convert)))));
return $new_format;
}

function formatDTM($dtm){
$dtm = (string)$dtm;
if ($dtm === "") { return ""; }
$day = "";
if(substr($dtm, 1,1) == "d" || substr($dtm, 2,1) == "d"){
    $day = explode("d",$dtm)[0]."d";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("d",$dtm)[1];
}elseif(substr($dtm, 1,1) == "w" && substr($dtm, 3,1) == "d" || substr($dtm, 2,1) == "w" && substr($dtm, 4,1) == "d"){
    $day = explode("d",$dtm)[0]."d";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("d",$dtm)[1];
}elseif (substr($dtm, 1,1) == "w" || substr($dtm, 2,1) == "w" ) {
    $day = explode("w",$dtm)[0]."w";
    $day = str_replace("d", "d ", str_replace("w", "w ", $day));
    $dtm = explode("w",$dtm)[1];
}

// secs
if(strlen($dtm) == "2" && substr($dtm, -1) == "s"){
    $format = $day." 00:00:0".substr($dtm, 0,-1);
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "s"){
    $format = $day." 00:00:".substr($dtm, 0,-1);
//minutes
}elseif(strlen($dtm) == "2" && substr($dtm, -1) == "m"){
    $format = $day." 00:0".substr($dtm, 0,-1).":00";
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "m"){
    $format = $day." 00:".substr($dtm, 0,-1).":00";
//hours
}elseif(strlen($dtm) == "2" && substr($dtm, -1) == "h"){
    $format = $day." 0".substr($dtm, 0,-1).":00:00";
}elseif(strlen($dtm) == "3" && substr($dtm, -1) == "h"){
    $format = $day." ".substr($dtm, 0,-1).":00:00";
 
//minutes -secs
}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm,1,-2) == "m"){
    $format = $day." "."00:0".substr($dtm, 0,1).":0".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,1,-3) == "m"){
    $format = $day." "."00:0".substr($dtm, 0,1).":".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,2,-2) == "m"){
    $format = $day." "."00:".substr($dtm, 0,2).":0".substr($dtm, 3,-1);
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,2,-3) == "m"){
    $format = $day." "."00:".substr($dtm, 0,2).":".substr($dtm, 3,-1);

//hours -secs
}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm,1,-2) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":00:0".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,1,-3) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":00:".substr($dtm, 2,-1);
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm,2,-2) == "h"){
    $format = $day." ".substr($dtm, 0,2).":00:0".substr($dtm, 3,-1);
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,2,-3) == "h"){
    $format = $day." ".substr($dtm, 0,2).":00:".substr($dtm, 3,-1);

//hours -secs
}elseif(strlen($dtm) == "4" && substr($dtm, -1) == "m" && substr($dtm,1,-2) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-1).":00";
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm,1,-3) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-1).":00";
}elseif(strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm,2,-2) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-1).":00";
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "m" && substr($dtm,2,-3) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-1).":00";

//hours minutes secs
}elseif(strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm,3,-2) == "m" && substr($dtm,1,-4) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-3).":0".substr($dtm, 4,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,3,-3) == "m" && substr($dtm,1,-5) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":0".substr($dtm, 2,-4).":".substr($dtm, 4,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,4,-2) == "m" && substr($dtm,1,-5) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-3).":0".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,4,-3) == "m" && substr($dtm,1,-6) == "h"){
    $format = $day." 0".substr($dtm, 0,1).":".substr($dtm, 2,-4).":".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm,4,-2) == "m" && substr($dtm,2,-4) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-3).":0".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,4,-3) == "m" && substr($dtm,2,-5) == "h"){
    $format = $day." ".substr($dtm, 0,2).":0".substr($dtm, 3,-4).":".substr($dtm, 5,-1);
}elseif(strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm,5,-2) == "m" && substr($dtm,2,-5) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-3).":0".substr($dtm, 6,-1);
}elseif(strlen($dtm) == "9" && substr($dtm, -1) == "s" && substr($dtm,5,-3) == "m" && substr($dtm,2,-6) == "h"){
    $format = $day." ".substr($dtm, 0,2).":".substr($dtm, 3,-4).":".substr($dtm, 6,-1);

}else{
    $format = $dtm;
}
return $format;
}


function randN($length) {
	$chars = "23456789";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randUC($length) {
	$chars = "ABCDEFGHJKLMNPRSTUVWXYZ";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}
function randLC($length) {
	$chars = "abcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randULC($length) {
	$chars = "ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNLC($length) {
	$chars = "23456789abcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNUC($length) {
	$chars = "23456789ABCDEFGHJKLMNPRSTUVWXYZ";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

function randNULC($length) {
	$chars = "23456789ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz";
	$charArray = str_split($chars);
	$charCount = strlen($chars);
	$result = "";
	for($i=1;$i<=$length;$i++)
	{
		$randChar = rand(0,$charCount-1);
		$result .= $charArray[$randChar];
	}
	return $result;
}

?>
