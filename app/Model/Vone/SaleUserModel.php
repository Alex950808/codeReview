<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;

class SaleUserModel extends Model
{
    protected $table = 'sale_user as su';

    protected $field = [
        'su.id','su.depart_id','su.user_name','su.min_profit','su.sale_user_cat',
        'su.money_cat','su.sale_short','su.payment_cycle','su.group_sn','su.is_start',
    ];

    /**
     * description:根据用户名称获取用户信息生成需求单号
     * editor:zhangdong
     * date : 2018.06.25
     * params: $queryType :查询方式 1，用户名 2，用户id
     * return Object
     */
    public function getSaleUserMsg($queryData, $queryType)
    {
        $queryType = intval($queryType);
        $field = ['id', 'user_name'];
        if ($queryType == 1) { //用户名查询
            $where = [
                ['user_name', 'like', '%' . $queryData . '%']
            ];
        } elseif ($queryType == 2) {//用户id查询
            $where = [
                ['id', intval($queryData)]
            ];
        } else {
            return false;
        }
        $queryRes = DB::table('sale_user')->select($field)->where($where)->first();
        return $queryRes;

    }

    /**
     * description:获取销售客户信息
     * editor:zhangdong
     * date : 2018.10.18
     * params: $department_id :部门id
     * return Object
     */
    public function getSaleUser($department_id = null, $sale_user_id = null, $sale_user_name = null, $sale_short = null,
                                $is_check = false)
    {
        $department_id = intval($department_id);
        $field = ['id', 'user_name', 'depart_id', 'min_profit', "sale_user_cat", "money_cat", "payment_cycle",
            "sale_short", "group_sn"];//modify by zongxing 12.05

        $saleUserInfo = DB::table('sale_user')->select($field);
        if ($department_id) $saleUserInfo->where('depart_id', $department_id);
        if ($sale_user_id) {
            if ($is_check) {
                $saleUserInfo->where('id', '!=', $sale_user_id);
            } else {
                $saleUserInfo->where('id', $sale_user_id);
            }
        }

        if ($sale_user_name || $sale_short) {
            $saleUserInfo->where(function ($query) use ($sale_user_name, $sale_short) {
                $query->where('user_name', $sale_user_name)
                    ->orWhere('sale_short', $sale_short);
            });
        }
        $saleUserInfo = $saleUserInfo->get();
        return $saleUserInfo;
    }

    /**
     * description:根据部门id获取销售用户信息
     * editor:zhangdong
     * date : 2018.12.06
     * params: $department_id :部门id
     * return Object
     */
    public function getSaleUserByDepartId($department_id)
    {
        $department_id = intval($department_id);
        $field = ['id', 'user_name'];
        $where = [
            ['depart_id', $department_id],
        ];
        $saleUserInfo = DB::table('sale_user')->select($field)->where($where)->get();
        return $saleUserInfo;

    }

    /**
     * description:获取销售客户回款规则信息
     * editor:zongxing
     * date: 2018.12.08
     * params: 1.$sale_user_id:销售客户id;2.$delivery_type:交货类别id;3.$ship_type:运输方式id;
     * return Object
     */
    public function getSaleRefundRules($sale_user_id, $delivery_type, $ship_type, $refund_rule_id = null, $is_check = false)
    {
        $saleRefundRulesInfo = DB::table('refund_rules');
        if ($refund_rule_id) {
            if ($is_check) {
                $saleRefundRulesInfo->where('id', '!=', $refund_rule_id);
            } else {
                $saleRefundRulesInfo->where('id', $refund_rule_id);
            }
        }
        if ($sale_user_id) $saleRefundRulesInfo->where('sale_user_id', $sale_user_id);
        if ($delivery_type) $saleRefundRulesInfo->where('delivery_type', $delivery_type);
        if ($ship_type) $saleRefundRulesInfo->where('ship_type', $ship_type);
        $saleRefundRulesInfo = $saleRefundRulesInfo->first(["id", "sale_user_id", "delivery_type", "ship_type",
            "ship_day", "tally_day", "pay_day"]);
        $saleRefundRulesInfo = objectToArrayZ($saleRefundRulesInfo);
        return $saleRefundRulesInfo;
    }

    /**
     * description:获取销售用户列表
     * editor:zongxing
     * date:2018.12.17
     */
    public function getSaleUserList()
    {
        $sale_user_list = DB::table("sale_user")->get(['id', 'user_name']);
        $sale_user_list = objectToArrayZ($sale_user_list);
        return $sale_user_list;
    }

    /**
     * description:获取销售用户信息
     * editor:zongxing
     * date:2018.12.17
     */
    public function getSaleUserInfo($sale_user_id)
    {
        $where = [
            ['id', $sale_user_id]
        ];
        $sale_user_list = DB::table("sale_user")->where($where)->first(['id', 'user_name']);
        $sale_user_list = objectToArrayZ($sale_user_list);
        return $sale_user_list;
    }

    /**
     * @description:从redis中获取销售用户信息
     * @editor:张冬
     * @date : 2019.05.31
     * @return object
     */
    public function getSaleUserInfoInRedis()
    {
        //从redis中获取销售用户信息，如果没有则对其设置
        $saleUserInfo = Redis::get('saleUserInfo');
        if (empty($saleUserInfo)) {
            $where = [
                ['is_start',1],
            ];
            $saleUserInfo = DB::table($this->table)->select($this->field)->where($where)->get()
                -> map(function ($value){
                    return (array) $value;
                }) -> toArray();
            Redis::set('saleUserInfo', json_encode($saleUserInfo, JSON_UNESCAPED_UNICODE));
            $saleUserInfo = Redis::get('saleUserInfo');
        }
        $saleUserInfo = objectToArray(json_decode($saleUserInfo));
        return $saleUserInfo;

    }


}//end of class
