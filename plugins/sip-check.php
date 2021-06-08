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
        // initialize metrics variables
        $this->METRIC_ADD("sip_connect_miliseconds", 0);
        $this->METRIC_ADD("sip_response_miliseconds", 0);
        $this->METRIC_ADD("sip_connect_fails", 0);

        $this->connect();
        $this->sendreq();
    }

    public function connect()
    {
        $this->measure_time(true);

        if ($this->config->protocol == "tcp") {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        } else {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        }

        if (!$this->socket) {
            throw new \Exception("Could not create a new socket!");
        }

        // connect socket
        $result = socket_connect($this->socket, $this->config->server_address, $this->config->server_port);

        if (!$result) {
            $this->ERROR("Failed to connect SIP server: " . socket_strerror(socket_last_error($this->socket)));
            return false;
        }

        // retrieve client addr and port
        if (!socket_getsockname($this->socket, $this->cli_addr, $this->cli_port)) {
            throw new \Exception("Could not get client socket information!");
        }

        $this->METRIC_STORE('sip_connect_miliseconds', $this->measure_time());
    }

    public static function gentag()
    {
        // generate a random string
        $chars = str_shuffle(implode('', array_merge(range('a', 'z'), range(0, 9))));

        return substr($chars, 0, 6);
    }

    public function sendreq()
    {
        $tag = self::gentag();
        $idtag = self::gentag();

        $req[] = "OPTIONS {$this->config->sip_uri} SIP/2.0";
        $req[] = "Via: SIP/2.0/UDP {$this->cli_addr}:{$this->cli_port};rport";
        $req[] = "From: sip:checksip@{$this->cli_addr}:{$this->cli_port};tag=$tag";
        $req[] = "To: {$this->config->sip_uri}";
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
        if(!@socket_write($this->socket, $req, strlen($req)))
        {
            $this->CRITICAL("Can't write to socket! Connectivity failure!");
            $this->METRIC_INC('sip_connect_fails');
        }

        // supress: don't print warning message
        $data = @socket_read($this->socket, 2048);
        if(!$data)
        {
            $this->CRITICAL("Can't read from socket! Connectivity failure!");
            $this->METRIC_INC('sip_connect_fails');
        }

        // store response time
        $this->METRIC_STORE('sip_response_miliseconds', $this->measure_time());
    }
}
