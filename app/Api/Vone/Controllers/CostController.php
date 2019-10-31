<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\OperateLogModel;
use App\Model\Vone\PurchaseCostModel;
use Illuminate\Http\Request;

/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class CostController extends BaseController
{
    /**
     * description:添加采购成本系数
     * editor:zongxing
     * type:POST
     * date : 2018.06.26
     * params: 1.采购成本系数:cost_coef;
     * return Object
     */
    public function createCost(Request $request)
    {
        $purchase_cost_info = $request->toArray();

        if ($request->isMethod('post')) {
            if (empty($purchase_cost_info["cost_coef"])) {
                return response()->json(['code' => '1002', 'msg' => '采购成本系数不能为空']);
            }

            PurchaseCostModel::create($purchase_cost_info);

            $code = "1000";
            $msg = "采购成本系数添加成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:获取采购成本系数列表
     * editor:zongxing
     * type:GET
     * date : 2018.06.26
     * return Object
     */
    public function getCostList(Request $request)
    {
        if ($request->isMethod('get')) {
            $purchase_cost_info = PurchaseCostModel::orderBy('create_time', 'desc')->get();

            $code = "1000";
            $msg = "获取采购成本系数列表成功";
            $data = $purchase_cost_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:更改采购成本系数状态
     * editor:zongxing
     * type:POST
     * date : 2018.06.26
     * params: 1.采购成本系数id:id;
     * return Object
     */
    public function changeStatus(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_cost_array = $request->toArray();
            $cost_id = $purchase_cost_array["id"];

            //获取指定采购方式信息
            $purchase_cost_info = PurchaseCostModel::where("id", $cost_id)->get();
            if ($purchase_cost_info->isEmpty()) {
                return response()->json(['code' => '1002', 'msg' => '采购成本系数不存在']);
            }

            //获取当前使用的采购成本系数
            $before_cost_info = PurchaseCostModel::where("cost_status", "1")->first();

            if ($before_cost_info) {
                $before_cost_info = $before_cost_info->toArray();
                $before_cost_id = $before_cost_info["id"];
            }

            $update_res = PurchaseCostModel::where("id", $cost_id)->update(["cost_status" => "1"]);

            $code = "1003";
            $msg = "采购成本系数编辑失败";
            $return_info = compact('code', 'msg');

            if ($update_res) {
                //改变之前使用的采购成本系数状态为“停用”
                if (isset($before_cost_id)) {
                    PurchaseCostModel::where("id", $before_cost_id)->update(["cost_status" => "2"]);
                }

                $code = "1000";
                $msg = "采购成本系数编辑成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_purchase_method',
                    'bus_desc' => '采购方式-确认提交编辑采购方式-采购成本系数id：' . $cost_id . '状态改为：1',
                    'bus_value' => $cost_id,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '采购方式-确认提交编辑采购方式',
                    'module_id' => 2,
                    'have_detail' => 0,
                ];
                $operateLogModel->insertMoreLog($logData);

                if (isset($before_cost_id)) {
                    $logData = [
                        'table_name' => 'jms_purchase_method',
                        'bus_desc' => '采购成本系数-改变采购成本系数状态-采购成本系数id：' . $before_cost_id . '状态改为：2',
                        'bus_value' => $before_cost_id,
                        'admin_name' => trim($loginUserInfo->user_name),
                        'admin_id' => trim($loginUserInfo->id),
                        'ope_module_name' => '采购成本系数-改变采购成本系数状态',
                        'module_id' => 2,
                        'have_detail' => 0,
                    ];
                    $operateLogModel->insertMoreLog($logData);
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



}