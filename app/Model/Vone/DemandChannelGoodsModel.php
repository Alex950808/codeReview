<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DemandChannelGoodsModel extends Model
{
    /**
     * description:获取需求单临时分配方案
     * editor:zongxing
     * date : 2019.02.19
     * return Array
     */
    public function getDemandChannelGoodsInfo($demand_sn, $purchase_sn, $spec_sn = [])
    {
        $where = [];
        if($spec_sn){
            $where[] = ['pdd.spec_sn','=',$spec_sn];
        }
        $purchase_demand_info = DB::table("demand_channel_goods as dcg")
            ->select(
                "pdd.purchase_sn", "pdd.demand_sn", "pdd.goods_name", "dcg.may_num", "dcg.spec_sn",
                "method_name", "channels_name", "b.brand_id",'dcg.id'
            )
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "dcg.spec_sn")
            ->leftJoin("goods as g", "g.goods_sn", "=", "gs.goods_sn")
            ->leftJoin("brand as b", "b.brand_id", "=", "g.brand_id")
            ->leftJoin("purchase_demand_detail as pdd", function ($leftJoin) {
                $leftJoin->on("pdd.purchase_sn", '=', "dcg.purchase_sn")
                    ->on("pdd.spec_sn", '=', "dcg.spec_sn")
                    ->on("pdd.demand_sn", '=', "dcg.demand_sn");
            })
            ->leftJoin("purchase_method as pm", "pm.method_sn", "=", "dcg.method_sn")
            ->leftJoin("purchase_channels as pc", "pc.channels_sn", "=", "dcg.channels_sn")
            ->where("pdd.demand_sn", $demand_sn)
            ->where("pdd.purchase_sn", $purchase_sn)
            ->where($where)
            ->get();
        $purchase_demand_info = objectToArrayZ($purchase_demand_info);
        return $purchase_demand_info;
    }


}
