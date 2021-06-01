<?php

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
