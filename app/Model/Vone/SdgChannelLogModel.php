<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SdgChannelLogModel extends Model
{
    protected $table = 'sdg_channel_log as scl';

    protected $field = [
        'scl.id', 'scl.sum_demand_sn', 'scl.spec_sn', 'scl.method_id', 'scl.channels_id', 'scl.channel_discount',
        'scl.may_num'
    ];

    /**
     * description:获取汇总单商品分配方案
     * editor:zongxing
     * date : 2019.06.21
     * return Array
     */
    public function sdgDiffAllotInfo($param_info)
    {
        $where = [];
        if (!empty($param_info['method_id'])) {
            $method_id = intval($param_info['method_id']);
            $where[] = ['scl.method_id', '=', $method_id];
        }
        if (!empty($param_info['channels_id'])) {
            $channels_id = intval($param_info['channels_id']);
            $where[] = ['scl.channels_id', '=', $channels_id];
        }
        if (!empty($param_info['check_time'])) {
            $check_time = trim($param_info['check_time']);
            $where[] = ['scl.create_time', '>=', $check_time];
        }

        $other_filed = ['method_name', 'channels_name',
            DB::raw("concat_ws('-',jms_pc.channels_name,jms_pm.method_name) as pcm_sn")
        ];
        $field = $this->field;
        $field = array_merge($field, $other_filed);
        $scl_obj = DB::table($this->table)
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'scl.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'scl.method_id')
            ->where($where)
            ->where('scl.status', 1);
        if (!empty($param_info['sd_sn_arr'])) {
            $sum_demand_sn = $param_info['sd_sn_arr'];
            $scl_obj->whereIn('scl.sum_demand_sn', $sum_demand_sn);
        }
        $scl_info = $scl_obj->get($field);
        $scl_info = objectToArrayZ($scl_info);
        $scl_list = [];
        foreach ($scl_info as $k => $v) {
            $scl_list[$v['spec_sn']][$v['pcm_sn']] = $v;
        }
        return $scl_list;
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
            $res = DB::table('sum_goods')
                ->where('sum_demand_sn', $sum_demand_sn)->whereIn('spec_sn', $spec_arr_info)
                ->update($update_data);
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
            $res = DB::table('sum_goods')
                ->where('sum_demand_sn', $sum_demand_sn)->whereIn('spec_sn', $spec_arr_info)
                ->update($update_data);
            return $res;
        });
        return $res;
    }

}
