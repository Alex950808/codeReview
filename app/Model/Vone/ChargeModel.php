<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;

class ChargeModel extends Model
{
    public $table = 'charge as c';
    private $field = [
        'c.charge_rate','c.charge_name','c.department_id','c.create_time',
    ];

    /**
     * @description:从redis中获取费用信息
     * @editor:张冬
     * @date : 2019.02.28
     * @return array
     */
    public function getChargeInfoInRedis()
    {
        //从redis中获取部门费用信息，如果没有则对其设置
        $chargeInfo = Redis::get('chargeInfo');
        if (empty($chargeInfo)) {
            $chargeInfo = DB::table($this->table) -> select($this->field)-> get()
                -> map(function ($value){
                    return (array) $value;
                }) -> toArray();
            Redis::set('chargeInfo', json_encode($chargeInfo, JSON_UNESCAPED_UNICODE));
            $chargeInfo = Redis::get('chargeInfo');
        }
        $chargeInfo = objectToArray(json_decode($chargeInfo));
        return $chargeInfo;

    }






}//end of class
