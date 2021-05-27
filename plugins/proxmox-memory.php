<?php

use \API\PluginApi;

namespace Plugin;

class Memory extends \API\PluginApi
{
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

                $text = "CT: " . $xx->name . ": " . formatBytes($xx->mem) . " of " . formatBytes($xx->maxmem) . ": " . sprintf("%0.2f%%", ($xx->mem * 100) / $xx->maxmem);

                if (($xx->mem * 100) / $xx->maxmem >= (float)$this->config->threshold) {
                    $this->ALERT("Warning: Memory usage exceeds {$this->config->threshold}% for " . $text);
                }

                $this->LOG($text);
            }
        }
    }
}
