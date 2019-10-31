<?php

namespace App\Model\Vone;

use Illuminate\Support\Facades\DB;
use Zizaco\Entrust\EntrustPermission;

class Permission extends EntrustPermission
{
    protected $table = 'permissions';
    protected $fields = ['id', 'name', 'web_name', 'display_name', 'description', 'rank', 'parent_id'];


    /**
     * description:检查权限信息
     * editor:zongxing
     * date : 2018.07.27
     * params: 1.请求参数:$role_info;
     * return Array
     */
    public function check_permission_info($role_info)
    {
        //检查管理员权限
        $user_info = DB::table("permissions")
            ->where("name", $role_info["name"])
            ->get();
        $user_info = $user_info->toArray();
        return $user_info;
    }

    /**
     * description:获取管理员权限
     * editor:zongxing
     * date : 2018.07.28
     * params: 1.请求参数:$user_id;
     * return Array
     */
    public function get_user_permission($loginUserInfo)
    {
        $role_id = $loginUserInfo->role_id;
        $where = [
            ['status', '=', 1]
        ];
        $permission_info = DB::table($this->table)
            ->where($where)
            ->orderBy('sort_num', 'asc')
            ->get($this->fields);
        $permission_info = objectToArrayZ($permission_info);

        $where = [
            ['role_id', '=', $role_id]
        ];
        $permission_role_info = DB::table('permission_role')
            ->where($where)
            ->pluck('permission_id');
        $permission_role_info = objectToArrayZ($permission_role_info);

        foreach ($permission_info as $k => $v) {
            $permission_id = $v['id'];
            if (!in_array($permission_id, $permission_role_info)) {
                unset($permission_info[$k]);
            }
        }

        $permission_list_1 = [];
        foreach ($permission_info as $k => $v) {
            $rank = $v['rank'];
            if ($rank == 1) {
                $permission_id = $v['id'];
                $permission_list_1[$permission_id] = $v;
            }
        }
        foreach ($permission_info as $k => $v) {
            $rank = $v['rank'];
            if ($rank == 2) {
                $permission_id = $v['id'];
                $permission_list_2[$permission_id] = $v;
            }
        }
        foreach ($permission_info as $k => $v) {
            $rank = $v['rank'];
            if ($rank == 3) {
                $permission_id = $v['id'];
                $permission_list_3[$permission_id] = $v;
            }
        }
        foreach ($permission_info as $k => $v) {
            $rank = $v['rank'];
            if ($rank == 9) {
                $permission_id = $v['id'];
                $permission_list_9[$permission_id] = $v;
            }
        }
        if(!empty($permission_list_9)){
            foreach ($permission_list_9 as $k => $v) {
                $parent_id = $v['parent_id'];
                if(isset($permission_list_3[$parent_id])){
                    $permission_list_3[$parent_id]['child_info'][] = $v;
                }
            }
        }
        if(!empty($permission_list_3)){
            foreach ($permission_list_3 as $k => $v) {
                $parent_id = $v['parent_id'];
                if(isset($permission_list_2[$parent_id])){
                    $permission_list_2[$parent_id]['child_info'][] = $v;
                }
            }
        }
        if(!empty($permission_list_2)){
            foreach ($permission_list_2 as $k => $v) {
                $parent_id = $v['parent_id'];
                if(isset($permission_list_1[$parent_id])){
                    $permission_list_1[$parent_id]['child_info'][] = $v;
                }
            }
        }
        $return_info = array_values($permission_list_1);
        return $return_info;
    }

    /**
     * description:获取权限列表
     * editor:zongxing
     * date : 2018.07.28
     * params: 1.请求参数:$user_id;
     * return Array
     */
    public function get_permission_list()
    {
        $permission_info = $this->get_child_permission_list(0);
        return $permission_info;
    }

    /**
     * description:获取各级权限
     * editor:zongxing
     * date : 2018.07.28
     * params: 1.父级id:$parent_id;
     * return Array
     */
    public function get_child_permission_list($parent_id)
    {
        $permission_info = DB::table("permissions as p")
            ->where("parent_id", $parent_id)
            ->where("status", 1)
            ->get(["p.id", "web_name", "p.display_name", "parent_id"]);
        $permission_info = $permission_info->toArray();

        foreach ($permission_info as $k => $v) {
            $child_info = $this->get_child_permission_list($v->id);
            if ($child_info) {
                $permission_info[$k]->child_info = $child_info;
            }
        }
        return $permission_info;
    }
}
