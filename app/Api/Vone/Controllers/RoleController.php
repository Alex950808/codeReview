<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\OperateLogModel;
use App\Model\Vone\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class RoleController extends BaseController
{
    /**
     * description:添加角色
     * editor:zongxing
     * type:POST
     * date : 2018.07.27
     * params: 1.角色简称:name;2.角色名称:display_name;3.角色描述:description;4.权限id:permission_list
     * return Object
     */
    public function addRole(Request $request)
    {
        if ($request->isMethod("post")) {
            $role_info = $request->toArray();

            if (empty($role_info["name"])) {
                return response()->json(['code' => '1002', 'msg' => '角色简称不能为空']);
            } else if (empty($role_info["display_name"])) {
                return response()->json(['code' => '1003', 'msg' => '角色名称不能为空']);
            } else if (empty($role_info["description"])) {
                return response()->json(['code' => '1004', 'msg' => '角色描述不能为空']);
            } else if (empty($role_info["permission_list"])) {
                return response()->json(['code' => '1005', 'msg' => '角色权限不能为空']);
            }

            //检查角色信息
            $roleModel = new Role();
            $check_role_info = $roleModel->check_role_info($role_info);

            if (!empty($check_role_info)) {
                return response()->json(['code' => '1006', 'msg' => '该角色已经存在']);
            }

            $permission_list = explode(",", $role_info["permission_list"]);
            unset($role_info["permission_list"]);
            $insert_role_info = [
                'name'=> trim($role_info['name']),
                'display_name'=> trim($role_info['display_name']),
                'description'=> trim($role_info['description']),
            ];

            //添加角色信息
            $insertRes = DB::table("roles")->insertGetId($insert_role_info);

            //添加角色权限信息
            $admin_role = Role::where('name', $role_info["name"])->first();
            $admin_role->attachPermissions($permission_list);

            $code = "1007";
            $msg = "添加角色失败";
            $return_info = compact('code', 'msg');

            if ($insertRes) {
                $code = "1000";
                $msg = "添加角色成功";
                $return_info = compact('code', 'msg');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_task',
                    'bus_desc' => '权限模块-添加角色-角色id：' . $insertRes,
                    'bus_value' => $insertRes,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '权限模块-添加角色',
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
     * description:获取角色列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.27
     * return Object
     */
    public function roleList(Request $request)
    {
        if ($request->isMethod("get")) {
            //获取管理员列表信息
            $role = new Role();
            $role_list_info = $role->get_role_list();

            $code = "1000";
            $msg = "获取角色列表成功";
            $data = $role_list_info;
            $return_info = compact('code', 'msg', 'data');

            if (empty($role_list_info)) {
                $code = "1002";
                $msg = "暂无角色";
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
     * description:编辑角色
     * editor:zongxing
     * type:POST
     * date : 2018.07.27
     * params: 1.角色简称:name;2.角色名称:display_name;3.角色描述:description;4.权限id:permission_list;5.角色id:role_id
     * return Object
     */
    public function editRole(Request $request)
    {
        if ($request->isMethod("get")) {
            //参数检查
            $role_info = $request->toArray();
            if (empty($role_info["id"])) {
                return response()->json(['code' => '1002', 'msg' => '请求参数错误']);
            }
            //检查角色信息
            $roleModel = new Role();
            $check_role_info = $roleModel->check_role_info($role_info);
            if (empty($check_role_info)) {
                return response()->json(['code' => '1003', 'msg' => '该角色不存在']);
            }

            //获取角色及权限信息
            $role_total_info = $roleModel->get_role_info($role_info);
            $code = "1000";
            $msg = "获取角色信息成功";
            $data = $role_total_info;
            $return_info = compact('code', 'msg', 'data');
        } elseif ($request->isMethod("post")) {
            //参数检查
            $role_info = $request->toArray();
            if (empty($role_info["name"])) {
                return response()->json(['code' => '1002', 'msg' => '角色简称不能为空']);
            } else if (empty($role_info["display_name"])) {
                return response()->json(['code' => '1003', 'msg' => '角色名称不能为空']);
            } else if (empty($role_info["description"])) {
                return response()->json(['code' => '1004', 'msg' => '角色描述不能为空']);
            } else if (empty($role_info["permission_list"])) {
                return response()->json(['code' => '1005', 'msg' => '角色权限不能为空']);
            }

            //检查角色信息
            $roleModel = new Role();
            $check_role_info = $roleModel->check_role_info($role_info);
            if (empty($check_role_info)) {
                return response()->json(['code' => '1006', 'msg' => '该角色不存在']);
            }

            $permission_list = substr(trim($role_info["permission_list"]), 0, -1);
            $permission_list = explode(",", $permission_list);
            $role_id = $role_info["role_id"];
            unset($role_info["permission_list"]);
            unset($role_info["role_id"]);

            //更新角色信息
            $update_info = [
                'description' => trim($role_info['description']),
                'display_name' => trim($role_info['display_name']),
                'name' => trim($role_info['name']),
            ];
            $updateRes = DB::table("roles")->where("id", $role_id)->update($update_info);

            $code = "1007";
            $msg = "编辑角色失败";
            $return_info = compact('code', 'msg');
            if ($updateRes !== false) {
                $code = "1000";
                $msg = "编辑角色成功";
                $return_info = compact('code', 'msg');

                //获取当前角色权限id列表信息
                $role_permission_list = DB::table("permission_role")->where('role_id', $role_id)->pluck("permission_id");
                $role_permission_list = objectToArrayZ($role_permission_list);

                $diff_add_arr = array_diff($permission_list, $role_permission_list);
                $diff_del_arr = array_diff($role_permission_list, $permission_list);

                //删除旧的角色权限信息
                if (!empty($diff_del_arr)) {
                    DB::table("permission_role")
                        ->where('role_id', $role_id)
                        ->whereIn('permission_id', $diff_del_arr)
                        ->delete();
                }
                //增加新的角色权限信息
                $admin_role = Role::where('name', $role_info["name"])->first();
                if (!empty($diff_add_arr)) {
                    $admin_role->attachPermissions($diff_add_arr);
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
     * description:删除角色
     * editor:zongxing
     * type:POST
     * date : 2018.07.28
     * return Object
     */
    public function delRole(Request $request)
    {
        if ($request->isMethod("get")) {
            $role_info = $request->toArray();
            if (empty($role_info["id"])) {
                return response()->json(['code' => '1002', 'msg' => '参数有误']);
            }

            //检查角色的信息
            $roleModel = new Role();
            $check_role_info = $roleModel->check_role_info($role_info);
            if (empty($check_role_info)) {
                return response()->json(['code' => '1003', 'msg' => '该角色不存在']);
            }

            //删除角色
            $role = Role::findOrFail($role_info["id"]);
            $role->users()->sync([]); // Delete relationship data
            $role->perms()->sync([]); // Delete relationship data
            $delRes = $role->forceDelete();

            $code = "1004";
            $msg = "删除角色失败";
            $return_info = compact('code', 'msg');

            if ($delRes) {
                $code = "1000";
                $msg = "删除角色成功";
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