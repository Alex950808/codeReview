<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\Model\Vone\SaleUserGoodsModel;
use Maatwebsite\Excel\Classes\PHPExcel;

class DemandModel extends Model
{
    protected $table = 'demand as d';

    //可操作字段
    protected $fillable = ['demand_sn', 'department', 'expire_time', 'status'];

    protected $field = [
        'd.id', 'd.sub_order_sn', 'd.demand_sn', 'd.department', 'd.sale_user_id',
        'd.expire_time', 'd.status', 'd.is_mark', 'd.create_time'
    ];

    protected $is_mark = [
        '0' => '未标记',
        '1' => '已标记',
    ];

    protected $demand_type = [
        '1' => '正常订单',
        '2' => '延期订单',
    ];


    //修改laravel 自动更新
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'modify_time';


    /**
     * description:获取需求列表
     * editor:zongxing
     * date : 2018.09.21
     * return Object
     */
    public function getDemandList($request, $status)
    {
        $demand_info = $request->toArray();
        $page_size = isset($demand_info['page_size']) ? intval($demand_info['page_size']) : 15;

        $where = [];
        if ($status == 2) {
            $where = function ($query) {
                $query->orWhere('d.status', 1)
                    ->orWhere('d.status', 2);
            };
        } elseif ($status == 3) {
            $where[] = ["d.status", "=", 3];
        }

        $where2 = [];
        if (isset($demand_info['query_sn']) && !empty($demand_info['query_sn'])) {
            $query_sn = trim($demand_info['query_sn']);
            $where2[] = ['d.demand_sn', "=", "$query_sn"];
        }

        $demand_list = DB::table('demand_goods as dg')
            ->select('d.demand_sn', 'd.department', 'd.expire_time', 'd.arrive_store_time', 'd.status', 'su.user_name',
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(goods_num) as goods_num'),
                DB::raw('sum(allot_num) as allot_num'),
                DB::raw('DATE(jms_d.create_time) as create_time')
            )
            ->leftJoin('demand as d', 'd.demand_sn', "=", 'dg.demand_sn')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->where($where)
            ->where('mos.status', 3)
            ->where($where2)
            ->orderBy('d.create_time', 'DESC')
            ->groupBy('d.demand_sn')
            ->paginate($page_size);
        $demand_list = objectToArrayZ($demand_list);
        $depart_model = new DepartmentModel();
        $depart_list = $depart_model->departInfoList();
        foreach ($demand_list['data'] as $k => $v) {
            $demand_list['data'][$k]['depart_name'] = $depart_list[$v['department']];
        }
        return $demand_list;
    }

