<?php

use \API\PluginApi;

namespace Plugin;

class ProxMox_Storage extends \API\PluginApi
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
            $x = Proxmox::request("/nodes/{$ll->node}/storage");
            foreach (($x->data ?: []) as $xx) {
                // PBS volumes report as non shared? bug?
                if ($xx->shared || $xx->type == 'pbs') {
                    if (!isset($shared[$xx->storage])) {
                        $shared[$xx->storage] = (($xx->total - $xx->avail) * 100) / $xx->total;

                        if ($shared[$xx->storage] >= (float)$this->config->threshold) {
                            $this->CRITICAL("Volume reached threshold: [" . $xx->storage . "] - " . formatBytes(($xx->total - $xx->avail)) . " of " . formatBytes($xx->total) . ": " . sprintf("%0.2f%%", $shared[$xx->storage]));
                        }
                    }
                } else {
                    if ($xx->enabled) {
                        $local[$ll->node][$xx->storage] = (($xx->total - $xx->avail) * 100) / $xx->total;
                    }
                }
            }
        }
    }
}
