<?php

namespace App\Model\Vone;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class PurchaseSumDateModel extends Model
{
    protected $table = 'purchase_sum_date as psd';
    protected $field = ['id', 'entrust_time', 'start_time', 'end_time'];

    /**
     * description:自采毛利率列表
     * editor:zongxing
     * date : 2019.03.13
     * return Array
     */
    public function getPurchaseSumDateList()
    {
        $purchase_sum_date_info = DB::table($this->table)->get($this->field);
        $purchase_sum_date_info = objectToArrayZ($purchase_sum_date_info);

        $user_info = JWTAuth::toUser()->attributes;
        $department_id = $user_info['department_id'];
        $status = ($department_id == 1) ? 1 : 0;
        $purchase_sum_date_list = [
            'info'=>$purchase_sum_date_info,
            'status'=>$status
        ];
        return $purchase_sum_date_list;
    }
}
