<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RealPurchaseDetailModel extends Model
{
    protected $table = 'real_purchase_detail';

    //可操作字段
    protected $field = [
        'real_purchase_sn', 'purchase_sn', 'erp_prd_no', 'erp_merchant_no', 'spec_sn', 'goods_name'
        , 'day_buy_num', 'allot_num', 'diff_num', 'remark', 'sort_num', 'id'
    ];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';


    /**
     * description:获取采购批次详情
     * editor:zongxing
     * type:POST
     * * params: 1.实采批次单号:real_purchase_sn;
     * date : 2018.07.10
     * return Object
     */
    public function getBatchGoodsDetail($batch_info)
    {
        //$real_purchase_sn = $batch_info["real_purchase_sn"];
        $purchase_sn = $batch_info["purchase_sn"];
        $group_sn = $batch_info["group_sn"];
        $is_mother = $batch_info["is_mother"];
        $fields = ['rpd.goods_name', 'rpd.erp_prd_no', 'rpd.erp_merchant_no', 'rpd.spec_sn', 'rpd.day_buy_num',
            'rpd.allot_num', 'rpd.remark', 'rpd.purchase_remark', 'gs.spec_price', 'cost_amount',
            'rpd.channel_discount', 'rpd.diff_num', 'post_amount', 'gs.goods_label',
            DB::raw('(day_buy_num - allot_num) as diff_num'),
            DB::raw('(CASE
                    WHEN jms_gs.spec_weight != 0 THEN
                        jms_gs.spec_weight
                    WHEN jms_gs.estimate_weight != 0 THEN
                        jms_gs.estimate_weight
                    ELSE
                        0.00
                    END) as spec_weight')
        ];
        $where = [
            ['rp.group_sn', '=', $group_sn],
            ['rp.purchase_sn', '=', $purchase_sn],
            ['rp.is_mother', '=', $is_mother]
        ];

        $real_purchase_obj = DB::table('real_purchase_detail as rpd')->select($fields)
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpd.spec_sn')
            ->where($where);
        if (isset($batch_info['query_sn'])) {
            $query_sn = trim($batch_info['query_sn']);
            if (preg_match("/[\x7f-\xff]/", $query_sn)) {
                $query_sn = explode(" ", $query_sn);
                $real_purchase_obj->where(function ($querey) use ($query_sn) {
                    foreach ($query_sn as $k => $v) {
                        $like_goods_name = '%' . $v . '%';
                        $querey->orWhere('goods_name', 'LIKE', '\'' . $like_goods_name . '\'');
                    }
                });
            } else {
                $query_sn = "%" . $query_sn . "%";
                $real_purchase_obj->where(function ($querey) use ($query_sn) {
                    $like_query_sn = '%' . $query_sn . '%';
                    $querey->orWhere('spec_sn', 'LIKE', '\'' . $like_query_sn . '\'')
                        ->orWhere('erp_prd_no', 'LIKE', '\'' . $like_query_sn . '\'')
                        ->orWhere('erp_merchant_no', 'LIKE', '\'' . $like_query_sn . '\'');
                });
            }
        }
        $real_purchase_list = $real_purchase_obj->get();
        $real_purchase_list = objectToArrayZ($real_purchase_list);

        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();

        $batch_goods_list = [];
        foreach ($real_purchase_list as $k => $v) {
            $spec_sn = $v['spec_sn'];
            if (isset($batch_goods_list[$spec_sn])) {
                $batch_goods_list[$spec_sn]['day_buy_num'] += $v['day_buy_num'];
                $batch_goods_list[$spec_sn]['allot_num'] += $v['allot_num'];
                $batch_goods_list[$spec_sn]['diff_num'] += $v['diff_num'];
            } else {
                $goods_label = explode(',', $v['goods_label']);
                $tmp_goods_label = [];
                foreach ($goods_label_info as $k1 => $v1) {
                    $label_id = intval($v1['id']);
                    if (in_array($label_id, $goods_label)) {
                        $tmp_goods_label[] = $v1;
                    }
                }
                $v['goods_label_list'] = $tmp_goods_label;
                $batch_goods_list[$spec_sn] = $v;
            }
        }
        $batch_goods_list = array_values($batch_goods_list);
        return $batch_goods_list;
    }

    /**
     * description:获取采购批次详情
     * editor:zongxing
     * type:POST
     * * params: 1.实采批次单号:real_purchase_sn;
     * date : 2018.07.10
     * return Object
     */
    public function getPurchaseGoodsDetail($purchase_info)
    {
        $purchase_sn = $purchase_info["purchase_sn"];
        $where[] = ['purchase_sn', '=', $purchase_sn];
        $fields = ['rpd.goods_name', 'rpd.erp_prd_no', 'rpd.erp_merchant_no', 'rpd.spec_sn', 'day_buy_num', 'allot_num',
            'diff_num', 'remark', 'purchase_remark', 'goods_label'];
        $batch_goods_obj = DB::table('real_purchase_detail as rpd')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpd.spec_sn')
            ->select($fields)->where($where);
        if (isset($purchase_info['query_sn'])) {
            $query_sn = trim($purchase_info['query_sn']);
            $query_sn = '%' . $query_sn . '%';
            $batch_goods_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('rpd.goods_name', 'LIKE', $query_sn)
                    ->orWhere('rpd.spec_sn', 'LIKE', $query_sn)
                    ->orWhere('rpd.erp_prd_no', 'LIKE', $query_sn)
                    ->orWhere('rpd.erp_merchant_no', 'LIKE', $query_sn);
            });
        }
        $batch_goods_detail = $batch_goods_obj->get();
        $batch_goods_detail = objectToArrayZ($batch_goods_detail);

        //获取采购期商品统计表数据
        $purchase_goods_detail = DB::table('demand_count')->where($where)->pluck('goods_num', 'spec_sn');
        $purchase_goods_detail = objectToArrayZ($purchase_goods_detail);
        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();

        $batch_goods_list = [];
        foreach ($batch_goods_detail as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $day_buy_num = $v['day_buy_num'];
            $allot_num = $v['allot_num'];
            $diff_num = $v['diff_num'];
            $goods_label = explode(',', $v['goods_label']);
            $tmp_goods_label = [];
            foreach ($goods_label_info as $k1 => $v1) {
                $label_id = intval($v1['id']);
                if (in_array($label_id, $goods_label)) {
                    $tmp_goods_label[] = $v1;
                }
            }
            $goods_num = 0;
            if (isset($purchase_goods_detail[$spec_sn])) {
                $goods_num = intval($purchase_goods_detail[$spec_sn]);
            }
            if (!isset($batch_goods_list[$spec_sn])) {
                $batch_goods_list[$spec_sn]['goods_name'] = $v['goods_name'];
                $batch_goods_list[$spec_sn]['erp_prd_no'] = $v['erp_prd_no'];
                $batch_goods_list[$spec_sn]['erp_merchant_no'] = $v['erp_merchant_no'];
                $batch_goods_list[$spec_sn]['spec_sn'] = $v['spec_sn'];
                $batch_goods_list[$spec_sn]['goods_num'] = $goods_num;
                $batch_goods_list[$spec_sn]['day_buy_num'] = 0;
                $batch_goods_list[$spec_sn]['allot_num'] = 0;
                $batch_goods_list[$spec_sn]['diff_num'] = 0;
                $batch_goods_list[$spec_sn]['goods_label_list'] = $tmp_goods_label;
            } else {
                $batch_goods_list[$spec_sn]['day_buy_num'] += $day_buy_num;
                $batch_goods_list[$spec_sn]['allot_num'] += $allot_num;
                $batch_goods_list[$spec_sn]['diff_num'] += $diff_num;
            }
        }

        $batch_goods_list = array_values($batch_goods_list);
        return $batch_goods_list;
    }

