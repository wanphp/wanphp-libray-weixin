<?php
/**
 * Created by PhpStorm.
 * 微信支付V2
 * User: 火子 QQ：284503866.
 * Date: 2020/10/9
 * Time: 9:43
 */

namespace Wanphp\Libray\Weixin;


use Exception;
use GuzzleHttp\Client;
use Wanphp\Libray\Slim\Setting;

class Pay
{
  use HttpTrait;

  private string $appid;//绑定支付的APPID
  private string $mchid;//商户号
  private string $appSecret;//商户支付密钥
  private string $notifyUrl;//支付回调url
  private string $sslCertPath;//商户证书路径
  private string $sslKeyPath;//证书密钥

  public function __construct(Setting $setting)
  {
    $config = $setting->get('wechat.pay-v2');
    $this->appid = $config['appid'] ?? '';
    $this->mchid = $config['mchid'] ?? '';
    $this->appSecret = $config['appSecret'] ?? '';
    $this->notifyUrl = $config['notifyUrl'] ?? '';
    $this->sslCertPath = $config['sslCertPath'] ?? '';
    $this->sslKeyPath = $config['sslKeyPath'] ?? '';
  }

  /**
   * 发放普通红包
   * @param $data = array(
   * 'send_name'=>'商户名称',
   * 're_openid'=>'用户openid',
   * 'total_amount'=>'付款金额，单位分',
   * 'total_num'=>'1',
   * 'wishing'=>'红包祝福语',
   * 'act_name'=>'活动名称',
   * 'remark'=>'备注'
   * );
   * @throws Exception
   */
  public function sendRedPack($data): array
  {
    $apiUrl = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack';
    $data = $this->getData($data);
    $data['client_ip'] = $this->getClientIP();
    $data['sign'] = $this->makeSign($data);
    $xmlData = $this->toXml($data);
    return $this->httpPost($apiUrl, $xmlData, true);
  }

  /**
   * 发放裂变红包
   * @param $data = array(
   * 'send_name'=>'商户名称',
   * 're_openid'=>'用户openid',
   * 'total_amount'=>'付款总金额，单位分',
   * 'total_num'=>'红包发放总人数',
   * 'amt_type'=>'ALL_RAND(红包金额设置方式,全部随机)',
   * 'wishing'=>'红包祝福语',
   * 'act_name'=>'活动名称',
   * 'remark'=>'备注'
   * );
   * @throws Exception
   */
  public function sendGroupRedPack($data): array
  {
    $apiUrl = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendgroupredpack';
    $data = $this->getData($data);
    $data['sign'] = $this->makeSign($data);
    $xmlData = $this->toXml($data);
    return $this->httpPost($apiUrl, $xmlData, true);
  }

  /**
   * 查询红包记录
   * @param $mch_billno '商户发放红包的商户订单号
   * @return array
   * @throws Exception
   */
  public function getRedPackInfo($mch_billno): array
  {
    $apiUrl = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/gethbinfo';
    $data = array('bill_type' => 'MCHT');
    $data['nonce_str'] = $this->getNonceStr();
    $data['mch_id'] = $this->mchid;
    $data['mch_billno'] = $mch_billno;
    $data['appid'] = $this->appid;
    $data['sign'] = $this->makeSign($data);
    $xmldata = $this->toXml($data);
    return $this->httpPost($apiUrl, $xmldata, true);
  }

  /**
   * 格式化参数格式化成url参数
   * @param $values
   * @return string
   */
  private function toUrlParams($values): string
  {
    $buff = '';
    foreach ($values as $k => $v) {
      if ($k != 'sign' && $v != '' && !is_array($v)) {
        $buff .= $k . '=' . $v . '&';
      }
    }
    return trim($buff, '&');
  }

  /**
   * 生成签名
   * @param $values
   * @return string
   */
  public function makeSign($values): string
  {
    //签名步骤一：按字典序排序参数
    ksort($values);
    $string = $this->toUrlParams($values);
    //签名步骤二：在string后加入KEY
    $string = $string . '&key=' . $this->appSecret;
    //签名步骤三：MD5加密
    $string = md5($string);
    //签名步骤四：所有字符转为大写
    return strtoupper($string);
  }

