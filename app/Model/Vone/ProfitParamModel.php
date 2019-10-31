<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfitParamModel extends Model
{
    protected $table = 'profit_param as pp';
    protected $field = [
        'pp.id', 'pp.param_name', 'pp.param_code',
    ];

    /**
     * description 毛利参数列表
     * editor:zongxing
     * date : 2019.03.13
     * return Array
     */
    public function getProfitParamList()
    {
        $field = [
            'pp.id', 'pp.param_name', 'pp.param_code',
        ];
        $pp_list = DB::table($this->table)->get($field);
        $pp_list = objectToArrayZ($pp_list);
        return $pp_list;
    }
}
