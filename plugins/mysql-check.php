<?php

use \API\PluginApi;

namespace Plugin;

class MySQL extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        if (!function_exists('mysqli_init')) {
            throw new \Exception('ERROR: You need to have MYSQLi enabled, or this plugin will not work!');
        }

        parent::__construct(CHECK_PLUGIN);
    }

    public function run()
    {
        $mysqli = mysqli_init();
        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        // try connecting to server
        if (!@$mysqli->real_connect($this->config->host, $this->config->user, $this->config->password, $this->config->database)) {
            $this->ALERT("Couln't connect to MySQL server {$this->config->host}, [" . $mysqli->error . "]");
        } else {
            // try running SQL query
            $res = $mysqli->query($this->config->query);
        
            if (!$res) {
                $this->ALERT("Failed to execute query on {$this->config->host}, [" . $mysqli->error . "]");
            } else {
                __debug("MySQL returned {$res->num_rows} rows from '{$this->config->query}' query");
                $res->free_result();
            }
        }

        $mysqli->close();
    }
}