//    public function getPurchaseGoodsDetail($param_info)
//    {
//        $purchase_sn = $param_info["purchase_sn"];
//        $where[] = ['purchase_sn', '=', $purchase_sn];
//        $fields = ['goods_name', 'erp_prd_no', 'erp_merchant_no', 'spec_sn','goods_num','may_buy_num','real_buy_num'];
//        $purchase_goods_obj = DB::table('demand_count as dc')->select($fields)->where($where);
//
//        if (isset($param_info['query_sn'])) {
//            $query_sn = trim($param_info['query_sn']);
//            $query_sn = '%' . $query_sn . '%';
//            $purchase_goods_obj->where(function ($query) use ($query_sn) {
//                $query->orWhere('goods_name', 'LIKE', $query_sn)
//                    ->orWhere('spec_sn', 'LIKE', $query_sn)
//                    ->orWhere('erp_prd_no', 'LIKE', $query_sn)
//                    ->orWhere('erp_merchant_no', 'LIKE', $query_sn);
//            });
//        }
////        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
////        $purchase_goods_detail = $purchase_goods_obj->paginate($page_size);
//        $purchase_goods_detail = $purchase_goods_obj->get()->groupBy('spec_sn');
//        $purchase_goods_detail = objectToArrayZ($purchase_goods_detail);
//
//        $goods_spec_sn = array_keys($purchase_goods_detail);
//        $fields = ['rpd.spec_sn', 'rpd.day_buy_num', 'rpd.allot_num',
//            'rpd.diff_num', 'rpd.remark', 'rpd.purchase_remark', 'gs.goods_label'];
//        $batch_goods_info = DB::table('real_purchase_detail as rpd')
//            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpd.spec_sn')
//            ->where($where)->whereIn('rpd.spec_sn',$goods_spec_sn)->get($fields);
//        $batch_goods_info = objectToArrayZ($batch_goods_info);
//
//        //获取商品标签列表
//        $goods_label_model = new GoodsLabelModel();
//        $goods_label_info = $goods_label_model->getAllGoodsLabelList();
//
//        $batch_goods_list = [];
//        foreach ($batch_goods_info as $k => $v) {
//            $spec_sn = $v['spec_sn'];
//            $day_buy_num = $v['day_buy_num'];
//            $allot_num = $v['allot_num'];
//            $diff_num = $v['diff_num'];
//            $goods_label = explode(',', $v['goods_label']);
//            $tmp_goods_label = [];
//            foreach ($goods_label_info as $k1 => $v1) {
//                $label_id = intval($v1['id']);
//                if (in_array($label_id, $goods_label)) {
//                    $tmp_goods_label[] = $v1;
//                }
//            }
//            $goods_num = 0;
//            if (isset($purchase_goods_detail[$spec_sn])) {
//                $goods_num = intval($purchase_goods_detail[$spec_sn]);
//            }
//            if (!isset($batch_goods_list[$spec_sn])) {
//                $batch_goods_list[$spec_sn]['goods_name'] = $v['goods_name'];
//                $batch_goods_list[$spec_sn]['erp_prd_no'] = $v['erp_prd_no'];
//                $batch_goods_list[$spec_sn]['erp_merchant_no'] = $v['erp_merchant_no'];
//                $batch_goods_list[$spec_sn]['spec_sn'] = $v['spec_sn'];
//                $batch_goods_list[$spec_sn]['goods_num'] = $goods_num;
//                $batch_goods_list[$spec_sn]['day_buy_num'] = 0;
//                $batch_goods_list[$spec_sn]['allot_num'] = 0;
//                $batch_goods_list[$spec_sn]['diff_num'] = 0;
//                $batch_goods_list[$spec_sn]['goods_label_list'] = $tmp_goods_label;
//            } else {
//                $batch_goods_list[$spec_sn]['day_buy_num'] += $day_buy_num;
//                $batch_goods_list[$spec_sn]['allot_num'] += $allot_num;
//                $batch_goods_list[$spec_sn]['diff_num'] += $diff_num;
//            }
//        }
//
//        $batch_goods_list = array_values($batch_goods_list);
//        return $batch_goods_list;
//    }
//
//        dd($purchase_goods_detail,$batch_goods_info);
//        return $purchase_goods_detail;
//    }

    /**
     * description:组装采购批次详情搜索条件
     * editor:zongxing
     * date : 2018.07.20
     * return String
     */
