<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MarginRateModel extends Model
{
    protected $table = 'margin_rate as mr';
    protected $field = ['mr.id', 'mr.pick_margin_rate', 'mr.create_time'];

    /**
     * description:自采毛利率列表
     * editor:zongxing
     * date : 2019.03.13
     * return Array
     */
    public function getMarginRateList()
    {
        $field = ['mr.id', 'mr.create_time',
            DB::raw('ROUND(jms_mr.pick_margin_rate, 2) as pick_margin_rate')
        ];
        $margin_rate_list = DB::table($this->table)->select($field)->get($this->field);
        $margin_rate_list = objectToArrayZ($margin_rate_list);
        return $margin_rate_list;
    }

    /**
     * description:自采毛利率信息
     * editor:zongxing
     * date : 2019.03.13
     * return Array
     */
    public function getMarginRateInfo($param_info)
    {
        $where = [];
        if (isset($param_info['id'])) {
            $id = intval($param_info['id']);
            $where[] = ['mr.id', '=', $id];
        }
        $margin_rate_info = DB::table($this->table)->where($where)->get($this->field);
        $margin_rate_info = objectToArrayZ($margin_rate_info);
        return $margin_rate_info;
    }

    /**
     * description:删除自采毛利率
     * editor:zongxing
     * date : 2019.03.13
     * return Array
     */
    public function delMarginRate($param_info)
    {
        $id = intval($param_info['id']);
        $del_margin_rate_res = DB::table('margin_rate')->where('id', '=', $id)->delete();
        return $del_margin_rate_res;
    }

    /**
     * description:统计符合条件的数量
     * date 2019.06.10
     * author:zhangdong
     */
    public function countMarginRateNum($pickMarginRate)
    {
        $where = [
            ['pick_margin_rate', $pickMarginRate],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }


}
