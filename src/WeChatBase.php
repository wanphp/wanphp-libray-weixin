<?php
/**
 * Created by PhpStorm.
 * 微信公众号
 * User: 火子 QQ：284503866.
 * Date: 2020/9/28
 * Time: 9:17
 */

namespace Wanphp\Libray\Weixin;


use DOMDocument;
use Exception;
use GuzzleHttp\Client;
use Wanphp\Libray\Slim\CacheInterface;
use Wanphp\Libray\Slim\HttpTrait;
use Wanphp\Libray\Slim\Setting;

class WeChatBase
{
  use HttpTrait;

  protected string $token;
  protected string $appid;
  protected string $app_secret;
  protected string $encodingAesKey;
  protected string $access_token;
  protected string $jsapi_ticket;
  protected array $_receive;
  protected CacheInterface $cache;
  protected array $queryParams;
  public string $uin_base64;
  public bool $webAuthorization = false;
  public static int $OK = 0;
  public static int $ValidateSignatureError = -40001;//签名验证错误
  public static int $ParseXmlError = -40002;//xml解析失败
  public static int $ComputeSignatureError = -40003;//sha加密生成签名失败
  public static int $IllegalAesKey = -40004;//encodingAesKey 非法
  public static int $ValidateAppidError = -40005;//appid 校验错误
  public static int $EncryptAESError = -40006;//aes 加密失败
  public static int $DecryptAESError = -40007;//aes 解密失败
  public static int $IllegalBuffer = -40008;//解密后得到的buffer非法

  public function __construct(Setting $setting, CacheInterface $cache)
  {
    $options = $setting->get('wechat.base');
    $this->token = $options['token'] ?? '';
    $this->appid = $options['appid'] ?? '';
    $this->encodingAesKey = $options['encodingAesKey'] ?? '';
    $this->app_secret = $options['appsecret'] ?? '';
    $this->uin_base64 = $options['uin_base64'] ?? '';
    $this->webAuthorization = $options['webAuthorization'] ?? true;

    $this->cache = $cache;
  }

