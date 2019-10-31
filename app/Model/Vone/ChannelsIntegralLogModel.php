<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChannelsIntegralLogModel extends Model
{
    protected $table = 'channels_integral_log as cil';

    protected $field = [
        'cil.id', 'cil.channels_id', 'cil.modify_num', 'cil.modify_category', 'cil.create_time', 'cil.modify_time'
    ];

    /**
     * description:提交新增渠道积分
     * editor:zongxing
     * date : 2019.04.29
     * return Bollean
     */
    public function doAddChannelIntegralLog($param_info, $channel_integral_info = [])
    {
        $channels_id = trim($param_info['channels_id']);
        $integral_balance = floatval($param_info['integral_balance']);
        $money_balance = floatval($param_info['money_balance']);
        $insert_data = [];
        if (empty($channel_integral_info)) {
            $insert_data[] = [
                'channels_id' => $channels_id,
                'modify_num' => $integral_balance,
                'modify_category' => 0
            ];
            $insert_data[] = [
                'channels_id' => $channels_id,
                'modify_num' => $money_balance,
                'modify_category' => 1
            ];
        }else{
            $old_integral_balance = floatval($channel_integral_info[0]['integral_balance']);
            $old_money_balance = floatval($channel_integral_info[0]['money_balance']);
            if ($old_integral_balance !== $integral_balance) {
                $insert_data[] = [
                    'channels_id' => $channels_id,
                    'modify_num' => $integral_balance,
                    'modify_category' => 0
                ];
            }
            if ($old_money_balance !== $money_balance) {
                $insert_data[] = [
                    'channels_id' => $channels_id,
                    'modify_num' => $money_balance,
                    'modify_category' => 1
                ];
            }
        }
        $insert_res = DB::table('channels_integral_log')->insert($insert_data);
        return $insert_res;
    }

    /**
     * description:获取渠道积分记录列表
     * editor:zongxing
     * date : 2019.04.29
     * return Bollean
     */
    public function getChannelIntegralLog($param = '')
    {
        $channel_integral_log_obj = DB::table('channels_integral_log as cil');
        if (isset($param['channels_id'])) {
            $channels_id = $param['channels_id'];
            $channel_integral_log_obj->where('cil.channels_id', $channels_id);
        }
        $channel_integral_log_list = $channel_integral_log_obj->get();
        $channel_integral_log_list = objectToArrayZ($channel_integral_log_list);
        $integral_log = [];
        $money_log = [];
        foreach ($channel_integral_log_list as $k=>$v){
            $modify_category = intval($v['modify_category']);
            if($modify_category == 0){
                $integral_log[] = $v;
            }else{
                $money_log[] = $v;
            }
        }
        $return_info = [
            'integral_log'=>$integral_log,
            'money_log'=>$money_log,
        ];
        return $return_info;
    }

}
