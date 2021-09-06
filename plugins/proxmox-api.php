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

class ProxMox extends \API\PluginApi
{
    protected static $response;
    protected static $curl;

    protected static $data;

    /**
     * Plugin class constructor.
     *
     * Registers plugin into autoloader class,
     * and optionally a task timer
     */
    public function __construct()
    {
        self::$curl = curl_init();

        // renew access ticket every hour
        parent::__construct(API_PLUGIN);

        self::$data = $this->config;

        // request access ticket
        self::ticket();
    }

    /**
     * Called to renew ProxMox ticket every hour
     *
     * @return void
     */
    public function run()
    {
        $this->METRIC_ADD("proxmox_response_miliseconds", 0);

        $this->measure_time(true);

        // request a ticket refresh
        self::ticket(true);

        $this->METRIC_STORE('proxmox_response_miliseconds', $this->measure_time());
    }

    /**
     * Retrieve a new API ticket from ProxMox service
     */
    public static function ticket($refresh = false)
    {
        if ($refresh) {
            // refresh ticket using old one as password
            $postdata = [
                'username' => self::$data->username . '@' . self::$data->realm,
                'password' => self::$response['ticket']
            ];
        } else {
            // build post fields
            $postdata = [
                'username' => self::$data->username . '@' . self::$data->realm,
                'password' => self::$data->password
            ];
        }

        curl_setopt(self::$curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(self::$curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$curl, CURLOPT_URL, "https://" . self::$data->hostname . ":" . self::$data->port . "/api2/json/access/ticket");
        curl_setopt(self::$curl, CURLOPT_POSTFIELDS, http_build_query($postdata, '', '&'));
        curl_setopt(self::$curl, CURLOPT_POST, true);
        curl_setopt(self::$curl, CURLOPT_HTTPGET, false);

        $retry = 0;
        while ($retry < 5) {
            self::$response = curl_exec(self::$curl);
            __debug("Proxmox Response: ", self::$response);
            self::$response = json_decode(self::$response, JSON_PRETTY_PRINT)['data'];

            if (self::$response) {
                break;
            }

            parent::call('CRITICAL', "Retrying connection to ProxMox... {$retry}");

            sleep(5);
            $retry++;
        }

        // failed to reach server, or auth failed
        if (!self::$response) {
            parent::call('ERROR', 'Failed to fetch data from ProxMox, check IP, port and credentials!');
            return false;
        }

        $headers = [
            'CSRFPreventionToken: ' . self::$response['CSRFPreventionToken'],
            'Cookie: PVEAuthCookie=' . self::$response['ticket'] . ''
        ];

        curl_setopt(self::$curl, CURLOPT_HTTPHEADER, $headers);

        return true;
    }

    public static function request($path, array $params = null, $method = "GET")
    {
        if (!self::$response['ticket']) {
            parent::call('ALERT', 'Tried call to ::request without a ticket!');

            // close active handle and try re-opening
            curl_close(self::$curl);
            self::$curl = curl_init();

            // try to issue a new ticket
            if (!self::ticket()) {
                return null;
            }
        }

        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }

        $api = "https://" . self::$data->hostname . ":" . self::$data->port . "/api2/json" . $path;

        switch ($method) {
            case "GET":
                curl_setopt(self::$curl, CURLOPT_URL, $api);
                //curl_setopt(self::$curl, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt(self::$curl, CURLOPT_POST, false);
                curl_setopt(self::$curl, CURLOPT_HTTPGET, true);
                break;
            case "PUT":
                //return self::$Client->put($api, $params);
                break;
            case "POST":
                //return self::$Client->post($api, $params);
                break;
            case "DELETE":
                //self::$Client->removeHeader('Content-Length');
                //return self::$Client->delete($api, $params);
                break;
            default:
                throw new \Exception('HTTP Request method not allowed.');
        }

        $response = curl_exec(self::$curl);

        if ($response === false) {
            parent::call('ERROR', 'HTTP Request failed!');
        }

        $data = json_decode($response);

        if (!$data) {
            parent::call('ERROR', "WARNING: Incorrect or no response from ProxMox host! [{$response}]");
            $data = new \stdClass();
        }

        return $data;
    }
}
