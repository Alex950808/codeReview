<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\DemandModel;
use App\Model\Vone\OperateLogModel;
use App\Model\Vone\PurchaseDateModel;
use App\Model\Vone\GoodsModel;
use App\Model\Vone\PurchaseDemandModel;
use App\Model\Vone\RealPurchaseAuditModel;
use App\Model\Vone\RealPurchaseDeatilAuditModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\PurchaseDemandDetailModel;
use App\Model\Vone\RealPurchaseModel;
use App\Model\Vone\DepartSortModel;
use App\Model\Vone\DepartSortGoodsModel;
use App\Model\Vone\SortDataModel;
use App\Model\Vone\SumDemandModel;
use App\Model\Vone\UserSortGoodsModel;
use App\Model\Vone\SortBatchModel;
use App\Modules\ParamsCheckSingle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use App\Modules\ArrayGroupBy;

/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class DateController extends BaseController
{
    /**
     * description:获取采购渠道和采购方式列表
     * editor:zongxing
     * type:GET
     * date : 2018.08.16
     * return Object
     */
    public function channel_method_list(Request $request)
    {
        if ($request->isMethod('get')) {
            $get_purchase_info = $request->toArray();

            if (!empty($get_purchase_info["purchase_sn"])) {
                $purchase_sn = $get_purchase_info["purchase_sn"];

                $purchase_info = DB::table("purchase_date")->where("purchase_sn", $purchase_sn)->first();
                $purchase_info = objectToArrayZ($purchase_info);

                if (empty($purchase_info))
                    return response()->json(['code' => '1005', 'msg' => '采购期单号有误']);
                if ($purchase_info["status"] != 2)
                    return response()->json(['code' => '1006', 'msg' => '采购单状态异常']);
            }

            $purchase_channel_info = DB::table("purchase_channels as pc")
                ->leftJoin("purchase_method as pm", "pm.id", "=", "pc.method_id")
                ->orderBy('pc.create_time', 'desc')
                ->get(["pc.id", "channels_sn", "channels_name", "method_name", "method_id"]);
            $purchase_channel_info = objectToArrayZ($purchase_channel_info);

            $purchase_method_info = DB::table("purchase_method")->orderBy('create_time', 'desc')->get();
            $purchase_method_info = objectToArrayZ($purchase_method_info);

            $channel_method_info["channel_info"] = $purchase_channel_info;
            $channel_method_info["method_info"] = $purchase_method_info;

            $code = "1000";
            $msg = "获取采购渠道和方式列表成功";
            $data = $channel_method_info;
            $return_info = compact('code', 'msg', 'data');

            if (empty($purchase_channel_info) || empty($purchase_method_info)) {
                $code = "1002";
                $msg = "获取采购渠道和方式列表失败";
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
     * description:创建采购期
     * editor:zongxing
     * type:POST
     * date : 2018.06.23
     * params: 1.开始时间:start_time;2.提货时间:delivery_time;3,提货队名称:delivery_team;
     *             4.提货队人数:delivery_pop_num;5.航班号:flight_sn;6,采购渠道:channels_list;7,预计到港天数:predict_day
     *              8.purchase_notice
     * return Object
     */
    public function createPurchaseDate(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_date_info = $request->toArray();

            if (empty($purchase_date_info["start_time"])) {
                return response()->json(['code' => '1002', 'msg' => '开始日期不能为空']);
            } else if (empty($purchase_date_info["delivery_team"])) {
                return response()->json(['code' => '1004', 'msg' => '提货队名不能为空']);
            } else if (empty($purchase_date_info["delivery_pop_num"])) {
                return response()->json(['code' => '1005', 'msg' => '提货队人数不能为空']);
            } else if (empty($purchase_date_info["channels_list"])) {
                return response()->json(['code' => '1007', 'msg' => '采购渠道不能为空']);
            } else if (empty($purchase_date_info["method_info"])) {
                return response()->json(['code' => '1012', 'msg' => '采购方式不能为空']);
            }
//            else if (empty($purchase_date_info["end_time"])) {
//                return response()->json(['code' => '1014', 'msg' => '提报需求结束时间不能为空']);
//            }
            else if (empty($purchase_date_info["delivery_time"])) {
                return response()->json(['code' => '1015', 'msg' => '提货日期不能为空']);
            } else if (empty($purchase_date_info["channels_info"])) {
                return response()->json(['code' => '1017', 'msg' => '采购渠道id不能为空']);
            }

            $start_date = $purchase_date_info["start_time"];
            $start_time = strtotime($start_date);
            $now_date = date("Y-m-d", time());
            $now_time = strtotime($now_date);
            if ($start_time < $now_time) {
                return response()->json(['code' => '1008', 'msg' => '开始日期必须大于等于当前日期']);
            }

            $status = 1;//默认为准备中
            if ($now_time == $start_time) {
                $status = 2;
            }

//            $end_time = strtotime($purchase_date_info['end_time']);
//            $after_time = strtotime('+1 day', $start_time);
//
//            if ($end_time <= $start_time) {
//                return response()->json(['code' => '1013', 'msg' => '提报需求结束时间必须大于开始时间']);
//            }
//
//            if ($end_time <= $after_time) {
//                return response()->json(['code' => '1013', 'msg' => '提报需求结束时间和开始时间必须间隔一天']);
//            }

            $delivery_time = strtotime($purchase_date_info['delivery_time']);
            if ($delivery_time <= $start_time) {
                return response()->json(['code' => '1016', 'msg' => '提货日期必须大于开始日期']);
            }

            //计算采购期编号
            $date_model = new PurchaseDateModel();
            $model_field = "purchase_sn";
            $pin_head = "HD-CG-";
            $last_purchase_sn = createNo($date_model, $model_field, $pin_head);
            $insert_purchase_date_info["purchase_sn"] = $last_purchase_sn;
            $insert_purchase_date_info["status"] = $status;
            $insert_purchase_date_info["delivery_team"] = $purchase_date_info['delivery_team'];
            $insert_purchase_date_info["delivery_pop_num"] = intval($purchase_date_info['delivery_pop_num']);
            $insert_purchase_date_info["delivery_time"] = $purchase_date_info['delivery_time'];
            $insert_purchase_date_info["start_time"] = $purchase_date_info['start_time'];
            //$insert_purchase_date_info["end_time"] = $purchase_date_info['end_time'];
            $insert_purchase_date_info["purchase_notice"] = '';
            if (isset($purchase_date_info['purchase_notice'])){
                $insert_purchase_date_info["purchase_notice"] = $purchase_date_info['purchase_notice'];
            }

            $insert_purchase_date_info["channels_list"] = json_encode($purchase_date_info["channels_list"], JSON_UNESCAPED_UNICODE);
            $insert_purchase_date_info["method_info"] = json_encode($purchase_date_info["method_info"], JSON_UNESCAPED_UNICODE);
            $insert_purchase_date_info["channels_info"] = json_encode($purchase_date_info["channels_info"], JSON_UNESCAPED_UNICODE);

            $createRes = DB::table("purchase_date")->insert($insert_purchase_date_info);
            $code = "1011";
            $msg = "增加采购期失败";
            $return_info = compact('code', 'msg');
            if ($createRes) {
                $code = "1000";
                $msg = "增加采购期成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_purchase_date',
                    'bus_desc' => '创建采购期，采购单号（bus_value）：' . $last_purchase_sn,
                    'bus_value' => $last_purchase_sn,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '采购期-创建采购期',
                    'module_id' => 2,
                    'have_detail' => 0,
                ];
                $operateLogModel->insertLog($logData);
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:打开编辑采购期详情页
     * editor:zongxing
     * type:POST
     * date : 2018.07.02
     * params: 1.开始时间:start_time;2.提货时间:delivery_time;3,提货队名称:delivery_team;
     *             4.提货队人数:delivery_pop_num;5.航班号:flight_sn;6,采购渠道:channels_list
     * return Object
     */
    public function editPurchaseDate(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_date_info = $request->toArray();
            //获取采购期编号
            $purchase_sn = $purchase_date_info["purchase_sn"];
            //打开采购期编辑页面
            $purchaseDateModel = new PurchaseDateModel();
            $purchase_detail_info = $purchaseDateModel->openPurchaseDate($purchase_sn);
            $code = "1002";
            $msg = "获取采购期详情失败";
            $return_info = compact('code', 'msg');
            if (!empty($purchase_detail_info)) {
                $purchase_detail_info["channels_list"] = json_decode($purchase_detail_info["channels_list"]);
                $purchase_detail_info["method_info"] = json_decode($purchase_detail_info["method_info"]);
                $code = "1000";
                $msg = "获取采购期详情成功";
                $data = $purchase_detail_info;
                $return_info = compact('code', 'msg', 'data');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:确认提交编辑采购期
     * editor:zongxing
     * type:POST
     * date : 2018.07.02
     * params: 1.开始时间:start_time;2.提货时间:delivery_time;3,提货队名称:delivery_team;
     *             4.提货队人数:delivery_pop_num;5.航班号:flight_sn;6,采购渠道:channels_list
     * return Object
     */
    public function doEditPurchaseDate(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_date_info = $request->toArray();
            //获取采购期编号
            $purchase_sn = $purchase_date_info["purchase_sn"];

            //获取采购渠道信息
            $purchaseDateModel = new PurchaseDateModel();
            $purchase_detail_info = $purchaseDateModel->openPurchaseDate($purchase_sn);

            if (empty($purchase_detail_info)) {
                return response()->json(['code' => '1009', 'msg' => '采购期单号有误']);
            }

 //           $purchase_detail_info["channels_list"] = json_encode($purchase_detail_info["channels_list"]);
//            $purchase_detail_info["method_info"] = json_encode($purchase_detail_info["method_info"]);

            //进行采购期编辑保存
            if (empty($purchase_date_info["start_time"])) {
                return response()->json(['code' => '1002', 'msg' => '开始时间不能为空']);
            } else if (empty($purchase_date_info["delivery_team"])) {
                return response()->json(['code' => '1004', 'msg' => '提货队名不能为空']);
            } else if (empty($purchase_date_info["delivery_pop_num"])) {
                return response()->json(['code' => '1005', 'msg' => '提货队人数不能为空']);
            } else if (empty($purchase_date_info["channels_list"])) {
                return response()->json(['code' => '1007', 'msg' => '采购渠道不能为空']);
            } else if (empty($purchase_date_info["method_info"])) {
                return response()->json(['code' => '1011', 'msg' => '采购方式不能为空']);
            }
//            else if (empty($purchase_date_info["end_time"])) {
//                return response()->json(['code' => '1013', 'msg' => '提报需求结束时间不能为空']);
//            }
            else if (empty($purchase_date_info["delivery_time"])) {
                return response()->json(['code' => '1015', 'msg' => '提货日期不能为空']);
            } else if (empty($purchase_date_info["channels_info"])) {
                return response()->json(['code' => '1017', 'msg' => '采购渠道id不能为空']);
            }

            $start_time = strtotime($purchase_date_info["start_time"]);
            //$end_time = strtotime($purchase_date_info['end_time']);
            $after_time = strtotime('+1 day', $start_time);

            $now_date = date("Y-m-d", time());
            $now_time = strtotime($now_date);
            if ($start_time < $now_time) {
                return response()->json(['code' => '1018', 'msg' => '开始日期必须大于等于当前日期']);
            }

//            if ($end_time <= $start_time){
//                return response()->json(['code' => '1019', 'msg' => '提报需求结束时间必须大于开始时间']);
//            }
//            if ($end_time <= $after_time){
//                return response()->json(['code' => '1014', 'msg' => '提报需求结束时间和开始时间必须间隔一天']);
//            }
            $delivery_time = strtotime($purchase_date_info['delivery_time']);
            if ($delivery_time <= $start_time){
                return response()->json(['code' => '1016', 'msg' => '提货日期必须大于开始日期']);
            }

            $channels_list = json_encode($purchase_date_info["channels_list"], JSON_UNESCAPED_UNICODE);
            $update_purchase_date_info["channels_list"] = $channels_list;

            $method_info = json_encode($purchase_date_info["method_info"], JSON_UNESCAPED_UNICODE);
            $update_purchase_date_info["method_info"] = $method_info;

            $channels_info = json_encode($purchase_date_info["channels_info"], JSON_UNESCAPED_UNICODE);
            $update_purchase_date_info["channels_info"] = $channels_info;

            $update_purchase_date_info["start_time"] = trim($purchase_date_info["start_time"]);
            //$update_purchase_date_info["end_time"] = trim($purchase_date_info["end_time"]);
            $update_purchase_date_info["delivery_team"] = trim($purchase_date_info["delivery_team"]);
            $update_purchase_date_info["delivery_pop_num"] = intval($purchase_date_info["delivery_pop_num"]);
            $update_purchase_date_info["delivery_team"] = trim($purchase_date_info["delivery_team"]);
            $update_purchase_date_info["delivery_time"] = trim($purchase_date_info["delivery_time"]);

            if (isset($purchase_date_info['purchase_notice'])){
                $update_purchase_date_info["purchase_notice"] = $purchase_date_info['purchase_notice'];
            }

            $purchase_update_res = DB::table("purchase_date")->where("purchase_sn", "=", $purchase_sn)->update($update_purchase_date_info);
            $code = "1010";
            $msg = "编辑采购期失败";
            $return_info = compact('code', 'msg');

            if ($purchase_update_res !== false) {
                $code = "1000";
                $msg = "编辑采购期成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_purchase_date',
                    'bus_desc' => '采购期-确认提交编辑采购期-采购期单号：' . $purchase_sn,
                    'bus_value' => $purchase_sn,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '采购期-确认提交编辑采购期',
                    'module_id' => 2,
                    'have_detail' => 1,
                ];
                $logDetailData["table_name"] = 'operate_log_detail';

                foreach ($purchase_date_info as $k => $v) {
                    if (isset($purchase_detail_info[$k]) && $purchase_detail_info[$k] != $v) {
                        if ($k == "channels_list") {
                            $old_channels_list = $purchase_detail_info[$k];
                            $logDetailData["update_info"][] = [
                                'table_field_name' => $k,
                                'field_old_value' => $old_channels_list,
                                'field_new_value' => $channels_list,
                            ];
                        } else if ($k == "method_info") {
                            $old_method_info = $purchase_detail_info[$k];
                            $logDetailData["update_info"][] = [
                                'table_field_name' => $k,
                                'field_old_value' => $old_method_info,
                                'field_new_value' => $method_info,
                            ];
                        } else if ($k == "channels_info") {
                            $old_channels_info = $purchase_detail_info[$k];
                            $logDetailData["update_info"][] = [
                                'table_field_name' => $k,
                                'field_old_value' => $old_channels_info,
                                'field_new_value' => $channels_info,
                            ];
                        } else {
                            $logDetailData["update_info"][] = [
                                'table_field_name' => $k,
                                'field_old_value' => $purchase_detail_info[$k],
                                'field_new_value' => $v,
                            ];
                        }
                    }
                }
                if (isset($logDetailData["update_info"])) {
                    $operateLogModel->insertMoreLog($logData, $logDetailData);
                }
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:获取采购期列表
     * editor:zongxing
     * type:GET
     * date : 2018.06.25
     * return Object
     */
    public function getDateList(Request $request)
    {
        $purchase_date = new PurchaseDateModel();
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            //获取采购期列表
            $param_info['status'] = 1;
            $purchase_date_info = $purchase_date->getPurchaseDateList($param_info);
            $return_info = ['code' => '1002', 'msg' => '暂无采购期'];
            if (!empty($purchase_date_info["data"])) {
                $return_info = ['code' => '1000', 'msg' => '获取采购期列表成功', 'data' => $purchase_date_info];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取首页采购期批次任务列表
     * editor:zongxing
     * type:GET
     * date : 2018.08.21
     * return Object
     */
    public function batchTaskList(Request $request)
    {
        if ($request->isMethod('get')) {
            //获取采购期列表
            $purchase_date = new PurchaseDateModel();
            $purchase_date_info = $purchase_date->get_batch_task_list($request);
            $return_info = ['code' => '1000', 'msg' => '获取采购期批次任务列表成功', 'data' => $purchase_date_info];
            if (empty($purchase_date_info['data'])) {
                $return_info = ['code' => '1002', 'msg' => '暂无采购期批次任务'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:过往采购期列表
     * editor:zongxing
     * type:GET
     * date : 2018.06.25
     * return Object
     */
    public function getPassDateList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            //获取采购期列表
            $param_info['status'] = 2;
            $purchase_date = new PurchaseDateModel();
            $purchase_date_info = $purchase_date->getPurchaseDateList($param_info);

            $return_info = ['code' => '1002', 'msg' => '暂无过往采购期'];
            if (!empty($purchase_date_info["data"])) {
                $return_info = ['code' => '1000', 'msg' => '获取过往采购期列表成功', 'data' => $purchase_date_info];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取过往采购期汇总详情
     * editor:zongxing
     * type:POST
     * * params: 1.采购期单号:purchase_sn;
     * date : 2018.07.11
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
            $msg = "获取过往采购期汇总详情成功";
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


    /**
     * description:改变表格标题样式
     * editor:zongxing
     * date : 2018.06.28
     * params: 1.excel对象:$obj_excel;2.最后一列的名称:$column_last_name;
     * return Object
     */
    public function changeTableTitle($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
    {
        //标题居中+加粗
        $obj_excel->getActiveSheet()->getStyle($column_first_name . $row_first_i . ":" . $column_last_name . $row_last_i)
            ->applyFromArray(
                array(
                    'font' => array(
                        'bold' => true
                    ),
                    'alignment' => array(
                        'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                    )
                )
            );
    }

    /**
     * description:改变表格内容样式
     * editor:zongxing
     * date : 2018.06.28
     * params: 1.excel对象:$obj_excel;2.最后一列的名称:$column_last_name;3.最大行号:$row_end;
     * return Object
     */
    public function changeTableContent($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
    {
        //内容只居中
        $obj_excel->getActiveSheet()->getStyle($column_first_name . $row_first_i . ":" . $column_last_name . $row_last_i)->applyFromArray(
            array(
                'alignment' => array(
                    'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                )
            )
        );
    }

    /**
     * description:定时任务-更新采购期状态
     * editor:zongxing
     * date : 2018.08.09
     * return String
     */
    public function changeDateToStart()
    {
        $log = new Logger('changeDateStatus');
        $log->pushHandler(new StreamHandler(storage_path('logs/changeDateStatus.log'), Logger::INFO));
        $log->addInfo("更新采购期状态为进行中-开始");

        $now_date = date("Y-m-d");

        //采购期状态由准备中变为进行中
        $update_data["status"] = 2;
        DB::table("purchase_date")
            ->where("start_time", "<=", $now_date)
            ->where("status", 1)
            ->update($update_data);

        $log->addInfo("采购期状态更新成功");
        $log->addInfo("更新采购期状态为进行中-结束");
        return 'success';
    }

    /**
     * description:定时任务-更新采购期状态
     * editor:zongxing
     * date : 2018.08.09
     * return String
     */
    public function changeDateToDisable()
    {
        $log = new Logger('changeDateStatus');
        $log->pushHandler(new StreamHandler(storage_path('logs/changeDateStatus.log'), Logger::INFO));
        $log->addInfo("更新采购期状态为失效-开始");

        $now_date = date("Y-m-d");
        $purchase_info = DB::table("purchase_date as pd")
            ->leftJoin("demand as d", "d.purchase_sn", "=", "pd.purchase_sn")
            ->where("status", 2)
            ->where("end_time", "<", $now_date)
            ->get(["pd.purchase_sn"]);
        $purchase_info = objectToArrayZ($purchase_info);

        foreach ($purchase_info as $k => $v) {
            $update_data["status"] = 4;
            DB::table("purchase_date")
                ->where("purchase_sn", $v["purchase_sn"])
                ->update($update_data);
        }

        $log->addInfo("采购期状态更新成功");
        $log->addInfo("更新采购期状态为提货中-结束");
        return 'success';
    }

    /**
     * description:采购任务-获取需要提醒的采购期任务信息
     * editor:zongxing
     * date : 2018.08.21
     * return String
     */
    public function getPurchaseTask(Request $request)
    {
        if ($request->method("get")) {
            $now_date = date("Y-m-d");
            $now_h = date("H");

            $now_i = date("i");
            if ($now_i <= 30) {
                $now_time = $now_h . ":30:00";
            } elseif ($now_i > 30) {
                $now_h = $now_h + 1;
                $now_time = $now_h . ":00:00";
            }

            $purchase_task_info = DB::table("batch_task")
                ->where("task_date", "<=", $now_date)
                ->where("task_time", "<=", $now_time)
                ->where("status", 0)
                ->get();
            $purchase_task_info = objectToArrayZ($purchase_task_info);

            $user_info = $request->user();
            $user_id = $user_info->id;

            $purchase_final_info = [];
            foreach ($purchase_task_info as $k => $v) {
                $user_list = explode(",", $v["user_list"]);
                if (in_array($user_id, $user_list)) {
                    array_push($purchase_final_info, $v);
                }
            }

            $code = "1002";
            $msg = "暂无提醒任务";
            $return_info = compact('code', 'msg');

            if (!empty($purchase_final_info)) {
                $code = "1000";
                $msg = "获取提醒任务成功";
                $data["task_info"] = $purchase_final_info;
                $data["user_info"] = $user_info->user_name;
                $return_info = compact('code', 'msg', 'data');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:关闭采购期
     * editor:zongxing
     * type:GET
     * date : 2018.09.05
     * return Object
     */
    public function closePurchase(Request $request)
    {
        if ($request->isMethod('get')) {
            $get_purchase_info = $request->toArray();
            $purchase_sn = $get_purchase_info["purchase_sn"];

            $purchase_info = DB::table("purchase_date")->where("purchase_sn", $purchase_sn)->first();
            $purchase_info = objectToArrayZ($purchase_info);

            if (empty($purchase_info))
                return response()->json(['code' => '1002', 'msg' => '采购期单号有误']);

            $update_info["status"] = 3;
            $update_purchase_res = DB::table("purchase_date")->where("purchase_sn", $purchase_sn)->update($update_info);

            $code = "1003";
            $msg = "关闭采购期失败";
            $return_info = compact('code', 'msg');

            if ($update_purchase_res !== false) {
                $code = "1000";
                $msg = "关闭采购期成功";
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
     * description:各部门根据商品部分好的数据对相应的销售客户进行分货
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function user_sort_goods(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['purchase_sn']) ||
            !isset($reqParams['real_purchase_sn'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $loginUserInfo = $request->user();
        $depart_id = intval($loginUserInfo->department_id);
        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);//实采单号
        $purDemDetModel = new PurchaseDemandDetailModel();
        //查询分货信息
        $userSortGoods = $purDemDetModel->getUserSortGoods($purchase_sn, $real_purchase_sn, $depart_id);
        if (count($userSortGoods) == 0) {
            $returnMsg = ['code' => '2045', 'msg' => '分货数据不存在'];
            return response()->json($returnMsg);
        }
        //查询一个采购单下当前部门的商品需求总数
        $goodsNeedMsg = $purDemDetModel->getGoodsNeedMsg($purchase_sn, $depart_id);
        $goodsModel = new GoodsModel();
        foreach ($userSortGoods as $key => $value) {
            $spec_sn = trim($value['spec_sn']);
            $goods_num = intval($value['goods_num']);//当前商品对应用户需求数量
            $may_sort_num = intval($value['may_sort_num']);//当前部门可分货数量
            $found_key = $goodsModel->twoArraySearch($goodsNeedMsg, $spec_sn, 'spec_sn');
            $totalNum = $goodsNeedMsg[$found_key]['goods_num'];
            //计算比例数量 = $handle_num * $goods_num/$totalNum
            $ratio = $totalNum == 0 ? 0 : $goods_num / $totalNum;
            $ratio_num = intval(round($may_sort_num * $ratio));
            $userSortGoods[$key]['totalNum'] = $totalNum;
            $userSortGoods[$key]['ratio_num'] = $ratio_num;
            $userSortGoods[$key]['handle_num'] = $ratio_num;
            $user_goods[] = [
                'purchase_sn' => $purchase_sn,
                'demand_sn' => trim($value['demand_sn']),
                'real_pur_sn' => $real_purchase_sn,
                'depart_id' => $depart_id,
                'sale_user_id' => intval($value['sale_user_id']),
                'goods_name' => trim($value['goods_name']),
                'spec_sn' => $spec_sn,
                'may_sort_num' => $may_sort_num,
                'user_need_num' => $goods_num,
                'user_total_num' => $totalNum,
                'ratio' => $ratio,
                'ratio_num' => $ratio_num,
                'handle_num' => $ratio_num,
            ];
        }//end of foreach
        //处理分货比例数据-数据纠正--按比例计算后会有误差
        $corDepGoods = $purDemDetModel->correctSortData($user_goods);
        //根据采购单号和批次单号查询分配表是否已经写入过数据
        $usrSortGoods = $purDemDetModel->queryUserSortGoods($purchase_sn, $real_purchase_sn, $depart_id);
        if ($usrSortGoods->count() == 0) {
            //将分配数据写入商品部按部门分货数据表
            $purDemDetModel->insertUsrSortGoods($corDepGoods);
        }
        //根据纠正过的数据查询对应信息
        $sortGoodsByPurSn = $purDemDetModel->userSortGoods($purchase_sn, $real_purchase_sn);
        $returnMsg = [
            'purchase_sn' => $purchase_sn,
            'real_purchase_sn' => $real_purchase_sn,
            'userSortGoods' => $sortGoodsByPurSn,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:各部门根据商品部分好的数据对相应的需求单进行分货-手动修改分货数量
     * editor:zhangdong
     * date : 2018.10.23
     * return Object
     */
    public function usr_handle_goods(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['purchase_sn']) ||
            !isset($reqParams['real_purchase_sn']) ||
            !isset($reqParams['demand_sn']) ||
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['handle_num'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);//实采单号
        $demand_sn = trim($reqParams['demand_sn']);//需求单号
        $spec_sn = trim($reqParams['spec_sn']);//规格码
        $handle_num = intval($reqParams['handle_num']);//手动调整值
        $purDemDetModel = new PurchaseDemandDetailModel();
        //检查要修改的值是否符合规定（该值不能大于对应采购单下的状态为待开单的实采单的清点数量）
        $canSortNumInfo = $purDemDetModel->usrCanSortNum($purchase_sn, $real_purchase_sn, $spec_sn, $demand_sn);
        if (is_null($canSortNumInfo->spec_sn)) {
            $returnMsg = ['code' => '2044', 'msg' => '实采单分配信息有误'];
            return response()->json($returnMsg);
        }
        $canSortNum = intval($canSortNumInfo->other_handle_num);
        $may_sort_num = intval($canSortNumInfo->may_sort_num);
        if ($canSortNum + $handle_num > $may_sort_num) {
            $returnMsg = ['code' => '2043', 'msg' => '手动调整值不可大于可分货数量总和'];
            return response()->json($returnMsg);
        }
        //修改调整值
        $modHandNum = $purDemDetModel->modUsrHandNum($purchase_sn, $real_purchase_sn, $demand_sn, $spec_sn, $handle_num);
        $returnMsg = ['code' => '2038', 'msg' => '修改成功'];
        if (!$modHandNum) {
            $returnMsg = ['code' => '2039', 'msg' => '修改失败'];
        }
        return $returnMsg;

    }

    /**
     * description:采购单-批次单-分货列表
     * editor:zhangdong
     * date : 2018.10.27
     * return Object
     */
    public function pur_real_list(Request $request)
    {
        $reqParams = $request->toArray();
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $start_page = isset($reqParams['start_page']) ? intval($reqParams['start_page']) : 1;
        $page_size = isset($reqParams['page_size']) ? intval($reqParams['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        //获取并组装列表数据
        $realPurModel = new RealPurchaseModel();
        $params['keywords'] = $keywords;
        $pur_real_list = $realPurModel->pur_real_list($params, $start_str, $page_size);
        $returnMsg = [
            'pur_real_list' => $pur_real_list['listData'],
            'total_num' => $pur_real_list['total_num'],
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:采购期列表-根据采购单号按部门生成分货数据
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function generalDepartSortData(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['purchase_sn']) || !isset($reqParams['real_purchase_sn'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);//实采单号
        //查询实采单状态
        $rpModel = new RealPurchaseModel();
        //查询实采单信息
        $realPurchaseStatus = $rpModel->getRealPurchaseStatus($real_purchase_sn);
        if ($realPurchaseStatus === false) {
            $returnMsg = ['code' => '2041', 'msg' => '采购单数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }
        //检查分货数据是否已经生成
        $dsModel = new DepartSortModel();
        $departSortCount = $dsModel->getDepartSortCount($purchase_sn, $real_purchase_sn, $realPurchaseStatus);
        if($departSortCount > 0){
            $returnMsg = ['code' => '2065', 'msg' => '该实采单已经生成部门分货数据'];
            return response()->json($returnMsg);
        }

        //以批次单为主对各部门下的每个商品按需求单的需求数量用比例进行分货
        $purDemDetModel = new PurchaseDemandDetailModel();
        //查询当前采购期单号下对应的商品数据（一个采购期会挂多个需求单）
        $purGoodsInfo = $purDemDetModel->sortGoodsByPurSn($purchase_sn);
        if ($purGoodsInfo->count() == 0) {
            $returnMsg = ['code' => '2041', 'msg' => '采购单数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }

        //根据实采单号查询采购数据
        $realGoodsInfo = $purDemDetModel->getRealGoodsInfo($purchase_sn, $real_purchase_sn);
        if (count($realGoodsInfo) == 0) {
            $returnMsg = ['code' => '2042', 'msg' => '实采单数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }

        $goodsModel = new GoodsModel();
        $depart_sort_goods = [];
        $sort_sn = $dsModel->generalSortSn();
        $depart_sort = [
            'sort_sn' => $sort_sn,
            'purchase_sn' => $purchase_sn,
            'real_pur_sn' => $real_purchase_sn,
            'status' => $realPurchaseStatus,
        ];
        $demandModel = new DemandModel();
        //将当前采购期单号下面的商品根据批次单数据查询出每个商品对应的可分货数量
        foreach ($purGoodsInfo as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            $dpm_goods_num = intval($value->dpm_goods_num);//单个部门下的sku需求数量
            $total_num = intval($value->total_num);//所有部门下的单个sku需求总数量
            $depart_id = intval($value->department);
            $demand_sn = trim($value->demand_sn);
            //根据需求单号查询销售用户id
            $demandInfo = $demandModel->getDemandOrderMsg($demand_sn);
            $sale_user_id = isset($demandInfo->sale_user_id) ? intval($demandInfo->sale_user_id) : 0;
            $found_key = $goodsModel->twoArraySearch($realGoodsInfo, $spec_sn, 'spec_sn');
            //如果商品sku没有出现在实采单中则跳过
            if ($found_key === false) {
                continue;
            }
            $may_sort_num = intval($realGoodsInfo[$found_key]['allot_num']);//可分货数量
            //单个部门下按比例可分得的商品数量 = $sort_num * $dpm_goods_num/$total_num
            $ratio = $total_num == 0 ? 0 : $dpm_goods_num / $total_num;
            $ratio_num = round($may_sort_num * $ratio);
            $depart_sort_goods[] = [
                'sort_sn' => $sort_sn,
                'depart_id' => $depart_id,
                'goods_name' => trim($value->goods_name),
                'spec_sn' => $spec_sn,
                'depart_need_num' => $dpm_goods_num,
                'total_num' => $total_num,
                'may_sort_num' => $may_sort_num,
                'demand_sn' => $demand_sn,
                'sale_user_id' => $sale_user_id,
                'ratio' => $ratio,
                'ratio_num' => intval($ratio_num),
                'handle_num' => intval($ratio_num),
            ];
        }//end of foreach

        //处理分货比例数据-数据纠正--按比例计算后会有误差
        $corDepGoods = $purDemDetModel->correctSortData($depart_sort_goods);
        //将分配数据写入商品部按部门分货数据表,
        //修改实采单状态为已分货并将实采单中商品的可分货数量置零
        $insertRes = $dsModel->insertData($depart_sort,$corDepGoods,$real_purchase_sn);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($insertRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }



    /**
     * description:销售模块-批次分货列表-查看部门分货数据
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function getDepartSortData(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['purchase_sn']) ||
            !isset($reqParams['real_purchase_sn']) ||
            !isset($reqParams['query_type'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);//实采单号
        $query_type = trim($reqParams['query_type']);//查询类型1，实时 2，最终
        //根据实采单号查询采购数据
        $pddModel = new PurchaseDemandDetailModel();
        $realGoodsInfo = $pddModel->getRealGoodsInfo($purchase_sn, $real_purchase_sn);
        $arrData = objectToArray($realGoodsInfo);
        if (count($arrData) == 0) {
            $returnMsg = ['code' => '2046', 'msg' => '数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }
        //查询分货单数据
        $dsModel = new DepartSortModel();
        $sortData = $dsModel->getSortData($purchase_sn, $real_purchase_sn, $query_type);
        if (is_null($sortData)) {
            $returnMsg = ['code' => '2046', 'msg' => '还没有分货数据，请先生成'];
            return response()->json($returnMsg);
        }
        $sort_sn = trim($sortData->sort_sn);
        //查询部门分货商品数据
        $dsgModel = new DepartSortGoodsModel();
        $sortGoodsData = $dsgModel->getSortGoodsData($sort_sn);
        //对查询数据进行分组操作
        $arrSortGoodsData = objectToArray($sortGoodsData);
        $group_field = ['spec_sn'];
        $group_by_value = [
            'spec_sn',
            'total_handle_num' => function ($data) {
                $totalNum = array_sum(array_column($data, 'handle_num'));
                return $totalNum;
            }
        ];
        $groupByData = ArrayGroupBy::groupBy($arrSortGoodsData, $group_field, $group_by_value);
        $arr_demand_sn = [];
        //组装需求单号
        foreach ($sortGoodsData as $value) {
            $arr_demand_sn[] = trim($value->demand_sn);
        }
        //获取需求单相关信息
        $demandModel = new DemandModel();
        $demandBaseInfo = $demandModel->queryDemandInfo($arr_demand_sn);
        $arrDemandData = objectToArray($demandBaseInfo);
        foreach ($sortGoodsData as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            //查询当前sku在实采单中的可分货数量
            $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
            //查询当前已手动分配商品数量总和
            $searchSortGoods = searchTwoArray($groupByData, $spec_sn, 'spec_sn');
            //最终可分货数量 = 清点数量 - 当前sku手动分货数量总和
            $allot_num = isset($searchRes[0]['allot_num']) ? intval($searchRes[0]['allot_num']) : 0;
            $total_handle_num = isset($searchSortGoods[0]['total_handle_num']) ?
                intval($searchSortGoods[0]['total_handle_num']) : 0;
            $may_sort_num = $allot_num - $total_handle_num;
            $sortGoodsData[$key]->may_sort_num = $may_sort_num;
            //查询需求单相关的一些必要信息
            $demand_sn = trim($value->demand_sn);
            $searchData = searchTwoArray($arrDemandData, $demand_sn, 'demand_sn');
            $sortGoodsData[$key]->demandInfo = [];
            if (count($searchData) > 0) {
                $sortGoodsData[$key]->demandInfo = $searchData[0];
            }
        }

        //获取用户分货数据列表-用于对用户分货
        $sortDemandData = $dsModel->getSortDemandData($sort_sn);
        //获取当前实采单的批次类别
        $rpModel = new RealPurchaseModel();
        $realPurchaseInfo = $rpModel->getRealPurchaseInfo($real_purchase_sn);
        $sortData->batch_cat = intval($realPurchaseInfo->batch_cat);
        $sortData->batch_cat_desc = '正常批次';
        if($sortData->batch_cat == 2){
            $sortData->batch_cat_desc = '预采批次';
        }
        $sortData->demand_sn = trim($realPurchaseInfo->demand_sn);
        //检查是否已经生成用户分货数据
        $usgModel = new UserSortGoodsModel();
        foreach ($sortDemandData as $key => $value) {
            $depart_id = intval($value->depart_id);
            $userSortCount = $usgModel->countUserSortGoods($sort_sn, $depart_id);
            $sortStatus = 0;
            $sortDesc = '用户未分货';
            if ($userSortCount > 0) {
                $sortStatus = 1;
                $sortDesc = '用户已分货';
            }
            $sortDemandData[$key]->sortStatus = $sortStatus;
            $sortDemandData[$key]->sortDesc = $sortDesc;
        }
        $dateInfo = [
            'delivery_time' => $realPurchaseInfo->delivery_time,
            'arrive_time' => $realPurchaseInfo->arrive_time,
        ];
        //查询批次信息
        $returnMsg = [
            'dateInfo' => $dateInfo,
            'sortData' => $sortData,
            'sortDemandData' => $sortDemandData,
            'sortGoodsData' => $sortGoodsData,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:根据采购单号以批次单为主按部门进行分货-手动修改分货数量
     * editor:zhangdong
     * date : 2018.10.23 update 2019.02.19
     * return Object
     */
    public function dep_handle_goods(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['sort_sn']) ||
            !isset($reqParams['depart_id']) ||
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['handle_num'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $sort_sn = trim($reqParams['sort_sn']);//分货单号
        $depart_id = intval($reqParams['depart_id']);//部门id
        $spec_sn = trim($reqParams['spec_sn']);//规格码
        $handle_num = intval($reqParams['handle_num']);//手动调整值

        //检查部门分货数据是否被停用
        $dsModel = new DepartSortModel();
        $departSortInfo = $dsModel->getSortMsg($sort_sn);
        if (is_null($departSortInfo)) {
            $returnMsg = ['code' => '2067', 'msg' => '部门分货数据异常或已停用'];
            return response()->json($returnMsg);
        }

        //检查当前分货单是否已生成用户分货数据
        $usgModel = new UserSortGoodsModel();
        $userSortData = $usgModel->getUserSortInfo($sort_sn, $depart_id);
        if ($userSortData->count() > 0) {
            $returnMsg = ['code' => '2067', 'msg' => '当前数据已生成用户分货数据，请勿修改'];
            return response()->json($returnMsg);
        }
        
        $dsgModel = new DepartSortGoodsModel();
        //检查当前要修改的商品存在的部门信息
        $countNum = $dsgModel->checkSortGoods($sort_sn, $spec_sn);
        if ($countNum == 1) {
            $returnMsg = ['code' => '2044', 'msg' => '当前批次只存在一个部门，无需分货'];
            return response()->json($returnMsg);
        }
        //查询分货单信息
        $sortData = $dsModel->getSortMsg($sort_sn);
        if (is_null($sortData)) {
            $returnMsg = ['code' => '2066', 'msg' => '部门分配数据异常'];
            return response()->json($returnMsg);
        }
        $real_purchase_sn = trim($sortData->real_pur_sn);
        //查询可分货总量
        $rpdModel = new RealPurchaseDetailModel();
        $canSortInfo = $rpdModel->getCanSortNum($real_purchase_sn, $spec_sn);
        $canSortNum =  isset($canSortInfo->allot_num) ? intval($canSortInfo->allot_num) : 0;
        //查询除了当前部门外其他部门已分得的数量
        $otherDepartNum = $dsgModel->getOtherDepartNum($sort_sn, $spec_sn, $depart_id);
        //总分货数量
        $sortTotalNum = $otherDepartNum + $handle_num;
        if ($sortTotalNum > $canSortNum) {
            $returnMsg = ['code' => '2043', 'msg' => '手动调整值不可大于可分货数量总和'];
            return response()->json($returnMsg);
        }
        //修改手动调整值
        $updateRes = $dsgModel->modifyHandleNum($sort_sn, $depart_id, $spec_sn, $handle_num);
        $returnMsg = ['code' => '2039', 'msg' => '修改失败'];
        if ($updateRes) {
            $returnMsg = ['code' => '2038', 'msg' => '修改成功'];
        }
        return $returnMsg;
    }



    /**
     * description:部门分货数据页-生成按用户分货的数据
     * editor:zhangdong
     * date : 2019.02.22
     */
    public function generalUserSortData(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['purchase_sn']) ||
            !isset($reqParams['depart_id']) ||
            !isset($reqParams['sort_sn']) ||
            !isset($reqParams['real_purchase_sn'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        $depart_id = trim($reqParams['depart_id']);//部门id
        $sort_sn = trim($reqParams['sort_sn']);//分单号
        //检查部门分货数据是否被停用
        $dsModel = new DepartSortModel();
        $departSortInfo = $dsModel->getSortMsg($sort_sn);
        if (is_null($departSortInfo)) {
            $returnMsg = ['code' => '2067', 'msg' => '部门分货数据异常或已停用'];
            return response()->json($returnMsg);
        }
        //检查分货数据是否已经生成
        $usgModel = new UserSortGoodsModel();
        $userSortCount = $usgModel->countUserSortGoods($sort_sn, $depart_id);
        if($userSortCount > 0){
            $returnMsg = ['code' => '2065', 'msg' => '请勿重复生成分货数据'];
            return response()->json($returnMsg);
        }
        //通过depart_sort中的采购期单号和depart_sort_goods中的部门id
        //查到采购期单号下该部门的所有销售用户
        //根据实采单号判断其是正常批次还是预采批次
        $rpModel = new RealPurchaseModel();
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);
        $realPurchaseInfo = $rpModel->getRealPurchaseInfo($real_purchase_sn);
        $batchCatValue = intval($realPurchaseInfo->batch_cat);
        //查询采购期单和当前部门下的用户数据
        $pdModel = new PurchaseDemandModel();
        $departUserInfo = [];
        //批次类别,1:正常批次;2:预采批次
        if($batchCatValue == 1){
            $departUserInfo = $pdModel->getDepartUserInfo($purchase_sn, $depart_id);
        }
        $saleUid = 0;
        if($batchCatValue == 2){
            $dgModel = new DemandGoodsModel();
            $demand_sn = trim($realPurchaseInfo->demand_sn);
            $departUserInfo = $dgModel->getDemandGoodsInfo($demand_sn);
            $demandModel = new DemandModel();
            $demandInfo = $demandModel->getDemandOrderMsg($demand_sn);
            $saleUid = intval($demandInfo->sale_user_id);
        }
        if ($departUserInfo->count() == 0) {
            $returnMsg = ['code' => '2041', 'msg' => '数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }

        //以部门分货数据为主对各部门下的每个商品按需求单的需求数量对销售用户按比例进行分货
        $dsgModel = new DepartSortGoodsModel();
        //查询当前部门分货单下对应的商品数据
        $departSortInfo = $dsgModel->getDepartSortGoods($sort_sn, $depart_id);
        if ($departSortInfo->count() == 0) {
            $returnMsg = ['code' => '2041', 'msg' => '数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }
        $arrDepartSortInfo = objectToArray($departSortInfo);
        //统计一个采购期下一个部门对应的所有用户的商品需求总量
        $userTotalNum = $pdModel->getUserTotalNum($purchase_sn, $depart_id);
        $arrUserTotalNum = objectToArray($userTotalNum);
        //将当前采购期单号下面的商品根据批次单数据查询出每个商品对应的可分货数量
        $user_sort_goods = [];
        foreach ($departUserInfo as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            $user_num = intval($value->goods_num);//单个部门下单个用户的sku需求数量
            //获取一个部门下所有用户的需求总量
            $searchTotalNum = searchTwoArray($arrUserTotalNum, $spec_sn, 'spec_sn');
            $total_num = 0;//单个部门下的所有用户的sku需求总数量
            if (count($searchTotalNum) > 0) {
                $total_num = intval($searchTotalNum[0]['totalNum']);
            }
            $sale_user_id = isset($value->sale_user_id) ? intval($value->sale_user_id) : $saleUid;
            $searchDepartSort = searchTwoArray($arrDepartSortInfo, $spec_sn, 'spec_sn');
            if (count($searchDepartSort) == 0) {
                continue;
            }
            $maySortNum = $searchDepartSort[0]['handle_num'];
            $demand_sn = $searchDepartSort[0]['demand_sn'];
            //计算比例数量 = $maySortNum * $user_num/$total_num
            $ratio = $total_num == 0 ? 0 : $user_num / $total_num;
            $ratio_num = intval(round($maySortNum * $ratio));
            $user_sort_goods[] = [
                'sort_sn' => $sort_sn,
                'depart_id' => $depart_id,
                'sale_user_id' => $sale_user_id,
                'goods_name' => trim($value->goods_name),
                'spec_sn' => $spec_sn,
                'need_num' => $user_num,
                'demand_sn' => $demand_sn,
                'total_num' => $total_num,
                'ratio_num' => intval($ratio_num),
                'handle_num' => intval($ratio_num),
                'may_sort_num' => intval($maySortNum),
            ];
        }//end of foreach
        $purDemDetModel = new PurchaseDemandDetailModel();
        //处理分货比例数据-数据纠正--按比例计算后会有误差
        $corDepGoods = $purDemDetModel->correctSortData($user_sort_goods);
        //保存分配数据
        $insertRes = $usgModel->insertData($corDepGoods);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($insertRes) {
            //检查当前分货单是否已经全部完成用户分货
            $checkRes = $dsgModel->checkIsFinishSort($sort_sn);
            //如果所有用户分货完成则将批次单分货状态改为完成分货
            if ($checkRes) {
                $is_sort_num = $rpModel->is_sort_int['FINISH_SORT'];
                $rpModel->updateIsSort($real_purchase_sn,$is_sort_num);
            }
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }


    /**
     * description:查询用户分货数据
     * editor:zhangdong
     * date : 2019.02.22
     */
    public function getUserSortData(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['depart_id']) || !isset($reqParams['sort_sn'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $depart_id = trim($reqParams['depart_id']);//部门id
        $sort_sn = trim($reqParams['sort_sn']);//分货单号
        //获取用户分货数据
        $usgModel = new  UserSortGoodsModel();
        $queryRes = $usgModel->getSpecNum($sort_sn, $depart_id);
        $everySpecNum = objectToArray($queryRes);
        $userSortData = $usgModel->getUserSortGoods($depart_id, $sort_sn);
        $arr_demand_sn = [];
        //组装需求单号
        foreach ($userSortData as $value) {
            $arr_demand_sn[] = trim($value->demand_sn);
        }
        //获取需求单相关信息
        $demandModel = new DemandModel();
        $demandBaseInfo = $demandModel->queryDemandInfo(array_unique($arr_demand_sn));
        $arrDemandData = objectToArray($demandBaseInfo);
        foreach ($userSortData as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            $searchRes = searchTwoArray($everySpecNum, $spec_sn, 'spec_sn');
            $is_set = isset($searchRes[0]['userNum']);
            //剩余可分配数 = 部门总分配数 - 当前用户分配数
            $sort_num = intval($value->sort_num);
            $userNum = $is_set ? $searchRes[0]['userNum'] : 0;
            $canSortNum = $sort_num - $userNum;
            $userSortData[$key]->can_sort_num = $canSortNum;

            //查询需求单相关的一些必要信息
            $demand_sn = trim($value->demand_sn);
            $searchData = searchTwoArray($arrDemandData, $demand_sn, 'demand_sn');
            $userSortData[$key]->demandInfo = [];
            if (count($searchData) > 0) {
                $userSortData[$key]->demandInfo = $searchData[0];
            }
        }
        $returnMsg = [
            'userSortData' => $userSortData,
            'sort_sn' => $sort_sn,
        ];
        return response()->json($returnMsg);

    }


    /**
     * description:批次单列表-商品部对用户分货-手动修改分货数量
     * author:zhangdong
     * date : 2019.02.25
     */
    public function user_handle_goods(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['sort_sn']) ||
            !isset($reqParams['depart_id']) ||
            !isset($reqParams['sale_user_id']) ||
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['handle_num'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $sort_sn = trim($reqParams['sort_sn']);//分货单号
        $depart_id = intval($reqParams['depart_id']);//部门id
        $sale_user_id = intval($reqParams['sale_user_id']);//用户id
        $spec_sn = trim($reqParams['spec_sn']);//规格码
        $handle_num = intval($reqParams['handle_num']);//手动调整值

        //检查部门分货数据是否被停用
        $dsModel = new DepartSortModel();
        $departSortInfo = $dsModel->getSortMsg($sort_sn);
        if (is_null($departSortInfo)) {
            $returnMsg = ['code' => '2067', 'msg' => '部门分货数据异常或已停用'];
            return response()->json($returnMsg);
        }

        //检查要修改的数据是否存在
        $usgModel = new userSortGoodsModel();
        $userSortInfo = $usgModel->checkSortInfo($sort_sn, $depart_id, $sale_user_id, $spec_sn);
        if (count($userSortInfo) === 0) {
            $returnMsg = ['code' => '2066', 'msg' => '用户分货信息不存在'];
            return response()->json($returnMsg);
        }

        //检查是否有必要分货
        $saleUserNum = $usgModel->checkSaleUserNum($sort_sn, $depart_id, $spec_sn);
        if ($saleUserNum === 1) {
            $returnMsg = ['code' => '2066', 'msg' => '当前批次只存在一个销售用户，无需分货'];
            return response()->json($returnMsg);
        }

        //检查手动修改值是否正常
        //检查该商品部门可分货总量
        $dsgModel = new DepartSortGoodsModel();
        $canSortNum = $dsgModel->getDepartGoodsCanSortNum($sort_sn, $depart_id, $spec_sn);
        //查询除了当前用户外其他部门已分得的数量
        $otherDepartNum = $usgModel->getOtherUserNum($sort_sn, $depart_id, $sale_user_id, $spec_sn);
        //总分货数量
        $sortTotalNum = $otherDepartNum + $handle_num;
        if ($sortTotalNum > $canSortNum) {
            $returnMsg = ['code' => '2043', 'msg' => '手动调整值不可大于可分货数量总和'];
            return response()->json($returnMsg);
        }

        //修改手动调整值
        $modifyId = intval($userSortInfo->id);
        $updateRes = $usgModel->modifyHandleNum($modifyId, $handle_num);
        $returnMsg = ['code' => '2039', 'msg' => '修改失败'];
        if ($updateRes) {
            $returnMsg = ['code' => '2038', 'msg' => '修改成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:部门分货-停用部门分货数据
     * editor:zhangdong
     * date : 2019.03.06
     */
    public function stopDepartSortData(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['sort_sn'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $sort_sn = trim($reqParams['sort_sn']);
        //检查分货单是否存在
        $dpModel = new DepartSortModel();
        $sortInfo = $dpModel->getSortMsg($sort_sn);
        if (is_null($sortInfo)) {
            $returnMsg = ['code' => '2067', 'msg' => '当前分货单不存在'];
            return response()->json($returnMsg);
        }
        //修改分货单使用状态为已停用
        $dpModel->stopUseDepartSort($sort_sn);
        //修改实采单为未分货
        $rpModel = new RealPurchaseModel();
        $realPurchaseSn = trim($sortInfo->real_pur_sn);
        $updateRes = $rpModel->rollbackIsSort($realPurchaseSn);
        $returnMsg = ['code' => '2039', 'msg' => '操作失败'];
        if ($updateRes) {
            $returnMsg = ['code' => '2038', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:预采批次-生成部门分货数据
     * author:zhangdong
     * date : 2019.03.12
     */
    public function makePerDepartSortData(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['purchase_sn']) || !isset($reqParams['real_purchase_sn'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);//实采单号
        //查询实采单状态
        $rpModel = new RealPurchaseModel();
        //查询实采单信息
        $realPurchaseInfo = $rpModel->getRealPurchaseInfo($real_purchase_sn);
        $demand_sn = isset($realPurchaseInfo->demand_sn) ? trim($realPurchaseInfo->demand_sn) : '';
        if (empty($demand_sn)) {
            $returnMsg = ['code' => '2067', 'msg' => '非预采批次请勿执行此操作'];
            return response()->json($returnMsg);
        }
        $realPurchaseStatus = intval($realPurchaseInfo->status);
        //检查分货数据是否已经生成
        $dsModel = new DepartSortModel();
        $departSortCount = $dsModel->getDepartSortCount($purchase_sn, $real_purchase_sn, $realPurchaseStatus);
        if($departSortCount > 0){
            $returnMsg = ['code' => '2065', 'msg' => '该实采单已经生成部门分货数据'];
            return response()->json($returnMsg);
        }
        //以实采单为主对各部门下的每个商品按需求单的需求数量进行分货
        //通过预采单对应的需求单查询对应需求单的商品数据
        $dgModel = new DemandGoodsModel();
        $demandGoodsInfo = $dgModel->getDemandGoodsInfo($demand_sn);
        if ($demandGoodsInfo->count() == 0) {
            $returnMsg = ['code' => '2041', 'msg' => '需求单数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }
        //根据需求单号查询对应需求单的部门id
        $demandModel = new DemandModel();
        $demandInfo = $demandModel->getDemandOrderMsg($demand_sn);
        $depart_id = isset($demandInfo->department) ? intval($demandInfo->department) : 0;
        $purDemDetModel = new PurchaseDemandDetailModel();
        //根据实采单号查询采购数据
        $realGoodsInfo = $purDemDetModel->getRealGoodsInfo($purchase_sn, $real_purchase_sn);
        if (count($realGoodsInfo) == 0) {
            $returnMsg = ['code' => '2042', 'msg' => '实采单数据异常，请联系管理员'];
            return response()->json($returnMsg);
        }
        $depart_sort_goods = [];
        $sort_sn = $dsModel->generalSortSn();
        $depart_sort = [
            'sort_sn' => $sort_sn,
            'purchase_sn' => $purchase_sn,
            'real_pur_sn' => $real_purchase_sn,
            'status' => $realPurchaseStatus,
        ];
        //将当前采购期单号下面的商品根据批次单数据查询出每个商品对应的可分货数量
        $arrData = objectToArray($realGoodsInfo);
        foreach ($demandGoodsInfo as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            $total_num = intval($value->goods_num);//所有部门下的单个sku需求总数量
            //根据规格码查询实采单的商品信息
            $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
            //如果商品sku没有出现在实采单中则跳过
            if (count($searchRes) == 0) {
                continue;
            }
            $may_sort_num = intval($searchRes[0]['allot_num']);//可分货数量
            //由于预采批次单无需分货，所以不需要按比例分货
            $depart_sort_goods[] = [
                'sort_sn' => $sort_sn,
                'depart_id' => $depart_id,
                'goods_name' => trim($value->goods_name),
                'spec_sn' => $spec_sn,
                //单个部门下的sku需求数量
                'depart_need_num' => $total_num,
                //所有部门下的单个sku需求总数量
                'total_num' => $total_num,
                'demand_sn' => trim($value->demand_sn),
                'ratio' => 0,
                'ratio_num' => intval($may_sort_num),
                'handle_num' => intval($may_sort_num),
            ];
        }//end of foreach
        //将分配数据写入商品部按部门分货数据表,
        //修改实采单状态为已分货并将实采单中商品的可分货数量置零
        $insertRes = $dsModel->insertData($depart_sort,$depart_sort_goods,$real_purchase_sn);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($insertRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }



    /**
     * description 批次单分货列表（real_purchase_audit表中已提交数据）
     * author zhangdong
     * date 2019.10.08
     */
    public function batchOrderList(Request $request)
    {
        $reqParams = $request->toArray();
        //获取并组装列表数据
        $rpaModel = new RealPurchaseAuditModel();
        $batchList = $rpaModel->getBatchSortList($reqParams);
        return response()->json($batchList);
    }




    /**
     * description 查看合单号对应的总分货数据
     * author:zhangdong
     * date : 2019.05.31
     */
    public function sumSortDataList(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->sumSortDataListParams($reqParams);
        $sumDemandSn = trim($reqParams['sum_demand_sn']);
        //获取合单号的总分货数据
        $sodModel = new SortDataModel();
        $sortData = $sodModel->getSortData($sumDemandSn);
        //组装销售用户名和还需分配数
        $sortDataList = $sodModel->makeSortDataList($sortData);
        $returnMsg = ['sum_demand_sn' => $sumDemandSn, 'sortDataList' => $sortDataList];
        return response()->json($returnMsg);
    }

    /**
     * description 查看批次单分货数据
     * author:zhangdong
     * date : 2019.06.04
     */
    public function getBatchOrdSortData(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->getBatchOrdSortDataParams($reqParams);
        $sumDemandSn = trim($reqParams['sum_demand_sn']);
        $realPurchaseSn = trim($reqParams['real_purchase_sn']);
        //获取合单号的总分货数据
        $sodModel = new SortDataModel();
        $countSortData = $sodModel->countSortData($sumDemandSn);
        if($countSortData == 0){
            $returnMsg = ['code' => '2067', 'msg' => '分货数据异常'];
            return response()->json($returnMsg);
        }
        //获取批次单基本信息
        $rpaModel = new RealPurchaseAuditModel();
        $batchInfo = $rpaModel->queryBatchInfo($realPurchaseSn);
        if(count($batchInfo) == 0){
            $returnMsg = ['code' => '2067', 'msg' => '批次单不存在'];
            return response()->json($returnMsg);
        }
        //获取批次单商品信息
        $rpdaModel = new RealPurchaseDeatilAuditModel();
        $batchGoodsInfo = $rpdaModel->queryGoodsSimple($realPurchaseSn);
        $arrBatchGoods = objectToArray($batchGoodsInfo);
        //将该批次中的规格码组装成数组形式
        $arrSpecSn = getFieldArrayVaule($arrBatchGoods, 'spec_sn');
        //获取该批次相关商品的分货数据
        $sortData = $sodModel->getSortData($sumDemandSn, $arrSpecSn);
        //组装批次相关商品可分货数量和还需分配数
        $sortData = $sodModel->packageSortData($sortData, $batchGoodsInfo);
        $returnMsg = [
            'sum_demand_sn' => $sumDemandSn,
            'batchSortData' => $sortData,
            'batchInfo' => $batchInfo,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description 生成批次单分货数据-将分货对象转移到了批次审核表
     * author zhangdong
     * date 2019.10.08
     */
    public function makeBatchOrdSortData(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->makeBatchOrdSortDataParams($reqParams);
        $sumDemandSn = trim($reqParams['sum_demand_sn']);
        $realPurchaseSn = trim($reqParams['real_purchase_sn']);
        //检查某个合单是否生成了合单初始数据
        $sodModel = new SortDataModel();
        $countSortData = $sodModel->countSortData($sumDemandSn);
        if($countSortData == 0){
            $returnMsg = ['code' => '2067', 'msg' => '分货数据异常'];
            return response()->json($returnMsg);
        }
        //检查批次单是否已生成分货数据
        $sbModel = new SortBatchModel();
        $countSortBatch = $sbModel->countNumByBatch($sumDemandSn, $realPurchaseSn);
        if($countSortBatch > 0){
            $returnMsg = ['code' => '2067', 'msg' => '该批次已生成分货数据'];
            return response()->json($returnMsg);
        }
        //检查批次单是否是当前合单下的数据
        $rpaModel = new RealPurchaseAuditModel();
        $countSumRealSn = $rpaModel->countSumRealSn($sumDemandSn, $realPurchaseSn);
        if ($countSumRealSn == 0) {
            return response()->json(['code' => '2067', 'msg' => '该合单下没有这个批次单']);
        }

        //获取批次单商品信息
        $rpdaModel = new RealPurchaseDeatilAuditModel();
        $batchGoodsInfo = $rpdaModel->queryGoodsSimple($realPurchaseSn);
        if(count($batchGoodsInfo) == 0){
            $returnMsg = ['code' => '2067', 'msg' => '批次单不存在'];
            return response()->json($returnMsg);
        }
        $arrBatchGoods = objectToArray($batchGoodsInfo);
        //将该批次中的规格码组装成数组形式
        $arrSpecSn = getFieldArrayVaule($arrBatchGoods, 'spec_sn');
        //获取该批次相关商品的分货数据
        $sortData = $sodModel->getSortData($sumDemandSn, $arrSpecSn);
        //计算还需分配数，默认分配数，已分配数等信息
        $batchSortData = $sodModel->calculateSortData($sortData, $arrBatchGoods);
        //更新数据-修改分货数据中的默认值和已分配值，更新批次表中对应商品的可分货数量，
        //记录该批次分给需求单各商品的数据
        $operateRes = $sodModel->operateSortData(
            $sumDemandSn,
            $realPurchaseSn,
            $batchSortData['sortData'],
            $batchSortData['batchGoods']
        );

        $returnMsg = ['code' => '2023', 'msg' => '操作失败，该批次下的商品可能已经生成了分货数据'];
        if ($operateRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description 手动调整分配数据
     * author:zhangdong
     * date : 2019.06.03
     */
    public function handleSortNum(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->handleSortNumParams($reqParams);
        $sumDemandSn = trim($reqParams['sum_demand_sn']);
        $realPurchaseSn = trim($reqParams['real_purchase_sn']);
        $demandSn = trim($reqParams['demand_sn']);
        $specSn = trim($reqParams['spec_sn']);
        //手动调整数
        $handleNum = isset($reqParams['handle_num']) ? intval($reqParams['handle_num']) : 0;
        //查询需求单的商品需求数量
        $dgModel = new DemandGoodsModel();
        $demandGoods = $dgModel->getDemandGoods($demandSn, $specSn);
        if (count($demandGoods) == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '需求单商品数据异常'];
            return response()->json($returnMsg);
        }
        //需求数
        $need_num = intval($demandGoods->goods_num);
        //获取当前商品已分配数
        $sodModel = new SortDataModel();
        $sodGoodsInfo = $sodModel->getGoodsSortData($sumDemandSn, $demandSn, $specSn);
        if (count($sodGoodsInfo) == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '分货数据异常'];
            return response()->json($returnMsg);
        }
        //已分配数
        $yet_num = intval($sodGoodsInfo->yet_num);
        //手动调整数 > 当前需求单的需求数 - 已分配数
        if ($handleNum > $need_num - $yet_num) {
            $returnMsg = ['code' => '2067', 'msg' => '调整数不可大于还需分配数'];
            return response()->json($returnMsg);
        }
        //获取批次单某商品的可分货数
        $rpdaModel = new  RealPurchaseDeatilAuditModel();
        $batchGoods = $rpdaModel->getBarchGoods($realPurchaseSn, $specSn);
        if (count($batchGoods) == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '批次单数据异常'];
            return response()->json($returnMsg);
        }
        //可分货数量
        $sort_num = intval($batchGoods->sort_num);
        //手动调整数不能大于可分货数量
        if ($handleNum > $sort_num) {
            $returnMsg = ['code' => '2067', 'msg' => '调整数不能大于可分货数量'];
            return response()->json($returnMsg);
        }
        //当handle_num为负数时即为回调值，可分货数和回调值的差不能大于购买数量
        $day_buy_num = intval($batchGoods->day_buy_num);
        if ($sort_num - $handleNum > $day_buy_num) {
            $returnMsg = ['code' => '2067', 'msg' => '调整数量超出范围'];
            return response()->json($returnMsg);
        }
        //组装sort_batch表所需数据
        $sortBatch = [
            'spec_sn' => $specSn,
            'handleNum' => $handleNum,
            'spec_price' => $batchGoods->spec_price,
            'lvip_price' => $batchGoods->lvip_price,
            'pay_price' => $batchGoods->pay_price,
            'channel_discount' => $batchGoods->channel_discount,
            'real_discount' => $batchGoods->real_discount,
        ];
         //数据处理
        $updateRes = $sodModel->modifySortData($sumDemandSn, $demandSn, $realPurchaseSn, $sortBatch);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($updateRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

//----------停用接口----------

    /**
     * description 生成批次单分货数据
     * author:zhangdong
     * date : 2019.05.31 update 2019.06.04 zhangdong
     */
    public function makeBatchOrdSortData_stop(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->makeBatchOrdSortDataParams($reqParams);
        $sumDemandSn = trim($reqParams['sum_demand_sn']);
        $realPurchaseSn = trim($reqParams['real_purchase_sn']);
        //检查某个合单是否生成了合单初始数据
        $sodModel = new SortDataModel();
        $countSortData = $sodModel->countSortData($sumDemandSn);
        if($countSortData == 0){
            $returnMsg = ['code' => '2067', 'msg' => '分货数据异常'];
            return response()->json($returnMsg);
        }
        //检查批次单是否已生成分货数据
        $sbModel = new SortBatchModel();
        $countSortBatch = $sbModel->countNumByBatch($sumDemandSn, $realPurchaseSn);
        if($countSortBatch > 0){
            $returnMsg = ['code' => '2067', 'msg' => '该批次已生成分货数据'];
            return response()->json($returnMsg);
        }
        //检查批次单是否是当前合单下的数据
        $rpModel = new RealPurchaseModel();
        $countSumRealSn = $rpModel->countSumRealSn($sumDemandSn, $realPurchaseSn);
        if ($countSumRealSn == 0) {
            return response()->json(['code' => '2067', 'msg' => '该合单下没有这个批次单']);
        }
        //获取批次单商品信息
        $rpdModel = new RealPurchaseDetailModel();
        $batchGoodsInfo = $rpdModel->queryGoodsSimple($realPurchaseSn);
        if(count($batchGoodsInfo) == 0){
            $returnMsg = ['code' => '2067', 'msg' => '批次单不存在'];
            return response()->json($returnMsg);
        }
        $arrBatchGoods = objectToArray($batchGoodsInfo);
        //将该批次中的规格码组装成数组形式
        $arrSpecSn = getFieldArrayVaule($arrBatchGoods, 'spec_sn');
        //获取该批次相关商品的分货数据
        $sortData = $sodModel->getSortData($sumDemandSn, $arrSpecSn);
        //计算还需分配数，默认分配数，已分配数等信息
        $batchSortData = $sodModel->calculateSortData($sortData, $arrBatchGoods);
        //更新数据-修改分货数据中的默认值和已分配值，更新批次表中对应商品的可分货数量，
        //记录该批次分给需求单各商品的数据
        $operateRes = $sodModel->operateSortData(
            $sumDemandSn,
            $realPurchaseSn,
            $batchSortData['sortData'],
            $batchSortData['batchGoods']
        );

        $returnMsg = ['code' => '2023', 'msg' => '操作失败，该批次下的商品可能已经生成了分货数据'];
        if ($operateRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description 批次单分货列表
     * author:zhangdong
     * date : 2019.05.30
     * notice 由于批次分货数据从real_purchase表换到了real_purchase_audit表，
     * 故方法停用 2019.10.08 zhangdong
     */
    public function batchOrderList_stop(Request $request)
    {
        $reqParams = $request->toArray();
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $start_page = isset($reqParams['start_page']) ? intval($reqParams['start_page']) : 1;
        $page_size = isset($reqParams['page_size']) ? intval($reqParams['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        //获取并组装列表数据
        $realPurModel = new RealPurchaseModel();
        $params['keywords'] = $keywords;
        $batchList = $realPurModel->getBatchOrderList($params, $start_str, $page_size);
        $returnMsg = [
            'batchList' => $batchList['listData'],
            'total_num' => $batchList['total_num'],
        ];
        return response()->json($returnMsg);
    }

    /**
     * description 根据总单号生成分货数据-停用
     * author:zhangdong
     * date : 2019.05.30
     */
    public function generalSortData(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->generalSortDataParams($reqParams);
        $sumDemandSn = trim($reqParams['sum_demand_sn']);
        //检查合单信息是否存在
        $sdModel = new SumDemandModel();
        $sumDemandInfo = $sdModel->getSumDemandInfo($sumDemandSn);
        if ($sumDemandInfo->count() == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '合单信息不存在'];
            return response()->json($returnMsg);
        }
        $sodModel = new SortDataModel();
        //检查分货数据是否已经存在
        $countSortData = $sodModel->countSortData($sumDemandSn);
        if ($countSortData > 0) {
            $returnMsg = ['code' => '2067', 'msg' => '请勿重复生成分货数据'];
            return response()->json($returnMsg);
        }
        //组装分货数据
        $sortData = $sodModel->makeSortData($sumDemandSn, $sumDemandInfo);
        //写入分货数据表
        $saveRes = $sodModel->saveData($sortData);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($saveRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }


}//end of class