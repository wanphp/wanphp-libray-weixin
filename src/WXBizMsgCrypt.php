<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Created Time: 2024/7/9 上午10:39
 */

namespace Wanphp\Libray\Weixin;

use Exception;

class WXBizMsgCrypt
{
  private string $token;
  private string $encodingAesKey;
  private string $appId;

  public function __construct($token, $encodingAesKey, $appId)
  {
    $this->token = $token;
    $this->encodingAesKey = $encodingAesKey;
    $this->appId = $appId;
  }

  public function createNonceStr(): string
  {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < 16; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  /**
   * 用SHA1算法生成安全签名
   * @param string $timestamp 时间戳
   * @param string $nonce 随机字符串
   * @param string $encrypt_msg 密文消息
   */
  public function getSHA1(string $timestamp, string $nonce, string $encrypt_msg): array
  {
    try {
      $array = [$encrypt_msg, $this->token, $timestamp, $nonce];
      sort($array, SORT_STRING);//排序
      $str = implode($array);
      return array(0, sha1($str));
    } catch (Exception) {
      return array('sha加密生成签名失败', null);
    }
  }

  /**
   * 对需要加密的明文进行填充补位
   * @param string $text 需要进行填充补位操作的明文
   * @return string 补齐明文字符串
   */
  function PKCS7Encode(string $text): string
  {
    $block_size = 32;
    //计算需要填充的位数
    $amount_to_pad = $block_size - (strlen($text) % $block_size);
    if ($amount_to_pad == 0) $amount_to_pad = $block_size;
    //获得补位所用的字符
    $pad_chr = chr($amount_to_pad);
    $tmp = str_repeat($pad_chr, $amount_to_pad);
    return $text . $tmp;
  }

  /**
   * 对解密后的明文进行补位删除
   * @param string $text decrypted 解密后的明文
   * @return string 删除填充补位后的明文
   */
  function PKCS7Decode(string $text): string
  {
    $pad = ord(substr($text, -1));
    if ($pad < 1 || $pad > 32) $pad = 0;
    return substr($text, 0, (strlen($text) - $pad));
  }

  /**
   * 提取出xml数据包中的加密消息
   * @param string $xml_str 待提取的xml字符串
   * @return array 提取出的加密消息字符串
   */
  public function xmlExtract(string $xml_str): array
  {
    if (!$xml_str) return [];
    return json_decode(json_encode(simplexml_load_string($xml_str, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
  }

  /**
   * 生成xml消息
   * @param string $encrypt 加密后的消息密文
   * @param string $signature 安全签名
   * @param string $timestamp 时间戳
   * @param string $nonce 随机字符串
   */
  public function xmlGenerate(string $encrypt, string $signature, string $timestamp, string $nonce): string
  {
    $format = "<xml>
<Encrypt><![CDATA[%s]]></Encrypt>
<MsgSignature><![CDATA[%s]]></MsgSignature>
<TimeStamp>%s</TimeStamp>
<Nonce><![CDATA[%s]]></Nonce>
</xml>";
    return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
  }

  /**
   * 将公众平台回复用户的消息加密打包.
   * <ol>
   *    <li>对要发送的消息进行AES-CBC加密</li>
   *    <li>生成安全签名</li>
   *    <li>将消息密文和安全签名打包成xml格式</li>
   * </ol>
   *
   * @param $replyMsg string 公众平台待回复用户的消息，xml格式的字符串
   * @param $timeStamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
   * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
   * @param &$encryptMsg string 加密后的可以直接回复用户的密文，包括msg_signature, timestamp, nonce, encrypt的xml格式的字符串,
   *                      当return返回0时有效
   *
   * @return int 成功0，失败返回对应的错误码
   */
  public function encryptMsg(string $replyMsg, string $timeStamp, string $nonce, string &$encryptMsg): int
  {
    //加密
    try {
      //获得16位随机字符串，填充到明文之前
      $random = $this->createNonceStr();
      $text = $random . pack("N", strlen($replyMsg)) . $replyMsg . $this->appId;
      $key = base64_decode($this->encodingAesKey . "=");
      $iv = substr($key, 0, 16);
      $text = $this->PKCS7Encode($text);
      $encrypt = openssl_encrypt($text, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_ZERO_PADDING, $iv);

      //生成安全签名
      $array = $this->getSHA1($timeStamp, $nonce, $encrypt);
      $ret = $array[0];
      if ($ret != 0) {
        return $ret;
      }
      $signature = $array[1];

      //生成发送的xml
      $encryptMsg = $this->xmlGenerate($encrypt, $signature, $timeStamp, $nonce);
      return 0;
    } catch (Exception) {
      return 'aes 加密失败';
    }
  }

  /**
   * 检验消息的真实性，并且获取解密后的明文.
   * <ol>
   *    <li>利用收到的密文生成安全签名，进行签名验证</li>
   *    <li>若验证通过，则提取xml中的加密消息</li>
   *    <li>对消息进行解密</li>
   * </ol>
   *
   * @param $msgSignature string 签名串，对应URL参数的msg_signature
   * @param $timestamp string 时间戳 对应URL参数的timestamp
   * @param $nonce string 随机串，对应URL参数的nonce
   * @param $postData string 密文，对应POST请求的数据
   * @param array $msg
   *
   * @return int|string 成功0，失败返回对应的错误码
   */
  public function decryptMsg(string $msgSignature, string $timestamp, string $nonce, string $postData, array &$msg): int|string
  {
    if (strlen($this->encodingAesKey) != 43) return 'encodingAesKey 非法';

    //提取密文
    $encrypt = $this->xmlExtract($postData)['Encrypt'] ?? '';
    if (empty($encrypt)) return 'xml解析失败';

    //验证安全签名
    $array = $this->getSHA1($timestamp, $nonce, $encrypt);
    $ret = $array[0];

    if ($ret != 0) return $ret;
    if ($array[1] != $msgSignature) return '签名验证错误';

    try {
      $key = base64_decode($this->encodingAesKey . "=");
      $iv = substr($key, 0, 16);
      $decrypted = openssl_decrypt($encrypt, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_ZERO_PADDING, $iv);
    } catch (Exception) {
      return '解密失败';
    }
    try {
      //去除补位字符
      $result = $this->PKCS7Decode($decrypted);
      //去除16位随机字符串,网络字节序和AppId
      if (strlen($result) < 16) return "";
      $content = substr($result, 16, strlen($result));
      $len_list = unpack("N", substr($content, 0, 4));
      $xml_content = substr($content, 4, $len_list[1]);
      $from_appid = substr($content, $len_list[1] + 4);
    } catch (Exception) {
      return '解密后得到的buffer非法';
    }
    if ($from_appid != $this->appId) return '校验错误';//避免传入appid是错误的情况

    $msg = $this->xmlExtract($xml_content);
    return 0;
  }
}
