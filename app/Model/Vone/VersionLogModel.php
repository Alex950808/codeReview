<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class VersionLogModel extends Model
{
    public $table = 'version_log as vl';
    private $field = [
        'vl.log_id','vl.serial_num','vl.content','vl.create_time',
    ];

    /**
     * description:获取版本日志列表
     * author:zhangdong
     * date : 2019.06.20
     */
    public function queryVersionLogList($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->orderBy('vl.create_time','desc')
            ->paginate($pageSize);
        return $queryRes;

    }

    /**
     * description:新增版本日志记录
     * author:zhangdong
     * date : 2019.06.20
     */
    public function addVersionLog($reqParams)
    {
        $serialNum = trim($reqParams['serial_num']);
        $content = trim($reqParams['content']);
        $addData = [
            'serial_num' => $serialNum,
            'content' => $content,
        ];
        $table = getTableName($this->table);
        $addRes = DB::table($table)->insert($addData);
        return $addRes;
    }

    /**
     * description 根据版本号统计版本条数
     * author zhangdong
     * date 2019.06.20
     */
    public function countVersionNum($serialNum)
    {
        $where = [
            ['serial_num', $serialNum],
        ];
        $countNum = DB::table($this->table)->where($where)->count();
        return $countNum;

    }

    /**
     * description 根据logId统计条数
     * author zhangdong
     * date 2019.06.21
     */
    public function countLogId($logId)
    {
        $where = [
            ['log_id', $logId],
        ];
        $countNum = DB::table($this->table)->where($where)->count();
        return $countNum;
    }

    /**
     * description 根据logId统计条数
     * author zhangdong
     * date 2019.06.21
     */
    public function editData($logId, $reqParams)
    {
        $serialNum = trim($reqParams['serial_num']);
        $content = trim($reqParams['content']);
        $where = [
            ['log_id', $logId],
        ];
        $update = [
            'serial_num' => $serialNum,
            'content' => $content,
        ];
        $editRes = DB::table($this->table)->where($where)->update($update);
        return $editRes;
    }

    //--------------------------------------------私有函数区--------------------------------------------

    /**
     * description:查询版本日志-组装查询条件
     * author:zhangdong
     * date:2019.06.20
     */
    private function makeWhere($reqParams)
    {
        //时间处理-查询日志列表时默认只查近三个月的
        //开始时间
        $start_time = Carbon::now()->addMonth(-3)->toDateTimeString();
        if (isset($reqParams['start_time'])) {
            $start_time = trim($reqParams['start_time']);
        }
        //结束时间
        $end_time = Carbon::now()->toDateTimeString();
        if (isset($reqParams['end_time'])) {
            $end_time = trim($reqParams['end_time']);
        }
        //时间筛选
        $where = [
            ['vl.create_time', '>=', $start_time],
            ['vl.create_time', '<=', $end_time],
        ];
        //版本编号
        if (isset($reqParams['serial_num'])) {
            $where[] = [
                'vl.serial_num', trim($reqParams['serial_num'])
            ];
        }
        //版本内容
        if (isset($reqParams['content'])) {
            $where[] = [
                'vl.content', 'LIKE', '%' .trim($reqParams['content']) . '%'
            ];
        }

        return $where;
    }//end of function





}//end of class
