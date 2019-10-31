<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\CommonModel;
use App\Model\Vone\DataModel;
use App\Model\Vone\DataModifyModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\DiscountModel;
use App\Model\Vone\ErpHouseModel;
use App\Model\Vone\ErpStorehouseModel;
use App\Model\Vone\GmcDiscountModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\PurchaseChannelGoodsModel;
use App\Model\Vone\PurchaseChannelModel;
use App\Model\Vone\PurchaseDateModel;
use App\Model\Vone\PurchaseDemandModel;
use App\Model\Vone\PurchaseMethodModel;
use App\Model\Vone\PurchaseUserModel;
use App\Model\Vone\RealPurchaseAuditModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\RealPurchaseModel;
use App\Model\Vone\SumDemandGoodsModel;
use App\Model\Vone\SupplierModel;
use App\Model\Vone\TaskModel;
use App\Modules\Excel\ExcuteExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;

/**
 * description:采购数据模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class DataController extends BaseController
{
    /**
     * description:检查采购期是否过期
     * editor:zongxing
     * type:GET
     * date : 2018.08.06
     * return Object
     */
    public function checkPurchaseDatePass_stop(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();

            $data_model = new DataModel();
            $check_purchase_info = $data_model->checkPurchaseDateInfo($purchase_info);

            $code = "1000";
            $msg = "采购期可上传数据";
            $return_info = compact('code', 'msg');

            if (empty($check_purchase_info)) {
                $code = "1002";
                $msg = "采购期已过期";
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
     * description:采购数据实时上传条件检查
     * editor:zongxing
     * type:GET
     * date : 2018.08.16
     * return Object
     */
    public function checkUpload(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            //参数检查
            if (empty($param_info["purchase_sn"]))
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            //采购期单号检查
            $param_info['status'] = 1;
            $purchase_date_model = new PurchaseDateModel();
            $purchase_info = $purchase_date_model->getPurchaseDateDetail($param_info);
            if (empty($purchase_info)) {
                return response()->json(['code' => '1003', 'msg' => '采购期单号有误']);
            }
            //获取采购编号id信息
            $purchase_user_model = new PurchaseUserModel();
            $purchase_user_info = $purchase_user_model->getUserList();
            if (empty($purchase_user_info)) {
                return response()->json(['code' => '1005', 'msg' => '暂无采购编号,请先设置采购编号']);
            }
            //获取采购方式信息
            $purchase_method_info = json_decode($purchase_info["method_info"]);
            $purchase_method_model = new PurchaseMethodModel();
            $purchase_method_list = $purchase_method_model->getMethodList($purchase_method_info);
            $purchase_method_list = array_values($purchase_method_list);
            //获取采购渠道信息
            $purchase_channels_info = json_decode($purchase_info["channels_info"]);
            $purchase_channels_model = new PurchaseChannelModel();
            $purchase_channels_list = $purchase_channels_model->getChannelList($purchase_channels_info);
            //获取供应商信息
            $is_page = false;
            $supplier_model = new SupplierModel();
            $supplier_list_info = $supplier_model->getSupplierList(null, $is_page);
            //获取任务模板信息
            $task_model = new TaskModel();
            $task_info = $task_model->getTaskList();
            //获取仓库信息
            $erp_house_model = new ErpHouseModel();
            $erp_house_list = $erp_house_model->getErpHouseList();
            $total_info['channels_info'] = $purchase_channels_list;
            $total_info['method_info'] = $purchase_method_list;
            $total_info['user_info'] = $purchase_user_info;
            $total_info['supplier_list_info'] = $supplier_list_info;
            $total_info['erp_house_list'] = $erp_house_list;
            $total_info['task_info'] = $task_info;
            $data = $total_info;
            $return_info = ['code' => '1000', 'msg' => '采购数据实时上传条件检查成功', 'data' => $data];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:通过方式获取渠道
     * editor:zongxing
     * type:GET
     * date : 2018.10.23
     * return Object
     */
    public function getChannelByMethod(Request $request)
    {
        if ($request->isMethod('get')) {
            $reqParams = $request->toArray();

            if (empty($reqParams["method_id"])) {
                return response()->json(['code' => '1002', 'msg' => '采购方式id不能为空']);
            } elseif (empty($reqParams["purchase_sn"])) {
                return response()->json(['code' => '1004', 'msg' => '采购期单号不能为空']);
            }

            $purchase_sn = $reqParams["purchase_sn"];
            $channels_info = DB::table("purchase_date")->where("purchase_sn", $purchase_sn)->first(["channels_info"]);
            $channels_info = objectToArrayZ($channels_info);
            $channels_info["channels_info"] = json_decode($channels_info["channels_info"]);

            $method_id = $reqParams["method_id"];
            $purchase_channels_info = DB::table("purchase_channels")
                ->where("method_id", $method_id)
                ->whereIn("id", $channels_info["channels_info"])
                ->get(["id", "channels_name", "channels_sn"]);
            $purchase_channels_info = objectToArrayZ($purchase_channels_info);

            $code = "1000";
            $msg = "获取采购id列表成功";
            $data = $purchase_channels_info;
            $return_info = compact('code', 'msg', 'data');

            if (empty($purchase_channels_info)) {
                $code = "1003";
                $msg = "采购方式有误";
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
     * description:采购数据实时上传
     * editor:zongxing
     * type:POST
     * date : 2018.07.05
     * params: 1.需要上传的excel表格文件:purchase_data;2.采购期单号:purchase_sn;3.采购方式id:method_id;4.采购渠道id:channels_id;
     *          5.自提或邮寄id:path_way;6.港口id:port_id;7.用户账号:user_id
     * return Object
     */
    public function doUploadData_stop(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            }
            $file = $_FILES;
            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '采购需求总表_优采推荐';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码', '采购量'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1005', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }

            //检查上传数据参数
            $check_res = $this->checkUploadData($param_info);
            if (!empty($check_res)) {
                return response()->json($check_res);
            }

            //整合上传的采购数据
            $upload_goods_info = [];
            $match_spec_sn = [];
            foreach ($res as $k => $v) {
                if ($k < 1 || !isset($v[0])) continue;

                $day_buy_num = intval($v[count($v) - 3]);
                if (isset($v[0]) && !empty($v[0]) && $day_buy_num == 0) {
                    continue;
                }
                $spec_sn = trim($v[0]);
                $is_match_str = trim($v[count($v) - 2]);
                $is_match = 0;
                if ($is_match_str == '是') {
                    $is_match = 1;
                    $match_spec_sn[$spec_sn] = '1';
                }
                $upload_goods_info[$spec_sn]['day_buy_num'] = $day_buy_num;
                $upload_goods_info[$spec_sn]['is_match'] = $is_match;
                $upload_goods_info[$spec_sn]['parent_spec_sn'] = trim($v[count($v) - 1]);;
            }

            //对上传的商品在系统中是否存在进行校验
            $upload_spec_sn = array_keys($upload_goods_info);
            $gs_model = new GoodsSpecModel();
            $upload_goods_total_info = $gs_model->get_goods_info($upload_spec_sn);
            if (empty($upload_goods_total_info)) {
                return response()->json(['code' => '1018', 'msg' => '您上传的商品的规格码有误,请重新确认']);
            }
            $error_info = '';
            foreach ($upload_goods_info as $k => $v) {
                $spec_sn = $k;
                if (!array_key_exists($spec_sn, $upload_goods_total_info)) {
                    $error_info .= $spec_sn . ',';
                }
            }
            if (!empty($error_info)) {
                $error_info = '您上传的商品中: ' . substr($error_info, 0, -1) . '商品规格码有误';
                return response()->json(['code' => '1019', 'msg' => $error_info]);
            }

            //获取采购期统计表数据
            $demand_count_model = new DemandCountModel();
            $demand_count_goods_info = $demand_count_model->get_demand_count_data($param_info);
            if (empty($demand_count_goods_info)) {
                return response()->json(['code' => '1020', 'msg' => '您选择的采购期有误,请重新确认']);
            }

            //对上传的非搭配商品是否在需求单中存在进行校验
            $error_info = '';
            foreach ($upload_goods_info as $k => $v) {
                $spec_sn = $k;
                $is_match = $v['is_match'];
                if ($is_match == 0 && !array_key_exists($spec_sn, $demand_count_goods_info)) {
                    $error_info .= $spec_sn . ',';
                }
            }
            if (!empty($error_info)) {
                $error_info = '您上传的商品中: ' . substr($error_info, 0, -1) . '在采购期需求中不存在,请检查';
                return response()->json(['code' => '1019', 'msg' => $error_info]);
            }

            //检查上传时采购期方式
            $purchase_mothod_model = new PurchaseMethodModel();
            $method_info = $purchase_mothod_model->checkUploadPurchaseMethod($param_info);
            if (empty($method_info)) {
                return response()->json(['code' => '1010', 'msg' => '您选择的采购方式有误,请重新确认']);
            }
            $method_sn = $method_info['method_sn'];
            $method_name = $method_info['method_name'];
            $param_info['method_sn'] = $method_sn;

            //检查上传时采购期方式
            $purchase_channel_model = new PurchaseChannelModel();
            $channels_info = $purchase_channel_model->checkUploadPurchaseChannel($param_info);
            if (empty($channels_info)) {
                return response()->json(['code' => '1012', 'msg' => '您选择的采购渠道有误,请重新确认']);
            }
            $channels_sn = $channels_info['channels_sn'];
            $channels_name = $channels_info['channels_name'];
            $param_info['channels_sn'] = $channels_sn;

            //检查上传商品的品牌是否在所选择的方式渠道存在折扣信息
            $discount_model = new DiscountModel();
            $brand_discount_goods_info = $discount_model->checkDiscountInfo($upload_goods_info, $param_info,
                $method_name, $channels_name);//停用

            if (isset($brand_discount_goods_info['code'])) {
                return response()->json($brand_discount_goods_info);
            }

            //获取采购港口简称
            $real_purchase_model = new RealPurchaseModel();
            $port_id = intval($param_info['port_id']);
            $port_sn = $real_purchase_model->getPortSn($port_id);
            if (!$port_sn) {
                return response()->json(['code' => '1009', 'msg' => '您选择的港口信息有误,请重新确认']);
            }

            $purchase_sn = $param_info['purchase_sn'];
            $path_way = $param_info['path_way'];
            $supplier_id = $param_info['supplier_id'];
            if ($path_way == 0) {
                $path_sn = "ZT";
                $group_sn = $purchase_sn . '-' . $path_sn . '-' . $supplier_id;
            } elseif ($path_way == 1) {
                $path_sn = "YJ";
                $group_sn = $purchase_sn . "-" . $method_sn . "-" . $channels_sn . "-" . $path_sn . "-" . $port_sn . '-' .
                    $supplier_id;
            }
            $real_purchase_info = $real_purchase_model->getRealPurchaseByGroupSn($group_sn, 1);
            $data_model = new DataModel();
            $return_info = $data_model->uploadPurchaseData($real_purchase_info, $param_info, $group_sn, $demand_count_goods_info,
                $upload_goods_info, $brand_discount_goods_info);
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
    public function checkUploadData($param_info, &$return_info = [])
    {
        if (!isset($param_info['purchase_sn']) || empty($param_info['purchase_sn'])) {
            $return_info = ['code' => '1006', 'msg' => '提供的采购期单号为空,请重新确认'];
        } elseif (empty($param_info['method_id'])) {
            $return_info = ['code' => '1009', 'msg' => '您选择的采购方式为空,请重新确认'];
        } elseif (empty($param_info['channels_id'])) {
            $return_info = ['code' => '1011', 'msg' => '您选择的采购渠道为空,请重新确认'];
        } elseif (empty($param_info['port_id'])) {
            $return_info = ['code' => '1019', 'msg' => '您选择的采购港口为空,请重新确认'];
        } elseif (empty($param_info['supplier_id'])) {
            $return_info = ['code' => '1013', 'msg' => '您选择的供应商id为空,请重新确认'];
        } elseif ($param_info['path_way'] != 0 && $param_info['path_way'] != 1) {
            $return_info = ['code' => '1015', 'msg' => '您选择的自提或邮寄方式有误,请重新确认'];
        }
        return $return_info;
    }



    /**
     * description:采购数据修正上传校验
     * editor:zongxing
     * type:POST
     * date : 2018.08.17
     * params: 1.实采单编号:real_purchase_sn
     * return Object
     */
    public function uploadDiffData(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_data_array = $request->toArray();
            //检查批次是否过期
            $real_purchase_sn = trim($purchase_data_array["real_purchase_sn"]);
            $real_purchase_info = DB::table("real_purchase")->where("real_purchase_sn", $real_purchase_sn)->first(["delivery_time"]);
            $real_purchase_info = objectToArrayZ($real_purchase_info);

            $return_info = ['code' => '1000', 'msg' => '请求成功'];
            if ($real_purchase_info["delivery_time"]) {
                $real_delivery_time = $real_purchase_info["delivery_time"] . "23:59:59";
                $now_time = date("Y-m-d H:m:s");
                if ($now_time > $real_delivery_time) {
                    $return_info = ['code' => '1002', 'msg' => '批次修正已过期'];
                }
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购数据修正上传
     * editor:zongxing
     * type:POST
     * date : 2018.08.17
     * params: 1.需要上传的excel表格文件:upload_file;2.采购期单号:purchase_sn;3.实采单编号:real_purchase_sn
     * return Object
     */
    public function doUploadDiffData(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            }
            //检查上传文件是否合格
            $file = $_FILES;
            $excuteExcel = new ExcuteExcel();
            $fileName = '采购需求总表_优采推荐_';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //保存上传的折扣表
//            $file_types = explode(".", $file['upload_file']['name']);
//            $file_type = $file_types [count($file_types) - 1];
//            $savePath = base_path() . '/uploadFile/' . date('Ymd') . '/';
//            if (!is_dir($savePath)) {
//                mkdir($savePath, 0777, true);
//            }
//            $str = date('Ymdhis');
//            $file_name = $str . '.' . $file_type; //iconv('UTF-8', 'GBK', $str . '.' . $file_type);
            //$request->file('upload_file')->move($savePath, $file_name);
            //检查字段名称
            $arrTitle = ['商品规格码', '采购量'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1006', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }

            //获取对应的采购期是否上传过数据
            $real_purchase_sn = $param_info['real_purchase_id'];
            $where = [
                ['real_purchase_sn', '=', $real_purchase_sn],
                ['day_buy_num', '>', 0]
            ];
            $batch_goods_info = DB::table('real_purchase_detail')->where($where)->pluck('day_buy_num', 'spec_sn');
            $batch_goods_info = objectToArrayZ($batch_goods_info);
            if (empty($batch_goods_info)) {
                return response()->json(['code' => '1007', 'msg' => '提供的批次单号有误']);
            }
            $detail_spec_total_info = array_keys($batch_goods_info);
            //组装上传商品数据
            $upload_goods_info = [];
            foreach ($res as $k => $v) {
                if ($k < 1 || empty($v[0])) continue;
                $spec_sn = trim($v[0]);
                $day_buy_num = intval($v[count($v) - 1]);
                $upload_goods_info[$spec_sn] = $day_buy_num;
            }
            //检查上传商品是否存在
            $diff_spec_key = array_diff_key($upload_goods_info, $batch_goods_info);
            if (!empty($diff_spec_key)) {
                $error_str = '您上传的商品中规格码为:';
                foreach ($diff_spec_key as $k => $v) {
                    $error_str .= $k . ',';
                }
                $error_str = substr($error_str, 0, -1);
                $error_str .= ' 的,不存在需求,请检查';
                return response()->json(['code' => '1011', 'msg' => $error_str]);
            }

            //组装采购数据详情表sql和统计表sql
            $detail_and_count_info = $this->modifyDetailSql($param_info, $upload_goods_info, $detail_spec_total_info);
            $updateRes = DB::transaction(function () use ($detail_and_count_info, $res) {
                //更新批次商品表
                $detail_sql = $detail_and_count_info["detail_sql"];
                DB::update(DB::raw($detail_sql));
                //更新采购渠道商品表
                $purchase_channel_sql = $detail_and_count_info["purchase_channel_sql"];
                DB::update(DB::raw($purchase_channel_sql));

                //更新数据修正表
                if (!empty($detail_and_count_info["data_modify_sql"])) {
                    $data_modify_sql = $detail_and_count_info["data_modify_sql"];
                    DB::update(DB::raw($data_modify_sql));
                }
                //新增数据修正表
                if (!empty($detail_and_count_info["modify_goods_info"])) {
                    $modify_goods_info = $detail_and_count_info["modify_goods_info"];
                    DB::table("data_modify")->insert($modify_goods_info);
                }
                //更新采购期商品统计表
                $count_sql = $detail_and_count_info["count_sql"];
                $update_res = DB::update(DB::raw($count_sql));
                return $update_res;
            });
            $return_info = ['code' => '1008', 'msg' => '数据上传失败'];
            if ($updateRes !== false) {
                $return_info = ['code' => '1000', 'msg' => '数据修正成功'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function modifyDetailSql($param_info, $upload_goods_info)
    {
        $purchase_sn = $param_info['purchase_sn'];
        $real_purchase_sn = $param_info['real_purchase_id'];
        $user_id = $param_info['user_id'];
        $batch_cat = $param_info['batch_cat'];
        //获取采购期商品统计表数据
        $dc_model = new DemandCountModel();
        $demand_count_goods_info = $dc_model->getGoodsByPurchaseSn($purchase_sn);
        $count_spec_total_info = array_keys($demand_count_goods_info);

        //获取采购期渠道商品统计表数据
        $pcg_model = new PurchaseChannelGoodsModel();
        $purchase_channel_goods_info = $pcg_model->getPurchseChannelGoodsInfo($purchase_sn, $real_purchase_sn);
        foreach ($purchase_channel_goods_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $purchase_channel_goods_list[$spec_sn] = $v;
        }
        $channel_spec_total_info = array_keys($purchase_channel_goods_list);

        //获取数据修正表数据
        $dm_model = new DataModifyModel();
        $data_modify_goods_info = $dm_model->getPurchseChannelGoodsInfo($real_purchase_sn, $purchase_sn);
        $modify_spec_total_info = array_keys($data_modify_goods_info);

        $modify_goods_info = [];
        $updateModifyGoods = [];
        foreach ($upload_goods_info as $k => $v) {
            //获取商品规格码
            $spec_sn = $k;
            $day_buy_num = intval($v);
            //批次商品表
            $updateBatchGoods['day_buy_num'][] = [
                $spec_sn => 'day_buy_num - ' . $day_buy_num
            ];
            $updateBatchGoods['allot_num'][] = [
                $spec_sn => 'allot_num - ' . $day_buy_num
            ];
            $updateBatchGoods['sort_num'][] = [
                $spec_sn => 'sort_num - ' . $day_buy_num
            ];
            //采购数据修正表
            if (in_array($spec_sn, $modify_spec_total_info)) {
                $updateModifyGoods['modify_num'][] = [
                    $spec_sn => 'modify_num + ' . $day_buy_num
                ];
            } else {
                $tmp_goods_info = [
                    'purchase_sn' => $purchase_sn,
                    'real_purchase_sn' => $real_purchase_sn,
                    'spec_sn' => $spec_sn,
                    'user_id' => $user_id,
                    'modify_num' => $day_buy_num,
                ];
                $modify_goods_info[] = $tmp_goods_info;
            }
            //进行实时数据汇总表数据的更新
            if (in_array($spec_sn, $count_spec_total_info)) {
                $updateDemandCountGoods['real_buy_num'][] = [
                    $spec_sn => 'real_buy_num - ' . $day_buy_num
                ];
                if ($batch_cat == 2) {
                    $updateDemandCountGoods['may_buy_num'][] = [
                        $spec_sn => 'may_buy_num - ' . $day_buy_num
                    ];
                }
            }
            //渠道商品统计表
            if (in_array($spec_sn, $channel_spec_total_info)) {
                $id = $purchase_channel_goods_list[$spec_sn]['id'];
                $updatePurchaseChannelGoods['real_num'][] = [
                    $id => 'real_num - ' . $day_buy_num
                ];
            }
        }
        //组装批量更新语句
        $param_data = [
            'real_purchase_sn' => $real_purchase_sn,
            'purchase_sn' => $purchase_sn,
            'updateBatchGoods' => $updateBatchGoods,
            'updateModifyGoods' => $updateModifyGoods,
            'updateDemandCountGoods' => $updateDemandCountGoods,
            'updatePurchaseChannelGoods' => $updatePurchaseChannelGoods,
        ];
        $total_sql = $this->createDataModifySql($param_data);
        $return_info["detail_sql"] = $total_sql['detail_sql'];
        $return_info["data_modify_sql"] = $total_sql['data_modify_sql'];
        $return_info["count_sql"] = $total_sql['count_sql'];
        $return_info["purchase_channel_sql"] = $total_sql['purchase_channel_sql'];
        $return_info["modify_goods_info"] = $modify_goods_info;
        return $return_info;
    }

    /**
     * description:组装批量更新语句
     * editor:zongxing
     * date : 2019.02.14
     * return Array
     */
    public function createDataModifySql($param_data)
    {
        $real_purchase_sn = $param_data['real_purchase_sn'];
        $purchase_sn = $param_data['purchase_sn'];
        $updateBatchGoods = $param_data['updateBatchGoods'];
        $updateModifyGoods = $param_data['updateModifyGoods'];
        $updateDemandCountGoods = $param_data['updateDemandCountGoods'];
        $updatePurchaseChannelGoods = $param_data['updatePurchaseChannelGoods'];
        $updateBatchGoodsSql = '';
        if (!empty($updateBatchGoods)) {
            //更新条件
            $where1['real_purchase_sn'] = $real_purchase_sn;
            //需要判断的字段
            $column = 'spec_sn';
            $updateBatchGoodsSql = makeBatchUpdateSql('jms_real_purchase_detail', $updateBatchGoods, $column, $where1);
        }
        $updateModifyGoodsSql = '';
        if (!empty($updateModifyGoods)) {
            $where2['real_purchase_sn'] = $real_purchase_sn;
            $column = 'spec_sn';
            $updateModifyGoodsSql = makeBatchUpdateSql('jms_data_modify', $updateModifyGoods, $column, $where2);
        }
        $updateDemandCountGoodsSql = '';
        if (!empty($updateDemandCountGoods)) {
            $where3['purchase_sn'] = $purchase_sn;
            $column = 'spec_sn';
            $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, $where3);
        }
        $updatePurchaseChannelGoodsSql = '';
        if (!empty($updatePurchaseChannelGoods)) {
            $column = 'id';
            $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods', $updatePurchaseChannelGoods,
                $column);
        }
        $return_info["detail_sql"] = $updateBatchGoodsSql;
        $return_info["data_modify_sql"] = $updateModifyGoodsSql;
        $return_info["count_sql"] = $updateDemandCountGoodsSql;
        $return_info["purchase_channel_sql"] = $updatePurchaseChannelGoodsSql;
        return $return_info;
    }

    /**
     * description:获取采购数据列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.06
     * return Object
     */
    public function getDataList(Request $request)
    {
        if ($request->isMethod("get")) {
            $reqParams = $request->toArray();
            //获取采购期及其商品数据的统计
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getDataList($reqParams);
            if (empty($purchase_data_list["data"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无采购数据']);
            }
            $return_info = ['code' => '1000', 'msg' => '获取采购数据列表成功', 'data' => $purchase_data_list];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取采购数据汇总详情
     * editor:zongxing
     * type:POST
     * * params: 1.采购期单号:purchase_sn;
     * date : 2018.07.06
     * return Object
     */
    public function getTotalDetail(Request $request)
    {
        if ($request->isMethod("post")) {
            $purchase_info = $request->toArray();

            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $purchase_goods_info = $realPurchaseDetailModel->getPurchaseGoodsDetail($purchase_info);
            if (empty($purchase_goods_info)) {
                return response()->json(['code' => '1002', 'msg' => '暂无实采数据']);
            }
            $code = "1000";
            $msg = "获取采购数据汇总详情成功";
            $data['purchase_goods_info'] = $purchase_goods_info;
            $data['goods_num'] = count($purchase_goods_info);
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function getBatchList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            //获取采购期及其商品数据总表
            $param_info['status'] = 1;
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info);
            if (empty($purchase_data_list["purchase_info"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无采购数据批次']);
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
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function batchSettingList(Request $request)
    {
        if ($request->isMethod("get")) {
            $param_info = $request->toArray();
            //获取采购期及其商品数据总表
            $param_info['status'] = 1;
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info, null, $request);
            if (empty($purchase_data_list["purchase_info"])) {
                return response()->json(['code' => '1002', 'msg' => '暂无采购数据批次']);
            }
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = ['code' => '1000', 'msg' => '获取采购数据批次列表成功', 'data' => $data, 'data_num' => $data_num];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取数据修正批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function modifyDataList_stop(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();
            //获取采购期及其商品数据总表
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->modifyDataList($purchase_info);

            if (empty($purchase_data_list["purchase_info"]))
                return response()->json(['code' => '1002', 'msg' => '暂无采购数据批次']);

            $code = "1000";
            $msg = "获取采购数据批次列表成功";
            $data = $purchase_data_list["purchase_info"];
            $data_num = $purchase_data_list["data_num"];
            $return_info = compact('code', 'msg', 'data_num', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:获取采购批次详情
     * editor:zongxing
     * type:POST
     * * params: 1.实采批次单号:real_purchase_sn;
     * date : 2018.07.10
     * return Object
     */
    public function getBatchDetail(Request $request)
    {
        if ($request->isMethod("post")) {
            $param_info = $request->toArray();
            if (empty($param_info['purchase_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            } elseif (empty($param_info['group_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '批次组合不能为空']);
            } elseif (empty($param_info['real_purchase_sn'])) {
                return response()->json(['code' => '1004', 'msg' => '批次单号不能为空']);
            } elseif (empty($param_info['is_mother'])) {
                return response()->json(['code' => '1005', 'msg' => '批次父子代码不能为空']);
            }
            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $batch_goods_info = $realPurchaseDetailModel->getBatchGoodsDetail($param_info);
            $return_info = ['code' => '1000', 'msg' => '获取采购批次详情成功', 'data' => $batch_goods_info];
            if (empty($batch_goods_info)) {
                $return_info = ['code' => '1006', 'msg' => '请求参数错误'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }


    /**
     * description:下载采购批次单表
     * editor:zongxing
     * type:GET
     * date : 2018.07.11
     * params: 1.实采批次单号:real_purchase_sn;
     * return excel
     */
    public function downLoadList(Request $request)
    {
        if ($request->isMethod("get")) {
            $batch_info = $request->toArray();
            if (empty($batch_info['purchase_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '采购期单号不能为空']);
            } elseif (empty($batch_info['group_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '批次组合不能为空']);
            } elseif (empty($batch_info['real_purchase_sn'])) {
                return response()->json(['code' => '1004', 'msg' => '批次单号不能为空']);
            } elseif (empty($batch_info['is_mother'])) {
                return response()->json(['code' => '1005', 'msg' => '批次父子代码不能为空']);
            }

            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $batch_goods_info = $realPurchaseDetailModel->getBatchGoodsDetail($batch_info);

            //对表内容进行格式化
            foreach ($batch_goods_info as $k => $v) {
                unset($v["brand_id"]);
                $batch_goods_info[$k] = $v;
            }

            $obpe = new PHPExcel();

            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '实采数量')->getColumnDimension('E')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F1', '清点数量')->getColumnDimension('F')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G1', '差异值')->getColumnDimension('G')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('H1', '备注')->getColumnDimension('H')->setWidth(20);
            $obpe->setActiveSheetIndex(0);

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

            //获取最大行数
            $row_last_i = count($batch_goods_info) + 2;

            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 2) continue;
                $row_i = $i - 2;
                for ($j = 0; $j < $column_last_num; $j++) {
                    //获取列名
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                    $batch_goods_info[$row_i] = array_values($batch_goods_info[$row_i]);

                    $obpe->getActiveSheet()->setCellValue($column_name . $i, $batch_goods_info[$row_i][$j]);
                }
            }

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;

            $commonModel = new CommonModel();
            //改变表格标题样式
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
            //改变表格内容样式
            $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            $obpe->getActiveSheet()->setTitle('采购批次单表_差异管理');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购批次单表_差异管理_' . $str . '.xls';

            //保存文件
            //$obwrite->save($filename);

            //直接在浏览器输出
            header('Pragma: public');
            header('Expires: 0');
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Content-Type:application/force-download');
            header('Content-Type:application/vnd.ms-execl');
            header('Content-Type:application/octet-stream');
            header('Content-Type:application/download');
            header("Content-Disposition:attachment;filename=$filename");
            header('Content-Transfer-Encoding:binary');
            $obwrite->save('php://output');

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:下载采购批次总表
     * editor:zongxing
     * type:GET
     * date : 2018.07.11
     * params: 1.采购期单号:purchase_sn;
     * return excel
     */
    public function downLoadTotalList(Request $request)
    {
        if ($request->isMethod("get")) {
            $purchase_info = $request->toArray();

            //获取指定实际采购批次的商品详细信息
            $realPurchaseDetailModel = new RealPurchaseDetailModel();
            $purchase_goods_info = $realPurchaseDetailModel->getPurchaseGoodsDetail($purchase_info);
            $purchase_goods_info = array_values($purchase_goods_info);
            $obpe = new PHPExcel();
            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('B1', '商品代码')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('C1', '商家编码')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '商品规格码')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E1', '实采数量')->getColumnDimension('E')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F1', '清点数量')->getColumnDimension('F')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G1', '差异值')->getColumnDimension('G')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('H1', '备注')->getColumnDimension('H')->setWidth(20);
            $obpe->setActiveSheetIndex(0);

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

            //获取最大行数
            $row_last_i = count($purchase_goods_info) + 2;
            for ($i = 0; $i < $row_last_i; $i++) {
                if ($i < 2) continue;
                $row_i = $i - 2;
                for ($j = 0; $j < $column_last_num; $j++) {
                    //获取列名
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                    $purchase_goods_info[$row_i] = array_values($purchase_goods_info[$row_i]);
                    $obpe->getActiveSheet()->setCellValue($column_name . $i, $purchase_goods_info[$row_i][$j]);
                }
            }

            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 1;

            $commonModel = new CommonModel();
            //改变表格标题样式
            $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
            //改变表格内容样式
            $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            $obpe->getActiveSheet()->setTitle('采购批次总表_采购数据');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '采购批次总表_采购数据_' . $str . '.xls';

            //保存文件
            //$obwrite->save($filename);

            //直接在浏览器输出
            header('Pragma: public');
            header('Expires: 0');
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Content-Type:application/force-download');
            header('Content-Type:application/vnd.ms-execl');
            header('Content-Type:application/octet-stream');
            header('Content-Type:application/download');
            header("Content-Disposition:attachment;filename=$filename");
            header('Content-Transfer-Encoding:binary');
            $obwrite->save('php://output');

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:批次到货设置任务
     * editor:zongxing
     * type:GET
     * date : 2018.08.28
     * return Array
     */
    public function doBatchSetting(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '采购期编号不能为空']);
            } else if (empty($param_info["real_purchase_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '批次单号不能为空']);
            } else if (empty($param_info["delivery_time"])) {
                return response()->json(['code' => '1004', 'msg' => '提货日期不能为空']);
            } else if (empty($param_info["arrive_time"])) {
                return response()->json(['code' => '1005', 'msg' => '到货日期不能为空']);
            } else if (empty($param_info["task_id"])) {
                return response()->json(['code' => '1006', 'msg' => '任务模板id不能为空']);
            } elseif (empty($param_info['group_sn'])) {
                return response()->json(['code' => '1015', 'msg' => '批次组合不能为空']);
            } elseif (empty($param_info['is_mother'])) {
                return response()->json(['code' => '1016', 'msg' => '批次父子代码不能为空']);
            }

            //对提货日期和到货日期进行时间判断
            $delivery_time = strtotime(trim($param_info['delivery_time']));
            $arrive_time = strtotime(trim($param_info['arrive_time']));
            $now_time = strtotime(date('Y-m-d'));
            if ($delivery_time < $now_time) {
                return response()->json(['code' => '1017', 'msg' => '提货日期不能小于当前']);
            }
            if ($delivery_time > $arrive_time) {
                return response()->json(['code' => '1017', 'msg' => '提货日期不能大于到货日期']);
            }

            //检查批次单号
            $real_purchase_sn = trim($param_info['real_purchase_sn']);
            $group_sn = trim($param_info['group_sn']);
            $is_mother = intval($param_info['is_mother']);
            $purchase_sn = trim($param_info['purchase_sn']);
            $real_purchase_model = new RealPurchaseModel();
            $real_purchase_info = $real_purchase_model->getRealPurchaseByRealSn($real_purchase_sn, $group_sn, $is_mother);
            if (empty($real_purchase_info)) {
                return response()->json(['code' => '1011', 'msg' => '批次单号错误']);
            } elseif ($real_purchase_info['is_setting'] == 1) {
                return response()->json(['code' => '1012', 'msg' => '该批次已经设置']);
            }

            //检查任务模板id
            $task_id = $param_info["task_id"];
            $task_model = new TaskModel();
            $task_info = $task_model->getTaskInfoById($task_id);
            if (empty($task_info)) {
                return response()->json(['code' => '1013', 'msg' => '任务模板id错误']);
            }

            //新增批次任务
            $data_model = new DataModel();
            $add_task_info = $data_model->addTaskInfo($param_info);
            if ($add_task_info == false) {
                return response()->json(['code' => '1014', 'msg' => '新增批次任务失败']);
            }

            if ($add_task_info) {
                $group_sn = $real_purchase_info['group_sn'];
                $delivery_time = $param_info['delivery_time'];
                $real_purchase['delivery_time'] = $param_info['delivery_time'];
                $real_purchase['arrive_time'] = $param_info['arrive_time'];
                $real_purchase['is_setting'] = 1;
                $is_mother = intval($param_info['is_mother']);
                $where = [
                    ['group_sn', '=', $group_sn],
                    ['purchase_sn', '=', $purchase_sn],
                    ['is_mother', '=', $is_mother]
                ];

                $updateRes = DB::transaction(function () use ($where, $real_purchase, $real_purchase_sn, $delivery_time) {
                    //更新审核表中的提货时间
                    $rpa_model = new RealPurchaseAuditModel();
                    $rpa_model->updateRPADeliverTime($real_purchase_sn, $delivery_time);

                    $updateRes = DB::table("real_purchase")->where($where)->update($real_purchase);
                    return $updateRes;
                });
                $return_info = ['code' => '1008', 'msg' => '批次到货设置失败'];
                if ($updateRes !== false) {
                    $return_info = ['code' => '1000', 'msg' => '批次到货设置成功'];
                }
            } else {
                $return_info = ['code' => '1007', 'msg' => '批次任务添加失败'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:批次到货设置运费
     * editor:zongxing
     * type:GET
     * date : 2019.01.03
     * return Array
     */
    public function doBatchSettingPost(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["real_purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '批次单号不能为空']);
            } elseif (empty($param_info["purchase_sn"])) {
                return response()->json(['code' => '1005', 'msg' => '采购期编号不能为空']);
            } elseif (empty($param_info['group_sn'])) {
                return response()->json(['code' => '1006', 'msg' => '批次组合不能为空']);
            } elseif (empty($param_info['is_mother'])) {
                return response()->json(['code' => '1007', 'msg' => '批次父子代码不能为空']);
            }

            //检查批次单号
            $real_purchase_sn = trim($param_info['real_purchase_sn']);
            $group_sn = trim($param_info['group_sn']);
            $is_mother = intval($param_info['is_mother']);
            $real_purchase_model = new RealPurchaseModel();
            $real_purchase_info = $real_purchase_model->getRealPurchaseByRealSn($real_purchase_sn, $group_sn, $is_mother);
            if (empty($real_purchase_info)) {
                return response()->json(['code' => '1003', 'msg' => '批次单号错误']);
            }
            $real_purchase_model = new RealPurchaseModel();
            $group_sn = trim($param_info["group_sn"]);
            $purchase_sn = trim($param_info["purchase_sn"]);
            $is_mother = intval($param_info["is_mother"]);
            $post_info['post_amount'] = floatval($param_info["post_amount"]);
            $post_info['is_set_post'] = 1;
            $where = [
                ['group_sn', '=', $group_sn],
                ['purchase_sn', '=', $purchase_sn],
                ['is_mother', '=', $is_mother]
            ];
            $updateRealPurchasePost = $real_purchase_model->updateRealPurchasePost($where, $post_info);
            $return_info = ['code' => '1004', 'msg' => '设置批次运费失败'];
            if ($updateRealPurchasePost !== false) {
                $return_info = ['code' => '1000', 'msg' => '设置批次运费成功'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

}