<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Created Time: 2024/11/26 上午11:04
 */

namespace Wanphp\Libray\Weixin\Traits;

use Exception;

trait MiniProgramLivePlayerTrait
{
  use HttpTrait;

  /**
   * 调用此接口创建直播间，创建成功后将在直播间列表展示
   * @param $data {
   * name: "测试直播房间1",  // 房间名字
   * coverImg: "",   // 通过 uploadfile 上传，填写 mediaID
   * startTime: 1588237130,   // 开始时间
   * endTime: 1588237130 , // 结束时间
   * anchorName: "zefzhang1",  // 主播昵称
   * anchorWechat: "WxgQiao_04",  // 主播微信号
   * subAnchorWechat: "WxgQiao_03",  // 主播副号微信号
   * createrWechat: 'test_creater', // 创建者微信号
   * shareImg: "hw7zsntcr0rE-RBfBAaF553DqBk-J02UtWsP8VqrUh3tKu3jO_JwEO8n1cWTJ5TN" ,  //通过 uploadfile 上传，填写 mediaID
   * feedsImg: "hw7zsntcr0rE-RBfBAaF553DqBk-J02UtWsP8VqrUh3tKu3jO_JwEO8n1cWTJ5TN",   //通过 uploadfile 上传，填写 mediaID
   * isFeedsPublic: 1, // 是否开启官方收录，1 开启，0 关闭
   * type: 1 , // 直播类型，1 推流 0 手机直播
   * closeLike: 0 , // 是否关闭点赞 1：关闭
   * closeGoods: 0, // 是否关闭商品货架，1：关闭
   * closeComment: 0 // 是否开启评论，1：关闭
   * closeReplay: 1 , // 是否关闭回放 1 关闭
   * closeShare: 0,   //  是否关闭分享 1 关闭
   * closeKf: 0, // 是否关闭客服，1 关闭
   * }
   * @throws Exception
   */
  public function createRoom(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/create?{ACCESS_TOKEN}', $data);
  }

