<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use App\Model\Vone\RealPurchaseDetailModel;
use Carbon\Carbon;
use Dingo\Api\Auth\Auth;
use Dingo\Api\Http\Request;
//引入Excel操作类库
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
//引入model
use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\DemandModel;
use App\Model\Vone\SaleUserModel;
use App\Model\Vone\GoodsModel;
use App\Model\Vone\DepartmentModel;
use App\Model\Vone\PurchaseDateModel;
//引入日志库文件 add by zhangdong on the 2018.06.28
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
//引入日志表模型 add by zhangdong on the 2018.07.10
use App\Model\Vone\OperateLogModel;
//引入Excel执行通用文件类
use App\Modules\Excel\ExcuteExcel;

//引入redis
use Illuminate\Support\Facades\Redis;


//create by zhangdong on the 2018.06.22
class DemandController extends BaseController
{
    /**
     * description:销售模块-需求管理-获取列表数据
     * editor:zhangdong
     * date : 2018.06.23
     */
    public function getDemandList(Request $request)
    {
        $reqParams = $request->toArray();
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //获取并组装列表数据
        $purchaseDateModel = new PurchaseDateModel();
        $params['keywords'] = $keywords;
        $purchaseList = $purchaseDateModel->getPurchaseList($params, $pageSize);
        $returnMsg = response()->json([
            'purchaseList' => $purchaseList
        ]);
        return $returnMsg;

    }