  /**
   * 统一下单签名
   * @param $nonceStr
   * @param $prepay_id
   * @param $timeStamp
   * @return array
   */
  public function makePaySign($nonceStr, $prepay_id, $timeStamp): array
  {
    $data = array(
      'appId' => $this->appid,
      'nonceStr' => $nonceStr,
      'package' => 'prepay_id=' . $prepay_id,
      'signType' => 'MD5',
      'timeStamp' => (string)$timeStamp
    );

    $data['paySign'] = $this->makeSign($data);
    return $data;
  }


  /**
   * 统一下单API
   * @param $order_no
   * @param $openid
   * @param $total_fee
   * @param string $body
   * @return array
   * @throws Exception
   */
  public function unifiedorder($order_no, $openid, $total_fee, string $body = '', $attach = '', $trade_type = 'JSAPI', $scene_info = ''): array
  {
    // 当前时间
    $time = time();
    // 生成随机字符串
    $nonceStr = $this->getNonceStr();

    // API参数
    $body = empty($body) ? $order_no : $body;
    $params = [
      'appid' => $this->appid,
      'attach' => $attach,
      'body' => $body,
      'mch_id' => $this->mchid,
      'nonce_str' => $nonceStr,
      'notify_url' => $this->notifyUrl,  // 异步通知地址"{$protocol}{$_SERVER['HTTP_HOST']}/paynotice.php"
      'out_trade_no' => $order_no,
      'spbill_create_ip' => $this->getClientIP(),
      'total_fee' => $total_fee, // 价格:单位分
      'trade_type' => $trade_type // JSAPI -JSAPI支付,NATIVE -Native支付,APP -APP支付
    ];
    if ($trade_type == 'JSAPI') $params['openid'] = $openid;
    if ($trade_type == 'MWEB') { //H5支付
      $params['scene_info'] = $scene_info; //'{"h5_info": {"type":"Wap","wap_url": "https://pay.qq.com","wap_name": "腾讯充值"}}';
    }

    // 生成签名
    $params['sign'] = $this->makeSign($params);
    $result = $this->httpPost('unifiedorder', $this->toXml($params));
    if ($trade_type == 'JSAPI') {
      // 生成 nonce_str 供前端使用
      return $this->makePaySign($nonceStr, $result['prepay_id'], $time);
    } else {
      return $result;
    }
  }

  /**
   * 微信支付订单的查询
   * @param array $data = array('transaction_id/out_trade_no'=>'微信订单号/商户订单号');
   * @return array
   * @throws Exception
   */
  public function orderFind(array $data): array
  {
    $params = [
      'appid' => $this->appid,
      'mch_id' => $this->mchid,
      'nonce_str' => $this->getNonceStr()
    ];
    if (!isset($data['transaction_id']) && !isset($data['out_trade_no'])) {
      throw new Exception('订单查询接口中，out_trade_no、transaction_id至少填一个！');
    }
    if (isset($data['transaction_id'])) $params['transaction_id'] = $data['transaction_id'];
    else $params['out_trade_no'] = $data['out_trade_no'];

    // 生成签名
    $params['sign'] = $this->makeSign($params);

    return $this->httpPost('orderquery', $this->toXml($params));
  }

  /**
   * @param $out_trade_no $out_trade_no = 商户订单号
   * @return array
   * @throws Exception
   */
  public function closeOrder($out_trade_no): array
  {
    $params = [
      'appid' => $this->appid,
      'mch_id' => $this->mchid,
      'out_trade_no' => $out_trade_no,
      'nonce_str' => $this->getNonceStr()
    ];

    // 生成签名
    $params['sign'] = $this->makeSign($params);

    return $this->httpPost('closeorder', $this->toXml($params));
  }

