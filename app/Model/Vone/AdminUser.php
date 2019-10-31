<?php

namespace App\Model\Vone;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    protected $table = 'admin_user';

    //可操作字段
    protected $fillable = ['user_name', 'password', 'email', 'jms_verify', 'action_list', 'role_id', 'last_ip', 'last_login'];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

}
