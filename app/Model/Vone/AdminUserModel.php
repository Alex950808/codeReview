<?php

namespace App\Model\Vone;

use Illuminate\Foundation\Auth\User as Authenticatable;

//引入entrust权限包 add by zhangdong on the 2018.07.18
use Illuminate\Support\Facades\DB;
use Zizaco\Entrust\Traits\EntrustUserTrait;
use Illuminate\Notifications\Notifiable;

class AdminUserModel extends Authenticatable
{
    use Notifiable;
    use EntrustUserTrait;

    protected $table = 'admin_user';

    //可操作字段
    protected $fillable = ['user_name', 'password', 'jms_verify', 'action_list', 'role_id', 'last_ip', 'last_login'];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    /**
     * description:更新用户登录IP
     * editor:zongxing
     * date : 2018.06.23
     * params: 1.用户名:$user_name;
     */
    public function upload_login_ip($user_name)
    {
        //获取登录IP
        $login_ip = $this->get_real_ip();

        AdminUserModel::where('user_name', $user_name)
            ->update(['last_ip' => $login_ip]);
    }

    /**
     * description:获取登录IP
     * editor:zongxing
     * date : 2018.06.23
     * return String
     */
    public function get_real_ip()
    {
        static $realip = NULL;

        if ($realip !== NULL) {
            return $realip;
        }

        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

                /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
                foreach ($arr AS $ip) {
                    $ip = trim($ip);

                    if ($ip != 'unknown') {
                        $realip = $ip;

                        break;
                    }
                }
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                if (isset($_SERVER['REMOTE_ADDR'])) {
                    $realip = $_SERVER['REMOTE_ADDR'];
                } else {
                    $realip = '0.0.0.0';
                }
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $realip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_CLIENT_IP')) {
                $realip = getenv('HTTP_CLIENT_IP');
            } else {
                $realip = getenv('REMOTE_ADDR');
            }
        }

        preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
        $realip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';

        return $realip;
    }


    /**
     * description:检查管理员的信息
     * editor:zongxing
     * date : 2018.07.27
     * params: 1.请求参数:$admin_user_info;
     * return Array
     */
    public function check_user_info($admin_user_info)
    {
        if(isset($admin_user_info["id"])){
            //检查管理员权限
            $user_info = DB::table("admin_user")
                ->where("id", $admin_user_info["id"])
                ->get();
        }else{
            $user_info = DB::table("admin_user")
                ->where("user_name", $admin_user_info["user_name"])
                ->get();
        }

        $user_info = $user_info->toArray();
        return $user_info;
    }

    /**
     * description:检查管理员的信息
     * editor:zongxing
     * date : 2018.07.27
     * params: 1.请求参数:$admin_user_info;
     * return Array
     */
    public function get_user_role($admin_user_info)
    {
        //检查管理员权限
        $user_info = DB::table("role_user")
            ->where("user_id", $admin_user_info["user_id"])
            ->get(["role_id"]);
        $user_info = $user_info->toArray();
        return $user_info;
    }


    /**
     * description:获取管理员列表
     * editor:zongxing
     * date : 2018.07.27
     * return Object
     */
    public function getUserList()
    {
        $user_list_info = DB::table("admin_user as au")
            ->leftJoin("role_user as ru", "ru.user_id", "=", "au.id")
            ->leftJoin("roles as r", "r.id", "=", "ru.role_id")
            ->get(["au.id", "user_name", "nickname", "display_name as role_name", "create_time", "last_login"]);
        $user_list_info = $user_list_info->toArray();
        return $user_list_info;
    }



}
