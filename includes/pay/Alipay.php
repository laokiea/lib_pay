<?php

/** 
  * @param 支付宝
  * @date 2017/10/18
  * @author ssp
  */

namespace lib\includes\pay;

use lib\includes\pay\Pay;
use lib\includes\Init;
use lib\includes\Aes;

class Alipay extends Pay 
{

    /**
     * init
     */
    public function __construct()
    {
        parent::__construct();
        $this->type = 'alipay';
    }

    /**
     * 发起支付接口 - 网页支付
     * @param $type 支付類型
     * @return null
     */
    public function pagePay($type = 'recharge', $extra = '')
    {
        // 支付前檢查
        $this->outToPage($this->lang("LOCATE_TO_ALIPAY"));
        $this->checkBeforePay();
    
        $params  = $_POST;
        $params['pay_type'] = $type;
        $params['extra'] = urldecode($extra);
        $this->doBeforePay($params);

        //初始化请求
        $this->initPagePay($params);
    }

    /**
     * 构造支付请求
     * @param $params 支付参数
     * @return mixed
     */
    public function initPagePay($params)
    {
        require_once SDK_PATH.'alipaySdk'.DS.'config.php';
        require_once SDK_PATH.'alipaySdk'.DS.'pagepay'.DS.'service'.DS.'AlipayTradeService.php';
        require_once SDK_PATH.'alipaySdk'.DS.'pagepay'.DS.'buildermodel'.DS.'AlipayTradePagePayContentBuilder.php';

        //构造参数
        $payRequestBuilder = new \AlipayTradePagePayContentBuilder();
        $payRequestBuilder->setBody($params['body']);
        $payRequestBuilder->setSubject($params['subject']);
        $payRequestBuilder->setTotalAmount($params['total']);
        $payRequestBuilder->setOutTradeNo($this->outTradeNo);
        $payRequestBuilder->setGoodType('0');
        // 回传参数，用于表示支付用途
        $payRequestBuilder->setPassbackParams(urlencode(json_encode(['pay_type' => $params['type']])));

        $aop = new \AlipayTradeService($config);

        $response = $aop->pagePay($payRequestBuilder,$config['return_url'],$config['notify_url']);

        //提交表单
        Init::_exit($response);
    }

    /**
     * 发起支付接口 - 扫码支付
     * @param $type 支付類型
     * @return mixed
     */
    public function qrPay($type = 'recharge', $extra = '') 
    {
        // 支付前檢查
        $this->checkBeforePay();

        // 获取qrUrl
        $qrUrl = $this->getQrUrl($type, urldecode($extra));

        // 获取html片段
        $imgHtml = $this->getImgHtml($qrUrl);

        Init::_exit($imgHtml);

    }

    /**
     * 获取支付二维码
     * @param $type 支付類型
     * @return mixed
     */
    public function getQrUrl($type, $extra)
    {
        require_once SDK_PATH.'alipaySdk'.DS.'config.php';
        require_once SDK_PATH.'alipaySdk'.DS.'pagepay'.DS.'service'.DS.'AlipayTradeService.php';
        require_once SDK_PATH.'alipaySdk'.DS.'aop'.DS.'request'.DS.'AlipayTradePrecreateRequest.php';

        $params  = $_POST;
        $params['pay_type'] = $type;
        $params['extra'] = $extra;
        $this->doBeforePay($params);

        //构造参数
        $precreateRequest = new \AlipayTradePrecreateRequest();
        $bizContent = [];
        $bizContent['body'] = $params['body'];
        $bizContent['total_amount'] = $params["total"];
        $bizContent['subject'] = $params['subject'];
        $bizContent['out_trade_no'] = $this->outTradeNo;
        $bizContent['passback_params'] = urlencode(json_encode(['pay_type' => $type, "cointype" => $cointype]));
        $bizContent = json_encode($bizContent, JSON_UNESCAPED_UNICODE);
        $precreateRequest->setBizContent($bizContent);

        $aop = new \AlipayTradeService($config);
        $result = $aop->aopclientPrecreateExecute($precreateRequest,$config['notify_url']);
        $qrUrl  = $result->alipay_trade_precreate_response->qr_code;

        return $qrUrl;
    }

