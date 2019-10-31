<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChannelsIntegralModel extends Model
{
    protected $table = 'channels_integral as ci';

    protected $field = [
        'ci.id', 'ci.channels_id', 'ci.integral_balance', 'ci.money_balance','ci.create_time','ci.modify_time'
    ];

    /**
     * description:提交新增渠道积分
     * editor:zongxing
     * date : 2019.04.23
     * return Bollean
     */
    public function doAddChannelIntegral($param_info)
    {
        $insert_data = [
            'channels_id' => trim($param_info['channels_id']),
            'integral_balance' => floatval($param_info['integral_balance']),
            'money_balance' => floatval($param_info['money_balance'])
        ];
        $insert_res = DB::table('channels_integral')->insert($insert_data);
        return $insert_res;
    }

    /**
     * description:获取渠道积分列表
     * editor:zongxing
     * date : 2019.04.23
     * return Bollean
     */
    public function getChannelIntegral($param_info = '')
    {
        $field_other = ['method_name', 'channels_name'];
        $field = $this->field;
        $field = array_merge($field, $field_other);
        $channel_integral_obj = DB::table($this->table)
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'ci.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'pc.method_id');
        if (isset($param_info['channels_id'])) {
            $channels_id = intval($param_info['channels_id']);
            $channel_integral_obj->where('channels_id', $channels_id);
        }
        $channel_integral_list = $channel_integral_obj->get($field);
        $channel_integral_list = objectToArrayZ($channel_integral_list);
        return $channel_integral_list;
    }

    /**
     * description:提交编辑渠道积分
     * editor:zongxing
     * date : 2019.04.23
     * return Bollean
     */
    public function doEditChannelIntegral($param_info)
    {
        $channels_id = trim($param_info['channels_id']);
        $update_data = [
            'integral_balance' => floatval($param_info['integral_balance']),
            'money_balance' => floatval($param_info['money_balance'])
        ];
        $update_res = DB::table($this->table)->where('channels_id', $channels_id)->update($update_data);
        return $update_res;
    }
}
