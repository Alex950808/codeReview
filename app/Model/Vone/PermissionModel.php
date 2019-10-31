<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;

class PermissionModel extends Model
{
    public $table = 'permissions as p';
    private $field = [
        'p.id','p.name','p.web_name','p.display_name','p.description','p.rank',
        'p.parent_id','p.status','p.sort_num',
    ];

    /**
     * @description:从redis中获取权限信息
     * @editor:张冬
     * @date : 2019.04.02
     */
    public function getPermissionInfoInRedis()
    {
        //从redis中获取权限信息，如果没有则对其设置
        $permissionInfo = Redis::get('permissionInfo');
        if (empty($permissionInfo)) {
            $permissionInfo = DB::table($this->table) -> select($this->field)-> get()
                -> map(function ($value){
                    return (array) $value;
                }) -> toArray();
            Redis::set('permissionInfo', json_encode($permissionInfo, JSON_UNESCAPED_UNICODE));
            $permissionInfo = Redis::get('permissionInfo');
        }
        $permissionInfo = objectToArray(json_decode($permissionInfo));
        return $permissionInfo;

    }






}//end of class
