<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Created Time: 2024/7/8 上午11:09
 */

namespace Wanphp\Libray\Weixin;

use Exception;
use GuzzleHttp\Client;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;
use Wanphp\Libray\Slim\RedisCacheFactory;
use Wanphp\Libray\Slim\Setting;
use Wanphp\Libray\Weixin\Traits\OfficialAccountTrait;

class ThirdPartyPlatforms
{
  use OfficialAccountTrait;

  public string $APPID;
  public string $AppSecret;
  public string $message_token;
  public string $message_encodingAesKey;
  protected string $appid;
  protected string $access_token;
  protected string $jsapi_ticket;

  protected CacheInterface $cache;
  private Client $client;
  private array $headers;
  private WXBizMsgCrypt $bizMsgCrypt;

  public function __construct(Setting $setting, RedisCacheFactory $cacheFactory)
  {
    $options = $setting->get('wxThirdPartyPlatforms');
    $this->APPID = $options['APPID'] ?? '';
    $this->AppSecret = $options['AppSecret'] ?? '';
    $this->message_token = $options['message_token'] ?? '';
    $this->message_encodingAesKey = $options['message_encodingAesKey'] ?? '';

    $this->cache = new Psr16Cache($cacheFactory->create($options['database'] ?? 0, $options['prefix'] ?? 'wxThird'));
    $this->client = new Client(['base_uri' => 'https://api.weixin.qq.com/cgi-bin/']);
    $this->headers = ['Accept' => 'application/json'];
    $this->bizMsgCrypt = new WXBizMsgCrypt($this->message_token, $this->message_encodingAesKey, $this->APPID);
  }

  /**
   * 接收推送消息
   * @throws Exception
   */
  public function receivingMessages($queryParams): array
  {
    $postXml = file_get_contents("php://input");
    $message = [];
    $msg_signature = $queryParams['msg_signature'] ?? '';
    $timestamp = $queryParams['timestamp'] ?? time();
    $nonce = $queryParams['nonce'] ?? '';
    $errCode = $this->bizMsgCrypt->decryptMsg($msg_signature, $timestamp, $nonce, $postXml, $message);
    if ($errCode == 0) {
      switch ($message['InfoType']) {
        case 'component_verify_ticket':
          // 存放验证票据
          $this->cache->set('component_verify_ticket', $message['ComponentVerifyTicket'], 3600 * 12);
          break;
        case 'authorized':
          // 授权成功
          $this->getAuthorizerRefreshToken($message['AuthorizationCode']);
          $this->cache->set($this->APPID . '_pre_auth_code', $message['PreAuthCode'], 600);
          break;
        case 'updateauthorized':
          // 更新授权
          $this->getAuthorizerRefreshToken($message['AuthorizationCode']);
          $this->cache->set($this->APPID . '_pre_auth_code', $message['PreAuthCode'], 600);
          break;
        case 'unauthorized':
          // 取消授权，删除本地记录
          $this->cache->deleteMultiple([
            $message['AuthorizerAppid'] . '_authorizer_access_token',
            $message['AuthorizerAppid'] . '_authorizer_refresh_token',
            $message['AuthorizerAppid'] . '_func_info'
          ]);
          break;
        default:

      }
      return $message;
    } else {
      return [];
    }
  }

  /**
   * 第三方平台接口的调用凭据
   * @throws Exception
   */
  public function getComponentAccessToken(): string
  {
    if (!empty($this->access_token)) return $this->access_token;
    // 取缓存
    $cacheKey = $this->APPID . '_component_access_token';
    $component_access_token = $this->cache->get($cacheKey);
    if (!empty($component_access_token)) {
      $this->access_token = $component_access_token;
      return $component_access_token;
    }

    $result = $this->request($this->client, 'POST', 'component/api_component_token', [
      'json' => [
        'component_appid' => $this->APPID,
        'component_appsecret' => $this->AppSecret,
        'component_verify_ticket' => $this->cache->get('component_verify_ticket', '')
      ],
      'headers' => $this->headers
    ]);
    if (isset($result['component_access_token'])) {
      $this->cache->set($cacheKey, $result['component_access_token'], $result['expires_in']);
      $this->access_token = $result['component_access_token'];
      return $result['component_access_token'];
    }
    return '';
  }

