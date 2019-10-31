<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use App\Model\Vone\CommonModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\RealPurchaseAuditModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Modules\Erp\ErpApi;
use Dingo\Api\Http\Request;

//引入实际采购数据表模型 add by zhangdong on the 2018.07.06
use App\Model\Vone\RealPurchaseModel;
//引入日志表模型 add by zhangdong on the 2018.07.10
use App\Model\Vone\OperateLogModel;
//引入时间处理类库
use Carbon\Carbon;
//引入Excel执行通用文件类
use App\Modules\Excel\ExcuteExcel;
use Illuminate\Support\Facades\DB;


//create by zhangdong on the 2018.07.06
class LogisticsController extends BaseController
{
    /**
     * description : 物流模块-商品清点-获取采购单
     * editor : zhangdong
     * date : 2018.07.06
     */
    public function goodsCheckList(Request $request)
    {
        $reqParams = $request->toArray();
        $keywords = array_key_exists('keywords', $reqParams) && !empty($reqParams['keywords']) ?
            trim($reqParams['keywords']) : '';
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //采购单状态 1,待清点;2,待确认差异3,待开单;4,待入库;5,已完成
        if (!isset($reqParams['status'])) {
            $returnMsg = ['code' => '2028', 'msg' => '采购单状态不能为空'];
            return response()->json($returnMsg);
        }
        $purOrdStatus = intval($reqParams['status']);
        //查询采购单是否过期标志
        $is_expire = isset($reqParams['is_expire']) && intval($reqParams['is_expire']) > 0 ? 1 : 0;
        //获取列表数据
        $realPurchaseModel = new RealPurchaseModel();
        $params['keywords'] = $keywords;
        $params['purOrdStatus'] = $purOrdStatus;
        $params['is_expire'] = $is_expire;
        //组装待清点采购单列表数据
        $waitCheckData = $realPurchaseModel->createWaitCheckData($params, $pageSize);
        $returnMsg = [
            'waitCheckData' => $waitCheckData,
        ];
        return response()->json($returnMsg);

    }

