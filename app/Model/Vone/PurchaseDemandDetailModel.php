<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Modules\ArrayGroupBy;

class PurchaseDemandDetailModel extends Model
{
    /**
     * description:优采推荐打开采购需求详情页
     * editor:zongxing
     * date : 2018.09.28
     * return Object
     */
    public function createRecommendDetail_stop($param_info, $discount_info)
    {
        $purchase_sn = $param_info["purchase_sn"];
        $demand_sn = $param_info["demand_sn"];

        $orWhere = [];
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = "%" . $query_sn . "%";
            $orWhere = [
                'orWhere1' => [
                    ['pdd.erp_merchant_no', 'LIKE', "%$query_sn%"],
                ],
                'orWhere2' => [
                    ['pdd.spec_sn', 'LIKE', "%$query_sn%"],
                ],
                'orWhere3' => [
                    ['pdd.goods_name', 'LIKE', "%$query_sn%"],
                ],
                'orWhere4' => [
                    ['pdd.erp_prd_no', 'LIKE', "%$query_sn%"],
                ],
            ];
        }
        $purchase_demand_info = DB::table("purchase_demand_detail as pdd")
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "pdd.spec_sn")
            ->leftJoin("goods as g", "g.goods_sn", "=", "gs.goods_sn")
            ->leftJoin("brand as b", "b.brand_id", "=", "g.brand_id")
            ->leftJoin("purchase_channel_goods as pcg", function ($leftJoin) {
                $leftJoin->on("pcg.purchase_sn", '=', "pdd.purchase_sn")
                    ->on("pcg.spec_sn", '=', "pdd.spec_sn")
                    ->on("pcg.demand_sn", '=', "pdd.demand_sn");
            })
            ->where("pdd.demand_sn", $demand_sn)
            ->where("pdd.purchase_sn", $purchase_sn)
            ->where(function ($result) use ($orWhere) {
                //if (count($orWhere) >= 1) {
                $result->orWhere($orWhere['orWhere1'])
                    ->orWhere($orWhere['orWhere2'])
                    ->orWhere($orWhere['orWhere3'])
                    ->orWhere($orWhere['orWhere4']);
                //}
            })
            ->get(["pdd.purchase_sn", "pdd.demand_sn", "pdd.goods_name", "pdd.erp_prd_no", "pdd.erp_merchant_no",
                "pdd.spec_sn", "pcg.may_num", "pcg.is_purchase", "pcg.method_sn", "pcg.channels_sn", "b.brand_id"]);
        $purchase_demand_info = objectToArrayZ($purchase_demand_info);

        //对表内容进行格式化
        $format_discount_info = [];
        $channel_arr = [];
        foreach ($discount_info as $current_info) {
            if (isset($format_discount_info[$current_info["brand_id"]])) {
                array_push($format_discount_info[$current_info["brand_id"]], $current_info);
            } else {
                $format_discount_info[$current_info["brand_id"]][] = $current_info;
            }

            $pin_str = $current_info["channels_name"] . "-" . $current_info["method_name"];
            if (!in_array($pin_str, $channel_arr)) {
                array_push($channel_arr, $pin_str);
            }
        }

        $discount_list = [];
        $tmp_arr = [];
        foreach ($format_discount_info as $k => $v) {
            //组装各个渠道的折扣
            foreach ($v as $k1 => $v1) {
                $pin_tmp_str = $v1["channels_sn"] . "-" . $v1["method_sn"];
                $tmp_arr[$pin_tmp_str] = $v1["brand_discount"];
                $tmp_arr[$pin_tmp_str . "-num"] = 0;
            }
            $discount_list[$k] = $tmp_arr;
            $tmp_arr = [];
        }

        foreach ($purchase_demand_info as $k => $v) {
            $brand_id = $v["brand_id"];
            $purchase_demand_info[$k]["discount_info"] = $discount_list[$brand_id];
        }

        $purchase_demand_tmp = [];
        foreach ($purchase_demand_info as $k => $v) {
            if (isset($purchase_demand_tmp[$v["spec_sn"]])) {
                array_push($purchase_demand_tmp[$v["spec_sn"]], $v);
            } else {
                $purchase_demand_tmp[$v["spec_sn"]][] = $v;
            }
        }

        $purchase_demand_list = [];
        foreach ($purchase_demand_tmp as $k => $v) {
            $tmp_demand_final["discount_info"] = $v[0]["discount_info"];
            foreach ($v as $k1 => $v1) {
                $tmp_demand_final["purchase_sn"] = $v1["purchase_sn"];
                $tmp_demand_final["demand_sn"] = $v1["demand_sn"];
                $tmp_demand_final["goods_name"] = $v1["goods_name"];
                $tmp_demand_final["erp_prd_no"] = $v1["erp_prd_no"];
                $tmp_demand_final["erp_merchant_no"] = $v1["erp_merchant_no"];
                $tmp_demand_final["spec_sn"] = $v1["spec_sn"];
                if ($v1["may_num"]) {
                    $pin_tmp_str = $v1["channels_sn"] . "-" . $v1["method_sn"];
                    $tmp_demand_final["discount_info"][$pin_tmp_str . "-num"] = $v1["may_num"];
                }
            }
            array_push($purchase_demand_list, $tmp_demand_final);
        }
        $recommend_detail_info["purchase_demand_list"] = $purchase_demand_list;
        $recommend_detail_info["channel_arr"] = $channel_arr;
        return $recommend_detail_info;
    }

    /**
     * description:获取商品在某一采购期需求详情
     * editor:zongxing
     * date : 2018.09.28
     * return Object
     */
    public function createDemandDetail($param_info, $discount_info)
    {
        $purchase_sn = $param_info["purchase_sn"];
        $demand_sn = $param_info["demand_sn"];

        $orWhere = [];
        if (isset($query_sn['query_sn']) && !empty($query_sn['query_sn'])) {
            $query_sn = trim($query_sn['query_sn']);
            $query_sn = "%" . $query_sn . "%";
            $orWhere = function ($query) use ($query_sn) {
                $query->orWhere('pdd.erp_merchant_no', 'LIKE', $query_sn)
                    ->orWhere('pdd.spec_sn', 'LIKE', $query_sn)
                    ->orWhere('pdd.goods_name', 'LIKE', $query_sn);
            };
        }

        $purchase_demand_info = DB::table("purchase_demand_detail as pdd")
            ->select(
                "pdd.purchase_sn", "pdd.demand_sn", "pdd.goods_name", "pdd.erp_prd_no", "pdd.erp_merchant_no",
                "pdd.spec_sn", "pdd.goods_num", "dcg.may_num", "method_name", "channels_name", "b.brand_id",
                "pdd.sale_discount", "dg.allot_num as allot_num", 'edit_status', 'goods_label'
            )
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "pdd.spec_sn")
            ->leftJoin("goods as g", "g.goods_sn", "=", "gs.goods_sn")
            ->leftJoin("brand as b", "b.brand_id", "=", "g.brand_id")
            ->leftJoin("demand_channel_goods as dcg", function ($leftJoin) {
                $leftJoin->on("dcg.purchase_sn", '=', "pdd.purchase_sn")
                    ->on("dcg.spec_sn", '=', "pdd.spec_sn")
                    ->on("dcg.demand_sn", '=', "pdd.demand_sn");
            })
            ->leftJoin("demand_goods as dg", function ($leftJoin) {
                $leftJoin->on("dg.spec_sn", '=', "pdd.spec_sn")
                    ->on("dg.demand_sn", '=', "pdd.demand_sn");
            })
            ->leftJoin("purchase_method as pm", "pm.method_sn", "=", "dcg.method_sn")
            ->leftJoin("purchase_channels as pc", "pc.channels_sn", "=", "dcg.channels_sn")
            ->where("pdd.demand_sn", $demand_sn)
            ->where("pdd.purchase_sn", $purchase_sn)
            ->where($orWhere)
            ->orderBy("pdd.goods_num", "DESC")
            ->get();
        $purchase_demand_info = objectToArrayZ($purchase_demand_info);

        //对表内容进行格式化
        $format_discount_info = [];
        $channel_arr = [];
        foreach ($discount_info as $current_info) {
            $format_discount_info[$current_info["brand_id"]][] = $current_info;
            $pin_str = $current_info["channels_name"] . "-" . $current_info["method_name"];
            if (!in_array($pin_str, $channel_arr)) {
                $channel_arr[] = $pin_str;
            }
        }

        $discount_list = [];
        foreach ($format_discount_info as $k => $v) {
            //组装各个渠道的折扣
            $key_arrays = [];
            $tmp_wai_arr = [];
            $tmp_zi_arr = [];
            $post_discount = 0;
            $is_wai = 0;
            $wai_line_discount = 0;
            foreach ($v as $k1 => $v1) {
                $pin_tmp_str = $v1["channels_name"] . "-" . $v1["method_name"];
                $channel_discount["brand_channel"] = $pin_tmp_str;
                $channel_discount["brand_discount"] = $v1["brand_discount"];
                $channel_discount["may_num"] = 0;
                $is_count_wai = $v1['is_count_wai'];
                if ($is_count_wai == 1 && $v1['method_name'] == '线上') {
                    $is_wai = 1;
                    $wai_line_discount = $v1['post_discount'];
                } else {
                    $post_discount += $v1['post_discount'];
                }

                //对自采外采进行判断 method_property,1表示自采,2表示外采
                if ($v1["method_property"] == 1) {
                    $key_arrays[] = $v1["brand_discount"];
                    $tmp_zi_arr[$pin_tmp_str] = $channel_discount;
                } else {
                    $tmp_wai_arr[$pin_tmp_str] = $channel_discount;
                }
            }
            //var_dump($key_arrays,$tmp_zi_arr,$tmp_wai_arr);
            //array_multisort($key_arrays, SORT_ASC, SORT_NUMERIC, $tmp_zi_arr);
            foreach ($tmp_zi_arr as $m => $n) {
                $tmp_wai_arr[$m] = $n;
            }

            $wai_line_point = 0;
            $post_discount_key = count($v);
            if ($is_wai) {
                $wai_line_point = $post_discount / $post_discount_key + $wai_line_discount;
            }
            $tmp_arr['wai_line_point'] = round($wai_line_point, 2);
            $tmp_arr['discount_detail'] = $tmp_wai_arr;
            $discount_list[$k] = $tmp_arr;
        }

        foreach ($purchase_demand_info as $k => $v) {
            $brand_id = $v["brand_id"];
            $purchase_demand_info[$k]["discount_info"] = '';
            if (isset($discount_list[$brand_id])) {
                $purchase_demand_info[$k]["discount_info"] = $discount_list[$brand_id];
            }
        }

        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();
        $purchase_demand_list = [];
        foreach ($purchase_demand_info as $k => $v) {
            $goods_label = explode(',', $v['goods_label']);
            $tmp_goods_label = [];
            if(!empty($goods_label)){
                foreach ($goods_label_info as $k1 => $v1) {
                    $label_id = intval($v1['id']);
                    if (in_array($label_id, $goods_label)) {
                        $tmp_goods_label[] = $v1;
                    }
                }
            }
            $v['goods_label_list'] = $tmp_goods_label;
            $purchase_demand_list[$v["spec_sn"]][] = $v;
        }

        $purchase_demand_total_list = [];
        foreach ($purchase_demand_list as $k => $v) {
            $tmp_demand_final = [
                'purchase_sn' => $v[0]['purchase_sn'],
                'demand_sn' => $v[0]['demand_sn'],
                'goods_name' => $v[0]['goods_name'],
                'erp_prd_no' => $v[0]['erp_prd_no'],
                'erp_merchant_no' => $v[0]['erp_merchant_no'],
                'spec_sn' => $v[0]['spec_sn'],
                'edit_status' => $v[0]['edit_status'],
                'goods_num' => intval($v[0]['goods_num']),
                'allot_num' => intval($v[0]['allot_num']),
                'may_num' => intval($v[0]['may_num']),
                'sale_discount' => round($v[0]["sale_discount"], 2),
                'goods_label_list' => $v[0]["goods_label_list"],
            ];

            if (empty($v[0]["discount_info"])) {
                $tmp_demand_final["discount_info"] = [];
            } else {
                $tmp_demand_final['wai_line_point'] = round($v[0]["discount_info"]['wai_line_point'], 2);
                $tmp_demand_final["discount_info"] = $v[0]['discount_info']['discount_detail'];
                foreach ($v as $k1 => $v1) {
                    $pin_tmp_str = $v1["channels_name"] . "-" . $v1["method_name"];
                    if (isset($tmp_demand_final["discount_info"][$pin_tmp_str])) {
                        $tmp_demand_final["discount_info"][$pin_tmp_str]["may_num"] = intval($v1['may_num']);
                        $tmp_demand_final['may_num'] += intval($v[0]['may_num']);
                    }
                }
                $tmp_demand_final["discount_info"] = array_values($tmp_demand_final["discount_info"]);
            }
            $purchase_demand_total_list[] = $tmp_demand_final;
        }
        $return_info["purchase_demand_list"] = $purchase_demand_total_list;
        $return_info["channel_arr"] = $channel_arr;
        return $return_info;
    }

    /**
     * description:优采推荐打开采购需求详情页
     * editor:zongxing
     * date : 2018.09.28
     * return Object
     */
    public function createTotalDemandList($param_info)
    {
        $purchase_sn = $param_info["purchase_sn"];
        $orWhere = [];
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = "%" . $query_sn . "%";
            $orWhere[] = ['pdd.erp_merchant_no', 'LIKE', $query_sn];
            $orWhere[] = ['pdd.spec_sn', 'LIKE', $query_sn];
            $orWhere[] = ['pdd.goods_name', 'LIKE', $query_sn];
            $orWhere[] = ['pdd.erp_prd_no', 'LIKE', $query_sn];
        }

        $purchase_demand_info = DB::table('purchase_channel_goods as pcg')
            ->select(
                'pcg.purchase_sn',
                'g.goods_name', 'gs.erp_prd_no', "gs.erp_merchant_no",
                "pcg.spec_sn", "method_name", "channels_name", "gs.erp_ref_no", "gs.spec_price", "dc.goods_num",
                "dc.may_buy_num", "dc.real_buy_num", "pcg.channel_discount","pdd.demand_sn","pdd.sale_discount",
                "user_name","method_property", 'pc.post_discount', 'pc.is_count_wai','goods_label',
                DB::Raw("jms_dc.may_buy_num - jms_dc.real_buy_num as diff_num"),
                DB::Raw("jms_pcg.may_num as may_channel_num"),
                DB::Raw("jms_pcg.may_num - jms_pcg.real_num as diff_channel_num")
            )
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "pcg.spec_sn")
            ->leftJoin("goods as g", "g.goods_sn", "=", "gs.goods_sn")
            ->leftJoin("demand_count as dc", function ($leftJoin) {
                $leftJoin->on("dc.purchase_sn", '=', "pcg.purchase_sn")
                    ->on("dc.spec_sn", '=', "pcg.spec_sn");
            })
            ->leftJoin("purchase_demand_detail as pdd", function ($leftJoin) {
                $leftJoin->on("pdd.purchase_sn", '=', "pcg.purchase_sn")
                    ->on("pdd.spec_sn", '=', "pcg.spec_sn");
            })
            ->leftJoin("demand as d", function ($leftJoin) {
                $leftJoin->on("d.demand_sn", '=', "pdd.demand_sn");
            })
            ->leftJoin("sale_user as su", function ($leftJoin) {
                $leftJoin->on("su.id", '=', "d.sale_user_id");
            })
            ->leftJoin("purchase_method as pm", "pm.method_sn", "=", "pcg.method_sn")
            ->leftJoin("purchase_channels as pc", "pc.channels_sn", "=", "pcg.channels_sn")
            ->where("pcg.purchase_sn", $purchase_sn)
            ->where($orWhere)
            ->get();
        $purchase_demand_info = objectToArrayZ($purchase_demand_info);
        if (empty($purchase_demand_info)) {
            return $purchase_demand_info;
        }

        $purchase_demand_tmp = [];
        foreach ($purchase_demand_info as $k => $v) {
            $purchase_demand_tmp[$v["spec_sn"]][] = $v;
        }

        $purchase_demand_total_info = [];
        foreach ($purchase_demand_tmp as $k => $v) {
            $tmp_arr = [];
            foreach ($v as $k1 => $v1) {
                $method_name = $v1["method_name"];
                $channels_name = $v1["channels_name"];
                if (!isset($tmp_arr[$channels_name . $method_name])) {
                    $tmp_arr[$channels_name . $method_name] = $v1;
                }
            }
            $purchase_demand_total_info[$k] = array_values($tmp_arr);
        }

        $purchase_demand_tmp = $purchase_demand_total_info;

        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();

        $purchase_demand_list = [];
        $purchase_goods_spec = [];
        $total_channel_num = 0;
        foreach ($purchase_demand_tmp as $k => $v) {
            $purchase_goods_spec[] = $k;
            $key_arrays = [];
            $tmp_demand_final = [];
            $post_discount = 0;
            $is_wai = 0;
            $wai_line_discount = 0;
            $tmp_demand_final["purchase_sn"] = $v[0]["purchase_sn"];
            $tmp_demand_final["goods_name"] = $v[0]["goods_name"];
            $tmp_demand_final["erp_prd_no"] = $v[0]["erp_prd_no"];
            $tmp_demand_final["erp_merchant_no"] = $v[0]["erp_merchant_no"];
            $tmp_demand_final["spec_sn"] = $v[0]["spec_sn"];
            $tmp_demand_final["erp_ref_no"] = $v[0]["erp_ref_no"];
            $tmp_demand_final["spec_price"] = $v[0]["spec_price"];
            $tmp_demand_final["diff_num"] = $v[0]["diff_num"];
            $tmp_demand_final["goods_num"] = $v[0]["goods_num"];
            $tmp_demand_final["may_buy_num"] = $v[0]["may_buy_num"];
            $tmp_demand_final["real_buy_num"] = $v[0]["real_buy_num"];
            $goods_label = explode(',', $v[0]['goods_label']);
            $tmp_goods_label = [];
            foreach ($goods_label_info as $k1 => $v1) {
                $label_id = intval($v1['id']);
                if (in_array($label_id, $goods_label)) {
                    $tmp_goods_label[] = $v1;
                }
            }
            $tmp_demand_final['goods_label_list'] = $tmp_goods_label;

            foreach ($v as $k1 => $v1) {
                $post_discount += $v1['post_discount'];
                $is_count_wai = $v1['is_count_wai'];
                if ($is_count_wai == 1 && $v1['method_name'] == '线上') {
                    $is_wai = 1;
                    $wai_line_discount = $v1['post_discount'];
                } else {
                    $post_discount += $v1['post_discount'];
                }

                $pin_tmp_str = $v1["channels_name"] . "-" . $v1["method_name"];
                if (!isset($key_arrays[$pin_tmp_str])) {
                    $key_arrays[$pin_tmp_str] = $v1["channel_discount"];
                }

                $tmp_demand_final["discount_info"][$pin_tmp_str]["brand_channel"] = $pin_tmp_str;
                $tmp_demand_final["discount_info"][$pin_tmp_str]["brand_discount"] = $v1["channel_discount"];
                $tmp_demand_final["discount_info"][$pin_tmp_str]["may_channel_num"] = $v1["may_channel_num"];
                $tmp_demand_final["discount_info"][$pin_tmp_str]["diff_channel_num"] = $v1["diff_channel_num"];
                $tmp_demand_final["discount_info"][$pin_tmp_str]["method_property"] = $v1["method_property"];

                $pin_demand_str = $v1["demand_sn"];
                $tmp_demand_final["sale_discount_info"][$pin_demand_str]["user_name"] = $v1["user_name"];
                $tmp_demand_final["sale_discount_info"][$pin_demand_str]["sale_discount"] = round($v1["sale_discount"], 2);
            }

            $wai_line_point = 0;
            $post_discount_key = count($v);
            if ($is_wai) {
                $wai_line_point = $post_discount / $post_discount_key + $wai_line_discount;
            }
            $tmp_demand_final['wai_line_point'] = round($wai_line_point, 2);
            if (count($key_arrays) > 1) {
                array_multisort($key_arrays, SORT_ASC, SORT_NUMERIC, $tmp_demand_final["discount_info"]);
            }
            if (count($key_arrays) > $total_channel_num) {
                $total_channel_num = count($key_arrays);
            }
            $tmp_demand_final["discount_info"] = array_values($tmp_demand_final["discount_info"]);
            $tmp_demand_final["sale_discount_info"] = array_values($tmp_demand_final["sale_discount_info"]);
            $tmp_demand_final["total_may_buy_num"] = 0;
            $tmp_demand_final["total_real_buy_num"] = 0;
            $purchase_demand_list[$k] = $tmp_demand_final;
        }

        //获取采购期对应的商品数据信息
        $dc_model = new DemandCountModel();
        $demand_count_goods_info = $dc_model->getDemandCountGoodsInfo($purchase_sn, $purchase_goods_spec);

        //获取采购期预采信息
