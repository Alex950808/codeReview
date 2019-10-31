<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\CommonModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\DiscountModel;
use App\Model\Vone\OperateLogModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;

/**
 * description:采购需求模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class PurchaseDemandController extends BaseController
{
    /**
     * description:获取采购需求列表
     * editor:zongxing
     * type:GET
     * date : 2018.06.29
     * return Object
     */
    public function getDemandList_stop(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();

            //获取采购需求信息
            $demandGoodsModel = new DemandGoodsModel();
            $demand_info = $demandGoodsModel->getDemandWaitList_stop($purchase_info);

            $code = "1000";
            $msg = "获取采购需求列表成功";
            $data = $demand_info["purchase_info"];
            $data_num = $demand_info["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
            if (empty($demand_info["purchase_info"])) {
                $code = "1002";
                $msg = "暂无待审核采购需求";
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
    public function getTotalDetail(Request $request)
    {
        if ($request->isMethod("post")) {
            $demand_info = $request->toArray();

            //获取采购需求信息
            $demandCountModel = new DemandCountModel();
            $demand_total_detail = $demandCountModel->getPurchaseTotalDetail($demand_info);

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
     * description:打开采购需求详情页
     * editor:zongxing
     * type:POST
     * date : 2018.07.02
     * params: 1.需求单编号:demand_sn;2.采购期编号:purchase_sn;
     * return Object
     */
    public function getDemandDetail_stop(Request $request)
    {
        if ($request->isMethod("post")) {
            $demand_info = $request->toArray();

            if (empty($demand_info["demand_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '需求单号不能为空']);
            } elseif (empty($demand_info["purchase_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '采购期单号有误']);
            }

            //获取采购需求信息
            $demandGoodsModel = new DemandGoodsModel();
            $demand_detail_info = $demandGoodsModel->getDemandDetail_stop($demand_info);

            //获取人员信息
            $sale_user_list = [];
            if (!isset($demand_info["sale_user_id"]) || empty($demand_info["sale_user_id"])) {
                $demand_sn = $demand_info["demand_sn"];
                $purchase_sn = $demand_info["purchase_sn"];

                $sale_user_list = DB::table("sale_user as su")
                    ->leftJoin("user_goods as ug", "ug.sale_user_id", "=", "su.id")
                    ->where("demand_sn", $demand_sn)
                    ->where("purchase_sn", $purchase_sn)
                    ->groupBy("su.id")
                    ->get(["su.id", "user_name"]);
            }

            $code = "1000";
            $msg = "打开采购需求详情页成功";
            $data = $demand_detail_info;
            $return_info = compact('code', 'msg', 'data', "sale_user_list");

            if (empty($demand_detail_info)) {
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
     * description:改变采购需求中商品的信息
     * editor:zongxing
     * type:POST
     * date : 2018.07.12
     * params: 1.采购期编号:purchase_sn;2.采购需求编号:demand_sn;3.商品规格码:spec_sn;4.要更改的字段名:filed_name;
     *          5.要更改的字段值:filed_value;
     * return Object
     */
    public function changeGoodsInfo_stop(Request $request)
    {
        if ($request->isMethod("post")) {
            $demand_goods_info = $request->toArray();

            //获取采购需求信息
            $demandGoodsModel = new DemandGoodsModel();
            $demand_detail_info = $demandGoodsModel->changeDemandGoods_stop($demand_goods_info);

            $code = "1002";
            $msg = "商品信息修改失败";
            $return_info = compact('code', 'msg');

            if ($demand_detail_info) {
                $code = "1000";
                $msg = "商品信息修改成功";
                $return_info = compact('code', 'msg');

                $purchase_sn = $demand_goods_info["purchase_sn"];
                $demand_sn = $demand_goods_info["demand_sn"];
                $spec_sn = $demand_goods_info["spec_sn"];
                $filed_name = $demand_goods_info["filed_name"];
                $filed_value = $demand_goods_info["filed_value"];

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_purchase_method',
                    'bus_desc' => '采购需求-改变采购需求中商品的信息-采购期单号：' . $purchase_sn . '需求单号：' . $demand_sn . '商品规格码：'
                        . $spec_sn . '字段：' . $filed_name . '更改为：' . $filed_value,
                    'bus_value' => '采购期单号：' . $purchase_sn . '需求单号：' . $demand_sn . '商品规格码：'
                        . $spec_sn,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '采购成本系数-改变采购成本系数状态',
                    'module_id' => 2,
                    'have_detail' => 0,
                ];
                $operateLogModel->insertMoreLog($logData);
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }


    /**
     * description:确认提交采购需求审核
     * editor:zongxing
     * type:POST
     * date : 2018.07.02
     * params: 1.采购需求表数据:goods_list;
     * return Object
     */
    public function checkDemand_stop(Request $request)
    {
        if ($request->isMethod("post")) {
            $demand_info = $request->toArray();
            $demand_info = $demand_info["goods_list"];

            //获取采购需求信息
            $demandGoodsModel = new DemandGoodsModel();
            $demand_detail_info = $demandGoodsModel->changeDemand($demand_info);

            $code = "1000";
            $msg = "需求审核成功";
            $return_info = compact('code', 'msg');

            if (!$demand_detail_info) {
                $code = "1002";
                $msg = "需求审核失败";
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
     * description:确认提交采购需求审核
     * editor:zongxing
     * type:POST
     * date : 2018.07.02
     * params: 1.采购需求表数据:goods_list;2.status:(2:表示审核未通过;3:表示审核通过)
     * return Object
     */
    public function changeDemandStatus_stop(Request $request)
    {
        if ($request->isMethod("post")) {
            $demand_info = $request->toArray();
            $demand_goods_info = $demand_info["goods_list"];
            $demand_status = $demand_info["status"];

            if ($demand_status != 2 && $demand_status != 3) {
                return response()->json(['code' => '1002', 'msg' => '您操作的采购需求状态有误，请重新提交']);
            }
            //获取采购期单号和需求单号
            $purchase_sn = $demand_goods_info[0]["purchase_sn"];
            $demand_sn = $demand_goods_info[0]["demand_sn"];

            //更新采购期商品统计信息和采购需求单状态
            $demandGoodsModel = new DemandGoodsModel();
            $demand_detail_info = $demandGoodsModel->changeDemandStatus_stop($demand_info, $purchase_sn, $demand_sn);

            $code = "1000";
            $msg = "需求审核成功";
            $return_info = compact('code', 'msg');

            if (!$demand_detail_info) {
                return response()->json(['code' => '1003', 'msg' => '需求审核失败']);
            }

            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'jms_demand',
                'bus_desc' => '采购需求-确认提交采购需求审核-采购期单号：' . $purchase_sn . '-需求单号：' . $demand_sn .
                    '-status改为：' . $demand_status,
                'bus_value' => '采购期单号：' . $purchase_sn . '-需求单号：' . $demand_sn,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '采购需求-确认提交采购需求审核',
                'module_id' => 2,
                'have_detail' => 0,
            ];
            $operateLogModel->insertMoreLog($logData);
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:下载采购需求表(待审核采购需求详情页)
     * editor:zongxing
     * type:GET
     * date : 2018.07.03
     * params: 1.需求单编号:demand_sn;2.采购期编号:purchase_sn;
     * return excel
     */
    public function downLoadDemandList_stop(Request $request)
    {
        if ($request->isMethod("get")) {
            $demand_info = $request->toArray();

            if (!isset($demand_info["demand_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号有误']);
            } elseif (!isset($demand_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号有误']);
            }

            //获取采购需求信息
            $demandGoodsModel = new DemandGoodsModel();
            $demand_detail_info = $demandGoodsModel->getDemandDetail($demand_info);

            //对表内容进行格式化
            foreach ($demand_detail_info as $k => $v) {
                $v = (array)$v;
                $demand_detail_info[$k] = $v;
            }

            $obpe = new PHPExcel();

            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '需求量')->getColumnDimension('E')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F1', '可采数量')->getColumnDimension('F')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G1', '是否为热品')->getColumnDimension('G')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('H1', '是否可采')->getColumnDimension('H')->setWidth(20);
            $obpe->setActiveSheetIndex(0);

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

            //获取最大行数
            $row_last_i = count($demand_detail_info) + 1;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 2) continue;
                $row_i = $i - 2;
                for ($j = 0; $j < $column_last_num; $j++) {
                    //获取列名
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                    $demand_detail_info[$row_i] = array_values($demand_detail_info[$row_i]);

                    if ($j == 6 || $j == 7) {
                        if ($demand_detail_info[$row_i][$j] == 1) {
                            $obpe->getActiveSheet()->setCellValue($column_name . $i, "是");
                        } elseif ($demand_detail_info[$row_i][$j] == 0) {
                            $obpe->getActiveSheet()->setCellValue($column_name . $i, "否");
                        }
                    } else {
                        $obpe->getActiveSheet()->setCellValue($column_name . $i, $demand_detail_info[$row_i][$j]);
                    }
                }
            }

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;
            //改变表格标题样式
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
            //改变表格内容样式
            $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            $obpe->getActiveSheet()->setTitle('采购需求单表');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购需求单表_' . $str . '.xls';

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
     * description:下载采购需求总表
     * editor:zongxing
     * type:GET
     * date : 2018.07.11
     * params: 采购期编号:purchase_sn;
     * return excel
     */
    public function downLoadTotalList_stop(Request $request)
    {
        if ($request->isMethod("get")) {
            $demand_info = $request->toArray();
            //获取采购需求信息
            $discountModel = new DiscountModel();
            $demand_detail_info = $discountModel->getDemandTotalDetail($demand_info);

            //对表内容进行格式化
            foreach ($demand_detail_info as $k => $v) {
                $v = (array)$v;
                $demand_detail_info[$k] = $v;
            }

            $obpe = new PHPExcel();

            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '需求数量')->getColumnDimension('E')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F1', '可采数量')->getColumnDimension('F')->setWidth(20);
            $obpe->setActiveSheetIndex(0);

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

            //获取最大行数
            $row_last_i = count($demand_detail_info) + 1;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 2) continue;
                $row_i = $i - 2;
                for ($j = 0; $j < $column_last_num; $j++) {
                    //获取列名
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                    $demand_detail_info[$row_i] = array_values($demand_detail_info[$row_i]);

                    $obpe->getActiveSheet()->setCellValue($column_name . $i, $demand_detail_info[$row_i][$j]);
                }
            }

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;
            //改变表格标题样式
            $commonModel = new CommonModel();
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
            //改变表格内容样式
            $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            $obpe->getActiveSheet()->setTitle('采购需求总表');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购需求总表_' . $str . '.xls';

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

}