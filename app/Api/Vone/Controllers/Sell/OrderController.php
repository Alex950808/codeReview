<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use Dingo\Api\Http\Request;
//数据库操作类
//引入erp类库 add by zhangdong on the 2018.06.27
use App\Modules\Erp\ErpApi;
//引入时间及日期处理包 add by zhangdong on the 2018.06.28
use Carbon\Carbon;
//引入日志库文件 add by zhangdong on the 2018.06.28
use Mockery\Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
//引入订单模型
use App\Model\Vone\OrderInfoModel;
//引入Excel操作类库 add by zhangdong on the 2018.07.04
use Maatwebsite\Excel\Facades\Excel;

//create by zhangdong on the 2018.06.22
class OrderController extends BaseController
{
    /**
     * description:定时任务-销售模块-获取erp订单管理数据
     * editor:zhangdong
     * date : 2018.06.27
     */
    public function getErpOrderData()
    {
        $log = new Logger('erpOrder');
        $log -> pushHandler(new StreamHandler(storage_path('logs/erpOrder.log'), Logger::INFO));
        $log -> addInfo("获取erp订单管理数据-开始");
        $erpApi = new ErpApi();
        $shopMsg = $erpApi::SHOP_MSG;
        //获取erp订单数据
        $start_time = Carbon::now() -> addHour(-1);
        $end_time = Carbon::now();
        foreach ($shopMsg as $key => $value){
            $shopNum = strval($key);
            $erpApi -> getErpOrder($shopNum, $start_time, $end_time);
            //订单状态-目前查询所有状态的订单，所以暂时弃用该段代码
//            $trade_status = $erpApi::TRADE_STATUS;
//            foreach ($trade_status as $key => $item){
//                $intStatus = intval($key);
//                $erpApi -> getErpOrder($shopNum, $start_time, $end_time, $intStatus);
//            }
        }
        $log -> addInfo("获取erp订单管理数据-结束");
        return 'success';

    }

    /**
     * description:定时任务-获取物流单号
     * editor:zhangdong
     * date : 2018.07.14
     */
    public function getLogisticsNo()
    {
        $log = new Logger('erpLogistics');
        $log -> pushHandler(new StreamHandler(storage_path('logs/erpLogistics.log'), Logger::INFO));
        $log -> addInfo("获取erp订单物流单号-开始");
        $erpApi = new ErpApi();
        $shopMsg = $erpApi::SHOP_MSG;
        foreach ($shopMsg as $key => $value){
            $shopNum = strval($key);
            $erpApi -> sycLogisticInfo($shopNum);

        }
        $log -> addInfo("获取erp订单物流单号-结束");
        return 'success';

    }

    /**
     * description:订单管理-获取待发货订单及超时未发货订单
     * editor:zhangdong
     * date : 2018.06.29
     */
    public function getWaiteOrder(Request $request)
    {
        $reqParams = $request -> toArray();
        //查询超时未发货订单标记 1,筛选超时的 0，不筛选超时的
        $expire_mark = isset($reqParams['expire_mark']) && intval($reqParams['expire_mark']) == 1 ? 1 : 0;
        $params['expire_mark'] = $expire_mark;
        //关键字
        $keywords = array_key_exists('keywords',$reqParams) && !empty($reqParams['keywords']) ? $reqParams['keywords'] : '';
        //根据订单状态获取订单列表
        $orderInfoModel = new OrderInfoModel();
        $queryType = 1;
        $params['keywords'] = $keywords;
        //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
        $params['order_status'] = 1;
        $orderInfo = $orderInfoModel -> getOrderMsg($params, $queryType);
        $returnMsg = [
            'orderInfo' => $orderInfo
        ];
        return response() -> json($returnMsg);

    }

    /**
     * description:订单管理-获取待清关订单及超时未清关订单
     * editor:zhangdong
     * date : 2018.06.29
     */
    public function getWaitCusOrder(Request $request)
    {
        $reqParams = $request -> toArray();
        //查询超时未清关订单标记 1,筛选超时的 0，不筛选超时的
        $expire_custom = array_key_exists('expire_custom',$reqParams) && intval($reqParams['expire_custom']) == 1 ?
            intval($reqParams['expire_custom']) : 0;
        $params['expire_custom'] = $expire_custom;
        //搜索关键字
        $keywords = array_key_exists('keywords',$reqParams) && !empty($reqParams['keywords']) ?
            $reqParams['keywords'] : '';
        //根据订单状态获取订单列表
        $orderInfoModel = new OrderInfoModel();
        $queryType = 2;
        $params['keywords'] = $keywords;
        //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
        $params['order_status'] = 2;
        $orderInfo = $orderInfoModel -> getOrderMsg($params, $queryType);
        $returnMsg = [
            'orderInfo' => $orderInfo
        ];
        return response() -> json($returnMsg);

    }


