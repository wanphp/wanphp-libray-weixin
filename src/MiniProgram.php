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
use Predis\ClientInterface;
use Wanphp\Libray\Slim\Setting;

class MiniProgram
{
  use HttpTrait;

  private string $appid;
  private string $appSecret;
  private string $access_token;
  private ClientInterface $redis;

  public function __construct(Setting $setting)
  {
    $options = $setting->get('wechat.miniprogram');
    $redis = $setting->get('redis');
    $this->appid = $options['appid'] ?? '';
    $this->appSecret = $options['appsecret'] ?? '';
    $this->redis = new \Predis\Client($redis['parameters'], $redis['options']);
  }

  /**
   * 获取AccessToken，保存到redis
   * @return bool
   * @throws Exception
   */
  public function checkAccessToken(): bool
  {
    //数据库取缓存
    $access_token = $this->redis->get('miniProgram_access_token');
    if ($access_token) {
      $this->access_token = $access_token;
      return $access_token;
    }

    $result = $this->httpGet('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->appSecret);
    if (isset($result['access_token'])) {
      $this->redis->setex('miniProgram_access_token', $result['expires_in'], $result['access_token']);
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
   * 获取用户访问小程序日留存
   * @param $data ["begin_date" => "20170313","end_date" => "20170313"] 结束日期，限定查询1天数据，允许设置的最大值为昨日。
   * @return array
   * @throws Exception
   */
  public function getDailyRetain($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappiddailyretaininfo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序月留存
   * @param $data ["begin_date" : "20170201(为自然月第一天)",  "end_date" : "20170228(为自然月最后一天)"]
   * @return array
   * @throws Exception
   */
  public function getMonthlyRetain($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappidmonthlyretaininfo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序周留存
   * @param $data ["begin_date" : "20170306(为周一日期)",  "end_date" : "20170312(为周日日期)"]
   * @return array
   * @throws Exception
   */
  public function getWeeklyRetain($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappidweeklyretaininfo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据概况
   * @param $data ["begin_date" => "20170313","end_date" => "20170313"] 结束日期，限定查询1天数据，允许设置的最大值为昨日。
   * @return array
   * @throws Exception
   */
  public function getDailySummary($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappiddailysummarytrend?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据日趋势
   * @param $data ["begin_date" => "20170313","end_date" => "20170313"] 结束日期，限定查询1天数据，允许设置的最大值为昨日。
   * @return array
   * @throws Exception
   */
  public function getDailyVisitTrend($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappiddailyvisittrend?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据月趋势(能查询到的最新数据为上一个自然月的数据)
   * @param $data ["begin_date" : "20170201(为自然月第一天)",  "end_date" : "20170228(为自然月最后一天)"]
   * @return array
   * @throws Exception
   */
  public function getMonthlyVisitTrend($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappidmonthlyvisittrend?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据周趋势
   * @param $data ["begin_date" : "20170306(为周一日期)",  "end_date" : "20170312(为周日日期)"]
   * @return array
   * @throws Exception
   */
  public function getWeeklyVisitTrend($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappidweeklyvisittrend?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取小程序新增或活跃用户的画像分布数据。时间范围支持昨天、最近7天、最近30天。
   * 其中，新增用户数为时间范围内首次访问小程序的去重用户数，活跃用户数为时间范围内访问过小程序的去重用户数。
   * @param $data ["begin_date" => "20170611","end_date" => "20170617"] 结束日期，开始日期与结束日期相差的天数限定为0/6/29，分别表示查询最近1/7/30天数据，允许设置的最大值为昨日。
   * @return array
   * @throws Exception
   */
  public function getUserPortrait($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappiduserportrait?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户小程序访问分布数据
   * @param $data ["begin_date" => "20170313", "end_date" => "20170313"] 结束日期，限定查询 1 天数据，允许设置的最大值为昨日.
   * @return array
   * @throws Exception
   */
  public function getVisitDistribution($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappidvisitdistribution?{ACCESS_TOKEN}', $data);
  }

  /**
   * 访问页面。目前只提供按 page_visit_pv 排序的 top200。
   * @param $data ["begin_date" => "20170313", "end_date" => "20170313"] 结束日期，限定查询 1 天数据，允许设置的最大值为昨日.
   * @return array
   * @throws Exception
   */
  public function getVisitPage($data): array
  {
    return $this->httpPost('https://api.weixin.qq.com/datacube/getweanalysisappidvisitpage?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取小程序账号的类目
   * @return array
   * @throws Exception
   */
  public function getCategory(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/newtmpl/getcategory?{ACCESS_TOKEN}');
  }

  /**
   * 获取帐号所属类目下的公共模板标题
   * @param string $ids 类目id，多个用逗号隔开
   * @param int $start 用于分页，表示从 start 开始。从 0 开始计数。
   * @param int $limit 用于分页，表示拉取 limit 条记录。最大为 30。
   * @return array
   * @throws Exception
   */
  public function getPubTemplateTitleList(string $ids, int $start, int $limit): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/newtmpl/getpubtemplatetitles?{ACCESS_TOKEN}&ids=' . $ids . '&start=' . $start . '&limit=' . $limit);
  }

  /**
   * 获取模板标题下的关键词列表
   * @param string $tid
   * @return array
   * @throws Exception
   */
  public function getPubTemplateKeyWordsById(string $tid): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/newtmpl/getpubtemplatekeywords?{ACCESS_TOKEN}&tid' . $tid);
  }

  /**
   * 组合模板并添加至帐号下的个人模板库
   * @param string $tid 模板标题 id
   * @param array $kidList 开发者自行组合好的模板关键词列表，关键词顺序可以自由搭配（例如 [3,5,4] 或 [4,5,3]），最多支持5个，最少2个关键词组合
   * @param string $sceneDesc 服务场景描述，15个字以内
   * @return array
   * @throws Exception
   */
  public function addTemplate(string $tid, array $kidList, string $sceneDesc): array
  {
    return $this->httpPost('https://api.weixin.qq.com/wxaapi/newtmpl/addtemplate?{ACCESS_TOKEN}', ['tid' => $tid, 'kidList' => $kidList, 'sceneDesc' => $sceneDesc]);
  }

  /**
   * 删除帐号下的个人模板
   * @param string $priTmplId 个人模板id
   * @return array
   * @throws Exception
   */
  public function deleteTemplate(string $priTmplId): array
  {
    return $this->httpPost('https://api.weixin.qq.com/wxaapi/newtmpl/deltemplate?{ACCESS_TOKEN}', ['priTmplId' => $priTmplId]);
  }

  /**
   * 获取当前帐号下的个人模板列表
   * @return array
   * @throws Exception
   */
  public function getTemplateList(): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/newtmpl/gettemplate?{ACCESS_TOKEN}');
  }

  /**
   * 发送订阅消息
   * @param string $touser 接收者（用户）的 openid
   * @param string $template_id 所需下发的订阅模板id
   * @param array $data 模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
   * @param string $page 点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
   * @param string $miniprogram_state 跳转小程序类型：developer为开发版；trial为体验版；formal为正式版；默认为正式版
   * @param string $lang 进入小程序查看”的语言类型，支持zh_CN(简体中文)、en_US(英文)、zh_HK(繁体中文)、zh_TW(繁体中文)，默认为zh_CN
   * @return array
   * @throws Exception
   */
  public function sendSubscribeMessage(string $touser, string $template_id, array $data, string $page = '', string $miniprogram_state = 'formal', string $lang = 'zh_CN'): array
  {
    $data = [
      'touser' => $touser,
      'template_id' => $template_id,
      'data' => $data,
      'page' => $page,
      'miniprogram_state' => $miniprogram_state,
      'lang' => $lang
    ];
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?{ACCESS_TOKEN}', $data);
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
  public function getFeedback(int $page, int $limit, int $type): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/feedback/list?{ACCESS_TOKEN}&page=' . $page . '&num=' . $limit . ($type > 0 ? '&type=' . $type : ''));
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
   * @param string $errmsg_keyword 错误关键字
   * @param int $type 查询类型，1 为客户端， 2为服务直达
   * @param string $client_version 客户端版本，可以通过 getVersionList 接口拉取, 不传或者传空代表所有版本
   * @param int $start_time 开始时间
   * @param int $end_time 结束时间
   * @param int $start 分页起始值
   * @param int $limit 一次拉取最大值
   * @throws Exception
   */
  public function getJsErrSearch(string $errmsg_keyword, int $type, string $client_version, int $start_time, int $end_time, int $start, int $limit)
  {
    $data = [
      "errmsg_keyword" => $errmsg_keyword,
      "type" => $type,
      "client_version" => $client_version,
      "start_time" => $start_time,
      "end_time" => $end_time,
      "start" => $start,
      "limit" => $limit
    ];
    $this->httpPost('https://api.weixin.qq.com/wxaapi/log/jserr_search?{ACCESS_TOKEN}', $data);
  }

  /**
   * 性能监控
   * @param int $cost_time_type 可选值 1（启动总耗时）， 2（下载耗时），3（初次渲染耗时）
   * @param int $default_start_time 查询开始时间戳
   * @param int $default_end_time 查询结束时间戳
   * @param string $device 系统平台，可选值 "@_all:"（全部），1（IOS）， 2（android）
   * @param string $networktype 是否下载代码包，当 type 为 1 的时候才生效，可选值 "@_all:"（全部），1（是）， 2（否）
   * @param string $scene 访问来源，当 type 为 1 或者 2 的时候才生效，通过 getSceneList 接口获取
   * @param string $is_download_code 网络环境, 当 type 为 2 的时候才生效，可选值 "@_all:"，wifi, 4g, 3g, 2g
   * @return array
   * @throws Exception
   */
  public function getPerformance(int $cost_time_type, int $default_start_time, int $default_end_time, string $device, string $networktype, string $scene, string $is_download_code): array
  {
    $data = [
      "cost_time_type" => $cost_time_type,
      "default_start_time" => $default_start_time,
      "default_end_time" => $default_end_time,
      "device" => $device,
      "networktype" => $networktype,
      "scene" => $scene,
      "is_download_code" => $is_download_code
    ];
    return $this->httpPost('https://api.weixin.qq.com/wxaapi/log/get_performance?{ACCESS_TOKEN}', $data);
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
  public function realtimelogSearch(array $data): array
  {
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/userlog/userlog_search?{ACCESS_TOKEN}&' . http_build_query($data));
  }

  /**
   * @param $url
   * @param string $referer
   * @return array
   * @throws Exception
   */
  private function httpGet($url, string $referer = ''): array
  {
    if (strpos($url, '{ACCESS_TOKEN}') != false) {
      if (!$this->access_token && !$this->checkAccessToken()) throw new Exception('ACCESS_TOKEN 无效', 400);
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
  private function httpPost($url, $data): array
  {
    if (strpos($url, '{ACCESS_TOKEN}') != false) {
      if (!$this->access_token && !$this->checkAccessToken()) throw new Exception('ACCESS_TOKEN 无效', 400);
      $url = str_replace('{ACCESS_TOKEN}', 'access_token=' . $this->access_token, $url);
    }
    return $this->request(new Client(), 'POST', $url, ['json' => $data, 'headers' => ['Accept' => 'application/json']]);
  }
}
