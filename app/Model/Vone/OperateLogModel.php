<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入时间处理包 add by zhangdong on the 2018.12.06
use Carbon\Carbon;

use JWTAuth;

class OperateLogModel extends Model
{
    protected $operate_log = 'operate_log';

    public $module = [
        'SHIPPING' => '1',//物流模块
        'PURCHASE' => '2',//采购模块
        'GOODS' => '3',//商品模块
        'TASK' => '4',//任务模块
        'PERMISSION' => '5',//权限模块
        'SALE' => '6',//销售模块
        'FINANCE' => '7',//财务模块
    ];

    //表名
    protected $table = 'operate_log as ol';
    //表字段名
    protected $field = [
        'ol.id','ol.log_id','ol.table_name','ol.bus_desc','ol.bus_value','ol.admin_name',
        'ol.admin_id','ol.ope_module_name','ol.module_id','ol.have_detail','ol.create_time',
    ];
    /**
     * description:写入日志
     * editor:zhangdong
     * date : 2018.07.10
     * return Boolean
     */
    public function insertLog(array $logData, $logDetailData = null)
    {
        $logData['log_id'] = $this->generalLogId();
        $insertRes = DB::table($this->operate_log)->insert($logData);

        if ($logDetailData) {
            $logDetailData['log_id'] = $logData['log_id'];
            $table_name = $logDetailData["table_name"];
            unset($logDetailData["table_name"]);
            $insertRes = DB::table($table_name)->insert($logDetailData);
        }
        return $insertRes;
    }

    /**
     * description:写入日志
     * editor:zongxing
     * date : 2018.07.17
     * return Boolean
     */
    public function insertMoreLog(array $logData, $logDetailData = null)
    {
        $logData['log_id'] = $this->generalLogId();
        $insertRes = DB::table($this->operate_log)->insert($logData);

        if ($logDetailData && $insertRes) {
            foreach ($logDetailData["update_info"] as $k => $v) {
                $logDetailData["update_info"][$k]['log_id'] = $logData['log_id'];
            }
            $table_name = $logDetailData["table_name"];
            $insertRes = DB::table($table_name)->insert($logDetailData["update_info"]);
        }
        return $insertRes;
    }

    /**
     * description:生成日志唯一标志
     * editor:zhangdong
     * date : 2018.07.10
     * return String
     */
    public function generalLogId()
    {
        $timestamp = time();
        do {
            $logId = 'LOG' . date('Ymd', $timestamp) . rand(10000, 99999);
            //检查日志单号是否已经存在
            $count = DB::table($this->operate_log)
                ->where([
                    ['log_id', '=', $logId]
                ])->count();
        } while ($count);
        return $logId;
    }

    /**
     * description:记录当前类系列日志
     * editor:zhangdong
     * date : 2019.03.23
     * return Boolean
     */
    public function recordLog($bus_desc,$bus_value,$ope_module_name,$module_id = 0,$have_detail = 0)
    {
        $loginInfo = JWTAuth::toUser()->original;
        $logData = [
            'bus_desc' => trim($bus_desc),
            'bus_value' => trim($bus_value),
            'admin_name' => trim($loginInfo['user_name']),
            'admin_id' => intval($loginInfo['id']),
            'ope_module_name' => trim($ope_module_name),
            'module_id' => $module_id,
            'have_detail' => $have_detail,
        ];
        //写入数据
        $recordRes = $this->insertLog($logData);
        return $recordRes;
    }

    /**
     * description:获取日志列表
     * author:zhangdong
     * date : 2019.03.27
     */
    public function queryLoggerList($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->orderBy('ol.create_time','desc')
            ->paginate($pageSize);
        return $queryRes;

    }

    /**
     * description:查询日志-组装查询条件
     * author:zhangdong
     * date:2019.03.27
     */
    protected function makeWhere($reqParams)
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
            ['ol.create_time', '>=', $start_time],
            ['ol.create_time', '<=', $end_time],
        ];
        //日志编号
        if (isset($reqParams['log_id'])) {
            $where[] = [
                'ol.log_id', trim($reqParams['log_id'])
            ];
        }
        //业务键值
        if (isset($reqParams['bus_value'])) {
            $where[] = [
                'ol.bus_value', 'LIKE', '%' .trim($reqParams['bus_value']) . '%'
            ];
        }
        //模块名称
        if (isset($reqParams['ope_module_name'])) {
            $where[] = [
                'ol.ope_module_name', 'LIKE', '%' .trim($reqParams['ope_module_name']) . '%'
            ];
        }
        //操作人
        if (isset($reqParams['admin_name'])) {
            $where[] = [
                'ol.admin_name', 'LIKE', '%' .trim($reqParams['admin_name']) . '%'
            ];
        }

        return $where;
    }//end of function

    /**
     * description:获取日志信息
     * author:zhangdong
     * date : 2019.03.27
     */
    public function queryLoggerInfo($log_id)
    {
        //组装查询条件
        $where = [
            ['ol.log_id', $log_id],
        ];
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->first();
        return $queryRes;

    }



}//end of class
