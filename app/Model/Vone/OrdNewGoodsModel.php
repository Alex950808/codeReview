<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdNewGoodsModel extends Model
{
    public $table = 'ord_new_goods as ong';
    private $field = [
        'ong.id','ong.mis_order_sn','ong.brand_name','ong.goods_name','ong.erp_merchant_no','ong.kl_code',
        'ong.xhs_code','ong.spec_price','ong.spec_weight','ong.exw_discount','ong.goods_number',
        'ong.entrust_time','ong.create_time','ong.is_created','ong.spec_sn','ong.wait_buy_num',
        'ong.sale_discount', 'ong.brand_id','ong.platform_barcode','ong.estimate_weight',
        'ong.erp_ref_no','ong.erp_prd_no',
    ];

    public $status = [
        'NO_REPLENISH' => 1,//未补单
        'YET_REPLENISH' => 2,//已补单
        'NOT_NEED_REPLENISH' => 3,//无需补单
    ];
    public $status_desc = [
        '1' => '未补单',
        '2' => '已补单',
        '3' => '无需补单',
    ];

    public $is_created = [
        'NOT_CREATE' => 0,//未新增
        'YET_CREATE' => 1,//已新增
    ];

    public $created_desc = [
        '0' => '未新增',
        '1' => '已新增',
    ];

    /**
     * description:总单导入-组装写入表数据-专用
     * editor:zhangdong
     * date:2019.04.12
     */
    public function createSaveData($mis_order_sn,$arrData)
    {
        $saveDate = [
            'mis_order_sn'=>$mis_order_sn,
            'brand_id'=>$arrData[0],
            'brand_name'=>$arrData[1],
            'goods_name'=>$arrData[2],
            'platform_barcode'=>$arrData[3],
            'spec_price'=>$arrData[4],
            'spec_weight'=>$arrData[5],
            'exw_discount'=>$arrData[6],
            'goods_number'=>$arrData[7],
            'entrust_time'=>$arrData[8],
            'sale_discount'=>$arrData[9],
            'wait_buy_num'=>$arrData[10],
        ];
        return $saveDate;
    }

    /**
     * description:写入数据
     * editor:zhangdong
     * date:2019.04.12
     */
    public function insertData($arrData)
    {
        $table = cutString($this->table, 0, 'as');
        $insertRes = DB::table($table)->insert($arrData);
        return $insertRes;
    }


    /**
     * description:通过总单号查询对应总单新品
     * author:zhangdong
     * date:2019.04.15
     */
    public function getOrderNewGoods($misOrderSn, array $reqParams = [])
    {
        if (count($reqParams) > 0) {
            $where = $this->createWhere($reqParams);
        }
        $where[] = ['mis_order_sn', $misOrderSn];
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description:创建订单新品筛选条件
     * author:zhangdong
     * date:2019.04.15
     */
    private function createWhere($reqParams)
    {
        $where = [];
        //品牌名称
        if (isset($reqParams['brand_name'])) {
            $where[] = [
                'ong.brand_name', 'like', '%' . trim($reqParams['brand_name'] . '%')
            ];
        }
        //商品名称
        if (isset($reqParams['goods_name'])) {
            $where[] = [
                'ong.goods_name', 'like', '%' . trim($reqParams['goods_name'] . '%')
            ];
        }
        //商家编码
        if (isset($reqParams['erp_merchant_no'])) {
            $where[] = [
                'ong.erp_merchant_no',trim($reqParams['erp_merchant_no'])
            ];
        }
        //考拉编码
        if (isset($reqParams['kl_code'])) {
            $where[] = [
                'ong.kl_code', trim($reqParams['kl_code'])
            ];
        }
        //小红书编码
        if (isset($reqParams['xhs_code'])) {
            $where[] = [
                'ong.xhs_code', trim($reqParams['xhs_code'])
            ];
        }
        //新品是否已经在系统中创建 0 未创建 1，已创建
        if (isset($reqParams['is_created'])) {
            $where[] = [
                'ong.is_created', intval($reqParams['is_created'])
            ];
        }
        //新品状态 1，未补单 2，已补单
        if (isset($reqParams['status'])) {
            $where[] = [
                'ong.status', intval($reqParams['status'])
            ];
        }
        return $where;
    }

    /**
     * description:总单新品-修改新品信息
     * author:zhangdong
     * date:2019.04.15
     */
    public function modifyNewGoodsInfo($misOrderSn, $ng_id, $reqParams)
    {
        $update = [];
        if (isset($reqParams['brand_name'])) {
            $update['brand_name'] = trim($reqParams['brand_name']);
        }
        if (isset($reqParams['goods_name'])) {
            $update['goods_name'] = trim($reqParams['goods_name']);
        }
        if (isset($reqParams['spec_price'])) {
            $update['spec_price'] = trim($reqParams['spec_price']);
        }
        if (isset($reqParams['exw_discount'])) {
            $update['exw_discount'] = floatval($reqParams['exw_discount']);
        }
        if (isset($reqParams['spec_weight'])) {
            $update['spec_weight'] = trim($reqParams['spec_weight']);
        }
        if (isset($reqParams['estimate_weight'])) {
            $update['estimate_weight'] = trim($reqParams['estimate_weight']);
        }
        if (isset($reqParams['erp_ref_no'])) {
            $update['erp_ref_no'] = trim($reqParams['erp_ref_no']);
        }
        if (isset($reqParams['erp_prd_no'])) {
            $update['erp_prd_no'] = trim($reqParams['erp_prd_no']);
        }

        $where = [
            ['mis_order_sn', $misOrderSn],
            ['id', $ng_id],
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:总单新品-根据总单号和新品id查询新品信息
     * author:zhangdong
     * date:2019.04.15
     */
    public function queryNewGoodsMsg($misOrderSn, $ng_id)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
            ['id', $ng_id],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:根据商品编码将所有订单中存在的该新品标记为已创建
     * author:zhangdong
     * date : 2019.04.16
     * @return bool
     */
    public function updateNewGoodsCreated($platformBarcode, $spec_sn)
    {
        $where = [];
        if (!empty($platformBarcode)) {
            $where[] = ['platform_barcode', $platformBarcode];
        }
        if (count($where) === 0) {
            return false;
        }
        $update = [
            'is_created' => 1,
            'spec_sn' => $spec_sn,
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:根据新品临时id和总单号查询商品信息
     * author:zhangdong
     * date : 2019.04.16
     */
    public function getNewGoodsById($misOrderSn, array $arrNgId = [])
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)
            ->whereIn('id', $arrNgId)->get();
        return $queryRes;
    }


    /**
     * description:检查要新增商品的信息是否正常
     * author:zhangdong
     * date : 2019.04.17
     */
    public function checkNewGoodsInfo($newGoodsInfo)
    {
        $brandModel = new BrandModel();
        $brandInfo = $brandModel->getBrandInfoInRedis();
        $none_brand = $none_price = $none_weight = $none_discount = [];
        foreach ($newGoodsInfo as $value) {
            //检查该批商品中是否有已经被新增的
            $is_created = intval($value->is_created);
            if ($is_created == 1) {
                return ['code' => '2067', 'msg' => '请勿选择已经创建过的新品'];
            }
            //组装平台条码为数组
            $gcModel = new GoodsCodeModel();
            $arrGoodsCode = array_unique(array_filter(explode(',', $value->platform_barcode)));
            $goodsCodeInfo = $gcModel->getSpecSnByCode($arrGoodsCode);
            if ($goodsCodeInfo->count() > 0) {
                return ['code' => '2067','msg' => '请勿选择已经创建过的新品'];
            }
            //品牌检查
            $brand_id = trim($value->brand_id);
            $brandSearch = searchTwoArray($brandInfo, $brand_id, 'brand_id');
            $id = intval($value->id);
            if (!isset($brandSearch[0])) {
                $none_brand[] = $id;
            }
            //美金原价检查
            $spec_price = floatval($value->spec_price);
            if ($spec_price <= 0) {
                $none_price[] = $id;
            }
            //规格重量和预估重量检查  update zhangdong 2019.06.28
            $spec_weight = floatval($value->spec_weight);
            $estimate_weight = floatval($value->estimate_weight);
            if ($spec_weight <= 0 && $estimate_weight <= 0) {
                $none_weight[] = $id;
            }
            //exw折扣检查
            $exw_discount = floatval($value->exw_discount);
            if ($exw_discount <= 0) {
                $none_discount[] = $id;
            }
        }//end of foreach
        $checkDesc = '';
        if (count($none_brand) > 0) {
            $checkDesc .= 'id为' . implode($none_brand, ',') . '的新品品牌信息有误 ';
        }
        if (count($none_discount) > 0) {
            $checkDesc .= 'id为' . implode($none_discount, ',') . '的新品exw折扣有误 ';
        }
        if (count($none_price) > 0) {
            $checkDesc .= 'id为' . implode($none_price, ',') . '的新品美金原价有误 ';
        }
        if (count($none_weight) > 0) {
            $checkDesc .= 'id为' . implode($none_weight, ',') . '的新品重量有误';
        }
        if (!empty($checkDesc)) {
            return ['code' => '2067','msg' => $checkDesc];
        }
        return true;

    }//end of function

    /**
     * description:根据商品编码将所有订单中存在的该新品标记为已创建
     * author:zhangdong
     * date : 2019.04.17
     */
    public function updateCreated(array $goods_code = [])
    {
        if (count($goods_code) == 0) {
            return false;
        }
        $update = ['is_created' => 1];
        $where = [
            ['is_created', 0],
        ];
        $updateRes = DB::table($this->table)->where($where)
            ->where(function ($query) use ($goods_code){
                $query->whereIn('platform_barcode', $goods_code);
            })->update($update);
        return $updateRes;
    }

    /**
     * description:修改总单新品状态
     * author:zhangdong
     * date : 2019.04.18
     */
    public function modifyNewStatus($misOrderSn, $status)
    {
        $intStatus = intval($status);
        if ($intStatus == 0) {
            return false;
        }
        $where = [
            ['mis_order_sn', $misOrderSn]
        ];
        $update = [
            'status' => $intStatus
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:检查总单补单信息
     * author:zhangdong
     * date : 2019.04.19
     * @return array
     */
    public function checkIsReplenish($mis_order_sn)
    {
        //查询新品条数
        $newCount = $this->countNewGoodsNum($mis_order_sn);
        //如果无新品则直接返回
        if ($newCount <= 0) {
            $isReplenish = [
                'newGoodsNum' => $newCount,
                'replenishInt' => $this->status['NOT_NEED_REPLENISH'],
                'replenishDesc' => $this->status_desc[$this->status['NOT_NEED_REPLENISH']],
            ];
            return $isReplenish;
        }
        $where = [
            ['mis_order_sn', $mis_order_sn],
            ['status', $this->status['NO_REPLENISH']],
        ];
        $countNum = DB::table($this->table)->where($where)->count();
        $isReplenish = [
            'newGoodsNum' => $newCount,
            'replenishInt' => $this->status['YET_REPLENISH'],
            'replenishDesc' => $this->status_desc[$this->status['YET_REPLENISH']],
        ];
        //如果统计出未补单的条数大于0说明还有未补单的新品，否则说明已全部补单
        if ($countNum > 0) {
            $isReplenish = [
                'newGoodsNum' => $newCount,
                'replenishInt' => $this->status['NO_REPLENISH'],
                'replenishDesc' => $this->status_desc[$this->status['NO_REPLENISH']],
            ];
        }
        return $isReplenish;
    }

    /**
     * description:根据总单号统计新品条数
     * author:zhangdong
     * date : 2019.04.19
     * @return int
     */
    public function countNewGoodsNum($mis_order_sn)
    {
        $where = [
            ['mis_order_sn', $mis_order_sn]
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;

    }

    /**
     * description:根据总单号和新品id获取新品信息
     * author:zhangdong
     * date : 2019.04.22
     */
    public function getNewGoodsInfo($misOrderSn, $ng_id)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
            ['id', $ng_id],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;

    }

    /**
     * description:获取新品创建信息
     * author:zhangdong
     * date : 2019.04.23
     */
    public function getCreatedInfo($newGoodsNum, $misOrderSn)
    {
        //获取已新增新品数量
        $params = [
            'is_created' => $this->is_created['YET_CREATE'],
        ];
        $newGoodsInfo = $this->getOrderNewGoods($misOrderSn, $params);
        //如果已创建新品数量和新品数量相等则说明已全部新增
        $returnMsg = ['finish_int' => 0, 'finish_desc' => '未完成'];
        if ($newGoodsNum == $newGoodsInfo->count()) {
            $returnMsg = ['finish_int' => 1, 'finish_desc' => '已完成'];
        }
        return $returnMsg;
    }


    /**
     * description:校验上传数据
     * autho:zhangdong
     * date:2019.04.25
     */
    public function checkUploadData($oldGoodsData, $newGoodsData)
    {
        //通过总单号查询总单新品信息
        $arrData = objectToArray($oldGoodsData);
        //循环检查导入的数据中各商品是否存在于对应订单中
        $none_id = $updateGoodsData = [];
        foreach($newGoodsData as $key => $newData){
            if ($key == 0) {
                continue;
            }
            $new_id = intval($newData[0]);
            $oldData = searchTwoArray($arrData, $new_id, 'id');
            if (count($oldData) == 0) {
                $none_id[] = $key;
                continue;
            }
            //对修改数据做对比，筛选出被修改的数据
            $modifiedData = self::filterModifiedData($newData, $oldData[0]);
            if (is_array($modifiedData)) {
                $updateGoodsData[] = $modifiedData;
            }
        }
        //将不存在的商品告知用户
        if(count($none_id) > 0){
            $checkRes = '第' . implode($none_id, ',') . '行的商品信息总单新品中不存在';
            return ['code' => '2067','msg' => $checkRes];
        }
        return $updateGoodsData;
    }

    /**
     * description:组装批量更新语句
     * autho:zhangdong
     * date:2019.06.06
     */
    public function createSqlInfo($misOrderSn, $modifyData)
    {
        $arr_update = [];
        foreach ($modifyData as $value) {
            $arr_update[] = [
                'id' => intval($value['id']),
                'brand_id' => intval($value['brand_id']),
                'brand_name' => trim($value['brand_name']),
                'goods_name' => trim($value['goods_name']),
                'spec_price' => floatval($value['spec_price']),
                'spec_weight' => floatval($value['spec_weight']),
                'exw_discount' => floatval($value['exw_discount']),
                'estimate_weight' => floatval($value['estimate_weight']),
                'erp_ref_no' => trim($value['erp_ref_no']),
                'erp_prd_no' => trim($value['erp_prd_no']),
            ];
        }
        $andWhere = [
            'mis_order_sn' => $misOrderSn,
        ];
        $table = 'jms_ord_new_goods';
        $arrSql = makeUpdateSql($table, $arr_update, $andWhere);
        return $arrSql;
    }


    /**
     * description:批量更新语句执行
     * editor:zhangdong
     * date : 2019.06.06
     */
    public function executeSql($modifySqlInfo)
    {
        $executeRes = DB::update($modifySqlInfo['updateSql'], $modifySqlInfo['bindings']);
        return $executeRes;
    }



    /**
     * description 过滤被修改过的数据
     * author zhangdong
     * date 2019.06.06
     */
    private static function filterModifiedData($newData, $oldData)
    {
        $new_brand_id = intval($newData[1]);
        $old_brand_id = intval($oldData['brand_id']);
        $new_brand_name = trim($newData[2]);
        $old_brand_name = trim($oldData['brand_name']);
        $new_goods_name = trim($newData[3]);
        $old_goods_name = trim($oldData['goods_name']);
        $new_spec_price = floatval($newData[6]);
        $old_spec_price = floatval($oldData['spec_price']);
        $new_spec_weight = floatval($newData[7]);
        $old_spec_weight = floatval($oldData['spec_weight']);
        $new_estimate_weight = floatval($newData[8]);
        $old_estimate_weight = floatval($oldData['estimate_weight']);
        $new_exw_discount = floatval($newData[9]);
        $old_exw_discount = floatval($oldData['exw_discount']);
        $new_erp_ref_no = trim($newData[10]);
        $old_erp_ref_no = trim($oldData['erp_ref_no']);
        $new_erp_prd_no = trim($newData[11]);
        $old_erp_prd_no = trim($oldData['erp_prd_no']);
        if (
            $new_brand_id != $old_brand_id ||
            $new_brand_name != $old_brand_name ||
            $new_goods_name != $old_goods_name ||
            $new_spec_price != $old_spec_price ||
            $new_spec_weight != $old_spec_weight ||
            $new_exw_discount != $old_exw_discount ||
            $new_estimate_weight != $old_estimate_weight ||
            $new_erp_ref_no != $old_erp_ref_no ||
            $new_erp_prd_no != $old_erp_prd_no
        ) {
            $modifiedData['id'] = intval($oldData['id']);
            $modifiedData['brand_id'] = $new_brand_id;
            $modifiedData['brand_name'] = $new_brand_name;
            $modifiedData['goods_name'] = $new_goods_name;
            $modifiedData['spec_price'] = $new_spec_price;
            $modifiedData['spec_weight'] = $new_spec_weight;
            $modifiedData['exw_discount'] = $new_exw_discount;
            $modifiedData['estimate_weight'] = $new_estimate_weight;
            $modifiedData['erp_ref_no'] = $new_erp_ref_no;
            $modifiedData['erp_prd_no'] = $new_erp_prd_no;
            return $modifiedData;
        }
        return false;
    }








}//end of class
