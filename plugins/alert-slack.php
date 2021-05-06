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

        $this->curl = curl_init();

        // setup cURL parameters
        curl_setopt($this->curl, CURLOPT_URL, $this->configs->hook_url);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_POST, true);

        $this->register_call(
            'ALERT', function ($caller, $msg) {
                $this->write_slack($caller, $msg); 
            }
        );
    }

    function write_slack($caller, $msg)
    {
        $fmt = ['attachments' => [[
                'color' => $this->configs->color,
                'pretext' => $this->configs->header,
                'author_name' => $this->configs->author,
                'footer' => 'Alert sent from '.$caller,
                'text' => $msg
        ]]];
    
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($fmt));

        $content  = curl_exec($this->curl);

        if(curl_errno($this->curl)){
            echo 'Request Error:' . curl_error($this->curl);exit;
        }
    }
}