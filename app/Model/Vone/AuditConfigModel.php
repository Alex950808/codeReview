<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;
use JWTAuth;

class AuditConfigModel extends Model
{
    public $table = 'audit_config as ac';
    private $field = [
        'ac.id','ac.config_sn','ac.admin_id',
        'ac.audit_order','ac.create_time',
    ];

    /**
     * @description:从redis中获取审核配置信息
     * @editor:张冬
     * @date : 2019.04.03
     */
    public function getAuditConfigInRedis()
    {
        $auditConfigInfo = Redis::get('auditConfigInfo');
        if (empty($auditConfigInfo)) {
            $auditConfigInfo = DB::table($this->table)->select($this->field)->get()
                -> map(function ($value){
                    return (array) $value;
                }) -> toArray();
            Redis::set('auditConfigInfo', json_encode($auditConfigInfo, JSON_UNESCAPED_UNICODE));
            $auditConfigInfo = Redis::get('auditConfigInfo');
        }
        $auditConfigInfo = objectToArray(json_decode($auditConfigInfo));
        return $auditConfigInfo;
    }

    /**
     * @description:查询审核配置信息
     * @editor:张冬
     * @date:2019.04.03
     */
    public function getAuditConfig($config_sn, $adminId)
    {
        $where = [
            ['config_sn', $config_sn],
            ['admin_id', $adminId],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /*
     * @description:检查当前操作人是否有权限操作
     * @editor:张冬
     * @date:2019.04.03
     * @param $config_sn (配置序列号)
     * @param $curAuditOrder (当前的审核进度)
     */
    public function checkHaveRight($config_sn, $curAuditOrder)
    {
        $loginInfo = JWTAuth::toUser()->original;
        $adminId = intval($loginInfo['id']);

        $auditConfigInfo = $this->getAuditConfig($config_sn, $adminId);
        if (is_null($auditConfigInfo)) {
            return false;
        }
        //当前审核进度
        $curAuditOrder = intval($curAuditOrder);
        //需要审核的下一个进度
        $nextAuditOrder = intval($auditConfigInfo->audit_order);
        //两个进度如果不满足条件则阻止操作
        if ($nextAuditOrder - 1 != $curAuditOrder) {
            return false;
        }
        return $nextAuditOrder;

    }

    /*
     * @description:根据配置序列号查询最后一个审核进度
     * @editor:张冬
     * @date:2019.04.03
     * @param $config_sn (配置序列号)
     */
    public function getLastAuditNum($config_sn)
    {
        $where = [
            ['config_sn', $config_sn],
        ];
        $field = ['audit_order'];
        $queryRes = DB::table($this->table)->select($field)->where($where)
            ->orderBy('audit_order', 'DESC')->first();
        $lastAuditNum = intval($queryRes->audit_order);
        return $lastAuditNum;
    }

    /*
     * @description:获取当前审核人
     * @editor:zhangdong
     * @date:2019.04.08
     * @param $config_sn (配置序列号)
     * @param $audit_order (审核进度)
     */
    public function getCurrentAuditor($config_sn, $audit_order)
    {
        $audit_order = $audit_order + 1;
        $where = [
            ['ac.config_sn', $config_sn],
            ['ac.audit_order', $audit_order],
        ];
        $field = ['au.nickname', 'au.id'];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('admin_user as au', 'au.id', 'ac.admin_id')
            ->where($where)->first();
        return $queryRes;

    }

    /*
     * @description:判断当前用户是否可以提交数据
     * @editor:zhangdong
     * @date:2019.04.08
     * @param $config_sn (配置序列号)
     * @param $audit_order (审核进度)
     */
    public function checkCanAudit($config_sn)
    {
        $queryRes = $this->getLastAuditInfo($config_sn);
        //最后一个进度对应的管理员id和当前登录的管理员id相等才能进行提交数据
        $lastAuditUid = isset($queryRes->admin_id) ? intval($queryRes->admin_id) : 0;
        $loginInfo = JWTAuth::toUser()->original;
        $curAdminId = intval($loginInfo['id']);
        if ($curAdminId === $lastAuditUid) {
            return 1;
        }
        return 0;
    }

    /*
     * @description:根据配置序列号查询最后一个审核进度信息
     * @editor:张冬
     * @date:2019.04.11
     * @param $config_sn (配置序列号)
     */
    public function getLastAuditInfo($config_sn)
    {
        $where = [
            ['config_sn', $config_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)
            ->orderBy('audit_order', 'DESC')->first();
        return $queryRes;
    }








}//end of class
