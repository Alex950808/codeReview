<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\BrandModel;
use App\Model\Vone\CommonModel;
use App\Model\Vone\DataModel;
use App\Model\Vone\DeliverGoodsModel;
use App\Model\Vone\DeliverOrderModel;
use App\Model\Vone\DemandChannelGoodsModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\DemandModel;
use App\Model\Vone\DiscountModel;
use App\Model\Vone\DiscountTypeModel;
use App\Model\Vone\ErpHouseModel;
use App\Model\Vone\ErpStorehouseModel;
use App\Model\Vone\ExchangeRateModel;
use App\Model\Vone\GoodsLabelModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\MisOrderModel;
use App\Model\Vone\MisOrderSubGoodsModel;
use App\Model\Vone\MisOrderSubModel;
use App\Model\Vone\PurchaseChannelGoodsModel;
use App\Model\Vone\PurchaseChannelModel;
use App\Model\Vone\PurchaseDateModel;
use App\Model\Vone\PurchaseDemandDetailModel;
use App\Model\Vone\PurchaseMethodModel;
use App\Model\Vone\PurchaseUserModel;
use App\Model\Vone\RealPurchaseAuditModel;
use App\Model\Vone\RealPurchaseDeatilAuditModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\RealPurchaseModel;
use App\Model\Vone\RefundRulesModel;
use App\Model\Vone\SaleUserModel;
use App\Model\Vone\SdgChannelLogModel;
use App\Model\Vone\SortBatchModel;
use App\Model\Vone\SortDataModel;
use App\Model\Vone\SpotGoodsModel;
use App\Model\Vone\SumDemandChannelGoodsModel;
use App\Model\Vone\SumDemandGoodsModel;
use App\Model\Vone\SumDemandModel;
use App\Model\Vone\SumGoodsModel;
use App\Model\Vone\SumModel;
use App\Model\Vone\SupplierModel;
use App\Model\Vone\TaskModel;
use App\Model\Vone\UserSortGoodsModel;
use App\Modules\Excel\ExcuteExcel;

use Dingo\Api\Http\Request;
use Maatwebsite\Excel\Classes\PHPExcel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

