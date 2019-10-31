<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;

class ApiAuditConfigModel extends Model
{
    public $table = 'api_audit_config as aac';
    private $field = [
        'aac.id','aac.config_sn','aac.api_en_name','aac.create_time'
    ];

    /**
     * @description:从redis中获取接口对应的默认配置序列号
     * @editor:张冬
     * @date : 2019.04.02
     */
    public function getApiAuditConfigInRedis()
    {
        //从redis中获取接口对应的默认配置序列号信息，如果没有则对其设置
        $apiAuditConfig = Redis::get('apiAuditConfig');
        if (empty($apiAuditConfig)) {
            $apiAuditConfig = DB::table($this->table) -> select($this->field)-> get()
                -> map(function ($value){
                    return (array) $value;
                }) -> toArray();
            Redis::set('apiAuditConfig', json_encode($apiAuditConfig, JSON_UNESCAPED_UNICODE));
            $apiAuditConfig = Redis::get('apiAuditConfig');
        }
        $apiAuditConfig = objectToArray(json_decode($apiAuditConfig));
        return $apiAuditConfig;

    }






}//end of class
