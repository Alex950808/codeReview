<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use App\Model\Vone\BrandModel;
use App\Model\Vone\DemandModel;
use App\Model\Vone\DiscountTypeInfoModel;
use App\Model\Vone\GoodsCodeModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\MarginRateModel;
use App\Model\Vone\OperateLogModel;
use App\Model\Vone\OrdNewGoodsModel;
use App\Model\Vone\SaleUserAccountModel;
use App\Model\Vone\SpotGoodsModel;
use App\Model\Vone\SpotOrderModel;
use App\Model\Vone\DiscountTypeModel;
use App\Model\Vone\SubPurchaseModel;
use App\Modules\Erp\ErpApi;
use App\Modules\ParamsCheckSingle;
use Dingo\Api\Http\Request;
use Exception;

//引入时间及日期处理包 add by zhangdong on the 2018.12.06
use Carbon\Carbon;
//引入日志库文件 add by zhangdong on the 2018.12.06
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
//MIS订单模型
use App\Model\Vone\MisOrderModel;
use App\Model\Vone\MisOrderGoodsModel;
//MIS子订单模型
use App\Model\Vone\MisOrderSubModel;
use App\Model\Vone\MisOrderSubGoodsModel;
//销售用户模型
use App\Model\Vone\SaleUserModel;
//商品模型
use App\Model\Vone\GoodsModel;
use App\Model\Vone\ConversionStatisticsModel;

//引入Excel执行通用文件类
use App\Modules\Excel\ExcuteExcel;

use JWTAuth;

//create by zhangdong on the 2018.12.06

/**
 * Class MisOrderController
 * @package App\Api\Vone\Controllers\Sell
 */
class MisOrderController extends BaseController
{

    
    private $moModel;
    private $executeExcel;
    private $mogModel;
    private $mosModel;
    private $mosgModel;

    /**
     * description:本控制器构造方法
     * author:zhangdong
     * date : 2019.01.02
     */
    public function __construct()
    {
        $this->moModel = new MisOrderModel();
        $this->executeExcel = new ExcuteExcel();
        $this->mogModel = new MisOrderGoodsModel();
        $this->mosModel = new MisOrderSubModel();
        $this->mosgModel = new MisOrderSubGoodsModel();
    }

    /**
     * description:MIS订单列表
     * author:zhangdong
     * date : 2018.12.08
     * return Object
     */
    public function getMisOrderList(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $misOrderList = $this->moModel->getMisOrderList($reqParams, $pageSize);
        $orderStatistics = $this->moModel->orderStatistics();
        //根据部门id获取其对应的销售客户
        $suModel = new SaleUserModel();
        $loginInfo = JWTAuth::toUser()['original'];
        $departId = $loginInfo['department_id'];
        $saleUserInfo = $suModel->getSaleUserByDepartId($departId);
        $returnMsg = [
            'orderStatistics' => $orderStatistics,
            'status' => $this->moModel->status,//订单状态
            'saleUserInfo' => $saleUserInfo,//当前部门对应的销售用户
            'orderType' => $this->moModel->order_type,
            'orderList' => $misOrderList,
        ];
        return response() ->json($returnMsg);
    }

    /**
     * description:MIS订单列表-导入总单页面
     * author:zhangdong
     * date : 2018.12.08
     * return Object
     */
    public function importOrderPage(Request $request)
    {
        //根据部门id获取销售用户信息
        $saleUserModel = new SaleUserModel();
        $loginUserInfo = $request->user();
        $depart_id = intval($loginUserInfo->department_id);
        $saleUserInfo = $saleUserModel->getSaleUserByDepartId($depart_id);
        $returnMsg = [
            'saleUserInfo' => $saleUserInfo,
        ];
        return response() ->json($returnMsg);

    }

