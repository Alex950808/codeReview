<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CatFormulaModel extends Model
{
    protected $table = 'cat_formula as cf';
    protected $field = [
        'cf.id', 'cf.cat_code', 'cf.method_id', 'cf.channels_id', 'cf.param_code_info',
    ];

    /**
     * description 获取折扣种类公式列表
     * author zongxing
     * date 2019.08.07
     * return Array
     */
    public function getCatFormulaList($param_info)
    {
        $field = $this->field;
        $add_field = ['cat_name','channels_name','method_name'];
        $field = array_merge($field, $add_field);
        $where = [];
        if (isset($param_info['channels_id'])) {
            $channels_id = intval($param_info['channels_id']);
            $where[] = ['channels_id', '=', $channels_id];
        }
        $cf_res = DB::table($this->table)
            ->leftJoin('discount_cat as dc','dc.cat_code','=','cf.cat_code')
            ->leftJoin('purchase_channels as pc','pc.id','=','cf.channels_id')
            ->leftJoin('purchase_method as pm','pm.id','=','cf.method_id')
            ->where($where)->get($field);
        $cf_res = objectToArrayZ($cf_res);
        return $cf_res;
    }

    /**
     * description 新增折扣种类公式
     * author zongxing
     * date 2019.08.07
     * return foolean
     */
    public function doAddCatFormula($param_info)
    {
        $insert_data = [
            'cat_code' => trim($param_info['cat_code']),
            'method_id' => intval($param_info['method_id']),
            'channels_id' => intval($param_info['channels_id']),
            'param_code_info' => trim($param_info['param_code_info']),
        ];
        $cf_res = DB::table('cat_formula')->insert($insert_data);
        $cf_res = objectToArrayZ($cf_res);
        return $cf_res;
    }


}//end of class
