<?php

use \API\PluginApi;

namespace Plugin;

// include parent class before autoloader
include_once 'logging-stdout.php';

class LoggingDebug extends LoggingStdout
{
    // register debug call
    public function __construct()
    {
        $this->register_call(
            '__debug', function ($caller, $msg) {
                $this->write_stdout(RED.'DEBUG', $caller, $msg); 
            }
        );
    }
}