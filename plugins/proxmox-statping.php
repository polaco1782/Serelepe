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

class ProxMox_StatPing extends \API\PluginApi
{
    protected static $curl;

    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN);

        self::$curl = curl_init();
    }

    public function statping_addhost($name, $ip)
    {
        curl_setopt_array(self::$curl, [
            CURLOPT_URL => $this->config->api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
              "name": "'.$name.'",
              "domain": "'.$ip.'",
              "expected": "",
              "expected_status": 200,
              "check_interval": 30,
              "type": "icmp",
              "method": "GET",
              "post_data": "",
              "port": 0,
              "timeout": 30,
              "order_id": 0,
              "group_id": 5
            }',
            CURLOPT_HTTPHEADER => array(
              'Content-Type: application/json',
              'Authorization: Bearer '.$this->config->api_key
            ),
        ]);

        $response = curl_exec(self::$curl);

        var_dump($response);
    }

    public function run()
    {
        $l = Proxmox::request('/nodes');

        $hosts = [];

        // parse each host/container status
        foreach ($l->data as $ll) {
            $x = Proxmox::request("/nodes/{$ll->node}/lxc");
            //$h = Proxmox::request("/nodes/{$ll->node}/network/adm0")->data;
   
            foreach ($x->data as $xx) {
                $z = Proxmox::request("/nodes/{$ll->node}/lxc/{$xx->vmid}/config")->data;
                $st = Proxmox::request("/nodes/{$ll->node}/lxc/{$xx->vmid}/status/current")->data;

                $nets = preg_grep('/^net[\d]*/', array_keys((array)$z));

                // parse each network device result
                if (!empty($nets)) {
                    foreach ($nets as $net) {
                        preg_match('/(?<=ip=)[^\/]+/', $z->{$net}, $out);

                        if (count($out)) {
                            $hosts[$z->hostname][] = $out[0];
                            $this->statping_addhost($z->hostname, $out[0]);
                        }
                    }
                }
            }
        }

        var_dump($hosts);
    }
}
