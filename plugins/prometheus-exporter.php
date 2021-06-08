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

// used by php server to fetch data
if (php_sapi_name() == 'cli-server') {
    print("TODO\n");
    return;
}

class Prometheus extends \API\PluginApi
{
    static $redis;

    // constructor registers plugin type and name
    public function __construct()
    {
        if (!function_exists('pcntl_fork')) {
            throw new \Exception('ERROR: You need to have PCNTL extension enabled, or this plugin will not work!');
        }

        if (!extension_loaded('redis')) {
            throw new \Exception('ERROR: You need to have REDIS extension enabled, or this plugin will not work!');
        }

        parent::__construct(METRIC_PLUGIN);

        $this->register_call(
            'METRIC_STORE',
            function ($caller, $msg) {
                self::$redis->set('prometheus_' . $msg[0], $msg[1]);
            }
        );

        $this->register_call(
            'METRIC_ADD',
            function ($caller, $msg) {
                self::$redis->set('prometheus_' . $msg[0], $msg[1]);
            }
        );

        $this->register_call(
            'METRIC_INC',
            function ($caller, $msg) {
                self::$redis->incr('prometheus_' . $msg[0]);
            }
        );

        self::$redis = new \Redis();
        self::$redis->connect('localhost');

        $this->start_webserver();
    }

    public function run()
    {
    }

    public function start_webserver()
    {
        //pcntl_sigprocmask(SIG_BLOCK, [SIGCHLD]);

        switch ($pid = pcntl_fork()) {
            case -1: // failed to create process
                throw new \Exception('ERROR: fork() failed!');
            case 0: // child
                pcntl_exec(PHP_BINARY, ['-S', '0.0.0.0:8000', __FILE__]);
                throw new \Exception('ERROR: exec() failed!');
        }

        //pcntl_sigprocmask(SIG_UNBLOCK, [SIGCHLD], $xxx);
    }
}
