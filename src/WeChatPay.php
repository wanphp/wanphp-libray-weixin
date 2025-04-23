<?php
/**
 * Created by PhpStorm.
 * 微信支付V3
 * User: 火子 QQ：284503866.
 * Date: 2020/9/29
 * Time: 16:40
 */

namespace Wanphp\Libray\Weixin;


use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Wanphp\Libray\Slim\Setting;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class WeChatPay
{
  private BuilderChainable $instance;
  private string $mchid;
  private string $appid;
  private string $notify_url;
  private string $apiV3Key;
  /**
   * @var array|array[]
   */
  private array $config;

  /**
   * @throws Exception
   */
  public function __construct(Setting $setting)
  {
    $config = $setting->get('wechat.pay-v3');
    if (!empty($config['merchantId']) &&
      !empty($config['merchantCertificateSerial']) &&
      !empty($config['merchantPrivateKeyFilePath']) &&
      !empty($config['platformCertificateSerial']) &&
      !empty($config['platformCertificateFilePath']) &&
      !empty($config['platformPublicKeyId']) &&
      !empty($config['platformPublicKeyFilePath'])
    ) {
      $this->mchid = $config['merchantId'];
      $this->appid = $config['appid'];
      $this->notify_url = $config['notify_url'];
      $this->apiV3Key = $config['apiV3Key'];
      $this->config = [
        'mchid' => $config['merchantId'],
        'serial' => $config['merchantCertificateSerial'],
        'privateKey' => Rsa::from($config['merchantPrivateKeyFilePath']),
        'certs' => [
          $config['platformCertificateSerial'] => Rsa::from($config['platformCertificateFilePath'], Rsa::KEY_TYPE_PUBLIC),
          $config['platformPublicKeyId'] => Rsa::from($config['platformPublicKeyFilePath'], Rsa::KEY_TYPE_PUBLIC),
        ]
      ];
      // 构造一个 APIv3 客户端实例
      $this->instance = Builder::factory($this->config);
    } else {
      throw new Exception('微信支付接口未正确配置', 500);
    }
  }

  /**
   * APP下单API
   * @param array $data
   * @return array
   * @throws Exception
   */
  public function app(array $data): array
  {
    $data['mchid'] = $data['mchid'] ?? $this->mchid;
    $data['appid'] = $data['appid'] ?? $this->appid;
    $data['notify_url'] = $data['notify_url'] ?? $this->notify_url;
    return $this->httpPost('/v3/pay/transactions/app', $data);
  }

  /**
   * JSAPI/小程序下单
   * @param array $data
   * @return array
   * @throws Exception
   */
  public function jsapi(array $data): array
  {
    $data['mchid'] = $data['mchid'] ?? $this->mchid;
    $data['appid'] = $data['appid'] ?? $this->appid;
    $data['notify_url'] = $data['notify_url'] ?? $this->notify_url;
    return $this->httpPost('/v3/pay/transactions/jsapi', $data);
  }

  /**
   * JSAPI调起支付，通过微信浏览器内置对象方法(WeixinJSBridge)调起微信支付收银台
   * @param string $prepay_id
   * @return array
   */
  public function getBrandWCPayRequest(string $prepay_id): array
  {
    $params = [
      'appId' => $this->appid,
      'timeStamp' => (string)Formatter::timestamp(),
      'nonceStr' => Formatter::nonce(),
      'package' => 'prepay_id=' . $prepay_id,
    ];
    $params += ['paySign' => Rsa::sign(Formatter::joinedByLineFeed(...array_values($params)), $this->config['privateKey']), 'signType' => 'RSA'];

    return $params;
  }

  /**
   * Native下单
   * @param array $data
   * @return array
   * @throws Exception
   */
  public function native(array $data): array
  {
    $data['mchid'] = $data['mchid'] ?? $this->mchid;
    $data['appid'] = $data['appid'] ?? $this->appid;
    $data['notify_url'] = $data['notify_url'] ?? $this->notify_url;
    return $this->httpPost('v3/pay/transactions/native', $data);
  }

  /**
   * H5下单
   * @param array $data
   * @return array
   * @throws Exception
   */
  public function h5(array $data): array
  {
    $data['mchid'] = $data['mchid'] ?? $this->mchid;
    $data['appid'] = $data['appid'] ?? $this->appid;
    $data['notify_url'] = $data['notify_url'] ?? $this->notify_url;
    return $this->httpPost('/v3/pay/transactions/h5', $data);
  }

  /**
   * 微信支付订单号查询订单
   * @param string $transaction_id
   * @return array
   * @throws Exception
   */
  public function findByTransactionsId(string $transaction_id): array
  {
    return $this->httpGet("/v3/pay/transactions/id/$transaction_id");
  }

  /**
   * 商户订单号查询订单
   * @param string $out_trade_no
   * @return array
   * @throws Exception
   */
  public function findByOutTradeNo(string $out_trade_no): array
  {
    return $this->httpGet("/v3/pay/transactions/out-trade-no/$out_trade_no");
  }

  /**
   * 关单
   * @param string $out_trade_no
   * @return array
   * @throws Exception
   */
  public function close(string $out_trade_no): array
  {
    return $this->httpPost("/v3/pay/transactions/out-trade-no/$out_trade_no/close", ['mchid' => $this->mchid]);
  }

  /**
   * 退款申请
   * @param array $data
   * @return array
   * @throws Exception
   */
  public function refunds(array $data): array
  {
    $data['notify_url'] = $data['notify_url'] ?? $this->notify_url;
    return $this->httpPost("/v3/refund/domestic/refunds", $data);
  }

  /**
   * 查询单笔退款（通过商户退款单号）
   * @param string $out_refund_no
   * @return array
   * @throws Exception
   */
  public function findRefunds(string $out_refund_no): array
  {
    return $this->httpGet("/v3/refund/domestic/refunds/$out_refund_no");
  }

  /**
   * 下载资金账单
   * @param array $query
   * @param ResponseInterface $response
   * @return ResponseInterface
   * @throws Exception
   */
  public function downloadFundFlowBill(array $query, ResponseInterface $response): ResponseInterface
  {
    $result = $this->httpGet("/v3/bill/fundflowbill", $query);
    $resp = $this->instance->chain($result['download_url'])->get();
    // 直接将文件内容输出给用户下载
    $response = $response->withHeader('Content-Type', 'text/csv')
      ->withHeader('Content-Disposition', 'attachment; filename="wechat_bill_' . $query['bill_date'] . '.csv"');
    $response->getBody()->write($resp->getBody());
    return $response;
  }

  /**
   * 下载交易账单
   * @param array $query
   * @param ResponseInterface $response
   * @return ResponseInterface
   * @throws Exception
   */
  public function downloadTradeBill(array $query, ResponseInterface $response): ResponseInterface
  {
    $result = $this->httpGet("/v3/bill/tradebill", $query);
    $resp = $this->instance->chain($result['download_url'])->get();
    // 直接将文件内容输出给用户下载
    $response = $response->withHeader('Content-Type', 'text/csv')
      ->withHeader('Content-Disposition', 'attachment; filename="wechat_bill_' . $query['bill_date'] . '.csv"');
    $response->getBody()->write($resp->getBody());
    return $response;
  }

  /**
   * @param ServerRequestInterface $request
   * @return array
   * @throws Exception
   */
  public function notify(ServerRequestInterface $request): array
  {
    $signature = $request->getHeaderLine('Wechatpay-Signature');
    $timestamp = $request->getHeaderLine('Wechatpay-Timestamp');
    $serial = $request->getHeaderLine('Wechatpay-Serial');
    $nonce = $request->getHeaderLine('Wechatpay-Nonce');
    $bodyStr = file_get_contents('php://input');

    // 检查通知时间偏移量，允许5分钟之内的偏移
    $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$timestamp);
    $verifiedStatus = Rsa::verify(
      Formatter::joinedByLineFeed($timestamp, $nonce, $bodyStr), // 构造验签名串
      $signature,
      $this->config['certs'][$serial]
    );
    if ($timeOffsetStatus && $verifiedStatus) {
      $data = $request->getParsedBody();
      // 加密文本消息解密
      $inBodyResource = AesGcm::decrypt($data['resource']['ciphertext'], $this->apiV3Key, $data['resource']['nonce'], $data['resource']['associated_data']);
      // 把解密后的文本转换为PHP Array数组
      return (array)json_decode($inBodyResource, true);
    } else {
      throw new Exception('验证失败', 500);
    }
  }

  /**
   * @param string $url
   * @param array $query
   * @return array
   * @throws Exception
   */
  private function httpGet(string $url, array $query = []): array
  {
    if (empty($query)) $resp = $this->instance->chain($url)->get();
    else $resp = $this->instance->chain($url)->get(['query' => $query]);
    if ($resp->getStatusCode() == 200) {
      $json = json_decode((string)$resp->getBody(), true);
      if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('响应数据格式错误。');
      return $json;
    } else {
      throw new Exception($resp->getReasonPhrase(), $resp->getStatusCode());
    }
  }

  /**
   * @param $url
   * @param $data
   * @return array
   * @throws Exception
   */
  private function httpPost($url, $data): array
  {
    $resp = $this->instance->chain($url)->post(['json' => $data]);
    if ($resp->getStatusCode() == 200) {
      $json = json_decode((string)$resp->getBody(), true);
      if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('响应数据格式错误。');
      return $json;
    } else {
      throw new Exception($resp->getReasonPhrase(), $resp->getStatusCode());
    }
  }

}
