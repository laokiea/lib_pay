<?php

/** 
  * @param 微信支付
  * @date 2017/10/18
  * @author ssp
  */

namespace lib\includes\pay;

use lib\includes\pay\Pay;
use lib\includes\Init;
use lib\includes\Aes;

class Wepay extends Pay 
{
    /**
     * init
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = 'wepay';
    }

    /**
     * 发起支付接口
     * @return null
     */
    public function qrPay($type = 'recharge', $extra = '')
    {
        // 支付前檢查
        $this->checkBeforePay();

        // 获取codeUrl
        $codeUrl = $this->getCodeUrl($type, urldecode($extra));

        // 请求生成二维码
        $imgHtml = $this->getImgHtml($codeUrl);

        Init::_exit($imgHtml);
    }

    /**
     * 获取condeUrl
     * @return $codeUrl
     */
    public function getCodeUrl($type, $extra) 
    {
        require_once SDK_PATH.'wepaySdk'.DS.'lib'.DS.'WxPay.Api.php';
        require_once SDK_PATH.'wepaySdk'.DS.'example'.DS.'WxPay.NativePay.php';
        require_once SDK_PATH.'wepaySdk'.DS.'example'.DS.'log.php';

        $params  = $_POST;
        $params['pay_type'] = $type;
        $params['extra'] = $extra;
        $this->doBeforePay($params);

        $notify = new \NativePay();
        /**
         * 流程：
         * 1、调用统一下单，取得code_url，生成二维码
         * 2、用户扫描二维码，进行支付
         * 3、支付完成之后，微信服务器会通知支付成功
         * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
         */
        $input = new \WxPayUnifiedOrder();
        $input->SetBody($params['body']);
        $input->SetAttach($params['subject']);
        $input->SetOut_trade_no($this->outTradeNo);
        // 交易单位为分
        $input->SetTotal_fee(bcmul($params['total'], 100, 0));
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetNotify_url($this->lib_config['wepay_notify_url']);
        $input->SetTrade_type("NATIVE");
        $input->SetProduct_id($this->lib_config['product_id'][$type]);
        // 用户参数
        $input->SetAttach(urlencode(json_encode(['pay_type' => $type])));

        $result = $notify->GetPayUrl($input);
        $codeUrl = $result["code_url"];

        return $codeUrl;
    }

    /**
     * 支付结果异步通知，由微信post到此，数据为xml格式，content-type: text/xml
     * @param null
     * 订单状态: TRADE_FINISHED 不做任何处理。支付宝会在三个月后自动通知到该接口，订单已经结束，所以针对目前的场景不做处理。
     */
    public function notifyUrl()
    {
        require_once SDK_PATH.'wepaySdk'.DS.'lib'.DS.'WxPay.Api.php';
        require_once SDK_PATH.'wepaySdk'.DS.'lib'.DS.'WxPay.Notify.php';
        require_once SDK_PATH.'wepaySdk'.DS.'example'.DS.'log.php';
        require_once SDK_PATH.'wepaySdk'.DS.'lib'.DS.'WxPay.Data.php';     

        $notify = new \WxPayNotify();
        //获取通知的数据
        $xml = file_get_contents('php://input');

        $result = \WxPayResults::Init($xml);

        $outTradeNo = $result['out_trade_no'];

        // 检查业务是否已经处理
        $tradeStatus = \C::t('forum_order')->fetch_status($outTradeNo);
        if(Init::checkNotEmpty($tradeStatus)) return;

        // 获取uid
        $this->uid = \C::t('forum_order')->fetch_uid($outTradeNo);

        // 支付结果检查
        if (!$this->checkNotifyData($result)) {
            $notify->SetReturn_code("FAIL");
            $notify->SetReturn_msg('FAIL');
            $notify->ReplyNotify(false);
            \C::t('forum_order')->updateStatus($outTradeNo, '0');
            return;
        }

        // 回传参数
        $attach = json_decode( urldecode($result['attach']), true );
        $cointype = $attach["cointype"];
        $notify->SetReturn_code("SUCCESS");
        $notify->SetReturn_msg('OK');
        $notify->ReplyNotify(false);
        
        $total = $cointype == 2 ? bcdiv($result['total_fee'], $this->lib_config['ratio']) : $result['total_fee'];
        $result['total'] = (int)bcsub( $total ,100);
        
        switch ( $attach['pay_type'] ) {
            
        }
        \C::t('forum_order')->updateStatus($outTradeNo, '1');
    }

    /**
     * 支付结果校验
     * @param $data 微信返回的支付相关信息
     * @return bool
     */
    public function checkNotifyData($data)
    {
        require_once SDK_PATH.'wepaySdk'.DS.'lib'.DS.'WxPay.Config.php';  

        if($data['result_code'] != 'SUCCESS') return false;
        if(\WxPayConfig::APPID != $data['appid'] || \WxPayConfig::MCHID != $data['mch_id']) return false;

        $total = \C::t('forum_order')->fetch_total($this->uid, $data['out_trade_no']);
        if(!Init::checkNotEmpty($total) || bcsub( bcdiv($data["total_fee"], 100), $total) != 0) return false;
        
        return true;
    }

}