<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleUserAccountModel extends Model
{
    protected $field = [
        'sua.id','sua.user_name','sua.sale_user_id','sua.create_time','sua.brand_id'
    ];

    protected $table = 'sale_user_account as sua';

    /**
     * description:根据销售用户id获取其对应的销售账号
     * author:zhangdong
     * date:2018.12.20
     * return Object
     */
    public function listSaleAccounts($value, $type = 1)
    {
        $type = intval($type);
        $where = [];
        if ($type == 1) { //销售用户id查询
            $value = intval($value);
            $where = [
                ['sua.sale_user_id', $value]
            ];
        }
        if ($type == 2) { //用户名查询
            $value = trim($value);
            $where = [
                ['sua.user_name', 'like', '%' . $value . '%']
            ];
        }
        $queryRes = DB::table('sale_user_account as sua')->select($this->field)->where($where)->get();
        return $queryRes;

    }

    /**
     * description:通过品牌id获取用户信息
     * author:zhangdong
     * date:2019.01.16
     * return Object
     */
    public function getMsgByBrandId($brandId, $saleUid)
    {
        $brandId = intval($brandId);
        $where = [
            ['brand_id','like','%' . $brandId . '%'],
            ['sale_user_id',$saleUid]
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:获取销售客户账号信息
     * editor:zongxing
     * date : 2018.12.08
     * params: 1.$user_name:销售账号;2.$sale_user_id:销售客户id;3.$sale_account_id:销售客户账号id;
     * return Object
     */
    public function getSaleAccount($user_name = null, $sale_user_id = null, $sale_account_id = null)
    {
        $saleAccountInfo = DB::table('sale_user_account as sua');
        if ($user_name) $saleAccountInfo->where('sua.user_name', $user_name);
        if ($sale_user_id) $saleAccountInfo->where('sua.sale_user_id', $sale_user_id);
        if ($sale_account_id) $saleAccountInfo->where('sua.id', $sale_account_id);
        $field = $this->field;
        $saleAccountInfo = $saleAccountInfo->first($field);
        $saleAccountInfo = objectToArrayZ($saleAccountInfo);
        if(!empty($saleAccountInfo)){
            $saleAccountInfo['brand_id'] = json_decode($saleAccountInfo['brand_id'],true);
        }
        return $saleAccountInfo;
    }


}//end of class
