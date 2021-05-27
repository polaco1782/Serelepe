<?php

use \API\PluginApi;

namespace Plugin;

function check_private($addr)
{
    $ip = ip2long($addr);

    $localaddr = [
      [ip2long('127.0.0.0'),   24],
      [ip2long('10.0.0.0'),    24],
      [ip2long('172.16.0.0'),  20],
      [ip2long('192.168.0.0'), 16],
      [ip2long('169.254.0.0'), 16],
    ];

    foreach ($localaddr as $ll) {
       // check if between netmask
        if (($ip & ~((1 << $ll[1]) - 1)) === $ll[0]) {
            return true;
        }
    }

    return false;
}

class Ping extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN);
    }
    
    public function run()
    {
        $l = Proxmox::request('/nodes');

        $cmd = "fping -A -a -q -i 1 -r 0 ";
        $hosts = [];
    
        foreach ($l->data as $ll) {
            $x = Proxmox::request("/nodes/{$ll->node}/lxc");
    
            foreach ($x->data as $xx) {
                $z = Proxmox::request("/nodes/{$ll->node}/lxc/{$xx->vmid}/config")->data;
                $st = Proxmox::request("/nodes/{$ll->node}/lxc/{$xx->vmid}/status/current")->data;

                $nets = preg_grep('/^net[\d]*/', array_keys((array)$z));

                if ($st->status == 'stopped') {
                    $this->LOG("Container {$st->name} is stopped, skipping...");
                    continue;
                }

                // parse each network device result
                if (!empty($nets)) {
                    foreach ($nets as $net) {
                        preg_match('/(?<=ip=)[^\/]+/', $z->{$net}, $out);
    
                        if (count($out)) {
                            $hosts[$z->hostname] = $out[0];
                        }
                    }
                }
            }
        }

        // execute and get result from ping test
        $out = shell_exec("{$cmd} " . implode(' ', $hosts));
        $out = explode("\n", $out);

        foreach (array_diff($hosts, $out) as $host => $diff) {
            $this->ALERT("No ICMP response from {$host}/{$diff} address");
        }
    }
}
