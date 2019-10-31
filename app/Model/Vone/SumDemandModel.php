<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SumDemandModel extends Model
{
    protected $table = 'sum_demand as sd';

    protected $field = [
        'sd.id', 'sd.sum_demand_sn', 'sd.demand_sn', 'sd.sort', 'sd.create_time'
    ];

    protected $status = [
        '1' => '待分配',
        '2' => '采购中',
        '3' => '已关闭',
    ];


    /**
     * description:合并需求单
     * editor:zongxing
     * date : 2019.05.13
     * return Array
     */
    public function insertSumDemand($param_info, $demand_info, $demand_goods_info, $demand_goods_detail)
    {
        $demand_sn_info = array_keys($demand_info);
        $now = date('Ymd', time());
        $sum_demand_sn = 'SUM' . $now . rand(pow(10, 7), pow(10, 8) - 1);
        $sum_info = [];
        $sum_demand_info = [];
        $sum_info[] = [
            'sum_demand_sn' => $sum_demand_sn,
            'sum_demand_name' => trim($param_info['sum_demand_name']),
        ];
        foreach ($demand_sn_info as $k => $v) {
            $sum_demand_info[] = [
                'sum_demand_sn' => $sum_demand_sn,
                'demand_sn' => $v,
                'sort' => $demand_info[$v],
            ];
        }
        $sum_demand_goods_info = [];
        foreach ($demand_goods_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            if (isset($sum_demand_goods_info[$spec_sn])) {
                $sum_demand_goods_info[$spec_sn]['goods_num'] += $v['goods_num'];
                $sum_demand_goods_info[$spec_sn]['allot_num'] += $v['goods_num'];
            } else {
                $sum_demand_goods_info[$spec_sn] = [
                    'sum_demand_sn' => $sum_demand_sn,
                    'goods_name' => $v['goods_name'],
                    'spec_sn' => $v['spec_sn'],
                    'erp_prd_no' => $v['erp_prd_no'],
                    'erp_merchant_no' => $v['erp_merchant_no'],
                    'goods_num' => $v['goods_num'],
                    'allot_num' => $v['goods_num'],
                ];
            }
        }
        $sum_demand_goods_info = array_values($sum_demand_goods_info);
        $sort_data_info = [];
        foreach ($demand_goods_detail as $k => $v) {
            if(!empty($v['demand_sn'])){
                $sort_data_info[] = [
                    'sum_demand_sn' => $sum_demand_sn,
                    'demand_sn' => $v['demand_sn'],
                    'sort' => $demand_info[$v['demand_sn']],
                    'spec_sn' => $v['spec_sn'],
                    'goods_num' => $v['goods_num'],
                ];
            }
        }

        $insertRes = DB::transaction(function () use (
            $sum_info, $sum_demand_info, $sum_demand_goods_info,
            $demand_sn_info, $sort_data_info
        ) {
            //更新需求单表状态
            $update_data = [
                'status' => 5
            ];
            DB::table('demand')->whereIn('demand_sn', $demand_sn_info)->update($update_data);
            //更新需求单商品表状态
            $update_data = [
                'is_postpone' => 2
            ];
            DB::table('demand_goods')->whereIn('demand_sn', $demand_sn_info)->update($update_data);
            //新增汇总需求单表
            DB::table('sum')->insert($sum_info);
            //新增汇总单需求单关系表
            DB::table('sum_demand')->insert($sum_demand_info);
            //新增合单分货表默认数据
            DB::table('sort_data')->insert($sort_data_info);
            //新增汇总需求单商品表
            $res = DB::table('sum_goods')->insert($sum_demand_goods_info);
            return $res;
        });
        return $insertRes;
    }

    /**
     * description:追加合单
     * editor:zongxing
     * date : 2019.05.29
     * return Array
     */
    public function doAddSumDemand($param, $add_goods_info, $update_demand_info, $add_demand_info, $add_demand_sn,
                                   $demand_goods_detail)
    {
        $sum_goods_info = [];
        $update_goods_num = [];
        $update_allot_num = [];
        $sum_demand_info = [];
        $sort_data_info = [];
        if (!empty($add_goods_info)) {
            $sum_demand_sn = $param['sum_demand_sn'];
            $sd_sn_arr[] = $sum_demand_sn;
            $sg_model = new SumGoodsModel();
            $sg_info = $sg_model->purchaseTaskDetail($sd_sn_arr, 0);
            $sg_list = [];
            foreach ($sg_info as $k => $v) {
                $sg_list[$v['spec_sn']] = $v;
            }
            foreach ($add_goods_info as $k => $v) {
                $spec_sn = $v['spec_sn'];
                $goods_num = $v['goods_num'];
                if (isset($sg_list[$spec_sn])) {
                    $id = $sg_list[$spec_sn]['id'];
                    $update_goods_num['goods_num'][] = [
                        $id => 'goods_num +' . $goods_num
                    ];
                    $update_allot_num['allot_num'][] = [
                        $id => 'allot_num +' . $goods_num
                    ];
                } else {
                    $sum_goods_info[] = [
                        'sum_demand_sn' => $sum_demand_sn,
                        'goods_name' => $v['goods_name'],
                        'spec_sn' => $spec_sn,
                        'erp_prd_no' => $v['erp_prd_no'],
                        'erp_merchant_no' => $v['erp_merchant_no'],
                        'goods_num' => $goods_num,
                        'allot_num' => $goods_num,
                    ];
                }
            }
            foreach ($add_demand_info as $k => $v) {
                $sum_demand_info[] = [
                    'sum_demand_sn' => $sum_demand_sn,
                    'demand_sn' => $k,
                    'sort' => $v,
                ];
            }

            foreach ($demand_goods_detail as $k => $v) {
                $sort = 0;
                if (isset($add_demand_info[$v['demand_sn']])) {
                    $sort = $add_demand_info[$v['demand_sn']];
                } elseif (isset($update_demand_info[$v['demand_sn']])) {
                    $sort = $update_demand_info[$v['demand_sn']]['sort'];
                }
                $sort_data_info[] = [
                    'sum_demand_sn' => $sum_demand_sn,
                    'demand_sn' => $v['demand_sn'],
                    'sort' => $sort,
                    'spec_sn' => $v['spec_sn'],
                    'goods_num' => $v['goods_num'],
                ];
            }
        }

        $updateGoodsNumSql = '';
        if (!empty($update_goods_num)) {
            $column = 'id';
            $updateGoodsNumSql = makeBatchUpdateSql('jms_sum_goods', $update_goods_num, $column);
        }
        $updateAllotNumSql = '';
        if (!empty($update_allot_num)) {
            $column = 'id';
            $updateAllotNumSql = makeBatchUpdateSql('jms_sum_goods', $update_allot_num, $column);
        }

        $update_demand_sort = [];
        foreach ($update_demand_info as $k => $v) {
            $sd_id = $v['sd_id'];
            $sort = $v['sort'];
            $update_demand_sort['sort'][] = [
                $sd_id => $sort
            ];
            $update_demand_sort['status'][] = [
                $sd_id => 1
            ];
        }
        $updateDemandSortSql = '';
        if (!empty($update_demand_sort)) {
            $column = 'id';
            $updateDemandSortSql = makeBatchUpdateSql('jms_sum_demand', $update_demand_sort, $column);
        }
        $insertRes = DB::transaction(function () use (
            $param, $sum_goods_info, $updateGoodsNumSql,
            $updateAllotNumSql, $updateDemandSortSql, $sum_demand_info, $add_demand_sn, $sort_data_info
        ) {
            //更新汇总需求单商品表需求数
            if (!empty($updateGoodsNumSql)) {
                DB::update(DB::raw($updateGoodsNumSql));
            }
            //更新汇总需求单商品表可分配数
            if (!empty($updateAllotNumSql)) {
                DB::update(DB::raw($updateAllotNumSql));
            }
            //更新汇总需求单表
            if (!empty($updateDemandSortSql)) {
                DB::update(DB::raw($updateDemandSortSql));
            }
            //新增汇总需求单商品表
            DB::table('sum_goods')->insert($sum_goods_info);
            //新增合单分货表默认数据
            DB::table('sort_data')->insert($sort_data_info);
            //新增汇总需求单商品表
            DB::table('sum_demand')->insert($sum_demand_info);
            $update_data = [
                'is_postpone' => 2
            ];
            DB::table('demand_goods')->whereIn('demand_sn', $add_demand_sn)->update($update_data);
            $update_data = [
                'status' => 5
            ];
            $res = DB::table('demand')->whereIn('demand_sn', $add_demand_sn)->update($update_data);
            return $res;
        });
        return $insertRes;
    }

    /**
     * description:根据需求单号获取汇总单单号
     * editor:zongxing
     * date : 2019.05.18
     * return Array
     */
    public function getSdSnList($demand_sn_arr)
    {
        $sd_sn_info = DB::table($this->table)
            ->whereIn('sd.demand_sn', $demand_sn_arr)->distinct()
            ->pluck('sum_demand_sn');
        $sd_sn_info = objectToArrayZ($sd_sn_info);
        return $sd_sn_info;
    }

    /**
     * @description:获取合单及对应需求单信息
     * @author:zhangdong
     * @date : 2019.05.30
     * @return mixed
     */
    public function getSumDemandInfo($sum_demand_sn)
    {
        $where = [
            ['sd.sum_demand_sn', $sum_demand_sn],
        ];
        $fields = [
            'sd.demand_sn', 'sd.sort', 'd.sale_user_id', 'd.expire_time',
            'dg.goods_name', 'dg.spec_sn', 'dg.erp_merchant_no', 'dg.goods_num',
        ];
        $queryRes = DB::table($this->table)->select($fields)
            ->leftJoin((new DemandModel())->getTable(), 'sd.demand_sn', 'd.demand_sn')
            ->leftJoin((new DemandGoodsModel())->getTable(), 'd.demand_sn', 'dg.demand_sn')
            ->where($where)->orderBy('dg.spec_sn', 'ASC')->get();
        return $queryRes;

    }

    /**
     * @description:获取合单信息
     * @author:zhangdong
     * @date : 2019.05.31
     */
    public function querySumDemandData($sum_demand_sn)
    {
        $where = [
            ['sum_demand_sn', $sum_demand_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)
            ->orderBy('sort', 'ASC')->get();
        return $queryRes;

    }

    /**
     * description:获取汇总单号下有多少需求单
     * editor:zongxing
     * date : 2019.06.27
     * return Array
     */
    public function getSumDemandNum($sum_demand_sn)
    {
        $sd_num = DB::table($this->table)->where('sd.sum_demand_sn', $sum_demand_sn)->whereIn('sd.status', [1, 3])->count();
        $sd_num = objectToArrayZ($sd_num);
        return $sd_num;
    }

    /**
     * description:根据需求单号获取当前有效的汇总单单号
     * editor:zongxing
     * date : 2019.07.09
     * return Array
     */
    public function getSdSnByDemandSn_stop($demand_sn)
    {
        $sd_sn_info = DB::table($this->table)
            ->where('sd.demand_sn', $demand_sn)
            ->where('sd.status', 1)
            ->first(['sum_demand_sn']);
        $sd_sn_info = objectToArrayZ($sd_sn_info);
        return $sd_sn_info;
    }


}//end of class
