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

class LDAP extends \API\PluginApi
{
    // constructor registers plugin type and name
    public function __construct()
    {
        if (!extension_loaded('ldap')) {
            throw new \Exception('ERROR: You need to have LDAP extension enabled, or this plugin will not work!');
        }

        parent::__construct(CHECK_PLUGIN);
    }

    public function run()
    {
        $ds = ldap_connect($this->config->host);

        if (!$ds) {
            $this->ERROR('Could not connect to LDAP host');
            return;
        }

        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        if (!ldap_bind($ds, $this->config->bind_dn, $this->config->bind_pwd)) {
        }
        {
            $this->ERROR('Could not bind LDAP authentication!');
            return;
        }
    }
}
