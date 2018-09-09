<?php

define('DS', DIRECTORY_SEPARATOR);

define("LIB_PATH", __DIR__.DS);

define('SDK_PATH', LIB_PATH.DS."sdk".DS);

define('INCLUDE_PATH', LIB_PATH.DS."includes".DS);

//外部环境初始化
require_once dirname(__DIR__).DS."source".DS."class".DS."class_core.php";
C::app()->init();$_G["uid"] = 1;

//组件化功能入口
$lib_config = require_once "lib_config.php";

// 开启调试
if($lib_config['debug']) {
    ini_set('display_errors','On');
    ini_set('display_startup_errors','On');
    error_reporting(E_ALL);
}

require_once INCLUDE_PATH."Init.php";

$init = new lib\includes\Init();

$init->__init($lib_config);


