<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Date: 2020/11/5
 * Time: 9:44
 */

namespace Wanphp\Libray\Weixin;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

trait HttpTrait
{
  /**
   * @param Client $client
   * @param string $method
   * @param string $uri
   * @param array $options
   * @return array|mixed
   * @throws \Exception
   */
  private function request(Client $client, string $method, $uri = '', array $options = [])
  {
    try {
      $resp = $client->request($method, $uri, $options);
      $body = $resp->getBody()->getContents();
      if ($resp->getStatusCode() == 200) {
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
          if (isset($json['errcode']) && $json['errcode'] != 0) {
            throw new \Exception($json['errcode'] . ' - ' . $json['errmsg'], 400);
          } else {
            return $json;
          }
        } else {
          $result = $this->fromXml($body);

          if ($result) {
            // 请求失败
            if (isset($result['return_code']) && $result['return_code'] === 'FAIL') {
              throw new \Exception('FAIL - ' . $result['return_msg'], 400);
            }

            if (isset($result['result_code']) && $result['result_code'] === 'FAIL') {
              throw new \Exception($result['err_code'] . ' - ' . $result['err_code_des'], 400);
            }
            return $result;
          } else {
            return ['content_type' => $resp->getHeaderLine('Content-Type'), 'body' => $body];
          }
        }
      } else {
        throw new \Exception($resp->getReasonPhrase(), $resp->getStatusCode());
      }
    } catch (RequestException $e) {
      $message = $e->getMessage();
      if ($e->hasResponse()) {
        $message .= "\n" . $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase();
        $message .= "\n" . $e->getResponse()->getBody();
      }
      throw new \Exception($message);
    } catch (GuzzleException $e) {
      throw new \Exception($e->getMessage(), $e->getCode());
    }
  }

  public function getClientIP()
  {
    return $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
  }

  /**
   * @param $datas
   * @return string
   */
  public function toXml($datas)
  {
    $xml = "<xml>";
    foreach ($datas as $key => $val) {
      if (is_numeric($val)) {
        $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
      } else {
        $xml .= "<" . $key . "><![CDATA[" . preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/", '', $val) . "]]></" . $key . ">";
      }
    }
    $xml .= "</xml>";
    return $xml;
  }

  /**
   * 将xml转为array
   * @param $xml
   * @return mixed
   */
  public function fromXml($xml)
  {
    if (!$xml) return [];
    // 禁止引用外部xml实体
    if (\PHP_VERSION_ID < 80000) libxml_disable_entity_loader(true);
    return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
  }
}
