<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\Modules\ArrayGroupBy;

//create zhangdong 2019.09.18 - 国耻日
class SubPurchaseModel extends Model
{
    public $table = 'sub_purchase as sp';
    private $field = [
        'sp.sub_order_sn','sp.real_purchase_sn','sp.spec_sn','sp.standby_num','sp.create_time'
    ];

    /**
     * description 分配常备量
     * author zhangdong
     * date 2019.09.18 - 国耻日
     */
    public function allotStandbyGoods($subOrderSn)
    {
        $verifyRes = $this->countSubSn($subOrderSn);
        if ($verifyRes > 0) {
            return false;
        }
        $subOrderMsg = $this->getSubMsg($subOrderSn);
        //取出子单商品信息同时检查子单是否存在
        if ($subOrderMsg->count() == 0) {
            return false;
        }
        //查询常备批次信息
        $standbyBatchMsg = $this->getStandbyBatchMsg();
        if ($standbyBatchMsg->count() == 0) {
            return false;
        }
        $arrData = objectToArray($standbyBatchMsg);
        $allotArr = $waitBuyNumData = [];
        //通过循环的方式开始给子单分配常备量同时组装使用日志数据
        foreach($subOrderMsg as $key => $value){
            $specSn = trim($value->spec_sn);
            //通过规格码搜索常备批次以分配常备量
            $standbyGoods = searchTwoArray($arrData, $specSn, 'spec_sn');
            if(count($standbyGoods) == 0){
                continue;
            }
            $waitBuyNum = intval($value->wait_buy_num);
            //开始分配常备数量
            $allotRes = $this->allotGoodsNum($standbyGoods, $waitBuyNum);
            $allotArr[] = $allotRes['standbyGoods'];
            //常备量被分配后的待采量数据-用于更新子单待采量
            $waitBuyNumData[] = [
                'spec_sn' => $specSn,
                'wait_buy_num' => $allotRes['waitBuyNum'],
                'standby_num' => $waitBuyNum - $allotRes['waitBuyNum'],
            ];
        }
        //数据库写入更新等
        $operateRes = $this->operateData($allotArr, $waitBuyNumData, $subOrderSn);
        return $operateRes;

    }

