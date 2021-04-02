<?php
/**
 * Created by PhpStorm.
 * 微信支付V3
 * User: 火子 QQ：284503866.
 * Date: 2020/9/29
 * Time: 16:40
 */

namespace Wanphp\Libray\Weixin;


use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use WechatPay\GuzzleMiddleware\Util\PemUtil;
use WechatPay\GuzzleMiddleware\WechatPayMiddleware;

class WeChatPay
{
  use HttpTrait;

  private $client;
  private $mchid;

  public function __construct($config)
  {
    if ($config['merchantId'] != '' && $config['merchantSerialNumber'] != '' && $config['pathToPrivateKey'] != '' && $config['pathToCertificate'] != '') {
      $merchantPrivateKey = PemUtil::loadPrivateKey($config['pathToPrivateKey']); // 商户私钥
      $wechatpayCertificate = PemUtil::loadCertificate($config['pathToCertificate']); // 微信支付平台证书

      // 构造一个WechatPayMiddleware
      $wechatpayMiddleware = WechatPayMiddleware::builder()
        ->withMerchant($config['merchantId'], $config['merchantSerialNumber'], $merchantPrivateKey)// 传入商户号，商户API证书序列号，商户私钥
        ->withWechatPay([$wechatpayCertificate])// 可传入多个微信支付平台证书，参数类型为array
        ->build();

      // 将WechatPayMiddleware添加到Guzzle的HandlerStack中
      $stack = HandlerStack::create();
      $stack->push($wechatpayMiddleware, 'wechatpay');
      $this->client = new Client(['base_uri' => 'https://api.mch.weixin.qq.com/v3/pay/transactions/', 'handler' => $stack]);
      $this->mchid = $config['merchantId'];
    }
  }

  /**
   * APP下单API
   * @param array $data
   * @return array
   * @throws \Exception
   */
  public function app(array $data): array
  {
    return $this->httpPost('app', $data);
  }

  /**
   * JSAPI/小程序下单
   * @param array $data
   * @return array
   * @throws \Exception
   */
  public function jsapi(array $data): array
  {
    return $this->httpPost('jsapi', $data);
  }

  /**
   * Native下单
   * @param array $data
   * @return array
   * @throws \Exception
   */
  public function native(array $data): array
  {
    return $this->httpPost('native', $data);
  }

  /**
   * H5下单
   * @param array $data
   * @return array
   * @throws \Exception
   */
  public function h5(array $data): array
  {
    return $this->httpPost('h5', $data);
  }

  /**
   * 微信支付订单号查询订单
   * @param string $id
   * @return array
   * @throws \Exception
   */
  public function findById(string $id): array
  {
    return $this->httpGet('id/' . $id . '?mchid=' . $this->mchid);
  }

  /**
   * 商户订单号查询订单
   * @param string $out_trade_no
   * @return array
   * @throws \Exception
   */
  public function findByOutTradeNo(string $out_trade_no): array
  {
    return $this->httpGet('out-trade-no/' . $out_trade_no . '?mchid=' . $this->mchid);
  }

  /**
   * 关单
   * @param string $out_trade_no
   * @return array
   * @throws \Exception
   */
  public function close(string $out_trade_no): array
  {
    return $this->httpPost("out-trade-no/{$out_trade_no}/close", ['mchid' => $this->mchid]);
  }

  /**
   * @param $url
   * @return mixed
   * @throws \Exception
   */
  private function httpGet($url)
  {
    return $this->request($this->client, 'GET', $url, ['headers' => ['Accept' => 'application/json']]);
  }

  /**
   * @param $url
   * @param $data
   * @return mixed
   * @throws \Exception
   */
  private function httpPost($url, $data)
  {
    return $this->request($this->client, 'POST', $url, ['json' => $data, 'headers' => ['Accept' => 'application/json']]);
  }

}