    /**
     * description:物流模块-商品清点-获取待清点批次单
     * editor:zongxing
     * type:GET
     * date : 2018.09.07
     * return Object
     */
    public function getAllotList_stop(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            //获取采购期及其商品数据总表
            $param_info['status'] = 1;
            $param_info['is_setting'] = 1;
            $param_info['is_set_post'] = 1;
            $task_link = 'pendingPurchaseOrder';
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info, null, $request, $task_link);
            if (empty($purchase_data_list["purchase_info"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无待清点批次单']);
            }
            $code = "1000";
            $msg = "获取待清点批次单列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description 物流模块-商品清点-获取清点批次单
     * editor zongxing
     * type GET
     * date 2019.09.25
     * return Array
     */
    public function getAllotList(Request $request)
    {
        $param_info = $request->toArray();
        $add_info = [
            'status' => 3,
            'task_link' => 'pendingPurchaseOrder'
        ];
        $param_info = array_merge($param_info, $add_info);
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_info = $rpa_model->getBatchList($param_info);

        //获取采购期及其商品数据总表
        $param_info['status'] = 1;
        $param_info['is_setting'] = 1;
        $param_info['is_set_post'] = 1;
        $task_link = 'pendingPurchaseOrder';
        $demandCountModel = new DemandCountModel();
        $purchase_data_list = $demandCountModel->getBatchList($param_info, null, $request, $task_link);
        if (empty($purchase_data_list["purchase_info"])) {
            return response()->json(['code' => '1002', 'msg' => '暂无待清点批次单']);
        }
        $code = "1000";
        $msg = "获取待清点批次单列表成功";
        $data = $purchase_data_list["purchase_info"];
        $data_num = $purchase_data_list["data_num"];
        $return_info = compact('code', 'msg', 'data_num', 'data');
        return response()->json($return_info);
    }

    /**
     * description:物流模块-商品清点-获取超时待清点批次单
     * editor:zongxing
     * type:GET
     * date : 2018.09.07
     * return Object
     */
    public function getAllotExtireList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            //获取采购期及其商品数据总表
            $param_info['status'] = 1;
            $param_info['is_setting'] = 1;
            $expire = 2;
            $task_link = 'pendingPurchaseOrder';
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info, $expire, $request, $task_link);
            if (empty($purchase_data_list["purchase_info"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无待清点批次单']);
            }
            $code = "1000";
            $msg = "获取采购数据批次列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:清点数据批量上传
     * editor:zongxing
     * type:POST
     * date : 2018.09.07
     * params: 1.需要上传的excel表格文件:upload_file;2.批次单号:real_purchase_sn;2.采购期单号:purchase_sn;
     * return Object
     */
    public function uploadAllotData(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            } elseif (empty($param_info['real_purchase_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '批次单号不能为空']);
            }
            //检查上传文件是否合格
            $file = $_FILES;
            $excuteExcel = new ExcuteExcel();
            $fileName = '商品清点表';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码', '清点数量'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1005', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }
            //获取对应的批次单信息
            $real_purchase_sn = $param_info['real_purchase_sn'];
            $rp_model = new RealPurchaseModel();
            $rp_goods_info = $rp_model->getBatchGoodsDetail($real_purchase_sn);
            if (empty($rp_goods_info)) {
                return response()->json(['code' => '1006', 'msg' => '提供的批次单号有误']);
            }
            //组装上传清点商品数据
            $error_info = '';
            foreach ($res as $k => $v) {
                if ($k == 0) continue;
                if (empty($v[2]) || empty($v[4]) || !is_numeric($v[4]) || intval($v[4]) < 0 ||
                    !isset($rp_goods_info[$v[2]])
                ) {
                    $row = $k + 1;
                    $error_info .= $row . ',';
                    continue;
                }
                $spec_sn = trim($v[2]);
                $allot_num = intval($v[4]);
                $remark = trim($v[5]);
                $id = $rp_goods_info[$spec_sn];
                $updateRpGoods['allot_num'][] = [
                    $id => "'" . $allot_num . "'"
                ];
                if (!empty($remark)) {
                    $updateRpGoods['remark'][] = [
                        $id => "'" . $remark . "'"
                    ];
                }
            }
            if (!empty($error_info)) {
                $error_info = '第:' . substr($error_info, 0, -1) . '行信息有误,请检查';
                return response()->json(['code' => '1007', 'msg' => $error_info]);
            }
            $updateRpGoodsSql = '';
            if (!empty($updateRpGoods)) {
                $column = 'id';
                $updateRpGoodsSql = makeBatchUpdateSql('jms_real_purchase_detail', $updateRpGoods, $column);
            }
            $updateRes = DB::transaction(function () use ($updateRpGoodsSql) {
                if (!empty($updateRpGoodsSql)) {
                    $res = DB::update(DB::raw($updateRpGoodsSql));
                }
                return $res;
            });
            $return_info = ['code' => '1008', 'msg' => '数据上传失败'];
            if ($updateRes !== false) {
                $return_info = ['code' => '1000', 'msg' => '数据上传成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }//end of function uploadAllotData


    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function createDetailData($purchase_data_array, $res)
    {
        $row_num = count($res);//行数
        $real_purchase_sn = $purchase_data_array['real_purchase_sn'];
        //获取该批次中所有商品信息
        $real_goods_info = DB::table("real_purchase_detail")
            ->where("real_purchase_sn", $real_purchase_sn)
            ->pluck("allot_num", "spec_sn");
        $real_goods_info = objectToArrayZ($real_goods_info);
        $real_spec_info = array_keys($real_goods_info);

        //组装批量更新语句
        $sql_real_purchase_detail = "UPDATE jms_real_purchase_detail SET allot_num = CASE spec_sn ";
        $diff_spec = [];
        $allot_spec_arr = [];
        $diff_spec_msg = '商品规格码:';
        for ($i = 0; $i < $row_num; $i++) {
            if ($i < 1) continue;//第1行数据为标题头
            if ($res[$i][2] && $res[$i][4]) {
                //获取商品规格码
                $goods_spec_sn = (string)($res[$i][2]);
                //检查该商品是否在该采购期需求当中
                if (!in_array($goods_spec_sn, $real_spec_info)) {
                    array_push($diff_spec, $goods_spec_sn);
                    $diff_spec_msg .= $goods_spec_sn . ",";
                    continue;
                }

                //清点数量
                $allot_num_now = intval($res[$i][4]);
                array_push($allot_spec_arr, $goods_spec_sn);
                $sql_real_purchase_detail .= sprintf(" WHEN " . $goods_spec_sn . " THEN " . $allot_num_now);
//                $allot_num_before = intval($real_goods_info[$goods_spec_sn]);
//                if ($allot_num_now != $allot_num_before) {
//
//                }
            }
        }
        if (!empty($diff_spec)) {
            $diff_spec_msg .= substr($diff_spec_msg, 0, -1) . "的商品不在本批次当中";
            return $return_info["diff_spec_msg"] = $diff_spec_msg;
        }
        if (!empty($allot_spec_arr)) {
            $allot_spec_arr = implode(',', array_values($allot_spec_arr));
            $sql_real_purchase_detail .= " END WHERE real_purchase_sn = '" . $real_purchase_sn . "' AND spec_sn IN (" . $allot_spec_arr . ")";
        } else {
            $sql_real_purchase_detail = '';
        }
        $return_info["real_purchase_detail_sql"] = $sql_real_purchase_detail;
        return $return_info;
    }

    /**
     * description:物流模块-商品清点-获取待入库批次单
     * editor:zongxing
     * type:GET
     * date : 2018.09.07
     * return Object
     */
    public function goodsStockList(Request $request)
    {
        $param_info = $request->toArray();
        //获取采购期及其商品数据总表
        $param_info['status'] = 4;
        $demandCountModel = new DemandCountModel();
        $task_link = 'purchaseOrder';
        $purchase_data_list = $demandCountModel->getBatchList($param_info, null, $request, $task_link);
        if (empty($purchase_data_list["purchase_info"])) {
            return response()->json(['code' => '1002', 'msg' => '暂无待入库批次']);
        }
        $data = $purchase_data_list["purchase_info"];
        $data_num = $purchase_data_list["data_num"];
        return response()->json(['code' => '1000', 'msg' => '获取待入库批次列表成功', 'data' => $data, 'data_num' => $data_num]);
    }

    /**
     * description:物流模块-商品清点-获取超时待入库批次单
     * editor:zongxing
     * type:GET
     * date : 2018.09.07
     * return Object
     */
    public function goodsStockOutList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            //获取采购期及其商品数据总表
            $param_info['status'] = 4;
            $expire = 2;
            $task_link = 'purchaseOrder';
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info, $expire, $request, $task_link);
            if (empty($purchase_data_list["purchase_info"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无超时待入库批次']);
            }
            $code = "1000";
            $msg = "获取采购数据批次列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description : 物流模块-商品清点-获取待清点采购单详情
     * editor : zhangdong
     * date : 2018.07.06
     * modify: zongxing 2019.01.14
     */
//    public function allotGoods(Request $request)
//    {
//        $reqParams = $request->toArray();
//        if (!isset($reqParams['real_purchase_sn'])) {
//            $returnMsg = ['code' => '2019', 'msg' => '实采单号不能为空'];
//            return response()->json($returnMsg);
//        }
//        $real_purchase_sn = $reqParams['real_purchase_sn'];
//        //根据实采单号获取实采单数据
//        $realPurchaseModel = new RealPurchaseModel();
//        $params['real_purchase_sn'] = $real_purchase_sn;
//        $realPurchaseInfo = $realPurchaseModel->getRealPurInfo($params);
//        $returnMsg = ['realPurchaseInfo' => $realPurchaseInfo];
//        return response()->json($returnMsg);
//    }
    public function allotGoods(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '实采单号不能为空']);
        }
        //根据实采单号获取实采单数据
        $realPurchaseModel = new RealPurchaseModel();
        $realPurchaseInfo = $realPurchaseModel->getBatchDetail($param_info);
        if (empty($realPurchaseInfo)) {
            return response()->json(['code' => '1003', 'msg' => '实采单号有误']);
        }
        $returnMsg = ['realPurchaseInfo' => $realPurchaseInfo];
        return response()->json($returnMsg);

    }

    /**
     * description : 物流模块-商品清点-获取待入库批次单详情
     * editor : zongxing
     * date : 2019.01.16
     */
    public function allotStockGoods(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '2019', 'msg' => '实采单号不能为空']);
        }
        //根据实采单号获取实采单数据
        $realPurchaseModel = new RealPurchaseModel();
        $realPurchaseInfo = $realPurchaseModel->getBatchDetail($param_info);
        if (!$realPurchaseInfo) {
            return response()->json(['code' => '2021', 'msg' => '实采单号有误']);
        }
        $returnMsg = ['realPurchaseInfo' => $realPurchaseInfo];
        return response()->json($returnMsg);

    }

    /**
     * description : 物流模块-商品清点-清点商品数量
     * editor : zhangdong
     * date : 2018.07.09
     */
//    public function allotGoodsNum(Request $request)
//    {
//        $reqParams = $request->toArray();
//        //判断对应参数是否传入
//        if (
//            !isset($reqParams['purchase_sn']) ||
//            !isset($reqParams['real_purchase_sn']) ||
//            !isset($reqParams['spec_sn']) ||
//            !isset($reqParams['allot_num'])
//        ) {
//            $returnMsg = ['code' => '2005', 'msg' => '参数错误 '];
//            return response()->json($returnMsg);
//        };
//        $purchase_sn = trim($reqParams['purchase_sn']);//采购单号
//        $real_purchase_sn = trim($reqParams['real_purchase_sn']);//实采单号
//        $spec_sn = trim($reqParams['spec_sn']);//商品规格码
//        $allot_num = intval($reqParams['allot_num']);//清点数量
//        if ($allot_num < 0) {
//            $returnMsg = ['code' => '2020', 'msg' => '清点数量填写有误，请确认'];
//            return response()->json($returnMsg);
//        }
//        $remark = isset($reqParams['remark']) ? trim($reqParams['remark']) : '';//备注
//        //更新清点数量并根据清点数量计算差异值
//        $realPurchaseModel = new RealPurchaseModel();
//        //根据采购单号和实采单号查询是否有对应信息
//        $purchaseOrdInfo = $realPurchaseModel->getPurchaseOrdInfo($purchase_sn, $real_purchase_sn, $spec_sn);
//        if (!$purchaseOrdInfo) {
//            $returnMsg = ['code' => '2021', 'msg' => '采购单信息异常'];
//            return response()->json($returnMsg);
//        }
//        if (empty($remark)) {
//            $remark = trim($purchaseOrdInfo->remark);
//        }
//        //清点数量必须小于或等于对应采购单的实采数量
//        $realBuyNum = intval($purchaseOrdInfo->day_buy_num);//实采数
//        if ($allot_num < 0) {
//            //$returnMsg = ['code' => '2022', 'msg' => '清点数量必须小于或等于对应采购单的实采数量'];
//            $returnMsg = ['code' => '2022', 'msg' => '清点数量必须大于等于0'];//zongxing 修改清点数量的判断 2018.09.04
//            return response()->json($returnMsg);
//        }
//        //根据采购单号，实采单号，商品规格码更新对应商品实采数量
//        $updateParams = [
//            'allot_num' => $allot_num,
//            'remark' => $remark,
//        ];
//        $updateRes = $realPurchaseModel->updateAllotNum($purchase_sn, $real_purchase_sn, $spec_sn, $updateParams);
//        if (!$updateRes) {
//            $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
//            return response()->json($returnMsg);
//        }
//        //计算：差异值 = 实采数 - 清点数
//        $diffNum = $realBuyNum - $allot_num;
//        $updateInfo = [
//            'diff_num' => $diffNum,
//            'remark' => $remark,
//        ];
//        $returnMsg = ['updateInfo' => $updateInfo];
//        return response()->json($returnMsg);
//
//    }
    /**
     * description : 物流模块-商品清点-清点商品数量
     * editor : zongxing
     * date : 2018.07.23
     */
    public function allotGoodsNum_stop(Request $request)
    {
        $reqParams = $request->toArray();
        //判断对应参数是否传入
        if (
            !isset($reqParams['purchase_sn']) ||
            !isset($reqParams['real_purchase_sn']) ||
            !isset($reqParams['spec_sn']) ||
            !isset($reqParams['allot_num']) ||
            !isset($reqParams['group_sn']) ||
            !isset($reqParams['is_mother'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        };
        //$purchase_sn = trim($reqParams['purchase_sn']);//采购单号
        //$real_purchase_sn = trim($reqParams['real_purchase_sn']);//实采单号
        $spec_sn = trim($reqParams['spec_sn']);//商品规格码
        $allot_num = intval($reqParams['allot_num']);//清点数量
        if ($allot_num < 0) {
            $returnMsg = ['code' => '2020', 'msg' => '清点数量填写有误，请确认'];
            return response()->json($returnMsg);
        }
        $remark = isset($reqParams['remark']) ? trim($reqParams['remark']) : '';//备注
        //更新清点数量并根据清点数量计算差异值
        $realPurchaseModel = new RealPurchaseModel();
        //根据采购单号和实采单号查询是否有对应信息
        $batchGoodsInfo = $realPurchaseModel->getBatchGoodsInfo($reqParams, $spec_sn);

        if (!$batchGoodsInfo) {
            $returnMsg = ['code' => '2021', 'msg' => '采购单信息异常'];
            return response()->json($returnMsg);
        }

        $goods_list = [];
        foreach ($batchGoodsInfo as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            if (isset($goods_list[$spec_sn])) {
                $goods_list[$spec_sn]['day_buy_num'] += intval($v['day_buy_num']);
                $goods_list[$spec_sn]['allot_num'] += intval($v['allot_num']);
            } else {
                $goods_list[$spec_sn] = [
                    'spec_sn' => $spec_sn,
                    'goods_name' => trim($v['goods_name']),
                    'erp_merchant_no' => trim($v['erp_merchant_no']),
                    'day_buy_num' => intval($v['day_buy_num']),
                    'allot_num' => intval($v['allot_num']),
                    'remark' => trim($v['remark']),
                ];
            }
        }
        //清点数量必须小于或等于对应采购单的实采数量
        $goods_list = array_values($goods_list)[0];
        $realBuyNum = intval($goods_list['day_buy_num']);//实采数
        if ($allot_num < 0) {
            return response()->json(['code' => '2022', 'msg' => '清点数量必须大于等于0']);//zongxing 修改清点数量的判断 2018.09.04
        }
        //如果采购部上传的数据不准确，则实际到货数量可能会大于上传的采购量
//        elseif ($allot_num > $realBuyNum) {
//            return response()->json(['code' => '2023', 'msg' => '清点数量必须小于或等于对应采购单的实采数量']);
//        }

        $updateRes = DB::transaction(function () use ($remark, $batchGoodsInfo, $allot_num) {
            $update_info = [];
            $total_allot_num = $allot_num;
            foreach ($batchGoodsInfo as $k => $v) {
                $real_purchase_sn = trim($v['real_purchase_sn']);
                $spec_sn = trim($v['spec_sn']);
                $where = [
                    'real_purchase_sn' => $real_purchase_sn,
                    'spec_sn' => $spec_sn,
                ];
                $day_buy_num = intval($v['day_buy_num']);
                if ($day_buy_num <= $total_allot_num) {
                    $update_info['allot_num'] = $day_buy_num;
                    $update_info['diff_num'] = 0;
                    $total_allot_num -= $day_buy_num;
                } elseif ($day_buy_num > $total_allot_num) {
                    $update_info['allot_num'] = $total_allot_num;
                    $update_info['diff_num'] = $day_buy_num - $total_allot_num;
                }

                if (!empty($remark)) {
                    $update_info['remark'] = $remark;
                }
//                $updateBatchAllotNum['allot_num'][] = [
//                    $spec_sn => $batch_allot_num
//                ];

//                $updateDemandCountGoodsSql = '';
//                if (!empty($updateDemandCountGoods)) {
//                    //更新条件
//                    $where['real_purchase_sn'] = $real_purchase_sn;
//                    //需要判断的字段
//                    $column = 'spec_sn';
//                    $updateBatchAllotNumSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, $where);
//                }
                //var_dump($update_info, $where);
                $update_res = DB::table("real_purchase_detail")->where($where)->update($update_info);
            }
            return $update_res;
        });
        $returnMsg = ['code' => '2000', 'msg' => '操作成功'];
        if (!$updateRes) {
            return response()->json(['code' => '2024', 'msg' => '操作失败']);
        }
        return response()->json($returnMsg);
    }

    public function allotGoodsNum(Request $request)
    {
        $param_info = $request->toArray();
        //判断对应参数是否传入
        if (!isset($param_info['real_purchase_sn']) || !isset($param_info['spec_sn']) || !isset($param_info['allot_num'])) {
            $returnMsg = ['code' => '1002', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        };
        $remark = isset($param_info['remark']) ? trim($param_info['remark']) : '';//备注
        $spec_sn = trim($param_info['spec_sn']);//商品规格码
        $allot_num = intval($param_info['allot_num']);//清点数量
        if ($allot_num < 0) {
            $returnMsg = ['code' => '1003', 'msg' => '清点数量填写有误，请确认'];
            return response()->json($returnMsg);
        }
        //获取批次商品信息
        $realPurchaseModel = new RealPurchaseModel();
        $batchGoodsInfo = $realPurchaseModel->getBatchGoodsInfo($param_info, $spec_sn);
        if (empty($batchGoodsInfo)) {
            return response()->json(['code' => '1004', 'msg' => '批次单号错误']);
        }
        $batch_goods_info = $batchGoodsInfo[0];
        $real_purchase_sn = trim($batch_goods_info['real_purchase_sn']);
        $spec_sn = trim($batch_goods_info['spec_sn']);
        $day_buy_num = intval($batch_goods_info['day_buy_num']);
        $diff_num = $day_buy_num - $allot_num;
        $update_info = [
            'allot_num' => $allot_num,
            'diff_num' => $diff_num,
        ];
        //备注
        if (!empty($remark)) {
            $update_info['remark'] = $remark;
        }
        $where = [
            'real_purchase_sn' => $real_purchase_sn,
            'spec_sn' => $spec_sn,
        ];
        $updateRes = DB::transaction(function () use ($where, $update_info) {
            $update_res = DB::table("real_purchase_detail")->where($where)->update($update_info);
            return $update_res;
        });
        $returnMsg = ['code' => '2000', 'msg' => '操作成功'];
        if (!$updateRes) {
            return response()->json(['code' => '2024', 'msg' => '操作失败']);
        }
        return response()->json($returnMsg);
    }
    /**
     * description : 物流模块-商品清点-清点商品数量-确认提交
     * editor : zhangdong
     * date : 2018.07.10
     */
//    public function sureAllot(Request $request)
//    {
//        $reqParams = $request->toArray();
//        //采购单号和实采单号判空
//        if (!isset($reqParams['purchase_sn']) || !isset($reqParams['real_purchase_sn'])) {
//            $returnMsg = ['code' => '2005', 'msg' => '参数错误 '];
//            return response()->json($returnMsg);
//        }
//        $purchase_sn = $reqParams['purchase_sn'];//采购单号
//        $real_purchase_sn = $reqParams['real_purchase_sn'];//实采单号单号
//
//        //检查清点商品的重量是否完整,不完整的需要物流部进行维护 zongxing 2018.12.29
//        $real_purchase_detail_model = new RealPurchaseDetailModel();
//        $check_real_purchase_goods = $real_purchase_detail_model->checkRealPurchaseGoods($real_purchase_sn);
//        if (!empty($check_real_purchase_goods)) {
//            return $check_real_purchase_goods;
//        }
//
//        //根据实采单号和采购单号修改实采单号的状态为待确认差异
//        $realPurchaseModel = new  RealPurchaseModel();
//        $status = 2;
//        $updateRes = $realPurchaseModel->updateRealSnStatus($purchase_sn, $real_purchase_sn, $status);
//        $returnMsg = ['code' => '2024', 'msg' => '确认清点成功 '];
//
//        if (!$updateRes) $returnMsg = ['code' => '2023', 'msg' => '确认清点失败 '];
//        //记录日志
//        $operateLogModel = new OperateLogModel();
//        $loginUserInfo = $request->user();
//        $logData = [
//            'table_name' => 'jms_real_purchase',
//            'bus_desc' => '修改实采单状态为待确认差异，采购单号：' . $purchase_sn . '-实采单号：' . $real_purchase_sn,
//            'bus_value' => $real_purchase_sn,
//            'admin_name' => trim($loginUserInfo->user_name),
//            'admin_id' => trim($loginUserInfo->id),
//            'ope_module_name' => '物流模块-商品清点-清点商品数量',
//            'module_id' => 1,
//            'have_detail' => 0,
//        ];
//        $operateLogModel->insertLog($logData);
//
//        //更新任务状态
//        $user_id = $loginUserInfo->id;
//        $task_str = "pendingPurchaseOrder";
//        $common_model = new CommonModel();
//        $common_model->updateSysTaskStatus($real_purchase_sn, $user_id, $task_str);
//
//        return response()->json($returnMsg);
//
//    }
    public function sureAllot(Request $request)
    {
        $param_info = $request->toArray();
        //采购单号和实采单号判空
        if (!isset($param_info['purchase_sn']) || !isset($param_info['real_purchase_sn']) || !isset($param_info['group_sn'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误 '];
            return response()->json($returnMsg);
        }
        $purchase_sn = $param_info['purchase_sn'];//采购单号
        $real_purchase_sn = $param_info['real_purchase_sn'];//批次单号
        $group_sn = $param_info['group_sn'];//批次组合方式

        //检查清点商品的重量是否完整,不完整的需要物流部进行维护 zongxing 2018.12.29
        $real_purchase_detail_model = new RealPurchaseDetailModel();
        $check_real_purchase_goods = $real_purchase_detail_model->checkRealPurchaseGoods($group_sn);
        if (!empty($check_real_purchase_goods)) {
            return $check_real_purchase_goods;
        }

        //根据实采单号和采购单号修改实采单号的状态为待确认差异
        $realPurchaseModel = new  RealPurchaseModel();
        $status = 2;
        $updateRes = $realPurchaseModel->updateRealSnStatus($purchase_sn, $group_sn, $status);
        $returnMsg = ['code' => '2024', 'msg' => '确认清点成功 '];

        if (!$updateRes) $returnMsg = ['code' => '2023', 'msg' => '确认清点失败 '];
        //记录日志
        $operateLogModel = new OperateLogModel();
        $loginUserInfo = $request->user();
        $logData = [
            'table_name' => 'jms_real_purchase',
            'bus_desc' => '修改实采单状态为待确认差异，采购单号：' . $purchase_sn . '-批次组合：' . $group_sn,
            'bus_value' => $group_sn,
            'admin_name' => trim($loginUserInfo->user_name),
            'admin_id' => trim($loginUserInfo->id),
            'ope_module_name' => '物流模块-商品清点-清点商品数量',
            'module_id' => 1,
            'have_detail' => 0,
        ];
        $operateLogModel->insertLog($logData);

        //更新任务状态
        $user_id = $loginUserInfo->id;
        $task_str = "pendingPurchaseOrder";
        $common_model = new CommonModel();
        $common_model->updateSysTaskStatus($real_purchase_sn, $user_id, $task_str);

        return response()->json($returnMsg);

    }

    /**
     * description : 物流模块-商品清点-下载待清点采购单
     * editor : zhangdong
     * date : 2018.07.06
     * update : zongxing
     * date : 2019.07.23
     */
    public function downloadAllotGoods(Request $request)
    {
        $param_info = $request->toArray();
        if (!isset($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '2019', 'msg' => '实采单号不能为空']);
        }
        //根据实采单号获取实采单数据
        $rp_model = new RealPurchaseModel();
        $rp_goods_info = $rp_model->getBatchDetail($param_info);
        //组装表格数据
        return $this->exportAllotData($rp_goods_info);
    }

    /**
     * description:物流模块-商品清点-清点页面数据组装-下载要清点的商品数据-数据组装
     * editor:zhangdong
     * date : 2018.07.10
     */
    private function exportAllotData($data)
    {
        if (empty($data)) return false;
        $title = [['商品名称', '商家编码', '商品规格码', '实采数量', '清点数量', '备注']];
        $goods_list = [];
        foreach ($data['goods_list'] as $key => $value) {
            $goods_list[$key] = [
                $value['goods_name'], $value['erp_merchant_no'], $value['spec_sn'],
                $value['day_buy_num'], $value['allot_num'], $value['remark']
            ];
        }
        //实采单号
        $real_purchase_sn = $data['real_purchase_sn'];
        $filename = '商品清点表_' . $real_purchase_sn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $excuteExcel = new ExcuteExcel();
        return $excuteExcel->export($exportData, $filename);

    }

    /**
     * description:物流模块-商品清点-上传要清点的商品数据
     * editor:zhangdong
     * date : 2018.07.11
     */
    public function upAllotGoods(Request $request)
    {
        $reqParams = $request->toArray();
        if (!isset($reqParams['real_purchase_sn']) || !isset($reqParams['upload_file']) || !isset($reqParams['purchase_sn'])) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $file = $_FILES;
        //检查上传文件是否合格
        $excuteExcel = new ExcuteExcel();
        $fileName = '商品清点表';//要上传的文件名，将对上传的文件名做比较
        $res = $excuteExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['商品规格码', '清点数量', '备注'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                $returnMsg = ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
                return response()->json($returnMsg);
            }
        }
        $realPurchaseModel = new RealPurchaseModel();
        $real_purchase_sn = trim($reqParams['real_purchase_sn']);
        $purchase_sn = trim($reqParams['purchase_sn']);
        //根据上传的商品清点表和实采单号修改清点数量
        $updateRes = false;
        foreach ($res as $key => $value) {
            if ($key == 0) continue;//第一行为标题头，跳过
            $spec_sn = $value[2];
            $updateParams['allot_num'] = $value[4];
            $updateParams['remark'] = $value[5];
            $updateRes = $realPurchaseModel->updateAllotNum($purchase_sn, $real_purchase_sn, $spec_sn, $updateParams);
        }
        $returnMsg = ['code' => '2000', 'msg' => '上传成功'];
        if ($updateRes === false) {
            $returnMsg = ['code' => '2001', 'msg' => '上传失败'];
        }
        return response()->json($returnMsg);

    }


    /**
     * description : 物流模块-入库管理-入库单页面-确认入库
     * editor : zhangdong
     * date : 2018.07.11
     */
    public function inputStorage(Request $request)
    {
        $reqParams = $request->toArray();
        //采购单号和实采单号判空
        if (
            !isset($reqParams['purchase_sn']) ||
            !isset($reqParams['real_purchase_sn']) ||
            !isset($reqParams['group_sn']) ||
            !isset($reqParams['is_mother'])
        ) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误 '];
            return response()->json($returnMsg);
        }
        $purchase_sn = $reqParams['purchase_sn'];//采购单号
        $real_purchase_sn = $reqParams['real_purchase_sn'];//实采单号单号
        $group_sn = $reqParams['group_sn'];//批次组合方式

        //进行erp开单操作
        $erp_api_model = new ErpApi();
        $erp_push_res = $erp_api_model->batch_store_push($reqParams);
        if (!$erp_push_res) {
            $returnMsg = ['code' => '2025', 'msg' => 'erp入库开单失败'];
            return response()->json($returnMsg);
        }

        //根据实采单号和采购单号修改实采单号的状态为已完成
        $realPurchaseModel = new  RealPurchaseModel();
        $status = 5;
        $updateRes = $realPurchaseModel->updateRealSnStatus($purchase_sn, $group_sn, $status);
        $returnMsg = ['code' => '2024', 'msg' => '操作成功 '];
        if (!$updateRes) $returnMsg = ['code' => '2023', 'msg' => '操作失败 '];
        //记录日志
        $operateLogModel = new OperateLogModel();
        $loginUserInfo = $request->user();
        $logData = [
            'table_name' => 'jms_real_purchase',
            'bus_desc' => '修改实采单状态为已完成，采购单号：' . $purchase_sn . '-实采单号：' . $real_purchase_sn,
            'bus_value' => $real_purchase_sn,
            'admin_name' => trim($loginUserInfo->user_name),
            'admin_id' => trim($loginUserInfo->id),
            'ope_module_name' => '物流模块-入库管理-商品入库',
            'module_id' => 1,
            'have_detail' => 0,
        ];
        $operateLogModel->insertLog($logData);

        //更新任务状态
        $user_id = $loginUserInfo->id;
        $task_str = "purchaseOrder";
        $common_model = new CommonModel();
        $common_model->updateSysTaskStatus($real_purchase_sn, $user_id, $task_str);

        return response()->json($returnMsg);

    }


}

//--end of class