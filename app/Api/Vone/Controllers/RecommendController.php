<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\CommonModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\DiscountModel;
use App\Model\Vone\PurchaseDemandDetailModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;

/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class RecommendController extends BaseController
{
    /**
     * description:打开采购需求汇总详情页
     * editor:zongxing
     * type:POST
     * date : 2018.07.06
     * params: 1.采购期编号:purchase_sn;
     * return Object
     */
    public function getTotalDetail_stop(Request $request)
    {
        if ($request->isMethod("post")) {
            $demand_info = $request->toArray();

            //获取采购需求信息
            $demandCountModel = new DemandCountModel();
            $demand_total_detail = $demandCountModel->getRecommendTotalDetail_stop($demand_info);

            $code = "1000";
            $msg = "打开采购需求汇总详情页成功";
            $data = $demand_total_detail;
            $return_info = compact('code', 'msg', 'data');

            if (!$demand_total_detail) {
                $code = "1002";
                $msg = "暂无采购需求";
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
     * description:打开采购需求汇总详情页
     * editor:zongxing
     * type:POST
     * date : 2018.07.06
     * params: 1.采购期编号:purchase_sn;
     * return Object
     */
    public function recommendTotalDetail(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            }
            //获取采购期信息
            $purchase_sn = $param_info['purchase_sn'];
            $purchase_method_channel_info = DB::table('purchase_date')->where('purchase_sn', $purchase_sn)
                ->first(['method_info', 'channels_info']);
            $purchase_method_channel_info = objectToArrayZ($purchase_method_channel_info);
            if (empty($purchase_method_channel_info)) {
                return response()->json(['code' => '1003', 'msg' => '该采购期单号有误,请检查']);
            }

            //获取当前采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_method_channel_info);
            if (empty($discount_info)) {
                return response()->json(['code' => '1004', 'msg' => '该采购期对应的渠道和方式暂无品牌折扣信息,请先维护折扣信息']);
            }

            $purchase_demand_detail_model = new PurchaseDemandDetailModel();
            $purchase_demand_info = $purchase_demand_detail_model->createTotalDemandList($param_info);
            if (empty($purchase_demand_info)) {
                return response()->json(['code' => '1006', 'msg' => '该采购期暂无分配信息,请先分配']);
            }
            $purchase_demand_list = $purchase_demand_info["purchase_demand_list"];
            $return_info = ['code' => '1000', 'msg' => '获取采购期需求详情成功', 'data' => $purchase_demand_list];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:优采推荐打开采购需求详情页
     * editor:zongxing
     * type:POST
     * date : 2018.07.03
     * params: 1.需求单编号:demand_sn;2.采购期编号:purchase_sn;
     * return Object
     */
    public function getDemandDetail_stop(Request $request)
    {
        if ($request->isMethod("post")) {
            $demand_info = $request->toArray();

            //获取采购需求信息
            $demandGoodsModel = new DemandGoodsModel();
            $demand_recommend_info = $demandGoodsModel->getDemandRecommendDetail_stop($demand_info);

            $code = "1000";
            $msg = "打开采购需求详情页成功";
            $data = $demand_recommend_info;
            $return_info = compact('code', 'msg', 'data');

            if (!$demand_recommend_info) {
                $code = "1002";
                $msg = "暂无采购需求";
                $return_info = compact('code', 'msg');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    public function recommendDetail(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }

            //获取采购期信息
            $purchase_sn = $param_info['purchase_sn'];
            $purchase_method_channel_info = DB::table('purchase_date')->where('purchase_sn', $purchase_sn)
                ->first(['method_info', 'channels_info']);
            $purchase_method_channel_info = objectToArrayZ($purchase_method_channel_info);
            if (empty($purchase_method_channel_info)) {
                return response()->json(['code' => '1004', 'msg' => '该采购期单号有误,请检查']);
            }

            //获取当前采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_method_channel_info);
            if (empty($discount_info)) {
                return response()->json(['code' => '1005', 'msg' => '该采购期对应的渠道和方式暂无品牌折扣信息,请先维护折扣信息']);
            }

            $purchase_demand_detail_model = new PurchaseDemandDetailModel();
            $purchase_demand_info = $purchase_demand_detail_model->createDemandDetail($param_info, $discount_info);
            if (empty($purchase_demand_info)) {
                return response()->json(['code' => '1006', 'msg' => '该采购期暂无分配信息,请先分配']);
            }
            $purchase_demand_list = $purchase_demand_info["purchase_demand_list"];
            $return_info = ['code' => '1000', 'msg' => '获取采购期需求详情成功', 'data' => $purchase_demand_list];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:下载采购需求表(优采推荐详情页)
     * editor:zongxing
     * type:GET
     * date : 2018.07.03
     * params: 1.需求单编号:demand_sn;2.采购期编号:purchase_sn;
     * return excel
     */
    public function downLoadRecommendList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();

            if (empty($param_info["demand_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '需求单号不能为空']);
            } elseif (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '采购期单号不能为空']);
            }
            $purchase_sn = $param_info["purchase_sn"];
            $purchase_method = DB::table("purchase_date as pda")
                ->leftJoin("purchase_demand as pde", "pde.purchase_sn", "=", "pda.purchase_sn")
                ->where("pda.purchase_sn", $purchase_sn)->first(["method_info"]);
            $purchase_method = objectToArrayZ($purchase_method);

            if (empty($purchase_method)) {
                return response()->json(['code' => '1005', 'msg' => '该采购期暂无需求信息']);
            }

            //获取当前采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($purchase_method);
            if (empty($discount_info)) {
                return response()->json(['code' => '1002', 'msg' => '暂无品牌折扣信息,请先维护折扣信息']);
            }

            $purchase_demand_detail_model = new PurchaseDemandDetailModel();
            $purchase_demand_info = $purchase_demand_detail_model->createDemandDetail($param_info, $discount_info);
            $purchase_demand_list = $purchase_demand_info["purchase_demand_list"];
            $channel_arr = $purchase_demand_info["channel_arr"];

            $obpe = new PHPExcel();
            $obpe->setActiveSheetIndex(0);

            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '渠道折扣及可采数')->getColumnDimension('E')->setWidth(30);

            //获取最大列数
            $channel_num = count($channel_arr);

            //获取最大行数
            $row_last_i = count($purchase_demand_list) + 1;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 1) continue;
                $row_i = $i - 1;
                $real_i = $i + 1;

                $obpe->getActiveSheet()->setCellValue("A" . $real_i, $purchase_demand_list[$row_i]["goods_name"]);
                $obpe->getActiveSheet()->setCellValue("B" . $real_i, $purchase_demand_list[$row_i]["erp_prd_no"]);
                $obpe->getActiveSheet()->setCellValue("C" . $real_i, $purchase_demand_list[$row_i]["erp_merchant_no"]);
                $obpe->getActiveSheet()->setCellValue("D" . $real_i, $purchase_demand_list[$row_i]["spec_sn"]);

                $discount_info = $purchase_demand_list[$row_i]["discount_info"];

                $discount_info = array_values($discount_info);

                for ($j = 0; $j < $channel_num; $j++) {
                    $real_j = $j + 4;
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($real_j);

                    $str = '';
                    $str .= $discount_info[$j]["brand_channel"] . " 折扣:" . $discount_info[$j]["brand_discount"] .
                        " 可采数:" . $discount_info[$j]["may_num"];
                    $obpe->getActiveSheet()->setCellValue($column_name . $real_i, $str)->getColumnDimension($column_name)->setWidth(30);
                }
            }

            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $obpe->getActiveSheet()->mergeCells('E1:' . "$column_last_name" . '1');

            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
            $column_next_num = $column_last_num;
            $column_next_name = \PHPExcel_Cell::stringFromColumnIndex($column_next_num);
            $obpe->getActiveSheet()->setCellValue($column_next_name . '1', "当天可采数")->getColumnDimension($column_next_name)->setWidth(20);

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;

            $commonModel = new CommonModel();
            //改变表格标题样式
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_next_name, $row_end_i);

            $obpe->getActiveSheet()->setTitle('采购需求单表_优采推荐');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购需求单表_优采推荐_' . $str . '.xls';

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
     * description:下载采购需求总表(优采推荐详情页)
     * editor:zongxing
     * type:GET
     * date : 2018.07.03
     * params: 1.采购期编号:purchase_sn;
     * return excel
     */
    public function downLoadRecommendTotalList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            if (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '采购期单号不能为空']);
            }

            $purchase_sn = $param_info["purchase_sn"];
            $purchase_info = DB::table("purchase_date as pda")
                ->leftJoin("purchase_demand as pde", "pde.purchase_sn", "=", "pda.purchase_sn")
                ->where("pda.purchase_sn", $purchase_sn)->first(["method_info", "pde.demand_sn"]);
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

            $purchase_demand_detail_model = new PurchaseDemandDetailModel();
            $purchase_demand_info = $purchase_demand_detail_model->createTotalDemandList($param_info);
            if (empty($purchase_demand_info)) {
                return response()->json(['code' => '1006', 'msg' => '该采购期暂无分配信息,请先分配']);
            }
            $purchase_demand_list = $purchase_demand_info["purchase_demand_list"];

            //获取最大渠道个数
            $channel_num = $purchase_demand_info["channel_num"];

            $obpe = new PHPExcel();
            $obpe->setActiveSheetIndex(0);

            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品参考码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('D1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F1', '美金原价')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G1', 'VIP价')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('H1', '需求量')->getColumnDimension('E')->setWidth(30);
            $obpe->getActiveSheet()->setCellValue('I1', '可采量')->getColumnDimension('E')->setWidth(30);
            $obpe->getActiveSheet()->setCellValue('J1', '实采量')->getColumnDimension('E')->setWidth(30);
            $obpe->getActiveSheet()->setCellValue('K1', '待采量')->getColumnDimension('E')->setWidth(30);
            $obpe->getActiveSheet()->setCellValue('L1', '可采总量')->getColumnDimension('E')->setWidth(30);
            $obpe->getActiveSheet()->setCellValue('M1', '实采总量')->getColumnDimension('E')->setWidth(30);
            $obpe->getActiveSheet()->setCellValue('N1', '渠道折扣及可采数')->getColumnDimension('E')->setWidth(30);

            //获取最大行数
            $row_last_i = count($purchase_demand_list) + 1;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 1) continue;
                $row_i = $i - 1;
                $real_i = $i + 1;
                $total_may_buy_num = intval($purchase_demand_list[$row_i]["total_may_buy_num"]);
                $total_real_buy_num = intval($purchase_demand_list[$row_i]["total_real_buy_num"]);
                $may_buy_num = intval($purchase_demand_list[$row_i]["may_buy_num"]);
                $obpe->getActiveSheet()->setCellValue("A" . $real_i, $purchase_demand_list[$row_i]["spec_sn"]);
                $obpe->getActiveSheet()->setCellValue("B" . $real_i, $purchase_demand_list[$row_i]["erp_ref_no"]);
                $obpe->getActiveSheet()->setCellValue("C" . $real_i, $purchase_demand_list[$row_i]["erp_prd_no"]);
                $obpe->getActiveSheet()->setCellValue("D" . $real_i, $purchase_demand_list[$row_i]["erp_merchant_no"]);
                $obpe->getActiveSheet()->setCellValue("E" . $real_i, $purchase_demand_list[$row_i]["goods_name"]);
                $obpe->getActiveSheet()->setCellValue("F" . $real_i, $purchase_demand_list[$row_i]["spec_price"]);
                $obpe->getActiveSheet()->setCellValue("G" . $real_i, 0);
                $obpe->getActiveSheet()->setCellValue("H" . $real_i, $purchase_demand_list[$row_i]["goods_num"]);
                $obpe->getActiveSheet()->setCellValue("I" . $real_i, $may_buy_num);
                $obpe->getActiveSheet()->setCellValue("J" . $real_i, $purchase_demand_list[$row_i]["real_buy_num"]);
                $obpe->getActiveSheet()->setCellValue("K" . $real_i, $purchase_demand_list[$row_i]["diff_num"]);
                $obpe->getActiveSheet()->setCellValue("L" . $real_i, $total_may_buy_num);
                $obpe->getActiveSheet()->setCellValue("M" . $real_i, $total_real_buy_num);


                if ($may_buy_num < 0) {
                    $obpe->setActiveSheetIndex(0)->getStyle('I' . $real_i)
                        ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                }
                if ($total_real_buy_num >= $total_may_buy_num) {
                    $obpe->setActiveSheetIndex(0)->getStyle('M' . $real_i)
                        ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                }

                $discount_info = $purchase_demand_list[$row_i]["discount_info"];
                $discount_info = array_values($discount_info);
                for ($j = 0; $j < $channel_num; $j++) {
                    $real_j = $j * 6;
                    $real_j_1 = $real_j + 13;
                    $real_j_2 = $real_j + 14;
                    $real_j_3 = $real_j + 15;
                    $real_j_4 = $real_j + 16;
                    $real_j_5 = $real_j + 17;
                    $real_j_6 = $real_j + 18;
                    $column_name1 = \PHPExcel_Cell::stringFromColumnIndex($real_j_1);
                    $column_name2 = \PHPExcel_Cell::stringFromColumnIndex($real_j_2);
                    $column_name3 = \PHPExcel_Cell::stringFromColumnIndex($real_j_3);
                    $column_name4 = \PHPExcel_Cell::stringFromColumnIndex($real_j_4);
                    $column_name5 = \PHPExcel_Cell::stringFromColumnIndex($real_j_5);
                    $column_name6 = \PHPExcel_Cell::stringFromColumnIndex($real_j_6);

                    if (!isset($discount_info[$j]["brand_channel"])) continue;

                    $obpe->getActiveSheet()->setCellValue($column_name1 . $real_i, $discount_info[$j]["brand_channel"] . "-折扣:")
                        ->getColumnDimension($column_name1)->setWidth(20);
                    $obpe->getActiveSheet()->setCellValue($column_name2 . $real_i, $discount_info[$j]["brand_discount"])
                        ->getColumnDimension($column_name2)->setWidth(5);

                    $obpe->getActiveSheet()->setCellValue($column_name3 . $real_i, "可采数:")
                        ->getColumnDimension($column_name3)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name4 . $real_i, $discount_info[$j]["may_channel_num"])
                        ->getColumnDimension($column_name4)->setWidth(5);

                    $obpe->getActiveSheet()->setCellValue($column_name5 . $real_i, "待采数:")
                        ->getColumnDimension($column_name5)->setWidth(10);
                    $obpe->getActiveSheet()->setCellValue($column_name6 . $real_i, $discount_info[$j]["diff_channel_num"])
                        ->getColumnDimension($column_name6)->setWidth(5);
                    $method_property = $discount_info[$j]["method_property"];
                    if ($method_property == 2) {
                        $obpe->setActiveSheetIndex(0)->getStyle($column_name1 . $real_i)
                            ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                        $obpe->setActiveSheetIndex(0)->getStyle($column_name2 . $real_i)
                            ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                        $obpe->setActiveSheetIndex(0)->getStyle($column_name3 . $real_i)
                            ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                        $obpe->setActiveSheetIndex(0)->getStyle($column_name4 . $real_i)
                            ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                        $obpe->setActiveSheetIndex(0)->getStyle($column_name5 . $real_i)
                            ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                        $obpe->setActiveSheetIndex(0)->getStyle($column_name6 . $real_i)
                            ->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                    }
                }
            }

            $currentSheet = $obpe->getSheet(0);
            $column_tmp_last_name = $currentSheet->getHighestColumn();
            $obpe->getActiveSheet()->mergeCells('N1:' . "$column_tmp_last_name" . '1');

            $column_next_num = \PHPExcel_Cell::columnIndexFromString($column_tmp_last_name);
            $column_next_name = \PHPExcel_Cell::stringFromColumnIndex($column_next_num);
            $obpe->getActiveSheet()->setCellValue($column_next_name . '1', "采购量")->getColumnDimension($column_next_name)
                ->setWidth(20);
            $column_last_num = $column_next_num + 1;
            $column_last_name = \PHPExcel_Cell::stringFromColumnIndex($column_last_num);
            $obpe->getActiveSheet()->setCellValue($column_last_name . '1', "是否为搭配（是或否）")
                ->getColumnDimension($column_last_name)->setWidth(20);
            $column_last_2_num = $column_next_num + 2;
            $column_last_2_name = \PHPExcel_Cell::stringFromColumnIndex($column_last_2_num);
            $obpe->getActiveSheet()->setCellValue($column_last_2_name . '1', '对应商品规格码')
                ->getColumnDimension($column_last_2_name)->setWidth(20);

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;

            //改变表格标题样式
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_2_name, $row_end_i);
            $obpe->getActiveSheet()->setTitle('采购需求总表_优采推荐');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购需求总表_优采推荐_' . $purchase_sn . '_' . $str . '.xls';

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
     * description:优采推荐-获取采购需求列表
     * editor:zongxing
     * type:GET
     * create_date : 2018.06.29
     * update_date : 2018.09.27
     * return Object
     */
    public function getDemandList(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();

            //获取采购需求信息
            $demandGoodsModel = new DemandGoodsModel();
            $demand_info = $demandGoodsModel->getDemandPassList($purchase_info);

            $data = $demand_info["purchase_info"];
            $data_num = $demand_info["data_num"];
            $return_info = ['code' => '1000', 'msg' => '获取采购需求列表成功', 'data' => $data, 'data_num' => $data_num];

            if (empty($demand_info["purchase_info"])) {
                $return_info = ['code' => '1002', 'msg' => '暂无待审核采购需求'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }


}