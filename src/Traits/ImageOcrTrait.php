<?php

namespace Wanphp\Libray\Weixin\Traits;

use Exception;

trait ImageOcrTrait
{
  use HttpTrait;

  /**
   * 图片智能裁剪
   * @param string $imagePath
   * @param string $ratios ratios参数为可选，如果为空，则算法自动裁剪最佳宽高比；如果提供多个宽高比，请以英文逗号“,”分隔，如：1,2.35，最多支持5个宽高比
   * @return array
   * @throws Exception
   */
  public function aiCrop(string $imagePath, string $ratios): array
  {
    return $this->httpUpload('https://api.weixin.qq.com/cv/img/aicrop?{ACCESS_TOKEN}', $imagePath, 'img', ['ratios' => $ratios]);
  }

  /**
   * 条码/二维码识别
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
}