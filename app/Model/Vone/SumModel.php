<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SumModel extends Model
{
    protected $table = 'sum as s';

    protected $field = [
        's.id', 's.sum_demand_sn', 's.sum_demand_name', 's.status', 's.create_time'
    ];

    protected $status = [
        '1' => '待分配',
        '2' => '采购中',
        '3' => '停止采购',
        '4' => '关闭',
    ];

    /**
     * description:获取合单列表
     * editor:zongxing
     * date : 2019.05.13
     * return Object
     */
    public function purchaseTaskList($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $demand_list_obj = DB::table('sum_goods as sg')
            ->select('s.sum_demand_sn', 's.sum_demand_name', 's.status',
                DB::raw('count(jms_sg.spec_sn) as sku_num'),
                DB::raw('sum(jms_sg.goods_num) as goods_num'),
                DB::raw('Round(SUM(jms_sg.goods_num * jms_gs.spec_price), 2) as total_purchase_price'),
                DB::raw('sum(jms_sg.allot_num) as allot_num'),
                DB::raw('sum(jms_sg.real_num) as real_num'),
                DB::raw('DATE(jms_s.create_time) as create_time'),
                DB::raw('sum(jms_sg.goods_num - jms_sg.real_num) as diff_num'),
                DB::raw('Round(SUM((jms_sg.goods_num - jms_sg.real_num) * jms_gs.spec_price), 2) as diff_purchase_price'),
                DB::raw('Round(SUM(jms_sg.real_num) / SUM(jms_sg.goods_num) * 100, 2) as real_rate')
            )
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sg.sum_demand_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn');
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $demand_list_obj->where(function ($where) use ($query_sn) {
                $where->Orwhere('s.sum_demand_sn', '=', $query_sn)
                    ->Orwhere('sd.demand_sn', '=', $query_sn);
            });
        }
        if (isset($param_info['status'])) {
            $status = $param_info['status'];
            $demand_list_obj->where('s.status', $status);
        } else {
            $demand_list_obj->whereIn('s.status', [1, 2, 3]);
        }
        if (isset($param_info['is_page']) && $param_info['is_page'] == 0) {
            $sum_demand_list = $demand_list_obj->where('sg.goods_num', '!=', 0)
                ->orderBy('s.create_time', 'DESC')->groupBy('sg.sum_demand_sn')
                ->limit($page_size)
                ->get();
        } else {
            $sum_demand_list = $demand_list_obj->where('sg.goods_num', '!=', 0)
                ->orderBy('s.create_time', 'DESC')->groupBy('sg.sum_demand_sn')
                ->paginate($page_size);
        }
        $sum_demand_list = objectToArrayZ($sum_demand_list);
        $sum_demand_sn = [];
        $tmp_sd_list = $sum_demand_list;
        if (isset($sum_demand_list['data'])) {
            $tmp_sd_list = $sum_demand_list['data'];
        }
        foreach ($tmp_sd_list as $k => $v) {
            $sum_demand_sn[] = $v['sum_demand_sn'];
        }

        $sd_obj = DB::table('sum_demand as sd')
            ->select(DB::raw('count(jms_sd.demand_sn) as demand_num'), 's.sum_demand_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sd.sum_demand_sn');
        $sdcg_obj = DB::table('sum_demand_channel_goods as sdcg')
            ->select(DB::raw('sum(jms_sdcg.may_num) as may_num'), 's.sum_demand_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sdcg.sum_demand_sn');
        $sd_info = $sd_obj->whereIn('sd.status', [1, 3])->whereIn('s.sum_demand_sn', $sum_demand_sn)->orderBy('s.create_time', 'DESC')
            ->groupBy('sd.sum_demand_sn')
            ->get();
        $sd_info = objectToArrayZ($sd_info);
        $sd_list = [];
        foreach ($sd_info as $k => $v) {
            $sd_list[$v['sum_demand_sn']] = $v['demand_num'];
        }
        $sdcg_info = $sdcg_obj->where('sdcg.status', 1)->whereIn('s.sum_demand_sn', $sum_demand_sn)->orderBy('s.create_time', 'DESC')
            ->groupBy('sdcg.sum_demand_sn')
            ->get();
        $sdcg_info = objectToArrayZ($sdcg_info);
        $sdcg_list = [];
        foreach ($sdcg_info as $k => $v) {
            $sdcg_list[$v['sum_demand_sn']] = $v['may_num'];
        }
        //获取合单分货表数据
        $rpd_goods_info = DB::table('real_purchase_detail_audit as rpda')->whereIn('rpda.purchase_sn', $sum_demand_sn)
            ->groupBy('rpda.purchase_sn')
            ->pluck(DB::raw('sum(jms_rpda.sort_num) as sort_num'), 'rpda.purchase_sn');
        $rpd_goods_info = objectToArrayZ($rpd_goods_info);
        $sum_demand_status = $this->status;
        foreach ($tmp_sd_list as $k => $v) {
            $sum_demand_sn = $v['sum_demand_sn'];
            $demand_list['data'][$k]['total_purchase_price'] = number_format($v['total_purchase_price'], 2);
            $tmp_sd_list[$k]['status'] = $sum_demand_status[$v['status']];
            //合单下需求单的个数
            if (isset($sd_list[$sum_demand_sn])) {
                $tmp_sd_list[$k]['demand_num'] = $sd_list[$sum_demand_sn];
            }
            //合单下可采数
            if (isset($sdcg_list[$sum_demand_sn])) {
                $tmp_sd_list[$k]['may_num'] = $sdcg_list[$sum_demand_sn];
            }
            //合单下待分货数
            if (isset($rpd_goods_info[$sum_demand_sn])) {
                $tmp_sd_list[$k]['sort_num'] = $rpd_goods_info[$sum_demand_sn];
            }
        }
        if (isset($sum_demand_list['data'])) {
            $sum_demand_list['data'] = $tmp_sd_list;
        }else{
            $sum_demand_list = $tmp_sd_list;
        }
        return $sum_demand_list;
    }

    /**
     * description:获取合单列表-App
     * editor:zongxing
     * date : 2019.08.22
     * return Object
     */
    public function purchaseTaskListApp($param_info)
    {
        $demand_list_obj = DB::table('sum_goods as sg')
            ->select('s.sum_demand_sn', 's.sum_demand_name', 's.status',
                DB::raw('count(jms_sg.spec_sn) as sku_num'),
                DB::raw('sum(jms_sg.goods_num) as goods_num'),
                DB::raw('Round(SUM(jms_sg.goods_num * jms_gs.spec_price), 2) as total_purchase_price'),
                DB::raw('sum(jms_sg.allot_num) as allot_num'),
                DB::raw('sum(jms_sg.real_num) as real_num'),
                DB::raw('DATE(jms_s.create_time) as create_time'),
                DB::raw('sum(jms_sg.goods_num - jms_sg.real_num) as diff_num'),
                DB::raw('Round(SUM((jms_sg.goods_num - jms_sg.real_num) * jms_gs.spec_price), 2) as diff_purchase_price'),
                DB::raw('Round(SUM(jms_sg.real_num) / SUM(jms_sg.goods_num) * 100, 2) as real_rate')
            )
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sg.sum_demand_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn');
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $demand_list_obj->where(function ($where) use ($query_sn) {
                $where->Orwhere('s.sum_demand_sn', '=', $query_sn)
                    ->Orwhere('sd.demand_sn', '=', $query_sn);
            });
        }
        if (isset($param_info['status'])) {
            $status = $param_info['status'];
            $demand_list_obj->where('s.status', $status);
        } else {
            $demand_list_obj->whereIn('s.status', [1, 2, 3]);
        }
        $demand_list_obj->where('sg.goods_num', '!=', 0)
            ->orderBy('s.create_time', 'DESC')->groupBy('sg.sum_demand_sn');
        //如果需要分页
        if (isset($param_info['is_page']) && intval($param_info['is_page']) == 1) {
            $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
            $sum_demand_list = $demand_list_obj->whereIn('s.status', [3])->paginate($page_size);
            $sum_demand_list = objectToArrayZ($sum_demand_list);
        } else {
            $sd_detail_list = $demand_list_obj->whereIn('s.status', [1, 2])->get();
            $sd_detail_list = objectToArrayZ($sd_detail_list);
            $sum_demand_list['data'] = $sd_detail_list;
        }
        $sum_demand_sn = [];
        foreach ($sum_demand_list['data'] as $k => $v) {
            $sum_demand_sn[] = $v['sum_demand_sn'];
        }

        $sd_obj = DB::table('sum_demand as sd')
            ->select(DB::raw('count(jms_sd.demand_sn) as demand_num'), 's.sum_demand_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sd.sum_demand_sn');
        $sdcg_obj = DB::table('sum_demand_channel_goods as sdcg')
            ->select(DB::raw('sum(jms_sdcg.may_num) as may_num'), 's.sum_demand_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'sdcg.sum_demand_sn');
        $sd_info = $sd_obj->whereIn('sd.status', [1, 3])->whereIn('s.sum_demand_sn', $sum_demand_sn)->orderBy('s.create_time', 'DESC')
            ->groupBy('sd.sum_demand_sn')
            ->get();
        $sd_info = objectToArrayZ($sd_info);
        $sd_list = [];
        foreach ($sd_info as $k => $v) {
            $sd_list[$v['sum_demand_sn']] = $v['demand_num'];
        }
        $sdcg_info = $sdcg_obj->where('sdcg.status', 1)->whereIn('s.sum_demand_sn', $sum_demand_sn)->orderBy('s.create_time', 'DESC')
            ->groupBy('sdcg.sum_demand_sn')
            ->get();
        $sdcg_info = objectToArrayZ($sdcg_info);
        $sdcg_list = [];
        foreach ($sdcg_info as $k => $v) {
            $sdcg_list[$v['sum_demand_sn']] = $v['may_num'];
        }
        //获取合单分货表数据
        $rpd_goods_info = DB::table('real_purchase_detail_audit as rpda')->whereIn('rpda.purchase_sn', $sum_demand_sn)
            ->groupBy('rpda.purchase_sn')
            ->pluck(DB::raw('sum(jms_rpda.sort_num) as sort_num'), 'rpda.purchase_sn');
        $rpd_goods_info = objectToArrayZ($rpd_goods_info);
        $sum_demand_status = $this->status;
        foreach ($sum_demand_list['data'] as $k => $v) {
            $sum_demand_sn = $v['sum_demand_sn'];
            $demand_list['data'][$k]['total_purchase_price'] = number_format($v['total_purchase_price'], 2);
            $sum_demand_list['data'][$k]['status'] = $sum_demand_status[$v['status']];
            //合单下需求单的个数
            $demand_num = 0;
            if (isset($sd_list[$sum_demand_sn])) {
                $demand_num = $sd_list[$sum_demand_sn];
            }
            $sum_demand_list['data'][$k]['demand_num'] = $demand_num;
            //合单下可采数
            $may_num = 0;
            if (isset($sdcg_list[$sum_demand_sn])) {
                $may_num = $sdcg_list[$sum_demand_sn];
            }
            $sum_demand_list['data'][$k]['may_num'] = $may_num;
            //合单下待分货数
            $sort_num = 0;
            if (isset($rpd_goods_info[$sum_demand_sn])) {
                $sort_num = $rpd_goods_info[$sum_demand_sn];
            }
            $sum_demand_list['data'][$k]['sort_num'] = $sort_num;
        }
        return $sum_demand_list;
    }

    /**
     * description:根据交付日期获取汇总单单号和相关的需求单
     * editor:zongxing
     * date : 2019.05.29
     * return Array
     */
    public function getSumDemandInfo($param = [])
    {
        $where = [];
        if (isset($param['sum_id'])) {
            $sum_id = intval($param['sum_id']);
            $where[] = ['s.id', '=', $sum_id];
        }

        $field = $this->field;
        $add_field = ['sd.id as sd_id', 'sd.demand_sn', 'mos.external_sn', 'sd.sort', 'sd.status as sd_status',
            'su.user_name', 'd.expire_time',
            DB::raw('if(jms_d.arrive_store_time,jms_d.arrive_store_time,"") as arrive_store_time')];
        $field = array_merge($field, $add_field);
        $sum_list_obj = DB::table($this->table)
            ->leftJoin('sum_demand as sd', 'sd.sum_demand_sn', '=', 's.sum_demand_sn')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'sd.demand_sn')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->where($where);
        $is_zero = intval($param['is_zero']);
        if ($is_zero) {
            $sum_list_obj->where('sd.status', 1);
        }
        if (isset($param['is_group']) && $param['is_group'] == 1) {
            $status = [1, 2];
            $sum_list = $sum_list_obj->whereIn('s.status', $status)->get($field)->groupBy('sum_demand_sn');
        } else {
            $sum_list = $sum_list_obj->get($field);
        }
        $sum_list = objectToArrayZ($sum_list);
        return $sum_list;

    }

    /**
     * description:获取合单数据
     * editor:zongxing
     * date : 2019.06.17
     * return Array
     */
    public function getSumInfo()
    {
        $sum_info = DB::table($this->table)->select($this->field)->get();
        $sum_info = objectToArrayZ($sum_info);
        return $sum_info;
    }

    /**
     * description:获取合单信息
     * author:zhangdong
     * date:2019.05.30
     */
    public function querySumInfo(array $arrSumDemandSn = [])
    {
        $queryRes = DB::table($this->table)->select($this->field)
            ->whereIn('sum_demand_sn', $arrSumDemandSn)->get();
        return $queryRes;
    }

    /**
     * description:更新合单及需求单数据
     * editor:zongxing
     * date : 2019.06.28
     * return Boolean
     */
    public function updateSumTotalInfo($sum_demand_sn, $sd_num, $demand_sn_arr)
    {
        //获取需求单信息
        $demand_info = DB::table('demand')->whereIn('demand_sn', $demand_sn_arr)->pluck('demand_type', 'demand_sn')->toArray();
        $update_demand_info = [];
        foreach ($demand_info as $k => $v) {
            if ($v == 2) {
                $update_demand_info['status_7'][] = $k;
            } elseif ($v == 1) {
                $update_demand_info['status_1'][] = $k;
            }
        }

        //获取拆分需求单对应的商品数据
//        $dg_model = new DemandGoodsModel();
//        $dg_total_info = $dg_model->getDgTotalInfo($demand_sn_arr);
        $sd_model = new SortDataModel();
        $dg_total_info = $sd_model->getDgTotalInfo($demand_sn_arr, $sum_demand_sn);
        $update_spec_info = [];
        $update_goods_info = [];
        foreach ($dg_total_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $demand_sn = $v1['demand_sn'];
                $spec_sn = $v1['spec_sn'];
                $goods_num = intval($v1['goods_num']);
                if ($demand_info[$k] == 2) {
                    $update_spec_info[$demand_sn][] = $spec_sn;
                }
                if (isset($update_goods_info[$spec_sn])) {
                    $update_goods_info[$spec_sn] += $goods_num;
                } else {
                    $update_goods_info[$spec_sn] = $goods_num;
                }
            }
        }
        $updateSumGoodsInfo = [];
        foreach ($update_goods_info as $k => $v) {
            $updateSumGoodsInfo['goods_num'][] = [
                $k => 'goods_num - ' . $v
            ];
        }
        $updateSumGoodsInfoSql = '';
        if (!empty($updateSumGoodsInfo)) {
            //更新条件
            $where = [
                'sum_demand_sn' => $sum_demand_sn
            ];
            //需要判断的字段
            $column = 'spec_sn';
            $updateSumGoodsInfoSql = makeBatchUpdateSql('jms_sum_goods', $updateSumGoodsInfo, $column, $where);
        }
        // dd($sd_num,$demand_sn_arr);
        $updateRes = DB::transaction(function () use (
            $sd_num, $sum_demand_sn, $demand_sn_arr, $updateSumGoodsInfoSql,
            $update_demand_info, $update_spec_info
        ) {
            $update_status = [
                'status' => 2
            ];
            //合单需求单表
            DB::table('sum_demand')->where('sum_demand_sn', $sum_demand_sn)->whereIn('demand_sn', $demand_sn_arr)->update($update_status);
            //合单总采购任务分配表
            DB::table('sum_demand_channel_goods')->where('sum_demand_sn', $sum_demand_sn)->update($update_status);
            //合单缺口任务分配表
            DB::table('sdg_channel_log')->where('sum_demand_sn', $sum_demand_sn)->update($update_status);
            //如果选中了合单下所有的需求单，则注销合单
            if ($sd_num == count($demand_sn_arr)) {
                $param = [
                    'sum_demand_sn' => $sum_demand_sn,
                    'status' => 4,
                ];
                $this->updateSumStatus($param);
            }
            //更新需求单信息(会存在延期和正常两种需求单)
            if (!empty($update_demand_info['status_1'])) {
                $update_status = ['status' => 1];
                DB::table('demand')->whereIn('demand_sn', $update_demand_info['status_1'])->update($update_status);
            } elseif (!empty($update_demand_info['status_7'])) {
                $update_status = ['status' => 7];
                DB::table('demand')->whereIn('demand_sn', $update_demand_info['status_7'])->update($update_status);
            }
            //更新延期需求单商品表中的状态
            foreach ($update_spec_info as $k => $v) {
                $update_status = ['is_postpone' => 1];
                DB::table('demand_goods')->where('demand_sn', $k)->whereIn('spec_sn', $v)->update($update_status);
            }

            //删除分货表中的原始数据
            DB::table('sort_data')->where('sum_demand_sn', $sum_demand_sn)->whereIn('demand_sn', $demand_sn_arr)->delete();
            //更新合单商品数据
            if (!empty($updateSumGoodsInfoSql)) {
                DB::update(DB::raw($updateSumGoodsInfoSql));
            }
            //更新合单表的可采数
            $update_allot_num = "UPDATE jms_sum_goods set allot_num = goods_num";
            $res = DB::update(DB::raw($update_allot_num));
            return $res;
        });
        return $updateRes;
    }

    /**
     * description:更新合单状态
     * editor:zongxing
     * date : 2019.06.28
     * return Boolean
     */
    public function updateSumStatus($param)
    {
        $update_where = [
            'sum_demand_sn' => $param['sum_demand_sn']
        ];
        $update_data = [
            'status' => intval($param['status'])
        ];
        $res = DB::table($this->table)->where($update_where)->update($update_data);
        return $res;
    }


}//enf of class
