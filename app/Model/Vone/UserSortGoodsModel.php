<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserSortGoodsModel extends Model
{
    protected $table = 'user_sort_goods as usg';
    protected $field = [
        'usg.id','usg.sort_sn','usg.sale_user_id','usg.goods_name','usg.spec_sn',
        'usg.need_num','usg.total_num','usg.ratio_num','usg.handle_num',
    ];
    /**
     * description:获取YD订单下需求单中的商品
     * editor:zonxing
     * date : 2018.12.14
     * param $sub_order_sn YD订单号
     * @return array
     */
    public function getUserSortGoods_stop($sub_order_sn)
    {
        $where = [
            ["sub_order_sn", $sub_order_sn]
        ];
        $demand_goods_list = DB::table("user_sort_goods as usg")
            ->leftJoin("demand as d","d.demand_sn","=","usg.demand_sn")
            ->where($where)->get();
        $demand_goods_list = ObjectToArrayZ($demand_goods_list);
        return $demand_goods_list;
    }


    /**
     * description:查询用户分货信息
     * author:zhangdong
     * date : 2019.02.22
     */
    public function countUserSortGoods($sort_sn, $depart_id)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
        ];
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * description:查询用户分货信息
     * author:zhangdong
     * date : 2019.02.22
     */
    public function insertData(array $arrData = [])
    {
        if (count($arrData) == 0) {
            return false;
        }

        $insertRes = DB::transaction(function () use ($arrData) {
            $insertRes = DB::table('user_sort_goods')->insert($arrData);
            return $insertRes;
        });
        return $insertRes;

    }

    /**
     * description:获取用户分货数据
     * author：zhangdong
     * date : 2019.02.22
     */
    public function getUserSortGoods($depart_id, $sort_sn)
    {
        $where = [
            ['usg.sort_sn', $sort_sn],
            ['dsg.depart_id', $depart_id],
        ];
        $field = [
            'usg.sort_sn','usg.depart_id','usg.sale_user_id','usg.goods_name','usg.spec_sn',
            'usg.total_num','usg.need_num','dsg.handle_num AS sort_num','usg.handle_num',
            'su.user_name','usg.demand_sn',
        ];
        $dsg_on = [
            ['usg.sort_sn', 'dsg.sort_sn'],
            ['usg.spec_sn', 'dsg.spec_sn'],
            ['usg.depart_id', 'dsg.depart_id'],
        ];
        $su_on = [
            ['su.id', 'usg.sale_user_id'],
        ];
        $qureyRes = DB::table($this->table)->select($field)
            ->leftJoin('depart_sort_goods AS dsg', $dsg_on)
            ->leftJoin('sale_user AS su', $su_on)
            ->where($where)->orderBy('usg.spec_sn', 'ASC')->get();
        return $qureyRes;
    }

    /**
     * description:检查要分货的数据是否存在
     * author：zhangdong
     * date : 2019.02.25
     */
    public function checkSortInfo($sort_sn, $depart_id, $sale_user_id, $spec_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
            ['sale_user_id', $sale_user_id],
            ['spec_sn', $spec_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;

    }

    /**
     * description:检查要分货的用户有几个
     * author：zhangdong
     * date : 2019.02.25
     */
    public function checkSaleUserNum($sort_sn, $depart_id, $spec_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
            ['spec_sn', $spec_sn],
        ];
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;

    }

    /**
     * description:查询除了当前部门外其他部门已分得的数量
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function getOtherUserNum($sort_sn, $depart_id, $sale_user_id, $spec_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
            ['spec_sn', $spec_sn],
            ['sale_user_id', '!=', $sale_user_id],
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
     * date : 2019.02.25
     */
    public function modifyHandleNum($modifyId, $handle_num) {
        $where = [
            ['id', $modifyId],
        ];
        $update = [
            'handle_num'=> $handle_num,
        ];
        $updateRes = DB::table('user_sort_goods')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:获取每个商品的分配总数
     * editor:zhangdong
     * date : 2019.02.25
     */
    public function getSpecNum($sort_sn, $depart_id)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
        ];
        $field = ['spec_sn',DB::raw('SUM(handle_num) AS userNum')];
        $queryRes = DB::table($this->table)->select($field)
            ->where($where)->groupBy('spec_sn')->get();
        return $queryRes;
    }

    /**
     * description:获取用户分货信息
     * author：zhangdong
     * date : 2019.03.11
     */
    public function getUserSortInfo($sort_sn, $depart_id)
    {
        $where = [
            ['sort_sn', $sort_sn],
            ['depart_id', $depart_id],
        ];
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description:统计用户分货数据中对应分货单已分货的用户数量
     * author:zhangdong
     * date : 2019.04.09
     */
    public function countUserSortDepartNum($sort_sn)
    {
        $where = [
            ['sort_sn', $sort_sn],
        ];
        $countRes = DB::table($this->table)->where($where)->count(DB::raw('DISTINCT depart_id'));
        return $countRes;
    }













}//end of class
