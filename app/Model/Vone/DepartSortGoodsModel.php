<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class DepartSortGoodsModel extends Model
{
    protected $table = "depart_sort_goods as dsg";

    //可操作字段
    protected $field = [
        'dsg.id', 'dsg.sort_sn', 'dsg.depart_id', 'dsg.goods_name', 'dsg.spec_sn', 'dsg.depart_need_num',
        'dsg.total_num', 'dsg.ratio', 'dsg.ratio_num', 'dsg.handle_num','dsg.demand_sn'
    ];


    /**
     * description:查询商品分货数据
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function getSortGoodsData($sort_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
        ];
        $field = ['d.de_name'];
        $this->field = array_merge($this->field, $field);
        $queryRes = DB::table($this->table)->select($this->field)
            ->leftJoin('department as d', 'd.department_id', 'dsg.depart_id')
            ->where($where)->orderBy('dsg.spec_sn', 'ASC')->get();
        return $queryRes;

    }

    /**
     * description:检查当前要分货的商品存在的部门信息
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function checkSortGoods($sort_sn, $spec_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['spec_sn', $spec_sn],
        ];
        $canSortNum = DB::table($this->table)->where($where)->count();
        return $canSortNum;
    }

    /**
     * description:查询除了当前部门外其他部门已分得的数量
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function getOtherDepartNum($sort_sn, $spec_sn, $depart_id)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['spec_sn', $spec_sn],
            ['depart_id', '!=', $depart_id],
        ];
        $field = [DB::raw('IFNULL(SUM(handle_num),0) as otherNum')];
        $queryRes = DB::table($this->table)->select($field)->where($where)->first();
        $canSortNum = 0;
        if (!is_null($queryRes)) {
            $canSortNum = intval($queryRes->otherNum);
        }
        return $canSortNum;
    }

    /**
     * description:修改手动调整值
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function modifyHandleNum($sort_sn, $depart_id, $spec_sn, $handle_num)
    {
        $sort = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
            ['spec_sn', $spec_sn],
        ];
        $sortUpdate = [
            'handle_num' => $handle_num,
        ];
        $updateRes = DB::transaction(
            function () use ($sort, $sortUpdate) {
                //修改分货单中的手动调整值
                $updateRes = DB::table('depart_sort_goods')->where($sort)->update($sortUpdate);
                return $updateRes;
            }
        );
        return $updateRes;

    }

    /**
     * description:获取用户分货数据
     * editor:zhangdong
     * date : 2019.02.22
     */
    public function getDepartSortGoods($sort_sn, $depart_id)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
        ];
        $field = ['spec_sn', 'handle_num', 'demand_sn'];
        $queryRes = DB::table($this->table)->select($field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:获取分货单下某个部门某个商品的可分配数量
     * editor:zhangdong
     * date : 2019.02.22
     */
    public function getDepartGoodsCanSortNum($sort_sn, $depart_id, $spec_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
            ['spec_sn', $spec_sn],
        ];
        $field = ['spec_sn', 'handle_num'];
        $queryRes = DB::table($this->table)->select($field)->where($where)->first();
        $handle_num = 0;
        if (!is_null($queryRes)) {
            $handle_num = intval($queryRes->handle_num);
        }
        return $handle_num;
    }


    /**
     * description:获取需求单分货信息
     * editor:zongxing
     * date : 2019.02.27
     */
    public static function demandSortInfo($demand_sn)
    {
        $field = [
            'sb.demand_sn',
            DB::raw('SUM(jms_sb.num) as handle_goods_num'),
            DB::raw('SUM(jms_sb.num * jms_sb.pay_price) as sort_purchase_total_price'),//采购
            DB::raw('SUM(jms_sb.num * jms_sb.spec_price * 
                (1 - (jms_sb.real_discount + 
                    ((
                    CASE
                    WHEN jms_gs.spec_weight != 0 THEN
                        jms_gs.spec_weight
                    WHEN jms_gs.estimate_weight != 0 THEN
                        jms_gs.estimate_weight
                    ELSE
                        0.00
                    END) /(jms_sb.spec_price * 0.0022 * 100))
                )/jms_dg.sale_discount)
            )
            as psrt_price'),//实采毛利金额
            DB::raw('SUM(jms_sb.num * jms_sb.spec_price * jms_dg.sale_discount) as psst_price'),//实采报价金额
        ];
        $demand_sort_goods_obj = DB::table('sort_batch as sb')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sb.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->join('demand_goods as dg', function ($join) {
                $join->on('dg.demand_sn', '=', 'sb.demand_sn')
                    ->on('dg.spec_sn', '=', 'sb.spec_sn');
            });
        $demand_sort_goods_info = $demand_sort_goods_obj->whereIn('sb.demand_sn', $demand_sn)
            ->groupBy('sb.demand_sn')
            ->get($field);
        $demand_sort_goods_info = objectToArrayZ($demand_sort_goods_info);
        return $demand_sort_goods_info;
    }

    /**
     * description:获取子单商品分货信息
     * editor:zongxing
     * date : 2019.02.28
     */
    public static function subOrderSortGoodsInfo($sub_order_sn)
    {
        $field = [
            'sb.real_purchase_sn', 'g.goods_name', 'sb.spec_sn', 'sb.spec_price',
            'rp.delivery_time', 'rp.path_way', 'sb.real_discount', 'pc.channels_name', 'sb.num as handle_num',
            DB::raw('SUM(jms_sb.num * jms_sb.spec_price * jms_dg.sale_discount *
                (1 - (jms_sb.real_discount + 
                    (jms_gs.spec_weight/(jms_sb.spec_price * 0.0022 * 100))
                )/jms_dg.sale_discount)
            )
            as psrt_price'),//实采毛利金额
            DB::raw('SUM(jms_sb.num * jms_sb.spec_price * jms_sb.real_discount) as psst_price'),//实采报价金额
        ];

        $demand_sort_goods_obj = DB::table('sort_batch as sb')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'sb.demand_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sb.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'sb.real_purchase_sn')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rp.channels_id')
            ->leftJoin('demand_goods as dg', function ($join) {
                $join->on('dg.demand_sn', '=', 'sb.demand_sn')
                    ->on('dg.spec_sn', '=', 'sb.spec_sn');
            })
            ->where('d.sub_order_sn', $sub_order_sn);
        $demand_sort_goods_info = $demand_sort_goods_obj->orderBy('rp.delivery_time', 'DESC')
            ->groupBy('sb.spec_sn')->get($field)->groupBy('spec_sn');
        $demand_sort_goods_info = objectToArrayZ($demand_sort_goods_info);
        return $demand_sort_goods_info;
    }

    /**
     * description:检查部门分货数据是否已完成用户分货
     * author:zhangdong
     * date:2019.04.09
     * return:boolean
     */
    public function checkIsFinishSort($sort_sn)
    {
        //统计部门分货数据中对应分货单有几个部门
        $departUserNum = $this->countDepartSortDepartNum($sort_sn);
        //统计用户分货数据中对应分货单已分货的部门数量
        $usgModel = new UserSortGoodsModel();
        $userSortUserNum = $usgModel->countUserSortDepartNum($sort_sn);
        if ($departUserNum == $userSortUserNum) {
            return true;
        }
        return false;
    }

    /**
     * description:统计部门分货数据中对应分货单有几个用户
     * author:zhangdong
     * date : 2019.04.09
     */
    public function countDepartSortDepartNum($sort_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
        ];
        $countRes = DB::table($this->table)->where($where)->count(DB::raw('DISTINCT depart_id'));
        return $countRes;
    }


}//end of class
