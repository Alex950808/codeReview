<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入时间处理包 add by zhangdong on the 2018.06.29
use Carbon\Carbon;
use App\Modules\ArrayGroupBy;


class MisOrderGoodsModel extends Model
{

    protected $table = 'mis_order_goods as mog';

    protected $field = [
        'mog.id','mog.mis_order_sn','mog.goods_name','mog.spec_sn','mog.erp_merchant_no',
        'mog.goods_number','mog.wait_buy_num','mog.stock_num','mog.spec_price',
        'mog.sale_discount','mog.entrust_time','mog.sale_user_account','mog.is_modify',
        'mog.create_time','mog.new_spec_price','mog.exw_discount',
    ];

    /**
     * @description:MIS订单列表-获取订单商品信息
     * @editor:zhangdong
     * @param $mis_order_sn MIS订单号
     * @param $goodsWhere 查询条件
     * date:2018.12.08
     */
    public function getMisOrderGoods($mis_order_sn = '', $goodsWhere = [])
    {
        $field = [
            'mog.goods_name','mog.spec_sn','mog.erp_merchant_no','mog.goods_number',
            'mog.wait_buy_num','mog.spec_price','mog.sale_discount','gs.lock_stock_num',
            'mog.entrust_time','mog.sale_user_account','gs.stock_num','gs.exw_discount',
            DB::raw('(CAST(jms_mog.goods_number as signed) - CAST(jms_gs.stock_num as signed)) as buy_num'),
        ];
        $where = [];
        //对查询条件进行处理-如果发现有本函数适用的条件则加入筛选
        foreach($goodsWhere as $value){
            $field_name = $value[0];
            $is_exit = strpos($field_name,'mog');
            /*此处strpos函数如果查到对应的字符则返回出现的位置，因为mog出现的位置为0
            而0不等于true，所以用false来判断*/
            if($is_exit !== false) $where[] = $value;
        }
        $where[] = ['mog.mis_order_sn',$mis_order_sn];
        $queryRes = DB::table('mis_order_goods as mog')->select($field)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'mog.spec_sn')
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * @description:总单商品数据修改-修改对应的挂靠项
     * @editor:zhangdong
     * @param $type 1,挂靠交付时间 2，挂靠销售账户
     * @param $affValue (挂靠值)
     * @param $upField (修改字段)
     * date:2018.12.08
     */
    public function orderAffiliate($upField, $affValue = '', $misOrderSn, $spec_sn)
    {
        $affValue = trim($affValue);
        $upField = trim($upField);
        if($affValue === '' || empty($upField)) return false;
        $where = [
            ['mis_order_sn', $misOrderSn],
            ['spec_sn', $spec_sn],
        ];
        $update = [
            $upField => $affValue,
        ];
        $executeRes = DB::table('mis_order_goods')->where($where)->update($update);
        return $executeRes;

    }
    /**
     * @description:MIS订单列表-根据订单商品信息的交付时间和销售账号进行拆分
     * @editor:zhangdong
     * @param $orderInfo
     * date:2018.12.10
     */
    public function submenuOrder($goodsInfo)
    {
        $groupData = [];
        foreach($goodsInfo as $key => $value){
            $entrustTime = trim($value->entrust_time);
            $saleUserAccount = trim($value->sale_user_account);
            $entrust_time = session('entrust_time');
            $sale_user_account = session('sale_user_account');
            //刚开始时交付日期和销售账户是空的，所以为空时进行赋值
            if (is_null($entrust_time) && is_null($sale_user_account)) {
                session(['entrust_time' => $entrustTime]);
                session(['sale_user_account' => $saleUserAccount]);
                $entrust_time = session('entrust_time');
                $sale_user_account = session('sale_user_account');
            }
            //只有当交付日期和用户账号都和原来的一样时才归为同一组数据
            if ($entrust_time == $entrustTime && $sale_user_account == $saleUserAccount) {
                $groupData[$entrust_time . $sale_user_account][] = $value;
            } else {
                session(['entrust_time' => $entrustTime]);
                session(['sale_user_account' => $saleUserAccount]);
                $groupData[$entrustTime . $saleUserAccount][] = $value;
            }
        }
        return $groupData;

    }

