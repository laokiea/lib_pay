<?php

/** 
  * @param Ping++
  * @date 2018/8/30
  * @author ssp
  */

namespace lib\includes\pay;

use lib\includes\pay\Pay;
use lib\includes\Init;

class Pingpp extends Pay 
{
    public function __construct()
    {
        parent::__construct();
        $this->type = 'pingpp';
    }

    /**
     * ping++支付
     * @return charge Object json 
     */
    public function charge($type, $tid) 
    {
        $app_id = $this->lib_config["pingpp"]["app_id"];
        $api_key = $this->lib_config["pingpp"]["api_key_test"];
        $api_callcheck_private_key_path = $this->lib_config["pingpp"]["api_callcheck_private_pem_path"];

        // 支付前检查
        $this->checkBeforePay();

        // 订单处理
        $params  = $_POST;
        $params['pay_type'] = $type;
        $params['extra'] = $tid;
        $this->doBeforePay($params);

        // Ping++支付
        \Pingpp\Pingpp::setApiKey($api_key);
        \Pingpp\Pingpp::setPrivateKeyPath($api_callcheck_private_key_path);

        $outTradeNo = $this->outTradeNo;

        $chargeOptions = array(
            'order_no'  => $outTradeNo,
            'amount'    => $params["total"] * 100,//订单总金额, 人民币单位：分（如订单总金额为 1 元，此处请填 100）
            'app'       => array('id' => $app_id),
            'channel'   => $params["channel"],
            'currency'  => 'cny',
            'client_ip' => $_SERVER["REMOTE_ADDR"],
            'subject'   => $params["subject"],
            'body'      => $params["subject"],
            'extra'     => array(
                //success_url 和 cancel_url 在本地测试不要写 localhost ，写 127.0.0.1，URL 后面不要加自定义参数
            ),
        );

        $params["success_url"] && ($chargeOptions["extra"][$params["channel"] == "alipay_wap" ? "success_url" : "result_url"] = $params["success_url"]);
        // $params["cancel_url"] && ($chargeOptions["extra"]["cancel_url"] = $params["cancel_url"]);

        $ch = \Pingpp\Charge::create($chargeOptions);

        echo $ch;
    }

    /**
     * 支付成功后，ping++发送异步通知
     * @return null
     */
    public function pingppWebhooks()
    {
        $raw_data = file_get_contents("php://input");

        $payInfo = json_decode($raw_data, true);

        // 验签
        $verifyResult = $this->verifySign($raw_data);
        if( !$verifyResult || $verifyResult == -1 ) {
            $this->_interrupt("Verify Signature Faild!");
        }

        // 支付成功
        if($payInfo["type"] == "charge.succeeded") {

            // 获取uid
            $this->uid = \C::t('forum_order')->fetch_uid($this->outTradeNo);

            $extra = \C::t('forum_order')->fetch_extra($this->outTradeNo);
            preg_match("/cointype=(\d+)/i", $extra, $match);

            $cointype = $match[1];
            $payType = \C::t('forum_order')->fetch_type($this->outTradeNo);

            $payInfo["out_trade_no"] = $this->outTradeNo;

            switch ($payType) {
            }
            $this->_interrupt("success", 200, '1');
        }

        $this->_interrupt();
    }

    public function showSucessResult() {
        $return_url = $_GET["return_url"];
        $html = <<<HTM
        <head><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no"></head>
        <img style='display:block;margin:20px auto;' src="http://www.jitashelocal.org/static/image/common/success.png"/>
        <h4 style='text-align:center;'>支付成功</h4>
        <h4 style='text-align:center;'>正在跳转到详情页...</h4>
        <script>setTimeout(function(){window.location.href = '$return_url'}, 3000)</script>
        
HTM;
        echo $html;
    }
}