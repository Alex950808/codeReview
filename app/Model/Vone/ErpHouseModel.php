<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ErpHouseModel extends Model
{
    public $table = 'erp_house as eh';
    private $field = [
        'eh.store_id','eh.store_name','eh.store_location',
    ];

    /**
     * description:获取ERP仓库列表信息
     * editor:zongxing
     * date : 2019.01.11
     */
    public function getErpHouseList()
    {
        $erpHouseList = DB::table($this->table)->select($this->field)->get();
        $erpHouseList = objectToArrayZ($erpHouseList);
        return $erpHouseList;
    }





}//end of class
