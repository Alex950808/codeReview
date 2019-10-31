<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SortBatchModel extends Model
{
    public $table = 'sort_batch as sb';
    private $field = [
        'sb.id', 'sb.sum_demand_sn', 'sb.demand_sn', 'sb.real_purchase_sn',
        'sb.spec_sn', 'sb.num', 'sb.create_time',
    ];

    /**
     * description 插入数据
     * author zhangdong
     * date 2019.06.01
     */
    public function insertData(array $arrData = [])
    {
        $sb = getTableName($this->table);
        $insertRes = DB::table($sb)->insert($arrData);
        return $insertRes;
    }

    /**
     * description 统计批次某个SKU的分货记录
     * author zhangdong
     * date 2019.06.01
     */
    public function countSortBatch($sumDemandSn, $demandSn, $realPurchaseSn, $specSn)
    {
        $where = [
            ['sum_demand_sn', $sumDemandSn],
            ['demand_sn', $demandSn],
            ['real_purchase_sn', $realPurchaseSn],
            ['spec_sn', $specSn],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }

    /**
     * description 修改已分货数量
     * author:zhangdong
     * date : 2019.06.03
     */
    public function modifyNum($sumDemandSn, $demandSn, $realPurchaseSn, $specSn, $num)
    {
        $where = [
            ['sum_demand_sn', $sumDemandSn],
            ['demand_sn', $demandSn],
            ['real_purchase_sn', $realPurchaseSn],
            ['spec_sn', $specSn],
        ];
        $update = [
            'num' => DB::raw('num + ' . $num),
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description 统计批次单分货记录条数
     * author zhangdong
     * date 2019.06.01
     */
    public function countNumByBatch($sumDemandSn, $realPurchaseSn)
    {
        $where = [
            ['sum_demand_sn', $sumDemandSn],
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }

    /**
     * description 获取指定需求单分货数据
     * author zongxing
     * date 2019.06.22
     */
    public function getSortByDemandSn($demand_sn)
    {
        $field = [
            'sb.sum_demand_sn', 'sb.demand_sn', 'sb.real_purchase_sn', 'sb.num', 'sb.spec_price', 'sb.channel_discount',
            'sb.real_discount', 'gs.spec_weight', 'gs.estimate_weight', 'dg.sale_discount', 'dg.spec_sn', 'g.brand_id',
            'rpa.original_or_discount', 'sb.lvip_price',
        ];
        $sb_info = DB::table('sort_batch as sb')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'sb.demand_sn')
            ->leftJoin('demand_goods as dg', function ($join) {
                $join->on('dg.demand_sn', '=', 'd.demand_sn');
                $join->on('dg.spec_sn', '=', 'sb.spec_sn');
            })
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'sb.real_purchase_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sb.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->whereIn('sb.demand_sn', $demand_sn)
            ->get($field);
        $sb_info = objectToArrayZ($sb_info);
        $total_sort_list = [];
        foreach ($sb_info as $k => $v) {
            $tmp_demand_sn = $v['demand_sn'];
            $spec_sn = $v['spec_sn'];
            $real_discount = $v['real_discount'];;
            $spec_weight = floatval($v['spec_weight']);
            $estimate_weight = floatval($v['estimate_weight']);
            $real_weight = $spec_weight == 0 ? $estimate_weight : $spec_weight;

            $original_or_discount = intval($v['original_or_discount']);
            $sort_num = intval($v['num']);
            $spec_price = floatval($v['spec_price']);
            $lvip_price = floatval($v['lvip_price']);
            $sale_discount = floatval($v['sale_discount']);
            $real_price = $original_or_discount == 1 ? $lvip_price : $spec_price;
            $sort_price = $sort_num * $real_price;//实采总金额
            if ($spec_price == 0 || $sale_discount == 0) {
                $sort_discount_price = 0;
            } else {
                $sort_discount_price = ($sort_num * $spec_price *
                    (1 - (
                            ($real_discount + ($real_weight / $spec_price / 0.0022 / 100)) / $sale_discount)
                    )
                );//需求单分货报价毛利金额
            }
            $sort_sale_price = $sort_num * $spec_price * $sale_discount;//需求单分货销售金额
            if (isset($total_sort_list[$tmp_demand_sn][$spec_sn])) {
                $total_sort_list[$tmp_demand_sn][$spec_sn]['sort_num'] += $sort_num;
                $total_sort_list[$tmp_demand_sn][$spec_sn]['sort_price'] += $sort_price;
                $total_sort_list[$tmp_demand_sn][$spec_sn]['sort_discount_price'] += $sort_discount_price;
                $total_sort_list[$tmp_demand_sn][$spec_sn]['sort_sale_price'] += $sort_sale_price;
            } else {
                $total_sort_list[$tmp_demand_sn][$spec_sn] = [
                    'demand_sn' => $tmp_demand_sn,
                    'sort_num' => $sort_num,
                    'sort_price' => number_format($sort_price, 2, '.', ''),
                    'sort_discount_price' => number_format($sort_discount_price, 2, '.', ''),
                    'sort_sale_price' => number_format($sort_sale_price, 2, '.', ''),
                ];
            }
        }
        $return_info = [];
        foreach ($total_sort_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $sort_num = intval($v1['sort_num']);
                $sort_price = floatval($v1['sort_price']);
                $sort_discount_price = floatval($v1['sort_discount_price']);
                $sort_sale_price = floatval($v1['sort_sale_price']);
                if (isset($return_info[$k])) {
                    $return_info[$k][0]['sort_num'] += $sort_num;
                    $return_info[$k][0]['sort_price'] += $sort_price;
                    $return_info[$k][0]['sort_discount_price'] += $sort_discount_price;
                    $return_info[$k][0]['sort_sale_price'] += $sort_sale_price;
                } else {
                    $return_info[$k][0] = [
                        'demand_sn' => $k,
                        'sort_num' => $sort_num,
                        'sort_price' => number_format($sort_price, 2, '.', ''),
                        'sort_discount_price' => number_format($sort_discount_price, 2, '.', ''),
                        'sort_sale_price' => number_format($sort_sale_price, 2, '.', ''),
                    ];
                }
            }
        }
        return $return_info;
    }


    /**
     * description 获取各个实采渠道的毛利
     * author zongxing
     * date 2019.06.29
     */
    public function getBatchDiscountInfo($param_info)
    {
        $where = [];
        if (!empty($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
        }
        if (!empty($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
        }
        if (isset($param_info['month'])) {
            $month = trim($param_info['month']);
            $start_time = Carbon::parse($month)->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::parse($month)->endOfMonth()->toDateTimeString();
        }
        if (!isset($param_info['start_time']) && !isset($param_info['end_time']) && !isset($param_info['month'])) {
            $start_time = Carbon::now()->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::now()->endOfMonth()->toDateTimeString();
        }
        $where[] = ['rp.delivery_time', '>=', $start_time];
        $where[] = ['rp.delivery_time', '<=', $end_time];
        $field = ['pc.channels_name', 'pm.method_name', 'sb.spec_sn', 'sb.num', 'sb.spec_price', 'dg.sale_discount',
            'sb.real_discount', 'gs.spec_weight', 'gs.estimate_weight',
        ];
        $batch_discount_info = DB::table('sort_batch as sb')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'sb.demand_sn')
            ->leftJoin('demand_goods as dg', function ($join) {
                $join->on('dg.demand_sn', '=', 'd.demand_sn');
                $join->on('dg.spec_sn', '=', 'sb.spec_sn');
            })
            ->leftJoin('real_purchase as rp', function ($join) {
                $join->on('rp.real_purchase_sn', '=', 'sb.real_purchase_sn');
            })
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rp.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rp.method_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sb.spec_sn')
            ->where($where)->get($field);
        $batch_discount_info = objectToArrayZ($batch_discount_info);
        $batch_list = [];
        foreach ($batch_discount_info as $k => $v) {
            $channels_name = trim($v['channels_name']);
            $method_name = trim($v['method_name']);
            $pin_str = $channels_name . '-' . $method_name;
            $sort_num = intval($v['num']);
            $spec_price = floatval($v['spec_price']);
            $sale_discount = floatval($v['sale_discount']);
            $real_discount = floatval($v['real_discount']);
            $spec_weight = floatval($v['spec_weight']);
            $estimate_weight = floatval($v['estimate_weight']);
            $real_weight = $spec_weight == 0 ? $estimate_weight : $spec_weight;
            if ($spec_price == 0 || $sale_discount == 0) {
                $sort_discount_price = 0;
            } else {
                //重价比折扣=单重/美金原价/0.0022/100
                //erp逻辑毛利 = （1-（EXW折扣+重价折扣）/报价折扣） * 100%
                $sort_discount_price = ($sort_num * $spec_price *
                    (1 - (
                            ($real_discount + ($real_weight / $spec_price / 0.0022 / 100)) / $sale_discount)
                    )
                );//实采分货毛利金额
            }
            $sort_sale_price = $sort_num * $spec_price * $sale_discount;//实采总销售金额
            if (isset($batch_list[$pin_str])) {
                $batch_list[$pin_str][0]['sort_sale_price'] += $sort_sale_price;
                $batch_list[$pin_str][0]['sort_discount_price'] += $sort_discount_price;
            } else {
                $batch_list[$pin_str][0] = [
                    'channel_method_name' => $pin_str,
                    'sort_sale_price' => number_format($sort_sale_price, 2, '.', ''),
                    'sort_discount_price' => number_format($sort_discount_price, 2, '.', '')
                ];
            }
        }
        return $batch_list;
    }

    /**
     * description 查询批次被分货条数
     * author zhangdong
     * date 2019.10.08
     */
    public function countSortNum($arrSumDemandSn, $arrRealSn)
    {

        $field = [
            DB::raw('count(*) as num'),'sum_demand_sn','real_purchase_sn'
        ];
        $queryRes = DB::table($this->table)->select($field)->whereIn('sum_demand_sn', $arrSumDemandSn)
            ->whereIn('real_purchase_sn', $arrRealSn)->groupBy('sum_demand_sn','real_purchase_sn')
            ->get();
        return $queryRes;
    }



}//end of class
