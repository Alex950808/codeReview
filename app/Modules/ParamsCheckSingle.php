<?php
namespace App\Modules;

/**
 * description 参数统一校验文件
 * design model 单例模式
 * model introduce 单例类不能在其它类中直接实例化，只能被其自身实例化。
 * author zhangdong
 * date 2019.05.22
 */
class ParamsCheckSingle
{
    //创建一个保存类实例的静态成员变量 zhangdong 2019.05.22
    static public $paramsCheck;

    //必须有一个构造函数，并且访问修饰符必须为private zhangdong 2019.05.22
    private function __construct()
    {

    }

    //创建访问这个实例的公共的静态方法 zhangdong 2019.05.22
    static public function paramsCheck()
    {
        if (!self::$paramsCheck) {
            self::$paramsCheck = new self();
        }
        return self::$paramsCheck;
    }

    /**
     * @description:参数校验--查看总单下的子单
     * @author:zhangdong
     * @date : 2019.05.23
     */
    public function getMisOrderSplitParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        if (empty($misOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--DD数据提交接口
     * @author:zhangdong
     * @date : 2019.05.22
     */
    public function submitDDataParams($reqParams)
    {
        $subOrderSn = self::subOrderSn($reqParams);
        $externalSn = self::externalSn($reqParams);
        if (empty($subOrderSn) || empty($externalSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--子单分单
     * @author:zhangdong
     * @date : 2019.05.22
     */
    public function subOrdSubmenuParams($reqParams)
    {
        $subOrderSn = self::subOrderSn($reqParams);
        if (empty($subOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--根据总单号生成分货数据
     * @author:zhangdong
     * @date : 2019.05.30
     */
    public function generalSortDataParams($reqParams)
    {
        $sumDemandSn = self::sumDemandSn($reqParams);
        if (empty($sumDemandSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }


    /**
     * @description:参数校验--查看合单号对应的总分货数据
     * @author:zhangdong
     * @date : 2019.05.31
     */
    public function sumSortDataListParams($reqParams)
    {
        $sumDemandSn = self::sumDemandSn($reqParams);
        if (empty($sumDemandSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--上传采购数据
     * @author:zongxing
     * @date : 2019.05.31
     */
    public function doUploadBatchDataParams($param_info)
    {
        $sumDemandSn = self::sumDemandSn($param_info);
        $methodId = self::methodId($param_info);
        $channelsId = self::channelsId($param_info);
        $portId = self::portId($param_info);
        $supplierId = self::supplierId($param_info);
        $pathWay = self::pathWay($param_info);
        $uploadFile = self::uploadFile($param_info);
        $deliveryTime = self::deliveryTime($param_info);
        $arriveTime = self::arriveTime($param_info);
        $buyTime = self::buyTime($param_info);

        if (empty($sumDemandSn) || empty($methodId) || empty($channelsId) || empty($portId) || empty($supplierId) ||
            (empty($pathWay) && $pathWay != 0) || empty($uploadFile) || empty($deliveryTime) || empty($arriveTime) ||
            empty($buyTime)
        ) {
            self::jsonReturn(['code' => '1002', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--上传常备商品采购数据
     * @author:zongxing
     * @date : 2019.05.31
     */
    public function uploadStandbyGoodsDataParams($param_info)
    {
        $methodId = self::methodId($param_info);
        $channelsId = self::channelsId($param_info);
        $portId = self::portId($param_info);
        $supplierId = self::supplierId($param_info);
        $pathWay = self::pathWay($param_info);
        $uploadFile = self::uploadFile($param_info);
        $deliveryTime = self::deliveryTime($param_info);
        $arriveTime = self::arriveTime($param_info);
        $buyTime = self::buyTime($param_info);

        if (empty($methodId) || empty($channelsId) || empty($portId) || empty($supplierId) ||
            (empty($pathWay) && $pathWay != 0) || empty($uploadFile) || empty($deliveryTime) || empty($arriveTime) ||
            empty($buyTime)
        ) {
            self::jsonReturn(['code' => '1002', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--生成批次单分货数据
     * @author:zhangdong
     * @date : 2019.05.31
     */
    public function makeBatchOrdSortDataParams($reqParams)
    {
        $sumDemandSn = self::sumDemandSn($reqParams);
        $realPurchaseSn = self::realPurchaseSn($reqParams);
        if (empty($sumDemandSn) || empty($realPurchaseSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--查看批次单分货数据
     * @author:zhangdong
     * @date : 2019.05.31
     */
    public function getBatchOrdSortDataParams($reqParams)
    {
        $sumDemandSn = self::sumDemandSn($reqParams);
        $realPurchaseSn = self::realPurchaseSn($reqParams);
        if (empty($sumDemandSn) || empty($realPurchaseSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--手动调整分配数据
     * @author:zhangdong
     * @date : 2019.06.03
     */
    public function handleSortNumParams($reqParams)
    {
        $sumDemandSn = self::sumDemandSn($reqParams);
        $realPurchaseSn = self::realPurchaseSn($reqParams);
        $demandSn = self::demandSn($reqParams);
        $specSn = self::specSn($reqParams);
        if (
            empty($sumDemandSn) ||
            empty($realPurchaseSn) ||
            empty($demandSn) ||
            empty($specSn)
        ) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--导出总单新品
     * @author:zhangdong
     * @date : 2019.06.06
     */
    public function exportOrdNewParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        if (empty($misOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--总单新品批量更新
     * @author:zhangdong
     * @date : 2019.06.06
     */
    public function importOrdNewParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        if (empty($misOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--导入DD子单数据
     * @author:zhangdong
     * @date : 2019.06.10
     */
    public function importDDSubOrdParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        $saleUserAccount = self::saleUserAccount($reqParams);
        $entrustTime = self::entrustTime($reqParams);
        $externalSn = self::externalSn($reqParams);
        $isRepeat = self::isRepeat($reqParams);
        $expectTime = self::expectTime($reqParams);
        if (
            empty($misOrderSn) ||
            empty($saleUserAccount) ||
            empty($entrustTime) ||
            empty($externalSn) ||
            empty($expectTime) ||
            $isRepeat === '' ||
            !in_array($isRepeat, [0, 1])
        ) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--订单商品报价-导出商品信息
     * @author:zhangdong
     * @date : 2019.06.10
     */
    public function exportOfferParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        $pickMarginRate = self::pickMarginRate($reqParams);
        if (empty($misOrderSn) || empty($pickMarginRate)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--订单商品报价-导入商品最终折扣
     * @author:zhangdong
     * @date : 2019.06.12
     */
    public function importOfferParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        if (empty($misOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--总单新品-商品批量新增
     * @author:zhangdong
     * @date : 2019.06.14
     */
    public function batchAddNewGoodsParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        $strNgId = self::strNgId($reqParams);
        if (empty($misOrderSn) || $strNgId == 0) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--总单列表-导入总单
     * @author:zhangdong
     * @date : 2019.06.17
     */
    public function importOrderParams($reqParams)
    {
        $saleUserId = self::saleUserId($reqParams);
        $uploadFile = self::uploadFile($reqParams);
        $mark = self::mark($reqParams);
        $orderType = self::orderType($reqParams);
        if ($saleUserId == 0 || empty($uploadFile) || empty($mark) || $orderType == 0) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }


    /**
     * @description 参数校验--查看有新价格的商品
     * @author zhangdong
     * @date 2019.06.19
     */
    public function getGoodsNewPriceParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        if (empty($misOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description 参数校验--修改价格
     * @author zhangdong
     * @date 2019.06.20
     */
    public function modifyNewPriceParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        $specSn = self::specSn($reqParams);
        if (empty($misOrderSn) || empty($specSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description 参数校验--提交价格
     * @author zhangdong
     * @date 2019.06.20
     */
    public function submitNewPriceParams($reqParams)
    {
        $misOrderSn = self::misOrderSn($reqParams);
        if (empty($misOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description 参数校验--新增版本日志记录
     * @author zhangdong
     * @date 2019.06.20
     */
    public function addVersionLogParams($reqParams)
    {
        $serialNum = self::serialNum($reqParams);
        $content = self::content($reqParams);
        if (empty($serialNum) || empty($content)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description 参数校验--编辑版本日志记录
     * @author zhangdong
     * @date 2019.06.21
     */
    public function editVersionLogParams($reqParams)
    {
        $logId = self::logId($reqParams);
        $content = self::content($reqParams);
        $serialNum = self::serialNum($reqParams);
        if (empty($serialNum) || empty($content) || $logId == 0) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description 参数校验--编辑版本日志记录
     * @author zhangdong
     * @date 2019.07.18
     */
    public function generateProfitParams($reqParams)
    {
        $settleDateType = self::settleDateType($reqParams);
        $startDate = self::startDate($reqParams);
        $endDate = self::endDate($reqParams);
        $channelId = self::channelsId($reqParams);
        $formula_mode = self::formula_mode($reqParams);
        if ($settleDateType == 0 || empty($startDate) || empty($endDate) || empty($channelId) ||
            empty($formula_mode)
        ) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description 参数校验--总单详情-商品报价
     * @author zhangdong
     * @date 2019.08.23
     */
    public function ordGoodsOfferParams($reqParams)
    {
        $saleUserId = self::saleUserId($reqParams);
        $misOrderSn = self::misOrderSn($reqParams);
        if ($saleUserId == 0 || empty($misOrderSn)) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description:参数校验--单个新增常备商品
     * @author:zhangdong
     * @date : 2019.09.16
     */
    public function addStandbyGoodsParams($reqParams)
    {
        $goodsName = self::goodsName($reqParams);
        $platformBarcode = self::platformBarcode($reqParams);
        $maxNum = self::maxNum($reqParams);
        if (empty($goodsName) || empty($platformBarcode) || $maxNum <= 0) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }

    /**
     * @description 参数校验--导出商品最终报价
     * @author zhangdong
     * @date 2019.10.21
     */
    public function exportGoodsOfferParams($reqParams)
    {
        $export_type = [
            'EMS_DOLLAR' => 1,
            'EMS_RMB' => 2,
            'AIRPORT_DOLLAR' => 3,
            'AIRPORT_RMB' => 4,
        ];
        $rmbRate = self::rmbRate($reqParams);
        $koreanRate = self::koreanRate($reqParams);
        $rmbKoreanRate = self::rmbKoreanRate($reqParams);
        $exportType = self::exportType($reqParams);
        if ($rmbRate <= 0 || $koreanRate <= 0 || $rmbKoreanRate <= 0 || !isset($export_type[$exportType])) {
            self::jsonReturn(['code' => '2005', 'msg' => '参数错误']);
        }
        return true;
    }









//--------------------------------------------(TODO)私有函数区--------------------------------------------
    /**
     * @description:json格式信息返回
     * @author:zhangdong
     * @date : 2019.05.22
     */
    private static function jsonReturn($returnMsg)
    {
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($returnMsg, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @description:参数校验--子单号
     * @author:zhangdong
     * @date : 2019.05.22
     * @return mixed
     */
    private static function subOrderSn($reqParams)
    {
        $subOrderSn = isset($reqParams['sub_order_sn']) ? $reqParams['sub_order_sn'] : '';
        return $subOrderSn;
    }

    /**
     * @description:参数校验--外部订单号
     * @author:zhangdong
     * @date : 2019.05.22
     * @return mixed
     */
    private static function externalSn($reqParams)
    {
        $externalSn = isset($reqParams['external_sn']) ? $reqParams['external_sn'] : '';
        return $externalSn;
    }

    /**
     * @description:参数校验--订单备注
     * @author:zhangdong
     * @date : 2019.05.22
     * @return mixed
     */
    private static function ramark($reqParams)
    {
        $ramark = isset($reqParams['ramark']) ? $reqParams['ramark'] : '';
        return $ramark;
    }


    /**
     * @description:参数校验--总单号
     * @author:zhangdong
     * @date : 2019.05.23
     */
    private static function misOrderSn($reqParams)
    {
        $misOrderSn = isset($reqParams['mis_order_sn']) ? $reqParams['mis_order_sn'] : '';
        return $misOrderSn;
    }

    /**
     * @description:参数校验--合单号
     * @author:zhangdong
     * @date : 2019.05.30
     */
    private static function sumDemandSn($reqParams)
    {
        $sumDemandSn = isset($reqParams['sum_demand_sn']) ? $reqParams['sum_demand_sn'] : '';
        return $sumDemandSn;
    }

    /**
     * @description:参数校验--合单号
     * @author:zhangdong
     * @date : 2019.05.30
     */
    private static function realPurchaseSn($reqParams)
    {
        $realPurchaseSn = isset($reqParams['real_purchase_sn']) ? $reqParams['real_purchase_sn'] : '';
        return $realPurchaseSn;
    }

    /**
     * @description:参数校验--采购方式id
     * @author:zongxing
     * @date : 2019.05.31
     * @return mixed
     */
    private static function methodId($param_info)
    {
        $methodId = isset($param_info['method_id']) ? $param_info['method_id'] : '';
        return $methodId;
    }

    /**
     * @description:参数校验--采购渠道id
     * @author:zongxing
     * @date : 2019.05.31
     * @return mixed
     */
    private static function channelsId($param_info)
    {
        $channelsId = isset($param_info['channels_id']) ? $param_info['channels_id'] : '';
        return $channelsId;
    }


    /**
     * @description:参数校验--计算方式
     * @author:zongxing
     * @date : 2019.05.31
     * @return mixed
     */
    private static function formula_mode($param_info)
    {
        $formula_mode = isset($param_info['formula_mode']) ? $param_info['formula_mode'] : '';
        return $formula_mode;
    }

    /**
     * @description:参数校验--港口id
     * @author:zongxing
     * @date : 2019.05.31
     * @return mixed
     */
    private static function portId($param_info)
    {
        $portId = isset($param_info['port_id']) ? $param_info['port_id'] : '';
        return $portId;
    }

    /**
     * @description:参数校验--供应商id
     * @author:zongxing
     * @date : 2019.05.31
     * @return mixed
     */
    private static function supplierId($param_info)
    {
        $supplierId = isset($param_info['supplier_id']) ? $param_info['supplier_id'] : '';
        return $supplierId;
    }

    /**
     * @description:参数校验--自提或邮寄方式
     * @author:zongxing
     * @date : 2019.05.31
     * @return mixed
     */
    private static function pathWay($param_info)
    {
        $pathWay = isset($param_info['path_way']) ? intval($param_info['path_way']) : '';
        return $pathWay;
    }

    /**
     * @description:参数校验--上传文件
     * @author:zongxing
     * @date : 2019.05.31
     * @return mixed
     */
    private static function uploadFile($param_info)
    {
        $uploadFile = isset($param_info['upload_file']) ? $param_info['upload_file'] : '';
        return $uploadFile;
    }

    /**
     * @description:参数校验--提货时间
     * @author:zongxing
     * @date : 2019.06.01
     * @return mixed
     */
    private static function deliveryTime($param_info)
    {
        $deliveryTime = isset($param_info['delivery_time']) ? $param_info['delivery_time'] : '';
        return $deliveryTime;
    }

    /**
     * @description:参数校验--到货时间
     * @author:zongxing
     * @date : 2019.06.01
     * @return mixed
     */
    private static function arriveTime($param_info)
    {
        $arriveTime = isset($param_info['arrive_time']) ? $param_info['arrive_time'] : '';
        return $arriveTime;
    }

    /**
     * @description:参数校验--购买日时间
     * @author:zongxing
     * @date : 2019.06.01
     * @return mixed
     */
    private static function buyTime($param_info)
    {
        $buyTime = isset($param_info['buy_time']) ? $param_info['buy_time'] : '';
        return $buyTime;
    }

    /**
     * @description:参数校验--需求单号
     * @author:zhangdong
     * @date : 2019.06.03
     */
    private static function demandSn($param_info)
    {
        $demandSn = isset($param_info['demand_sn']) ? $param_info['demand_sn'] : '';
        return $demandSn;
    }

    /**
     * @description:参数校验--规格码
     * @author:zhangdong
     * @date : 2019.06.03
     */
    private static function specSn($param_info)
    {
        $specSn = isset($param_info['spec_sn']) ? $param_info['spec_sn'] : '';
        return $specSn;
    }

    /**
     * @description:参数校验--销售账号
     * @author:zhangdong
     * @date : 2019.06.10
     */
    private static function saleUserAccount($param_info)
    {
        $saleUserAccount = isset($param_info['sale_user_account']) ? $param_info['sale_user_account'] : '';
        return $saleUserAccount;
    }

    /**
     * @description:参数校验--交付日期
     * @author:zhangdong
     * @date : 2019.06.10
     */
    private static function entrustTime($param_info)
    {
        $entrustTime = isset($param_info['entrust_time']) ? $param_info['entrust_time'] : '';
        return $entrustTime;
    }

    /**
     * @description:参数校验--自采毛利率
     * @author:zhangdong
     * @date : 2019.06.10
     */
    private static function pickMarginRate($param_info)
    {
        $pickMarginRate = isset($param_info['pick_margin_rate']) ? $param_info['pick_margin_rate'] : '';
        return $pickMarginRate;
    }

    /**
     * @description:参数校验--销售用户id
     * @author:zhangdong
     * @date : 2019.06.11
     */
    private static function saleUserId($param_info)
    {
        $saleUserId = isset($param_info['sale_user_id']) ? $param_info['sale_user_id'] : 0;
        return $saleUserId;
    }

    /**
     * @description:参数校验--总单新品id
     * @author:zhangdong
     * @date : 2019.06.14
     */
    private static function strNgId($param_info)
    {
        $strNgId = isset($param_info['str_ng_id']) ? $param_info['str_ng_id'] : 0;
        return $strNgId;
    }

    /**
     * @description:参数校验--总单标记
     * @author:zhangdong
     * @date : 2019.06.17
     */
    private static function mark($param_info)
    {
        $mark = isset($param_info['mark']) ? $param_info['mark'] : '';
        return $mark;
    }

    /**
     * @description:参数校验--订单类型
     * @author:zhangdong
     * @date : 2019.06.17
     */
    private static function orderType($param_info)
    {
        $orderType = isset($param_info['order_type']) ? $param_info['order_type'] : 0;
        return $orderType;
    }

    /**
     * @description:参数校验--商品新美金原价
     * @author:zhangdong
     * @date : 2019.06.20
     */
    private static function newSpecPrice($param_info)
    {
        $newSpecPrice = isset($param_info['new_spec_price']) ? $param_info['new_spec_price'] : 0;
        return $newSpecPrice;
    }

    /**
     * @description:参数校验--版本号
     * @author:zhangdong
     * @date : 2019.06.20
     */
    private static function serialNum($param_info)
    {
        $serialNum = isset($param_info['serial_num']) ? $param_info['serial_num'] : '';
        return $serialNum;
    }

    /**
     * @description:参数校验--版本内容
     * @author:zhangdong
     * @date : 2019.06.20
     */
    private static function content($param_info)
    {
        $content = isset($param_info['content']) ? $param_info['content'] : '';
        return $content;
    }

    /**
     * @description:参数校验--版本日志ID
     * @author:zhangdong
     * @date : 2019.06.21
     */
    private static function logId($param_info)
    {
        $logId = isset($param_info['log_id']) ? $param_info['log_id'] : 0;
        return $logId;
    }

    /**
     * @description:参数校验--是否查重( 0 不检查 1 检查)
     * @author:zhangdong
     * @date : 2019.06.24
     */
    private static function isRepeat($param_info)
    {
        $isRepeat = isset($param_info['is_repeat']) ? $param_info['is_repeat'] : '';
        return $isRepeat;
    }

    /**
     * @description:参数校验--结算日期类型 1，提货日 2，购买日
     * @author:zhangdong
     * @date : 2019.07.18
     */
    private static function settleDateType($param_info)
    {
        $settleDateType = isset($param_info['settle_date_type']) ? $param_info['settle_date_type'] : 0;
        return $settleDateType;
    }

    /**
     * @description:参数校验--结算开始日期
     * @author:zhangdong
     * @date : 2019.07.18
     */
    private static function startDate($param_info)
    {
        $startDate = isset($param_info['start_date']) ? $param_info['start_date'] : '';
        return $startDate;
    }

    /**
     * @description:参数校验--结算结束日期
     * @author:zhangdong
     * @date : 2019.07.18
     */
    private static function endDate($param_info)
    {
        $endDate = isset($param_info['end_date']) ? $param_info['end_date'] : '';
        return $endDate;
    }

    /**
     * @description 参数校验--商品名称
     * @author zhangdong
     * @date 2019.09.16
     */
    private static function goodsName($param_info)
    {
        $goodsName = isset($param_info['goods_name']) ? $param_info['goods_name'] : '';
        return $goodsName;
    }

    /**
     * @description 参数校验--平台条码
     * @author zhangdong
     * @date 2019.09.16
     */
    private static function platformBarcode($param_info)
    {
        $platformBarcode = isset($param_info['platform_barcode']) ? $param_info['platform_barcode'] : '';
        return $platformBarcode;
    }

    /**
     * @description 参数校验--最大采购量
     * @author zhangdong
     * @date 2019.09.16
     */
    private static function maxNum($param_info)
    {
        $maxNum = isset($param_info['max_num']) ? $param_info['max_num'] : '';
        return $maxNum;
    }

    /**
     * @description 参数校验--期望到仓日
     * @author zhangdong
     * @date  2019.09.25
     */
    private static function expectTime($param_info)
    {
        $expectTime = isset($param_info['expect_time']) ? $param_info['expect_time'] : '';
        return $expectTime;
    }

    /**
     * @description 参数校验--人民币汇率
     * @author zhangdong
     * @date  2019.10.21
     */
    private static function rmbRate($param_info)
    {
        $rmbRate = isset($param_info['rmbRate']) ? $param_info['rmbRate'] : 0;
        return $rmbRate;
    }

    /**
     * @description 参数校验--韩币汇率
     * @author zhangdong
     * @date  2019.10.21
     */
    private static function koreanRate($param_info)
    {
        $koreanRate = isset($param_info['koreanRate']) ? $param_info['koreanRate'] : 0;
        return $koreanRate;
    }

    /**
     * @description 参数校验--人民币兑换韩币汇率
     * @author zhangdong
     * @date  2019.10.21
     */
    private static function rmbKoreanRate($param_info)
    {
        $rmbKoreanRate = isset($param_info['rmbKoreanRate']) ? $param_info['rmbKoreanRate'] : 0;
        return $rmbKoreanRate;
    }

    /**
     * @description 参数校验--文件导出类型
     * @author zhangdong
     * @date  2019.10.21
     */
    private static function exportType($param_info)
    {
        $rmbKoreanRate = isset($param_info['exportType']) ? $param_info['exportType'] : '';
        return $rmbKoreanRate;
    }








}//end of class