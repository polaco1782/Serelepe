<?php

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
