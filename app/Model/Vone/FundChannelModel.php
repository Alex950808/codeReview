<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FundChannelModel extends Model
{
    public $channel_cat = [
        '1' => '自有可支配资金',
        '2' => '可融资金',
        '3' => '待回款资金',
        '4' => '需求资金'
    ];


    /**
     * description:获取该资金渠道信息
     * editor:zongxing
     * date : 2018.12.06
     * return Object
     */
    public function getFundChannelInfo(
        $fund_channel_name = null,
        $fund_cat_id = null,
        $fund_channel_id = null,
        $sale_user_id = null
    )
    {
        $fields = ['id', 'fund_channel_name', 'fund_cat_id', 'usd', 'cny', 'krw', 'covert_cny', 'sale_user_id',
            DB::raw('DATE(jms_fc.modify_time) as end_date')
        ];
        $fund_channel_obj = DB::table('fund_channel as fc')->select($fields);
        if ($fund_channel_name) $fund_channel_obj->where('fund_channel_name', $fund_channel_name);
        if ($fund_cat_id) $fund_channel_obj->where('fund_cat_id', $fund_cat_id);
        if ($fund_channel_id) $fund_channel_obj->where('id', $fund_channel_id);
        if ($sale_user_id) $fund_channel_obj->where('sale_user_id', $sale_user_id);
        $fund_channel_info = $fund_channel_obj->get();
        $fund_channel_info = ObjectToArrayZ($fund_channel_info);
        return $fund_channel_info;
    }

    /**
     * description:需求资金详情列表
     * editor:zongxing
     * date : 2018.12.17
     * return Array
     */
    public function getFundListDetail($param_info)
    {
        $action = trim($param_info['action']);
        if ($action == 'purchase') {
            //获取采购期待采的需求资金
            $pcg_model = new PurchaseChannelGoodsModel();
            $pcg_list = $pcg_model->getPurchseFund();
            if (empty($pcg_list)) {
                return ['code' => '1002', 'msg' => '暂无需求资金'];
            }

            //获取采购期预采信息
            $purchase_sn_arr = [];
            foreach ($pcg_list as $k => $v) {
                $purchase_sn_arr[] = $k;
            }
            $rpd_model = new RealPurchaseDetailModel();
            $predict_goods_detail = $rpd_model->getBatchPredictInfo($purchase_sn_arr);

            $error_info = '商品:';
            $purchase_channel_goods = [];
            foreach ($pcg_list as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $spec_sn = $v1['spec_sn'];
                    if ($v1['brand_discount'] == null) {
                        $method_name = $v1['method_name'];
                        $channels_name = $v1['channels_name'];
                        $error_info .= $spec_sn . ' 在 ' . $method_name . '-' . $channels_name . ',';
                    }
                    $purchase_channel_goods[$k][$spec_sn][] = $v1;
                }
            }
            if ($error_info !== '商品:') {
                $error_info = substr($error_info, 0, -1);
                $error_info .= ' 不存在采购折扣信息,请维护';
                return response()->json(['code' => '1003', 'msg' => $error_info]);
            }

            $data_list = $this->createDemandFundDetail($purchase_channel_goods, $predict_goods_detail);
            $total_num = count($data_list);
        } elseif ($action == 'demand') {
            //获取预采需求单的需求资金
            $demand_goods_model = new DemandGoodsModel();
            $predict_demand_goods_info = $demand_goods_model->getFundOfDemandPredict();
            //获取预采中需求单的已采资金
            $rpd_model = new RealPurchaseDetailModel();
            $rpd_goods_info = $rpd_model->getFundOfRealPredict();
            //计算剩采购需求资金
            foreach ($rpd_goods_info as $k => $v) {
                if (isset($predict_demand_goods_info[$k])) {
                    $total_price = floatval($predict_demand_goods_info[$k][0]['total_price']);
                    $real_total_price = floatval($rpd_goods_info[$k][0]['total_price']);
                    $diff_fund = $total_price - $real_total_price;
                    $predict_demand_goods_info[$k][0]['total_price'] = round($diff_fund, 2);
                }
            }
            $predict_total_info = [];
            foreach ($predict_demand_goods_info as $k => $v) {
                $predict_total_info[] = $predict_demand_goods_info[$k][0];
            }
            $data_list = ObjectToArrayZ($predict_total_info);
            $total_num = count($data_list);
        }

        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $return_info['data_list'] = array_values(array_slice($data_list, $start_page, $page_size));
        $return_info['total_num'] = $total_num;
        return $return_info;
    }

    /**
     * description:需求资金详情列表-数据组装
     * editor:zongxing
     * date : 2019.01.18
     * return Array
     */
    public function createDemandFundDetail($purchase_channel_goods, $predict_goods_detail)
    {
        $purchase_channel_goods_list = [];
        foreach ($purchase_channel_goods as $k => $v) {
            $sku_num = count($v);
            $purchase_channel_goods_list[$k]['purchase_sn'] = $k;
            $purchase_channel_goods_list[$k]['sku_num'] = $sku_num;
            $purchase_may_num = 0;
            $purchase_real_num = 0;
            $purchase_total_amount = 0;
            foreach ($v as $k1 => $v1) {
                $goods_may_num = 0;
                $goods_real_num = 0;
                $goods_total_amount = 0;
                foreach ($v1 as $k2 => $v2) {
                    $goods_may_num += intval($v2['may_num']);
                    $goods_real_num += intval($v2['real_num']);
                    $diff_num = intval($v2['diff_num']);
                    $spec_price = floatval($v2['spec_price']);
                    $goods_total_amount += round(($spec_price * $diff_num * floatval($v2['brand_discount'])), 2);
                }
                $purchase_may_num += $goods_may_num;
                $purchase_real_num += $goods_real_num;
                if ($goods_total_amount > 0) {
                    $purchase_total_amount += $goods_total_amount;
                }
            }
            if (isset($predict_goods_detail[$k])) {
                $predict_goods_num = intval($predict_goods_detail[$k][0]['predict_goods_num']);
                $purchase_may_num -= $predict_goods_num;
                $purchase_real_num -= $predict_goods_num;
            }
            $purchase_channel_goods_list[$k]['purchase_may_num'] = $purchase_may_num;
            $purchase_channel_goods_list[$k]['purchase_real_num'] = $purchase_real_num;
            $purchase_channel_goods_list[$k]['purchase_total_amount'] = $purchase_total_amount;
        }
        return $purchase_channel_goods_list;
    }


}
