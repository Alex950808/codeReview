<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NoticeModel extends Model
{
    protected $table = 'notice';

    //可操作字段
    protected $fillable = ['notice_sn', 'notice_date', 'notice_time', 'notice_content'];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';


}
