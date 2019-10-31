<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscountTypeInfoModel extends Model
{
    protected $table = 'discount_type_info as dti';

    //可操作字段
    protected $field = [
        'dti.id', 'dti.type_name', 'dti.method_id', 'dti.channels_id', 'dti.type_cat', 'dti.exw_type', 'dti.is_start',
        'dti.min', 'dti.max',
    ];

    //是否超额 1 超额 2 未超额 3 持平
    protected $is_excess = [
        'IS_YES' => 1,
        'IS_NOT' => 2,
        'IS_FLAT' => 3,
    ];

    protected $excess_desc = [
        1 => 'IS_YES',
        2 => 'IS_NOT',
        3 => 'IS_FLAT',
    ];


    /**
     * description:新增折扣类型
     * editor:zongxing
     * date : 2019.05.05
     * return Array
     */
    public function doAddDiscountType($param_info)
    {
        $min = isset($param_info['min']) ? intval($param_info['min']) : 0;
        $max = isset($param_info['max']) ? intval($param_info['max']) : 0;
        $insert_data = [
            'method_id' => intval($param_info['method_id']),
            'channels_id' => intval($param_info['channels_id']),
            'type_name' => trim($param_info['type_name']),
            'type_cat' => intval($param_info['type_cat']),
            'min' => $min,
            'max' => $max,
        ];
        $res = DB::table('discount_type_info')->insert($insert_data);
        return $res;
    }

    /**
     * description:获取折扣类型列表
     * editor:zongxing
     * date : 2019.05.05
     * return Array
     */
    public function getDiscountTypeList($param_info = [], $type_cat = 0)
    {
        $field = $this->field;
        $field = array_merge($field, ['pm.method_name', 'pc.channels_name']);
        $discount_type_obj = DB::table($this->table)
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'dti.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'dti.channels_id');
        if ($type_cat) {
            $discount_type_obj->where('dti.type_cat', $type_cat);
        }
        if (isset($param_info['type_id'])) {
            $type_id = intval($param_info['type_id']);
            $discount_type_obj->where('dti.id', $type_id);
        }
        if (isset($param_info['method_name'])) {
            $method_name = trim($param_info['method_name']);
            $discount_type_obj->where('pm.method_name', $method_name);
        }
        if (isset($param_info['channels_name'])) {
            $channels_name = trim($param_info['channels_name']);
            $discount_type_obj->where('pc.channels_name', $channels_name);
        }
        if (isset($param_info['method_id'])) {
            $method_id = intval($param_info['method_id']);
            $discount_type_obj->where('pm.id', $method_id);
        }
        if (isset($param_info['channels_id'])) {
            $channels_id = intval($param_info['channels_id']);
            $discount_type_obj->where('pc.id', $channels_id);
        }
        if (isset($param_info['type_name'])) {
            $type_name = trim($param_info['type_name']);
            $discount_type_obj->where('dti.type_name', $type_name);
        }
        if (isset($param_info['discount_type'])) {
            $discount_type = intval($param_info['discount_type']);
            $discount_type_obj->where('dti.discount_type', $discount_type);
        }
        $discount_type_list = $discount_type_obj->orderBy('dti.id', 'DESC')->get($field);
        $discount_type_list = objectToArrayZ($discount_type_list);
        return $discount_type_list;
    }

    /**
     * description:获取折扣类型列表
     * editor:zongxing
     * date : 2019.05.05
     * return Array
     */
    public function createDiscountTypeList($discount_type_info)
    {
        $discount_type_list = [];
        foreach ($discount_type_info as $k => $v) {
            $method_id = $v['method_id'];
            $method_name = $v['method_name'];
            $channels_id = $v['channels_id'];
            $channels_name = $v['channels_name'];
            $type_id = $v['id'];
            $type_name = $v['type_name'];
            $type_cat = $v['type_cat'];

            $channel_info = [
                'channels_id' => $channels_id,
                'channels_name' => $channels_name,
            ];
            $type_info = [
                'type_id' => $type_id,
                'type_name' => $type_name,
                'type_cat' => $type_cat,
            ];
            if (!isset($discount_type_list[$method_id])) {
                $tmp_channel_info = [];
                $tmp_channel_info[$channels_id] = $channel_info;
                $tmp_channel_info[$channels_id]['type_info'][] = $type_info;
                $discount_type_list[$method_id] = [
                    'method_id' => $method_id,
                    'method_name' => $method_name,
                    'channel_info' => $tmp_channel_info,
                ];
            } else {
                if (!isset($discount_type_list[$method_id]['channel_info'][$channels_id])) {
                    $channel_info['type_info'] = $type_info;
                    $discount_type_list[$method_id]['channel_info'][$channels_id] = $channel_info;
                } else {
                    $discount_type_list[$method_id]['channel_info'][$channels_id]['type_info'][] = $type_info;
                }
            }
        }

        foreach ($discount_type_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $discount_type_list[$k]['channel_info'] = array_values($v['channel_info']);
            }
        }
        $discount_type_list = array_values($discount_type_list);
        return $discount_type_list;
    }

    /**
     * description 获取当月真实的档位对应的各个品牌的折扣
     * author zongxing
     * date 2019.07.25
     */
    public function getRealMonthGear($channelId, $type_cat, $performance = 0, $type_id = [])
    {
        $where = [
            ['dti.channels_id', $channelId],
            ['dti.method_id', 34],//线上
            ['dti.type_cat', $type_cat],
        ];
        if ($performance) {
            $add_where = [
                ['dti.min', '<=', $performance],
                ['dti.max', '>=', $performance],
            ];
            $where = array_merge($where, $add_where);
        }

        $dti_obj = DB::table($this->table)->where($where);
        if (!empty($type_id)) {
            $dti_obj->whereIn('dti.id', $type_id);
        }
        $dti_info = $dti_obj->get();
        $dti_info = objectToArrayZ($dti_info);
        return $dti_info;
    }

    /**
     * description:编辑折扣档位
     * editor:zongxing
     * date : 2019.07.26
     * return Boolean
     */
    public function editDiscountType($param_info)
    {
        $type_id = intval($param_info['type_id']);
        $edit_field = trim($param_info['edit_field']);
        $status = intval($param_info['status']);

        $dti_where = [
            'id' => $type_id,
        ];
        $dti_update = [
            $edit_field => $status,
        ];
        $res = DB::transaction(function () use ($dti_where, $dti_update) {
            //discount_type_info表更新成本折扣数据
            $res = DB::table($this->table)->where($dti_where)->update($dti_update);
            return $res;
        });
        return $res;
    }

    /**
     * description:获取当月真实档位折扣
     * author zhangdong
     * date : 2019.07.22
     * modify zongxing 2019.07.25
     * $performance 当月业绩
     * $channelId 渠道id
     */
    public function getGearsPoints($performance, $param_info, $dtr_info)
    {
        //获取成本折扣对应的档位信息
        $cost_arr = $dtr_info['cost_arr'];
        $dti_info = $this->getDiscountTypeInfo($cost_arr, 2);
        $dti_detail_info = $dti_info[0];
        $channelId = intval($param_info['channels_id']);
        if ($dti_detail_info['max'] == 0) {
            $isExcess = 1;//如果为基础折扣,则上限、下限为0;
        } else {
            //根据当月业绩和所设定的采购档位判断是否超额
            $isExcess = $this->judgeIsExcess($dti_detail_info, $performance);
        }
        //获取当月真实档位折扣
        $gears_points = [];
        if ($isExcess == 1) {
            $type_cat = 2;
            $month_type_arr = $dtr_info['month_type_arr'];
            $type_info = $this->getRealMonthGear($channelId, $type_cat, $performance, $month_type_arr);
            if (!empty($type_info)) {
                $type_id[] = intval($type_info[0]['id']);
                $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
                $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
                $dt_model = new DiscountTypeModel();
                $gears_points = $dt_model->getMonthGearPoints($type_id, $start_date, $end_date);
            }
        }
        return $gears_points;
    }

    /**
     * description 根据某月业绩判断是否超额
     * author zhangdong
     * date 2019.07.23
     */
    public function judgeIsExcess($curMonthGear, $performance)
    {
        $min = floatval($curMonthGear['min']);
        $max = floatval($curMonthGear['max']);
        switch ($performance) {
            //未超额
            case $performance < $min:
                $isExcess = $this->is_excess['IS_NOT'];
                break;
            //持平
            case $performance >= $min && $performance < $max:
                $isExcess = $this->is_excess['IS_FLAT'];
                break;
            //超额
            case $performance >= $max:
                $isExcess = $this->is_excess['IS_YES'];
                break;
            default:
                $isExcess = $this->is_excess['IS_FLAT'];
        }
        return $isExcess;
    }

    /**
     * description 获取品牌活动对应的返点数据
     * author zongxing
     * date 2019.07.30
     */
    public function getBrandPoints($bg_list, $gmc_discount_list, $param_info, $profit_field, $er_list, $month_type_arr)
    {
        $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
        $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
        $channelId = intval($param_info['channels_id']);
        $diff_bg_list = $bg_list;
        foreach ($gmc_discount_list as $k => $v) {
            $spec_sn = $v['spec_sn'];
            if (isset($diff_bg_list[$spec_sn])) {
                unset($diff_bg_list[$spec_sn]); //去除批次中的特价sku信息
            }
        }
        //品牌活动业绩分为两种:月度和时段
        $brand_points = [];
        $dt_model = new DiscountTypeModel();
        //计算当月品牌活动(基础)
        if (in_array('brand_month_points', $profit_field)) {
            //获取当月真实品牌活动档位折扣
            $type_cat = 4;
            $type_info = $this->getRealMonthGear($channelId, $type_cat, 0, $month_type_arr);
            if (!empty($type_info)) {
                $type_id = [];
                foreach ($type_info as $k => $v) {
                    $type_id[] = intval($v['id']);
                }
                $month_base_points = $dt_model->getMonthGearPoints($type_id, $start_date, $end_date);
                $brand_points['month_points'] = $month_base_points;
            }
        }
        //计算当月品牌活动(追加)
        if (in_array('brand_gears_points', $profit_field)) {
            //获取当月品牌活动对应的品牌信息
            $discount_model = new DiscountModel();
            $brand_info = $discount_model->getMonthBrandInfo($param_info, $month_type_arr);
            if (!empty($brand_info)) {
                //计算当月商品业绩
                $performance_arr = $this->createBrandPerformance($diff_bg_list, $er_list, $brand_info, $param_info);
                foreach ($performance_arr as $m => $n) {
                    $performance = $n['performance'];
                    $type_id_arr = $n['type_info'];//折扣类型数组
                    //获取当月品牌活动档位折扣
                    $type_cat = 9;
                    $type_info = $this->getRealMonthGear($channelId, $type_cat, $performance, $type_id_arr);
                    if (!empty($type_info)) {
                        $type_id = [];
                        foreach ($type_info as $k => $v) {
                            $type_id[] = intval($v['id']);
                        }
                        $month_gears_points = $dt_model->getMonthGearPoints($type_id, $start_date, $end_date);
                        if (!empty($brand_points['month_points'])) {
                            foreach ($month_gears_points as $k => $v) {
                                if (isset($brand_points['month_points'][$k])) {
                                    $tmp_points = $v;
                                    $brand_points['month_points'][$k] += $tmp_points;
                                }
                            }
                        } else {
                            $brand_points['month_points'] = $month_gears_points;
                        }
                    }
                }
            }
        }
        //获取品牌活动时段折扣
        if (in_array('brand_period_points', $profit_field)) {
            $type_cat = 4;
            $time_gear_info = $dt_model->getTimeMonthGear($channelId, $type_cat, $start_date, $end_date, $month_type_arr);
            $time_gear_list = [];
            foreach ($time_gear_info as $k => $v) {
                $type_id = intval($v['type_id']);
                $start_date = trim($v['start_date']);
                $end_date = trim($v['end_date']);
                $min = floatval($v['min']);
                $max = floatval($v['max']);
                $pin_str = $type_id . '_' . $start_date . '_' . $end_date;
                if (!isset($time_gear_list[$pin_str])) {
                    $time_gear_list[$pin_str]['type_info'] = [
                        'type_id' => $type_id,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'min' => $min,
                        'max' => $max,
                    ];
                }
                $brand_id = intval($v['brand_id']);
                $time_gear_list[$pin_str]['type_list'][$brand_id] = $v;
            }
            //计算各个时段对应的业绩
            $time_arr_info = array_keys($time_gear_list);
            $settle_date_type = $param_info['settle_date_type'];
            $time_performance = [];
            foreach ($time_arr_info as $m => $n) {
                $time_arr = explode('_', $n);
                $start_time = Carbon::parse($time_arr[1]);
                $end_time = Carbon::parse($time_arr[2]);
                $tmp_performance = 0;
                foreach ($diff_bg_list as $k => $v) {
                    foreach ($v as $k1 => $v1) {
                        $payment_str = $settle_date_type == 1 ? 'delivery_time' : 'buy_time';
                        $payment_time = trim($v1[$payment_str]);
                        $foolean = Carbon::parse($payment_time)->between($start_time, $end_time);
                        if ($foolean) {
                            $margin_payment = intval($v1['margin_payment']);//毛利结算方式：1，美金原价；2，lvip折扣
                            $payment_str = $margin_payment == 1 ? 'spec_price' : 'lvip_price';
                            $pay_price = floatval($v1[$payment_str]);
                            $day_buy_num = intval($v1['day_buy_num']);
                            $tmp_performance += $pay_price * $day_buy_num;
                        }
                    }
                }
                $tmp_performance = $tmp_performance / 10000;
                $min = floatval($time_gear_list[$n]['type_info']['min']);
                $max = floatval($time_gear_list[$n]['type_info']['max']);
                if ($min <= $tmp_performance && $tmp_performance <= $max) {
                    $time_performance[$n] = $time_gear_list[$n]['type_list'];
                }
            }
            $brand_points['time_points'] = $time_performance;
        }
        return $brand_points;
    }

    /**
     * description 获取品牌HDW特别活动返点
     * author zongxing
     * date 2019.08.12
     */
    public function getBrandHdwPoints($param_info, $month_type_arr)
    {
        $channelId = intval($param_info['channels_id']);
        //获取HDW特别活动返点
        $brand_hdw_points = [];
        $type_cat = 10;
        $type_info = $this->getRealMonthGear($channelId, $type_cat, 0, $month_type_arr);
        if (!empty($type_info)) {
            $type_id = [];
            foreach ($type_info as $k => $v) {
                $type_id[] = intval($v['id']);
            }
            $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
            $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
            $dt_model = new DiscountTypeModel();
            $brand_hdw_points = $dt_model->getMonthGearPoints($type_id, $start_date, $end_date);
        }
        return $brand_hdw_points;
    }

    /**
     * description 计算品牌活动当月业绩
     * author zongxing
     * date 2019.08.12
     */
    public function createBrandPerformance($diff_bg_list, $er_info, $brand_info, $param_info)
    {
        $settleDateType = intval($param_info['settle_date_type']);
        $time_str = $settleDateType == 1 ? 'delivery_time' : 'buy_time';
        foreach ($brand_info as $k => $v) {
            $brand_id_str = substr($k, 0, -1);
            $brand_id_arr = explode(',', $brand_id_str);//品牌数组
            $performance = 0;
            foreach ($diff_bg_list as $k1 => $v1) {
                foreach ($v1 as $k2 => $v2) {
                    $brand_id = intval($v2['brand_id']);//品牌id
                    if (in_array($brand_id, $brand_id_arr)) {
                        $margin_payment = intval($v2['margin_payment']);//毛利结算方式：1，美金原价；2，lvip折扣
                        $payment_str = $margin_payment == 1 ? 'spec_price' : 'lvip_price';
                        $pay_price = floatval($v2[$payment_str]);
                        $day_buy_num = intval($v2['day_buy_num']);
                        $tmp_performance = $pay_price * $day_buy_num;
                        $margin_currency = intval($v2['margin_currency']);//品牌活动档位衡量币种：1，美金原价；2，韩币
                        $day_time = trim($v2[$time_str]);//日期
                        $usd_krw_rate = isset($er_list[$day_time]['usd_krw_rate']) ? $er_list[$day_time]['usd_krw_rate']
                            : '1216.71';//这里取美金对韩币汇率,如果定时任务没有获取到,就取默认值
                        if ($margin_currency == 2) {
                            $tmp_performance = $tmp_performance * $usd_krw_rate / 100000000;
                        } else {
                            $tmp_performance = $performance / 10000;
                        }
                        $performance += $tmp_performance;
                    }
                }
            }
            $brand_info[$k]['performance'] = $performance;
        }
        return $brand_info;
    }

    /**
     * description 获取渠道档位折扣数
     * author zhangdong
     * date 2019.07.23
     */
    public function getChannelGearNum($param_info)
    {
        $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
        $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
        $channelId = intval($param_info['channels_id']);
        $where = [
            ['start_date', '<=', $start_date],
            ['end_date', '>=', $end_date],
            ['channels_id', '=', $channelId],
            ['type_cat', '=', 2],
        ];
        $gears_num = DB::table('discount_type as dt')
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->where($where)
            ->distinct()->get(['dti.id', 'dti.type_name'])->count();
        return $gears_num;
    }

    /**
     * description:获取折扣类型信息
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function getDiscountTypeInfo($cost_arr, $type_cat)
    {
        $field = $this->field;
        $dti_obj = DB::table('discount_type_info as dti')->select($field);
        $dti_obj->whereIn('dti.id', $cost_arr);
        $dti_info = $dti_obj->where('dti.type_cat', $type_cat)->get();
        $dti_info = objectToArrayZ($dti_info);
        return $dti_info;
    }


}//end of class
