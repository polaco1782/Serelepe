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
  
class SSH extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        if (!extension_loaded('ssh2')) {
            throw new \Exception('ERROR: You need to have SSH extension enabled, or this plugin will not work!');
        }

        parent::__construct(CHECK_PLUGIN);
    }

    public function run()
    {
        pcntl_signal(SIGALRM, function ($signal) {
            $this->CRITICAL('Timed out trying to connect SSH host ' . $this->config->host);
            return;
        });

        pcntl_alarm(5);

        // supress: may fail to connect
        $connection = @ssh2_connect($this->config->host, $this->config->port);

        if (!$connection) {
            $this->ERROR('Could not connect to SSH host ' . $this->config->host);
            return;
        }

        if (!ssh2_auth_password($connection, $this->config->username, $this->config->password)) {
            $this->ERROR('Could not authenticate SSH username/password!');
            return;
        }

        $stdout_stream = ssh2_exec($connection, $this->config->command);

        if (!$stdout_stream) {
            $this->ERROR('Failed to execute command on the remote host!');
            return;
        }

        $sio_stream = ssh2_fetch_stream($stdout_stream, SSH2_STREAM_STDIO);
        $err_stream = ssh2_fetch_stream($stdout_stream, SSH2_STREAM_STDERR);
       
        stream_set_blocking($sio_stream, true);
        stream_set_blocking($err_stream, true);
       
        $result_dio = stream_get_contents($sio_stream);
        $result_err = stream_get_contents($err_stream);
  
        echo 'stderr: ' . $result_err;
        echo 'stdio : ' . $result_dio;
    }
}
