<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入redis
use Illuminate\Support\Facades\Redis;

class PurchaseMethodModel extends Model
{
    protected $table = "purchase_method";

    //可操作字段
    protected $fillable = ["method_sn", "method_name", "method_weight", 'method_property'];

    //修改laravel 自动更新
    const UPDATED_AT = "modify_time";
    const CREATED_AT = "create_time";

    //采购方式
    public $purMethodInfo;

    /**
     * description:本模型构造函数-实例化的时候将必要信息初始化
     * author:zhangdong
     * date : 2018.11.19
     * return Object
     */
    public function __construct()
    {
        //从redis中获取采购方式信息，如果没有则对其设置
        $purMethodInfo = Redis::get('purMethodInfo');
        if (empty($purMethodInfo)) {
            $purMethodInfo = $this->getPurMethodInfo();
            Redis::set('purMethodInfo', json_encode($purMethodInfo));
            $purMethodInfo = Redis::get('purMethodInfo');
        }
        $this->purMethodInfo = objectToArray(json_decode($purMethodInfo));
    }

    /**
     * description:获取采购方式信息
     * author:zhangdong
     * date : 2018.11.19
     * return Object
     */
    public function getPurMethodInfo($params = [])
    {
        $field = ['id', 'method_sn', 'method_name', 'method_weight', 'method_property'];
        $where = [];
        if (!empty($params['method_sn'])) {
            $where[] = ['method_sn', trim($params['method_sn'])];
        }
        if (!empty($params['method_name'])) {
            $where[] = ['method_name', trim($params['method_name'])];
        }
        if (!empty($params['method_weight'])) {
            $where[] = ['method_weight', trim($params['method_weight'])];
        }
        if (!empty($params['method_property'])) {
            $where[] = ['method_property', trim($params['method_property'])];
        }
        $purMethodInfo = DB::table('purchase_method')->select($field)->where($where)
            ->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $purMethodInfo;
    }

    /**
     * description:获取采购方式id及对应的方式名数组信息
     * author:zongxing
     * date : 2018.12.26
     * return Array
     */
    public function getMethodList($where_option = [])
    {
        $method_total_obj = DB::table('purchase_method');
        if (!empty($where_option)) {
            $method_total_obj->whereIn('id', $where_option);
        }
        $method_total_info = $method_total_obj->get(['id', 'method_name', 'method_sn']);
        $method_list = objectToArrayZ($method_total_info);
        $method_list_total = [];
        foreach ($method_list as $k => $v) {
            $method_name = $v['method_name'];
            $method_list_total[$method_name] = $v;
        }
        return $method_list_total;
    }

    /**
     * description:获取采购方式id及对应的方式名数组信息
     * author:zongxing
     * date : 2018.12.26
     * return Array
     */
    public function getTotalMethodList()
    {
        $method_list = DB::table($this->table)->orderBy('create_time', 'desc')->get();
        $method_list = objectToArrayZ($method_list);
        return $method_list;
    }

    /**
     * description:检查上传时采购期方式
     * author:zongxing
     * date : 2018.12.28
     * return Array
     */
    public function checkUploadPurchaseMethod($param_info)
    {
        $method_id = intval($param_info['method_id']);
        $method_info = DB::table('purchase_method')->where('id', $method_id)->first(['method_sn', 'method_name']);
        $method_info = objectToArrayZ($method_info);
        return $method_info;
    }




}//end of class
