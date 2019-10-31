<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;

class PurchaseCostModel extends Model
{
    protected $table = "purchase_cost";

    //可操作字段
    protected $fillable = ["cost_coef", "cost_status"];

    //修改laravel 自动更新
    const UPDATED_AT = "modify_time";
    const CREATED_AT = "create_time";


}
