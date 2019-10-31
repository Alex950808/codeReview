<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SumDemandChannelGoodsModel extends Model
{
    protected $table = 'sum_demand_channel_goods as sdcg';

    protected $field = [
        'sdcg.id', 'sdcg.sum_demand_sn', 'sdcg.spec_sn', 'sdcg.method_id', 'sdcg.channels_id', 'sdcg.channel_discount',
        'sdcg.may_num', 'sdcg.real_num'
    ];

    /**
     * description:获取汇总单商品分配方案
     * editor:zongxing
     * date : 2019.05.16
     * return Array
     */
    public function sumDemandGoodsAllotInfo($param_info, $spec_sn = [])
    {
        $field = [
            'sdcg.id', 'sdcg.sum_demand_sn', 'sdcg.spec_sn', 'sdcg.method_id', 'sdcg.channels_id', 'sdcg.channel_discount',
            'sdcg.may_num', 'sdcg.real_num', 'method_name', 'channels_name',
            DB::raw("concat_ws('-',jms_pc.channels_name,jms_pm.method_name) as pcm_sn"),
            DB::raw("(jms_sdcg.may_num - jms_sdcg.real_num) as diff_num"),
            DB::raw("(jms_sdcg.may_num - jms_sdcg.real_num)* jms_gs.spec_price as diff_price")
        ];

        $sdcg_obj = DB::table($this->table)
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'sdcg.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'sdcg.method_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sdcg.spec_sn');
        if (!empty($param_info['sd_sn_arr'])) {
            $sd_sn_arr = $param_info['sd_sn_arr'];
            $sdcg_obj->whereIn('sdcg.sum_demand_sn', $sd_sn_arr);
        }
        if (!empty($param_info['method_id'])) {
            $method_id = intval($param_info['method_id']);
            $sdcg_obj->where('sdcg.method_id', '=', $method_id);
        }
        if (!empty($param_info['channels_id'])) {
            $channels_id = intval($param_info['channels_id']);
            $sdcg_obj->where('sdcg.channels_id', '=', $channels_id);
        }
        if (!empty($spec_sn)) {
            $sdcg_obj->whereIn('sdcg.spec_sn', $spec_sn);
        }
        $sdcg_info = $sdcg_obj->where('sdcg.status', 1)->get($field);
        $sdcg_info = objectToArrayZ($sdcg_info);
        $sdcg_list = [];
        foreach ($sdcg_info as $k => $v) {
            if (isset($sdcg_list[$v['spec_sn']][$v['pcm_sn']])) {
                $sdcg_list[$v['spec_sn']][$v['pcm_sn']]['may_num'] += intval($v['may_num']);
                $sdcg_list[$v['spec_sn']][$v['pcm_sn']]['real_num'] += intval($v['real_num']);
            } else {
                $sdcg_list[$v['spec_sn']][$v['pcm_sn']] = $v;
            }
        }
        return $sdcg_list;
    }

    /**
     * description:上传汇总单商品分配方案
     * editor:zongxing
     * date : 2019.05.16
     * return Array
     */
    public function allotSumDemandGoods($sum_demand_detail, $upload_goods_info, $sum_demand_sn, $sdcg_list)
    {
        $sum_demand_goods = [];
        foreach ($sum_demand_detail as $k => $v) {
            $sum_demand_goods[$v['spec_sn']] = $v;
        }

        $spec_arr_info = array_keys($upload_goods_info);
        $upload_goods = [];
        $update_sdcg_goods = [];
        $update_sdg_goods = [];
        foreach ($upload_goods_info as $k => $v) {
            $total_allot_num = 0;
            foreach ($v as $k1 => $v1) {
                $channels_discount = $v1['channel_discount'];
                $may_num = intval($v1['may_num']);
                //已经存在分配数据-更新
                if (isset($sdcg_list[$k][$k1])) {
                    $id = $sdcg_list[$k][$k1]['id'];
                    $old_may_num = $sdcg_list[$k][$k1]['may_num'];
                    $update_sdcg_goods['may_num'][] = [
                        $id => $may_num
                    ];
                    $update_sdcg_goods['channel_discount'][] = [
                        $id => $channels_discount
                    ];
                    $diff_num = $may_num - $old_may_num;
                } else {//新增
                    $diff_num = $may_num;
                    $upload_goods[] = [
                        'sum_demand_sn' => $sum_demand_sn,
                        'spec_sn' => $k,
                        'channels_id' => $v1['channels_id'],
                        'method_id' => $v1['method_id'],
                        'channel_discount' => $channels_discount,
                        'may_num' => $may_num,
                    ];
                }
                $total_allot_num += $diff_num;
            }
            //更新汇总需求单商品可分配数
            if (isset($sum_demand_goods[$k])) {
                $update_sdg_goods['allot_num'][] = [
                    $k => 'allot_num - ' . $total_allot_num
                ];
            }
        }

        $update_sdcg_goods_sql = '';
        if (!empty($update_sdcg_goods)) {
            //需要判断的字段
            $column = 'id';
            $update_sdcg_goods_sql = makeBatchUpdateSql('jms_sum_demand_channel_goods', $update_sdcg_goods, $column);
        }
        $update_sdg_goods_sql = '';
        if (!empty($update_sdg_goods)) {
            $where = [
                'sum_demand_sn' => $sum_demand_sn
            ];
            //需要判断的字段
            $column = 'spec_sn';
            $update_sdg_goods_sql = makeBatchUpdateSql('jms_sum_goods', $update_sdg_goods, $column, $where);
        }
        $res = DB::transaction(function () use (
            $upload_goods, $spec_arr_info, $sum_demand_sn, $update_sdcg_goods_sql,
            $update_sdg_goods_sql
        ) {
            //新增汇总单渠道商品统计表
            if (!empty($upload_goods)) {
                DB::table('sum_demand_channel_goods')->insert($upload_goods);
            }
            //更新汇总单渠道商品统计表
            if (!empty($update_sdcg_goods_sql)) {
                DB::update(DB::raw($update_sdcg_goods_sql));
            }
            //更新汇总单商品表可分配数
            if (!empty($update_sdg_goods_sql)) {
                DB::update(DB::raw($update_sdg_goods_sql));
            }
            //更新采购期渠道商品表
            $update_data = ['edit_status' => 1];
            DB::table('sum_goods')
                ->where('sum_demand_sn', $sum_demand_sn)->whereIn('spec_sn', $spec_arr_info)
                ->update($update_data);
            //更新合单状态
            $update_data = ['status' => 2];
            $res = DB::table('sum')->where('sum_demand_sn', $sum_demand_sn)->update($update_data);
            return $res;
        });
        return $res;
    }

    /**
     * description:上传汇总单缺口商品分配方案
     * editor:zongxing
     * date : 2019.06.21
     * return Array
     */
    public function allotSumDemandDiffGoods($sum_demand_detail, $upload_goods_info, $sum_demand_sn, $sdcg_list)
    {
        $sum_demand_goods = [];
        foreach ($sum_demand_detail as $k => $v) {
            $sum_demand_goods[$v['spec_sn']] = $v;
        }

        $upload_goods = [];
        $update_sdcg_goods = [];
        foreach ($upload_goods_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $channels_discount = $v1['channel_discount'];
                $may_num = intval($v1['may_num']);
                //已经存在分配数据-更新
                if (isset($sdcg_list[$k][$k1])) {
                    $id = $sdcg_list[$k][$k1]['id'];
                    $update_sdcg_goods['may_num'][] = [
                        $id => $may_num
                    ];
                    $update_sdcg_goods['channel_discount'][] = [
                        $id => $channels_discount
                    ];
                } else {//新增
                    $upload_goods[] = [
                        'sum_demand_sn' => $sum_demand_sn,
                        'spec_sn' => $k,
                        'channels_id' => $v1['channels_id'],
                        'method_id' => $v1['method_id'],
                        'channel_discount' => $channels_discount,
                        'may_num' => $may_num,
                    ];
                }
            }
        }

        $update_sdcg_goods_sql = '';
        if (!empty($update_sdcg_goods)) {
            //需要判断的字段
            $column = 'id';
            $update_sdcg_goods_sql = makeBatchUpdateSql('jms_sdg_channel_log', $update_sdcg_goods, $column);
        }

        $res = DB::transaction(function () use ($upload_goods, $update_sdcg_goods_sql) {
            //新增汇总单渠道商品统计表
            if (!empty($upload_goods)) {
                $res = DB::table('sdg_channel_log')->insert($upload_goods);
            }
            //更新汇总单渠道商品统计表
            if (!empty($update_sdcg_goods_sql)) {
                $res = DB::update(DB::raw($update_sdcg_goods_sql));
            }
            return $res;
        });
        return $res;
    }

}
