<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class AuditModel extends Model
{
    public $table = 'audit as a';
    private $field = [
        'a.id','a.audit_sn','a.api_id','a.config_sn','a.status',
        'a.audit_order','a.is_audit','a.create_time',
    ];

    //审核单是否需要审核
    public $is_audit = [
        '0' => '不需要',
        '1' => '需要',
    ];

    //审核状态 0 未开始 1，审核通过 2，审核未通过
    public $status = [
        'NO_START' => 0,//未开始
        'AUDIT_PASS' => 1,//审核通过
        'AUDIT_NO_PASS' => 2,//审核未通过
    ];

    public $status_desc = [
        '0' => '未开始',
        '1' => '审核通过',
        '2' => '审核未通过',
    ];

    /**
     * @description:保存品牌折扣数据
     * @author：zhangdong
     * @date : 2019.04.02
     * @param $res(表格上传数据)
     * @return array
     */
    public function saveBrandDiscountData($disAuditData)
    {
        //组装审核主表数据
        $auditSaveData = $this->createSaveData();
        //组装品牌折扣详情表数据
        $daModel = new DiscountAuditModel();
        $audit_sn = $auditSaveData['audit_sn'];
        $brandSaveData = $daModel->createArrSaveData($audit_sn, $disAuditData);
        //写入audit和discount_audit表
        $saveRes = $this->saveDiscountAuditData($auditSaveData, $brandSaveData);
        return [
            'saveRes' => $saveRes,
            'audit_sn' => $audit_sn,
        ];

    }


    /**
     * description:组装审核主表数据
     * author：zhangdong
     * date : 2019.04.02
     */
    public function createSaveData()
    {
        //审核单号
        $audit_sn = $this->generalAuditSn();
        //接口英文名称
        $apiName = getApiName();
        //获取中文接口名称
        $permissioinModel = new PermissionModel();
        $redisPermisInfo = $permissioinModel->getPermissionInfoInRedis();
        $searchApiName = searchTwoArray($redisPermisInfo, $apiName, 'name');
        $api_id = isset($searchApiName[0]['id']) ? $searchApiName[0]['id'] : 0;
        //获取接口对应的默认配置序列号
        $aacModel = new ApiAuditConfigModel();
        $apiAuditConfig = $aacModel->getApiAuditConfigInRedis();
        $searchAudit = searchTwoArray($apiAuditConfig, $apiName, 'api_en_name');
        $config_sn = isset($searchAudit[0]['config_sn']) ? $searchAudit[0]['config_sn'] : '';
        $saveData = [
            'audit_sn' => $audit_sn,
            'api_id' => $api_id,
            'api_en_name' => $apiName,
            'config_sn' => $config_sn
        ];
        return $saveData;
    }


    /**
     * description:生成审核单号唯一标志
     * author:zhangdong
     * date : 2019.04.02
     * return String
     */
    private function generalAuditSn()
    {
        $timestamp = time();
        do {
            $audit_sn = 'AU' . date('Ymd', $timestamp) . rand(10000, 99999);
            //检查审核单号是否已经存在
            $count = DB::table($this->table)
                ->where([
                    ['audit_sn', '=', $audit_sn]
                ])->count();
        } while ($count);
        return $audit_sn;
    }

    /**
     * description:保存品牌折扣上传数据
     * editor:zhangdong
     * date : 2019.04.03
     * return Boolean
     */
    public function saveDiscountAuditData(array $auditSaveData = [], array $discountAudit = [])
    {
        if (count($auditSaveData) <= 0 || count($discountAudit) <= 0 ) {
            return false;
        }
        $saveRes = DB::transaction(function () use ($auditSaveData, $discountAudit) {
            //audit和discount_audit两张表有外键关系，所以必须先写audit表后写discount_audit表
            //去除表别名（插入数据时不允许有别名）
            $audit = cutString($this->table, 0, 'as');
            DB::table($audit)->insert($auditSaveData);
            $daModel = new DiscountAuditModel();
            //订单商品数据保存
            $discount_audit = cutString($daModel->table, 0, 'as');
            $insertRes = DB::table($discount_audit)->insert($discountAudit);
            return $insertRes;
        });
        return $saveRes;
    }

    /**
     * description:获取审核单信息
     * editor:zhangdong
     * date : 2019.04.03
     */
    public function getAuditInfo($audit_sn)
    {
        $where = [
            ['audit_sn',$audit_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * @description:更新审核单数据
     * @author:zhangdong
     * @date : 2019.04.03
     * @param $audit_sn (审核单号)
     * @param $isPass (是否通过 1 通过 2 未通过)
     */
    public function updateAuditData($audit_sn, $isPass, $nextAuditOrder)
    {
        $where = [
            ['audit_sn', $audit_sn],
        ];
        $update = [
            'status' => intval($isPass),
            'audit_order' => intval($nextAuditOrder),
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /*
     * @description:检查审核单是否已经全部审核通过
     * @author:zhangdong
     * @date:2019.04.03
     * @param $audit_sn 审核单号
     */
    public function checkAuditIsPass($auditInfo)
    {
        //根据配置序列号查询最后一个审核进度
        $config_sn = trim($auditInfo->config_sn);
        $acModel = new AuditConfigModel();
        $lastAuditNum = $acModel->getLastAuditNum($config_sn);
        //当前审核进度
        $curAuditNum = intval($auditInfo->audit_order);
        //当前审核状态
        $status = intval($auditInfo->status);
        //如果当前审核进度和最后一个审核进度相等且状态为审核通过则视为审核完成
        if ($curAuditNum == $lastAuditNum && $status == $this->status['AUDIT_PASS']) {
            return true;
        }
        return false;

    }

    /*
     * @description:查询审核列表
     * @author:zhangdong
     * @date:2019.04.08
     */
    public function queryAuditList($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $this->field = array_merge($this->field,['p.display_name']);
        $queryRes = DB::table($this->table)->select($this->field)
            ->leftJoin('permissions as p', 'p.id', 'a.api_id')
            ->where($where)->orderBy('a.create_time','desc')
            ->paginate($pageSize);
        return $queryRes;
    }

    /**
     * description:查询审核列表-组装查询条件
     * author:zhangdong
     * date:2019.04.08
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
            ['a.create_time', '>=', $start_time],
            ['a.create_time', '<=', $end_time],
        ];
        //审核单号
        if (isset($reqParams['audit_sn'])) {
            $where[] = [
                'a.audit_sn', trim($reqParams['audit_sn'])
            ];
        }
        //配置序列号
        if (isset($reqParams['config_sn'])) {
            $where[] = [
                'a.config_sn', trim($reqParams['config_sn'])
            ];
        }
        //是否需要审核 0 不需要 1 需要
        if (isset($reqParams['is_audit'])) {
            $where[] = [
                'a.is_audit', intval($reqParams['is_audit'])
            ];
        }
        //审核状态 0 未开始 1，审核通过 2，审核未通过
        if (isset($reqParams['status'])) {
            $where[] = [
                'a.status', intval($reqParams['status'])
            ];
        }

        return $where;
    }//end of function














}//end of class
