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
