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

class ProxMox_IPAM extends \API\PluginApi
{
    protected static $curl;
    protected static $token;
    protected static $subnets;

    public $error_codes = [
            // OK
            200 => "OK",
            201 => "Created",
            202 => "Accepted",
            204 => "No Content",
            // Client errors
            400 => "Bad Request",
            401 => "Unauthorized",
            403 => "Forbidden",
            404 => "Not Found",
            405 => "Method Not Allowed",
            415 => "Unsupported Media Type",
            // Server errors
            500 => "Internal Server Error",
            501 => "Not Implemented",
            503 => "Service Unavailable",
            505 => "HTTP Version Not Supported",
            511 => "Network Authentication Required"
    ];

    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN);

        $this->getToken();
    }

    // request token from php IPAM api
    public function getToken()
    {
        self::$curl = curl_init();

        curl_setopt_array(self::$curl, [
            CURLOPT_URL => $this->config->api_url.'/user/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
              'Authorization: Basic '.base64_encode($this->config->api_key)
            ],
        ]);
        
        $response = curl_exec(self::$curl);

        // decode response
        $response = json_decode($response);

        self::$token = $response->data->token;
    }

    // get all IPs from php IPAM api
    public function getAllIps()
    {
        curl_setopt_array(self::$curl, [
            CURLOPT_URL => $this->config->api_url.'/addresses/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
              'token: '.self::$token
            ],
        ]);
        
        $response = json_decode(curl_exec(self::$curl));

        return $response->data;
    }

    public function findSubnet($addr)
    {
        if(!self::$subnets)
        {
            curl_setopt_array(self::$curl, [
                CURLOPT_URL => $this->config->api_url.'/subnets/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'token: '.self::$token
                ]
            ]);

            $response = json_decode(curl_exec(self::$curl), true);
            self::$subnets = $response['data'];
        }
        
        $ids = array_column(self::$subnets, 'id');
        $nets = array_column(self::$subnets, 'subnet');
        $masters = array_column(self::$subnets, 'masterSubnetId');
        $mask = array_column(self::$subnets, 'mask');

        $id = array_filter(array_map(function($net, $mask, $id) use ($addr, $masters) {
            $mask = intval($mask);
            $net = ip2long($net);
            $ip = ip2long($addr);

            if(!in_array($id, $masters))
            {
                // check if ipv4 is in subnet range
                if (($ip & ~((1 << (32 - $mask)) - 1)) == $net) {
                    return $id;
                }
            }
            
        }, $nets, $mask, $ids));

        return array_pop($id);
    }


    // post IP to php IPAM api
    public function postIp($ip,$hostname,$description='')
    {
        curl_setopt_array(self::$curl, [
            CURLOPT_URL => $this->config->api_url.'/addresses/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'token: '.self::$token
            ],
            CURLOPT_POSTFIELDS => '{
                "ip": "'.$ip.'",
                "hostname": "'.$hostname.'",
                "description": "'.$description.'",
                "note": "'.$this->config->note.'",
                "subnetId": '.$this->findSubnet($ip).'
            }',
        ]);

        $response = json_decode(curl_exec(self::$curl));

        print_r($response);

        // on conflict address, update it
        if($response->code == 409)
            $this->updateIp($ip,$hostname,$description);
    }

    // update IP to php IPAM api
    public function updateIp($ip,$hostname,$description='')
    {
        $id = (int)array_column($this->findID($ip),'id')[0];

        curl_setopt_array(self::$curl, [
            CURLOPT_URL => $this->config->api_url.'/addresses/'.$id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'token: '.self::$token
            ],
            CURLOPT_POSTFIELDS => '{
                "id": '.$id.',
                "hostname": "'.$hostname.'",
                "description": "'.$description.'",
                "note": "'.$this->config->note.'"
            }',
        ]);

        $response = json_decode(curl_exec(self::$curl));

        var_dump($response);
    }

    // find ID into IPAM api
    public function findID($ip)
    {
        curl_setopt_array(self::$curl, [
            CURLOPT_URL => $this->config->api_url.'/addresses/search/'.$ip,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'token: '.self::$token
            ]
        ]);

        $response = json_decode(curl_exec(self::$curl));

        return $response->data;
    }

    public function run()
    {
        $l = Proxmox::request('/nodes');
        $iplist = $this->getAllIps();

        $hosts = [];

        $ipam_hosts=array_combine(array_column($iplist, 'ip'), array_column($iplist, 'hostname'));

        // parse each host/container status
        foreach ($l->data as $ll) {
            $x = Proxmox::request("/nodes/{$ll->node}/lxc");
            $y = Proxmox::request("/nodes/{$ll->node}/qemu");

            // VMs
            foreach ($y->data as $yy) {
                $z = Proxmox::request("/nodes/{$ll->node}/qemu/{$yy->vmid}/config")->data;

                $nets = preg_grep('/^net[\d]*/', array_keys((array)$z));

                // parse each network device result
                if (!empty($nets)) {
                    foreach ($nets as $net) {

                        // find mac address on the string
                        preg_match("/([a-f0-9]{2}[:|\-]?){6}/i", $z->{$net}, $m);
                        print_r($m);
                    }
                }

            }

            // containers
            foreach ($x->data as $xx) {
                $z = Proxmox::request("/nodes/{$ll->node}/lxc/{$xx->vmid}/config")->data;
                $st = Proxmox::request("/nodes/{$ll->node}/lxc/{$xx->vmid}/status/current")->data;

                $nets = preg_grep('/^net[\d]*/', array_keys((array)$z));

                // parse each network device result
                if (!empty($nets)) {
                    foreach ($nets as $net) {
                        preg_match('/(?<=ip=)[^\/]+/', $z->{$net}, $out);

                        if (count($out)) {
                            $hosts[$out[0]] = $z->hostname;

                            $this->postIp($out[0], $z->hostname, '#ON PROXMOX NODE: '.$ll->node);
                        }
                    }
                }
            }
        }
    }
}
