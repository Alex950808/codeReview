<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SumGoodsModel extends Model
{
    protected $table = 'sum_goods as sg';

    protected $field = [
        'sg.id', 'sg.sum_demand_sn', 'sg.spec_sn', 'sg.goods_num', 'sg.allot_num'
    ];

    /**
     * description:采购任务详情
     * editor:zongxing
     * date : 2019.05.14
     * return Array
     */
    public function purchaseTaskDetail($sd_sn_arr, $is_zero = 1, $spec_sn = [])
    {
        $field = $this->field;
        $tmp_field = ['g.brand_id', 'b.name', 'gs.erp_ref_no', 'gs.erp_merchant_no', 'gs.spec_price', 'sg.real_num',
            'g.goods_name',
            DB::raw('sum(jms_sg.goods_num - jms_sg.real_num) as diff_num'),
            DB::raw('sum(jms_sg.goods_num * jms_gs.spec_price) as sg_demand_price'),
            DB::raw('sum((jms_sg.goods_num - jms_sg.real_num) * jms_gs.spec_price) as sg_diff_price'),
            DB::raw('ROUND(jms_sg.real_num / jms_sg.goods_num * 100, 2) as sg_real_rate'),
        ];
        $field = array_merge($field, $tmp_field);
        $sg_obj = DB::table($this->table)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sg.sum_demand_sn')
            ->whereIn('sg.sum_demand_sn', $sd_sn_arr)
            ->where('s.status', '!=', 4);
        if ($is_zero) {
            $sg_obj->where('sg.goods_num', '!=', 0);
        }
        if (!empty($spec_sn)) {
            $sg_obj->whereIn('sg.spec_sn', $spec_sn);
        }
        $sum_demand_detail = $sg_obj->orderBy('g.brand_id', 'DESC')
            ->groupBy('sg.spec_sn')
            ->get($field);
        $sum_demand_detail = objectToArrayZ($sum_demand_detail);
        return $sum_demand_detail;
    }

    /**
     * description:合单缺口
     * editor:zongxing
     * date : 2019.07.17
     * return Array
     */
    public function sumDiffDetail($sd_sn_arr)
    {
        $field = [
            'sd.spec_sn', 'gs.erp_ref_no', 'gs.erp_merchant_no', 'gs.spec_price', 'g.goods_name',
            DB::raw('max(jms_sd.goods_num) as goods_num'),
            DB::raw('sum(jms_sd.yet_num) as yet_num'),
            DB::raw('max(jms_sd.goods_num) - sum(jms_sd.yet_num) as diff_num'),
            DB::raw('max(jms_sd.goods_num) * jms_gs.spec_price as sg_demand_price'),
            DB::raw('(max(jms_sd.goods_num) - sum(jms_sd.yet_num)) * jms_gs.spec_price as sg_diff_price'),
        ];
        $sd_goods_info = DB::table('sort_data as sd')->whereIn('sd.sum_demand_sn', $sd_sn_arr)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sd.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->groupBy('sd.demand_sn')->groupBy('sd.spec_sn')->orderBy('g.brand_id', 'DESC')
            ->get($field);
        $sd_goods_info = objectToArrayZ($sd_goods_info);

        $total_goods_info = [];
        foreach ($sd_goods_info as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            $erp_ref_no = trim($v['erp_ref_no']);
            $erp_merchant_no = trim($v['erp_merchant_no']);
            $goods_name = trim($v['goods_name']);
            $goods_num = intval($v['goods_num']);
            $yet_num = intval($v['yet_num']);
            $diff_num = intval($v['diff_num']);
            $spec_price = floatval($v['spec_price']);
            $sg_demand_price = floatval($v['sg_demand_price']);
            $sg_diff_price = floatval($v['sg_diff_price']);
            if (isset($total_goods_info[$spec_sn])) {
                $total_goods_info[$spec_sn]['goods_num'] += $goods_num;
                $total_goods_info[$spec_sn]['real_num'] += $yet_num;
                $total_goods_info[$spec_sn]['diff_num'] += $diff_num;
                $total_goods_info[$spec_sn]['sg_demand_price'] += $sg_demand_price;
                $total_goods_info[$spec_sn]['sg_diff_price'] += $sg_diff_price;
            } else {
                $total_goods_info[$spec_sn]['goods_name'] = $goods_name;
                $total_goods_info[$spec_sn]['spec_sn'] = $spec_sn;
                $total_goods_info[$spec_sn]['erp_ref_no'] = $erp_ref_no;
                $total_goods_info[$spec_sn]['erp_merchant_no'] = $erp_merchant_no;
                $total_goods_info[$spec_sn]['spec_price'] = $spec_price;
                $total_goods_info[$spec_sn]['goods_num'] = $goods_num;
                $total_goods_info[$spec_sn]['real_num'] = $yet_num;
                $total_goods_info[$spec_sn]['diff_num'] = $diff_num;
                $total_goods_info[$spec_sn]['sg_demand_price'] = $sg_demand_price;
                $total_goods_info[$spec_sn]['sg_diff_price'] = $sg_diff_price;
            }
        }
        foreach ($total_goods_info as $k => $v) {
            $goods_num = $v['goods_num'];
            $real_num = $v['real_num'];
            $sg_real_rate = '0%';
            if ($goods_num) {
                $sg_real_rate = ROUND($real_num / $goods_num * 100, 2) . '%';
            }
            $total_goods_info[$k]['sg_real_rate'] = $sg_real_rate;
        }
        $sum_goods_info = array_values($total_goods_info);
        return $sum_goods_info;
    }

    /**
     * description:组装汇总单商品渠道统计信息
     * editor:zongxing
     * date : 2019.05.14
     * return Array
     */
    public function createSumDemandDetail_stop($sum_demand_detail, $discount_list, $data_type, $sd_sn_arr, $is_check_time = 0)
    {
        //获取汇总单商品的分配数据
        $param_info['sd_sn_arr'] = $sd_sn_arr;
        if ($data_type == 1) {
            $sdcg_model = new SumDemandChannelGoodsModel();
            $sdcg_list = $sdcg_model->sumDemandGoodsAllotInfo($param_info);
        } else {
            if ($is_check_time == 1) {
                $param_info['check_time'] = Carbon::today()->toDateTimeString();
            }
            $scl_model = new SdgChannelLogModel();
            $sdcg_list = $scl_model->sdgDiffAllotInfo($param_info);
            if ($is_check_time == 1) {
                if (empty($sdcg_list)) {
                    return false;
                }
            }
        }
        //获取商品特殊折扣
        $sum_spec_info = [];
        foreach ($sum_demand_detail as $k => $v) {
            $sum_spec_info[] = $v['spec_sn'];
        }
//        $gmc_model = new GmcDiscountModel();
//        $type_cat_arr = [6, 7];
//        $gmc_discount_list = $gmc_model->gmcDiscountList($sum_spec_info, null, null, $type_cat_arr);
//        $gmc_info = [];
//        foreach ($gmc_discount_list as $k => $v) {
//            $pin_str = $v['channels_name'] . '-' . $v['method_name'];
//            $gmc_info[$v['spec_sn']][$pin_str][] = $v;
//        }
        //获取合单商品对应的需求单数据
        $dg_model = new DemandGoodsModel();
        $sum_goods_info = $dg_model->sumDemandGoods($sd_sn_arr, $sum_spec_info);
        if (empty($sum_goods_info)) {
            return false;
        }

        $sum_goods_sort_info = [];
        foreach ($sum_goods_info as $k => $v) {
            $sum_goods_sort_info[$v['spec_sn']][] = $v;
        }
        $sum_goods_list = [];
        $demand_list = [];
        $external_sn_list = [];
        $sale_user_list = [];
        $expire_time_list = [];
        $dg_goods_num = [];
        foreach ($sum_goods_info as $k => $v) {
            $pin_str = $v['user_name'] . '-' . $v['sale_user_account'];
            $expire_time = $v['expire_time'];
            $demand_sn = $v['demand_sn'];
            $external_sn = $v['external_sn'];
            if (!in_array($demand_sn, $demand_list)) {
                $sale_user_list[] = $pin_str;
                $expire_time_list[] = $expire_time;
                $demand_list[] = $demand_sn;
            }
            if (!isset($external_sn_list[$demand_sn])) {
                $external_sn_list[$demand_sn] = $external_sn;
            }

            $goods_num = intval($v['goods_num']);
            $spec_price = floatval($v['spec_price']);
            $dg_demand_price = floatval(number_format($goods_num * $spec_price, 2, '.', ''));
            $sum_goods_list[$v['spec_sn']][$demand_sn] = [
                'goods_num' => $goods_num,
                'yet_num' => 0,
                'diff_num' => $goods_num,
                'dg_demand_price' => $dg_demand_price,//需求总额
                'dg_diff_price' => $dg_demand_price,//缺口总额
                'dg_real_rate' => 0.00,
            ];

            //需求单需求数统计
            $pin_goods_num = $demand_sn . '需求数';
            $pin_diff_num = $demand_sn . '缺口数';
            if (isset($dg_goods_num[$pin_goods_num])) {
                $dg_goods_num[$pin_goods_num] += $goods_num;
                $dg_goods_num[$pin_diff_num] += $goods_num;
                $dg_goods_num[$demand_sn . '需求总额'] += $dg_demand_price;
                $dg_goods_num[$demand_sn . '缺口总额'] += $dg_demand_price;
            } else {
                $dg_goods_num[$pin_goods_num] = $goods_num;
                $dg_goods_num[$pin_diff_num] = $goods_num;
                $dg_goods_num[$demand_sn . '需求总额'] = $dg_demand_price;
                $dg_goods_num[$demand_sn . '缺口总额'] = $dg_demand_price;
                $dg_goods_num[$demand_sn . '实采数'] = 0;
            }
        }
        //给汇总单的商品增加品牌基础折扣
        $channel_arr = [];
        $sum_demand_goods = [];
        $cm_goods_num = [];
        $goods_num_info = [
            '需求数' => 0,
            '可分配数' => 0,
            '实采数' => 0,
            '缺口数' => 0,
            '需求总额' => 0.00,
            '缺口总额' => 0.00,
        ];
        foreach ($sum_demand_detail as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            if (isset($sum_goods_list[$spec_sn])) {
                $brand_id = intval($v['brand_id']);
                $real_num = intval($v['real_num']);
                $spec_price = floatval($v['spec_price']);
                $goods_num = intval($v['goods_num']);
                $diff_num = intval($v['diff_num']);
                $goods_num_info['需求数'] += $goods_num;
                $goods_num_info['可分配数'] += intval($v['allot_num']);
                $goods_num_info['实采数'] += $real_num;
                $goods_num_info['缺口数'] += $diff_num;
                $goods_num_info['需求总额'] += floatval(number_format($goods_num * $spec_price, 2, '.', ''));
                $goods_num_info['缺口总额'] += floatval(number_format($diff_num * $spec_price, 2, '.', ''));
                //需求单预计分货数据
                if (isset($sum_goods_sort_info[$spec_sn]) && $real_num > 0) {
                    foreach ($sum_goods_sort_info[$spec_sn] as $ks => $vs) {
                        $demand_sn = trim($vs['demand_sn']);
                        $tmp_goods_num = intval($vs['goods_num']);
                        if ($tmp_goods_num < $real_num) {
                            $sum_goods_list[$spec_sn][$demand_sn]['yet_num'] = $tmp_goods_num;//预计分货数
                            $sum_goods_list[$spec_sn][$demand_sn]['diff_num'] = 0;//缺口数
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_diff_price'] = 0.00;//缺口总额
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_real_rate'] = 100.00;//采满率
                            $dg_goods_num[$demand_sn . '实采数'] += $tmp_goods_num;
                            $dg_goods_num[$demand_sn . '缺口数'] -= $tmp_goods_num;
                            //缺口总额
                            $dg_goods_num[$demand_sn . '缺口总额'] -= floatval(number_format($tmp_goods_num * $spec_price, 2, '.', ''));
                        } else {
                            $sum_goods_list[$spec_sn][$demand_sn]['yet_num'] = $real_num;
                            $sum_goods_list[$spec_sn][$demand_sn]['diff_num'] = $tmp_goods_num - $real_num;
                            $dg_goods_num[$demand_sn . '实采数'] += $real_num;
                            $dg_goods_num[$demand_sn . '缺口数'] -= $real_num;
                            //缺口总额
                            $dg_diff_price = floatval(number_format(($tmp_goods_num - $real_num) * $spec_price, 2, '.', ''));
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_diff_price'] = $dg_diff_price;
                            $dg_goods_num[$demand_sn . '缺口总额'] = $dg_diff_price;
                            //采满率
                            $dg_real_rate = number_format($real_num / $goods_num * 100, 2, '.', '');
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_real_rate'] = $dg_real_rate;
                            break;
                        }
                    }
                }
                //需求单分布信息
                $v['demand_info'] = $sum_goods_list[$spec_sn];
                $goods_discount_info = '';
                dd($discount_list);
                if (isset($discount_list[$brand_id])) {
                    $goods_discount_info = $discount_list[$brand_id];
                }
                //如果存在渠道折扣信息
                $final_goods_discount = [];
                if (!empty($goods_discount_info)) {
                    foreach ($goods_discount_info as $k1 => $v1) {
                        //组装标题渠道类型行信息
                        $tmp_discount_type = [];
                        if (!isset($channel_arr[$k1])) {
                            foreach ($v1 as $k2 => $v2) {
                                $tmp_discount_type[] = $k2;
                            }
                            $channel_arr[$k1] = [
                                'channels_name' => $k1,
                                'discount_type' => $tmp_discount_type,
                            ];
                        } else {
                            foreach ($v1 as $k2 => $v2) {
                                if (!in_array($k2, $channel_arr[$k1]['discount_type'])) {
                                    $channel_arr[$k1]['discount_type'][] = $k2;
                                }
                            }
                        }
                        $brand_discount = $tmp_final_discount = array_values($v1)[0];//初始折扣
                        //如果商品有设置其他折扣，后期这里要增加关于渠道中的其他折扣
                        if (isset($gmc_info[$spec_sn][$k1])) {
                            $spec_gmc_info = $gmc_info[$spec_sn][$k1];
                            foreach ($spec_gmc_info as $k3 => $v3) {
                                $discount = floatval($v3['discount']);
                                //增加特殊折扣到渠道所属列
                                $diff_discount = $brand_discount - $discount;
                                $goods_discount_info[$k1][$v3['type_name']] = $diff_discount;
                                //增加二级渠道信息
                                if (!in_array($v3['type_name'], $channel_arr[$k1]['discount_type'])) {
                                    if (in_array('最终折扣', $channel_arr[$k1]['discount_type'])) {
                                        //$channel_arr[$k1]['discount_type'][] = '最终折扣';
                                        $key_value = array_keys($channel_arr[$k1]['discount_type'], '最终折扣')[0];
                                        $push_arr = [$v3['type_name']];
                                        array_splice($channel_arr[$k1]['discount_type'], $key_value, 0, $push_arr);
                                    } else {
                                        $channel_arr[$k1]['discount_type'][] = $v3['type_name'];
                                    }
                                }
                                //计算最终折扣
                                if ($diff_discount > 0) {
                                    $tmp_final_discount -= $diff_discount;
                                } else {
                                    $tmp_final_discount += $diff_discount;
                                }
                            }
                        }
                        $goods_discount_info[$k1]['最终折扣']['discount'] = $tmp_final_discount;
                        if (!in_array('最终折扣', $channel_arr[$k1]['discount_type'])) {
                            $channel_arr[$k1]['discount_type'][] = '最终折扣';
                        }
                        if (!in_array('可采数', $channel_arr[$k1]['discount_type'])) {
                            $channel_arr[$k1]['discount_type'][] = '可采数';
                        }
                        //更新渠道可采数信息
                        $may_num = 0;
                        if (isset($sdcg_list[$spec_sn])) {
                            $spec_sdcg_info = $sdcg_list[$spec_sn];
                            if (isset($spec_sdcg_info[$k1])) {
                                $may_num = intval($spec_sdcg_info[$k1]['may_num']);
                            }
                        }
                        $goods_discount_info[$k1]['可采数'] = $may_num;
                        //渠道可采数统计
                        if (isset($cm_goods_num[$k1])) {
                            $cm_goods_num[$k1] += $may_num;
                        } else {
                            $cm_goods_num[$k1] = $may_num;
                        }
                    }
                    //对商品折扣进行排序，排序方式：线上和线下分开
                    $sort_arr = [];
                    $tmp_arr = [];
                    foreach ($goods_discount_info as $k4 => $v4) {
                        if (strstr($k4, '线上')) {
                            $sort_arr['xs'][] = $v4['最终折扣']['discount'];
                            $tmp_arr['xs'][$k4] = $v4;
                            $tmp_arr['xs'][$k4]['channels_name'] = $k4;
                        } else {
                            $sort_arr['xx'][] = $v4['最终折扣']['discount'];
                            $tmp_arr['xx'][$k4] = $v4;
                            $tmp_arr['xx'][$k4]['channels_name'] = $k4;
                        }
                    }

                    foreach ($tmp_arr as $k5 => $v5) {
                        array_multisort($sort_arr[$k5], $tmp_arr[$k5]);
                        $tmp_sort_arr = array_values($tmp_arr[$k5]);
                        foreach ($tmp_sort_arr as $k6 => $v6) {
                            $tmp_arr[$k5][$v6['channels_name']]['最终折扣']['sort'] = $k6 + 1;
                        }
                        $final_goods_discount = array_merge($final_goods_discount, $tmp_arr[$k5]);
                    }
                    foreach ($final_goods_discount as $k7 => $v7) {
                        unset($final_goods_discount[$k7]['channels_name']);
                    }
                    //对商品折扣进行排序，排序方式：不区分线上线下
//                $tmp_arr = $goods_discount_info;
//                foreach ($goods_discount_info as $ks => $vs) {
//                    $sort_arr[] = $vs['最终折扣']['discount'];
//                }
//                foreach ($tmp_arr as $ks1 => $vs1) {
//                    $tmp_arr[$ks1]['channels_name'] = $ks1;
//                }
//                $tmp_arr = array_values($tmp_arr);
//                foreach ($tmp_arr as $ks2 => $vs2) {
//                    $goods_discount_info[$vs2['channels_name']]['最终折扣']['sort'] = $ks2 + 1;
//                }
                }
                $v['channels_info'] = $final_goods_discount;
                $sum_demand_goods[] = $v;
            }
        }
        $tmp_arr = ['线上', '线下'];
        $channel_list = [];
        foreach ($tmp_arr as $m => $n) {
            foreach ($channel_arr as $k => $v) {
                if (strstr($k, $n) && !isset($channel_list[$n])) {
                    $channel_list[$n]['method_name'] = $n;
                }
                if (strstr($k, $n)) {
                    $channel_list[$n]['channel_info'][] = $v;
                }
            }
        }
        //计算需求单采满率
        foreach ($dg_goods_num as $k => $v) {
            $demand_sn = substr($k, 0, 18);
            if (!isset($dg_goods_num[$demand_sn . '采满率'])) {
                $goods_num = $dg_goods_num[$demand_sn . '需求数'];
                $real_num = $dg_goods_num[$demand_sn . '实采数'];
                $total_real_rate = 0;
                if ($goods_num) {
                    $total_real_rate = number_format($real_num / $goods_num * 100, 2, '.', '');
                }
                $dg_goods_num[$demand_sn . '采满率'] = $total_real_rate;
            }
        }
        //计算合计单采满率
        $total_goods_num = intval($goods_num_info['需求数']);
        $total_real_num = intval($goods_num_info['实采数']);
        $goods_num_info['采满率'] = number_format($total_real_num / $total_goods_num * 100, 2, '.', '') . '%';
        $return_info['sum_demand_goods'] = $sum_demand_goods;
        $return_info['channel_arr'] = $channel_list;
        $return_info['demand_arr'] = $demand_list;
        $return_info['external_sn_list'] = $external_sn_list;
        $return_info['sale_user_list'] = $sale_user_list;
        $return_info['expire_time_list'] = $expire_time_list;
        $return_info['goods_num_info'] = $goods_num_info;
        $return_info['dg_goods_num'] = $dg_goods_num;
        $return_info['cm_goods_num'] = $cm_goods_num;
        return $return_info;
    }

    public function createSumDemandDetail($goods_discount_list, $discount_list, $data_type, $sd_sn_arr, $is_check_time = 0)
    {
        //获取汇总单商品的分配数据
        $param_info['sd_sn_arr'] = $sd_sn_arr;
        if ($data_type == 1) {
            $sdcg_model = new SumDemandChannelGoodsModel();
            $sdcg_list = $sdcg_model->sumDemandGoodsAllotInfo($param_info);
        } else {
            if ($is_check_time == 1) {
                $param_info['check_time'] = Carbon::today()->toDateTimeString();
            }
            $scl_model = new SdgChannelLogModel();
            $sdcg_list = $scl_model->sdgDiffAllotInfo($param_info);
            if ($is_check_time == 1) {
                if (empty($sdcg_list)) {
                    return false;
                }
            }
        }
        //整理商品信息
        $sum_spec_info = [];
        $spec_final_discount = [];
        $spec_total_info = [];
        foreach ($goods_discount_list as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $sum_spec_info[] = $spec_sn;
            $tmp_discount_info = [];
            if (!empty($v['channels_info'])) {
                foreach ($v['channels_info'] as $k1 => $v1) {
                    $pin_str = $v1['channels_name'] . '-' . $v1['method_name'];
                    $tmp_discount_info[$pin_str] = $v1;
                }
            }
            $spec_final_discount[$spec_sn] = $tmp_discount_info;
            unset($v['channels_info']);
            $spec_total_info[] = $v;
        }

        //获取合单商品对应的需求单数据
        $dg_model = new DemandGoodsModel();
        $sum_goods_info = $dg_model->sumDemandGoods($sd_sn_arr, $sum_spec_info);
        if (empty($sum_goods_info)) {
            return false;
        }
        $sum_goods_sort_info = [];
        foreach ($sum_goods_info as $k => $v) {
            $sum_goods_sort_info[$v['spec_sn']][] = $v;
        }
        $sum_goods_list = [];
        $demand_list = [];
        $external_sn_list = [];
        $sale_user_list = [];
        $expire_time_list = [];
        $dg_goods_num = [];
        foreach ($sum_goods_info as $k => $v) {
            $pin_str = $v['user_name'] . '-' . $v['sale_user_account'];
            $expire_time = $v['expire_time'];
            $demand_sn = $v['demand_sn'];
            $external_sn = $v['external_sn'];
            if (!in_array($demand_sn, $demand_list)) {
                $sale_user_list[] = $pin_str;
                $expire_time_list[] = $expire_time;
                $demand_list[] = $demand_sn;
            }
            if (!isset($external_sn_list[$demand_sn])) {
                $external_sn_list[$demand_sn] = $external_sn;
            }

            $goods_num = intval($v['goods_num']);
            $spec_price = floatval($v['spec_price']);
            $dg_demand_price = floatval(number_format($goods_num * $spec_price, 2, '.', ''));
            $sum_goods_list[$v['spec_sn']][$demand_sn] = [
                'goods_num' => $goods_num,
                'yet_num' => 0,
                'diff_num' => $goods_num,
                'dg_demand_price' => $dg_demand_price,//需求总额
                'dg_diff_price' => $dg_demand_price,//缺口总额
                'dg_real_rate' => 0.00,
            ];

            //需求单需求数统计
            $pin_goods_num = $demand_sn . '需求数';
            $pin_diff_num = $demand_sn . '缺口数';
            if (isset($dg_goods_num[$pin_goods_num])) {
                $dg_goods_num[$pin_goods_num] += $goods_num;
                $dg_goods_num[$pin_diff_num] += $goods_num;
                $dg_goods_num[$demand_sn . '需求总额'] += $dg_demand_price;
                $dg_goods_num[$demand_sn . '缺口总额'] += $dg_demand_price;
            } else {
                $dg_goods_num[$pin_goods_num] = $goods_num;
                $dg_goods_num[$pin_diff_num] = $goods_num;
                $dg_goods_num[$demand_sn . '需求总额'] = $dg_demand_price;
                $dg_goods_num[$demand_sn . '缺口总额'] = $dg_demand_price;
                $dg_goods_num[$demand_sn . '实采数'] = 0;
            }
        }
        //给汇总单的商品增加品牌基础折扣
        $channel_arr = [];
        $sum_demand_goods = [];
        $cm_goods_num = [];
        $goods_num_info = [
            '需求数' => 0,
            '可分配数' => 0,
            '实采数' => 0,
            '缺口数' => 0,
            '需求总额' => 0.00,
            '缺口总额' => 0.00,
        ];
        foreach ($spec_total_info as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            if (isset($sum_goods_list[$spec_sn])) {
                $brand_id = intval($v['brand_id']);
                $real_num = intval($v['real_num']);
                $spec_price = floatval($v['spec_price']);
                $goods_num = intval($v['goods_num']);
                $diff_num = intval($v['diff_num']);
                $goods_num_info['需求数'] += $goods_num;
                $goods_num_info['可分配数'] += intval($v['allot_num']);
                $goods_num_info['实采数'] += $real_num;
                $goods_num_info['缺口数'] += $diff_num;
                $goods_num_info['需求总额'] += floatval(number_format($goods_num * $spec_price, 2, '.', ''));
                $goods_num_info['缺口总额'] += floatval(number_format($diff_num * $spec_price, 2, '.', ''));
                //需求单预计分货数据
                if (isset($sum_goods_sort_info[$spec_sn]) && $real_num > 0) {
                    foreach ($sum_goods_sort_info[$spec_sn] as $ks => $vs) {
                        $demand_sn = trim($vs['demand_sn']);
                        $tmp_goods_num = intval($vs['goods_num']);
                        if ($tmp_goods_num < $real_num) {
                            $sum_goods_list[$spec_sn][$demand_sn]['yet_num'] = $tmp_goods_num;//预计分货数
                            $sum_goods_list[$spec_sn][$demand_sn]['diff_num'] = 0;//缺口数
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_diff_price'] = 0.00;//缺口总额
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_real_rate'] = 100.00;//采满率
                            $dg_goods_num[$demand_sn . '实采数'] += $tmp_goods_num;
                            $dg_goods_num[$demand_sn . '缺口数'] -= $tmp_goods_num;
                            //缺口总额
                            $dg_goods_num[$demand_sn . '缺口总额'] -= floatval(number_format($tmp_goods_num * $spec_price, 2, '.', ''));
                        } else {
                            $sum_goods_list[$spec_sn][$demand_sn]['yet_num'] = $real_num;
                            $sum_goods_list[$spec_sn][$demand_sn]['diff_num'] = $tmp_goods_num - $real_num;
                            $dg_goods_num[$demand_sn . '实采数'] += $real_num;
                            $dg_goods_num[$demand_sn . '缺口数'] -= $real_num;
                            //缺口总额
                            $dg_diff_price = floatval(number_format(($tmp_goods_num - $real_num) * $spec_price, 2, '.', ''));
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_diff_price'] = $dg_diff_price;
                            $dg_goods_num[$demand_sn . '缺口总额'] = $dg_diff_price;
                            //采满率
                            $dg_real_rate = number_format($real_num / $goods_num * 100, 2, '.', '');
                            $sum_goods_list[$spec_sn][$demand_sn]['dg_real_rate'] = $dg_real_rate;
                            break;
                        }
                    }
                }
                //需求单分布信息
                $v['demand_info'] = $sum_goods_list[$spec_sn];
            }
            //如果存在渠道折扣信息
            $final_goods_discount = [];
            if (isset($discount_list[$brand_id])) {
                $goods_discount_info = $discount_list[$brand_id];
                $final_goods_discount = [];
                $tmp_goods_discount = [];
                if (!empty($goods_discount_info)) {
                    foreach ($goods_discount_info as $gdk1 => $gdv1) {
                        $pin_name = $gdv1['channels_name'] . '-' . $gdv1['method_name'];
                        $type_name = $gdv1['type_name'];
                        $brand_discount = floatval($gdv1['brand_discount']);
                        $tmp_goods_discount[$pin_name][$type_name] = $brand_discount;
                    }
//                    foreach ($spec_final_discount[$spec_sn] as $sfk1 => $sfv1) {
//                        $pin_name = $sfv1['channels_name'] . '-' . $sfv1['method_name'];
//                        $type_name = $sfv1['type_name'];
//                        $brand_discount = floatval($sfv1['brand_discount']);
//                        if(!isset($tmp_goods_discount[$pin_name])){
//                            $tmp_goods_discount[$pin_name][$type_name] = $brand_discount;
//                        }
//                    }
                    foreach ($tmp_goods_discount as $k1 => $v1) {
                        //组装标题渠道类型行信息
                        $tmp_discount_type = [];
                        if (!isset($channel_arr[$k1])) {
                            foreach ($v1 as $k2 => $v2) {
                                $tmp_discount_type[] = $k2;
                            }
                            $channel_arr[$k1] = [
                                'channels_name' => $k1,
                                'discount_type' => $tmp_discount_type,
                            ];
                        } else {
                            foreach ($v1 as $k2 => $v2) {
                                if (!in_array($k2, $channel_arr[$k1]['discount_type'])) {
                                    $channel_arr[$k1]['discount_type'][] = $k2;
                                }
                            }
                        }
                        $tmp_final_discount = array_values($v1)[0];//初始折扣
                        if (isset($spec_final_discount[$spec_sn][$k1])) {
                            $tmp_final_discount = floatval($spec_final_discount[$spec_sn][$k1]['brand_discount']);
                        }
                        $tmp_goods_discount[$k1]['最终折扣']['discount'] = $tmp_final_discount;
                        if (!in_array('最终折扣', $channel_arr[$k1]['discount_type'])) {
                            $channel_arr[$k1]['discount_type'][] = '最终折扣';
                        }
                        if (!in_array('可采数', $channel_arr[$k1]['discount_type'])) {
                            $channel_arr[$k1]['discount_type'][] = '可采数';
                        }
                        //更新渠道可采数信息
                        $may_num = 0;
                        if (isset($sdcg_list[$spec_sn])) {
                            $spec_sdcg_info = $sdcg_list[$spec_sn];
                            if (isset($spec_sdcg_info[$k1])) {
                                $may_num = intval($spec_sdcg_info[$k1]['may_num']);
                            }
                        }
                        $tmp_goods_discount[$k1]['可采数'] = $may_num;
                        //渠道可采数统计
                        if (isset($cm_goods_num[$k1])) {
                            $cm_goods_num[$k1] += $may_num;
                        } else {
                            $cm_goods_num[$k1] = $may_num;
                        }
                    }
                    //对商品折扣进行排序，排序方式：线上和线下分开
                    $sort_arr = [];
                    $tmp_arr = [];
                    foreach ($tmp_goods_discount as $k4 => $v4) {
                        if (strstr($k4, '线上')) {
                            $sort_arr['xs'][] = $v4['最终折扣']['discount'];
                            $tmp_arr['xs'][$k4] = $v4;
                            $tmp_arr['xs'][$k4]['channels_name'] = $k4;
                        } else {
                            $sort_arr['xx'][] = $v4['最终折扣']['discount'];
                            $tmp_arr['xx'][$k4] = $v4;
                            $tmp_arr['xx'][$k4]['channels_name'] = $k4;
                        }
                    }

                    foreach ($tmp_arr as $k5 => $v5) {
                        array_multisort($sort_arr[$k5], $tmp_arr[$k5]);
                        $tmp_sort_arr = array_values($tmp_arr[$k5]);
                        foreach ($tmp_sort_arr as $k6 => $v6) {
                            $tmp_arr[$k5][$v6['channels_name']]['最终折扣']['sort'] = $k6 + 1;
                        }
                        $final_goods_discount = array_merge($final_goods_discount, $tmp_arr[$k5]);
                    }
                    foreach ($final_goods_discount as $k7 => $v7) {
                        unset($final_goods_discount[$k7]['channels_name']);
                    }
                }
            }
            $v['channels_info'] = $final_goods_discount;
            $sum_demand_goods[] = $v;
        }
        $tmp_arr = ['线上', '线下'];
        $channel_list = [];
        foreach ($tmp_arr as $m => $n) {
            foreach ($channel_arr as $k => $v) {
                if (strstr($k, $n) && !isset($channel_list[$n])) {
                    $channel_list[$n]['method_name'] = $n;
                }
                if (strstr($k, $n)) {
                    $channel_list[$n]['channel_info'][] = $v;
                }
            }
        }
        //计算需求单采满率
        foreach ($dg_goods_num as $k => $v) {
            $demand_sn = substr($k, 0, 18);
            if (!isset($dg_goods_num[$demand_sn . '采满率'])) {
                $goods_num = $dg_goods_num[$demand_sn . '需求数'];
                $real_num = $dg_goods_num[$demand_sn . '实采数'];
                $total_real_rate = 0;
                if ($goods_num) {
                    $total_real_rate = number_format($real_num / $goods_num * 100, 2, '.', '');
                }
                $dg_goods_num[$demand_sn . '采满率'] = $total_real_rate;
            }
        }
        //计算合计单采满率
        $total_goods_num = intval($goods_num_info['需求数']);
        $total_real_num = intval($goods_num_info['实采数']);
        $goods_num_info['采满率'] = number_format($total_real_num / $total_goods_num * 100, 2, '.', '') . '%';
        $return_info['sum_demand_goods'] = $sum_demand_goods;
        $return_info['channel_arr'] = $channel_list;
        $return_info['demand_arr'] = $demand_list;
        $return_info['external_sn_list'] = $external_sn_list;
        $return_info['sale_user_list'] = $sale_user_list;
        $return_info['expire_time_list'] = $expire_time_list;
        $return_info['goods_num_info'] = $goods_num_info;
        $return_info['dg_goods_num'] = $dg_goods_num;
        $return_info['cm_goods_num'] = $cm_goods_num;
        return $return_info;
    }

    /**
     * description:组装汇总单商品渠道统计信息(无序)
     * editor:zongxing
     * date : 2019.05.14
     * return Array
     */
    public function createSddNoSort($sum_demand_detail, $discount_list, $sd_sn_arr)
    {
        //获取汇总单商品的分配数据
        $param_info['sd_sn_arr'] = $sd_sn_arr;
        $sdcg_model = new SumDemandChannelGoodsModel();
        $sdcg_list = $sdcg_model->sumDemandGoodsAllotInfo($param_info);
        //获取商品特殊折扣
        $sum_spec_info = [];
        foreach ($sum_demand_detail as $k => $v) {
            $sum_spec_info[] = $v['spec_sn'];
        }
        $gmc_model = new GmcDiscountModel();
        $type_cat_arr = [6, 7];
        $gmc_discount_list = $gmc_model->gmcDiscountList($sum_spec_info, null, null, $type_cat_arr);
        $gmc_info = [];
        foreach ($gmc_discount_list as $k => $v) {
            $pin_str = $v['channels_name'] . '-' . $v['method_name'];
            $gmc_info[$v['spec_sn']][$pin_str][] = $v;
        }
        //获取合单商品对应的需求单数据
        $dg_model = new DemandGoodsModel();
        $sum_goods_info = $dg_model->sumDemandGoods($sd_sn_arr, $sum_spec_info);
        $sum_goods_list = [];
        $demand_list = [];
        $sale_user_list = [];
        $expire_time_list = [];
        $dg_goods_num = [];
        foreach ($sum_goods_info as $k => $v) {
            $pin_str = $v['user_name'] . '-' . $v['sale_user_account'];
            $expire_time = $v['expire_time'];
            if (!in_array($v['demand_sn'], $demand_list)) {
                $sale_user_list[] = $pin_str;
                $expire_time_list[] = $expire_time;
                $demand_list[] = $v['demand_sn'];
            }
            $sum_goods_list[$v['spec_sn']][$v['demand_sn']] = intval($v['goods_num']);
            //需求单需求数统计
            if (isset($dg_goods_num[$v['demand_sn']])) {
                $dg_goods_num[$v['demand_sn']] += intval($v['goods_num']);
            } else {
                $dg_goods_num[$v['demand_sn']] = intval($v['goods_num']);
            }
        }

        //给汇总单的商品增加品牌基础折扣
        $channel_arr = [];
        $sum_demand_goods = [];
        $cm_goods_num = [];
        $goods_num_info = [
            '需求数' => 0,
            '可分配数' => 0,
        ];
        foreach ($sum_demand_detail as $k => $v) {
            $brand_id = intval($v['brand_id']);
            $spec_sn = trim($v['spec_sn']);
            $goods_num_info['需求数'] += intval($v['goods_num']);
            $goods_num_info['可分配数'] += intval($v['allot_num']);
            //需求单分布信息
            $v['demand_info'] = $sum_goods_list[$spec_sn];
            $goods_discount_info = '';
            if (isset($discount_list[$brand_id])) {
                $goods_discount_info = $discount_list[$brand_id];
            }
            //如果存在渠道折扣信息
            if (!empty($goods_discount_info)) {
                foreach ($goods_discount_info as $k1 => $v1) {
                    //组装标题渠道类型行信息
                    $tmp_discount_type = [];
                    if (!isset($channel_arr[$k1])) {
                        foreach ($v1 as $k2 => $v2) {
                            $tmp_discount_type[] = $k2;
                        }
                        $channel_arr[$k1] = [
                            'channels_name' => $k1,
                            'discount_type' => $tmp_discount_type,
                        ];
                    } else {
                        foreach ($v1 as $k2 => $v2) {
                            if (!in_array($k2, $channel_arr[$k1]['discount_type'])) {
                                $channel_arr[$k1]['discount_type'][] = $k2;
                            }
                        }
                    }
                    $tmp_final_discount = array_values($v1)[0];//初始折扣
                    //如果商品有设置其他折扣，后期这里要增加关于渠道中的其他折扣
                    if (isset($gmc_info[$spec_sn][$k1])) {
                        $spec_gmc_info = $gmc_info[$spec_sn][$k1];
                        $total_discount = 0;
                        //dd($spec_gmc_info);
                        foreach ($spec_gmc_info as $k3 => $v3) {
                            $discount = floatval($v3['discount']);
                            $total_discount += $discount;
                            //增加特殊折扣到渠道所属列
                            $goods_discount_info[$k1][$v3['type_name']] = $discount;
                            //增加二级渠道信息
                            if (!in_array($v3['type_name'], $channel_arr[$k1]['discount_type'])) {
                                if (in_array('最终折扣', $channel_arr[$k1]['discount_type'])) {
                                    //$channel_arr[$k1]['discount_type'][] = '最终折扣';
                                    $key_value = array_keys($channel_arr[$k1]['discount_type'], '最终折扣')[0];
                                    $push_arr = [$v3['type_name']];
                                    array_splice($channel_arr[$k1]['discount_type'], $key_value, 0, $push_arr);
                                } else {
                                    $channel_arr[$k1]['discount_type'][] = $v3['type_name'];
                                }
                            }
                        }
                        //计算最终折扣
                        $tmp_final_discount -= $discount;
                    }
                    $goods_discount_info[$k1]['最终折扣']['discount'] = $tmp_final_discount;
                    if (!in_array('最终折扣', $channel_arr[$k1]['discount_type'])) {
                        $channel_arr[$k1]['discount_type'][] = '最终折扣';
                    }
                    if (!in_array('可采数', $channel_arr[$k1]['discount_type'])) {
                        $channel_arr[$k1]['discount_type'][] = '可采数';
                    }
                    //更新渠道可采数信息
                    $may_num = 0;
                    if (isset($sdcg_list[$spec_sn])) {
                        $spec_sdcg_info = $sdcg_list[$spec_sn];
                        if (isset($spec_sdcg_info[$k1])) {
                            $may_num = intval($spec_sdcg_info[$k1]['may_num']);
                        }
                    }
                    $goods_discount_info[$k1]['可采数'] = $may_num;
                    //渠道可采数统计
                    if (isset($cm_goods_num[$k1])) {
                        $cm_goods_num[$k1] += $may_num;
                    } else {
                        $cm_goods_num[$k1] = $may_num;
                    }
                }
                //对商品折扣进行排序，排序方式：线上和线下分开
                $sort_arr = [];
                $tmp_arr = [];
                foreach ($goods_discount_info as $k4 => $v4) {
                    if (strstr($k4, '线上')) {
                        $sort_arr['xs'][] = $v4['最终折扣']['discount'];
                        $tmp_arr['xs'][$k4] = $v4;
                        $tmp_arr['xs'][$k4]['channels_name'] = $k4;
                    } else {
                        $sort_arr['xx'][] = $v4['最终折扣']['discount'];
                        $tmp_arr['xx'][$k4] = $v4;
                        $tmp_arr['xx'][$k4]['channels_name'] = $k4;
                    }
                }
                $final_goods_discount = [];
                foreach ($tmp_arr as $k5 => $v5) {
                    array_multisort($sort_arr[$k5], $tmp_arr[$k5]);
                    $tmp_sort_arr = array_values($tmp_arr[$k5]);
                    foreach ($tmp_sort_arr as $k6 => $v6) {
                        $tmp_arr[$k5][$v6['channels_name']]['最终折扣']['sort'] = $k6 + 1;
                    }
                    $final_goods_discount = array_merge($final_goods_discount, $tmp_arr[$k5]);
                }
                foreach ($final_goods_discount as $k7 => $v7) {
                    unset($final_goods_discount[$k7]['channels_name']);
                }
                //对商品折扣进行排序，排序方式：不区分线上线下
//                $tmp_arr = $goods_discount_info;
//                foreach ($goods_discount_info as $ks => $vs) {
//                    $sort_arr[] = $vs['最终折扣']['discount'];
//                }
//                foreach ($tmp_arr as $ks1 => $vs1) {
//                    $tmp_arr[$ks1]['channels_name'] = $ks1;
//                }
//                $tmp_arr = array_values($tmp_arr);
//                foreach ($tmp_arr as $ks2 => $vs2) {
//                    $goods_discount_info[$vs2['channels_name']]['最终折扣']['sort'] = $ks2 + 1;
//                }
            }
            //dd($final_goods_discount);
            $v['channels_info'] = $final_goods_discount;
            $sum_demand_goods[] = $v;
        }
        $channel_list = [];
        foreach ($channel_arr as $k => $v) {
            $channel_list['线上']['method_name'] = '线上';
            if (strstr($k, '线上')) {
                $channel_list['线上']['channel_info'][] = $v;
            }
        }
        foreach ($channel_arr as $k => $v) {
            $channel_list['线下']['method_name'] = '线下';
            if (strstr($k, '线下')) {
                $channel_list['线下']['channel_info'][] = $v;
            }
        }
        $return_info['sum_demand_goods'] = $sum_demand_goods;
        $return_info['channel_arr'] = $channel_list;
        $return_info['demand_arr'] = $demand_list;
        $return_info['sale_user_list'] = $sale_user_list;
        $return_info['expire_time_list'] = $expire_time_list;
        $return_info['goods_num_info'] = $goods_num_info;
        $return_info['dg_goods_num'] = $dg_goods_num;
        $return_info['cm_goods_num'] = $cm_goods_num;
        return $return_info;
    }

    /**
     * description:组装汇总单商品分配数据
     * editor:zongxing
     * date : 2019.05.16
     * return Array
     */
    public function createUploadSumDemandGoods($res, $sum_demand_goods, $pcm_list, $channel_start_num)
    {
        $upload_goods_info = [];
        $error_spec_info = [];
        $error_pcm_info = [];
        foreach ($res as $k => $v) {
            if ($k < 4) continue;
            //进行商品是否存在需求判断
            $spec_sn = $v[0];
            if ($spec_sn == '总计' || empty($spec_sn)) continue;
            if (!isset($sum_demand_goods[$spec_sn])) {
                $error_spec_info[] = $spec_sn;
                continue;
            }
            //组装上传商品信息
            $total_may_num = 0;
            foreach ($v as $k1 => $v1) {
                if ($k1 < $channel_start_num) continue;
                if ($res[2][$k1] == '可采数') {
                    $channels_name = $res[1][$k1];
                    $may_num = intval($v1);

                    $total_may_num += $may_num;
                    //判断上传的商品是否已经分配过--暂时保留
//                    if ($may_num == 0 && $data_type == 2) {
//                        continue;
//                    }
                    //判断渠道方式是否存在
                    if (!isset($pcm_list[$channels_name])) {
                        $error_pcm_info[] = $spec_sn;
                        continue;
                    }
                    //判断商品折扣是否存在
                    $channels_discount = 0;
                    if (isset($sum_demand_goods[$spec_sn]['channels_info'][$channels_name])) {
                        $channels_discount = $sum_demand_goods[$spec_sn]['channels_info'][$channels_name];
                    }

                    $channels_id = $pcm_list[$channels_name]['channels_id'];
                    $method_id = $pcm_list[$channels_name]['method_id'];
                    $upload_goods_info[$spec_sn][$channels_name] = [
                        'channels_id' => $channels_id,
                        'method_id' => $method_id,
                        'channel_discount' => $channels_discount,
                        'may_num' => $may_num,
                    ];
                }
            }
            //检查商品的可采数分配是否大于可分配数
            $goods_num = $sum_demand_goods[$spec_sn]['goods_num'];
            if ($goods_num < $total_may_num) {
                return ['code' => '1105', 'msg' => '您上传的商品:' . $spec_sn . '可采数大于总需求数,请检查'];
            }
        }

        if (!empty($error_spec_info)) {
            $error_spec_info = json_encode($error_spec_info, JSON_UNESCAPED_UNICODE);
            $error_info = substr($error_spec_info, 1, -1);
            return ['code' => '1101', 'msg' => '您上传的商品:' . $error_info . '不存在需求信息,请检查'];
        }
        if (!empty($error_pcm_info)) {
            $error_pcm_info = json_encode($error_pcm_info, JSON_UNESCAPED_UNICODE);
            $error_info = substr($error_pcm_info, 1, -1);
            return ['code' => '1103', 'msg' => '您上传的商品:' . $error_info . '对应的渠道方式信息有误,请检查'];
        }
        if (empty($upload_goods_info)) {
            return ['code' => '1104', 'msg' => '您上传的商品未分配可采数,请检查'];
        }
        return $upload_goods_info;
    }

    /**
     * description:获取汇总需求单统计信息
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function sumDemandStatistic($sd_sn_arr)
    {
        $field = $this->field;
        $tmp_field = [
            's.sum_demand_name',
            DB::raw('sum(jms_sg.goods_num) as goods_num'),
            DB::raw('sum(jms_sg.goods_num) as final_goods_num'),
            DB::raw('sum(jms_sg.goods_num) - sum(jms_sg.allot_num) as may_buy_num'),
            DB::raw('sum(jms_sg.goods_num) - sum(jms_sg.real_num) as diff_num'),
            DB::raw('sum(jms_sg.real_num) as real_buy_num'),
            DB::raw('sum(jms_sg.real_num) as final_buy_num'),
            DB::raw('round(sum(jms_sg.real_num) / sum(jms_sg.goods_num) * 100, 2) as real_buy_rate')
        ];
        $field = array_merge($field, $tmp_field);
        $sum_demand_statistic = DB::table($this->table)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sg.sum_demand_sn')
            ->whereIn('sg.sum_demand_sn', $sd_sn_arr)
            ->where('s.status', '!=', 4)
            ->groupBy('sg.sum_demand_sn')
            ->get($field)
            ->groupBy('sum_demand_sn');
        $sum_demand_statistic = objectToArrayZ($sum_demand_statistic);
        return $sum_demand_statistic;
    }

    /**
     * description:获取汇总需求单的商品统计数据信息
     * editor:zongxing
     * date : 2019.02.12
     * return Array
     */
    public function get_sg_info($sd_sn_info)
    {
        $field = ['sg.spec_sn',
            DB::raw('SUM(jms_sg.goods_num) - SUM(jms_sg.allot_num) as total_may_buy_num'),
            DB::raw('SUM(jms_sg.real_num) as total_real_buy_num')
        ];
        $sg_info = DB::table($this->table)
            ->whereIn('sg.sum_demand_sn', $sd_sn_info)->groupBy('sg.spec_sn')->get($field)
            ->groupBy('spec_sn');
        $sg_info = objectToArrayZ($sg_info);
        return $sg_info;
    }

}
