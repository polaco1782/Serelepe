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

define('CYAN', "\e[96m");
define('BLUE', "\e[94m");
define('YELLOW', "\e[93m");
define('RED', "\e[91m");
define('GREEN', "\e[92m");
define('GRAY', "\e[0m");

pcntl_async_signals(true);

pcntl_signal(SIGUSR1, function ($signal) {
    __debug("SIGUSR1 received!");
    \API\Autoloader::unload_plugins();
    \API\Autoloader::load_plugins();
});

pcntl_signal(SIGCHLD, function ($signal) {
    __debug("SIGCHLD received!");
    pcntl_waitpid(0, $status, WNOHANG);
    pcntl_wexitstatus($status);
});

function formatBytes($bytes, $precision = 2)
{
    $unit = ["B", "KB", "MB", "GB", "TB"];
    $exp = floor(log($bytes, 1024)) | 0;
    return round($bytes / (pow(1024, $exp)), $precision) . $unit[$exp];
}

function __debug(...$str)
{
    $trace = debug_backtrace()[0];

    printf(
        "%sDEBUG%s%s->> %s[%s%s%s:%s%d%s], %s\n",
        RED,
        GRAY,
        BLUE,
        GRAY,
        CYAN,
        basename($trace['file']),
        GRAY,
        YELLOW,
        $trace['line'],
        GRAY,
        implode(' ', $str)
    );
}
