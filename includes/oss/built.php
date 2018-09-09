<?php

// OSS图片处理

if( self::checkNotEmpty($_GET['call_func']) && self::checkNotEmpty($_GET['aid'])) {
    
    $instanceName = "lib\\includes\\oss\\Oss";

    $classFile = INCLUDE_PATH."oss".DS."Oss".$this->config['ext'];

    $methodName = strtolower($_GET['call_func']);

    if($this->callCheck($classFile, $instanceName, $methodName)) {
        $oss = new $instanceName();
        $method = new \ReflectionMethod($instanceName, $methodName);
        $args = [];
        $parameters = $method->getParameters();
        if( !empty($parameters) ) {
            $query = array_slice(explode("&", getenv('QUERY_STRING')) , 2);
            $args = array_map(function($v){
                return explode('=',$v)[1];
            }, $query); 
        }
        call_user_func_array([$oss,$methodName], $args);
        return true;
    }

}

return false;