    /**
     * description:导入总单
     * author:zhangdong
     * date : 2018.12.08
     * return Object
     */
    public function importOrder(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->importOrderParams($reqParams);
        $loginUserInfo = $request->user();
        $depart_id = intval($loginUserInfo->department_id);//部门id
        $saleUserId = intval($reqParams['sale_user_id']);//销售用户id
        $misOrderModel = new MisOrderModel();
        $orderType = intval($reqParams['order_type']);//订单类型
        if (!isset($misOrderModel->order_type[$orderType])) {
            return response()->json(['code' => 2067, 'msg' => '您选择的订单类型有误']);
        }
        $file = $_FILES;
        //检查上传文件是否合格
        $executeExcel = new ExcuteExcel();
        $fileName = '总单导入模板';
        $res = $executeExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = [
            '品牌ID','品牌','商品名称','平台条码','美金原价','商品重量','EXW折扣',
            '需求量','交付时间','销售折扣','预判采购量',
        ];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response()->json($returnMsg);
            }
        }
        //如果上传表格数据为空则抛异常
        if (count($res[1]) == 0) {
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //对上传的数据进行校验，如果有新品则告知用户并且将新品保存
        $goodsModel = new GoodsModel();
        $checkGoodsRes = $goodsModel->checkGoods($res);
        if ($checkGoodsRes === false) {
            return response()->json(['code' => '2058','msg' => '上传文件中商家编码，品牌ID等一些必填信息不能为空']);
        }
        if (count($checkGoodsRes['arrGoodsInfo']) == 0) {
            return response()->json(['code' => '2067','msg' => '表格中均是新品，请新增后再导入']);
        }
        $goodsInfo = $checkGoodsRes['arrGoodsInfo'];
        //检查导入商品是否有重复的，如果有则返回重复SKU
        $filter_field = 'erp_merchant_no';
        $uniqueGoodsInfo = filter_duplicate($goodsInfo,$filter_field);
        if (count($uniqueGoodsInfo) >= 1) {
            $duplicateGoods = implode($uniqueGoodsInfo,',');
            $returnMsg = ['code' => '2059', 'msg' => '商家编码为 ' . $duplicateGoods . ' 的商品为重复数据'];
            return response()->json($returnMsg);
        }

        //根据上传YD数据组装YD订单数据
        $mark = trim($reqParams['mark']);//订单标记
        $orderData = $misOrderModel->createOrderData(
            $goodsInfo, $depart_id, $saleUserId, '', $orderType, $mark
        );
        //组装新品数据
        $mis_order_sn = $orderData['orderData']['mis_order_sn'];
        $ongModel = new OrdNewGoodsModel();
        $arrNewGoods = [];
        foreach ($checkGoodsRes['newGoods'] as $value) {
            $arrNewGoods[] = $ongModel->createSaveData($mis_order_sn, $value);
        }
        //将订单数据保存
        $saveRes = $misOrderModel->saveOrderData($orderData, $arrNewGoods);
        if ($saveRes == false) {
            $returnMsg = ['code' => '2001', 'msg' => '上传失败'];
            return response() -> json($returnMsg);
        }
        //将上传文件保存到指定位置
        $saveUploadRes = $executeExcel->saveUploadFile($file, $mis_order_sn);
        //写入日志
        $bus_desc = $saveUploadRes ? "文件已保存至：$saveUploadRes" : '文件保存失败';
        $bus_desc .= ' -- bus_value为总单号';
        //总单号
        $mis_order_sn = $orderData['orderData']['mis_order_sn'];
        $ope_module_name = '总单列表-导入总单';
        $olModel = new OperateLogModel();
        $module_id = $olModel->module['SALE'];
        $olModel->recordLog($bus_desc,$mis_order_sn,$ope_module_name,$module_id);
        $newGoodsDesc = '';
        if (count($arrNewGoods) > 0) {
            $newGoodsDesc = '--共计新品' . count($arrNewGoods) . '条，请到总单详情查看';
        }
        $returnMsg = ['code' => '2000', 'msg' => '上传成功' . '-总单号-'. $mis_order_sn . $newGoodsDesc];
        return response() -> json($returnMsg);
    }


    /**
     * description:MIS订单列表-订单商品报价
     * author:zhangdong
     * date : 2018.12.07
     * return Object
     */
    public function ordGoodsOffer(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->ordGoodsOfferParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);//总单号
        $sale_user_id = intval($reqParams['sale_user_id']);//销售用户id
        $store_id = isset($reqParams['store_id']) ? intval($reqParams['store_id']) : 1002;//仓库id
        //获取报价用的基本信息
        $goodsModel = new GoodsModel();
        $loginUserInfo = $request->user();
        //部门id
        $depart_id = intval($loginUserInfo->department_id);
        $offerInfo = $goodsModel->getOfferInfo($depart_id);
        //检查部门是否存在
        $curDepart = searchTwoArray($offerInfo['departmentInfo'], $depart_id, 'department_id');
        if(empty($curDepart)){
            return response() -> json(['code' => '2035', 'msg' => '部门信息有误，请联系管理员']);
        }
        //获取自采毛利率档位信息
        $pickMarginRate = $offerInfo['pickMarginRate'];
        $arrPickRate = [];
        foreach ($pickMarginRate as $item) {
            $arrPickRate[] = sprintf('%.0f%%', trim($item['pick_margin_rate']));
        }
        //获取费用信息
        $chargeInfo = $offerInfo['chargeInfo'];
        $arrCharge = [];
        $totalCharge = 0;
        $dtModel = new DiscountTypeModel();
        foreach ($chargeInfo as $item) {
            $arrCharge[] = [sprintf('%.0f%%', trim($item['charge_rate'])) => trim($item['charge_name'])];
            $totalCharge += $item['charge_rate'];
        }
        $arrCharge[] = [sprintf('%.0f%%', $totalCharge) => '费用合计'];
        //根据订单号获取订单基本信息
        $misOrderModel = new MisOrderModel();
        $orderInfo = $misOrderModel->getOrderInfo($misOrderSn);
        //根据订单号获取订单商品信息
        $orderGoodsInfo = $misOrderModel->getOrderGoodsInfo($misOrderSn, $reqParams);
        $goodsNum = $orderGoodsInfo->count();
        if ($orderGoodsInfo->count() > 0) {
            $goodsModel->userGoodsList(
                $orderGoodsInfo, $pickMarginRate, $chargeInfo,$offerInfo['goodsHouseInfo'], $store_id
            );
            $orderGoodsInfo = $dtModel->getFinalDiscount(objectToArray($orderGoodsInfo));
        }
        //查询对应销售用户最近15天的BD和DD折扣
        $mosModel = new MisOrderSubModel();
        $mosGoodsInfo = $mosModel->getMosGoodsInfo($sale_user_id);
        $arrData = objectToArray($mosGoodsInfo);
        $gcModel = new GoodsCodeModel();
        $code_type = $gcModel->getCodeType($sale_user_id);
        foreach ($orderGoodsInfo as $key => $value) {
            $spec_sn = trim($value['spec_sn']);
            //在总单对应的子单商品信息中查出BD和DD折扣
            $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
            $orderGoodsInfo[$key]['bd_sale_discount'] = 0;
            $orderGoodsInfo[$key]['dd_sale_discount'] = 0;
            if (count($searchRes) > 0) {
                $orderGoodsInfo[$key]['bd_sale_discount'] = trim($searchRes[0]['bd_sale_discount']);
                $orderGoodsInfo[$key]['dd_sale_discount'] = trim($searchRes[0]['dd_sale_discount']);
            }
            //根据销售用户id组装考拉或小红书等编码
            $where = [
                ['spec_sn',$spec_sn],
                ['code_type',$code_type],
            ];
            $goodsCode = $gcModel->getStrCodeByWhere($where);
            if (empty($goodsCode)) {
                $goodsCode = $value['erp_merchant_no'];
            }
            $orderGoodsInfo[$key]['goodsCode'] = $goodsCode;
            //免税店折扣
            $discountDFS = $dtModel->makeDiscountDFS($value);
            $orderGoodsInfo[$key]['abk_discount'] = (float)$discountDFS['abk_discount'];
            $orderGoodsInfo[$key]['lt_discount'] = (float)$discountDFS['lt_discount'];
            $orderGoodsInfo[$key]['xl_discount'] = (float)$discountDFS['xl_discount'];
            unset($orderGoodsInfo[$key]['channels_info']);
        }
        $returnMsg = [
            'orderInfo' => $orderInfo,
            'goodsNum' => $goodsNum,
            'goodsOfferInfo' => $orderGoodsInfo,
            'erpInfo' => $offerInfo['goodsHouseInfo'],
            'arrPickRate' => $arrPickRate,
            'arrCharge' => $arrCharge,
        ];
        return response() -> json($returnMsg);
    }

    /**
     * description:MIS订单列表-订单挂靠-给总单商品挂靠交付时间和销售账户及数量调整
     * author:zhangdong
     * date : 2018.12.08
     * return Object
     */
    public function orderAffiliate(Request $request)
    {
        $reqParams = $request -> toArray();
        //总单号
        $misOrderSn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        //规格码
        $spec_sn = isset($reqParams['spec_sn']) ?
            trim($reqParams['spec_sn']) : '';
        //挂靠类型 1,挂靠交付时间 2，挂靠销售账户 3，修改待采量
        $type =  isset($reqParams['type']) ?
            intval($reqParams['type']) : '';
        if (empty($misOrderSn) || empty($spec_sn) || empty($type)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        switch ($type){
            case 1 ://1,挂靠交付时间
                $affValue = isset($reqParams['entrust_time']) ?
                    trim($reqParams['entrust_time']) : '';
                $field = 'entrust_time';
                break;
            case 2 ://2，挂靠销售账户
                $affValue = isset($reqParams['sale_user_account']) ?
                    trim($reqParams['sale_user_account']) : '';
                $field = 'sale_user_account';
                break;
            case 3 ://3，修改预判采购量
                $affValue = isset($reqParams['wait_buy_num']) ?
                    intval($reqParams['wait_buy_num']) : '';
                $field = 'wait_buy_num';
                break;
            default :
                $affValue = '';
                $field = '';
        }
        if($affValue === ''){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //修改对应的挂靠项
        $mogModel = new MisOrderGoodsModel();
        $updateRes = $mogModel->orderAffiliate($field, $affValue, $misOrderSn, $spec_sn);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:MIS订单列表-拆分订单
     * author:zhangdong
     * date : 2018.12.10
     * return Object
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderSubmenu(Request $request)
    {
        $reqParams = $request -> toArray();
        //总单号
        $mis_order_sn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        if(empty($mis_order_sn)){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询总单补单相关信息
        $ongModel = new OrdNewGoodsModel();
        $isReplenish = $ongModel->checkIsReplenish($mis_order_sn);
        if ($isReplenish['replenishInt'] === 1) {
            $returnMsg = ['code' => '2067','msg' => '当前总单还有未补单的数据'];
            return response()->json($returnMsg);
        }
        //检查总单状态是否正常
        $moModel = new MisOrderModel();
        $misOrderInfo = $moModel->getOrderInfo($mis_order_sn);
        $status = intval($misOrderInfo[0] -> status);
        $is_offer = intval($misOrderInfo[0] -> is_offer);
        $is_advance = intval($misOrderInfo[0] -> is_advance);
        //只有待拆分且完成报价和预判操作后的总单才可拆单
        if($status !== 2 || $is_offer === 0 || $is_advance === 0){
            $returnMsg = ['code' => '2049','msg' => '只有完成报价和预判才能执行该操作'];
            return response()->json($returnMsg);
        }
        //先检查该订单是否已经分单
        $mosModel = new MisOrderSubModel();
        $queryRes = $mosModel->querySubOrder($mis_order_sn);
        if($queryRes->count() > 0){
            $returnMsg = ['code' => '2048','msg' => '重复拆单'];
            return response()->json($returnMsg);
        }
        //通过订单号查询订单商品信息
        $mogModel = new MisOrderGoodsModel();
        $goodsInfo = $mogModel->getMisOrderGoods($mis_order_sn);
        //通过订单商品信息进行拆单,返回商品拆单数据
        $submenuData = $mogModel->submenuOrder($goodsInfo);
        //通过商品拆单数据组装子订单
        $makeSubOrdData = $mosModel->makeSubOrdData($submenuData, $mis_order_sn);
        //将组装好的子单数据保存
        $saveRes = $mosModel->saveSubOrderData($makeSubOrdData);
        $updateRes = false;
        if($saveRes){
            //保存成功后将总单状态改为已结束
            //订单状态 1，待挂靠2，待拆分3，已结束
            $status = 3;
            $updateRes = $moModel->updateStatus($mis_order_sn, $status);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }//end of function

    /**
     * description:MIS子订单列表
     * author:zhangdong
     * date : 2018.12.11
     * return Object
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubList(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $subOrderModel = new MisOrderSubModel();
        //排序规则 1，创建时间 2，交付日期
        $orderType = isset($reqParams['orderType']) ? intval($reqParams['orderType']) : 0;
        //排序顺序 1，倒序 2 正序
        $orderNum = isset($reqParams['orderNum']) ? intval($reqParams['orderNum']) : '0';
        $subOrderList = $subOrderModel->getSubOrderList($reqParams, $pageSize, $orderType, $orderNum);
        $orderStatus =  $subOrderModel->status;
        $loginInfo = JWTAuth::toUser()['original'];
        $departId = $loginInfo['department_id'];
        $suModel = new SaleUserModel();
        $saleUserInfo = $suModel->getSaleUserByDepartId($departId);
        $returnMsg = [
            'subOrderList' => $subOrderList,
            'saleUserInfo' => $saleUserInfo,
            'orderStatus' => $orderStatus,
        ];
        return response() ->json($returnMsg);
    }


    /**
     * description:MIS订单列表-获取总订单详情
     * author:zhangdong
     * date : 2018.12.11
     * return Object
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMisOrderDetail(Request $request)
    {
        $reqParams = $request -> toArray();
        //总单号
        $mis_order_sn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        if(empty($mis_order_sn)){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //根据总单号查看订单详情
        $moModel = new MisOrderModel();
        $orderDetail = $moModel->getOrderDetail($mis_order_sn, $reqParams);
        $returnMsg = [
            'orderDetail' => $orderDetail
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:MIS订单列表-获取拆分详情
     * author:zhangdong
     * date : 2018.12.11
     * return Object
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderSplitDetail(Request $request)
    {
        $reqParams = $request -> toArray();
        //总单号
        $mis_order_sn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        if(empty($mis_order_sn)){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //根据总单号查看订单拆分详情
        //检查总单是否存在
        $orderInfo = $this->moModel->getOrderInfo($mis_order_sn);
        if ($orderInfo->count() == 0) {
            $returnMsg = ['code' => '2067','msg' => '订单不存在'];
            return response()->json($returnMsg);
        }
        $orderDetail = $this->moModel->getOrderSplitDetail($mis_order_sn, $orderInfo);
        $returnMsg = [
            'orderDetail' => $orderDetail
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:子订单列表-获取子订单详情
     * author:zhangdong
     * date : 2018.12.12
     * return Object
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubDetail(Request $request)
    {
        $reqParams = $request -> toArray();
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ?
            trim($reqParams['sub_order_sn']) : '';
        if(empty($sub_order_sn)){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //获取订单数据
        $mosModel = new MisOrderSubModel();
        $orderInfo = $mosModel->getSubOrderInfo($sub_order_sn);
        //获取详情数据
        $mosgModel = new MisOrderSubGoodsModel();
        $subOrderDetail = $mosgModel->getSubDetail($sub_order_sn);
        $orderInfo->sku_num = count($subOrderDetail);
        $returnMsg = [
            'subOrderInfo' => $orderInfo,
            'subOrderDetail' => $subOrderDetail,
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:子订单列表-分单操作（根据库存分为现货和采购单）
     * author:zhangdong
     * date : 2018.12.12
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subOrdSubmenu_stop(Request $request)
    {
        $reqParams = $request -> toArray();
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ?
            trim($reqParams['sub_order_sn']) : '';
        if(empty($sub_order_sn)){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //先查询当前子单是否已分单
        $mosModel = new MisOrderSubModel();
        $subInfo = $mosModel->getSubOrderInfo($sub_order_sn);
        if(is_null($subInfo)){
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //检查当前子单是否已经分单（生成现货单和需求单）
        $is_submenu = $mosModel->checkIsSubmenu($sub_order_sn);
        if($is_submenu === true){
            $returnMsg = ['code' => '2053','msg' => '该订单已被分单'];
            return response()->json($returnMsg);
        }
        //根据子单号查询对应商品信息
        $mosgModel = new MisOrderSubGoodsModel();
        $orderGoodsInfo = $mosgModel->getSubDetail($sub_order_sn);
        $goodsNum = $orderGoodsInfo->count();
        if($goodsNum == 0){
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //根据当前商品库存将订单商品组装为现货单和采购单
        $goodsData = $mosgModel->submenuGoods($orderGoodsInfo);
        //为采购单生成订单信息并保存
        $saveRes = false;
        if(!empty($goodsData['demGoodsData'])){
            $demandModel = new DemandModel();
            $demandOrdData = $demandModel->makeDemOrdData($goodsData['demGoodsData'], $sub_order_sn);
            $saveRes = $demandModel->saveDemOrdData($demandOrdData);
        }
        //为现货单生成订单信息并保存
        if(!empty($goodsData['spotGoodsData'])){
            $soModel = new SpotOrderModel();
            $spotOrdData = $soModel->makeSpotOrdData($goodsData['spotGoodsData'], $sub_order_sn);
            $saveRes = $soModel->saveSpotOrdData($spotOrdData);
            //保存成功后进行锁库
            if($saveRes){
                $gsModel = new GoodsSpecModel();
                foreach ($spotOrdData['spotGoodsData'] as $value) {
                    $spotOrdNum = intval($value['goods_number']);
                    $spec_sn = trim($value['spec_sn']);
                    $gsModel->lockGoodsStock($spec_sn,$spotOrdNum);
                }
            }
            //将该订单推送至erp
            $erpApi = new ErpApi();
            $spot_order_sn = trim($spotOrdData['spotOrdData']['spot_order_sn']);
            $erpApi->spot_order_push($spot_order_sn);

        }
        $updateRes = false;
        if($saveRes){
            //保存成功后将子单分单状态改为已分单，订单状态改为BD并写入BD折扣
            $is_submenu = 1;
            $updateRes = $mosModel->updateIsSubmenu($sub_order_sn, $is_submenu);
            //写入子单对应的商品BD折扣和BD时间
            $mosgModel->updateSaleDiscount($sub_order_sn);
            //记录BD状态下的商品数量（此时生成的子单为BD状态，
            //当天如果没有数据则新增，否则更新，第二天新增且数值从0开始重新递增）
            $csModel = new ConversionStatisticsModel();
            $arrData['bd_num'] = $goodsNum;
            $csModel->writeData($arrData);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /**
     * description:现货单列表-erp订单推送
     * author:zhangdong
     * date : 2018.12.14
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function erpOrderPush(Request $request)
    {
        $reqParams = $request -> toArray();
        //子单号
        $spot_order_sn = isset($reqParams['spot_order_sn']) ?
            trim($reqParams['spot_order_sn']) : '';
        if(empty($spot_order_sn)){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //组装推送数据并进行推送-此处为了方便后期加定时任务，所以涉及到的全部业务放入erp的操作
        //类中
        $erpModel = new ErpApi();
        $pushRes = $erpModel->spot_order_push($spot_order_sn);
        $returnMsg = ['code' => '2052','msg' => '推送失败'];
        if($pushRes){
            $returnMsg = ['code' => '2051','msg' => '推送成功'];
        }
        return response()->json($returnMsg);

    }


    /**
     * description:现货单列表
     * author:zhangdong
     * date : 2018.12.15
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSpotList(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $soModel = new SpotOrderModel();
        $spotOrderList = $soModel -> getSpotOrderList($reqParams, $pageSize);
        $returnMsg = [
            'spotOrderList' => $spotOrderList,
        ];
        return response() ->json($returnMsg);
    }


    /**
     * description:子订单列表-获取现货单详情
     * author:zhangdong
     * date : 2018.12.15
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSpotDetail(Request $request)
    {
        $reqParams = $request -> toArray();
        //子单号
        $spot_order_sn = isset($reqParams['spot_order_sn']) ?
            trim($reqParams['spot_order_sn']) : '';
        if(empty($spot_order_sn)){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //获取订单数据
        $soModel = new SpotOrderModel();
        $orderInfo = $soModel->getSpotOrderMsg($spot_order_sn);
        //获取商品详情数据
        $sgModel = new SpotGoodsModel();
        $spotOrderDetail = $sgModel->getSpotDetail($spot_order_sn);
        $returnMsg = [
            'subOrderInfo' => $orderInfo,
            'subOrderDetail' => $spotOrderDetail,
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:现货单列表-取消现货单并将其推送至erp
     * author:zhangdong
     * date : 2018.12.17
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelSpotOrd(Request $request)
    {
        $reqParams = $request -> toArray();
        //子单号
        $spot_order_sn = isset($reqParams['spot_order_sn']) ?
            trim($reqParams['spot_order_sn']) : '';
        if (empty($spot_order_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }

        $soModel = new SpotOrderModel();
        //检查订单是否存在
        $spotOrder = $soModel->getSpotOrderMsg($spot_order_sn);
        $order_status = intval($spotOrder->order_status);
        if(count($spotOrder) === 0 || $order_status === 6){
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //查看子单状态是否为DD，只有DD状态才可取消
        $subOrderSn = trim($spotOrder->sub_order_sn);
        $subOrderInfo = $this->mosModel->getSubOrderInfo($subOrderSn);
        $subStatus = intval($subOrderInfo->int_status);
        if ($subStatus != 3) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //修改现货单状态为已取消
        $status = 6;
        $updateRes = $soModel->updateStatus($spot_order_sn, $status);
        //将取消的订单推送至erp
        $pushRes = false;
        if($updateRes){
            $erpModel = new ErpApi();
            $pushRes = $erpModel->spot_order_push($spot_order_sn);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($pushRes){
            //取消成功后修改商品库存
            $spotGoodsModel = new SpotGoodsModel();
            $orderGoods = $spotGoodsModel->getSpotGoodsInfo($spot_order_sn);
            $gsModel = new GoodsSpecModel();
            foreach ($orderGoods as $value) {
                $goodsNum = intval($value->goods_number);
                $spec_sn = trim($value->spec_sn);
                $gsModel->releaseGoodsStock($spec_sn,$goodsNum);
            }
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /*
     * description:子订单详情-导出订单
     * author:zhangdong
     * date : 2018.12.17
     * @param Request $request
     */
    public function exportSubOrd(Request $request)
    {
        $reqParams = $request->toArray();
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ?
            trim($reqParams['sub_order_sn']) : '';
        if (empty($sub_order_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //根据子单号查询要导出的订单信息
        $mosgModel = new MisOrderSubGoodsModel();
        $subOrdGoodsInfo = $mosgModel->getSubDetail($sub_order_sn);
        //导出数据
        return $this->executeExcel->exportSpotOrdData($subOrdGoodsInfo);
    }


    /*
     * description:子订单详情-导入DD单（导入成功后则将订单状态改为DD状态）
     * author:zhangdong
     * date : 2018.12.18
     * update:由于要对DD单上传操作进行多次导入，故将本接口进行拆分 zhangdong 2019.04.29
     */
    public function importSubOrd(Request $request)
    {
        $reqParams = $request->toArray();
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ? trim($reqParams['sub_order_sn']) : '';
        if (empty($sub_order_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询该订单信息
        $orderSubInfo = $this->mosModel->getSubOrderInfo($sub_order_sn);
        //判断订单状态，只有BD状态才能导入
        $orderStatus = isset($orderSubInfo->int_status) ? $orderSubInfo->int_status : 0;
        if ($orderStatus !== $this->mosModel->status_int['BD']) {
            $returnMsg = ['code' => '2063','msg' => '只有BD状态的订单才可导入'];
            return response()->json($returnMsg);
        }
        //开始导入数据
        $file = $_FILES;
        if(count($file) === 0){
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //检查上传文件是否合格
        $fileName = '订单信息';
        $res = $this->executeExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['商家编码', '商品规格码', '最大供货数量', '销售折扣'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response()->json($returnMsg);
            }
        }
        //如果上传表格数据为空则抛异常
        if (count($res[1]) == 0) {
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //检查表格数据是否有重复的
        $checkImportData = $this->executeExcel->checkImportData($res);
        if (count($checkImportData['repeatData']) > 0) {
            $strRepeatData = implode($checkImportData['repeatData'], ',');
            $returnMsg = ['code' => '2063', 'msg' => '您上传的表格中规格码为' . $strRepeatData . '的数据重复'];
            return response()->json($returnMsg);
        }
        //检查子单的数据是否正常（现货单和需求单至少有一个）
        $checkSubRes = $this->mosModel->checkSubOrderData($sub_order_sn);
        if (isset($checkSubRes['code'])) {
            return response()->json($checkSubRes);
        }
        $spot_sn = $checkSubRes['spot_sn'];
        $demand_sn = $checkSubRes['demand_sn'];
        //根据上传的数据开始整合BD状态的订单
        $modifyRes = $this->mosModel
            ->operateOrd($checkImportData['subOrderGoods'], $sub_order_sn, $spot_sn, $demand_sn);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($modifyRes !== false){
            //将上传文件保存到指定位置
            $saveUploadRes = $this->executeExcel->saveUploadFile($file,$sub_order_sn);
            //写入日志
            $bus_desc = $saveUploadRes ? "文件已保存至：$saveUploadRes" : '文件保存失败';
            $bus_desc .= ' -- bus_value为子单号';
            //总单号
            $ope_module_name = '子单详情-导入DD单';
            $olModel = new OperateLogModel();
            $module_id = $olModel->module['SALE'];
            $olModel->recordLog($bus_desc,$sub_order_sn,$ope_module_name,$module_id);
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }//end of function

    /*
     * description:子订单详情-DD单数据提交
     * author:zhangdong
     * date : 2019.04.29
     */
    public function submitSubOrd(Request $request)
    {
        $reqParams = $request->toArray();
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ? trim($reqParams['sub_order_sn']) : '';
        //外部订单号
        $external_sn = isset($reqParams['external_sn']) ? trim($reqParams['external_sn']) : '';
        if (empty($sub_order_sn) || empty($external_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //子单备注
        $remark = isset($reqParams['remark']) ? trim($reqParams['remark']) : '';
        $orderSubInfo = $this->mosModel->getSubOrderInfo($sub_order_sn);
        //判断订单状态，只有BD状态才能提交
        $orderStatus = isset($orderSubInfo->int_status) ? $orderSubInfo->int_status : 0;
        if ($orderStatus !== $this->mosModel->status_int['BD']) {
            $returnMsg = ['code' => '2063','msg' => '只有BD状态的订单才可提交数据'];
            return response()->json($returnMsg);
        }
        //检查子单的数据是否正常（现货单和需求单至少有一个）
        $checkSubRes = $this->mosModel->checkSubOrderData($sub_order_sn);
        if (isset($checkSubRes['code'])) {
            return response()->json($checkSubRes);
        }
        $spot_sn = $checkSubRes['spot_sn'];
        //数据检查通过后将对应的现货单推送至erp
        if (!empty($spot_sn)) {
            $erpModel = new ErpApi();
            $erpModel->spot_order_push($spot_sn);
        }
        //操作成功后将子单状态改为DD
        $status = $this->mosModel->status_int['DD'];
        $modifyRes = $this->mosModel->updateStatus($sub_order_sn, $status, $remark, $external_sn);
        //转化率统计表(conversion_statistics)-记录DD状态下的商品数量（此时生成的子单为DD状态，
        //当天如果没有数据则新增，否则更新，第二天新增且数值从0开始重新递增）
        $csModel = new ConversionStatisticsModel();
        $arrData['dd_num'] = $this->mosgModel->getSubGoodsNum($sub_order_sn);
        $csModel->writeData($arrData);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($modifyRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /*
     * description:总单详情-导入DD子单数据
     * author:zhangdong
     * date : 2019.05.21
     */
    public function importDDSubOrd(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->importDDSubOrdParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        $saleUserAccount = trim($reqParams['sale_user_account']);
        $entrustTime = trim($reqParams['entrust_time']);
        $externalSn = trim($reqParams['external_sn']);
        $remark = isset($reqParams['remark']) ? trim($reqParams['remark']) : '';
        $expectTime = isset($reqParams['expect_time']) ? trim($reqParams['expect_time']) : '';
        //查询总单信息
        $misOrderInfo = $this->moModel->getMisOrderInfo($misOrderSn);
        if (is_null($misOrderInfo)) {
            return response()->json(['code' => '2063','msg' => '总单不存在']);
        }
        //根据外部单号检查是否已经生成了子单
        $checkRes = $this->mosModel->checkSubExist($misOrderSn, $externalSn);
        if ($checkRes > 0) {
            return response()->json(['code' => '2063','msg' => '该子单已生成']);
        }
        //已报价，已预判，已补单的总单才能上传DD子单
        //判断是否已报价
        $is_offer = intval($misOrderInfo['is_offer']);
        if ($is_offer != $this->moModel->int_offer['YET_OFFER']) {
            return response()->json(['code' => '2063','msg' => '该总单还未报价']);
        }
        //判断是否已预判
        $is_advance = intval($misOrderInfo['is_advance']);
        if ($is_advance != $this->moModel->int_advance['YET_ADVANCE']) {
            return response()->json(['code' => '2063','msg' => '该总单还未预判']);
        }
        //判断是否已补单
        //查询总单补单相关信息
        $ongModel = new OrdNewGoodsModel();
        $isReplenish = $ongModel->checkIsReplenish($misOrderSn);
        if ($isReplenish['replenishInt'] === 1) {
            return response()->json(['code' => '2067','msg' => '当前总单还有未补单的数据']);
        }
        //开始导入数据
        $file = $_FILES;
        if(count($file) === 0){
            return response()->json(['code' => '2002','msg' => '上传文件不能为空']);
        }
        //检查上传文件是否合格
        $fileName = 'DD子单导入';
        $res = $this->executeExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['平台条码','美金原价','EXW折扣','销售折扣','DD数量','现货数量'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                return response()->json(['code' => '2009', 'msg' => '您的标题头有误，请按模板导入']);
            }
        }
        //检查导入数据
        $checkRes = $this->mogModel->checkDDSubData($res, $misOrderInfo);
        if (count($checkRes['none_goods']) > 0) {
            $msg = '第' . implode($checkRes['none_goods'], ',') . '行商品在总单中不存在';
            return response()->json(['code' => '2068', 'msg' => $msg]);
        }
        //检查折扣数据是否正常
        if (count($checkRes['error_discount']) > 0) {
            $msg = '第' . implode($checkRes['error_discount'], ',') . '行商品销售折扣异常';
            return response()->json(['code' => '2068', 'msg' => $msg]);
        }
        //检查表格中的商品数据是否和已经导入的子单数据一模一样，如果一样则提示警告信息，
        //避免某几个商品不断重复提交DD子单
        $arrSpecSn = $checkRes['arraySpecSn'];
        //是否检查重复商品数据 0 不检查 1 检查 默认为1
        $checkRepeatRes = true;
        if (intval($reqParams['is_repeat']) === 1) {
            $checkRepeatRes = $this->mosgModel->checkRepeatGoodsData($arrSpecSn, $misOrderSn);
        }
        if ($checkRepeatRes !== true) {
            return response()->json([
                'code' => '2067',
                'msg' => '您所导入的商品数据和子单 ' . $checkRepeatRes . ' 的商品数据有重复，请确认',
            ]);
        }
        //数据检查通过后开始组装子单数据
        $subOrderData = $this->mosModel->makeSubData(
            $checkRes['dd_data'],$misOrderSn,$saleUserAccount,$entrustTime,$externalSn,$remark,$expectTime
        );
        $saveRes = $this->mosModel->saveSubOrderData($subOrderData);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($saveRes){
            $subOrderSn = $subOrderData['subOrderData'][0]['sub_order_sn'];
            //子单常备量加入
            //如果子单对常备量已经接入过则忽略
            $spModel = new SubPurchaseModel();
            $spModel->allotStandbyGoods($subOrderSn);
            //生成子单后将子单号填入总单中对应的商品以做标记
            $this->mogModel->updateMisSubSn($misOrderSn, $arrSpecSn, $subOrderSn);
            //将上传文件保存到指定位置
            $saveUploadRes = $this->executeExcel->saveUploadFile($file, $subOrderSn);
            //写入日志
            $bus_desc = $saveUploadRes ? "文件已保存至：$saveUploadRes" : '文件保存失败';
            $bus_desc .= ' -- bus_value为子单号';
            //总单号
            $ope_module_name = '子单详情-导入DD单';
            $olModel = new OperateLogModel();
            $module_id = $olModel->module['SALE'];
            $olModel->recordLog($bus_desc,$module_id,$ope_module_name,$module_id);
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }//end of function

    /*
     * description:根据总单号获取销售用户的账号
     * author:zhangdong
     * date : 2018.12.20
     * @param Request $request
     */
    public function listSaleAccount(Request $request)
    {
        $reqParams = $request->toArray();
        //销售用户id
        $saleUserId = isset($reqParams['saleUserId']) ?
            intval($reqParams['saleUserId']) : 0;
        if ($saleUserId === 0) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //根据销售用户id获取其对应的销售账户
        $suaModel = new SaleUserAccountModel();
        $saleAccount = $suaModel->listSaleAccounts($saleUserId);
        if ($saleAccount->count() === 0) {
            $returnMsg = ['code' => '2055','msg' => '销售用户信息错误'];
            return response()->json($returnMsg);
        }
        $returnMsg = [
            'saleAccount' => $saleAccount
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:总单报价-修改销售折扣
     * author:zhangdong
     * date : 2018.12.20
     * return Object
     */
    public function modSaleDiscount(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['sale_discount']) ||
            !isset($reqParams['mis_order_sn']) ||
            !isset($reqParams['sale_user_id'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $spec_sn = trim($reqParams['spec_sn']);
        $sale_discount = trim($reqParams['sale_discount']);
        $mis_order_sn = trim($reqParams['mis_order_sn']);
        $sale_user_id = trim($reqParams['sale_user_id']);
        $mogModel = new MisOrderGoodsModel();
        //修改对应销售折扣
        $modifyRes = $mogModel->updateMisGoodsDiscount($mis_order_sn, $spec_sn, $sale_discount);
        if (!$modifyRes) {
            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
            return response()->json($returnMsg);
        }
        //根据订单号获取订单商品信息
        $misOrderModel = new MisOrderModel();
        $orderGoodsInfo = $misOrderModel->getOrderGoodsInfo($mis_order_sn, $reqParams);
        if ($orderGoodsInfo->count() == 0) {
            $returnMsg = ['code' => '2023', 'msg' => '商品信息有误'];
            return response()->json($returnMsg);
        }
        $goodsModel = new GoodsModel();
        $loginUserInfo = $request->user();
        //部门id
        $depart_id = intval($loginUserInfo->department_id);
        $offerInfo = $goodsModel->getOfferInfo($depart_id);
        $store_id = 1002;
        $goodsModel->userGoodsList(
            $orderGoodsInfo, $offerInfo['pickMarginRate'], $offerInfo['chargeInfo'],
            $offerInfo['goodsHouseInfo'], $store_id
        );
        //查询对应销售用户最近15天的BD和DD折扣
        $mosModel = new MisOrderSubModel();
        $mosGoodsInfo = $mosModel->getMosGoodsInfo($sale_user_id, $spec_sn);
        $orderGoodsInfo[0]->bd_sale_discount = 0;
        $orderGoodsInfo[0]->dd_sale_discount = 0;
        if ($mosGoodsInfo->count() > 0) {
            $orderGoodsInfo[0]->bd_sale_discount = trim($mosGoodsInfo[0]->bd_sale_discount);
            $orderGoodsInfo[0]->dd_sale_discount = trim($mosGoodsInfo[0]->dd_sale_discount);
        }
        $returnMsg = [
            'code' => '1000',
            'goodsOfferInfo' => $orderGoodsInfo,
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:MIS订单管理-子单详情-修改待采量
     * author:zhangdong
     * date : 2018.12.26
     * return Object
     */
    public function modifyWaitNum(Request $request)
    {
        $reqParams = $request -> toArray();
        //子单号
        $subOrderSn = isset($reqParams['sub_order_sn']) ?
            trim($reqParams['sub_order_sn']) : '';
        //规格码
        $spec_sn = isset($reqParams['spec_sn']) ?
            trim($reqParams['spec_sn']) : '';
        //要修改的数量
        $waitNum = isset($reqParams['wait_num']) ? intval($reqParams['wait_num']) : '';
        if (empty($subOrderSn) || empty($spec_sn) || empty($waitNum)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $mosgModel = new MisOrderSubGoodsModel();
        // 校验输入的待采量 - 待采量 <= 总需求 - 当前商品库存
        // 查询子单商品信息
        $subOrderGoodsInfo = $mosgModel->getGoodsNum($subOrderSn, $spec_sn);
        if (is_null($subOrderGoodsInfo)) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        $goodsNum = intval($subOrderGoodsInfo->goods_number);
        //获取当前商品的库存
        $gsModel = new GoodsSpecModel();
        $goodsInfo = $gsModel->getGoodsSpecInfo($spec_sn);
        if (is_null($goodsInfo)) {
            $returnMsg = ['code' => '2013','msg' => '没有该商品信息'];
            return response()->json($returnMsg);
        }
        $stockNum = intval($goodsInfo->stock_num);
        // 待采量 <= 总需求 - 当前商品库存
        if ($waitNum > $goodsNum - $stockNum) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $updateRes = $mosgModel->updateWaitNum($subOrderSn, $spec_sn, $waitNum);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:MIS订单管理-总单详情-导出总单
     * author:zhangdong
     * date : 2019.01.02
     * return Object
     */
    public function misOrderExport_Stop(Request $request)
    {
        $reqParams = $request -> toArray();
        //总单号
        $misOrderSn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        //参数校验
        if (empty($misOrderSn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //检查总单信息是否存在，如果存在则导出
        $misOrderInfo = $this->moModel->getMisOrderInfo($misOrderSn);
        if (is_null($misOrderInfo)) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //查询订单商品信息
        $misOrderGoods = $this->moModel->getOrderGoodsInfo($misOrderSn);
        if ($misOrderGoods->count() === 0) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //查询总单下对应子单的商品信息
        $subGoodsInfo = $this->mosModel->getSubInfo($misOrderSn);
        //导出总单信息
        return $this->executeExcel->exportMisOrdData($misOrderGoods, $misOrderSn, $subGoodsInfo);
    }

    /**
     * description:MIS订单管理-总单详情-导出总单
     * author:zhangdong
     * date : 2019.06.13
     */
    public function misOrderExport(Request $request)
    {
        $reqParams = $request -> toArray();
        //总单号
        $misOrderSn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        //参数校验
        if (empty($misOrderSn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //检查总单信息是否存在，如果存在则导出
        $misOrderInfo = $this->moModel->getMisOrderInfo($misOrderSn);
        if (is_null($misOrderInfo)) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //查询订单商品信息
        $misOrderGoods = $this->mogModel->getExportOrderData($misOrderSn);
        if ($misOrderGoods->count() === 0) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        $sale_user_id = intval($misOrderInfo['sale_user_id']);
        $gcModel = new GoodsCodeModel();
        $code_type = $gcModel->getCodeType($sale_user_id);
       //根据销售用户id组装平台条码
        foreach ($misOrderGoods as $key => $value) {
            $spec_sn = $value->spec_sn;
            $where = [
                ['spec_sn',$spec_sn],
                ['code_type',$code_type],
            ];
            $goodsCode = $gcModel->getStrCodeByWhere($where);
            if (empty($goodsCode)) {
                $goodsCode = $value->erp_merchant_no;
            }
            $misOrderGoods[$key]->goodsCode = $goodsCode;
        }
        //导出总单信息
        return $this->executeExcel->exportMisOrder($misOrderSn, $misOrderGoods);
    }

    /**
     * description:总单详情-批量挂靠交付时间
     * author:zhangdong
     * date : 2019.01.08
     * @param Request
     */
    public function affiliateTime(Request $request)
    {
        $reqParams = $request->toArray();
        //总单号
        $mis_order_sn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        if (empty($mis_order_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询总订单信息
        $misOrderInfo = $this->moModel->getMisOrderInfo($mis_order_sn);
        $status = isset($misOrderInfo['status']) ? intval($misOrderInfo['status']) : '';
        //判断订单状态，只有待挂靠状态才能导入
        if ($status != 1) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //开始导入数据
        $file = $_FILES;
        if(count($file) === 0){
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //检查上传文件是否合格
        $fileName = '总单批量挂靠交付时间';
        $res = $this->executeExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = [
            '商品规格码', '需求量', '库存量', '待采量', '交付时间', '销售账号'
        ];
        $checkFieldRes = $this->executeExcel->checkImportField($arrTitle, $res[0]);
        if (!$checkFieldRes) {
            $returnMsg = [
                'code' => '2009', 'msg' => '您的标题头有误，请按模板导入'
            ];
            return response()->json($returnMsg);
        }
        //如果上传表格数据为空则抛异常
        if (count($res[1]) == 0) {
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //根据上传的数据对交付时间进行批量更新
        $modifyRes = false;
        foreach ($res as $key => $value) {
            if ($key==0) {
                continue;
            }
            $spec_sn = trim($value[2]);
            $entrustTime = trim($value[7]);
            //更新总单交付时间
            $modifyRes = $this->mogModel
                ->orderAffiliate('entrust_time', $entrustTime, $mis_order_sn, $spec_sn);
        }
        //操作成功后检查当前订单的其他交付时间和销售账户是否已经设置，如果
        //已全部设置则将总单状态改为待拆分
        $moModel = new MisOrderModel();
        $checkRes = $moModel->checkAffiliate($mis_order_sn);
        //如果已全部设置则更新总单状态为待拆分
        if(intval($checkRes -> num) === 0){
            $status = 2;
            $moModel->updateStatus($mis_order_sn, $status);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($modifyRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /**
     * description:商品报价页面-完成报价
     * author:zhangdong
     * date : 2019.01.09
     * @param Request
     */
    public function finishOffer(Request $request)
    {
        $reqParams = $request->toArray();
        //总单号
        $mis_order_sn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        if (empty($mis_order_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询总订单信息
        $misOrderInfo = $this->moModel->getMisOrderInfo($mis_order_sn);
        $offerStatus = isset($misOrderInfo['is_offer']) ? intval($misOrderInfo['is_offer']) : '';
        if ($offerStatus !== 0) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }

        //查询总单补单相关信息
        $ongModel = new OrdNewGoodsModel();
        $isReplenish = $ongModel->checkIsReplenish($mis_order_sn);
        if ($isReplenish['replenishInt'] === 1) {
            $returnMsg = ['code' => '2067','msg' => '当前总单还有未补单的数据'];
            return response()->json($returnMsg);
        }
        //查询总单商品报价折扣是否符合要求（只能在0到1之间）
        $countRes = $this->mogModel->checkDiscountNum($mis_order_sn);
        if($countRes > 0){
            $returnMsg = ['code' => '2067','msg' => '总单折扣信息有误，请检查'];
            return response()->json($returnMsg);
        }

        //将总单改为已报价
        $is_offer = 1;
        $updateRes = $this->moModel->updateIsOffer($mis_order_sn, $is_offer);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /*
     * description:子单详情-修改锁库数量
     * author:zhangdong
     * date : 2019.01.11
     * @param Request
     */
    public function modifyWaitLockNum(Request $request)
    {
        $reqParams = $request->toArray();
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ?
            trim($reqParams['sub_order_sn']) : '';
        //规格码
        $spec_sn = isset($reqParams['spec_sn']) ?
            trim($reqParams['spec_sn']) : '';
        //要修改的待锁库数量
        $waitLockNum = isset($reqParams['wait_lock_num']) ?
            trim($reqParams['wait_lock_num']) : '';
        $checkParams = empty($sub_order_sn) || empty($spec_sn) || empty($waitLockNum) || $waitLockNum < 0;
        if ($checkParams) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询子订单信息
        $subOrderInfo = $this->mosModel->getSubOrderInfo($sub_order_sn);
        $status = isset($subOrderInfo->int_status) ? intval($subOrderInfo->int_status) : 0;
        //非YD状态的订单不许修改待锁库数量
        if ($status !== 1) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        $subGoodsInfo = $this->mosgModel->getGoodsNum($sub_order_sn, $spec_sn);
        $wait_lock_num = intval($subGoodsInfo->wait_lock_num);
        if ($waitLockNum > $wait_lock_num) {
            $returnMsg = ['code' => '2057','msg' => '要修改的值不能大于当前默认值'];
            return response()->json($returnMsg);
        }
        //修改子单锁库数量
        $updateRes = $this->mosgModel->updateWaitLockNum($sub_order_sn, $spec_sn, $waitLockNum);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /*
     * description:子单详情-修改备注
     * author:zhangdong
     * date : 2019.01.11
     * @param Request
     */
    public function modifyRemark(Request $request)
    {
        $reqParams = $request->toArray();
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ? trim($reqParams['sub_order_sn']) : '';
        //备注信息
        $value = isset($reqParams['value']) ? trim($reqParams['value']) : '';
        //修改类型 1 备注 2 外部订单号
        $type = isset($reqParams['type']) ? intval($reqParams['type']) : 0;
        if (empty($value) || $type == 0) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询子订单信息
        $subOrderInfo = $this->mosModel->getSubOrderInfo($sub_order_sn);
        if (is_null($subOrderInfo)) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //如果子单为DD状态则禁止修改任何子单数据
        $intStatus = trim($subOrderInfo->int_status);
        if ($intStatus == $this->mosModel->status_int['DD']) {
            $returnMsg = ['code' => '2067','msg' => 'DD状态子单禁止修改相关信息'];
            return response()->json($returnMsg);
        }

        //修改子单备注信息
        $updateRes = $this->mosModel->updateRemark($sub_order_sn, $value, $type);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }


    /**
     * description:商品报价-根据某个自采毛利率批量修改销售折扣
     * author:zhangdong
     * date : 2019.01.18
     */
    public function batchModSaleDiscount(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['mis_order_sn']) || !isset($reqParams['pick_margin_rate'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $mis_order_sn = trim($reqParams['mis_order_sn']);
        $pick_margin_rate = trim($reqParams['pick_margin_rate']);
        $goodsModel = new GoodsModel();
        //检查自采毛利率中是否有该档位
        $pickRateInfo = $goodsModel->getPickMarginInfo($pick_margin_rate);
        if (count($pickRateInfo) == 0) {
            $returnMsg = ['code' => '2005', 'msg' => '没有该档位毛利率'];
            return response()->json($returnMsg);
        }
        //检查当前总单状态是否允许修改销售折扣
        $moModel = new MisOrderModel();
        $misOrderInfo = $moModel->getMisOrderInfo($mis_order_sn);
        $is_offer = intval($misOrderInfo['is_offer']);
        //已报价状态的订单禁止修改
        if ($is_offer === 1) {
            $returnMsg = ['code' => '2005', 'msg' => '当前订单状态禁止修改'];
            return response()->json($returnMsg);
        }
        //查询总单对应的商品信息
        $misGoodsInfo = $this->mogModel->getMisGoodsData($mis_order_sn);
        if ($misGoodsInfo->count() == 0) {
            $returnMsg = ['code' => '2060', 'msg' => '该订单不存在'];
            return response()->json($returnMsg);
        }
        //获取erp仓库信息-重价系数（默认为香港仓）
        $store_id = 1002;
        $goodsHouseInfo = $goodsModel->getErpStoreInfo($store_id);
        $store_factor = trim($goodsHouseInfo[0]['store_factor']);
        $arr_update = [];
        foreach ($misGoodsInfo as $value) {
            //该参数本来要用spec_sn，但觉得用id也可以，这样做是否有问题还未知
            $misGoodsId = intval($value->id);
            $spec_weight = trim($value->spec_weight);//商品重量
            $spec_price = trim($value->spec_price);
            $exw_discount = trim($value->exw_discount); //exw折扣
            //获取有关erp的所有商品数据
            $erpGoodsData = $goodsModel->getErpGoodsData($spec_weight, $spec_price, $store_factor, $exw_discount);
            //根据所选自采毛利率档位计算定价折扣
            //销售折扣=自采毛利率=重价比折扣/（1-对应档位利率）
            $sale_discount = $erpGoodsData['hprDiscount'] / (1 - $pick_margin_rate / 100);
            $sale_discount = round($sale_discount, DECIMAL_DIGIT);
            $arr_update[] = [
                'id' => $misGoodsId,
                'sale_discount' => $sale_discount,
            ];
        }
        $table = 'jms_mis_order_goods';
        $arrSql = makeUpdateSql($table, $arr_update);
        $updateRes = $arrSql;
        if ($arrSql) {
            //开始批量更新
            $strSql = $arrSql['updateSql'];
            $bindData = $arrSql['bindings'];
            $updateRes = $this->mogModel->executeSql($strSql, $bindData);
        }
        $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        if (!$updateRes) {
            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        }
        return response()->json($returnMsg);
    }


    /**
     * description:商品报价-根据选定的sku批量修改对应销售折扣
     * author:zhangdong
     * date : 2019.03.01
     */
    public function modSaleDiscountBySpec(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['mis_order_sn']) ||
            !isset($reqParams['strSpecSn']) ||
            !isset($reqParams['sale_discount'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $mis_order_sn = trim($reqParams['mis_order_sn']);
        $strSpecSn = $reqParams['strSpecSn'];
        //将规格码转为数组
        $arrSpecSn = array_filter(explode(',',$strSpecSn));
        if (count($arrSpecSn) == 0) {
            $returnMsg = ['code' => '2005', 'msg' => '请选择要修改的商品'];
            return response()->json($returnMsg);
        }
        $sale_discount = floatval($reqParams['sale_discount']);
        //检查当前总单状态是否允许修改销售折扣
        $moModel = new MisOrderModel();
        $misOrderInfo = $moModel->getMisOrderInfo($mis_order_sn);
        $is_offer = intval($misOrderInfo['is_offer']);
        //已报价状态的订单禁止修改
        if ($is_offer === 1) {
            $returnMsg = ['code' => '2005', 'msg' => '当前订单状态禁止修改'];
            return response()->json($returnMsg);
        }
        //开始根据商品规格码批量修改销售折扣
        $mogModel = new MisOrderGoodsModel();
        $updateRes = $mogModel->modifySaleDiscountBySpec($mis_order_sn, $sale_discount, $arrSpecSn);

        $returnMsg = ['code' => '1000', 'msg' => '操作成功'];
        if (!$updateRes) {
            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        }
        return response()->json($returnMsg);
    }

    /*
     * description:总单详情页面-完成预判
     * author:zhangdong
     * date : 2019.03.04
     * @param Request
     */
    public function finishAdvance(Request $request)
    {
        $reqParams = $request->toArray();
        //总单号
        $mis_order_sn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        if (empty($mis_order_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询总订单信息
        $misOrderInfo = $this->moModel->getMisOrderInfo($mis_order_sn);
        $is_advance = isset($misOrderInfo['is_advance']) ? intval($misOrderInfo['is_advance']) : false;
        if ($is_advance === false) {
            $returnMsg = ['code' => '2050','msg' => '当前总单信息未找到'];
            return response()->json($returnMsg);
        }

        //查询总单补单相关信息
        $ongModel = new OrdNewGoodsModel();
        $isReplenish = $ongModel->checkIsReplenish($mis_order_sn);
        if ($isReplenish['replenishInt'] === 1) {
            $returnMsg = ['code' => '2067','msg' => '当前总单还有未补单的数据'];
            return response()->json($returnMsg);
        }

        if ($is_advance === 1) {
            $returnMsg = ['code' => '2050','msg' => '当前总单已经提交了预判数量'];
            return response()->json($returnMsg);
        }
        //将总单改为已预判
        $update_advance = 1;
        $updateRes = $this->moModel->updateIsAdvance($mis_order_sn, $update_advance);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }


    /*
     * description:总单详情页面-完成挂靠
     * author:zhangdong
     * date : 2019.03.19
     * @param Request
     */
    public function finishAffiliated(Request $request)
    {
        $reqParams = $request->toArray();
        //总单号
        $mis_order_sn = isset($reqParams['mis_order_sn']) ?
            trim($reqParams['mis_order_sn']) : '';
        if (empty($mis_order_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询总订单信息
        $misOrderInfo = $this->moModel->getMisOrderInfo($mis_order_sn);
        $is_affiliated = isset($misOrderInfo['status']) ? intval($misOrderInfo['status']) : false;
        if ($is_affiliated === false) {
            $returnMsg = ['code' => '2050','msg' => '当前总单信息未找到'];
            return response()->json($returnMsg);
        }

        //查询总单补单相关信息
        $ongModel = new OrdNewGoodsModel();
        $isReplenish = $ongModel->checkIsReplenish($mis_order_sn);
        if ($isReplenish['replenishInt'] === 1) {
            $returnMsg = ['code' => '2067','msg' => '当前总单还有未补单的数据'];
            return response()->json($returnMsg);
        }

        if ($is_affiliated !== 1) {
            $returnMsg = ['code' => '2050','msg' => '当前总单已经挂靠完成'];
            return response()->json($returnMsg);
        }
        //操作成功后检查当前订单的其他交付时间和销售账户是否已经设置，如果
        //已全部设置则将总单状态改为待拆分
        $moModel = new MisOrderModel();
        $checkRes = $moModel->checkAffiliate($mis_order_sn);
        //如果已全部设置则更新总单状态为待拆分
        $updateRes = false;
        if(intval($checkRes -> num) === 0){
            $status = 2;
            $updateRes = $moModel->updateStatus($mis_order_sn, $status);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败，请检查所有商品是否挂靠完毕！'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
        
    }


    /**
     * description:总单详情-订单批量挂靠
     * author:zhangdong
     * date : 2019.03.21
     * return Object
     */
    public function batchAffiliate(Request $request)
    {
        $reqParams = $request -> toArray();
        //总单号
        $misOrderSn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        //挂靠类型 1,挂靠交付时间 2，挂靠销售账户
        $type =  isset($reqParams['type']) ? intval($reqParams['type']) : '';
        //规格码
        $strSpecSn =  isset($reqParams['strSpecSn']) ? trim($reqParams['strSpecSn']) : '';
        //修改数值
        $modifyValue =  isset($reqParams['modifyValue']) ? trim($reqParams['modifyValue']) : '';
        //将规格码转为数组
        $arrSpecSn = array_filter(explode(',',$strSpecSn));
        if (empty($misOrderSn) || count($arrSpecSn) == 0 || empty($type) || empty($modifyValue)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        switch ($type){
            case 1 ://1,挂靠交付时间
                $field = 'entrust_time';
                break;
            case 2 ://2，挂靠销售账户
                $field = 'sale_user_account';
                break;
            default :
                $field = '';
        }
        if($field === ''){
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //修改对应的挂靠项
        $mogModel = new MisOrderGoodsModel();
        $updateRes = $mogModel->batchAffiliate($field, $modifyValue, $misOrderSn, $arrSpecSn);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:总单详情-查看总单新品
     * author:zhangdong
     * date : 2019.04.15
     * return Object
     */
    public function getOrderNewGoods(Request $request)
    {
        $reqParams = $request -> toArray();
        $moModel = new MisOrderModel();
        $misOrderSn = $moModel->checkMisOrderSn($reqParams);
        if(isset($misOrderSn['code'])) {
            return response()->json($misOrderSn);
        }
        //查询订单信息
        $misOrderInfo = $moModel->getOrderInfo($misOrderSn);
        //查询新品是否已全部补单
        $ongModel = new  OrdNewGoodsModel();
        $replenishInfo = $ongModel->checkIsReplenish($misOrderSn);
        //查询新品是否已全部新增
        $newGoodsNum = intval($replenishInfo['newGoodsNum']);
        $createdInfo = $ongModel->getCreatedInfo($newGoodsNum, $misOrderSn);
        //通过总单号查询对应总单新品
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->getOrderNewGoods($misOrderSn, $reqParams);
        $returnMsg = [
            'misOrderInfo' => $misOrderInfo,
            'replenishInfo' => $replenishInfo,
            'createdInfo' => $createdInfo,
            'newGoodsInfo' => $newGoodsInfo,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:总单新品-修改新品信息
     * author:zhangdong
     * date : 2019.04.15
     */
    public function modifyNewGoodsData(Request $request)
    {
        $reqParams = $request -> toArray();
        $misOrderSn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        $ng_id = isset($reqParams['ng_id']) ? intval($reqParams['ng_id']) : 0;
        //参数检查
        $checkRes = $this->checkParamsA($misOrderSn, $ng_id);
        if(isset($checkRes['code'])){
            return response()->json($checkRes);
        }
        $ongModel = new OrdNewGoodsModel();
        $modifyRes = $ongModel->modifyNewGoodsInfo($misOrderSn, $ng_id, $reqParams);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($modifyRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:总单新品-修改新品信息弹框数据查询
     * author:zhangdong
     * date : 2019.04.15
     */
    public function queryNewGoodsData(Request $request)
    {
        $reqParams = $request -> toArray();
        $misOrderSn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        $ng_id = isset($reqParams['ng_id']) ? intval($reqParams['ng_id']) : 0;
        //参数检查
        $checkRes = $this->checkParamsA($misOrderSn, $ng_id);
        if(isset($checkRes['code'])){
            return response()->json($checkRes);
        }
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->queryNewGoodsMsg($misOrderSn, $ng_id);
        //获取品牌信息
        $brandModel = new BrandModel();
        $brandInfo = $brandModel->getBrandInfoInRedis();
        $returnMsg = [
            'newGoodsInfo' => $newGoodsInfo,
            'brandInfo' => $brandInfo
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:总单新品-单条商品新增
     * author:zhangdong
     * date : 2019.04.15
     */
    public function addNewGoodsData(Request $request)
    {
        $reqParams = $request -> toArray();
        $misOrderSn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        $ng_id = isset($reqParams['ng_id']) ? intval($reqParams['ng_id']) : 0;
        //参数检查
        $checkRes = $this->checkParamsA($misOrderSn, $ng_id);
        if(isset($checkRes['code'])){
            return response()->json($checkRes);
        }
        //通过总单号和id查询对应新品
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->getNewGoodsById($misOrderSn, (array)$ng_id);
        if ($newGoodsInfo->count() == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '该商品不存在'];
            return response()->json($returnMsg);
        }
        //检查新品数据是否正确
        $checkRes = $ongModel->checkNewGoodsInfo($newGoodsInfo);
        if($checkRes !== true){
            return response()->json($checkRes);
        }
        $newGoodsInfo = (array)$newGoodsInfo[0];
        //如果都不存在则开始创建新商品
        $goodsModel = new GoodsModel();
        //通过总单号查询销售用户id用来写入平台条码
        $orderMsg = $this->moModel->getOrderMsg($misOrderSn);
        $suid = intval($orderMsg->sale_user_id);
        $createRes = $goodsModel->createNewGoods($newGoodsInfo, $suid);
        //创建成功后根据商品编码将所有订单中存在的该新品标记为已创建
        if ($createRes['insertRes'] === true) {
            $ongModel = new OrdNewGoodsModel();
            $spec_sn = trim($createRes['spec_sn']);
            $platformBarcode = trim($newGoodsInfo['platform_barcode']);
            //新增成功后将所有涉及的总单新品全部改为已新增，且保存对应规格码
            $createRes = $ongModel->updateNewGoodsCreated($platformBarcode, $spec_sn);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($createRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }//end of function

    /**
     * description:总单新品-商品批量新增
     * author:zhangdong
     * date : 2019.04.17
     */
    public function batchAddNewGoods(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->batchAddNewGoodsParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        $strNgId = trim($reqParams['str_ng_id']);
        //将新品临时id转为数组
        $arrNgId = array_filter(explode(',',$strNgId));
        if (count($arrNgId) == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '请选择要新增的商品'];
            return response()->json($returnMsg);
        }
        //根据新品临时id和总单号查询要新增的商品信息
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->getNewGoodsById($misOrderSn, $arrNgId);
        if ($newGoodsInfo->count() == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '您选择的商品不存在'];
            return response()->json($returnMsg);
        }
        //检查要新增商品的信息是否正常
        $checkRes = $ongModel->checkNewGoodsInfo($newGoodsInfo);
        if($checkRes !== true){
            return response()->json($checkRes);
        }
        //通过总单号查询销售用户id用来写入平台条码
        $orderMsg = $this->moModel->getOrderMsg($misOrderSn);
        $suid = intval($orderMsg->sale_user_id);
        //新增商品信息无误后将新品写入相应数据表
        $goodsModel = new GoodsModel();
        $insertRes = $goodsModel->batchCreateGoods($newGoodsInfo, $suid);
        if ($insertRes['insertRes'] !== true) {
            $returnMsg = ['code' => '2023','msg' => '操作失败'];
            return response()->json($returnMsg);
        }
        //新增成功后将规格码写入总单新品信息中
        if (is_array($insertRes['arrSql'])) {
            //开始批量更新
            $strSql = $insertRes['arrSql']['updateSql'];
            $bindData = $insertRes['arrSql']['bindings'];
            $this->mogModel->executeSql($strSql, $bindData);
        }
        //新增成功后将订单新品表中的对应商品状态改为已创建
        //组装商品编码为值不重复的一维数组
        $arrUniqueCode = array_unique($insertRes['arrPlatformBarcode']);
        $updateRes = $ongModel->updateCreated($arrUniqueCode);
        //修改成功后返回成功信息
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /**
     * description 总单新品-补充新品到对应总单中
     * author:zhangdong
     * date : 2019.04.18
     */
    public function replenishGoodsIntoOrder(Request $request)
    {
        $reqParams = $request -> toArray();
        //参数校验
        $moModel = new MisOrderModel();
        $misOrderSn = $moModel->checkMisOrderSn($reqParams);
        if(isset($misOrderSn['code'])) {
            return response()->json($misOrderSn);
        }
        //检查总单信息
        $misOrderInfo = $moModel->getMisOrderInfo($misOrderSn);
        if (is_null($misOrderInfo)) {
            $returnMsg = ['code' => '2067','msg' => '订单不存在'];
            return response()->json($returnMsg);
        }

        //根据总单号查询新品
        $ongModel = new OrdNewGoodsModel();
        $params = [ 'status' => $ongModel->status['NO_REPLENISH']];
        $newGoodsInfo = $ongModel->getOrderNewGoods($misOrderSn, $params);

        //检查是否有未补单的新品
        if ($newGoodsInfo->count() == 0) {
            $returnMsg = ['code' => '2067','msg' => '当前订单没有需要补单的新品'];
            return response()->json($returnMsg);
        }

        //如果有未补单新品则检查是否还有未新增的商品
        $keyValue = $ongModel->is_created['NOT_CREATE'];
        $arrData = objectToArray($newGoodsInfo);
        $searchNoCreated  = searchTwoArray($arrData, $keyValue,'is_created');
        if (count($searchNoCreated) > 0) {
            $returnMsg = ['code' => '2067','msg' => '当前订单还有未新增的商品'];
            return response()->json($returnMsg);
        }

        //检查新品是否真的已被全部创建（防止由于数据问题导致将未创建的新品补充到总单）
        $gcModel = new GoodsCodeModel();
        //对$newGoodsInfo的第一次处理-加入spec_sn
        $checkNewRes = $gcModel->checkNewIsCreated($newGoodsInfo);
        if ($checkNewRes === false) {
            $returnMsg = ['code' => '2067','msg' => '部分商品不在系统中'];
            return response()->json($returnMsg);
        }

        //过滤已经存在于订单中的商品
        $mogModel = new MisOrderGoodsModel();
        $misOrderGoods = $mogModel->getInfoByOrderSn($misOrderSn);
        $arrMisOrdInfo = objectToArray($misOrderGoods);
        //对$newGoodsInfo的第二次处理-删除已经存在的商品
        $mogModel->filterExistGoodsInOrder($newGoodsInfo, $arrMisOrdInfo);
        //以规格码为主查询补单必要的商品信息（所有商品信息重新获取）
        $goodsModel = new GoodsModel();
        $arrGoodsInfo = $goodsModel->getNewGoodsInfo($newGoodsInfo);
        //组装写入数据
        $depart_id = intval($misOrderInfo['depart_id']);
        $sale_user_id = intval($misOrderInfo['sale_user_id']);
        $orderData = $this->moModel->createOrderData($arrGoodsInfo,$depart_id,$sale_user_id,$misOrderSn);
        //将新品补充到相应的总单商品信息中去
        $orderGoods = $orderData['orderGoods'];
        $saveRes = $mogModel->saveOrderGoodsData($orderGoods);
        if($saveRes){
            //操作成功后将订单所有新品状态改为已补单
            $status = intval($ongModel->status['YET_REPLENISH']);
            $saveRes = $ongModel->modifyNewStatus($misOrderSn, $status);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($saveRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }//end of function

    /**
     * description 总单新品-新增规格
     * author:zhangdong
     * date : 2019.04.22
     */
    public function addSpecPage(Request $request)
    {
        $reqParams = $request -> toArray();
        $misOrderSn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        $ng_id = isset($reqParams['ng_id']) ? intval($reqParams['ng_id']) : 0;
        //参数检查
        $checkRes = $this->checkParamsA($misOrderSn, $ng_id);
        if(isset($checkRes['code'])){
            return response()->json($checkRes);
        }
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->queryNewGoodsMsg($misOrderSn, $ng_id);
        if (is_null($newGoodsInfo)) {
            $returnMsg = ['code' => '2067','msg' => '没有该新品'];
            return response()->json($returnMsg);
        }
        $returnMsg = [
            'newGoodsInfo' => $newGoodsInfo,
        ];
        return response()->json($returnMsg);
    }

    /**
     * description 总单新品-新增规格-品名搜索
     * author:zhangdong
     * date : 2019.04.22
     */
    public function searchGoodsName(Request $request)
    {
        $reqParams = $request -> toArray();
        $goods_name = isset($reqParams['goods_name']) ? trim($reqParams['goods_name']) : '';
        //基于商品名称从系统中搜索商品信息
        $goodsModel = new GoodsModel();
        $goodsInfo = $goodsModel->searchGoodsName($goods_name);
        $returnMsg = [
            'goodsInfo' => $goodsInfo
        ];
        return response()->json($returnMsg);
    }

    /**
     * description 总单新品-新增规格-提交
     * author:zhangdong
     * date : 2019.04.22
     */
    public function addSpecData(Request $request)
    {
        $reqParams = $request -> toArray();
        $misOrderSn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        $ng_id = isset($reqParams['ng_id']) ? intval($reqParams['ng_id']) : 0;
        //参数检查
        $checkRes = $this->checkParamsA($misOrderSn, $ng_id);
        if(isset($checkRes['code'])){
            return response()->json($checkRes);
        }
        $goods_sn = isset($reqParams['goods_sn']) ? trim($reqParams['goods_sn']) : '';
        if (empty($goods_sn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //通过goods_sn校验商品信息是否存在
        $goodsModel = new GoodsModel();
        $goodsInfo = $goodsModel->getGoodsBySn($goods_sn);
        if (is_null($goodsInfo)) {
            $returnMsg = ['code' => '2067','msg' => '商品数据异常'];
            return response()->json($returnMsg);
        }
        //查询新品信息
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->getNewGoodsInfo($misOrderSn, $ng_id);
        if (intval($newGoodsInfo->is_created) != 0) {
            return response()->json(['code' => '2067','msg' => '请勿重复新建商品']);
        }
        $arrNewGoodsInfo = (array)$newGoodsInfo;
        $gsModel = new GoodsSpecModel();
        //检查必填信息
        $checkSpecData = $gsModel->checkSpecData($arrNewGoodsInfo);
        if ($checkSpecData == false) {
            return response()->json(['code' => '2067','msg' => '商品重量或美金原价数据有误，请先编辑']);
        }
        //通过商品编码查询该商品是否已经建立
        $gcModel = new GoodsCodeModel();
        $strGoodsCode = trim($arrNewGoodsInfo['platform_barcode']);
        $arrGoodsCode = $gcModel->makeArrGoodsCode($strGoodsCode);
        $goodsCodeInfo = $gcModel->getSpecSnByCode($arrGoodsCode);
        if ($goodsCodeInfo->count() > 0) {
            $returnMsg = ['code' => '2067','msg' => '请勿重复新建商品'];
            return response()->json($returnMsg);
        }
        //组装规格写入数据
        $specInfo = $gsModel->generalSpecInfo($arrNewGoodsInfo,$goods_sn);
        //将规格信息保存
        $insertRes = $gsModel->writeSpecData($specInfo);
        if (!$insertRes) {
            $returnMsg = ['code' => '2023','msg' => '操作失败'];
            return response()->json($returnMsg);
        }
        //通过总单号查询销售用户id用来写入平台条码
        $orderMsg = $this->moModel->getOrderMsg($misOrderSn);
        $suid = intval($orderMsg->sale_user_id);
        $spec_sn = trim($specInfo['spec_sn']);
        //保存商品编码信息$strGoodsCode可能是多个编码组成的，所以要对其处理
        $goodsCodeInfo = $gcModel->makeGoodsCode($strGoodsCode, $spec_sn, $suid);
        $gcModel->insertGoodsCode($goodsCodeInfo);
        //将相关的总单新品状态改为已新增
        $updateRes = $ongModel->updateNewGoodsCreated($strGoodsCode, $spec_sn);
        //返回成功信息
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /**
     * description 预判数据导出
     * author:zhangdong
     * date : 2019.04.24
     */
    public function exportAdvance(Request $request)
    {
        $reqParams = $request -> toArray();
        $moModel = new MisOrderModel();
        $misOrderSn = $moModel->checkMisOrderSn($reqParams);
        if(isset($misOrderSn['code'])) {
            return response()->json($misOrderSn);
        }
        //只有已补单未预判的总单才可导出预判数据
        $ongModel = new OrdNewGoodsModel();
        $replenishInfo = $ongModel->checkIsReplenish($misOrderSn);
        //判断补单状态
        if ($replenishInfo['replenishInt'] === 1) {
            $returnMsg = ['code' => '2067','msg' => '当前总单还有未补单的数据'];
            return response()->json($returnMsg);
        }
        //判断预判状态
        $moModel = new MisOrderModel();
        $orderInfo = $moModel->getOrderMsg($misOrderSn);
        if (is_null($orderInfo)) {
            $returnMsg = ['code' => '2067','msg' => '该订单不存在'];
            return response()->json($returnMsg);
        }
        $is_advance = intval($orderInfo->is_advance);
        if ($is_advance === 1) {
            $returnMsg = ['code' => '2067','msg' => '该订单已经预判'];
            return response()->json($returnMsg);
        }
        //导出预判数据
        //获取订单商品信息
        $misOrderGoods = $moModel->getOrderGoodsInfo($misOrderSn);
        if ($misOrderGoods->count() === 0) {
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        //组装导出数据
        $makeExportData = $moModel->makeExportData($misOrderGoods);
        $executeModel = new ExcuteExcel();
        return $executeModel->exportAdvanceData($misOrderSn, $makeExportData);
    }

    /**
     * description 预判数据导入
     * author:zhangdong
     * date : 2019.04.25
     */
    public function importAdvance(Request $request)
    {
        $reqParams = $request -> toArray();
        $moModel = new MisOrderModel();
        //检查总单号
        $misOrderSn = $moModel->checkMisOrderSn($reqParams);
        if(isset($misOrderSn['code'])) {
            return response()->json($misOrderSn);
        }
        //通过总单号检查订单信息
        $misOrderInfo = $moModel->getOrderMsg($misOrderSn);
        if (count($misOrderInfo) == 0) {
            $returnMsg = ['code' => '2067','msg' => '订单不存在'];
            return response()->json($returnMsg);
        }
        //检查预判状态
        $is_advance = intval($misOrderInfo->is_advance);
        if ($is_advance === 1) {
            $returnMsg = ['code' => '2067','msg' => '该订单已经预判'];
            return response()->json($returnMsg);
        }
        //检查导入文件
        $file = $_FILES;
        if(count($file) === 0){
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //检查上传文件是否合格
        $executeExcel = new ExcuteExcel();
        $fileName = '预判数量';
        $res = $executeExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $needTitle = ['商品规格码', '预判采购量'];
        $checkTitle = $executeExcel->checkTitle($res[0], $needTitle);
        if ($checkTitle !== true) {
            return response()->json($checkTitle);
        }
        //如果上传表格数据为空则抛异常
        if (count($res[1]) == 0) {
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //检查上传数据
        $checkRes = $this->mogModel->checkUploadData($misOrderSn, $res);
        if (isset($checkRes['code'])) {
            return response()->json($checkRes);
        }
        $correctData = $checkRes;
        //更新预判数量
        $updateRes = $this->mogModel->updateWaitNum($misOrderSn, $correctData);
        //返回成功信息
        $returnMsg = ['code' => '2023','msg' => '操作失败，请检查数据是否有变化'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }//end of function

    /**
     * description 修改DD子单数据
     * author:zhangdong
     * date : 2019.05.21
     */
    public function modifyDDSubData(Request $request)
    {
        $reqParams = $request -> toArray();
        $subOrderSn = $this->mosModel->checkSubOrderSn($reqParams);
        if(isset($subOrderSn['code'])) {
            return response()->json($subOrderSn);
        }
        //检查规格码
        $spec_sn = isset($reqParams['spec_sn']) ? trim($reqParams['spec_sn']) : '';
            //修改类型 1,修改现货数量 2，修改dd数量 3，修改DD销售折扣 4,预判采购数量
        $type = isset($reqParams['type']) ? intval($reqParams['type']) : 0;
        //修改值
        $value = isset($reqParams['type']) ? trim($reqParams['value']) : '';
        if (empty($spec_sn) || $value == '' || $type == 0) {
            $returnMsg = ['code' => '2067','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //检查子单状态-如果为DD则不可修改
        $subOrderInfo = $this->mosModel->getSubOrderInfo($subOrderSn);
        if ($subOrderInfo->int_status == $this->mosModel->status_int['DD']) {
            $returnMsg = ['code' => '2067','msg' => 'DD状态的子单禁止修改数据'];
            return response()->json($returnMsg);
        }
        $modifyRes = $this->mosgModel->modifySubData($subOrderSn, $spec_sn, $type, $value);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($modifyRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description DD数据提交
     * author zhangdong
     * date 2019.05.22
     */
    public function submitDData(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->submitDDataParams($reqParams);
        $subOrderSn = trim($reqParams['sub_order_sn']);
        //检查子单状态-如果为DD则不可重复提交数据
        $subInfo = $this->mosModel->getSubOrderInfo($subOrderSn);
        if(is_null($subInfo)){
            $returnMsg = ['code' => '2050','msg' => '订单不存在'];
            return response()->json($returnMsg);
        }
        if ($subInfo->int_status == $this->mosModel->status_int['DD']) {
            $returnMsg = ['code' => '2067','msg' => '请勿重复提交'];
            return response()->json($returnMsg);
        }
        //检查子单商品报价折扣是否符合要求（只能在0到1之间） zhnagdong 2019.09.09
        $countRes = $this->mosgModel->checkDiscountNum($subOrderSn);
        if($countRes > 0){
            $returnMsg = ['code' => '2067','msg' => '折扣信息有误，请检查'];
            return response()->json($returnMsg);
        }
        //修改子单状态为DD
        $status = $this->mosModel->status_int['DD'];
        $remark = isset($reqParams['ramark']) ? trim($reqParams['ramark']) : '';
        $external_sn = trim($reqParams['external_sn']);
        $modifyRes = $this->mosModel->updateStatus($subOrderSn, $status, $remark, $external_sn);
        $returnMsg = $modifyRes ?
            ['code' => '2024','msg' => '操作成功'] : ['code' => '2023','msg' => '操作失败'];
        return response()->json($returnMsg);
    }

    /**
     * title 子订单详情-子单分单
     * desc 根据DD状态下子单的预判采购量和现货数生成需求单和现货单
     * author zhangdong
     * date 2019.05.22
     */
    public function subOrdSubmenu(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->subOrdSubmenuParams($reqParams);
        //子单号
        $sub_order_sn = isset($reqParams['sub_order_sn']) ? trim($reqParams['sub_order_sn']) : '';
        //检查子单是否为DD状态
        $subInfo = $this->mosModel->getSubOrderInfo($sub_order_sn);
        if(is_null($subInfo)){
            $returnMsg = ['code' => '2050','msg' => '订单信息有误'];
            return response()->json($returnMsg);
        }
        if ($subInfo->int_status != $this->mosModel->status_int['DD']) {
            return response()->json(['code' => '2067','msg' => '非DD子单禁止分单']);
        }
        //检查当前子单是否已经分单（生成现货单和需求单）
        $is_submenu = $this->mosModel->checkIsSubmenu($sub_order_sn);
        if($is_submenu === true){
            return response()->json(['code' => '2053','msg' => '该订单已被分单']);
        }
        //根据子单号查询对应商品信息
        $orderGoodsInfo = $this->mosgModel->getSubDetail($sub_order_sn);
        $goodsNum = $orderGoodsInfo->count();
        if($goodsNum == 0){
            return response()->json(['code' => '2050','msg' => '订单信息有误']);
        }
        //检查子单中是否有重复的sku
        $repeatSku = filter_duplicate(objectToArray($orderGoodsInfo), 'spec_sn');
        if (count($repeatSku) > 0) {
            $msg = '规格码为 ' . implode(',', $repeatSku) . ' 的商品重复';
            return response()->json(['code' => '2067','msg' => $msg]);
        }

        //根据当前商品库存将订单商品组装为现货单和需求单
        $goodsData = $this->mosgModel->submenuGoods($orderGoodsInfo);
        if (count($goodsData['demGoodsData']) == 0 && count($goodsData['spotGoodsData']) == 0) {
            $msg = '该子单中SKU的需求量已被常备量满足，故无需分单';
            return response()->json(['code' => '2067','msg' => $msg]);
        }
        //为采购单生成订单信息并保存
        $saveRes = false;
        if(!empty($goodsData['demGoodsData'])){
            $demandModel = new DemandModel();
            $demandOrdData = $demandModel->makeDemOrdData($goodsData['demGoodsData'], $sub_order_sn);
            $saveRes = $demandModel->saveDemOrdData($demandOrdData);
        }
        //为现货单生成订单信息并保存
        if(!empty($goodsData['spotGoodsData'])){
            $soModel = new SpotOrderModel();
            $spotOrdData = $soModel->makeSpotOrdData($goodsData['spotGoodsData'], $sub_order_sn);
            $saveRes = $soModel->saveSpotOrdData($spotOrdData);
            //将该订单推送至erp - 新业务暂时停用 2019.05.22
            /*$erpApi = new ErpApi();
            $spot_order_sn = trim($spotOrdData['spotOrdData']['spot_order_sn']);
            $erpApi->spot_order_push($spot_order_sn);*/
        }

        $updateRes = false;
        if($saveRes){
            //保存成功后将子单分单状态改为已分单
            $is_submenu = $this->mosModel->submenu_int['YET_SUBMENU'];
            $updateRes = $this->mosModel->modifySubmenu($sub_order_sn, $is_submenu);
        }
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description 总单详情-查看总单下的子单
     * author zhangdong
     * date 2019.05.23
     */
    public function getMisOrderSplit(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->getMisOrderSplitParams($reqParams);
        $mis_order_sn = trim($reqParams['mis_order_sn']);
        //进行总订单状态的校验
        $mis_order_info = $this->moModel->getMisOrderInfo($mis_order_sn);
        if (is_null($mis_order_info)) {
            return response()->json(['code' => '2067','msg' => '总单不存在']);
        }
        //通过总单号获取对应子单列表
        $misSubOrderList = $this->mosModel->querySubOrderList($mis_order_sn);
        return response()->json(['misSubOrderList' => $misSubOrderList]);
    }

    /**
     * @description:参数检查-订单新品相关-检查总单号和新品自增id
     * @author:zhangdong
     * @date : 2019.04.16
     * @param $misOrderSn (总单号)
     * @param $ng_id (新品自增id)
     * @return mixed
     */
    private function checkParamsA($misOrderSn, $ng_id)
    {
        //检查总单号
        if (empty($misOrderSn) || $ng_id == 0) {
            return ['code' => '2005','msg' => '参数错误'];
        }
        return true;
    }

    /**
     * description 总单新品列表-导出总单新品
     * author zhangdong
     * date 2019.06.06
     */
    public function exportOrdNew(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->exportOrdNewParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        //查询新品
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->getOrderNewGoods($misOrderSn, $reqParams);
        if ($newGoodsInfo->count() === 0) {
            $returnMsg = ['code' => '2067','msg' => '总单新品信息有误'];
            return response()->json($returnMsg);
        }

        //查询新品是否已补单
        $replenishInfo = $ongModel->checkIsReplenish($misOrderSn);
        if ($replenishInfo['replenishInt'] == $ongModel->status['YET_REPLENISH']) {
            return response()->json([
                'code' => '2067','msg' => '已补单状态禁止导出'
            ]);
        }
        //组装导出数据
        $executeModel = new ExcuteExcel();
        return $executeModel->exportOrdNew($misOrderSn, $newGoodsInfo);

    }

    /**
     * description 总单新品批量更新
     * author:zhangdong
     * date : 2019.06.06
     */
    public function importOrdNew(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->importOrdNewParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        //查询新品
        $ongModel = new OrdNewGoodsModel();
        $newGoodsInfo = $ongModel->getOrderNewGoods($misOrderSn);
        if ($newGoodsInfo->count() === 0) {
            $returnMsg = ['code' => '2067','msg' => '总单新品信息有误'];
            return response()->json($returnMsg);
        }

        //查询新品是否已补单
        $replenishInfo = $ongModel->checkIsReplenish($misOrderSn);
        if ($replenishInfo['replenishInt'] == $ongModel->status['YET_REPLENISH']) {
            return response()->json([
                'code' => '2067','msg' => '已补单状态的订单其新品禁止更新'
            ]);
        }
        //检查导入文件
        $file = $_FILES;
        if(count($file) === 0){
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //检查上传文件是否合格
        $executeExcel = new ExcuteExcel();
        $fileName = '总单新品';
        $res = $executeExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $needTitle = [
            '信息ID','品牌ID','品牌名称','商品名称','商家编码','平台条码',
            '美金原价','商品重量','商品预估重量','EXW折扣','参考代码','商品代码',
        ];
        $checkTitle = $executeExcel->checkTitle($res[0], $needTitle);
        if ($checkTitle !== true) {
            return response()->json($checkTitle);
        }
        //检查上传数据-过滤出被修改过的数据
        $checkRes = $ongModel->checkUploadData($newGoodsInfo, $res);
        if (isset($checkRes['code'])) {
            return response()->json($checkRes);
        }
        if (count($checkRes) == 0) {
            return response()->json(['code' => '2067','msg' => '没有要修改的数据']);
        }
        $modifyData = $checkRes;
        //组装批量更新语句
        $modifySqlInfo = $ongModel->createSqlInfo($misOrderSn, $modifyData);
        //更新预判数量
        $updateRes = $ongModel->executeSql($modifySqlInfo);
        //返回成功信息
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }//end of function

    /**
     * description 订单商品报价-导出商品信息
     * author:zhangdong
     * date : 2019.06.10
     */
    public function exportOffer(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->exportOfferParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        $pickMarginRate = floatval($reqParams['pick_margin_rate']);
        //检查总单号信息是否正常
        $moModel = new MisOrderModel();
        $countMisOrdNum = $moModel->countNum($misOrderSn);
        if ($countMisOrdNum <= 0) {
            return response()->json(['code' => '2067','msg' => '该总单不存在']);
        }
        //检查自采毛利率档位是否存在
        $mrModel = new MarginRateModel();
        $countMarginRateNum = $mrModel->countMarginRateNum($pickMarginRate);
        if ($countMarginRateNum <= 0) {
            return response()->json(['code' => '2067','msg' => '该档位自采毛利率不存在']);
        }
        //查询要导出的数据
        //根据订单号获取订单商品信息
        $orderGoodsInfo = $moModel->getOrderGoodsInfo($misOrderSn, $reqParams);
        if ($orderGoodsInfo->count() <= 0) {
            return response()->json(['code' => '2067','msg' => '该总单下商品数据不存在']);
        }
        //组装要导出的信息
        $exportData = $moModel->packageExportData($orderGoodsInfo, $misOrderSn, $pickMarginRate);
        //导出数据
        return $this->executeExcel->exportOffer($misOrderSn, $exportData, $pickMarginRate);
    }


    /**
     * description 订单商品报价-导入商品最终折扣
     * author:zhangdong
     * date : 2019.06.12
     */
    public function importOffer(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->importOfferParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        //通过总单号检查订单信息
        $moModel = new MisOrderModel();
        $misOrderInfo = $moModel->getOrderMsg($misOrderSn);
        if (count($misOrderInfo) == 0) {
            $returnMsg = ['code' => '2067','msg' => '订单不存在'];
            return response()->json($returnMsg);
        }
        //检查是否已报价
        $is_offer = intval($misOrderInfo->is_offer);
        if ($is_offer === $moModel->int_offer['YET_OFFER']) {
            $returnMsg = ['code' => '2067','msg' => '该订单已完成报价'];
            return response()->json($returnMsg);
        }
        //检查导入文件
        $file = $_FILES;
        //检查上传文件是否合格
        $executeExcel = new ExcuteExcel();
        $fileName = '报价信息导入';
        $res = $executeExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $needTitle = ['商品规格码', '确定销售折扣'];
        $checkTitle = $executeExcel->checkTitle($res[0], $needTitle);
        if ($checkTitle !== true) {
            return response()->json($checkTitle);
        }
        //如果上传表格数据为空则抛异常
        if (count($res[1]) == 0) {
            $returnMsg = ['code' => '2002','msg' => '上传文件不能为空'];
            return response()->json($returnMsg);
        }
        //检查上传数据
        $checkRes = $this->mogModel->checkOfferData($misOrderSn, $res);
        if (isset($checkRes['code'])) {
            return response()->json($checkRes);
        }
        $correctData = $checkRes;
        //更新销售折扣
        $updateRes = $this->mogModel->updateSaleDiscount($misOrderSn, $correctData);
        //返回成功信息
        $returnMsg = ['code' => '2023','msg' => '操作失败,请检查数据是否有改动'];
        if($updateRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }//end of function

    /**
     * description 查看有新价格的商品
     * author zhangdong
     * date 2019.06.19
     */
    public function getGoodsNewPrice(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->getGoodsNewPriceParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        $moModel = new MisOrderModel();
        //获取有新价格的商品信息
        $reqParams['new_spec_price'] = true;
        $goodsInfo = $moModel->getOrderGoodsInfo($misOrderSn, $reqParams);
        $returnMsg = [
            'goodsInfo' => $goodsInfo
        ];
        return response()->json($returnMsg);
    }

    /**
     * description 修改价格
     * author zhangdong
     * date 2019.06.19
     */
    public function modifyNewPrice(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->modifyNewPriceParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        $specSn = trim($reqParams['spec_sn']);
        //检查总单下是否存在该SKU
        $countOrderSpec = $this->mogModel->countOrderSpec($misOrderSn, $specSn);
        if ($countOrderSpec == 0) {
            return response()->json(['code' => '2067','msg' => '总单商品信息异常']);
        }
        $newSpecPrice = floatval($reqParams['new_spec_price']);
        $modifyRes = $this->mogModel->modifyNewSpecPrice($misOrderSn, $specSn, $newSpecPrice);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($modifyRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description 商品新价格页面-提交价格
     * author zhangdong
     * date 2019.06.20
     */
    public function submitNewPrice(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->submitNewPriceParams($reqParams);
        $misOrderSn = trim($reqParams['mis_order_sn']);
        //检查总单是否存在
        $countNum = $this->moModel->countNum($misOrderSn);
        if ($countNum == 0) {
            return response()->json(['code' => '2067','msg' => '订单不存在']);
        }
        //已报价的订单不可修改价格
        //查询价格有变动的SKU
        $priceChangeSku = $this->mogModel->getPriceChangeSku($misOrderSn);
        //组装价格要变动的SKU
        $needModifySku = $this->mogModel->makeChangeSku($priceChangeSku);
        if (count($needModifySku) == 0) {
            return response()->json(['code' => '2067','msg' => '没有需要变动的商品价格']);
        }
        //更新这些SKU的价格到商品规格表和订单商品表
        $modifyRes = $this->mogModel->modifyGoodsPrice($misOrderSn, $needModifySku);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($modifyRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /**
     * description 首页-DD单品排行
     * author zhangdong
     * date 2019.09.02
     */
    public function ddGoodsRankList(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $goodsRankList = $this->mosModel->getGoodsRankList($reqParams, $pageSize);
        return response() ->json([
            'goodsRankList' => $goodsRankList,
        ]);
    }

    /**
     * description APP-DD单品排行
     * author zhangdong
     * date 2019.09.03
     */
    public function ddGoodsRankApp(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $reqParams['orderType'] = 'desc';
        $goodsRankList = $this->mosModel->getGoodsRankList($reqParams, $pageSize);
        return response() ->json([
            'code' => '1000',
            'msg' => '请求成功',
            'data' => [
                'goodsRankList' => $goodsRankList->items()
            ],
        ]);
    }

    /**
     * description 获取销售用户
     * author zhangdong
     * date 2019.09.03
     */
    public function getSaleUser(Request $request)
    {
        $saleUser = (new SaleUserModel())->getSaleUserInfoInRedis();
        //去除多余字段
        foreach ($saleUser as $key => $value) {
            $saleUser[$key]['id'] = strval($value['id']);
            unset(
                $saleUser[$key]['depart_id'],$saleUser[$key]['min_profit'],
                $saleUser[$key]['sale_user_cat'],$saleUser[$key]['money_cat'],
                $saleUser[$key]['sale_short'],$saleUser[$key]['payment_cycle'],
                $saleUser[$key]['group_sn'],$saleUser[$key]['is_start']
            );
        }
        return response() ->json([
            'code' => '1000',
            'msg' => '请求成功',
            'data' => [
                'saleUser' => $saleUser
            ],
        ]);
    }



}//end of class