<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ExampleModel extends Model
{
    public $table = 'erp_storehouse as es';
    private $field = [
        'es.store_name','es.store_location','es.store_factor',
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






}//end of class
