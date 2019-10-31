<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DataModifyModel extends Model
{
    /**
     * description:获取数据修正表数据
     * editor:zongxing
     * date : 2019.02.15
     * return Array
     */
    public function getPurchseChannelGoodsInfo($real_purchase_sn, $purchase_sn)
    {
        $data_modify_goods_info = DB::table("data_modify")
            ->where("purchase_sn", $purchase_sn)
            ->where("real_purchase_sn", $real_purchase_sn)
            ->pluck("modify_num", "spec_sn");
        $data_modify_goods_info = objectToArrayZ($data_modify_goods_info);
        return $data_modify_goods_info;
    }
}