    /**
     * 网页交易成功后，支付宝get到该处，返回同步返回参数。
     * 由于同步返回的不可靠性，支付结果必须以异步通知或查询接口返回为准，不能依赖同步跳转。 
     */
    public function returnUrl()
    {
        $this->outToPage($this->lang("WAIT_PAY_RESULT"));

        $params = $_GET;
        $checkResult = $this->payResultCheck($params);
        $tradeNo = $params['out_trade_no'];
        if(!$checkResult) Init::_exit();

        // 请求订单状态
        $status = $this->getOrderStatus($tradeNo);
 
        switch($status) {
            case '1': $payStatus = 'succ';break;
            case '0': $payStatus = 'faild';break;
            case '3': 
            $payResult =  $this->query();
            $payStatus = ($payResult->trade_status == 'TRADE_SUCCESS' || $payResult->trade_status == 'TRADE_FINISHED') ? 'succ&show_tip=1' : 'faild';
            break;
        }
        
        $locateUrl = $this->lib_config['pay_result_url']."&payStatus=".$payStatus;
        $this->locate($locateUrl, 'js');
    }

    /**
     * 交易查询，异步通知如果超时，调用查询结果。
     * @return null
     */
    public function query()
    {
        require_once SDK_PATH.'alipaySdk'.DS.'config.php';
        require_once SDK_PATH.'alipaySdk'.DS.'pagepay'.DS.'service'.DS.'AlipayTradeService.php';
        require_once SDK_PATH.'alipaySdk'.DS.'pagepay'.DS.'buildermodel'.DS.'AlipayTradeQueryContentBuilder.php';

        $trade_no = trim($_GET['trade_no']);

        $RequestBuilder = new \AlipayTradeQueryContentBuilder();
        $RequestBuilder->setTradeNo($trade_no);

        $aop = new \AlipayTradeService($config);
        $response = $aop->Query($RequestBuilder);
        return $response;
    }

    /**
     * 支付结果异步通知，由支付宝post到此
     * @param null
     * 订单状态: TRADE_FINISHED 不做任何处理。支付宝会在三个月后自动通知到该接口，订单已经结束，所以针对目前的场景不做处理。
     */
    public function notifyUrl()
    {
        global $_G;
        require_once SDK_PATH.'alipaySdk'.DS.'config.php';
        require_once SDK_PATH.'alipaySdk'.DS.'pagepay'.DS.'service'.DS.'AlipayTradeService.php';

        $params = $_POST;
        $this->unsetParams($params);

        $tradeStatus = $params['trade_status'];
        $tradeNo = $params['out_trade_no'];

        if(Init::checkNotEmpty($params['passback_params'])) {
            $passbackParams = json_decode( urldecode($params['passback_params']), true);
            $payType = $passbackParams['pay_type'];
            $cointype = $passbackParams['cointype'] ?? 2;
        } else {
            $extra = \C::t('forum_order')->fetch_extra($tradeNo);
            preg_match("/cointype=(\d+)/i", $extra, $match);
            $cointype = $match[1];
            $payType = \C::t('forum_order')->fetch_type($tradeNo);
        }

        // 查看业务是否已经处理
        $status = \C::t('forum_order')->fetch_status($tradeNo);
        if(Init::checkNotEmpty($status)) return;

        $alipaySevice = new \AlipayTradeService($config); 
        $alipaySevice->writeLog(var_export($params, true));
        $result = $alipaySevice->check($params);

        if($result) {  
            // 获取uid
            $this->uid = \C::t('forum_order')->fetch_uid($tradeNo);
            // 通知参数校验
            $checkResult = $this->payResultCheck($params);

            if($checkResult) {
                if($tradeStatus == "TRADE_SUCCESS") {
                    $params['total'] = $cointype == 2 ? (int)bcdiv($params['total_amount'], $this->lib_config['ratio']) : (int)$params['total_amount'];
                    switch ($payType) {
                    }
                    \C::t('forum_order')->updateStatus($tradeNo, '1');
                    return;
                }
            }
        } 
        \C::t('forum_order')->updateStatus($tradeNo, '0');
    }

    /**
     * 支付结果校验
     * @param $params 支付宝返回的支付相关信息
     * @return bool
     */
    public function payResultCheck(array &$params) 
    {
        $params['out_trade_no'] = str_replace(' ', '+', urldecode(trim($params['out_trade_no'])));
        $total = \C::t('forum_order')->fetch_total($this->uid, $params['out_trade_no']);

        if(!Init::checkNotEmpty($total) || bcsub($params["total_amount"],$total) != 0) return false;
        if($params['app_id'] != $this->lib_config['app_id']) return false;
        if($params['seller_id'] != $this->lib_config['seller_id']) return false;

        return true;
    }

    /**
     * 退款
     */
    public function refund(){}

    /**
     * 退款查询
     */
    public function refundQuery(){}

    /**
     * 交易关闭
     */
    public function close(){}

}