<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//用户集中计划任务的模型类，所有关于计划任务的业务模型及流程均在本文件实现 zhangdong 2019.03.14
class planModel extends Model
{
    private $currency_arr = [
        ['USD', 'CNY'],
        ['USD', 'KRW'],
        ['KRW', 'CNY'],
    ];
    //汇率查询接口请求key zhangdong 2019.08.22
    private $rateAppKey = '238e19c4fa586dbb478edd56f1388c25';

    //汇率查询接口请求url zhangdong 2019.08.22
    private $rateUrl = 'http://op.juhe.cn/onebox/exchange/currency';
    /**
     * description:定时任务-取消三天内未上传DD单的现货单
     * author:zhangdong
     * date : 2019.03.13
     */
    public function planCancelSpotOrder()
    {
        $log = logInfo('order/cancelSpot');
        $log->addInfo('取消三天内未上传DD的现货单任务 —— 开始执行');
        $soModel = new SpotOrderModel();
        //获取三天内未上传DD单的现货单
        $spotOrderList = $soModel -> getNeedCancelSpotOrder();
        if (count($spotOrderList) == 0) {
            $log->addInfo('没有要取消的现货单');
            return '结束执行';
        }
        //循环将对应的现货单状态改为已取消并将现货单推送至erp（从erp中取消）
        foreach ($spotOrderList as $value) {
            $spot_order_sn = trim($value);
            $log->addInfo('要取消的现货单 ' . $spot_order_sn);
            //修改现货单状态为已取消
            $status = 6;
            $updateRes = $soModel->updateStatus($spot_order_sn, $status);
            $log->addInfo('取消结果 ' . $updateRes);
            //将取消的订单推送至erp
            $pushRes = false;
            if($updateRes){
                $erpModel = new ErpApi();
                $pushRes = $erpModel->spot_order_push($spot_order_sn);
                $log->addInfo('ERP取消结果 ' . $pushRes);
            }
            if($pushRes){
                //取消成功后修改商品库存
                $log->addInfo('回滚库存');
                $spotGoodsModel = new SpotGoodsModel();
                $orderGoods = $spotGoodsModel->getSpotGoodsInfo($spot_order_sn);
                $gsModel = new GoodsSpecModel();
                foreach ($orderGoods as $value) {
                    $goodsNum = intval($value->goods_number);
                    $spec_sn = trim($value->spec_sn);
                    $log->addInfo('规格码:' . $spec_sn . ' - 回滚数量:' . $goodsNum);
                    $executeRes = $gsModel->releaseGoodsStock($spec_sn,$goodsNum);
                    $log->addInfo('回滚结果 ' . $executeRes);
                }
            }
        }
        $log->addInfo('取消三天内未上传DD的现货单任务 —— 结束执行');
        return true;
    }//end of function


    /**
     * description:定时任务-更新每日汇率
     * author:zhangdong
     * date : 2019.08.22
     */
    public function updateRate()
    {
        $log = logInfo('cron/updateRate');
        $log->addInfo('更新每日汇率计划任务 —— 开始执行');
        //当天日期
        $date = date('Y-m-d');
        //查询当天是否已保存当天的汇率，有则直接结束，无则保存当天的汇率
        //根据日期查询汇率
        $curDayRate = $this->getRateByDate($date);
        $log->addInfo('当天系统汇率查询结果-' . json_encode($curDayRate, JSON_UNESCAPED_UNICODE));
        //如果当天汇率不存在则保存当天此刻市场汇率
        if (is_null($curDayRate)) {
            $this->saveRate($date);
        }
        $log->addInfo('更新每日汇率计划任务 —— 任务结束');
        exit();
    }



    /**
     * description:汇率列表
     * editor:zongxing
     * date : 2019.07.23
     * return Array
     */
    public function getRateByDate($day_date)
    {
        $where = [
            ['day_time', $day_date],
        ];
        $field = ['usd_cny_rate','usd_krw_rate','krw_cny_rate',];
        $queryRes = DB::table('exchange_rate')->select($field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description 汇率新增
     * author zhangdong
     * date 2019.08.22
     */
    public function saveRate($day_time)
    {
        $log = logInfo('cron/updateRate');
        $currency_arr = $this->currency_arr;
        $log->addInfo('查询日期'. $day_time);
        $insertData['day_time'] = $day_time;
        foreach ($currency_arr as $k => $v) {
            $from = $v[0];
            $to = $v[1];
            switch (true){
                case $from == 'USD' && $to == 'CNY':
                    //美元兑换人民币汇率
                    $field = 'usd_cny_rate';
                    break;
                case $from == 'USD' && $to == 'KRW':
                    //美元兑换韩元汇率
                    $field = 'usd_krw_rate';
                    break;
                case $from == 'KRW' && $to == 'CNY':
                    //韩币兑换人民币汇率
                    $field = 'krw_cny_rate';
                    break;
                default:
                    //如果是其他的参数则直接跳过
                    $field = '';
                    break;
            }
            if($field == ''){
                continue;
            }
            //查询汇率
            $todayRate = $this->queryRate($from, $to);
            $log->addInfo('查询结果' . json_encode($todayRate, JSON_UNESCAPED_UNICODE));
            //保留四位小数点的汇率
            $rate = isset($todayRate['result'][0]['result']) ? floatval($todayRate['result'][0]['result']) : 0;
            //可能会因为网络原因导致接口异常，所以此处要做容错处理
            if (isset($todayRate['error_code']) && $todayRate['error_code'] !== 0) {
                //此处如果请求失败意味着整天汇率都不存在（定时任务一天只跑一次），
                //所以应该在访问首页的时候对当天汇率值做判断，如果汇率异常要第一时间提示管理员,并将汇率手动填入
                $log->addInfo('汇率请求失败-返回信息' . json_encode($todayRate, JSON_UNESCAPED_UNICODE));
            }
            $insertData[$field] = $rate;
        }
        $insertRes = DB::table('exchange_rate')->insert($insertData);
        $insertResDesc = $insertRes ? '汇率写入成功' : '汇率写入失败';
        $log->addInfo($insertResDesc);
        return $insertRes;

    }//end of function

    /**
     * description 汇率查询
     * author zhangdong
     * date 2019.08.22
     */
    public function queryRate($from, $to)
    {
        $appkey = $this->rateAppKey;
        $url = $this->rateUrl;
        $params = [
            'from' => $from,//转换汇率前的货币代码
            'to' => $to,//转换汇率成的货币代码
            'key' => $appkey,//应用APPKEY(应用详细页查询)
        ];
        $params = http_build_query($params);
        $queryRes = rateCurl($url, $params);
        $result = json_decode($queryRes, true);
        return $result;
    }
















}//end of class
