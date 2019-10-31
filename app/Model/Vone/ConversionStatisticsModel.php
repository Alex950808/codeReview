<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ConversionStatisticsModel extends Model
{
    protected $table = "conversion_statistics as cs";

    //可操作字段
    protected $field = ["cs.id", "cs.yd_num", "cs.bd_num", "cs.dd_num", "cs.create_time"];

    /**
     * @description:保存子单数据
     * @editor:zhangdong
     * @param $orderInfo
     * date:2019.01.28
     */
    public function writeData(array $arrData = [])
    {
        //查询当天是否有数据，有则更新无则新增
        $date = date('Y-m-d');
        $todayData = $this->getDataByTime($date);
        //没有数据则新增
        $result = false;
        $makeData = $this->makeData($arrData);
        if(is_null($todayData)){
            $result = $this->insertData($makeData);
        }
        //有数据则更新
        if(count($todayData) > 0){
            $id = intval($todayData->id);
            $result = $this->updateData($id, $makeData);
        }
        return $result;
    }

    /**
     * @description:根据时间查询统计数据
     * @editor:zhangdong
     * date:2019.01.28
     */
    public function getDataByTime($date)
    {
        $where = [
            ['create_time', 'like', $date . '%']
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * @description:组装要写入的数据
     * @editor:zhangdong
     * date:2019.01.28
     */
    private function makeData(array $arrData = [])
    {
        $yd_num = isset($arrData['yd_num']) ? intval($arrData['yd_num']) : 0;
        $bd_num = isset($arrData['bd_num']) ? intval($arrData['bd_num']) : 0;
        $dd_num = isset($arrData['dd_num']) ? intval($arrData['dd_num']) : 0;
        $makeRes = [
            'yd_num' => $yd_num,
            'bd_num' => $bd_num,
            'dd_num' => $dd_num,
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
        $insertRes = DB::table('conversion_statistics')->insert($insertData);
        return $insertRes;
    }

    /**
     * @description:插入数据
     * @editor:zhangdong
     * date:2019.01.28
     */
    private function updateData($id, array $updateData = [])
    {
        $where = [
            ['id', $id]
        ];
        $yd_num = isset($updateData['yd_num']) ? intval($updateData['yd_num']) : 0;
        $bd_num = isset($updateData['bd_num']) ? intval($updateData['bd_num']) : 0;
        $dd_num = isset($updateData['dd_num']) ? intval($updateData['dd_num']) : 0;
        $update = [
            'yd_num' => DB::raw('yd_num + ' . $yd_num),
            'bd_num' => DB::raw('bd_num + ' . $bd_num),
            'dd_num' => DB::raw('dd_num + ' . $dd_num)
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }









}
