<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FundCatModel extends Model
{
    /**
     * description:获取资金渠道类别列表
     * editor:zongxing
     * date:2018.12.17
     */
    public function getFundCatList()
    {
        $fund_cat_list = DB::table("fund_cat")->get(['id', 'fund_cat_name']);
        $fund_cat_list = objectToArrayZ($fund_cat_list);
        return $fund_cat_list;
    }
}