//    public function createPurGoodsDetail_stop($purchase_info)
//    {
//        $purchase_sn = $purchase_info["purchase_sn"];
//
//        $sql_pur_goods_detail = "SELECT goods_name,erp_prd_no,erp_merchant_no,spec_sn,sum(day_buy_num) as total_buy_num,
//                allot_num,diff_num,remark,purchase_remark,day_buy_num
//                FROM jms_real_purchase_detail WHERE purchase_sn = '" . $purchase_sn . "'";
//        if (isset($purchase_info['query_sn'])) {
//            $query_sn = trim($purchase_info['query_sn']);
//            if (preg_match("/[\x7f-\xff]/", $query_sn)) {
//                $query_sn = explode(" ", $query_sn);
//                foreach ($query_sn as $k => $v) {
//                    $sql_pur_goods_detail .= "AND goods_name LIKE '%" . $v . "%' ";
//                }
//            } else {
//                $query_sn = "%" . $query_sn . "%";
//                $sql_pur_goods_detail .= "AND (spec_sn LIKE '" . $query_sn . "'
//                            OR erp_prd_no LIKE '" . $query_sn . "' OR erp_merchant_no LIKE '" . $query_sn . "')";
//            }
//        }
//        $sql_pur_goods_detail .= "GROUP BY spec_sn";
//        dd($sql_pur_goods_detail);
//        return $sql_pur_goods_detail;
//    }

    /**
     * description:部门分货时，如果按比例分货与可分配数不一致，更新批次详情表共销仓数据
     * editor:zongxing
     * date : 2018.11.27
     * return String
     */
    public function updateRealPurDetailPublic($purchase_sn, $real_purchase_sn)
    {
        //获取商品按比例分货和可分货总量的数据
        $where = [
            ["purchase_sn", "=", $purchase_sn],
            ["real_pur_sn", "=", $real_purchase_sn]
        ];
        $depart_sort_goods = DB::table("depart_sort_goods")
            ->select(
                "spec_sn", "may_sort_num",
                DB::Raw("sum(ratio_num) as ratio_total_num")
            )
            ->where($where)->groupBy("spec_sn")->get();
        $depart_sort_goods = objectToArrayZ($depart_sort_goods);

        //对比比例分货和分货总量,如果数量不一致,则在批次详情表标记差异的值到“共销仓”
        $update_real_sql = "UPDATE jms_real_purchase_detail SET public_num = CASE spec_sn";
        $spec_sn_arr = [];
        foreach ($depart_sort_goods as $k => $v) {
            if (intval($v["may_sort_num"]) > intval($v["ratio_total_num"])) {
                $diff_num = intval($v["may_sort_num"]) - intval($v["ratio_total_num"]);
                $spec_sn = $v["spec_sn"];
                $update_real_sql .= " WHEN $spec_sn THEN '$diff_num'";
                $spec_sn_arr[] = $spec_sn;
            }
        }

        $update_res = true;
        if (!empty($spec_sn_arr)) {
            $spec_sn_str = implode(',', array_values($spec_sn_arr));
            $update_real_sql .= " END ";
            $update_real_sql .= " WHERE purchase_sn = '" . $purchase_sn . "' AND real_purchase_sn = '" . $real_purchase_sn . "' AND spec_sn IN ($spec_sn_str)";
            $update_res = DB::update(DB::raw($update_real_sql));
        }
        return $update_res;
    }

    /**
     * description:针对此次手动调整的值,同步更新批次详情表中“共销仓”的数据
     * editor:zongxing
     * date : 2018.11.27
     * return String
     */
    public function updatePublicByHandle($purchase_sn, $real_purchase_sn, $spec_sn, $depart_id, $handle_num)
    {
        //获取某个部门在一个采购期下对应的批次单中某个商品的分货信息
        $where = [
            ['dsg.purchase_sn', $purchase_sn],
            ['real_pur_sn', $real_purchase_sn],
            ['dsg.spec_sn', $spec_sn],
            ['depart_id', $depart_id],
        ];
        $handle_public_info = DB::table('depart_sort_goods as dsg')
            ->leftJoin("real_purchase_detail as rpd", function ($leftJoin) {
                $leftJoin->on("rpd.purchase_sn", "=", "dsg.purchase_sn");
                $leftJoin->on("rpd.real_purchase_sn", "=", "dsg.real_pur_sn");
                $leftJoin->on("rpd.goods_name", "=", "dsg.goods_name");
                $leftJoin->on("rpd.spec_sn", "=", "dsg.spec_sn");
            })
            ->where($where)->first(["handle_num", "public_num"]);
        $handle_public_info = objectToArrayZ($handle_public_info);
        //更新批次详情表中“共销仓”中商品的数据
        $update_public = [
            ['purchase_sn', $purchase_sn],
            ['real_purchase_sn', $real_purchase_sn],
            ['spec_sn', $spec_sn],
        ];
        $before_handle_num = intval($handle_public_info["handle_num"]);
        $before_public_num = intval($handle_public_info["public_num"]);
        if ($before_handle_num > $handle_num) {
            $diff_num = $before_handle_num - $handle_num;
            $public_num = $before_public_num + $diff_num;
            $update_public_num = ["public_num" => $public_num];
        } else {
            $diff_num = $handle_num - $before_handle_num;
            $public_num = $before_public_num - $diff_num;
            $update_public_num = ["public_num" => $public_num];
        }
        $update_public_res = DB::table("real_purchase_detail")->where($update_public)->update($update_public_num);
        return $update_public_res;
    }

    /**
     * description:获取YD订单下需求单已预采的商品
     * editor:zongxing
     * date : 2018.12.15
     * return Array
     */
    public function getPredictGoods($sub_order_sn)
    {
        $fileds = ['rpd.spec_sn', 'rpd.day_buy_num'];
        $where = [
            ['sub_order_sn', $sub_order_sn]
        ];
        $predict_goods_info = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin('demand as d', "d.demand_sn", "=", "rp.demand_sn")
            ->where($where)->get($fileds);
        $predict_goods_info = objectToArrayZ($predict_goods_info);
        return $predict_goods_info;
    }

    /**
     * description:获取预采中需求单的已采资金
     * editor:zongxing
     * date : 2018.12.17
     * @return Array
     */
    public function getFundOfRealPredict()
    {
        $where = [
            ['rp.batch_cat', 2]
        ];
        $real_purchase_goods_info = DB::table("real_purchase_detail as rpd")
            ->select("rp.demand_sn",
                DB::raw("SUM(jms_rpd.day_buy_num * jms_gs.spec_price) as total_price")
            )
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "rpd.spec_sn")
            ->where($where)
            ->groupBy("rp.demand_sn")
            ->get()
            ->groupBy("demand_sn");
        $real_purchase_goods_info = objectToArrayZ($real_purchase_goods_info);
        return $real_purchase_goods_info;
    }

    /**
     * description:获取实采单详情
     * editor:zhangdong
     * date : 2018.12.19
     * @return
     */
    public function getRealPruDetail($real_purchase_sn, $spec_sn = '')
    {
        $field = [
            'rpd.real_purchase_sn', 'rpd.spec_sn', 'rpd.goods_name',
            'rpd.day_buy_num', 'rpd.allot_num', 'rpd.diff_num',
            'rpd.real_allot_num',
        ];
        $where[] = ['rpd.real_purchase_sn', $real_purchase_sn];
        if (!empty($spec_sn)) {
            $where[] = ['rpd.spec_sn', $spec_sn];
        }
        $queryRes = DB::table('real_purchase_detail as rpd')->select($field)
            ->where($where)->get();
        return $queryRes;

    }

    /**
     * description:获取YD订单下需求单已预采的商品
     * editor:zongxing
     * date : 2018.12.15
     * return Array
     */
    public function checkRealPurchaseGoods($group_sn)
    {
        $fileds = ['rpd.spec_sn', 'gs.spec_weight'];
        $where = [
            ['rp.group_sn', $group_sn]
        ];
        $real_purchase_goods_info = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin('goods_spec as gs', "gs.spec_sn", "=", "rpd.spec_sn")
            ->where($where)->get($fileds);
        $real_purchase_goods_info = objectToArrayZ($real_purchase_goods_info);

        $error_msg = '您清点的商品中,规格码为:';
        foreach ($real_purchase_goods_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $spec_weight = $v['spec_weight'];
            if ($spec_weight == '0.0000') {
                $error_msg .= $spec_sn . ',';
            }
        }

        $return_info = '';
        if ($error_msg != '您清点的商品中,规格码为:') {
            $error_msg = substr($error_msg, 0, -1);
            $error_msg .= '的商品,商品规格重量信息缺失,请完善';
            $return_info = ['code' => '1000', 'msg' => $error_msg];
        }
        return $return_info;
    }

    /**
     * description:更新批次商品的成本价
     * editor:zongxing
     * date : 2019.01.03
     * return Array
     */
    public function updateBatchCostPrice($upload_goods_info, $param_info)
    {
        $purchase_sn = $param_info["purchase_sn"];
        $group_sn = $param_info["group_sn"];
        $is_mother = $param_info["is_mother"];
        $where = [
            'purchase_sn' => $purchase_sn,
            'group_sn' => $group_sn,
            'is_mother' => $is_mother
        ];
        $batch_sn_arr = DB::table('real_purchase')->where($where)->pluck('real_purchase_sn');
        $batch_sn_arr = objectToArrayZ($batch_sn_arr);

        $updateRealPurchaseGoods = [];
        foreach ($upload_goods_info as $k => $v) {
            $spec_sn = $k;
            $cost_amount = $v;
            $updateRealPurchaseGoods['cost_amount'][] = [
                $spec_sn => $cost_amount
            ];
        }

        if (empty($updateRealPurchaseGoods)) {
            return false;
        }

        $updateRes = DB::transaction(function () use ($upload_goods_info, $updateRealPurchaseGoods, $batch_sn_arr) {
            foreach ($batch_sn_arr as $k => $v) {
                //更新条件
                $where = [
                    'real_purchase_sn' => $v
                ];
                //需要判断的字段
                $column = 'spec_sn';
                $updateDemandCountSql = makeBatchUpdateSql('jms_real_purchase_detail', $updateRealPurchaseGoods, $column, $where);
                $return_info = DB::update(DB::raw($updateDemandCountSql));
            }
            return $return_info;
        });
        return $updateRes;
    }

    /**
     * description:获取批次统计列表
     * editor:zongxing
     * date : 2019.01.03
     * return Array
     */
    public function getBatchStatisticsList($where, $bool = false)
    {
        $real_purchase_obj = DB::table("real_purchase_detail as rpd")
            ->select("rpd.real_purchase_sn", 'rp.demand_sn', "path_way", "method_name", "channels_name", "delivery_time",
                "arrive_time", "status", "batch_cat", 'group_sn',
                DB::raw("count(jms_rpd.spec_sn) as sku_num"),
                DB::raw("sum(jms_rpd.day_buy_num) as day_buy_num"),
                DB::raw("sum(jms_rpd.day_buy_num * jms_gs.spec_price * jms_rpd.channel_discount) as real_total_price")
            )
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
            ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "rpd.spec_sn")
            ->where($where)
            ->groupBy("real_purchase_sn");
        if ($bool) {
            $real_purchase_obj->leftJoin("purchase_demand as pd", "pd.purchase_sn", "=", "rp.purchase_sn");
        }
        $real_purchase_detail = $real_purchase_obj->get();
        $real_purchase_detail = objectToArrayZ($real_purchase_detail);
        return $real_purchase_detail;
    }

    /**
     * description:获取批次统计列表
     * editor:zongxing
     * date : 2019.01.03
     * return Array
     */
    public function getBatchPredictInfo($purchase_sn_arr)
    {
        $field = [
            'rp.real_purchase_sn', 'rp.purchase_sn', 'rp.demand_sn', 'rpd.spec_sn', 'rpd.day_buy_num',
            //DB::raw('SUM(jms_rpd.day_buy_num) as predict_goods_num')
        ];
        $predict_purchase_detail = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->where('batch_cat', 2)
            ->whereIn('rp.purchase_sn', $purchase_sn_arr)->groupBy('rp.demand_sn')->get($field)->groupBy('purchase_sn');
        $predict_purchase_detail = objectToArrayZ($predict_purchase_detail);
        return $predict_purchase_detail;
    }

    /**
     * description:获取批次统计列表
     * editor:zongxing
     * date : 2019.01.03
     * return Array
     */
    public function getDemandPredictInfo($purchase_sn_arr = [], $demand_sn, $group_by = '')
    {
        $field = [
            'rp.real_purchase_sn', 'rp.purchase_sn', 'rp.demand_sn',
            DB::raw('SUM(jms_rpd.allot_num) as predict_goods_num')//这里使用清点数量作为实际数量
        ];
        $predict_purchase_obj = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->where('batch_cat', 2);
        if (!empty($purchase_sn_arr)) {
            $predict_purchase_obj->whereIn('rp.purchase_sn', $purchase_sn_arr);
        }

        if (!empty($group_by)) {
            $predict_purchase_detail = $predict_purchase_obj->groupBy($group_by)
                ->whereIn('rp.demand_sn', $demand_sn)->get($field)->groupBy($group_by);
        } else {
            $predict_purchase_detail = $predict_purchase_obj->groupBy('rp.demand_sn')
                ->whereIn('rp.demand_sn', $demand_sn)->get($field);
        }

        $predict_purchase_detail = objectToArrayZ($predict_purchase_detail);
        return $predict_purchase_detail;
    }

    /**
     * description:获取批次商品统计列表
     * editor:zongxing
     * date : 2019.01.18
     * return Array
     */
    public function getBatchPredictGoodsInfo($purchase_sn, $spec_sn = [])
    {
        $field = [
            'rp.real_purchase_sn', 'rp.demand_sn', 'rp.purchase_sn', 'rpd.spec_sn',
            DB::raw('SUM(jms_rpd.day_buy_num) as predict_goods_num')
        ];

        $predict_purchase_obj = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->where('batch_cat', 2)
            ->whereIn('rp.purchase_sn', $purchase_sn);
        if (!empty($spec_sn)) {
            $predict_purchase_obj->whereIn('rpd.spec_sn', $spec_sn);
        }
        $predict_purchase_detail = $predict_purchase_obj->groupBy('rpd.spec_sn')->get($field);
        $predict_purchase_detail = objectToArrayZ($predict_purchase_detail);
        return $predict_purchase_detail;
    }

    /**
     * description:提交批次核价
     * editor:zongxing
     * date : 2019.01.21
     * return Array
     */
    public function changeBatchCost($param_info, $rp_info)
    {
        $purchase_sn = trim($rp_info['purchase_sn']);
        $real_purchase_sn = trim($rp_info['real_purchase_sn']);
        $delivery_time = trim($rp_info['delivery_time']);
        $arrive_time = trim($rp_info['arrive_time']);
        $path_way = intval($rp_info['path_way']);
        $group_sn = intval($rp_info['group_sn']);
        $method_id = intval($rp_info['method_id']);
        $channels_id = intval($rp_info['channels_id']);
        $where = [
            ['er.day_time', $delivery_time],
            ['er.method_id', $method_id],
            ['er.channels_id', $channels_id],
        ];
        $usa_rate = DB::table('exchange_rate as er')->where($where)->first(['usa_rate']);
        $usa_rate = objectToArrayZ($usa_rate);
        if (empty($usa_rate)) {
            return response()->json(['code' => '1101', 'msg' => '批次提货日无对应的美金汇率,请先维护']);
        }
        //获取批次下面子批次对应的各个美金原价
        $where = [['rpa.group_sn', $group_sn]];
        //如果是自提
        if ($path_way == 0) {
            $where = [
                ['rpa.purchase_sn', $purchase_sn],
                ['rpa.delivery_time', $delivery_time],
                ['rpa.arrive_time', $arrive_time],
                ['rpa.path_way', $path_way],
                ['rpa.method_id', '!=', '36'],
            ];
        }
        $field = [
            'rpda.id', 'rpda.real_purchase_sn', 'rpda.spec_sn', 'rpda.spec_price', 'rpda.channel_discount',
            'rpda.day_buy_num',
            DB::raw('(jms_rpda.spec_price * jms_rpda.channel_discount * jms_er.usa_rate) as cost_amount'),
            DB::raw('(CASE
                    WHEN jms_gs.spec_weight != 0 THEN
                        jms_gs.spec_weight
                    WHEN jms_gs.estimate_weight != 0 THEN
                        jms_gs.estimate_weight
                    ELSE
                        0.00
                    END) as spec_weight')
        ];
        $rpa_detail = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('exchange_rate as er', function ($join) {
                $join->on('er.day_time', '=', 'rpa.delivery_time');
                $join->on('er.method_id', '=', 'rpa.method_id');
                $join->on('er.channels_id', '=', 'rpa.channels_id');
            })
            ->where($where)->get($field)->groupBy('spec_sn');
        $rpa_detail = objectToArrayZ($rpa_detail);
        //计算子批次商品总重
        $total_weight = 0;
        foreach ($rpa_detail as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $day_buy_num = intval($v1['day_buy_num']);
                $spec_weight = floatval($v1['spec_weight']);
                $total_weight += floatval($day_buy_num * $spec_weight);
            }
        }
        //子批次批次商品成本价增加运费计算
        $post_amount = floatval($param_info['post_amount']);
        $per_post = $post_amount / $total_weight;
        foreach ($rpa_detail as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $spec_weight = floatval($v1['spec_weight']);
                $rpa_detail[$k][$k1]['cost_amount'] += floatval($spec_weight * $per_post);
            }
        }
        //获取批次商品信息
        $rp_detail = DB::table('real_purchase_detail as rpd')->where('rpd.real_purchase_sn', $real_purchase_sn)
            ->pluck('id', 'spec_sn');
        $rp_detail = objectToArrayZ($rp_detail);
        //批次商品核价
        $updateBatchGoods = [];
        foreach ($rp_detail as $k => $v) {
            if (!isset($rpa_detail[$k])) continue;
            $rp_goods_info = $rpa_detail[$k];
            $total_cost = 0;
            $total_num = 0;
            foreach ($rp_goods_info as $m => $n) {
                $day_buy_num = floatval($n['day_buy_num']);
                $cost_amount = floatval($n['cost_amount']) * $day_buy_num;
                $total_cost += $cost_amount;
                $total_num += $day_buy_num;
            }
            $id = intval($v);
            $cost_amount = $total_cost / $total_num;
            $updateBatchGoods['cost_amount'][] = [
                $id => floatval($cost_amount)
            ];
        }
        //组装核价sql
        $column = 'id';
        $updateBatchGoodsSql = '';
        if (!empty($updateBatchGoods)) {
            $updateBatchGoodsSql = makeBatchUpdateSql('jms_real_purchase_detail', $updateBatchGoods, $column);
        }
        $updateRes = DB::transaction(function () use ($updateBatchGoodsSql) {
            $update_res = DB::update(DB::raw($updateBatchGoodsSql));
            return $update_res;
        });
        return $updateRes;
    }

    /**
     * description:财务模块-需求资金管理-通过需求单号获取预采商品信息
     * editor:zongxing
     * date : 2019.01.22
     * return Array
     */
    public function getPredictByDemand($demand_sn_arr)
    {
        $field = [
            'rp.purchase_sn', 'rp.real_purchase_sn', 'rp.demand_sn', 'rpd.spec_sn', 'rpd.day_buy_num', 'rp.method_id',
            'rp.channels_id',
        ];
        $predict_goods_info = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rp.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rp.channels_id')
            ->where('batch_cat', 2)
            ->whereIn('rp.demand_sn', $demand_sn_arr)->get($field);
        $predict_goods_info = objectToArrayZ($predict_goods_info);
        return $predict_goods_info;
    }

    /**
     * description:获取各个批次的详细信息
     * editor:zongxing
     * date : 2019.01.25
     * return Array
     */
    public function getPurchaseTotalDetail($where, $param_info)
    {
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $rp_sn_info = DB::table('real_purchase as rp')
            ->orderBy('rp.create_time', 'desc')
            ->distinct()
            ->skip($start_page)->take($page_size)->distinct()->pluck('purchase_sn');
        $rp_sn_info = objectToArrayZ($rp_sn_info);

        $fields = [
            'pd.id as purchase_id', 'rp.delivery_time', 'rp.purchase_sn', 'rp.real_purchase_sn', 'method_name', 'channels_name',
            'path_way', 'rp.delivery_time', 'rp.arrive_time', 'is_setting', 'group_sn', 'batch_cat', 'rp.status',
            'is_set_post', 'day_buy_num', 'port_id', 'is_mother', 'sum_demand_name',
            DB::raw('sum(day_buy_num) as total_buy_num')
        ];
        if (isset($param_info['status'])) {
            $status = intval($param_info['status']);
            $expireTime = $this->create_expire_time($status);
            if ($status == 1) {
                $add_field = [DB::raw('if(jms_rp.arrive_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 2) {
                //待确认差异
                $add_field = [DB::raw('if(jms_rp.allot_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 3) {
                //待开单
                $add_field = [DB::raw('if(jms_rp.diff_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 4) {
                //待入库
                $add_field = [DB::raw('if(jms_rp.billing_time > ' . $expireTime . ',0,1) expire_status')];
            }
        }
        if (!empty($add_field)) {
            $fields = array_merge($fields, $add_field);
        }
        $purchase_goods_info = DB::table('real_purchase_detail as rpd')
            ->select($fields)
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', "=", 'rpd.real_purchase_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', "=", 'rp.purchase_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'rp.purchase_sn')
            ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
            ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
            ->where($where)
            ->whereIn('rp.purchase_sn', $rp_sn_info)
            ->orderBy('rp.batch_cat', 'asc')
            ->orderBy('rp.create_time', 'desc')
            ->groupBy('rpd.real_purchase_sn')
            ->get()
            ->groupBy('purchase_sn');
        $purchase_goods_info = objectToArrayZ($purchase_goods_info);
        return $purchase_goods_info;
    }

    /**
     * description:创建过期对比时间
     * editor:zongxing
     * params: 1.实采批次单号:real_purchase_sn;2.要修改的状态:status;
     * date : 2018.07.16
     * return String
     */
    public function create_expire_time($status)
    {
        if ($status == 1) {
            $expireHour = config('constants.ALLOT_EXPIRE_TIME');
        } elseif ($status == 2) {
            $expireHour = config('constants.DIFF_EXPIRE_TIME');
        } elseif ($status == 3) {
            $expireHour = config('constants.BILLING_EXPIRE_TIME');
        } elseif ($status == 4) {
            $expireHour = config('constants.STORAGE_EXPIRE_TIME');
        } elseif ($status == 6) {
            $expireHour = config('constants.PRICEING_EXPIRE_TIME');
        }
        $curTime = Carbon::now();
        $expireTime = $curTime->addHour(-$expireHour);
        $expireTime = $expireTime->toDateString();
        return $expireTime;
    }

    /**
     * description:获取各个批次的详细信息
     * editor:zongxing
     * date : 2019.01.25
     * return Array
     */
    public function getBatchDetail($purchase_sn = [], $where = '', $param_info)
    {
        $fields = ["pd.id as purchase_id", "rpd.real_purchase_sn", "rp.purchase_sn", "method_name", "channels_name",
            "path_way", "rp.delivery_time", "rp.arrive_time", "is_setting", "group_sn", "batch_cat", 'rp.status',
            'is_set_post', 'day_buy_num', 'port_id', 'is_mother',
            DB::raw('SUM(jms_rpd.allot_num) as total_buy_num')];
        $add_field = [];
        if (isset($param_info['status'])) {
            $status = intval($param_info['status']);
            $expireTime = $this->create_expire_time($status);
            if ($status == 1) {
                $add_field = [DB::raw('if(jms_rp.arrive_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 2) {
                //待确认差异
                $add_field = [DB::raw('if(jms_rp.allot_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 3) {
                //待开单
                $add_field = [DB::raw('if(jms_rp.diff_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 4) {
                //待入库
                $add_field = [DB::raw('if(jms_rp.billing_time > ' . $expireTime . ',0,1) expire_status')];
            }
        }
        if (!empty($add_field)) {
            $fields = array_merge($fields, $add_field);
        }
        $real_goods_obj = DB::table("real_purchase_detail as rpd")
            ->select($fields)
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
            ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn");
        if (!empty($purchase_sn)) {
            $real_goods_obj->whereIn("rp.purchase_sn", $purchase_sn);
        }
        if (!empty($where)) {
            $real_goods_obj->where($where);
        }
        $real_goods_info = $real_goods_obj->orderBy('rp.batch_cat', 'asc')
            ->orderBy('rp.create_time', 'desc')
            ->groupBy("rp.real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $real_goods_info = objectToArrayZ($real_goods_info);
        return $real_goods_info;
    }

    /**
     * description:查询分货数据
     * editor:zhangdong
     * params:$query_type 1， 查询实时分货数据 2，查询最终分货数据
     * date : 2019.02.19
     */
    public function getCanSortNum($real_purchase_sn, $spec_sn)
    {
        $where = [
            ['real_purchase_sn', $real_purchase_sn],
            ['spec_sn', $spec_sn],
        ];
        $field = ['day_buy_num', 'allot_num'];
        $queryRes = DB::table($this->table)->select($field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:以商品为单位获取指定采购期的采购数据汇总
     * editor:zongxing
     * date : 2019.03.01
     * return Array
     */
    public function getBatchGoodsList($purchase_sn_info)
    {
        $field = [
            'rpd.spec_sn', 'rpd.purchase_sn', 'rpd.spec_sn',
            DB::raw('SUM(jms_rpd.allot_num) as total_allot_num')
        ];
        $batch_goods_info = DB::table('real_purchase_detail as rpd')
            ->whereIn('rpd.purchase_sn', $purchase_sn_info)
            ->groupBy('rpd.spec_sn')
            ->get($field)
            ->groupBy('spec_sn');
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        return $batch_goods_info;
    }


    /**
     * description:回滚可分货数据
     * author:zhangdong
     * date : 2019.03.06
     */
    public function rollbackSortNum($realPurchaseSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $update = [
            'sort_num' => DB::raw('day_buy_num'),
        ];
        $executeRes = DB::table($this->table)->where($where)->update($update);
        return $executeRes;
    }

    /**
     * description:采购期渠道统计列表
     * editor:zongxing
     * date : 2019.03.15
     * return Array
     */
    public function purchaseChannelStatisticsList($param_info)
    {
        $where = [];
        if (isset($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $where[] = ['pd.delivery_time', '>=', $start_time];
        }
        if (isset($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
            $where[] = ['pd.delivery_time', '<=', $end_time];
        }
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $channel_goods_list = DB::table("real_purchase_detail as rpd")
            ->select('method_name', 'channels_name', 'rp.method_id', 'rp.channels_id',
                DB::raw('COUNT(jms_rpd.spec_sn) as sku_num'),
                DB::raw('SUM(jms_rpd.allot_num) as total_allot_num'),
                DB::raw('SUM(jms_rpd.allot_num * jms_gs.spec_price) as total_price')
            )
            ->leftJoin('real_purchase as rp', "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
            ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "rpd.spec_sn")
            ->where($where)
            ->groupBy('rp.method_id', 'rp.channels_id')
            ->paginate($page_size);
        $channel_goods_list = objectToArrayZ($channel_goods_list);
        return $channel_goods_list;
    }

    /**
     * description:采购期渠道统计详情
     * editor:zongxing
     * date : 2019.03.15
     * return Array
     */
    public function purchaseChannelStatisticsDetail($param_info)
    {
        $where = [];
        if (isset($param_info['method_id'])) {
            $method_id = trim($param_info['method_id']);
            $where[] = ['rp.method_id', '=', $method_id];
        }
        if (isset($param_info['channels_id'])) {
            $channels_id = trim($param_info['channels_id']);
            $where[] = ['rp.channels_id', '=', $channels_id];
        }
        $channel_goods_info = DB::table('real_purchase_detail as rpd')
            ->select('g.goods_name', 'gs.spec_sn', 'gs.erp_prd_no', 'gs.erp_merchant_no', 'gs.goods_label', 'gs.spec_price',
                DB::raw('SUM(jms_rpd.allot_num) as total_allot_num')
            )
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpd.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->where($where)
            ->groupBy('gs.spec_sn')
            ->get();
        $channel_goods_info = objectToArrayZ($channel_goods_info);

        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();

        foreach ($channel_goods_info as $k => $v) {
            $goods_label = explode(',', $v['goods_label']);
            $tmp_goods_label = [];
            foreach ($goods_label_info as $k1 => $v1) {
                $label_id = intval($v1['id']);
                if (in_array($label_id, $goods_label)) {
                    $tmp_goods_label[] = $v1;
                }
            }
            $channel_goods_info[$k]['goods_label_list'] = $tmp_goods_label;
        }
        return $channel_goods_info;
    }

    /**
     * description:采购期渠道商品信息
     * editor:zongxing
     * date : 2019.04.09
     * return Array
     */
    public function purchaseChannelGoodsInfo($param_info)
    {
        //计算时间段
        $time_option = $this->createChannelTimeInfo($param_info);
        $where = [];
        if (isset($time_option['where'])) {
            $where = $time_option['where'];
        }

        //当月创建渠道商品
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $channel_create_goods_info = $rpda_model->getBatchAuditDetailInfo($param_info, $where);
        if (empty($channel_create_goods_info)) {
            return $channel_create_goods_info;
        }

        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $total_goods_num = COUNT($channel_create_goods_info);
        $channel_goods_list = array_slice($channel_create_goods_info, $start_page, $page_size);

        $channel_goods_spec = array_keys($channel_goods_list);
        if (isset($time_option['start_time'])) {
            $start_time = $time_option['start_time'];
            $where[] = ['rpa.delivery_time', '>=', $start_time];
        }
        if (isset($time_option['end_time'])) {
            $end_time = $time_option['end_time'];
            $where[] = ['rpa.delivery_time', '<=', $end_time];
        }
        //当月提货渠道商品
        $channel_deliver_goods_info = $rpda_model->getBatchAuditDetailInfo($param_info, $where, $channel_goods_spec);;
        $channel_deliver_goods_info = objectToArrayZ($channel_deliver_goods_info);

        $channel_goods_total_list = [];
        $channel_total_list = [];
        //创建时间差额百分比
        foreach ($channel_goods_list as $k => $v) {
            $tmp_goods_list = [
                'goods_name' => $v[0]['goods_name'],
                'spec_sn' => $v[0]['spec_sn'],
                'erp_prd_no' => $v[0]['erp_prd_no'],
                'erp_merchant_no' => $v[0]['erp_merchant_no'],
                'erp_ref_no' => $v[0]['erp_ref_no'],
                'goods_label' => $v[0]['goods_label'],
                'spec_price' => $v[0]['spec_price'],
            ];
            foreach ($v as $k1 => $v1) {
                //收集渠道信息
                $channels_name = $v1['channels_name'];
                $method_name = $v1['method_name'];
                $day_buy_num = intval($v1['day_buy_num']);
                $pin_str = $channels_name . '-' . $method_name;
                $create_total_price = floatval($v1['create_total_price']);//实采渠道创建总金额
                $quote_total_price = floatval($v1['quote_total_price']);//实采渠道500万总金额
                $cq_diff_price = $create_total_price - $quote_total_price;//创建差异总金额

                if (isset($channel_total_list[$pin_str])) {
                    $channel_total_list[$pin_str]['channel_name'] = $pin_str;//渠道名称
                    $channel_total_list[$pin_str]['channel_real_num'] += $day_buy_num;//渠道实采数
                    $channel_total_list[$pin_str]['cc_total_price'] += $create_total_price;//渠道创建总金额
                    $channel_total_list[$pin_str]['ccq_total_price'] += $quote_total_price;//渠道500万总金额
                } else {
                    $channel_total_list[$pin_str]['channel_name'] = $pin_str;
                    $channel_total_list[$pin_str]['channel_real_num'] = $day_buy_num;
                    $channel_total_list[$pin_str]['cc_total_price'] = $create_total_price;
                    $channel_total_list[$pin_str]['ccq_total_price'] = $quote_total_price;
                    $channel_total_list[$pin_str]['cd_total_price'] = 0;//渠道提货总金额
                    $channel_total_list[$pin_str]['cdq_total_price'] = 0;//渠道提货500万总金额
                }

                $create_diff_rate = number_format($cq_diff_price / $quote_total_price * 100, 2);
                $tmp_goods_list[$pin_str] = [
                    'day_buy_num' => intval($v1['day_buy_num']),
                    'create_total_price' => number_format($create_total_price, 2),
                    'cq_diff_price' => number_format($cq_diff_price, 2),
                    'create_diff_rate' => $create_diff_rate,
                    'deliver_diff_rate' => 0,
                    'deliver_total_price' => 0
                ];
            }
            $channel_goods_total_list[$k] = $tmp_goods_list;
        }

        //提货时间差额百分比
        foreach ($channel_deliver_goods_info as $k => $v) {
            if (isset($channel_goods_total_list[$k])) {
                foreach ($v as $k1 => $v1) {
                    //收集渠道信息
                    $channels_name = $v1['channels_name'];
                    $method_name = $v1['method_name'];
                    $pin_str = $channels_name . '-' . $method_name;
                    $deliver_total_price = floatval($v1['deliver_total_price']);//实采渠道提货总金额
                    $quote_total_price = floatval($v1['quote_total_price']);//实采渠道500万总金额
                    $dq_diff_price = $deliver_total_price - $quote_total_price;//提货总金额-500万总金额

                    if (isset($channel_goods_total_list[$k][$pin_str])) {
                        $deliver_diff_rate = number_format($dq_diff_price / $quote_total_price * 100, 2);
                        $channel_goods_total_list[$k][$pin_str]['deliver_diff_rate'] = $deliver_diff_rate;
                        $channel_goods_total_list[$k][$pin_str]['deliver_total_price'] = number_format($deliver_total_price, 2);
                        $channel_goods_total_list[$k][$pin_str]['dq_diff_price'] = number_format($dq_diff_price, 2);//提货差异总金额
                    }

                    if (isset($channel_total_list[$pin_str])) {
                        $channel_total_list[$pin_str]['cd_total_price'] += $deliver_total_price;
                        $channel_total_list[$pin_str]['cdq_total_price'] += $quote_total_price;
                    }
                }
            }
        }

        $channel_total_list = array_values($channel_total_list);
        foreach ($channel_total_list as $k => $v) {
            $cc_total_price = $v['cc_total_price'];
            $ccq_total_price = $v['ccq_total_price'];
            $create_diff_rate = 0;
            $cq_diff_price = $cc_total_price - $ccq_total_price;
            if ($ccq_total_price) {
                $create_diff_rate = number_format($cq_diff_price / $ccq_total_price * 100, 2);
            }
            $channel_total_list[$k]['create_diff_rate'] = $create_diff_rate;
            $channel_total_list[$k]['cq_diff_price'] = number_format($cq_diff_price, 2);
            $cd_total_price = $v['cd_total_price'];
            $cdq_total_price = $v['cdq_total_price'];
            $deliver_diff_rate = 0;
            $dq_diff_price = $cd_total_price - $cdq_total_price;
            if ($cdq_total_price) {
                $deliver_diff_rate = number_format($dq_diff_price / $cdq_total_price * 100, 2);
            }
            $channel_total_list[$k]['deliver_diff_rate'] = $deliver_diff_rate;
            $channel_total_list[$k]['dq_diff_price'] = number_format($dq_diff_price, 2);
        }

        $return_info['channel_goods_total_list'] = array_values($channel_goods_total_list);
        $return_info['total_goods_num'] = $total_goods_num;
        $return_info['channel_total_list'] = $channel_total_list;
        return $return_info;
    }

    /**
     * description:计算搜索渠道商品的时间
     * editor:zongxing
     * date : 2019.04.09
     * return Array
     */
    public function createChannelTimeInfo($param_info)
    {
        $where = [];
        if (!empty($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $where[] = ['rpa.create_time', '>=', $start_time];

            if (!empty($param_info['end_time'])) {
                $end_time = trim($param_info['end_time']);
                $where[] = ['rpa.create_time', '<=', $end_time];
            }
        } elseif (!empty($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
            $where[] = ['rpa.create_time', '<=', $end_time];
        } elseif (!empty($param_info['month_time'])) {
            $month_time = trim($param_info['month_time']);
            $start_time = Carbon::parse($month_time)->toDateString();
            $end_time = Carbon::parse($month_time)->endOfMonth()->toDateString();
            $where[] = ['rpa.create_time', '>=', $start_time];
            $where[] = ['rpa.create_time', '<=', $end_time];
        } else {
            $start_time = Carbon::now()->startOfMonth()->toDateString();
            $end_time = Carbon::parse('+1 day')->toDateString();
            $where[] = ['rpa.create_time', '>=', $start_time];
            $where[] = ['rpa.create_time', '<=', $end_time];
        }
        if (isset($start_time)) {
            $return_info['start_time'] = $start_time;
        }
        if (isset($end_time)) {
            $return_info['end_time'] = $end_time;
        }
        if (isset($start_time)) {
            $return_info['where'] = $where;
        }
        return $return_info;
    }

    /**
     * description:获取各个批次的积分信息
     * editor:zongxing
     * date : 2019.04.24
     * return Array
     */
    public function getBatchIntegralInfo($param_info = [])
    {
        $field = [
            'rpda.purchase_sn', 'rpda.real_purchase_sn', 'pm.method_name', 'pc.channels_name',
            'rpa.path_way', 'rpa.delivery_time as rp_delivery_time', 'rpa.arrive_time as rp_arrive_time', 'rpa.batch_cat',
            'rpa.is_integral', 'rpa.port_id', 'rpda.spec_sn', 'rpa.integral_time',
            'rpa.create_time', 'rpa.channels_method_sn',
            DB::raw('SUM(jms_rpda.day_buy_num) as total_buy_num'),
            //待返积分 = 美金原价*商品数量*[(1-成本折扣) - (1-VIP折扣)]
            //DB::raw('SUM(jms_gs.spec_price * jms_rpda.day_buy_num * (jms_dt2.discount - jms_dt.discount)) as total_integral'),
            //待返积分 = 商品数量*[实付美金-美金原价*成本折扣]
            DB::raw('SUM(jms_rpda.day_buy_num * (jms_rpda.pay_price - jms_rpda.spec_price * jms_rpda.channel_discount)) as total_integral'),
        ];
        $rpd_obj = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn');
        if (isset($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $rpd_obj->where(function ($query) use ($start_time) {
                $query->where('rpa.integral_time', '>=', $start_time);
            });
        }
        if (isset($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
            $rpd_obj->where(function ($query) use ($end_time) {
                $query->where('rpa.integral_time', '<=', $end_time);
            });
        }
        if (isset($param_info['month'])) {
            $month = trim($param_info['month']);
            $start_time = Carbon::parse($month)->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::parse($month)->endOfMonth()->toDateTimeString();
            $rpd_obj->where(function ($query) use ($start_time, $end_time) {
                $query->where('rpa.integral_time', '>=', $start_time);
                $query->where('rpa.integral_time', '<=', $end_time);
            });
        }
        if (!isset($param_info['start_time']) && !isset($param_info['end_time']) && !isset($param_info['month'])) {
            $start_time = Carbon::now()->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::now()->endOfMonth()->toDateTimeString();
            $rpd_obj->where(function ($query) use ($start_time, $end_time) {
                $query->where('rpa.integral_time', '>=', $start_time);
                $query->where('rpa.integral_time', '<=', $end_time);
            });
        }
        $where = [
            'rpa.status' => 3,
            'rpa.is_integral' => 0,
        ];
        $batch_integral_info = $rpd_obj->orderBy('rpa.batch_cat', 'asc')
            ->orderBy('rpa.integral_time', 'asc')
            ->groupBy('rpda.real_purchase_sn')
            ->where($where)
            ->where('pm.method_property', '!=', 2)//外采不返积分
            ->where('rpa.method_id', 34)//线上返积分
            ->get($field);
        $batch_integral_info = objectToArrayZ($batch_integral_info);
        return $batch_integral_info;
    }

    /**
     * description:获取各个批次的积分信息
     * editor:zongxing
     * date : 2019.04.24
     * return Array
     */
    public function getBatchDetailIntegralInfo($param_info)
    {
        $field = [
            'rpda.goods_name', 'rpda.spec_sn', 'rpda.erp_merchant_no', 'rpda.erp_prd_no', 'gs.erp_ref_no', 'rpda.day_buy_num',
            'rpda.spec_price', 'rpda.pay_price', 'rpda.channel_discount as cost_discount',
            //待返积分 = 美金原价*商品数量*[(1-成本折扣) - (1-VIP折扣)]
            //DB::raw('ROUND(jms_gs.spec_price * jms_rpda.day_buy_num * (jms_dt2.discount - jms_dt.discount),2) as total_integral'),
            //待返积分 = 商品数量*[实付美金-美金原价*成本折扣]
            DB::raw('SUM(jms_rpda.day_buy_num * (jms_rpda.pay_price - jms_rpda.spec_price * jms_rpda.channel_discount)) as total_integral'),
        ];
        $rpd_obj = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->leftJoin('discount as d', function ($join) {
                $join->on('d.brand_id', '=', 'g.brand_id');
                $join->on('d.method_id', '=', 'rpa.method_id');
                $join->on('d.channels_id', '=', 'rpa.channels_id');
            });
        $real_purchase_sn = trim($param_info['real_purchase_sn']);
        $purchase_sn = trim($param_info['purchase_sn']);
        $where = [
            'rpa.real_purchase_sn' => $real_purchase_sn,
            'rpa.purchase_sn' => $purchase_sn,
        ];
        $batch_goods_info = $rpd_obj->where($where)
            ->groupBy('rpda.spec_sn')
            ->orderBy('rpda.create_time', 'desc')
            ->get($field);
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        return $batch_goods_info;
    }

    /**
     * description:查询批次单商品信息-全量
     * author:zhangdong
     * date : 2019.05.31
     */
    public function queryBatchGoods($realPurchaseSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:获取批次单商品信息
     * author:zhangdong
     * date : 2019.06.03
     */
    public function getBarchGoods($realPurchaseSn, $specSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
            ['spec_sn', $specSn],
        ];

        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description 修改可分货数量
     * author:zhangdong
     * date : 2019.06.03
     */
    public function modifySortNum($realPurchaseSn, $specSn, $num)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
            ['spec_sn', $specSn],
        ];
        $update = [
            'sort_num' => DB::raw('sort_num - ' . $num),
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:查询批次单商品信息 -- 规格码，可分货数量
     * author:zhangdong
     * date : 2019.06.04
     */
    public function queryGoodsSimple($realPurchaseSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $this->field = ['id', 'spec_sn', 'sort_num'];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description 通过批次单查询可分货总量-批次分货列表用来显示当前批次是否存在分货数
     * author zhangdong
     * date 2019.07.04
     */
    public function countSortNum($realSn)
    {
        $where = [
            ['real_purchase_sn', $realSn],
            ['sort_num', '>', 0],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }


}//end of class