    /**
     * description:订单管理-待清关订单-上传清关订单
     * editor:zhangdong
     * date : 2018.07.04
     * return json
     */
    public function uploadWaitCusOrd(Request $request)
    {
        $reqParams = $request -> toArray();
        if(!array_key_exists('custom_order_file',$reqParams)){
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response() -> json($returnMsg);
        }
        if(empty($reqParams['custom_order_file'])){
            $returnMsg = ['code' => '2002', 'msg' => '上传文件不能为空'];
            return response() -> json($returnMsg);
        }
        $file = $_FILES;
        //检查表格名称
        $fileName = '上传清关订单';
        $uploadName = $file['custom_order_file']['name'];
        $matchingRes = strrpos($uploadName,$fileName);
        if($matchingRes === false){
            $returnMsg = ['code' => '2007', 'msg' => '请选择本网站提供的模板进行导入'];
            return response() -> json($returnMsg);
        }
        //检查表格文件格式
        $file_types = explode (".", $uploadName);
        $file_type = $file_types [count($file_types) - 1];
        if(strtolower($file_type) != "xlsx"){
            $returnMsg = ['code' => '2008', 'msg' => '请上传xlsx格式的Excel文件'];
            return response() -> json($returnMsg);
        }
        $excel_file_path = $file['custom_order_file']['tmp_name'];
        $res = [];
        Excel::load($excel_file_path, function($reader) use(&$res) {
            $reader = $reader->getSheet(0);
            $res = $reader->toArray();
        });
        //检查字段名称
        $arrTitle = ['订单号'];
        foreach ($arrTitle as $title) {
            if(!in_array(trim($title),$res[0])){
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response() -> json($returnMsg);
            }
        }
        //根据上传的待清关订单表格更新订单状态-将待清关改为待转关
        $uploadMark = 1;
        $operateRes = $this -> operateOrder($res, $uploadMark);
        $returnMsg = ['code' => '2001','msg' => '上传失败'];
        if($operateRes){
            $returnMsg = ['code' => '2000','msg' => '上传成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:根据上传的待清关订单表格更新订单状态-将待清关改为转关中
     * editor:zhangdong
     * params: $uploadMark 上传文件标记1,上传已清关订单文件 2，上传已转关订单文件
     * date : 2018.07.04
     * return Boolean
     */
    private function operateOrder($res, $uploadMark)
    {
        $orderModel = new  OrderInfoModel();
        $arrTradeSn = [];
        foreach ($res as $key => $value) {
            if ($key == 0) continue;
            $trade_sn = $value[0];
            //如果上传已清关订单文件则仅对待清关的订单做处理
            //如果上传已转关订单文件则仅对转关中的订单做处理
            $queryType = $uploadMark == 1 ? 2 : 3;
            $orderInfo = $orderModel -> getOderInfo($trade_sn, $queryType);
            //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            if($orderInfo -> count() == 0) continue;
            $arrTradeSn[] = $trade_sn;
        }
        //如果上传已清关订单文件则将待清关状态改为转关中
        //如果上传已转关订单文件则将转关中状态改为配发中
        $status = $uploadMark == 1 ? 3 : 4;
        $batchUpdateRes = $orderModel -> updateOrderStatus($arrTradeSn, $status);
        return $batchUpdateRes;
    }

    /**
     * description:清关管理-获取转关中订单
     * editor:zhangdong
     * date : 2018.07.04
     */
    public function getCusTransitOrder(Request $request)
    {
        $reqParams = $request -> toArray();
        //搜索关键字
        $keywords = array_key_exists('keywords',$reqParams) && !empty($reqParams['keywords']) ?
            $reqParams['keywords'] : '';
        //根据订单状态获取订单列表
        $orderInfoModel = new OrderInfoModel();
        $queryType = 3;
        $params['keywords'] = $keywords;
        //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
        $params['order_status'] = 3;
        $orderInfo = $orderInfoModel -> getOrderMsg($params, $queryType);
        $returnMsg = [
            'orderInfo' => $orderInfo
        ];
        return response() -> json($returnMsg);

    }

    /**
     * description:清关管理-待转关订单-上传已转关订单
     * editor:zhangdong
     * date : 2018.07.04
     * return json
     */
    public function uploadCusTransitOrd(Request $request)
    {
        $reqParams = $request -> toArray();
        if(!array_key_exists('cus_tran_order',$reqParams)){
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response() -> json($returnMsg);
        }
        if(empty($reqParams['cus_tran_order'])){
            $returnMsg = ['code' => '2002', 'msg' => '上传文件不能为空'];
            return response() -> json($returnMsg);
        }
        $file = $_FILES;
        //检查表格名称
        $fileName = '上传转关订单';
        $uploadName = $file['cus_tran_order']['name'];
        $matchingRes = strrpos($uploadName,$fileName);
        if($matchingRes === false){
            $returnMsg = ['code' => '2007', 'msg' => '请选择本网站提供的模板进行导入'];
            return response() -> json($returnMsg);
        }
        //检查表格文件格式
        $file_types = explode (".", $uploadName);
        $file_type = $file_types [count($file_types) - 1];
        if(strtolower($file_type) != "xlsx"){
            $returnMsg = ['code' => '2008', 'msg' => '请上传xlsx格式的Excel文件'];
            return response() -> json($returnMsg);
        }
        $excel_file_path = $file['cus_tran_order']['tmp_name'];
        $res = [];
        Excel::load($excel_file_path, function($reader) use(&$res) {
            $reader = $reader->getSheet(0);
            $res = $reader->toArray();
        });
        //检查字段名称
        $arrTitle = ['订单号'];
        foreach ($arrTitle as $title) {
            if(!in_array(trim($title),$res[0])){
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response() -> json($returnMsg);
            }
        }
        //根据上传的待清关订单表格更新订单状态-将转关中改为配发中
        $uploadMark = 2;//上传文件1,上传已清关订单文件 2，上传已转关订单文件
        $operateRes = $this -> operateOrder($res, $uploadMark);
        $returnMsg = ['code' => '2001','msg' => '上传失败'];
        if($operateRes){
            $returnMsg = ['code' => '2000','msg' => '上传成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:清关管理-待转关订单-上传发货订单
     * editor:zhangdong
     * date : 2018.11.07
     * return Object
     */
    public function upDeliverOrd(Request $request)
    {
        $reqParams = $request -> toArray();
        if (!isset($reqParams['goods_file']) || !isset($reqParams['up_type'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response() -> json($returnMsg);
        }
        //1,上传已发货订单 2，上传清关订单 3，上传转关订单
        $up_type  = intval($reqParams['up_type']);
        //开始导入数据
        $file = $_FILES;
        //检查表格名称
        if($up_type == 1) $fileName = '发货订单';
        if($up_type == 2) $fileName = '清关订单';
        if($up_type == 3) $fileName = '转关订单';
        $uploadName = $file['goods_file']['name'];
        $matchingRes = strrpos($uploadName,$fileName);
        if($matchingRes === false){
            $returnMsg = ['code' => '2007', 'msg' => '请选择本网站提供的模板进行导入'];
            return response() -> json($returnMsg);
        }
        //检查表格文件格式
        $file_types = explode (".", $uploadName);
        $file_type = $file_types [count($file_types) - 1];
        if(strtolower($file_type) != "xls"){
            $returnMsg = ['code' => '2008', 'msg' => '请上传xls格式的Excel文件'];
            return response() -> json($returnMsg);
        }
        $excel_file_path = $file['goods_file']['tmp_name'];
        $res = [];
        Excel::load($excel_file_path, function($reader) use(&$res) {
            $reader = $reader->getSheet(0);
            $res = $reader->toArray();
        });
        //检查字段名称
        if($up_type == 1) $arrTitle = ['erp订单号','物流单号','物流公司'];
        if($up_type == 2 || $up_type == 3) $arrTitle = ['erp订单号'];
        foreach ($arrTitle as $title) {
            if(!in_array(trim($title),$res[0])){
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response() -> json($returnMsg);
            }
        }
        $orderModel = new OrderInfoModel();
        //更新订单物流信息
        if(count($res) <= 1){
            $returnMsg = ['code' => '2002', 'msg' => '上传文件不能为空'];
            return response() -> json($returnMsg);
        }
        $update = false;
        if($up_type == 1){
            $update = $orderModel -> updateShipNo($res);
        }
        if($up_type == 2 || $up_type == 3){
            $update = $orderModel -> updateOrderData($res, $up_type);
        }
        $returnMsg = ['code' => '2001', 'msg' => '上传失败'];
        if($update){
            $returnMsg = ['code' => '2000', 'msg' => '上传成功'];
        }
        return response() -> json($returnMsg);
    }


    /**
     * description:订单列表
     * editor:zhangdong
     * date : 2018.11.08
     * return Object
     */
    public function getOrderList(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $orderModel = new OrderInfoModel();
        $orderList = $orderModel -> getOrderList($reqParams, $pageSize);

        $returnMsg = [
            'orderList' => $orderList,
        ];
        return response() ->json($returnMsg);
    }

    /**
     * description:订单列表——查看详情
     * editor:zhangdong
     * date : 2018.11.12
     * return Object
     */
    public function orderDetail(Request $request)
    {
        $reqParams = $request -> toArray();
        if(!isset($reqParams['trade_no'])){
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response() -> json($returnMsg);
        }
        $trade_no = trim($reqParams['trade_no']);
        //获取订单详情
        $orderModel = new OrderInfoModel();
        $queryRes = $orderModel->getOrderDetail($trade_no);
        if(empty($queryRes)){
            $returnMsg = ['code' => '2046', 'msg' => '数据异常，请联系管理员'];
            return response() -> json($returnMsg);
        }
        //组装详情数据
        $orderDetail = $orderModel->makeDetailData($queryRes);
        $returnMsg = [
            'orderData' => $orderDetail['orderData'],
            'goodsData' => $orderDetail['goodsData'],
        ];
        return response() ->json($returnMsg);
    }



}