  /**
   * 用SHA1算法生成安全签名
   * @param string $token 票据
   * @param string $timestamp 时间戳
   * @param string $nonce 随机字符串
   * @param string $encrypt_msg 密文消息
   */
  public function getSHA1(string $token, string $timestamp, string $nonce, string $encrypt_msg): array
  {
    try {
      $array = array($encrypt_msg, $token, $timestamp, $nonce);
      sort($array, SORT_STRING);//排序
      $str = implode($array);
      return array(self::$OK, sha1($str));
    } catch (Exception) {
      return array(self::$ComputeSignatureError, null);
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
    $text_length = strlen($text);
    //计算需要填充的位数
    $amount_to_pad = $block_size - ($text_length % $block_size);
    if ($amount_to_pad == 0) {
      $amount_to_pad = $block_size;
    }
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
    if ($pad < 1 || $pad > 32) {
      $pad = 0;
    }
    return substr($text, 0, (strlen($text) - $pad));
  }

  /**
   * 提取出xml数据包中的加密消息
   * @param string $xml_str 待提取的xml字符串
   * @return array 提取出的加密消息字符串
   */
  public function xmlExtract(string $xml_str): array
  {
    try {
      $xml = new DOMDocument();
      $xml->loadXML($xml_str);
      $array_e = $xml->getElementsByTagName('Encrypt');
      $array_a = $xml->getElementsByTagName('ToUserName');
      $encrypt = $array_e->item(0)->nodeValue;
      $username = $array_a->item(0)->nodeValue;
      return array(0, $encrypt, $username);
    } catch (Exception) {
      return array(self::$ParseXmlError, null, null);
    }
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
      $text = $random . pack("N", strlen($replyMsg)) . $replyMsg . $this->appid;
      $key = base64_decode($this->encodingAesKey . "=");
      $iv = substr($key, 0, 16);
      $text = $this->PKCS7Encode($text);
      $encrypt = openssl_encrypt($text, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_ZERO_PADDING, $iv);

      //生成安全签名
      $array = $this->getSHA1($this->token, $timeStamp, $nonce, $encrypt);
      $ret = $array[0];
      if ($ret != 0) {
        return $ret;
      }
      $signature = $array[1];

      //生成发送的xml
      $encryptMsg = $this->xmlGenerate($encrypt, $signature, $timeStamp, $nonce);
      return self::$OK;
    } catch (Exception) {
      return self::$EncryptAESError;
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
   * @param &$msg string 解密后的原文，当return返回0时有效
   *
   * @return int|string 成功0，失败返回对应的错误码
   */
  public function decryptMsg(string $msgSignature, string $timestamp, string $nonce, string $postData, string &$msg): int|string
  {
    if (strlen($this->encodingAesKey) != 43) {
      return self::$IllegalAesKey;
    }

    //提取密文
    $array = $this->xmlExtract($postData);
    $ret = $array[0];

    if ($ret != 0) {
      return $ret;
    }

    $encrypt = $array[1];

    //验证安全签名
    $array = $this->getSHA1($this->token, $timestamp, $nonce, $encrypt);
    $ret = $array[0];

    if ($ret != 0) {
      return $ret;
    }

    $signature = $array[1];
    if ($signature != $msgSignature) {
      return self::$ValidateSignatureError;
    }

    try {
      $key = base64_decode($this->encodingAesKey . "=");
      $iv = substr($key, 0, 16);
      $decrypted = openssl_decrypt($encrypt, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_ZERO_PADDING, $iv);
    } catch (Exception) {
      return self::$DecryptAESError;
    }
    try {
      //去除补位字符
      $result = $this->PKCS7Decode($decrypted);
      //去除16位随机字符串,网络字节序和AppId
      if (strlen($result) < 16) return "";

      $content = substr($result, 16, strlen($result));
      $len_list = unpack("N", substr($content, 0, 4));
      $xml_len = $len_list[1];
      $xml_content = substr($content, 4, $xml_len);
      $from_appid = substr($content, $xml_len + 4);
      if (!$this->appid) $this->appid = $from_appid;
      //如果传入的appid是空的，则认为是订阅号，使用数据中提取出来的appid
    } catch (Exception) {
      return self::$IllegalBuffer;
    }
    if ($from_appid != $this->appid) return self::$ValidateAppidError;//避免传入appid是错误的情况

    $msg = $xml_content;
    return self::$OK;
  }

  /**
   * For weixin server validation
   */
  protected function checkSignature(): bool
  {
    $signature = $this->queryParams["signature"] ?? '';
    $timestamp = $this->queryParams["timestamp"] ?? '';
    $nonce = $this->queryParams["nonce"] ?? '';

    $token = $this->token;
    $tmpArr = array($token, $timestamp, $nonce);
    sort($tmpArr, SORT_STRING);
    $tmpStr = implode($tmpArr);
    $tmpStr = sha1($tmpStr);

    if ($tmpStr == $signature) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * For weixin server validation
   * @param array $queryParams
   * @return bool|string
   */
  public function valid(array $queryParams): bool|string
  {
    $this->queryParams = $queryParams;
    $echoStr = $queryParams["echostr"] ?? '';
    if ($echoStr && $this->checkSignature()) {
      return $echoStr;
    } else {
      if ($this->checkSignature()) return true;
      else return 'no access';
    }
  }

  /**
   * 设置发送消息
   * @param string $type 消息类型 text，image，voice，video，music，news
   * @param array $msg 消息数组,查看官方说明：https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Passive_user_reply_message.html#2
   * @return array|string
   * @throws Exception
   */
  public function Message(string $type = 'text' | 'image' | 'voice' | 'video' | 'music' | 'news', array $msg = []): array|string
  {
    if ($type == 'news') $msg['ArticleCount'] = count($msg['Articles']);

    $msgData = array(
      'ToUserName' => $this->getRevFrom(),
      'FromUserName' => $this->getRevTo(),
      'MsgType' => $type,
      'CreateTime' => time()
    );

    return $this->reply(array_merge($msgData, $msg));
  }

  /**
   * 获取微信服务器发来的信息
   */
  /**
   * @return $this
   * @throws Exception
   */
  public function getRev(): static
  {
    if (!empty($this->_receive)) return $this;
    $postStr = file_get_contents("php://input");

    if (!empty($postStr)) {

      if ($this->encodingAesKey) {//安全模式
        $msg = '';
        $msg_signature = $this->queryParams['msg_signature'] ?? '';
        $timestamp = $this->queryParams['timestamp'] ?? time();
        $nonce = $this->queryParams['nonce'] ?? '';
        $errCode = $this->decryptMsg($msg_signature, $timestamp, $nonce, $postStr, $msg);
        if ($errCode == 0) {
          $postStr = $msg;
        } else {
          throw new Exception("解密出错: " . $errCode, 400);
        }
      }

      $this->_receive = $this->fromXml($postStr);
    }
    return $this;
  }

  /**
   * 获取微信服务器发来的信息
   */
  public function getRevData(): array
  {
    return $this->_receive;
  }

  /**
   * 获取消息发送者
   */
  public function getRevFrom()
  {
    return $this->_receive['FromUserName'] ?? false;
  }

  /**
   * 获取消息接受者
   */
  public function getRevTo()
  {
    return $this->_receive['ToUserName'] ?? false;
  }

  /**
   * 获取接收消息的类型
   */
  public function getRevType()
  {
    return $this->_receive['MsgType'] ?? false;
  }

  /**
   * 获取消息ID
   */
  public function getRevID()
  {
    return $this->_receive['MsgId'] ?? false;
  }

  /**
   * 获取消息发送时间
   */
  public function getRevCtime()
  {
    return $this->_receive['CreateTime'] ?? false;
  }

  /**
   * 获取接收消息内容正文
   */
  public function getRevContent()
  {
    if (isset($this->_receive['Content']))
      return $this->_receive['Content'];
    elseif (isset($this->_receive['Recognition'])) //获取语音识别文字内容，需申请开通
      return $this->_receive['Recognition'];
    else
      return false;
  }

  /**
   * 获取接收消息图片
   */
  public function getRevPic(): bool|array
  {
    if (isset($this->_receive['MediaId'])) {
      return array(
        'mediaId' => $this->_receive['MediaId'],
        'picUrl' => $this->_receive['PicUrl'],
      );
    } else
      return false;
  }

  /**
   * 获取接收消息链接
   */
  public function getRevLink(): bool|array
  {
    if (isset($this->_receive['Url'])) {
      return array(
        'url' => $this->_receive['Url'],
        'title' => $this->_receive['Title'],
        'description' => $this->_receive['Description']
      );
    } else {
      return false;
    }
  }

  /**
   * 获取接收地理位置
   */
  public function getRevGeo(): bool|array
  {
    if (isset($this->_receive['Location_X'])) {
      return array(
        'x' => $this->_receive['Location_X'],
        'y' => $this->_receive['Location_Y'],
        'scale' => $this->_receive['Scale'],
        'label' => $this->_receive['Label']
      );
    } else
      return false;
  }

  /**
   * 获取接收事件推送
   */
  public function getRevEvent(): bool|array
  {
    if (isset($this->_receive['Event'])) {
      return array(
        'event' => $this->_receive['Event'],
        'key' => $this->_receive['EventKey'] ?? '',
      );
    } else
      return false;
  }

  /**
   * 获取接收语言推送
   */
  public function getRevVoice(): bool|array
  {
    if (isset($this->_receive['MediaId'])) {
      return array(
        'mediaId' => $this->_receive['MediaId'],
        'format' => $this->_receive['Format'],
      );
    } else
      return false;
  }

  /**
   * 获取接收视频推送
   */
  public function getRevVideo(): bool|array
  {
    if (isset($this->_receive['MediaId'])) {
      return array(
        'mediaId' => $this->_receive['MediaId'],
        'thumbMediaId' => $this->_receive['ThumbMediaId']
      );
    } else
      return false;
  }

  /**
   * 获取接收TICKET
   */
  public function getRevTicket()
  {
    return $this->_receive['Ticket'] ?? false;
  }

  /**
   *
   * 回复微信服务器
   * @param array $msg 要发送的信息
   * @return string
   * @throws Exception
   */
  public function reply(array $msg): string
  {
    $xmlData = $this->toXml($msg);

    if ($this->encodingAesKey) {//安全模式
      $encryptMsg = '';
      $timestamp = $this->queryParams['timestamp'] ?? time();
      $nonce = $this->queryParams['nonce'] ?? '';
      $errCode = $this->encryptMsg($xmlData, $timestamp, $nonce, $encryptMsg);
      if ($errCode == 0) {
        $xmlData = $encryptMsg;
      } else {
        throw new Exception("加密出错: " . $errCode, 400);
      }
    }

    return $xmlData;
  }


  /**
   * 通用auth验证方法，保存到缓存库
   * @return string
   * @throws Exception
   */
  public function checkAuth(): string
  {
    //数据库取缓存
    $access_token = $this->cache->get($this->appid . '_weixin_access_token');
    if (isset($access_token)) {
      $this->access_token = $access_token;
      return $access_token;
    }

    $result = $this->httpGet('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->app_secret);
    if (isset($result['access_token'])) {
      $this->cache->set($this->appid . '_weixin_access_token', $result['access_token'], $result['expires_in']);
      $this->access_token = $result['access_token'];
      return $result['access_token'];
    }

    return '';
  }

  /**
   * 创建菜单
   * @param array $data 菜单数组数据
   * @return array
   * @throws Exception
   */
  public function createMenu(array $data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/menu/create?{ACCESS_TOKEN}', $data);
  }

  /**
   * 创建个性化菜单
   * @param array $data 菜单数组数据
   * @return array
   * @throws Exception
   */
  public function addConditional(array $data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/menu/addconditional?{ACCESS_TOKEN}', $data);
  }

  /**
   * 删除个性化菜单
   * @param array $data = array("menuid" => "208379533");
   * @return array
   * @throws Exception
   */
  public function delConditional(array $data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/menu/delconditional?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取菜单
   * @return array
   * @throws Exception
   */
  public function getMenu(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/menu/get?{ACCESS_TOKEN}');
  }

  /**
   * 删除菜单
   * @return array
   * @throws Exception
   */
  public function deleteMenu(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/menu/delete?{ACCESS_TOKEN}');
  }

  /**
   * 创建二维码ticket
   * @param string $scene_id 自定义追踪id
   * @param int $type 二维码类型，永久二维码(此时expire参数无效)
   * @param int $expire 临时二维码有效期，最大为2592000秒（30天）
   * @return array array('ticket'=>'qrcode字串','expire_seconds'=>1800)
   * @throws Exception
   */
  public function getQRCode(string $scene_id, int $type = 0, int $expire = 1800): array
  {
    switch ($type) {
      case 1:
        $data['action_name'] = 'QR_LIMIT_SCENE';
        $data['action_info'] = array('scene' => array('scene_id' => $scene_id));
        break;
      case 2:
        $data['expire_seconds'] = $expire;
        $data['action_name'] = 'QR_STR_SCENE';
        $data['action_info'] = array('scene' => array('scene_str' => $scene_id));
        break;
      case 3:
        $data['action_name'] = 'QR_LIMIT_STR_SCENE';
        $data['action_info'] = array('scene' => array('scene_str' => $scene_id));
        break;
      default:
        $data['expire_seconds'] = $expire;
        $data['action_name'] = 'QR_SCENE';
        $data['action_info'] = array('scene' => array('scene_id' => $scene_id));
    }

    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/qrcode/create?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取二维码图片
   * @param string $ticket 传入由getQRCode方法生成的ticket参数
   * @return string url 返回http地址
   */
  public function getQRUrl(string $ticket): string
  {
    return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $ticket;
  }

  /**
   * 识别二维码
   * @param string $imagePath
   * @return array
   * @throws Exception
   */
  public function identifyQRCode(string $imagePath): array
  {
    return $this->httpUpload('https://api.weixin.qq.com/cv/img/qrcode?{ACCESS_TOKEN}', $imagePath);
  }

  /**
   * 文字识别
   * 身份证:idcard,银行卡:bankcard,行驶证:driving,驾驶证:drivinglicense,车牌:platenum,营业执照:bizlicense,通用印刷体:comm,菜单识别:menu
   * @param string $type
   * @param string $imagePath
   * @return array
   * @throws Exception
   */
  public function ocr(string $type, string $imagePath): array
  {
    return $this->httpUpload('https://api.weixin.qq.com/cv/ocr/' . $type . '?{ACCESS_TOKEN}', $imagePath);
  }

  /**
   * 批量获取关注用户列表
   * @param string $next_openid
   * @return array
   * @throws Exception
   */
  public function getUserList(string $next_openid = ''): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/user/get?{ACCESS_TOKEN}&next_openid=' . $next_openid);
  }

  /**
   * 获取关注者详细信息
   * @param string $openid
   * @return array
   * @throws Exception
   */
  public function getUserInfo(string $openid): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/user/info?{ACCESS_TOKEN}&openid=' . $openid);
  }

  /**
   * 批量获取用户基本信息
   * @param array $openid
   * @return array
   * @throws Exception
   */
  public function getUserListInfo(array $openid): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/user/info/batchget?{ACCESS_TOKEN}', ['user_list' => $openid]);
  }

  /**
   * 获取公众号已创建的标签
   * @return array
   * @throws Exception
   */
  public function getTags(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/tags/get?{ACCESS_TOKEN}');
  }

  /**
   * 创建标签
   * @param string $name 标签名称
   * @return array
   * @throws Exception
   */
  public function createTag(string $name): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/tags/create?{ACCESS_TOKEN}', ['tag' => ['name' => $name]]);
  }

  /**
   * 编辑标签
   * @param int $id 标签id
   * @param string $name 标签名称
   * @return array
   * @throws Exception
   */
  public function updateTag(int $id, string $name): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/tags/update?{ACCESS_TOKEN}', ['tag' => ['id' => $id, 'name' => $name]]);
  }

  /**
   * 删除标签
   * @param int $id 标签id
   * @return array
   * @throws Exception
   */
  public function deleteTag(int $id): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/tags/delete?{ACCESS_TOKEN}', ['tag' => ['id' => $id]]);
  }

