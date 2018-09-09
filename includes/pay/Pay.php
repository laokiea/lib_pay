<?php

namespace lib\includes\pay;

use lib\includes\Init;
use lib\includes\Aes;

/** 
  * @param 支付基类
  * @date 2017/10/18
  * @author laokeia
  */

class Pay {

    /**
     * 用户id
     */
    public $uid;

    /**
     * 订单号
     */
    public $outTradeNo;

    /**
     * 类型
     */
    public $type;

    /**
     * config数组
     */
    public $lib_config;

    /**
     * port
     */
    private $port = 819;

    /**
     * 状态查询超时时间
     */
    private $time_out = 10;

    /**
     * 初始化
     */
    public function __construct()
    {
        global $_G,$lib_config;
        $this->uid = $_G['uid'];
        $this->lib_config = $lib_config;
    }

    /** 
      * 支付前对部分参数进行检查
      * cointype是网站使用的货币，现在默认是元，值为1，拨片为2
      * @return bool
      */
    public function checkBeforePay()
    {
        $cointype = $_POST["cointype"] ?? 1;

        $_POST['total'] = trim($_POST['total']);

        if(!$this->checkIsPost()) 
            Init::_exit($this->lang('ILLEGALITY'));

        if(!Init::checkNotEmpty($_POST['total']) || !is_numeric($_POST['total'])) 
            Init::_exit($this->lang('PARAM_ERROR'));

        if($cointype == 1 && $_POST["total"] < 1) 
            Init::_exit($this->lang('MIN_RMB_ERROR'));

        if($cointype == 2 && ($_POST["total"] < 10 || $_POST['total'] % 10 != 0)) 
            Init::_exit($this->lang('BOPIAN_ERROR'));

        $_POST['total']    = $cointype == 2 ? (int)bcmul($this->lib_config['ratio'], $_POST['total']) : (int)$_POST['total']; 
        $_POST['subject']  = Init::checkNotEmpty($_POST['subject']) ? trim($_POST['subject']) : $this->lang('PAY_DESCRIPTION');
        $_POST['body']     = Init::checkNotEmpty($_POST['body']) ? trim($_POST['body']) : $this->lang('PAY_DESCRIPTION');
        $_POST["cointype"] = $cointype;

        return true;
    }

    /**
     * 查询用户订单状态
     * 返回值$status 1: 支付和业务处理都成功
     *               0: 支付失败或关闭
     *               2：支付成功，业务处理失败(暂不考虑)
     *               3. 超时，直接查询接口
     * @param $orderId 订单id
     * @return $status
     */
    public function getOrderStatus($orderId)
    {
        $startSec = time();
        while(true) {  
            $status = \C::t('forum_order')->fetch_status($orderId);
            if(Init::checkNotEmpty($status)) {
                return $status;
            }
            if( time() - $startSec >= $this->time_out ) return 3;
        }
    }

    /**
     * 地址跳转
     * @param $url 地址
     */
    public function locate($url, $type='') 
    {
        if($type == 'js') {
            $html = "<script type='text/javascript'>window.location.href = '".$url."';</script>";
            echo $html;exit();
        }
        header("Location: ".$url);
    }

    /**
     * 有多层缓冲区的时候，将信息输出到浏览器
     * @param $msg 待输出的信息
     */
    public function outToPage($msg)
    {
        while(@ob_end_flush());
        echo $msg.PHP_EOL;
        flush();
    }

    /**
     * 获取lang信息
     * @param $langId 
     * @return string  
     */
    public function lang($langId)
    {
        if(Init::checkNotEmpty($this->lib_config['lang'][$langId])){
            return $this->lib_config['lang'][$langId];
        } 
        return '';
    }

    /**
     * 生成订单
     * @return string $traderNo 
     * 微信支付订单号
     */
    public function generateTradeNo($type = 'alipay') 
    {
        $count = \C::t('forum_order')->count_by_search($this->uid);

        $baseKey = $this->uid.'_'.$count."_".uniqid();
        $encrypt = in_array($type, ["wepay", "pingpp"]) ? hash('sha256',$baseKey) : $this->cipherTradeNo($baseKey,'encrypt');
        $tradeNo = date('YmdHis').substr($encrypt, 0, 18);

        return $tradeNo;
    }

    /**
     * 新建订单记录
     * @param $traderNo 订单编号
     * @param $total 订单金额
     */
    public function createNewOrder($tradeNo,$total,$type = 'recharge',$extra = '') 
    {
        $trade = [];
        $trade['orderid'] = $tradeNo;
        $trade['uid'] = $this->uid;
        $trade['price'] = $total;
        $trade['amount'] = bcmul($total, $this->lib_config['ratio']);
        $trade['submitdate'] = time();
        $trade['type'] = $type;
        $trade['extra'] = $extra;

        return \C::t('forum_order')->insert($trade, true);
    }

    /**
     * 创建一个套接字，等待连接
     * @return bool
     */
    public function createSocketForNotify()
    {
        $connections = [];
        $masterSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $res1 = socket_bind($masterSocket, $this->lib_config['ip'], $this->port);
        $res2 = socket_listen($masterSocket, 1);
        if(!$masterSocket || !$res1 || !$res2) return false;

        $connections[] = $masterSocket; 
        $writes = null;
        $except = null;

        while(true) {
            // 当前会阻塞
            socket_select($connections, $writes, $except);
            foreach ($connections as $connect) {
                if($connect === $masterSocket) {
                   $newSocket =  socket_accept($masterSocket);
                   $connections[] = $newSocket;
                } else {
                    socket_recv($connect, $data, 1024, 0);
                   //todo
                }
            }
        }
    }