    /**
     * @description:MIS订单报价-修改销售折扣
     * @editor:zhangdong
     * date:2018.12.20
     * @param $mis_order_sn (总单号)
     * @param $spec_sn(商品规格码)
     * @param $sale_discount(销售折扣)
     */
    public function updateMisGoodsDiscount($mis_order_sn, $spec_sn, $sale_discount)
    {
        $sale_discount = trim($sale_discount);
        $where = [
            ['spec_sn', $spec_sn],
            ['mis_order_sn', $mis_order_sn],
        ];
        $update = [
            'sale_discount' => $sale_discount,
            'is_modify' => 1,
        ];
        $updateRes = DB::table('mis_order_goods')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * @description:总单报价页面-批量修改销售折扣专用
     * @editor:zhangdong
     * @param $mis_order_sn (总单号)
     * date:2019.01.18
     */
    public function getMisGoodsData($mis_order_sn)
    {
        $field = [
            'mog.id','gs.spec_price', 'gs.spec_weight','gs.exw_discount', 'gs.spec_sn',
        ];
        $where[] = ['mog.mis_order_sn', $mis_order_sn];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'mog.spec_sn')
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description:批量更新语句执行
     * editor:zhangdong
     * date : 2019.01.18
     * @return bool
     */
    public function executeSql($strSql, $bindData)
    {
        $executeRes = DB::update($strSql, $bindData);
        return $executeRes;
    }

    /**
     * description:统计总单的商品总数
     * editor:zhangdong
     * date : 2019.01.23
     */
    public function countMisGoodsNum($mis_order_sn = '')
    {
        $where = [];
        if (!empty($mis_order_sn)) {
            $where[] = ['mis_order_sn', $mis_order_sn];
        }
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * description:通过规格码批量修改订单销售折扣
     * editor:zhangdong
     * date : 2019.03.01
     */
    public function modifySaleDiscountBySpec($mis_order_sn, $sale_discount, array $arrSpecSn)
    {
        if (count($arrSpecSn) == 0) {
            return false;
        }
        $where = [
            ['mis_order_sn', $mis_order_sn],
        ];
        $update = [
            'sale_discount'=>$sale_discount,
            'is_modify'=>1,
        ];
        $updateRes = DB::table('mis_order_goods')->where($where)
            ->whereIn('spec_sn', $arrSpecSn)->update($update);
        return $updateRes;

    }

    /**
     * @description:总单详情-批量挂靠
     * @author:zhangdong
     * @param $affValue (挂靠值)
     * @param $upField (修改字段)
     * @return bool
     * date:2019.03.21
     */
    public function batchAffiliate($upField, $affValue = '', $misOrderSn, $arrSpecSn = [])
    {
        $affValue = trim($affValue);
        $upField = trim($upField);
        if($affValue === '' || empty($upField)) return false;
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $update = [
            $upField => $affValue,
        ];
        $executeRes = DB::table('mis_order_goods')->whereIn('spec_sn', $arrSpecSn)
            ->where($where)->update($update);
        return $executeRes;

    }

    /**
     * description:通过给定的规格码查询哪些存在于给定的订单中
     * autho:zhangdong
     * date:2019.04.18
     */
    public function getSpecSn($mis_order_sn, array $arrSpecSn=[])
    {
        $where = [
            ['mis_order_sn', $mis_order_sn],
        ];
        $field = ['spec_sn'];
        $queryRes = DB::table($this->table)->select($field)
            ->where($where)->whereIn('spec_sn', $arrSpecSn)
            ->implode('spec_sn', ',');
        //将查询结果转为数组
        $existArrSpec = explode(',',$queryRes);
        return $existArrSpec;
    }

    /**
     * description:过滤已经存在于订单中的商品
     * autho:zhangdong
     * date:2019.04.18
     */
    public function filterExistGoodsInOrder($newGoodsInfo, $arrMisOrdInfo)
    {
        foreach ($newGoodsInfo as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            //检查规格码是否已经在总单商品信息中，如果在则删除
            $searchRes =  searchTwoArray($arrMisOrdInfo, $spec_sn, 'spec_sn');
            if(count($searchRes) > 0){
                unset($newGoodsInfo[$key]);
            }
        }
        return $newGoodsInfo;
    }

    /**
     * description:根据总单号获取订单商品信息
     * autho:zhangdong
     * date:2019.04.18
     */
    public function getInfoByOrderSn($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn]
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:保存总订单商品数据
     * autho:zhangdong
     * date:2019.04.18
     */
    public function saveOrderGoodsData($orderGoods)
    {
        $orderGoodsTable = cutString($this->table, 0, 'as');
        $insertRes = DB::table($orderGoodsTable)->insert($orderGoods);
        return $insertRes;

    }

    /**
     * description:校验上传数据
     * autho:zhangdong
     * date:2019.04.25
     */
    public function checkUploadData($misOrderSn, $goodsData)
    {
        //通过总单号查询总单商品信息
        $misOrderGoods = $this->queryMisOrderGoods($misOrderSn);
        $arrData = objectToArray($misOrderGoods);
        //循环检查导入的数据中各商品是否存在于对应订单中
        $none_id = $updateGoodsData = [];
        foreach($goodsData as $key => $value){
            if ($key == 0) {
                continue;
            }
            $spec_sn = trim($value[2]);
            $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
            if (count($searchRes) == 0) {
                $none_id[] = $key;
                continue;
            }
            //对修改数量做对比，筛选出被修改的数据
            $oldNum = intval($searchRes[0]['wait_buy_num']);
            $newNum = intval($value[9]);
            $newSpecPrice = floatval($value[11]);
            if ($oldNum != $newNum || $newSpecPrice > 0) {
                //$updateGoodsData中元素的顺序不可调整
                $updateGoodsData[$key] = [
                    'spec_sn' => $spec_sn,
                    'wait_buy_num' => $newNum,
                    'new_spec_price' => $newSpecPrice,
                ];
            }
        }
        //将不存在的商品告知用户
        if(count($none_id) > 0){
            $checkRes = '第' . implode($none_id, ',') . '行的商品信息订单中不存在';
            return ['code' => '2067','msg' => $checkRes];
        }
        return $updateGoodsData;
    }

    /**
     * description:批量更新预判采购量
     * autho:zhangdong
     * date:2019.04.25
     */
    public function updateWaitNum($misOrderSn, $correctData)
    {
        $andWhere = [
            'mis_order_sn' => $misOrderSn,
        ];
        $this->table = 'jms_mis_order_goods';
        $arrSql = makeUpdateSql($this->table, $correctData, $andWhere);
        $updateRes = false;
        if ($arrSql) {
            //开始批量更新
            $strSql = $arrSql['updateSql'];
            $bindData = $arrSql['bindings'];
            $updateRes = $this->executeSql($strSql, $bindData);
        }
        return $updateRes;
    }

    /**
     * description:根据总单号查询总单商品信息
     * autho:zhangdong
     * date:2019.04.25
     */
    public function queryMisOrderGoods($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;

    }

    /**
     * description:根据总单商品数据检查DD子单导入数据
     * autho:zhangdong
     * date:2019.05.21
     */
    public function checkDDSubData($res,$mis_order_sn)
    {
        $misOrderGoods = $this->queryMisOrderGoods($mis_order_sn);
        //组装该总单的sku
        $misOrderGoods = objectToArray($misOrderGoods);
        $arrSpecSn = getFieldArrayVaule($misOrderGoods, 'spec_sn');
        //获取该总单下所有sku的商品编码
        $gcModel = new GoodsCodeModel();
        $goodsCodeInfo = $gcModel->getCodeBySpecSn($arrSpecSn);
        //对商品编码分组去重
        $group_field = ['goods_code'];
        $group_by_value = ['spec_sn', 'goods_code'];
        $goodsCodeInfo = ArrayGroupBy::groupBy(objectToArray($goodsCodeInfo), $group_field, $group_by_value);
        $none_goods = $dd_data = $arraySpecSn = $error_discount = [];
        foreach ($res as $key => $value) {
            if ($key == 0) {
                continue;
            }
            //检查sku是否存在于对应总单中
            $strGoodsCode = trim($value[2]);
            $arrGoodsCode = array_filter(explode(',', $strGoodsCode));
            //根据goodsCode查出spec_sn
            $specSn = $gcModel->getSpecSnByGoodsCode($goodsCodeInfo, $arrGoodsCode);
            //如果没有找到则说明表格中当前行的商品不存在于总单中，记录表格行号
            if (empty($specSn)) {
                $none_goods[] = $key + 1;
                continue;
            }
            $arraySpecSn[] = $specSn;
            //转化表格数据为系统对应数据
            $dd_data[$key]['spec_sn'] = $specSn;
            $sale_discount = floatval($value[5]);
            //检查折扣数据是否正常
            if ($sale_discount <= 0 || $sale_discount >= 1) {
                $error_discount[] = $key + 1;
                continue;
            }
            $dd_data[$key]['sale_discount'] = $sale_discount;
            //能到这一步说明spec_sn肯定在总单中，所以下面不用做空指针异常处理(TODO)
            $searchRes = searchTwoArray($misOrderGoods, $specSn, 'spec_sn');
            $mogInfo = $searchRes[0];
            $dd_data[$key]['goods_name'] = trim($mogInfo['goods_name']);
            $dd_data[$key]['spec_price'] = floatval($mogInfo['spec_price']);
            $dd_data[$key]['exw_discount'] = floatval($mogInfo['exw_discount']);
            $dd_data[$key]['goods_number'] = intval($mogInfo['goods_number']);
            $dd_data[$key]['stock_num'] = intval($mogInfo['stock_num']);
            $dd_data[$key]['erp_merchant_no'] = trim($mogInfo['erp_merchant_no']);
            $dd_data[$key]['platform_barcode'] = $strGoodsCode;
            $dd_data[$key]['dd_num'] = intval($value[6]);
            $dd_data[$key]['cash_num'] = intval($value[7]);
        }
        return [
            'none_goods' => $none_goods,
            'dd_data' => $dd_data,
            'arraySpecSn' => $arraySpecSn,
            'error_discount' => $error_discount,
        ];
    }

    /**
     * description:更新总单商品对应的子单号
     * autho:zhangdong
     * date:2019.05.23
     */
    public function updateMisSubSn($mis_order_sn, $arraySpecSn, $subOrderSn)
    {
        $where = ['mis_order_sn' => $mis_order_sn];
        $update = ['sub_order_sn' => DB::raw("CONCAT(sub_order_sn,',','$subOrderSn')")];
        $updateRes = DB::table($this->table)->where($where)
            ->whereIn('spec_sn', $arraySpecSn)
            ->update($update);
        return $updateRes;

    }

    /**
     * description:校验报价信息上传数据
     * author:zhangdong
     * date:2019.06.12
     */
    public function checkOfferData($misOrderSn, $goodsData)
    {
        //通过总单号查询总单商品信息
        $misOrderGoods = $this->queryMisOrderGoods($misOrderSn);
        $arrData = objectToArray($misOrderGoods);
        //循环检查导入的数据中各商品是否存在于对应订单中
        $none_id = $updateGoodsData = [];
        foreach($goodsData as $key => $value){
            if ($key == 0) {
                continue;
            }
            $spec_sn = trim($value[1]);
            $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
            if (count($searchRes) == 0) {
                $none_id[] = $key + 1;
                continue;
            }
            //对修改数量做对比，筛选出被修改的数据
            $oldSaleDiscount = floatval($searchRes[0]['sale_discount']);
            $newSaleDiscount = floatval($value[17]);
            if ($oldSaleDiscount != $newSaleDiscount) {
                $updateGoodsData[$key] = [
                    'spec_sn' => $spec_sn,
                    'sale_discount' => $newSaleDiscount,
                ];
            }
        }
        //将不存在的商品告知用户
        if(count($none_id) > 0){
            $checkRes = '第' . implode($none_id, ',') . '行的商品信息订单中不存在';
            return ['code' => '2067','msg' => $checkRes];
        }
        return $updateGoodsData;
    }

    /**
     * description:批量更新销售折扣
     * author:zhangdong
     * date:2019.06.12
     */
    public function updateSaleDiscount($misOrderSn, $correctData)
    {
        $andWhere = [
            'mis_order_sn' => $misOrderSn,
        ];
        $this->table = 'jms_mis_order_goods';
        $arrSql = makeUpdateSql($this->table, $correctData, $andWhere);
        $updateRes = false;
        if ($arrSql) {
            //开始批量更新
            $strSql = $arrSql['updateSql'];
            $bindData = $arrSql['bindings'];
            $updateRes = $this->executeSql($strSql, $bindData);
        }
        return $updateRes;
    }

    /**
     * description 获取总单导出信息
     * author zhangdong
     * date 2019.06.13
     */
    public function getExportOrderData($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $field = [
            'spec_sn','goods_name','erp_merchant_no','wait_buy_num',
            'spec_price','entrust_time','platform_barcode'
        ];
        $queryRes = DB::table($this->table)->select($field)->where($where)->get();
        return $queryRes;

    }

    /**
     * description 获取总单导出信息
     * author zhangdong
     * date 2019.06.13
     */
    public function countNewPrice($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
            ['new_spec_price', '>', 0],
        ];
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * description 修改新价格
     * author zhangdong
     * date 2019.06.20
     */
    public function modifyNewSpecPrice($misOrderSn, $specSn, $newSpecPrice)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
            ['spec_sn', $specSn],
        ];
        $update = [
            'new_spec_price' => $newSpecPrice,
        ];
        $modifyRes = DB::table($this->table)->where($where)->update($update);
        return $modifyRes;
    }

