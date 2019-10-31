<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\CommonModel;
use App\Model\Vone\DataModel;
use App\Model\Vone\DeliverGoodsModel;
use App\Model\Vone\DeliverOrderModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\DemandModel;
use App\Model\Vone\DepartSortGoodsModel;
use App\Model\Vone\DiscountModel;
use App\Model\Vone\GoodsLabelModel;
use App\Model\Vone\MisOrderModel;
use App\Model\Vone\MisOrderSubGoodsModel;
use App\Model\Vone\MisOrderSubModel;
use App\Model\Vone\PurchaseChannelModel;
use App\Model\Vone\PurchaseDateModel;
use App\Model\Vone\PurchaseDemandDetailModel;
use App\Model\Vone\PurchaseDemandModel;
use App\Model\Vone\PurchaseMethodModel;
use App\Model\Vone\PurchaseSumDateModel;
use App\Model\Vone\PurchaseUserModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\RefundRulesModel;
use App\Model\Vone\SpotGoodsModel;
use App\Model\Vone\SpotOrderModel;
use App\Model\Vone\SumDemandGoodsModel;
use App\Model\Vone\SumDemandModel;
use App\Model\Vone\SumGoodsModel;
use App\Model\Vone\SumModel;
use App\Model\Vone\SupplierModel;
use App\Model\Vone\UserSortGoodsModel;
use App\Modules\Excel\ExcuteExcel;
use Dingo\Api\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;
use Tymon\JWTAuth\Facades\JWTAuth;

