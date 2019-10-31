<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\CommonModel;
use App\Model\Vone\OperateLogModel;
use App\Model\Vone\Role;
use App\Model\Vone\TaskDetailModel;
use App\Model\Vone\TaskModel;
use Dingo\Api\Contract\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends BaseController
{
    /**
     * description:新增任务模板
     * editor:zongxing
     * type:POST
     * date : 2018.08.20
     * params: 1.任务模板名称:task_name;
     * return Object
     */
    public function addTask(Request $request)
    {
        if ($request->isMethod('post')) {
            $task_info = $request->toArray();
            if (empty($task_info['task_name'])) {
                return response()->json(['code' => '1002', 'msg' => '任务模板名称不能为空']);
            }

            //计算任务编号
            $model_obj = new TaskModel();
            $model_field = "task_sn";
            $pin_head = "TK-";
            $last_task_sn = createNo($model_obj, $model_field, $pin_head, false);
            $task_info["task_sn"] = $last_task_sn;

            //生成系统任务
            $task_detail_model = new TaskDetailModel();
            $add_sys_task = $task_detail_model->addSysTask($last_task_sn);

            if (!$add_sys_task) {
                return response()->json(['code' => '1003', 'msg' => '生成系统任务失败']);
            }

            $insert_task_info = [
                'task_name' => trim($task_info["task_name"]),
            ];
            $task_add_res = DB::table("task")->insert($insert_task_info);

            if (!$task_add_res) {
                return response()->json(['code' => '1004', 'msg' => '任务模板添加失败']);
            }

            //$task_total_info = DB::table("task_detail")->where("task_sn", $last_task_sn)->get(["id","task_content"]);

            $code = "1000";
            $msg = "任务模板添加成功";
            //$data = $task_total_info;
            $return_info = compact('code', 'msg');

            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'jms_task',
                'bus_desc' => '任务模板编号：' . $task_info['task_sn'],
                'bus_value' => $task_info['task_sn'],
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '任务模块-新增任务模板',
                'module_id' => 4,
                'have_detail' => 0,
            ];
            $operateLogModel->insertLog($logData);

        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:打开编辑任务页面
     * editor:zongxing
     * type:POST
     * date : 2018.08.31
     * params: 1.任务id:id;
     * return Object
     */
    public function editTask(Request $request)
    {
        if ($request->isMethod('post')) {
            $task_info = $request->toArray();

            if (empty($task_info['id'])) {
                return response()->json(['code' => '1002', 'msg' => '任务id不能为空']);
            }

            $task_detail = DB::table("task_detail")->where("id", $task_info['id'])->first();
            $task_detail = objectToArrayZ($task_detail);
            $task_detail["role_id"] = explode(",", $task_detail["role_id"]);
            $task_detail["user_list"] = explode(",", $task_detail["user_list"]);
            $data["task_info"] = $task_detail;

            //获取角色列表信息
            $role = new Role();
            $role_list_info = $role->get_role_list();
            $data["role_list"] = $role_list_info;

            //管理员列表信息
            $admin_user_list = DB::table("admin_user")->get(["id", "user_name", "role_id"]);
            $data["user_list"] = $admin_user_list;

            $code = "1003";
            $msg = "暂无该任务信息";
            $return_info = compact('code', 'msg');

            if ($task_detail) {
                $code = "1000";
                $msg = "获取任务信息成功";
                $return_info = compact('code', 'msg', "data");
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:提交编辑任务
     * editor:zongxing
     * type:POST
     * date : 2018.08.31
     * params: 1.任务id:id;2.任务日期规格:task_date;3.任务时刻:task_time;4.任务内容:task_content;5.角色id:role_id;
     *          6.管理员列表:user_list;7.任务是否是系统任务:is_system;
     * return Object
     */
    public function doEditTask(Request $request)
    {
        if ($request->isMethod('post')) {
            $task_info = $request->toArray();

            if (empty($task_info['id'])) {
                return response()->json(['code' => '1002', 'msg' => '任务id不能为空']);
            } else if (empty($task_info['task_date'])) {
                return response()->json(['code' => '1003', 'msg' => '任务日期规格不能为空']);
            } else if (empty($task_info['task_time'])) {
                return response()->json(['code' => '1004', 'msg' => '任务时刻不能为空']);
            } else if (empty($task_info['task_content'])) {
                return response()->json(['code' => '1005', 'msg' => '任务内容不能为空']);
            } else if (empty($task_info['role_id'])) {
                return response()->json(['code' => '1006', 'msg' => '角色id不能为空']);
            } else if (empty($task_info['user_list'])) {
                return response()->json(['code' => '1007', 'msg' => '管理员列表不能为空']);
            } else if (empty($task_info['is_system']) && $task_info['is_system'] != 0) {
                return response()->json(['code' => '1008', 'msg' => '系统任务判断字段不能为空']);
            } else if (!isset($task_info['sort_num'])) {
                return response()->json(['code' => '1009', 'msg' => '系统任务排序字段不能为空']);
            } else if (empty($task_info['task_sn'])) {
                return response()->json(['code' => '1010', 'msg' => '任务模板编号不能为空']);
            } elseif (strpos($task_info['task_sn'], 'TK') === false) {
                return response()->json(['code' => '1015', 'msg' => '任务模板编号有误']);
            }

            $is_system = intval($task_info['is_system']);
            if ($is_system == 1) {
                //检查系统任务的时间设置
                $task_detail_model = new TaskDetailModel();
                $check_sys_info = $task_detail_model->check_sys_info($task_info);

                if (!empty($check_sys_info["code"])) {
                    return response()->json($check_sys_info);
                }
            }
            $update_task_info = [
                'task_date' => trim($task_info["task_date"]),
                'task_time' => trim($task_info["task_time"]),
                'task_content' => trim($task_info["task_content"]),
                'role_id' => intval($task_info["role_id"]),
                'user_list' => trim($task_info["user_list"]),
            ];
            $update_task_detail = DB::table("task_detail")->where("id", $task_info['id'])->update($update_task_info);

            $code = "1014";
            $msg = "编辑任务失败";
            $return_info = compact('code', 'msg');

            if ($update_task_detail !== false) {
                $code = "1000";
                $msg = "编辑任务成功";
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
     * description:新增任务节点
     * editor:zongxing
     * type:POST
     * date : 2018.08.20
     * params: 1.任务日期:task_date;2.任务时刻:task_time;3.任务内容:task_content;4.角色id:role_id;4.任务管理员id:user_list;
     *         4.任务编号:task_sn;
     * return Object
     */
    public function addTaskDetail(Request $request)
    {
        if ($request->isMethod('post')) {
            $task_detail_info = $request->toArray();

            if (empty($task_detail_info['task_date'])) {
                return response()->json(['code' => '1002', 'msg' => '任务日期不能为空']);
            } elseif (empty($task_detail_info['task_time'])) {
                return response()->json(['code' => '1003', 'msg' => '任务时刻不能为空']);
            } elseif (empty($task_detail_info['task_content'])) {
                return response()->json(['code' => '1004', 'msg' => '任务内容不能为空']);
            } elseif (empty($task_detail_info['role_id'])) {
                return response()->json(['code' => '1005', 'msg' => '任务所属角色不能为空']);
            } elseif (empty($task_detail_info['user_list'])) {
                return response()->json(['code' => '1006', 'msg' => '任务管理员不能为空']);
            } elseif (empty($task_detail_info['task_sn'])) {
                return response()->json(['code' => '1007', 'msg' => '任务模板编号不能为空']);
            } elseif (strpos($task_detail_info['task_sn'], 'TK') === false) {
                return response()->json(['code' => '1009', 'msg' => '任务模板编号有误']);
            } elseif (strpos($task_detail_info['task_date'], 'T') === false && strpos($task_detail_info['task_date'], 'D') === false) {
                return response()->json(['code' => '1010', 'msg' => '任务日期有误']);
            }

            $insert_task_detail_info = [
                'task_date' => trim($task_detail_info["task_date"]),
                'task_time' => trim($task_detail_info["task_time"]),
                'task_content' => trim($task_detail_info["task_content"]),
                'role_id' => intval($task_detail_info["role_id"]),
                'user_list' => trim($task_detail_info["user_list"]),
            ];
            $task_detail_add_res = DB::table("task_detail")->insertGetId($insert_task_detail_info);

            $code = "1008";
            $msg = "任务添加失败";
            $return_info = compact('code', 'msg');

            if ($task_detail_add_res) {
                $task_detail_total_info = DB::table("task_detail")->where("task_sn", $task_detail_info['task_sn'])->get();
                $code = "1000";
                $msg = "任务添加成功";
                $data = $task_detail_total_info;
                $return_info = compact('code', 'msg', 'data');

                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_task',
                    'bus_desc' => '新增任务任务id：' . $task_detail_add_res,
                    'bus_value' => $task_detail_add_res,
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '任务模块-新增任务',
                    'module_id' => 4,
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
     * description:获取任务列表
     * editor:zongxing
     * type:GET
     * date : 2018.08.20
     * return Object
     */
    public function taskList(Request $request)
    {
        if ($request->isMethod('get')) {
            $task_info = $request->toArray();
            //获取任务列表
            $task_model = new TaskModel();
            $task_list_info = $task_model->get_task_list($task_info);

            if (empty($task_list_info["task_list"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无任务']);
            }

            $code = "1000";
            $msg = "获取任务列表成功";
            $data = $task_list_info["task_list"];
            $data_num = $task_list_info["total_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');

        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:更新采购期任务状态
     * editor:zongxing
     * type:GET
     * date : 2018.08.22
     * return Object
     */
    public function changeTaskStatus(Request $request)
    {
        if ($request->method("post")) {
            $batch_task_info = $request->toArray();
            $batch_task_id = $batch_task_info["id"];

            $findRes = DB::table("batch_task")->where("id", $batch_task_id)->get(["id", "task_date", "task_time", "delay_time"]);
            $findRes = json_decode(json_encode($findRes), true);

            if (empty($findRes)) {
                return response()->json(['code' => '1002', 'msg' => '暂无此任务']);
            }

            if ($batch_task_info["status"] != 1) {
                return response()->json(['code' => '1003', 'msg' => '任务状态有误']);
            }

            $task_date_time = $findRes[0]["task_date"] . ' ' . $findRes[0]["task_time"];
            $task_date_time = strtotime($task_date_time);
            $now_time = time();

            if ($now_time > $task_date_time) {
                $diff_seconds = $now_time - $task_date_time;
                $common_model = new CommonModel();
                $diff_time = $common_model->secToTime($diff_seconds);
                $batch_task_info["delay_time"] = $diff_time;
            }

            $updateRes = DB::table("batch_task")->where("id", $batch_task_id)->update($batch_task_info);

            $code = "1004";
            $msg = "更新任务状态失败";
            $return_info = compact('code', 'msg');

            if ($updateRes) {
                $code = "1000";
                $msg = "更新任务状态成功";
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
     * description:获取任务模板列表
     * editor:zongxing
     * type:GET
     * date : 2018.08.28
     * return Object
     */
    public function taskModelList(Request $request)
    {
        if ($request->isMethod('get')) {
            $task_info = $request->toArray();
            $task_model = new TaskModel();
            $task_list_info = $task_model->check_sys_set($task_info);
            if (empty($task_list_info["task_info"])) {
                return response()->json(['code' => '1002', 'msg' => '请先设置系统任务的时间']);
            }

            $code = "1000";
            $msg = "获取任务模板表成功";
            $data = $task_list_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:打开新增任务
     * editor:zongxing
     * type:GET
     * date : 2018.09.03
     * return Object
     */
    public function checkTaskModel(Request $request)
    {
        if ($request->isMethod('get')) {
            $task_info = $request->toArray();
            if (empty($task_info["task_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '模板参数有误']);
            }
            $task_model = new TaskModel();
            $task_info = $task_model->check_sys_set($task_info);
            if (empty($task_info["task_info"])) {
                return response()->json(['code' => '1002', 'msg' => '请先设置系统任务的时间']);
            }

            //获取管理员列表信息
            $role = new Role();
            $role_list_info = $role->get_role_list();
            $data["role_list"] = $role_list_info;

            //管理员列表信息
            $admin_user_list = DB::table("admin_user")->get(["id", "user_name", "role_id"]);
            $data["user_list"] = $admin_user_list;

            $code = "1000";
            $msg = "获取任务模板表成功";
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }




}