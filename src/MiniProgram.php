<?php
/**
 * Created by PhpStorm.
 * 微信小程序
 * User: 火子 QQ：284503866.
 * Date: 2020/10/13
 * Time: 9:03
 */

namespace Wanphp\Libray\Weixin;


use Exception;
use GuzzleHttp\Client;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;
use Wanphp\Libray\Slim\RedisCacheFactory;
use Wanphp\Libray\Slim\Setting;
use Wanphp\Libray\Weixin\Traits\HttpTrait;
use Wanphp\Libray\Weixin\Traits\ImageOcrTrait;
use Wanphp\Libray\Weixin\Traits\MiniProgramDataCubeTrait;
use Wanphp\Libray\Weixin\Traits\MiniProgramSubscribeMessageTrait;

class MiniProgram
{
  use HttpTrait;
  use ImageOcrTrait;
  use MiniProgramDataCubeTrait;
  use MiniProgramSubscribeMessageTrait;

  protected string $appid;
  protected string $appSecret;
  protected string $access_token;
  protected CacheInterface $cache;
  protected Client $client;
  protected array $headers;

  public function __construct(Setting $setting, RedisCacheFactory $cacheFactory)
  {
    $options = $setting->get('wechat.miniprogram');
    $this->appid = $options['appid'] ?? '';
    $this->appSecret = $options['appsecret'] ?? '';
    $this->cache = new Psr16Cache($cacheFactory->create($options['database'] ?? 0, $options['prefix'] ?? 'wxMiniProgram'));

    $this->client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
    $this->headers = ['Accept' => 'application/json'];
  }

  /**
   * 获取AccessToken，保存到缓存库
   * @return string
   * @throws Exception
   */
  public function checkAuth(): string
  {
    //数据库取缓存
    $access_token = $this->cache->get($this->appid . '_miniProgram_access_token');
    if ($access_token) {
      $this->access_token = $access_token;
      return $access_token;
    }

    $result = $this->httpGet('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->appSecret);
    if (isset($result['access_token'])) {
      $this->cache->set($this->appid . '_miniProgram_access_token', $result['access_token'], $result['expires_in']);
      $this->access_token = $result['access_token'];
      return $result['access_token'];
    } else {
      throw new Exception($result['errcode'] . ' - ' . $result['errmsg'], 400);
    }
  }

  /**
   * 登录凭证校验。通过 wx.login 接口获得临时登录凭证 code 后传到开发者服务器调用此接口完成登录流程。更多使用方法详见 小程序登录。
   * @param $jsCode
   * @return array
   * @throws Exception
   */
  public function code2Session($jsCode): array
  {
    return $this->httpGet('https://api.weixin.qq.com/sns/jscode2session?appid=' . $this->appid . '&secret=' . $this->appSecret . '&js_code=' . $jsCode . '&grant_type=authorization_code');
  }

  /**
   * 检验登录态
   * @param string $openid
   * @param string $sessionKey
   * @return array
   * @throws Exception
   */
  public function checkSessionKey(string $openid, string $sessionKey): array
  {
    $signature = hash_hmac('sha256', '', $sessionKey);
    return $this->httpGet('https://api.weixin.qq.com/wxa/checksession?{ACCESS_TOKEN}&openid=' . $openid . '&signature=' . $signature . '&sig_method=hmac_sha256');
  }

  /**
   * 重置登录态
   * @param string $openid
   * @param string $sessionKey
   * @return array
   * @throws Exception
   */
  public function resetUserSessionKey(string $openid, string $sessionKey): array
  {
    $signature = hash_hmac('sha256', '', $sessionKey);
    return $this->httpGet('https://api.weixin.qq.com/wxa/resetusersessionkey?{ACCESS_TOKEN}&openid=' . $openid . '&signature=' . $signature . '&sig_method=hmac_sha256');
  }

  /**
   * 该接口用于将code换取用户手机号。 说明，每个code只能使用一次，code的有效期为5min
   * @param string $code 手机号获取凭证
   * @return array
   * @throws Exception
   */
  public function getPhoneNumber(string $code): array
  {
    return $this->httpPost('https://api.weixin.qq.com/wxa/business/getuserphonenumber?{ACCESS_TOKEN}', ['code' => $code]);
  }
  /**
   * 获取插件用户openpid
   * @param string $code 通过 wx.pluginLogin 获得的插件用户标志凭证 code，有效时间为5分钟，一个 code 只能获取一次 openpid。
   * @return array
   * @throws Exception
   */
  public function getPluginOpenPId(string $code): array
  {
    return $this->httpPost('https://api.weixin.qq.com/wxa/getpluginopenpid?{ACCESS_TOKEN}', ['code' => $code]);
  }
  /**
   * 用户支付完成后，获取该用户的 UnionId，无需用户授权。本接口支持第三方平台代理查询。
   * 注意：调用前需要用户完成支付，且在支付后的五分钟内有效。
   * @param $openid
   * @param $transaction_id
   * @return array
   * @throws Exception
   */
  public function getPaidUnionId($openid, $transaction_id): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxa/getpaidunionid?{ACCESS_TOKEN}&openid=' . $openid . '&transaction_id=' . $transaction_id);
  }

