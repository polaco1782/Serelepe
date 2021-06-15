<?php

/*
MIT License
Copyright (c) 2021 Cassiano Martin
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

use \API\PluginApi;

namespace Plugin;

class SIP extends \API\PluginApi
{
    private $socket;
    private $cli_port;
    private $cli_addr;

    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN);
    }

    public function run()
    {
        foreach($this->config->servers as $server)
        {
            // initialize metrics variables
            $this->METRIC_ADD($server->name."_sip_connect_miliseconds", 0);
            $this->METRIC_ADD($server->name."_sip_response_miliseconds", 0);
            $this->METRIC_ADD($server->name."_sip_connect_fails", 0);

            if (!$this->connect($server)) {
                continue;
            }
    
            $this->sendreq($server);
            $this->disconnect();
        }
    }

    public function connect($server)
    {
        $this->measure_time(true);

        if ($server->protocol == "tcp") {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        } else {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        }

        if (!$this->socket) {
            throw new \Exception("Could not create a new socket!");
        }

        // connect socket, supressed warning messages
        $result = @socket_connect($this->socket, $server->address, $server->port);

        if (!$result) {
            $this->CRITICAL("[{$server->name}] Failed to connect SIP server: " . socket_strerror(socket_last_error($this->socket)));
            return false;
        }

        // retrieve client addr and port
        if (!socket_getsockname($this->socket, $this->cli_addr, $this->cli_port)) {
            throw new \Exception("[{$server->name}] Could not get client socket information!");
        }

        // setup socket timeouts
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->config->timeout, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->config->timeout, 'usec' => 0]);
        
        $this->METRIC_STORE($server->name.'_sip_connect_miliseconds', $this->measure_time());

        return true;
    }

    public static function gentag()
    {
        // generate a random string
        $chars = str_shuffle(implode('', array_merge(range('a', 'z'), range(0, 9))));

        return substr($chars, 0, 6);
    }

    public function sendreq($server)
    {
        $tag = self::gentag();
        $idtag = self::gentag();

        $req[] = "OPTIONS {$server->sip_uri} SIP/2.0";
        $req[] = "Via: SIP/2.0/UDP {$this->cli_addr}:{$this->cli_port};rport";
        $req[] = "From: sip:checksip@{$this->cli_addr}:{$this->cli_port};tag=$tag";
        $req[] = "To: {$server->sip_uri}";
        $req[] = "Call-ID: $idtag@{$this->cli_addr}";
        $req[] = "CSeq: 1 OPTIONS";
        $req[] = "Contact: sip:checksip@{$this->cli_addr}:{$this->cli_port}";
        $req[] = "Content-length: 0";
        $req[] = "Max-Forwards: 70";
        $req[] = "User-agent: check_sip 1.01";
        $req[] = "Accept: text/plain";

        $req = implode("\r\n", $req);

        $this->measure_time(true);

        // supress: don't print warning message
        if (!@socket_write($this->socket, $req, strlen($req))) {
            $this->CRITICAL("[{$server->name}] Can't write to socket! Connectivity failure!");
            $this->METRIC_INC($server->name.'_sip_connect_fails');

            return;
        }

        // supress: don't print warning message
        $data = @socket_read($this->socket, 2048);
        if (!$data) {
            $this->CRITICAL("[{$server->name}] Can't read from socket, no response from host, or Connectivity failure!");
            $this->METRIC_INC($server->name.'_sip_connect_fails');

            return;
        }

        // supress: an empty value might return
        $data = @explode("\r\n", $data)[0];

        if (preg_match('/^SIP.+200/', $data)) {
            // store response time
            $this->METRIC_STORE($server->name.'_sip_response_miliseconds', $this->measure_time());
            $this->LOG("[{$server->name}] SIP response code: {$data}");
        } else {
            $this->ERROR("[{$server->name}] Wrong SIP response code: " . $data);
        }

    }

    public function disconnect()
    {
        // supress: trying to close an invalid handle
        @socket_close($this->socket);
    }
}
