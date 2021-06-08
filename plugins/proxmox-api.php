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
    protected static $hostname;
    protected static $username;
    protected static $password;
    protected static $realm;
    protected static $port;
    protected static $response;
    protected static $curl;

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

        // request access ticket
        self::ticket($this->config);
    }

    /**
     * Called to renew ProxMox ticket every hour
     *
     * @return void
     */
    public function run()
    {
        $this->METRIC_ADD("proxmox_response_miliseconds", 0);

        $data = $this->config;
        $data->password = self::$response['ticket'];

        $this->measure_time(true);
        self::ticket($data);
        $this->METRIC_STORE('proxmox_response_miliseconds', $this->measure_time());
    }

    /**
     * Retrieve a new API ticket from ProxMox service
     */
    public static function ticket($data)
    {
        self::$port = $data->port;
        self::$hostname = $data->hostname;
        self::$username = $data->username;
        self::$password = $data->password;

        // build post fields
        $postdata = [
            'username' => $data->username . '@' . $data->realm,
            'password' => $data->password
        ];

        curl_setopt(self::$curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(self::$curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$curl, CURLOPT_URL, "https://{$data->hostname}:{$data->port}/api2/json/access/ticket");
        curl_setopt(self::$curl, CURLOPT_POSTFIELDS, http_build_query($postdata, '', '&'));
        curl_setopt(self::$curl, CURLOPT_POST, true);

        self::$response = curl_exec(self::$curl);
        self::$response = json_decode(self::$response, JSON_PRETTY_PRINT)['data'];

        // failed to reach server, or auth failed
        if (!self::$response) {
            throw new \Exception('Failed to fetch data from ProxMox, check IP, port and credentials!');
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
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }

        $api = "https://" . self::$hostname . ":" . self::$port . "/api2/json" . $path;

        switch ($method) {
            case "GET":
                curl_setopt(self::$curl, CURLOPT_URL, $api);
                curl_setopt(self::$curl, CURLOPT_CUSTOMREQUEST, 'GET');
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
            throw new \Exception('HTTP Request failed!');
        }

        $data = json_decode($response);

        if (!$data) {
            parent::call('ERROR', "WARNING: Incorrect or no response from ProxMox host! [{$response}]");
            $data = new \stdClass();
        }

        return $data;
    }
}
