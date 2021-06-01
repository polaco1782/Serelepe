<?php

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
