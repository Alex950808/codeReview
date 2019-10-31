<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProfitFormulaModel extends Model
{
    protected $table = 'profit_formula as pf';
    protected $field = [
        'pf.id', 'pf.formula_sn', 'pf.formula_name', 'pf.cat_info',
    ];

    /**
     * description 新增毛利公式
     * author zongxing
     * date 2019.07.31
     * return foolean
     */
    public function doAddProfitFormula($param_info)
    {
        //$cat_info = json_encode($param_info['cat_info'], JSON_UNESCAPED_UNICODE);
        $formula_sn = $this->profitFormulaSn();
        $insert_data = [
            'formula_sn' => $formula_sn,
            'formula_name' => trim($param_info['formula_name']),
            'cat_info' => trim($param_info['cat_info']),
        ];
        $dc_res = DB::table('profit_formula')->insert($insert_data);
        $dc_res = objectToArrayZ($dc_res);
        return $dc_res;
    }

    /**
     * description 获取毛利公式列表
     * author zongxing
     * date 2019.07.31
     * return Array
     */
    public function getProfitFormulaList($param_info)
    {
        $where = [];
        if (isset($param_info['query_sn'])) {
            $query_sn = '%' . trim($param_info['query_sn']) . '%';
            $where[] = ['formula_name', 'like', $query_sn];
        }
        if (isset($param_info['formula_sn'])) {
            $formula_sn = trim($param_info['formula_sn']);
            $where[] = ['formula_sn', '=', $formula_sn];
        }
        $field = $this->field;
        $pf_list = DB::table('profit_formula as pf')->where($where)->get($field);
        $pf_list = objectToArrayZ($pf_list);
        return $pf_list;
    }

    /**
     * description 生成毛利公式单号
     * author zongxing
     * date 2019.07.31
     * return String
     */
    public function profitFormulaSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $formula_sn = 'GS' . $strNum;
            //查找这个毛利公式单号是否已经存在
            $count = DB::table($this->table)
                ->where([
                    ['formula_sn', '=', $formula_sn]
                ])->count();
        } while ($count);
        return $formula_sn;
    }

    /**
     * description 获取毛利公式信息
     * author zongxing
     * date 2019.07.31
     * return Array
     */
    public function getProfitFormulaInfo($profit_sn_arr)
    {
        $field = $this->field;
        $pf_list = DB::table('profit_formula as pf')
            ->leftJoin('profit as p', 'p.formula_sn', '=', 'pf.formula_sn')
            ->whereIn('profit_sn', $profit_sn_arr)->get($field);
        $pf_list = objectToArrayZ($pf_list);
        return $pf_list;
    }


}//end of class
