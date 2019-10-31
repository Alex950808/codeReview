<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\SupplierModel;
use Dingo\Api\Contract\Http\Request;


class SupplierController extends BaseController
{
    /**
     * description:添加供应商
     * editor:zongxing
     * type:POST
     * date : 2019.01.07
     * params: 1.供应商名称:supplier_name;
     * return Array
     */
    public function addSupplier(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info['supplier_name'])) {
                return response()->json(['code' => '1002', 'msg' => '供应商名称不能为空']);
            }elseif (empty($param_info['supplier_num'])) {
                return response()->json(['code' => '1002', 'msg' => '供应商编号不能为空']);
            }
            //检查供应商名称是否已经存在
            $supplier_name = trim($param_info['supplier_name']);
            $supplier_model = new SupplierModel();
            $supplier_info = $supplier_model->getSupplierByName($supplier_name);
            if (!empty($supplier_info)) {
                return response()->json(['code' => '1003', 'msg' => '供应商名称已经存在']);
            }

            $inser_supplier_res = $supplier_model->addSupplierByName($param_info);
            if ($inser_supplier_res === false) {
                return response()->json(['code' => '1005', 'msg' => '添加供应商失败']);
            }
            $return_info = ['code' => '1000', 'msg' => '添加供应商成功'];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:供应商列表
     * editor:zongxing
     * type:GET
     * date : 2019.01.07
     * return Array
     */
    public function supplierList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $supplier_model = new SupplierModel();
            $is_page = true;
            $supplier_list = $supplier_model->getSupplierList($param_info, $is_page);
            if (empty($supplier_list)) {
                return response()->json(['code' => '1005', 'msg' => '暂无供应商信息']);
            }
            $return_info = ['code' => '1000', 'msg' => '获取供应商列表成功', 'data' => $supplier_list];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }


}