<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class GoodsIntegralModel extends Model
{
    protected $table = "goods_integral as gi";

    protected $field = [
        'gi.id','gi.spec_sn', 'gi.channel_id','gi.integral','gi.is_use','gi.create_time',
    ];
    public $is_use = [
        '0' => '不可用',
        '1' => '可用',
    ];


    /**
     * @description:保存数据
     * @editor:zhangdong
     * date:2019.01.30
     */
    public function writeData(array $arrData = [])
    {
        $makeData = $this->makeData($arrData);
        $result = $this->insertData($makeData);
        return $result;
    }

    /**
     * @description:查询商品积分数据
     * @author:zhangdong
     * date:2019.01.30
     */
    public function getData($value, $type = 1)
    {
        //按商品规格码查询
        $where = [];
        if ($type == 1) {
            $where[] = ['spec_sn', trim($value)];
        }
        //按渠道id查询
        if ($type == 2) {
            $where[] = ['channel_id', intval($value)];
        }
        //按是否可用查询
        if ($type == 3) {
            $where[] = ['is_use', intval($value)];
        }
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }

    /**
     * @description:组装要写入的数据
     * @editor:zhangdong
     * date:2019.01.28
     */
    private function makeData(array $arrData = [])
    {
        $spec_sn = isset($arrData['spec_sn']) ? trim($arrData['spec_sn']) : '';
        $channel_id = isset($arrData['channel_id']) ? intval($arrData['channel_id']) : 0;
        $integral = isset($arrData['integral']) ? intval($arrData['integral']) : 0;
        $makeRes = [
            'spec_sn' => $spec_sn,
            'channel_id' => $channel_id,
            'integral' => $integral,
        ];
        return $makeRes;
    }

    /**
     * @description:插入数据
     * @editor:zhangdong
     * date:2019.01.28
     */
    private function insertData(array $insertData = [])
    {
        $insertRes = DB::table('goods_integral')->insert($insertData);
        return $insertRes;
    }

    /**
     * @description:更新数据
     * @auditor:zhangdong
     * date:2019.01.30
     */
    public function updateData($id, array $updateData = [])
    {
        $where = [
            ['id', $id]
        ];
        $update = [];
        $channel_id = isset($updateData['channel_id']) ? intval($updateData['channel_id']) : 0;
        if ($channel_id > 0) {
            $update['channel_id'] = $channel_id;
        }
        $integral = isset($updateData['integral']) ? intval($updateData['integral']) : 0;
        if ($integral > 0) {
            $update['integral'] = $integral;
        }
        $is_use = isset($updateData['is_use']) ? intval($updateData['is_use']) : 0;
        if ($is_use > 0) {
            $update['is_use'] = $is_use;
        }
        if (count($update) == 0) {
            return false;
        }
        $updateRes = DB::table('goods_integral')->where($where)->update($update);
        return $updateRes;
    }

    /**
     * @description:查询商品积分数据
     * @author:zhangdong
     * date:2019.01.30
     */
    public function getIntegralInfo($arrParams)
    {
        //加入规格码筛选
        $where = [];
        $spec_sn = isset($arrParams['spec_sn']) ? trim($arrParams['spec_sn']) : '';
        if (!empty($spec_sn)) {
            $where[] = ['spec_sn', $spec_sn];
        }
        //加入渠道id筛选
        $channel_id = isset($arrParams['channel_id']) ? trim($arrParams['channel_id']) : '';
        if (!empty($spec_sn)) {
            $where[] = ['channel_id', $channel_id];
        }
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }








}