//        $rpd_model = new RealPurchaseDetailModel();
//        $purchase_sn_arr[] = $purchase_sn;
//        $predict_goods_detail = $rpd_model->getBatchPredictGoodsInfo($purchase_sn_arr);
//
//        if (!empty($predict_goods_detail)) {
//            foreach ($predict_goods_detail as $k => $v) {
//                $spec_sn = $v['spec_sn'];
//                $predict_goods_num = intval($v['predict_goods_num']);
//                if (isset($purchase_demand_list[$spec_sn])) {
//                    $purchase_demand_list[$spec_sn]['real_buy_num'] -= $predict_goods_num;
//                    $purchase_demand_list[$spec_sn]['diff_num'] += $predict_goods_num;
//                }
//            }
//        }
        foreach ($demand_count_goods_info as $k => $v) {
            $spec_sn = $v[0]['spec_sn'];
            $total_may_buy_num = $v[0]['total_may_buy_num'];
            $total_real_buy_num = $v[0]['total_real_buy_num'];
            if (isset($purchase_demand_list[$spec_sn])) {
                $purchase_demand_list[$spec_sn]['total_may_buy_num'] = $total_may_buy_num;
                $purchase_demand_list[$spec_sn]['total_real_buy_num'] = $total_real_buy_num;
            }
        }

        $return_info["purchase_demand_list"] = array_values($purchase_demand_list);
        $return_info["channel_num"] = $total_channel_num;
        return $return_info;
    }

    /**
     * description:以采购单为主对各部门下的每个商品按需求单的需求数量比例进行分货
     * editor:zhangdong
     * date : 2018.10.22
     * return Object
     */
    public function sortGoodsByPurSn($purchase_sn, $demand_sn = '')
    {
        $purchase_sn = trim($purchase_sn);
        $field = [
            'pd.department', 'pdd.goods_name', 'pdd.erp_merchant_no', 'pdd.spec_sn',
            DB::raw('SUM(jms_pdd.goods_num) AS dpm_goods_num'),
            'dc.goods_num AS total_num', 'pd.demand_sn'
        ];
        $where = [
            ['pd.purchase_sn', $purchase_sn]
        ];
        if (!empty($demand_sn)) {
            $where[] = ['pd.demand_sn', $demand_sn];
        }
        $pdd_on = [
            ['pdd.purchase_sn', '=', 'pd.purchase_sn'],
            ['pdd.demand_sn', '=', 'pd.demand_sn'],
        ];
        $dc_on = [
            ['dc.purchase_sn', '=', 'pdd.purchase_sn'],
            ['dc.spec_sn', '=', 'pdd.spec_sn'],
        ];
        $purGoodsInfo = DB::table('purchase_demand AS pd')->select($field)
            ->leftJoin('purchase_demand_detail AS pdd', $pdd_on)
            ->leftJoin('demand_count AS dc', $dc_on)
            ->where($where)
            ->groupBy(['pdd.spec_sn', 'pd.department'])->get();
        return $purGoodsInfo;

    }


    /**
     * description:根据批次单获取对应商品数据
     * editor:zhangdong
     * date : 2018.10.22
     * return Object
     */
    public function getRealGoodsInfo($purchase_sn, $real_purchase_sn)
    {
        $field = [
            'rpd.erp_merchant_no', 'rpd.spec_sn', 'rpd.sort_num', 'rpd.allot_num'
        ];
        $where = [
            ['rp.purchase_sn', $purchase_sn],
            ['rp.real_purchase_sn', $real_purchase_sn],
        ];
        $rpd_on = [
            ['rpd.purchase_sn', '=', 'rp.purchase_sn'],
            ['rpd.real_purchase_sn', '=', 'rp.real_purchase_sn'],
        ];
        $realGoodsInfo = DB::table('real_purchase AS rp')->select($field)
            ->leftJoin('real_purchase_detail AS rpd', $rpd_on)
            ->where($where)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $realGoodsInfo;

    }


    /**
     * description:根据采购单号和批次单号查询分配表是否已经写入过数据
     * editor:zhangdong
     * date : 2018.10.22
     * return Object
     */
    public function queryDepSortGoods($purchase_sn, $real_purchase_sn)
    {
        $field = ['purchase_sn', 'real_pur_sn', 'depart_id', 'spec_sn', 'ratio_num', 'handle_num', 'may_sort_num'];
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_pur_sn', $real_purchase_sn]
        ];
        $depSortGoods = DB::table('depart_sort_goods')->select($field)->where($where)->get()
            ->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $depSortGoods;
    }


    /**
     * description:根据采购单号以批次单为主按部门进行分货-手动修改分货数量
     * editor:zhangdong
     * date : 2018.10.22
     * return Object
     */
    public function modHandNum($purchase_sn, $real_purchase_sn, $depart_id, $spec_sn, $handle_num)
    {
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_pur_sn', $real_purchase_sn],
            ['depart_id', $depart_id],
            ['spec_sn', $spec_sn]
        ];
        $update = ['handle_num' => $handle_num];
        $modRes = DB::table('depart_sort_goods')->where($where)->update($update);
        return $modRes;

    }

    /**
     * description:商品部根据采购单以批次单为基础分货-获取可分货的数量
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function getCanSortNum($purchase_sn, $real_purchase_sn, $spec_sn, $depart_id)
    {
        $field = [
            'purchase_sn', 'real_pur_sn', 'spec_sn', 'may_sort_num', 'handle_num',
            DB::raw('SUM(handle_num) AS other_handle_num')
        ];
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_pur_sn', $real_purchase_sn],
            ['spec_sn', $spec_sn],
        ];
        $not_in = [$depart_id];
        $canSortNum = DB::table('depart_sort_goods')->select($field)->where($where)->whereNotIn('depart_id', $not_in)->first();
        return $canSortNum;
    }

    /**
     * description:查询分货信息
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function getUserSortGoods($purchase_sn, $real_purchase_sn, $depart_id)
    {
        $field = [
            'pd.purchase_sn', 'pd.demand_sn', 'dsg.real_pur_sn', 'pd.department', 'd.sale_user_id', 'pdd.spec_sn', 'pdd.goods_name',
            'pdd.goods_num', 'dsg.handle_num as may_sort_num'
        ];
        $where = [
            ['pd.department', $depart_id],
            ['pd.purchase_sn', $purchase_sn],
            ['dsg.real_pur_sn', $real_purchase_sn]
        ];
        $pdd_on = [
            ['pdd.purchase_sn', '=', 'pd.purchase_sn'],
            ['pdd.demand_sn', '=', 'pd.demand_sn'],
        ];
        $dsg_on = [
            ['dsg.purchase_sn', '=', 'pd.purchase_sn'],
            ['dsg.depart_id', '=', 'pd.department'],
            ['dsg.spec_sn', '=', 'pdd.spec_sn'],
        ];
        $userSortGoodsInfo = DB::table('purchase_demand AS pd')->select($field)
            ->leftJoin('purchase_demand_detail AS pdd', $pdd_on)
            ->leftJoin('demand AS d', 'd.demand_sn', '=', 'pd.demand_sn')
            ->leftJoin('depart_sort_goods AS dsg', $dsg_on)
            ->where($where)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $userSortGoodsInfo;


    }

    /**
     * description:查询一个采购单下当前部门的商品需求总数
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function getGoodsNeedMsg($purchase_sn, $depart_id)
    {
        $field = [DB::raw('SUM(jms_pdd.goods_num) AS goods_num'), 'pdd.spec_sn'];
        $where = [
            ['pd.department', $depart_id],
            ['pd.purchase_sn', $purchase_sn],
        ];
        $pdd_on = [
            ['pdd.purchase_sn', '=', 'pd.purchase_sn'],
            ['pdd.demand_sn', '=', 'pd.demand_sn'],
        ];
        $goodsNeedMsg = DB::table('purchase_demand AS pd')->select($field)
            ->leftJoin('purchase_demand_detail AS pdd', $pdd_on)
            ->where($where)->groupBy('pdd.spec_sn')->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $goodsNeedMsg;
    }

    /**
     * description:根据采购单号,批次单号,部门id查询分配表是否已经写入过数据
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function queryUserSortGoods($purchase_sn, $real_purchase_sn, $depart_id)
    {
        $field = ['purchase_sn', 'real_pur_sn', 'depart_id', 'sale_user_id', 'spec_sn', 'may_sort_num', 'ratio_num', 'handle_num'];
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_pur_sn', $real_purchase_sn],
            ['depart_id', $depart_id],
        ];
        $depSortGoods = DB::table('user_sort_goods')->select($field)->where($where)->get();
        return $depSortGoods;
    }

    /**
     * description:将分配数据写入商品部按部门分货数据表
     * editor:zhangdong
     * date : 2018.10.22
     * return Object
     */
    public function insertUsrSortGoods($user_sort_goods)
    {
        $addRes = DB::table('user_sort_goods')->insert($user_sort_goods);
        return $addRes;
    }

    /**
     * description:商品部根据采购单以批次单为基础分货-获取可分货的数量
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function usrCanSortNum($purchase_sn, $real_purchase_sn, $spec_sn, $demand_sn)
    {
        $field = ['purchase_sn', 'real_pur_sn', 'depart_id', 'sale_user_id', 'spec_sn', 'may_sort_num', 'ratio_num', DB::raw('SUM(handle_num) AS other_handle_num')];
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_pur_sn', $real_purchase_sn],
            ['spec_sn', $spec_sn],
        ];
        $not_in = [$demand_sn];
        $canSortNum = DB::table('user_sort_goods')->select($field)->where($where)->whereNotIn('demand_sn', $not_in)->first();
        return $canSortNum;
    }

    /**
     * description:修改调整值
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function modUsrHandNum($purchase_sn, $real_purchase_sn, $demand_sn, $spec_sn, $handle_num)
    {
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_pur_sn', $real_purchase_sn],
            ['demand_sn', $demand_sn],
            ['spec_sn', $spec_sn]
        ];
        $update = ['handle_num' => $handle_num];
        $modRes = DB::table('user_sort_goods')->where($where)->update($update);
        return $modRes;
    }


    /**
     * description:部门分货数据纠正-按比例计算后会有误差
     * editor:zhangdong
     * date : 2018.10.22
     * return Object
     */
    public function correctSortData($arrData)
    {
        //根据当前的采购单和实采单数据按规格码进行分组
        $group_field = ['spec_sn'];
        $group_by_value = [
            'spec_sn',
            'may_sort_num',
            'total_ratio_num' => function ($data) {
                $totalNum = array_sum(array_column($data, 'ratio_num'));
                return $totalNum;
            }
        ];
        $groupByData = ArrayGroupBy::groupBy($arrData, $group_field, $group_by_value);
        $newSortGoods = [];
        foreach ($groupByData as $value) {
            $spec_sn = trim($value['spec_sn']);
            $maySortNum = intval($value['may_sort_num']);//可分货数量
            $total_ratio_num = intval($value['total_ratio_num']);//按比例分配的总数
            if ($total_ratio_num > $maySortNum) {
                $diffNum = intval($total_ratio_num - $maySortNum);//误差值
                $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
                //对搜索到的值按比例数量进行倒序排序
                $sortRes = sortTwoArray($searchRes, 'SORT_DESC', 'ratio_num');
                //从需求比例最大的项中将差值减掉
                $depart_id = $sortRes[0]['depart_id'];
                foreach ($arrData as $key => $item) {
                    if ($item['depart_id'] == $depart_id && $item['spec_sn'] == $spec_sn) {
                        $ratio_num = intval($item['ratio_num']);
                        $correctNum = $ratio_num - $diffNum;
                        $newSortGoods[$key] = $item;
                        $newSortGoods[$key]['ratio_num'] = $correctNum;
                        $newSortGoods[$key]['handle_num'] = $correctNum;
                        //减少循环次数
                        unset($arrData[$key]);
                        break;
                    }
                }
            }
        }
        $finalData = array_merge($newSortGoods, $arrData);
        //删除may_sort_num键
        foreach ($finalData as $key => $value) {
            unset($finalData[$key]['may_sort_num']);
        }
        return $finalData;
    }


    /**
     * description:根据纠正过的数据查询对应信息-以部门分货
     * editor:zhangdong
     * date : 2018.10.26
     * return Object
     */
    public function getSortGoods($purchase_sn, $real_purchase_sn)
    {
        $field = [
            'dsg.handle_num', 'dsg.goods_name', 'dsg.depart_need_num', 'dsg.total_num', 'dpm.de_name as department',
            'dsg.depart_id', 'dsg.spec_sn', DB::raw('IFNULL(jms_rpd.sort_num,0) as may_sort_num'), 'dsg.ratio', 'dsg.ratio_num'
        ];
        $where = [
            ['dsg.purchase_sn', $purchase_sn],
            ['dsg.real_pur_sn', $real_purchase_sn],
        ];
        $dpm_on = [
            ['dsg.depart_id', '=', 'dpm.department_id'],
        ];
        $rpd_on = [
            ['rpd.real_purchase_sn', '=', 'dsg.real_pur_sn'],
            ['rpd.purchase_sn', '=', 'dsg.purchase_sn'],
            ['rpd.spec_sn', '=', 'dsg.spec_sn'],
        ];
        $queryRes = DB::table('depart_sort_goods AS dsg')->select($field)
            ->leftJoin('department AS dpm', $dpm_on)
            ->leftJoin('real_purchase_detail AS rpd', $rpd_on)->where($where)->orderBy('dsg.spec_sn', 'ASC')->get();
        return $queryRes;

    }

    /**
     * description:根据纠正过的数据查询对应信息-以销售用户分货
     * editor:zhangdong
     * date : 2018.10.26
     * return Object
     */
    public function userSortGoods($purchase_sn, $real_purchase_sn)
    {
        $field = [
            'demand_sn', 'su.user_name as sale_user', 'usg.sale_user_id', 'goods_name', 'spec_sn', 'user_need_num as goods_num',
            'user_total_num as total_num', 'ratio', 'ratio_num', 'may_sort_num', 'handle_num'
        ];
        $where = [
            ['usg.purchase_sn', $purchase_sn],
            ['usg.real_pur_sn', $real_purchase_sn],
        ];
        $queryRes = DB::table('user_sort_goods AS usg')->select($field)
            ->leftJoin('sale_user as su', 'su.id', '=', 'usg.sale_user_id')
            ->where($where)->orderBy('usg.spec_sn', 'ASC')->get();
        return $queryRes;

    }

    /**
     * description:获取指定采购期内某一需求单的商品信息
     * editor:zongxing
     * date : 2018.12.11
     * return Array
     */
    public function getGoodsByPurchaseAndDemand($where)
    {
        $purchase_demand_goods_info = DB::table("purchase_demand as pd")
            ->leftJoin("purchase_demand_detail as pdd", function ($leftJoin) {
                $leftJoin->on("pdd.purchase_sn", "=", "pd.purchase_sn")
                    ->on("pdd.demand_sn", "=", "pd.demand_sn");
            })
            ->where($where)->get();
        $purchase_demand_goods_info = objectToArrayZ($purchase_demand_goods_info);
        return $purchase_demand_goods_info;
    }


}
