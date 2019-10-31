<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SupplierModel extends Model
{
    protected $table = 'supplier';

    //可操作字段
    protected $field = ['supplier_id', 'supplier_name'];


    /**
     * description:检查供应商是否存在
     * editor:zongxing
     * type:POST
     * date : 2019.01.07
     * params: 1.供应商名称:supplier_name;
     * return Array
     */
    public function getSupplierByName($supplier_name)
    {
        $where = [
            ['supplier_name', $supplier_name]
        ];
        $supplier_info = DB::table($this->table)->where($where)->first();
        $supplier_info = objectToArrayZ($supplier_info);
        return $supplier_info;
    }

    /**
     * description:添加供应商
     * editor:zongxing
     * type:POST
     * date : 2019.01.07
     * params: 1.供应商名称:supplier_name;
     * return Array
     */
    public function addSupplierByName($param_info)
    {
        $insert_supplier['supplier_name'] = trim($param_info['supplier_name']);
        $insert_supplier['supplier_num'] = trim($param_info['supplier_num']);
        $inser_supplier_res = DB::table($this->table)->insert($insert_supplier);
        return $inser_supplier_res;
    }

    /**
     * description:供应商列表
     * editor:zongxing
     * date : 2019.01.07
     * return Array
     */
    public function getSupplierList($param_info = null, $is_page)
    {
        if ($is_page) {
            $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
            $supplier_list = DB::table($this->table)->paginate($page_size);
        } else {
            $supplier_list = DB::table($this->table)->get(['supplier_id', 'supplier_name']);
        }
        $supplier_list = objectToArrayZ($supplier_list);
        return $supplier_list;
    }

}
