<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\CommonModel;
use App\Model\Vone\DataModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\RealPurchaseModel;
use App\Modules\Excel\ExcuteExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;

/**
 * description:采购数据模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class DataManagementController extends BaseController
{
    /**
     * description:获取采购数据列表
     * editor:zongxing
     * type:GET
     * date : 2018.08.06
     * return Object
     */
    public function getDataManagementList(Request $request)
    {
        if ($request->isMethod("get")) {
            $goods_info = $request->toArray();

            //获取采购期及其商品数据的统计
            $demandCountModel = new DemandCountModel();
            $purchase_goods_list = $demandCountModel->getDataManagementList($goods_info);

            $code = "1000";
            $msg = "获取采购数据列表成功";
            $data_num = $purchase_goods_list["data_num"];
            $data = $purchase_goods_list["purchase_info"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
            if (empty($purchase_goods_list["purchase_info"])) {
                $code = "1002";
                $msg = "暂无采购数据";
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
     * description:获取商品数据列表
     * editor:zongxing
     * type:GET
     * date : 2018.08.06
     * return Object
     */
    public function getDataGoodsList(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();

            //获取采购期及其商品数据的统计
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getDataGoodsList($purchase_info);

            $code = "1000";
            $msg = "获取商品数据列表成功";
            $data_num = $purchase_data_list["data_num"];
            $data = $purchase_data_list["purchase_info"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
            if (empty($purchase_data_list["purchase_info"])) {
                $code = "1002";
                $msg = "暂无商品数据";
                $return_info = compact('code', 'msg');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }




}