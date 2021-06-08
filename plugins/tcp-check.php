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

class TCPconn extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN);
    }

    public function run()
    {
        // initialize metrics variables
        $this->METRIC_ADD('tcp_connect_miliseconds', 0);
        $this->METRIC_ADD('tcp_connect_fails', 0);

        if (is_array($this->config->host)) {
            __debug("Multiple host port test", ...$this->config->host);
            
            // host based loop
            foreach ($this->config->host as $host) {
                // supress: value may be missing
                @list($addr,$port) = explode(':', $host);

                $this->measure_time(true);
                $connection = @fsockopen($addr, $port, $errn, $errs, $this->config->timeout); // supress: may fail to connect
                $fmt = $this->measure_time();

                if (!is_resource($connection)) {
                    $this->ALERT("TCP connection test failed for {$host}. [{$errs}]");
                } else {
                    $this->LOG("Connection to {$host} sucessful, {$fmt}ms");
                    fclose($connection);
                }
            }
        } elseif (is_array($this->config->port)) {
            __debug("Single host port test");
            
            // port based loop
            foreach ($this->config->port as $port) {
                $this->measure_time(true);
                $connection = @fsockopen($this->config->host, $port, $errn, $errs, $this->config->timeout); // supress: may fail to connect
                $fmt = $this->measure_time();

                if (!is_resource($connection)) {
                    $this->ALERT("TCP connection test failed for {$this->config->host}:{$port}. [{$errs}]");
                } else {
                    $this->LOG("Connection to {$this->config->host}:{$port} sucessful, {$fmt}ms");
                    fclose($connection);

                    $this->METRIC_STORE('tcp_connect_miliseconds', $fmt);
                }
            }
        } else {
            $this->METRIC_INC('tcp_connect_fails');
        }
    }
}
