<?php

use \API\PluginApi;

namespace Plugin;

class Prometheus extends \API\PluginApi
{
    private $server;
    private $file;

    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(LOGGING_PLUGIN, '* * * * *');

        $this->file = tempnam(sys_get_temp_dir(), 'prometheus-');

        
    }

    public function run()
    {
        // fputs($this->data, "TEST FEED!\n");
        // fflush($this->data);
    }
}