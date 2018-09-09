<?php

return [
    'debug' => false,

    'lib_types' =>  ['pay','oss',],
    'sub_types'  =>  [
       'pay' => ['alipay', 'wepay', "pingpp"],
       "pay_action" => ["recharge", "buy", "reward"],
    ],

    'class_map' => [
        'lib\\includes\\init' => INCLUDE_PATH."init.php",
    ],

    'autoload_map' => [
        'lib\\includes' => INCLUDE_PATH,
    ],

    // 余额比例
    'ratio' => 0.1,

    'ext' => '.php',
    'lib_built_file' => 'built.php',

    'aes_key' => '',

    'ip' => '127.0.0.1',

    //lang
    'lang' => [
        //ali
        'ILLEGALITY' => '系统繁忙，请稍后再试',
        'PARAM_ERROR' => '支付参数错误',
        'WAIT_PAY_RESULT' => '等待支付完成，请稍后...',
        'PAY_DESCRIPTION' => '站点充值',
        'LOCATE_TO_ALIPAY' => '正在跳往支付宝...',
        //we
        'LOCATE_TO_WEPAY' => '正在请求微信支付...',
        'MIN_RMB_ERROR' => '最少一元',
        'BOPIAN_ERROR' => '最少10拨片且为10的整数倍',
    ],

    //locate
    'pay_success_url' => '',
    'pay_result_url' => '',

    // 支付宝相关
    'app_id' => '',
    'seller_id' => '', // 商户uid，一个商户可能有多个

    // wepay相关
    'wepay_notify_url' => 'http://www.example.com/wepay/notify.php', //微信要求回调地址不能有参数，所以更改成pathinfo
    'product_id' => [
        'recharge' => '001',
        'buy' => '002',
        'reward' => '003',
    ],

    'encodeQrUrl' => [
        // 'wepay' => 'http://paysdk.weixin.qq.com/example/qrcode.php?data=',
        'wepay' => "http://www.example.com/component.html?lib_type=pay&sub_type=alipay&call_func=getQrImg&url=",
        'alipay' => "http://www.example.com/component.html?lib_type=pay&sub_type=alipay&call_func=getQrImg&url=",
        'pingpp' => "http://www.example.com/component.html?lib_type=pay&sub_type=alipay&call_func=getQrImg&url=",
    ],

    'libParamsNames' => [
        'lib_type','sub_type','call_func','tradeNo','url',
    ],
    
    // oss 地址
    "oss_url" => "%s/data/attachment/forum/%s?x-oss-process=image%s",
    'default_img_url' => '',

    //pingpp
    "pingpp" => [
        "app_id" => "",
        "api_key_test" => "",
        "api_key_live" => "",
        "api_callcheck_private_pem_path" => INCLUDE_PATH."pay".DS."pingpp_callcheck_private_key.pem",
        "api_callcheck_public_pem_path" => INCLUDE_PATH."pay".DS."pingpp_callcheck_public_key.pem",
    ],
];