<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeliverOrderModel extends Model
{
    public $order_sta = [
        '1' => '待配货',
        '2' => '待发货',
        '3' => '已发货',
        '4' => '已完成',
        '5' => '已关闭',
        '6' => '待清点入库',
    ];
    //现货单推送状态
    public $is_push_erp = [
        '1' => '未推送',
        '2' => '已推送',
    ];

    /**
     * description:生成发货单
     * editor:zongxing
     * date : 2018.12.15
     * return boolen
     */
    public function makeDeliverOrder($deliver_order_info, $deliver_goods_info)
    {
        $insertRes = DB::transaction(function () use ($deliver_order_info, $deliver_goods_info) {
            //新增发货单
            if (!empty($deliver_order_info)) {
                DB::table("deliver_order")->insert($deliver_order_info);
            }
            //新增发货单中的商品
            if (!empty($deliver_goods_info)) {
                $insert_res = DB::table("deliver_goods")->insert($deliver_goods_info);
            }
            return $insert_res;
        });
        return $insertRes;
    }

    /**
     * description:配货单列表
     * editor:zongxing
     * date : 2018.12.15
     * return Array
     */
    public function deliverOrderList($param_info, $status = null, $is_not = false)
    {
        //获取查询条件
        $where = $this->createDeliverListOption($param_info, $status = null, $is_not = false);
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $fields = ['do.deliver_order_sn', 'order_sta', 'order_amount', 'real_order_amount',
            DB::raw('COUNT(jms_dg.spec_sn) as sku_num'),
            DB::raw('SUM(jms_dg.real_arrival_num) as goods_num'),
        ];
        $deliver_order_list = DB::table("deliver_order as do")
            ->select($fields)
            ->leftJoin('deliver_goods as dg', 'dg.deliver_order_sn', '=', 'do.deliver_order_sn')
            ->where($where)
            ->groupBy('do.deliver_order_sn')
            ->paginate($page_size);
        $deliver_order_list = objectToArrayZ($deliver_order_list);

        foreach ($deliver_order_list['data'] as $k => $v) {
            $order_sta_key = $v['order_sta'];
            $deliver_order_list['data'][$k]['order_sta'] = $this->order_sta[$order_sta_key];
        }
        return $deliver_order_list;
    }

    /**
     * description:组装发货单列表查询条件
     * editor:zongxing
     * date : 2019.01.30
     * return Array
     */
    public function createDeliverListOption($param_info, $status = null, $is_not = false){
        $where = [];
        if ($status) {
            $where = [
                ['order_sta', $status]
            ];
        }
        if ($status == 2) {
            $where = [1, 2];
            $where = function ($query) use ($where) {
                $query->whereIn('order_sta', $where);
            };
        }
        if ($status == 4 && $is_not = true) {
            $where = [4, 5];
            $sale_user_id = intval($param_info['sale_user_id']);
            $where = function ($query) use ($where, $sale_user_id) {
                $query->where('sale_user_id', $sale_user_id);
                $query->whereNotIn('order_sta', $where);
            };
        }
        return $where;
    }

    /**
     * description:erp订单推送-根据发货单号获取发货单基本信息
     * editor:zongxing
     * date : 2018.12.18
     * param 1.发货单号:$deliver_order_sn
     * return Array
     */
    public function getDeliverOrderInfo($deliver_order_sn)
    {
        $field = [
            'deliver_order_sn', 'order_sta', 'create_time'
        ];
        $where = [
            ['is_push_erp', 1],
            ['deliver_order_sn', $deliver_order_sn]
        ];
        $deliver_order_info = DB::table('deliver_order')->where($where)->first($field);
        $deliver_order_info = ObjectToArrayZ($deliver_order_info);
        return $deliver_order_info;
    }

    /**
     * description:修改发货单是否已推送
     * editor:zongxing
     * date : 2018.12.18
     * param $spot_order_sn 订单号
     * param $is_push_erp 是否已推送 1，未推送 2，已推送
     */
    public function updateIsPush($deliver_order_sn, $is_push_erp)
    {
        $pushDesc = $this->is_push_erp[$is_push_erp];
        if (is_null($pushDesc)) return false;
        $where = [
            ['deliver_order_sn', $deliver_order_sn],
        ];
        $update = [
            'is_push_erp' => $is_push_erp,
        ];
        $updateRes = DB::table('deliver_order')->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:更新发货单状态
     * editor:zongxing
     * date : 2018.12.19
     * return Array
     */
    public function changeDeliverStatus($deliver_order_sn, $status = 1)
    {
        $where = [
            ['deliver_order_sn', $deliver_order_sn]
        ];
        $update_data = [
            'order_sta' => $status,
        ];
        $updateRes = DB::table('deliver_order')->where($where)->update($update_data);
        return $updateRes;
    }


}
