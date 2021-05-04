<?php

use \API\PluginApi;

namespace Plugin;

define('CYAN', "\e[96m");
define('BLUE', "\e[94m");
define('YELLOW', "\e[93m");
define('RED', "\e[91m");
define('GREEN', "\e[92m");
define('GRAY', "\e[0m");

class LoggingStdout extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(LOGGING_PLUGIN);

        $this->register_call(
            'LOG', function ($caller, $msg) {
                $this->write_stdout(GREEN.'LOG', $caller, $msg); 
            }
        );
        $this->register_call(
            'ALERT', function ($caller, $msg) {
                $this->write_stdout(YELLOW.'ALERT', $caller, $msg); 
            }
        );
        $this->register_call(
            'REPORT', function ($caller, $msg) {
                $this->write_stdout(RED.'REPORT', $caller, $msg); 
            }
        );
    }

    public function write_stdout($type, $caller, $msg)
    {
        printf(BLUE."%s %s: ".GRAY."[".CYAN."%s".GRAY."]: %s\n", date(DATE_RFC2822), $type, $caller, $msg);
    }
}