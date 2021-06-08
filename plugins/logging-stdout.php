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

class LoggingStdout extends \API\PluginApi
{
    private $loglevel;

    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(LOGGING_PLUGIN);

        // load active logging levels
        $this->loglevel = explode(',', strtoupper($this->config->loglevel));

        if (in_array('LOG', $this->loglevel)) {
            $this->register_call(
                'LOG',
                function ($caller, $msg) {
                    $this->write_stdout(GREEN . 'LOG', $caller, $msg);
                }
            );
        }
        if (in_array('ALERT', $this->loglevel)) {
            $this->register_call(
                'ALERT',
                function ($caller, $msg) {
                    $this->write_stdout(YELLOW . 'ALERT', $caller, $msg);
                }
            );
        }
        if (in_array('CRITICAL', $this->loglevel)) {
            $this->register_call(
                'CRITICAL',
                function ($caller, $msg) {
                    $this->write_stdout(RED . 'CRITICAL', $caller, $msg);
                }
            );
        }
        if (in_array('ERROR', $this->loglevel)) {
            $this->register_call(
                'ERROR',
                function ($caller, $msg) {
                    $this->write_stdout(RED . 'ERROR', $caller, $msg);
                }
            );
        }
    }

    public function write_stdout($type, $caller, $msg)
    {
        printf(BLUE . "%s %s: " . GRAY . "[" . CYAN . "%s" . GRAY . "]: %s\n", date("D, d M Y H:i:s"), $type, $caller, ...$msg);
    }
}
