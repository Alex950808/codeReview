<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class DepartmentModel extends Model
{
    public $table = 'department as d';
    public $field = ['d.department_id', 'd.de_name'];

    /**
     * @description:获取部门信息
     * @editor:张冬
     * @date : 2018.10.15
     * @return array
     */
    public function getDepartmentInfo($department_id = '')
    {
        $department_id = intval($department_id);
        $where = $department_id > 0 ? [['department_id', $department_id]] : [];
        $field = ['department_id', 'de_name'];
        $departmentInfo = DB::table('department')->select($field)
            ->where($where)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $departmentInfo;
    }


    /**
     * @description:从redis中获取部门信息
     * @editor:张冬
     * @date : 2019.02.28
     * @return array
     */
    public function getDepartmentInfoInRedis()
    {
        //从redis中获取部门信息，如果没有则对其设置
        $departmentInfo = Redis::get('departmentInfo');
        if (empty($departmentInfo)) {
            $field = ['department_id', 'de_name'];
            $departmentInfo = DB::table('department')->select($field)->get()
                ->map(function ($value) {
                    return (array)$value;
                })->toArray();
            Redis::set('departmentInfo', json_encode($departmentInfo, JSON_UNESCAPED_UNICODE));
            $departmentInfo = Redis::get('departmentInfo');
        }
        $departmentInfo = objectToArray(json_decode($departmentInfo));
        return $departmentInfo;

    }

    /**
     * description:获取部门列表
     * editor:zongxing
     * date : 2019.05.20
     * return Object
     */
    public function departInfoList()
    {
        $depart_list = DB::table($this->table)->pluck('de_name', 'department_id');
        $depart_list = objectToArrayZ($depart_list);
        return $depart_list;
    }


}//end of class
