<?php

/** 
  * @param simple initial
  * @date 2017/10/19
  * @author ssp
  */

namespace lib\includes;

class Init 
{

    public $config;

    public $lib_type;

    /**
     * initial lib
     *
     * @param  array  $config
     */
    public function __init(array $config) 
    {
        $this->config   = $config;
        $this->lib_type = $_GET['lib_type'];

        //session
        session_start();

        //ob
        @ob_start("ob_gzhandler") && ob_start("ob_gzhandler");

        // simple autoload
        $this->register(true);

        // init check and built
        if ( !$this->initCheck() ) $this->error();

        //built
        if ( !$this->built() ) $this->error();
    }

    /**
     * Registers this instance as an autoloader.
     *
     */
    public function register($prepend = false) 
    {
        spl_autoload_register( array($this, 'simple_autoload') , true, $prepend);
    }

    /**
     * register function for autoloading
     *
     */
    public function simple_autoload($class) 
    {
        $file = $this->findFile($class);
        if($file && $this->checkFile($file)) require_once $file;
    }   

    /**
     * find the path to the file
     *
     */
    public function findFile($class) 
    {
        $subPath = $class;
        $suffix  = '';

        if( isset($this->config['class_map'][$class]) ) {
            $file = $this->config['class_map'][$class];
            return $file;
        }

        while( false !== $lastPos = strrpos($subPath, '\\') ) {
            $suffix = substr($subPath, $lastPos).$suffix;
            $suffix = str_replace('\\', DS, $suffix);
            $subPath = substr($subPath, 0, $lastPos);
            if ( isset($this->config['autoload_map'][$subPath]) ) {
                $file = $this->config['autoload_map'][$subPath].$suffix.$this->config['ext'];
                return $file;
            }
        }
    }

    /**
     * simple checks before initial
     *
     */
    public function initCheck() 
    {
        if(!$this->lib_type || !in_array($this->lib_type, $this->config['lib_types'])) return false;

        return true;
    }

    /**
     * require lib's built file
     *
     */
    public function built() 
    {
        $builtFile = INCLUDE_PATH.$this->lib_type.DS.$this->config['lib_built_file'];
        if( !$this->checkFile($builtFile) ) return false;

        $result = require_once $builtFile;
        if(!$result) $this->error();
        return true;
    }

    /**
     * check file exists and can be read
     *
     */
    public function checkFile($file)
    {
        return file_exists($file) && is_readable($file);
    }

    /**
     * check lib core file is callable
     *
     */
    public function callCheck($classFile, $className, $methodName) 
    {
        if($this->checkFile($classFile)) {
            require_once $classFile;
            if( method_exists($className, $methodName) && is_callable(array($className, $methodName)) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * check var not empty
     */
    public static function checkNotEmpty($value, $except = '0') 
    {
        if($value === $except) return true;
        if(isset($value) && !empty($value)) return true;
        return false;
    }

    /**
     * output error msg or locate to 404 page when error appear
     *
     */
    public function error($error = null) 
    {
        if(isset($error) && is_string($error)) {
            //more info
            $error = <<<ERROR
                <h4>$error</h4>
ERROR;
        }
        self::_exit($error);
    }

    /**
     * exit process
     *
     */
    public static function _exit($msg) 
    {
        if(!isset($msg)) { 
            header("Location: /plugin.php?id=jitashetools:tools&mod=error");return;
        }
        echo $msg.PHP_EOL;
        @ob_end_flush();
        exit();
    }

}