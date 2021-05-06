<?php

namespace API;

define('API_PLUGIN', 0);
define('CHECK_PLUGIN', 1);
define('LOGGING_PLUGIN', 2);
define('ALERT_PLUGIN', 3);

class Autoloader
{
    static $plugins = [];

    public static function load_plugins()
    {
        // load each plugin file
        foreach(glob("plugins/*.php") as $filename) {
            include_once $filename;
        }

        foreach(get_declared_classes() as $classname)
        {
            // only accept classes under Plugin namespace
            if(explode('\\', $classname)[0] != 'Plugin') {
                continue;
            }

            self::$plugins[$classname] = new $classname;
        }
    }

    public static function unload_plugin($plugin)
    {
        if(isset(self::$plugins[$plugin])) {
            unset(self::$plugins[$plugin]);
        }
    }

    public static function dump_plugins()
    {
        var_dump(self::$plugins);
    }

    public static function run_plugins()
    {
        foreach(self::$plugins as $p)
        {
            if($p->parse_crontab()) {
                $p->run();
            }
        }
    }
}

class PluginApi
{
    public $plugin;
    public $type;
    private $crontab;
    protected $configs;

    static $calls = [];

    // calls a dynamic registered function
    public function __call($method, $args)
    {
        $found = false;
        foreach(self::$calls as $call)
        {
            // call every registered function
            if(@$call[$method]) {
                $call[$method]($this->plugin, ...$args);
                $found = true;
            }
        }

        // don't trap debug calls
        if($method == '__debug')
            return;
        elseif (!$found)
            throw new \Exception("Method not implemented: ".$method."(), make sure plugin is loaded before call().\n");
    }

    public function __construct($type, $crontab=null)
    {
        // register plugin name
        $this->plugin = get_called_class();
        $this->type = $type;
        $this->crontab = $crontab;

        $conf = 'conf/'.explode('\\', $this->plugin)[1].'.json';

        // load existing configuration file
        if(file_exists($conf)) {
            $this->configs = json_decode(file_get_contents($conf));

            // damaged or missing content
            if(!$this->configs) {
                throw new \Exception("Empty or damaged configuration file {$conf} for plugin {$this->plugin}");
            }
        }
    }

    // register a new dynamic call
    public function register_call($call, $func)
    {
        self::$calls[][$call] = $func;
    }

    public function parse_crontab()
    {
        // ignore empty crontabs
        if($this->crontab == null) {
            return;
        }

        $time = explode(' ', date('i G j n w'));
        $crontab = explode(' ', $this->crontab);
        foreach ($crontab as $k => &$v)
        {
            $time[$k] = preg_replace('/^0+(?=\d)/', '', $time[$k]);
            $v = explode(',', $v);

            foreach ($v as &$v1)
            {
                $v1 = preg_replace(
                    ['/^\*$/', '/^\d+$/', '/^(\d+)\-(\d+)$/', '/^\*\/(\d+)$/'],
                    ['true', $time[$k] . '===\0', '(\1<=' . $time[$k] . ' and ' . $time[$k] . '<=\2)', $time[$k] . '%\1===0'],
                    $v1
                );
            }

            $v = '(' . implode(' or ', $v) . ')';
        }

        $code = eval('return ('.implode(' and ', $crontab).');');

        $this->__debug('evaluated crontab code: '.($code?'true':'false'));

        return $code;
    }
}