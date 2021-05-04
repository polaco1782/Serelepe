<?php

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
        parent::__construct(API_PLUGIN, '0 */1 * * *');

        // request access ticket
        self::ticket($this->configs);
    }

    /**
     * Called to renew ProxMox ticket every hour
     *
     * @return void
     */
    public function run()
    {
        $data = $this->configs;
        $data->password = self::$response['ticket'];

        self::ticket($data);
    }

    /**
     * Retrieve a new API ticket from ProxMox service
     */
    public static function ticket($data)
    {
        // build post fields
        $postdata = [
            'username' => $data->username.'@'.$data->realm,
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
        if(!self::$response) {
            throw new \Exception('Failed to fetch data from ProxMox, check IP, port and credentials!');
        }

        $headers = [
            'CSRFPreventionToken: '.self::$response['CSRFPreventionToken'],
            'Cookie: PVEAuthCookie='.self::$response['ticket'].''
        ];

        curl_setopt(self::$curl, CURLOPT_HTTPHEADER, $headers);

        return true;
    }

    public static function request($path, array $params = null, $method="GET")
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

        return json_decode(curl_exec(self::$curl));
    }   
}