<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入redis
use Illuminate\Support\Facades\Redis;

class PurchaseChannelModel extends Model
{
    protected $table = 'purchase_channels as pc';

    //可操作字段
    protected $fillable = ['channels_sn', 'channels_name', 'pc.post_discount',
        'pc.is_count_wai', 'pc.original_or_discount'];

    protected $field = [
        'pc.id', 'pc.channels_sn', 'pc.channels_name', 'pc.method_id', 'pc.post_discount', 'pc.is_count_wai',
        'pc.original_or_discount', 'pc.margin_payment', 'pc.margin_currency', 'pc.team_add_points',
    ];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    /**
     * description:获取系统所有渠道
     * editor:zongxing
     * date : 2018.12.26
     * return Array
     */
    public function getTotalChannelList()
    {
        $field = $this->field;
        $add_arr = ['method_id', 'method_name'];
        $field = array_merge($field, $add_arr);
        $channel_info = DB::table($this->table)
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'pc.method_id')
            ->orderBy('pc.create_time', 'desc')
            ->get($field);
        $channel_info = objectToArrayZ($channel_info);
        return $channel_info;
    }

    /**
     * description:获取渠道信息
     * editor:zongxing
     * date : 2019.01.07
     * return Array
     */
    public function getChannelList($purchase_channels_id = [], $method_id = '')
    {
        $purchase_channels_obj = DB::table('purchase_channels as pc')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'pc.method_id');
        if (!empty($purchase_channels_info)) {
            $purchase_channels_obj->whereIn('id', $purchase_channels_id);
        }
        if (!empty($method_id)) {
            $purchase_channels_obj->where('method_id', $method_id);
        }
        $field = $this->field;
        $add_field = ['method_id', 'method_sn', 'method_name'];
        $field = array_merge($field, $add_field);
        $purchase_channels_list = $purchase_channels_obj->orderBy(DB::raw('convert(`channels_name` using gbk)'))
            ->get($field);
        $purchase_channels_list = objectToArrayZ($purchase_channels_list);
        return $purchase_channels_list;
    }

    /**
     * description:检查上传时采购期渠道
     * author:zongxing
     * date : 2018.12.28
     * return Array
     */
    public function checkUploadPurchaseChannel($param_info)
    {
        $channels_id = $param_info['channels_id'];
        $channels_info = DB::table('purchase_channels')->where('id', $channels_id)->first(['channels_sn', 'channels_name',
            'original_or_discount']);
        $channels_info = objectToArrayZ($channels_info);
        return $channels_info;
    }

    /**
     * description:通过id获取渠道
     * author:zhangdong
     * date : 2019.01.30
     */
    public function getChannelInfo($channel_id)
    {
        $where = [
            ['id', intval($channel_id)],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * @description:从redis中获取采购渠道信息
     * @editor:张冬
     * @date : 2019.03.29
     * @return object
     */
    public function getPurChannelInRedis()
    {
        //从redis中获取品牌信息，如果没有则对其设置
        $purChannelInfo = Redis::get('purChannelInfo');
        if (empty($purChannelInfo)) {
            $field = ['id', 'channels_name'];
            $purChannelInfo = DB::table($this->table)->select($field)->get()
                ->map(function ($value) {
                    return (array)$value;
                })->toArray();
            Redis::set('purChannelInfo', json_encode($purChannelInfo, JSON_UNESCAPED_UNICODE));
            $purChannelInfo = Redis::get('purChannelInfo');
        }
        $purChannelInfo = objectToArray(json_decode($purChannelInfo));
        return $purChannelInfo;

    }


}//end of class
