<?php

namespace Wanphp\Libray\Weixin\Traits;

use Exception;

trait MiniProgramSubscribeMessageTrait
{
  use HttpTrait;

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
    return $this->httpGet('https://api.weixin.qq.com/wxaapi/newtmpl/getpubtemplatekeywords?{ACCESS_TOKEN}&tid=' . $tid);
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
   * @param string $openid 接收者（用户）的 openid
   * @param string $template_id 所需下发的订阅模板id
   * @param array $data 模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
   * @param string $page 点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
   * @param string $miniprogram_state 跳转小程序类型：developer为开发版；trial为体验版；formal为正式版；默认为正式版
   * @param string $lang 进入小程序查看”的语言类型，支持zh_CN(简体中文)、en_US(英文)、zh_HK(繁体中文)、zh_TW(繁体中文)，默认为zh_CN
   * @return array
   * @throws Exception
   */
  public function subscribeMessageSend(string $openid, string $template_id, array $data, string $page = '', string $miniprogram_state = 'formal', string $lang = 'zh_CN'): array
  {
    $data = [
      'touser' => $openid,
      'template_id' => $template_id,
      'data' => $data,
      'page' => $page,
      'miniprogram_state' => $miniprogram_state,
      'lang' => $lang
    ];
    return $this->httpPost('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?{ACCESS_TOKEN}', $data);
  }
}