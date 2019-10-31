<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SortDataModel extends Model
{
    public $table = 'sort_data as sod';
    private $field = [
        'sod.id', 'sod.sum_demand_sn', 'sod.demand_sn', 'sod.spec_sn', 'sod.default_num',
        'sod.yet_num', 'sod.create_time',
    ];

    /**
     * description 组装分货表写入数据
     * author zhangdong
     * date 2019.05.30
     */
    public function makeSortData($sumDemandSn, $sumDemandInfo)
    {
        $sortData = [];
        foreach ($sumDemandInfo as $key => $value) {
            $sortData[] = [
                'sum_demand_sn' => $sumDemandSn,
                'demand_sn' => $value->demand_sn,
                'spec_sn' => $value->spec_sn,
                'sort' => $value->sort,
            ];
        }
        return $sortData;
    }

    /**
     * description 保存数据
     * author zhangdong
     * date 2019.05.30
     */
    public function saveData(array $sortData = [])
    {
        if (count($sortData) == 0) {
            return false;
        }
        $sortTable = getTableName($this->table);
        $saveRes = DB::table($sortTable)->insert($sortData);
        return $saveRes;
    }

    /**
     * description 统计分货条数
     * author zhangdong
     * date 2019.05.30
     */
    public function countSortData($sumDemandSn)
    {
        $where = [
            ['sum_demand_sn', $sumDemandSn],
        ];
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * description 查看合单号对应的总分货数据
     * author zhangdong
     * date 2019.05.31
     */
    public function getSortData($sumDemandSn, array $arrSpecSn = [])
    {
        $where = [
            ['sum_demand_sn', $sumDemandSn]
        ];
        $dg_on = [
            ['sod.demand_sn', 'dg.demand_sn'],
            ['sod.spec_sn', 'dg.spec_sn'],
        ];
        $fields = [
            'sod.id', 'sod.demand_sn', 'd.sale_user_id', 'd.expire_time', 'dg.goods_name',
            'dg.erp_merchant_no', 'sod.goods_num', 'sod.default_num', 'sod.yet_num', 'sod.sort',
            'dg.spec_sn',
        ];
        $queryRes = DB::table($this->table)->select($fields)
            ->leftJoin((new DemandModel())->getTable(), 'sod.demand_sn', 'd.demand_sn')
            ->leftJoin((new DemandGoodsModel())->getTable(), $dg_on)
            ->where(function ($query) use ($where, $arrSpecSn) {
                $query->where($where);
                if (count($arrSpecSn) > 0) {
                    $query->whereIn('sod.spec_sn', $arrSpecSn);
                }
            })
            ->orderBy('dg.spec_sn', 'ASC')->orderBy('sod.sort', 'ASC')->get();
        return $queryRes;

    }

    /**
     * description 组装销售用户名和还需分配数等数据
     * author zhangdong
     * date 2019.05.31
     */
    public function makeSortDataList($sortData)
    {
        //获取销售用户信息
        $saleUserInfo = (new SaleUserModel())->getSaleUserInfoInRedis();
        foreach ($sortData as $key => $value) {
            $saleUid = intval($value->sale_user_id);
            //查询销售用户名
            $searchRes = searchTwoArray($saleUserInfo, $saleUid, 'id');
            $saleUname = isset($searchRes[0]['user_name']) ? trim($searchRes[0]['user_name']) : '';
            $sortData[$key]->sale_user_name = $saleUname;
            //计算还需分配数 = 需求数-已分配数
            $still_need_num = ($value->goods_num - $value->yet_num) > 0 ?
                intval($value->goods_num - $value->yet_num) : 0;
            $sortData[$key]->still_need_num = $still_need_num;
        }
        return $sortData;

    }

    /**
     * description 过滤总分货数据中不相关的数据
     * author zhangdong
     * date 2019.05.31
     */
    public function filterSortDataByBatch(array $arrBatchGoods = [], $sortData)
    {
        //过滤总分货数据中不相关的数据并对需求单按优先级排序
        foreach ($sortData as $key => $value) {
            //在批次商品中查找，如果找不到则将其过滤
            $spec_sn = $value->spec_sn;
            $searchRes = searchTwoArray($arrBatchGoods, $spec_sn, 'spec_sn');
            if (count($searchRes) == 0) {
                unset($sortData[$key]);
                continue;
            }
        }//end of foreach
        return $sortData;
    }


    /**
     * description 批次分货-计算还需分配数，默认分配数，已分配数等信息
     * author zhangdong
     * date 2019.05.31
     */
    public function calculateSortData($sortData, $arrBatchGoods)
    {
        foreach ($sortData as $key => $value) {
            $spec_sn = $value->spec_sn;
            $searchRes = searchTwoArray($arrBatchGoods, $spec_sn, 'spec_sn');
            if (count($searchRes) == 0) {
                unset($sortData[$key]);
                continue;
            }
            //批次单对应的商品采购数
            $sort_num = intval($searchRes[0]['sort_num']);
            $sortData[$key]->spec_price = $searchRes[0]['spec_price'];
            $sortData[$key]->lvip_price = $searchRes[0]['lvip_price'];
            $sortData[$key]->pay_price = $searchRes[0]['pay_price'];
            $sortData[$key]->channel_discount = $searchRes[0]['channel_discount'];
            $sortData[$key]->real_discount = $searchRes[0]['real_discount'];
            $need_num = intval($value->goods_num);
            //还需分配数 = 需求数-已分配数
            $still_need_num = intval($need_num - $value->yet_num);
            //计算默认分配数（根据需求单的排序进行分货，靠前的先被满足）
            $diff = intval($still_need_num - $sort_num);
            //如果差值大于0，表示可分数量不足，此时默认分配数=商品采购数
            $sortData[$key]->sort_num = $sort_num;
            $updateNum = 0;
            if ($diff > 0) {
                $sortData[$key]->default_num = $sort_num;
                $sortData[$key]->yet_num = $sort_num;
                $sortData[$key]->still_need_num = $need_num - $sort_num;
                //可分数量不足时说明此时已经没有可分货的商品了，将对应的sort_num更新为0
                $updateNum = 0;
            }
            //如果差值小于0，表示可分数量足够，此时默认分配数=商品需求数，
            if ($diff <= 0) {
                $sortData[$key]->default_num = $still_need_num;
                $sortData[$key]->yet_num = $still_need_num;
                $sortData[$key]->still_need_num = $still_need_num;
                //可分数量足够时,剩余的可分货数量等于采购总数减去需求数
                $updateNum = abs($diff);
            }
            //将$arrBatchGoods中商品的可分货数量更新为$updateNum以进行下一轮计算
            $arrBatchGoods = updateTwoArrayValue(
                $arrBatchGoods,
                'spec_sn',
                $spec_sn,
                'sort_num',
                $updateNum
            );

        }//end of foreach
        return ['sortData' => $sortData, 'batchGoods' => $arrBatchGoods];
    }

    /**
     * description 更新数据-修改分货数据中的默认值和已分配值，更新批次表中对应商品的可分货数量，
     * 记录该批次分给需求单各商品的数据
     * author zhangdong
     * date 2019.06.01
     */
    public function operateSortData($sumDemandSn, $realPurchaseSn, $sortData, $batchGoods)
    {
        //循环更新分货数据中的默认值和已分配值
        $sort_batch = [];
        foreach ($sortData as $value) {
            $still_need_num = isset($value->still_need_num) ? intval($value->still_need_num) : 0;
            $yet_num = isset($value->yet_num) ? intval($value->yet_num) : 0;
            if ($yet_num > 0 && $still_need_num > 0) {
                $this->updateSortData($value->id, $value->default_num, $value->yet_num);
                $sort_batch[] = [
                    'sum_demand_sn' => $sumDemandSn,
                    'demand_sn' => $value->demand_sn,
                    'real_purchase_sn' => $realPurchaseSn,
                    'spec_sn' => $value->spec_sn,
                    'num' => $value->yet_num,
                    'spec_price' => $value->spec_price,
                    'lvip_price' => $value->lvip_price,
                    'pay_price' => $value->pay_price,
                    'channel_discount' => $value->channel_discount,
                    'real_discount' => $value->real_discount,
                ];
            }
        }
        //记录该批次分给需求单各商品的数据
        $sbModel = new SortBatchModel();
        $sbModel->insertData($sort_batch);
        //更新批次表中对应商品的可分货数量
        $rpd_update = [];
        foreach ($batchGoods as $item) {
            $rpd_update[] = [
                'id' => $item['id'],
                'sort_num' => $item['sort_num'],
            ];
        }
        $table = 'jms_real_purchase_detail_audit';
        $rpdSql = makeUpdateSql($table, $rpd_update);
        $strSql = $rpdSql['updateSql'];
        $bindData = $rpdSql['bindings'];
        $updateRes = $this->executeSql($strSql, $bindData);
        return $updateRes;

    }

    /**
     * description:批量更新语句执行
     * editor:zhangdong
     * date : 2019.06.01
     * @return object
     */
    public function executeSql($strSql, $bindData)
    {
        $executeRes = DB::update($strSql, $bindData);
        return $executeRes;
    }

    /**
     * description:分货数据更新
     * editor:zhangdong
     * date : 2019.06.01
     */
    public function updateSortData($id, $default_num, $yet_num)
    {
        $where = [
            ['id', $id],
        ];
        $update = [
            'default_num' => DB::raw('default_num + ' . $default_num),
            'yet_num' => DB::raw('yet_num + ' . $yet_num),
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:获取分货数据中某个需求单下商品信息
     * editor:zhangdong
     * date : 2019.06.03
     */
    public function getGoodsSortData($sumDemandSn, $demandSn, $specSn)
    {
        $where = [
            ['sum_demand_sn', $sumDemandSn],
            ['demand_sn', $demandSn],
            ['spec_sn', $specSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description 修改已分货数量
     * editor:zhangdong
     * date : 2019.06.03
     */
    public function modifyYetNum($sumDemandSn, $demandSn, $specSn, $handleNum)
    {
        $where = [
            ['sum_demand_sn', $sumDemandSn],
            ['demand_sn', $demandSn],
            ['spec_sn', $specSn],
        ];
        $update = [
            'yet_num' => DB::raw('yet_num + ' . $handleNum),
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description 修改已分货数量
     * editor:zhangdong
     * date : 2019.06.03
     */
    public function modifySortData($sumDemandSn, $demandSn, $realPurchaseSn, $sortBatch)
    {
        //调整值校验通过后开始更新分货数据表（sort_data）的已分配数
        $specSn = trim($sortBatch['spec_sn']);
        $handleNum = intval($sortBatch['handleNum']);
        //yet_num = yet_num + handle_num
        $this->modifyYetNum($sumDemandSn, $demandSn, $specSn, $handleNum);
        $sbModel = new SortBatchModel();
        //统计批次分货记录是否存在，有则更新，无则新增
        $countSortBatch = $sbModel->countSortBatch($sumDemandSn, $demandSn, $realPurchaseSn, $specSn);
        //更新批次分货表（sort_batch）的分配数 num = num + handle_num
        if ($countSortBatch == 0) {
            //新增批次分货记录
            $sort_batch[] = [
                'sum_demand_sn' => $sumDemandSn,
                'demand_sn' => $demandSn,
                'real_purchase_sn' => $realPurchaseSn,
                'spec_sn' => $specSn,
                'num' => $handleNum,
                'spec_price' => $sortBatch['spec_price'],
                'lvip_price' => $sortBatch['lvip_price'],
                'pay_price' => $sortBatch['pay_price'],
                'channel_discount' => $sortBatch['channel_discount'],
                'real_discount' => $sortBatch['real_discount'],
            ];
            $sbModel->insertData($sort_batch);
        }

        if ($countSortBatch > 0) {
            //更新批次分货记录
            $sbModel->modifyNum($sumDemandSn, $demandSn, $realPurchaseSn, $specSn, $handleNum);
        }
        //更新批次表中的可分货数量 sort_num = sort_num - handle_num;
        $rpdaModel = new RealPurchaseDeatilAuditModel();
        $updateRes = $rpdaModel->modifySortNum($realPurchaseSn, $specSn, $handleNum);
        return $updateRes;

    }

    /**
     * description 组装批次相关商品可分货数量和还需分配数
     * author:zhangdong
     * date : 2019.06.04
     */
    public function packageSortData($sortData, $batchGoodsInfo)
    {
        $arrData = objectToArray($batchGoodsInfo);
        foreach ($sortData as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
            $sortNum = isset($searchRes[0]['sort_num']) ? intval($searchRes[0]['sort_num']) : 0;
            $sortData[$key]->sort_num = $sortNum;
            $stillNeedNum = intval($value->goods_num) - intval($value->yet_num);
            $sortData[$key]->still_need_num = $stillNeedNum;
        }
        return $sortData;

    }

    /**
     * description 获取汇总单对应需求单分货信息
     * author:zongxing
     * date : 2019.06.27
     */
    public function getDemandSortInfo($sd_sn_arr)
    {
        $field = [
            'd.expire_time', 'dg.spec_sn',
            DB::raw('sum(jms_dg.goods_num) as goods_num'),
            DB::raw('sum(jms_sod.yet_num) as yet_num'),
            DB::raw('(sum(jms_dg.goods_num) - sum(jms_sod.yet_num)) as diff_num')
        ];
        $demand_sort_info = DB::table($this->table)
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'sod.demand_sn')
            ->leftJoin('demand_goods as dg', 'dg.demand_sn', '=', 'd.demand_sn')
            ->whereIn('sod.sum_demand_sn', $sd_sn_arr)
            ->get($field);
        $demand_sort_info = objectToArrayZ($demand_sort_info);
        $demand_sort_list = [];
        foreach ($demand_sort_info as $k => $v) {
            $demand_sort_list[$v['spec_sn']][$v['expire_time']][] = $v;
        }
        return $demand_sort_info;
    }

    /**
     * description 获取指定需求单对应商品的信息
     * author zongxing
     * date 2019.07.09
     */
    public function getDgTotalInfo($demand_sn_arr, $sum_demand_sn)
    {
        $field = ['sd.demand_sn', 'sd.spec_sn', 'sd.goods_num'];
        $dg_total_info = DB::table('sort_data as sd')
            ->where('sum_demand_sn', $sum_demand_sn)
            ->whereIn('sd.demand_sn', $demand_sn_arr)
            ->get($field)->groupBy('demand_sn');
        $dg_total_info = objectToArrayZ($dg_total_info);
        return $dg_total_info;
    }

    /**
     * description 获取指定子单单号对应的分货信息
     * author zongxing
     * date 2019.09.04
     */
    public function getDgSortInfo($sub_order_sn)
    {
        $sd_info = DB::table('sort_data as sd')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'sd.demand_sn')
            ->whereIn('d.sub_order_sn', $sub_order_sn)
            ->pluck('sd.yet_num', 'sd.spec_sn');
        $sd_info = objectToArrayZ($sd_info);
        return $sd_info;
    }


}//end of class
