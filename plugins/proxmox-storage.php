<?php

use \API\PluginApi;

namespace Plugin;

class ProxMox_Storage extends \API\PluginApi
{
    private $logfmt = "VOLUME threshold reached: [%s] - %s of %s: %0.2f%%";
    
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

                        switch (true) {
                            case ($shared[$xx->storage] >= $this->config->threshold_critical):
                                $this->CRITICAL(sprintf($this->logfmt, $xx->storage, formatBytes(($xx->total - $xx->avail)), formatBytes($xx->total), $shared[$xx->storage]));
                                break;
                            case ($shared[$xx->storage] >= $this->config->threshold_alert):
                                $this->ALERT(sprintf($this->logfmt, $xx->storage, formatBytes(($xx->total - $xx->avail)), formatBytes($xx->total), $shared[$xx->storage]));
                                break;
                            default:
                                break;
                        }
                    }
                } else {
                    if ($xx->enabled) {
                        $local[$ll->node][$xx->storage] = (($xx->total - $xx->avail) * 100) / $xx->total;

                        switch (true) {
                            case ($local[$ll->node][$xx->storage] >= $this->config->threshold_critical):
                                $this->CRITICAL(sprintf($this->logfmt, $xx->storage, formatBytes(($xx->total - $xx->avail)), formatBytes($xx->total), $local[$ll->node][$xx->storage]));
                                break;
                            case ($local[$ll->node][$xx->storage] >= $this->config->threshold_alert):
                                $this->ALERT(sprintf($this->logfmt, $xx->storage, formatBytes(($xx->total - $xx->avail)), formatBytes($xx->total), $local[$ll->node][$xx->storage]));
                                break;
                            default:
                                $this->LOG(sprintf("Checking %s[%s] storage: %s (%0.2f%% used)", $ll->node, $xx->storage, formatBytes(($xx->total - $xx->avail)), $local[$ll->node][$xx->storage]));
                                break;
                        }
                    }
                }
            }
        }
    }
}