//create by zongxing on the 2018.09.21
class DemandController extends BaseController
{
    /**
     * description:采购模块-需求管理-获取待分配需求列表
     * editor:zongxing
     * date: 2018.09.21
     */
    public function waitAllotDemand(Request $request)
    {
        if ($request->isMethod('get')) {
            $demand_model = new DemandModel();
            $status = 2;
            $demand_list_info = $demand_model->getDemandList($request, $status);

            $code = "1000";
            $msg = "获取待分配需求列表成功";
            $data = $demand_list_info;
            $return_info = compact('code', 'msg', 'data');
            if (empty($demand_list_info['data'])) {
                $code = "1002";
                $msg = "暂无待分配需求列表";
                $return_info = compact('code', 'msg');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-获取已分配需求列表
     * editor:zongxing
     * date: 2018.09.21
     */
    public function alreadyAllotDemand(Request $request)
    {
        if ($request->isMethod('get')) {
            $demand_model = new DemandModel();
            $status = 3;
            $demand_list_info = $demand_model->getDemandList($request, $status);

            $code = "1000";
            $msg = "获取待分配需求列表成功";
            $data = $demand_list_info;
            $return_info = compact('code', 'msg', 'data');

            if (empty($demand_list_info['data'])) {
                $code = "1002";
                $msg = "暂无已分配需求列表";
                $return_info = compact('code', 'msg');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-待分配-获取符合挂期的采购期列表
     * editor:zongxing
     * date: 2018.09.25
     */
    public function demandAttach(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            //查询需求单信息
            $demand_sn = trim($param_info['demand_sn']);
            $demand_model = new DemandModel();
            $demand_info = $demand_model->getDemandInfo($demand_sn);
            if (empty($demand_info)) {
                return response()->json(['code' => '1002', 'msg' => '需求单号有误']);
            }

            //获取符合条件的采购期
            $purchase_date_model = new PurchaseDateModel();
            $purchase_list = $purchase_date_model->getPurchaseListForDemand($demand_info);
            $return_info = ['code' => '1000', 'msg' => '获取符合需求的采购期列表成功', 'data' => $purchase_list];
            if (empty($purchase_list)) {
                $return_info = ['code' => '1003', 'msg' => '暂无符合需求的采购期列表'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-待分配-提交需求挂期
     * editor:zongxing
     * date: 2018.09.25
     */
    public function doDemandAttach(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            $demand_model = new DemandModel();
            $demand_attach_res = $demand_model->doDemandAttach($param_info);

            $return_info = ['code' => '1002', 'msg' => '提交需求挂期失败'];
            if ($demand_attach_res !== false) {
                $return_info = ['code' => '1000', 'msg' => '提交需求挂期成功'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-待分配-获取采购期需求汇总详情
     * editor:zongxing
     * date: 2018.09.25
     */
    public function demandAllot(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();

            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            }
            $demand_sn = trim($param_info["demand_sn"]);
            $orWhere = [];
            if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
                $query_sn = trim($param_info['query_sn']);
                $query_sn = "%" . $query_sn . "%";
                $orWhere = function ($query) use ($query_sn) {
                    $query->orWhere('pdd.erp_merchant_no', 'LIKE', $query_sn)
                        ->orWhere('pdd.spec_sn', 'LIKE', $query_sn)
                        ->orWhere('pdd.goods_name', 'LIKE', $query_sn);
                };
            }

            $demand_total_info = DB::table("demand_goods as dg")
                ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
                ->where("demand_sn", $demand_sn)
                ->where($orWhere)
                ->get()
                ->groupBy('spec_sn');
            $demand_total_list = objectToArrayZ($demand_total_info);
            if (empty($demand_total_info)) {
                return response()->json(['code' => '1003', 'msg' => '需求单号有误']);
            }

            //获取商品标签
            $goods_label_model = new GoodsLabelModel();
            $goods_label_info = $goods_label_model->getAllGoodsLabelList();

            $demand_total_info = [];
            foreach ($demand_total_list as $k => $v) {
                $spec_sn = $v[0]['spec_sn'];
                $goods_label = explode(',', $v[0]['goods_label']);
                $tmp_goods_label = [];
                foreach ($goods_label_info as $k1 => $v1) {
                    $label_id = intval($v1['id']);
                    if (in_array($label_id, $goods_label)) {
                        $tmp_goods_label[] = $v1;
                    }
                }
                $v[0]['goods_label_list'] = $tmp_goods_label;
                $demand_total_info[$spec_sn] = $v[0];
            }

            $purchase_demand_info = DB::table("purchase_demand_detail as pdd")
                ->select(
                    "pdd.purchase_sn", "pdd.goods_name", "pdd.erp_prd_no", "pdd.erp_merchant_no", "pdd.spec_sn",
                    "pdd.may_num", "delivery_team"
                )
                ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "pdd.purchase_sn")
                ->where("pdd.demand_sn", $demand_sn)
                ->groupBy('spec_sn', 'purchase_sn')
                ->get();
            $purchase_demand_info = objectToArrayZ($purchase_demand_info);
            $purchase_list = [];
            foreach ($purchase_demand_info as $k => $v) {
                $spec_sn = $v['spec_sn'];
                $may_num = $v['may_num'];
                if (isset($demand_total_info[$spec_sn]) && isset($demand_total_info[$spec_sn]['total_may_num'])) {
                    $demand_total_info[$spec_sn]['total_may_num'] += $may_num;
                } else {
                    $demand_total_info[$spec_sn]['total_may_num'] = $may_num;
                }
                $purchase_sn = $v['purchase_sn'];
                $demand_total_info[$spec_sn][$purchase_sn] = $may_num;

                if (!in_array($purchase_sn, $purchase_list)) {
                    $purchase_list[] = $purchase_sn;
                }
            }
            $purchase_demand_list = array_values($demand_total_info);
            $code = "1000";
            $msg = "获取采购期需求详情成功";
            $data["purchase_list"] = $purchase_list;
            $data["goods_list"] = $purchase_demand_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }
//    public function demandAllot(Request $request)
//    {
//        if ($request->isMethod('post')) {
//            $param_info = $request->toArray();
//
//            if (empty($param_info["demand_sn"])) {
//                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
//            }
//            $demand_sn = $param_info["demand_sn"];
//
//            $orWhere = [];
//            if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
//                $query_sn = trim($param_info['query_sn']);
//                $query_sn = "%" . $query_sn . "%";
//                $orWhere = [
//                    'orWhere1' => [
//                        ['pdd.erp_merchant_no', 'LIKE', $query_sn],
//                    ],
//                    'orWhere2' => [
//                        ['pdd.spec_sn', 'LIKE', $query_sn],
//                    ],
//                    'orWhere3' => [
//                        ['pdd.goods_name', 'LIKE', $query_sn],
//                    ]
//                ];
//            }
//
//            $purchase_demand_info = DB::table("purchase_demand_detail as pdd")
//                ->select(
//                    "pdd.purchase_sn", "pdd.goods_name", "pdd.erp_prd_no", "pdd.erp_merchant_no", "pdd.spec_sn",
//                    "pdd.goods_num", "pdd.may_num", "delivery_team", "pdd.sale_discount", "dg.allot_num as diff_num",
//                    //'dg.goods_num',
//                    DB::raw("jms_dg.goods_num - jms_dg.allot_num as total_may_num")
//                )
//                ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "pdd.purchase_sn")
//                ->leftJoin("demand_goods as dg", function ($leftJoin) {
//                    $leftJoin->on("dg.spec_sn", '=', "pdd.spec_sn")
//                        ->on("dg.demand_sn", '=', "pdd.demand_sn");
//                })
//                ->where("dg.demand_sn", $demand_sn)
//                ->where(function ($result) use ($orWhere) {
//                    if (count($orWhere) >= 1) {
//                        $result->orWhere($orWhere['orWhere1'])
//                            ->orWhere($orWhere['orWhere2'])
//                            ->orWhere($orWhere['orWhere3']);
//                    }
//                })
//                ->groupBy("spec_sn", "purchase_sn")
//                ->orderBy("pdd.goods_num", "DESC")
//                ->get();
//            $purchase_demand_info = objectToArrayZ($purchase_demand_info);
//            if (empty($purchase_demand_info)) {
//                return response()->json(['code' => '1003', 'msg' => '暂无采购期需求信息']);
//            }
//
//            $purchase_demand_list = [];
//            $purchase_list = [];
//            foreach ($purchase_demand_info as $k => $v) {
//                $tmp_arr["goods_name"] = $v["goods_name"];
//                $tmp_arr["erp_prd_no"] = $v["erp_prd_no"];
//                $tmp_arr["erp_merchant_no"] = $v["erp_merchant_no"];
//                $tmp_arr["spec_sn"] = $v["spec_sn"];
//                $tmp_arr["goods_num"] = $v["goods_num"];
//                $tmp_arr["spec_sn"] = $v["spec_sn"];
//                $tmp_arr["total_may_num"] = $v["total_may_num"];
//                $tmp_arr["diff_num"] = $v["diff_num"];
//                $tmp_arr[$v["purchase_sn"]] = $v["may_num"];
//                $tmp_arr["sale_discount"] = round($v["sale_discount"], 2);
//
//                if (isset($purchase_demand_list[$v["spec_sn"]])) {
//                    $purchase_demand_list[$v["spec_sn"]][$v["purchase_sn"]] = $v["may_num"];
//                } else {
//                    $purchase_demand_list[$v["spec_sn"]] = $tmp_arr;
//                }
//
//                $purchase_sn = $v["purchase_sn"];
//                if (!in_array($purchase_sn, $purchase_list)) {
//                    array_push($purchase_list, $purchase_sn);
//                }
//            }
//            $purchase_demand_list = array_values($purchase_demand_list);
//
//            $code = "1000";
//            $msg = "获取采购期需求详情成功";
//            $data["purchase_list"] = $purchase_list;
//            $data["goods_list"] = $purchase_demand_list;
//            $return_info = compact('code', 'msg', 'data');
//        } else {
//            $code = "1001";
//            $msg = "请求错误";
//            $return_info = compact('code', 'msg');
//        }
//        return response()->json($return_info);
//    }

    /**
     * description:采购模块-需求管理-待分配-获取某一采购期需求详情
     * editor:zongxing
     * date: 2018.09.26
     */
    public function purchaseDemandAllot(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }

            $purchase_sn = $param_info["purchase_sn"];
            $purchase_info = DB::table("purchase_date as pda")
                ->leftJoin("purchase_demand as pde", "pde.purchase_sn", "=", "pda.purchase_sn")
                ->where("pda.purchase_sn", $purchase_sn)->first(["method_info", "channels_info", "pde.demand_sn"]);
            $purchase_info = objectToArrayZ($purchase_info);
            if (empty($purchase_info["demand_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '该采购期暂无需求信息']);
            }

            //获取当前采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_info);
            if (empty($discount_info)) {
                return response()->json(['code' => '1005', 'msg' => '暂无品牌折扣信息,请先维护折扣信息']);
            }

            //获取商品在某一采购期需求详情
            $purchase_demand_detail_model = new PurchaseDemandDetailModel();
            $purchase_demand_list = $purchase_demand_detail_model->createDemandDetail($param_info, $discount_info);

            $code = "1000";
            $msg = "获取采购期需求详情成功";
            $data = $purchase_demand_list["purchase_demand_list"];
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-待分配-打开编辑商品可采页面
     * editor:zongxing
     * date: 2018.09.25
     */
    public function editDemandAllot(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();

            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            } elseif (empty($param_info["spec_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '商品规格码不能为空']);
            }

            $purchase_sn = trim($param_info["purchase_sn"]);
            $purchase_info = DB::table("purchase_date as pda")
                ->leftJoin("purchase_demand as pde", "pde.purchase_sn", "=", "pda.purchase_sn")
                ->where("pda.purchase_sn", $purchase_sn)->first(["method_info", "pde.demand_sn"]);
            $purchase_info = objectToArrayZ($purchase_info);
            if (empty($purchase_info["demand_sn"])) {
                return response()->json(['code' => '1006', 'msg' => '暂无需求信息']);
            }

            //获取商品品牌信息
            $spec_sn = trim($param_info["spec_sn"]);
            $goods_brand_info = DB::table("goods as g")
                ->leftJoin("goods_spec as gs", "gs.goods_sn", "=", "g.goods_sn")
                ->where("gs.spec_sn", $spec_sn)->first(['brand_id']);
            $goods_brand_info = objectToArrayZ($goods_brand_info);

            //获取指定采购期对应的各个方式渠道的采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_info);
            //对折扣内容进行格式化
            $format_discount_info = [];
            $channel_arr = [];
            foreach ($discount_info as $current_info) {
                $format_discount_info[$current_info["brand_id"]][] = $current_info;
                $pin_str = $current_info["channels_name"] . "-" . $current_info["method_name"];
                if (!in_array($pin_str, $channel_arr)) {
                    $channel_arr[] = $pin_str;
                }
            }

            if (!array_key_exists($goods_brand_info['brand_id'], $format_discount_info)) {
                return response()->json(['code' => '1007', 'msg' => '该品牌暂无折扣信息,请维护']);
            }

            //获取需求单中的某个商品是否在采购期进行了分配
            $demand_sn = trim($param_info["demand_sn"]);
            $spec_sn = trim($param_info["spec_sn"]);
            $dcg_model = new DemandChannelGoodsModel();
            $purchase_demand_info = $dcg_model->getDemandChannelGoodsInfo($demand_sn, $purchase_sn, $spec_sn);

            if (empty($purchase_demand_info)) {
                $goods_info = DB::table("brand as b")
                    ->leftJoin("goods as g", "g.brand_id", "=", "b.brand_id")
                    ->leftJoin("goods_spec as gs", "gs.goods_sn", "=", "g.goods_sn")
                    ->where("gs.spec_sn", $spec_sn)
                    ->first(["b.brand_id", "goods_name"]);
                $goods_info = objectToArrayZ($goods_info);

                $goods_name = $goods_info["goods_name"];
                $goods_brand_id = $goods_info["brand_id"];
                $goods_discount_info = $format_discount_info[$goods_brand_id];

                $goods_discount_list = [];
                foreach ($goods_discount_info as $k => $v) {
                    $pin_tmp_str = $v["channels_name"] . "-" . $v["method_name"];
                    $tmp_dis_arr["channel_name"] = $pin_tmp_str;
                    $tmp_dis_arr["brand_discount"] = $v["brand_discount"];
                    $tmp_dis_arr["may_num"] = 0;
                    $goods_discount_list[] = $tmp_dis_arr;
                }
            } else {
                $tmp_purchase_goods = [];
                foreach ($purchase_demand_info as $k => $v) {
                    $pin_tmp_str = $v["channels_name"] . "-" . $v["method_name"];
                    $tmp_purchase_goods[$pin_tmp_str] = $v["may_num"];
                }

                $goods_name = $purchase_demand_info[0]["goods_name"];
                $goods_brand_id = $purchase_demand_info[0]["brand_id"];
                $goods_discount_info = $format_discount_info[$goods_brand_id];

                $goods_discount_list = [];
                foreach ($goods_discount_info as $k => $v) {
                    $pin_tmp_str = $v["channels_name"] . "-" . $v["method_name"];
                    $may_num = 0;
                    if (isset($tmp_purchase_goods[$pin_tmp_str])) {
                        $may_num = $tmp_purchase_goods[$pin_tmp_str];
                    }
                    $tmp_dis_arr["channel_name"] = $pin_tmp_str;
                    $tmp_dis_arr["brand_discount"] = $v["brand_discount"];
                    $tmp_dis_arr["may_num"] = $may_num;
                    $goods_discount_list[] = $tmp_dis_arr;
                }
            }

            $data["goods_name"] = $goods_name;
            $data["goods_discount_list"] = $goods_discount_list;
            $code = "1000";
            $msg = "打开编辑商品可采页面成功";
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-待分配-检查品牌折扣信息是否完整
     * editor:zongxing
     * date: 2018.09.25
     */
    public function check_brand_discount($demand_sn, $purchase_sn = null, $spec_sn = null)
    {
        $where = [];
        if ($spec_sn) {
            $where = [
                ["pdd.spec_sn", "=", $spec_sn],
                ["pdd.purchase_sn", "=", $purchase_sn]
            ];
        }

        $purchase_brand_info = DB::table("purchase_demand_detail as pdd")
            ->leftJoin("purchase_demand as pd", function ($leftJoin) {
                $leftJoin->on("pd.purchase_sn", "=", "pdd.purchase_sn")
                    ->on("pd.demand_sn", '=', "pdd.demand_sn");
            })
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "pdd.spec_sn")
            ->leftJoin("goods as g", "g.goods_sn", "=", "gs.goods_sn")
            ->leftJoin("brand as b", "b.brand_id", "=", "g.brand_id")
            ->where("pdd.demand_sn", $demand_sn)
            ->where($where)
            ->pluck("b.brand_id");
        $purchase_brand_info = objectToArrayZ($purchase_brand_info);

        if (empty($purchase_brand_info)) {
            $return_info = ['code' => '1006', 'msg' => "品牌折扣信息有误"];
            return $return_info;
        }

        $brand_id_info = DB::table("discount")
            ->groupBy("brand_id")
            ->pluck("brand_id");
        $brand_id_info = objectToArrayZ($brand_id_info);
        $diff_arr = array_diff($purchase_brand_info, $brand_id_info);
        if (empty($diff_arr)) {
            $return_info = ['code' => '1000'];
            return $return_info;
        }

        $brand_name_info = DB::table("brand")
            ->whereIn("brand_id", $diff_arr)
            ->pluck("name");
        $str = "";
        foreach ($brand_name_info as $k => $v) {
            $real_num = $k + 1;
            $str .= $real_num . "." . $v;
        }
        $return_info = ['code' => '1005', 'msg' => "请先完善品牌:" . $str . " 的折扣信息"];
        return $return_info;
    }

    /**
     * description:采购模块-需求管理-待分配-提交编辑采购期可采数
     * editor:zongxing
     * date: 2018.09.25
     */
    public function doEditDemandAllot(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            } elseif (empty($param_info["goods_num"])) {
                return response()->json(['code' => '1007', 'msg' => '需求总数不能为空']);
            }

            $purchase_sn = trim($param_info["purchase_sn"]);
            $demand_sn = trim($param_info["demand_sn"]);
            $purchase_info = DB::table("purchase_date as pd")
                ->leftJoin("purchase_demand as pde", "pde.purchase_sn", "=", "pd.purchase_sn")
                ->where("pde.purchase_sn", $purchase_sn)
                ->where("pde.demand_sn", $demand_sn)
                ->first(["method_info", "pde.demand_sn"]);
            $purchase_info = objectToArrayZ($purchase_info);
            if (empty($purchase_info["demand_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '暂无需求信息']);
            }

            $purchase_sn = trim($param_info["purchase_sn"]);
            $demand_sn = trim($param_info["demand_sn"]);
            $spec_sn = trim($param_info["spec_sn"]);

            //$channel_discount = json_decode($param_info["channel_discount"], true);//postman 测试用的，勿删
            $channel_discount = $param_info["channel_discount"];

            $total_num = 0;
            foreach ($channel_discount as $k => $v) {
                $now_num = intval($v["may_num"]);
                $total_num += $now_num;
            }

            //可分配数作校验
            $where = [
                'pdd.purchase_sn' => $purchase_sn,
                'pdd.demand_sn' => $demand_sn,
                'pdd.spec_sn' => $spec_sn
            ];
            $pdd_model = new PurchaseDemandDetailModel();
            $pdd_goods_info = $pdd_model->getGoodsByPurchaseAndDemand($where);

            $demand_goods_model = new DemandGoodsModel();
            $demand_goods_info = $demand_goods_model->getDemandGoodsDetailInfo($demand_sn, $spec_sn);
            if (empty($pdd_goods_info) || empty($demand_goods_info)) {
                return response()->json(['code' => '1008', 'msg' => '采购期、需求单、商品规格码有误,请检查']);
            }

            if ($pdd_goods_info[0]['may_num'] < $total_num) {
                $diff_num = $total_num - intval($pdd_goods_info[0]['may_num']);
                if ($demand_goods_info[$spec_sn]["allot_num"] < $diff_num) {
                    return response()->json(['code' => '1009', 'msg' => '您提交的商品在各个渠道分配数大于总可分配数']);
                }
            }

            //获取临时分配方案中指定采购期、需求单、商品对应的方式个渠道信息
            $where = [
                ["dcg.purchase_sn", "=", $purchase_sn],
                ["dcg.demand_sn", "=", $demand_sn],
                ["dcg.spec_sn", "=", $spec_sn]
            ];

            $demand_channel_goods = DB::table("demand_channel_goods as dcg")
                ->leftJoin("purchase_method as pm", "pm.method_sn", "=", "dcg.method_sn")
                ->leftJoin("purchase_channels as pc", "pc.channels_sn", "=", "dcg.channels_sn")
                ->where($where)
                ->get(["dcg.id as dcg_id", "method_name", "channels_name", 'may_num']);
            $demand_channel_goods = objectToArrayZ($demand_channel_goods);

            $demand_channel_goods_info = [];
            foreach ($demand_channel_goods as $k => $v) {
                $method_name = $v["method_name"];
                $channels_name = $v["channels_name"];
                $pin_method_channels = $channels_name . "-" . $method_name;
                $demand_channel_goods_info[$pin_method_channels]['dcg_id'] = $v['dcg_id'];
                $demand_channel_goods_info[$pin_method_channels]['may_num'] = $v['may_num'];
            }

            //渠道信息
            $channel_info = DB::table("purchase_channels as pc")
                ->leftJoin("purchase_method as pm", "pm.id", "=", "pc.method_id")
                ->get(["channels_sn", "channels_name", "method_sn", "method_name"]);
            $channel_info = objectToArrayZ($channel_info);

            $channel_total_info = [];
            foreach ($channel_info as $k => $v) {
                $channels_name = $v["channels_name"];
                $method_name = $v["method_name"];
                $pin_str = $channels_name . "-" . $method_name;
                if (!isset($channel_total_info[$pin_str])) {
                    $channel_total_info[$pin_str]["channels_sn"] = $v["channels_sn"];
                    $channel_total_info[$pin_str]["method_sn"] = $v["method_sn"];
                }
            }

            $pcg_model = new PurchaseChannelGoodsModel();
            $pcg_info = $pcg_model->getPurchseChannelGoodsInfo($purchase_sn);
            $purchase_channel_goods_list = [];
            if (!empty($pcg_info)) {
                foreach ($pcg_info as $k => $v) {
                    $tmp_spec_sn = $v['spec_sn'];
                    $pin_str = $v['channels_name'] . '-' . $v['method_name'];
                    $may_num = intval($v['may_num']);
                    $id = intval($v['id']);
                    $purchase_channel_goods_list[$tmp_spec_sn][$pin_str]['may_num'] = $may_num;
                    $purchase_channel_goods_list[$tmp_spec_sn][$pin_str]['id'] = $id;
                }
            }

            $dcg_model = new DemandCountModel();
            $dcg_info = $dcg_model->getDemandCountGoodsList($purchase_sn, $spec_sn);
            if (empty($dcg_info)) {
                return response()->json(['code' => '1015', 'msg' => '参数有误,请检查']);
            }
            $demand_status = $demand_goods_info[$spec_sn]['status'];
            //更新需求渠道商品表数据
            $insert_demand_channel = [];
            $insert_purchase_channel = [];
            $add_num = 0;
            $del_num = 0;

            foreach ($channel_discount as $k => $v) {
                $now_num = intval($v["may_num"]);
                $brand_discount = $v["brand_discount"];
                $channel_method_name = trim($v['channel_name']);
                $channels_sn = trim($channel_total_info[$v['channel_name']]['channels_sn']);
                $method_sn = trim($channel_total_info[$v['channel_name']]['method_sn']);

                if (isset($demand_channel_goods_info[$channel_method_name])) {
                    $dcg_id = $demand_channel_goods_info[$channel_method_name]['dcg_id'];
                    $updateDemandChannelGoods['may_num'][] = [
                        $dcg_id => $now_num
                    ];
                    $dcg_may_num = intval($demand_channel_goods_info[$channel_method_name]['may_num']);
                    if (isset($purchase_channel_goods_list[$spec_sn][$channel_method_name])) {
                        $pcg_id = $purchase_channel_goods_list[$spec_sn][$channel_method_name]['id'];
                        $pcg_may_num = $purchase_channel_goods_list[$spec_sn][$channel_method_name]['may_num'];
                        if ($now_num < $dcg_may_num) {
                            $diff_num = $dcg_may_num - $now_num;
                            $pcg_may_num -= $diff_num;
                        } elseif ($now_num > $dcg_may_num) {
                            $diff_num = $now_num - $dcg_may_num;
                            $pcg_may_num += $diff_num;
                        }
                        $updatePurchaseChannelGoods['may_num'][] = [
                            $pcg_id => $pcg_may_num
                        ];
                    } else {
                        $insert_purchase_channel[] = [
                            'purchase_sn' => $purchase_sn,
                            'spec_sn' => $spec_sn,
                            'channels_sn' => $channels_sn,
                            'method_sn' => $method_sn,
                            'channel_discount' => $brand_discount,
                            'may_num' => $now_num,
                        ];
                    }

                    if ($demand_status == 3 && isset($dcg_info[$spec_sn])) {//分配过得订单,其商品必然在demand_count表中存在
                        if ($now_num < $dcg_may_num) {
                            $diff_num = $dcg_may_num - $now_num;
                            $del_num += $diff_num;
                        } elseif ($now_num > $dcg_may_num) {
                            $diff_num = $now_num - $dcg_may_num;
                            $add_num += $diff_num;
                        }
                    }
                } else {
                    $insert_demand_channel[] = [
                        'demand_sn' => $demand_sn,
                        'purchase_sn' => $purchase_sn,
                        'spec_sn' => $spec_sn,
                        'channels_sn' => $channels_sn,
                        'method_sn' => $method_sn,
                        'channel_discount' => $brand_discount,
                        'may_num' => $now_num,
                    ];
                    if (isset($purchase_channel_goods_list[$spec_sn][$channel_method_name])) {
                        $pcg_id = $purchase_channel_goods_list[$spec_sn][$channel_method_name]['id'];
                        $updatePurchaseChannelGoods['may_num'][] = [
                            $pcg_id => 'may_num' + $now_num
                        ];
                    } else {
                        $insert_purchase_channel[] = [
                            'purchase_sn' => $purchase_sn,
                            'spec_sn' => $spec_sn,
                            'channels_sn' => $channels_sn,
                            'method_sn' => $method_sn,
                            'channel_discount' => $brand_discount,
                            'may_num' => $now_num,
                        ];
                    }
                    if ($demand_status == 3 && isset($dcg_info[$spec_sn])) {//分配过得订单,其商品必然在demand_count表中存在
                        $add_num += $now_num;
                    }
                }
            }

            $updateDemandCountGoods = [];
            if ($del_num > 0) {
                $deg_m_id = $dcg_info[$spec_sn]['id'];
                $updateDemandCountGoods['may_buy_num'][] = [
                    $deg_m_id => 'may_buy_num -' . $del_num
                ];
            }
            if ($add_num > 0) {
                $deg_m_id = $dcg_info[$spec_sn]['id'];
                $updateDemandCountGoods['may_buy_num'][] = [
                    $deg_m_id => 'may_buy_num +' . $add_num
                ];
            }

            $updateDemandChannelGoodsSql = '';
            if (!empty($updateDemandChannelGoods)) {
                $column = 'id';
                $updateDemandChannelGoodsSql = makeBatchUpdateSql('jms_demand_channel_goods',
                    $updateDemandChannelGoods, $column);
            }

            $updatePurchaseChannelGoodsSql = '';
            if (!empty($updatePurchaseChannelGoods)) {
                $column = 'id';
                $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods',
                    $updatePurchaseChannelGoods, $column);
            }

            $updateDemandCountGoodsSql = '';
            if (!empty($updateDemandCountGoods)) {
                $column = 'id';
                $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count',
                    $updateDemandCountGoods, $column);
            }

            //更新需求详情表
            $where = [
                ["pdd.purchase_sn", "=", $purchase_sn],
                ["pdd.demand_sn", "=", $demand_sn],
                ["pdd.spec_sn", "=", $spec_sn]
            ];
            $purchase_demand_goods = DB::table("purchase_demand_detail as pdd")
                ->where($where)
                ->first(["may_num"]);
            $purchase_demand_goods = objectToArrayZ($purchase_demand_goods);
            $demand_detail_goods = DB::table("demand_goods")
                ->where("demand_sn", $demand_sn)
                ->where("spec_sn", $spec_sn)
                ->first(["allot_num"]);
            $demand_detail_goods = objectToArrayZ($demand_detail_goods);

            if ($purchase_demand_goods["may_num"] <= $total_num) {
                $diff_num = $total_num - $purchase_demand_goods["may_num"];
                $updateInfo["allot_num"] = $demand_detail_goods["allot_num"] - $diff_num;
            } else {
                $diff_num = $purchase_demand_goods["may_num"] - $total_num;
                $updateInfo["allot_num"] = $demand_detail_goods["allot_num"] + $diff_num;
            }

            $updateRes = DB::transaction(function () use (
                $insert_demand_channel, $updateInfo, $demand_sn, $spec_sn,
                $total_num, $where, $updateDemandChannelGoodsSql, $updatePurchaseChannelGoodsSql, $insert_purchase_channel,
                $updateDemandCountGoodsSql
            ) {
                //更新需求渠道表
                if (!empty($updateDemandChannelGoodsSql)) {
                    DB::update(DB::raw($updateDemandChannelGoodsSql));
                }

                //添加采购期渠道商品表
                if (!empty($insert_purchase_channel)) {
                    DB::table("purchase_channel_goods")->insert($insert_purchase_channel);
                }

                //更新采购期渠道商品表
                if (!empty($updatePurchaseChannelGoodsSql)) {
                    DB::update(DB::raw($updatePurchaseChannelGoodsSql));
                }

                //更新采购期统计表
                if (!empty($updateDemandCountGoodsSql)) {
                    DB::update(DB::raw($updateDemandCountGoodsSql));
                }

                //添加需求渠道表
                if (!empty($insert_demand_channel)) {
                    DB::table("demand_channel_goods")->insert($insert_demand_channel);
                }

                //更新需求单详情表可采数据
                DB::table("demand_goods")->where("demand_sn", $demand_sn)->where("spec_sn", $spec_sn)->update($updateInfo);

                //更新采购期需求详情表可采数据
                $update_pdd_info = [
                    'may_num' => $total_num,
                    'edit_status' => 1,
                ];
                $update_res = DB::table("purchase_demand_detail as pdd")->where($where)->update($update_pdd_info);
                return $update_res;
            });

            $code = "1005";
            $msg = "编辑采购期可采数失败";
            $return_info = compact('code', 'msg');
            if ($updateRes !== false) {
                $return_info = ['code' => '1000', 'msg' => '编辑采购期可采数成功'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-待分配-提交确认的需求分配方案
     * editor:zongxing
     * date: 2018.09.25
     */
//    public function doDemandAllot(Request $request)
//    {
//        if ($request->isMethod('post')) {
//            $param_info = $request->toArray();
//
//            if (empty($param_info["demand_sn"])) {
//                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
//            }
//
//            $demand_sn = trim($param_info["demand_sn"]);
//            $check_brand_discount = $this->check_brand_discount($demand_sn);
//            if ($check_brand_discount["code"] != 1000) {
//                return response()->json($check_brand_discount);
//            }
//
//            //判断商品是否都进行了分配开采数，不一定每个商品都有需求，故隐藏
////            $check_channels_goods = $this->check_channels_goods($demand_sn);
////            if ($check_channels_goods["code"] != 1000) {
////                return response()->json($check_channels_goods);
////            }
//
//            //更新demand的状态、purchase_channel_goods和demand_count统计表数据
//            $demand_count_model = new DemandCountModel();
//            $updateRes = $demand_count_model->updateDataByAllot($param_info);
//
//            $code = "1003";
//            $msg = "提交确认的需求分配方案失败";
//            $return_info = compact('code', 'msg');
//
//            if ($updateRes !== false) {
//                $code = "1000";
//                $msg = "提交确认的需求分配方案成功";
//                $return_info = compact('code', 'msg');
//            }
//        } else {
//            $code = "1001";
//            $msg = "请求错误";
//            $return_info = compact('code', 'msg');
//        }
//        return response()->json($return_info);
//    }
    public function doDemandAllot(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();

            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            }

            $demand_sn = trim($param_info["demand_sn"]);
            $check_brand_discount = $this->check_brand_discount($demand_sn);
            if ($check_brand_discount["code"] != 1000) {
                return response()->json($check_brand_discount);
            }

            //判断商品是否都进行了分配开采数，不一定每个商品都有需求，故隐藏
//            $check_channels_goods = $this->check_channels_goods($demand_sn);
//            if ($check_channels_goods["code"] != 1000) {
//                return response()->json($check_channels_goods);
//            }

            //更新demand的状态、purchase_channel_goods和demand_count统计表数据
            $demand_count_model = new DemandCountModel();
            $updateRes = $demand_count_model->updateDataByAllot($param_info);

            $code = "1003";
            $msg = "提交确认的需求分配方案失败";
            $return_info = compact('code', 'msg');

            if ($updateRes !== false) {
                $code = "1000";
                $msg = "提交确认的需求分配方案成功";
                $return_info = compact('code', 'msg');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-待分配-检查品牌折扣信息是否完整
     * editor:zongxing
     * date: 2018.09.25
     */
    public function check_channels_goods($demand_sn)
    {
        $demand_detail_info = DB::table("purchase_demand_detail as pdd")
            ->leftJoin("purchase_demand as pd", function ($leftJoin) {
                $leftJoin->on("pd.purchase_sn", "=", "pdd.purchase_sn")
                    ->on("pd.demand_sn", '=', "pdd.demand_sn");
            })
            ->where("pdd.demand_sn", $demand_sn)
            ->get(["pdd.purchase_sn", "spec_sn"])
            ->groupBy("purchase_sn");
        $demand_detail_info = objectToArrayZ($demand_detail_info);

        $diff_spec_str = '';
        foreach ($demand_detail_info as $k => $v) {
            $purchase_sn = $k;

            $sku_num = count($demand_detail_info[$purchase_sn]);

            $channel_goods_info = DB::table("demand_channel_goods")
                ->where("purchase_sn", $purchase_sn)
                ->where("demand_sn", $demand_sn)
                ->pluck("purchase_sn", "spec_sn");
            $channel_goods_info = objectToArrayZ($channel_goods_info);

            $channel_spec_info = array_keys($channel_goods_info);
            $channel_goods_num = count($channel_spec_info);

            if ($sku_num != $channel_goods_num) {
                foreach ($v as $k1 => $v1) {
                    $goods_spec_sn = $v1["spec_sn"];
                    if (!in_array($goods_spec_sn, $channel_spec_info)) {
                        if (strpos($diff_spec_str, $purchase_sn) !== false) {
                            $diff_spec_str .= ";" . $purchase_sn . ":" . $goods_spec_sn . ",";
                        } else {
                            $diff_spec_str .= $goods_spec_sn . ",";
                        }
                    }
                }
            }
        }
        $diff_spec_str = substr($diff_spec_str, 0, -1);

        if (empty($diff_spec_str)) {
            $return_info = ['code' => '1000'];
            return $return_info;
        }

        $return_info = ['code' => '1005', 'msg' => "请先完善商品:" . $diff_spec_str . " 的渠道可采数分配信息"];
        return $return_info;
    }

    /**
     * description:采购模块-需求管理-已分配-获取采购期需求汇总详情
     * editor:zongxing
     * date: 2018.09.25
     */
    public function demandAlreadyDetail(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();

            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            }
            $demand_sn = $param_info["demand_sn"];

            $orWhere = [];
            if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
                $query_sn = trim($param_info['query_sn']);
                $query_sn = "%" . $query_sn . "%";
                $orWhere = [
                    'orWhere1' => [
                        ['pdd.erp_merchant_no', 'LIKE', $query_sn],
                    ],
                    'orWhere2' => [
                        ['pdd.spec_sn', 'LIKE', $query_sn],
                    ],
                    'orWhere3' => [
                        ['pdd.goods_name', 'LIKE', $query_sn],
                    ],
                    'orWhere4' => [
                        ['pdd.erp_prd_no', 'LIKE', $query_sn],
                    ]

                ];
            }

            $purchase_demand_info = DB::table("purchase_demand_detail as pdd")
                ->select(
                    "pdd.purchase_sn", "pdd.goods_name", "pdd.erp_prd_no", "pdd.erp_merchant_no", "pdd.spec_sn",
                    "pdd.goods_num", "pdd.may_num", "delivery_team", "pdd.sale_discount", "dg.allot_num as diff_num",
                    DB::raw("jms_dg.goods_num - jms_dg.allot_num as total_may_num")
                )
                ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "pdd.purchase_sn")
                ->leftJoin("demand_goods as dg", function ($leftJoin) {
                    $leftJoin->on("dg.spec_sn", '=', "pdd.spec_sn")
                        ->on("dg.demand_sn", '=', "pdd.demand_sn");
                })
                ->where("pdd.demand_sn", $demand_sn)
                ->where(function ($result) use ($orWhere) {
                    if (count($orWhere) >= 1) {
                        $result->orWhere($orWhere['orWhere1'])
                            ->orWhere($orWhere['orWhere2'])
                            ->orWhere($orWhere['orWhere3'])
                            ->orWhere($orWhere['orWhere4']);
                    }
                })
                ->groupBy("spec_sn", "purchase_sn")
                ->get();
            $purchase_demand_info = objectToArrayZ($purchase_demand_info);

            if (empty($purchase_demand_info)) {
                return response()->json(['code' => '1003', 'msg' => '暂无采购期需求信息']);
            }

            $purchase_demand_list = [];
            $purchase_list = [];
            foreach ($purchase_demand_info as $k => $v) {
                $tmp_arr["goods_name"] = $v["goods_name"];
                $tmp_arr["erp_prd_no"] = $v["erp_prd_no"];
                $tmp_arr["erp_merchant_no"] = $v["erp_merchant_no"];
                $tmp_arr["spec_sn"] = $v["spec_sn"];
                $tmp_arr["goods_num"] = $v["goods_num"];
                $tmp_arr["spec_sn"] = $v["spec_sn"];
                $tmp_arr["total_may_num"] = $v["total_may_num"];
                $tmp_arr["diff_num"] = $v["diff_num"];
                $tmp_arr[$v["purchase_sn"]] = $v["may_num"];
                $tmp_arr["sale_discount"] = round($v["sale_discount"], 2);

                if (isset($purchase_demand_list[$v["spec_sn"]])) {
                    $purchase_demand_list[$v["spec_sn"]][$v["purchase_sn"]] = $v["may_num"];
                } else {
                    $purchase_demand_list[$v["spec_sn"]] = $tmp_arr;
                }

                $purchase_sn = $v["purchase_sn"];
                if (!in_array($purchase_sn, $purchase_list)) {
                    array_push($purchase_list, $purchase_sn);
                }
            }
            $purchase_demand_list = array_values($purchase_demand_list);

            $code = "1000";
            $msg = "获取采购期需求详情成功";
            $data["purchase_list"] = $purchase_list;
            $data["goods_list"] = $purchase_demand_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理-已分配-获取某一采购期需求详情
     * editor:zongxing
     * date: 2018.09.26
     */
    public function purchaseDemandAlready(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();

            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }

            $purchase_sn = $param_info["purchase_sn"];
            $purchase_info = DB::table("purchase_date as pda")
                ->leftJoin("purchase_demand as pde", "pde.purchase_sn", "=", "pda.purchase_sn")
                ->where("pda.purchase_sn", $purchase_sn)->first(["method_info", "pde.demand_sn"]);
            $purchase_info = objectToArrayZ($purchase_info);

            if (empty($purchase_info["demand_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '该采购期暂无需求信息']);
            }

            //获取当前采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_info);

            //获取采购需求信息
            $purchase_demand_detail_model = new PurchaseDemandDetailModel();
            $purchase_demand_list = $purchase_demand_detail_model->createDemandDetail($param_info, $discount_info);

            $code = "1000";
            $msg = "获取采购期需求详情成功";
            $data = $purchase_demand_list["purchase_demand_list"];
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-需求管理管理-查看需求详情
     * editor:zongxing
     * date: 2018.09.26
     */
    public function getDemandDetial(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            }

            $demand_detail_list = DB::table("demand_goods as dg")
                ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
                ->where("demand_sn", $param_info["demand_sn"])->get();
            $demand_detail_list = objectToArrayZ($demand_detail_list);
            //获取商品标签列表
            $goods_label_model = new GoodsLabelModel();
            $goods_label_info = $goods_label_model->getAllGoodsLabelList();
            $demand_goods_total_list = [];
            foreach ($demand_detail_list as $k => $v) {
                $goods_label = explode(',', $v['goods_label']);
                $tmp_goods_label = [];
                foreach ($goods_label_info as $k1 => $v1) {
                    $label_id = intval($v1['id']);
                    if (in_array($label_id, $goods_label)) {
                        $tmp_goods_label[] = $v1;
                    }
                }
                $v['goods_label_list'] = $tmp_goods_label;
                $demand_goods_total_list[] = $v;
            }

            $code = "1000";
            $msg = "获取采购期需求详情成功";
            $data = $demand_goods_total_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-预采需求管理-预采需求列表
     * editor:zongxing
     * date: 2018.12.10
     */
    public function predictDemandList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $demand_model = new DemandModel();
            $predict_demand_list = $demand_model->predictDemandList($param_info);
            if (empty($predict_demand_list["data"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无预采需求']);
            }
            $return_info = ['code' => '1000', 'msg' => '获取预采需求列表成功', 'data' => $predict_demand_list];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-预采需求管理-预采需求单详情
     * editor:zongxing
     * date: 2018.12.12
     */
    public function downloadPredictDemand(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            }
            $demand_sn = trim($param_info["demand_sn"]);
            $demand_model = new DemandModel();
            $down_load_res = $demand_model->downloadPredictDemand($demand_sn);

            $code = "1000";
            $msg = "获取预采需求列表成功";
            $return_info = compact('code', 'msg');
            if (!$down_load_res) {
                $return_info = ['code' => '1003', 'msg' => '需求单号有误'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-预采需求管理-获取预采需求单详情
     * editor:zongxing
     * date: 2018.12.10
     */
    public function predictDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            }
            $demand_sn = trim($param_info["demand_sn"]);
            $demand_model = new DemandModel();
            $predict_demand_detail = $demand_model->getPredictDetail($demand_sn);
            if (empty($predict_demand_detail)) {
                return response()->json(['code' => '1003', 'msg' => '需求单号有误,请检查']);
            }
            $code = "1000";
            $msg = "获取预采需求单详情成功";
            $data = $predict_demand_detail;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-预采需求管理-上传预采批次
     * editor:zongxing
     * type:GET
     * date : 2018.12.10
     * return Object
     */
    public function uploadPredictReal(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();
            //参数检查
            if (empty($reqParams["demand_sn"]))
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            //需求单号检查
            $demand_sn = $reqParams["demand_sn"];
            $demand_info = DB::table("demand")->where("demand_sn", $demand_sn)->first();
            if (empty($demand_info)) {
                return response()->json(['code' => '1003', 'msg' => '需求单号有误']);
            }

            //获取采购期相关信息
            $demand_info = objectToArrayZ($demand_info);
            $expire_time = $demand_info['expire_time'];
            $param_info['time_option'] = $expire_time;
            $param_info['status'] = 1;
            $purchase_date_model = new PurchaseDateModel();
            $purchase_info = $purchase_date_model->getPurchaseDateList($param_info);
            $purchase_info = $purchase_info['data'];
            if (empty($purchase_info)) {
                return response()->json(['code' => '1006', 'msg' => '暂无满足条件的采购期']);
            }
            //获取采购方式信息
            $purchase_method_list = DB::table("purchase_method")
                ->orderBy(DB::raw('convert(`method_name` using gbk)'))
                ->get(["id", "method_name", "method_sn"]);
            $purchase_method_list = objectToArrayZ($purchase_method_list);
            //获取采购渠道信息
            $purchase_channels_list = DB::table("purchase_channels")
                ->orderBy(DB::raw('convert(`channels_name` using gbk)'))
                ->get(["id", "channels_name", "method_id"]);
            $purchase_channels_list = objectToArrayZ($purchase_channels_list);
            //对获取到的信息进行组装
            foreach ($purchase_info as $k => $v) {
                $id = $purchase_info[$k]["id"];
                $purchase_sn = $purchase_info[$k]["purchase_sn"];
                $purchase_finnal_info[$k]["id"] = $id;
                $purchase_finnal_info[$k]["purchase_sn"] = $purchase_sn;
                $purchase_method_info = json_decode($purchase_info[$k]["method_info"]);
                $purchase_channels_info = json_decode($purchase_info[$k]["channels_info"]);
                foreach ($purchase_method_list as $k1 => $v1) {
                    $method_id = $v1["id"];
                    if (in_array($method_id, $purchase_method_info)) {
                        foreach ($purchase_channels_list as $k2 => $v2) {
                            $channel_id = $v2["id"];
                            $parent_id = $v2["method_id"];
                            if (in_array($channel_id, $purchase_channels_info) && $parent_id == $method_id) {
                                $v1["channel_list"][] = $v2;
                            }
                        }
                        $purchase_finnal_info[$k]["method_list"][] = $v1;
                    }
                }
            }
            //获取采购编号id信息
            $purchase_user_model = new PurchaseUserModel();
            $purchase_user_info = $purchase_user_model->getUserList();
            if (empty($purchase_user_info)) {
                return response()->json(['code' => '1005', 'msg' => '暂无采购编号,请先设置采购编号']);
            }
            //获取供应商信息
            $is_page = false;
            $supplier_model = new SupplierModel();
            $supplier_list_info = $supplier_model->getSupplierList(null, $is_page);
            //获取仓库信息
            $erp_house_model = new ErpHouseModel();
            $erp_house_list = $erp_house_model->getErpHouseList();
            //获取任务模板信息
            $task_model = new TaskModel();
            $task_info = $task_model->getTaskList();
            $data["purchase_total_info"] = $purchase_finnal_info;
            $data["user_info"] = $purchase_user_info;
            $data["supplier_list_info"] = $supplier_list_info;
            $data["erp_house_list"] = $erp_house_list;
            $data["task_info"] = $task_info;
            $return_info = ['code' => '1000', 'msg' => '采购数据实时上传条件检查成功', 'data' => $data];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-预采需求管理-确认上传预采批次
     * editor:zongxing
     * type:POST
     * date : 2018.12.10
     * params: 1.需要上传的excel表格文件:purchase_data;2.采购期单号:purchase_sn;3.采购方式id:method_id;4.采购渠道id:channels_id;
     *          5.自提或邮寄id:path_way;6.港口id:port_id;7.用户账号:user_id;8.需求单单号:demand_sn
     * return Object
     */
    public function doUploadPredictReal(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            //检查上传数据参数
            $check_res = $this->checkUploadData($param_info);
            if (!empty($check_res)) {
                return response()->json($check_res);
            }

            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '预采需求表_';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($_FILES, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码', '采购量', '是否为搭配商品（是或否）', '对应商品规格码'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1005', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }
            //获取上传商品的数据
            $upload_goods_info = [];
            foreach ($res as $k => $v) {
                if ($k == 0) continue;
                if (!isset($v[3])) continue;
                if (isset($v[3]) && empty(intval($v[6]))) continue;

                $spec_sn = trim($v[3]);
                $predict_num = intval($v[6]);//采购量
                $upload_goods_info[$spec_sn]['day_buy_num'] = $predict_num;

                //检查商品是否是搭配商品
                $is_match_str = trim($v[7]);
                $is_match = 0;
                if ($is_match_str == '是') {
                    $is_match = 1;
                }
                $upload_goods_info[$spec_sn]['is_match'] = $is_match;
                $upload_goods_info[$spec_sn]['parent_spec_sn'] = trim($v[8]);
            }
            //对上传的商品在系统中是否存在进行校验
            $upload_spec_sn = array_keys($upload_goods_info);
            $gs_model = new GoodsSpecModel();
            $upload_goods_total_info = $gs_model->get_goods_info($upload_spec_sn);
            if (empty($upload_goods_total_info)) {
                return response()->json(['code' => '1018', 'msg' => '您上传的商品的规格码有误,请重新确认']);
            }
            $error_info = '';
            foreach ($upload_goods_info as $k => $v) {
                $spec_sn = $k;
                if (!array_key_exists($spec_sn, $upload_goods_total_info)) {
                    $error_info .= $spec_sn . ',';
                }
            }
            if (!empty($error_info)) {
                $error_info = '您上传的商品中: ' . substr($error_info, 0, -1) . '商品规格码有误';
                return response()->json(['code' => '1019', 'msg' => $error_info]);
            }

            //获取需求单的商品数据
            $demand_sn = trim($param_info['demand_sn']);
            $demand_goods_model = new DemandGoodsModel();
            $demand_goods_info = $demand_goods_model->getDemandGoodsDetail($demand_sn, $upload_spec_sn);
            if (empty($demand_goods_info)) {
                return response()->json(['code' => '1015', 'msg' => '您选择的需求单号有误,请重新确认']);
            }
            //对上传的非搭配商品是否在需求单中存在进行校验
            $error_info = '';
            foreach ($upload_goods_info as $k => $v) {
                $spec_sn = $k;
                $is_match = $v['is_match'];
                if ($is_match == 0 && !array_key_exists($spec_sn, $demand_goods_info)) {
                    $error_info .= $spec_sn . ',';
                }
            }
            if (!empty($error_info)) {
                $error_info = '您上传的商品中: ' . substr($error_info, 0, -1) . '在需求单中不存在,请检查';
                return response()->json(['code' => '1019', 'msg' => $error_info]);
            }

            $purchase_sn = $param_info['purchase_sn'];
            //采购期单号检查
            $param_info['status'] = 1;
            $purchase_date_model = new PurchaseDateModel();
            $purchase_date_info = $purchase_date_model->getPurchaseDateDetail($param_info);
            if (empty($purchase_date_info)) {
                return response()->json(['code' => '1013', 'msg' => '您选择的采购期已结束,请重新确认']);
            }
            //获取采购方式代码简称
            $purchase_mothod_model = new PurchaseMethodModel();
            $method_info = $purchase_mothod_model->checkUploadPurchaseMethod($param_info);
            if (empty($method_info)) {
                return response()->json(['code' => '1012', 'msg' => '您选择的采购方式有误,请重新确认']);
            }
            $method_sn = $method_info['method_sn'];
            $method_name = $method_info['method_name'];
            $param_info['method_sn'] = $method_sn;
            //获取采购渠道代码简称
            $purchase_channel_model = new PurchaseChannelModel();
            $channels_info = $purchase_channel_model->checkUploadPurchaseChannel($param_info);
            if (empty($channels_info)) {
                return response()->json(['code' => '1008', 'msg' => '您选择的采购渠道有误,请重新确认']);
            }
            $channels_sn = $channels_info['channels_sn'];
            $channels_name = $channels_info['channels_name'];
            $param_info['channels_sn'] = $channels_sn;
            $real_purchase_model = new RealPurchaseModel();
            //获取采购港口简称
            $port_id = intval($param_info['port_id']);
            $port_sn = $real_purchase_model->getPortSn($port_id);
            if (!$port_sn) {
                return response()->json(['code' => '1009', 'msg' => '您选择的港口信息有误,请重新确认']);
            }
            //检查上传商品的品牌是否在所选择的方式渠道存在折扣信息
            $discount_model = new DiscountModel();
            $brand_discount_goods_info = $discount_model->checkDiscountInfo($upload_goods_info, $param_info,
                $method_name, $channels_name);//停用
            if (isset($brand_discount_goods_info['code'])) {
                return response()->json($brand_discount_goods_info);
            }
            //检查任务模板id
            $task_id = intval($param_info['task_id']);
            $task_model = new TaskModel();
            $task_info = $task_model->getTaskInfoById($task_id);
            if (empty($task_info)) {
                return response()->json(['code' => '1022', 'msg' => '任务模板id错误']);
            }

            //获取采购自提或邮寄代码简称
            $path_way = $param_info['path_way'];
            $supplier_id = $param_info['supplier_id'];
            if ($path_way == 0) {
                $path_sn = "ZT";
                //检查该采购期自提方式是否存在
                $group_sn = $purchase_sn . '-' . $path_sn . '-' . $supplier_id;
            } elseif ($path_way == 1) {
                $path_sn = "YJ";
                //判断渠道、方式、港口、邮寄的选择是否存在
                $group_sn = $purchase_sn . "-" . $method_sn . "-" . $channels_sn . "-" . $path_sn . "-" . $port_sn . '-' .
                    $supplier_id;
            }
            $data_model = new DataModel();
            $return_info = $data_model->uploadPurchaseData($param_info, $group_sn, $upload_goods_info,
                $brand_discount_goods_info);
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:检查上传数据参数
     * editor:zongxing
     * type:POST
     * date : 2019.01.07
     * return Array
     */
    public function checkUploadData($param_info, &$return_info = [])
    {
        if (!isset($param_info['upload_file']) || empty($param_info['upload_file'])) {
            return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
        } elseif (!isset($param_info['demand_sn']) || empty($param_info['demand_sn'])) {
            return response()->json(['code' => '1014', 'msg' => '需求单号不能为空']);
        } elseif (!isset($param_info['purchase_sn']) || empty($param_info['purchase_sn'])) {
            $return_info = ['code' => '1006', 'msg' => '提供的采购期单号为空,请重新确认'];
        } elseif (!isset($param_info['method_id']) || empty($param_info['method_id'])) {
            $return_info = ['code' => '1007', 'msg' => '您选择的采购方式为空,请重新确认'];
        } elseif (!isset($param_info['channels_id']) || empty($param_info['channels_id'])) {
            $return_info = ['code' => '1008', 'msg' => '您选择的采购渠道为空,请重新确认'];
        } elseif (!isset($param_info['port_id']) || empty($param_info['port_id'])) {
            $return_info = ['code' => '1009', 'msg' => '您选择的采购港口为空,请重新确认'];
        } elseif (!isset($param_info['supplier_id']) || empty($param_info['supplier_id'])) {
            $return_info = ['code' => '1013', 'msg' => '您选择的供应商id为空,请重新确认'];
        } elseif (!isset($param_info['path_way'])) {
            $return_info = ['code' => '1014', 'msg' => '您选择的自提或邮寄方式为空,请重新确认'];
        } elseif ($param_info['path_way'] != 0 && $param_info['path_way'] != 1) {
            $return_info = ['code' => '1015', 'msg' => '您选择的自提或邮寄方式有误,请重新确认'];
        }
        return $return_info;
    }

    /**
     * description:销售模块-MIS订单管理管理-获取YD订单列表
     * editor:zongxing
     * date: 2018.12.10
     */
    public function misSubOrderList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $mis_order_sn = trim($param_info["mis_order_sn"]);
            //进行总订单状态的校验
            $mo_model = new MisOrderModel();
            $mis_order_info = $mo_model->getMisOrderInfo($mis_order_sn);
            //如果商品未进行挂靠和拆分,则返回
            if ($mis_order_info['status'] == 1) {
                return response()->json(['code' => '1003', 'msg' => '请先进行总订单挂靠']);
            } elseif ($mis_order_info['status'] == 2) {
                return response()->json(['code' => '1004', 'msg' => '请先进行总订单拆分']);
            }
            //通过MIS总单号获取下面可以发货的商品
            $mos_model = new MisOrderSubModel();
            $mis_sub_order_list = $mos_model->misSubOrderList($mis_order_sn);
            $mis_sub_order_list = objectToArrayZ($mis_sub_order_list);
            if (empty($mis_sub_order_list)) {
                return response()->json(['code' => '1005', 'msg' => '总单单号有误,请检查']);
            }
            $code = "1000";
            $msg = "获取YD订单列表成功";
            $data = $mis_sub_order_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-MIS订单管理管理-获取YD订单下可发货的商品
     * editor:zongxing
     * date: 2018.12.10
     */
    public function misCanDeliverGoods(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["sub_order_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '订单单号有误,请检查']);
            }
            $sub_order_sn = trim($param_info["sub_order_sn"]);
            //进行YD订单状态的校验,此操作是以防万一
            $mos_model = new MisOrderSubModel();
            $mis_order_sub_info = $mos_model->getSubOrderInfo($sub_order_sn);
            $mis_order_sub_info = objectToArrayZ($mis_order_sub_info);
            if ($mis_order_sub_info['status'] !== 'DD') {
                return response()->json(['code' => '1003', 'msg' => '该需求单暂不能发货,请检查']);
            }
            //获取YD订单下现货单中的商品
            $spot_goods_model = new SpotGoodsModel();
            $spot_goods_list = $spot_goods_model->getSpotGoods($sub_order_sn);
            $data["spot_goods_list"] = $spot_goods_list;
            //获取YD订单中的商品
            $mos_goods_model = new MisOrderSubGoodsModel();
            $mos_goods_list = $mos_goods_model->getSubDetail($sub_order_sn);
            $mos_goods_list = objectToArrayZ($mos_goods_list);
            if (empty($mos_goods_list)) {
                return response()->json(['code' => '1004', 'msg' => '需求单参数有误,请检查']);
            }
            //获取YD订单下需求单已分配的商品
            $usg_model = new UserSortGoodsModel();
            $user_sort_goods_list = $usg_model->getUserSortGoods($sub_order_sn);
            //获取YD订单下需求单已预采的商品
//            $rpd_model = new RealPurchaseDetailModel();
//            $predict_goods_list = $rpd_model->getPredictGoods($sub_order_sn);
            //获取YD订单下发货单中已经发货的商品
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_goods_list = $deliver_goods_model->getDeliverGoods($sub_order_sn);
            //对获取到的商品数据进行组装
            $data_arr = [
                'mos_goods_list' => $mos_goods_list,
                'user_sort_goods_list' => $user_sort_goods_list,
                //'predict_goods_list' => $predict_goods_list,
                'deliver_goods_list' => $deliver_goods_list,
            ];
            $order_sub_goods_info = $this->create_order_sub_goods($data_arr);
            $data["demand_goods_list"] = $order_sub_goods_info;
            $code = "1000";
            $msg = "获取YD订单下可发货的商品列表成功";
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:组装YD订单下可发货的商品
     * editor:zongxing
     * date: 2018.12.14
     */
    public function create_order_sub_goods($data_arr)
    {
        $mos_goods_list = $data_arr['mos_goods_list'];
        $user_sort_goods_list = $data_arr['user_sort_goods_list'];
        //$predict_goods_list = $data_arr['predict_goods_list'];
        $deliver_goods_list = $data_arr['deliver_goods_list'];

        //重组YD订单表中的商品信息
        $mos_real_goods = [];
        foreach ($mos_goods_list as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $v['can_deliver_num'] = 0;
            $mos_real_goods[$spec_sn] = $v;
        }
        //重组需求单分货表的商品信息
        foreach ($user_sort_goods_list as $k => $v) {
            $spec_sn = $v["spec_sn"];
            $handle_num = $v["handle_num"];
            if (isset($mos_real_goods[$spec_sn])) {
                $mos_real_goods[$spec_sn]["can_deliver_num"] += $handle_num;
            }
        }
        //重组预采批次表的商品信息
//        foreach ($predict_goods_list as $k => $v) {
//            $spec_sn = $v["spec_sn"];
//            $day_buy_num = $v["day_buy_num"];
//            if (isset($mos_real_goods[$spec_sn])) {
//                $mos_real_goods[$spec_sn]["can_deliver_num"] += $day_buy_num;
//            }
//        }
        //重组发货单已发货的商品信息
        foreach ($deliver_goods_list as $k => $v) {
            $spec_sn = $v["spec_sn"];
            $status = $v["order_sta"];
            $pre_ship_num = $v["pre_ship_num"];
            $diff_num = $v["diff_num"];
            if ($status == 1) {
                if (isset($mos_real_goods[$spec_sn])) {
                    $mos_real_goods[$spec_sn]["can_deliver_num"] -= $pre_ship_num;
                }
            } elseif ($status == 2) {
                if (isset($mos_real_goods[$spec_sn])) {
                    $already_deliver_num = $pre_ship_num - $diff_num;
                    $mos_real_goods[$spec_sn]["can_deliver_num"] -= $already_deliver_num;
                }
            }
        }
        $mos_real_goods = array_values($mos_real_goods);
        return $mos_real_goods;
    }

    /**
     * description:销售模块-MIS订单管理管理-生成发货单
     * editor:zongxing
     * date: 2018.12.15
     */
    public function comfirmDeliverGoods(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            //参数检查
            if (empty($param_info['sub_order_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '订单参数有误,请检查']);
            } elseif (empty($param_info['delivery_type'])) {
                return response()->json(['code' => '1008', 'msg' => '交货类别不能为空']);
            } elseif (empty($param_info['ship_type'])) {
                return response()->json(['code' => '1009', 'msg' => '运输方式不能为空']);
            }
            if (!empty($param_info["spot_order_sn"])) {
                $spot_order_sn = trim($param_info["spot_order_sn"]);
                $deliver_order_info['spot_order_sn'] = $spot_order_sn;
            }

            //获取发货单所属的销售用户id
            $sub_order_sn = trim($param_info["sub_order_sn"]);
            $mis_order_model = new MisOrderModel();
            $sale_user_info = $mis_order_model->getSaleUserOfDeliver($sub_order_sn);
            $sale_user_id = $sale_user_info['sale_user_id'];
            //获取回款规则
            $delivery_type = intval($param_info['delivery_type']);
            $ship_type = intval($param_info['ship_type']);
            $refund_rule_model = new RefundRulesModel();
            $refund_rule_info = $refund_rule_model->getRefundRuleInfo($sale_user_id, $delivery_type, $ship_type);
            if (empty($refund_rule_info)) {
                return response()->json(['code' => '1010', 'msg' => '该需求单所述的销售客户没有该交货类别或运输方式,请添加']);
            }
            //生成发货单号
            $pin_str = 'FH';
            $deliver_order_sn = makeRandNumber($pin_str);
            //组装发货单信息
            $deliver_order_info['deliver_order_sn'] = $deliver_order_sn;
            $deliver_order_info['sub_order_sn'] = $sub_order_sn;
            $deliver_order_info['sale_user_id'] = $sale_user_id;
            $deliver_order_info['refund_rule_id'] = $refund_rule_info['id'];
            //获取YD单对应的需求单中的商品
            $demand_goods_model = new DemandGoodsModel();
            $demand_goods_info = $demand_goods_model->getDemandGoodsOfYd($sub_order_sn);
            $demand_goods_list = [];
            foreach ($demand_goods_info as $k => $v) {
                $spec_sn = $v['spec_sn'];
                $demand_goods_list[$spec_sn] = $v;
            }
            //获取需求单中被选中的商品
            //$deliver_info = json_decode($param_info["deliver_info"], true);//postman 测试用的
            $order_amount = 0;
            $deliver_goods_info = [];
            if (!empty($param_info["deliver_info"])) {
                $deliver_info = $param_info["deliver_info"];
                foreach ($deliver_info as $k => $v) {
                    $spec_sn = trim($v['spec_sn']);
                    $pre_ship_num = intval($v['pre_ship_num']);
                    if (isset($demand_goods_list[$spec_sn])) {
                        $deliver_goods_info[$spec_sn] = [
                            'deliver_order_sn' => $deliver_order_sn,
                            'goods_name' => $demand_goods_list[$spec_sn]['goods_name'],
                            'spec_sn' => $spec_sn,
                            'pre_ship_num' => $pre_ship_num,
                            'goods_weight' => $demand_goods_list[$spec_sn]['spec_weight'],
                            'spec_price' => $demand_goods_list[$spec_sn]['spec_price'],
                            'sale_discount' => $demand_goods_list[$spec_sn]['sale_discount'],
                        ];
                        $spec_price = floatval($demand_goods_list[$spec_sn]['spec_price']);
                        $sale_discount = floatval($demand_goods_list[$spec_sn]['sale_discount']);
                        $total_price = $spec_price * $pre_ship_num * $sale_discount;
                        $order_amount += round($total_price, 2);
                    }
                }
            }

            //判断现货单是否被选中,如果被选中,修改现货的的状态,并将现货单商品数据导入到发货单
            if (!empty($param_info["spot_order_sn"])) {
                $spot_goods_model = new SpotGoodsModel();
                $spot_goods_info = $spot_goods_model->getSpotGoods($sub_order_sn);
                foreach ($spot_goods_info as $k => $v) {
                    $goods_name = trim($v['goods_name']);
                    $spec_sn = trim($v['spec_sn']);
                    $pre_ship_num = intval($v['goods_number']);
                    $spec_price = floatval($v['spec_price']);
                    $sale_discount = floatval($v['sale_discount']);
                    $spec_weight = floatval($v['spec_weight']);
                    $total_price = $spec_price * $pre_ship_num * $sale_discount;
                    $order_amount += round($total_price, 2);
                    if (isset($deliver_goods_info[$spec_sn])) {
                        $deliver_goods_info[$spec_sn]['pre_ship_num'] += $pre_ship_num;
                    } else {
                        $deliver_goods_info[$spec_sn] = [
                            'deliver_order_sn' => $deliver_order_sn,
                            'goods_name' => $goods_name,
                            'spec_sn' => $spec_sn,
                            'pre_ship_num' => $pre_ship_num,
                            'goods_weight' => $spec_weight,
                            'spec_price' => $spec_price,
                            'sale_discount' => $sale_discount,
                        ];
                    }
                }
            }
            $deliver_goods_info = array_values($deliver_goods_info);
            $deliver_order_info['order_amount'] = $order_amount;
            $deliver_order_model = new DeliverOrderModel();
            $insertRes = $deliver_order_model->makeDeliverOrder($deliver_order_info, $deliver_goods_info);
            if (!$insertRes) {
                return response()->json(['code' => '1006', 'msg' => '生成发货单失败']);
            }
            $code = "1000";
            $msg = "生成发货单成功";
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-MIS订单管理-需求单列表-编辑期望到仓日
     * editor:zongxing
     * type:POST
     * date : 2019.02.15
     * return Array
     */
    public function editArriveStoreTime(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            //参数检查
            if (empty($param_info['demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info['arrive_store_time'])) {
                return response()->json(['code' => '1003', 'msg' => '期望到仓日不能为空']);
            }
            //查询需求单信息
            $demand_sn = trim($param_info['demand_sn']);
            $demand_model = new DemandModel();
            $demand_info = $demand_model->getDemandInfo($demand_sn);
            if (empty($demand_info)) {
                return response()->json(['code' => '1004', 'msg' => '需求单号有误']);
            }
            //判断期望到仓日
            $expire_time = strtotime($demand_info['expire_time']);
            $arrive_store_time = strtotime($param_info['arrive_store_time']);
            if ($expire_time >= $arrive_store_time) {
                return response()->json(['code' => '1005', 'msg' => '期望到仓日必须大于采购截止日期']);
            }
            //更新需求单期望到仓日
            $update_data = [
                'arrive_store_time' => trim($param_info['arrive_store_time'])
            ];
            $res = $demand_model->updateDemandInfo($where, $update_data);
            if ($res == false) {
                return response()->json(['code' => '1006', 'msg' => '编辑期望到仓日失败']);
            }
            $return_info = ['code' => '1000', 'msg' => '编辑期望到仓日成功'];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:下载指定待分配商品数据
     * editor:zongxing
     * type:GET
     * date : 2019.02.26
     * params: 1.采购期编号:purchase_sn;2.需求单号:demand_sn;
     * return excel
     */
    public function downLoadWaitAllotGoodsInfo(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            if (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            } elseif (empty($param_info['demand_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '需求单号不能为空']);
            }

            $purchase_sn = trim($param_info['purchase_sn']);
            $demand_sn = trim($param_info['demand_sn']);
            $where = [
                ['pda.purchase_sn', '=', $purchase_sn],
                ['pde.demand_sn', '=', $demand_sn],
            ];
            $purchase_info = DB::table("purchase_date as pda")
                ->leftJoin("purchase_demand as pde", "pde.purchase_sn", "=", "pda.purchase_sn")
                ->where($where)->first(["method_info", "pde.demand_sn"]);
            $purchase_info = objectToArrayZ($purchase_info);
            if (empty($purchase_info["demand_sn"])) {
                return response()->json(['code' => '1005', 'msg' => '该采购期暂无需求信息']);
            }

            //获取当前采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_info);
            if (empty($discount_info)) {
                return response()->json(['code' => '1002', 'msg' => '暂无品牌折扣信息,请先维护折扣信息']);
            }

            //获取商品在某一采购期需求详情
            $purchase_demand_detail_model = new PurchaseDemandDetailModel();
            $purchase_demand_info = $purchase_demand_detail_model->createDemandDetail($param_info, $discount_info);

            $purchase_demand_info = $purchase_demand_info['purchase_demand_list'];
            $purchase_demand_list = [];
            foreach ($purchase_demand_info as $k => $v) {
                if (intval($v['allot_num']) != 0) {
                    $purchase_demand_list[] = $v;
                }
            }

            $obpe = new PHPExcel();
            $obpe->setActiveSheetIndex(0);
            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品规格码')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品名称')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '需求量')->getColumnDimension('E')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('F1', '可分配数')->getColumnDimension('F')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('G1', '渠道折扣及可采数')->getColumnDimension('G')->setWidth(20);

            //获取最大行数
            $row_last_i = count($purchase_demand_list) + 1;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 1) continue;
                $row_i = $i - 1;
                $real_i = $i + 1;

                $allot_num = intval($purchase_demand_list[$row_i]['allot_num']);

//                if ($allot_num == 0) {
//                    $obpe->setActiveSheetIndex(0)->getStyle('F' . $real_i)
//                        ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
//                }
                $obpe->getActiveSheet()->setCellValue("A" . $real_i, $purchase_demand_list[$row_i]["spec_sn"]);
                $obpe->getActiveSheet()->setCellValue("B" . $real_i, $purchase_demand_list[$row_i]["erp_prd_no"]);
                $obpe->getActiveSheet()->setCellValue("C" . $real_i, $purchase_demand_list[$row_i]["erp_merchant_no"]);
                $obpe->getActiveSheet()->setCellValue("D" . $real_i, $purchase_demand_list[$row_i]["goods_name"]);
                $obpe->getActiveSheet()->setCellValue("E" . $real_i, $purchase_demand_list[$row_i]["goods_num"]);
                $obpe->getActiveSheet()->setCellValue("F" . $real_i, $allot_num);

                $discount_info = $purchase_demand_list[$row_i]["discount_info"];
                foreach ($discount_info as $k => $v) {
                    $real_j = $k * 6;
                    $real_j_1 = $real_j + 6;
                    $real_j_2 = $real_j + 7;
                    $real_j_3 = $real_j + 8;
                    $real_j_4 = $real_j + 9;
                    $real_j_5 = $real_j + 10;
                    $real_j_6 = $real_j + 11;

                    $column_name1 = \PHPExcel_Cell::stringFromColumnIndex($real_j_1);
                    $column_name2 = \PHPExcel_Cell::stringFromColumnIndex($real_j_2);
                    $column_name3 = \PHPExcel_Cell::stringFromColumnIndex($real_j_3);
                    $column_name4 = \PHPExcel_Cell::stringFromColumnIndex($real_j_4);
                    $column_name5 = \PHPExcel_Cell::stringFromColumnIndex($real_j_5);
                    $column_name6 = \PHPExcel_Cell::stringFromColumnIndex($real_j_6);
                    if (!isset($discount_info[$k]["brand_channel"])) continue;
                    $obpe->getActiveSheet()->setCellValue($column_name1 . $real_i, '渠道-方式:')
                        ->getColumnDimension($column_name3)->setWidth(30);
                    $obpe->getActiveSheet()->setCellValue($column_name2 . $real_i, $discount_info[$k]['brand_channel'])
                        ->getColumnDimension($column_name2)->setWidth(15);

                    $obpe->getActiveSheet()->setCellValue($column_name3 . $real_i, '折扣:')
                        ->getColumnDimension($column_name3)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name4 . $real_i, $discount_info[$k]['brand_discount'])
                        ->getColumnDimension($column_name4)->setWidth(5);

                    $obpe->getActiveSheet()->setCellValue($column_name5 . $real_i, '可采数:')
                        ->getColumnDimension($column_name5)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name6 . $real_i, $discount_info[$k]['may_num'])
                        ->getColumnDimension($column_name6)->setWidth(5);
                }
            }

            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $obpe->getActiveSheet()->mergeCells('G1:' . $column_last_name . '1');

            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            $column_next_num = $column_last_num;
            $column_next_name = \PHPExcel_Cell::stringFromColumnIndex($column_next_num);

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;

            //改变表格标题样式
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_next_name, $row_end_i);
            $obpe->getActiveSheet()->setTitle('采购需求总表_优采推荐');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '优采推荐_可采数分配_' . $purchase_sn . '_' . $demand_sn . '_' . $str . '.xls';

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

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:上传需求单指定采购期下的分配数据
     * editor:zongxing
     * type:GET
     * date : 2019.02.28
     * params: 1.采购期编号:purchase_sn;2.需求单号:demand_sn;3.分配数据:upload_file;
     * return Array
     */
    public function uploadWaitAllotGoodsInfo(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            } elseif (empty($param_info['demand_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info['upload_file'])) {
                return response()->json(['code' => '1004', 'msg' => '上传文件不能为空']);
            }

            $file = $_FILES;
            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '优采推荐_可采数分配';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1009', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }

            $purchase_sn = trim($param_info['purchase_sn']);
            $demand_sn = trim($param_info['demand_sn']);
            //采购期需求表数据
            $where = [
                ['purchase_sn', '=', $purchase_sn],
                ['demand_sn', '=', $demand_sn],
            ];
            $purchase_demand_detail_info = DB::table('purchase_demand_detail')->where($where)->get();
            $purchase_demand_detail_info = objectToArrayZ($purchase_demand_detail_info);
            if (empty($purchase_demand_detail_info)) {
                return response()->json(['code' => '1007', 'msg' => '该采购期暂无需求信息']);
            }
            $purchase_demand_detail_list = [];
            foreach ($purchase_demand_detail_info as $k => $v) {
                $purchase_demand_detail_list[$v['spec_sn']] = $v;
            }

            //获取需求商品及品牌信息
            $demand_goods_model = new DemandGoodsModel();
            $demand_goods_info = $demand_goods_model->getDemandGoodsDetailInfo($demand_sn);

            //获取商品临时分配方案
            $dcg_model = new DemandChannelGoodsModel();
            $demand_channel_goods_info = $dcg_model->getDemandChannelGoodsInfo($demand_sn, $purchase_sn);
            $demand_channel_goods_list = [];
            foreach ($demand_channel_goods_info as $k => $v) {
                $tmp_spec_sn = $v['spec_sn'];
                $pin_str = $v['channels_name'] . '-' . $v['method_name'];
                $may_num = intval($v['may_num']);
                $id = intval($v['id']);
                $demand_channel_goods_list[$tmp_spec_sn][$pin_str]['may_num'] = $may_num;
                $demand_channel_goods_list[$tmp_spec_sn][$pin_str]['id'] = $id;
            }

            //获取采购期渠道商品统计表数据
            $pcg_model = new PurchaseChannelGoodsModel();
            $purchase_channel_goods_info = $pcg_model->getPurchseChannelGoodsInfo($purchase_sn);
            $purchase_channel_goods_list = [];
            foreach ($purchase_channel_goods_info as $k => $v) {
                $tmp_spec_sn = $v['spec_sn'];
                $pin_str = $v['channels_name'] . '-' . $v['method_name'];
                $may_num = intval($v['may_num']);
                $id = intval($v['id']);
                $purchase_channel_goods_list[$tmp_spec_sn][$pin_str]['may_num'] = $may_num;
                $purchase_channel_goods_list[$tmp_spec_sn][$pin_str]['id'] = $id;
            }

            $dcg_model = new DemandCountModel();
            $demand_count_info = $dcg_model->getDemandCountGoodsList($purchase_sn);
            if (empty($demand_count_info)) {
                return response()->json(['code' => '1015', 'msg' => '参数有误,请检查']);
            }

            //获取当前采购折扣数据
            $purchase_info = DB::table('purchase_date')->where('purchase_sn', $purchase_sn)->first(['method_info']);
            $purchase_info = objectToArrayZ($purchase_info);
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_info);

            if (empty($discount_info)) {
                return response()->json(['code' => '1008', 'msg' => '暂无品牌折扣信息,请先维护折扣信息']);
            }
            $discount_list = [];
            foreach ($discount_info as $k => $v) {
                $channels_name = $v['channels_name'];
                $method_name = $v['method_name'];
                $pin_str = $channels_name . '-' . $method_name;
                $discount_list[$v['brand_id']][$pin_str] = $v;
            }

            $diff_spec_sn = $diff_allot_spec_sn = $demand_channel_goods_info = $demand_goods_allot_info =
            $purchase_demand_spec_sn = [];
            $upload_goods_info = [];
            foreach ($res as $k => $v) {
                //检查标题
                if ($k < 1) continue;
                $column_num = count($v) - 1;
                $discount_num = $column_num / 6;
                //检查商品规格码
                if (empty($v[0])) continue;
                $spec_sn = trim($v[0]);
                if (!isset($demand_goods_info[$spec_sn])) {
                    $diff_spec_sn[] = $spec_sn;
                    continue;
                }

                $total_num = 0;
                for ($i = 0; $i < $discount_num; $i++) {
                    $real_j = $i * 6;
                    $real_j_2 = $real_j + 7;
                    $real_j_6 = $real_j + 11;
                    if (empty($v[$real_j_2]) || $v[$real_j_6] === null) continue;
                    $channel_method = trim($v[$real_j_2]);

                    //检查品牌折扣信息
                    $brand_id = $demand_goods_info[$spec_sn]['brand_id'];
                    if (!isset($discount_list[$brand_id][$channel_method])) {
                        $msg = '您上传的商品:' . $spec_sn . '在' . $channel_method . '折扣信息有误,请检查';
                        return response()->json(['code' => '1011', 'msg' => $msg]);
                    }

                    $may_num = intval($v[$real_j_6]);
                    $total_num += $may_num;
                    $upload_goods_info[$spec_sn][$channel_method] = $may_num;
                }

                //检查可分配数
                $allot_num = intval($demand_goods_info[$spec_sn]['goods_num']);
                if ($allot_num < $total_num) {
                    $diff_allot_spec_sn[] = $spec_sn;
                }
            }

            if (!empty($diff_spec_sn)) {
                $diff_spec_sn = json_encode($diff_spec_sn);
                $diff_spec_sn = substr($diff_spec_sn, 1, -1);
                return response()->json(['code' => '1010', 'msg' => '您上传的商品:' . $diff_spec_sn . '不存在需求信息,请检查']);
            }
            if (!empty($diff_allot_spec_sn)) {
                $diff_allot_spec_sn = json_encode($diff_allot_spec_sn);
                $diff_allot_spec_sn = substr($diff_allot_spec_sn, 1, -1);
                return response()->json(['code' => '1013', 'msg' => '您上传的商品:' . $diff_allot_spec_sn . '可采总数大于可分配数,请检查']);
            }

            $purchase_demand_spec_sn = [];
            $insert_demand_channel = $insert_purchase_channel = $updateDemandGoodsAllotNum = $updateDemandCountGoods =
            $updatePurchaseDemandGoods = [];
            foreach ($upload_goods_info as $k => $v) {
                $spec_sn = $k;
                $modify_num = intval($demand_goods_info[$spec_sn]['allot_num']);
                $demand_status = intval($demand_goods_info[$spec_sn]['status']);
                $demand_id = intval($demand_goods_info[$spec_sn]['id']);
                $brand_id = intval($demand_goods_info[$spec_sn]['brand_id']);

                //设置采购统计表可采数
                $demand_count_may_num = 0;
                if ($demand_status == 3 && isset($demand_count_info[$spec_sn])) {
                    $demand_count_may_num = $demand_count_info[$spec_sn]['may_buy_num'];
                    $demand_count_id = $demand_count_info[$spec_sn]['id'];
                }

                //设置采购需求商品表可采数
                $purchase_demand_may_num = 0;
                if (isset($purchase_demand_detail_list[$spec_sn])) {
                    $purchase_demand_may_num = $purchase_demand_detail_list[$spec_sn]['may_num'];
                    $purchase_demand_id = $purchase_demand_detail_list[$spec_sn]['id'];
                }

                $total_may_num = 0;
                foreach ($v as $k1 => $v1) {
                    $channel_method = $k1;//渠道名称
                    $may_num = intval($v1);//渠道可采数
                    $total_may_num += $may_num;//计算商品各个渠道可采数总和，当商品在采购期统计表中不存在时用

                    //设置采购渠道可采数
                    $pcg_may_num = 0;
                    if (isset($purchase_channel_goods_list[$spec_sn][$channel_method])) {
                        $pcg_may_num = intval($purchase_channel_goods_list[$spec_sn][$channel_method]['may_num']);
                    }

                    //需求渠道表
                    if (isset($demand_channel_goods_list[$spec_sn][$channel_method])) {
                        //计算需求商品表可分配数
                        $dcg_may_num = intval($demand_channel_goods_list[$spec_sn][$channel_method]['may_num']);

                        //组装需求渠道表更新数据
                        $dcg_id = intval($demand_channel_goods_list[$spec_sn][$channel_method]['id']);
                        $updateDemandChannelGoods['may_num'][] = [
                            $dcg_id => $may_num
                        ];

                        if ($dcg_may_num < $may_num) {
                            $diff_allot_num = $may_num - $dcg_may_num;
                            $modify_num -= $diff_allot_num;
                            $pcg_may_num += $diff_allot_num;
                            $demand_count_may_num += $diff_allot_num;
                            $purchase_demand_may_num += $diff_allot_num;
                        } else {
                            if (($dcg_may_num - $may_num) < 0) {
                                $msg = '您上传的商品:' . $spec_sn . '在' . $channel_method . '的可采数分配有误';
                                return response()->json(['code' => '1020', 'msg' => $msg]);
                            }
                            $diff_allot_num = $dcg_may_num - $may_num;
                            $modify_num += $diff_allot_num;
                            $pcg_may_num -= $diff_allot_num;
                            $demand_count_may_num -= $diff_allot_num;
                            $purchase_demand_may_num -= $diff_allot_num;
                        }
                    } else {
                        //组装需求渠道表新增数据
                        $insert_demand_channel[] = [
                            'demand_sn' => $demand_sn,
                            'purchase_sn' => $purchase_sn,
                            'spec_sn' => $spec_sn,
                            'method_sn' => $discount_list[$brand_id][$channel_method]['method_sn'],
                            'channels_sn' => $discount_list[$brand_id][$channel_method]['channels_sn'],
                            'channel_discount' => $discount_list[$brand_id][$channel_method]['brand_discount'],
                            'may_num' => $may_num,
                        ];
                        $modify_num -= $may_num;
                        $pcg_may_num += $may_num;
                        $demand_count_may_num += $may_num;
                        $purchase_demand_may_num += $may_num;
                    }
                    //采购渠道表
                    if (isset($purchase_channel_goods_list[$spec_sn][$channel_method])) {
                        //组装采购渠道表更新数据
                        $pcg_id = intval($purchase_channel_goods_list[$spec_sn][$channel_method]['id']);
                        $updatePurchaseChannelGoods['may_num'][] = [
                            $pcg_id => $pcg_may_num
                        ];
                    } else {
                        //组装采购渠道表新增数据
                        $insert_purchase_channel[] = [
                            'purchase_sn' => $purchase_sn,
                            'spec_sn' => $spec_sn,
                            'method_sn' => $discount_list[$brand_id][$channel_method]['method_sn'],
                            'channels_sn' => $discount_list[$brand_id][$channel_method]['channels_sn'],
                            'channel_discount' => $discount_list[$brand_id][$channel_method]['brand_discount'],
                            'may_num' => $may_num,
                        ];
                    }
                }
                if ($total_may_num) {
                    $purchase_demand_spec_sn[] = $spec_sn;
                }
                //组装需求商品表更新数据
                $updateDemandGoodsAllotNum['allot_num'][] = [
                    $demand_id => $modify_num
                ];
                //组装采购统计表更新数据
                if ($demand_status == 3 && isset($demand_count_info[$spec_sn])) {
                    $updateDemandCountGoods['may_buy_num'][] = [
                        $demand_count_id => $demand_count_may_num
                    ];
                }
                //组装采购期需求商品表更新数据
                if (isset($purchase_demand_detail_list[$spec_sn])) {
                    $updatePurchaseDemandGoods['may_num'][] = [
                        $purchase_demand_id => $purchase_demand_may_num
                    ];
                }
            }

            $updateDemandGoodsAllotNumSql = '';
            if (!empty($updateDemandGoodsAllotNum)) {
                //需要判断的字段
                $column = 'id';
                $updateDemandGoodsAllotNumSql = makeBatchUpdateSql('jms_demand_goods', $updateDemandGoodsAllotNum, $column);
            }
            $updateDemandCountGoodsSql = '';
            if (!empty($updateDemandCountGoods)) {
                $column = 'id';
                $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column);
            }

            $updatePurchaseChannelGoodsSql = '';
            if (!empty($updatePurchaseChannelGoods)) {
                $column = 'id';
                $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods', $updatePurchaseChannelGoods, $column);
            }
            $updatePurchaseDemandGoodsSql = '';
            if (!empty($updatePurchaseDemandGoods)) {
                $column = 'id';
                $updatePurchaseDemandGoodsSql = makeBatchUpdateSql('jms_purchase_demand_detail', $updatePurchaseDemandGoods, $column);
            }
            $updateDemandChannelGoodsSql = '';
            if (!empty($updateDemandChannelGoods)) {
                $column = 'id';
                $updateDemandChannelGoodsSql = makeBatchUpdateSql('jms_demand_channel_goods', $updateDemandChannelGoods, $column);
            }
            $updateRes = DB::transaction(function () use (
                $insert_purchase_channel, $insert_demand_channel, $updateDemandGoodsAllotNumSql, $purchase_demand_spec_sn,
                $purchase_sn, $demand_sn, $updateDemandCountGoodsSql, $updatePurchaseChannelGoodsSql,
                $updatePurchaseDemandGoodsSql, $updateDemandChannelGoodsSql
            ) {
                //新增采购期渠道商品表
                if (!empty($insert_purchase_channel)) {
                    DB::table('purchase_channel_goods')->insert($insert_purchase_channel);
                }
                //更新采购期渠道商品表
                if (!empty($updatePurchaseChannelGoodsSql)) {
                    DB::update(DB::raw($updatePurchaseChannelGoodsSql));
                }
                //新增需求渠道商品表
                if (!empty($insert_demand_channel)) {
                    DB::table('demand_channel_goods')->insert($insert_demand_channel);
                }
                //更新需求渠道商品表
                if (!empty($updateDemandChannelGoodsSql)) {
                    DB::update(DB::raw($updateDemandChannelGoodsSql));
                }
                //更新需求单商品表可分配数
                if (!empty($updateDemandGoodsAllotNumSql)) {
                    DB::update(DB::raw($updateDemandGoodsAllotNumSql));
                }
                //更新采购期需求商品表
                if (!empty($updatePurchaseDemandGoodsSql)) {
                    DB::update(DB::raw($updatePurchaseDemandGoodsSql));
                }
                //更新采购期统计表
                if (!empty($updateDemandCountGoodsSql)) {
                    DB::update(DB::raw($updateDemandCountGoodsSql));
                }

                //更新需求单临时分配方案
                if (!empty($purchase_demand_spec_sn)) {
                    $where = [
                        ['purchase_sn', '=', $purchase_sn],
                        ['demand_sn', '=', $demand_sn],
                    ];
                    $update_data = ['edit_status' => 1];
                    $update_res = DB::table('purchase_demand_detail')->where($where)
                        ->whereIn('spec_sn', $purchase_demand_spec_sn)->update($update_data);
                }
                return $update_res;
            });
            $return_info = ['code' => '1014', 'msg' => '批量导入临时分配方案失败'];
            if ($updateRes !== false) {
                $return_info = ['code' => '1000', 'msg' => '批量导入临时分配方案成功'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购需求列表
     * editor:zongxing
     * date: 2019.05.13
     */
    public function purchaseDemandList(Request $request)
    {
        $param_info = $request->toArray();
        $demand_model = new DemandModel();
        $demand_list_info = $demand_model->purchaseDemandList($param_info);
        $code = "1000";
        $msg = "获取待分配需求列表成功";
        $data = $demand_list_info;
        $return_info = compact('code', 'msg', 'data');
        if (empty($demand_list_info)) {
            $code = "1002";
            $msg = "暂无待分配需求列表";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购需求详情
     * editor:zongxing
     * date: 2019.05.27
     */
    public function purchaseDemandDetail(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['demand_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
        }

        //获取需求单商品信息
        $dg_model = new DemandGoodsModel();
        $demand_detail_list = $dg_model->demandGoodsInfo($param_info);
        if (empty($demand_detail_list)) {
            return response()->json(['code' => '1003', 'msg' => '需求单号错误']);
        }
        $demand_goods_total_list = $demand_detail_list;

        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();
        if (!empty($goods_label_info)) {
            $demand_goods_total_list = [];
            foreach ($demand_detail_list as $k => $v) {
                $goods_label = explode(',', $v['goods_label']);
                $tmp_goods_label = [];
                foreach ($goods_label_info as $k1 => $v1) {
                    $label_id = intval($v1['id']);
                    if (in_array($label_id, $goods_label)) {
                        $tmp_goods_label[] = $v1;
                    }
                }
                $v['goods_label_list'] = $tmp_goods_label;
                $demand_goods_total_list[] = $v;
            }
        }
        $return_info = ['code' => '1000', 'msg' => '获取采购期需求详情成功', 'data' => $demand_goods_total_list];
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购需求列表-合并需求单
     * editor:zongxing
     * date: 2019.05.13
     */
    public function sumPurchaseDemand(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['demand_sn_info'])) {
            return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
        } elseif (empty($param_info['sum_demand_name'])) {
            return response()->json(['code' => '1003', 'msg' => '合单名称不能为空']);
        }
        $sum_demand_name = trim($param_info['sum_demand_name']);
        if (strlen($sum_demand_name) > 20) {
            return response()->json(['code' => '1006', 'msg' => '合单名称长度不能超过20个字符']);
        }

        //获取参数中的订单信息
        $param_demand_info = json_decode(json_encode($param_info['demand_sn_info']), true);
        //$param_demand_info = json_decode($param_info['demand_sn_info'], true);
        $demand_info = [];
        $demand_sn_info = [];
        $total_sort_info = [];
        foreach ($param_demand_info as $k => $v) {
            $demand_sn = trim($v['demand_sn']);
            $demand_sn_info[] = $demand_sn;
            $sort = intval($v['sort']);
            $demand_info[$demand_sn] = $sort;
            if (in_array($sort, $total_sort_info)) {
                return response()->json(['code' => '1004', 'msg' => '需求单排序不能重复']);
            } else {
                $total_sort_info[] = $sort;
            }
        }
        //获取要合并的需求单对应的商品数据
        $dg_model = new DemandGoodsModel();
        $demand_goods_info = $dg_model->getDemandDetail($demand_sn_info);
        if (empty($demand_goods_info)) {
            return response()->json(['code' => '1006', 'msg' => '需求单错误']);
        }
        $demand_goods_detail = $dg_model->getGoodsByDemandSn($demand_sn_info);
        //合并需求单
        $sd_model = new SumDemandModel();
        $res = $sd_model->insertSumDemand($param_info, $demand_info, $demand_goods_info, $demand_goods_detail);
        $return_info = ['code' => '1005', 'msg' => '合并需求单失败'];
        if ($res !== false) {
            $return_info = ['code' => '1000', 'msg' => '合并需求单成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购需求列表-打开追加合单页面
     * editor:zongxing
     * date: 2019.05.29
     */
    public function addSumDemand()
    {
        //获取汇总需求单列表
        $param['is_zero'] = 1;
        $param['is_group'] = 1;
        $sum_model = new SumModel();
        $sum_info = $sum_model->getSumDemandInfo($param);
        if (empty($sum_info)) {
            return response()->json(['code' => '1003', 'msg' => '暂无符合需求汇总需求单']);
        }
        $sum_list = [];
        foreach ($sum_info as $k => $v) {
            $tmp_arr = [
                'sum_demand_sn' => $k,
                'sum_demand_name' => $v[0]['sum_demand_name'],
                'sum_id' => $v[0]['id'],
                'demand_info' => $v
            ];
            $sum_list[] = $tmp_arr;
        }
        $return_info = ['code' => '1000', 'msg' => '获取汇总需求单列表成功', 'data' => $sum_list];
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购需求列表-追加合单
     * editor:zongxing
     * date: 2019.05.29
     */
    public function doAddSumDemand(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['demand_sn_info'])) {
            return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
        } elseif (empty($param_info['sum_id'])) {
            return response()->json(['code' => '1003', 'msg' => '汇总需求单id不能为空']);
        } elseif (empty($param_info['sum_demand_sn'])) {
            return response()->json(['code' => '1008', 'msg' => '合单单号不能为空']);
        }
        //合单单号
        $sum_demand_sn = trim($param_info['sum_demand_sn']);
        //检查合单是否已经上传采购数据
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_info = $rpa_model->isSumUploadData($sum_demand_sn);
        if (!empty($rpa_info) && !isset($param_info['is_sure'])) {
            return response()->json(['code' => '1009', 'msg' => '该合单已上传采购数据,追加后不能进行拆分,是否确认追加?']);
        } elseif (isset($param_info['is_sure']) && intval($param_info['is_sure']) != 1) {
            return response()->json(['code' => '1010', 'msg' => '确认参数错误']);
        }
        //获取汇总需求单列表
        $param['sum_id'] = intval($param_info['sum_id']);
        $param['is_zero'] = 0;
        $sum_model = new SumModel();
        $sum_info = $sum_model->getSumDemandInfo($param);
        $param['sum_demand_sn'] = trim($sum_info[0]['sum_demand_sn']);
        if (empty($sum_info)) {
            return response()->json(['code' => '1004', 'msg' => '汇总需求单id错误']);
        }

        $demand_list = [];
        foreach ($sum_info as $k => $v) {
            $demand_list[$v['demand_sn']] = $v;
        }
        //获取参数中的订单信息
        $param_demand_info = json_decode(json_encode($param_info['demand_sn_info']), true);
        //$param_demand_info = json_decode($param_info['demand_sn_info'], true);
        $demand_sn_info = [];
        $update_demand_info = [];
        $total_sort_info = [];
        $add_demand_info = [];
        $add_demand_sn = [];
        foreach ($param_demand_info as $k => $v) {
            $demand_sn = trim($v['demand_sn']);
            $is_new = intval($v['is_new']);
            $demand_sn_info[] = $demand_sn;
            $new_sort = intval($v['sort']);
            if (in_array($new_sort, $total_sort_info)) {
                return response()->json(['code' => '1005', 'msg' => '需求单排序不能重复']);
            } else {
                $total_sort_info[] = $new_sort;
            }

            if (isset($demand_list[$demand_sn])) {
                $sd_id = intval($demand_list[$demand_sn]['sd_id']);
                $sd_status = intval($demand_list[$demand_sn]['sd_status']);
                if ($is_new == 1 && $sd_status == 1) {
                    return response()->json(['code' => '1007', 'msg' => '该需求单在选择合单中已经存在']);
                }
                if ($sd_status == 2) {
                    $add_demand_sn[] = $demand_sn;
                }
                $update_demand_info[$demand_sn] = [
                    'sd_id' => $sd_id,
                    'sort' => $new_sort,
                ];
            } else {
                $add_demand_sn[] = $demand_sn;
                $add_demand_info[$demand_sn] = $new_sort;
            }
        }
        //获取要合并的需求单对应的商品数据
        $add_goods_info = [];
        $demand_goods_detail = [];
        if (!empty($add_demand_sn)) {
            $dg_model = new DemandGoodsModel();
            $add_goods_info = $dg_model->getDemandDetail($add_demand_sn);
            if (empty($add_goods_info)) {
                return response()->json(['code' => '1007', 'msg' => '需求单号错误']);
            }
            $demand_goods_detail = $dg_model->getGoodsByDemandSn($demand_sn_info);
        }
        //合并需求单
        $sd_model = new SumDemandModel();
        $res = $sd_model->doAddSumDemand($param, $add_goods_info, $update_demand_info, $add_demand_info, $add_demand_sn,
            $demand_goods_detail);
        $return_info = ['code' => '1006', 'msg' => '追加合单失败'];
        if ($res !== false) {
            $return_info = ['code' => '1000', 'msg' => '追加合单成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购任务列表
     * editor:zongxing
     * date: 2019.05.13
     */
    public function purchaseTaskList(Request $request)
    {
        $param_info = $request->toArray();
        $s_model = new SumModel();
        $demand_list_info = $s_model->purchaseTaskList($param_info);
        $return_info = ['code' => '1002', 'msg' => '暂无采购任务列表'];
        if (!empty($demand_list_info) || (isset($demand_list_info['data']) && !empty($demand_list_info['data']))) {
            $return_info = ['code' => '1000', 'msg' => '获取采购任务列表成功', 'data' => $demand_list_info];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购任务列表-App
     * editor:zongxing
     * date: 2019.05.13
     */
    public function purchaseTaskListApp(Request $request)
    {
        $param_info = $request->toArray();
        $s_model = new SumModel();
        $demand_list_info = $s_model->purchaseTaskListApp($param_info);
        $return_info = ['code' => '1002', 'msg' => '暂无采购任务列表'];
        if (!empty($demand_list_info['data'])) {
            $return_info = ['code' => '1000', 'msg' => '获取采购任务列表成功', 'data' => $demand_list_info];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购任务管理-采购任务详情
     * editor:zongxing
     * date: 2019.05.14
     */
    public function purchaseTaskDetail(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['sum_demand_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
        }
        $data_type = isset($param_info['data_type']) ? intval($param_info['data_type']) : 1;
        //获取汇总单商品数据
        $sd_sn_arr[] = trim($param_info['sum_demand_sn']);
        $sg_model = new SumGoodsModel();
        $sum_demand_detail = $sg_model->purchaseTaskDetail($sd_sn_arr);
        if (empty($sum_demand_detail)) {
            return response()->json(['code' => '1003', 'msg' => '汇总单已关闭或单号错误']);
        }

        //获取采购成本折扣数据
        $discountModel = new DiscountModel();
        $discount_list = $discountModel->getTotalDiscount();
        //获取采购最终折扣数据
        $buy_time = date('Y-m-d');
        $dt_model = new DiscountTypeModel();
        $goods_discount_list = $dt_model->getFinalDiscount($sum_demand_detail, null, $buy_time);
        //获取商品在某一采购期需求详情
        $sg_model = new SumGoodsModel();
        $sd_goods_info = $sg_model->createSumDemandDetail($goods_discount_list, $discount_list, $data_type, $sd_sn_arr);
        if ($sd_goods_info == false) {
            return response()->json(['code' => '1004', 'msg' => '获取采购任务详情失败']);
        }
        $data = [
            'sum_demand_goods' => $sd_goods_info['sum_demand_goods'],
            'channel_arr' => array_values($sd_goods_info['channel_arr']),
            'demand_arr' => $sd_goods_info['demand_arr'],
            'sale_user_list' => $sd_goods_info['sale_user_list'],
            'expire_time_list' => $sd_goods_info['expire_time_list'],
            'dg_goods_num' => $sd_goods_info['dg_goods_num'],
            'cm_goods_num' => $sd_goods_info['cm_goods_num'],
        ];
        $return_info = ['code' => '1000', 'msg' => '获取采购任务详情成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:下载汇总单待分配商品数据
     * editor:zongxing
     * type:GET
     * date : 2019.05.14
     * params: 1.采购期编号:sum_demand_sn;
     * return excel
     */
    public function downLoadSumDemandGoodsInfo(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info['sum_demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
            } elseif (empty($param_info['data_type'])) {
                return response()->json(['code' => '1005', 'msg' => '数据类型不能为空']);
            }
            $data_type = intval($param_info['data_type']);
            //获取汇总单商品数据
            $sd_sn_arr[] = trim($param_info['sum_demand_sn']);
            $sg_model = new SumGoodsModel();
            $sum_demand_detail = $sg_model->purchaseTaskDetail($sd_sn_arr);
            if (empty($sum_demand_detail)) {
                return response()->json(['code' => '1003', 'msg' => '汇总单单号错误']);
            }
            //获取采购成本折扣数据
            $discountModel = new DiscountModel();
            $discount_list = $discountModel->getTotalDiscount();
            //获取采购最终折扣数据
            $dt_model = new DiscountTypeModel();
            $goods_discount_list = $dt_model->getFinalDiscount($sum_demand_detail);
            //获取商品在某一采购期需求详情
            $sg_model = new SumGoodsModel();
            $sd_goods_info = $sg_model->createSumDemandDetail($goods_discount_list, $discount_list, $data_type, $sd_sn_arr);
            if ($sd_goods_info == false) {
                return response()->json(['code' => '1004', 'msg' => '获取采购任务详情失败']);
            }
            $sum_demand_goods = $sd_goods_info['sum_demand_goods'];
            $channel_arr = array_values($sd_goods_info['channel_arr']);
            $demand_arr = $sd_goods_info['demand_arr'];
            $external_sn_list = $sd_goods_info['external_sn_list'];
            $sale_user_list = $sd_goods_info['sale_user_list'];
            $expire_time_list = $sd_goods_info['expire_time_list'];
            $obpe = new PHPExcel();
            $obpe->setActiveSheetIndex(0);
            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品规格码')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品参考码')->getColumnDimension('B')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品名称')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '美金原价')->getColumnDimension('E')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('F1', 'Livp价')->getColumnDimension('F')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('G1', '实付美金')->getColumnDimension('G')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('H1', '需求数')->getColumnDimension('H')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('I1', '可分配数')->getColumnDimension('I')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('J1', '实采数')->getColumnDimension('J')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('K1', '缺口数')->getColumnDimension('K')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('L1', '需求总额')->getColumnDimension('L')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('M1', '缺口总额')->getColumnDimension('M')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('N1', '采满率')->getColumnDimension('N')->setWidth(15);

            $demand_num = count($demand_arr) * 6;
            $demand_end_name = \PHPExcel_Cell::stringFromColumnIndex($demand_num + 14);
            $demand_start_column_num = \PHPExcel_Cell::columnIndexFromString('N');
            $type_start_column_num = \PHPExcel_Cell::columnIndexFromString($demand_end_name) - 1;
            //赋值需求单信息
            foreach ($sale_user_list as $k => $v) {
                //当前需求单的开始列数
                $real_k = $k * 6;
                for ($tmp_i = 0; $tmp_i < 6; $tmp_i++) {
                    $column_num = $real_k + $demand_start_column_num + $tmp_i;
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($column_num);
                    $sale_user = $v;
                    $expire_time = $expire_time_list[$k];
                    $demand_sn = $demand_arr[$k];
                    $external_sn = $external_sn_list[$demand_sn];
                    $obpe->getActiveSheet()->setCellValue($column_name . '1', $sale_user)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name . '2', $expire_time)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name . '3', $demand_sn)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name . '4', $external_sn)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $row_5_value = '';
                    if ($tmp_i == 0) {
                        $row_5_value = '需求数';
                    } elseif ($tmp_i == 1) {
                        $row_5_value = '实采数';
                    } elseif ($tmp_i == 2) {
                        $row_5_value = '缺口数';
                    } elseif ($tmp_i == 3) {
                        $row_5_value = '需求总额';
                    } elseif ($tmp_i == 4) {
                        $row_5_value = '缺口总额';
                    } elseif ($tmp_i == 5) {
                        $row_5_value = '采满率';
                    }
                    $obpe->getActiveSheet()->setCellValue($column_name . '5', $row_5_value)
                        ->getColumnDimension($column_name)->setWidth(10);
                }
            }
            //重组渠道信息
            $total_type_info = [];
            $total_column_num = 0;
            foreach ($channel_arr as $k => $v) {
                $method_num = 0;
                foreach ($v['channel_info'] as $k1 => $v1) {
                    $method_num += count($v1['discount_type']);
                    $total_column_num += count($v1['discount_type']);
                    array_push($total_type_info, $v1);
                }
                $channel_arr[$k]['method_num'] = $method_num;
            }
            //赋值渠道信息
            $start_num = $type_start_column_num;
            foreach ($channel_arr as $k => $v) {
                $method_name = $v['method_name'];
                $method_num = $v['method_num'];
                $start_num += $k;
                $method_column_name = \PHPExcel_Cell::stringFromColumnIndex($start_num);
                $obpe->getActiveSheet()->setCellValue($method_column_name . '1', $method_name)
                    ->getColumnDimension($method_column_name)->setWidth(10);
                $start_num += $method_num - 1;
                $method_end_name = \PHPExcel_Cell::stringFromColumnIndex($start_num);
                $obpe->getActiveSheet()->mergeCells($method_column_name . '1:' . $method_end_name . '1');
            }
            //设置格式
            $currentSheet = $obpe->getActiveSheet();
            $column_last_name = $currentSheet->getHighestColumn();
            $column_total_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            for ($m = 0; $m < $column_total_num; $m++) {
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($m);
                $column_1_value = $obpe->getActiveSheet()->getCell($column_name . 1)->getValue();
                $column_4_value = $obpe->getActiveSheet()->getCell($column_name . 4)->getValue();
                if ($column_1_value == '需求总额' || $column_1_value == '缺口总额' ||
                    $column_4_value == '需求总额' || $column_4_value == '缺口总额'
                ) {
                    $obpe->getActiveSheet()->getStyle($column_name)->getNumberFormat()
                        ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
                }
//                if ($column_1_value == '采满率' || $column_4_value == '采满率') {
//                    $obpe->getActiveSheet()->getStyle($column_name)->getNumberFormat()
//                        ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00);
//                }
            }

            //赋值渠道折扣等信息
            $tmp_type_num = $type_start_column_num;
            foreach ($total_type_info as $k => $v) {
                //当前渠道的开始列数
                $column_num = $tmp_type_num;
                foreach ($v['discount_type'] as $k1 => $v1) {
                    //设置当前渠道下的档位
                    $type_num = $k1 + $column_num;
                    //当前渠道的名称
                    $channles_name = $v['channels_name'];
                    $type_start_name = \PHPExcel_Cell::stringFromColumnIndex($type_num);
                    $obpe->getActiveSheet()->setCellValue($type_start_name . '2', $channles_name)
                        ->getColumnDimension($type_start_name)->setWidth(10);
                    $type_column_name = \PHPExcel_Cell::stringFromColumnIndex($type_num);
                    $obpe->getActiveSheet()->setCellValue($type_column_name . '3', $v1)
                        ->getColumnDimension($type_column_name)->setWidth(10);
                }
                //当前渠道的档位数
                $channel_column_num = count($v['discount_type']);
                $tmp_type_num += $channel_column_num;//计算第二个渠道的开始列数
            }

            //获取最大行数
            $row_total_i = count($sum_demand_goods) + 5;
            for ($i = 0; $i < $row_total_i; $i++) {
                if ($i == 0) continue;
                $row_i = $i + 1;
                if ($i < 5) continue;
                $real_i = $i - 5;
                $goods_num = intval($sum_demand_goods[$real_i]['goods_num']);
                $allot_num = intval($sum_demand_goods[$real_i]['allot_num']);
                $real_num = intval($sum_demand_goods[$real_i]['real_num']);
                $diff_num = intval($sum_demand_goods[$real_i]['diff_num']);
                $spec_price = number_format($sum_demand_goods[$real_i]['spec_price'], 2, '.', '');
                $sg_demand_price = number_format($sum_demand_goods[$real_i]['sg_demand_price'], 2, '.', '');
                $sg_diff_price = number_format($sum_demand_goods[$real_i]['sg_diff_price'], 2, '.', '');
                $sg_real_rate = number_format($sum_demand_goods[$real_i]['sg_real_rate'], 2, '.', '');
                $obpe->getActiveSheet()->setCellValue('A' . $row_i, $sum_demand_goods[$real_i]['spec_sn']);
                $obpe->getActiveSheet()->setCellValue('B' . $row_i, $sum_demand_goods[$real_i]['erp_ref_no']);
                $obpe->getActiveSheet()->setCellValue('C' . $row_i, $sum_demand_goods[$real_i]['erp_merchant_no']);
                $obpe->getActiveSheet()->setCellValue('D' . $row_i, $sum_demand_goods[$real_i]['goods_name']);
                $obpe->getActiveSheet()->setCellValue('E' . $row_i, $spec_price);
                $obpe->getActiveSheet()->setCellValue('F' . $row_i, $spec_price);
                $obpe->getActiveSheet()->setCellValue('G' . $row_i, $spec_price);
                $obpe->getActiveSheet()->setCellValue('H' . $row_i, $goods_num);
                $obpe->getActiveSheet()->setCellValue('I' . $row_i, $allot_num);
                $obpe->getActiveSheet()->setCellValue('J' . $row_i, $real_num);
                $obpe->getActiveSheet()->setCellValue('K' . $row_i, $diff_num);
                $obpe->getActiveSheet()->setCellValue('L' . $row_i, $sg_demand_price);
                $obpe->getActiveSheet()->setCellValue('M' . $row_i, $sg_diff_price);
                $obpe->getActiveSheet()->setCellValue('N' . $row_i, $sg_real_rate . '%');

                //给需求单赋值
                for ($j = 0; $j < $demand_num; $j = $j + 6) {
                    for ($tmp_j = 0; $tmp_j < 6; $tmp_j++) {
                        $real_column_num = $demand_start_column_num + $j + $tmp_j;
                        $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                        $channel_name_value = $obpe->getActiveSheet()->getCell($real_column_name . '3')->getValue();
                        if (isset($sum_demand_goods[$real_i]['demand_info'][$channel_name_value])) {
                            $goods_num = intval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['goods_num']);
                            $yet_num = intval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['yet_num']);
                            $diff_num = intval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['diff_num']);
                            $dg_demand_price = floatval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['dg_demand_price']);
                            $dg_diff_price = floatval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['dg_diff_price']);
                            $dg_real_rate = floatval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['dg_real_rate']);
                            if ($tmp_j == 0) {
                                $real_value = $goods_num;
                            } elseif ($tmp_j == 1) {
                                $real_value = $yet_num;
                            } elseif ($tmp_j == 2) {
                                $real_value = $diff_num;
                            } elseif ($tmp_j == 3) {
                                $real_value = $dg_demand_price;
                            } elseif ($tmp_j == 4) {
                                $real_value = $dg_diff_price;
                            } elseif ($tmp_j == 5) {
                                $real_value = $dg_real_rate . '%';
                            }
                            $obpe->getActiveSheet()->setCellValue($real_column_name . $row_i, $real_value);
                        }
                    }
                }

                for ($j = 0; $j < $total_column_num; $j++) {
                    $real_column_num = $type_start_column_num + $j;
                    $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                    $channel_name_value = $obpe->getActiveSheet()->getCell($real_column_name . '2')->getValue();
                    if (isset($sum_demand_goods[$real_i]['channels_info'][$channel_name_value])) {
                        $type_name_value = $obpe->getActiveSheet()->getCell($real_column_name . '3')->getValue();
                        if (isset($sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value])) {
                            if ($type_name_value == '最终折扣') {
                                $discount = $sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value]['discount'];
                                $sort = $sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value]['sort'];
                                $column_value = '【' . $sort . '】' . $discount;
                            } else {
                                $column_value = $sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value];
                            }
                            if ($data_type != 2 || ($data_type == 2 && $type_name_value != '可采数')) {
                                $obpe->getActiveSheet()->setCellValue($real_column_name . $row_i, $column_value);
                            }
                        }
                    }
                }
            }
            //总计数据
            $goods_num_info = $sd_goods_info['goods_num_info'];
            $dg_goods_num = $sd_goods_info['dg_goods_num'];
            $cm_goods_num = $sd_goods_info['cm_goods_num'];
            $total_info = array_merge($goods_num_info, $dg_goods_num);
            $total_info = array_merge($total_info, $cm_goods_num);
            $currentSheet = $obpe->getActiveSheet();
            $column_last_name = $currentSheet->getHighestColumn();
            $column_total_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            $row_last_num = $currentSheet->getHighestRow();
            $last_row_i = $row_last_num + 1;
            $obpe->getActiveSheet()->setCellValue('A' . $last_row_i, '总计');
            for ($m = 0; $m < $column_total_num; $m++) {
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($m);
                $column_1_value = $obpe->getActiveSheet()->getCell($column_name . 1)->getValue();
                $column_3_value = $obpe->getActiveSheet()->getCell($column_name . 3)->getValue();
                $column_4_value = $obpe->getActiveSheet()->getCell($column_name . 4)->getValue();
                if (isset($total_info[$column_1_value])) {
                    $last_row_value = $total_info[$column_1_value];
                    $obpe->getActiveSheet()->setCellValue($column_name . $last_row_i, $last_row_value);
                } elseif (isset($total_info[$column_3_value . $column_4_value])) {
                    $last_row_value = $total_info[$column_3_value . $column_4_value];
                    $obpe->getActiveSheet()->setCellValue($column_name . $last_row_i, $last_row_value);
                }
            }
            //合并需求单标题
            for ($i = 0; $i < $demand_num; $i = $i + 6) {
                $real_i = $i + $demand_start_column_num;
                $real_i_2 = $i + $demand_start_column_num + 5;
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($real_i);
                $column_2_name = \PHPExcel_Cell::stringFromColumnIndex($real_i_2);
                for ($j = 0; $j < 4; $j++) {
                    $real_j = $j + 1;
                    $obpe->getActiveSheet()->mergeCells($column_name . $real_j . ':' . $column_2_name . $real_j);
                }
            }

            //赋值渠道单元格
            $start_column_num = 14 + $demand_num;
            foreach ($channel_arr as $k => $v) {
                foreach ($v['channel_info'] as $k1 => $v1) {
                    $channel_column_num = count($v1['discount_type']);
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($start_column_num);
                    $last_channel_column_num = $start_column_num + $channel_column_num - 1;
                    $last_channel_column_name = \PHPExcel_Cell::stringFromColumnIndex($last_channel_column_num);
                    $obpe->getActiveSheet()->mergeCells($column_name . '2:' . $last_channel_column_name . '2');
                    for ($i = 0; $i < $channel_column_num; $i++) {
                        $tmp_start_column_num = $i + $start_column_num;
                        $tmp_column_name = \PHPExcel_Cell::stringFromColumnIndex($tmp_start_column_num);
                        $obpe->getActiveSheet()->mergeCells($tmp_column_name . '3:' . $tmp_column_name . '5');
                    }
                    $start_column_num += $channel_column_num;
                }
            }
            //复制最后三列数据
            if ($data_type == 1) {
                $last_column_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
                for ($m = 0; $m < 4; $m++) {
                    $real_column_num = $last_column_num + $m;
                    $column_weight = 15;
                    if ($m == 0) {
                        $column_name_value = '外采折扣';
                    } elseif ($m == 1) {
                        $column_name_value = '采购数量';
                    } elseif ($m == 2) {
                        $column_name_value = '是否为搭配(是/否)';
                    } elseif ($m == 3) {
                        $column_name_value = '搭配商品对应的规格码';
                        $column_weight = 25;
                    }
                    $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                    $obpe->getActiveSheet()->setCellValue($real_column_name . '1', $column_name_value)
                        ->getColumnDimension($real_column_name)->setWidth($column_weight);
                    $obpe->getActiveSheet()->mergeCells($real_column_name . '1:' . $real_column_name . '5');
                }
            }
            //合并标题单元格
            for ($s = 0; $s < 14; $s++) {
                $title_column_name = \PHPExcel_Cell::stringFromColumnIndex($s);
                $obpe->getActiveSheet()->mergeCells($title_column_name . '1:' . $title_column_name . '5');
            }

            //改变表格标题样式
            $column_last_name = $currentSheet->getHighestColumn();
            $row_last_num = $currentSheet->getHighestRow();
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, 'A', 1, $column_last_name, 1);
            $commonModel->changeTableContent($obpe, 'A', 2, $column_last_name, $row_last_num);
            $obpe->getActiveSheet()->setTitle('汇总需求单_优采推荐');

            //清除缓存
            ob_end_clean();
            //写入内容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel2007');

            $str = rand(1000, 9999);
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            if ($data_type == 1) {
                $filename = '汇总需求单_所有_';
            } else {
                $filename = '汇总需求单_缺口_';
            }
            $filename = $filename . $sum_demand_sn . '_' . $str . '.xls';

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

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:下载当日采购任务
     * editor:zongxing
     * type:GET
     * date : 2019.06.22
     * params: 1.采购期编号:sum_demand_sn;
     * return excel
     */
    public function downLoadTodayPurTask(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info['sum_demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
            }
            //获取汇总单商品数据
            $sd_sn_arr[] = trim($param_info['sum_demand_sn']);
            $sg_model = new SumGoodsModel();
            $sum_demand_detail = $sg_model->purchaseTaskDetail($sd_sn_arr);
            if (empty($sum_demand_detail)) {
                return response()->json(['code' => '1003', 'msg' => '汇总单单号错误']);
            }
            //获取采购成本折扣数据
            $discountModel = new DiscountModel();
            $discount_list = $discountModel->getTotalDiscount();
            //获取采购最终折扣数据
            $dt_model = new DiscountTypeModel();
            $goods_discount_list = $dt_model->getFinalDiscount($sum_demand_detail);
            //获取商品在某一采购期需求详情
            $data_type = 2;
            $is_check_time = 1;
            $sg_model = new SumGoodsModel();
            $sd_goods_info = $sg_model->createSumDemandDetail($goods_discount_list, $discount_list, $data_type, $sd_sn_arr, $is_check_time);
            if ($sd_goods_info == false) {
                return response()->json(['code' => '1004', 'msg' => '暂无当日采购任务，请联系采购部门主管进行分配任务']);
            }
            $sum_demand_goods = $sd_goods_info['sum_demand_goods'];
            $channel_arr = array_values($sd_goods_info['channel_arr']);
            $demand_arr = $sd_goods_info['demand_arr'];
            $external_sn_list = $sd_goods_info['external_sn_list'];
            $sale_user_list = $sd_goods_info['sale_user_list'];
            $expire_time_list = $sd_goods_info['expire_time_list'];
            $obpe = new PHPExcel();
            $obpe->setActiveSheetIndex(0);
            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品规格码')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品参考码')->getColumnDimension('B')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品名称')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '美金原价')->getColumnDimension('E')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('F1', 'Livp价')->getColumnDimension('F')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('G1', '实付美金')->getColumnDimension('G')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('H1', '需求数')->getColumnDimension('H')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('I1', '可分配数')->getColumnDimension('I')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('J1', '实采数')->getColumnDimension('J')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('K1', '缺口数')->getColumnDimension('K')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('L1', '需求总额')->getColumnDimension('L')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('M1', '缺口总额')->getColumnDimension('M')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('N1', '采满率')->getColumnDimension('N')->setWidth(15);

            $demand_num = count($demand_arr) * 6;
            $demand_end_name = \PHPExcel_Cell::stringFromColumnIndex($demand_num + 14);
            $demand_start_column_num = \PHPExcel_Cell::columnIndexFromString('N');
            $type_start_column_num = \PHPExcel_Cell::columnIndexFromString($demand_end_name) - 1;
            //赋值需求单信息
            foreach ($sale_user_list as $k => $v) {
                //当前需求单的开始列数
                $real_k = $k * 6;
                for ($tmp_i = 0; $tmp_i < 6; $tmp_i++) {
                    $column_num = $real_k + $demand_start_column_num + $tmp_i;
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($column_num);
                    $sale_user = $v;
                    $expire_time = $expire_time_list[$k];
                    $demand_sn = $demand_arr[$k];
                    $external_sn = $external_sn_list[$demand_sn];
                    $obpe->getActiveSheet()->setCellValue($column_name . '1', $sale_user)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name . '2', $expire_time)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name . '3', $demand_sn)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name . '4', $external_sn)
                        ->getColumnDimension($column_name)->setWidth(10);
                    $row_5_value = '';
                    if ($tmp_i == 0) {
                        $row_5_value = '需求数';
                    } elseif ($tmp_i == 1) {
                        $row_5_value = '实采数';
                    } elseif ($tmp_i == 2) {
                        $row_5_value = '缺口数';
                    } elseif ($tmp_i == 3) {
                        $row_5_value = '需求总额';
                    } elseif ($tmp_i == 4) {
                        $row_5_value = '缺口总额';
                    } elseif ($tmp_i == 5) {
                        $row_5_value = '采满率';
                    }
                    $obpe->getActiveSheet()->setCellValue($column_name . '5', $row_5_value)
                        ->getColumnDimension($column_name)->setWidth(10);
                }
            }
            //重组渠道信息
            $total_type_info = [];
            $total_column_num = 0;
            foreach ($channel_arr as $k => $v) {
                $method_num = 0;
                foreach ($v['channel_info'] as $k1 => $v1) {
                    $method_num += count($v1['discount_type']);
                    $total_column_num += count($v1['discount_type']);
                    array_push($total_type_info, $v1);
                }
                $channel_arr[$k]['method_num'] = $method_num;
            }
            //赋值渠道信息
            $start_num = $type_start_column_num;
            foreach ($channel_arr as $k => $v) {
                $method_name = $v['method_name'];
                $method_num = $v['method_num'];
                $start_num += $k;
                $method_column_name = \PHPExcel_Cell::stringFromColumnIndex($start_num);
                $obpe->getActiveSheet()->setCellValue($method_column_name . '1', $method_name)
                    ->getColumnDimension($method_column_name)->setWidth(10);
                $start_num += $method_num - 1;
                $method_end_name = \PHPExcel_Cell::stringFromColumnIndex($start_num);
                $obpe->getActiveSheet()->mergeCells($method_column_name . '1:' . $method_end_name . '1');
            }
            //设置格式
            $currentSheet = $obpe->getActiveSheet();
            $column_last_name = $currentSheet->getHighestColumn();
            $column_total_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            for ($m = 0; $m < $column_total_num; $m++) {
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($m);
                $column_1_value = $obpe->getActiveSheet()->getCell($column_name . 1)->getValue();
                $column_4_value = $obpe->getActiveSheet()->getCell($column_name . 4)->getValue();
                if ($column_1_value == '需求总额' || $column_1_value == '缺口总额' ||
                    $column_4_value == '需求总额' || $column_4_value == '缺口总额'
                ) {
                    $obpe->getActiveSheet()->getStyle($column_name)->getNumberFormat()
                        ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
                }
                if ($column_1_value == '采满率' || $column_4_value == '采满率') {
                    $obpe->getActiveSheet()->getStyle($column_name)->getNumberFormat()
                        ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00);
                }
            }

            //赋值渠道折扣等信息
            $tmp_type_num = $type_start_column_num;
            foreach ($total_type_info as $k => $v) {
                //当前渠道的开始列数
                $column_num = $tmp_type_num;
                foreach ($v['discount_type'] as $k1 => $v1) {
                    //设置当前渠道下的档位
                    $type_num = $k1 + $column_num;
                    //当前渠道的名称
                    $channles_name = $v['channels_name'];
                    $type_start_name = \PHPExcel_Cell::stringFromColumnIndex($type_num);
                    $obpe->getActiveSheet()->setCellValue($type_start_name . '2', $channles_name)
                        ->getColumnDimension($type_start_name)->setWidth(10);
                    $type_column_name = \PHPExcel_Cell::stringFromColumnIndex($type_num);
                    $obpe->getActiveSheet()->setCellValue($type_column_name . '3', $v1)
                        ->getColumnDimension($type_column_name)->setWidth(10);
                }
                //当前渠道的档位数
                $channel_column_num = count($v['discount_type']);
                $tmp_type_num += $channel_column_num;//计算第二个渠道的开始列数
            }

            //获取最大行数
            $row_total_i = count($sum_demand_goods) + 5;
            for ($i = 0; $i < $row_total_i; $i++) {
                if ($i == 0) continue;
                $row_i = $i + 1;
                if ($i < 5) continue;
                $real_i = $i - 5;
                $goods_num = intval($sum_demand_goods[$real_i]['goods_num']);
                $allot_num = intval($sum_demand_goods[$real_i]['allot_num']);
                $real_num = intval($sum_demand_goods[$real_i]['real_num']);
                $diff_num = intval($sum_demand_goods[$real_i]['diff_num']);
                $spec_price = number_format($sum_demand_goods[$real_i]['spec_price'], 2, '.', '');
                $sg_demand_price = number_format($sum_demand_goods[$real_i]['sg_demand_price'], 2, '.', '');
                $sg_diff_price = number_format($sum_demand_goods[$real_i]['sg_diff_price'], 2, '.', '');
                $sg_real_rate = floatval($sum_demand_goods[$real_i]['sg_real_rate']);
                $obpe->getActiveSheet()->setCellValue('A' . $row_i, $sum_demand_goods[$real_i]['spec_sn']);
                $obpe->getActiveSheet()->setCellValue('B' . $row_i, $sum_demand_goods[$real_i]['erp_ref_no']);
                $obpe->getActiveSheet()->setCellValue('C' . $row_i, $sum_demand_goods[$real_i]['erp_merchant_no']);
                $obpe->getActiveSheet()->setCellValue('D' . $row_i, $sum_demand_goods[$real_i]['goods_name']);
                $obpe->getActiveSheet()->setCellValue('E' . $row_i, $spec_price);
                $obpe->getActiveSheet()->setCellValue('F' . $row_i, $spec_price);
                $obpe->getActiveSheet()->setCellValue('G' . $row_i, $goods_num);
                $obpe->getActiveSheet()->setCellValue('H' . $row_i, $allot_num);
                $obpe->getActiveSheet()->setCellValue('I' . $row_i, $real_num);
                $obpe->getActiveSheet()->setCellValue('J' . $row_i, $diff_num);
                $obpe->getActiveSheet()->setCellValue('K' . $row_i, $sg_demand_price);
                $obpe->getActiveSheet()->setCellValue('L' . $row_i, $sg_diff_price);
                $obpe->getActiveSheet()->setCellValue('M' . $row_i, $sg_real_rate . '%');

                //给需求单赋值
                for ($j = 0; $j < $demand_num; $j = $j + 6) {
                    for ($tmp_j = 0; $tmp_j < 6; $tmp_j++) {
                        $real_column_num = $demand_start_column_num + $j + $tmp_j;
                        $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                        $channel_name_value = $obpe->getActiveSheet()->getCell($real_column_name . '3')->getValue();
                        if (isset($sum_demand_goods[$real_i]['demand_info'][$channel_name_value])) {
                            $goods_num = intval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['goods_num']);
                            $yet_num = intval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['yet_num']);
                            $diff_num = intval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['diff_num']);
                            $dg_demand_price = floatval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['dg_demand_price']);
                            $dg_diff_price = floatval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['dg_diff_price']);
                            $dg_real_rate = floatval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]['dg_real_rate']);
                            if ($tmp_j == 0) {
                                $real_value = $goods_num;
                            } elseif ($tmp_j == 1) {
                                $real_value = $yet_num;
                            } elseif ($tmp_j == 2) {
                                $real_value = $diff_num;
                            } elseif ($tmp_j == 3) {
                                $real_value = $dg_demand_price;
                            } elseif ($tmp_j == 4) {
                                $real_value = $dg_diff_price;
                            } elseif ($tmp_j == 5) {
                                $real_value = $dg_real_rate . '%';
                            }
                            $obpe->getActiveSheet()->setCellValue($real_column_name . $row_i, $real_value);
                        }
                    }
                }

                for ($j = 0; $j < $total_column_num; $j++) {
                    $real_column_num = $type_start_column_num + $j;
                    $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                    $channel_name_value = $obpe->getActiveSheet()->getCell($real_column_name . '2')->getValue();
                    if (isset($sum_demand_goods[$real_i]['channels_info'][$channel_name_value])) {
                        $type_name_value = $obpe->getActiveSheet()->getCell($real_column_name . '3')->getValue();
                        if (isset($sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value])) {
                            if ($type_name_value == '最终折扣') {
                                $discount = $sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value]['discount'];
                                $sort = $sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value]['sort'];
                                $column_value = '【' . $sort . '】' . $discount;
                            } else {
                                $column_value = $sum_demand_goods[$real_i]['channels_info'][$channel_name_value][$type_name_value];
                            }

                            if ($data_type != 2 || ($data_type == 2 && $type_name_value != '可采数')) {
                                $obpe->getActiveSheet()->setCellValue($real_column_name . $row_i, $column_value);
                            }
                        }
                    }
                }
            }

            //总计数据
            $goods_num_info = $sd_goods_info['goods_num_info'];
            $dg_goods_num = $sd_goods_info['dg_goods_num'];
            $cm_goods_num = $sd_goods_info['cm_goods_num'];
            $total_info = array_merge($goods_num_info, $dg_goods_num);
            $total_info = array_merge($total_info, $cm_goods_num);
            $currentSheet = $obpe->getActiveSheet();
            $column_last_name = $currentSheet->getHighestColumn();
            $column_total_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            $row_last_num = $currentSheet->getHighestRow();
            $last_row_i = $row_last_num + 1;
            $obpe->getActiveSheet()->setCellValue('A' . $last_row_i, '总计');
            for ($m = 0; $m < $column_total_num; $m++) {
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($m);
                $column_1_value = $obpe->getActiveSheet()->getCell($column_name . 1)->getValue();
                $column_3_value = $obpe->getActiveSheet()->getCell($column_name . 3)->getValue();
                $column_4_value = $obpe->getActiveSheet()->getCell($column_name . 4)->getValue();
                if (isset($total_info[$column_1_value])) {
                    $last_row_value = $total_info[$column_1_value];
                    $obpe->getActiveSheet()->setCellValue($column_name . $last_row_i, $last_row_value);
                } elseif (isset($total_info[$column_3_value . $column_4_value])) {
                    $last_row_value = $total_info[$column_3_value . $column_4_value];
                    $obpe->getActiveSheet()->setCellValue($column_name . $last_row_i, $last_row_value);
                }
            }
            //合并需求单标题
            for ($i = 0; $i < $demand_num; $i = $i + 6) {
                $real_i = $i + $demand_start_column_num;
                $real_i_2 = $i + $demand_start_column_num + 5;
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($real_i);
                $column_2_name = \PHPExcel_Cell::stringFromColumnIndex($real_i_2);
                for ($j = 0; $j < 4; $j++) {
                    $real_j = $j + 1;
                    $obpe->getActiveSheet()->mergeCells($column_name . $real_j . ':' . $column_2_name . $real_j);
                }
            }

            //赋值渠道单元格
            $start_column_num = 14 + $demand_num;
            foreach ($channel_arr as $k => $v) {
                foreach ($v['channel_info'] as $k1 => $v1) {
                    $channel_column_num = count($v1['discount_type']);
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($start_column_num);
                    $last_channel_column_num = $start_column_num + $channel_column_num - 1;
                    $last_channel_column_name = \PHPExcel_Cell::stringFromColumnIndex($last_channel_column_num);
                    $obpe->getActiveSheet()->mergeCells($column_name . '2:' . $last_channel_column_name . '2');
                    for ($i = 0; $i < $channel_column_num; $i++) {
                        $tmp_start_column_num = $i + $start_column_num;
                        $tmp_column_name = \PHPExcel_Cell::stringFromColumnIndex($tmp_start_column_num);
                        $obpe->getActiveSheet()->mergeCells($tmp_column_name . '3:' . $tmp_column_name . '5');
                    }
                    $start_column_num += $channel_column_num;
                }
            }
            //复制最后三列数据
            if ($data_type == 1) {
                $last_column_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
                for ($m = 0; $m < 4; $m++) {
                    $real_column_num = $last_column_num + $m;
                    $column_weight = 15;
                    if ($m == 0) {
                        $column_name_value = '外采折扣';
                    } elseif ($m == 1) {
                        $column_name_value = '采购数量';
                    } elseif ($m == 2) {
                        $column_name_value = '是否为搭配(是/否)';
                    } elseif ($m == 3) {
                        $column_name_value = '搭配商品对应的规格码';
                        $column_weight = 25;
                    }
                    $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                    $obpe->getActiveSheet()->setCellValue($real_column_name . '1', $column_name_value)
                        ->getColumnDimension($real_column_name)->setWidth($column_weight);
                    $obpe->getActiveSheet()->mergeCells($real_column_name . '1:' . $real_column_name . '5');
                }
            }
            //合并标题单元格
            for ($s = 0; $s < 14; $s++) {
                $title_column_name = \PHPExcel_Cell::stringFromColumnIndex($s);
                $obpe->getActiveSheet()->mergeCells($title_column_name . '1:' . $title_column_name . '5');
            }

            //改变表格标题样式
            $column_last_name = $currentSheet->getHighestColumn();
            $row_last_num = $currentSheet->getHighestRow();
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, 'A', 1, $column_last_name, 1);
            $commonModel->changeTableContent($obpe, 'A', 2, $column_last_name, $row_last_num);
            $obpe->getActiveSheet()->setTitle('汇总需求单_优采推荐');

            //清除缓存
            ob_end_clean();
            //写入内容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel2007');

            $str = rand(1000, 9999);
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            $filename = '当日采购任务表_' . $sum_demand_sn . '_' . $str . '.xls';

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

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:下载汇总单待分配商品数据(无序)
     * editor:zongxing
     * type:GET
     * date : 2019.05.30
     * params: 1.采购期编号:sum_demand_sn;
     * return excel
     */
    public function downLoadSdgInfoNoSort(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info['sum_demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
            }
            //获取汇总单商品数据
            $sd_sn_arr[] = trim($param_info['sum_demand_sn']);
            $sg_model = new SumGoodsModel();
            $sum_demand_detail = $sg_model->purchaseTaskDetail($sd_sn_arr);
            if (empty($sum_demand_detail)) {
                return response()->json(['code' => '1003', 'msg' => '汇总单单号错误']);
            }

//            //获取当前采购折扣数据
//            $discountModel = new DiscountModel();
//            $discount_list = $discountModel->getTotalDiscount();
//
//            //获取商品在某一采购期需求详情
//            $sg_model = new SumGoodsModel();
//            $sd_goods_info = $sg_model->createSddNoSort($sum_demand_detail, $discount_list, $sd_sn_arr);

            //获取采购成本折扣数据
            $discountModel = new DiscountModel();
            $discount_list = $discountModel->getTotalDiscount();
            //获取采购最终折扣数据
            $dt_model = new DiscountTypeModel();
            $goods_discount_list = $dt_model->getFinalDiscount($sum_demand_detail);
            //获取商品在某一采购期需求详情
            $data_type = 2;
            $is_check_time = 1;
            $sg_model = new SumGoodsModel();
            $sd_goods_info = $sg_model->createSumDemandDetail($goods_discount_list, $discount_list, $data_type, $sd_sn_arr, $is_check_time);

            $sum_demand_goods = $sd_goods_info['sum_demand_goods'];
            $channel_arr = array_values($sd_goods_info['channel_arr']);
            $demand_arr = $sd_goods_info['demand_arr'];
            $sale_user_list = $sd_goods_info['sale_user_list'];
            $expire_time_list = $sd_goods_info['expire_time_list'];
            $obpe = new PHPExcel();
            $obpe->setActiveSheetIndex(0);
            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品规格码')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品名称')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '需求数')->getColumnDimension('E')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('F1', '可分配数')->getColumnDimension('F')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('G1', '需求单分布')->getColumnDimension('G')->setWidth(20);

            $demand_num = count($demand_arr);
            $demand_end_name = \PHPExcel_Cell::stringFromColumnIndex($demand_num + 5);
            $demand_start_column_num = \PHPExcel_Cell::columnIndexFromString('F');
            $type_start_column_num = \PHPExcel_Cell::columnIndexFromString($demand_end_name);

            //赋值需求单信息
            foreach ($sale_user_list as $k => $v) {
                //当前需求单的开始列数
                $column_num = $k + $demand_start_column_num;
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($column_num);
                $sale_user = $v;
                $expire_time = $expire_time_list[$k];
                $demand_sn = $demand_arr[$k];
                $obpe->getActiveSheet()->setCellValue($column_name . '1', $sale_user)
                    ->getColumnDimension($column_name)->setWidth(20);
                $obpe->getActiveSheet()->setCellValue($column_name . '2', $expire_time)
                    ->getColumnDimension($column_name)->setWidth(20);
                $obpe->getActiveSheet()->setCellValue($column_name . '3', $demand_sn)
                    ->getColumnDimension($column_name)->setWidth(20);
            }

            $total_type_info = [];
            $total_column_num = 0;
            foreach ($channel_arr as $k => $v) {
                $method_num = 0;
                foreach ($v['channel_info'] as $k1 => $v1) {
                    $method_num += count($v1['discount_type']);
                    $total_column_num += count($v1['discount_type']);
                    array_push($total_type_info, $v1);
                }
                $channel_arr[$k]['method_num'] = $method_num;
            }

            //获取最大行数
            $demand_goods_num_i = count($sum_demand_goods) * 3;
            for ($i = 0; $i < $demand_goods_num_i; $i = $i + 3) {
                $row_i = $i + 4;
                $real_i = ($row_i - 4) / 3;
                $goods_num = intval($sum_demand_goods[$real_i]['goods_num']);
                $allot_num = intval($sum_demand_goods[$real_i]['allot_num']);
                $obpe->getActiveSheet()->setCellValue('A' . $row_i, $sum_demand_goods[$real_i]['spec_sn']);
                $obpe->getActiveSheet()->setCellValue('B' . $row_i, $sum_demand_goods[$real_i]['erp_ref_no']);
                $obpe->getActiveSheet()->setCellValue('C' . $row_i, $sum_demand_goods[$real_i]['erp_merchant_no']);
                $obpe->getActiveSheet()->setCellValue('D' . $row_i, $sum_demand_goods[$real_i]['goods_name']);
                $obpe->getActiveSheet()->setCellValue('E' . $row_i, $goods_num);
                $obpe->getActiveSheet()->setCellValue('F' . $row_i, $allot_num);
                //给需求单赋值
                for ($j = 0; $j < $demand_num; $j++) {
                    $real_column_num = $demand_start_column_num + $j;
                    $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                    $channel_name_value = $obpe->getActiveSheet()->getCell($real_column_name . '3')->getValue();
                    if (isset($sum_demand_goods[$real_i]['demand_info'][$channel_name_value])) {
                        $column_value = intval($sum_demand_goods[$real_i]['demand_info'][$channel_name_value]);
                        $obpe->getActiveSheet()->setCellValue($real_column_name . $row_i, $column_value);
                    }
                }
                //合并商品和需求单信息单元格
                $demand_str_num = $demand_num + 6;
                for ($x = 0; $x < $demand_str_num; $x++) {
                    $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($x);
                    $end_row_num = $row_i + 2;
                    $obpe->getActiveSheet()->mergeCells($real_column_name . $row_i . ':' . $real_column_name . $end_row_num);
                }
                $channels_info = $sum_demand_goods[$real_i]['channels_info'];
                $channels_list = [];

                foreach ($channels_info as $k => $v) {
                    foreach ($v as $k1 => $v1) {
                        $channels_list[$k][] = [
                            'channel_name' => $k,
                            'type_info' => $k1,
                            'num_info' => $v1,
                        ];
                    }
                }
                $channels_list = array_values($channels_list);
                $tmp_start_column_num = $type_start_column_num;
                foreach ($channels_list as $k => $v) {
                    //$tmp_start_column_name = \PHPExcel_Cell::stringFromColumnIndex($tmp_start_column_num);
                    foreach ($v as $k1 => $v1) {
                        $type_column_num = $k1 + $tmp_start_column_num;
                        $type_column_name = \PHPExcel_Cell::stringFromColumnIndex($type_column_num);
                        $obpe->getActiveSheet()->setCellValue($type_column_name . $row_i, $v1['channel_name'])
                            ->getColumnDimension($type_column_name)->setWidth(20);
                        $row_i_2 = $row_i + 1;
                        $row_i_3 = $row_i + 2;
                        $type_info = $v1['type_info'];
                        $num_info = $v1['num_info'];
                        $obpe->getActiveSheet()->setCellValue($type_column_name . $row_i_2, $v1['type_info'])
                            ->getColumnDimension($type_column_name)->setWidth(15);
                        if ($type_info == '最终折扣') {
                            $num_info = '【' . $v1['num_info']['sort'] . '】' . $v1['num_info']['discount'];
                        }
                        $obpe->getActiveSheet()->setCellValue($type_column_name . $row_i_3, $num_info)
                            ->getColumnDimension($type_column_name)->setWidth(15);
                    }
                    //$type_num = count($v) - 1;
                    //$tmp_end_column_num = $tmp_start_column_num + $type_num;
                    $tmp_start_column_num += count($v);
                    //$tmp_end_column_name = \PHPExcel_Cell::stringFromColumnIndex($tmp_end_column_num);
                    //$obpe->getActiveSheet()->mergeCells($tmp_start_column_name . $row_i . ':' . $tmp_end_column_name . $row_i);
                }
            }
            //合并渠道单元格
            $goods_num_info = $sd_goods_info['goods_num_info'];
            $dg_goods_num = $sd_goods_info['dg_goods_num'];
            $cm_goods_num = $sd_goods_info['cm_goods_num'];
            $total_info = array_merge($goods_num_info, $dg_goods_num);
            $total_info = array_merge($total_info, $cm_goods_num);
            $currentSheet = $obpe->getActiveSheet();
            $column_last_name = $currentSheet->getHighestColumn();
            $column_total_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            $row_last_num = $currentSheet->getHighestRow();
            $last_row_i = $row_last_num + 1;
            $obpe->getActiveSheet()->setCellValue('A' . $last_row_i, '总计');

            for ($m = 0; $m < $column_total_num; $m++) {
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($m);
                $column_1_value = $obpe->getActiveSheet()->getCell($column_name . 1)->getValue();
                $column_3_value = $obpe->getActiveSheet()->getCell($column_name . 3)->getValue();
                if (isset($total_info[$column_1_value])) {
                    $last_row_value = $total_info[$column_1_value];
                    $obpe->getActiveSheet()->setCellValue($column_name . $last_row_i, $last_row_value);
                } elseif (isset($total_info[$column_3_value])) {
                    $last_row_value = $total_info[$column_3_value];
                    $obpe->getActiveSheet()->setCellValue($column_name . $last_row_i, $last_row_value);
                }
            }
            for ($i = 0; $i < $demand_goods_num_i; $i = $i + 3) {
                $row_i = $i + 4;
                for ($m = $demand_num + 6; $m < 25; $m++) {
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($m);
                    $column_2_value = $obpe->getActiveSheet()->getCell($column_name . $row_i)->getValue();
                    $row_i_3 = $row_i + 1;
                    $column_3_value = $obpe->getActiveSheet()->getCell($column_name . $row_i_3)->getValue();
                    if (isset($total_info[$column_2_value]) && $column_3_value == '可采数') {
                        $last_row_value = $total_info[$column_2_value];
                        $obpe->getActiveSheet()->setCellValue($column_name . $last_row_i, $last_row_value);
                    }
                }
                $tmp_start_column_num = $type_start_column_num;
                foreach ($channels_list as $k => $v) {
                    $tmp_start_column_name = \PHPExcel_Cell::stringFromColumnIndex($tmp_start_column_num);
                    $type_num = count($v) - 1;
                    $tmp_end_column_num = $tmp_start_column_num + $type_num;
                    $tmp_start_column_num += count($v);
                    $tmp_end_column_name = \PHPExcel_Cell::stringFromColumnIndex($tmp_end_column_num);
                    $obpe->getActiveSheet()->mergeCells($tmp_start_column_name . $row_i . ':' . $tmp_end_column_name . $row_i);
                }
            }

            $last_column_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            for ($m = 0; $m < 3; $m++) {
                $real_column_num = $last_column_num + $m;
                $column_weight = 15;
                if ($m == 0) {
                    $column_name_value = '采购数量';
                } elseif ($m == 1) {
                    $column_name_value = '是否为搭配(是/否)';
                } elseif ($m == 2) {
                    $column_name_value = '搭配商品对应的规格码';
                    $column_weight = 25;
                }
                $real_column_name = \PHPExcel_Cell::stringFromColumnIndex($real_column_num);
                $obpe->getActiveSheet()->setCellValue($real_column_name . '1', $column_name_value)
                    ->getColumnDimension($real_column_name)->setWidth($column_weight);
            }

            //合并标题单元格
            for ($s = 0; $s < 6; $s++) {
                $title_column_name = \PHPExcel_Cell::stringFromColumnIndex($s);
                $obpe->getActiveSheet()->mergeCells($title_column_name . '1:' . $title_column_name . '3');
            }

            //改变表格标题样式
            $column_last_name = $currentSheet->getHighestColumn();
            $row_last_num = $currentSheet->getHighestRow();
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, 'A', 1, $column_last_name, 1);
            $commonModel->changeTableContent($obpe, 'A', 2, $column_last_name, $row_last_num);
            $obpe->getActiveSheet()->setTitle('汇总需求单_优采推荐');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel2007');

            $str = rand(1000, 9999);
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            $filename = '汇总需求单_优采推荐_' . $sum_demand_sn . '_' . $str . '.xls';

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

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:上传汇总单的分配数据
     * editor:zongxing
     * type:POST
     * date : 2019.05.15
     * params: 1.汇总需求单号:sum_demand_sn;2.分配数据:upload_file;
     * return Array
     */
    public function uploadSumDemandGoodsInfo(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['sum_demand_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
        } elseif (empty($param_info['upload_file'])) {
            return response()->json(['code' => '1003', 'msg' => '上传文件不能为空']);
        } elseif (empty($param_info['data_type'])) {
            return response()->json(['code' => '1016', 'msg' => '数据类型不能为空']);
        }
        $data_type = intval($param_info['data_type']);
        $file = $_FILES;
        //检查上传文件是否合格
        if ($data_type == 1) {
            $fileName = '汇总需求单_所有_';
        } else {
            $fileName = '汇总需求单_缺口_';
        }
        $excuteExcel = new ExcuteExcel();
        $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }

        //检查字段名称
        $arrTitle = ['商品规格码'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                return response()->json(['code' => '1006', 'msg' => '您的标题头有误，请按模板导入']);
            }
        }

        //整理渠道信息
        foreach ($res[0] as $k => $v) {
            if (empty($v)) {
                $channel_start_num = $k - 1;
                break;
            }
        }
        $channels_name = '';
        foreach ($res[1] as $k => $v) {
            if ($k < $channel_start_num) continue;
            if ($v) {
                $channels_name = $v;
            } else {
                $res[1][$k] = $channels_name;
            }
        }

        //获取汇总单商品数据
        $sum_demand_sn = trim($param_info['sum_demand_sn']);
        $sd_sn_arr[] = $sum_demand_sn;
        $sg_model = new SumGoodsModel();
        $sum_demand_detail = $sg_model->purchaseTaskDetail($sd_sn_arr);
        if (empty($sum_demand_detail)) {
            return response()->json(['code' => '1007', 'msg' => '汇总单单号错误']);
        }
        //获取采购成本折扣数据
        $discountModel = new DiscountModel();
        $discount_list = $discountModel->getTotalDiscount();
        //获取采购最终折扣数据
        $dt_model = new DiscountTypeModel();
        $goods_discount_list = $dt_model->getFinalDiscount($sum_demand_detail);
        //获取商品在某一采购期需求详情
        $sg_model = new SumGoodsModel();
        $sd_goods_info = $sg_model->createSumDemandDetail($goods_discount_list, $discount_list, $data_type, $sd_sn_arr);
        if ($sd_goods_info == false) {
            return response()->json(['code' => '1004', 'msg' => '获取采购任务详情失败']);
        }
        $sum_demand_goods_info = $sd_goods_info['sum_demand_goods'];
        $sum_demand_goods = [];
        foreach ($sum_demand_goods_info as $k => $v) {
            $channels_info = [];
            foreach ($v['channels_info'] as $k1 => $v1) {
                $channels_info[$k1] = $v1['最终折扣']['discount'];
            }
            $sum_demand_goods[$v['spec_sn']] = [
                'spec_sn' => $v['spec_sn'],
                'goods_num' => $v['goods_num'],
                'allot_num' => $v['allot_num'],
                'channels_info' => $channels_info,
            ];
        }

        //获取渠道及方式的信息
        $pc_model = new PurchaseChannelModel();
        $pcm_info = $pc_model->getChannelList();
        $pcm_list = [];
        foreach ($pcm_info as $k => $v) {
            $pin_str = $v['channels_name'] . '-' . $v['method_name'];
            $pcm_list[$pin_str] = [
                'method_id' => $v['method_id'],
                'channels_id' => $v['id'],
            ];
        }
        //整合上传商品数据
        $sg_model = new SumGoodsModel();
        $upload_goods_info = $sg_model->createUploadSumDemandGoods($res, $sum_demand_goods, $pcm_list, $channel_start_num);
        if (isset($upload_goods_info['code'])) {
            return response()->json($upload_goods_info);
        }

        $param_info['sd_sn_arr'] = $sd_sn_arr;
        //获取商品已分配数据
        if ($data_type == 1) {//总体分配方案
            $sdcg_model = new SumDemandChannelGoodsModel();
            $sdcg_list = $sdcg_model->sumDemandGoodsAllotInfo($param_info);
        } else {//缺口分配方案
            $scl_model = new SdgChannelLogModel();
            $sdcg_list = $scl_model->sdgDiffAllotInfo($param_info);
        }
        //上传汇总单商品分配方案
        $sdcg_model = new SumDemandChannelGoodsModel();
        if ($data_type == 1) {//总体分配方案
            $res = $sdcg_model->allotSumDemandGoods($sum_demand_detail, $upload_goods_info, $sum_demand_sn, $sdcg_list);
        } else {//缺口分配方案
            $res = $sdcg_model->allotSumDemandDiffGoods($sum_demand_detail, $upload_goods_info, $sum_demand_sn, $sdcg_list);
        }
        $return_info = ['code' => '1014', 'msg' => '批量导入汇总单分配方案失败'];
        if ($res !== false) {
            //获取商品在某一采购期需求详情---成功后重新获取详情
//            $sg_model = new SumGoodsModel();
//            $sd_goods_info = $sg_model->createSumDemandDetail($sum_demand_detail, $discount_list, $data_type);
//            if ($sd_goods_info == false) {
//                return response()->json(['code' => '1015', 'msg' => '获取采购任务详情失败']);
//            }
//            $data = [
//                'sum_demand_goods' => $sd_goods_info['sum_demand_goods'],
//                'channel_arr' => array_values($sd_goods_info['channel_arr']),
//                'demand_arr' => $sd_goods_info['demand_arr'],
//                'sale_user_list' => $sd_goods_info['sale_user_list'],
//                'expire_time_list' => $sd_goods_info['expire_time_list'],
//                'dg_goods_num' => $sd_goods_info['dg_goods_num'],
//                'cm_goods_num' => $sd_goods_info['cm_goods_num'],
//            ];
            $return_info = ['code' => '1000', 'msg' => '批量导入汇总单分配方案成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:需求单详情-标记商品延期
     * author:zongxing
     * date: 2019.05.22
     */
    public function markGoodsPostpone(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['demand_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '需求单单号不能为空']);
        } elseif (empty($param_info['spec_sn'])) {
            return response()->json(['code' => '1003', 'msg' => '商品规格码不能为空']);
        } elseif (empty($param_info['is_postpone'])) {
            return response()->json(['code' => '1008', 'msg' => '标记状态不能为空']);
        } elseif (intval($param_info['is_postpone']) != 1 && intval($param_info['is_postpone']) != 2) {
            return response()->json(['code' => '1009', 'msg' => '标记状态错误']);
        }

        //判断是否可标记或取消
        if (isset($param_info['sum_demand_sn']) && intval($param_info['is_postpone']) == 2) {
            return response()->json(['code' => '1010', 'msg' => '合单中不允许取消操作']);
        } elseif (!isset($param_info['sum_demand_sn']) && intval($param_info['is_postpone']) == 1) {
            return response()->json(['code' => '1011', 'msg' => '需求单中不允许标记延期']);
        }
        $demand_info = [];
        if (isset($param_info['sum_demand_sn'])) {
            //合单单号
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            //检查合单是否已经上传采购数据
            $rpa_model = new RealPurchaseAuditModel();
            $rpa_info = $rpa_model->isSumUploadData($sum_demand_sn);
            if (!empty($rpa_info) && intval($param_info['is_postpone']) == 2) {
                return response()->json(['code' => '1009', 'msg' => '该合单已上传采购数据,不能取消延期标记']);
            }
            $demand_sn = trim($param_info['demand_sn']);
            $spec_sn = trim($param_info['spec_sn']);
            //检查当前需求单是否允许做延期标记-只有需求单状态为5（采购中）才允许进行延期操作
            $demandModel = new DemandModel();
            $demand_info = $demandModel->getDemandInfo($demand_sn, $spec_sn, $sum_demand_sn);
            if (empty($demand_info)) {
                return response()->json(['code' => '1004', 'msg' => '需求单单号或商品错误']);
            } elseif ($demand_info['status'] != 5 && $demand_info['status'] != 7) {
                return response()->json(['code' => '1005', 'msg' => '当前需求单禁止标记延期']);
            } elseif ($demand_info['diff_num'] <= 0) {
                return response()->json(['code' => '1007', 'msg' => '该商品已分货完成,不能延期']);
            }
        }
        //开始标记商品
        $dg_model = new DemandGoodsModel();
        $res = $dg_model->markGoodsPostpone($param_info, $demand_info);
        $return_info = ['code' => '1006', 'msg' => '标记商品延期失败'];
        if ($res !== false) {
            $return_info = ['code' => '1000', 'msg' => '标记商品延期成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description 首页
     * author zongxing
     * date 2019.06.20
     */
    public function firstPage(Request $request)
    {
        $param_info = $request->toArray();
        //获取客户列表信息
        $su_model = new SaleUserModel();
        $su_info = $su_model->getSaleUserInfoInRedis();
        $data['su_info'] = $su_info;
        //获取DD子单信息
        $mosg_model = new MisOrderSubGoodsModel();
        $mos_total_list = $mosg_model->getDdMosList($param_info);
        if (empty($mos_total_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无DD订单信息', 'data' => $data]);
        }
        $mos_list = $mos_total_list['mos_list'];
        //获取DD需求单信息
        $dg_model = new DemandGoodsModel();
        $sub_order_sn = array_keys($mos_list);
        $dg_info = $dg_model->getDdDemandList($sub_order_sn);
        $sd_model = new SortDataModel();
        $sd_info = $sd_model->getDgSortInfo($sub_order_sn);
        $dg_list = $dg_model->createDdDemandList($dg_info, $sd_info);
        //获取DD现货单信息
        $spot_model = new SpotGoodsModel();
        $spot_list = $spot_model->getSpotStatistics($sub_order_sn);
        //获取需求单分货信息
        $demand_sn = array_keys($dg_list);
        $sb_model = new SortBatchModel();
        $sort_info = $sb_model->getSortByDemandSn($demand_sn);
        $total_info = $this->createFistPageData($dg_list, $spot_list, $sort_info);
        //获取待返积分统计
        $rpd_model = new RealPurchaseDetailModel();
        $integral_info = $rpd_model->getBatchIntegralInfo($param_info);
        $integral_total_info = $this->createIntegralData($integral_info);
        //获取各个渠道最后上传时间
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_info = $rpa_model->getLastBatchInfo($param_info);
        $channel_info = [];
        if (!empty($rpa_info)) {
            foreach ($rpa_info as $k => $v) {
                $channel_info[$k] = $v[0];
            }
        }
        //获取各个实采渠道的毛利
        $sb_model = new SortBatchModel();
        $batch_discount_info = $sb_model->getBatchDiscountInfo($param_info);
        //获取各个渠道采购数量
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $rpda_info = $rpda_model->getBatchDataInfo($param_info);
        $channel_data = $this->createChannelData($rpda_info, $channel_info, $batch_discount_info);
        //获取未审核批次数量
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_num = $rpa_model->getRpaNum();
        //组装数据
        $data_total = $this->createData($total_info, $mos_total_list, $integral_total_info, $channel_data, $rpa_num, $dg_list);
        $data = array_merge($data, $data_total);
        $return_info = ['code' => '1000', 'msg' => '获取首页统计成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:组装数据
     * editor:zongxing
     * date: 2019.06.20
     */
    public function createData($total_info, $mos_total_list, $integral_total_info, $channel_data, $rpa_num, $dg_list)
    {
        $day_time = date('Y-m-d');
        $param['day_time'] = $day_time;
        $er_model = new ExchangeRateModel();
        $er_info = $er_model->exchangeRateList($param);
        $integral_list = $integral_total_info['integral_list'];
        $integral_channel = $integral_total_info['integral_channel'];
        $integral_total = $integral_total_info['integral_total'];
        $integral_list_app = $integral_total_info['integral_list_app'];
        $integral_total_app = $integral_total_info['integral_total_app'];

        $total_pur_price = floatval($total_info['total_pur_price']);
        $total_sort_price = floatval($total_info['total_sort_price']);
        $total_price_margin_rate = number_format($total_sort_price / $total_pur_price * 100, 2, '.', '');

        $data = [
            'demand_num' => count($dg_list),//DD订单总需求数
            //'sub_goods_num' => $sub_goods_num,//DD订单总需求数
            'demand_goods_num' => $total_info['demand_goods_num'],//DD需求单总需求数
            'demand_sort_num' => strval($total_info['demand_sort_num']),//DD需求单总实采数
            'demand_diff_num' => strval($total_info['demand_diff_num']),//总缺口数
            'spot_goods_num' => strval($total_info['spot_goods_num']),//DD需求单总现货数
            'total_pur_price' => number_format($total_pur_price, 2, '.', ''),//总采购额
            'total_sale_price' => number_format($total_info['total_sale_price'], 2, '.', ''),//总销售额
            'total_discout_price' => number_format($total_info['total_discout_price'], 2, '.', ''),//总销售毛利额
            'total_sort_dis_price' => number_format($total_info['total_sort_dis_price'], 2, '.', ''),//总实采毛利额
            'total_sort_price' => number_format($total_sort_price, 2, '.', ''),//总实采额
            'total_diff_price' => number_format($total_info['total_diff_price'], 2, '.', ''),//总缺口额
            //'total_diff_price' => number_format($total_diff_price, 2, '.', ''),//总缺口额
            'total_sale_margin_rate' => number_format($total_info['total_sale_margin_rate'], 2, '.', '') . '%',//总报价毛利率
            'total_price_margin_rate' => strval($total_price_margin_rate) . '%',//采购金额满足率
            'sale_user_price' => $total_info['sale_user_price'],//客户毛利统计
            'integral_list' => $integral_list,//积分统计
            'integral_channel' => $integral_channel,//积分统计渠道
            'integral_total' => $integral_total,//积分统计汇总
            'total_sort_margin_rate' => number_format($total_info['total_sort_margin_rate'], 2, '.', '') . '%',//总实采毛利率
            'sort_real_rate' => number_format($total_info['sort_real_rate'], 2, '.', '') . '%',//数量满足率
            'sort_diff_rate' => number_format($total_info['sort_diff_rate'], 2, '.', '') . '%',//缺口率
            //'total_channel_info' => $total_channel_info,//渠道采购统计
            'channel_name' => array_keys($channel_data),//渠道采购统计
            'total_channel_info' => array_values($channel_data),//渠道采购统计
            'rpa_num' => strval($rpa_num),//待审核批次数
            'sku_num' => strval($mos_total_list['sku_num']),//sku数量
            'integral_list_app' => $integral_list_app,//积分统计
            'integral_total_app' => $integral_total_app,//积分统计汇总
            'usd_cny_rate' => $er_info[0]['usd_cny_rate'],//汇率
        ];
        return $data;
    }

    /**
     * description:首页
     * editor:zongxing
     * date: 2019.06.20
     */
    public function createFistPageData($dg_list, $spot_list, $sort_info)
    {
        //需求单信息
        $demand_goods_num = $demand_sort_num = $total_sort_price = $total_pur_price = $total_discout_price =
        $total_sale_price = $total_demand_sale_price = $total_sort_dis_price = $total_diif_price =
        $total_sort_sale_price = $demand_diff_num = 0;
        $sale_user_price = [];
        foreach ($dg_list as $k => $v) {
            $goods_num = intval($v[0]['goods_num']);
            $demand_goods_num += $goods_num;
            $demand_sale_price = floatval($v[0]['demand_sale_price']);//总销售额
            $total_demand_sale_price += $demand_sale_price;
            $total_sale_price += $demand_sale_price;
            $demand_pur_price = floatval($v[0]['demand_pur_price']);//总需求额
            $demand_discount_price = floatval($v[0]['demand_discount_price']);//总销售毛利额
            $total_discout_price += $demand_discount_price;
            $total_pur_price += $demand_pur_price;
            $diff_num = intval($v[0]['diff_num']);
            $demand_diff_num += $diff_num;//需求单缺口总数
            $demand_diff_price = floatval($v[0]['demand_diff_price']);//需求单缺口总额
            $total_diif_price += $demand_diff_price;
            $sort_discount_price = $sort_sale_price = $sort_num = 0;
            if (isset($sort_info[$k])) {
                $sort_num = intval($sort_info[$k][0]['sort_num']);
                $demand_sort_num += $sort_num;
                $sort_price = $sort_info[$k][0]['sort_price'];
                $total_sort_price += floatval(number_format($sort_price, 2, '.', ''));
                $sort_discount_price = $sort_info[$k][0]['sort_discount_price'];//总实采毛利额
                $total_sort_dis_price += floatval($sort_discount_price);
                $sort_sale_price = $sort_info[$k][0]['sort_sale_price'];//实采销售额
                $total_sort_sale_price += floatval($sort_sale_price);
            }
            $sale_user_id = intval($v[0]['sale_user_id']);
            $user_name = trim($v[0]['user_name']);
            if (isset($sale_user_price[$sale_user_id])) {
                $sale_user_price[$sale_user_id]['goods_num'] += $goods_num;//需求数
                $sale_user_price[$sale_user_id]['sort_num'] += $sort_num;//分货数
                $sale_user_price[$sale_user_id]['sale_discount_price'] += $demand_discount_price;//客户毛利额
                $sale_user_price[$sale_user_id]['sale_price'] += $demand_sale_price;//客户销售额
                $sale_user_price[$sale_user_id]['sort_discount_price'] += $sort_discount_price;//客户实采毛利额
                $sale_user_price[$sale_user_id]['sort_sale_price'] += $sort_sale_price;//客户实采销售额
            } else {
                $sale_user_price[$sale_user_id] = [
                    'user_name' => $user_name,
                    'goods_num' => $goods_num,
                    'sort_num' => $sort_num,
                    'sale_discount_price' => $demand_discount_price,
                    'sale_price' => $demand_sale_price,
                    'sort_discount_price' => $sort_discount_price,
                    'sort_sale_price' => $sort_sale_price,
                ];
            }
        }
        $return_info['demand_goods_num'] = $demand_goods_num;//DD订单采购需求数
        $return_info['demand_diff_num'] = $demand_diff_num;//需求单缺口总数
        $return_info['demand_sort_num'] = $demand_sort_num;//总实采数
        $return_info['total_pur_price'] = $total_pur_price;//总采购金额
        $return_info['total_sort_price'] = $total_sort_price;//总实采金额
        $return_info['total_diff_price'] = $total_diif_price;//总缺口金额
        //现货单信息
        $spot_goods_num = 0;
        foreach ($spot_list as $k => $v) {
            $spot_goods_num += intval($v[0]['spot_goods_num']);
            $spot_sale_price = floatval($v[0]['pst_price']);
            $total_sale_price += $spot_sale_price;
            $spot_discount_price = floatval($v[0]['spot_discount_price']);
            $total_discout_price += $spot_discount_price;
            $sale_user_id = intval($v[0]['sale_user_id']);
            $user_name = trim($v[0]['user_name']);
            if (isset($sale_user_price[$sale_user_id])) {
                $sale_user_price[$sale_user_id]['sale_discount_price'] += $spot_discount_price;//客户毛利额
                $sale_user_price[$sale_user_id]['sale_price'] += $spot_sale_price;//客户销售额
            } else {
                $sale_user_price[$sale_user_id] = [
                    'user_name' => $user_name,
                    'sale_discount_price' => $spot_discount_price,
                    'sale_price' => $spot_sale_price,
                ];
            }
        }
        $return_info['spot_goods_num'] = $spot_goods_num;//DD订单现货数量
        $return_info['total_sale_price'] = $total_sale_price;//总销售额
        $return_info['total_discout_price'] = number_format($total_discout_price, 2, '.', '');//销售报价毛利额
        $return_info['total_sort_dis_price'] = number_format($total_sort_dis_price, 2, '.', '');//实采毛利额
        $return_info['sort_real_rate'] = number_format($demand_sort_num / $demand_goods_num * 100, 2, '.', '');//采满率
        $return_info['sort_diff_rate'] = number_format(($demand_goods_num - $demand_sort_num)
            / $demand_goods_num * 100, 2, '.', '');//缺口率
        //总报价毛利率
        $total_sale_margin_rate = 0;
        if ($total_sale_price != 0) {
            $total_sale_margin_rate = number_format($total_discout_price / $total_sale_price * 100, 2, '.', '');
        }
        $return_info['total_sale_margin_rate'] = $total_sale_margin_rate;
        //总实采毛利率
        $total_sort_margin_rate = 0;
        if ($total_sort_sale_price != 0) {
            $total_sort_margin_rate = number_format($total_sort_dis_price / $total_sort_sale_price * 100, 2, '.', '');
        }
        $return_info['total_sort_margin_rate'] = $total_sort_margin_rate;
        //销售客户销售和实采统计
        $sale_user_price = array_values($sale_user_price);
        foreach ($sale_user_price as $k => $v) {
            //销售客户销售统计
            if (!isset($v['goods_num'])) {
                $sale_user_price[$k]['goods_num'] = '0';
            }
            if (!isset($v['sort_num'])) {
                $sale_user_price[$k]['sort_num'] = '0';
            }
            $sale_margin_rate = 0;
            if (!empty($v['sale_price'])) {
                $sale_discount_price = floatval($v['sale_discount_price']);
                $sale_price = floatval($v['sale_price']);
                $sale_margin_rate = number_format($sale_discount_price / $sale_price * 100, 2, '.', '');
                $sale_user_price[$k]['sale_discount_price'] = number_format($v['sale_discount_price'], 2, '.', '');
                $sale_user_price[$k]['sale_price'] = number_format($v['sale_price'], 2, '.', '');
            } else {
                $sale_user_price[$k]['sort_discount_price'] = '0.00';
                $sale_user_price[$k]['sort_sale_price'] = '0.00';
            }
            $sale_user_price[$k]['sale_margin_rate'] = $sale_margin_rate;

            //销售客户实采统计
            $sort_margin_rate = 0;
            if (!empty($v['sort_sale_price'])) {
                $sort_discount_price = floatval($v['sort_discount_price']);
                $sort_sale_price = floatval($v['sort_sale_price']);
                if ($sort_sale_price) {
                    $sort_margin_rate = number_format($sort_discount_price / $sort_sale_price * 100, 2, '.', '');
                }
                $sale_user_price[$k]['sort_discount_price'] = number_format($v['sort_discount_price'], 2, '.', '');
                $sale_user_price[$k]['sort_sale_price'] = number_format($v['sort_sale_price'], 2, '.', '');
            } else {
                $sale_user_price[$k]['sort_discount_price'] = '0.00';
                $sale_user_price[$k]['sort_sale_price'] = '0.00';
            }
            $sale_user_price[$k]['sort_margin_rate'] = $sort_margin_rate;
        }
        $return_info['sale_user_price'] = $sale_user_price;
        return $return_info;
    }

    /**
     * description:首页渠道数据
     * editor:zongxing
     * date: 2019.06.20
     */
    public function createChannelData($rpda_info, $channel_info, $batch_discount_info)
    {
        //计算渠道总金额
        $total_batch_price = 0;
        if (!empty($rpda_info)) {
            foreach ($rpda_info as $k => $v) {
                $total_batch_price += floatval($v[0]['batch_price']);
            }
        }
        if (!empty($channel_info)) {
            $sort_data = [];
            foreach ($channel_info as $k => $v) {
                $day_buy_num = 0;
                $batch_price = 0.00;
                $price_rate = 0.00;
                if (isset($rpda_info[$k])) {
                    $day_buy_num = intval($rpda_info[$k][0]['day_buy_num']);
                    $batch_price = floatval($rpda_info[$k][0]['batch_price']);
                    $price_rate = $batch_price / $total_batch_price * 100;
                }
                $sort_data[] = $price_rate;
                $channel_info[$k]['day_buy_num'] = $day_buy_num;
                $channel_info[$k]['batch_price'] = number_format($batch_price, 2, '.', '');;
                $channel_info[$k]['price_rate'] = number_format($price_rate, 2, '.', '');;

                $dis_price = 0.00;
                $sort_sale_price = 0.00;
                $dis_rate = 0.00;
                if (isset($batch_discount_info[$k])) {
                    $dis_price = floatval($batch_discount_info[$k][0]['sort_discount_price']);//渠道实采毛利金额
                    $sort_sale_price = floatval($batch_discount_info[$k][0]['sort_sale_price']);//渠道实采毛利金额
                    if ($sort_sale_price) {
                        $dis_rate = number_format($dis_price / $sort_sale_price * 100, 2, '.', '');//渠道实采毛利率
                    }
                }
                $channel_info[$k]['dis_price'] = number_format($dis_price, 2, '.', '');//渠道实采毛利金额
                $channel_info[$k]['sort_sale_price'] = number_format($sort_sale_price, 2, '.', '');//渠道实采销售金额
                $channel_info[$k]['dis_rate'] = $dis_rate;//渠道实采毛利率
            }
            array_multisort($sort_data, SORT_DESC, SORT_NUMERIC, $channel_info);
        }
        return $channel_info;
    }

    /**
     * description:组装首页积分数据
     * editor:zongxing
     * date: 2019.06.20
     */
    public function createIntegralData($integral_info)
    {
        $integral_list = $integral_date = $integral_total = $integral_list_app = $integral_total_app = $integral_channel = [];
        //@@@@@@@@@@ 控制语句可简化
        if (!empty($integral_info)) {
            foreach ($integral_info as $k => $v) {
                $integral_time = $v['integral_time'];
                $pin_str = $v['channels_name'] . '-' . $v['method_name'];
                $total_integral = number_format($v['total_integral'], 2, '.', '');
                if (!in_array($integral_time, $integral_date)) {
                    $integral_date[] = $integral_time;
                }
                if (!isset($integral_list_app[$integral_time])) {
                    $integral_list_app[$integral_time] = [
                        'date' => $integral_time,
                        'integral' => floatval($total_integral),
                    ];
                } else {
                    $integral_list_app[$integral_time]['integral'] += $total_integral;
                }
                if (isset($integral_list[$integral_time])) {
                    $integral_list[$integral_time] += $total_integral;
                } else {
                    $integral_list[$integral_time] = floatval($total_integral);
                }
                if (isset($integral_total[$pin_str][$integral_time])) {
                    $integral_total[$pin_str][$integral_time] += $total_integral;
                } else {
                    $integral_total[$pin_str][$integral_time] = floatval($total_integral);
                }
                if (!isset($integral_total_app[$pin_str])) {
                    $integral_total_app[$pin_str]['channels_name'] = $pin_str;
                }
                if (!isset($integral_total_app[$pin_str]['info'][$integral_time])) {
                    $integral_total_app[$pin_str]['info'][$integral_time] = [
                        'date' => $integral_time,
                        'integral' => floatval($total_integral),
                    ];
                } else {
                    $integral_total_app[$pin_str]['info'][$integral_time]['integral'] += $total_integral;
                }
            }
            foreach ($integral_date as $k => $v) {
                foreach ($integral_total as $k1 => $v1) {
                    if (!isset($v1[$v])) {
                        $integral_total[$k1][$v] = 0.00;
                    }
                }
                foreach ($integral_total_app as $k2 => $v2) {
                    if (!isset($v2['info'][$v])) {
                        $integral_total_app[$k2]['info'][$v] = [
                            'date' => $v,
                            'integral' => '0.00',
                        ];
                    }
                }
            }
            $sort_data = array_keys($integral_date);
            foreach ($integral_total_app as $k => $v) {
                array_multisort($v['info'], SORT_ASC, SORT_REGULAR, $sort_data);
                $integral_total_app[$k]['info'] = array_values($v['info']);
            }
            $integral_channel = array_keys($integral_total);
        }

        $return_info['integral_list'] = $integral_list;
        $return_info['integral_channel'] = $integral_channel;
        $return_info['integral_total'] = $integral_total;
        $return_info['integral_total_app'] = array_values($integral_total_app);
        $return_info['integral_list_app'] = array_values($integral_list_app);
        return $return_info;
    }

    /**
     * description:获取合单下需求单的信息
     * editor:zongxing
     * date: 2019.06.26
     */
    public function sumDemandInfo(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['sum_demand_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
        }
        $sum_demand_sn = trim($param_info['sum_demand_sn']);
        $dg_model = new DemandGoodsModel();
        $sum_demand_info = $dg_model->sumDemandInfo($sum_demand_sn);
        if (empty($sum_demand_info)) {
            return response()->json(['code' => '1001', 'msg' => '合单单号错误']);
        }
        $return_info = ['code' => '1000', 'msg' => '获取首页统计成功', 'data' => $sum_demand_info];
        return response()->json($return_info);
    }

    /**
     * description:查看合单缺口统计
     * editor:zongxing
     * date: 2019.06.26
     */
    public function sumDiffInfo(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['sum_demand_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
        }
        //合单单号
        $sd_sn_arr = $param_info['sum_demand_sn'];
        //$sd_sn_arr = json_decode($sd_sn_arr, true);

        //获取合单下需求单商品的信息
        $dg_model = new DemandGoodsModel();
        $sd_goods_info = $dg_model->sumDemandGoodsInfo($sd_sn_arr);
        if (empty($sd_goods_info)) {
            return response()->json(['code' => '1003', 'msg' => '汇总单单号错误']);
        }

        $sd_goods_list = [];
        $sd_goods_list['total_info']['demand_num'] = count($sd_goods_info);
        $sd_goods_list['total_info']['diff_num'] = 0;
        $sd_goods_list['total_info']['dg_diff_price'] = 0.00;
        foreach ($sd_goods_info as $k => $v) {
            //总计信息
            $sd_goods_list['total_info']['diff_num'] += intval($v['diff_num']);
            $sd_goods_list['total_info']['dg_diff_price'] += floatval($v['dg_diff_price']);
            //交期信息
            $expire_time = trim($v['expire_time']);
            if (isset($sd_goods_list['demand_info'][$expire_time])) {
                $sd_goods_list['demand_info'][$expire_time]['demand_num']++;
                $sd_goods_list['demand_info'][$expire_time]['diff_num'] += intval($v['diff_num']);
                $sd_goods_list['demand_info'][$expire_time]['dg_diff_price'] += floatval($v['dg_diff_price']);
            } else {
                $sd_goods_list['demand_info'][$expire_time]['demand_num'] = 1;
                $sd_goods_list['demand_info'][$expire_time]['diff_num'] = intval($v['diff_num']);
                $sd_goods_list['demand_info'][$expire_time]['dg_diff_price'] = floatval($v['dg_diff_price']);
            }
        }
        $expire_time = array_keys($sd_goods_list['demand_info']);
        $return_info['sd_goods_list'] = $sd_goods_list;
        $return_info['expire_time'] = $expire_time;

        //获取汇总单商品数据
        $sg_model = new SumGoodsModel();
        $sum_demand_detail = $sg_model->sumDiffDetail($sd_sn_arr);

        //获取汇总单商品分配方案数据
        $sdcg_model = new SumDemandChannelGoodsModel();
        $param_info['sd_sn_arr'] = $sd_sn_arr;
        $sdcg_info = $sdcg_model->sumDemandGoodsAllotInfo($param_info);
        $channel_arr = [];
        foreach ($sdcg_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($k1, $channel_arr)) {
                    $channel_arr[] = $k1;
                }
            }
        }

        foreach ($sum_demand_detail as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $channel_info = [];
            if (isset($sdcg_info[$spec_sn])) {
                $channel_info = $sdcg_info[$spec_sn];
            }
            $sum_demand_detail[$k]['channel_info'] = $channel_info;
        }
        $return_info['sum_demand_detail'] = $sum_demand_detail;
        $return_info['channel_arr'] = $channel_arr;

        $return_info = ['code' => '1000', 'msg' => '获取合单缺口统计信息成功', 'data' => $return_info];
        return response()->json($return_info);
    }

    /**
     * description:拆分合单需求单
     * editor:zongxing
     * date: 2019.06.28
     */
    public function splitSumDemand(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['sum_demand_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
        } elseif (empty($param_info['demand_sn'])) {
            return response()->json(['code' => '1003', 'msg' => '需求单号不能为空']);
        }
        //合单单号
        $sum_demand_sn = trim($param_info['sum_demand_sn']);
        //检查合单是否已经上传采购数据
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_info = $rpa_model->isSumUploadData($sum_demand_sn);
        if (!empty($rpa_info)) {
            return response()->json(['code' => '1004', 'msg' => '该合单已上传采购数据,不能拆分']);
        }
        //需求单号数组
        $demand_sn_arr = $param_info['demand_sn'];
        //$demand_sn_arr = json_decode($demand_sn_arr, true);
        //获取合单对应的需求单数
        $sd_model = new SumDemandModel();
        $sd_num = $sd_model->getSumDemandNum($sum_demand_sn);
        //更新数据
        $sum_model = new SumModel();
        $res = $sum_model->updateSumTotalInfo($sum_demand_sn, $sd_num, $demand_sn_arr);
        $return_info = ['code' => '1000', 'msg' => '拆分合单需求单成功'];
        if ($res == false) {
            $return_info = ['code' => '1005', 'msg' => '拆分合单需求单失败'];
        }
        return response()->json($return_info);

    }

    /**
     * description 品牌折扣列表
     * author zhangdong
     * date 2019.10.29
     */
    public function brandDiscountList(Request $request)
    {
        $reqParams = $request -> toArray();
        $brandModel = new BrandModel();
        //有成本折扣的品牌--分页数据基础
        $brandList = $brandModel->getBrandList($reqParams);
        //组装品牌折扣数据
        $brandDiscount = $brandModel->makeBrandDiscount($brandList);
        return response()->json($brandDiscount);
    }





}//end of class