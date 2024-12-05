<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Created Time: 2024/11/28 上午9:09
 */

namespace Wanphp\Libray\Weixin\Traits;

use Exception;

trait MiniProgramDataCubeTrait
{
  use HttpTrait;
  /**
   * 获取用户访问小程序日留存
   * @param $data ["begin_date" => "20170313","end_date" => "20170313"] 结束日期，限定查询1天数据，允许设置的最大值为昨日。
   * @return array
   * @throws Exception
   */
  public function getDailyRetain($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappiddailyretaininfo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序月留存
   * @param $data ["begin_date" : "20170201(为自然月第一天)",  "end_date" : "20170228(为自然月最后一天)"]
   * @return array
   * @throws Exception
   */
  public function getMonthlyRetain($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappidmonthlyretaininfo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序周留存
   * @param $data ["begin_date" : "20170306(为周一日期)",  "end_date" : "20170312(为周日日期)"]
   * @return array
   * @throws Exception
   */
  public function getWeeklyRetain($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappidweeklyretaininfo?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据概况
   * @param $data ["begin_date" => "20170313","end_date" => "20170313"] 结束日期，限定查询1天数据，允许设置的最大值为昨日。
   * @return array
   * @throws Exception
   */
  public function getDailySummary($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappiddailysummarytrend?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据日趋势
   * @param $data ["begin_date" => "20170313","end_date" => "20170313"] 结束日期，限定查询1天数据，允许设置的最大值为昨日。
   * @return array
   * @throws Exception
   */
  public function getDailyVisitTrend($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappiddailyvisittrend?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据月趋势(能查询到的最新数据为上一个自然月的数据)
   * @param $data ["begin_date" : "20170201(为自然月第一天)",  "end_date" : "20170228(为自然月最后一天)"]
   * @return array
   * @throws Exception
   */
  public function getMonthlyVisitTrend($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappidmonthlyvisittrend?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户访问小程序数据周趋势
   * @param $data ["begin_date" : "20170306(为周一日期)",  "end_date" : "20170312(为周日日期)"]
   * @return array
   * @throws Exception
   */
  public function getWeeklyVisitTrend($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappidweeklyvisittrend?{ACCESS_TOKEN}', $data);
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
    return $this->httpPost('/datacube/getweanalysisappiduserportrait?{ACCESS_TOKEN}', $data);
  }

  /**
   * 获取用户小程序访问分布数据
   * @param $data ["begin_date" => "20170313", "end_date" => "20170313"] 结束日期，限定查询 1 天数据，允许设置的最大值为昨日.
   * @return array
   * @throws Exception
   */
  public function getVisitDistribution($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappidvisitdistribution?{ACCESS_TOKEN}', $data);
  }

  /**
   * 访问页面。目前只提供按 page_visit_pv 排序的 top200。
   * @param $data ["begin_date" => "20170313", "end_date" => "20170313"] 结束日期，限定查询 1 天数据，允许设置的最大值为昨日.
   * @return array
   * @throws Exception
   */
  public function getVisitPage($data): array
  {
    return $this->httpPost('Goods/datacube/getweanalysisappidvisitpage?{ACCESS_TOKEN}', $data);
  }
}
