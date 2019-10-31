<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\PurchaseUserModel;
use Illuminate\Http\Request;

/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class PurchaseController extends BaseController
{
    /**
     * description:新增采购id
     * editor:zongxing
     * type:POST
     * date : 2018.07.04
     * params: 1.采购人员姓名:real_name;2.采购人员护照号:passport_sn;3.采购方式id:method_id;4.采购渠道id:channels_id;
     *          5.账号:account_number;
     * return Object
     */
    public function createPurchaseUser(Request $request)
    {
        if ($request->isMethod("post")) {
            $purchase_user_info = $request->toArray();

            if (empty($purchase_user_info["real_name"])) {
                return response()->json(['code' => '1002', 'msg' => '采购人员姓名不能为空']);
            } else if (empty($purchase_user_info["passport_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购人员护照号不能为空']);
            } else if (empty($purchase_user_info["method_id"])) {
                return response()->json(['code' => '1004', 'msg' => '采购方式id不能为空']);
            } else if (empty($purchase_user_info["channels_id"])) {
                return response()->json(['code' => '1005', 'msg' => '采购渠道id不能为空']);
            } else if (empty($purchase_user_info["account_number"])) {
                return response()->json(['code' => '1006', 'msg' => '账号不能为空']);
            }

            //检查采购人员的信息
            $purchaseUserModel = new PurchaseUserModel();
            $check_user_info = $purchaseUserModel->check_user_info($purchase_user_info);

            if (!$check_user_info) {
                return response()->json(['code' => '1007', 'msg' => '该采购人员已经存在']);
            }

            $insertRes = PurchaseUserModel::create($purchase_user_info);

            $code = "1008";
            $msg = "创建采购id失败";
            $return_info = compact('code', 'msg');

            if ($insertRes) {
                $code = "1000";
                $msg = "创建采购id成功";
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
     * description:获取采购id列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getUserList(Request $request)
    {
        if ($request->isMethod("get")) {
            //获取采购id列表信息
            $purchaseUserModel = new PurchaseUserModel();
            $user_list_info = $purchaseUserModel->getUserList();
            $code = "1000";
            $msg = "获取采购id列表成功";
            $data = $user_list_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:打开编辑采购id页面
     * editor:zongxing
     * type:POST
     * date : 2018.07.10
     * return Object
     */
    public function eidtUser(Request $request)
    {
        if ($request->isMethod("post")) {
            $user_info = $request->toArray();

            //获取采购id列表信息
            $purchaseUserModel = new PurchaseUserModel();
            $user_info = $purchaseUserModel->getUserInfo($user_info);

            $code = "1000";
            $msg = "获取采购id信息成功";
            $data = $user_info;
            $return_info = compact('code', 'msg', 'data');
            if (empty($user_info)) {
                $code = "1002";
                $msg = "该采购id不存在";
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
     * description:提交编辑采购id页面
     * editor:zongxing
     * type:POST
     * date : 2018.07.10
     * return Object
     */
    public function doEidtUser(Request $request)
    {
        if ($request->isMethod("post")) {
            $purchase_user_info = $request->toArray();

            if (empty($purchase_user_info["real_name"])) {
                return response()->json(['code' => '1002', 'msg' => '采购人员姓名不能为空']);
            } else if (empty($purchase_user_info["passport_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购人员护照号不能为空']);
            } else if (empty($purchase_user_info["method_id"])) {
                return response()->json(['code' => '1004', 'msg' => '采购方式id不能为空']);
            } else if (empty($purchase_user_info["channels_id"])) {
                return response()->json(['code' => '1005', 'msg' => '采购渠道id不能为空']);
            } else if (empty($purchase_user_info["account_number"])) {
                return response()->json(['code' => '1006', 'msg' => '账号不能为空']);
            }

            //检查采购人员的信息
            $purchaseUserModel = new PurchaseUserModel();
            $check_user_info = $purchaseUserModel->check_user_info($purchase_user_info);

            if ($check_user_info) {
                return response()->json(['code' => '1007', 'msg' => '该采购id不存在']);
            }

            //更新采购id列表信息
            $updateRes = PurchaseUserModel::where("id", $purchase_user_info["id"])->update($purchase_user_info);

            $code = "1008";
            $msg = "编辑采购id失败";
            $return_info = compact('code', 'msg');

            if ($updateRes) {
                $code = "1000";
                $msg = "编辑采购id成功";
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