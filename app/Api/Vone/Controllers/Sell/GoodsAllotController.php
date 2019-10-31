<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use App\Model\Vone\DemandModel;
use App\Model\Vone\GoodsModel;
use App\Model\Vone\PurchaseMethodModel;
use Dingo\Api\Http\Request;

//引入时间及日期处理包 add by zhangdong on the 2018.06.28
use Carbon\Carbon;
//引入日志库文件 add by zhangdong on the 2018.06.28
use Illuminate\Support\Facades\DB;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
//引入采购单模型
use App\Model\Vone\PurchaseDateModel;
//引入商品需求统计模型 add by zhangdong on the 2018.07.05
use App\Model\Vone\DemandCountModel;

use Illuminate\Support\Facades\Redis;

//create by zhangdong on the 2018.06.22
class GoodsAllotController extends BaseController
{
    /**
     * description : 商品分配管理-商品分配预测-获取列表数据
     * editor : zhangdong
     * date : 2018.07.02
     */
    public function getGoodsAllotList(Request $request)
    {
        $reqParams = $request->toArray();
        $keywords = array_key_exists('keywords', $reqParams) && !empty($reqParams['keywords']) ?
            trim($reqParams['keywords']) : '';
        //获取列表数据
        $purchaseDateModel = new PurchaseDateModel();
        $queryType = 1;//查询方式 1，按状态查询
        $params['keywords'] = $keywords;
        $goodsAllotMsg = $purchaseDateModel->getGoodsAllotMsg($params, $queryType);
        if ($goodsAllotMsg === false) {
            $goodsAllotMsg = '';
        }
        $returnMsg = [
            'goodsAllotMsg' => $goodsAllotMsg,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description : 商品分配管理-商品分配预测-实时分配查看
     * editor : zhangdong
     * date : 2018.07.05
     */
    public function getGoodsAllot(Request $request)
    {
        $reqParams = $request->toArray();
        if (!array_key_exists('purchase_sn', $reqParams) || empty($reqParams['purchase_sn'])) {
            $returnMsg = ['code' => '2003', 'msg' => '采购单号不能为空'];
            return response()->json($returnMsg);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);
        //查询总表数据
        $demandCountModel = new DemandCountModel();
        //构造实时分配预测数据
        $goodsAllot = $demandCountModel->getGoodsAllot($purchase_sn);
        //获取客户
        $userInfo = $demandCountModel->getUserInfo();
        $arrUser = [];
        foreach ($userInfo as $key => $value) {
            $userName = trim($value->user_name);
            $arrUser[] = $userName;
            array_push($arrUser, '预测数' . $userName);
        }
        $returnMsg = [
            'goodsAllot' => $goodsAllot,
            'userName' => $arrUser,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description : 商品分配管理-商品分配预测-获取批次预测分配详情
     * editor : zhangdong
     * date : 2018.07.05
     */
    public function getGoodsAllotPredict(Request $request)
    {
        if ($request->isMethod("get")) {
            $post_purchase_info = $request->toArray();

            if (empty($post_purchase_info["real_purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '实采单号不能为空']);
            } else if (empty($post_purchase_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }

            //查询总表数据
            $demandCountModel = new DemandCountModel();
            //构造实时分配预测数据
            $post_purchase_info["status"] = 1;
            $goodsAllot = $demandCountModel->getGoodsAllotReal($post_purchase_info);

            $code = "1004";
            $msg = "获取批次预测分配详情失败";
            $return_info = compact('code', 'msg');

            if (!empty($goodsAllot)) {
                //获取客户
                $userInfo = $demandCountModel->getUserInfo();
                $arrUser = [];
                foreach ($userInfo as $key => $value) {
                    $userName = trim($value->user_name);
                    $arrUser[] = $userName;
                    array_push($arrUser, '预测数,' . $userName);
                }
                $returnMsg = [
                    'goodsAllot' => $goodsAllot,
                    'userName' => $arrUser,
                ];

                $code = "1000";
                $msg = "获取批次预测分配详情";
                $data = $returnMsg;
                $return_info = compact('code', 'msg', 'data');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description : 商品分配管理-商品分配预测-获取批次实时分配详情
     * editor : zhangdong
     * date : 2018.07.05
     */
    public function getGoodsAllotReal(Request $request)
    {
        if ($request->isMethod("get")) {
            $post_purchase_info = $request->toArray();

            if (empty($post_purchase_info["real_purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '实采单号不能为空']);
            } else if (empty($post_purchase_info["purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
            }

            //查询总表数据
            $demandCountModel = new DemandCountModel();
            //构造实时分配预测数据
            $post_purchase_info["status"] = 2;
            $goodsAllot = $demandCountModel->getGoodsAllotReal($post_purchase_info);

            $code = "1004";
            $msg = "暂无实际分配批次";
            $return_info = compact('code', 'msg');

            if (!empty($goodsAllot)) {
                //获取客户
                $userInfo = $demandCountModel->getUserInfo();
                $arrUser = [];
                foreach ($userInfo as $key => $value) {
                    $userName = trim($value->user_name);
                    $arrUser[] = $userName;
                    array_push($arrUser, '预测数,' . $userName);
                }
                $returnMsg = [
                    'goodsAllot' => $goodsAllot,
                    'userName' => $arrUser,
                ];

                $code = "1000";
                $msg = "获取批次预测分配详情";
                $data = $returnMsg;
                $return_info = compact('code', 'msg', 'data');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description : 商品分配管理-实采商品分配-实际分配查看
     * editor : zhangdong
     * date : 2018.07.09
     */
    public function goodsRealAllot(Request $request)
    {
        $reqParams = $request->toArray();
        if (!array_key_exists('purchase_sn', $reqParams) || empty($reqParams['purchase_sn'])) {
            $returnMsg = ['code' => '2003', 'msg' => '采购单号不能为空'];
            return response()->json($returnMsg);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);
        //查询总表数据
        $demandCountModel = new DemandCountModel();
        //获取一个采购单下所有待开单状态的实采单号中实际分配的商品数量（此时采购部已经确认完差异了）
        $goodsRealAllot = $demandCountModel->getGoodsRealAllot($purchase_sn);
        //获取客户
        $userInfo = $demandCountModel->getUserInfo();
        $arrUser = [];
        foreach ($userInfo as $key => $value) {
            $userName = trim($value->user_name);
            $arrUser[] = $userName;
            array_push($arrUser, '预测数' . $userName);
        }
        $returnMsg = [
            'goodsRealAllot' => $goodsRealAllot,
            'userName' => $arrUser,
        ];
        return response()->json($returnMsg);

    }


    /**
     * description:获取批次预测分配列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function batchListPredict(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();
            //获取采购期及其商品数据总表
            $goods_model = new GoodsModel();
            $purchase_data_list = $goods_model->getbatchListPredict($purchase_info);

            $code = "1000";
            $msg = "获取采购数据批次列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');

            if (empty($purchase_data_list)) {
                $code = "1002";
                $msg = "暂无采购数据批次";
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
     * description:获取批次实时分配列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function batchListReal(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();
            //获取采购期及其商品数据总表
            $goods_model = new GoodsModel();
            $purchase_data_list = $goods_model->getbatchListReal($purchase_info);

            $code = "1000";
            $msg = "获取采购数据批次列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');

            if (empty($purchase_data_list)) {
                $code = "1002";
                $msg = "暂无采购数据批次";
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
     * description:商品分配管理-商品实际分配（按部门）- 获取批次单列表
     * editor:zhangdong
     * date : 2018.09.28
     * return Object
     */
    public function getRealPurList(Request $request)
    {
        $reqParams = $request->toArray();
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //获取并组装列表数据
        $purchaseDateModel = new PurchaseDateModel();
        $params['keywords'] = $keywords;
        $realPurList = $purchaseDateModel->getRealPurList($params, $pageSize);
        $returnMsg = response()->json([
            'realPurList' => $realPurList
        ]);
        return $returnMsg;
    }

    /**
     * description:商品分配管理-商品实际分配（按部门）- 根据批次单号获取对应商品信息
     * editor:zhangdong
     * date : 2018.09.29
     * return Object
     */
    public function getRealGoodsInfo(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['real_purchase_sn'])) {
            $returnMsg = ['code' => '2029', 'msg' => '批次单号不能为空'];
            return response()->json($returnMsg);
        }
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);
        $purchaseDateModel = new PurchaseDateModel();
        //根据批次单号查询对应商品信息
        $realGoodsInfo = $purchaseDateModel->getRealGoodsInfo($real_purchase_sn);
        $returnMsg = response()->json([
            'real_purchase_sn' => $real_purchase_sn,
            'realGoodsInfo' => $realGoodsInfo,
        ]);
        return $returnMsg;

    }

    /**
     * description:商品分配管理-商品实际分配（按部门）- 以批次单号为基准对商品数量进行分配
     * editor:zhangdong
     * date : 2018.09.29
     * return Object
     */
    public function updateRealAllotNum(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['real_purchase_sn']) || !isset($reqParams['spec_sn']) || !isset($reqParams['real_allot_num'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);
        $spec_sn = trim($reqParams['spec_sn']);
        $real_allot_num = intval($reqParams['real_allot_num']);
        //分配数量不能大于清点数量
        //查询批次单信息
        $purchaseDateModel = new PurchaseDateModel();
        $realPurSnInfo = $purchaseDateModel->getRealPurSnInfo($real_purchase_sn, $spec_sn);
        if (empty($realPurSnInfo)) {
            $returnMsg = ['code' => '2013', 'msg' => '没有该商品信息'];
            return response()->json($returnMsg);
        }
        $allot_num = intval($realPurSnInfo->allot_num);
        if ($real_allot_num > $allot_num) {
            $returnMsg = ['code' => '2030', 'msg' => '分配数量不能大于清点数量'];
            return response()->json($returnMsg);
        }
        //根据批次单号和商品规格码更新对应的实际分配数量
        $updateRealNum = $purchaseDateModel->updateRealNum($real_purchase_sn, $spec_sn, $real_allot_num);
        $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        if (!$updateRealNum) {
            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:商品分配管理-商品分配-获取采购单列表(不论是否挂了需求单)
     * editor:zhangdong
     * date : 2018.09.30
     * return Object
     */
    public function getPurOrderList(Request $request)
    {
        $reqParams = $request->toArray();
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //获取采购方式信息
        $purMethodModel = new PurchaseMethodModel();
        $arrMethodInfo = $purMethodModel -> purMethodInfo;
        //获取并组装列表数据
        $purchaseDateModel = new PurchaseDateModel();
        $params['keywords'] = $keywords;
        $purOrderList = $purchaseDateModel->getPurOrderList($params, $pageSize);
        foreach ($purOrderList as $key => $value) {
            $status = $purchaseDateModel->status[intval($value->status)];
            $purOrderList[$key]->status = $status;
            $channels_list = implode(json_decode($value->channels_list),',');
            $purOrderList[$key]->channels_list = $channels_list;
            $arrMethodId = json_decode($value->method_info);
            $method_info = '';
            foreach ($arrMethodId as $value){
                $searchRes = searchTwoArray($arrMethodInfo, intval($value), 'id');
                if(!empty($searchRes)) $method_info .= $searchRes[0]['method_name'] . ',';
            }
            $purOrderList[$key]->method_info = substr($method_info,0,-1);
        }
        $returnMsg = response()->json([
            'purOrderList' => $purOrderList
        ]);
        return $returnMsg;
    }

    /**
     * description:商品分配管理-商品分配-采购单列表-点击采购单号进入对应需求单号
     * editor:zhangdong
     * date : 2018.09.30
     * return Object
     */
    public function getPurDemOrdList(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['purchase_sn'])) {
            return response()->json(['code' => '2031', 'msg' => '采购单号不能为空']);
        }
        //采购单号
        $purchase_sn = trim($reqParams['purchase_sn']);
        $purchaseDateModel = new PurchaseDateModel();
        $purDemOrdList = $purchaseDateModel->getPurDemOrdList($purchase_sn);
        $returnMsg = response()->json([
            'purchase_sn' => $purchase_sn,
            'purDemOrdList' => $purDemOrdList
        ]);
        return $returnMsg;
    }

    /**
     * description:商品分配管理-商品分配-采购单列表-需求单列表-点击需求单号进入需求单对应的商品信息
     * editor:zhangdong
     * date : 2018.09.30
     * return Object
     */
    public function getPurDemOrdInfo(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['purchase_sn']) || !isset($reqParams['demand_sn'])) {
            return response()->json(['code' => '2005', 'msg' => '参数错误']);
        }
        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        $demand_sn = trim($reqParams['demand_sn']);//需求单号
        $purchaseDateModel = new PurchaseDateModel();
        //根据采购单号和需求单号查询对应商品信息
        $purDemOrdInfo = $purchaseDateModel->getPurDemOrdInfo($purchase_sn, $demand_sn);
        $returnMsg = response()->json([
            'purchase_sn' => $purchase_sn,
            'demand_sn' => $demand_sn,
            'purDemOrdList' => $purDemOrdInfo
        ]);
        return $returnMsg;
    }

    /**
     * description:销售模块-商品分配管理-需求单列表
     * editor:zongxing
     * date: 2018.10.18
     */
    public function demandAllotList(Request $request)
    {
        if ($request->isMethod('get')) {
            $demand_model = new DemandModel();
            $demand_list_info = $demand_model->demandAllotList($request);
            $return_info = ['code' => '1000', 'msg' => '获取需求单列表成功', 'data' => $demand_list_info];
            if (empty($demand_list_info["demand_list"])) {
                $return_info = ['code' => '1002', 'msg' => '暂无需求单列表'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-商品分配管理-需求单汇总信息
     * editor:zongxing
     * date: 2018.10.18
     */
    public function demandAllotDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();
            if (!isset($reqParams['demand_sn']) || empty($reqParams['demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号有误']);
            }
            $demand_sn = $reqParams["demand_sn"];
            $demand_model = new DemandModel();
            $demand_detail_info = $demand_model->demandAllotDetail($demand_sn);

            $code = "1000";
            $msg = "获取需求单详情成功";
            $data = $demand_detail_info;
            $return_info = compact('code', 'msg', 'data');

            if (empty($demand_detail_info)) {
                $code = "1002";
                $msg = "暂无需求单详情";
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
     * description:销售模块-商品分配管理-需求采购期列表
     * editor:zongxing
     * date: 2018.10.18
     */
    public function demandPurchaseList(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();

            if (!isset($reqParams['demand_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '需求单号不能为空']);
            }

            $demand_sn = $reqParams['demand_sn'];
            $purchase_demand_list = DB::table("demand_count as dc")
                ->select(
                    "pd.id as purchase_id", "dc.purchase_sn",
                    DB::raw("sum(jms_dc.goods_num) as goods_num"),
                    DB::raw("sum(jms_dc.may_buy_num) as may_buy_num"),
                    DB::raw("sum(jms_dc.real_buy_num) as real_buy_num")
                )
                ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "dc.purchase_sn")
                ->leftJoin("purchase_demand as pd2", "pd2.purchase_sn", "=", "dc.purchase_sn")
                ->where("pd2.demand_sn", $demand_sn)
                ->groupBy("purchase_sn")
                ->get();
            $purchase_demand_list = objectToArrayZ($purchase_demand_list);

            foreach ($purchase_demand_list as $k => $v) {
                //计算实际采满率
                $real_buy_rate = (((int)$v["real_buy_num"]) / ((int)$v["goods_num"])) * 100;
                $real_buy_rate = round($real_buy_rate, 2);
                $purchase_demand_list[$k]["real_buy_rate"] = $real_buy_rate;
            }

            $code = "1000";
            $msg = "获取需求采购期列表成功";
            $data = $purchase_demand_list;
            $return_info = compact('code', 'msg', 'data');

            if (empty($purchase_demand_list)) {
                $code = "1003";
                $msg = "需求单号有误";
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
     * description:销售模块-商品分配管理-需求单采购期详情
     * editor:zongxing
     * date: 2018.10.18
     */
    public function demandPurchaseDetail(Request $request)
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

            $purchase_demand_detail = DB::table("purchase_demand_detail")
                ->select(
                    "goods_name", "spec_sn", "erp_prd_no", "erp_merchant_no", "goods_num", "may_num"
                )
                ->where("demand_sn", $demand_sn)
                ->where("purchase_sn", $purchase_sn)
                ->get();
            $purchase_demand_detail = objectToArrayZ($purchase_demand_detail);

            $purchase_demand_count = DB::table("demand_count")
                ->where("purchase_sn", $purchase_sn)
                ->get()->groupBy("spec_sn");
            $purchase_demand_count = objectToArrayZ($purchase_demand_count);

            if (empty($purchase_demand_detail) || empty($purchase_demand_count)) {
                return response()->json(['code' => '1004', 'msg' => '需求单号或采购期单号有误']);
            }

            foreach ($purchase_demand_detail as $k => $v) {
                $spec_sn = $v["spec_sn"];
                $goods_num = $v["goods_num"];
                $may_num = $v["may_num"];
                $miss_buy_rate = ($goods_num - $may_num) / $goods_num * 100;
                $purchase_demand_detail[$k]["miss_buy_rate"] = round($miss_buy_rate,2);
                if (isset($purchase_demand_count[$spec_sn])) {
                    $goods_rate = $goods_num / $purchase_demand_count[$spec_sn][0]["goods_num"];
                    $may_allot_num = floor($goods_rate * $purchase_demand_count[$spec_sn][0]["real_buy_num"]);
                    $purchase_demand_detail[$k]["may_allot_num"] = $may_allot_num;
                }
            }

            $code = "1000";
            $msg = "获取需求采购期列表成功";
            $data = $purchase_demand_detail;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }


}