<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\AdminUserModel;
use App\Model\Vone\DepartmentModel;
use App\Model\Vone\OperateLogModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class AdminUserController extends BaseController
{
    /**
     * description:添加管理员
     * editor:zongxing
     * type:POST
     * date : 2018.07.27
     * params: 1.用户名:user_name;2.密码:password;3.确认密码:confirm_password;4.角色id:role_id;
     * return Object
     */
    public function addAdminUser(Request $request)
    {
        if ($request->isMethod("post")) {
            $admin_user_info = $request->toArray();
            if (empty($admin_user_info["user_name"])) {
                return response()->json(['code' => '1002', 'msg' => '用户名不能为空']);
            } else if (empty($admin_user_info["password"])) {
                return response()->json(['code' => '1003', 'msg' => '密码不能为空']);
            } else if (empty($admin_user_info["confirm_password"])) {
                return response()->json(['code' => '1004', 'msg' => '确认密码不能为空']);
            } else if ($admin_user_info["password"] != $admin_user_info["confirm_password"]) {
                return response()->json(['code' => '1005', 'msg' => '两次输入的密码不一致']);
            } else if (empty($admin_user_info["role_id"])) {
                return response()->json(['code' => '1006', 'msg' => '角色不能为空']);
            } else if (empty($admin_user_info["department_id"])) {
                return response()->json(['code' => '1007', 'msg' => '部门id不能为空']);
            } else if (empty($admin_user_info["nickname"])) {
                return response()->json(['code' => '1008', 'msg' => '昵称不能为空']);
            }

            //检查管理员的信息
            $adminUserModel = new AdminUserModel();
            $check_user_info = $adminUserModel->check_user_info($admin_user_info);

            if (!empty($check_user_info)) {
                return response()->json(['code' => '1009', 'msg' => '该管理员已经存在']);
            }

            $role_id = intval($admin_user_info["role_id"]);
            $password = bcrypt($admin_user_info["password"]);
            $insert_data = [
                'user_name' => trim($admin_user_info["user_name"]),
                'nickname' => trim($admin_user_info["nickname"]),
                'password' => $password,
                'role_id' => $role_id,
                'department_id' => intval($admin_user_info["department_id"]),
            ];
            $insertRes = DB::table("admin_user")->insertGetId($insert_data);

            $code = "1008";
            $msg = "添加管理员失败";
            $return_info = compact('code', 'msg');

            if ($insertRes !== false) {
                $code = "1000";
                $msg = "添加管理员成功";
                $return_info = compact('code', 'msg');

                //添加管理员成功，添加角色
                $user = AdminUserModel::where('user_name', $admin_user_info["user_name"])->first();
                $user->attachRole($role_id); //参数可以是Role对象，数组或id

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_task',
                    'bus_desc' => '权限模块-添加管理员-管理员id：' . $insertRes,
                    'bus_value' => $insertRes,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '权限模块-添加管理员',
                    'module_id' => 5,
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
     * description:获取管理员列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.27
     * return Object
     */
    public function adminUserList(Request $request)
    {
        if ($request->isMethod("get")) {
            //获取管理员列表信息
            $adminUserModel = new AdminUserModel();
            $user_list_info = $adminUserModel->getUserList();

            $data["user_list_info"] = $user_list_info;

            $permission_list = DB::table("roles")->select("id", "display_name")->get();
            $permission_list = $permission_list->toArray();
            $data["role_list"] = $permission_list;

            //部门信息
            $department_model = new DepartmentModel();
            $department_info = $department_model->getDepartmentInfo();
            $data["department_info"] = $department_info;

            $code = "1000";
            $msg = "获取管理员列表成功";
            $return_info = compact('code', 'msg', 'data');

            if (empty($user_list_info)) {
                $code = "1002";
                $msg = "暂无管理员";
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
     * description:改变管理员角色
     * editor:zongxing
     * type:POST
     * date : 2018.07.27
     * return Object
     */
    public function eidtAdminUser(Request $request)
    {
        if ($request->isMethod("post")) {
            $admin_user_info = $request->toArray();
            if (empty($admin_user_info["user_id"]) || empty($admin_user_info["role_id"])) {
                return response()->json(['code' => '1002', 'msg' => '参数有误']);
            }

            //检查管理员的信息
            $adminUserModel = new AdminUserModel();
            $get_user_info = $adminUserModel->get_user_role($admin_user_info);

            if (empty($get_user_info)) {
                return response()->json(['code' => '1003', 'msg' => '该管理员角色不存在']);
            }
            //更改管理员角色
            $updateRes = DB::table('role_user')
                ->where('user_id', $admin_user_info["user_id"])
                ->update(['role_id' => $admin_user_info["role_id"]]);

            $code = "1004";
            $msg = "改变管理员角色失败";
            $return_info = compact('code', 'msg');

            if ($updateRes !== false) {
                $code = "1000";
                $msg = "改变管理员角色成功";
                $return_info = compact('code', 'msg');

                DB::table('admin_user')
                    ->where('id', $admin_user_info["user_id"])
                    ->update(['role_id' => $admin_user_info["role_id"]]);

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_task',
                    'bus_desc' => '权限模块-改变管理员角色-管理员id：' . $admin_user_info["user_id"] . '的角色id:' . $admin_user_info["role_id"],
                    'bus_value' => $admin_user_info["role_id"],
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '权限模块-改变管理员角色',
                    'module_id' => 5,
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
     * description:删除管理员
     * editor:zongxing
     * type:POST
     * date : 2018.07.27
     * return Object
     */
    public function delAdminUser(Request $request)
    {
        if ($request->isMethod("get")) {
            $admin_user_info = $request->toArray();
            if (empty($admin_user_info["id"])) {
                return response()->json(['code' => '1002', 'msg' => '参数有误']);
            }

            //检查管理员的信息
            $adminUserModel = new AdminUserModel();
            $check_user_info = $adminUserModel->check_user_info($admin_user_info);
            if (empty($check_user_info)) {
                return response()->json(['code' => '1003', 'msg' => '该管理员不存在']);
            }

            //更改管理员状态
            $updateRes = DB::table('admin_user')->where('id', $admin_user_info["id"])->update(['status' => 2]);

            $code = "1004";
            $msg = "删除管理员失败";
            $return_info = compact('code', 'msg');

            if ($updateRes) {
                $code = "1000";
                $msg = "删除管理员成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_task',
                    'bus_desc' => '权限模块-删除管理员-修改管理员id：' . $admin_user_info["id"] . '的status',
                    'bus_value' => 2,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '权限模块-删除管理员',
                    'module_id' => 5,
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
     * description:通过角色id获取管理员列表
     * editor:zongxing
     * type:POST
     * date : 2018.08.22
     * return Object
     */
    public function userOfRole(Request $request)
    {
        if ($request->isMethod("post")) {
            $role_info = $request->toArray();
            $role_id = $role_info["role_id"];

            $user_list_info = DB::table("admin_user")->where("role_id", $role_id)->get(["id", "user_name"]);
            $user_list_info = $user_list_info->toArray();

            $code = "1002";
            $msg = "暂无管理员";
            $return_info = compact('code', 'msg');

            if (!empty($user_list_info)) {
                $code = "1000";
                $msg = "获取管理员列表成功";
                $data = $user_list_info;
                $return_info = compact('code', 'msg', 'data');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }


}