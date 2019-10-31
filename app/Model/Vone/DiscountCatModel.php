<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscountCatModel extends Model
{
    protected $table = 'discount_cat as dc';
    protected $field = [
        'dc.id', 'dc.cat_name', 'dc.cat_code', 'dc.is_profit',
    ];

    /**
     * description 获取毛利计算所需的折扣种类列表
     * author zongxing
     * date 2019.07.31
     * return Array
     */
    public function getDiscountCatList($param = [])
    {
        $where = [];
        if (isset($param['is_profit'])) {
            $is_profit = intval($param['is_profit']);
            $where[] = ['is_profit', '=', $is_profit];
        }
        if (isset($param['is_discount'])) {
            $is_discount = intval($param['is_discount']);
            $where[] = ['is_discount', '=', $is_discount];
        }
        if (isset($param['is_count']) && $param['is_count'] == 0) {
            $where[] = ['id', '!=', 12];
        }
        $dc_list = DB::table($this->table)->where($where)->get($this->field);
        $dc_list = objectToArrayZ($dc_list);
        return $dc_list;
    }

    /**
     * description 获取毛利计算所需的折扣种类信息
     * author zongxing
     * date 2019.08.02
     * return Array
     */
    public function getDiscountCatInfo($param_info)
    {
        $where = [];
        if (isset($param_info['cat_code'])) {
            $cat_code = $param_info['cat_code'];
            $where[] = ['cat_code', '=', $cat_code];
        }
        $dc_info = DB::table($this->table)->where($where)->get($this->field);
        $dc_info = objectToArrayZ($dc_info);
        return $dc_info;
    }

    /**
     * description 新增折扣类型种类
     * author zongxing
     * date 2019.08.02
     * return Array
     */
    public function doAddDiscountCat($param_info)
    {
        $where = [];
        if (isset($param_info['cat_code'])) {
            $cat_code = $param_info['cat_code'];
            $where[] = ['cat_code', '=', $cat_code];
        }
        //$cat_sn = $this->createCatSn();
        $insert_data = [
            'cat_name' => trim($param_info['cat_name']),
            'cat_code' => trim($param_info['cat_code']),
            'is_profit' => intval($param_info['is_profit']),
        ];
        $res = DB::table('discount_cat')->insert($insert_data);
        return $res;
    }

    /**
     * description 折扣类型种类单号
     * author zongxing
     * date 2019.08.07
     * return String
     */
    public function createCatSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $cat_sn = 'CAT' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table($this->table)
                ->where([
                    ['cat_sn', '=', $cat_sn]
                ])->count();
        } while ($count);
        return $cat_sn;
    }


}//end of class
