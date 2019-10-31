<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProfitGoodsModel extends Model
{
    public $table = 'profit_goods as pg';
    private $field = [
        'pg.id', 'pg.profit_sn', 'pg.spec_sn', 'pg.goods_name', 'pg.real_purchase_sn', 'pg.margin_payment', 'pg.spec_price',
        'pg.pay_price', 'pg.lvip_price', 'pg.day_buy_num', 'pg.channel_discount', 'pg.real_discount',
        'pg.gears_points', 'pg.high_points', 'pg.low_points', 'pg.brand_month_points',
        'pg.brand_period_points', 'pg.team_month_points', 'pg.team_period_points', 'pg.brand_gears_points',
        'pg.hdw_points', 'pg.team_add_points',
        'pg.create_time',
    ];

    /**
     * description:生成后台毛利数据
     * author:zhangdong
     * date : 2019.07.19
     * modify zongxing 2019.07.25
     */
    public function generalProfit($profitGoodsInfo, $param_info)
    {
        $settleDateType = intval($param_info['settle_date_type']);
        $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
        $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
        $channelId = intval($param_info['channels_id']);
        $formula_mode = intval($param_info['formula_mode']);//计算方式,按照系统统计还是按照团长指定
        //获取汇率
        $param = [
            'start_time' => $start_date,
            'end_time' => $end_date,
        ];
        $er_model = new ExchangeRateModel();
        $er_info = $er_model->exchangeRateList($param);
        $er_list = [];
        foreach ($er_info as $k => $v) {
            $er_list[$v['day_time']] = $v;
        }
        $param = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'channels_id' => $channelId,
        ];
        //获取选择时间对应的生成毛利所需配置
        $dtr_model = new DiscountTypeRecordModel();
        $dtr_info = $dtr_model->getDiscountTypeRecordInfo($param);
        if (empty($dtr_info)) {
            return ['code' => '1101', 'msg' => '您选择的时间段中未进行折扣配置,请先配置'];
        }
        //获取采购金额数据
        $cc_model = new CardConsumeModel();
        $cc_info = $cc_model->getCardConsumeList($param);
        $card_return_points = 0;
        $recharge_points = 0;
        foreach ($cc_info as $k => $v) {
            $consume_type = intval($v['consume_type']);
            $consume_money = floatval($v['consume_money']);
            $return_rate = floatval($v['return_rate']);
            $tmp_points = $consume_money * $return_rate;
            if ($consume_type == 1) {
                $card_return_points += $tmp_points;
            } else {
                $recharge_points += $tmp_points;
            }
        }
        //获取选择公式所包含的数项
        $formula_sn = trim($param_info['formula_sn']);//毛利计算公式编号
        $param['is_profit'] = 1;
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param);
        $param['formula_sn'] = $formula_sn;
        $pf_model = new ProfitFormulaModel();
        $pf_list = $pf_model->getProfitFormulaList($param);
        $profit_field = [];
        foreach ($pf_list as $k => $v) {
            $cat_info = explode(',', $v['cat_info']);
            foreach ($dc_list as $k1 => $v1) {
                $dc_id = $v1['id'];
                $cat_code = $v1['cat_code'];
                if (in_array($dc_id, $cat_info)) {
                    $profit_field[] = $cat_code;
                }
            }
        }
        //生成毛利单号
        $profitModel = new ProfitModel();
        $profitSn = $profitModel->generalProfitSn();
        //组装毛利基本信息
        $profitData = [
            'profit_sn' => $profitSn,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'settle_date_type' => $settleDateType,
            'channel_id' => $channelId,
            'formula_sn' => $formula_sn,
            'card_return_points' => $card_return_points,
        ];
        //计算当月业绩
        $performance = 0;
        $bg_list = [];
        $time_str = $settleDateType == 1 ? 'delivery_time' : 'buy_time';
        foreach ($profitGoodsInfo as $k => $v) {
            $margin_payment = intval($v['margin_payment']);//毛利结算方式：1，美金原价；2，lvip折扣
            $payment_str = $margin_payment == 1 ? 'spec_price' : 'lvip_price';
            $lvip_price = floatval($v[$payment_str]);
            $day_buy_num = intval($v['day_buy_num']);
            $tmp_performance = $lvip_price * $day_buy_num;
            $margin_currency = intval($v['margin_currency']);//品牌活动档位衡量币种：1，美金原价；2，韩币
            $day_time = trim($v[$time_str]);//日期
            $usd_krw_rate = isset($er_list[$day_time]['usd_krw_rate']) ? $er_list[$day_time]['usd_krw_rate']
                : '1216.71';//这里取美金对韩币汇率,如果定时任务没有获取到,就取默认值
            if ($margin_currency == 2) {
                $tmp_performance = $tmp_performance * $usd_krw_rate / 100000000;
            } else {
                $tmp_performance = $tmp_performance / 10000;
            }
            $performance += $tmp_performance;
            $spec_sn = trim($v['spec_sn']);
            $real_purchase_sn = trim($v['real_purchase_sn']);
            if (!isset($bg_list[$spec_sn][$real_purchase_sn])) {
                $v['high_points'] = $v['low_points'] = 0;
                $bg_list[$spec_sn][$real_purchase_sn] = $v;
            }
        }
        $dti_model = new DiscountTypeInfoModel();
        //计算品牌档位追加折扣
        $gearsPointsInfo = [];
        if (in_array('gears_points', $profit_field)) {
            if ($formula_mode == 1) {//系统计算
                $gearsPointsInfo = $dti_model->getGearsPoints($performance, $param_info, $dtr_info);
            } elseif ($formula_mode == 2) {//团长指定
                if (empty($param_info['brand_type_id'])) {
                    return ['code' => '1102', 'msg' => '指定档位不能为空'];
                }
                $brand_type_id = intval($param_info['brand_type_id']);
                $brand_type_arr[] = $brand_type_id;
                $dt_model = new DiscountTypeModel();
                $gearsPointsInfo = $dt_model->getMonthGearPoints($brand_type_arr, $start_date, $end_date);
            }
        }
        //计算商品档位追加折扣
        if (in_array('goods_gears_points', $profit_field)) {
            $goodsGearsPointsInfo = [];
            if ($formula_mode == 1) {//系统计算,这种情况先不考虑
            } elseif ($formula_mode == 2) {//团长指定
                if (!isset($param_info['goods_type_id'])) {
                    return ['code' => '1103', 'msg' => '指定档位不能为空'];
                }
                //获取成本折扣对应的档位信息
                $cost_arr = $dtr_info['cost_arr'];
                $dti_info = $dti_model->getDiscountTypeInfo($cost_arr, 12);
                $goods_type_id = intval($param_info['goods_type_id']);
                if(!empty($dti_info)){
                    $dti_info = $dti_info[0];
                    $type_id = intval($dti_info['id']);
                    if ($type_id != $goods_type_id) {//如果团长指定的档位和系统设置的成本折扣档位一致,则不存在档位追加
                        $goods_type_id = $type_id;
                    }
                }
                $goods_type_arr[] = $goods_type_id;
                $gmc_model = new GmcDiscountModel();
                $goodsGearsPointsInfo = $gmc_model->getMonthGearPoints($goods_type_arr, $param_info);
            }
            if (!empty($goodsGearsPointsInfo)) {
                foreach ($goodsGearsPointsInfo as $k => $v) {
                    $spec_sn = $v['spec_sn'];
                    $discount = floatval($v['discount']);
                    $start_date = strtotime($v['start_date']);
                    $end_date = strtotime($v['end_date']);
                    $goods_discount = 1 - $discount;
                    if (isset($bg_list[$spec_sn])) {
                        $goods_info = $bg_list[$spec_sn];
                        foreach ($goods_info as $k => $v) {
                            $channel_discount = floatval($v['channel_discount']);
                            $real_purchase_sn = trim($v['real_purchase_sn']);
                            //需要考虑到如果是单个商品如果存在多种追加
                            $buy_time = strtotime(trim($v['buy_time']));
                            if ($channel_discount > $goods_discount && in_array('low_points', $profit_field) &&
                                $buy_time >= $start_date && $buy_time <= $end_date
                            ) {
                                $bg_list[$spec_sn][$real_purchase_sn]['low_points'] += $discount;
                            } elseif ($channel_discount < $goods_discount && in_array('high_points', $profit_field) &&
                                $buy_time >= $start_date && $buy_time <= $end_date
                            ) {
                                $bg_list[$spec_sn][$real_purchase_sn]['high_points'] += $discount;
                            }
                        }
                    }
                }
            }
        }
        //获取商品追加返点信息
        $gmc_model = new GmcDiscountModel();
        $type_cat_arr = [6, 7];
        $gmc_discount_list = $gmc_model->gmcDiscountList(null, $param_info, null, $type_cat_arr);
        //计算高价sku和低价sku返点数据
        if ((in_array('high_points', $profit_field) || in_array('low_points', $profit_field)) && !empty($gmc_discount_list)) {
            foreach ($gmc_discount_list as $k => $v) {
                $spec_sn = $v['spec_sn'];
                $type_cat = $v['type_cat'];
                $start_date = strtotime($v['start_date']);
                $end_date = strtotime($v['end_date']);
                $discount = floatval($v['discount']);
                $goods_discount = 1 - $discount;
                if (isset($bg_list[$spec_sn])) {
                    $goods_info = $bg_list[$spec_sn];
                    foreach ($goods_info as $k => $v) {
                        $channel_discount = floatval($v['channel_discount']);
                        $real_purchase_sn = trim($v['real_purchase_sn']);
                        $buy_time = strtotime(trim($v['buy_time']));
                        //$diff_discount = $channel_discount - $goods_discount;
                        //需要考虑到如果是单个商品如果存在多种追加
                        if ($type_cat == 7 && in_array('low_points', $profit_field) &&
                            $buy_time >= $start_date && $buy_time <= $end_date
                        ) {
                            $final_discount = (1 - $channel_discount) + $discount;
                            $bg_list[$spec_sn][$real_purchase_sn]['low_points'] += $final_discount;
                        } elseif ($channel_discount < $goods_discount && $type_cat == 6 &&
                            in_array('high_points', $profit_field) && $buy_time >= $start_date && $buy_time <= $end_date
                        ) {
                            $bg_list[$spec_sn][$real_purchase_sn]['high_points'] += $discount;
                        }
                    }
                }
            }
        }
        //获取品牌活动对应的返点数据
        $month_type_arr = $dtr_info['month_type_arr'];
        $dti_model = new DiscountTypeInfoModel();
        $brand_points = $dti_model->getBrandPoints($bg_list, $gmc_discount_list, $param_info, $profit_field, $er_list,
            $month_type_arr);
        //计算高价sku和低价sku返点数据
        $brand_hdw_points = [];
        if (in_array('hdw_points', $profit_field)) {
            $brand_hdw_points = $dti_model->getBrandHdwPoints($param_info, $month_type_arr);
        }
        //组装毛利商品表数据
        $profit_goods_info = $this->createProfitGoodsInfo($profitSn, $bg_list, $gearsPointsInfo, $brand_points,
            $param_info, $brand_hdw_points, $gmc_discount_list);
        $insertRes = DB::transaction(function () use ($profitData, $profit_goods_info) {
            //毛利表
            DB::table('profit')->insert($profitData);
            //毛利批次商品表
            $res = DB::table('profit_goods')->insert($profit_goods_info);
            return $res;
        });
        return $insertRes;
    }

    /**
     * description 组装毛利商品表数据
     * author zongxing
     * date 2019.07.30
     * return Array
     */
    public function createProfitGoodsInfo_stop($profitSn, $bg_list, $gearsPointsInfo, $brand_points, $param_info,
                                               $brand_hdw_points, $gmc_discount_list)
    {
        //组装特殊商品信息
        $gmc_spec_info = [];
        foreach ($gmc_discount_list as $k => $v) {
            $gmc_spec_info[] = $v['spec_sn'];
        }
        $settleDateType = intval($param_info['settle_date_type']);
        //获取公式信息
        $cf_model = new CatFormulaModel();
        $cf_info = $cf_model->getCatFormulaList($param_info);
        //获取基础参数
        $pp_model = new ProfitParamModel();
        $pp_info = $pp_model->getProfitParamList();
        $cf_list = [];
        foreach ($cf_info as $k => $v) {
            $cat_code = $v['cat_code'];
            $param_code_info = $v['param_code_info'];
            $cf_list[$cat_code] = $param_code_info;
        }
        //获取渠道团队追加热品、非热品对应的返点
        $channels_id = intval($param_info['channels_id']);
        $pc_model = new PurchaseChannelModel();
        $pc_info = $pc_model->getChannelInfo($channels_id);
        $pc_info = objectToArrayZ($pc_info);
        $pc_team_add_points = $pc_info['team_add_points'];
        $profit_goods_info = [];
        foreach ($bg_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $base_points = $gears_points = $real_discount = $low_points = $high_points = $brand_month_points =
                $brand_period_points = $team_add_points = 0;
                $spec_sn = trim($v1['spec_sn']);
                $spec_price = floatval($v1['spec_price']);
                $pay_price = floatval($v1['pay_price']);
                $lvip_price = floatval($v1['lvip_price']);
                $brand_id = floatval($v1['brand_id']);
                $channel_discount = floatval($v1['channel_discount']);
                $real_discount = $channel_discount;
                $margin_payment = intval($v1['margin_payment']);
                $day_buy_num = intval($v1['day_buy_num']);
                //HDW特殊活动,如果这个活动存在,则不参与其他活动
                if (isset($brand_hdw_points[$brand_id]) && isset($cf_list['hdw_points'])) {
                    $hdw_points = floatval($brand_hdw_points[$brand_id]);
                    $v1['hdw_points'] = $hdw_points;
                    $hdw_points_code = $cf_list['hdw_points'];
                    $hdw_points = $this->changeFormula($pp_info, $hdw_points_code, $v1);
                    $profit_goods_info[] = [
                        'profit_sn' => $profitSn,
                        'spec_sn' => $spec_sn,
                        'goods_name' => $v1['goods_name'],
                        'real_purchase_sn' => $v1['real_purchase_sn'],
                        'margin_payment' => $margin_payment,
                        'spec_price' => $spec_price,
                        'pay_price' => $pay_price,
                        'lvip_price' => $lvip_price,
                        'day_buy_num' => $day_buy_num,
                        'channel_discount' => $channel_discount,
                        'real_discount' => $real_discount,
                        'base_points' => $base_points,
                        'gears_points' => $gears_points,
                        'low_points' => $low_points,
                        'high_points' => $high_points,
                        'brand_month_points' => $brand_month_points,
                        'brand_period_points' => $brand_period_points,
                        'hdw_points' => $hdw_points,
                        'team_add_points' => $team_add_points,
                    ];
                    continue;
                }
                $base_points_code = $cf_list['base_points'];
                $base_points = $this->changeFormula($pp_info, $base_points_code, $v1);
                //计算档位追加点
                if (isset($gearsPointsInfo[$brand_id]) && isset($cf_list['gears_points'])) {
                    $brand_discount = floatval($gearsPointsInfo[$brand_id]);
                    if ($channel_discount >= $brand_discount) {
                        $real_discount = $brand_discount;
                        $v1['real_discount'] = $real_discount;
                        $gears_points_code = $cf_list['gears_points'];
                        $gears_points = $this->changeFormula($pp_info, $gears_points_code, $v1);
                    }
                }
                //计算品牌活动(月度)
                if (isset($brand_points['month_points'][$brand_id]) && isset($cf_list['brand_month_points']) &&
                    !in_array($spec_sn, $gmc_spec_info)//如果商品存在特殊折扣,则不计算品牌活动
                ) {
                    $brand_month_points = floatval($brand_points['month_points'][$brand_id]);
                    $real_discount -= $brand_month_points;
                    $v1['brand_month_points'] = $brand_month_points;
                    $bm_points_code = $cf_list['brand_month_points'];
                    $brand_month_points = $this->changeFormula($pp_info, $bm_points_code, $v1);//后期如果需要改为详细的，则将品牌活动变为数组
                }
                //计算品牌活动(时段)
                $delivery_time = trim($v1['delivery_time']);
                $buy_time = trim($v1['buy_time']);
                $payment_time = $settleDateType == 1 ? $delivery_time : $buy_time;
                if (isset($brand_points['time_points']) && isset($cf_list['brand_period_points']) &&
                    !in_array($spec_sn, $gmc_spec_info)//如果商品存在特殊折扣,则不计算品牌活动
                ) {
                    foreach ($brand_points['time_points'] as $m => $n) {
                        $time_arr = explode('_', $m);
                        $start_time = Carbon::parse($time_arr[1]);
                        $end_time = Carbon::parse($time_arr[2]);
                        $foolean = Carbon::parse($payment_time)->between($start_time, $end_time);//判断批次日期是否存在品牌活动
                        if ($foolean) {
                            if (isset($brand_points['month_points'][$brand_id])) {//判断商品是否存在品牌活动
                                $brand_period_points = floatval($brand_points['month_points'][$brand_id]);
                                $real_discount -= $brand_period_points;
                                $v1['brand_period_points'] = $brand_period_points;
                                $bp_points_code = $cf_list['brand_period_points'];
                                $brand_period_points = $this->changeFormula($pp_info, $bp_points_code, $v1);
                            }
                        }
                    }
                }
                //计算团队追加
                if ($pc_team_add_points != 0 && isset($cf_list['team_add_points'])) {
                    $v1['team_add_points'] = $pc_team_add_points;
                    $real_discount -= $pc_team_add_points;
                    $team_points_code = $cf_list['team_add_points'];
                    $team_add_points = $this->changeFormula($pp_info, $team_points_code, $v1);
                }

                //计算高低价sku
                $low_points = floatval($v1['low_points']);
                if ($low_points > 0 && isset($cf_list['low_points'])) {
                    $real_discount = 1 - $low_points;
                    $low_points_code = $cf_list['low_points'];
                    $low_points = $this->changeFormula($pp_info, $low_points_code, $v1);
                }
                $high_points = floatval($v1['high_points']);
                if ($high_points > 0 && isset($cf_list['high_points'])) {
                    $real_discount = 1 - $high_points;
                    $high_points_code = $cf_list['high_points'];
                    $high_points = $this->changeFormula($pp_info, $high_points_code, $v1);
                }
                $profit_goods_info[] = [
                    'profit_sn' => $profitSn,
                    'spec_sn' => $v1['spec_sn'],
                    'goods_name' => $v1['goods_name'],
                    'real_purchase_sn' => $v1['real_purchase_sn'],
                    'margin_payment' => $margin_payment,
                    'spec_price' => $spec_price,
                    'pay_price' => $pay_price,
                    'lvip_price' => $lvip_price,
                    'day_buy_num' => $day_buy_num,
                    'channel_discount' => $channel_discount,
                    'real_discount' => $real_discount,
                    'base_points' => $base_points,
                    'gears_points' => $gears_points,
                    'low_points' => $low_points,
                    'high_points' => $high_points,
                    'brand_month_points' => $brand_month_points,
                    'brand_period_points' => $brand_period_points,
                    'team_add_points' => $team_add_points,
                    'hdw_points' => 0,
                ];
            }
        }
        return $profit_goods_info;
    }

    public function createProfitGoodsInfo($profitSn, $bg_list, $gearsPointsInfo, $brand_points, $param_info,
                                          $brand_hdw_points, $gmc_discount_list)
    {
        $type_cat = intval($param_info['type_cat']);
        //组装特殊商品信息
        $gmc_spec_info = [];
        foreach ($gmc_discount_list as $k => $v) {
            $gmc_spec_info[] = $v['spec_sn'];
        }
        $settleDateType = intval($param_info['settle_date_type']);
        //获取公式信息
        $cf_model = new CatFormulaModel();
        $cf_info = $cf_model->getCatFormulaList($param_info);
        //获取基础参数
        $pp_model = new ProfitParamModel();
        $pp_info = $pp_model->getProfitParamList();
        $cf_list = [];
        foreach ($cf_info as $k => $v) {
            $cat_code = $v['cat_code'];
            $param_code_info = $v['param_code_info'];
            $cf_list[$cat_code] = $param_code_info;
        }
        //获取渠道团队追加热品、非热品对应的返点
        $channels_id = intval($param_info['channels_id']);
        $pc_model = new PurchaseChannelModel();
        $pc_info = $pc_model->getChannelInfo($channels_id);
        $pc_info = objectToArrayZ($pc_info);
        $pc_team_add_points = $pc_info['team_add_points'];
        $profit_goods_info = [];
        foreach ($bg_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $base_points = $gears_points = $real_discount = $low_points = $high_points = $brand_month_points =
                $brand_period_points = $team_add_points = 0;
                $spec_sn = trim($v1['spec_sn']);
                $spec_price = floatval($v1['spec_price']);
                $pay_price = floatval($v1['pay_price']);
                $lvip_price = floatval($v1['lvip_price']);
                $brand_id = floatval($v1['brand_id']);
                $channel_discount = floatval($v1['channel_discount']);
                $real_discount = $channel_discount;
                $margin_payment = intval($v1['margin_payment']);
                $day_buy_num = intval($v1['day_buy_num']);
                //HDW特殊活动,如果这个活动存在,则不参与其他活动
                if (isset($brand_hdw_points[$brand_id]) && isset($cf_list['hdw_points'])) {
                    $hdw_points = floatval($brand_hdw_points[$brand_id]);
                    $v1['hdw_points'] = $hdw_points;
                    $hdw_points_code = $cf_list['hdw_points'];
                    $hdw_points = $this->changeFormula($pp_info, $hdw_points_code, $v1);
                    $profit_goods_info[] = [
                        'profit_sn' => $profitSn,
                        'spec_sn' => $spec_sn,
                        'goods_name' => $v1['goods_name'],
                        'real_purchase_sn' => $v1['real_purchase_sn'],
                        'margin_payment' => $margin_payment,
                        'spec_price' => $spec_price,
                        'pay_price' => $pay_price,
                        'lvip_price' => $lvip_price,
                        'day_buy_num' => $day_buy_num,
                        'channel_discount' => $channel_discount,
                        'real_discount' => $real_discount,
                        'base_points' => $base_points,
                        'gears_points' => $gears_points,
                        'low_points' => $low_points,
                        'high_points' => $high_points,
                        'brand_month_points' => $brand_month_points,
                        'brand_period_points' => $brand_period_points,
                        'hdw_points' => $hdw_points,
                        'team_add_points' => $team_add_points,
                    ];
                    continue;
                }
                $base_points_code = $cf_list['base_points'];
                $base_points = $this->changeFormula($pp_info, $base_points_code, $v1);
                //计算档位追加点
                if (isset($gearsPointsInfo[$brand_id]) && isset($cf_list['gears_points'])) {
                    $brand_discount = floatval($gearsPointsInfo[$brand_id]);
                    if ($channel_discount >= $brand_discount && $type_cat == 2) {
                        $real_discount = $brand_discount;
                        $v1['real_discount'] = $real_discount;
                    } elseif ($type_cat == 14) {
                        $real_discount = $channel_discount - $brand_discount;
                        $v1['gears_add_points'] = $brand_discount;
                    }
                    $gears_points_code = $cf_list['gears_points'];
                    $gears_points = $this->changeFormula($pp_info, $gears_points_code, $v1);
                }
                //计算品牌活动(月度)
                if (isset($brand_points['month_points'][$brand_id]) && isset($cf_list['brand_month_points']) &&
                    !in_array($spec_sn, $gmc_spec_info)//如果商品存在特殊折扣,则不计算品牌活动
                ) {
                    $brand_month_points = floatval($brand_points['month_points'][$brand_id]);
                    $real_discount -= $brand_month_points;
                    $v1['brand_month_points'] = $brand_month_points;
                    $bm_points_code = $cf_list['brand_month_points'];
                    $brand_month_points = $this->changeFormula($pp_info, $bm_points_code, $v1);//后期如果需要改为详细的，则将品牌活动变为数组
                }
                //计算品牌活动(时段)
                $delivery_time = trim($v1['delivery_time']);
                $buy_time = trim($v1['buy_time']);
                $payment_time = $settleDateType == 1 ? $delivery_time : $buy_time;
                if (isset($brand_points['time_points']) && isset($cf_list['brand_period_points']) &&
                    !in_array($spec_sn, $gmc_spec_info)//如果商品存在特殊折扣,则不计算品牌活动
                ) {
                    foreach ($brand_points['time_points'] as $m => $n) {
                        $time_arr = explode('_', $m);
                        $start_time = Carbon::parse($time_arr[1]);
                        $end_time = Carbon::parse($time_arr[2]);
                        $foolean = Carbon::parse($payment_time)->between($start_time, $end_time);//判断批次日期是否存在品牌活动
                        if ($foolean) {
                            if (isset($brand_points['month_points'][$brand_id])) {//判断商品是否存在品牌活动
                                $brand_period_points = floatval($brand_points['month_points'][$brand_id]);
                                $real_discount -= $brand_period_points;
                                $v1['brand_period_points'] = $brand_period_points;
                                $bp_points_code = $cf_list['brand_period_points'];
                                $brand_period_points = $this->changeFormula($pp_info, $bp_points_code, $v1);
                            }
                        }
                    }
                }
                //计算团队追加
                if ($pc_team_add_points != 0 && isset($cf_list['team_add_points'])) {
                    $v1['team_add_points'] = $pc_team_add_points;
                    $real_discount -= $pc_team_add_points;
                    $team_points_code = $cf_list['team_add_points'];
                    $team_add_points = $this->changeFormula($pp_info, $team_points_code, $v1);
                }

                //计算高低价sku
                $low_points = floatval($v1['low_points']);
                if ($low_points > 0 && isset($cf_list['low_points'])) {
                    $real_discount = 1 - $low_points;
                    $low_points_code = $cf_list['low_points'];
                    $low_points = $this->changeFormula($pp_info, $low_points_code, $v1);
                }
                $high_points = floatval($v1['high_points']);
                if ($high_points > 0 && isset($cf_list['high_points'])) {
                    $real_discount = 1 - $high_points;
                    $high_points_code = $cf_list['high_points'];
                    $high_points = $this->changeFormula($pp_info, $high_points_code, $v1);
                }
                $profit_goods_info[] = [
                    'profit_sn' => $profitSn,
                    'spec_sn' => $v1['spec_sn'],
                    'goods_name' => $v1['goods_name'],
                    'real_purchase_sn' => $v1['real_purchase_sn'],
                    'margin_payment' => $margin_payment,
                    'spec_price' => $spec_price,
                    'pay_price' => $pay_price,
                    'lvip_price' => $lvip_price,
                    'day_buy_num' => $day_buy_num,
                    'channel_discount' => $channel_discount,
                    'real_discount' => $real_discount,
                    'base_points' => $base_points,
                    'gears_points' => $gears_points,
                    'low_points' => $low_points,
                    'high_points' => $high_points,
                    'brand_month_points' => $brand_month_points,
                    'brand_period_points' => $brand_period_points,
                    'team_add_points' => $team_add_points,
                    'hdw_points' => 0,
                ];
            }
        }
        return $profit_goods_info;
    }

    /**
     * description 公式转换
     * author zongxing
     * date 2019.08.08
     * return number
     */
    public function changeFormula($pp_info, $points_code, $v1)
    {
        foreach ($pp_info as $k => $v) {
            $pin_str = $v['param_code'];
            if (strpos($points_code, $pin_str) !== false) {
                $replace_str = $v1[$pin_str];
                $points_code = str_replace($pin_str, $replace_str, $points_code);
            }
        }
        $gears_points = eval("return $points_code;");
        return $gears_points;
    }

    /**
     * description 获取档位追加点数
     * author zongxing
     * date 2019.07.25
     * return Object
     */
    public function getGearsPoints()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $profit_sn = 'ML' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table($this->table)
                ->where([
                    ['profit_sn', '=', $profit_sn]
                ])->count();
        } while ($count);
        return $profit_sn;
    }

    /**
     * description 毛利数据列表
     * author zongxing
     * date 2019.07.31
     * return Array
     */
    public function profitList($param_info)
    {
        $where[] = ['p.is_delete', 0];
        if (isset($param_info['start_date'])) {
            $start_date = trim($param_info['start_date']);
            $where[] = ['start_date', '>=', $start_date];
        }
        if (isset($param_info['end_date'])) {
            $end_date = trim($param_info['end_date']);
            $where[] = ['end_date', '<=', $end_date];
        }

        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $field = [
            'p.profit_sn', 'p.start_date', 'p.end_date', 'pf.cat_info', 'pf.formula_sn', 'pc.channels_name',
            DB::raw('(CASE jms_p.settle_date_type WHEN 1 THEN "提货日" WHEN 2 THEN "购买日" END) AS settle_date_type'),
            DB::raw('SUM(jms_pg.base_points) AS base_points'),
            DB::raw('SUM(jms_pg.gears_points) AS gears_points'),
            DB::raw('SUM(jms_pg.high_points) AS high_points'),
            DB::raw('SUM(jms_pg.low_points) AS low_points'),
            DB::raw('SUM(jms_pg.brand_month_points) AS brand_month_points'),
            DB::raw('SUM(jms_pg.brand_period_points) AS brand_period_points'),
            DB::raw('SUM(jms_pg.brand_gears_points) AS brand_gears_points'),
            DB::raw('SUM(jms_pg.team_month_points) AS team_month_points'),
            DB::raw('SUM(jms_pg.team_period_points) AS team_period_points'),
            DB::raw('SUM(jms_pg.hdw_points) AS hdw_points'),
            DB::raw('SUM(jms_pg.team_add_points) AS team_add_points'),
            'p.recharge_points', 'p.card_return_points',
        ];
        $profit_list = DB::table('profit_goods as pg')
            ->select($field)
            ->leftJoin('profit as p', 'p.profit_sn', '=', 'pg.profit_sn')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'p.channel_id')
            ->leftJoin('real_purchase_detail_audit as rpda', function ($join) {
                $join->on('rpda.real_purchase_sn', '=', 'pg.real_purchase_sn')
                    ->on('rpda.spec_sn', '=', 'pg.spec_sn');
            })
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('profit_formula as pf', 'pf.formula_sn', '=', 'p.formula_sn')
            ->where($where)
            ->groupBy('p.profit_sn')->orderBy('p.create_time', 'DESC')->paginate($page_size);
        $profit_list = objectToArrayZ($profit_list);

        //获取折扣种类
        $param = [
            'is_profit' => 1,
            'is_count' => 0,//是否包含特殊折扣种类//目前商品档位追加为一种特殊折扣,计算时计入高价sku追加,而不显示在列表
        ];
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param);
        foreach ($profit_list['data'] as $k => $v) {
            $cat_info = explode(',', $v['cat_info']);
            $profit_list['data'][$k]['formula'] = '';
            foreach ($dc_list as $k1 => $v1) {
                $dc_id = $v1['id'];
                $cat_name = $v1['cat_name'];
                $cat_code = $v1['cat_code'];
                if (in_array($dc_id, $cat_info)) {
                    $profit_list['data'][$k]['formula'] .= $cat_name . ' + ';
                }
                if (!in_array($dc_id, $cat_info)) {
                    $profit_list['data'][$k][$cat_code] = '';
                }
            }
            $profit_list['data'][$k]['formula'] = substr($profit_list['data'][$k]['formula'], 0, -3);
        }
        $return_info = [
            'profit_list' => $profit_list,
            'title_list' => $dc_list,
        ];
        return $return_info;
    }

    /**
     * description 毛利数据详情
     * author zongxing
     * date 2019.08.01
     * return Array
     */
    public function profitDetail($profit_sn_arr)
    {
        //获取指定的毛利单号毛利计算需要的参数
        $param = [
            'is_profit' => 1,
            'is_count' => 0,
        ];
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param);
        $pf_list = DB::table('profit_formula as pf')
            ->leftJoin('profit as p', 'p.formula_sn', '=', 'pf.formula_sn')
            ->whereIn('p.profit_sn', $profit_sn_arr)->get();
        $pf_list = objectToArrayZ($pf_list);
        //组装查询字段
        $field = [
            'pg.id', 'pg.profit_sn', 'pg.spec_sn', 'pg.goods_name', 'pg.real_purchase_sn', 'pg.spec_price',
            'pg.pay_price', 'pg.lvip_price', 'pg.day_buy_num', 'pg.channel_discount', 'pg.real_discount',
            'pg.create_time', 'p.recharge_points', 'p.card_return_points',
            'gs.erp_ref_no', 'rpda.erp_prd_no', 'rpda.erp_merchant_no',
            DB::raw('
            (CASE jms_rpa.margin_payment WHEN 1 THEN "美金原价" WHEN 2 THEN "lvip价格" END) margin_payment'
            ), 'rpda.day_buy_num', 'pc.channels_name',
        ];
        $title_field = [];
        foreach ($pf_list as $k => $v) {
            $cat_info = explode(',', $v['cat_info']);
            foreach ($dc_list as $k1 => $v1) {
                $dc_id = $v1['id'];
                $cat_code = $v1['cat_code'];
                $cat_name = $v1['cat_name'];
                $tmp_str = DB::raw('jms_pg.' . $cat_code . ' AS ' . $cat_code);
                if (in_array($dc_id, $cat_info) && !in_array($tmp_str, $field)) {
                    $field[] = $tmp_str;
                    $title_field[] = [
                        'cat_name' => $cat_name,
                        'cat_code' => $cat_code,
                    ];
                }
            }
        }
        $profit_total_detail = DB::table('profit_goods as pg')
            ->leftJoin('profit as p', 'p.profit_sn', '=', 'pg.profit_sn')
            ->leftJoin('real_purchase_detail_audit as rpda', function ($join) {
                $join->on('rpda.real_purchase_sn', '=', 'pg.real_purchase_sn')
                    ->on('rpda.spec_sn', '=', 'pg.spec_sn');
            })
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->whereIn('pg.profit_sn', $profit_sn_arr)
            ->orderBy('rpa.channels_id', 'desc')
            ->get($field);
        $profit_total_detail = objectToArrayZ($profit_total_detail);

        $profit_detail = [];
        foreach ($profit_total_detail as $k => $v) {
            $real_purchase_sn = $v['real_purchase_sn'];
            $spec_sn = $v['spec_sn'];
            $pin_str = $real_purchase_sn . '_' . $spec_sn;
            if (!isset($profit_detail[$pin_str])) {
                $profit_detail[$pin_str] = $v;
            }
        }
        $profit_detail = array_values($profit_detail);
        $return_info = [
            'title_field' => $title_field,
            'profit_detail' => $profit_detail,
        ];
        return $return_info;
    }


}//end of class
