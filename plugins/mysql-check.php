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

class MySQL extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        if (!function_exists('mysqli_init')) {
            throw new \Exception('ERROR: You need to have MYSQLi enabled, or this plugin will not work!');
        }

        $this->METRIC_ADD('mysql_connect_miliseconds', 0);
        $this->METRIC_ADD('mysql_query_miliseconds', 0);
        $this->METRIC_ADD('mysql_failed_queries', 0);
        $this->METRIC_ADD('mysql_failed_connects', 0);

        parent::__construct(CHECK_PLUGIN);
    }

    public function run()
    {
        $mysqli = mysqli_init();
        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        $this->measure_time(true);

        // try connecting to server
        if (!@$mysqli->real_connect($this->config->host, $this->config->user, $this->config->password, $this->config->database)) {
            $this->ALERT("Couln't connect to MySQL server {$this->config->host}, [" . $mysqli->error . "]");
            $this->METRIC_INC('mysql_failed_connects');
        } else {

            $this->METRIC_STORE('mysql_connect_miliseconds', $this->measure_time());

            // try running SQL query
            $res = $mysqli->query($this->config->query);
        
            if (!$res) {
                $this->ALERT("Failed to execute query on {$this->config->host}, [" . $mysqli->error . "]");
                $this->METRIC_INC('mysql_failed_queries');
            } else {
                __debug("MySQL returned {$res->num_rows} rows from '{$this->config->query}' query");
                $res->free_result();
            }

            $this->METRIC_STORE('mysql_query_miliseconds', $this->measure_time());
        }

        $mysqli->close();
    }
}