    /**
     * description 查询总单下是否存在该SKU
     * author zhangdong
     * date 2019.06.20
     */
    public function countOrderSpec($misOrderSn, $specSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
            ['spec_sn', $specSn],
        ];
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * description 查询价格有变动的SKU
     * author zhangdong
     * date 2019.06.20
     */
    public function getPriceChangeSku($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
            ['new_spec_price', '>', 0],
        ];
        $field = ['spec_sn', 'new_spec_price',];
        $queryRes = DB::table($this->table)->select($field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description 组装价格有变动的SKU
     * author zhangdong
     * date 2019.06.20
     */
    public function makeChangeSku($priceChangeSku)
    {
        //组装商品规格码为数组
        $arrSpecSn = getFieldArrayVaule(objectToArray($priceChangeSku), 'spec_sn');
        //根据规格码查询商品价格
        $gsModel = new GoodsSpecModel();
        $goodsPrice = $gsModel->getGoodsPrice($arrSpecSn);
        $arrGoodsPrice = objectToArray($goodsPrice);
        //对比新旧商品美金原价，剔除没有发生变化的
        $needModifySku = [];
        foreach ($priceChangeSku as $key => $value) {
            $specSn = $value->spec_sn;
            $searchRes = searchTwoArray($arrGoodsPrice, $specSn, 'spec_sn');
            if (count($searchRes) == 0) {
                continue;
            }
            $oldPrice = floatval($searchRes[0]['spec_price']);
            $newPrice = floatval($value->new_spec_price);
            if ($oldPrice != $newPrice) {
                $needModifySku[$key]['spec_sn'] = $specSn;
                $needModifySku[$key]['spec_price'] = $newPrice;
            }
        }
        return $needModifySku;
    }

    /**
     * description 更新SKU的价格到商品规格表和订单商品表
     * author zhangdong
     * date 2019.06.20
     */
    public function modifyGoodsPrice($misOrderSn, $needModifySku)
    {
        $prefix = config('database.connections.mysql.prefix');
        $goodsSpec = $prefix . (new GoodsSpecModel())->getTable();
        $goodsSpecSql = makeUpdateSql($goodsSpec, $needModifySku);
        $misOrderGoods = $prefix . $this->table;
        $andWhere = ['mis_order_sn' => $misOrderSn,];
        $misOrderGoodsSql = makeUpdateSql($misOrderGoods, $needModifySku, $andWhere);
        $modifyRes = DB::transaction(function () use ($goodsSpecSql, $misOrderGoodsSql){
            DB::update($goodsSpecSql['updateSql'],$goodsSpecSql['bindings']);
            $modifyRes = DB::update($misOrderGoodsSql['updateSql'],$misOrderGoodsSql['bindings']);
            return $modifyRes;
        });
        return $modifyRes;
    }

    /**
     * description 统计报价折扣异常的SKU数量
     * author zhangdong
     * date 2019.08.27
     */
    public function checkDiscountNum($mis_order_sn)
    {
        $where = [
            ['mis_order_sn', $mis_order_sn]
        ];
        $countRes = DB::table($this->table)->where($where)->where(function ($query){
            $query->orWhere('sale_discount', '<=', 0);
            $query->orWhere('sale_discount', '>', 1);
        })->count();
        return $countRes;
    }







}//end of class