class StatisticsController extends BaseController
{
    /**
     * description:数据统计模块-订单管理-总单统计列表
     * editor:zongxing
     * date: 2019.01.07
     */
    public function misOrderStatisticsList(Request $request)
    {
        if ($request->isMethod('get')) {
            $demand_model = new DemandModel();
            $demand_list_info = $demand_model->misOrderStatisticsList($request);

            $return_info = ['code' => '1000', 'msg' => '获取总单统计列表成功', 'data' => $demand_list_info];
            if (empty($demand_list_info)) {
                $return_info = ['code' => '1002', 'msg' => '暂无总单统计数据'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-订单管理-需求单统计列表
     * editor:zongxing
     * date: 2019.01.08
     */
    public function demandStasticsList(Request $request)
    {
        if ($request->isMethod('get')) {
            $demand_model = new DemandModel();
            $demand_list_info = $demand_model->demandStasticsList($request);
            $return_info = ['code' => '1000', 'msg' => '获取需求单统计列表成功', 'data' => $demand_list_info];
            if (empty($demand_list_info)) {
                $return_info = ['code' => '1002', 'msg' => '暂无需求单统计数据'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-订单管理-需求单对应采购期的批次列表
     * editor:zongxing
     * date: 2019.01.08
     */
    public function demandRealPurchaseList(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();

            if (!isset($reqParams['demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (!isset($reqParams['purchase_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }
            $demand_sn = $reqParams['demand_sn'];
            $purchase_sn = $reqParams['purchase_sn'];
            $field = [
                'd.demand_sn',
                DB::raw('SUM(jms_dg.goods_num) as demand_goods_num'),
                DB::raw('SUM(jms_dg.goods_num) as final_demand_num'),
            ];
            $demand_list = DB::table('demand_goods as dg')
                ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
                ->where('d.demand_sn', $demand_sn)
                ->groupBy('d.demand_sn')->get($field)->groupBy('demand_sn');
            $demand_list = objectToArrayZ($demand_list);

            //获取预采批次列表信息
            $where = function ($query) use ($demand_sn, $purchase_sn) {
                $query->where('rp.demand_sn', '=', $demand_sn)
                    ->where('rp.batch_cat', '=', 2)
                    ->where('rp.purchase_sn', '=', $purchase_sn);
            };
            $rpd_model = new RealPurchaseDetailModel();
            $predict_real_purchase_detail = $rpd_model->getBatchStatisticsList($where);

            //获取采购期汇总信息
            $purchase_sn_arr[] = $purchase_sn;
            $demand_count_model = new DemandCountModel();
            $demand_count_list = $demand_count_model->getDemandCountDetail($purchase_sn_arr);
            $demand_list = array_values($demand_list)[0][0];
            $demand_count_list = array_values($demand_count_list)[0][0];

            foreach ($predict_real_purchase_detail as $k => $v) {
                $demand_sn = $v['demand_sn'];
                $predict_goods_num = intval($v['day_buy_num']);
                //计算需求单最终需求数据
                $demand_list['final_demand_num'] -= $predict_goods_num;
                //计算采购期统计表的最终需求数据
                $demand_count_list['final_goods_num'] -= $predict_goods_num;
            }

            //获取正常批次列表信息
            $where = [
                ['pd.demand_sn', $demand_sn],
                ['rp.purchase_sn', $purchase_sn],
                ['rp.batch_cat', 1]
            ];
            $real_purchase_detail = $rpd_model->getBatchStatisticsList($where, true);
            $real_purchase_detail = array_merge($real_purchase_detail, $predict_real_purchase_detail);

            $demand_rate = 0;
            if ($demand_count_list['final_goods_num']) {
                $demand_rate = $demand_list['final_demand_num'] / $demand_count_list['final_goods_num'];
            }
            //计算美金原价和人民币价格
            $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
            foreach ($real_purchase_detail as $k => $v) {
                $real_purchase_detail[$k]["real_total_price"] = round($v["real_total_price"], 2);
                $real_purchase_detail[$k]["cny_total_price"] = round($v["real_total_price"] * $USD_CNY_RATE, 2);
                if ($v['batch_cat'] == 1) {
                    $real_buy_num = $demand_rate * intval($v['day_buy_num']);
                } elseif ($v['batch_cat'] == 2) {
                    $real_buy_num = intval($v['day_buy_num']);
                }
                $real_purchase_detail[$k]['real_buy_num'] = $real_buy_num;
            }

            $real_purchase_list = [];
            foreach ($real_purchase_detail as $k => $v) {
                $group_sn = $v['group_sn'];
                if (isset($real_purchase_list[$group_sn])) {
                    $real_purchase_list[$group_sn]['batch_info'][] = $v;
                } else {
                    $real_purchase_list[$group_sn] = $v;
                }
            }
            $real_purchase_list = array_values($real_purchase_list);
            $return_info = ['code' => '1000', 'msg' => '获取需求单采购期批次列表成功', 'data' => $real_purchase_list];
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-订单管理-需求单对应采购期的批次详情
     * editor:zongxing
     * date: 2019.01.08
     */
    public function demandRealPurchaseDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (!isset($param_info['demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (!isset($param_info['purchase_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            } elseif (!isset($param_info['real_purchase_sn'])) {
                return response()->json(['code' => '1004', 'msg' => '批次单号不能为空']);
            }
            $demand_sn = $param_info['demand_sn'];
            $purchase_sn = $param_info['purchase_sn'];
            $real_purchase_sn = $param_info['real_purchase_sn'];
            $field = [
                'd.demand_sn',
                DB::raw('SUM(jms_dg.goods_num) as demand_goods_num'),
                DB::raw('SUM(jms_dg.goods_num) as final_demand_num'),
            ];
            $demand_list = DB::table('demand_goods as dg')
                ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
                ->where('d.demand_sn', $demand_sn)
                ->groupBy('d.demand_sn')->get($field);
            $demand_list = objectToArrayZ($demand_list);

            //获取预采批次列表信息
            $where = function ($query) use ($demand_sn, $purchase_sn) {
                $query->where('rp.demand_sn', '=', $demand_sn)
                    ->where('rp.batch_cat', '=', 2)
                    ->where('rp.purchase_sn', '=', $purchase_sn);
            };
            $rpd_model = new RealPurchaseDetailModel();
            $predict_real_purchase_detail = $rpd_model->getBatchStatisticsList($where);
            //获取采购期汇总信息
            $purchase_sn_arr[] = $purchase_sn;
            $demand_count_model = new DemandCountModel();
            $demand_count_list = $demand_count_model->getDemandCountDetail($purchase_sn_arr);
            $demand_list = $demand_list[0];
            $demand_count_list = array_values($demand_count_list)[0][0];
            foreach ($predict_real_purchase_detail as $k => $v) {
                $demand_sn = $v['demand_sn'];
                $predict_goods_num = intval($v['day_buy_num']);
                //计算需求单最终需求数据
                $demand_list['final_demand_num'] -= $predict_goods_num;
                //计算采购期统计表的最终需求数据
                $demand_count_list['final_goods_num'] -= $predict_goods_num;
            }

            $demand_spec_sn_list = DB::table('demand_goods')
                ->where('demand_sn', $demand_sn)->pluck('spec_sn');
            $demand_spec_sn_list = objectToArrayZ($demand_spec_sn_list);
            $field = [
                'rp.real_purchase_sn', 'spec_sn', 'day_buy_num', 'erp_prd_no', 'erp_merchant_no', 'goods_name'
            ];
            $real_purchase_detail = DB::table('real_purchase_detail as rpd')
                ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
                ->where('rp.real_purchase_sn', $real_purchase_sn)
                ->whereIn('rpd.spec_sn', $demand_spec_sn_list)
                ->groupBy('rp.real_purchase_sn')->get($field);
            $real_purchase_detail = objectToArrayZ($real_purchase_detail);
            $demand_rate = 0;
            if ($demand_count_list['final_goods_num']) {
                $demand_rate = $demand_list['final_demand_num'] / $demand_count_list['final_goods_num'];
            }
            foreach ($real_purchase_detail as $k => $v) {
                $real_purchase_detail[$k]["real_buy_num"] = floor($demand_rate * intval($v['day_buy_num']));
            }
            $return_info = ['code' => '1000', 'msg' => '获取需求单采购期批次详情成功', 'data' => $real_purchase_detail];
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-订单管理-需求单对应采购期的商品详情
     * editor:zongxing
     * date: 2019.01.08
     */
//    public function demandPurchaseGoodsDetail(Request $request)
//    {
//        if ($request->isMethod('get')) {
//            $param_info = $request->toArray();
//
//            if (!isset($param_info['demand_sn'])) {
//                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
//            } elseif (!isset($param_info['purchase_sn'])) {
//                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
//            }
//
//            $demand_sn = $param_info['demand_sn'];
//            $demand_goods_model = new DemandGoodsModel();
//            $demand_goods_info = $demand_goods_model->getDemandGoodsList($demand_sn);
//
//            $purchase_sn = $param_info['purchase_sn'];
//            $goods_spec_sn = array_keys($demand_goods_info);
//            $demand_count_model = new DemandCountModel();
//            $demand_count_goods_info = $demand_count_model->getDemandCountGroupSn($purchase_sn, $goods_spec_sn);
//
//            $final_demand_goods_list = [];
//            foreach ($demand_goods_info as $k => $v) {
//                $demand_goods_num = $v[0]['goods_num'];
//                $real_buy_num = 0;
//                $real_buy_rate = 0;
//                if (isset($demand_count_goods_info[$k])) {
//                    $purchase_goods_num = $demand_count_goods_info[$k][0]['goods_num'];
//                    $purchase_real_buy_num = $demand_count_goods_info[$k][0]['real_buy_num'];
//
//                    $demand_rate = $demand_goods_num / $purchase_goods_num;
//                    $real_buy_num = floor($demand_rate * $purchase_real_buy_num);
//                    $real_buy_rate = ($real_buy_num / $demand_goods_num) * 100;
//                    $real_buy_rate = round($real_buy_rate, 2);
//                }
//                $v[0]['real_buy_num'] = $real_buy_num;
//                $v[0]['real_buy_rate'] = $real_buy_rate;
//                $final_demand_goods_list[] = $v[0];
//            }
//            $return_info = ['code' => '1000', 'msg' => '获取需求单采购期批次列表成功', 'data' => $final_demand_goods_list];
//        } else {
//            $code = "1001";
//            $msg = "请求错误";
//            $return_info = compact('code', 'msg');
//        }
//        return response()->json($return_info);
//    }
    public function demandPurchaseGoodsDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (!isset($param_info['demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (!isset($param_info['purchase_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }

            $demand_sn = $param_info['demand_sn'];
            $purchase_sn = $param_info['purchase_sn'];
            $field = [
                'd.demand_sn',
                DB::raw('SUM(jms_dg.goods_num) as demand_goods_num'),
                DB::raw('SUM(jms_dg.goods_num) as final_demand_num'),
            ];
            $demand_list = DB::table('demand_goods as dg')
                ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
                ->where('d.demand_sn', $demand_sn)
                ->groupBy('d.demand_sn')->get($field);
            $demand_list = objectToArrayZ($demand_list);

            //获取预采批次列表信息
            $where = function ($query) use ($demand_sn, $purchase_sn) {
                $query->where('rp.demand_sn', '=', $demand_sn)
                    ->where('rp.batch_cat', '=', 2)
                    ->where('rp.purchase_sn', '=', $purchase_sn);
            };
            $rpd_model = new RealPurchaseDetailModel();
            $predict_real_purchase_detail = $rpd_model->getBatchStatisticsList($where);
            //获取采购期汇总信息
            $purchase_sn_arr[] = $purchase_sn;
            $demand_count_model = new DemandCountModel();
            $demand_count_list = $demand_count_model->getDemandCountDetail($purchase_sn_arr);
            $demand_list = $demand_list[0];
            $demand_count_list = array_values($demand_count_list)[0][0];
            foreach ($predict_real_purchase_detail as $k => $v) {
                $demand_sn = $v['demand_sn'];
                $predict_goods_num = intval($v['day_buy_num']);
                //计算需求单最终需求数据
                $demand_list['final_demand_num'] -= $predict_goods_num;
                //计算采购期统计表的最终需求数据
                $demand_count_list['final_goods_num'] -= $predict_goods_num;
            }

            $demand_spec_sn_list = DB::table('demand_goods')
                ->where('demand_sn', $demand_sn)->pluck('spec_sn');
            $demand_spec_sn_list = objectToArrayZ($demand_spec_sn_list);
            $field = [
                'dc.purchase_sn', 'dc.spec_sn', 'dc.real_buy_num', 'dc.erp_prd_no', 'dc.erp_merchant_no', 'dc.goods_name'
            ];
            $demand_count_detail = DB::table('demand_count as dc')
                ->where('dc.purchase_sn', $purchase_sn)
                ->whereIn('dc.spec_sn', $demand_spec_sn_list)->get($field);
            $demand_count_detail = objectToArrayZ($demand_count_detail);

            $demand_rate = 0;
            if ($demand_count_list['final_goods_num']) {
                $demand_rate = $demand_list['final_demand_num'] / $demand_count_list['final_goods_num'];
            }
            foreach ($demand_count_detail as $k => $v) {
                $demand_count_detail[$k]["final_real_buy_num"] = floor($demand_rate * intval($v['real_buy_num']));
            }
            $return_info = ['code' => '1000', 'msg' => '获取需求单采购期详情成功', 'data' => $demand_count_detail];
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-订单管理-订单统计列表
     * editor:zongxing
     * date: 2019.01.07
     */
    public function orderStatisticsList(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['batch_type'])) {
            return response()->json(['code' => '1002', 'msg' => '订单分货状态不能为空']);
        }
//        $str1 = !empty($param_info['start_time']) && empty($param_info['end_time']);
//        $str2 = empty($param_info['start_time']) && !empty($param_info['end_time']);
//        if ($str1 || $str2) {
//            return response()->json(['code' => '1003', 'msg' => '开始时间和结束时间必须同时存在']);
//        }
        $demand_model = new DemandModel();
        $param_info['is_page'] = 1;//是否分页
        $demand_list_info = $demand_model->orderStatisticsList($param_info);
        if (empty($demand_list_info['data'])) {
            return response()->json(['code' => '1003', 'msg' => '暂无订单统计数据']);
        }

        //获取、存储采购部合期的交期--这个合期的功能已经去掉
        //----------start-----------
        if (!empty($param_info['start_time']) && isset($param_info['is_combine']) && $param_info['is_combine'] == 1) {
            //存储采购部合期的交期
            $where = [
                'entrust_time' => trim($demand_list_info['data'][0]['entrust_time']),
                'sum_date_cat' => 1
            ];
            $purchase_sum_date = DB::table('purchase_sum_date')->where($where)->first();
            if (empty($purchase_sum_date)) {
                $insert_data = [
                    'entrust_time' => trim($demand_list_info['data'][0]['entrust_time']),
                    'start_time' => trim($param_info['start_time']),
                    'end_time' => trim($param_info['end_time']),
                    'sum_date_cat' => 1
                ];
                $add_sum_date = DB::table('purchase_sum_date')->insert($insert_data);
                if ($add_sum_date == false) {
                    return response()->json(['code' => '1004', 'msg' => '采购部合期失败']);
                }
            }
        }
        //----------end-----------

        //获取合单列表信息
        $sum_model = new SumModel();
        $sum_info = $sum_model->getSumInfo();
        //获取订单中的所有客户
        $mo_model = new MisOrderModel();
        $sale_user_info = $mo_model->getSaleInfo();
        $data = [
            'demand_list_info' => $demand_list_info,
            'purchase_sum_date' => $sum_info,
            'sale_user_info' => $sale_user_info
        ];
        $return_info = ['code' => '1000', 'msg' => '获取订单统计列表成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:下载合单对应需求单统计数据-日报
     * editor:zongxing
     * type:GET
     * date : 2019.06.17
     * params: 1.采购期编号:sum_demand_sn;
     * return excel
     */
    public function downLoadDailyInfo(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info['sum_demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '汇总单单号不能为空']);
            }
            $demand_model = new DemandModel();
            $param_info['batch_type'] = 2;
            $param_info['is_page'] = 0;//是否分页
            $demand_list_info = $demand_model->orderStatisticsList($param_info);
            if (empty($demand_list_info['data'])) {
                return response()->json(['code' => '1003', 'msg' => '暂无订单统计列表']);
            }
            $demand_total_info = $demand_list_info['data'];
            $sd_model = new SumGoodsModel();
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            $sum_sn_arr[] = $sum_demand_sn;
            $sum_demand_info = $sd_model->sumDemandStatistic($sum_sn_arr);
            if (empty($sum_demand_info)) {
                return response()->json(['code' => '1004', 'msg' => '合单单号错误']);
            }
            $sum_demand_info = $sum_demand_info[$sum_demand_sn][0];
            $sum_info = $this->createDailyInfo($sum_demand_info, $demand_total_info);

            $obpe = new PHPExcel();
            $obpe->setActiveSheetIndex(0);
            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '单号')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '客户')->getColumnDimension('B')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('C1', '交期')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '客户单号')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '子单需求数')->getColumnDimension('E')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('F1', '需求单需求数')->getColumnDimension('E')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('G1', '实采数')->getColumnDimension('F')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('H1', '缺口数')->getColumnDimension('G')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('I1', '需求总金额')->getColumnDimension('H')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('J1', '缺口总金额')->getColumnDimension('I')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('K1', '报价毛利额')->getColumnDimension('J')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('L1', '报价销售额')->getColumnDimension('J')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('M1', '报价逻辑毛利')->getColumnDimension('J')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('N1', '实采逻辑毛利')->getColumnDimension('K')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('O1', '采购满足率')->getColumnDimension('L')->setWidth(15);
            //首行合单信息
            for ($i = 0; $i < 15; $i++) {
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($i);
                if ($i == 0) {
                    $sum_demand_sn = $sum_info['sum_demand_sn'];
                    $obpe->getActiveSheet()->setCellValue($column_name . '2', $sum_demand_sn)
                        ->getColumnDimension($column_name)->setWidth(20);
                    continue;
                }
                if ($i > 1 && $i < 5) continue;
                $real_value = '';
                if ($i == 1) {
                    $real_value = trim($sum_info['sum_demand_name']);
                } elseif ($i == 5) {
                    $real_value = intval($sum_info['goods_num']);
                } elseif ($i == 6) {
                    $real_value = intval($sum_info['real_buy_num']);
                } elseif ($i == 7) {
                    $real_value = intval($sum_info['diff_num']);
                } elseif ($i == 8) {
                    $real_value = number_format($sum_info['purchase_total_price'], 2, '.', '');
                } elseif ($i == 9) {
                    $real_value = number_format($sum_info['diff_total_price'], 2, '.', '');
                } elseif ($i == 10) {
                    $real_value = number_format($sum_info['sqrt_price'], 2);
                } elseif ($i == 11) {
                    $real_value = number_format($sum_info['sqt_price'], 2);
                } elseif ($i == 12) {
                    $real_value = number_format($sum_info['sub_quote_rate'], 2) . '%';
                } elseif ($i == 13) {
                    $real_value = number_format($sum_info['sub_profit_rate'], 2) . '%';
                } elseif ($i == 14) {
                    $real_value = number_format($sum_info['real_buy_rate'], 2) . '%';
                }
                $obpe->getActiveSheet()->setCellValue($column_name . '2', $real_value)
                    ->getColumnDimension($column_name)->setWidth(20);
            }
            //需求单
            $title_info = [
                '单号' => 'demand_sn',
                '客户' => 'user_name',
                '交期' => 'entrust_time',
                '客户单号' => 'external_sn',
                '子单需求数' => 'mis_order_sub_total_num',
                '需求单需求数' => 'demand_goods_num',
                '实采数' => 'real_buy_num',
                '缺口数' => 'wait_buy_num',
                '需求总金额' => 'sub_purchase_total_price',
                '缺口总金额' => 'sub_purchase_diff_total_price',
                '报价毛利额' => 'sqrt_price',
                '报价销售额' => 'sqt_price',
                '报价逻辑毛利' => 'sub_quote_rate',
                '实采逻辑毛利' => 'sub_profit_rate',
                '采购满足率' => 'sub_real_rate',
            ];
            $demand_num = count($demand_total_info);
            $total_num = $demand_num + 3;
            for ($i = 3; $i < $total_num; $i++) {
                $row_key = $i - 3;
                $demand_info = $demand_total_info[$row_key];
                for ($j = 0; $j < 15; $j++) {
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                    $title_name_value = $obpe->getActiveSheet()->getCell($column_name . '1')->getValue();
                    if (isset($title_info[$title_name_value])) {
                        $real_key = $title_info[$title_name_value];
                        $real_value = $demand_info[$real_key];
                        if ($j > 11) {
                            $real_value .= '%';
                        }
                        $obpe->getActiveSheet()->setCellValue($column_name . $i, $real_value)
                            ->getColumnDimension($column_name)->setWidth(20);
                    }
                }
            }

            $currentSheet = $obpe->getActiveSheet();
            //改变表格标题样式
            $column_last_name = $currentSheet->getHighestColumn();
            $row_last_num = $currentSheet->getHighestRow();
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, 'A', 1, $column_last_name, 1);
            $commonModel->changeTableContent($obpe, 'A', 2, $column_last_name, $row_last_num);
            $obpe->getActiveSheet()->setTitle('订单统计');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel2007');

            $str = rand(1000, 9999);
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            $filename = '订单统计_' . $sum_demand_sn . '_' . $str . '.xls';

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

    public function createDailyInfo($sum_demand_info, $demand_total_info)
    {
        $daily_info = [
            'sum_demand_sn' => $sum_demand_info['sum_demand_sn'],
            'sum_demand_name' => $sum_demand_info['sum_demand_name'],
            'goods_num' => $sum_demand_info['goods_num'],
            'real_buy_num' => $sum_demand_info['real_buy_num'],
            'real_buy_rate' => $sum_demand_info['real_buy_rate'],
            'diff_num' => $sum_demand_info['goods_num'] - $sum_demand_info['real_buy_num'],
            'overflow_num' => 0,
            'sqrt_price' => 0,
            'sqt_price' => 0,
            'psrt_price' => 0,
            'psst_price' => 0,
            'diff_total_price' => 0,
            'purchase_total_price' => 0,
        ];

        if (intval($daily_info['diff_num']) < 0) {
            $daily_info['diff_num'] = 0;
            $daily_info['overflow_num'] = abs($daily_info['diff_num']);
        }
        foreach ($demand_total_info as $k => $v) {
            $daily_info['sqrt_price'] += floatval($v['sqrt_price']);//报价毛利金额
            $daily_info['sqt_price'] += floatval($v['sqt_price']);//销售金额
            $daily_info['psrt_price'] += floatval($v['psrt_price']);//实采毛利金额
            $daily_info['psst_price'] += floatval($v['psst_price']);//实采报价金额
            $daily_info['diff_total_price'] += floatval($v['sub_purchase_diff_total_price']);//缺口金额
            $daily_info['purchase_total_price'] += floatval($v['sub_purchase_total_price']);//采购需求总额
        }

        $sub_quote_rate = 0;
        if ($daily_info['sqt_price']) {
            $sub_quote_rate = number_format($daily_info['sqrt_price'] / $daily_info['sqt_price'] * 100, 2);
        }
        $sub_profit_rate = 0;
        if ($daily_info['psst_price']) {
            $sub_profit_rate = number_format($daily_info['psrt_price'] / $daily_info['psst_price'] * 100, 2);
        }
        $daily_info['sub_quote_rate'] = $sub_quote_rate;
        $daily_info['sub_profit_rate'] = $sub_profit_rate;
        return $daily_info;
    }

    /**
     * description:数据统计模块-订单管理-删除合期数据
     * editor:zongxing
     * date: 2019.04.01
     */
    public function delPurchaseSumDate(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['id'])) {
            return response()->json(['code' => '1002', 'msg' => '合期id不能为空']);
        }

        $id = intval($param_info['id']);
        $res = DB::table('purchase_sum_date')->where('id', $id)->delete();
        if ($res == false) {
            return response()->json(['code' => '1003', 'msg' => '删除合期失败']);
        }
        return response()->json(['code' => '1000', 'msg' => '删除合期成功']);
    }

    /**
     * description:数据统计模块-订单管理-子单统计列表
     * editor:zongxing
     * date: 2019.02.28
     */
    public function subOrderStatisticsList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info['sub_order_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '子单单号不能为空']);
            }elseif (empty($param_info['batch_type'])) {
                return response()->json(['code' => '1003', 'msg' => '子单分货类别不能为空']);
            }
            $sub_order_sn = trim($param_info['sub_order_sn']);
            //获取子单商品信息
            $msg_model = new MisOrderSubGoodsModel();
            $sub_order_goods_info = $msg_model->getSubGoodsInfo($sub_order_sn);
            //获取商品标签列表
            $goods_label_model = new GoodsLabelModel();
            $goods_label_info = $goods_label_model->getAllGoodsLabelList();

            //获取商品分货信息
            $batch_type = intval($param_info['batch_type']);
            $demand_sort_goods_info = [];
            if ($batch_type == 1) {
                $dg_model = new DemandGoodsModel();
                $demand_sort_goods_info = $dg_model->subOrderRealGoodsInfo($sub_order_sn);
            } elseif ($batch_type == 2) {
                $dsg_model = new DepartSortGoodsModel();
                $demand_sort_goods_info = $dsg_model->subOrderSortGoodsInfo($sub_order_sn);
            }

            //获取商品现货信息
            $spot_goods_model = new SpotGoodsModel();
            $spot_goods_info = $spot_goods_model->getSpotGoods($sub_order_sn);
            $spot_goods_list = [];
            foreach ($spot_goods_info as $k => $v) {
                $spec_sn = $v['spec_sn'];
                $spot_goods_list[$spec_sn] = $v;
            }
            $sub_order_goods_list = $batch_info_arr = $batch_info_arr_app = [];
            foreach ($sub_order_goods_info as $k => $v) {
                //报价折扣
                $v[0]['dd_sale_discount'] = number_format($v[0]['dd_sale_discount'], 3);
                $goods_label = explode(',', $v[0]['goods_label']);
                $tmp_goods_label = [];
                foreach ($goods_label_info as $k1 => $v1) {
                    $label_id = intval($v1['id']);
                    if (in_array($label_id, $goods_label)) {
                        $tmp_goods_label[] = $v1;
                    }
                }
                $v[0]['goods_label_list'] = $tmp_goods_label;
                $spec_sn = $v[0]['spec_sn'];
                $diff_num = intval($v[0]['wait_buy_num']);

                //计算报价逻辑毛利
                $sqrt_price = floatval($v[0]['sqrt_price']);//报价毛利金额
                $sqt_price = floatval($v[0]['sqt_price']);//销售金额
                $sub_quote_rate = 0;
                if ($sqt_price) {
                    $sub_quote_rate = number_format($sqrt_price / $sqt_price * 100, 2, '.', '');
                }
                $v[0]['sub_quote_rate'] = $sub_quote_rate;
                $v[0]['sqrt_price'] = number_format($sqrt_price, 2, '.', '');
                $v[0]['sqt_price'] = number_format($sqt_price * 100, 2, '.', '');
                //处理分货数据
                $v[0]['batch_info'] = [];
                $total_sort_num = $psrt_price = $psst_price = $real_discount = 0;
                if (isset($demand_sort_goods_info[$spec_sn])) {
                    foreach ($demand_sort_goods_info[$spec_sn] as $k1 => $v1) {
                        $delivery_time = substr($v1['delivery_time'], 5);
                        $path_way = $v1['path_way'] == 1 ? '邮寄' : '自提';
                        $channels_name = $v1['channels_name'];
                        $real_purchase_sn = $v1['real_purchase_sn'];
                        $pin_str = $delivery_time . $channels_name . $path_way;
                        if (!in_array($pin_str, $batch_info_arr)) {
                            $batch_info_arr[] = $pin_str;
                            $batch_info_arr_app[] = [
                                'name' => $pin_str,
                                'sort' => $real_purchase_sn,
                            ];
                        }
                        $handle_num = intval($v1['handle_num']);
                        $channel_discount = number_format($v1['real_discount'], 2);
                        $v[0]['batch_info'][$pin_str] = [
                            'handle_num' => $handle_num,
                            'channel_discount' => $channel_discount
                        ];
                        $v[0]['batch_info_app'][] = [
                            'sort' => $real_purchase_sn,
                            'handle_num' => $handle_num,
                            'channel_discount' => $channel_discount
                        ];
                        if ($handle_num > 0) {
                            $total_sort_num += $handle_num;
                            $diff_num -= $handle_num;
                        }
                        //实采毛利金额
                        $psrt_price += floatval($v1['psrt_price']);
                        //实采报价金额
                        $psst_price += floatval($v1['psst_price']);
                    }
                }
                //实采逻辑毛利
                $sub_profit_rate = 0;
                if ($psst_price) {
                    $sub_profit_rate = number_format($psrt_price / $psst_price * 100, 2);
                }
                $v[0]['sub_profit_rate'] = $sub_profit_rate;
                $v[0]['total_sort_num'] = $total_sort_num;
                //处理现货数据
                $spot_num = 0;
                if (isset($spot_goods_info[$spec_sn])) {
                    $goods_number = intval($spot_goods_info[$spec_sn][0]['goods_number']);
                    if ($goods_number > 0) {
                        $spot_num = $goods_number;
                        $diff_num -= $goods_number;
                    }
                }
                $v[0]['spot_num'] = $spot_num;
                //获取待采数据
                $v[0]['diff_num'] = $diff_num;
                $sub_order_goods_list[] = $v[0];
            }
            $data['batch_info_arr'] = $batch_info_arr;
            $data['batch_info_arr_app'] = $batch_info_arr_app;
            $data['sub_order_goods_list'] = $sub_order_goods_list;
            $return_info = ['code' => '1000', 'msg' => '获取子单商品统计列表成功', 'data' => $data];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-订单管理-商品统计列表
     * editor:zongxing
     * date: 2019.03.01
     */
//    public function goodsStatisticsList(Request $request)
//    {
//        $param_info = $request->toArray();
//        //获取子单商品统计信息
//        $mosg_model = new MisOrderSubGoodsModel();
//        $mosg_info = $mosg_model->getSubGoodsStatisticsInfo($param_info);
//
//        $sub_goods_list = $mosg_info['sub_goods_list'];
//        $sub_order_sn = $mosg_info['sub_order_sn_arr'];
//        $sub_goods_info = $mosg_info['sub_goods_info'];
//        if (empty($sub_goods_list['data'])) {
//            return response()->json(['code' => '1002', 'msg' => '暂无订单统计列表']);
//        }
//
//        //获取需求单商品统计信息
//        $demand_goods_model = new DemandGoodsModel();
//        $demand_list = $demand_goods_model->getDemandStatistics($sub_order_sn);
//        $demand_sn_arr = array_keys($demand_list);
//
//        //根据需求单号获取采购期单号
//        $pd_model = new PurchaseDemandModel();
//        $purchase_sn_info = $pd_model->getPurchaseSnList($demand_sn_arr);
//        //根据采购期单号获取需求单号
//        $demand_sn_info = $pd_model->getDemandSnList($purchase_sn_info);
//
//        //以商品为单位获取指定采购期的采购数据汇总
////        $rpd_model = new RealPurchaseDetailModel();
////        $batch_goods_info = $rpd_model->getBatchGoodsList($purchase_sn_info);
//        $dc_model = new DemandCountModel();
//        $demand_count_goods_info = $dc_model->getDemandCountGoodsInfo($purchase_sn_info);
//
//        //以商品为单位获取指定现货单的数据汇总
//        $spot_goods_model = new SpotGoodsModel();
//        $spot_order_list = $spot_goods_model->getSpotGoodsList($sub_order_sn);
//        //获取商品标签列表
//        $goods_label_model = new GoodsLabelModel();
//        $goods_label_info = $goods_label_model->getAllGoodsLabelList();
//
//        foreach ($sub_goods_info as $k => $v) {
//            //增加商品标签
//            $goods_label = explode(',', $v['goods_label']);
//            $tmp_goods_label = [];
//            foreach ($goods_label_info as $k1 => $v1) {
//                $label_id = intval($v1['id']);
//                if (in_array($label_id, $goods_label)) {
//                    $tmp_goods_label[] = $v1;
//                }
//            }
//            $sub_goods_info[$k]['goods_label_list'] = $tmp_goods_label;
//            $sub_goods_info[$k]['wait_num'] = intval($v['sub_wait_buy_num']);
//            $alredy_buy_num = 0;
//            if (isset($demand_count_goods_info[$k])) {
//                $alredy_buy_num = intval($demand_count_goods_info[$k][0]['total_real_buy_num']);
//                $sub_goods_info[$k]['wait_num'] -= $alredy_buy_num;
//            }
//            $sub_goods_info[$k]['alredy_buy_num'] = $alredy_buy_num;
//            $spot_goods_num = 0;
//            if (isset($spot_order_list[$k])) {
//                $spot_goods_num = intval($spot_order_list[$k][0]['total_spot_goods_num']);
//                $sub_goods_info[$k]['wait_num'] -= $alredy_buy_num;
//            }
//            $sub_goods_info[$k]['spot_goods_num'] = $spot_goods_num;
//            $sub_goods_info[$k]['purchase_sn_info'] = $purchase_sn_info;
//            $sub_goods_info[$k]['demand_sn_info'] = $demand_sn_info;
//        }
//
//        $sub_goods_list['data'] = array_values($sub_goods_info);
//        $return_info = ['code' => '1000', 'msg' => '获取订单统计列表成功', 'data' => $sub_goods_list];
//        return response()->json($return_info);
//    }
    public function goodsStatisticsList(Request $request)
    {
        $param_info = $request->toArray();
        //获取子单商品统计信息
        $mosg_model = new MisOrderSubGoodsModel();
        $mosg_info = $mosg_model->getSubGoodsStatisticsInfo($param_info);

        $total_goods_num = $mosg_info['total_goods_num'];
        $sub_order_sn = $mosg_info['sub_order_sn_arr'];
        $sub_goods_info = $mosg_info['sub_goods_info'];
        if (empty($sub_goods_info)) {
            return response()->json(['code' => '1002', 'msg' => '暂无订单统计列表']);
        }

        //获取需求单商品统计信息
        $demand_goods_model = new DemandGoodsModel();
        $demand_list = $demand_goods_model->getDemandStatistics($sub_order_sn);
        $demand_sn_arr = array_keys($demand_list);

        //根据需求单号获取采购期单号
        $pd_model = new PurchaseDemandModel();
        $purchase_sn_info = $pd_model->getPurchaseSnList($demand_sn_arr);
        //根据采购期单号获取需求单号
        $demand_sn_info = $pd_model->getDemandSnList($purchase_sn_info);

        //根据需求单号获取采购期单号
        $sd_model = new SumDemandModel();
        $sd_sn_info = $sd_model->getSdSnList($demand_sn_arr);

        //以商品为单位获取指定采购期的采购数据汇总
        $dc_model = new DemandCountModel();
        $demand_count_goods_info = $dc_model->getDemandCountGoodsInfo($purchase_sn_info);

        //以商品为单位获取指定汇总需求单的采购数据汇总
        $sdg_model = new SumGoodsModel();
        $sdg_info = $sdg_model->get_sg_info($sd_sn_info);

        //以商品为单位获取指定现货单的数据汇总
        $spot_goods_model = new SpotGoodsModel();
        $spot_order_list = $spot_goods_model->getSpotGoodsList($sub_order_sn);
        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();

        $purchase_sn_info = array_merge($purchase_sn_info, $sd_sn_info);
        foreach ($sub_goods_info as $k => $v) {
            //增加商品标签
            $goods_label = explode(',', $v['goods_label']);
            $tmp_goods_label = [];
            foreach ($goods_label_info as $k1 => $v1) {
                $label_id = intval($v1['id']);
                if (in_array($label_id, $goods_label)) {
                    $tmp_goods_label[] = $v1;
                }
            }
            $sub_goods_info[$k]['goods_label_list'] = $tmp_goods_label;
            $sub_goods_info[$k]['wait_num'] = intval($v['sub_wait_buy_num']);
            $alredy_buy_num = 0;
            if (isset($demand_count_goods_info[$k])) {
                $alredy_buy_num = intval($demand_count_goods_info[$k][0]['total_real_buy_num']);
                $sub_goods_info[$k]['wait_num'] -= $alredy_buy_num;
            }
            if (isset($sdg_info[$k])) {
                $alredy_buy_num = intval($sdg_info[$k][0]['total_real_buy_num']);
                $sub_goods_info[$k]['wait_num'] -= $alredy_buy_num;
            }

            $sub_goods_info[$k]['alredy_buy_num'] = $alredy_buy_num;
            $spot_goods_num = 0;
            if (isset($spot_order_list[$k])) {
                $spot_goods_num = intval($spot_order_list[$k][0]['total_spot_goods_num']);
                $sub_goods_info[$k]['wait_num'] -= $alredy_buy_num;
            }
            $sub_goods_info[$k]['spot_goods_num'] = $spot_goods_num;
            $sub_goods_info[$k]['purchase_sn_info'] = $purchase_sn_info;
            $sub_goods_info[$k]['demand_sn_info'] = $demand_sn_info;
        }

        $data['sub_goods_list'] = array_values($sub_goods_info);
        $data['total_goods_num'] = $total_goods_num;
        $return_info = ['code' => '1000', 'msg' => '获取订单统计列表成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-采购期统计管理-采购期渠道统计列表
     * editor:zongxing
     * type:GET
     * date : 2019.03.15
     * return Array
     */
    public function purchaseChannelStatisticsList(Request $request)
    {
        $param_info = $request->toArray();
        //获取商品基础信息
        $demand_goods_model = new DemandGoodsModel();
        $goods_basic_total_info = $demand_goods_model->getDemandGoodsBasicInfo($param_info);
        if (empty($goods_basic_total_info)) {
            return response()->json(['code' => '1002', 'msg' => '暂无渠道统计数据']);
        }
        $total_goods_num = $goods_basic_total_info['total_goods_num'];
        $goods_basic_info = $goods_basic_total_info['goods_basic_list'];

        //获取当前折扣信息
        $discount_model = new DiscountModel();
        $discount_total_info = $discount_model->getDiscountCurrent();
        $discount_info = [];
        foreach ($discount_total_info as $k => $v) {
            $pin_str = $v['method_sn'] . '-' . $v['channels_sn'];
            $discount_info[$v['brand_id']][$pin_str] = $v;
        }
        $discount_list = [];
        foreach ($discount_info as $k => $v) {
            $discount_list[$k] = array_values($v);
        }

        //获取、存储采购部合期的交期
        if (!empty($param_info['start_time']) && isset($param_info['is_combine']) && $param_info['is_combine'] == 1) {
            //存储采购部合期的交期
            $where = [
                'entrust_time' => trim($goods_basic_info[0]['entrust_time']),
                'sum_date_cat' => 2
            ];
            $purchase_sum_date = DB::table('purchase_sum_date')->where($where)->first();
            if (empty($purchase_sum_date)) {
                $insert_data = [
                    'entrust_time' => trim($goods_basic_info[0]['entrust_time']),
                    'start_time' => trim($param_info['start_time']),
                    'end_time' => trim($param_info['end_time']),
                    'sum_date_cat' => 2
                ];
                $add_sum_date = DB::table('purchase_sum_date')->insert($insert_data);
                if ($add_sum_date == false) {
                    return response()->json(['code' => '1004', 'msg' => '采购部合期失败']);
                }
            }
        }

        //获取采购部合期的交期
        $psd_model = new PurchaseSumDateModel();
        $purchase_sum_date_list = $psd_model->getPurchaseSumDateList();

        $demand_sn_arr = [];
        $spec_sn_arr = [];
        $goods_basic_list = [];
        foreach ($goods_basic_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $goods_num = intval($v['goods_num']);
            $spec_price = round($v['spec_price'], 2);
            $spec_weight = round($v['spec_weight'], 4);
            $total_weight = round($goods_num * $spec_weight, 2);
            $total_spec_price = number_format($goods_num * $spec_price, 2);
            if (isset($goods_basic_list[$spec_sn])) {
                $goods_basic_list[$spec_sn]['goods_num'] += intval($v['goods_num']);
                $goods_basic_list[$spec_sn]['wait_buy_num'] += intval($v['wait_buy_num']);
                $goods_basic_list[$spec_sn]['total_weight'] += $total_weight;
                $goods_basic_list[$spec_sn]['total_spec_price'] += $total_spec_price;
            } else {
                //给商品增加折扣信息
                $brand_id = $v['brand_id'];
                $discount_info = [];
                if (isset($discount_list[$brand_id])) {
                    $discount_info = $discount_list[$brand_id];
                }
                $v['discount_info'] = $discount_info;
                $v['total_weight'] = $total_weight;
                $v['total_spec_price'] = $total_spec_price;
                $v['channel_info'] = [];
                $goods_basic_list[$spec_sn] = $v;
                $spec_sn_arr[] = $spec_sn;
            }
            //收集相关需求单单号信息
            $demand_sn = $v['demand_sn'];
            if (!in_array($demand_sn, $demand_sn_arr)) {
                $demand_sn_arr[] = $demand_sn;
            }
        }

        //获取先关采购期单号信息
        $pd_model = new PurchaseDemandModel();
        $purchase_sn_info = $pd_model->getPurchaseSnList($demand_sn_arr);
        $purchase_sn_info = objectToArrayZ($purchase_sn_info);

        //获取采购期渠道统计表信息
        $field = ['pcg.spec_sn', 'pcg.method_sn', 'pcg.channels_sn', 'd.brand_discount', 'pc.original_or_discount',
            'pc.channels_name', 'pm.method_name', 'gs.spec_price', 'gs.spec_weight',
            DB::raw('SUM(may_num) AS may_num'),
            DB::raw('SUM(real_num) AS real_num'),
        ];
        $purchase_channel_goods_info = DB::table('purchase_channel_goods as pcg')
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'pcg.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('purchase_channels AS pc', 'pc.channels_sn', '=', 'pcg.channels_sn')
            ->leftJoin('purchase_method AS pm', 'pm.method_sn', '=', 'pcg.method_sn')
            ->join('discount AS d', function ($join) {
                $join->on('d.brand_id', '=', 'g.brand_id');
                $join->on('d.channels_id', '=', 'pc.id');
                $join->on('d.method_id', '=', 'pm.id');
            })
            ->whereIn('pcg.spec_sn', $spec_sn_arr)
            ->whereIn('purchase_sn', $purchase_sn_info)
            ->groupBy('pcg.spec_sn')
            ->groupBy('pcg.method_sn', 'pcg.channels_sn')
            ->get($field)
            ->groupBy('spec_sn');
        $purchase_channel_goods_info = objectToArrayZ($purchase_channel_goods_info);

        $purchase_channel_goods_list = [];
        foreach ($purchase_channel_goods_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                //组装商品渠道名称
                $channels_name = $v1['channels_name'];
                $method_name = $v1['method_name'];
                $pin_str = $channels_name . '-' . $method_name;
                $purchase_channel_goods_list[$k][$pin_str] = $v1;
            }
        }

        //获取预采数据信息
        $field = ['rpd.spec_sn', 'pm.method_sn', 'pc.channels_sn',
            'pc.channels_name', 'pm.method_name',
            DB::raw('SUM(allot_num) AS allot_num'),
        ];
        $batch_goods_info = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase AS rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->leftJoin('purchase_channels AS pc', 'pc.id', '=', 'rp.channels_id')
            ->leftJoin('purchase_method AS pm', 'pm.id', '=', 'rp.method_id')
            ->whereIn('rpd.spec_sn', $spec_sn_arr)
            ->whereIn('rp.purchase_sn', $purchase_sn_info)
            ->where('rp.batch_cat', 2)
            ->groupBy('rpd.spec_sn')
            ->groupBy('pm.method_sn', 'pc.channels_sn')
            ->get($field)
            ->groupBy('spec_sn');
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        foreach ($batch_goods_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                //组装商品渠道名称
                $channels_name = $v1['channels_name'];
                $method_name = $v1['method_name'];
                $pin_str = $channels_name . '-' . $method_name;
                if (isset($purchase_channel_goods_list[$k][$pin_str])) {
                    $allot_num = $v1['allot_num'];
                    $purchase_channel_goods_list[$k][$pin_str]['real_num'] += $allot_num;
                } else {
                    $purchase_channel_goods_list[$k][$pin_str] = $v1;
                }
            }
        }

        //获取渠道信息
        $purchase_channel_list = [];
        foreach ($purchase_channel_goods_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($k1, $purchase_channel_list)) {
                    $tmp_channel_info = [
                        'channel_name' => $k1,
                        'channel_may_num' => 0,
                        'channel_real_num' => 0,
                        'channel_may_price' => 0,
                        'channel_real_price' => 0,
                        'channel_may_weight' => 0,
                        'channel_real_weight' => 0,
                    ];
                    $purchase_channel_list[$k1] = $tmp_channel_info;
                }
            }
        }
        //整理渠道可采信息
        $pcg_list = [];
        foreach ($purchase_channel_list as $k => $v) {
            foreach ($purchase_channel_goods_list as $k1 => $v1) {
                if (isset($v1[$k])) {
                    $pcg_list[$k1][$k] = $v1[$k];
                } else {
                    $pcg_list[$k1][$k] = [];
                }
            }
        }

        $purchase_channel_goods_total_list = [];
        foreach ($pcg_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (empty($v1)) {
                    $purchase_channel_goods_total_list[$k][][] = $k1;
                    continue;
                }

                $tmp_channel_goods['channel_name'] = $k1;
                //组装商品渠道采购数据
                $spec_price = floatval($v1['spec_price']);
                $may_num = intval($v1['may_num']);
                $real_num = intval($v1['real_num']);
                $target_total_price = floatval($spec_price * $may_num);
                $real_total_price = floatval($spec_price * $real_num);
                $original_or_discount = intval($v1['original_or_discount']);
                if ($original_or_discount) {
                    $brand_discount = floatval($v1['brand_discount']);
                    $target_total_price = floatval($target_total_price * $brand_discount);
                    $real_total_price = floatval($real_total_price * $brand_discount);
                }
                $v1['target_total_price'] = number_format($target_total_price, 2);
                $v1['real_total_price'] = number_format($real_total_price, 2);
                //组装商品重量
                $spec_weight = floatval($v1['spec_weight']);
                $v1['target_total_weight'] = number_format($spec_weight * $may_num, 2);
                $v1['real_total_weight'] = number_format($spec_weight * $real_num, 2);
                $tmp_channel_goods['channel_info'] = $v1;
                $purchase_channel_goods_total_list[$k][] = $tmp_channel_goods;
                //计算渠道统计信息
                $purchase_channel_list[$k1]['channel_may_num'] += $may_num;
                $purchase_channel_list[$k1]['channel_real_num'] += $real_num;
                $purchase_channel_list[$k1]['channel_may_price'] += $target_total_price;
                $purchase_channel_list[$k1]['channel_real_price'] += $real_total_price;
                $purchase_channel_list[$k1]['channel_may_weight'] += floatval($v1['spec_weight']) * $may_num;
                $purchase_channel_list[$k1]['channel_real_weight'] += floatval($v1['spec_weight']) * $real_num;
            }
        }

        foreach ($purchase_channel_goods_total_list as $k => $v) {
            if (isset($goods_basic_list[$spec_sn])) {
                $spec_sn = $k;
                foreach ($v as $k1 => $v1) {
                    if (isset($v1['channel_info'])) {
                        $real_buy_num = intval($v1['channel_info']['real_num']);
                        $goods_basic_list[$spec_sn]['wait_buy_num'] -= $real_buy_num;
                    }
                }
                $goods_basic_list[$spec_sn]['channel_info'] = $v;
            }
        }

        $purchase_channel_list = array_values($purchase_channel_list);
        foreach ($purchase_channel_list as $k => $v) {
            $purchase_channel_list[$k]['channel_may_price'] = number_format($v['channel_may_price'], 2);
            $purchase_channel_list[$k]['channel_real_price'] = number_format($v['channel_real_price'], 2);
            $purchase_channel_list[$k]['channel_may_weight'] = number_format($v['channel_may_weight'], 2);
            $purchase_channel_list[$k]['channel_real_weight'] = number_format($v['channel_real_weight'], 2);
        }
        $data['purchase_channel_list'] = array_values($purchase_channel_list);
        $goods_basic_list = array_values($goods_basic_list);
        $data['goods_basic_list'] = $goods_basic_list;
        $data['total_goods_num'] = $total_goods_num;
        $data['purchase_sum_date_list'] = $purchase_sum_date_list;
        $return_info = ['code' => '1000', 'msg' => '获取采购期渠道统计列表成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-采购期统计管理-采购期渠道统计详情
     * editor:zongxing
     * type:GET
     * date : 2019.03.15
     * return Array
     */
    public function purchaseChannelStatisticsDetail(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['method_id'])) {
            return response()->json(['code' => '1002', 'msg' => '采购期方式id不能为空']);
        } elseif (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1003', 'msg' => '采购期渠道id不能为空']);
        }
        $rp_model = new RealPurchaseDetailModel();
        $channel_goods_statistics_info = $rp_model->purchaseChannelStatisticsDetail($param_info);
        if (empty($channel_goods_statistics_info)) {
            return response()->json(['code' => '1004', 'msg' => '参数错误']);
        }
        $return_info = ['code' => '1000', 'msg' => '获取采购期渠道统计详情成功', 'data' => $channel_goods_statistics_info];
        return response()->json($return_info);
    }

    /**
     * description:数据统计模块-采购期统计管理-当月实采商品统计列表
     * editor:zongxing
     * type:GET
     * date : 2019.04.09
     * return Array
     */
    public function currentGoodsStatisticsList(Request $request)
    {
        $param_info = $request->toArray();
        $rp_model = new RealPurchaseDetailModel();
        $channel_goods_statistics_info = $rp_model->purchaseChannelGoodsInfo($param_info);
        if (empty($channel_goods_statistics_info)) {
            return response()->json(['code' => '1002', 'msg' => '暂无商品统计数据']);
        }
        $return_info = ['code' => '1000', 'msg' => '获取采购期渠道统计详情成功', 'data' => $channel_goods_statistics_info];
        return response()->json($return_info);
    }

}
