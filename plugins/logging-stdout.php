<?php

use \API\PluginApi;

namespace Plugin;

class LoggingStdout extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(LOGGING_PLUGIN);

        $this->register_call(
            'LOG',
            function ($caller, $msg) {
                $this->write_stdout(GREEN . 'LOG', $caller, $msg);
            }
        );
        $this->register_call(
            'ALERT',
            function ($caller, $msg) {
                $this->write_stdout(YELLOW . 'ALERT', $caller, $msg);
            }
        );
        $this->register_call(
            'CRITICAL',
            function ($caller, $msg) {
                $this->write_stdout(RED . 'CRITICAL', $caller, $msg);
            }
        );
        $this->register_call(
            'ERROR',
            function ($caller, $msg) {
                $this->write_stdout(RED . 'ERROR', $caller, $msg);
            }
        );
    }

    public function write_stdout($type, $caller, $msg)
    {
        printf(BLUE . "%s %s: " . GRAY . "[" . CYAN . "%s" . GRAY . "]: %s\n", date("D, d M Y H:i:s"), $type, $caller, ...$msg);
    }
}