  /**
   * 批量为用户打标签
   * @param int $tagId 标签id
   * @param array $openid 用户openid数组
   * @return array
   * @throws Exception
   */
  public function membersTagging(int $tagId, array $openid): array
  {
    $data = ['openid_list' => $openid, 'tagid' => $tagId];
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging?{ACCESS_TOKEN}', $data);
  }

  /**
   * 批量为用户取消标签
   * @param $tagId
   * @param $openid
   * @return array
   * @throws Exception
   */
  public function membersUnTagging($tagId, $openid): array
  {
    $data = ['openid_list' => $openid, 'tagid' => $tagId];
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/tags/members/batchuntagging?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户身上的标签列表
   * @param $openid
   * @return array
   * @throws Exception
   */
  public function memberGetidlist($openid): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/tags/getidlist?{ACCESS_TOKEN}', ['openid' => $openid]);
  }

  /**
   * 修改用户备注
   * @param $openid
   * @param $remark
   * @return array
   * @throws Exception
   */
  public function updateUserRemark($openid, $remark): array
  {
    $data = ['openid' => $openid, 'remark' => $remark];
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/user/info/updateremark?{ACCESS_TOKEN}', $data);
  }

  /**
   * 发送客服消息
   * @param array $data 消息结构{"touser":"OPENID","msgtype":"news","news":{...}}
   * @return array
   * @throws Exception
   */
  public function sendCustomMessage(array $data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/message/custom/send?{ACCESS_TOKEN}', $data);
  }

