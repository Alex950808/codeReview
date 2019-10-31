<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TaskDetailModel extends Model
{
    protected $table = 'task_detail';

    //可操作字段
    protected $fillable = ['task_sn', 'task_date', 'task_time', 'task_content', 'role_id', 'user_list'];

    //修改laravel 自动更新
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'modify_time';


    /**
     * description:生成系统任务
     * editor:zongxing
     * date : 2018.08.31
     * return object
     */
    public function addSysTask($task_sn){
        $sys_task = [];
        $tmp_arr1["task_sn"] = $task_sn;
        $tmp_arr1["task_content"] = "物流部清点";
        $tmp_arr1["is_system"] = 1;
        $tmp_arr1["sort_num"] = 1;
        $tmp_arr1["task_link"] = "pendingPurchaseOrder";
        array_push($sys_task,$tmp_arr1);

        $tmp_arr2["task_sn"] = $task_sn;
        $tmp_arr2["task_content"] = "采购部确认差异";
        $tmp_arr2["is_system"] = 1;
        $tmp_arr2["sort_num"] = 2;
        $tmp_arr2["task_link"] = "confirmDifference";
        array_push($sys_task,$tmp_arr2);

        $tmp_arr3["task_sn"] = $task_sn;
        $tmp_arr3["task_content"] = "采购部开单";
        $tmp_arr3["is_system"] = 1;
        $tmp_arr3["sort_num"] = 3;
        $tmp_arr3["task_link"] = "stayOpenBill";
        array_push($sys_task,$tmp_arr3);

        $tmp_arr4["task_sn"] = $task_sn;
        $tmp_arr4["task_content"] = "物流部入库";
        $tmp_arr4["is_system"] = 1;
        $tmp_arr4["sort_num"] = 4;
        $tmp_arr4["task_link"] = "purchaseOrder";
        array_push($sys_task,$tmp_arr4);

        $tmp_arr4["task_sn"] = $task_sn;
        $tmp_arr4["task_content"] = "财务核价";
        $tmp_arr4["is_system"] = 1;
        $tmp_arr4["sort_num"] = 5;
        $tmp_arr4["task_link"] = "alreadyPricing";
        array_push($sys_task,$tmp_arr4);

        $return_info = DB::table("task_detail")->insert($sys_task);
        return $return_info;
    }

    /**
     * description:检查系统任务的时间设置
     * editor:zongxing
     * date : 2018.09.03
     * return object
     */
    public function check_sys_info($task_info){
        $sort_num = intval($task_info['sort_num']);
        $task_sn = trim($task_info['task_sn']);

        $sort_prev = $sort_num - 1;
        if($sort_num == 1){
            $sort_prev = $sort_num;
        }

        $prev_task = DB::table("task_detail")
            ->where("sort_num",$sort_prev)->where("task_sn",$task_sn)
            ->first(["task_date","task_time"]);
        $prev_task = objectToArrayZ($prev_task);

        if($sort_num != 1 && empty($prev_task["task_date"])){
            $return_info = ['code' => '1011', 'msg' => '请先设置上一步的时间'];
            return $return_info;
        }

        $t_time = config('constants.MODIFY_T_DATE');
        $d_time = config('constants.MODIFY_D_DATE');

        $now_str = str_split($task_info["task_date"]);
        $modify_now = $now_str[1] . $now_str[2] . " days";
        if ($now_str[0] == "T") {
            $now_day = Carbon::parse($t_time)->modify($modify_now)->toDateString();
            $tmp_now_str = $now_day.' '.$task_info["task_time"];
            $now_time = strtotime($tmp_now_str);
        }elseif ($now_str[0] == "D"){
            $now_day = Carbon::parse($d_time)->modify($modify_now)->toDateString();
            $tmp_now_str = $now_day.' '.$task_info["task_time"];
            $now_time = strtotime($tmp_now_str);
        }

        if(!empty($next_task["task_date"])){
            $prev_str = str_split($prev_task["task_date"]);
            $modify_prev = $prev_str[1] . $prev_str[2] . " days";
            if ($prev_str[0] == "T") {
                $prev_day = Carbon::parse($t_time)->modify($modify_prev)->toDateString();
                $tmp_prev_str = $prev_day.' '.$prev_task["task_time"];
                $prev_time = strtotime($tmp_prev_str);
            }elseif ($prev_str[0] == "D"){
                $prev_day = Carbon::parse($d_time)->modify($modify_prev)->toDateString();
                $tmp_prev_str = $prev_day.' '.$prev_task["task_time"];
                $prev_time = strtotime($tmp_prev_str);
            }

            if($prev_time >= $now_time){
                $return_info = ['code' => '1012', 'msg' => '设置的时间必须大于上一步时间'];
                return $return_info;
            }
        }

        $sort_next = $sort_num + 1;
        $next_task = DB::table("task_detail")
            ->where("sort_num",$sort_next)->where("task_sn",$task_sn)
            ->first(["task_date","task_time"]);
        $next_task = json_decode(json_encode($next_task),true);

        if(!empty($next_task["task_date"])){
            $next_str = str_split($next_task["task_date"]);
            $modify_next = $next_str[1] . $next_str[2] . " days";
            if ($next_str[0] == "T") {
                $next_day = Carbon::parse($t_time)->modify($modify_next)->toDateString();
                $tmp_next_str = $next_day.' '.$next_task["task_time"];
                $next_time = strtotime($tmp_next_str);
            }elseif ($next_str[0] == "D"){
                $next_day = Carbon::parse($d_time)->modify($modify_next)->toDateString();
                $tmp_next_str = $next_day.' '.$next_task["task_time"];
                $next_time = strtotime($tmp_next_str);
            }

            if($next_time <= $now_time){
                $return_info = ['code' => '1013', 'msg' => '设置的时间必须小于下一步时间'];
                return $return_info;
            }
        }
        return true;
    }



}
