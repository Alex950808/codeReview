<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\CommonModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\RealPurchaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;

/**
 * description:采购数据模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class DiffController extends BaseController
{
    /**
     * description:获取待确认差异批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
//    public function getBatchList(Request $request)
//    {
//        if ($request->isMethod("get")) {
//            $purchase_info = $request->toArray();
//            //获取采购期对应的批次列表
//            $realPurchaseModel = new RealPurchaseModel();
//            $status = 2;
//            $batch_list_info = $realPurchaseModel->getDiffList($status, $purchase_info, $request);
//
//            $code = "1002";
//            $msg = "暂无采购数据批次";
//            $return_info = compact('code', 'msg');
//            if (!empty($batch_list_info["purchase_info"])) {
//                $code = "1000";
//                $msg = "获取采购数据批次列表成功";
//                $data = $batch_list_info["purchase_info"];
//                $data_num = $batch_list_info["data_num"];
//                $return_info = compact('code', 'msg', 'data_num', 'data');
//            }
//        } else {
//            $code = "1001";
//            $msg = "请求错误";
//            $return_info = compact('code', 'msg');
//        }
//        return response()->json($return_info);
//    }
    public function getBatchList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            //获取采购期及其商品数据总表
            $param_info['status'] = 2;
            $task_link = 'confirmDifference';
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info, null, $request, $task_link);
            if (empty($purchase_data_list["purchase_info"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无待确认差异批次']);
            }
            $code = "1000";
            $msg = "获取采购数据批次列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取超时未确认差异批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
//    public function getExtireBatchList(Request $request)
//    {
//        if ($request->isMethod("get")) {
//            $purchase_info = $request->toArray();
//            //获取采购期对应的批次列表
//            $realPurchaseModel = new RealPurchaseModel();
//            $status = 2;
//            $batch_list_info = $realPurchaseModel->getDiffExtireList($status, $purchase_info, $request);
//
//            $code = "1002";
//            $msg = "暂无采购数据批次";
//            $return_info = compact('code', 'msg');
//
//            if (!empty($batch_list_info["purchase_info"])) {
//                $code = "1000";
//                $msg = "获取采购数据批次列表成功";
//                $data = $batch_list_info["purchase_info"];
//                $data_num = $batch_list_info["data_num"];
//                $return_info = compact('code', 'msg', 'data_num', 'data');
//            }
//        } else {
//            $code = "1001";
//            $msg = "请求错误";
//            $return_info = compact('code', 'msg');
//        }
//        return response()->json($return_info);
//    }
    public function getExtireBatchList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            //获取采购期及其商品数据总表
            $param_info['status'] = 2;
            $expire = 2;
            $task_link = 'confirmDifference';
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info, $expire, $request, $task_link);
            if (empty($purchase_data_list["purchase_info"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无超时未确认差异批次']);
            }
            $code = "1000";
            $msg = "获取采购数据批次列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
        } else {
            $return_info = ['code' => 'code', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取采购批次详情
     * editor:zongxing
     * type:POST
     * * params: 1.实采批次单号:real_purchase_sn;
     * date : 2018.07.10
     * return Object
     */
    public function getBatchDetail(Request $request)
    {
        if ($request->isMethod("post")) {
            $batch_info = $request->toArray();
            if (empty($batch_info['purchase_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            } elseif (empty($batch_info['group_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '批次组合不能为空']);
            } elseif (empty($batch_info['real_purchase_sn'])) {
                return response()->json(['code' => '1004', 'msg' => '批次单号不能为空']);
            } elseif (empty($batch_info['is_mother'])) {
                return response()->json(['code' => '1005', 'msg' => '批次父子代码不能为空']);
            }
            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $batch_goods_info = $realPurchaseDetailModel->getBatchGoodsDetail($batch_info);
            $return_info = ['code' => '1000', 'msg' => '获取采购批次详情成功', 'data' => $batch_goods_info];
            if (empty($batch_goods_info)) {
                $return_info = ['code' => '1006', 'msg' => '请求参数错误'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取采购批次汇总详情
     * editor:zongxing
     * type:POST
     * * params: 1.采购期单号:purchase_sn;
     * date : 2018.07.10
     * return Object
     */
    public function getTotalDetail(Request $request)
    {
        if ($request->isMethod("post")) {
            $purchase_info = $request->toArray();

            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $purchase_goods_info = $realPurchaseDetailModel->getPurchaseGoodsDetail($purchase_info);
            if (empty($purchase_goods_info)) {
                response()->json(['code' => '1002', 'msg' => '采购期单号错误']);
            }
            $code = "1000";
            $msg = "获取采购批次汇总详情成功";
            $data = $purchase_goods_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:改变批次状态（确认差异或拒绝）
     * editor:zongxing
     * type:POST
     * params: 1.实采批次单号:real_purchase_sn;2.要修改的状态:status;3.是否生成子批次单:create_child_sn
     * date : 2018.07.10
     * return Object
     */
    public function changeStatus(Request $request)
    {
        if ($request->isMethod("post")) {
            $batch_info = $request->toArray();
            if (empty($batch_info['purchase_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            } elseif (empty($batch_info['group_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '批次组合不能为空']);
            } elseif (empty($batch_info['real_purchase_sn'])) {
                return response()->json(['code' => '1004', 'msg' => '批次单号不能为空']);
            } elseif (empty($batch_info['is_mother'])) {
                return response()->json(['code' => '1005', 'msg' => '批次父子代码不能为空']);
            }
            //获取要更改的实采单状态
            $status = $batch_info["status"];
            if ($status != 1 && $status != 6) {
                return response()->json(['code' => '1006', 'msg' => '您操作的采购需求状态有误，请重新提交']);
            }

            //更新实采批次的状态
            $diff_time = date("Y-m-d H:i:s");
            $realPurchaseModel = new RealPurchaseModel();
            $loginUserInfo = $request->user();
            $update_info = [
                'status' => $status,
                'diff_time' => $diff_time,
                'is_sort' => 0
            ];
            $purchase_goods_info = $realPurchaseModel->changeRealPurStatus($loginUserInfo, $batch_info, $update_info);
            if (!$purchase_goods_info) {
                return response()->json(['code' => '1007', 'msg' => '更新实采批次的状态失败']);
            }
            $return_info = ['code' => '1000', 'msg' => '更新实采批次的状态成功'];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:增加商品备注
     * editor:zongxing
     * type:POST
     * params: 1.实采批次单号:real_purchase_sn;2.商品规格码:spec_sn;3.商品备注:purchase_remark;
     * date : 2018.08.02
     * return Object
     */
    public function addDiffRemark(Request $request)
    {
        if ($request->isMethod("post")) {
            $batch_info = $request->toArray();

            //更新实采批次的状态
            $realPurchaseModel = new RealPurchaseModel();
            $purchase_goods_info = $realPurchaseModel->addDiffRemark($request, $batch_info);

            $code = "1000";
            $msg = "增加商品备注成功";
            $return_info = compact('code', 'msg', 'data');

            if (!$purchase_goods_info) {
                $code = "1003";
                $msg = "增加商品备注失败";
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
     * description:下载采购批次单表
     * editor:zongxing
     * type:GET
     * date : 2018.07.11
     * params: 1.实采批次单号:real_purchase_sn;
     * return excel
     */
    public function downLoadDiffList(Request $request)
    {
        if ($request->isMethod("get")) {
            $batch_info = $request->toArray();
            if (empty($batch_info['purchase_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            } elseif (empty($batch_info['group_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '批次组合不能为空']);
            } elseif (empty($batch_info['real_purchase_sn'])) {
                return response()->json(['code' => '1004', 'msg' => '批次单号不能为空']);
            } elseif (empty($batch_info['is_mother'])) {
                return response()->json(['code' => '1005', 'msg' => '批次父子代码不能为空']);
            }
            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $batch_goods_info = $realPurchaseDetailModel->getBatchGoodsDetail($batch_info);

            //对表内容进行格式化
            foreach ($batch_goods_info as $k => $v) {
                unset($v["brand_id"]);
                $batch_goods_info[$k] = $v;
            }

            $obpe = new PHPExcel();

            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '实采数量')->getColumnDimension('E')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F1', '清点数量')->getColumnDimension('F')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G1', '差异值')->getColumnDimension('G')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('H1', '备注')->getColumnDimension('H')->setWidth(20);
            $obpe->setActiveSheetIndex(0);

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

            //获取最大行数
            $row_last_i = count($batch_goods_info) + 2;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 2) continue;
                $row_i = $i - 2;
                for ($j = 0; $j < $column_last_num; $j++) {
                    //获取列名
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                    $batch_goods_info[$row_i] = array_values($batch_goods_info[$row_i]);

                    $obpe->getActiveSheet()->setCellValue($column_name . $i, $batch_goods_info[$row_i][$j]);
                }
            }

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;

            $commonModel = new CommonModel();
            //改变表格标题样式
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
            //改变表格内容样式
            $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            $obpe->getActiveSheet()->setTitle('采购批次单表_差异管理');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购批次单表_差异管理_' . $str . '.xls';

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
     * description:下载采购批次总表
     * editor:zongxing
     * type:GET
     * date : 2018.07.11
     * params: 1.采购期单号:purchase_sn;
     * return excel
     */
    public function downLoadTotalList(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();

            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $purchase_goods_info = $realPurchaseDetailModel->getPurchaseGoodsDetail($purchase_info);

            $obpe = new PHPExcel();
            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '实采数量')->getColumnDimension('E')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F1', '清点数量')->getColumnDimension('F')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G1', '差异值')->getColumnDimension('G')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('H1', '备注')->getColumnDimension('H')->setWidth(20);
            $obpe->setActiveSheetIndex(0);

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

            //获取最大行数
            $row_last_i = count($purchase_goods_info) + 2;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 2) continue;
                $row_i = $i - 2;
                for ($j = 0; $j < $column_last_num; $j++) {
                    //获取列名
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                    $purchase_goods_info[$row_i] = array_values($purchase_goods_info[$row_i]);

                    $obpe->getActiveSheet()->setCellValue($column_name . $i, $purchase_goods_info[$row_i][$j]);
                }
            }

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;

            $commonModel = new CommonModel();
            //改变表格标题样式
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
            //改变表格内容样式
            $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            $obpe->getActiveSheet()->setTitle('采购批次总表_差异管理');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购批次总表_差异管理_' . $str . '.xls';

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