  /**
   * 该接口用于获取直播间列表及直播间信息。也可以用来获取已结束直播间的回放源视频（一般在直播结束后10分钟内生成，源视频无评论等内容）。
   * @param array $data {
   * "start": 0, // 起始拉取房间，start = 0 表示从第 1 个房间开始拉取
   * "limit": 10， // 每次拉取的个数上限，不要设置过大，建议 100 以内
   * "action": 'get_replay', // 只能填"get_replay"，表示获取回放。选填
   * "room_id": 10 // 当action有值时该字段必填，直播间ID。选填
   * }
   * @return array
   * @throws Exception
   */
  public function getLiveInfo(array $data): array
  {
    return $this->httpPost('/wxa/business/getliveinfo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 删除直播间
   * @param array $data {
   * "id" : 6491 //房间ID
   * }
   * @return array
   * @throws Exception
   */
  public function deleteRoom(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/deleteroom?{ACCESS_TOKEN}', $data);
  }

  /**
   * 往指定直播间导入商品
   * @param array $data {
   * "ids": [1150, 1111],  // 数组列表，可传入多个，里面填写 商品 ID
   * "roomId": 2554
   * }
   * @return array
   * @throws Exception
   */
  public function importGoods(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/addgoods?{ACCESS_TOKEN}', $data);
  }

  /**
   * 编辑直播间
   * @param array $data {
   * "id": 811,
   * "name": "测试更新副号1",
   * "coverImg": "hw7zsntcr0rE-RBfBAaF553DqBk-J02UtWsP8VqrUh3tKu3jO_JwEO8n1cWTJ5TN",
   * "startTime": 1607443200,
   * "endTime": 1607450400,
   * "anchorName": "主播昵称11",
   * "anchorWechat": "lintest1",
   * "shareImg": "hw7zsntcr0rE-RBfBAaF553DqBk-J02UtWsP8VqrUh3tKu3jO_JwEO8n1cWTJ5TN",
   * "closeLike": 0,
   * "closeGoods": 0,
   * "closeComment": 0,
   * "isFeedsPublic": 0,
   * "closeReplay": 0,
   * "closeShare": 0,
   * "closeKf": 0,
   * "feedsImg": "hw7zsntcr0rE-RBfBAaF553DqBk-J02UtWsP8VqrUh3tKu3jO_JwEO8n1cWTJ5TN"
   * }
   * @return array
   * @throws Exception
   */
  public function editRoom(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/editroom?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取直播间推流地址
   * @param int $roomId
   * @return array
   * @throws Exception
   */
  public function getPushUrl(int $roomId): array
  {
    return $this->httpGet('/wxaapi/broadcast/room/getpushurl?{ACCESS_TOKEN}&roomId=' . $roomId);
  }

  /**
   * 获取直播间分享二维码
   * @param int $roomId
   * @param string $params 自定义参数,选填
   * @return array
   * @throws Exception
   */
  public function getSharedCode(int $roomId, string $params = ''): array
  {
    return $this->httpGet('/wxaapi/broadcast/room/getsharedcode?{ACCESS_TOKEN}&roomId=' . $roomId . ($params ? '&params=' . $params : ''));
  }

  /**
   * 获取主播副号
   * @param int $roomId
   * @return array
   * @throws Exception
   */
  public function getSubAnchor(int $roomId): array
  {
    return $this->httpGet('/wxaapi/broadcast/room/getsubanchor?{ACCESS_TOKEN}&roomId=' . $roomId);
  }

  /**
   * 添加主播副号
   * @param array $data {
   * "roomId": 6827, // 房间ID
   * "username": "wechattest" //微信号
   * }
   * @return array
   * @throws Exception
   */
  public function addSubAnchor(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/addsubanchor?{ACCESS_TOKEN}', $data);
  }

  /**
   * 修改主播副号
   * @param array $data {
   * "roomId": 6827, // 房间ID
   * "username": "wechattest" //微信号
   * }
   * @return array
   * @throws Exception
   */
  public function modifySubAnchor(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/modifysubanchor?{ACCESS_TOKEN}', $data);
  }

  /**
   * 删除主播副号
   * @param array $data {
   * "roomId": 6827 // 房间ID
   * }
   * @return array
   * @throws Exception
   */
  public function deleteSubAnchor(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/deletesubanchor?{ACCESS_TOKEN}', $data);
  }

  /**
   * 删除直播间商品
   * @param array $data {
   * "roomId": 6827, // 房间ID
   * "goodsId": 123 // 商品ID
   * }
   * @return array
   * @throws Exception
   */
  public function deleteGoods(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/deleteInRoom?{ACCESS_TOKEN}', $data);
  }

  /**
   * 推送商品到直播间
   * @param array $data {
   * "roomId": 6827, // 房间ID
   * "goodsId": 123 // 商品ID
   * }
   * @return array
   * @throws Exception
   */
  public function pushGoods(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/push?{ACCESS_TOKEN}', $data);
  }

  /**
   * 下架或者上架商品
   * @param array $data {
   * "roomId": 6827, // 房间ID
   * "goodsId": 123, // 商品ID
   * "onSale": 0 // 上下架 【0：下架，1：上架】
   * }
   * @return array
   * @throws Exception
   */
  public function saleGoods(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/onsale?{ACCESS_TOKEN}', $data);
  }

  /**
   * 下直播间商品排序
   * @param array $data {
   * "roomId": 6827, // 房间ID
   * "goods" : [{"goodsId":"123"}, {"goodsId":"234"}]
   * }
   * @return array
   * @throws Exception
   */
  public function sortGoods(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/sort?{ACCESS_TOKEN}', $data);
  }

  /**
   * 修改直播间小助手
   * @param array $data {
   * "roomId": 6474, // 房间ID
   * "username": "testwechat", // 用户微信号
   * "nickname": "testnick" //用户微信昵称
   * }
   * @return array
   * @throws Exception
   */
  public function modifyAssistant(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/modifyassistant?{ACCESS_TOKEN}', $data);
  }

  /**
   * 查询直播间小助手
   * @param int $roomId
   * @return array
   * @throws Exception
   */
  public function getAssistantList(int $roomId): array
  {
    return $this->httpGet('/wxaapi/broadcast/room/getassistantlist?{ACCESS_TOKEN}&roomId=' . $roomId);
  }

  /**
   * 删除直播间小助手
   * @param array $data {
   * "roomId": 6474, // 房间ID
   * "username": "testwechat" // 用户微信号
   * }
   * @return array
   * @throws Exception
   */
  public function removeAssistant(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/removeassistant?{ACCESS_TOKEN}', $data);
  }

  /**
   * 添加管理直播间小助手
   * @param array $data {
   * "roomId": 6474,
   * "users": [{"username":"testwechat","nickname":"testnick"}]
   * }
   * @return array
   * @throws Exception
   */
  public function addAssistant(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/addassistant?{ACCESS_TOKEN}', $data);
  }

  /**
   * 开启/关闭直播间全局禁言
   * @param array $data {
   * "roomId": 6474,
   * "banComment" : 1 //1-禁言，0-取消禁言
   * }
   * @return array
   * @throws Exception
   */
  public function updateComment(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/updatecomment?{ACCESS_TOKEN}', $data);
  }

  /**
   * 开启/关闭直播间官方收录
   * @param array $data {
   * "roomId": 6474,
   * "isFeedsPublic" : 1 // 是否开启官方收录 【1: 开启，0：关闭】
   * }
   * @return array
   * @throws Exception
   */
  public function updateFeedPublic(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/updatefeedpublic?{ACCESS_TOKEN}', $data);
  }

  /**
   * 用于开启/关闭客服功能
   * @param array $data {
   * "roomId": 6474,
   * "closeKf" : 1 // 是否关闭客服 【0：开启，1：关闭】
   * }
   * @return array
   * @throws Exception
   */
  public function updateKF(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/updatekf?{ACCESS_TOKEN}', $data);
  }

  /**
   * 用于开启/关闭回放功能
   * @param array $data {
   * "roomId": 6474,
   * "closeReplay" : 1 // 是否关闭回放 【0：开启，1：关闭】
   * }
   * @return array
   * @throws Exception
   */
  public function updateReplay(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/room/updatereplay?{ACCESS_TOKEN}', $data);
  }

  /**
   * 下载商品讲解视频
   * @param array $data {
   * "roomId": 6474,
   * "goodsId" : 1 // 商品ID
   * }
   * @return array
   * @throws Exception
   */
  public function downloadGoodsVideo(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/getVideo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 调用此接口上传并提审需要直播的商品信息，审核通过后商品录入【小程序直播】商品库。
   * 添加成功后开发者必须保存【商品ID】与【审核单ID】，如果丢失，则无法调用其他相关接口
   * @param array $data {
   * "goodsInfo": {
   * "coverImgUrl": "ZuYVNKk9sMP1X4m7FXdcDCKra251KDZTjS502UTV7gwalgLZXcrOhG6oNYX6c7AR", //填入mediaID（mediaID获取后，三天内有效）
   * "name":"TIT茶杯", //商品名称，最长14个汉字，1个汉字相当于2个字符
   * "priceType":1, //价格类型，1：一口价（只需要传入price，price2不传） 2：价格区间（price字段为左边界，price2字段为右边界，price和price2必传） 3：显示折扣价（price字段为原价，price2字段为现价， price和price2必传）
   * "price":99.5, //数字，最多保留两位小数，单位元
   * // "price2": 150.5, priceType为2或3时必填
   * "url":"pages/index/index", //商品详情页的小程序路径，路径参数存在 url 的，该参数的值需要进行 encode 处理再填入
   * "thirdPartyAppid": "" //当商品为第三方小程序的商品则填写为对应第三方小程序的appid，自身小程序商品则为''
   * }
   * }
   * @return array
   * @throws Exception
   */
  public function addGoods(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/add?{ACCESS_TOKEN}', $data);
  }

  /**
   * 对已撤回提审的商品再次发起提审申请
   * @param array $data {
   * "goodsId": 1
   * }
   * @return array
   * @throws Exception
   */
  public function resubmitAudit(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/audit?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取商品的信息与审核状态
   * @param array $data {
   * "goods_ids": [1] //最多支持一次获取20个商品状态
   * }
   * @return array
   * @throws Exception
   */
  public function getGoodsAuditInfo(array $data): array
  {
    return $this->httpPost('/wxa/business/getgoodswarehouse?{ACCESS_TOKEN}', $data);
  }

  /**
   * 撤回商品审核，消耗的提审次数不返还
   * @param array $data {
   * "auditId": 525022184, //审核单ID
   * "goodsId": 9
   * }
   * @return array
   * @throws Exception
   */
  public function resetAudit(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/resetaudit?{ACCESS_TOKEN}', $data);
  }

  /**
   * 更新商品信息，审核通过的商品仅允许更新价格类型与价格，审核中的商品不允许更新，未审核的商品允许更新所有字段， 只传入需要更新的字段。
   * @param array $data {
   * "goodsInfo": {
   * // 需要更新哪个字段就传入哪个字段，goodsId 必传
   * "coverImgUrl": "ZuYVNKk9sMP1X4m7FXdcDCKra251KDZTjS502UTV7gwalgLZXcrOhG6oNYX6c7AR",
   * "name":"TIT茶杯",
   * "priceType":1,
   * "price":99.5,
   * // "price2": 150.5, priceType为2或3时必填
   * "url": "pages/index/index",
   * "goodsId": 9
   * }
   * }
   * @return array
   * @throws Exception
   */
  public function updateGoodsInfo(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/update?{ACCESS_TOKEN}', $data);
  }

  /**
   * 删除【小程序直播】商品库中的商品，删除后直播间上架的该商品也将被同步删除，不可恢复
   * @param array $data {
   * "goodsId": 9
   * }
   * @return array
   * @throws Exception
   */
  public function deleteGoodsInfo(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/goods/delete?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取不同审核状态的商品信息
   * @param int $offset 分页条数起点
   * @param int $status 商品状态，0：未审核。1：审核中，2：审核通过，3：审核驳回
   * @param int $limit 分页大小，默认30，不超过100
   * @return array
   * @throws Exception
   */
  public function getGoodsInfo(int $offset, int $status, int $limit = 30): array
  {
    return $this->httpGet("/wxaapi/broadcast/goods/getapproved?{ACCESS_TOKEN}&offset={$offset}&status={$status}&limit={$limit}");
  }

  /**
   * 设置小程序直播成员的管理员、运营者和主播角色
   * @param array $data {
   * username: 'test_1', // 微信号
   * role: 1, // 取值[1-管理员，2-主播，3-运营者]，设置超级管理员将无效
   * }
   * @return array
   * @throws Exception
   */
  public function addRole(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/role/addrole?{ACCESS_TOKEN}', $data);
  }

  /**
   * 移除小程序直播成员的管理员、运营者和主播角色
   * @param array $data {
   * username: 'test_1', // 微信号
   * role: 1, // 取值[1-管理员，2-主播，3-运营者]，删除超级管理员将无效
   * }
   * @return array
   * @throws Exception
   */
  public function deleteRole(array $data): array
  {
    return $this->httpPost('/wxaapi/broadcast/role/deleterole?{ACCESS_TOKEN}', $data);
  }

  /**
   * 查询小程序直播成员列表
   * @param int $role 取值 [-1-所有成员， 0-超级管理员，1-管理员，2-主播，3-运营者]
   * @param int $offset 分页条数起点
   * @param int $limit 查询个数，最大30，默认10
   * @param string $keyword 搜索的微信号或昵称，不传则返回全部
   * @return array
   * @throws Exception
   */
  public function getRoleList(int $role = -1, int $offset = 0, int $limit = 10, string $keyword = ''): array
  {
    return $this->httpGet("/wxaapi/broadcast/role/getrolelist?{ACCESS_TOKEN}&role={$role}&offset={$offset}&limit={$limit}&keyword={$keyword}");
  }

  /**
   * 向长期订阅用户群发直播间开始事件
   * @param array $data {
   * "room_id": 1,
   * "user_openid":["openid1", "openid2"]
   * }
   * @return array
   * @throws Exception
   */
  public function pushMessage(array $data): array
  {
    return $this->httpPost('/wxa/business/push_message?{ACCESS_TOKEN}',$data);
  }

  /**
   * 获取长期订阅用户列表
   * @param array $data {
   * "limit": 200, 获取长期订阅用户的个数限制，默认200，最大2000
   * "page_break":0 翻页标记，获取第一页时不带，第二页开始需带上上一页返回结果中的page_break
   * }
   * @return array
   * @throws Exception
   */
  public function getFollowers(array $data): array
  {
    return $this->httpPost('/wxa/business/get_wxa_followers?{ACCESS_TOKEN}',$data);
  }
}
