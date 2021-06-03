<?php

use \API\PluginApi;

namespace Plugin;

class ProxMox_Memory extends \API\PluginApi
{
    private $logfmt = "MEM Threshold reached for CT: %s: %s of %s : %0.2f%%";

    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN);
    }
    
    public function run()
    {
        $l = Proxmox::request('/nodes');

        foreach ($l->data as $ll) {
            $x = Proxmox::request("/nodes/{$ll->node}/lxc");
            foreach (($x->data ?: []) as $xx) {
                $usage = ($xx->mem * 100) / $xx->maxmem;

                switch (true) {
                    case ($usage >= $this->config->threshold_critical):
                        $this->CRITICAL(sprintf($this->logfmt, $xx->name, formatBytes($xx->mem), formatBytes($xx->maxmem), $usage));
                        break;
                    case ($usage >= $this->config->threshold_alert):
                        $this->ALERT(sprintf($this->logfmt, $xx->name, formatBytes($xx->mem), formatBytes($xx->maxmem), $usage));
                        break;
                    default:
                        $this->LOG(sprintf("Checking %s memory: %s (%0.2f%% used)", $xx->name, formatBytes($xx->maxmem), $usage));
                        break;
                }
            }
        }
    }
}
