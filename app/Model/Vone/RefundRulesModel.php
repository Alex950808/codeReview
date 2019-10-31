<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefundRulesModel extends Model
{
    public $delivery_type = [
        '1' => '香港fob交货',
        '2' => '保税CIF交货',
        '3' => '香港DDP交货',
        '4' => '其他',
    ];
    public $ship_type = [
        '1' => '空运',
        '2' => '陆运',
        '3' => '海运',
    ];

    /**
     * description:销售客户回款规则列表
     * editor:zongxing
     * date:2018.12.14
     */
    public function refundRulesList()
    {
        $refund_rules_list = DB::table("refund_rules as rr")
            ->leftJoin("sale_user as su", "su.id", "=", "rr.sale_user_id")
            ->get(["rr.id", "user_name", "rr.ship_day", "rr.tally_day", "rr.pay_day", 'delivery_type', 'ship_type']);
        $refund_rules_list = objectToArrayZ($refund_rules_list);
        foreach ($refund_rules_list as $k => $v) {
            $delivery_key = $v["delivery_type"];
            $total_delivery_type = $this->delivery_type;
            $refund_rules_list[$k]["delivery_type"] = $total_delivery_type[$delivery_key];
            $ship_type = $v["ship_type"];
            $total_ship_type = $this->ship_type;
            $refund_rules_list[$k]["ship_type"] = $total_ship_type[$ship_type];
        }
        return $refund_rules_list;
    }

    /**
     * description:获取回款规则信息
     * editor:zongxing
     * date:2018.12.17
     */
    public function getRefundRuleInfo($sale_user_id, $delivery_type, $ship_type)
    {
        $where = [
            ['sale_user_id', $sale_user_id],
            ['delivery_type', $delivery_type],
            ['ship_type', $ship_type]
        ];
        $refund_rules_info = DB::table("refund_rules")->where($where)->first(['id']);
        $refund_rules_info = objectToArrayZ($refund_rules_info);
        return $refund_rules_info;
    }


}
