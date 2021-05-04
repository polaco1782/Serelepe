<?php

use \API\PluginApi;

namespace Plugin;

function formatBytes($bytes, $precision = 2)
{
    $unit = ["B", "KB", "MB", "GB", "TB"];
    $exp = floor(log($bytes, 1024)) | 0;
    return round($bytes / (pow(1024, $exp)), $precision).$unit[$exp];
}

class Diskspace extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        parent::__construct(CHECK_PLUGIN, '0 */12 * * *');
    }
    
    public function run()
    {
        $l = Proxmox::request('/nodes');

        foreach($l->data as $ll)
        {
            $x = Proxmox::request("/nodes/{$ll->node}/lxc");
            foreach(($x->data?:[]) as $xx)
            {
                $text = $xx->name.": ".formatBytes($xx->disk)." of ".formatBytes($xx->maxdisk).": ".sprintf("%0.2f%%", ($xx->disk*100)/$xx->maxdisk);
    
                if(($xx->disk*100)/$xx->maxdisk >= 75.00) {
                    $this->ALERT("-->WARNING: $text");
                    $this->REPORT(["text" => "Warning: Disk usage exceeds 75% for ".$text]);
                }
                else {
                    $this->LOG($text);
                }
            }
        }
    
    }
}