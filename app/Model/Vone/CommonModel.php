<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommonModel extends Model
{
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

    /**
     * description:计算编号
     * editor:zongxing
     * date : 2018.06.26
     * params: 1.模型对象:$model_obj;2.需要更新的字段名:$model_field;3,拼接字符串头:$pin_head;4.是否带年月日:$status;
     * return Object
     */
    public function createNo($model_obj, $model_field, $pin_head, $status = true)
    {
        $last_purchase_info = $model_obj->orderBy('create_time', 'desc')->first();
        $last_purchase_sn = $last_purchase_info["attributes"][$model_field];

        $pin_str = '001';
        if ($last_purchase_sn) {
            $last_three_str = substr($last_purchase_sn, '-3');
            $last_three_str_int = intval($last_three_str);
            $pin_int = $last_three_str_int + 1;
            if ($pin_int >= 100) {
                $pin_str = $pin_int;
            } else if ($pin_int >= 10) {
                $pin_str = '0' . $pin_int;
            } else {
                $pin_str = '00' . $pin_int;
            }
        }

        $now_date = '';
        if ($status) {
            $now_date = str_replace('-', '', date('Y-m-d', time()));
        }

        $return_sn = $pin_head . $now_date . $pin_str;
        return $return_sn;
    }

    /**
     * description:根据时间计算编号
     * editor:zongxing
     * date : 2018.07.05
     * params: 1.模型对象:$model_obj;2.需要更新的字段名:$model_field;3,拼接字符串头:$pin_head;4.是否检查日期:$status;
     * return Object
     */
    public function createNoByTime($model_obj, $model_field, $pin_head, $status = true)
    {
        $last_purchase_info = $model_obj->orderBy('create_time', 'desc')->first();

        if (empty($last_purchase_info)) {
            $pin_str = '001';
        } else {
            if ($status) {
                $last_purchase_sn = $last_purchase_info["attributes"][$model_field];
                //最后一个时间
                $last_create_time = $last_purchase_info["attributes"]["create_time"];

                $last_day = substr($last_create_time, 0, 10);
                $now_day = date("Y-m-d", time());

                if ($last_day == $now_day) {
                    $pin_str = '001';
                    if ($last_purchase_sn) {
                        $last_three_str = substr($last_purchase_sn, '-3');
                        $last_three_str_int = intval($last_three_str);
                        $pin_int = $last_three_str_int + 1;
                        if ($pin_int >= 100) {
                            $pin_str = $pin_int;
                        } else if ($pin_int >= 10) {
                            $pin_str = '0' . $pin_int;
                        } else {
                            $pin_str = '00' . $pin_int;
                        }
                    }
                } else {
                    $pin_str = '001';
                }
            }
        }

        $return_sn = $pin_head . $pin_str;
        return $return_sn;
    }


    /**
     * description：过滤参数
     * author：zongxing
     * date：2018.08.24
     */
    public function strFilter($str)
    {
        $str = str_replace('`', '', $str);
        $str = str_replace('·', '', $str);
        $str = str_replace('~', '', $str);
        $str = str_replace('!', '', $str);
        $str = str_replace('！', '', $str);
        //$str = str_replace('@', '', $str);
        $str = str_replace('#', '', $str);
        $str = str_replace('$', '', $str);
        $str = str_replace('￥', '', $str);
        $str = str_replace('%', '', $str);
        $str = str_replace('^', '', $str);
        $str = str_replace('……', '', $str);
        $str = str_replace('&', '', $str);
        $str = str_replace('*', '', $str);
        $str = str_replace('(', '', $str);
        $str = str_replace(')', '', $str);
        $str = str_replace('（', '', $str);
        $str = str_replace('）', '', $str);
//        $str = str_replace('-', '', $str);
//        $str = str_replace('_', '', $str);
        $str = str_replace('——', '', $str);
        $str = str_replace('+', '', $str);
        $str = str_replace('=', '', $str);
        $str = str_replace('|', '', $str);
        $str = str_replace('\\', '', $str);
        $str = str_replace('[', '', $str);
        $str = str_replace(']', '', $str);
        $str = str_replace('【', '', $str);
        $str = str_replace('】', '', $str);
        $str = str_replace('{', '', $str);
        $str = str_replace('}', '', $str);
        $str = str_replace(';', '', $str);
        $str = str_replace('；', '', $str);
        $str = str_replace(':', '', $str);
        $str = str_replace('：', '', $str);
        $str = str_replace('\'', '', $str);
        $str = str_replace('"', '', $str);
        $str = str_replace('“', '', $str);
        $str = str_replace('”', '', $str);
        $str = str_replace(',', '', $str);
        $str = str_replace('，', '', $str);
        $str = str_replace('<', '', $str);
        $str = str_replace('>', '', $str);
        $str = str_replace('《', '', $str);
        $str = str_replace('》', '', $str);
        $str = str_replace('.', '', $str);
        $str = str_replace('。', '', $str);
        $str = str_replace('/', '', $str);
        $str = str_replace('、', '', $str);
        $str = str_replace('?', '', $str);
        $str = str_replace('？', '', $str);
        $str = str_replace(["\r\n", "\r", "\n"], '', $str);
        return trim($str);
    }

    /**
     * description:把秒数转换为时分秒的格式
     * editor:zongxing
     * date : 2018.08.29
     * return String
     */
    public function secToTime($times)
    {
        $result = '00:00:00';
        if ($times > 0) {
            $hour = floor($times / 3600);
            $minute = floor(($times - 3600 * $hour) / 60);
            $second = floor((($times - 3600 * $hour) - 60 * $minute) % 60);
            $result = $hour . ':' . $minute . ':' . $second;
        }
        return $result;
    }

    /**
     * description:更新系统任务状态
     * editor:zongxing
     * date : 2018.09.03
     * params: 1.系统任务代码:$str;
     * return String
     */
    public function updateSysTaskStatus($real_purchase_sn, $user_id, $str)
    {
        $batch_task_info = DB::table("batch_task")
            ->where("real_purchase_sn", $real_purchase_sn)
            ->where("task_link", $str)
            ->where("status", 0)
            ->first(["id", "task_date", "task_time", "user_list"]);
        $batch_task_info = objectToArrayZ($batch_task_info);

        if (!empty($batch_task_info)) {
            $user_list = explode(",", $batch_task_info["user_list"]);
            if (in_array($user_id, $user_list)) {
                $task_date_time = $batch_task_info["task_date"] . ' ' . $batch_task_info["task_time"];
                $task_date_time = strtotime($task_date_time);
                $now_time = time();

                if ($now_time > $task_date_time) {
                    $diff_seconds = $now_time - $task_date_time;
                    $diff_time = $this->secToTime($diff_seconds);
                    $update_task_info["delay_time"] = $diff_time;
                }
                $update_task_info["status"] = 1;
                DB::table("batch_task")->where("id", $batch_task_info["id"])->update($update_task_info);
            }
        }
    }


}
