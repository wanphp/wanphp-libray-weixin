<?php
/**
 * Created by PhpStorm.
 * 微信公众号
 * User: 火子 QQ：284503866.
 * Date: 2020/9/28
 * Time: 9:17
 */

namespace Wanphp\Libray\Weixin;


use Exception;
use GuzzleHttp\Client;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;
use Wanphp\Libray\Slim\RedisCacheFactory;
use Wanphp\Libray\Slim\Setting;
use Wanphp\Libray\Weixin\Traits\OfficialAccountTrait;

class WeChatBase
{
  use OfficialAccountTrait;

  protected string $appid;
  protected string $app_secret;
  public string $message_token;
  public string $message_encodingAesKey;
  protected string $access_token;
  protected string $jsapi_ticket;

  protected CacheInterface $cache;

  public string $uin_base64;
  public bool $webAuthorization = false;
  public static int $OK = 0;

  private Client $client;
  private array $headers;
  private WXBizMsgCrypt $bizMsgCrypt;

  public function __construct(Setting $setting, RedisCacheFactory $cacheFactory)
  {
    $options = $setting->get('wechat.base');
    $this->message_token = $options['token'] ?? '';
    $this->appid = $options['appid'] ?? '';
    $this->message_encodingAesKey = $options['encodingAesKey'] ?? '';
    $this->app_secret = $options['appsecret'] ?? '';
    $this->uin_base64 = $options['uin_base64'] ?? '';
    $this->webAuthorization = $options['webAuthorization'] ?? true;

    $this->cache = new Psr16Cache($cacheFactory->create($options['database'] ?? 0, $options['prefix'] ?? 'wxBase'));
    $this->client = new Client(['base_uri' => 'https://api.weixin.qq.com/cgi-bin/']);
    $this->headers = ['Accept' => 'application/json'];
    $this->bizMsgCrypt = new WXBizMsgCrypt($this->message_token, $this->message_encodingAesKey, $this->appid);
  }

  /**
   * 通用auth验证方法，保存到缓存库
   * @return string
   * @throws Exception
   */
  public function checkAuth(): string
  {
    if (!empty($this->access_token)) return $this->access_token;
    //数据库取缓存
    $access_token = $this->cache->get($this->appid . '_weixin_access_token');
    if (!empty($access_token)) {
      $this->access_token = $access_token;
      return $access_token;
    }

    $result = $this->httpGet('token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->app_secret);
    if (isset($result['access_token'])) {
      $this->cache->set($this->appid . '_weixin_access_token', $result['access_token'], $result['expires_in']);
      $this->access_token = $result['access_token'];
      return $result['access_token'];
    }

    return '';
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

}