    /**
     * 检查请求是否是POST
     * @return bool
     */
    public function checkIsPost()
    {
        return getenv('REQUEST_METHOD') == 'POST';
    }

    /**
     * 编码/解码订单编号
     * @return bool
     */
    public function cipherTradeNo($cipher, $type = 'encrypt')
    {
        $aes = new Aes($this->lib_config['aes_key']);
        
        return $aes->{$type}($cipher);
    }

    /**
     * 轮询订单状态
     * @param $tradeNo 订单号
     * @return bool
     */
    public function queryTradeStatus($tradeNo) 
    {
        $tradeNo = str_replace(' ', '+', $tradeNo);
        $tradeNo = $this->cipherTradeNo($tradeNo, 'decrypt');

        $status = \C::t('forum_order')->fetch_status($tradeNo);
        Init::_exit($status);
    }

    /**
     * 支付前-请求参数处理
     * @param $params 参数
     * @return bool
     */
    public function doBeforePay(&$params)
    {
        // 整理参数
        $cointype = $params["cointype"] ?? 2;
        $params["extra"] .= "_cointype=".$cointype;

        // 新建订单记录
        $outTradeNo = $this->generateTradeNo($this->type);
        $this->outTradeNo = $outTradeNo;
        $this->createNewOrder($outTradeNo,$params['total'],$params['pay_type'], $params['extra']);
    }

    /**
     * 获取返回给前端的html片段
     * @param $url 二维码包含的地址
     * @return mixed
     */
    public function getImgHtml($url) 
    {
        $codeUrl = $this->lib_config['encodeQrUrl'][$this->type];
        $url = urlencode($url);
        // Encode tradeNo
        $encodeTradeNo = $this->cipherTradeNo($this->outTradeNo);
        $imgHtml = <<<HTM
          <img alt="扫码支付" src="$codeUrl$url" class="wepay_qr"/>
          <input type='input' id='cipher' value='$encodeTradeNo' style='display:none;'/>
HTM;
        return $imgHtml;
    }

    /**
     * 支付宝获取二维码图片
     * @param $params 参数
     * @return bool
     */
    public function getQrImg($url)
    {
        require_once INCLUDE_PATH.'phpqrcode.php';
        $url = urldecode($url);
        \QRcode::png($url);
    }

    /**
     * 去掉$_POST/$_GET部分参数
     * @param $params 参数
     * @return bool
     */
    public function unsetParams(&$params)
    {
        foreach($this->lib_config['libParamsNames'] as $v) {
            if(Init::checkNotEmpty($params[$v])) unset($params[$v]);
        }
    }

    /**
     * 输出debug信息
     * @return null
     */
    public function debug($info)
    {
        $path = __DIR__.DS."debug.txt";
        file_put_contents($path, var_export($info, true), FILE_APPEND);
    }

    /**
     * 接入ping++支付
     * post参数：subject body pay_type[buy reward] channel total
     * 访问示例：/component.html?lib_type=pay&sub_type=alipay&call_func=chargePingpp&pay_type=buy&extra='
     */
    public function chargePingpp($type, $extra = "")
    {
        $app_id = $this->lib_config["pingpp"]["app_id"];
        $api_key = $this->lib_config["pingpp"]["api_key_live"];
        $api_callcheck_private_key_path = $this->lib_config["pingpp"]["api_callcheck_private_pem_path"];

        // 支付前檢查
        $this->checkBeforePay();

        // 订单处理
        $params  = $_POST;
        $params['pay_type'] = $type;
        $params['extra'] = $extra;
        $channel = $params["channel"];
        $this->doBeforePay($params);

        // Ping++支付
        \Pingpp\Pingpp::setApiKey($api_key);
        \Pingpp\Pingpp::setPrivateKeyPath($api_callcheck_private_key_path);

        $outTradeNo = $this->outTradeNo;

        $chargeOptions = array(
            'order_no'  => $outTradeNo,
            'amount'    => $params["total"] * 100,//订单总金额, 人民币单位：分（如订单总金额为 1 元，此处请填 100）
            'app'       => array('id' => $app_id),
            'channel'   => $channel,
            'currency'  => 'cny',
            'client_ip' => $_SERVER["REMOTE_ADDR"],
            'subject'   => $params["subject"],
            'body'      => $params["body"],
            'extra'     => array(),
        );

        $params["success_url"] && ($chargeOptions["extra"][$channel == "alipay_wap" ? "success_url" : "result_url"] = $params["success_url"]);
        // $params["cancel_url"] && ($chargeOptions["extra"]["cancel_url"] = $params["cancel_url"]);

        $ch = \Pingpp\Charge::create($chargeOptions);

        if(in_array($channel, ["alipay_wap", "wx_wap"])) {
             echo $ch;
        } else if(in_array($channel, ["alipay_qr", "wx_pub_qr"])) {
            $qrUrl = $this->lib_config['encodeQrUrl'][$this->type] . $ch["credential"][$channel];
            __p($qrUrl);
        }
    }

    /**
     * 验证webhooks签名
     * @return null
     */
    public function verifySign($raw_data)
    {
        $headers = getallheaders();
        $signature = $headers["x-pingplusplus-signature"] ?? NULL;
        $pubkey = file_get_contents($this->lib_config["pingpp"]["api_callcheck_public_pem_path"]);

        return openssl_verify($raw_data, base64_decode($signature), $pubkey, "sha256");
    }

    /**
     * 中断webhooks
     *@return null
     */
    public function _interrupt($msg = "faild", $code = 500, $updateStatus = '0') 
    {
        http_response_code($code);
        \C::t('forum_order')->updateStatus($this->outTradeNo, $updateStatus);
        Init::_exit($msg);
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

}