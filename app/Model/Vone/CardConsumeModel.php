<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CardConsumeModel extends Model
{
    protected $table = 'card_consume as cc';
    protected $field = [
        'cc.id', 'cc.day_time', 'cc.channels_id', 'cc.method_id', 'cc.consume_money', 'cc.return_rate', 'cc.consume_type',
    ];

    /**
     * description:采购金额列表
     * editor:zongxing
     * date : 2019.03.13
     * return Array
     */
    public function getCardConsumeList($param_info)
    {

        if (isset($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
        }
        if (isset($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
        }
        if (!isset($param_info['start_time']) && !isset($param_info['end_time'])) {
            $start_time = Carbon::now()->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::now()->endOfMonth()->toDateTimeString();
        }
        if (!empty($start_time)) {
            $where[] = ['cc.day_time', '>=', $start_time];
        }
        if (!empty($end_time)) {
            $where[] = ['cc.day_time', '<=', $end_time];
        }
        if (isset($param_info['channels_id'])) {
            $channels_id = intval($param_info['channels_id']);
            $where[] = ['cc.channels_id', '=', $channels_id];
        }

        $field = [
            'cc.id', 'cc.day_time', 'cc.channels_id', 'cc.method_id', 'cc.consume_money', 'pc.channels_name',
            'pm.method_name', 'cc.return_rate', 'cc.consume_type',
            DB::raw("(
                    CASE jms_cc.consume_type
                    WHEN 1 THEN
                        '结账卡消费金额'
                    WHEN 2 THEN
                        '充值金额'
                    END
                ) consume_type"),
            DB::raw("(jms_cc.return_rate * 100) as final_return_rate")
        ];
        $cc_obj = DB::table($this->table)
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'cc.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'cc.method_id');
        if (isset($param_info['query_sn'])) {
            $query_sn = '%' . trim($param_info['query_sn']) . '%';
            $cc_obj->where(function ($where) use ($query_sn) {
                $where->orWhere('channels_name', 'like', $query_sn)
                    ->orWhere('method_name', 'like', $query_sn);
            });
        }
        $cc_list = $cc_obj->where($where)->get($field);
        $cc_list = objectToArrayZ($cc_list);
        return $cc_list;
    }

    /**
     * description 维护采购金额
     * author zongxing
     * date 2019.08.13
     * return foolean
     */
    public function doAddCardConsume($param_info)
    {
        $insert_data = [
            'day_time' => trim($param_info['day_time']),
            'method_id' => intval($param_info['method_id']),
            'channels_id' => intval($param_info['channels_id']),
            'consume_type' => intval($param_info['consume_type']),
            'consume_money' => floatval($param_info['consume_money']),
        ];
        $cc_res = DB::table('card_consume')->insert($insert_data);
        $cc_res = objectToArrayZ($cc_res);
        return $cc_res;
    }


}