    /**
     * description:需求管理-提报需求-商品上传
     * editor:zhangdong
     * date : 2018.06.25
     */
    public function goodsUploadStop(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['goods_file']) || !isset($reqParams['purchase_sn'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $purchase_sn = $reqParams['purchase_sn'];//采购单号
        $demandGoodsModel = new DemandGoodsModel();
        //检查采购单是否存在
        $purchaseOrdInfo = $demandGoodsModel->getPurchaseInfo($purchase_sn);
        if (empty($purchaseOrdInfo))
            return response()->json(['code' => '2021', 'msg' => '采购单信息异常']);
        if ($purchaseOrdInfo["status"] != 2)
            return response()->json(['code' => '2021', 'msg' => '采购单信息异常']);
        //采购单状态：采购期状态：1,准备中（采购单生成但未提交商品采购需求）;
        //2,进行中（第一批需求已提交）;3,提货中;4,到货分配中;5,关闭
        $pur_status = intval($purchaseOrdInfo["status"]);
        //仅准备中和进行中才可以提需求
        if ($pur_status != 1 && $pur_status != 2)
            return response()->json(['code' => '2027', 'msg' => '采购单当前状态不允许提交需求']);
        //判断提报需求的日期是否在开始时间和结束时间之间，如果不是则禁止提交
        $curTime = Carbon::now();
        $start_time = $purchaseOrdInfo["start_time"] . "00:00:00";
        $end_time = $purchaseOrdInfo["end_time"] . "23:59:59";
        $pur_start_time = Carbon::createFromTimeString($start_time);
        $pur_end_time = Carbon::createFromTimeString($end_time);
        $checkTime = $curTime->between($pur_start_time, $pur_end_time);
        if ($checkTime === false) return response()->json(['code' => '2026', 'msg' => '当前时间不允许提交需求']);

        //开始导入数据
        $file = $_FILES;
        //检查表格名称
        $fileName = '提报需求-商品明细';
        $uploadName = $file['goods_file']['name'];
        $matchingRes = strrpos($uploadName, $fileName);
        if ($matchingRes === false) {
            $returnMsg = ['code' => '2007', 'msg' => '请选择本网站提供的模板进行导入'];
            return response()->json($returnMsg);
        }
        //检查表格文件格式
        $file_types = explode(".", $uploadName);
        $file_type = $file_types [count($file_types) - 1];
        if (strtolower($file_type) != "xlsx") {
            $returnMsg = ['code' => '2008', 'msg' => '请上传xlsx格式的Excel文件'];
            return response()->json($returnMsg);
        }
        $excel_file_path = $file['goods_file']['tmp_name'];
        $res = [];
        Excel::load($excel_file_path, function ($reader) use (&$res) {
            $reader = $reader->getSheet(0);
            $res = $reader->toArray();
        });
        //检查字段名称
        $arrTitle = ['商家编码', '商品规格码', '需求数量'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response()->json($returnMsg);
            }
        }
        //组装本次上传需求中所涉及的用户数
        $saleUser = $this->createSaleUser($res[0]);
        if (count($saleUser) == 0) {
            $returnMsg = ['code' => '2017', 'msg' => '上传表格中标题头不能没有销售用户，请核对后再上传'];
            return response()->json($returnMsg);
        }
        if (count(array_unique($saleUser)) != count($saleUser)) {
            $returnMsg = ['code' => '2018', 'msg' => '上传表格中标题头中销售用户重复，请核对后再上传'];
            return response()->json($returnMsg);
        }
        //组装相关需求表的写入数据
        $demandData = $this->createDemandData($res, $purchase_sn, $saleUser);

        //指定提报部门
        $demandData['demandOrdData']['department'] = 1;

        $demandOrdData = $demandData['demandOrdData'];//需求订单数据
        $goodsData = $demandData['demandGoodsData'];//需求商品数据
        $countData = $demandData['demandCountData'];//需求商品统计数据
        $userGoodsData = $demandData['userGoodsData'];//销售用户商品数据
        $tipMsg = $demandData['tipMsg'];//异常数据提示
        //商品需求表数据批量写入商品需求表及需求订单表
        $uploadRes = $demandGoodsModel->batchInsert($demandOrdData, $goodsData, $countData, $userGoodsData);
        $returnMsg = ['code' => '2001', 'msg' => '上传失败'];
        if ($uploadRes) {
            $returnMsg = ['code' => '2000', 'msg' => '上传成功'];
            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'demand,demand_goods,demand_count',
                'bus_desc' => '销售部-上传采购单商品需求,采购单号：' . $purchase_sn,
                'bus_value' => '表名：' . $uploadName,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '销售模块-需求管理-提报需求',
                'module_id' => 3,
                'have_detail' => 0,
            ];
            $operateLogModel->insertLog($logData);

        }
        $returnMsg['tipMsg'] = $tipMsg;
        return response()->json($returnMsg);
    }


    /**
     * description:销售部-需求管理-提报需求-商品上传
     * editor:zhangdong
     * date : 2018.09.21
     */
    public function goodsUpload(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['goods_file']) || !isset($reqParams['department']) || !isset($reqParams['expire_time'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $department = intval($reqParams['department']);//部门
        $expire_time = trim($reqParams['expire_time']);//截止日期
        $demandGoodsModel = new DemandGoodsModel();
        //开始导入数据
        $file = $_FILES;
        //检查表格名称
        $fileName = '提报需求-商品明细';
        $uploadName = $file['goods_file']['name'];
        $matchingRes = strrpos($uploadName, $fileName);
        if ($matchingRes === false) {
            $returnMsg = ['code' => '2007', 'msg' => '请选择本网站提供的模板进行导入'];
            return response()->json($returnMsg);
        }
        //检查表格文件格式
        $file_types = explode(".", $uploadName);
        $file_type = $file_types [count($file_types) - 1];
        if (strtolower($file_type) != "xlsx") {
            $returnMsg = ['code' => '2008', 'msg' => '请上传xlsx格式的Excel文件'];
            return response()->json($returnMsg);
        }
        $excel_file_path = $file['goods_file']['tmp_name'];
        $res = [];
        Excel::load($excel_file_path, function ($reader) use (&$res) {
            $reader = $reader->getSheet(0);
            $res = $reader->toArray();
        });
        //检查字段名称
        $arrTitle = ['商家编码', '商品规格码', '需求数量'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response()->json($returnMsg);
            }
        }
        //组装本次上传需求中所涉及的用户数
        $saleUser = $this->createSaleUser($res[0]);
        if (count($saleUser) == 0) {
            $returnMsg = ['code' => '2017', 'msg' => '上传表格中标题头不能没有销售用户，请核对后再上传'];
            return response()->json($returnMsg);
        }
        if (count(array_unique($saleUser)) != count($saleUser)) {
            $returnMsg = ['code' => '2018', 'msg' => '上传表格中标题头中销售用户重复，请核对后再上传'];
            return response()->json($returnMsg);
        }
        //组装相关需求表的写入数据
        $demandData = $this->createDemandData($res, $saleUser);
        //指定提报部门
        $demandData['demandOrdData']['department'] = $department;
        //截止日期
        $demandData['demandOrdData']['expire_time'] = $expire_time;
        $demandOrdData = $demandData['demandOrdData'];//需求订单数据
        $goodsData = $demandData['demandGoodsData'];//需求商品数据
        $userGoodsData = $demandData['userGoodsData'];//销售用户商品数据
        $tipMsg = $demandData['tipMsg'];//异常数据提示
        //商品需求表数据批量写入商品需求表及需求订单表
        $uploadRes = $demandGoodsModel->batchInsert($demandOrdData, $goodsData, $userGoodsData);
        $returnMsg = ['code' => '2001', 'msg' => '上传失败'];
        if ($uploadRes) {
            $returnMsg = ['code' => '2000', 'msg' => '上传成功'];
            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'demand,demand_goods,demand_count',
                'bus_desc' => '销售部-上传采购单商品需求,需求单号：' . $demandOrdData['demand_sn'],
                'bus_value' => '表名：' . $uploadName,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '销售模块-需求管理-提报需求',
                'module_id' => 3,
                'have_detail' => 0,
            ];
            $operateLogModel->insertLog($logData);

        }
        $returnMsg['tipMsg'] = $tipMsg;
        return response()->json($returnMsg);
    }

    /**
     * description:需求管理-提报需求-获取页面数据
     * editor:zhangdong
     * date : 2018.06.26
     */
    public function getPageDataStop(Request $request)
    {
        $reqParams = $request->toArray();
        //判断所需参数是否传入
        if (!array_key_exists('purchase_sn', $reqParams)) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //采购单号
        $purchase_sn = $reqParams['purchase_sn'];
        if (empty($purchase_sn)) {
            $returnMsg = ['code' => '2010', 'msg' => '采购单号不能为空'];
            return response()->json($returnMsg);
        }
        $demandGoodsModel = new DemandGoodsModel();
        $userGoods = $demandGoodsModel->getUserGoods($purchase_sn);
        $returnMsg = [
            'userGoods' => $userGoods,
            'purchase_sn' => $purchase_sn,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:需求管理-提报需求-手动添加商品-搜索-根据商家编码获取商品信息
     * editor:zhangdong
     * date : 2018.06.26
     */
    public function getGoodsData(Request $request)
    {
        $reqParams = $request->toArray();
        //判断所需参数是否传入
        if (!array_key_exists('keywords', $reqParams)) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //搜索关键字
        $keywords = $reqParams['keywords'];
        if (empty($keywords)) {
            $returnMsg = ['code' => '2011', 'msg' => '搜索关键字不能为空'];
            return response()->json($returnMsg);
        }
        $goodsModel = new GoodsModel();
        $searchGoods = $goodsModel->getGoodsByKeywords($keywords);
        $returnMsg = [
            'searchGoods' => $searchGoods,
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:需求管理-提报需求-手动添加商品-搜索-添加需求商品
     * editor:zhangdong
     * date : 2018.06.26
     */
    public function insertDemandGoods(Request $request)
    {
        $reqParams = $request->toArray();
        //判断所需参数是否传入
        if (
            !array_key_exists('erp_merchant_no', $reqParams) ||
            !array_key_exists('sale_user', $reqParams) ||
            !array_key_exists('purchase_sn', $reqParams) ||
            !array_key_exists('goods_num', $reqParams)
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //商家编码
        $erp_merchant_no = $reqParams['erp_merchant_no'];
        if (empty($erp_merchant_no)) {
            $returnMsg = ['code' => '2012', 'msg' => '商家编码不能为空'];
            return response()->json($returnMsg);
        }
        //销售客户名称
        $sale_user = $reqParams['sale_user'];
        if (empty($sale_user)) {
            $returnMsg = ['code' => '2004', 'msg' => '请选择客户'];
            return response()->json($returnMsg);
        }
        //采购单号
        $purchase_sn = $reqParams['purchase_sn'];//采购单号
        if (empty($purchase_sn)) {
            $returnMsg = ['code' => '2003', 'msg' => '采购单号不能为空'];
            return response()->json($returnMsg);
        }
        //需求数量
        $goods_num = intval($reqParams['goods_num']);
        if ($goods_num == 0) {
            $returnMsg = ['code' => '2014', 'msg' => '请输入需求数量'];
            return response()->json($returnMsg);
        }
        //根据销售客户名称查询是否存在该客户
        $saleUserModel = new SaleUserModel();
        $queryType = 1;//查询方式 1，用户名 2，用户id
        $saleUserMsg = $saleUserModel->getSaleUserMsg($sale_user, $queryType);
        if ($saleUserMsg === false) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        if (empty($saleUserMsg)) {
            $returnMsg = ['code' => '2006', 'msg' => '没有该销售用户信息'];
            return response()->json($returnMsg);
        }
        $sale_user_id = intval($saleUserMsg->id);
        //根据采购单号生成需求单号
        $demandGoodsModel = new DemandGoodsModel();
        $demand_sn = $demandGoodsModel->getDemandSn($purchase_sn);
        //需求订单数据
        $demandOrdData = [
            'purchase_sn' => $purchase_sn,
            'demand_sn' => $demand_sn,
            'sale_user_id' => $sale_user_id,
        ];
        //根据商家编码查询商品信息
        $type = 1;//根据商家编码查询
        $goodsModel = new GoodsModel();
        $goodsInfo = $goodsModel->getGoodsInfo($erp_merchant_no, $type);
        if (empty($goodsInfo) || $goodsInfo === false) {
            $returnMsg = ['code' => '2013', 'msg' => '没有该商品信息'];
            return response()->json($returnMsg);
        }
        $demandGoodsData[] = [
            'purchase_sn' => $purchase_sn,
            'demand_sn' => $demand_sn,
            'erp_merchant_no' => trim($goodsInfo->erp_merchant_no),
            'goods_num' => intval($goods_num),
            'goods_name' => trim($goodsInfo->goods_name),
            'erp_prd_no' => trim($goodsInfo->erp_prd_no),
        ];
        //商品需求表数据写入商品需求表
        $addRes = $demandGoodsModel->batchInsert($demandOrdData, $demandGoodsData);
        $returnMsg = ['code' => '2016', 'msg' => '添加失败'];
        if ($addRes) {
            $returnMsg = ['code' => '2015', 'msg' => '添加成功'];
        }
        return response()->json($returnMsg);
    }//end of function


    /**
     * description:需求管理-过往需求-获取过往需求单（已关闭采购单）列表数据
     * editor:zhangdong
     * date : 2018.06.27
     */
    public function getCompletePurList(Request $request)
    {
        $reqParams = $request->toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //获取过往需求单（已关闭采购单）
        $purchaseDateModel = new PurchaseDateModel();
        //采购单状态：1,准备中;2,进行中;3,提货中;4,到货分配中;5,关闭
        $status = 5;
        $queryType = 1;//查询方式 1，按状态查询
        $purchaseDate = $purchaseDateModel->getPurchaseMsg($status, $queryType, $pageSize);
        if ($purchaseDate === false) {
            $purchaseDate = '';
        }
        $returnMsg = [
            'purchaseDate' => $purchaseDate,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:需求管理-提报需求-商品上传-组装销售用户
     * editor:zhangdong
     * date : 2018.07.03
     */
    private function createSaleUser($userData)
    {
        $saleUser = [];
        foreach ($userData as $key => $value) {
            if ($key <= 12) continue;
            $saleUserName = $value;
            //检查是否存在当前用户
            $saleUserModel = new SaleUserModel();
            $queryType = 1;//查询方式 1，用户名 2，用户id
            $saleUserMsg = $saleUserModel->getSaleUserMsg($saleUserName, $queryType);
            if (empty($saleUserMsg)) continue;
            $sale_user_id = intval($saleUserMsg->id);
            $saleUser[$key] = $sale_user_id;
        }
        return $saleUser;
    }

    /**
     * description:需求管理-提报需求-商品上传-组装相关需求表的写入数据
     * editor:zhangdong
     * params:$insertData:表格数据；$purchase_sn：采购单号；$demand_sn：需求单号；$saleUser：销售用户
     * date : 2018.07.03
     */
    private function createDemandDataStop($insertData, $purchase_sn, $saleUser)
    {
        $goodsModel = new GoodsModel();
        $demandGoodsModel = new DemandGoodsModel();
        $demandGoodsData = [];
        $demandCountData = [];
        $userGoodsData = [];
        //根据采购单号生成需求单号
        $demand_sn = $demandGoodsModel->getDemandSn($purchase_sn);
        $demandOrdData = [
            'purchase_sn' => $purchase_sn,
            'demand_sn' => $demand_sn,
        ];
        $arrErpNo = [];
        $arrSpecSn = [];
        foreach ($insertData as $key => $value) {
            if ($key === 0) continue;//第一行数据为标题头，不写入
            //根据商家编码或者商品规格码查询商品信息
            $queryData = trim($value[3]);
            $type = 1;//根据商家编码查询
            if (empty($queryData)) {
                $queryData = trim($value[4]);
                $type = 2;//根据规格码查询
                if (empty($queryData)) continue;
            }
            $goodsInfo = $goodsModel->getGoodsInfo($queryData, $type);
            if (empty($goodsInfo) || $goodsInfo === false) {
                //如果没有查到对应商品信息则将其保存
                if ($type == 1) {
                    $arrErpNo[$key] = $queryData;
                }
                if ($type == 2) {
                    $arrSpecSn[$key] = $queryData;
                }
                continue;
            }
            //商品需求数据
            $erp_merchant_no = trim($value[3]);
            $spec_sn = trim($goodsInfo->spec_sn);
            $goods_num = intval($value[12]);
            $demandGoodsData[] = [
                'purchase_sn' => $purchase_sn,
                'demand_sn' => $demand_sn,
                'erp_merchant_no' => $erp_merchant_no,
                'spec_sn' => $spec_sn,
                'goods_num' => $goods_num,
                'recover_num' => $goods_num,
                'goods_name' => trim($goodsInfo->goods_name),
                'erp_prd_no' => trim($goodsInfo->erp_prd_no),
            ];
            //依据采购单号查询需求统计表中是否已经有对应商品，有则叠加无则新增
            $checkGoods = $demandGoodsModel->checkGoods($purchase_sn, $spec_sn);
            if ($checkGoods == 1) {//同一采购期内如果已经有商品数据了则更新商品总需求
                $demandGoodsModel->updateGoodsNum($purchase_sn, $spec_sn, $goods_num);
            } elseif ($checkGoods > 1) {//同一采购期内如果有多个同样的商品数据则记录异常日志
                $log = new Logger('demand_count');
                $log->pushHandler(new StreamHandler(storage_path('logs/demand_count.log'), Logger::INFO));
                $log->addInfo('采购单号：' . $purchase_sn . '-商品规格码：' . $spec_sn . '商品统计数据异常');
            } elseif ($checkGoods == 0) {//同一采购期内如果没有商品数据则新增商品
                $demandCountData[] = [
                    'purchase_sn' => $purchase_sn,
                    'goods_name' => trim($goodsInfo->goods_name),
                    'spec_sn' => trim($goodsInfo->spec_sn),
                    'erp_merchant_no' => $erp_merchant_no,
                    'goods_num' => $goods_num,
                ];
            }
            //组装用户商品需求数据
            foreach ($saleUser as $userKey => $item) {
                $userGoodsNum = intval($value[$userKey]);
                $sale_user_id = $item;
                if ($userGoodsNum > 0) {
                    $userGoodsData[] = [
                        'purchase_sn' => $purchase_sn,
                        'demand_sn' => $demand_sn,
                        'spec_sn' => trim($goodsInfo->spec_sn),
                        'erp_merchant_no' => $erp_merchant_no,
                        'goods_num' => $userGoodsNum,
                        'recover_num' => $userGoodsNum,
                        'sale_user_id' => $sale_user_id,
                    ];
                }
            }
        }//end of foreach
        //数据异常的商品
        $tipMsg = '';
        if (count($arrErpNo) > 0) {
            $tipMsg .= '商家编码为：' . implode(',', $arrErpNo) . '的商品系统中未找到';
        }
        if (count($arrSpecSn) > 0) {
            $tipMsg .= 'sku编码为：' . implode(',', $arrSpecSn) . '的商品系统中未找到';
        }
        $demandData = [
            'demandOrdData' => $demandOrdData,//需求订单数据
            'demandGoodsData' => $demandGoodsData,//商品需求数据
            'userGoodsData' => $userGoodsData,//销售用户商品数据
            'demandCountData' => $demandCountData,//商品需求统计数据
            'tipMsg' => $tipMsg,//异常数据提示信息
        ];
        return $demandData;
    }

    /**
     * description:需求管理-提报需求-商品上传-组装相关需求表的写入数据
     * editor:zhangdong
     * params:$insertData:表格数据；$purchase_sn：采购单号；$demand_sn：需求单号；$saleUser：销售用户
     * date : 2018.09.21
     */
    private function createDemandData($insertData, $saleUser)
    {
        $goodsModel = new GoodsModel();
        $demandGoodsModel = new DemandGoodsModel();
        $demandGoodsData = [];
        $userGoodsData = [];
        //根据采购单号生成需求单号
        $demand_sn = $demandGoodsModel->generalDemandSn();
        $demandOrdData = [
            'demand_sn' => $demand_sn,
        ];
        $arrErpNo = [];
        $arrSpecSn = [];
        foreach ($insertData as $key => $value) {
            if ($key === 0) continue;//第一行数据为标题头，不写入
            //根据商家编码或者商品规格码查询商品信息
            $queryData = trim($value[3]);
            $type = 1;//根据商家编码查询
            if (empty($queryData)) {
                $queryData = trim($value[4]);
                $type = 2;//根据规格码查询
                if (empty($queryData)) continue;
            }
            $goodsInfo = $goodsModel->getGoodsInfo($queryData, $type);
            if (empty($goodsInfo) || $goodsInfo === false) {
                //如果没有查到对应商品信息则将其保存
                if ($type == 1) {
                    $arrErpNo[$key] = $queryData;
                }
                if ($type == 2) {
                    $arrSpecSn[$key] = $queryData;
                }
                continue;
            }
            //商品需求数据
            $erp_merchant_no = trim($value[3]);
            $spec_sn = trim($goodsInfo->spec_sn);
            $goods_num = intval($value[12]);
            $demandGoodsData[] = [
                'demand_sn' => $demand_sn,
                'erp_merchant_no' => $erp_merchant_no,
                'spec_sn' => $spec_sn,
                'goods_num' => $goods_num,
                'goods_name' => trim($goodsInfo->goods_name),
                'erp_prd_no' => trim($goodsInfo->erp_prd_no),
            ];
            //组装用户商品需求数据
            foreach ($saleUser as $userKey => $item) {
                $userGoodsNum = intval($value[$userKey]);
                $sale_user_id = $item;
                if ($userGoodsNum > 0) {
                    $userGoodsData[] = [
                        'demand_sn' => $demand_sn,
                        'spec_sn' => trim($goodsInfo->spec_sn),
                        'erp_merchant_no' => $erp_merchant_no,
                        'goods_num' => $userGoodsNum,
                        'sale_user_id' => $sale_user_id,
                    ];
                }
            }
        }//end of foreach
        //数据异常的商品
        $tipMsg = '';
        if (count($arrErpNo) > 0) {
            $tipMsg .= '商家编码为：' . implode(',', $arrErpNo) . '的商品系统中未找到';
        }
        if (count($arrSpecSn) > 0) {
            $tipMsg .= 'sku编码为：' . implode(',', $arrSpecSn) . '的商品系统中未找到';
        }
        $demandData = [
            'demandOrdData' => $demandOrdData,//需求订单数据
            'demandGoodsData' => $demandGoodsData,//商品需求数据
            'userGoodsData' => $userGoodsData,//销售用户商品数据
            'tipMsg' => $tipMsg,//异常数据提示信息
        ];
        return $demandData;
    }

    /**
     * description:需求管理-需求列表-查看需求单详情-根据需求单号获取商品需求数据
     * editor:zhangdong
     * date : 2018.09.27
     */
    public function demandDetail(Request $request)
    {
        $reqParams = $request->toArray();
        //判断所需参数是否传入
        if (!isset($reqParams['demand_sn'])) {
            $returnMsg = ['code' => '2010', 'msg' => '需求单号不能为空'];
            return response()->json($returnMsg);
        }
        //需求单号
        $demand_sn = $reqParams['demand_sn'];
        $demandGoodsModel = new DemandGoodsModel();
        $demandGoodsData = $demandGoodsModel->getDemandGoodsData($demand_sn);
        $returnMsg = [
            'demand_sn' => $demand_sn,
            'demandGoodsData' => $demandGoodsData,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:销售模块-需求管理-需求商品报价-获取需求列表
     * editor:zhangdong
     * date : 2018.10.09
     */
    public function getNeedGoodsList(Request $request)
    {
        $reqParams = $request->toArray();
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //获取并组装列表数据
        $purchaseDateModel = new PurchaseDateModel();
        $params['keywords'] = $keywords;
        $needGoodsList = $purchaseDateModel->getNeedGoodsList($params, $pageSize);
        $returnMsg = response()->json([
            'needGoodsList' => $needGoodsList
        ]);
        return $returnMsg;

    }

    /**
     * description:销售模块-需求管理-需求商品报价-获取需求列表-点击报价计算-获取需求单下面的商品信息
     * editor:zhangdong
     * date : 2018.10.10
     */
    public function queryNeedGoodsInfo(Request $request)
    {
        $reqParams = $request->toArray();
        //判断所需参数是否传入
        if (!isset($reqParams['demand_sn'])) {
            $returnMsg = ['code' => '2010', 'msg' => '需求单号不能为空'];
            return response()->json($returnMsg);
        }
        //需求单号
        $demand_sn = trim($reqParams['demand_sn']);
        //仓库id
        $store_id = isset($reqParams['store_id']) ? intval($reqParams['store_id']) : 1002;
        $demandGoodsModel = new DemandGoodsModel();
        //获取自采毛利率档位信息
        $goodsModel = new GoodsModel();
        $pickMarginRate = $goodsModel->getPickMarginInfo();
        $arrPickRate = [];
        foreach ($pickMarginRate as $item) {
            $arrPickRate[] = sprintf('%.0f%%', trim($item['pick_margin_rate']));
        }
        //根据部门获取费用项
        $loginUserInfo = $request->user();
        $params_depId = 0;
        if (isset($reqParams['department_id'])) {
            $params_depId = intval($reqParams['department_id']);
        }
        $department_id = $params_depId > 0 ? $params_depId : intval($loginUserInfo->department_id);
        //获取部门信息
        $departmentModel = new DepartmentModel();
        $departmentInfo = $departmentModel->getDepartmentInfo();
        //检查是否存在当前的部门
        $found_key = $goodsModel->twoArraySearch($departmentInfo, $department_id, 'department_id');
        if ($found_key === false) {
            $returnMsg = ['code' => '2035', 'msg' => '部门信息有误，请联系管理员'];
            return response()->json($returnMsg);
        }
        $chargeInfo = $goodsModel->getChargeInfo($department_id);
        $arrCharge = [];
        $totalCharge = 0;
        foreach ($chargeInfo as $item) {
            $arrCharge[] = [sprintf('%.0f%%', trim($item['charge_rate'])) => trim($item['charge_name'])];
            $totalCharge += $item['charge_rate'];
        }
        $arrCharge[] = [sprintf('%.0f%%', $totalCharge) => '费用合计'];
        //获取当前需求单有哪几个品牌
        $demandGoodsInfo = $demandGoodsModel->queryNeedGoodsInfo($demand_sn, $pickMarginRate, $chargeInfo, $store_id);
        $brand_info = [];
        if (!empty($demandGoodsInfo['brandInfo'])) {
            $brand_info = $goodsModel->array_unique_fb($demandGoodsInfo['brandInfo']);
        }
        //检查该需求单的定价折扣是否已经保存，如果已经保存则无需再次保存
        $pricingInfo = $demandGoodsModel->getPricingInfo($demand_sn);
        if ($pricingInfo->count() >= 1) {
            unset($demandGoodsInfo['arrPricingRate']);
        }
        if (!empty($demandGoodsInfo['arrPricingRate'])) {
            //将定价折扣存入表中
            $arrPricingRate = $demandGoodsInfo['arrPricingRate'];
            $demandGoodsModel->savePricingRate($arrPricingRate);
        }
        $returnMsg = [
            'demand_sn' => $demand_sn,
            'store_id' => $store_id,
            'department_id' => $department_id,
            'arrPickRate' => $arrPickRate,
            'arrCharge' => $arrCharge,
            'departmentInfo' => $departmentInfo,
            'brand_info' => $brand_info,
            'erpInfo' => $demandGoodsInfo['erpInfo'],
            'demandGoodsInfo' => $demandGoodsInfo['goodsBaseInfo'],
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:点击报价计算-报价计算页面-新增自采毛利率
     * editor:zhangdong
     * date : 2018.10.12
     */
    public function addMarginRate(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['mar_rate'])) {
            $returnMsg = ['code' => '2032', 'msg' => '请输入自采毛利率'];
            return response()->json($returnMsg);
        }
        $mar_rate = intval($reqParams['mar_rate']);
        $goodsModel = new GoodsModel();
        //检查当前毛利率是否已经存在，存在则无需新增
        $queryRes = $goodsModel->getMarRate($mar_rate);
        if (count($queryRes) >= 1) {
            $returnMsg = ['code' => '2033', 'msg' => '该档位毛利率已经存在，无需添加'];
            return response()->json($returnMsg);
        }
        //将该毛利率新增
        $addRes = $goodsModel->addNewMarRate($mar_rate);
        //删除redis中的自采毛利率信息-再次获取时将获取最新数据
        $purMethodInfo = Redis::del('marginRateInfo');
        $returnMsg = ['code' => '2015', 'msg' => '添加成功'];
        if (!$addRes) {
            $returnMsg = ['code' => '2016', 'msg' => '添加失败'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:点击报价计算-报价计算页面-单个修改定价折扣
     * editor:zhangdong
     * date : 2018.10.12
     */
    public function modifyPricingRate(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['demand_sn']) ||
            !isset($reqParams['pricing_rate']) ||
            !isset($reqParams['store_id'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $demand_sn = trim($reqParams['demand_sn']);
        $spec_sn = trim($reqParams['spec_sn']);
        $pricing_rate = trim($reqParams['pricing_rate']);
        $store_id = intval($reqParams['store_id']);
        $goodsModel = new GoodsModel();
        $modifyRes = $goodsModel->updatePricRate($demand_sn, $spec_sn, $pricing_rate);

        if (!$modifyRes) {
            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
            return response()->json($returnMsg);
        }
        //计算修改之后的值
        //查询商品信息
        $demandGoodsModel = new DemandGoodsModel();

        $demandGoodsInfo = $demandGoodsModel->getDemGoodsInfo($demand_sn, $spec_sn);
        $goodsInfo = $demandGoodsInfo[0];
        $gold_discount = trim($goodsInfo->gold_discount);
        $black_discount = trim($goodsInfo->black_discount);
        $spec_price = trim($goodsInfo->spec_price);
        $spec_weight = trim($goodsInfo->spec_weight);
        $exw_discount = trim($goodsInfo->exw_discount);
        $goodsPrice = $goodsModel->calculateGoodsPrice($spec_price, $gold_discount, $black_discount);
        //金卡价=美金原价*金卡折扣
        $goodsInfo->gold_price = $goodsPrice['goldPrice'];
        //黑卡价=美金原价*黑卡折扣
        $goodsInfo->black_price = $goodsPrice['blackPrice'];
        //根据部门获取费用项
        $loginUserInfo = $request->user();
        $department_id = intval($loginUserInfo->department_id);
        $chargeInfo = $goodsModel->getChargeInfo($department_id);
        $arrCharge = [];
        $totalCharge = 0;
        foreach ($chargeInfo as $item) {
            $arrCharge[] = [sprintf('%.0f%%', trim($item['charge_rate'])) => trim($item['charge_name'])];
            $totalCharge += $item['charge_rate'];
        }
        $arrCharge[] = [sprintf('%.0f%%', $totalCharge) => '费用合计'];
        //获取erp仓库信息-重价系数（默认为香港仓）
        $goodsHouseInfo = $goodsModel->getErpStoreInfo($store_id);
        $store_factor = trim($goodsHouseInfo[0]['store_factor']);
        $goodsInfo->store_name = trim($goodsHouseInfo[0]['store_name']);
        //获取有关erp的所有商品数据
        $erpGoodsData = $goodsModel->getErpGoodsData($spec_weight, $spec_price, $store_factor, $exw_discount);
        //重价比=重量/美金原价/重价系数/100
        $goodsInfo->high_price_ratio = $erpGoodsData['highPriceRatio'];
        //重价比折扣 = exw折扣+重价比
        $hrp_discount = $erpGoodsData['hprDiscount'];
        $goodsInfo->hpr_discount = $hrp_discount;
        //erp成本价=美金原价*重价比折扣*汇率
        $goodsInfo->erp_cost_price = $erpGoodsData['erpCostPrice'];
        //获取自采毛利率档位信息
        $pickMarginRate = $goodsModel->getPickMarginInfo();
        $arrMarginRate = [];
        foreach ($pickMarginRate as $item) {
            $marginRate = sprintf('%.0f%%', $item['pick_margin_rate']);//自采毛利率当前档位
            $rateData = round($erpGoodsData['hprDiscount'] / (1 - $item['pick_margin_rate'] / 100), 2);
            $arrMarginRate[] = [$marginRate => $rateData];
        }
        $goodsInfo->arrMarginRate = $arrMarginRate;
        $goodsInfo->pricing_rate = $pricing_rate;
        //重价比折扣 = exw折扣+重价比
        $hrp_discount = $erpGoodsData['hprDiscount'];
        $afterModData = $goodsModel->calculPricingInfo($spec_price, $pricing_rate, $hrp_discount, $chargeInfo);
        $goodsInfo->salePrice = $afterModData['salePrice'];
        $goodsInfo->saleMarRate = $afterModData['saleMarRate'];
        $goodsInfo->runMarRate = $afterModData['runMarRate'];
        $goodsInfo->arrChargeRate = $afterModData['arrChargeRate'];
        $returnMsg = [
            'arrCharge' => $arrCharge,
            'goodsInfo' => $goodsInfo,
        ];
        return $returnMsg;
    }

    /**
     * description:点击报价计算-报价计算页面-批量修改定价折扣
     * editor:zhangdong
     * date : 2018.10.13
     */
    public function batchModPricRate(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['demand_sn']) ||
            !isset($reqParams['pick_margin_rate']) ||
            !isset($reqParams['store_id'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $demand_sn = trim($reqParams['demand_sn']);
        $store_id = intval($reqParams['store_id']);
        $pick_margin_rate = trim($reqParams['pick_margin_rate']);
        //查询该需求单对应的商品信息
        $demandGoodsModel = new DemandGoodsModel();
        $goodsModel = new GoodsModel();
        //检查自采毛利率中是否有该档位
        $pickRateInfo = $goodsModel->getPickMarginInfo($pick_margin_rate);
        if (count($pickRateInfo) == 0) {
            $returnMsg = ['code' => '2005', 'msg' => '没有该档位毛利率'];
            return response()->json($returnMsg);
        }
        $goodsBaseInfo = $demandGoodsModel->getDemGoodsInfo($demand_sn);
        //获取erp仓库信息-重价系数（默认为香港仓）
        $goodsHouseInfo = $goodsModel->getErpStoreInfo($store_id);
        $store_factor = trim($goodsHouseInfo[0]['store_factor']);
        foreach ($goodsBaseInfo as $value) {
            $spec_weight = trim($value->spec_weight);//商品重量
            $spec_sn = trim($value->spec_sn);
            $spec_price = trim($value->spec_price);
            $exw_discount = trim($value->exw_discount); //exw折扣
            //获取有关erp的所有商品数据
            $erpGoodsData = $goodsModel->getErpGoodsData($spec_weight, $spec_price, $store_factor, $exw_discount);
            //根据所选自采毛利率档位计算定价折扣
            //定价折扣=自采毛利率=重价比折扣/（1-对应档位利率）
            $pricing_rate = round($erpGoodsData['hprDiscount'] / (1 - $pick_margin_rate / 100), 2);
            //更新需求商品定价折扣
            $modifyRes = $goodsModel->updatePricRate($demand_sn, $spec_sn, $pricing_rate);
        }
        $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        if (!$modifyRes) {
            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        }
        return response()->json($returnMsg);
    }


    /**
     * description:销售模块-需求管理-获取费用列表
     * editor:zhangdong
     * date : 2018.10.15
     */
    public function getChargeList(Request $request)
    {
        $loginUserInfo = $request->user();
        $goodsModel = new GoodsModel();
        $department_id = intval($loginUserInfo->department_id);
        //查询是否存在该部门
        $departmentModel = new DepartmentModel();
        $dep_info = $departmentModel->getDepartmentInfo($department_id);
        if (count($dep_info) == 0) {
            $returnMsg = ['code' => '2035', 'msg' => '您的部门信息有误，请联系管理员'];
            return response()->json($returnMsg);
        }
        $departmentName = $dep_info[0]['de_name'];
        $chargeInfo = $goodsModel->getChargeInfo($department_id);
        $returnMsg = [
            'departmentName' => $departmentName,
            'chargeInfo' => $chargeInfo,
        ];
        return $returnMsg;
    }

    /**
     * description:销售模块-需求管理-获取费用列表-新增费用项
     * editor:zhangdong
     * date : 2018.10.15
     */
    public function addCharge(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['charge_rate']) || !isset($reqParams['charge_name'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //费用比例
        $charge_rate = floatval($reqParams['charge_rate']);
        if ($charge_rate > 100 || $charge_rate <= 0) {
            $returnMsg = ['code' => '2037', 'msg' => '费用比例设置有误'];
            return response()->json($returnMsg);
        }
        $goodsModel = new GoodsModel();
        $loginUserInfo = $request->user();
        $department_id = intval($loginUserInfo->department_id);
        //费用名称
        $charge_name = trim($reqParams['charge_name']);
        //检查是否已经存在该费用
        $goodsModel = new GoodsModel();
        $getCharMsg = $goodsModel->getCharMsg($charge_name, $department_id);
        if ($getCharMsg->count() >= 1) {
            $returnMsg = ['code' => '2036', 'msg' => '该费用项已经存在，请勿重复添加'];
            return response()->json($returnMsg);
        }
        //保存添加的费用项
        $addRes = $goodsModel->addCharge($charge_rate, $charge_name, $department_id);
        $returnMsg = ['code' => '2015', 'msg' => '添加成功'];
        if (!$addRes) {
            $returnMsg = ['code' => '2016', 'msg' => '添加失败'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:销售模块-需求管理-获取费用列表-修改费用项
     * editor:zhangdong
     * date : 2018.10.16
     */
    public function modifyCharge(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['charge_rate']) || !isset($reqParams['charge_name'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $charge_rate = floatval($reqParams['charge_rate']);//费用比例
        if ($charge_rate <= 0) {
            $returnMsg = ['code' => '2037', 'msg' => '费用比例设置有误'];
            return response()->json($returnMsg);
        }
        $charge_name = trim($reqParams['charge_name']);//费用名称
        $loginInfo = $request->user();
        $department_id = intval($loginInfo->department_id);
        //根据部门和费用名称修改费用比例
        $goodsModel = new GoodsModel();
        $modRes = $goodsModel->modifyCharge($charge_rate, $charge_name, $department_id);
        $returnMsg = ['code' => '2038', 'msg' => '修改成功'];
        if (!$modRes) {
            $returnMsg = ['code' => '2039', 'msg' => '修改失败'];
        }
        return response()->json($returnMsg);

    }

    /**
     * description:需求管理-需求列表-新增需求页面
     * editor:zhangdong
     * date : 2018.10.18
     */
    public function newDemPage(Request $request)
    {
        //检查部门是否存在
        $loginInfo = $request->user();
        $department_id = intval($loginInfo->department_id);
        $departModel = new DepartmentModel();
        $departInfo = $departModel->getDepartmentInfo($department_id);
        if (count($departInfo) == 0) {
            $returnMsg = ['code' => '2035', 'msg' => '部门信息有误，请联系管理员'];
            return response()->json($returnMsg);
        }
        //查询对应部门下的销售用户
        $saleUserModel = new SaleUserModel();
        $saleUserInfo = $saleUserModel->getSaleUser($department_id);
        $returnMsg = [
            'saleUserInfo' => $saleUserInfo
        ];
        return $returnMsg;


    }

    /**
     * description:需求管理-提报需求-需求商品上传-第三版
     * editor:zhangdong
     * date : 2018.10.17
     */
    public function demGoodsUp(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['upload_file']) ||
            !isset($reqParams['sale_user_id']) ||
            !isset($reqParams['expire_time'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //检查部门是否存在
        $loginInfo = $request->user();
        $department_id = intval($loginInfo->department_id);
        $departModel = new DepartmentModel();
        $departInfo = $departModel->getDepartmentInfo($department_id);
        if (count($departInfo) == 0) {
            $returnMsg = ['code' => '2035', 'msg' => '部门信息有误，请联系管理员'];
            return response()->json($returnMsg);
        }
        //检查销售用户是否存在
        $saleUserModel = new SaleUserModel();
        $sale_user_id = intval($reqParams['sale_user_id']);
        $saleUserInfo = $saleUserModel->getSaleUserMsg($sale_user_id, 2);
        if (is_null($saleUserInfo)) {
            $returnMsg = ['code' => '2006', 'msg' => '没有该销售用户的信息'];
            return response()->json($returnMsg);
        }
        $expire_time = trim($reqParams['expire_time']);//截止日期
        $demandGoodsModel = new DemandGoodsModel();
        //开始导入数据
        $file = $_FILES;
        //检查上传文件是否合格
        $excuteExcel = new ExcuteExcel();
        $fileName = '提报需求-商品明细';//要上传的文件名，将对上传的文件名做比较
        $res = $excuteExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['商家编码', '商品规格码', '需求数量'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response()->json($returnMsg);
            }
        }
        $demandModel = new DemandModel();
        //组装相关需求表的写入数据
        $demandData = $demandModel->createDemandData($res, $department_id, $sale_user_id, $expire_time);
        //如果发现有数据异常则直接终止流程
        if (strlen($demandData['tipMsg']) > 0) {
            $returnMsg = ['code' => '2040', 'msg' => '商品上传被终止，请检查' . $demandData['tipMsg']];
            return response()->json($returnMsg);
        }
        $demandOrdData = $demandData['demandOrdData'];//需求订单数据
        $goodsData = $demandData['demandGoodsData'];//需求商品数据
        //商品需求表数据批量写入商品需求表及需求订单表
        $uploadRes = $demandGoodsModel->batchInsert_b($demandOrdData, $goodsData);
        $returnMsg = ['code' => '2001', 'msg' => '上传失败'];
        if ($uploadRes) {
            $returnMsg = ['code' => '2000', 'msg' => '上传成功'];
            //记录日志
            $uploadName = $file['upload_file']['name'];
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'demand,demand_goods,demand_count',
                'bus_desc' => '销售部-上传采购单商品需求,需求单号：' . $demandOrdData['demand_sn'],
                'bus_value' => '表名：' . $uploadName,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '销售模块-需求管理-提报需求',
                'module_id' => 3,
                'have_detail' => 0,
            ];
            $operateLogModel->insertLog($logData);

        }
        return response()->json($returnMsg);
    }

    /**
     * description:需求列表-需求详情
     * editor:zhangdong
     * date : 2018.10.18
     */
    public function queryDemDetail(Request $request)
    {
        $reqParams = $request->toArray();
        //判断所需参数是否传入
        if (!isset($reqParams['demand_sn'])) {
            $returnMsg = ['code' => '2010', 'msg' => '需求单号不能为空'];
            return response()->json($returnMsg);
        }
        //需求单号
        $demand_sn = trim($reqParams['demand_sn']);
        $demandGoodsModel = new DemandGoodsModel();
        //根据部门获取费用项
        $loginUserInfo = $request->user();
        $params_depId = 0;
        if (isset($reqParams['department_id'])) {
            $params_depId = intval($reqParams['department_id']);
        }
        $department_id = $params_depId > 0 ? $params_depId : intval($loginUserInfo->department_id);
        //获取部门信息
        $departmentModel = new DepartmentModel();
        $departmentInfo = $departmentModel->getDepartmentInfo($department_id);
        $depart_name = $departmentInfo[0]['de_name'];
        //检查是否存在当前的部门
        $goodsModel = new GoodsModel();
        if (empty($depart_name)) {
            $returnMsg = ['code' => '2035', 'msg' => '部门信息有误，请联系管理员'];
            return response()->json($returnMsg);
        }
        $chargeInfo = $goodsModel->getChargeInfo($department_id);
        $arrCharge = [];
        $totalCharge = 0;
        foreach ($chargeInfo as $item) {
            $arrCharge[] = [sprintf('%.0f%%', trim($item['charge_rate'])) => trim($item['charge_name'])];
            $totalCharge += $item['charge_rate'];
        }
        $arrCharge[] = [sprintf('%.0f%%', $totalCharge) => '费用合计'];
        //获取当前需求单有哪几个品牌
        $demandGoodsInfo = $demandGoodsModel->getDemDetail($demand_sn, $chargeInfo);
        //根据需求单获取需求单信息
        $demandModel = new DemandModel();
        $demOrdInfo = $demandModel->getDemOrdMsg($demand_sn);
        $returnMsg = [
            'demand_sn' => $demand_sn,
            'depart_name' => $depart_name,
            'demOrdInfo' => $demOrdInfo,
            'arrCharge' => $arrCharge,
            'demandGoodsInfo' => $demandGoodsInfo['goodsBaseInfo'],
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:需求列表-需求详情-修改定价折扣
     * editor:zhangdong
     * date : 2018.10.19
     */
    public function updateSaleRate(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['demand_sn']) ||
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['sale_discount']) ||
            !isset($reqParams['is_change'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $demand_sn = trim($reqParams['demand_sn']);
        $spec_sn = trim($reqParams['spec_sn']);
        $sale_discount = trim($reqParams['sale_discount']);
        //是否要更新用户商品销售折扣 1，更新 2，不更新
        $is_change = intval($reqParams['is_change']);
        $demandModel = new DemandModel();
        //查询需求单信息
        $demOrdInfo = $demandModel->getDemOrdMsg($demand_sn);
        //先更新demand_goods表，
        $modDemGoods = $demandModel->modSaleRate($demand_sn, $spec_sn, $sale_discount);
        //根据选择更新用户商品表
        if ($is_change == 1) {
            $sale_user_id = intval($demOrdInfo->sale_user_id);
            $depart_id = intval($demOrdInfo->department);
            $demandModel->modSaleUserRate($spec_sn, $sale_discount, $sale_user_id, $depart_id);
        }
        if (!$modDemGoods) {
            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
            return response()->json($returnMsg);
        }
        //计算修改之后的值
        //查询商品信息
        $demandGoodsModel = new DemandGoodsModel();
        $demandGoodsInfo = $demandGoodsModel->getDemDetGoods($demand_sn, $spec_sn);
        $goodsInfo = $demandGoodsInfo[0];
        $spec_price = trim($goodsInfo->spec_price);
        $spec_weight = trim($goodsInfo->spec_weight);
        $exw_discount = trim($goodsInfo->exw_discount);
        $goodsModel = new GoodsModel();
        //根据部门获取费用项
        $loginUserInfo = $request->user();
        $department_id = intval($loginUserInfo->department_id);
        $chargeInfo = $goodsModel->getChargeInfo($department_id);
        $arrCharge = [];
        $totalCharge = 0;
        foreach ($chargeInfo as $item) {
            $arrCharge[] = [sprintf('%.0f%%', trim($item['charge_rate'])) => trim($item['charge_name'])];
            $totalCharge += $item['charge_rate'];
        }
        $arrCharge[] = [sprintf('%.0f%%', $totalCharge) => '费用合计'];
        //获取erp仓库信息-重价系数（默认为香港仓）
        $store_id = 1002;
        $goodsHouseInfo = $goodsModel->getErpStoreInfo($store_id);
        $store_factor = trim($goodsHouseInfo[0]['store_factor']);
        //获取有关erp的所有商品数据
        $erpGoodsData = $goodsModel->getErpGoodsData($spec_weight, $spec_price, $store_factor, $exw_discount);
        $goodsInfo->sale_discount = $sale_discount;
        //重价比折扣 = exw折扣+重价比
        $hrp_discount = $erpGoodsData['hprDiscount'];
        $afterModData = $goodsModel->calculPricingInfo($spec_price, $sale_discount, $hrp_discount, $chargeInfo);
        $goodsInfo->salePrice = $afterModData['salePrice'];
        $goodsInfo->saleMarRate = $afterModData['saleMarRate'];
        $goodsInfo->runMarRate = $afterModData['runMarRate'];
        $goodsInfo->arrChargeRate = $afterModData['arrChargeRate'];
        $returnMsg = [
            'arrCharge' => $arrCharge,
            'goodsInfo' => $goodsInfo,
        ];
        return $returnMsg;
    }

    /**
     * description:销售模块-在途商品管理-需求单列表
     * editor:zongxing
     * date: 2018.10.18
     */
    public function demandPassageList(Request $request)
    {
        if ($request->isMethod('get')) {
            $demand_model = new DemandModel();
            $demand_list_info = $demand_model->demandAllotList($request);

            $code = "1000";
            $msg = "获取需求单列表成功";
            $data = $demand_list_info;
            $return_info = compact('code', 'msg', 'data');

            if (empty($demand_list_info["demand_list"])) {
                $code = "1002";
                $msg = "暂无需求单列表";
                $return_info = compact('code', 'msg');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-在途商品管理-需求单对应采购期的批次列表
     * editor:zongxing
     * date: 2018.10.18
     */
//    public function passageRealPurchaseList(Request $request)
//    {
//        if ($request->isMethod('get')) {
//            $reqParams = $request->toArray();
//
//            if (!isset($reqParams['demand_sn'])) {
//                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
//            } elseif (!isset($reqParams['purchase_sn'])) {
//                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
//            }
//
//            $demand_sn = $reqParams['demand_sn'];
//            $purchase_sn = $reqParams['purchase_sn'];
//
//            //获取批次列表信息
//            $real_purchase_detail = DB::table("real_purchase_detail as rpd")
//                ->select("rpd.real_purchase_sn", "path_way", "method_name", "channels_name", "delivery_time",
//                    "arrive_time", "status", "batch_cat",
//                    DB::raw("count(jms_rpd.spec_sn) as sku_num"),
//                    DB::raw("sum(jms_rpd.day_buy_num) as day_buy_num"),
//                    DB::raw("sum(jms_rpd.day_buy_num * jms_gs.spec_price * jms_rpd.channel_discount) as real_total_price")
//                )
//                ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
//                ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
//                ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
//                ->leftJoin("purchase_demand as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
//                ->leftJoin("purchase_demand_detail as pdd", function ($leftJoin) {
//                    $leftJoin->on("pdd.demand_sn", '=', "pd.demand_sn")
//                        ->on("pdd.purchase_sn", '=', "pd.purchase_sn");
//                })
//                ->leftJoin("goods_spec as gs", function ($leftJoin) {
//                    $leftJoin->on("gs.spec_sn", '=', "rpd.spec_sn")
//                        ->on("gs.erp_prd_no", '=', "rpd.erp_prd_no")
//                        ->on("gs.erp_merchant_no", '=', "rpd.erp_merchant_no");
//                })
//                ->orWhere("rp.demand_sn", $demand_sn)
//                ->orWhere("rp.purchase_sn", $purchase_sn)
//                ->groupBy("real_purchase_sn")
//                ->get();
//            $real_purchase_detail = objectToArrayZ($real_purchase_detail);
//dd($real_purchase_detail);
//            //计算美金原价和人民币价格
//            $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
//            foreach ($real_purchase_detail as $k => $v) {
//                $real_purchase_detail[$k]["real_total_price"] = round($v["real_total_price"], 2);
//                $real_purchase_detail[$k]["cny_total_price"] = round($v["real_total_price"] * $USD_CNY_RATE, 2);
//            }
//
//            if (empty($real_purchase_detail)) {
//                return response()->json(['code' => '1005', 'msg' => '暂无需求单采购期批次列表']);
//            }
//            $code = "1000";
//            $msg = "获取需求单采购期批次列表成功";
//            $data = $real_purchase_detail;
//            $return_info = compact('code', 'msg', 'data');
//        } else {
//            $code = "1001";
//            $msg = "请求错误";
//            $return_info = compact('code', 'msg');
//        }
//        return response()->json($return_info);
//    }
    public function passageRealPurchaseList(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();

            if (!isset($reqParams['demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            } elseif (!isset($reqParams['purchase_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }

            $demand_sn = $reqParams['demand_sn'];
            $purchase_sn = $reqParams['purchase_sn'];

            //获取预采批次列表信息
            $where = function($query)use($demand_sn,$purchase_sn)
            {
                $query->where('rp.demand_sn', '=', $demand_sn)
                    ->where('rp.purchase_sn', '=', $purchase_sn)
                    ->where('rp.batch_cat', '=', 2)
                    ->whereIn('rp.status', ['1','2','3','4','6']);
            };
            $rpd_model = new RealPurchaseDetailModel();
            $real_purchase_detail = $rpd_model->getBatchStatisticsList($where);

            //获取正常批次列表信息
            $where = [
                ['pd.demand_sn', $demand_sn],
                ['rp.purchase_sn', $purchase_sn],
                ['rp.batch_cat', 1]
            ];
            $predict_real_purchase_detail = $rpd_model->getBatchStatisticsList($where,true);
            $real_purchase_detail = array_merge($real_purchase_detail, $predict_real_purchase_detail);

            //计算美金原价和人民币价格
            $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
            foreach ($real_purchase_detail as $k => $v) {
                $real_purchase_detail[$k]["real_total_price"] = round($v["real_total_price"], 2);
                $real_purchase_detail[$k]["cny_total_price"] = round($v["real_total_price"] * $USD_CNY_RATE, 2);
            }
            $real_purchase_list = [];
            foreach ($real_purchase_detail as $k => $v) {
                $group_sn = $v['group_sn'];
                if (isset($real_purchase_list[$group_sn])){
                    $real_purchase_list[$group_sn]['batch_info'] = $v;
                }else{
                    $real_purchase_list[$group_sn] = $v;
                }
            }

            $code = "1000";
            $msg = "获取需求单采购期批次列表成功";
            $data = $real_purchase_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-在途商品管理-需求单对应采购期的批次详情
     * editor:zongxing
     * date: 2018.11.22
     */
    public function passageRealPurchaseDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();

            if (!isset($reqParams['real_purchase_sn'])) {
                return response()->json(['code' => '1004', 'msg' => '采购批次单号不能为空']);
            }

            //获取批次中商品详情信息
            $real_purchase_sn = $reqParams['real_purchase_sn'];
            $real_purchase_detail = DB::table("real_purchase_detail as rpd")
                ->select("goods_name", "rpd.spec_sn", "rpd.erp_prd_no", "rpd.erp_merchant_no", "day_buy_num",
                    "spec_price", "channel_discount",
                    DB::raw('jms_rpd.day_buy_num * jms_gs.spec_price * jms_rpd.channel_discount as spec_total_price')
                )
                ->leftJoin("goods_spec as gs", function ($leftJoin) {
                    $leftJoin->on("gs.spec_sn", "=", "rpd.spec_sn");
                    $leftJoin->on("gs.erp_prd_no", "=", "rpd.erp_prd_no");
                    $leftJoin->on("gs.erp_merchant_no", "=", "rpd.erp_merchant_no");
                })
                ->where("real_purchase_sn", $real_purchase_sn)
                ->groupBy("rpd.spec_sn")
                ->get();
            $real_purchase_detail = objectToArrayZ($real_purchase_detail);

            //计算美金原价和人民币价格
            $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
            foreach ($real_purchase_detail as $k => $v) {
                $real_purchase_detail[$k]["spec_total_price"] = round($v["spec_total_price"], 2);
                $real_purchase_detail[$k]["cny_total_price"] = round($v["spec_total_price"] * $USD_CNY_RATE, 2);
            }

            $code = "1000";
            $msg = "获取需求单采购期详情成功";
            $data = $real_purchase_detail;
            $return_info = compact('code', 'msg', 'data');

            if (empty($real_purchase_detail)) {
                $code = "1000";
                $msg = "批次单号有误";
                $return_info = compact('code', 'msg');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:需求单详情-商品预采标记
     * author:zhangdong
     * date: 2018.12.20
     */
    public function demandGoodsMark(Request $request)
    {
        $reqParams = $request->toArray();
        if (
            !isset($reqParams['demand_sn']) ||
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['is_mark'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $demand_sn = trim($reqParams['demand_sn']);
        $spec_sn = trim($reqParams['spec_sn']);
        $is_mark = intval($reqParams['is_mark']);
        //检查当前需求单对应的子单是否允许做预采标记-只有BD状态才能做预采标记
        $demandModel = new DemandModel();
        $demandStatus = $demandModel->getSubStatusByDemandSn($demand_sn);
        if ($demandStatus != 2) {
            $returnMsg = ['code' => '2050', 'msg' => '当前子订单状态禁止标记预采'];
            return response()->json($returnMsg);
        }
        //开始标记商品
        $dgModel = new DemandGoodsModel();
        $markRes = $dgModel->updateGoodsMark($demand_sn, $spec_sn, $is_mark);
        //修改需求单标记状态
        $dModel = new DemandModel();
        //查询对应需求单下的商品标记情况
        $demandGoods = $dgModel->getDemandGoodsInfo($demand_sn);
        $arrDemandGoods = objectToArray($demandGoods);
        //检查该需求单下的被标记商品个数
        $markNum = searchTwoArray($arrDemandGoods, '1', 'is_mark');
        $countMarkNum = count($markNum);
        //当且仅当只有一个商品被标记了才对需求单做标记
        if ($countMarkNum === 1) {
            $dModel->updateMark($demand_sn, $countMarkNum);
        }
        //所有商品都没有被标记才将需求单改为未标记
        if ($countMarkNum === 0) {
            $dModel->updateMark($demand_sn, $countMarkNum);
        }
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($markRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功', 'mark_status' => $is_mark];
        }
        return response()->json($returnMsg);

    }

    /**
     * description:销售模块-需求管理-获取预采列表数据
     * author:zhangdong
     * date : 2019.03.04
     */
    public function advanceBuyList(Request $request)
    {
        $reqParams = $request->toArray();
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //获取并组装列表数据
        $demandModel = new DemandModel();
        $params['keywords'] = $keywords;
        $advanceBuyList = $demandModel->getAdvanceBuyList($params, $pageSize);
        $returnMsg = response()->json([
            'advanceBuyList' => $advanceBuyList
        ]);
        return $returnMsg;

    }





}//end of class