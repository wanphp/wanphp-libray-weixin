<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Created Time: 2024/7/17 上午9:50
 */

namespace Wanphp\Libray\Weixin\Traits;

use Exception;
use GuzzleHttp\Exception\GuzzleException;

trait OfficialAccountTrait
{
  use HttpTrait;

  private array $queryParams;
  private array $_receive;

  /**
   * 微信JS-SDK
   * @param string $url 当前URL
   * @param string $appId 公众号appId
   * @return array|null
   * @throws Exception
   */
  public function getSignPackage(string $url, string $appId = ''): ?array
  {
    $jsapiTicket = $this->getJsApiTicket($appId);
    if ($jsapiTicket) {
      $timestamp = time();
      $nonceStr = $this->bizMsgCrypt->createNonceStr();

      // 这里参数的顺序要按照 key 值 ASCII 码升序排序
      $signature = sha1("jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url");

      return [
        "appId" => ($appId ?: $this->appid),
        "nonceStr" => $nonceStr,
        "timestamp" => $timestamp,
        "signature" => $signature
      ];
    } else {
      return null;
    }
  }

  /**
   * 获取 ticket
   * @param string $appId 公众号appId
   * @return string
   * @throws Exception
   */
  protected function getJsApiTicket(string $appId = ''): string
  {
    if (!empty($this->jsapi_ticket)) return $this->jsapi_ticket;
    $jsapi_ticket = $this->cache->get(($appId ?: $this->appid) . '_weixin_jsapi_ticket');
    if (!empty($jsapi_ticket)) {
      $this->jsapi_ticket = $jsapi_ticket;
      return $jsapi_ticket;
    }

    $result = $this->httpGet('ticket/getticket?type=jsapi&{ACCESS_TOKEN}');
    if ($result) {
      $this->cache->set(($appId ?: $this->appid) . '_weixin_jsapi_ticket', $result['ticket'], $result['expires_in']);
      $this->jsapi_ticket = $result['ticket'];
      return $result['ticket'];
    }
    return '';
  }

