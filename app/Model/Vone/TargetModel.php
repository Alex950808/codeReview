<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TargetModel extends Model
{
    protected $table = 'target as t';

    //可操作字段
    protected $field = ['t.id', 't.target_name', 't.target_content'];

    /**
     * description 获取目标列表
     * author zongxing
     * type GET
     * date 2019.10.28
     * return Array
     */
    public function targetList($param = [])
    {
        $field = $this->field;
        $target_obj = DB::table($this->table);
        if (isset($param['target_name'])) {
            $target_obj->where('target_name', trim($param['target_name']));
        }
        if (isset($param['id'])) {
            $target_obj->where('id', intval($param['id']));
        }
        $target_list = $target_obj->get($field);
        $target_list = objectToArrayZ($target_list);
        return $target_list;
    }

    /**
     * description 新增目标
     * author zongxing
     * type POST
     * date 2019.10.28
     * return boolean
     */
    public function addTarget($param_info)
    {
        $data = [
            'target_name' => trim($param_info['target_name']),
            'target_content' => trim($param_info['target_content']),
        ];
        $insert_res = DB::table('target')->insert($data);
        return $insert_res;
    }

    /**
     * description 编辑目标
     * author zongxing
     * type POST
     * date 2019.10.28
     * return boolean
     */
    public function editTarget($param_info)
    {
        $target_id = intval($param_info['id']);
        $data = [
            'target_name' => trim($param_info['target_name']),
            'target_content' => trim($param_info['target_content']),
        ];
        $insert_res = DB::table('target')->where('id', $target_id)->update($data);
        return $insert_res;
    }

}
