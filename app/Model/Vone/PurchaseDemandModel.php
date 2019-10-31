<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseDemandModel extends Model
{

    protected $table = 'purchase_demand AS pd';
    /**
     * description:获取采购期需求表相关数据
     * editor:zongxing
     * date : 2019.01.29
     * return Array
     */
    public function getPurchaseDemandList($demand_sn_arr)
    {
        $purchase_demand_list = DB::table('purchase_demand')->whereIn('demand_sn', $demand_sn_arr)->get();
        $purchase_demand_list = objectToArrayZ($purchase_demand_list);
        return $purchase_demand_list;
    }

    /**
     * description:通过depart_sort中的采购期单号和depart_sort_goods
     * 中的部门id查到采购期单号下该部门的所有销售用户
     * author:zhangdong
     * date : 2019.02.22
     */
    public function getDepartUserInfo($purchase_sn, $depart_id)
    {
        $field = [
            'pd.demand_sn','pdd.goods_name','pdd.spec_sn','pdd.goods_num','d.sale_user_id',
        ];
        $where = [
            ['pd.purchase_sn', $purchase_sn],
            ['pd.department', $depart_id],
        ];
        $pdd_on = [
            ['pd.purchase_sn', 'pdd.purchase_sn'],
            ['pd.demand_sn', 'pdd.demand_sn'],
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('purchase_demand_detail AS pdd',$pdd_on)
            ->leftJoin('demand AS d', 'd.demand_sn', 'pd.demand_sn')
            ->where($where)->get();
        return $queryRes;

    }

    /**
     * description:通过depart_sort中的采购期单号和depart_sort_goods
     * 中的部门id查到采购期单号下该部门的所有销售用户
     * author:zhangdong
     * date : 2019.02.22
     */
    public function getUserTotalNum($purchase_sn, $depart_id)
    {
        $field = [
            'pdd.spec_sn',DB::raw('SUM(jms_pdd.goods_num) as totalNum'),
        ];
        $where = [
            ['pd.purchase_sn', $purchase_sn],
            ['pd.department', $depart_id],
        ];
        $pdd_on = [
            ['pd.purchase_sn', 'pdd.purchase_sn'],
            ['pd.demand_sn', 'pdd.demand_sn'],
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('purchase_demand_detail AS pdd',$pdd_on)
            ->leftJoin('demand AS d', 'd.demand_sn', 'pd.demand_sn')
            ->where($where)->groupBy('pdd.spec_sn')->get();
        return $queryRes;

    }

    /**
     * description:根据需求单号获取采购期单号
     * editor:zongxing
     * date : 2019.03.01
     * return Array
     */
    public function getPurchaseSnList($demand_sn_arr)
    {
        $purchase_sn_info = DB::table('purchase_demand as pd')
            ->whereIn('pd.demand_sn', $demand_sn_arr)->distinct()
            ->pluck('purchase_sn');
        $purchase_sn_info = objectToArrayZ($purchase_sn_info);
        return $purchase_sn_info;
    }

    /**
     * description:根据采购期单号获取需求单号
     * editor:zongxing
     * date : 2019.03.01
     * return Array
     */
    public function getDemandSnList($purchase_sn_info)
    {
        $demand_sn_info = DB::table('purchase_demand as pd')
            ->whereIn('pd.purchase_sn', $purchase_sn_info)->distinct()
            ->pluck('demand_sn');
        $demand_sn_info = objectToArrayZ($demand_sn_info);
        return $demand_sn_info;
    }
















}//end of class
