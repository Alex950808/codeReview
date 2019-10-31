<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\AuditConfigModel;
use App\Model\Vone\AuditModel;
use App\Model\Vone\DataModel;
use App\Model\Vone\DiscountModel;
use App\Model\Vone\DiscountTypeModel;
use App\Model\Vone\DiscountTypeRecordModel;
use App\Model\Vone\ErpHouseModel;
use App\Model\Vone\GmcDiscountModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\PurchaseChannelModel;
use App\Model\Vone\PurchaseMethodModel;
use App\Model\Vone\PurchaseUserModel;
use App\Model\Vone\RealPurchaseAuditModel;
use App\Model\Vone\RealPurchaseDeatilAuditModel;
use App\Model\Vone\RealPurchaseModel;
use App\Model\Vone\RoleUserModel;
use App\Model\Vone\SumDemandGoodsModel;
use App\Model\Vone\SumGoodsModel;
use App\Model\Vone\SupplierModel;
use App\Model\Vone\TaskModel;
use App\Modules\Excel\ExcuteExcel;
use App\Modules\ParamsCheckSingle;
use Dingo\Api\Contract\Http\Request;
use Illuminate\Support\Facades\DB;


class BatchController extends BaseController
{
    /**
     * description:采购模块-采购数据管理-待审核批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function getBatchList(Request $request)
    {
        $param_info = $request->toArray();
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_info = $rpa_model->getBatchAuditList($param_info);
        if (empty($rpa_info['batch_list'])) {
            return response()->json(['code' => '1001', 'msg' => '暂无待审核批次列表']);
        }
        $total_num = $rpa_info['total_num'];
        $purchase_sn_arr = $rpa_info['batch_list'];
        //获取合单对应的批次信息
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $batch_detail_info = $rpda_model->getWaitAuditBatchDetail($param_info, $purchase_sn_arr);
        //组装数据
        $batch_total_list = [];
        foreach ($batch_detail_info as $k => $v) {
            $purchase_sn = $v['purchase_sn'];
            $title_name = $v['title_name'];
            if (!isset($batch_total_list[$purchase_sn])) {
                $batch_total_list[$purchase_sn]['title_info'] = [
                    'purchase_sn' => $purchase_sn,
                    'title_name' => $title_name,
                ];
            }
            $batch_total_list[$purchase_sn]['batch_list'][] = $v;
        }
        $data = [
            'total_num' => $total_num,
            'batch_total_list' => array_values($batch_total_list),
        ];
        $return_info = ['code' => '1000', 'msg' => '获取待审核批次列表成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:获取待审核采购批次详情
     * editor:zongxing
     * type:POST
     * * params: 1.实采批次单号:real_purchase_sn;
     * date : 2019.04.10
     * return Object
     */
    public function batchAuditDetail(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '1004', 'msg' => '批次单号不能为空']);
        }
        //获取指定实际采购批次的商品详细信息
        $rpda_Model = new RealPurchaseDeatilAuditModel();
        $batch_goods_info = $rpda_Model->getBatchAuditDetail($param_info);
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_info = $rpa_model->batchAuditInfo($param_info);
        $data = [
            'rpa_info' => $rpa_info,
            'batch_goods_info' => $batch_goods_info,
        ];
        $return_info = ['code' => '1000', 'msg' => '获取采购批次详情成功', 'data' => $data];
        if (empty($batch_goods_info)) {
            $return_info = ['code' => '1006', 'msg' => '请求参数错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-采购数据管理-提交批次审核
     * editor:zongxing
     * date: 2019.04.03
     */
    public function doBatchAudit(Request $request)
    {
        $param_info = $request->toArray();
        $rpa_model = new RealPurchaseAuditModel();
        $batch_info = $rpa_model->batchAuditInfo($param_info);
        if (empty($batch_info) || empty($batch_info['audit_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '参数错误']);
        }
        $audit_sn = isset($batch_info['audit_sn']) ? trim($batch_info['audit_sn']) : '';
        $isPass = 1;
        //检查审核单号是否存在
        $auditModel = new AuditModel();
        $auditSnInfo = $auditModel->getAuditInfo($audit_sn);
        if (is_null($auditSnInfo)) {
            $returnMsg = ['code' => '1003', 'msg' => '没找到该审核单信息'];
            return response()->json($returnMsg);
        }
        //是否需要审核 0 不需要 1 需要
        $is_audit = intval($auditSnInfo->is_audit);
        if ($is_audit === 0) {
            $returnMsg = ['code' => '1004', 'msg' => '当前审核单无需审核，请直接提交数据'];
            return response()->json($returnMsg);
        }
        //检查当前操作人是否有权限操作
        $config_sn = trim($auditSnInfo->config_sn);
        //当前审核进度
        $curAuditOrder = intval($auditSnInfo->audit_order);
        $acModel = new AuditConfigModel();
        $checkRes = $acModel->checkHaveRight($config_sn, $curAuditOrder);
        if ($checkRes === false) {
            $returnMsg = ['code' => '1005', 'msg' => '当前审核单您没有权限操作'];
            return response()->json($returnMsg);
        }
        //要修改的下一个审核进度
        $nextAuditOrder = $checkRes;
        //修改审核状态
        $updateRes = $auditModel->updateAuditData($audit_sn, $isPass, $nextAuditOrder);
        if ($updateRes == false) {
            return response()->json(['code' => '1006', 'msg' => '批次审核失败']);
        }

        //根据配置序列号查询最后一个审核进度
        $config_sn = trim($auditSnInfo->config_sn);
        $acModel = new AuditConfigModel();
        $lastAuditNum = $acModel->getLastAuditNum($config_sn);
        //如果当前审核进度和最后一个审核进度相等且状态为审核通过则视为审核完成
        if ($nextAuditOrder == $lastAuditNum) {
            //更新审核批次状态
            $rpa_model = new RealPurchaseAuditModel();
            $update_rpa_res = $rpa_model->updateAuditBatchInfo($audit_sn);
            $returnMsg = ['code' => '1007', 'msg' => '更新审核批次状态失败'];
            if ($update_rpa_res) {
                $returnMsg = ['code' => '1000', 'msg' => '更新审核批次状态成功'];
            }
            return response()->json($returnMsg);
        }
        return response()->json(['code' => '1007', 'msg' => '批次审核成功']);
    }

    /**
     * description:采购模块-采购数据管理-提交批次数据
     * editor:zongxing
     * date: 2019.04.03
     */
    public function uploadBatchAudit(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '批次单号不能为空']);
        }
        //获取批次审核表批次数据
        $rpa_model = new RealPurchaseAuditModel();
        $batch_info = $rpa_model->batchAuditInfo($param_info);
        if (empty($batch_info)) {
            return response()->json(['code' => '1002', 'msg' => '参数错误']);
        }
        if (intval($batch_info['status']) == 3) {
            return response()->json(['code' => '1003', 'msg' => '批次数据已提交']);
        }
        //获取批次审核表商品数据
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $batch_goods_info = $rpda_model->getBatchAuditDetail($param_info);
        //更新批次审核表到批次表
        $rpa_model = new RealPurchaseAuditModel();
        $updateRes = $rpa_model->uploadBatchAudit($batch_info, $batch_goods_info);
        if (isset($updateRes['code'])) {
            return response()->json($updateRes);
        }
        $returnMsg = ['code' => '1000', 'msg' => '提交批次数据成功'];
        if ($updateRes === false) {
            $returnMsg = ['code' => '1005', 'msg' => '提交批次数据失败'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:采购数据实时上传条件检查
     * editor:zongxing
     * type:GET
     * date : 2019.05.27
     * return Object
     */
    public function uploadBatchData()
    {
        $pc_model = new PurchaseChannelModel();
        $channels_info = $pc_model->getTotalChannelList();
        if (empty($channels_info)) {
            return response()->json(['code' => '1002', 'msg' => '暂无采购渠道,请先设置采购渠道']);
        }
        $pm_model = new PurchaseMethodModel();
        $method_info = $pm_model->getTotalMethodList();
        if (empty($method_info)) {
            return response()->json(['code' => '1003', 'msg' => '暂无采购方式,请先设置采购方式']);
        }
        //获取采购编号id信息
        $purchase_user_model = new PurchaseUserModel();
        $purchase_user_info = $purchase_user_model->getUserList();
        if (empty($purchase_user_info)) {
            return response()->json(['code' => '1004', 'msg' => '暂无采购编号,请先设置采购编号']);
        }
        //获取供应商信息
        $is_page = false;
        $supplier_model = new SupplierModel();
        $supplier_list_info = $supplier_model->getSupplierList(null, $is_page);
        if (empty($supplier_list_info)) {
            return response()->json(['code' => '1005', 'msg' => '暂无供应商信息,请先设置供应商信息']);
        }
        //获取任务模板信息
        $task_model = new TaskModel();
        $task_info = $task_model->getTaskList();
        if (empty($task_info)) {
            return response()->json(['code' => '1006', 'msg' => '暂无采购任务模板,请先设置采购任务模板']);
        }
        //获取仓库信息
        $erp_house_model = new ErpHouseModel();
        $erp_house_list = $erp_house_model->getErpHouseList();
        if (empty($erp_house_list)) {
            return response()->json(['code' => '1007', 'msg' => '暂无仓库信息,请先设置仓库信息']);
        }
        $total_info['channels_info'] = $channels_info;
        $total_info['method_info'] = $method_info;
        $total_info['user_info'] = $purchase_user_info;
        $total_info['supplier_list_info'] = $supplier_list_info;
        $total_info['erp_house_list'] = $erp_house_list;
        $total_info['task_info'] = $task_info;
        $data = $total_info;
        $return_info = ['code' => '1000', 'msg' => '采购数据实时上传条件检查成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:采购数据实时上传
     * editor:zongxing
     * type:POST
     * date : 2019.05.17
     * params: 1.需要上传的excel表格文件:purchase_data;2.汇总需求单单号:sum_demand_sn;3.采购方式id:method_id;4.采购渠道id:channels_id;
     *          5.自提或邮寄id:path_way;6.港口id:port_id;7.用户账号:user_id
     * return Object
     */
    public function doUploadBatchData(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            //检查上传数据参数
            ParamsCheckSingle::paramsCheck()->doUploadBatchDataParams($param_info);
            //检查上传文件是否合格
            $file = $_FILES;
            $excuteExcel = new ExcuteExcel();
            $fileName = '采购数据表_';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码', '美金原价', 'Livp价', '实付美金', '外采折扣', '采购数量', '是否为搭配(是/否)', '搭配商品对应的规格码'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1005', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }
            //检查上传数据
            $check_res = $this->checkUploadBatchData($param_info);
            if (isset($check_res['code'])) {
                return response()->json($check_res);
            }
            //获取汇总单商品数据
            $sum_demand_detail = $check_res['sum_demand_detail'];
            $sum_demand_goods = [];
            foreach ($sum_demand_detail as $k => $v) {
                $sum_demand_goods[$v['spec_sn']] = $v;
            }
            //获取上传时的采购方式
            $method_info = $check_res['method_info'];
            $method_sn = $method_info['method_sn'];
            //检查上传时采购渠道
            $channels_info = $check_res['channels_info'];
            $channels_sn = $channels_info['channels_sn'];
            $original_or_discount = $channels_info['original_or_discount'];

            //整合上传的采购数据
            $upload_goods_info = [];
            $upload_spec_sn = [];
            $wai_discount_key = $spec_price_key = $pay_price_key = $buy_num_key = $is_match_key = $ps_sn_key = 0;
            foreach ($res as $k => $v) {
                if ($k == 0) {
                    $wai_discount_key = array_keys($v, '外采折扣')[0];
                    $spec_price_key = array_keys($v, '美金原价')[0];
                    $lvip_price_key = array_keys($v, 'Livp价')[0];
                    $pay_price_key = array_keys($v, '实付美金')[0];
                    $buy_num_key = array_keys($v, '采购数量')[0];
                    $is_match_key = array_keys($v, '是否为搭配(是/否)')[0];
                    $ps_sn_key = array_keys($v, '搭配商品对应的规格码')[0];
                }
                if ($k < 5 || empty($v[0])) continue;
                $spec_sn = trim($v[0]);
                if ($spec_sn == '总计') continue;
                if (!isset($v[$buy_num_key]) || intval($v[$buy_num_key]) == 0) continue;
                $day_buy_num = intval($v[$buy_num_key]);
                $is_match = trim($v[$is_match_key]) == '是' ? 1 : 0;
                $spec_price = floatval($v[$spec_price_key]);
                $lvip_price = floatval($v[$lvip_price_key]);
                $lvip_discount = $original_or_discount == 1 ? $lvip_price / $spec_price : 1;
                $upload_goods_info[$spec_sn] = [
                    'spec_price' => $spec_price,
                    'lvip_price' => $lvip_price,
                    'pay_price' => floatval($v[$pay_price_key]),
                    'day_buy_num' => $day_buy_num,
                    'is_match' => $is_match,
                    'parent_spec_sn' => trim($v[$ps_sn_key]),
                    'lvip_discount' => $lvip_discount,
                ];
                $upload_spec_sn[] = $spec_sn;
                if (!empty($v[$wai_discount_key])) {
                    $upload_goods_info[$spec_sn]['wai_discount'] = floatval($v[$wai_discount_key]);
                }
            }
            if (empty($upload_goods_info)) {
                return response()->json(['code' => '1011', 'msg' => '您上传的采购数据为空,请重新确认']);
            }
            //对上传的商品在系统中是否存在进行校验
            $gs_model = new GoodsSpecModel();
            $upload_spec_total_info = $gs_model->get_goods_info($upload_spec_sn);
            if (count($upload_spec_sn) != count($upload_spec_total_info)) {
                $error_info = '';
                foreach ($upload_spec_sn as $k => $v) {
                    if (!isset($upload_spec_total_info[$v])) {
                        $error_info .= $v . ',';
                    }
                }
                $error_info = '您上传的商品中: ' . substr($error_info, 0, -1) . ' 在系统中未找到';
                return response()->json(['code' => '1012', 'msg' => $error_info]);
            }
            //获取采购最终折扣数据
            foreach ($upload_goods_info as $k => $v) {
                $upload_goods_info[$k]['spec_sn'] = $upload_spec_total_info[$k][0]['spec_sn'];
                $upload_goods_info[$k]['goods_name'] = $upload_spec_total_info[$k][0]['goods_name'];
                $upload_goods_info[$k]['erp_prd_no'] = $upload_spec_total_info[$k][0]['erp_prd_no'];
                $upload_goods_info[$k]['erp_merchant_no'] = $upload_spec_total_info[$k][0]['erp_merchant_no'];
                $upload_goods_info[$k]['erp_ref_no'] = $upload_spec_total_info[$k][0]['erp_ref_no'];
                $upload_goods_info[$k]['brand_id'] = $upload_spec_total_info[$k][0]['brand_id'];
            }
            $channels_id = $param_info['channels_id'];
            $buy_time = $param_info['buy_time'];
            $dt_model = new DiscountTypeModel();
            $goods_discount_list = $dt_model->getFinalDiscount($upload_goods_info, $channels_id, $buy_time);
            //获取采购成本折扣数据
            $param = [
                'channels_id' => $channels_id,
                'buy_time' => $buy_time,
            ];
            $discountModel = new DiscountModel();
            $discount_list = $discountModel->getTotalDiscount($param);
            if (isset($discount_list['code'])) {
                return response()->json($upload_goods_info);
            }
            $upload_goods_info = $this->createGoodsDiscount($goods_discount_list, $discount_list);
            if (isset($upload_goods_info['code'])) {
                return response()->json($upload_goods_info);
            }
            //获取采购港口简称
            $real_purchase_model = new RealPurchaseModel();
            $port_id = intval($param_info['port_id']);
            $port_sn = $real_purchase_model->getPortSn($port_id);
            if (!$port_sn) {
                return response()->json(['code' => '1013', 'msg' => '您选择的港口信息有误,请重新确认']);
            }
            //检查任务模板id
            $task_id = intval($param_info['task_id']);
            $task_model = new TaskModel();
            $task_info = $task_model->getTaskInfoById($task_id);
            if (empty($task_info)) {
                return response()->json(['code' => '1014', 'msg' => '任务模板id错误']);
            }

            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            $path_way = $param_info['path_way'];
            $supplier_id = $param_info['supplier_id'];
            $delivery_time = trim($param_info['delivery_time']);
            $arrive_time = trim($param_info['arrive_time']);
            $integral_time = trim($param_info['integral_time']);
            $buy_time = trim($param_info['buy_time']);
            if ($path_way == 0) {
                $path_sn = 'ZT';
            } elseif ($path_way == 1) {
                $path_sn = 'YJ';
            }
            $group_sn = $sum_demand_sn . '-' . $method_sn . '-' . $channels_sn . '-' . $path_sn . '-' . $port_sn . '-' .
                $supplier_id . '-' . $delivery_time . '-' . $arrive_time . '-' . $integral_time . '-' . $buy_time;
            $param_info['batch_cat'] = 1;
            $data_model = new DataModel();
            $return_info = $data_model->uploadBatchData($param_info, $group_sn, $upload_goods_info);
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:检查上传数据参数
     * editor:zongxing
     * type:POST
     * date : 2019.01.07
     * return Array
     */
    public function checkUploadBatchData($param_info)
    {
        $return_info = [];
        //获取汇总单商品数据
        $sd_sn_arr[] = trim($param_info['sum_demand_sn']);
        $sg_model = new SumGoodsModel();
        $sum_demand_detail = $sg_model->purchaseTaskDetail($sd_sn_arr);
        if (empty($sum_demand_detail)) {
            return ['code' => '1108', 'msg' => '汇总单单号错误'];
        }
        $return_info['sum_demand_detail'] = $sum_demand_detail;

        //检查上传时采购方式
        $purchase_mothod_model = new PurchaseMethodModel();
        $method_info = $purchase_mothod_model->checkUploadPurchaseMethod($param_info);
        if (empty($method_info)) {
            return ['code' => '1109', 'msg' => '您选择的采购方式有误,请重新确认'];
        }
        $return_info['method_info'] = $method_info;

        //检查上传时采购渠道
        $purchase_channel_model = new PurchaseChannelModel();
        $channels_info = $purchase_channel_model->checkUploadPurchaseChannel($param_info);
        if (empty($channels_info)) {
            return ['code' => '1110', 'msg' => '您选择的采购渠道有误,请重新确认'];
        }
        $return_info['channels_info'] = $channels_info;
        return $return_info;
    }

    /**
     * description:对商品和折扣信息进行整合
     * editor:zongxing
     * type:POST
     * date : 2019.05.27
     * return Array
     */
    public function createGoodsDiscount($goods_discount_list, $discount_list)
    {
        $brand_discount_list = [];
        foreach ($discount_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $pin_str = $v1['channels_name'] . '-' . $v1['method_name'];
                $brand_discount_list[$k][$pin_str] = floatval($v1['brand_discount']);
            }
        }
        $error_info = '';
        foreach ($goods_discount_list as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $lvip_discount = $v['lvip_discount'];
            $real_discount = 1;
            $channel_discount = 1;
            $brand_id = floatval($v['brand_id']);
            if (isset($v['wai_discount'])) {
                $wai_discount = floatval($v['wai_discount']);
                $goods_discount_list[$k]['channel_discount'] = $wai_discount;
                $goods_discount_list[$k]['real_discount'] = $wai_discount;
                if (isset($goods_discount_list[$k]['channels_info'])) {
                    unset($goods_discount_list[$k]['channels_info']);
                }
                continue;
            }

            if (!isset($v['channels_info'])) {
                $error_info .= $spec_sn . ',';
                continue;
            }
            foreach ($v['channels_info'] as $k1 => $v1) {
                $brand_discount = floatval($v1['brand_discount']);
                if ($brand_discount < $real_discount) {
                    $real_discount = $brand_discount;
                }
                $pin_str = $v1['channels_name'] . '-' . $v1['method_name'];
                if (isset($brand_discount_list[$brand_id][$pin_str])) {
                    $tmp_channel_discount = $brand_discount_list[$brand_id][$pin_str];
                    $channel_discount = $tmp_channel_discount;
                }
            }
            unset($goods_discount_list[$k]['channels_info']);
            $goods_discount_list[$k]['channel_discount'] = $channel_discount;
            $real_discount = (1 - $lvip_discount) * $channel_discount + $lvip_discount * $real_discount;
            $goods_discount_list[$k]['real_discount'] = $real_discount;
        }
        if (!empty($error_info)) {
            $error_info = '您上传的商品: ' . $error_info . ' 在所选渠道中不存在折扣';
            $error_arr = ['code' => '2001', 'msg' => $error_info];
            return $error_arr;
        }
        return $goods_discount_list;
    }

    /**
     * description:采购数据修正
     * editor:zongxing
     * type:POST
     * date : 2019.09.23
     * params: 1.需要上传的excel表格文件:upload_file;2.实采单编号:real_purchase_sn
     * return Object
     */
    public function modifyBatchData(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['upload_file'])) {
            return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
        } elseif (empty($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '1003', 'msg' => '批次单号不能为空']);
        }
        //检查上传文件是否合格
        $file = $_FILES;
        $excuteExcel = new ExcuteExcel();
        $fileName = '采购数据表_';//要上传的文件名，将对上传的文件名做比较
        $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['商品规格码', '采购数量'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                return response()->json(['code' => '1004', 'msg' => '您的标题头有误，请按模板导入']);
            }
        }
        //获取对应的批次单商品数据
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $rpda_info = $rpda_model->getBatchAuditDetail($param_info);
        if (empty($rpda_info)) {
            return response()->json(['code' => '1005', 'msg' => '提供的批次单号有误']);
        } elseif ($rpda_info[0]['status'] == 3) {
            return response()->json(['code' => '1006', 'msg' => '该批次已经审核通过,不允许修改数量']);
        }
        $rpda_list = $batch_spec_info = [];
        foreach ($rpda_info as $k => $v) {
            $rpda_list[$v['spec_sn']] = $v;
            $batch_spec_info[] = $v['spec_sn'];
        }
        //检查上传商品是否存在
        $upload_goods_info = [];
        $spec_sn_key = $num_key = $error_info = '';
        foreach ($res as $k => $v) {
            if ($k < 1 || empty($v[0])) {
                $spec_sn_key = array_keys($v, '商品规格码')[0];
                $num_key = array_keys($v, '采购数量')[0];
                continue;
            };
            $spec_sn = trim($v[$spec_sn_key]);
            $day_buy_num = intval($v[$num_key]);
            if ($day_buy_num == 0) continue;
            if (!in_array($spec_sn, $batch_spec_info)) {
                $error_info .= $spec_sn . ',';
                continue;
            }
            $upload_goods_info[$spec_sn] = $day_buy_num;
        }
        if (!empty($error_info)) {
            $error_info = '您上传的商品:' . substr($error_info, 0, -1) . '不在所选批次中';
            return response()->json(['code' => '1007', 'msg' => $error_info]);
        }
        //组装采购数据详情表sql和统计表sql
        $modify_info = $this->modifyDetailSql($upload_goods_info, $rpda_list);
        $res = DB::transaction(function () use ($modify_info, $res) {
            //更新数据修正表
            $res = true;
            if (!empty($modify_info['rpda_sql'])) {
                $rpda_sql = $modify_info['rpda_sql'];
                $res = DB::update(DB::raw($rpda_sql));
            }
            return $res;
        });
        $return_info = ['code' => '1008', 'msg' => '数据修正失败'];
        if ($res !== false) {
            $return_info = ['code' => '1000', 'msg' => '数据修正成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function modifyDetailSql($upload_goods_info, $rpda_list)
    {
        $updateRpdaGoods = [];
        foreach ($upload_goods_info as $k => $v) {
            //获取商品规格码
            $spec_sn = $k;
            $day_buy_num = intval($v);
            //采购数据审核表
            if (isset($rpda_list[$spec_sn])) {
                $id = $rpda_list[$spec_sn]['id'];
                $updateRpdaGoods['day_buy_num'][] = [
                    $id => 'day_buy_num - ' . $day_buy_num
                ];
            }
        }
        //组装批量更新语句
        $updateRpdaGoodsSql = '';
        if (!empty($updateRpdaGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateRpdaGoodsSql = makeBatchUpdateSql('jms_real_purchase_detail_audit', $updateRpdaGoods, $column);
        }
        $return_info['rpda_sql'] = $updateRpdaGoodsSql;
        return $return_info;
    }


}