  /**
   * 获取小程序码
   * @param string $path 扫码进入的小程序页面路径，最大长度 1024 个字符，不能为空，scancode_time为系统保留参数，不允许配置；
   * 对于小游戏，可以只传入 query 部分，来实现传参效果，如：传入 "?foo=bar"，
   * 即可在 wx.getLaunchOptionsSync 接口中的 query 参数获取到 {foo:"bar"}。
   * @param int $width 二维码的宽度，单位 px。默认值为430，最小 280px，最大 1280px
   * @return array
   * @throws Exception
   */
  public function getQRCode(string $path, int $width = 430): array
  {
    $data = [
      "path" => $path,
      "width" => $width
    ];
    return $this->httpPost('https://api.weixin.qq.com/wxa/getwxacode?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取小程序二维码
   * @param string $path 扫码进入的小程序页面路径，最大长度 128 个字符，不能为空；
   * 对于小游戏，可以只传入 query 部分，来实现传参效果，如：传入 "?foo=bar"，即可在 wx.getLaunchOptionsSync 接口中的 query 参数获取到 {foo:"bar"}。
   * scancode_time为系统保留参数，不允许配置。
   * @param int $width 二维码的宽度，单位 px。默认值为430，最小 280px，最大 1280px
   * @return array
   * @throws Exception
   */
  public function createQRCode(string $path, int $width = 430): array
  {
    $data = [
      "path" => $path,
      "width" => $width
    ];
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取不限制的小程序码
   * @param string $scene 最大32个可见字符
   * @param string $page 默认是主页，页面 page，例如 pages/index/index
   * @param int $width 默认430，二维码的宽度，单位 px，最小 280px，最大 1280px
   * @return array
   * @throws Exception
   */
  public function getUnlimitedQRCode(string $scene, string $page = '', int $width = 430): array
  {
    $data = [
      "scene" => $scene,
      "page" => $page,
      "width" => $width
    ];
    return $this->httpPost('https://api.weixin.qq.com/wxa/getwxacodeunlimit?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户反馈列表
   * @param int $page 分页的页数，从1开始
   * @param int $limit 分页拉取的数据数量
   * @param int $type 反馈的类型，默认拉取全部类型，
   * 1,无法打开小程序
   * 2,小程序闪退
   * 3,卡顿
   * 4,黑屏白屏
   * 5,死机
   * 6,界面错位
   * 7,界面加载慢
   * 8,其他异常
   * @return array
   * @throws Exception
   */
  public function getFeedback(int $page, int $limit, int $type = 0): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/feedback/list?{ACCESS_TOKEN}&page=' . $page . '&num=' . $limit . ($type > 0 ? '&type=' . $type : ''));
  }

  /**
   * 获取用户反馈信息的图片
   * @param int $record_id 用户反馈信息的 record_id, 可通过 getFeedback 获取
   * @param string $media_id 图片的 mediaId
   * @return array
   * @throws Exception
   */
  public function getFeedbackMedia(int $record_id, string $media_id): array
  {
    return $this->httpGet('https://api.weixin.qq.com/cgi-bin/media/getfeedbackmedia?{ACCESS_TOKEN}&record_id=' . $record_id . '&media_id=' . $media_id);
  }

  /**
   * 获取客户端版本
   * @return array
   * @throws Exception
   */
  public function getVersionList(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/log/get_client_version?{ACCESS_TOKEN}');
  }

  /**
   * 查询错误列表
   * @param array $params
   * @return array
   * @throws Exception
   */
  public function getJsErrList(array $params): array
  {
    $data = [
      "keyword" => $params['keyword'] ?? '', // 从错误中搜索关键词，关键词过滤
      "errType" => $params['errType'] ?? '0', //错误类型 "0"【全部】，"1"【业务代码错误】，"2"【插件错误】，"3"【系统框架错误】
      "appVersion" => $params['appVersion'] ?? '0', //小程序版本 "0"代表全部， 例如：“2.0.18”
      "startTime" => $params['startTime'] ?? date('Y-m-d', strtotime('-1 day')), // 开始时间
      "endTime" => $params['endTime'] ?? date('Y-m-d'), //开始时间，格式 “xxxx-xx-xx”
      "openid" => $params['openid'] ?? '', // 发生错误的用户 openId
      "orderby" => $params['orderby'] ?? 'uv', // 排序字段 "uv", "pv" 二选一
      "desc" => $params['desc'] ?? '1', // 排序规则 "1" orderby字段降序，"2" orderby字段升序
      "offset" => $params['offset'] ?? '0', // 分页起始值
      "limit" => $params['limit'] ?? '10' // 一次拉取最大值， 最大 30
    ];
    return $this->httpPost('https://api.weixin.qq.com/wxaapi/log/jserr_list?{ACCESS_TOKEN}', $data);
  }

  /**
   * 查询JS错误详情
   * @param array $params $data = [
   * "startTime" => =>"2021-01-25", 开始时间， 格式 "xxxx-xx-xx"
   * "endTime" => =>"2021-01-26", 结束时间，格式 “xxxx-xx-xx”
   * "errorMsgMd5" => =>"f2fb4f8cd638466ad0e7607b01b7d0ca", 错误信息的md5
   * "errorStackMd5" => =>"795a63b70ce5755c7103611d93077603", errorStack的Md5信息
   * "appVersion" => =>"0", 小程序版本 "0"代表全部， 例如：“2.0.18”
   * "sdkVersion" => =>"0", 基础库版本 "0"表示所有版本，例如 "2.14.1"
   * "osName" => =>"2", 系统类型 "0"【全部】，"1" 【安卓】，"2" 【IOS】，"3"【其他】
   * "clientVersion" => =>"0", 客户端版本 "0"表示所有版本， 例如 "7.0.22"
   * "openid" => =>"", 发生错误的用户 openId
   * "offset" => =>0, 分页起始值
   * "limit" => =>10, 一次拉取最大值
   * "desc" => =>"0" 排序规则 "0" 升序, "1" 降序
   * ];
   * @return array
   * @throws Exception
   */
  public function getJsErrDetail(array $params): array
  {
    $data = [
      "startTime" => $params[''] ?? date('Y-m-d'),
      "endTime" => $params[''] ?? date('Y-m-d', strtotime('-1 day')),
      "errorMsgMd5" => $params[''] ?? "",
      "errorStackMd5" => $params[''] ?? "",
      "appVersion" => $params[''] ?? "0",
      "sdkVersion" => $params[''] ?? "0",
      "osName" => $params[''] ?? "0",
      "clientVersion" => $params[''] ?? "0",
      "openid" => $params[''] ?? "",
      "offset" => $params[''] ?? 0,
      "limit" => $params[''] ?? 10,
      "desc" => $params[''] ?? "0"
    ];
    return $this->httpPost('https://api.weixin.qq.com/wxaapi/log/jserr_detail?{ACCESS_TOKEN}', $data);
  }

  /**
   * 性能监控
   * @param array $params [
   * "cost_time_type"=> 2, // 可选值 1（启动总耗时）， 2（下载耗时），3（初次渲染耗时）
   * "default_start_time"=> 1572339403, // 查询开始时间戳
   * "default_end_time"=> 1574931403, // 查询结束时间戳
   * "device"=> "@_all", // 系统平台，可选值 "@_all:"（全部），1（IOS）， 2（android）
   * "networktype"=> "@_all", // 是否下载代码包，当 type 为 1 的时候才生效，可选值 "@_all:"（全部），1（是）， 2（否）
   * "scene"=> "@_all", // 访问来源，当 type 为 1 或者 2 的时候才生效，通过 getSceneList 接口获取
   * "is_download_code"=> "@_all" // 网络环境, 当 type 为 2 的时候才生效，可选值 "@_all:"，wifi, 4g, 3g, 2g
   * ];
   * @return array
   * @throws Exception
   */
  public function getPerformance(array $params): array
  {

    $data = [
      "cost_time_type" => $params['cost_time_type'] ?? '1',
      "default_start_time" => $params['default_start_time'] ?? strtotime('-1 day'),
      "default_end_time" => $params['default_end_time'] ?? time(),
      "device" => $params['device'] ?? '@_all',
      "networktype" => $params['networktype'] ?? '@_all',
      "scene" => $params['scene'] ?? '@_all',
      "is_download_code" => $params['is_download_code'] ?? '@_all'
    ];
    return $this->httpPost('https://api.weixin.qq.com/wxaapi/log/get_performance?{ACCESS_TOKEN}', $data);
  }

  /**
   * 查询小程序域名配置信息
   * @return array
   * @throws Exception
   */
  public function getDomainInfo(): array
  {
    return $this->httpPost('https://api.weixin.qq.com/wxa/getwxadevinfo?{ACCESS_TOKEN}', ["action" => "getserverdomain"]);
  }

  /**
   * 获取访问来源
   * @return array
   * @throws Exception
   */
  public function getSceneList(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/log/get_scene?{ACCESS_TOKEN}');
  }

  /**
   * 实时日志查询
   * @param array $data
   * date  string    是  YYYYMMDD格式的日期，仅支持最近7天
   * begintime  number    是  开始时间，必须是date指定日期的时间
   * endtime  number    是  结束时间，必须是date指定日期的时间
   * start  number  0  否  开始返回的数据下标，用作分页，默认为0
   * limit  number  20  否  返回的数据条数，用作分页，默认为20
   * traceId  string    否  小程序启动的唯一ID，按TraceId查询会展示该次小程序启动过程的所有页面的日志。
   * url  string    否  小程序页面路径，例如pages/index/index
   * id  string    否  用户微信号或者OpenId
   * filterMsg  string    否  开发者通过setFileterMsg/addFilterMsg指定的filterMsg字段
   * level  number    否  日志等级，返回大于等于level等级的日志，level的定义为2（Info）、4（Warn）、8（Error），如果指定为4，则返回大于等于4的日志，即返回Warn和Error日志。
   * @return array
   * @throws Exception
   */
  public function realtimeLogSearch(array $data): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/userlog/userlog_search?{ACCESS_TOKEN}&' . http_build_query($data));
  }
}