  /**
   * 发送客服消息
   * @param array $data 消息结构{"touser":"OPENID","template_id":"模板ID","url":"模板跳转链接","data":{...}}
   * @return array
   * @throws Exception
   */
  public function sendTemplateMessage(array $data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/message/template/send?{ACCESS_TOKEN}', $data);
  }

  /**
   * 添加消息模板设置的行业信息
   * @return array
   * @throws Exception
   */
  public function getIndustry(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/template/get_industry?{ACCESS_TOKEN}');
  }

  /**
   * 添加消息模板
   * @param $id
   * @return array
   * @throws Exception
   */
  public function addTemplateMessage($id): array
  {
    $data = array('template_id_short' => $id);
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/template/api_add_template?{ACCESS_TOKEN}', $data);
  }

  /**
   * 删除消息模板
   * @param $id
   * @return array
   * @throws Exception
   */
  public function delTemplateMessage($id): array
  {
    $data = array('template_id' => $id);
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/template/del_private_template?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取消息模板
   * @return array
   * @throws Exception
   */
  public function templateMessage(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?{ACCESS_TOKEN}');
  }

  /**
   * 获取客服基本信息
   * @return array
   * @throws Exception
   */
  public function getKFList(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/customservice/getkflist?{ACCESS_TOKEN}');
  }

  /**
   * 获取在线客服基本信息
   * @return array
   * @throws Exception
   */
  public function getOnlineKFList(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/customservice/getonlinekflist?{ACCESS_TOKEN}');
  }

  /**
   * 回复多客服消息
   * @return string
   * @throws Exception
   */
  public function service(): string
  {
    $msg = array(
      'ToUserName' => $this->getRevFrom(),
      'FromUserName' => $this->getRevTo(),
      'CreateTime' => time(),
      'MsgType' => 'transfer_customer_service'
    );
    return $this->reply($msg);
  }

  /**
   * 获取未接入会话列表
   * /**
   * @return array
   * @throws Exception
   */
  public function getWaitCase(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/customservice/kfsession/getwaitcase?{ACCESS_TOKEN}');
  }

  /**
   * 获取素材列表
   * /**
   * @param array $data = ["type":素材的类型，图片（image）、视频（video）、语音 （voice）,  "offset":0表示从第一个素材 返回,  "count":取值在1到20之间]
   * @return array
   * @throws Exception
   */
  public function batchGetMaterial(array $data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/material/batchget_material?{ACCESS_TOKEN}', $data);
  }

  /**
   * 上传图文消息内的图片获取URL
   * 本接口所上传的图片不占用公众号的素材库中图片数量的100000个的限制。图片仅支持jpg/png格式，大小必须在1MB以下。
   * @param string $filePath
   * @return array
   * @throws Exception
   */
  public function uploadImage(string $filePath): array
  {
    return $this->httpUpload('https://api.weixin.qq.com/cgi-bin/media/uploadimg?{ACCESS_TOKEN}', $filePath, 'media');
  }

  /**
   * 新增临时素材
   * @param string $type 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
   * @param string $filePath 文件路径
   * @return array
   * @throws Exception
   */
  public function uploadMaterial(string $type, string $filePath): array
  {
    return $this->httpUpload('https://api.weixin.qq.com/cgi-bin/media/upload?type=' . $type . '&{ACCESS_TOKEN}', $filePath, 'media');
  }

  /**
   * 新增其他类型永久素材
   * @param string $type 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
   * @param string $filePath 文件路径
   * @param array $description {"title":视频素材的标题, "introduction":视频素材的描述}
   * @return array
   * @throws Exception
   */
  public function addMaterial(string $type, string $filePath, array $description = []): array
  {
    if ($type == 'video') {
      if ($this->checkAuth() == '') return [];
      return $this->request(new Client(), 'POST',
        'https://api.weixin.qq.com/cgi-bin/material/add_material?type=video&access_token=' . $this->access_token,
        ['multipart' => [
          [
            'name' => 'media',
            'contents' => fopen($filePath, 'r'),
            'filename' => pathinfo($filePath, PATHINFO_BASENAME)
          ],
          [
            'name' => 'description',
            'contents' => json_encode($description)
          ]
        ]]);
    }
    return $this->httpUpload('https://api.weixin.qq.com/cgi-bin/material/add_material?type=' . $type . '&{ACCESS_TOKEN}', $filePath, 'media');
  }

  /**
   * 获取永久素材,临时素材
   * @param string $media_id 媒体文件id
   * @param string $path 下载文件存放目录
   * @param bool $temporary
   * @return array
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function downloadMaterial(string $media_id, string $path, bool $temporary = true): array
  {
    if ($this->checkAuth() == '') return [];
    if ($temporary) $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $this->access_token . '&media_id=' . $media_id;
    else $url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=' . $this->access_token;

    $client = new \GuzzleHttp\Client();
    $save_path = rtrim($path, '/');
    $basename = bin2hex(random_bytes(8));
    $downloadFile = $save_path . '/temp/' . sprintf('%s.%0.8s', $basename, 'dat');
    if (!is_dir($save_path . '/temp')) mkdir($save_path . '/temp', 0755, true);
    if ($temporary) $resp = $client->request('GET', $url, ['sink' => $downloadFile]);
    else $resp = $client->request('POST', $url, ['body' => json_encode(['media_id' => $media_id]), 'sink' => $downloadFile, 'headers' => ['Accept' => 'application/json']]);

    $content_type = $resp->getHeaderLine('Content-Type');
    if (str_contains($content_type, 'application/json') || str_contains($content_type, 'text/plain')) {
      // 删除文件
      if (is_file($downloadFile)) unlink($downloadFile);
      $json = json_decode($resp->getBody()->getContents(), true);
      if (json_last_error() === JSON_ERROR_NONE) return $json;
    } else {
      $content_type = mime_content_type($downloadFile);
      if ($content_type) {
        $type = explode('/', $content_type);
        $filepath = '/' . $type[0] . date('/Ym/');
        $extension = '';
        switch ($type[1]) {
          case 'gif':
            $extension = 'gif';
            break;
          case 'jpg':
          case 'jpeg':
            $extension = 'jpg';
            break;
          case 'png':
            $extension = 'png';
            break;
          case 'mpeg':
            $extension = 'mp3';
            break;
        }

        if ($extension) {
          $filename = sprintf('%s.%0.8s', $basename, $extension);

          if (!is_dir($save_path . $filepath)) mkdir($save_path . $filepath, 0755, true);
          if (rename($downloadFile, $save_path . $filepath . $filename)) {
            return ['type' => $content_type, 'file' => $filepath . $filename];
          }
        }
        if (is_file($downloadFile)) unlink($downloadFile);
      }
    }
    return [];
  }

  /**
   * 获取永久素材,临时素材
   * @param string $media_id 媒体文件id
   * @param bool $temporary
   * @return array
   * @throws Exception
   */
  public function getMaterial(string $media_id, bool $temporary = true): array
  {
    if ($temporary) $url = 'https://api.weixin.qq.com/cgi-bin/media/get?{ACCESS_TOKEN}&media_id=' . $media_id;
    else $url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?{ACCESS_TOKEN}';

    if ($temporary) return $this->httpGet($url);
    else return $this->httpPost($url, ['media_id' => $media_id]);
  }

  /**
   * 删除永久素材
   * @param string $media_id
   * @return array
   * @throws Exception
   */
  public function delMaterial(string $media_id): array
  {
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/material/del_material?{ACCESS_TOKEN}', ['media_id' => $media_id]);
  }

  /**
   * 获取素材总数
   * @return array
   * @throws Exception
   */
  public function getMaterialCount(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/material/get_materialcount?{ACCESS_TOKEN}');
  }

  /**
   * 第一步：用户同意授权，获取code
   * oauth 授权跳转接口
   * @param string $callback 回调URI
   * @param string $state
   * @param string $scope
   * @return string
   */
  public function getOauthRedirect(string $callback, string $state = '', string $scope = 'snsapi_userinfo'): string
  {
    return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->appid . '&redirect_uri=' . urlencode($callback) . '&response_type=code&scope=' . $scope . '&state=' . $state . '#wechat_redirect';
  }

  /**
   * 第二步：通过code换取网页授权access_token
   * 通过code获取Access Token
   * @param string $code
   * @return array
   * @throws Exception
   */
  public function getOauthAccessToken(string $code): array
  {
    $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $this->appid . '&secret=' . $this->app_secret . '&code=' . $code . '&grant_type=authorization_code';
    return $this->httpGet($url);
  }

  /**
   * 第三步：拉取用户信息(需第一步的scope为 snsapi_userinfo)
   * 获取授权后的用户资料
   * @param string $access_token
   * @param string $openid
   * @return array {openid,nickname,sex,province,city,country,headimgurl,privilege}
   * @throws Exception
   */
  public function getOauthUserinfo(string $access_token, string $openid): array
  {
    return $this->httpGet('https://api.weixin.qq.com/sns/userinfo?access_token=' . $access_token . '&openid=' . $openid);
  }

  /**
   * 微信JS-SDK
   * @param string $url
   * @return array|null
   * @throws Exception
   */
  public function getSignPackage(string $url = ''): ?array
  {
    $jsapiTicket = $this->getJsApiTicket();
    if ($jsapiTicket) {
      // 注意 URL 一定要动态获取，不能 hardcode.
      if ($url == '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = $_SERVER['REQUEST_URI'];
        $url = "$protocol{$_SERVER['HTTP_HOST']}$url";
      }

      $timestamp = time();
      $nonceStr = $this->createNonceStr();

      // 这里参数的顺序要按照 key 值 ASCII 码升序排序
      $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

      $signature = sha1($string);

      return [
        "appId" => $this->appid,
        "nonceStr" => $nonceStr,
        "timestamp" => $timestamp,
        "signature" => $signature
      ];
    } else {
      return null;
    }
  }

  protected function createNonceStr($length = 16): string
  {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  /**
   * 获取 ticket
   * @return string
   * @throws Exception
   */
  protected function getJsApiTicket(): string
  {
    $jsapi_ticket = $this->cache->get($this->appid . '_weixin_jsapi_ticket');
    if ($jsapi_ticket) {
      $this->jsapi_ticket = $jsapi_ticket;
      return $jsapi_ticket;
    }

    $result = $this->httpGet('https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&{ACCESS_TOKEN}');
    if ($result) {
      $this->cache->set($this->appid . '_weixin_jsapi_ticket', $result['ticket'], $result['expires_in']);
      $this->jsapi_ticket = $result['ticket'];
      return $result['ticket'];
    }
    return '';
  }

  /**
   * @param $url
   * @param string $referer
   * @return array
   * @throws Exception
   */
  protected function httpGet($url, string $referer = ''): array
  {
    if (str_contains($url, '{ACCESS_TOKEN}') && $this->checkAuth()) {
      $url = str_replace('{ACCESS_TOKEN}', 'access_token=' . $this->access_token, $url);
    }

    $headers = ['Accept' => 'application/json'];
    if ($referer != '') $headers['referer'] = $referer;
    return $this->request(new Client(), 'GET', $url, ['headers' => $headers]);
  }

  /**
   * @param $url
   * @param $data
   * @return array
   * @throws Exception
   */
  protected function httpPost($url, $data): array
  {
    if (str_contains($url, '{ACCESS_TOKEN}') && $this->checkAuth()) {
      $url = str_replace('{ACCESS_TOKEN}', 'access_token=' . $this->access_token, $url);
    }

    return $this->request(new Client(), 'POST', $url, ['body' => json_encode($data, JSON_UNESCAPED_UNICODE), 'headers' => ['Accept' => 'application/json']]);
  }

  /**
   * @param $url
   * @param $filePath
   * @param string $field
   * @return array
   * @throws Exception
   */
  protected function httpUpload($url, $filePath, string $field = 'img'): array
  {
    if (str_contains($url, '{ACCESS_TOKEN}') && $this->checkAuth()) {
      $url = str_replace('{ACCESS_TOKEN}', 'access_token=' . $this->access_token, $url);
    }

    return $this->request(new Client(), 'POST', $url, ['multipart' => [
      [
        'name' => $field,
        'contents' => fopen($filePath, 'r'),
        'filename' => pathinfo($filePath, PATHINFO_BASENAME)
      ]
    ]]);
  }
}
