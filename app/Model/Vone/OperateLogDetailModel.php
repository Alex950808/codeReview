<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OperateLogDetailModel extends Model
{
    protected $table = 'operate_log_detail as old';

    protected $field = [
        'old.detail_id','old.log_id','old.table_field_name','old.field_old_value',
        'old.field_new_value','old.create_time'
    ];

    /**
     * description:通过日志编码获取日志详情
     * author:zhangdong
     * date : 2019.03.27
     */
    public function getLogDetailByLid(array $log_id = [])
    {
        $queryRes = DB::table($this->table)->select($this->field)
            ->whereIn('log_id', $log_id)->get();
        return $queryRes;
    }

    /**
     * description:通过日志编码获取日志详情
     * author:zhangdong
     * date : 2019.03.27
     */
    public function getLogDetail($log_id)
    {
        $where = [
            ['old.log_id', $log_id],
        ];
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->get();
        return $queryRes;
    }

}//end of class
