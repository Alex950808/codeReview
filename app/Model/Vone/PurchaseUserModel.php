<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseUserModel extends Model
{
    protected $table = 'purchase_user';

    //可操作字段
    protected $fillable = ['real_name', 'passport_sn', 'method_id', 'channels_id', 'account_number'];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    /**
     * description:检查采购人员的信息
     * editor:zongxing
     * date : 2018.07.04
     * params: 1.采购人员姓名:real_name;2.采购人员护照号:passport_sn;3.采购方式id:method_id;4.采购渠道id:channels_id;
     *          5.账号:account_number;
     * return Array
     */
    public function check_user_info($purchase_user_info)
    {
        //检查采购人员姓名
        $user_info = DB::table("purchase_user")
            ->where("real_name", $purchase_user_info["real_name"])
            ->orWhere("passport_sn", $purchase_user_info["passport_sn"])
            ->orWhere("account_number", $purchase_user_info["account_number"])
            ->get();
        $user_info = $user_info->toArray();
        return $user_info;
    }

    /**
     * description:获取采购id列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getUserList()
    {
        $user_list_info = DB::table("purchase_user as pu")
            ->select("pu.id", "real_name", "passport_sn", "channels_name", "method_name", "account_number")
            ->leftJoin("purchase_channels as pc", "pc.id", "=", "pu.channels_id")
            ->leftJoin("purchase_method as pm", "pm.id", "=", "pu.method_id")
            ->orderBy(DB::raw('convert(`real_name` using gbk)'))
            ->get();
        $user_list_info = objectToArrayZ($user_list_info);
        return $user_list_info;
    }

    /**
     * description:获取采购id信息
     * editor:zongxing
     * type:POST
     * date : 2018.07.10
     * return Object
     */
    public function getUserInfo($user_info)
    {
        $purchase_user_id = $user_info["id"];
        $user_detail_info = DB::table("purchase_user")
            ->select("id", "real_name", "passport_sn", "channels_id", "method_id", "account_number")
            ->where("id", $purchase_user_id)
            ->get();
        $user_detail_info = $user_detail_info->toArray();
        return $user_detail_info;
    }

    /**
     * description:提交编辑采购id页面
     * editor:zongxing
     * type:POST
     * date : 2018.07.10
     * return Object
     */
    public function editUserInfo($user_info)
    {
        $purchase_user_id = $user_info["id"];
        $update_user_info = DB::table("purchase_user as pu")
            ->where("id", $purchase_user_id)
            ->update($user_info);
        $update_user_info = $update_user_info->toArray();
        return $update_user_info;
    }

}