    /**
     * description:获取需求列表
     * editor:zongxing
     * date : 2019.05.22
     * return Object
     */
    public function purchaseDemandList_stop($param_info)
    {

        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $where = [];
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $where[] = ['d.demand_sn', '=', $query_sn];
        }
        $demand_list = DB::table('demand_goods as dg')
            ->select('d.demand_sn', 'd.department', 'd.expire_time', 'd.arrive_store_time', 'd.status', 'su.user_name',
                'mos.external_sn', 'mos.remark',
                DB::raw('count(jms_dg.spec_sn) as sku_num'),
                DB::raw('sum(goods_num) as goods_num'),
                DB::raw('sum(goods_num * spec_price * sale_discount) as total_sale_price'),
                DB::raw('sum(goods_num * spec_price) as total_purchase_price'),
                DB::raw('sum(allot_num) as allot_num'),
                DB::raw('DATE(jms_d.create_time) as create_time')
            )
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', "=", 'dg.spec_sn')
            ->leftJoin('demand as d', 'd.demand_sn', "=", 'dg.demand_sn')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', "=", 'd.sub_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->where($where)
            ->where('mos.status', 3)
            ->whereIn('d.status', [1, 7])
            ->where('goods_num', '>', 0)
            ->orderBy('d.expire_time', 'DESC')
            ->groupBy('d.demand_sn')
            ->paginate($page_size);
        $demand_list = objectToArrayZ($demand_list);
        $depart_model = new DepartmentModel();
        $depart_list = $depart_model->departInfoList();
        foreach ($demand_list['data'] as $k => $v) {
            $demand_list['data'][$k]['total_sale_price'] = number_format($v['total_sale_price'], 2);
            $demand_list['data'][$k]['total_purchase_price'] = number_format($v['total_purchase_price'], 2);
            $demand_list['data'][$k]['depart_name'] = $depart_list[$v['department']];
        }
        return $demand_list;
    }

    public function purchaseDemandList($param_info)
    {
        $where = [];
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $where[] = ['d.demand_sn', '=', $query_sn];
        }
        $demand_num = DB::table('demand as d')->where($where)->whereIn('d.status', [1, 7])->count();

        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $field = [
            'd.demand_sn', 'd.department', 'd.expire_time', 'd.create_time', 'd.status', 'su.user_name',
            'mos.external_sn',
            DB::raw('if(jms_d.arrive_store_time,jms_d.arrive_store_time,"") as arrive_store_time'),
            DB::raw('count(jms_dg.spec_sn) as sku_num'),
            DB::raw('sum(jms_dg.goods_num) as goods_num')
        ];
        //延期商品
        $demand_goods_info = DB::table('demand_goods as dg')
            ->select($field)
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', "=", 'd.sub_order_sn')
            ->where($where)
            ->whereIn('d.status', [1, 7])
            ->where('dg.is_postpone',
                DB::raw('(
                    CASE jms_d.status
                    WHEN 1 THEN
                        2
                    ELSE
                        1
                    END
                )'))
            ->orderBy('d.expire_time', 'DESC')
            ->groupBy('dg.demand_sn')
            ->skip($start_page)->take($page_size)
            ->get();
        $demand_goods_info = objectToArrayZ($demand_goods_info);

        $demand_sn_info = [];
        foreach ($demand_goods_info as $k => $v) {
            $demand_sn_info[] = $v['demand_sn'];
        }
        $sd_goods_info = DB::table('sort_data as sda')
            ->leftJoin('demand_goods as dg', function ($join) {
                $join->on('dg.demand_sn', '=', 'sda.demand_sn');
                $join->on('dg.spec_sn', '=', 'sda.spec_sn');
            })
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->whereIn('sda.demand_sn', $demand_sn_info)
            ->where('dg.is_postpone',
                DB::raw('(
                    CASE jms_d.status
                    WHEN 1 THEN
                        2
                    ELSE
                        1
                    END
                )'))
            ->groupBy('sda.demand_sn')
            ->pluck(DB::raw('sum(jms_sda.yet_num) as yet_num'), 'sda.demand_sn');
        $sd_goods_info = objectToArrayZ($sd_goods_info);
        foreach ($demand_goods_info as $k => $v) {
            if (isset($sd_goods_info[$v['demand_sn']])) {
                $yet_num = intval($sd_goods_info[$v['demand_sn']]);
                $demand_goods_info[$k]['goods_num'] -= $yet_num;
            }
        }
        $return_info['total_num'] = $demand_num;
        $return_info['demand_goods_info'] = $demand_goods_info;
        return $return_info;
    }

    /**
     * description:销售模块-在途商品管理-在途需求单列表
     * editor:zongxing
     * date : 2018.09.21
     * return Object
     */
    public function demandAllotList($request)
    {
        $demand_info = $request->toArray();
        $start_page = isset($demand_info['start_page']) ? intval($demand_info['start_page']) : 1;
        $page_size = isset($demand_info['page_size']) ? intval($demand_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;

        $where = [];
        if (isset($demand_info['query_sn']) && !empty($demand_info['query_sn'])) {
            $query_sn = trim($demand_info['query_sn']);
            $query_sn = "%" . $query_sn . "%";
            $where = [
                ["d.demand_sn", "LIKE", "$query_sn"]
            ];
        }
        //获取需求单列表信息
        $demand_list = DB::table("demand_goods as dg")
            ->select("d.demand_sn", "d.department", "d.expire_time", "d.status", "purchase_sn",
                DB::Raw("count(jms_dg.spec_sn) as sku_num"),
                DB::raw('sum(jms_dg.goods_num) as goods_num')
            )
            ->leftJoin("demand as d", "d.demand_sn", "=", "dg.demand_sn")
            ->leftJoin("purchase_demand as pd", "pd.demand_sn", '=', "dg.demand_sn")
            ->leftJoin("goods_spec as gs", function ($leftJoin) {
                $leftJoin->on("gs.spec_sn", '=', "dg.spec_sn")
                    ->on("gs.erp_prd_no", '=', "dg.erp_prd_no")
                    ->on("gs.erp_merchant_no", '=', "dg.erp_merchant_no");
            })
            ->where("d.status", 3)
            ->where($where)
            ->orderBy("d.create_time", "desc")
            ->skip($start_str)->take($page_size)
            ->groupBy("demand_sn", "purchase_sn")
            ->get();
        $demand_list = objectToArrayZ($demand_list);

        //计算人民币对美元汇率
        $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");

        $total_demand_list = [];
        foreach ($demand_list as $k => $v) {
            if (isset($total_demand_list[$v["demand_sn"]])) {
                $total_demand_list[$v["demand_sn"]]["purchase_info"][] = $v["purchase_sn"];
            } else {
                $total_demand_list[$v["demand_sn"]]["demand_sn"] = $v["demand_sn"];
                $total_demand_list[$v["demand_sn"]]["department"] = $v["department"];
                $total_demand_list[$v["demand_sn"]]["expire_time"] = $v["expire_time"];
                $total_demand_list[$v["demand_sn"]]["status"] = $v["status"];
                $total_demand_list[$v["demand_sn"]]["sku_num"] = $v["sku_num"];
                $total_demand_list[$v["demand_sn"]]["goods_num"] = $v["goods_num"];
                $total_demand_list[$v["demand_sn"]]["purchase_info"][] = $v["purchase_sn"];
            }
        }
        //获取采购期列表信息
        $purchase_demand_list = DB::table("demand_count as dc")
            ->select("dc.purchase_sn",
                DB::raw("sum(jms_dc.goods_num) as goods_num"),
                DB::raw("sum(jms_dc.may_buy_num) as may_buy_num"),
                DB::raw("sum(jms_dc.real_buy_num) as real_buy_num"),
                DB::raw("round(sum(jms_dc.real_buy_num)/sum(jms_dc.may_buy_num) * 100) as real_buy_rate"),
                DB::raw("round((sum(jms_dc.goods_num)-sum(jms_dc.may_buy_num))/sum(jms_dc.goods_num) * 100) as miss_buy_rate")
            )
            ->groupBy("purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $purchase_demand_list = objectToArrayZ($purchase_demand_list);

        //获取商品实采和可采金额
        $purchase_channel_goods_list = DB::table("purchase_channel_goods as pcg")
            ->select("pcg.purchase_sn",
                DB::raw('sum(jms_pcg.may_num * jms_gs.spec_price * jms_pcg.channel_discount) as may_total_price'),
                DB::raw('sum(jms_pcg.real_num * jms_gs.spec_price * jms_pcg.channel_discount) as real_total_price')
            )
            ->leftJoin("goods_spec as gs", "gs.spec_sn", '=', "pcg.spec_sn")
            ->groupBy("pcg.purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $purchase_channel_goods_list = objectToArrayZ($purchase_channel_goods_list);
        foreach ($purchase_demand_list as $k => $v) {
            $purchase_demand_list[$k][0]["real_total_price"] = 0;
            $purchase_demand_list[$k][0]["may_total_price"] = 0;
            $purchase_demand_list[$k][0]["real_cny_total_price"] = 0;
            $purchase_demand_list[$k][0]["may_cny_total_price"] = 0;
            $purchase_demand_list[$k][0]["price_rate"] = 0;
            if (isset($purchase_channel_goods_list[$k]) && $purchase_channel_goods_list[$k][0]["may_total_price"] > 0) {
                $purchase_demand_list[$k][0]["real_total_price"] = round($purchase_channel_goods_list[$k][0]["real_total_price"], 2);
                $purchase_demand_list[$k][0]["may_total_price"] = round($purchase_channel_goods_list[$k][0]["may_total_price"], 2);
                $purchase_demand_list[$k][0]["real_cny_total_price"] = round($purchase_channel_goods_list[$k][0]["real_total_price"] * $USD_CNY_RATE, 2);
                $purchase_demand_list[$k][0]["may_cny_total_price"] = round($purchase_channel_goods_list[$k][0]["may_total_price"] * $USD_CNY_RATE, 2);
                $price_rate = $purchase_channel_goods_list[$k][0]["real_total_price"] / $purchase_channel_goods_list[$k][0]["may_total_price"];
                $purchase_demand_list[$k][0]["price_rate"] = round($price_rate, 2) * 100;
            }
        }

        foreach ($total_demand_list as $k => $v) {
            $real_total_price = 0;
            $may_total_price = 0;
            $real_cny_total_price = 0;
            $may_cny_total_price = 0;
            foreach ($v["purchase_info"] as $k1 => $v1) {
                $tmp_purchase_sn = $v1;
                $total_demand_list[$k]["purchase_info"][$k1] = $purchase_demand_list[$tmp_purchase_sn][0];

                $real_total_price += $purchase_demand_list[$tmp_purchase_sn][0]["real_total_price"];
                $may_total_price += $purchase_demand_list[$tmp_purchase_sn][0]["may_total_price"];
                $real_cny_total_price += $purchase_demand_list[$tmp_purchase_sn][0]["real_cny_total_price"];
                $may_cny_total_price += $purchase_demand_list[$tmp_purchase_sn][0]["may_cny_total_price"];
            }
            $total_demand_list[$k]["real_total_price"] = $real_total_price;
            $total_demand_list[$k]["may_total_price"] = $may_total_price;
            $total_demand_list[$k]["real_cny_total_price"] = $real_cny_total_price;
            $total_demand_list[$k]["may_cny_total_price"] = $may_cny_total_price;
        }

        $total_demand_list = array_values($total_demand_list);
        $return_info["demand_list"] = '';
        if (!empty($total_demand_list)) {
            $return_info["demand_list"] = $total_demand_list;
        }
        //计算需求单总数
        $demand_list_total = DB::table("demand")->where("status", 3)->where($where)->get();
        $return_info["total_num"] = $demand_list_total->count();
        return $return_info;
    }

    /**
     * description:获取需求单汇总信息
     * editor:zongxing
     * date : 2018.09.21
     * return Object
     */
    public function demandAllotDetail($demand_sn)
    {
        $purchase_demand_list = DB::table("demand_count as dc")
            ->select(
                "dc.goods_name", "dc.spec_sn", "dc.erp_prd_no", "dc.erp_merchant_no",
                DB::raw("jms_dc.goods_num as goods_num"),
                DB::raw("sum(jms_dc.may_buy_num) as may_num"),
                DB::raw("sum(jms_dc.real_buy_num) as real_buy_num"),
                DB::raw("round(sum(jms_dc.real_buy_num)/sum(jms_dc.may_buy_num) * 100) as real_buy_rate"),
                DB::raw("round((jms_dc.goods_num - sum(jms_dc.may_buy_num))/jms_dc.goods_num * 100) as miss_buy_rate")
            )
            ->leftJoin("purchase_demand as pd", "pd.purchase_sn", "=", "dc.purchase_sn")
            ->where("pd.demand_sn", $demand_sn)
            ->groupBy("dc.spec_sn")
            ->get()
            ->groupBy("spec_sn");
        $purchase_demand_list = objectToArrayZ($purchase_demand_list);

        //获取本需求单的商品需求信息
        $demand_goods_detail = DB::table("demand_goods")
            ->where("demand_sn", $demand_sn)
            ->get();
        $demand_goods_detail = json_decode(json_encode($demand_goods_detail), true);

        //拼装本需求单在统计表中的预测分配数
        $return_info = [];
        foreach ($demand_goods_detail as $k => $v) {
            $spec_sn = $v["spec_sn"];
            $goods_num = $v["goods_num"];
            if (isset($purchase_demand_list[$spec_sn])) {
                $goods_rate = $goods_num / $purchase_demand_list[$spec_sn][0]["goods_num"];
                $may_allot_num = floor($goods_rate * $purchase_demand_list[$spec_sn][0]["real_buy_num"]);
                $purchase_demand_list[$spec_sn][0]["may_allot_num"] = $may_allot_num;
                array_push($return_info, $purchase_demand_list[$spec_sn][0]);
            }
        }
        return $return_info;
    }

    /**
     * description:进行需求的挂期
     * editor:zongxing
     * date : 2018.09.21
     * return Object
     */
//    public function doDemandAttach($param_info)
//    {
//        $purchase_date_info = explode(",", $param_info["purchase_date_info"]);
//        $demand_sn = $param_info["demand_sn"];
//        $department = $param_info["department"];
//
//        $demand_goods_info = DB::table("demand_goods")->where("demand_sn", $demand_sn)->get();
//        $demand_goods_info = objectToArrayZ($demand_goods_info);
//
//        //获取采购期需求统计表的数据
//        $purchse_detail_info = DB::table("purchase_demand_detail")
//            ->where("demand_sn", $demand_sn)
//            ->get(['id', 'spec_sn', 'purchase_sn'])
//            ->groupBy('purchase_sn');
//        $purchse_detail_info = objectToArrayZ($purchse_detail_info);
//        $purchse_goods_info = [];
//        foreach ($purchse_detail_info as $k => $v) {
//            foreach ($v as $k1 => $v1) {
//                $spec_sn = $v1['spec_sn'];
//                $purchse_goods_info[$k][] = $spec_sn;
//            }
//        }
//
//        $purchase_demand_detail_arr = [];
//        $purchase_demand_arr = [];
//        $tmp_spec_sn = [];
//        foreach ($purchase_date_info as $k => $v) {
//            if (!isset($purchse_goods_info[$v])) {
//                $tmp_arr["demand_sn"] = $demand_sn;
//                $tmp_arr["purchase_sn"] = $v;
//                $tmp_arr["department"] = $department;
//                $purchase_demand_arr[] = $tmp_arr;
//            }
//            foreach ($demand_goods_info as $k1 => $v1) {
//                $spec_sn = $v1['spec_sn'];
//                $goods_num = $v1['goods_num'];
//                if (isset($purchse_goods_info[$v]) && in_array($spec_sn, $purchse_goods_info[$v])) {
//                    if (!in_array($spec_sn, $tmp_spec_sn)) {
//                        $tmp_spec_sn[] = $spec_sn;
//                        $updatePurchaseGoods['goods_num'][][$spec_sn] = 'goods_num + ' . $goods_num;
//                    }
//                } else {
//                    $tmp_arr1["purchase_sn"] = $v;
//                    $tmp_arr1["demand_sn"] = $demand_sn;
//                    $tmp_arr1["goods_name"] = $v1["goods_name"];
//                    $tmp_arr1["erp_prd_no"] = $v1["erp_prd_no"];
//                    $tmp_arr1["erp_merchant_no"] = $v1["erp_merchant_no"];
//                    $tmp_arr1["spec_sn"] = $spec_sn;
//                    $tmp_arr1["goods_num"] = $goods_num;
//                    $tmp_arr1["may_num"] = 0;
//                    $tmp_arr1["sale_discount"] = $v1["sale_discount"];
//                    $purchase_demand_detail_arr[] = $tmp_arr1;
//                }
//            }
//        }
//        $other_option['column'] = 'purchase_sn';
//        $other_option['data'] = $purchase_date_info;
//
//        $updatePurchaseGoodsSql = '';
//        if (!empty($updatePurchaseGoods)) {
//            //更新条件
//            $where = [
//                'demand_sn' => $demand_sn
//            ];
//            //需要判断的字段
//            $column = 'spec_sn';
//            $updatePurchaseGoodsSql = makeBatchUpdateSql('jms_purchase_demand_detail', $updatePurchaseGoods, $column,
//                $where, $other_option);
//        }
//
//        //获取采购期统计表的数据
//        $demand_count_goods_info = DB::table("demand_count")->get(["purchase_sn", "goods_num", "spec_sn"]);
//        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
//        $demand_count_total_info = [];
//        foreach ($demand_count_goods_info as $k => $v) {
//            if (!isset($demand_count_total_info[$v["purchase_sn"]][$v["spec_sn"]])) {
//                $demand_count_total_info[$v["purchase_sn"]][$v["spec_sn"]] = $v["goods_num"];
//            }
//        }
//
//        //新增和更新商品统计表数据
//        $demand_count_insert_arr = [];
//        $tmp_spec_sn = [];
//        $updateDemandCountGoods = [];
//        foreach ($purchase_date_info as $k => $v) {
//            foreach ($demand_goods_info as $k2 => $v2) {
//                $spec_sn = $v2["spec_sn"];
//                $goods_num = $v1['goods_num'];
//                $bd_goods_num = $v1['bd_goods_num'];
//                if (isset($demand_count_total_info[$v][$spec_sn])) {
//                    if (!in_array($spec_sn, $tmp_spec_sn)) {
//                        $tmp_spec_sn[] = $spec_sn;
//                        $diff_goods_num = $goods_num - $bd_goods_num;
//                        $updateDemandCountGoods['goods_num'][][$spec_sn] = 'goods_num + ' . $diff_goods_num;
//                    }
//                } else {
//                    $tmp_arr_count["purchase_sn"] = $v;
//                    $tmp_arr_count["goods_name"] = $v2["goods_name"];
//                    $tmp_arr_count["erp_prd_no"] = $v2["erp_prd_no"];
//                    $tmp_arr_count["erp_merchant_no"] = $v2["erp_merchant_no"];
//                    $tmp_arr_count["spec_sn"] = $v2["spec_sn"];
//                    $tmp_arr_count["goods_num"] = $v2["goods_num"];
//                    $tmp_arr_count["may_buy_num"] = 0;
//                    $tmp_arr_count["real_buy_num"] = 0;
//                    $demand_count_insert_arr[] = $tmp_arr_count;
//                }
//            }
//        }
//
//        $updateDemandCountGoodsSql = '';
//        if (!empty($updateDemandCountGoods)) {
//            //需要判断的字段
//            $column = 'spec_sn';
//            $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, null, $other_option);
//        }
//
//        $insertRes = DB::transaction(function () use (
//            $purchase_demand_arr, $purchase_demand_detail_arr, $updateDemandCountGoodsSql, $demand_count_insert_arr,
//            $updatePurchaseGoodsSql, $demand_sn
//        ) {
//            //新增采购期需求表数据
//            if (!empty($purchase_demand_arr)) {
//                DB::table("purchase_demand")->insert($purchase_demand_arr);
//            }
//            //新增采购期需求详情表数据
//            if (!empty($purchase_demand_detail_arr)) {
//                DB::table("purchase_demand_detail")->insert($purchase_demand_detail_arr);
//            }
//            //更新采购期需求详情表
//            if (!empty($updatePurchaseGoodsSql)) {
//                DB::update(DB::raw($updatePurchaseGoodsSql));
//            }
//
//            //更新商品统计表
//            if (!empty($updateDemandCountGoodsSql)) {
//                DB::update(DB::raw($updateDemandCountGoodsSql));
//            }
//
//            //插入商品统计表
//            if (!empty($demand_count_insert_arr)) {
//                DB::table("demand_count")->insert($demand_count_insert_arr);
//            }
//
//            $update_arr["status"] = 2;
//            $insert_res = DB::table("demand")->where("demand_sn", $demand_sn)->update($update_arr);
//            return $insert_res;
//        });
//        return $insertRes;
//    }
    public function doDemandAttach($param_info)
    {
        $purchase_date_info = explode(',', $param_info['purchase_date_info']);
        $demand_sn = trim($param_info['demand_sn']);
        $department = intval($param_info['department']);
        $is_modify_status = isset($param_info['is_modify_status']) ? intval($param_info['is_modify_status']) : 0;

        $fileds = ['goods_name', 'erp_prd_no', 'erp_merchant_no', 'sale_discount', 'spec_sn', 'goods_num'];
        $demand_goods_info = DB::table('demand_goods')->where('demand_sn', $demand_sn)->get($fileds);
        $demand_goods_info = objectToArrayZ($demand_goods_info);

        $purchase_demand_detail_arr = [];
        $purchase_demand_arr = [];
        $purchase_sn_arr = [];
        foreach ($purchase_date_info as $k => $v) {
            $purchase_sn_arr[] = $v;
            $purchase_demand_arr[] = [
                'demand_sn' => $demand_sn,
                'purchase_sn' => $v,
                'department' => $department,
            ];
            foreach ($demand_goods_info as $k1 => $v1) {
                $spec_sn = $v1['spec_sn'];
                $goods_num = intval($v1['goods_num']);
                $purchase_demand_detail_arr[] = [
                    'purchase_sn' => $v,
                    'demand_sn' => $demand_sn,
                    'goods_name' => $v1['goods_name'],
                    'erp_prd_no' => $v1['erp_prd_no'],
                    'erp_merchant_no' => $v1['erp_merchant_no'],
                    'sale_discount' => $v1['sale_discount'],
                    'spec_sn' => $spec_sn,
                    'goods_num' => $goods_num,
                ];
            }
        }

        //获取采购期统计表的数据
        $demand_count_goods_info = DB::table("demand_count")->whereIn('purchase_sn', $purchase_sn_arr)
            ->get(['id', 'purchase_sn', 'goods_num', 'spec_sn']);
        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
        $demand_count_total_info = [];
        foreach ($demand_count_goods_info as $k => $v) {
            if (!isset($demand_count_total_info[$v["purchase_sn"]][$v["spec_sn"]])) {
                $demand_count_total_info[$v["purchase_sn"]][$v["spec_sn"]] = $v["id"];
            }
        }

        //新增和更新商品统计表数据
        $demand_count_insert_arr = [];
        $updateDemandCountGoods = [];
        foreach ($purchase_date_info as $k => $v) {
            foreach ($demand_goods_info as $k2 => $v2) {
                $spec_sn = $v2["spec_sn"];
                $goods_num = intval($v2['goods_num']);
                if (isset($demand_count_total_info[$v][$spec_sn])) {
                    $id = $demand_count_total_info[$v][$spec_sn];
                    $updateDemandCountGoods['goods_num'][][$id] = 'goods_num + ' . $goods_num;
                } else {
                    $demand_count_insert_arr[] = [
                        'purchase_sn' => $v,
                        'spec_sn' => $spec_sn,
                        'goods_name' => $v2['goods_name'],
                        'erp_prd_no' => $v2['erp_prd_no'],
                        'erp_merchant_no' => $v2['erp_merchant_no'],
                        'goods_num' => $goods_num,
                    ];
                }
            }
        }

        $updateDemandCountGoodsSql = '';
        if (!empty($updateDemandCountGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column);
        }

        $insertRes = DB::transaction(function () use (
            $purchase_demand_arr, $purchase_demand_detail_arr, $updateDemandCountGoodsSql, $demand_count_insert_arr,
            $demand_sn, $is_modify_status
        ) {
            //新增采购期需求表数据
            if (!empty($purchase_demand_arr)) {
                DB::table("purchase_demand")->insert($purchase_demand_arr);
            }
            //新增采购期需求详情表数据
            if (!empty($purchase_demand_detail_arr)) {
                DB::table("purchase_demand_detail")->insert($purchase_demand_detail_arr);
            }

            //更新商品统计表
            if (!empty($updateDemandCountGoodsSql)) {
                $res = DB::update(DB::raw($updateDemandCountGoodsSql));
            }

            //插入商品统计表
            if (!empty($demand_count_insert_arr)) {
                $res = DB::table("demand_count")->insert($demand_count_insert_arr);
            }
            if ($is_modify_status) {
                $update_arr["status"] = 2;
                $res = DB::table("demand")->where("demand_sn", $demand_sn)->update($update_arr);
            }
            return $res;
        });
        return $insertRes;
    }

    /**
     * description:需求管理-提报需求-商品上传-组装相关需求表的写入数据
     * editor:zhangdong
     * params:$insertData:表格数据；$department_id：部门id；$sale_user_id：销售用户id；$expire_time：交付日期
     * date : 2018.10.18
     */
    public function createDemandData($insertData, $department_id, $sale_user_id, $expire_time)
    {
        $goodsModel = new GoodsModel();
        $demandGoodsModel = new DemandGoodsModel();
        $demandGoodsData = [];
        //根据采购单号生成需求单号
        $demand_sn = $demandGoodsModel->generalDemandSn();
        $demandOrdData = [
            'demand_sn' => $demand_sn,
            'department' => $department_id,
            'sale_user_id' => $sale_user_id,
            'expire_time' => $expire_time,
        ];
        $arrErpNo = [];
        $arrSpecSn = [];
        $loseUgoods = [];
        foreach ($insertData as $key => $value) {
            if ($key === 0) continue;//第一行数据为标题头，不写入
            //根据商家编码或者商品规格码查询商品信息
            $queryData = trim($value[3]);
            $type = 1;//根据商家编码查询
            if (empty($queryData)) {
                $queryData = trim($value[4]);
                $type = 2;//根据规格码查询
                if (empty($queryData)) continue;
            }
            $goodsInfo = $goodsModel->getGoodsInfo($queryData, $type);
            if (empty($goodsInfo) || $goodsInfo === false) {
                //如果没有查到对应商品信息则将其返回
                if ($type == 1) $arrErpNo[$key] = $queryData;
                if ($type == 2) $arrSpecSn[$key] = $queryData;
                continue;
            }
            $spec_sn = trim($goodsInfo->spec_sn);
            //根据spec_sn查询对应销售用户是否有定价折扣，如果没有则返回
            $sugModel = new SaleUserGoodsModel();
            $saleUserGoods = $sugModel->getSugInfo($department_id, $sale_user_id, $spec_sn, 2);
            if (is_null($saleUserGoods)) {
                //如果没有查到对应商品信息则将其规格码返回
                $loseUgoods[$key] = $spec_sn;
                continue;
            }
            //商品需求数据
            $erp_merchant_no = trim($value[3]);
            $spec_sn = trim($goodsInfo->spec_sn);
            $goods_num = intval($value[12]);
            $sale_discount = trim($saleUserGoods->sale_discount);
            $demandGoodsData[] = [
                'demand_sn' => $demand_sn,
                'sale_discount' => $sale_discount,
                'erp_merchant_no' => $erp_merchant_no,
                'spec_sn' => $spec_sn,
                'goods_num' => $goods_num,
                'allot_num' => $goods_num,
                'goods_name' => trim($goodsInfo->goods_name),
                'erp_prd_no' => trim($goodsInfo->erp_prd_no),
            ];
        }//end of foreach
        //数据异常的商品
        $tipMsg = '';
        if (count($arrErpNo) > 0) $tipMsg .= '商家编码为：' . implode(',', $arrErpNo) . ' 的商品系统中未找到';
        if (count($arrSpecSn) > 0) $tipMsg .= 'sku编码为：' . implode(',', $arrSpecSn) . ' 的商品系统中未找到';
        if (count($loseUgoods) > 0) $tipMsg .= 'sku编码为：' . implode(',', $loseUgoods) . ' 的商品用户商品信息中未找到';
        $demandData = [
            'demandOrdData' => $demandOrdData,//需求订单数据
            'demandGoodsData' => $demandGoodsData,//商品需求数据
            'tipMsg' => $tipMsg,//异常数据提示信息
        ];
        return $demandData;
    }

    /**
     * description:需求管理-需求详情-根据需求单获取需求单信息
     * editor:zhangdong
     * date : 2018.10.19
     * @param $demand_sn (需求单号)
     */
    public function getDemOrdMsg($demand_sn)
    {
        $demand_sn = trim($demand_sn);
        $field = [
            'd.demand_sn', 'd.department', 'd.sale_user_id', 'd.expire_time', 'd.status',
            'd.create_time', 'su.user_name', 'su.min_profit', 'd.is_mark'
        ];
        $where = [
            ['demand_sn', $demand_sn],
        ];
        $demOrdMsg = DB::table('demand as d')->select($field)
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->where($where)->first();
        return $demOrdMsg;

    }

    /**
     * description:需求管理-需求详情-更新销售折扣
     * editor:zhangdong
     * date : 2018.10.19
     * @param $demand_sn 需求单号
     * @param $spec_sn 规格码
     * @param $sale_discount 销售折扣
     * @param $is_change 是否修改 1，更新 2，不更新
     */
    public function modSaleRate($demand_sn, $spec_sn, $sale_discount)
    {
        //先更新需求商品表
        $where = [
            ['demand_sn', $demand_sn],
            ['spec_sn', $spec_sn]
        ];
        $update = ['sale_discount' => $sale_discount];
        $modRes = DB::table('demand_goods')->where($where)->update($update);
        return $modRes;
    }

    /**
     * description:需求管理-需求详情-更新销售折扣
     * editor:zhangdong
     * date : 2018.10.19
     * @param $spec_sn 规格码
     * @param $sale_discount 销售折扣
     * @param $sale_user_id 销售用户id
     * @param $depart_id 部门id
     * @return
     */
    public function modSaleUserRate($spec_sn, $sale_discount, $sale_user_id, $depart_id)
    {
        //先更新需求商品表
        $where = [
            ['sale_user_id', $sale_user_id],
            ['spec_sn', $spec_sn],
            ['depart_id', $depart_id],
        ];
        $update = ['sale_discount' => $sale_discount];
        $modRes = DB::table('sale_user_goods')->where($where)->update($update);
        return $modRes;
    }

    /**
     * description:采购模块-预采需求管理-预采需求列表
     * editor:zongxing
     * date : 2018.12.10
     * return Array
     */
    public function predictDemandList($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $predict_demand_list = DB::table('demand as d')
            ->select('d.demand_sn', 'de_name', 'user_name', 'd.create_time',
                DB::raw('SUM(jms_dg.goods_num) as goods_num')
            )
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->leftJoin('demand_goods as dg', 'dg.demand_sn', '=', 'd.demand_sn')
            ->leftJoin('department as dp', 'dp.department_id', '=', 'd.department')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->where('dg.is_mark', 1)
            ->where(function ($query) {
                $query->orWhere('mos.status', 1)
                    ->orWhere('mos.status', 2);
            })
            ->orderBy('d.create_time', 'DESC')
            ->groupBy('d.demand_sn')
            ->paginate($page_size);
        $predict_demand_list = objectToArrayZ($predict_demand_list);
        return $predict_demand_list;
    }

    /**
     * description:采购模块-预采需求管理-下载预采需求列表
     * editor:zongxing
     * date: 2018.12.10
     */
//    public function downloadPredictDemand($demand_sn)
//    {
//        //获取预采需求单详情
//        $predict_demand_detail = $this->getPredictDetail($demand_sn);
//        if (empty($predict_demand_detail)) {
//            return false;
//        }
//
//        $obpe = new PHPExcel();
//        //设置采购渠道及列宽
//        $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
//        $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('E1', '需求数量')->getColumnDimension('E')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('F1', '是否预采')->getColumnDimension('F')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('G1', '采购量')->getColumnDimension('G')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('H1', '是否为搭配商品（是或否）')->getColumnDimension('H')->setWidth(20);
//        $obpe->setActiveSheetIndex(0);
//
//        //获取最大列名称
//        $currentSheet = $obpe->getSheet(0);
//        $column_last_name = $currentSheet->getHighestColumn();
//        $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
//
//        //获取最大行数
//        $row_last_i = count($predict_demand_detail) + 2;
//
//        for ($i = 0; $i < $row_last_i; $i++) {
//            if ($i < 2) continue;
//            $row_i = $i - 2;
//            for ($j = 0; $j < $column_last_num; $j++) {
//                //获取列名
//                $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
//                $predict_demand_detail[$row_i] = array_values($predict_demand_detail[$row_i]);
//
//
//                $last_prev_j = $column_last_num - 2;
//                $last_j = $column_last_num - 1;
//                if ($j == $last_prev_j) {
//                    $is_mark = intval($predict_demand_detail[$row_i][$j]);
//                    if ($is_mark == 0) {
//                        $obpe->getActiveSheet()->setCellValue($column_name . $i, '否');
//                    } else {
//                        $obpe->getActiveSheet()->setCellValue($column_name . $i, '是');
//                    }
//                } elseif ($j == $last_j) {
//                    $obpe->getActiveSheet()->setCellValue($column_name . $i, 0);
//                } else {
//                    $obpe->getActiveSheet()->setCellValue($column_name . $i, $predict_demand_detail[$row_i][$j]);
//                }
//
//            }
//        }
//
//        $column_first_name = "A";
//        $row_first_i = 1;
//        $row_end_i = 1;
//        $commonModel = new CommonModel();
//        //改变表格标题样式
//        $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
//        //改变表格内容样式
//        $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);
//        $obpe->getActiveSheet()->setTitle('预采需求表'.$demand_sn);
//
//        //清除缓存
//        ob_end_clean();
//        //写入类容
//        $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');
//
//        $str = rand(1000, 9999);
//        $filename = '预采需求表_' . $str . '.xls';
//
//        //保存文件
//        //$obwrite->save($filename);
//
//        //直接在浏览器输出
//        header('Pragma: public');
//        header('Expires: 0');
//        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
//        header('Content-Type:application/force-download');
//        header('Content-Type:application/vnd.ms-execl');
//        header('Content-Type:application/octet-stream');
//        header('Content-Type:application/download');
//        header("Content-Disposition:attachment;filename=$filename");
//        header('Content-Transfer-Encoding:binary');
//        $obwrite->save('php://output');
//    }
    public function downloadPredictDemand($demand_sn)
    {
        //获取预采需求单详情
        $predict_demand_detail = $this->getPredictDetail($demand_sn);
        if (empty($predict_demand_detail)) {
            return false;
        }

        $obpe = new PHPExcel();
        //设置采购渠道及列宽
        $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('B1', '商品参考码')->getColumnDimension('B')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('E1', '需求数量')->getColumnDimension('E')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('F1', '是否预采')->getColumnDimension('F')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('G1', '采购量')->getColumnDimension('G')->setWidth(20);
        $obpe->setActiveSheetIndex(0);

        //获取最大列名称
        $currentSheet = $obpe->getSheet(0);
        $column_last_name = $currentSheet->getHighestColumn();
        $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

        //获取最大行数
        $row_last_i = count($predict_demand_detail) + 2;
        for ($i = 0; $i < $row_last_i; $i++) {
            if ($i < 2) continue;
            $row_i = $i - 2;
            for ($j = 0; $j < $column_last_num; $j++) {
                //获取列名
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                $predict_demand_detail[$row_i] = array_values($predict_demand_detail[$row_i]);

                $last_prev_2_j = $column_last_num - 2;
                $last_prev_j = $column_last_num - 1;
                if ($j == $last_prev_2_j) {
                    $is_mark = intval($predict_demand_detail[$row_i][$j]);
                    if ($is_mark == 0) {
                        $obpe->getActiveSheet()->setCellValue($column_name . $i, '否');
                    } else {
                        $obpe->getActiveSheet()->setCellValue($column_name . $i, '是');
                    }
                } elseif ($j == $last_prev_j) {
                    $obpe->getActiveSheet()->setCellValue($column_name . $i, 0);
                } else {
                    $obpe->getActiveSheet()->setCellValue($column_name . $i, $predict_demand_detail[$row_i][$j]);
                }
            }
        }
        $obpe->getActiveSheet()->setCellValue('H1', '是否为搭配商品（是或否）')->getColumnDimension('H')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('I1', '对应商品规格码')->getColumnDimension('I')->setWidth(20);
        $column_last_name = $currentSheet->getHighestColumn();

        $column_first_name = "A";
        $row_first_i = 1;
        $row_end_i = 1;
        $commonModel = new CommonModel();
        //改变表格标题样式
        $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
        //改变表格内容样式
        $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
        $obpe->getActiveSheet()->setTitle($demand_sn);

        //清除缓存
        ob_end_clean();
        //写入类容
        $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

        $str = rand(1000, 9999);
        $filename = '预采需求表_' . $demand_sn . '_' . $str . '.xls';

        //保存文件
        //$obwrite->save($filename);

        //直接在浏览器输出
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Content-Type:application/force-download');
        header('Content-Type:application/vnd.ms-execl');
        header('Content-Type:application/octet-stream');
        header('Content-Type:application/download');
        header("Content-Disposition:attachment;filename=$filename");
        header('Content-Transfer-Encoding:binary');
        $obwrite->save('php://output');
    }

    /**
     * description:采购模块-预采需求管理-获取预采需求单详情
     * editor:zongxing
     * date: 2018.12.10
     */
    public function getPredictDetail($demand_sn)
    {
        $where = [
            ["d.demand_sn", $demand_sn],
            ["dg.is_mark", 1]
        ];
        $predict_demand_detail = DB::table('demand_goods as dg')
            ->select(
                "dg.goods_name", "gs.erp_ref_no", "dg.erp_merchant_no", "dg.spec_sn", "goods_num", "dg.is_mark",
                'goods_label'
            )
            ->leftJoin('demand as d', "d.demand_sn", "=", "dg.demand_sn")
            ->leftJoin('goods_spec as gs', "gs.spec_sn", "=", "dg.spec_sn")
            ->where($where)
            ->get();
        $predict_demand_detail = objectToArrayZ($predict_demand_detail);

        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();

        foreach ($predict_demand_detail as $k => $v) {
            $goods_label = explode(',', $v['goods_label']);
            $tmp_goods_label = [];
            foreach ($goods_label_info as $k1 => $v1) {
                $label_id = intval($v1['id']);
                if (in_array($label_id, $goods_label)) {
                    $tmp_goods_label[] = $v1;
                }
            }
            $predict_demand_detail[$k]['goods_label_list'] = $tmp_goods_label;
        }
        return $predict_demand_detail;
    }

    /**
     * description:生成需求单信息
     * editor:zhangdong
     * date : 2018.12.13
     */
    public function makeDemOrdData($demGoodsData, $sub_order_sn)
    {
        $demandGoodsModel = new DemandGoodsModel();
        //生成需求单号
        $demand_sn = $demandGoodsModel->generalDemandSn();
        //根据子单号查询子单信息
        $mosModel = new MisOrderSubModel();
        $subOrderInfo = $mosModel->getSubOrderInfo($sub_order_sn);
        $expire_time = is_null($subOrderInfo->entrust_time) ?
            '' : trim($subOrderInfo->entrust_time);
        //根据总单号查询总单信息
        $misOrderSn = trim($subOrderInfo->mis_order_sn);
        $misOrderModel = new  MisOrderModel();
        $misOrderInfo = $misOrderModel->getOrderInfo($misOrderSn);
        $depart_id = intval($misOrderInfo[0]->depart_id);
        $sale_user_id = intval($misOrderInfo[0]->sale_user_id);
        $demandOrdData = [
            'sub_order_sn' => $sub_order_sn,
            'demand_sn' => $demand_sn,
            'department' => $depart_id,
            'sale_user_id' => $sale_user_id,
            'expire_time' => $expire_time,
        ];
        //将需求单号写入商品信息中
        foreach ($demGoodsData as $key => $value) {
            $demGoodsData[$key]['demand_sn'] = $demand_sn;
        }
        return [
            'demandOrdData' => $demandOrdData,
            'demGoodsData' => $demGoodsData,
        ];
    }

    /**
     * description:保存需求单信息
     * editor:zhangdong
     * date : 2018.12.13
     */
    public function saveDemOrdData($demandOrdData)
    {
        $demOrdData = $demandOrdData['demandOrdData'];
        $demGoodsData = $demandOrdData['demGoodsData'];
        if (empty($demOrdData) || empty($demGoodsData)) return false;
        $saveRes = DB::transaction(function () use ($demOrdData, $demGoodsData) {
            DB::table('demand')->insert($demOrdData);
            $execRes = DB::table('demand_goods')->insert($demGoodsData);
            return $execRes;
        });
        return $saveRes;
    }

    /**
     * description:查询需求单信息
     * editor:zhangdong
     * date : 2018.12.19
     */
    public function getDemOrderMsg($value, $type = 1)
    {
        $where = [];
        if ($type === 1) {
            $where[] = ['d.demand_sn', $value];
        }
        if ($type === 2) {
            $where[] = ['d.sub_order_sn', $value];
        }
        $queryRes = DB::table('demand as d')->select($this->field)
            ->where($where)->first();
        return $queryRes;
    }

    /**
     * description:商品标记
     * editor:zhangdong
     * date : 2018.12.20
     */
    public function updateMark($demand_sn, $is_mark)
    {
        $demand_sn = trim($demand_sn);
        $is_mark = intval($is_mark);
        if (!isset($this->is_mark[$is_mark])) return false;
        $where = [
            ['d.demand_sn', $demand_sn],
        ];
        $update = [
            'd.is_mark' => $is_mark,
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:数据统计模块-订单管理-总单统计列表
     * editor:zongxing
     * date : 2019.01.07
     * return Array
     */
    public function misOrderStatisticsList($request)
    {
        $param_info = $request->toArray();
        //获取总单信息
        $mis_order_model = new MisOrderModel();
        $mis_order_list = $mis_order_model->getMisOrderListByPage($param_info);
        if (empty($mis_order_list['data'])) {
            return $mis_order_list['data'];
        }

        $mis_order_detail_info = $mis_order_list['data'];
        $mis_order_sn = [];
        $mis_order_detail_list = [];
        foreach ($mis_order_detail_info as $k => $v) {
            $tmp_mis_order_sn = $v['mis_order_sn'];
            $mis_order_sn[] = $tmp_mis_order_sn;
            $v['real_buy_num'] = 0;
            $v['total_real_buy_num'] = 0;
            $v['real_buy_rate'] = 0;
            $v['spot_num'] = 0;
            $v['wait_buy_num'] = 0;
            $v['overflow_num'] = 0;
            $mis_order_detail_list[$tmp_mis_order_sn] = $v;
        }
        //获取子单信息
        $mosg_model = new MisOrderSubGoodsModel();
        $mis_order_sub_list = $mosg_model->getMisSubOrderGoods($mis_order_sn);

        $sub_order_sn = [];
        foreach ($mis_order_sub_list as $k => $v) {
            $tmp_sub_order_sn = $v[0]['sub_order_sn'];
            $sub_order_sn[] = $tmp_sub_order_sn;
            $mis_order_sub_list[$k][0]['real_buy_num'] = 0;
            $mis_order_sub_list[$k][0]['total_real_buy_num'] = 0;
            $mis_order_sub_list[$k][0]['real_buy_rate'] = 0;
            $mis_order_sub_list[$k][0]['spot_num'] = 0;
            $mis_order_sub_list[$k][0]['wait_buy_num'] = 0;
            $mis_order_sub_list[$k][0]['overflow_num'] = 0;
        }
        //获取现货单信息
        $spot_goods_model = new SpotGoodsModel();
        $spot_order_list = $spot_goods_model->getSpotStatistics($sub_order_sn);

        //获取需求单信息
        $demand_goods_model = new DemandGoodsModel();
        $demand_list = $demand_goods_model->getDemandStatistics($sub_order_sn);
        $demand_sn = [];
        foreach ($demand_list as $k => $v) {
            $demand_sn[] = $k;
            $demand_list[$k][0]['real_buy_num'] = 0;
        }

        //获取与需求单相关的采购期的信息
        $purchase_demand_list = DB::table('purchase_demand as pd')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'pd.demand_sn')
            ->whereIn('d.demand_sn', $demand_sn)->get(['purchase_sn', 'd.demand_sn']);
        $purchase_demand_list = objectToArrayZ($purchase_demand_list);
        //获取与需求单相关的采购期商品统计表的信息
        $total_pd_info = [];
        if (!empty($purchase_demand_list)) {
            foreach ($purchase_demand_list as $k => $v) {
                $purchase_sn = $v['purchase_sn'];
                $tmp_demand_sn = $v['demand_sn'];
                $total_pd_info[$purchase_sn][] = $tmp_demand_sn;
            }
            $purchase_sn_arr = array_keys($total_pd_info);
            //获取采购期汇总信息
            $demand_count_model = new DemandCountModel();
            $demand_count_list = $demand_count_model->getDemandCountDetail($purchase_sn_arr);
        }
        //获取与需求单相关的汇总单的信息
        $sum_demand_list = DB::table('sum_demand as sd')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'sd.demand_sn')
            ->whereIn('d.demand_sn', $demand_sn)->get(['sum_demand_sn', 'd.demand_sn']);
        $sum_demand_list = objectToArrayZ($sum_demand_list);
        //获取与需求单相关的采购期商品统计表的信息
        $total_sd_info = [];
        if (!empty($sum_demand_list)) {
            foreach ($sum_demand_list as $k => $v) {
                $sum_demand_sn = $v['sum_demand_sn'];
                $tmp_demand_sn = $v['demand_sn'];
                $total_sd_info[$sum_demand_sn][] = $tmp_demand_sn;
            }
            $sd_sn_arr = array_keys($total_sd_info);
            //获取采购期汇总信息
            $sg_model = new SumGoodsModel();
            $sd_statistic = $sg_model->sumDemandStatistic($sd_sn_arr);
        }

        //获取采购期预采信息
        $rpd_model = new RealPurchaseDetailModel();
        $predict_purchase_detail = $rpd_model->getDemandPredictInfo($purchase_sn_arr, $demand_sn);
        if (empty($purchase_demand_list) && empty($predict_purchase_detail)) {
            return $purchase_demand_list;
        }

        //重组数据结构
        $mis_order_detail_list = $this->createMisOrderDetail($demand_list, $demand_count_list, $total_pd_info,
            $mis_order_sub_list, $mis_order_detail_list, $spot_order_list, $predict_purchase_detail, $sd_statistic,
            $total_sd_info);
        $mis_order_list['data'] = array_values($mis_order_detail_list);
        return $mis_order_list;
    }

    /**
     * description:数据统计模块-订单管理-总单统计列表-数据组装
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function createMisOrderDetail($demand_list, $demand_count_list, $total_pd_info, $mis_order_sub_list,
                                         $mis_order_detail_list, $spot_order_list, $predict_purchase_detail, $sd_statistic,
                                         $total_sd_info)
    {
        if (!empty($predict_purchase_detail)) {
            foreach ($predict_purchase_detail as $k => $v) {
                $demand_sn = $v['demand_sn'];
                $predict_goods_num = intval($v['predict_goods_num']);
                //把预采批次分配给对应的需求单
                if (isset($demand_list[$demand_sn])) {
                    $demand_list[$demand_sn][0]['real_buy_num'] += $predict_goods_num;
                }
            }
        }

        if (!empty($demand_count_list)) {
            foreach ($demand_list as $k => $v) {
                $demand_sn = $k;
                $demand_goods_num = intval($v[0]['demand_goods_num']);
                foreach ($demand_count_list as $k1 => $v1) {
                    $purchase_sn = $k1;
                    $goods_num = intval($v1[0]['goods_num']);
                    $final_buy_num = intval($v1[0]['final_buy_num']);
                    if (in_array($demand_sn, $total_pd_info[$purchase_sn])) {
                        $demand_rate = $demand_goods_num / $goods_num;
                        $demand_real_num = floor($demand_rate * $final_buy_num);
                        $demand_list[$k][0]['real_buy_num'] += $demand_real_num;
                    }
                }
            }
        }
        if (!empty($sd_statistic)) {
            foreach ($demand_list as $k => $v) {
                $demand_sn = $k;
                $demand_goods_num = intval($v[0]['demand_goods_num']);
                foreach ($sd_statistic as $k1 => $v1) {
                    $sd_sn = $k1;
                    $goods_num = intval($v1[0]['goods_num']);
                    $final_buy_num = intval($v1[0]['final_buy_num']);
                    if (in_array($demand_sn, $total_sd_info[$sd_sn])) {
                        $demand_rate = $demand_goods_num / $goods_num;
                        $demand_real_num = floor($demand_rate * $final_buy_num);
                        $demand_list[$k][0]['real_buy_num'] += $demand_real_num;
                    }
                }
            }
        }

        foreach ($demand_list as $k => $v) {
            $sub_order_sn = $v[0]['sub_order_sn'];
            $real_buy_num = intval($v[0]['real_buy_num']);
            if (isset($mis_order_sub_list[$sub_order_sn])) {
                //子单实采数
                $mis_order_sub_list[$sub_order_sn][0]['real_buy_num'] += $real_buy_num;
                //子单实采数+现货数
                $mis_order_sub_list[$sub_order_sn][0]['total_real_buy_num'] += $real_buy_num;
            }
        }

        foreach ($spot_order_list as $k => $v) {
            $sub_order_sn = $v[0]['sub_order_sn'];
            $spot_goods_num = intval($v[0]['spot_goods_num']);
            if (isset($mis_order_sub_list[$sub_order_sn])) {
                $mis_order_sub_list[$sub_order_sn][0]['total_real_buy_num'] += $spot_goods_num;
                $mis_order_sub_list[$sub_order_sn][0]['spot_num'] += $spot_goods_num;
            }
        }

        foreach ($mis_order_sub_list as $k => $v) {
            $real_buy_num = intval($v[0]['real_buy_num']);
            $mis_order_sub_wait_buy_num = intval($v[0]['mis_order_sub_wait_buy_num']);
            $diff_num = $mis_order_sub_wait_buy_num - $real_buy_num;
            if ($diff_num < 0) {
                $v[0]['overflow_num'] = abs($diff_num);
            } elseif ($diff_num >= 0) {
                $v[0]['wait_buy_num'] = $diff_num;
            }
            $mis_order_sn = $v[0]['mis_order_sn'];
            if ($mis_order_detail_list[$mis_order_sn]) {
                //总单实采数
                $mis_order_detail_list[$mis_order_sn]['real_buy_num'] += $real_buy_num;
                //总单实采数+现货数
                $total_real_buy_num = intval($v[0]['total_real_buy_num']);
                $mis_order_detail_list[$mis_order_sn]['total_real_buy_num'] += $total_real_buy_num;
                //子单满足率 = （实采数+现货数）/ 需求数
                $mis_order_sub_total_num = intval($v[0]['mis_order_sub_total_num']);
                $real_buy_rate = $total_real_buy_num / $mis_order_sub_total_num * 100;
                $v[0]['real_buy_rate'] = round($real_buy_rate, 2);
                $mis_order_detail_list[$mis_order_sn]['sub_order_info'][] = $v[0];
            }
        }

        foreach ($mis_order_detail_list as $k => $v) {
            //总单待采数和溢采数
            $mis_order_wait_buy_num = intval($v['mis_order_wait_buy_num']);
            $real_buy_num = intval($v['real_buy_num']);
            $diff_num = $mis_order_wait_buy_num - $real_buy_num;
            if ($diff_num < 0) {
                $mis_order_detail_list[$k]['overflow_num'] = abs($diff_num);
            } elseif ($diff_num >= 0) {
                $mis_order_detail_list[$k]['wait_buy_num'] = $diff_num;
            }
            //总单满足率
            $total_real_buy_num = intval($v['total_real_buy_num']);
            $mis_order_total_num = intval($v['mis_order_total_num']);
            $real_buy_rate = $total_real_buy_num / $mis_order_total_num * 100;
            $mis_order_detail_list[$k]['real_buy_rate'] = round($real_buy_rate, 2);
        }
        return $mis_order_detail_list;
    }

    /**
     * description:数据统计模块-订单管理-需求单统计列表
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function demandStasticsList($request)
    {
        $param_info = $request->toArray();
        //获取子单商品统计信息
        $sub_order_sn[] = $param_info['sub_order_sn'];
        $demand_goods_model = new DemandGoodsModel();
        $demand_list = $demand_goods_model->getDemandStatistics($sub_order_sn);

        $demand_sn = [];
        foreach ($demand_list as $k => $v) {
            $demand_sn[] = $k;
            $demand_list[$k][0]['real_buy_num'] = 0;
            $demand_list[$k][0]['wait_buy_num'] = 0;
            $demand_list[$k][0]['overflow_num'] = 0;
            $demand_list[$k][0]['predict_goods_num'] = 0;
        }

        //获取和需求单相关的采购期信息
        $purchase_demand_list = DB::table('purchase_demand as pd')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'pd.demand_sn')
            ->whereIn('d.demand_sn', $demand_sn)->get(['purchase_sn', 'd.demand_sn']);
        $purchase_demand_list = objectToArrayZ($purchase_demand_list);

        $tmp_purchase_demand_info = [];
        $purchase_sn_arr = [];
        if (!empty($purchase_demand_list)) {
            foreach ($purchase_demand_list as $k => $v) {
                $purchase_sn = $v['purchase_sn'];
                $tmp_demand_sn = $v['demand_sn'];
                $tmp_purchase_demand_info[$purchase_sn][] = $tmp_demand_sn;
            }
            $purchase_sn_arr = array_keys($tmp_purchase_demand_info);
        }

        //获取采购期预采信息
        $rpd_model = new RealPurchaseDetailModel();
        $predict_purchase_detail = $rpd_model->getDemandPredictInfo($purchase_sn_arr, $demand_sn, 'purchase_sn');
        if (empty($purchase_demand_list) && empty($predict_purchase_detail)) {
            return $purchase_demand_list;
        }

        if (!empty($predict_purchase_detail)) {
            foreach ($predict_purchase_detail as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $demand_sn = $v1['demand_sn'];
                    $predict_goods_num = intval($v1['predict_goods_num']);
                    //计算需求单最终需求数据和实采数据
                    if (isset($demand_list[$demand_sn])) {
                        $demand_list[$demand_sn][0]['real_buy_num'] += $predict_goods_num;
                        $demand_list[$demand_sn][0]['predict_goods_num'] += $predict_goods_num;
                    }
                }
                if (!in_array($k, $purchase_sn_arr)) {
                    $purchase_sn_arr[] = $k;
                }
            }
        }

        //获取采购期汇总信息
        $demand_count_model = new DemandCountModel();
        $demand_count_list = $demand_count_model->getDemandCountDetail($purchase_sn_arr);
        foreach ($demand_count_list as $k => $v) {
            $demand_count_list[$k][0]['wait_buy_num'] = 0;
            $demand_count_list[$k][0]['overflow_num'] = 0;
        }

        foreach ($demand_list as $k => $v) {
            $demand_sn = $k;
            $demand_goods_num = $v[0]['demand_goods_num'];
            foreach ($demand_count_list as $k1 => $v1) {
                $purchase_sn = $k1;
                $goods_num = $v1[0]['goods_num'];
                $final_buy_num = $v1[0]['final_buy_num'];
                $real_buy_num = $v1[0]['real_buy_num'];
                //计算溢采值和待采值
                $diff_num = $goods_num - $real_buy_num;
                if ($diff_num < 0) {
                    $v1[0]['overflow_num'] = abs($diff_num);
                } elseif ($diff_num >= 0) {
                    $v1[0]['wait_buy_num'] = $diff_num;
                }
                $bool_str = isset($tmp_purchase_demand_info[$purchase_sn]) &&
                    in_array($demand_sn, $tmp_purchase_demand_info[$purchase_sn]);
                if ($bool_str) {
                    $demand_rate = $demand_goods_num / $goods_num;
                    $demand_real_num = floor($demand_rate * $final_buy_num);
                    $v[0]['real_buy_num'] += $demand_real_num;

                    //计算采购期所包含的本需求单的商品的采满率
                    $purchase_real_rate = $real_buy_num / $goods_num * 100;
                    $v1[0]['real_buy_rate'] = round($purchase_real_rate, 2);
                }
                $predict_goods_num = 0;
                if (isset($predict_purchase_detail[$purchase_sn])) {
                    $predict_goods_num = intval($predict_purchase_detail[$purchase_sn][0]['predict_goods_num']);
                }
                $v1[0]['predict_goods_num'] = $predict_goods_num;
                $v[0]['purchase_info'][] = $v1[0];
            }
            //计算需求单的商品的采满率
            $demand_real_rate = intval($v[0]['real_buy_num']) / $demand_goods_num * 100;
            $v[0]['real_buy_rate'] = round($demand_real_rate, 2);
            //计算溢采值和待采值
            $diff_num = $demand_goods_num - $v[0]['real_buy_num'];
            if ($diff_num < 0) {
                $v[0]['overflow_num'] = abs($diff_num);
            } elseif ($diff_num >= 0) {
                $v[0]['wait_buy_num'] = $diff_num;
            }
            $demand_total_list[] = $v[0];
        }
        return $demand_total_list;
    }

    /**
     * description:财务模块-需求资金管理-获取DD状态的需求单信息
     * editor:zongxing
     * date : 2019.01.22
     * return Array
     */
    public function getDdDemandList($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $field = [
            'd.demand_sn', 'mos.sub_order_sn'
        ];
        $where = [
            'mos.status' => 3,
            'd.status' => 3
        ];
        $demand_list = DB::table('demand as d')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->select($field)->where($where)->paginate($page_size);
        $demand_list = objectToArrayZ($demand_list);
        return $demand_list;
    }

    /**
     * description:销售模块-MIS订单管理-需求单列表-编辑期望到仓日-获取需求单信息
     * editor:zongxing
     * date : 2019.02.15
     * return Array
     */
    public function getDemandInfo($demand_sn, $spec_sn = '', $sum_demand_sn = '')
    {
        $field = ['d.demand_sn', 'expire_time', 'd.status', 'd.demand_type'];
        $demand_obj = DB::table('demand as d');
        if (!empty($spec_sn)) {
            $demand_obj->leftJoin('demand_goods as dg', 'dg.demand_sn', '=', 'd.demand_sn')
                ->leftJoin('sort_data as sd', function ($join) {
                    $join->on('sd.demand_sn', '=', 'dg.demand_sn');
                    $join->on('sd.spec_sn', '=', 'dg.spec_sn');
                })
                ->where('dg.spec_sn', $spec_sn)
                ->where('sd.sum_demand_sn', $sum_demand_sn);
            $field[] = 'sd.goods_num';
            $field[] = 'dg.spec_sn';
            $field[] = DB::raw('(jms_sd.goods_num - (
                        CASE jms_sd.yet_num
                        WHEN jms_sd.yet_num THEN
                            jms_sd.yet_num
                        ELSE
                           0
                        END
                    )) as diff_num');
        }
        $demand_info = $demand_obj->where('d.demand_sn', $demand_sn)->first($field);
        $demand_info = objectToArrayZ($demand_info);
        return $demand_info;
    }

    /**
     * description:财务模块-需求资金管理-获取DD状态的需求单信息
     * editor:zongxing
     * date : 2019.01.22
     * return Array
     */
    public function updateDemandInfo($update_where, $update_data)
    {
        $update_res = DB::table('demand')->where($update_where)->update($update_data);
        return $update_res;
    }

    /**
     * description:数据统计模块-订单管理-订单统计列表
     * editor:zongxing
     * date : 2019.02.26
     * return Array
     */
    public function orderStatisticsList($param_info)
    {
        //获取总单信息
//        $mis_order_model = new MisOrderModel();
//        $mis_order_list = $mis_order_model->getMisOrderListByPage($param_info);
//        if (empty($mis_order_list['data'])) {
//            return $mis_order_list;
//        }

//        $mis_order_detail_info = $mis_order_list['data'];
//        $mis_order_sn = [];
//        $mis_order_detail_list = [];
//        foreach ($mis_order_detail_info as $k => $v) {
//            $tmp_mis_order_sn = $v['mis_order_sn'];
//            $mis_order_sn[] = $tmp_mis_order_sn;
//            $v['real_buy_num'] = $v['total_real_buy_num'] = $v['real_buy_rate'] = $v['spot_num'] = $v['wait_buy_num'] =
//            $v['overflow_num'] = 0;
//            $mis_order_detail_list[$tmp_mis_order_sn] = $v;
//        }

        //获取订单数统计信息
        $mos_model = new MisOrderSubModel();
        $order_type = 1;
        $mos_yd_num = $mos_model->getMisOrderSubSum($order_type);
        $order_type = 2;
        $mos_bd_num = $mos_model->getMisOrderSubSum($order_type);
        $order_type = 3;
        $mos_dd_num = $mos_model->getMisOrderSubSum($order_type);

        //获取子单信息
        $mosg_model = new MisOrderSubGoodsModel();
        $mis_order_sub_info = $mosg_model->getMisSubOrderListByPage($param_info);
        $mis_order_sub_detail = $mis_order_sub_info['data'];

        $sub_order_sn = [];
        $mis_order_sub_list = [];
        foreach ($mis_order_sub_detail as $k => $v) {
            $tmp_sub_order_sn = trim($v['sub_order_sn']);
            $sub_order_sn[] = $tmp_sub_order_sn;
            $v['wait_buy_num'] = $v['overflow_num'] = $v['spot_num'] = $v['demand_goods_num'] = $v['total_real_buy_num'] =
            $v['total_real_buy_num'] = $v['real_buy_num'] = 0;
            $v['sub_purchase_total_price'] = $v['sub_purchase_diff_total_price'] = $v['sub_profit_rate'] =
            $v['sub_quote_rate'] = $v['sub_real_rate'] = 0;
            $v['target_rate'] = 8;
            $mis_order_sub_list[$tmp_sub_order_sn] = $v;
        }

        //获取现货单信息
        $spot_goods_model = new SpotGoodsModel();
        $spot_order_list = $spot_goods_model->getSpotStatistics($sub_order_sn);

        //获取需求单信息
        $demand_goods_model = new DemandGoodsModel();
        $demand_list = $demand_goods_model->getDemandStatistics($sub_order_sn);
        $demand_sn = [];
        foreach ($demand_list as $k => $v) {
            $demand_sn[] = $k;
            $demand_list[$k][0]['real_buy_num'] = $demand_list[$k][0]['sort_purchase_total_price'] =
            $demand_list[$k][0]['sort_sell_total_price'] =
            $demand_list[$k][0]['psrt_price'] = $demand_list[$k][0]['psst_price'] = 0;
        }

        //获取需求单分货信息
        $batch_type = intval($param_info['batch_type']);
        $demand_sort_goods_info = [];
        if ($batch_type == 1) {
            $dg_model = new DemandGoodsModel();
            $demand_sort_goods_info = $dg_model->demandBatchInfo($demand_sn);
        } elseif ($batch_type == 2) {
            $dsg_model = new DepartSortGoodsModel();
            $demand_sort_goods_info = $dsg_model->demandSortInfo($demand_sn);
        }
        //重组数据结构
        $mis_order_total_list = $this->createOrderTotalInfo($demand_list, $spot_order_list, $demand_sort_goods_info,
            $mis_order_sub_list);
        $title_info = $mis_order_total_list['title_info'];
        $title_info['mos_yd_num'] = $mos_yd_num;
        $title_info['mos_bd_num'] = $mos_bd_num;
        $title_info['mos_dd_num'] = $mos_dd_num;
        $mis_order_sub_info['title_info'] = $title_info;
        $mis_order_sub_info['data'] = array_values($mis_order_total_list['mis_order_sub_list']);
        return $mis_order_sub_info;
    }

    /**
     * description:数据统计模块-订单管理-订单统计列表
     * editor:zongxing
     * date : 2019.02.26
     * return Array
     */
    public function createOrderTotalInfo($demand_list, $spot_order_list, $demand_sort_goods_info,
                                         $mis_order_sub_list)
    {
        //把分货数据给对应的需求单
        if (!empty($demand_sort_goods_info)) {
            foreach ($demand_sort_goods_info as $k => $v) {
                $demand_sn = $v['demand_sn'];
                $handle_goods_num = intval($v['handle_goods_num']);//分货数量
                $psrt_price = floatval($v['psrt_price']);//实采毛利金额
                $psst_price = floatval($v['psst_price']);//实采报价金额
                if (isset($demand_list[$demand_sn])) {
                    $demand_list[$demand_sn][0]['real_buy_num'] += $handle_goods_num;//订单分货数量
                    $demand_list[$demand_sn][0]['sort_purchase_total_price'] += floatval($v['sort_purchase_total_price']);//采购分货总额
                    $demand_list[$demand_sn][0]['psrt_price'] += $psrt_price;//实采毛利金额
                    $demand_list[$demand_sn][0]['psst_price'] += $psst_price;//实采报价金额
                }
            }
        }

        //将需求单数据并入子单
        $title_psrt_price = 0;
        $title_psst_price = 0;
        foreach ($demand_list as $k => $v) {
            $sub_order_sn = $v[0]['sub_order_sn'];
            $demand_sn = $v[0]['demand_sn'];
            //实采满足率 实采逻辑毛利
            $sub_real_rate = $sub_profit_rate = 0;
            if (isset($mis_order_sub_list[$sub_order_sn])) {
                $demand_goods_num = intval($v[0]['demand_goods_num']);//需求单需求数
                $real_buy_num = intval($v[0]['real_buy_num']);//需求单实采数
                if ($demand_goods_num) {
                    $sub_real_rate = number_format($real_buy_num / $demand_goods_num * 100, 2);//实采满足率
                }

                $mis_order_sub_list[$sub_order_sn]['demand_sn'] = $demand_sn;//子单对应的需求单
                $mis_order_sub_list[$sub_order_sn]['demand_goods_num'] = $demand_goods_num;//子单对应的需求单
                $mis_order_sub_list[$sub_order_sn]['real_buy_num'] = $real_buy_num;//订单实采数
                $mis_order_sub_list[$sub_order_sn]['total_real_buy_num'] = $real_buy_num;//订单满足数

                $psrt_price = floatval($v[0]['psrt_price']);//实采毛利金额
                $psst_price = floatval($v[0]['psst_price']);//实采报价金额
                $mis_order_sub_list[$sub_order_sn]['psrt_price'] = $psrt_price;//子单实采毛利金额
                $mis_order_sub_list[$sub_order_sn]['psst_price'] = $psst_price;//子单实采报价金额
                if ($psst_price) {
                    $sub_profit_rate = number_format($psrt_price / $psst_price * 100, 2);//实采逻辑毛利
                }
                $mis_order_sub_list[$sub_order_sn]['sdt_price'] -= floatval($psst_price);//销售缺口金额
                $title_psrt_price += $psrt_price;//标题实采毛利金额
                $title_psst_price += $psst_price;//标题实采报价金额
                //采购需求总额
                $demand_purchase_total_price = floatval($v[0]['demand_purchase_total_price']);
                $mis_order_sub_list[$sub_order_sn]['sub_purchase_total_price'] = number_format($demand_purchase_total_price, 2, '.', '');
                //采购缺口总额
                $sort_purchase_total_price = floatval($v[0]['sort_purchase_total_price']);
                $sub_purchase_diff_total_price = $demand_purchase_total_price - $sort_purchase_total_price;
                $mis_order_sub_list[$sub_order_sn]['sub_purchase_diff_total_price'] = floatval($sub_purchase_diff_total_price);
            }
            $mis_order_sub_list[$sub_order_sn]['sub_profit_rate'] = $sub_profit_rate;//实采逻辑毛利
            $mis_order_sub_list[$sub_order_sn]['sub_real_rate'] = $sub_real_rate;//实采满足率
        }

        //将现货单数据并入子单
        foreach ($spot_order_list as $k => $v) {
            $sub_order_sn = $v[0]['sub_order_sn'];
            if (isset($mis_order_sub_list[$sub_order_sn])) {
                $spot_goods_num = $v[0]['spot_goods_num'];
                $pst_price = $v[0]['pst_price'];
                $mis_order_sub_list[$sub_order_sn]['total_real_buy_num'] += $spot_goods_num;//订单满足数
                $mis_order_sub_list[$sub_order_sn]['spot_num'] += $spot_goods_num;//订单现货数量
                $mis_order_sub_list[$sub_order_sn]['sdt_price'] -= floatval($pst_price);//销售缺口金额
            }
        }
        //对子单数据进行整理
        $title_info = [];
        $tmp_sale_discount = $tmp_sku_num = $tpd_total_price = $tsd_total_price = $title_sqrt_price = $title_sqt_price = 0;
        //报价逻辑毛利
        foreach ($mis_order_sub_list as $k => $v) {
            //计算子单采满率
            $total_real_buy_num = intval($v['total_real_buy_num']);//订单满足数
            $mis_order_sub_total_num = intval($v['mis_order_sub_total_num']);//订单总需求数
            $sub_total_real_rate = 0;
            if ($mis_order_sub_total_num) {
                $sub_total_real_rate = number_format($total_real_buy_num / $mis_order_sub_total_num * 100, 2);
            }
            $mis_order_sub_list[$k]['sub_total_real_rate'] = $sub_total_real_rate;
            //计算待采数和溢采数
            if (isset($v['real_buy_num'])) {
                $real_buy_num = intval($v['real_buy_num']);
                $demand_goods_num = intval($v['demand_goods_num']);
                $diff_num = $demand_goods_num - $real_buy_num;
                if ($diff_num < 0) {
                    $mis_order_sub_list[$k]['overflow_num'] = abs($diff_num);
                } elseif ($diff_num >= 0) {
                    $mis_order_sub_list[$k]['wait_buy_num'] = $diff_num;
                }
            }

            //计算标题信息
            $spd_total_price = floatval($v['sub_purchase_diff_total_price']);//订单采购缺口金额
            $sdt_price = floatval($v['sdt_price']);//订单销售缺口金额
            $tpd_total_price += $spd_total_price;//标题订单采购缺口金额
            $tsd_total_price += $sdt_price;//标题订单销售缺口金额
            $mis_order_sub_list[$k]['sub_purchase_diff_total_price'] = $v['sub_purchase_diff_total_price'];//采购缺口总额
            $mis_order_sub_list[$k]['sdt_price'] = $v['sdt_price'];

            //计算平均折扣
            $tmp_sku_num += intval($v['sku_num']);
            $tmp_sale_discount += floatval($v['sub_total_sale_discount']);

            //计算报价逻辑毛利
            $sub_quote_rate = 0;
            $sqt_price = floatval($v['sqt_price']);//销售金额
            $title_sqt_price += $sqt_price;//标题销售金额
            $sqrt_price = floatval($v['sqrt_price']);//报价毛利金额
            $mis_order_sub_list[$k]['sqrt_price'] = number_format($sqrt_price, 2, '.', '');
            if ($sqt_price) {
                $title_sqrt_price += $sqrt_price;//标题报价毛利金额
                $sub_quote_rate = number_format($sqrt_price / $sqt_price * 100, 2, '.', '');
                $mis_order_sub_list[$k]['sqt_price'] = number_format($sqt_price, 2, '.', '');
            }
            $mis_order_sub_list[$k]['sub_quote_rate'] = $sub_quote_rate;
        }

        $title_info['purchase_diff_total_price'] = number_format($tpd_total_price, 2, '.', '');//标题订单采购缺口金额
        $title_info['sell_diff_total_price'] = number_format($tsd_total_price, 2, '.', '');//标题订单销售缺口金额

        //标题实采逻辑毛利
        $title_sub_profit_rate = 0;
        if ($title_psst_price) {
            $title_sub_profit_rate = number_format($title_psrt_price / $title_psst_price * 100, 2, '.', '');
        }
        $title_info['sub_profit_rate'] = $title_sub_profit_rate;
        //标题报价逻辑毛利
        $title_sub_quote_rate = 0;
        if ($title_sqt_price) {
            $title_sub_quote_rate = number_format($title_sqrt_price / $title_sqt_price * 100, 2, '.', '');
        }
        $title_info['sub_quote_rate'] = $title_sub_quote_rate;
        //标题平均折扣
        $avg_sale_discount = 0;
        if ($tmp_sku_num) {
            $avg_sale_discount = number_format($tmp_sale_discount / $tmp_sku_num, 2, '.', '');
        }
        $title_info['avg_sale_discount'] = $avg_sale_discount;
        $return_info['title_info'] = $title_info;
        $return_info['mis_order_sub_list'] = $mis_order_sub_list;
        return $return_info;
    }


    /**
     * description:通过需求单号查询其对应子单的状态
     * author:zhangdong
     * date : 2019.03.04
     * return object
     */
    public function getSubStatusByDemandSn($demand_sn)
    {
        $where = [
            ['d.demand_sn', $demand_sn],
        ];
        $field = ['mos.status'];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('mis_order_sub AS mos', 'mos.sub_order_sn', 'd.sub_order_sn')
            ->where($where)->first();
        $subStatus = isset($queryRes->status) ? intval($queryRes->status) : 0;
        return $subStatus;
    }

    /**
     * description:销售模块-需求管理-获取并组装列表数据
     * editor:zhangdong
     * date : 2018.07.11
     * return Object
     */
    public function getAdvanceBuyList($params, $pageSize)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        //只筛选BD状态的子订单
        $where = [
            ['mos.status', 2],
        ];
        if ($keywords) {
            $where[] = ['demand_sn', 'LIKE', "%$keywords%"];
        }
        $field = [
            'd.demand_sn', 'd.department', 'd.expire_time', 'd.status', 'd.create_time', 'd.is_mark',
            'd.sub_order_sn', 'd.arrive_store_time',
        ];
        $demandList = DB::table($this->table)->select($field)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', 'd.sub_order_sn')
            ->where($where)->orderBy("create_time", "desc")->paginate($pageSize);
        foreach ($demandList as $key => $value) {
            $demandList[$key]->desc_mark = $this->is_mark[intval($value->is_mark)];
        }
        return $demandList;
    }


    /**
     * description:通过需求单号查询需求单信息
     * author:zhangdong
     * date : 2019.03.20
     * return object
     */
    public function getDemandOrderMsg($demand_sn)
    {
        $where = [
            ['demand_sn', $demand_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;

    }

    /**
     * description:需求管理-需求详情-根据需求单获取需求单信息
     * editor:zhangdong
     * date : 2018.10.19
     * @param $demand_sn (需求单号)
     */
    public function queryDemandInfo(array $demand_sn = [])
    {
        $mosModel = new MisOrderSubModel();
        $moModel = new MisOrderModel();
        $suModel = new SaleUserModel();
        $field = [
            'd.demand_sn', 'mos.sub_order_sn', 'mos.sale_user_account', 'mos.entrust_time',
            'mo.sale_user_id', 'su.user_name',
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin($mosModel->getTable(), 'mos.sub_order_sn', 'd.sub_order_sn')
            ->leftJoin($moModel->getTable(), 'mo.mis_order_sn', 'mos.mis_order_sn')
            ->leftJoin($suModel->getTable(), 'su.id', 'mo.sale_user_id')
            ->whereIn('d.demand_sn', $demand_sn)->get();
        return $queryRes;
    }

    /**
     * description:获取订单中交期最早的订单
     * editor:zongxing
     * type:POST
     * date : 2019.05.29
     * params: 1.需求单数组:$demand_sn_info;
     * return Object
     */
    public function getDemandExpireInfo($demand_sn_info)
    {
        $demand_info = DB::table($this->table)->whereIn('demand_sn', $demand_sn_info)
            ->orderBy('expire_time', 'ASC')->first();
        $demand_info = objectToArrayZ($demand_info);
        return $demand_info;
    }

    /**
     * description 根据子单号查询需求单信息
     * author zhangdong
     * date 2019.08.20
     */
    public function getDemandBySubOrder($subOrderSn)
    {
        $where = [
            ['sub_order_sn', $subOrderSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }


}//end of class
