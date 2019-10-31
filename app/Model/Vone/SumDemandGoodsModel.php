<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SumDemandGoodsModel extends Model
{
    protected $table = 'sum_demand_goods as sdg';

    protected $field = [
        'sdg.id', 'sdg.sum_demand_sn', 'sdg.goods_name', 'sdg.erp_prd_no', 'sdg.erp_merchant_no', 'sdg.spec_sn',
        'sdg.goods_num', 'sdg.allot_num'
    ];

    /**
     * description:采购任务详情
     * editor:zongxing
     * date : 2019.05.14
     * return Array
     */
    public function purchaseTaskDetail($sd_sn_arr)
    {
        $field = $this->field;
        $tmp_field = ['g.brand_id', 'b.name', 'gs.erp_ref_no'];
        $field = array_merge($field, $tmp_field);
        $sum_demand_detail = DB::table($this->table)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sdg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->whereIn('sdg.sum_demand_sn', $sd_sn_arr)
            ->orderBy('g.brand_id', 'DESC')
            ->get($field);
        $sum_demand_detail = objectToArrayZ($sum_demand_detail);
        return $sum_demand_detail;
    }

    /**
     * description:组装汇总单商品渠道统计信息
     * editor:zongxing
     * date : 2019.05.14
     * return Array
     */
    public function createSumDemandDetail_stop($sum_demand_detail, $discount_list, $sd_sn_arr)
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
        $gmc_discount_list = $gmc_model->gmcDiscountList($sum_spec_info);
        $gmc_info = [];
        foreach ($gmc_discount_list as $k => $v) {
            $pin_str = $v['channels_name'] . '-' . $v['method_name'];
            $gmc_info[$v['spec_sn']][$pin_str][] = $v;
        }
        //获取合单商品对应的需求单数据
        $dg_model = new DemandGoodsModel();
        $sum_goods_info = $dg_model->sumDemandGoods($sd_sn_arr);

        $sum_goods_list = [];
        $demand_list = [];
        $dg_goods_num = [];
        foreach ($sum_goods_info as $k => $v) {
            if (!in_array($v['demand_sn'], $demand_list)) {
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
    public function createUploadSumDemandGoods($res, $sum_demand_goods, $pcm_list, $sdcg_list, $channel_start_num)
    {
        $upload_goods_info = [];
        $error_spec_info = [];
        $error_pcm_info = [];
        foreach ($res as $k => $v) {
            if ($k < 3) continue;
            //进行商品是否存在需求判断
            $spec_sn = $v[0];
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
                    //判断上传的商品是否已经分配过
                    if ($may_num == 0 && !isset($sdcg_list[$spec_sn][$channels_name]['may_num'])) {
                        continue;
                    }
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
            $error_spec_info = json_encode($error_spec_info);
            $error_info = substr($error_spec_info, 1, -1);
            return ['code' => '1101', 'msg' => '您上传的商品:' . $error_info . '不存在需求信息,请检查'];
        }
        if (!empty($error_pcm_info)) {
            $error_pcm_info = json_encode($error_pcm_info);
            $error_info = substr($error_pcm_info, 1, -1);
            return ['code' => '1103', 'msg' => '您上传的商品对应的:' . $error_info . '渠道方式信息有误,请检查'];
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
            DB::raw('sum(jms_sdg.goods_num) as goods_num'),
            DB::raw('sum(jms_sdg.goods_num) as final_goods_num'),
            DB::raw('sum(jms_sdg.goods_num) - sum(jms_sdg.allot_num) as may_buy_num'),
            DB::raw('sum(jms_sdg.real_num) as real_buy_num'),
            DB::raw('sum(jms_sdg.real_num) as final_buy_num'),
//            DB::raw('round(sum(jms_sdg.real_num) / (sum(jms_sdg.goods_num) - sum(jms_sdg.allot_num)) * 100, 2) as real_buy_rate'),
//            DB::raw('round((sum(jms_sdg.goods_num) - sum(jms_sdg.allot_num) - sum(jms_sdg.real_num)) /
//            (sum(jms_sdg.goods_num) - sum(jms_sdg.allot_num)) * 100,2) as miss_buy_rate')
        ];
        $field = array_merge($field, $tmp_field);
        $sum_demand_statistic = DB::table($this->table)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sdg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->whereIn('sdg.sum_demand_sn', $sd_sn_arr)
            ->groupBy('sdg.sum_demand_sn')
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
    public function get_sdg_info($sd_sn_info)
    {
        $field = ['sdg.spec_sn',
            DB::raw('SUM(jms_sdg.goods_num) - SUM(jms_sdg.allot_num) as total_may_buy_num'),
            DB::raw('SUM(jms_sdg.real_num) as total_real_buy_num')
        ];
        $sdg_info = DB::table($this->table)
            ->whereIn('sdg.sum_demand_sn', $sd_sn_info)->groupBy('sdg.spec_sn')->get($field)
            ->groupBy('spec_sn');
        $sdg_info = objectToArrayZ($sdg_info);
        return $sdg_info;
    }

}