  /**
   * 获取预授权码
   * @throws Exception
   */
  private function getPreAuthCode(): string
  {
    // 取缓存
    $cacheKey = $this->APPID . '_pre_auth_code';
    $pre_auth_code = $this->cache->get($cacheKey);
    if (!empty($pre_auth_code)) return $pre_auth_code;

    $result = $this->request($this->client, 'POST', 'component/api_create_preauthcode?component_access_token=' . $this->getComponentAccessToken(), [
      'json' => ['component_appid' => $this->APPID],
      'headers' => $this->headers
    ]);
    if (isset($result['pre_auth_code'])) {
      $this->cache->set($cacheKey, $result['pre_auth_code'], $result['expires_in']);
      return $result['pre_auth_code'];
    }
    return '';
  }

  /**
   * 第一步：管理同意授权，获取auth_code
   * https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/Before_Develop/Authorization_Process_Technical_Description.html
   *
   * @param string $callback 回调URI
   * - 要授权的账号类型，即商家点击授权链接或者扫了授权码之后，展示在用户手机端的授权账号类型。
   * - 1 表示手机端仅展示公众号；2 表示仅展示小程序，3 表示公众号和小程序都展示。
   * - 4表示小程序推客账号；
   * - 5表示视频号账号；
   * - 6表示全部，即公众号、小程序、视频号都展示
   * - 第三方平台开发者可以使用本字段来控制授权的账号类型。
   * - 对于已经注销、冻结、封禁、以及未完成注册的账号不再出现于授权账号列表。
   * @param int $auth_type
   * @param string $biz_appid
   * @param array $category_id_list https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/product/third_party_authority_instructions.html
   * @return array
   * @throws Exception
   */
  public function getAuthorizedRedirect(string $callback, int $auth_type = 1, string $biz_appid = '', array $category_id_list = []): array
  {
    $paramsStr = '&redirect_uri=' . urlencode($callback);
    if (in_array($auth_type, [1, 2, 3, 4, 5, 6])) $paramsStr .= '&auth_type=' . $auth_type;
    else $paramsStr .= '&auth_type=1';
    if ($biz_appid) $paramsStr .= '&biz_appid=' . $biz_appid;
    if (count($category_id_list)) $paramsStr .= '&category_id_list=' . implode('|', $category_id_list);
    return [
      'PC' => 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid=' . $this->APPID . '&pre_auth_code=' . $this->getPreAuthCode() . $paramsStr,
      'H5' => 'https://open.weixin.qq.com/wxaopen/safe/bindcomponent?action=bindcomponent&no_scan=1&component_appid=' . $this->APPID . '&pre_auth_code=' . $this->getPreAuthCode() . $paramsStr . '#wechat_redirect'
    ];
  }

  /**
   * 使用授权码获取授权信息
   * 获取授权账号的authorizer_access_token
   * @throws Exception
   */
  public function getAuthorizerRefreshToken($authorization_code): string
  {
    $result = $this->request($this->client, 'POST', 'component/api_query_auth?component_access_token=' . $this->getComponentAccessToken(), [
      'json' => ['component_appid' => $this->APPID, 'authorization_code' => $authorization_code],
      'headers' => $this->headers
    ]);
    if (isset($result['authorization_info'])) {
      $info = $result['authorization_info'];
      $this->cache->set($info['authorizer_appid'] . '_authorizer_access_token', $info['authorizer_access_token'], $info['expires_in']);
      $this->cache->set($info['authorizer_appid'] . '_authorizer_refresh_token', $info['authorizer_refresh_token']);
      // 授权给开发者的权限集列表
      $this->cache->set($info['authorizer_appid'] . '_func_info', $info['func_info']);
      return $info['authorizer_refresh_token'];
    }
    return '';
  }

