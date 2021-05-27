<?php

use \API\PluginApi;

namespace Plugin;

class Diskspace extends \API\PluginApi
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
                $text = "CT: " . $xx->name . ": " . formatBytes($xx->disk) . " of " . formatBytes($xx->maxdisk) . ": " . sprintf("%0.2f%%", ($xx->disk * 100) / $xx->maxdisk);
    
                if (($xx->disk * 100) / $xx->maxdisk >= (float)$this->config->threshold) {
                    $this->ALERT("Warning: Disk usage exceeds {$this->config->threshold}% for " . $text);
                }

                $this->LOG($text);
            }
        }
    }
}
