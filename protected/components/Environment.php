<?php
class Environment {
    const DEVELOPMENT = 100;
    const TEST = 200;
    const STAGE = 300;
    const PRODUCTION = 400;

    private $_mode = 'DEVELOPMENT';
    private $_debug;
    private $_trace_level;
    private $_config;
    private $_env_name = 'MOD_ENV';
    private $_options = array();

    private $_config_path;
    private static $_instance = null;


    public function __construct($mode=null, $options = array())
    {


        $this->_options    = $options;
        $this->_config_dir = $this->getOption('config_dir', ROOT_DIR . '/protected/config');
        $this->_env_name   = $this->getOption('env_name', $this->_env_name);

        $this->_mode = $mode ? $mode : $this->getMode();

        $this->setDebug();

        //非开发环境 从缓存中读取配置
        if (substr($this->_mode, 0, 3) != 'DEV' and isset($options['life_time'])){
            $this->_cache_dir = $this->getOption('cache_dir', ROOT_DIR . '/protected/runtime/cache');
            $this->loadConfigFromCache($options['life_time']);
        }
        else {
            $this->loadConfig();
        }

        self::setInstance($this);
    }

    public static function instance()
    {
        if(!self::$_instance){
            self::$_instance = new Environment();
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    public function set($key, $value)
    {
        if (is_array($key)){
            $config = $key;    
            $this->_config = self::mergeArray($this->_config, $config);
            return true;
        }
        elseif (is_string($key)) {
            $tree = preg_split('[\.\/]', $key);
            $config = &$this->_config;
            $last_key = array_pop($tree);
            foreach($tree as $level){
                if (isset($config[$level])){
                    if (is_array($config[$level])){
                        $config = &$config[$level];
                    }
                    else {
                        return false;
                    }
                }
                else {
                    $config[$level] = array();
                    $config = &$config[$level];
                }
            }
            $config[$last_key] = $value; 
            return true;
        }
        return false;
    }

    public function get($key, $default=null)
    {
        $tree = preg_split('/[\.\/]/', $key);
        $config = $this->_config;
        foreach($tree as $level){
            if (isset($config[$level])){
                $config = $config[$level];
            }
            else {
                return $default;
            }
        }
        return $config;
    }

    public function getOption($key, $default=null)
    {
        return isset($this->_options[$key]) ? $this->_options[$key] : $default;
    }

    public function setDebug()
    {
        $mode = $this->_mode;
        //mode serials support
        $tmp = explode('_', $mode);
        if (count($tmp) >= 2){
            $mode = $tmp[0];
        }
        switch($mode){
        case "DEV":
        case "DEVELOPMENT":
            $this->_debug = TRUE;
            $this->_trace_level = 3;
            break;
        case "TEST":
            $this->_debug = false;
            $this->_trace_level = 0;
            break;
        case "STAGE":
            $this->_debug = true;
            $this->_trace_level = 0;
            break;
        case "PROD":
        case "PRODUCTION":
            $this->_debug = false;
            $this->_trace_level = 0;
            break;
        default:
            $this->_debug = true;
            $this->_trace_level = 0;
        }
        defined('MOD_DEBUG') or define('MOD_DEBUG',$this->getDebug());
        defined('MOD_TRACE_LEVEL') or define('MOD_TRACE_LEVEL', $this->getTraceLevel());
    }

    public function loadConfig()
    {

        switch($this->_mode){
        case "DEVELOPMENT":
            $this->_config = self::mergeArray($this->_main(), $this->_development());
           // echo "loadConfig:".implode('-',$this->_config)."<br>";
            $this->_debug = TRUE;
            $this->_trace_level = 3;
            break;
        case "TEST":
            $this->_config = self::mergeArray($this->_main(), $this->_test());
            $this->_debug = false;
            $this->_trace_level = 0;
            break;
        case "STAGE":
            $this->_config = self::mergeArray($this->_main(), $this->_stage());
            $this->_debug = true;
            $this->_trace_level = 0;
            break;
        case "PRODUCTION":
            $this->_config = self::mergeArray($this->_main(), $this->_production());
            $this->_debug = false;
            $this->_trace_level = 0;
            break;
        default:
            if (strlen($this->_mode) > 0){
                //custom mode
                $name = strtolower($this->_mode) . ".conf";
                $this->_config = self::mergeArray($this->_main(), $this->_include($name));
            }
            else {
                $this->_config = $this->_main();
            }
            $this->_debug = true;
            $this->_trace_level = 0;
        }

        $this->_config = self::mergeArray($this->_config, $this->_local());
        $this->_config['params']['environment'] = defined("self::" . $this->_mode) ? constant("self::" . $this->_mode) : 0;
    }

    public function getMode()
    {
        $mode = getenv($this->_env_name);
        if (!$mode){
            $mode_file = ROOT_DIR . '/protected/config/mode.php';
            if (file_exists($mode_file)){
                $mode = file_get_contents($mode_file);
                $mode = trim($mode);
            }
        }

        $mode = strtoupper($mode);
        return $mode;
    }

    public function getDebug()
    {
        return $this->_debug;
    }

    public function getConfig($from_cache = false, $lifetime = 30)
    {
        if (is_null($this->_config)){
            if ($from_cache){
                $this->loadConfigFromCache($lifetime);
            }
            else {
                $this->loadConfig();
            }
        }
        return $this->_config;
    }

    public function loadConfigFromCache($lifetime=30)
    {
        if (!is_dir($this->_cache_dir)){
            mkdir($this->_cache_dir);
        }
        $file = $this->_cache_dir . '/config_' . strtolower($this->_mode) . '.dat';
        if (!file_exists($file) || time() - filemtime($file) > $lifetime){
            $this->loadConfig();
            file_put_contents($file, serialize($this->_config));
        }
        $content = file_get_contents($file);
        $this->_config = unserialize($content);
    }

    public function clearConfigCache()
    {
        $file = $this->_cache_dir . '/config_' . strtolower($this->_mode) . '.dat';
        unlink($file);
    }

    public function getTraceLevel()
    {
        return $this->_trace_level;
    }

    public function getModPath()
    {
        return $this->_config['params']['modPath'];
    }

    public function _main()
    {
        return $this->_include('main.conf');
    }

    public function _local()
    {
        return $this->_include('local.conf');
    }

    public function _development()
    {
        return $this->_include('development.conf');
    }

    public function _production()
    {
        return $this->_include('production.conf');
    }

    public function _test()
    {
        return $this->_include('test.conf');
    }

    public function _stage()
    {
        return $this->_include('stage.conf');
    }

    public function _include($name)
    {
        $file = $this->_config_dir . '/' . $name . '.php';
        $data = array();
        if (file_exists($file)){
            $result = include($file);
            if (is_array($result)){
                $data = $result;
            }
        }
        return $data;
    }

	public static function mergeArray($a,$b)
	{
		$args=func_get_args();
		$res=array_shift($args);
		while(!empty($args))
		{
			$next=array_shift($args);
			foreach($next as $k => $v)
			{
                $last_letter = substr($k, -1, 1);
                //强制替换
                if ($last_letter == '!'){
                    $k = substr($k, 0, -1);
                    $res[$k]=$v;
                }
                elseif ($last_letter == '?'){
                    $k = substr($k, 0, -1);
                    if (!isset($res[$k])){
                        $res[$k]=$v;
                    }
                }
                else {
                    if(is_integer($k))
                        isset($res[$k]) ? $res[]=$v : $res[$k]=$v;
                    else if(is_array($v) && isset($res[$k]) && is_array($res[$k]))
                        $res[$k]=self::mergeArray($res[$k],$v);
                    else
                        $res[$k]=$v;
                }
			}
		}
		return $res;
	}
}

?>
