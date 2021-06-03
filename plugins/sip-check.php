<?php

use \API\PluginApi;

namespace Plugin;

class SIP extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN);
    }

    public function run()
    {
        // initialize metrics variables
        $this->METRIC_ADD("sip_connect_miliseconds", 0);
        $this->METRIC_ADD("sip_connect_fails", 0);
    }
}