  /**
   * 获取授权账号调用令牌
   * 获取授权账号的authorizer_access_token
   * @param string $authorizer_appid 授权账号appId
   * @return string
   * @throws Exception
   */
  private function getAuthorizerAccessToken(string $authorizer_appid): string
  {
    // 取缓存
    $cacheKey = $authorizer_appid . '_authorizer_access_token';
    $authorizer_access_token = $this->cache->get($cacheKey);
    if (isset($authorizer_access_token)) return $authorizer_access_token;
    // 刷新令牌
    $cacheRefreshTokenKey = $authorizer_appid . '_authorizer_refresh_token';
    $authorizer_refresh_token = $this->cache->get($cacheRefreshTokenKey);
    if (empty($authorizer_refresh_token)) {
      throw new Exception('用户未授权！');
    }

    $result = $this->request($this->client, 'POST', 'component/api_authorizer_token?component_access_token=' . $this->getComponentAccessToken(), [
      'json' => [
        'component_appid' => $this->APPID,
        'authorizer_appid' => $authorizer_appid,
        'authorizer_refresh_token' => $authorizer_refresh_token
      ],
      'headers' => $this->headers
    ]);
    if (isset($result['authorizer_access_token'])) {
      $this->cache->set($cacheKey, $result['authorizer_access_token'], $result['expires_in']);
      $this->cache->set($cacheRefreshTokenKey, $result['authorizer_refresh_token']);
      return $result['authorizer_access_token'];
    }
    return '';
  }

  /**
   * 设置授权公众号appid
   * @param $authorizer_appid
   * @return void
   */
  public function setAuthorizerAppId($authorizer_appid): void
  {
    $this->appid = $authorizer_appid;
  }

  /**
   * @throws Exception
   */
  public function checkAuth(): string
  {
    //数据库取缓存
    $access_token = $this->getAuthorizerAccessToken($this->appid);
    if ($access_token) {
      $this->access_token = $access_token;
      return $access_token;
    }

    return '';
  }

  /**
   * 拉取已授权的帐号信息
   * @throws Exception
   */
  public function getAuthorizerList(int $offset = 1, int $limit = 10): array
  {
    $result = $this->request($this->client, 'POST', 'component/api_get_authorizer_list?component_access_token=' . $this->getComponentAccessToken(), [
      'json' => [
        'component_appid' => $this->APPID,
        'offset' => $offset,
        'count' => $limit
      ],
      'headers' => $this->headers
    ]);
    if ($result['total_count'] > 0) foreach ($result['list'] as &$item) {
      $item['info'] = $this->getAuthorizerInfo($item['authorizer_appid']);
    }
    return $result;
  }

  /**
   * 获取授权账号详情
   * @throws Exception
   */
  public function getAuthorizerInfo(string $authorizer_appid): array
  {
    return $this->request($this->client, 'POST', 'component/api_get_authorizer_info?component_access_token=' . $this->getComponentAccessToken(), [
      'json' => [
        'component_appid' => $this->APPID,
        'authorizer_appid' => $authorizer_appid
      ],
      'headers' => $this->headers
    ]);
  }

  /**
   * 代公众号发起网页授权
   * 第一步：用户同意授权，获取code
   * oauth 授权跳转接口
   * @param string $appid 公众号appId
   * @param string $callback 回调URI
   * @param string $state
   * @param string $scope snsapi_base,snsapi_userinfo
   * @return string
   */
  public function getOauthRedirect(string $appid, string $callback, string $state = '', string $scope = 'snsapi_userinfo'): string
  {
    return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $appid . '&redirect_uri=' . urlencode($callback) . '&response_type=code&scope=' . $scope . '&state=' . $state . '&component_appid=' . $this->APPID . '#wechat_redirect';
  }

  /**
   * 代公众号发起网页授权
   * 第二步：通过code换取网页授权access_token
   * 通过code获取Access Token
   * @param string $appid
   * @param string $code
   * @return array
   * @throws Exception
   */
  public function getOauthAccessToken(string $appid, string $code): array
  {
    $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&code=' . $code . '&grant_type=authorization_code' . '&component_appid=' . $this->APPID . '&component_access_token=' . $this->getComponentAccessToken();
    return $this->request($this->client, 'GET', $url, ['headers' => $this->headers]);
  }

  /**
   * 代公众号发起网页授权
   * 第三步：拉取用户信息(需第一步的scope为 snsapi_userinfo)
   * 获取授权后的用户资料
   * @param string $access_token 网页授权access_token
   * @param string $openid
   * @return array {openid,nickname,sex,province,city,country,headimgurl,privilege}
   * @throws Exception
   */
  public function getOauthUserinfo(string $access_token, string $openid): array
  {
    return $this->request($this->client, 'GET', 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $access_token . '&openid=' . $openid, ['headers' => $this->headers]);
  }
}
