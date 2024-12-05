<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Created Time: 2024/11/26 上午11:14
 */

namespace Wanphp\Libray\Weixin\Traits;

use Exception;

trait HttpTrait
{
  use \Wanphp\Libray\Slim\HttpTrait;

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
    $headers = $this->headers;
    if ($referer != '') $headers['referer'] = $referer;
    return $this->request($this->client, 'GET', $url, ['headers' => $headers]);
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

    return $this->request($this->client, 'POST', $url,
      ['body' => json_encode($data, JSON_UNESCAPED_UNICODE), 'headers' => $this->headers]);
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

    return $this->request($this->client, 'POST', $url, ['multipart' => [
      [
        'name' => $field,
        'contents' => fopen($filePath, 'r'),
        'filename' => pathinfo($filePath, PATHINFO_BASENAME)
      ]
    ]]);
  }
}
