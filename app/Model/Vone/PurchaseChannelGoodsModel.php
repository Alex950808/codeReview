<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseChannelGoodsModel extends Model
{
    /**
     * description:获取采购期采购需求资金列表
     * editor:zongxing
     * date : 2018.12.26
     * return Array
     */
//    public function getPurchseFund()
//    {
//        $fields = ['pcg.purchase_sn', 'b.brand_id', 'pm.id as method_id', 'pc.id as channel_id', 'pcg.spec_sn',
//            'd.brand_discount', 'method_name', 'channels_name', 'may_num', 'real_num', 'spec_price', 'dc.goods_num',
//            'pc.original_or_discount', DB::raw('(may_num - real_num) as diff_num')];
//        $where = [
//            ['pd.status','<=',2]
//        ];
//        $purchase_channel_goods = DB::table('purchase_channel_goods as pcg')
//            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'pcg.spec_sn')
//            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
//            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
//            ->leftJoin('purchase_method as pm', 'pm.method_sn', '=', 'pcg.method_sn')
//            ->leftJoin('purchase_channels as pc', 'pc.channels_sn', '=', 'pcg.channels_sn')
//            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'pcg.purchase_sn')
//            ->leftJoin('demand_count as dc', 'dc.purchase_sn', '=', 'pcg.purchase_sn')
//            ->leftJoin('discount as d', function ($leftJoin) {
//                $leftJoin->on('d.brand_id', '=', 'b.brand_id');
//                $leftJoin->on('d.method_id', '=', 'pm.id');
//                $leftJoin->on('d.channels_id', '=', 'pc.id');
//            })
//            ->where($where)
//            ->get($fields)
//            ->groupBy('purchase_sn');
//        $purchase_channel_goods = ObjectToArrayZ($purchase_channel_goods);
//        return $purchase_channel_goods;
//    }
    public function getPurchseFund()
    {
        $fields = ['pcg.purchase_sn', 'b.brand_id', 'pm.id as method_id', 'pc.id as channels_id', 'pcg.spec_sn',
            'd.brand_discount', 'method_name', 'channels_name', 'may_num', 'real_num', 'spec_price',
            'pc.original_or_discount', DB::raw('(may_num - real_num) as diff_num')];
        $where = [
            ['pd.status', '<=', 2]
        ];
        $purchase_channel_goods = DB::table('purchase_channel_goods as pcg')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'pcg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->leftJoin('purchase_method as pm', 'pm.method_sn', '=', 'pcg.method_sn')
            ->leftJoin('purchase_channels as pc', 'pc.channels_sn', '=', 'pcg.channels_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'pcg.purchase_sn')
            ->leftJoin('discount as d', function ($leftJoin) {
                $leftJoin->on('d.brand_id', '=', 'b.brand_id');
                $leftJoin->on('d.method_id', '=', 'pm.id');
                $leftJoin->on('d.channels_id', '=', 'pc.id');
            })
            ->where($where)
            ->get($fields)
            ->groupBy('purchase_sn');
        $purchase_channel_goods = ObjectToArrayZ($purchase_channel_goods);
        return $purchase_channel_goods;
    }

    /**
     * description:获取批次对应的采购期渠道商品统计列表
     * editor:zongxing
     * date : 2019.02.15
     * return Array
     */
    public function getPurchseChannelGoodsInfo($purchase_sn, $real_purchase_sn = [])
    {
        $where = [];
        if ($real_purchase_sn) {
            //获取采购方式及渠道信息
            $method_channel_info = DB::table("real_purchase as rp")
                ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
                ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
                ->where("real_purchase_sn", $real_purchase_sn)
                ->first(["method_sn", "channels_sn"]);
            $method_channel_info = objectToArrayZ($method_channel_info);

            $method_sn = $method_channel_info["method_sn"];
            $channels_sn = $method_channel_info["channels_sn"];
            $where = [
                ['pcg.method_sn', '=', $method_sn],
                ['pcg.channels_sn', '=', $channels_sn],
            ];
        }

        $purchase_channel_goods_info = DB::table("purchase_channel_goods as pcg")
            ->leftJoin("purchase_method as pm", "pm.method_sn", "=", "pcg.method_sn")
            ->leftJoin("purchase_channels as pc", "pc.channels_sn", "=", "pcg.channels_sn")
            ->where("purchase_sn", $purchase_sn)
            ->where($where)
            ->get(['pcg.id', 'real_num', 'spec_sn', 'may_num', 'method_name', 'channels_name']);
        $purchase_channel_goods_info = objectToArrayZ($purchase_channel_goods_info);
        return $purchase_channel_goods_info;
    }

}
