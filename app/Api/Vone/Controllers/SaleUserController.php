<?php
namespace App\Api\Vone\Controllers;

use App\Api\Vone\Controllers\BaseController;
use App\Model\Vone\DepartmentModel;
use App\Model\Vone\OperateLogModel;
use App\Model\Vone\RefundRules;
use App\Model\Vone\RefundRulesModel;
use App\Model\Vone\SaleUserAccountModel;
use App\Model\Vone\SaleUserModel;
use Dingo\Api\Http\Request;
use Illuminate\Support\Facades\DB;

//create by zongxing on the 2018.11.08
class SaleUserController extends BaseController
{
    /**
     * description:商品模块-商品管理-新增销售客户
     * editor:zongxing
     * date: 2018.11.08
     */
    public function addCustomer(Request $request)
    {
        if ($request->isMethod('get')) {
            //部门信息
            $department_model = new DepartmentModel();
            $department_info = $department_model->getDepartmentInfo();
            $data["department_info"] = $department_info;

            $code = "1000";
            $msg = "获取部门列表成功";
            $return_info = compact('code', 'msg', 'data');

            if (empty($department_info)) {
                $code = "1002";
                $msg = "暂无部门,请先创建部门";
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
     * description:商品模块-商品管理-确认新增销售客户
     * editor:zongxing
     * date: 2018.12.05
     */
    public function doAddCustomer(Request $request)
    {
        if ($request->isMethod('post')) {
            $reqParams = $request->toArray();

            if (empty($reqParams['depart_id'])) {
                return response()->json(['code' => '1002', 'msg' => '部门id不能为空']);
            } elseif (empty($reqParams['customer_name'])) {
                return response()->json(['code' => '1003', 'msg' => '客户名称不能为空']);
            } elseif (empty($reqParams['min_profit'])) {
                return response()->json(['code' => '1004', 'msg' => '客户最低利润不能为空']);
            } elseif (empty($reqParams['sale_user_cat'])) {
                return response()->json(['code' => '1005', 'msg' => '销售客户类型不能为空']);
            } elseif (empty($reqParams['money_cat'])) {
                return response()->json(['code' => '1006', 'msg' => '支付币种不能为空']);
            } elseif (empty($reqParams['payment_cycle'])) {
                return response()->json(['code' => '1007', 'msg' => '付款周期不能为空']);
            } elseif (empty($reqParams['sale_short'])) {
                return response()->json(['code' => '1008', 'msg' => '客户简称不能为空']);
            }

            if (intval($reqParams['min_profit']) < 0) {
                return response()->json(['code' => '1009', 'msg' => '客户最低利润不能小于0']);
            }

            $depart_id = intval($reqParams['depart_id']);
            $user_name = trim($reqParams['customer_name']);
            $sale_short = trim($reqParams['sale_short']);

            //检查销售用户是否存在
            $saleUserModel = new SaleUserModel();
            $sale_user_info = $saleUserModel->getSaleUser($depart_id, null, $user_name, $sale_short);
            $sale_user_info = objectToArrayZ($sale_user_info);
            if (!empty($sale_user_info)) {
                return response()->json(['code' => '1010', 'msg' => '该部门下销售用户已经存在']);
            }

            $insert_sale_user['depart_id'] = $depart_id;
            $insert_sale_user['user_name'] = $user_name;
            $insert_sale_user['min_profit'] = trim($reqParams['min_profit']);
            $insert_sale_user['sale_user_cat'] = trim($reqParams['sale_user_cat']);
            $insert_sale_user['money_cat'] = trim($reqParams['money_cat']);
            $insert_sale_user['sale_short'] = trim($reqParams['sale_short']);
            $insert_sale_user['payment_cycle'] = intval($reqParams['payment_cycle']);

            $group_sn = $insert_sale_user['sale_user_cat'] . $insert_sale_user['money_cat'] . "-" .
                $insert_sale_user['sale_short'] . "-" .
                $insert_sale_user['payment_cycle'];
            $insert_sale_user['group_sn'] = $group_sn;
            $res_insert_sale_user = DB::table("sale_user")->insert($insert_sale_user);

            $code = "1010";
            $msg = "新增客户失败";
            $return_info = compact('code', 'msg');
            if ($res_insert_sale_user !== false) {
                $code = "1000";
                $msg = "新增客户成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_sale_user',
                    'bus_desc' => '新增客户，客户ID：' . $group_sn,
                    'bus_value' => $group_sn,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '销售模块-销售客户管理-新增客户',
                    'module_id' => 6,
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
     * description:商品模块-商品管理-编辑销售客户
     * editor:zongxing
     * date: 2018.12.05
     */
    public function editCustomer(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();

            if (empty(intval($reqParams['sale_user_id']))) {
                return response()->json(['code' => '1002', 'msg' => '销售客户id不能为空']);
            }

            //检查销售用户是否存在
            $saleUserModel = new SaleUserModel();
            $sale_user_id = intval($reqParams['sale_user_id']);
            $sale_user_info = $saleUserModel->getSaleUser(null, $sale_user_id);
            $sale_user_info = objectToArrayZ($sale_user_info);

            if (empty($sale_user_info)) {
                return response()->json(['code' => '1003', 'msg' => '该销售用户不存在']);
            }
            //部门信息
            $department_model = new DepartmentModel();
            $department_info = $department_model->getDepartmentInfo();
            $data["department_info"] = $department_info;

            $code = "1000";
            $msg = "获取客户信息";
            $data["sale_user_info"] = $sale_user_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:商品模块-商品管理-确认编辑销售客户
     * editor:zongxing
     * date: 2018.12.05
     */
    public function doEditCustomer(Request $request)
    {
        if ($request->isMethod('post')) {
            $reqParams = $request->toArray();

            if (empty($reqParams['sale_user_id'])) {
                return response()->json(['code' => '1011', 'msg' => '客户id不能为空']);
            } elseif (empty($reqParams['depart_id'])) {
                return response()->json(['code' => '1002', 'msg' => '部门id不能为空']);
            } elseif (empty($reqParams['customer_name'])) {
                return response()->json(['code' => '1003', 'msg' => '客户名称不能为空']);
            } elseif (empty($reqParams['min_profit'])) {
                return response()->json(['code' => '1004', 'msg' => '客户最低利润不能为空']);
            } elseif (empty($reqParams['sale_user_cat'])) {
                return response()->json(['code' => '1005', 'msg' => '销售客户类型不能为空']);
            } elseif (empty($reqParams['money_cat'])) {
                return response()->json(['code' => '1006', 'msg' => '支付币种不能为空']);
            } elseif (empty($reqParams['payment_cycle'])) {
                return response()->json(['code' => '1007', 'msg' => '付款周期不能为空']);
            } elseif (empty($reqParams['sale_short'])) {
                return response()->json(['code' => '1008', 'msg' => '客户简称不能为空']);
            }

            if (intval($reqParams['min_profit']) < 0) {
                return response()->json(['code' => '1009', 'msg' => '客户最低利润不能小于0']);
            }

            //检查销售用户是否存在
            $saleUserModel = new SaleUserModel();
            $sale_user_id = intval($reqParams['sale_user_id']);
            $sale_user_info = $saleUserModel->getSaleUser(null, $sale_user_id);
            $sale_user_info = objectToArrayZ($sale_user_info);
            if (empty($sale_user_info)) {
                return response()->json(['code' => '1011', 'msg' => '该销售用户不存在']);
            }

            //检查部门下的销售客户是否重复
            $depart_id = intval($reqParams['depart_id']);
            $user_name = trim($reqParams['customer_name']);
            $sale_short = trim($reqParams['sale_short']);
            $saleUserModel = new SaleUserModel();
            $sale_user_isset = $saleUserModel->getSaleUser($depart_id, $sale_user_id, $user_name, $sale_short, true);
            $sale_user_isset = objectToArrayZ($sale_user_isset);
            if (!empty($sale_user_isset)) {
                return response()->json(['code' => '1012', 'msg' => '指定部门下该客户已经存在']);
            }

            //组装更新客户信息
            $update_sale_user = [
                'depart_id' => $depart_id,
                'user_name' => $user_name,
                'min_profit' => trim($reqParams['min_profit']),
                'sale_user_cat' => trim($reqParams['sale_user_cat']),
                'money_cat' => trim($reqParams['money_cat']),
                'sale_short' => $sale_short,
                'payment_cycle' => intval($reqParams['payment_cycle']),
            ];
            $group_sn = $update_sale_user['sale_user_cat'] . $update_sale_user['money_cat'] . "-" .
                $update_sale_user['sale_short'] . "-" .
                $update_sale_user['payment_cycle'];
            $update_sale_user['group_sn'] = $group_sn;
            $res_update_sale_user = DB::table("sale_user")->where("id", $sale_user_id)->update($update_sale_user);

            $code = "1010";
            $msg = "编辑客户失败";
            $return_info = compact('code', 'msg');
            if ($res_update_sale_user !== false) {
                $code = "1000";
                $msg = "编辑客户成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_sale_user',
                    'bus_desc' => '编辑客户，客户ID：' . $group_sn,
                    'bus_value' => $group_sn,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '销售模块-销售客户管理-编辑客户',
                    'module_id' => 6,
                    'have_detail' => 0,
                ];

                $logDetailData["table_name"] = 'operate_log_detail';
                $sale_user_info = $sale_user_info[0];
                foreach ($update_sale_user as $k => $v) {
                    if (isset($sale_user_info[$k]) && $sale_user_info[$k] != $v) {
                        $logDetailData["update_info"][] = [
                            'table_field_name' => $k,
                            'field_old_value' => $sale_user_info[$k],
                            'field_new_value' => $v,
                        ];
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
     * description:销售模块-销售客户账号管理-新增销售客户账号
     * editor:zongxing
     * date: 2018.12.08
     */
    public function addSaleAccount(Request $request)
    {
        if ($request->isMethod('get')) {
            //销售客户列表信息
            $sale_user_list = DB::table("sale_user")->get(["id", "user_name", "depart_id"]);
            if (empty($sale_user_list)) {
                return response()->json(['code' => '1002', 'msg' => '暂无销售客户信息,请先创建销售客户']);
            }
            //品牌信息
            $brand_list = DB::table('brand')->get(['brand_id','name']);
            if (empty($brand_list)) {
                return response()->json(['code' => '1002', 'msg' => '暂无品牌信息,请先维护']);
            }

            $code = "1000";
            $msg = "获取销售客户成功";
            $data['sale_user_list'] = $sale_user_list;
            $data['brand_list'] = $brand_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-销售客户账号管理-确认新增销售客户账号
     * editor:zongxing
     * date: 2018.12.08
     */
    public function doAddSaleAccount(Request $request)
    {
        if ($request->isMethod('post')) {
            $reqParams = $request->toArray();
            if (empty($reqParams['user_name'])) {
                return response()->json(['code' => '1002', 'msg' => '账号不能为空']);
            } elseif (empty($reqParams['sale_user_id'])) {
                return response()->json(['code' => '1003', 'msg' => '客户id不能为空']);
            } elseif (empty($reqParams['brand_id'])) {
                return response()->json(['code' => '1004', 'msg' => '客户账号品牌id不能为空']);
            }

            //检查销售客户账号是否存在
            $user_name = trim($reqParams['user_name']);
            $sale_user_id = intval($reqParams['sale_user_id']);
            $sua_model = new SaleUserAccountModel();
            $sale_account_info = $sua_model->getSaleAccount($user_name, $sale_user_id);
            if (!empty($sale_account_info)) {
                return response()->json(['code' => '1005', 'msg' => '该销售用户下此账号已经存在']);
            }

            $insert_sale_account['user_name'] = $user_name;
            $insert_sale_account['sale_user_id'] = $sale_user_id;
            $insert_sale_account['brand_id'] = json_encode($reqParams["brand_id"], JSON_UNESCAPED_UNICODE);
            $res_insert_sale_user = DB::table("sale_user_account")->insertGetId($insert_sale_account);

            $code = "1006";
            $msg = "新增销售客户账号失败";
            $return_info = compact('code', 'msg');
            if ($res_insert_sale_user !== false) {
                $code = "1000";
                $msg = "新增销售客户账号成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_sale_user_account',
                    'bus_desc' => '新增销售客户账号，销售客户id：' . $res_insert_sale_user,
                    'bus_value' => $res_insert_sale_user,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '销售模块-销售客户账号管理-新增销售客户账号',
                    'module_id' => 6,
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
     * description:销售模块-销售客户账号管理-编辑销售客户账号
     * editor:zongxing
     * date: 2018.12.05
     */
    public function editSaleAccount(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();
            if (empty(intval($reqParams['sale_account_id']))) {
                return response()->json(['code' => '1002', 'msg' => '销售客户账号id不能为空']);
            }

            //检查销售客户账号是否存在
            $sale_account_id = intval($reqParams['sale_account_id']);
            $sua_model = new SaleUserAccountModel();
            $sale_account_info = $sua_model->getSaleAccount(null, null, $sale_account_id);
            if (empty($sale_account_info)) {
                return response()->json(['code' => '1003', 'msg' => '该销售客户账号不存在']);
            }
            //销售客户列表信息
            $sale_user_list = DB::table("sale_user")->get(["id", "user_name", "depart_id"]);
            $data["sale_user_list"] = $sale_user_list;

            //品牌信息
            $brand_list = DB::table('brand')->get(['brand_id','name']);
            $data['brand_list'] = $brand_list;

            $code = "1000";
            $msg = "获取销售客户账号信息成功";
            $data["sale_account_info"] = $sale_account_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-销售客户账号管理-确认编辑销售客户账号
     * editor:zongxing
     * date: 2018.12.05
     */
    public function doEditSaleAccount(Request $request)
    {
        if ($request->isMethod('post')) {
            $reqParams = $request->toArray();
            if (empty($reqParams['user_name'])) {
                return response()->json(['code' => '1002', 'msg' => '账号不能为空']);
            } elseif (empty($reqParams['sale_user_id'])) {
                return response()->json(['code' => '1003', 'msg' => '客户id不能为空']);
            } elseif (empty($reqParams['sale_account_id'])) {
                return response()->json(['code' => '1004', 'msg' => '客户账号id不能为空']);
            } elseif (empty($reqParams['brand_id'])) {
                return response()->json(['code' => '1008', 'msg' => '客户账号品牌id不能为空']);
            }

            //检查销售客户账号是否存在
            $sale_account_id = intval($reqParams['sale_account_id']);
            $sua_model = new SaleUserAccountModel();
            $sale_account_info = $sua_model->getSaleAccount(null, null, $sale_account_id);
            if (empty($sale_account_info)) {
                return response()->json(['code' => '1005', 'msg' => '该销售客户账号不存在']);
            }

            //检查销售客户中此账号是否存在
            $user_name = trim($reqParams['user_name']);
            $sale_user_id = intval($reqParams['sale_user_id']);
            $user_name_old = trim($sale_account_info['user_name']);
            $sale_user_id_old = intval($sale_account_info['sale_user_id']);
            $sale_account_id_old = intval($sale_account_info['id']);
            if ($user_name == $user_name_old && $sale_user_id == $sale_user_id_old && $sale_account_id != $sale_account_id_old) {
                return response()->json(['code' => '1006', 'msg' => '该销售用户下此账号已经存在']);
            }

            //更新客户账号信息
            $brand_id = json_encode($reqParams["brand_id"], JSON_UNESCAPED_UNICODE);
            $update_sale_account['user_name'] = $user_name;
            $update_sale_account['sale_user_id'] = $sale_user_id;
            $update_sale_account['brand_id'] = $brand_id;
            $res_update_sale_account = DB::table("sale_user_account")->where("id", $sale_account_id)->update($update_sale_account);
            $code = "1007";
            $msg = "编辑销售客户账号失败";
            $return_info = compact('code', 'msg');
            if ($res_update_sale_account !== false) {
                $code = "1000";
                $msg = "编辑销售客户账号成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_sale_user',
                    'bus_desc' => '编辑销售客户账号，销售客户账号id：' . $sale_account_id,
                    'bus_value' => $sale_account_id,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '销售模块-销售客户管理-确认编辑销售客户账号',
                    'module_id' => 6,
                    'have_detail' => 0,
                ];

                $logDetailData["table_name"] = 'operate_log_detail';
                foreach ($update_sale_account as $k => $v) {
                    if (isset($sale_account_info[$k]) && $sale_account_info[$k] != $v) {
                        $old_value = $sale_account_info[$k];
                        if ($k == 'brand_id'){
                            $old_value = json_encode($sale_account_info[$k], JSON_UNESCAPED_UNICODE);
                        }
                        $logDetailData["update_info"][] = [
                            'table_field_name' => $k,
                            'field_old_value' => $old_value,
                            'field_new_value' => $v,
                        ];
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
     * description:销售模块-销售客户账号管理-销售客户账号列表
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * return Object
     */
    public function saleAccountList()
    {
        $field = ['sua.id', 'sua.user_name as account_name', 'sale_user_id', 'su.user_name as sale_user_name',
            DB::raw('DATE(jms_sua.create_time) as create_time')
        ];
        $sale_account_list = DB::table("sale_user_account as sua")
            ->leftJoin("sale_user as su", "su.id", "=", "sua.sale_user_id")
            ->orderBy('sua.create_time','desc')
            ->get($field);
        $sale_account_list = objectToArrayZ($sale_account_list);
        if (empty($sale_account_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无销售客户账号']);
        }
        $code = "1000";
        $msg = "获取销售客户账号列表成功";
        $data = $sale_account_list;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:销售模块-销售客户账号管理-新增销售客户回款规则
     * editor:zongxing
     * date: 2018.12.13
     */
    public function addRefundRules(Request $request)
    {
        if ($request->isMethod('get')) {
            //销售客户信息
            $sale_user_list = DB::table("sale_user")->get(["id", "user_name", "depart_id"]);
            if (empty($sale_user_list)) {
                return response()->json(['code' => '1002', 'msg' => '暂无销售客户信息,请先创建销售客户']);
            }

            $code = "1000";
            $msg = "获取销售客户成功";
            $data["sale_user_list"] = $sale_user_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-销售客户账号管理-确认新增销售客户回款规则
     * editor:zongxing
     * date: 2018.12.13
     */
    public function doAddRefundRules(Request $request)
    {
        if ($request->isMethod('post')) {
            $reqParams = $request->toArray();
            if (empty($reqParams['sale_user_id'])) {
                return response()->json(['code' => '1002', 'msg' => '客户id不能为空']);
            } elseif (empty($reqParams['delivery_type'])) {
                return response()->json(['code' => '1003', 'msg' => '交货类别不能为空']);
            } elseif (empty($reqParams['ship_type'])) {
                return response()->json(['code' => '1004', 'msg' => '运输方式不能为空']);
            } elseif (empty($reqParams['ship_day'])) {
                return response()->json(['code' => '1005', 'msg' => '运输天数不能为空']);
            } elseif (empty($reqParams['tally_day'])) {
                return response()->json(['code' => '1006', 'msg' => '理货天数不能为空']);
            } elseif (empty($reqParams['pay_day'])) {
                return response()->json(['code' => '1007', 'msg' => '支付天数不能为空']);
            }
            //检查交货类别
            $total_delivery_type = [1, 2, 3, 4];
            $delivery_type = intval($reqParams['delivery_type']);
            if (!in_array($delivery_type, $total_delivery_type)) {
                return response()->json(['code' => '1008', 'msg' => '交货类别有误,请检查']);
            }
            //检查运输方式
            $total_ship_type = [1, 2, 3];
            $ship_type = intval($reqParams['ship_type']);
            if (!in_array($ship_type, $total_ship_type)) {
                return response()->json(['code' => '1009', 'msg' => '运输方式有误,请检查']);
            }
            //检查销售客户账号是否存在
            $sale_user_id = intval($reqParams['sale_user_id']);
            $ship_type = intval($reqParams['ship_type']);
            $saleUserModel = new SaleUserModel();
            $sale_refund_rule_info = $saleUserModel->getSaleRefundRules($sale_user_id, $delivery_type, $ship_type);
            if (!empty($sale_refund_rule_info)) {
                return response()->json(['code' => '1010', 'msg' => '该销售用户下此回款规则已经存在']);
            }
            //新增回款规则数据组装
            $ship_day = intval($reqParams['ship_day']);
            $tally_day = intval($reqParams['tally_day']);
            $pay_day = intval($reqParams['pay_day']);
            $insert_refund_rule['sale_user_id'] = $sale_user_id;
            $insert_refund_rule['delivery_type'] = $delivery_type;
            $insert_refund_rule['ship_type'] = $ship_type;
            $insert_refund_rule['ship_day'] = $ship_day;
            $insert_refund_rule['tally_day'] = $tally_day;
            $insert_refund_rule['pay_day'] = $pay_day;
            $res_insert_refund_rule = DB::table("refund_rules")->insertGetId($insert_refund_rule);

            $code = "1011";
            $msg = "新增销售客户回款规则失败";
            $return_info = compact('code', 'msg');
            if ($res_insert_refund_rule !== false) {
                $code = "1000";
                $msg = "新增销售客户回款规则成功";
                $return_info = compact('code', 'msg');
                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_sale_user_account',
                    'bus_desc' => '新增销售客户回款规则,销售客户id：' . $res_insert_refund_rule,
                    'bus_value' => $res_insert_refund_rule,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '销售模块-销售客户账号管理-新增销售客户回款规则',
                    'module_id' => 6,
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
     * description:销售模块-销售客户账号管理-销售客户回款规则列表
     * editor:zongxing
     * type:POST
     * date : 2018.12.13
     * return Object
     */
    public function refundRulesList()
    {
        $refund_rule_model = new RefundRulesModel();
        $refundRulesList = $refund_rule_model->refundRulesList();
        if (empty($refundRulesList)) {
            return response()->json(['code' => '1002', 'msg' => '暂无销售客户回款规则']);
        }
        $code = "1000";
        $msg = "获取销售客户回款规则列表成功";
        $data = $refundRulesList;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:销售模块-销售客户账号管理-编辑销售客户回款规则
     * editor:zongxing
     * date: 2018.12.13
     */
    public function editRefundRules(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();
            if (empty(intval($reqParams['id']))) {
                return response()->json(['code' => '1002', 'msg' => '销售客户回款规则id不能为空']);
            }

            //检查销售客户回款规则是否存在
            $refund_rule_id = intval($reqParams['id']);
            $saleUserModel = new SaleUserModel();
            $refund_rule_info = $saleUserModel->getSaleRefundRules(null, null, null, $refund_rule_id);
            if (empty($refund_rule_info)) {
                return response()->json(['code' => '1003', 'msg' => '该销售客户回款规则不存在']);
            }
            //销售客户列表信息
            $sale_user_list = DB::table("sale_user")->get(["id", "user_name", "depart_id"]);
            $data["sale_user_list"] = $sale_user_list;

            $code = "1000";
            $msg = "获取销售客户回款规则信息成功";
            $data["refund_rule_info"] = $refund_rule_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-销售客户账号管理-确认编辑销售客户回款规则
     * editor:zongxing
     * date: 2018.12.13
     */
    public function doEditRefundRules(Request $request)
    {
        if ($request->isMethod('post')) {
            $reqParams = $request->toArray();
            if (empty($reqParams['sale_user_id'])) {
                return response()->json(['code' => '1002', 'msg' => '客户id不能为空']);
            } elseif (empty($reqParams['delivery_type'])) {
                return response()->json(['code' => '1003', 'msg' => '交货类别不能为空']);
            } elseif (empty($reqParams['ship_type'])) {
                return response()->json(['code' => '1004', 'msg' => '运输方式不能为空']);
            } elseif (empty($reqParams['ship_day'])) {
                return response()->json(['code' => '1005', 'msg' => '运输天数不能为空']);
            } elseif (empty($reqParams['tally_day'])) {
                return response()->json(['code' => '1006', 'msg' => '理货天数不能为空']);
            } elseif (empty($reqParams['pay_day'])) {
                return response()->json(['code' => '1007', 'msg' => '支付天数不能为空']);
            } elseif (empty($reqParams['id'])) {
                return response()->json(['code' => '1008', 'msg' => '回款规则id不能为空']);
            }

            //检查销售客户账号是否存在
            $refund_rule_id = intval($reqParams['id']);
            $saleUserModel = new SaleUserModel();
            $refund_rule_info = $saleUserModel->getSaleRefundRules(null, null, null, $refund_rule_id);
            if (empty($refund_rule_info)) {
                return response()->json(['code' => '1009', 'msg' => '该销售客户回款规则不存在']);
            }

            //检查销售客户中此账号是否存在
            $sale_user_id = intval($reqParams['sale_user_id']);
            $delivery_type = intval($reqParams['delivery_type']);
            $ship_type = intval($reqParams['ship_type']);
            $saleUserModel = new SaleUserModel();
            $refund_rule_isset = $saleUserModel->getSaleRefundRules($sale_user_id, $delivery_type, $ship_type, $refund_rule_id, true);
            if (!empty($refund_rule_isset)) {
                return response()->json(['code' => '1010', 'msg' => '该销售用户下此回款规则已经存在']);
            }

            //更新客户回款规则信息
            $ship_day = intval($reqParams['ship_day']);
            $tally_day = intval($reqParams['tally_day']);
            $pay_day = intval($reqParams['pay_day']);
            $update_refund_rule['sale_user_id'] = $sale_user_id;
            $update_refund_rule['delivery_type'] = $delivery_type;
            $update_refund_rule['ship_type'] = $ship_type;
            $update_refund_rule['ship_day'] = $ship_day;
            $update_refund_rule['tally_day'] = $tally_day;
            $update_refund_rule['pay_day'] = $pay_day;
            $res_update_sale_account = DB::table("refund_rules")->where("id", $refund_rule_id)->update($update_refund_rule);

            $code = "1007";
            $msg = "编辑销售客户回款规则失败";
            $return_info = compact('code', 'msg');
            if ($res_update_sale_account !== false) {
                $code = "1000";
                $msg = "编辑销售客户回款规则成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_refund_rules',
                    'bus_desc' => '编辑销售客户回款规则，销售客户回款规则id：' . $refund_rule_id,
                    'bus_value' => $refund_rule_id,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '销售模块-销售客户管理-确认编辑销售客户回款规则',
                    'module_id' => 6,
                    'have_detail' => 0,
                ];

                $logDetailData["table_name"] = 'operate_log_detail';
                foreach ($refund_rule_info as $k => $v) {
                    if ($refund_rule_info[$k] != $v) {
                        $logDetailData["update_info"][] = [
                            'table_field_name' => $k,
                            'field_old_value' => $refund_rule_info[$k],
                            'field_new_value' => $v,
                        ];
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

}