  /**
   * @param array $data = array('transaction_id/out_trade_no'=>'微信订单号/商户订单号','total_fee'=>'订单总额','refund_fee'=>'退款金额');
   * @return array
   * @throws Exception
   */
  public function refund(array $data): array
  {
    $params = [
      'appid' => $this->appid,
      'mch_id' => $this->mchid,
      'nonce_str' => $this->getNonceStr(),
      'out_refund_no' => $this->mchid . date('YmdHis') . rand(1000, 9999),
      'notify_url' => $this->notifyUrl  // 异步通知地址
    ];
    if (!isset($data['transaction_id']) && !isset($data['out_trade_no'])) {
      throw new Exception('退款订单接口中，out_trade_no、transaction_id至少填一个！');
    }
    if (isset($data['transaction_id'])) $params['transaction_id'] = $data['transaction_id'];
    else $params['out_trade_no'] = $data['out_trade_no'];
    $params['total_fee'] = $data['total_fee'] * 100; // 订单金额:单位分
    $params['refund_fee'] = $data['refund_fee'] * 100; //退款金额
    if (isset($data['refund_desc'])) $params['refund_desc'] = $data['refund_desc'];//(商户发起时)退款原因

    // 生成签名
    $params['sign'] = $this->makeSign($params);

    return $this->httpPost('refund', $this->toXml($params), true);
  }

  /**
   * @param array $data = array('out_refund_no/transaction_id/out_trade_no'=>'微信订单号/商户订单号');
   * @throws Exception
   */
  public function refundfind(array $data): array
  {
    $params = [
      'appid' => $this->appid,
      'mch_id' => $this->mchid,
      'nonce_str' => $this->getNonceStr()
    ];
    if (!isset($data['transaction_id']) && !isset($data['out_trade_no']) && !isset($data['out_refund_no'])) {
      throw new Exception('退款订单查询接口中，out_refund_no、out_trade_no、transaction_id至少填一个！');
    }
    if (isset($data['transaction_id'])) $params['transaction_id'] = $data['transaction_id'];
    elseif (isset($data['out_refund_no'])) $params['out_refund_no'] = $data['out_refund_no'];
    else $params['out_trade_no'] = $data['out_trade_no'];

    // 生成签名
    $params['sign'] = $this->makeSign($params);

    return $this->httpPost('refundquery', $this->toXml($params));
  }

  /**
   * @param $bill_date ,下载对账单的日期，格式：20140603
   * @param string $bill_type ,账单类型：ALL，返回当日所有订单信息，默认值
   *  SUCCESS，返回当日成功支付的订单
   *  REFUND，返回当日退款订单
   *  REVOKED, 返回当日撤销的订单
   * @return bool|mixed|string
   * @throws Exception
   */
  public function downloadBill($bill_date, string $bill_type = 'ALL')
  {
    $params = [
      'appid' => $this->appid,
      'mch_id' => $this->mchid,
      'bill_date' => $bill_date,
      'bill_type' => $bill_type,
      'nonce_str' => $this->getNonceStr()
    ];

    // 生成签名
    $params['sign'] = $this->makeSign($params);

    return $this->httpPost('downloadbill', $this->toXml($params));
  }


  public function refund_decrypt($str): bool|string
  {
    return openssl_decrypt(base64_decode($str), "AES-256-ECB", md5($this->appSecret), OPENSSL_RAW_DATA);
  }

  /**
   * 产生随机字符串，默认长32位
   * @param int $length
   * @return string
   */
  private function getNonceStr(int $length = 32)
  {
    $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  /**
   * @param string $url
   * @param string $xmlStr
   * @param bool $cert
   * @return array
   * @throws Exception
   */
  private function httpPost(string $url, string $xmlStr, bool $cert = false): array
  {
    $client = new Client(['base_uri' => 'https://api.mch.weixin.qq.com/pay/']);
    $options = [
      'body' => $xmlStr,
      'headers' => ['Accept' => 'text/xml']
    ];
    if ($cert == true) {
      $options['cert'] = $this->sslCertPath;
      $options['ssl_key'] = $this->sslKeyPath;
    }
    return $this->request($client, 'POST', $url, $options);
  }

  /**
   * @param $data
   * @return array
   */
  protected function getData($data): array
  {
    $data['nonce_str'] = $this->getNonceStr();
    $data['mch_id'] = $this->mchid;
    if (!isset($data['mch_billno'])) $data['mch_billno'] = $this->mchid . date('YmdHis') . rand(1000, 9999);
    $data['wxappid'] = $this->appid;
    return $data;
  }
}