  /**
   * For weixin server validation
   * @param array $queryParams
   * @return bool|string
   */
  public function valid(array $queryParams): bool|string
  {
    $this->queryParams = $queryParams;
    if (!isset($this->queryParams["signature"])) return 'no access';

    $tmpArr = [$this->message_token, $this->queryParams["timestamp"] ?? '', $this->queryParams["nonce"] ?? ''];
    sort($tmpArr, SORT_STRING);
    $tmpStr = implode($tmpArr);
    $signature = sha1($tmpStr);

    if (isset($this->queryParams["echostr"]) && $this->queryParams["signature"] == $signature) {
      return $this->queryParams["echostr"];
    } else {
      if ($this->queryParams["signature"] == $signature) return true;
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
      $this->_receive = [];
      if (isset($this->queryParams['msg_signature'])) {//安全模式
        $msg_signature = $this->queryParams['msg_signature'] ?? '';
        $timestamp = $this->queryParams['timestamp'] ?? time();
        $nonce = $this->queryParams['nonce'] ?? '';
        $errCode = $this->bizMsgCrypt->decryptMsg($msg_signature, $timestamp, $nonce, $postStr, $this->_receive);
        if ($errCode !== 0) {
          throw new Exception("解密出错: " . $errCode, 400);
        }
      }
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

    if (isset($this->queryParams['timestamp'])) {//安全模式
      $timestamp = $this->queryParams['timestamp'];
      $nonce = $this->queryParams['nonce'];
      $errCode = $this->bizMsgCrypt->encryptMsg($xmlData, $timestamp, $nonce, $xmlData);
      if ($errCode !== 0) {
        throw new Exception("加密出错: " . $errCode, 400);
      }
    }
    return $xmlData;
  }

  /**
   * 创建菜单
   * @param array $data 菜单数组数据
   * @return array
   * @throws Exception
   */
  public function createMenu(array $data): array
  {
    return $this->httpPost('menu/create?{ACCESS_TOKEN}', $data);
  }

  /**
   * 创建个性化菜单
   * @param array $data 菜单数组数据
   * @return array
   * @throws Exception
   */
  public function addConditional(array $data): array
  {
    return $this->httpPost('menu/addconditional?{ACCESS_TOKEN}', $data);
  }

  /**
   * 删除个性化菜单
   * @param array $data = array("menuid" => "208379533");
   * @return array
   * @throws Exception
   */
  public function delConditional(array $data): array
  {
    return $this->httpPost('menu/delconditional?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取菜单
   * @return array
   * @throws Exception
   */
  public function getMenu(): array
  {
    return $this->httpGet('menu/get?{ACCESS_TOKEN}');
  }

  /**
   * 删除菜单
   * @return array
   * @throws Exception
   */
  public function deleteMenu(): array
  {
    return $this->httpGet('menu/delete?{ACCESS_TOKEN}');
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

    return $this->httpPost('qrcode/create?{ACCESS_TOKEN}', $data);
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
    return $this->httpGet('user/get?{ACCESS_TOKEN}&next_openid=' . $next_openid);
  }

  /**
   * 获取关注者详细信息
   * @param string $openid
   * @return array
   * @throws Exception
   */
  public function getUserInfo(string $openid): array
  {
    return $this->httpGet('user/info?{ACCESS_TOKEN}&openid=' . $openid);
  }

  /**
   * 批量获取用户基本信息
   * @param array $openid
   * @return array
   * @throws Exception
   */
  public function getUserListInfo(array $openid): array
  {
    return $this->httpPost('user/info/batchget?{ACCESS_TOKEN}', ['user_list' => $openid]);
  }

  /**
   * 获取公众号已创建的标签
   * @return array
   * @throws Exception
   */
  public function getTags(): array
  {
    return $this->httpGet('tags/get?{ACCESS_TOKEN}');
  }

  /**
   * 创建标签
   * @param string $name 标签名称
   * @return array
   * @throws Exception
   */
  public function createTag(string $name): array
  {
    return $this->httpPost('tags/create?{ACCESS_TOKEN}', ['tag' => ['name' => $name]]);
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
    return $this->httpPost('tags/update?{ACCESS_TOKEN}', ['tag' => ['id' => $id, 'name' => $name]]);
  }

  /**
   * 删除标签
   * @param int $id 标签id
   * @return array
   * @throws Exception
   */
  public function deleteTag(int $id): array
  {
    return $this->httpPost('tags/delete?{ACCESS_TOKEN}', ['tag' => ['id' => $id]]);
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
    return $this->httpPost('tags/members/batchtagging?{ACCESS_TOKEN}', $data);
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
    return $this->httpPost('tags/members/batchuntagging?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户身上的标签列表
   * @param $openid
   * @return array
   * @throws Exception
   */
  public function memberGetidlist($openid): array
  {
    return $this->httpPost('tags/getidlist?{ACCESS_TOKEN}', ['openid' => $openid]);
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
    return $this->httpPost('user/info/updateremark?{ACCESS_TOKEN}', $data);
  }

  /**
   * 发送客服消息
   * @param array $data 消息结构{"touser":"OPENID","msgtype":"news","news":{...}}
   * @return array
   * @throws Exception
   */
  public function sendCustomMessage(array $data): array
  {
    return $this->httpPost('message/custom/send?{ACCESS_TOKEN}', $data);
  }

  /**
   * 发送客服消息
   * @param array $data 消息结构{"touser":"OPENID","template_id":"模板ID","url":"模板跳转链接","data":{...}}
   * @return array
   * @throws Exception
   */
  public function sendTemplateMessage(array $data): array
  {
    return $this->httpPost('message/template/send?{ACCESS_TOKEN}', $data);
  }

  /**
   * 添加消息模板设置的行业信息
   * @return array
   * @throws Exception
   */
  public function getIndustry(): array
  {
    return $this->httpGet('template/get_industry?{ACCESS_TOKEN}');
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
    return $this->httpPost('template/api_add_template?{ACCESS_TOKEN}', $data);
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
    return $this->httpPost('template/del_private_template?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取消息模板
   * @return array
   * @throws Exception
   */
  public function templateMessage(): array
  {
    return $this->httpGet('template/get_all_private_template?{ACCESS_TOKEN}');
  }

  /**
   * 获取客服基本信息
   * @return array
   * @throws Exception
   */
  public function getKFList(): array
  {
    return $this->httpGet('customservice/getkflist?{ACCESS_TOKEN}');
  }

  /**
   * 获取在线客服基本信息
   * @return array
   * @throws Exception
   */
  public function getOnlineKFList(): array
  {
    return $this->httpGet('customservice/getonlinekflist?{ACCESS_TOKEN}');
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
    return $this->httpPost('material/batchget_material?{ACCESS_TOKEN}', $data);
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
    return $this->httpUpload('media/uploadimg?{ACCESS_TOKEN}', $filePath, 'media');
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
    return $this->httpUpload('media/upload?type=' . $type . '&{ACCESS_TOKEN}', $filePath, 'media');
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
      return $this->request($this->client, 'POST',
        'material/add_material?type=video&access_token=' . $this->access_token,
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
    return $this->httpUpload('material/add_material?type=' . $type . '&{ACCESS_TOKEN}', $filePath, 'media');
  }

  /**
   * 获取永久素材,临时素材
   * @param string $media_id 媒体文件id
   * @param string $path 下载文件存放目录
   * @param bool $temporary
   * @return array
   * @throws GuzzleException
   * @throws Exception
   */
  public function downloadMaterial(string $media_id, string $path, bool $temporary = true): array
  {
    if ($this->checkAuth() == '') return [];
    if ($temporary) $url = 'media/get?access_token=' . $this->access_token . '&media_id=' . $media_id;
    else $url = 'material/get_material?access_token=' . $this->access_token;

    $save_path = rtrim($path, '/');
    $basename = bin2hex(random_bytes(8));
    $downloadFile = $save_path . '/temp/' . sprintf('%s.%0.8s', $basename, 'dat');
    if (!is_dir($save_path . '/temp')) mkdir($save_path . '/temp', 0755, true);
    if ($temporary) $resp = $this->client->request('GET', $url, ['sink' => $downloadFile]);
    else $resp = $this->client->request('POST', $url, ['body' => json_encode(['media_id' => $media_id]), 'sink' => $downloadFile, 'headers' => ['Accept' => 'application/json']]);

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
    if ($temporary) $url = 'media/get?{ACCESS_TOKEN}&media_id=' . $media_id;
    else $url = 'material/get_material?{ACCESS_TOKEN}';

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
    return $this->httpPost('material/del_material?{ACCESS_TOKEN}', ['media_id' => $media_id]);
  }

  /**
   * 获取素材总数
   * @return array
   * @throws Exception
   */
  public function getMaterialCount(): array
  {
    return $this->httpGet('material/get_materialcount?{ACCESS_TOKEN}');
  }

  /**
   * 新建草稿
   * https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Add_draft.html
   * @throws Exception
   */
  public function draftAdd(array $data): array
  {
    return $this->httpPost('draft/add?{ACCESS_TOKEN}', $data);
  }

  /**
   * 更新草稿
   * https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Update_draft.html
   * @throws Exception
   */
  public function draftUpdate(array $data): array
  {
    return $this->httpPost('draft/update?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取取已添加草稿
   * @throws Exception
   */
  public function draftGet(string $media_id): array
  {
    return $this->httpPost('draft/get?{ACCESS_TOKEN}', ['media_id' => $media_id]);
  }

  /**
   * 删除草稿
   * @throws Exception
   */
  public function draftDelete(string $media_id): array
  {
    return $this->httpPost('draft/delete?{ACCESS_TOKEN}', ['media_id' => $media_id]);
  }

  /**
   * 获取草稿列表
   * $data = [
   *    "offset"=>0~,
   *    "count"=>1~20,
   *    "no_content"=>0|1
   * ]
   * https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Get_draft_list.html
   * @throws Exception
   */
  public function draftList(array $data): array
  {
    return $this->httpPost('draft/batchget?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取草稿总数
   * @return array
   * @throws Exception
   */
  public function draftCount(): array
  {
    return $this->httpGet('draft/count?{ACCESS_TOKEN}');
  }

  /**
   * 获取成功发布列表
   * @throws Exception
   */
  public function freePublishArticles(array $data): array
  {
    return $this->httpPost('freepublish/batchget?{ACCESS_TOKEN}', $data);
  }

}
