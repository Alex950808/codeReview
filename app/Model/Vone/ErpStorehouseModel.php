<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;

class ErpStorehouseModel extends Model
{
    public $table = 'erp_storehouse as es';
    private $field = [
        'es.store_id','es.store_name','es.store_location','es.store_factor',
    ];

    /**
     * description:获取ERP仓库信息
     * editor:zhangdong
     * date : 2018.12.27
     * @param $spec_sn(规格码)
     * @return
     */
    public function getErpStoreInfo($store_id = 1002)
    {
        $store_id = intval($store_id);
        $where = [
            ['store_id', $store_id],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * @description:从redis中获取erp仓库信息
     * @editor:张冬
     * @date : 2019.02.28
     * @param $store_id
     * @return object
     */
    public function getErpStoreInfoInRedis()
    {
        //从redis中获取部门费用信息，如果没有则对其设置
        $erpInfo = Redis::get('erpInfo');
        if (empty($erpInfo)) {
            $erpInfo = DB::table($this->table)->select($this->field)->get()
                ->map(function ($value) {
                    return (array)$value;
                })->toArray();
            Redis::set('erpInfo', json_encode($erpInfo, JSON_UNESCAPED_UNICODE));
            $erpInfo = Redis::get('erpInfo');
        }
        $erpInfo = objectToArray(json_decode($erpInfo));
        return $erpInfo;

    }






}//end of class
