<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleUserGoodsModel extends Model
{
    /**
     * @description:根据销售用户id，部门id和条码或规格码查询销售用户商品信息
     * @author:zhangdong
     * @date : 2018.10.18
     * @param $department_id (部门id)
     * @param $sale_user_id (销售用户id)
     * @param $queryData (查询值)
     * @param $type (查询方式 1，商家编码查询 2，规格码查询)
     * @return object
     */
    public function getSugInfo($department_id,$sale_user_id,$queryData,$type)
    {
        $type = intval($type);
        $queryField = $type == 1 ? 'erp_merchant_no' : 'spec_sn';
        $where = [
            ['depart_id',$department_id],
            ['sale_user_id',$sale_user_id],
            [$queryField,$queryData],
        ];
        $field = ['depart_id','sale_user_id','erp_merchant_no','spec_sn','sale_discount'];
        $saleUgoodsInfo = DB::table('sale_user_goods') -> select($field) -> where($where) -> first();
        return $saleUgoodsInfo;
    }


}
