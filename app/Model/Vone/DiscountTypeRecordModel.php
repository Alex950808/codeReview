<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscountTypeRecordModel extends Model
{
    protected $table = 'discount_type_record as dtr';
    protected $field = ['dtr.id', 'dtr.start_date', 'dtr.end_date', 'dtr.method_id', 'dtr.channels_id',
        'dtr.cost_id', 'dtr.predict_id', 'dtr.month_type_id', 'dtr.brand_month_predict_id'];

    /**
     * description 获取折扣类型记录信息
     * editor zongxing
     * date 2019.09.02
     * return Array
     */
    public function getDiscountTypeRecordInfo($param_info)
    {
        $start_date = $param_info['start_date'];
        $end_date = $param_info['end_date'];
        $channels_id = intval($param_info['channels_id']);
        $where = [
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
        if ($channels_id != 0) {
            $where['channels_id'] = $channels_id;
        }
        $dtr_info = DB::table($this->table)->where($where)->first();
        $dtr_info = objectToArrayZ($dtr_info);
        if (!empty($dtr_info)) {
            $dtr_info['cost_arr'] = explode(',', $dtr_info['cost_id']);
            $dtr_info['predict_arr'] = explode(',', $dtr_info['predict_id']);
            $dtr_info['month_type_arr'] = explode(',', $dtr_info['month_type_id']);
            $dtr_info['brand_month_predict_arr'] = explode(',', $dtr_info['brand_month_predict_id']);
        }
        return $dtr_info;
    }

    /**
     * description 获取折扣类型记录列表
     * editor zongxing
     * date 2019.09.02
     * return Array
     */
    public function getDiscountTypeRecordList($param_info = [])
    {
        $field = $this->field;
        $field = array_merge($field, ['pm.method_name', 'pc.channels_name']);
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $dtr_obj = DB::table($this->table)->select($field)
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'dtr.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'dtr.channels_id');
        if (isset($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $dtr_obj->where(function ($where) use ($query_sn) {
                $where->orWhere('pc.channels_name', $query_sn);
                $where->orWhere('pm.method_name', $query_sn);
            });
        }
        if (!empty($param_info['buy_time'])) {
            $buy_time = trim($param_info['buy_time']);
            $dtr_obj->where('dtr.start_date', '<=', $buy_time)
                ->where('dtr.end_date', '>=', $buy_time);
        } else {
            if (isset($param_info['start_date'])) {
                $start_date = trim($param_info['start_date']);
                $dtr_obj->where('dtr.start_date', $start_date);
            }
            if (isset($param_info['end_date'])) {
                $end_date = trim($param_info['end_date']);
                $dtr_obj->where('dtr.end_date', $end_date);
            }
        }
        if (isset($param_info['method_name'])) {
            $method_name = trim($param_info['method_name']);
            $dtr_obj->where('pm.method_name', $method_name);
        }
        if (isset($param_info['channels_name'])) {
            $channels_name = trim($param_info['channels_name']);
            $dtr_obj->where('pc.channels_name', $channels_name);
        }
        if (isset($param_info['method_id'])) {
            $method_id = intval($param_info['method_id']);
            $dtr_obj->where('pm.id', $method_id);
        }
        if (isset($param_info['channels_id']) && intval($param_info['channels_id']) != 0) {
            $channels_id = intval($param_info['channels_id']);
            $dtr_obj->where('pc.id', $channels_id);
        }
        $dtr_list = $dtr_obj->orderBy('dtr.create_time', 'desc')->paginate($page_size);
        $dtr_list = objectToArrayZ($dtr_list);
        return $dtr_list;
    }

    /**
     * description 新增折扣类型记录
     * editor zongxing
     * date 2019.09.02
     * return Array
     */
    public function doAddDiscountTypeRecord($param_info)
    {
        $start_date = $param_info['start_date'];
        $end_date = $param_info['end_date'];
        $method_id = intval($param_info['method_id']);
        $channels_id = intval($param_info['channels_id']);
        $cost_id = trim($param_info['cost_id']);
        $predict_id = trim($param_info['predict_id']);
        $month_type_id = trim($param_info['month_type_id']);
        $brand_month_predict_id = isset($param_info['brand_month_predict_id']) ? trim($param_info['brand_month_predict_id']) : '';
        $data = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'method_id' => $method_id,
            'channels_id' => $channels_id,
            'cost_id' => $cost_id,
            'predict_id' => $predict_id,
            'month_type_id' => $month_type_id,
            'brand_month_predict_id' => $brand_month_predict_id,
        ];
        $res = DB::table('discount_type_record')->insert($data);
        return $res;
    }
}
