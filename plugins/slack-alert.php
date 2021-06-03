<?php

use \API\PluginApi;

namespace Plugin;

class SlackAlert extends \API\PluginApi
{
    private $curl;

    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(ALERT_PLUGIN);

        if (!$this->config->hook_url) {
            throw new \Exception('Empty slack hook_url parameter!');
        }

        $this->curl = curl_init();

        // setup cURL parameters
        curl_setopt($this->curl, CURLOPT_URL, $this->config->hook_url);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_POST, true);

        $this->register_call(
            'ALERT',
            function ($caller, $msg) {
                $this->write_slack('*ALERT*', $caller, ...$msg);
            }
        );
        $this->register_call(
            'CRITICAL',
            function ($caller, $msg) {
                $this->write_slack('*`CRITICAL`*', $caller, ...$msg);
            }
        );
    }

    function write_slack($type, $caller, $msg)
    {
        $fmt = ['attachments' => [[
                'color' => $this->config->color,
                'pretext' => $this->config->header,
                'author_name' => $this->config->author,
                'footer' => 'Alert sent from ' . $caller,
                'text' => "--{$type}-- {$msg}"
        ]]];

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($fmt));

        $content  = curl_exec($this->curl);

        if (curl_errno($this->curl)) {
            echo 'Request Error:' . curl_error($this->curl);
            exit;
        }
    }
}