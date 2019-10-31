<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GoodsLabelModel extends Model
{
    public $table = 'goods_label as gl';
    public $field = ['id', 'label_name', 'label_color'];

    /**
     * description:获取商品标签信息
     * editor:zongxing
     * params:商品标签名称:$label_name
     * date : 2019.02.21
     */
    public function getGoodsLabelInfo($param_str)
    {
        $label_obj = DB::table($this->table);
        if (isset($param_str['id'])) {
            $label_obj->where('id','=', $param_str['id']);
        }
        if (isset($param_str['label_real_name'])) {
            $label_obj->where('label_real_name','=', $param_str['label_real_name']);
        }
        if (isset($param_str['label_name'])) {
            $label_obj->where('label_name','=', $param_str['label_name']);
        }
        $label_info = $label_obj->first();
        $label_info = objectToArrayZ($label_info);
        return $label_info;
    }

    /**
     * description:新增商品标签
     * editor:zongxing
     * params:商品标签名称:$label_name
     * date : 2019.02.21
     */
    public function doAddGoodsLabel($param_info)
    {
        $insert_label = [
            'label_name' => trim($param_info['label_name']),
            'label_real_name' => trim($param_info['label_real_name']),
            'label_color' => trim($param_info['label_color'])
        ];
        $insert_res = DB::table('goods_label')->insert($insert_label);
        return $insert_res;
    }

    /**
     * description:获取商品标签列表
     * editor:zongxing
     * date : 2019.02.21
     */
    public function getGoodsLabelList($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $label_list = DB::table($this->table)->select($this->field)->paginate($page_size);
        $label_list = objectToArrayZ($label_list);
        return $label_list;
    }

    /**
     * description:获取所有商品标签
     * editor:zongxing
     * date : 2019.02.22
     */
    public function getAllGoodsLabelList()
    {
        $label_list = DB::table($this->table)->select($this->field)->get();
        $label_list = objectToArrayZ($label_list);
        return $label_list;
    }

    /**
     * description:提交编辑商品标签
     * editor:zongxing
     * params:1.商品标签名称:label_name;2.商品标签id:id
     * date : 2019.02.21
     */
    public function doEditGoodsLable($param_info)
    {
        $id = intval($param_info['id']);
        $update_label = [
            'label_name' => trim($param_info['label_name']),
            'label_real_name' => trim($param_info['label_real_name']),
            'label_color' => trim($param_info['label_color'])
        ];
        $insert_res = DB::table($this->table)->where('id', $id)->update($update_label);
        return $insert_res;
    }

}
