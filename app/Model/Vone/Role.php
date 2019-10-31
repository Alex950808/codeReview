<?php

namespace App\Model\Vone;

use Illuminate\Support\Facades\DB;
use Zizaco\Entrust\EntrustRole;

class Role extends EntrustRole
{
    protected $table = 'roles';

    protected $fillable = ['name', 'display_name', 'description'];

    public function permissions()
    {
        return $this->belongsToMany('App\Model\Vone\Permission', 'permission_role', 'role_id', 'permission_id');
    }

    public function users()
    {
        return $this->belongsToMany('App\Model\Vone\AdminUserModel', 'role_user', 'role_id', 'user_id');
    }

    /**
     * description:检查角色信息
     * editor:zongxing
     * date : 2018.07.27
     * params: 1.请求参数:$role_info;
     * return Array
     */
    public function check_role_info($role_info)
    {
        if (isset($role_info["id"])) {
            $user_info = DB::table("roles")
                ->where("id", $role_info["id"])
                ->get(["id", "name", "display_name", "description"]);
        } else {
            $user_info = DB::table("roles")
                ->where("name", $role_info["name"])
                ->get(["id", "name", "display_name", "description"]);
        }
        $user_info = objectToArrayZ($user_info);
        return $user_info;
    }

    /**
     * description:获取角色列表
     * editor:zongxing
     * date : 2018.07.27
     * return Object
     */
    public function get_role_list()
    {
        $role_list_info = DB::table("roles")
            ->get(["id", "name", "display_name as role_name", "description"]);
        $role_list_info = $role_list_info->toArray();
        return $role_list_info;
    }

    /**
     * description:获取角色信息
     * editor:zongxing
     * date : 2018.07.28
     * return Object
     */
    public function get_role_info($role_info)
    {
        $role_id = $role_info["id"];
        //获取角色信息
        $role_detail_info = DB::table("roles")
            ->where("id", $role_id)
            ->get(["id", "name", "display_name as role_name", "description"]);
        $role_detail_info = $role_detail_info->toArray();

        //获取角色权限信息
        $role_permission_info = DB::table("permission_role")
            ->where("role_id", $role_id)->pluck("permission_id");
        $role_permission_info = $role_permission_info->toArray();

        //获取权限列表信息
        $permissionModel = new Permission();
        $user_permission_info = $permissionModel->get_permission_list();

        $return_info["role_info"] = $role_detail_info;
        $return_info["role_permission_info"] = $role_permission_info;
        $return_info["permission_list_info"] = $user_permission_info;
        return $return_info;
    }
}
