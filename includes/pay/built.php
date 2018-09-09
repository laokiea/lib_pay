<?php

// 支付功能转发
global $_G;

if(self::checkNotEmpty($_GET['sub_type']) && self::checkNotEmpty($_GET['call_func']) && (self::checkNotEmpty($_G['uid']) || in_array($_GET["call_func"], ["notifyUrl", "pingppWebhooks"]))) {

    $payType = strtolower($_GET['sub_type']);

    $methodName = strtolower($_GET['call_func']);

    $className = ucfirst($payType);

    $instanceName = "lib\\includes\\pay\\".$className;

    $classFile = INCLUDE_PATH.$this->lib_type.DS.$className.$this->config['ext'];

    // 初始化检查
    if( in_array($payType, $this->config["sub_types"][$this->lib_type]) ) {
        if ( $this->callCheck($classFile, $instanceName, $methodName) ) {
            //分发
            $pay = new $instanceName();
            $method = new \ReflectionMethod($instanceName, $methodName);
            $args = [];
            $parameters = $method->getParameters();
            if( !empty($parameters) ) {
                $query = array_slice(explode("&", getenv('QUERY_STRING')) , 3);
                $args = array_map(function($v){
                    return explode('=',$v)[1];
                }, $query); 
            }
            call_user_func_array([$pay,$methodName], $args);
            return true;
        }
    }
}

return false;
