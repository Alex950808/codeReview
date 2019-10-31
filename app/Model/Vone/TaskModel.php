<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TaskModel extends Model
{
    protected $table = 'task as t';

    //可操作字段
    protected $field = ['t.id', 't.task_sn', 't.task_name'];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    /**
     * description:获取任务列表
     * editor:zongxing
     * date : 2018.08.21
     * return Object
     */
    public function get_task_list($task_info)
    {
        $start_page = isset($task_info['start_page']) ? intval($task_info['start_page']) : 1;
        $page_size = isset($task_info['page_size']) ? intval($task_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;

        if (isset($task_info['query_sn']) && !empty($task_info['query_sn'])) {
            $task_sn = trim($task_info['query_sn']);
            $task_list = DB::table("task")->orderBy("create_time", "desc")->where("task_sn", $task_sn)->get();
            $task_total = DB::table("task")->where("task_sn", $task_sn)->get();
        } else {
            $task_total = DB::table("task")->get();
            $task_list = DB::table("task")->orderBy("create_time", "desc")->skip($start_str)->take($page_size)->get();
        }

        $task_list = objectToArrayZ($task_list);
        $total_num = count($task_total);

        foreach ($task_list as $k => $v) {
            $task_detail_info = DB::table('task_detail')->where("task_sn", $v["task_sn"])->get();
            $task_detail_info = json_decode(json_encode($task_detail_info), true);

            if (empty($task_detail_info)) {
                $task_list[$k]["task_detail_info"] = [];
                continue;
            }
            $t_time = config('constants.MODIFY_T_DATE');
            $d_time = config('constants.MODIFY_D_DATE');
            foreach ($task_detail_info as $k1 => $v1) {
                //根据任务日期规格计算采购期任务日期
                $sort_num = intval($v1["sort_num"]);
                $task_detail_info[$k1]["sort_time"] = 9999999999999 + $sort_num;
                if (!empty($v1["task_date"])) {//第一次获取任务模板的时候，系统任务的时间是空的
                    $ping_str = str_split($v1["task_date"]);
                    $modify_str = $ping_str[1] . $ping_str[2] . " days";
                    if ($ping_str[0] == "T") {
                        $sort_day = Carbon::parse($t_time)->modify($modify_str)->toDateString();
                        $tmp_str = $sort_day . ' ' . $v1["task_time"];
                        $task_detail_info[$k1]["sort_time"] = strtotime($tmp_str);
                    } elseif ($ping_str[0] == "D") {
                        $sort_day = Carbon::parse($d_time)->modify($modify_str)->toDateString();
                        $tmp_str = $sort_day . ' ' . $v1["task_time"];
                        $task_detail_info[$k1]["sort_time"] = strtotime($tmp_str);
                    }
                }
            }

            $key_arrays = [];
            foreach ($task_detail_info as $val) {
                $key_arrays[] = $val['sort_time'];
            }
            array_multisort($key_arrays, SORT_ASC, SORT_NUMERIC, $task_detail_info);
            $task_list[$k]["task_detail_info"] = $task_detail_info;
        }

        $return_info["task_list"] = $task_list;
        $return_info["total_num"] = $total_num;
        return $return_info;
    }

    /**
     * description:获取任务模板信息
     * editor:zongxing
     * date : 2019.01.02
     * return Array
     */
    public function getTaskInfoById($task_id)
    {
        $task_info = DB::table('task')
            ->where('id', $task_id)
            ->first(['task_sn', 'task_name']);
        $task_info = objectToArrayZ($task_info);
        return $task_info;
    }

    /**
     * description:检查模板下的系统任务是否都设置了时间
     * editor:zongxing
     * date : 2018.09.03
     * return String
     */
    public function check_sys_set($task_info = [])
    {

        $sql_task = "SELECT id,task_sn,task_name FROM jms_task WHERE 1=1 ";
        $sql_task_detail = "SELECT task_sn,task_date FROM jms_task_detail WHERE task_date = '' ";

        if (isset($task_info["task_sn"])) {
            $task_sn = trim($task_info["task_sn"]);
            $sql_task .= "AND task_sn = '" . $task_sn . "' ";
            $sql_task_detail .= "AND task_sn = '" . $task_sn . "' ";
        }

        $task_list_info = DB::select(DB::raw($sql_task));
        $task_list_info = objectToArrayZ($task_list_info);

        $task_detail_info = DB::select(DB::raw($sql_task_detail));
        $task_detail_info = objectToArrayZ($task_detail_info);

        foreach ($task_list_info as $k => $v) {
            foreach ($task_detail_info as $k1 => $v1) {
                $task_sn = $v1["task_sn"];
                if (isset($task_list_info[$k]["task_sn"]) && $task_list_info[$k]["task_sn"] == $task_sn) {
                    unset($task_list_info[$k]);
                }
            }
        }
        $tmp_arr = [];
        foreach ($task_list_info as $k => $v) {
            array_push($tmp_arr, $v);
        }

        $return_info["task_info"] = $tmp_arr;
        return $return_info;
    }

    /**
     * description:获取已经设置任务时间的系统任务模板信息
     * editor:zongxing
     * date : 2019.05.09
     * return Array
     */
    public function getTaskList()
    {
        $field = $this->field;
        $task_list_info = DB::table($this->table)
            ->leftJoin('task_detail as td', 'td.task_sn', '=', 't.task_sn')
            ->where('td.task_date', '!=', '')->distinct()->get($field);
        $task_list_info = objectToArrayZ($task_list_info);
        return $task_list_info;
    }

}
