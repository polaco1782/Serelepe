<?php

namespace API;

define('API_PLUGIN',     (1<<0));
define('CHECK_PLUGIN',   (1<<1));
define('LOGGING_PLUGIN', (1<<2));
define('ALERT_PLUGIN',   (1<<3));
define('METRIC_PLUGIN',  (1<<4));

class Autoloader
{
    static $plugins = [];
    static $conf;

    static $processes, $signal_queue;

    public static function load_plugins()
    {
        self::$conf = json_decode(file_get_contents('conf/serelepe.json'))->Autoloader;

        if (!self::$conf) {
            throw new \Exception("Autoloader configuration section is broken or missing!");
        }

        // load each plugin file
        foreach (glob("plugins/*.php") as $filename) {
            include_once $filename;
        }

        foreach (get_declared_classes() as $class) {
            // supress: explode may return only one element. Not critical.
            @list($namespace,$classname) = explode('\\', $class);

            // only accept classes under Plugin namespace
            if ($namespace != 'Plugin') {
                continue;
            }

            // ignore disabled plugins
            if (in_array($classname, self::$conf->disabled_classes)) {
                continue;
            }

            self::$plugins[$class] = new $class();
        }
    }

    public static function unload_plugins()
    {
        foreach (self::$plugins as &$p) {
            unset($p);
        }
    }

    // checks if at least one desired class type is loaded
    public static function is_enabled($type)
    {
        foreach (self::$plugins as &$p) {
            if($p->type == $type)
                return true;
        }

        return false;
    }

    public static function dump_plugins()
    {
        var_dump(self::$plugins);
    }

    public static function run_plugins()
    {
        foreach (self::$plugins as $p) {
            // run each instance into a forked process
            if (self::$conf->fork_run) {
                $pid = pcntl_fork();
                if ($pid == 0) {
                    if ($p->parse_crontab()) {
                        $p->run();
                    }

                    exit();
                } elseif ($pid == -1) {
                    throw new \Exception("Could not fork() a new children process!");
                }
            } else {
                if ($p->parse_crontab()) {
                    $p->run();
                }
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
        foreach (self::$calls as $call) {
            // supress: call to an unknown method, falls into next exception.
            if (@$call[$method]) {
                $call[$method]($this->plugin, $args);
                $found = true;
            }
        }

        // trap, but not crash on failed calls
        if(!$found) {
            __debug("Method not implemented: " . $method . "(), make sure plugin is loaded before call().\n");
            print_r(debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        }
    }

    public function __construct($type)
    {
        // register plugin name
        $this->plugin = get_called_class();
        $this->type = $type;

        $class = explode('\\', $this->plugin)[1];
        $conf = json_decode(file_get_contents('conf/serelepe.json'))->Plugins;

        if (!$conf) {
            throw new \Exception("Plugin configuration section is broken or missing!");
        }

        // supress: section may be missing. (eg: no config needed)
        $this->config = @$conf->{$class} ?: null;
    }

    // register a new dynamic call
    public function register_call($call, $func)
    {
        self::$calls[][$call] = $func;
    }

    public function parse_crontab()
    {
        // ignore empty crontabs
        if (!isset($this->config->crontab) || $this->config->crontab == null) {
            return;
        }

        $time = explode(' ', date('i G j n w'));
        $crontab = explode(' ', trim($this->config->crontab));

        if (count($crontab) != 5) {
            throw new \Exception("Broken crontab section for {$this->plugin}!");
        }

        foreach ($crontab as $k => &$v) {
            $time[$k] = preg_replace('/^0+(?=\d)/', '', $time[$k]);
            $v = explode(',', $v);

            foreach ($v as &$v1) {
                $v1 = preg_replace(
                    ['/^\*$/', '/^\d+$/', '/^(\d+)\-(\d+)$/', '/^\*\/(\d+)$/'],
                    ['true', $time[$k] . '===\0', '(\1<=' . $time[$k] . ' and ' . $time[$k] . '<=\2)', $time[$k] . '%\1===0'],
                    $v1
                );
            }

            $v = '(' . implode(' or ', $v) . ')';
        }

        $code = eval('return (' . implode(' and ', $crontab) . ');');

        __debug('evaluated crontab code: ' . ($code ? 'true' : 'false'));

        return $code;
    }
}