    /**
     * description 统计关系表中子单个数
     * author zhangdong
     * date 2019.09.19
     */
    public function countSubSn($subOrderSn)
    {
        $where = [
            ['sub_order_sn', $subOrderSn],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }


    //----------(TODO) 私有方法区 ----------

    /**
     * description 获取子单待采量等信息-专用
     * author zhangdong
     * date 2019.09.18 - 国耻日
     */
    private function getSubMsg($subOrderSn)
    {
        $where = [
            ['sub_order_sn', $subOrderSn],
            //待采量为0的不需要分配常备量
            ['wait_buy_num', '>', 0],
        ];
        $field = ['spec_sn','wait_buy_num',];
        $queryRes = DB::table((new MisOrderSubGoodsModel())->getTable())->select($field)
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description 获取常备批次信息-专用
     * author zhangdong
     * date 2019.09.18 - 国耻日
     */
    private function getStandbyBatchMsg()
    {
        $rpaModel = new RealPurchaseAuditModel();
        $where = [
            ['rpa.batch_cat', $rpaModel->batch_cat['STANDBY']],
            ['rpa.status', '>=', $rpaModel->status['YET_SUBMIT']],
            ['rpda.available_num', '>', 0],
        ];
        $field = ['rpda.id','rpda.real_purchase_sn','rpda.spec_sn','rpda.available_num',];
        $rpda_on = [
            ['rpa.real_purchase_sn','rpda.real_purchase_sn'],
        ];
        $queryRes = DB::table($rpaModel->getTable())->select($field)
            ->leftJoin((new RealPurchaseDeatilAuditModel())->getTable(),$rpda_on)
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description 分配常备数量-专用
     * author zhangdong
     * date 2019.09.18 - 国耻日
     */
    private function allotGoodsNum($standbyGoods, $waitBuyNum)
    {
        foreach ($standbyGoods as $key => $value) {
            //常备批次的可用采购量
            $availableNum = intval($value['available_num']);
            //剩余待采量 = 原待采量 - 常备批次的可用采购量
            $remainNum = $waitBuyNum - $availableNum;
            $waitBuyNum = $remainNum;
            //分配完成后常备批次的可用采购量
            $standbyGoods[$key]['available_num'] = 0;
            //被使用量-用于记录常备批次被子单使用情况-记录入库
            $standbyGoods[$key]['used_num'] = $availableNum - $standbyGoods[$key]['available_num'];
            //如果待采量全部被常备批次的可用采购量占用，则剩余待采量为0,此时剩余待采量可能为负数，
            //所以此处用if语句对下面的值进行控制
            if($remainNum <= 0){
                $waitBuyNum = 0;
                //分配完成后常备批次的可用采购量 = 剩余待采量（绝对值）
                $standbyGoods[$key]['available_num'] = abs($remainNum);
                $standbyGoods[$key]['used_num'] = $availableNum - $standbyGoods[$key]['available_num'];
                break;
            }
        }
        return [
            'waitBuyNum' => $waitBuyNum,
            'standbyGoods' => $standbyGoods,
        ];

    }


    /**
     * description 分配常备数量-专用
     * author zhangdong
     * date 2019.09.19
     */
    private function operateData($allotArr, $waitBuyNumData, $subOrderSn)
    {
        if (count($allotArr) == 0) {
            return false;
        }
        //参数解释看下文
        $relationData = $standbyBatchUpdate = $arrAllotData = [];
        foreach ($allotArr as $key => $value) {
            foreach ($value as $k => $item) {
                //如果没有used_num键则说明该条常备批次的可用采购量未被使用，直接忽略
                if (!isset($item['used_num'])) {
                    continue;
                }
                //组装关系表写入数据
                $relationData[] = [
                    'sub_order_sn' => $subOrderSn,
                    'real_purchase_sn' => $item['real_purchase_sn'],
                    'spec_sn' => $item['spec_sn'],
                    'standby_num' => $item['used_num'],
                ];
                //将常备商品更新数据放到一个数组里
                $arrAllotData[] = $item;
                //常备批次表可用采购量更新数据
                $standbyBatchUpdate[] = [
                    'id' => $item['id'],
                    'available_num' => $item['available_num'],
                ];
            }//end of foreach
        }

        //组装常备批次表可用采购量更新sql
        $rpda = 'jms_real_purchase_detail_audit';
        $rpdaSql = makeUpdateSql($rpda, $standbyBatchUpdate);
        //组装常备商品表可用采购量更新sql
        $group_field = ['spec_sn'];
        $group_by_value = [
            'spec_sn',
            'available_num' => function ($data) {
                $totalNum = array_sum(array_column($data, 'used_num'));
                return $totalNum;
            }
        ];
        $usedGoods = ArrayGroupBy::groupBy($arrAllotData, $group_field, $group_by_value);
        $sg = 'jms_standby_goods';
        $sgSql = makeUpdateSql($sg, $usedGoods, '', 'available_num');
        //组装相应子单预判采购量更新sql
        $mosg = 'jms_mis_order_sub_goods';
        $andWhere = ['sub_order_sn' => $subOrderSn,];
        $mosgSql = makeUpdateSql($mosg, $waitBuyNumData, $andWhere);
        $executeRes = DB::transaction(function () use ($relationData, $rpdaSql, $sgSql, $mosgSql){
            //写入DD子单和常备批次关系表
            DB::table('sub_purchase')->insert($relationData);
            //更新常备批次表可用采购量
            DB::update($rpdaSql['updateSql'],$rpdaSql['bindings']);
            //更新常备商品表可用采购量
            DB::update($sgSql['updateSql'],$sgSql['bindings']);
            //更新相应子单预判采购量
            $modifyRes = DB::update($mosgSql['updateSql'],$mosgSql['bindings']);
            return $modifyRes;
        });
        return $executeRes;

    }




}//end of class
