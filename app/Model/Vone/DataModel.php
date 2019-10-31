<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DataModel extends Model
{
    /**
     * description:检查采购期信息
     * editor:zongxing
     * date : 2018.08.06
     * return Array
     */
    public function checkPurchaseDateInfo($purchase_info)
    {
        $purchase_sn = trim($purchase_info["purchase_sn"]);
        $now_time = date('Y-m-d');

        $check_purchase_info = DB::table("purchase_date")
            ->where(function ($query) {
                $query->where('status', '2')
                    ->orWhere('status', '3');
            })
            ->where("delivery_time", ">=", $now_time)
            ->where('purchase_sn', $purchase_sn)
            ->get();
        $check_purchase_info = $check_purchase_info->toArray();
        return $check_purchase_info;
    }

    /**
     * description:增加采购期批次任务信息
     * editor:zongxing
     * date : 2018.08.20
     * return Object
     */
    public function addTaskInfo($batch_info)
    {
        $purchase_sn = $batch_info['purchase_sn'];
        $real_purchase_sn = $batch_info['real_purchase_sn'];
        $delivery_time = $batch_info['delivery_time'];
        $arrive_time = $batch_info['arrive_time'];
        $task_id = $batch_info['task_id'];
        //获取任务模板详情
        $task_list_info = DB::table('task_detail as td')
            ->leftJoin("task as t", "t.task_sn", "=", "td.task_sn")
            ->where("t.id", $task_id)
            ->get(["t.task_sn", "task_date", "task_time", "task_content", "role_id", "user_list", "is_system", "task_link"]);
        $task_list_info = objectToArrayZ($task_list_info);

        $return_info = [];
        foreach ($task_list_info as $k => $v) {
            //根据任务日期规格计算采购期任务日期
            $ping_str = str_split($v["task_date"]);
            $modify_str = $ping_str[1] . $ping_str[2] . " days";
            if ($ping_str[0] == "T") {
                $tmp_arr["task_date"] = Carbon::parse($delivery_time)->modify($modify_str)->toDateString();
            } elseif ($ping_str[0] == "D") {
                $tmp_arr["task_date"] = Carbon::parse($arrive_time)->modify($modify_str)->toDateString();
            }

            $tmp_arr["task_sn"] = $v["task_sn"];
            $tmp_arr["task_time"] = $v["task_time"];
            $tmp_arr["task_content"] = $v["task_content"];
            $tmp_arr["purchase_sn"] = $purchase_sn;
            $tmp_arr["real_purchase_sn"] = $real_purchase_sn;
            $tmp_arr["role_id"] = $v["role_id"];
            $tmp_arr["user_list"] = $v["user_list"];
            $tmp_arr["is_system"] = $v["is_system"];
            $tmp_arr["task_link"] = $v["task_link"];
            array_push($return_info, $tmp_arr);
        }

        $queryTaskInfoRes = DB::table("batch_task")->insert($return_info);
        return $queryTaskInfoRes;
    }

    /**
     * description:采购数据上传
     * editor:zongxing
     * date : 2018.10.09
     * return String
     * modify: zongxing 2019.05.17
     */
    public function uploadPurchaseData($param_info, $group_sn, $upload_goods_info, $brand_discount_goods_info)
    {
        $method_sn = trim($param_info['method_sn']);
        $channels_sn = trim($param_info['channels_sn']);
        $channels_method_sn = $method_sn . '-' . $channels_sn;
        //组装审核数据
        $audit_model = new AuditModel();
        $auditSaveData = $audit_model->createSaveData();
        $audit_sn = $auditSaveData['audit_sn'];
        //组装添加数据
        $realPurchaseData = [
            'group_sn' => $group_sn,
            'purchase_sn' => trim($param_info['purchase_sn']),
            'method_id' => intval($param_info['method_id']),
            'channels_id' => intval($param_info['channels_id']),
            'path_way' => intval($param_info['path_way']),
            'port_id' => intval($param_info['port_id']),
            'user_id' => intval($param_info['user_id']),
            'supplier_id' => intval($param_info['supplier_id']),
            'delivery_time' => trim($param_info['delivery_time']),
            'arrive_time' => trim($param_info['arrive_time']),
            'task_id' => intval($param_info['task_id']),
            'audit_sn' => $audit_sn,
            'channels_method_sn' => $channels_method_sn,
        ];

        //计算实际采购编号
        $model_field = 'real_purchase_sn';
        $now_date = str_replace('-', '', date('Y-m-d', time()));
        $pin_head = 'PC-' . $now_date . '-';
        $rpa_Model = new RealPurchaseAuditModel();
        $last_purchase_sn = createNoByTime($rpa_Model, $model_field, $pin_head);
        $realPurchaseData['real_purchase_sn'] = $last_purchase_sn;
        //组装采购数据详情表数据和统计表sql
        $batch_detail_info = $this->createRPAudit($realPurchaseData, $upload_goods_info, $brand_discount_goods_info);
        $updateRes = DB::transaction(function () use ($realPurchaseData, $batch_detail_info, $auditSaveData) {
            //插入审核表数据
            DB::table('audit')->insert($auditSaveData);
            //实时采购数据增加到实时采购数据表
            if (!empty($realPurchaseData)) {
                DB::table('real_purchase_audit')->insert($realPurchaseData);
            }
            //实时采购数据增加到实时采购数据详情表
            if (!empty($batch_detail_info['insertRPDAudit'])) {
                $insert_res = DB::table('real_purchase_detail_audit')->insert($batch_detail_info['insertRPDAudit']);
            }
            return $insert_res;
        });
        $return_info = ['code' => '1000', 'msg' => '数据上传成功'];
        if ($updateRes === false) {
            $return_info = ['code' => '1016', 'msg' => '数据上传失败'];
        }
        return $return_info;
    }

    /**
     * description:采购数据上传
     * editor:zongxing
     * date : 2018.10.09
     * return String
     * modify: zongxing 2019.05.17
     */
    public function uploadBatchData($param_info, $group_sn, $upload_goods_info)
    {
        $channels_id = intval($param_info['channels_id']);
        $pc_model = new PurchaseChannelModel();
        $pc_info = $pc_model->getChannelInfo($channels_id);
        $pc_info = objectToArrayZ($pc_info);
        //组装审核数据
        $audit_model = new AuditModel();
        $auditSaveData = $audit_model->createSaveData();
        $audit_sn = $auditSaveData['audit_sn'];
        //组装添加数据
        $realPurchaseData = [
            'group_sn' => $group_sn,
            'purchase_sn' => trim($param_info['sum_demand_sn']),
            'method_id' => intval($param_info['method_id']),
            'channels_id' => $channels_id,
            'path_way' => intval($param_info['path_way']),
            'port_id' => intval($param_info['port_id']),
            'user_id' => intval($param_info['user_id']),
            'supplier_id' => intval($param_info['supplier_id']),
            'delivery_time' => trim($param_info['delivery_time']),
            'arrive_time' => trim($param_info['arrive_time']),
            'integral_time' => trim($param_info['integral_time']),
            'buy_time' => trim($param_info['buy_time']),
            'task_id' => intval($param_info['task_id']),
            'original_or_discount' => intval($pc_info['original_or_discount']),
            'margin_payment' => intval($pc_info['margin_payment']),
            'margin_currency' => intval($pc_info['margin_currency']),
            'audit_sn' => $audit_sn,
            'batch_cat' => intval($param_info['batch_cat']),
        ];

        //计算实际采购编号
        $model_field = 'real_purchase_sn';
        $now_date = str_replace('-', '', date('Y-m-d', time()));
        $pin_head = 'PC-' . $now_date . '-';
        $rpa_Model = new RealPurchaseAuditModel();
        $last_purchase_sn = createNoByTime($rpa_Model, $model_field, $pin_head);
        $realPurchaseData['real_purchase_sn'] = $last_purchase_sn;
        //组装采购数据详情表数据和统计表sql
        $param = [
            'sum_demand_sn' => trim($param_info['sum_demand_sn']),
            'real_purchase_sn' => $last_purchase_sn,
        ];
        $batch_detail_info = $this->createBatchAudit($param, $upload_goods_info);
        $updateRes = DB::transaction(function () use ($realPurchaseData, $batch_detail_info, $auditSaveData) {
            //插入审核表数据
            $insert_res = DB::table('audit')->insert($auditSaveData);
            //实时采购数据增加到实时采购数据表
            if (!empty($realPurchaseData)) {
                $insert_res = DB::table('real_purchase_audit')->insert($realPurchaseData);
            }
            //实时采购数据增加到实时采购数据详情表
            if (!empty($batch_detail_info['insertRPDAudit'])) {
                $insert_res = DB::table('real_purchase_detail_audit')->insert($batch_detail_info['insertRPDAudit']);
            }
            return $insert_res;
        });
        $return_info = ['code' => '1000', 'msg' => '数据上传成功'];
        if ($updateRes === false) {
            $return_info = ['code' => '1016', 'msg' => '数据上传失败'];
        }
        return $return_info;
    }
    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function createDetailData($realPurchaseData, $method_sn, $channels_sn, $demand_count_goods_info,
                                     $upload_goods_info, $brand_discount_goods_info)
    {
        //采购期单号
        $purchase_sn = $realPurchaseData['purchase_sn'];
        $purchase_channel_goods_list = DB::table('purchase_channel_goods')
            ->where('purchase_sn', $purchase_sn)
            ->where('method_sn', $method_sn)
            ->where('channels_sn', $channels_sn)
            ->get(['id', 'real_num', 'spec_sn']);
        $purchase_channel_goods_list = objectToArrayZ($purchase_channel_goods_list);

        $purchase_channel_goods_info = [];
        foreach ($purchase_channel_goods_list as $k => $v) {
            $purchase_channel_goods_info[$v['spec_sn']] = $v;
        }
        $channel_spec_total_info = array_keys($purchase_channel_goods_info);

        //组装新增和更新数据
        $realPurchaseInsertArr = [];
        $purchaseChannelInsertArr = [];
        $updateDemandCountGoods = [];
        $updatePurchaseChannelGoods = [];
        foreach ($upload_goods_info as $k => $v) {
            $spec_sn = $k;
            $day_buy_num = intval($v['day_buy_num']);
            $is_match = intval($v['is_match']);
            $parent_spec_sn = $v['parent_spec_sn'];
            $goods_info = $brand_discount_goods_info[$spec_sn];
            $channel_discount = $goods_info['brand_discount'];
            $realPurchaseInsertArr[] = [
                "real_purchase_sn" => $realPurchaseData["real_purchase_sn"],
                "purchase_sn" => $purchase_sn,
                "goods_name" => $goods_info["goods_name"],
                "erp_prd_no" => $goods_info["erp_prd_no"],
                "erp_merchant_no" => $goods_info["erp_merchant_no"],
                "spec_sn" => $spec_sn,
                "day_buy_num" => $day_buy_num,
                "sort_num" => $day_buy_num,
                "allot_num" => $day_buy_num,
                "channel_discount" => $channel_discount,
                'is_match' => $is_match,
                'parent_spec_sn' => $parent_spec_sn
            ];

            //采购期统计表
            if (isset($demand_count_goods_info[$spec_sn])) {
                $updateDemandCountGoods['real_buy_num'][] = [
                    $spec_sn => 'real_buy_num + ' . $day_buy_num
                ];
                $updateDemandCountGoods['upload_buy_num'][] = [
                    $spec_sn => 'upload_buy_num + ' . $day_buy_num
                ];
            }

            //商品采购期渠道统计表
            if (in_array($spec_sn, $channel_spec_total_info)) {
                $purchase_channel_goods_id = $purchase_channel_goods_info[$spec_sn]['id'];
                $updatePurchaseChannelGoods['real_num'][] = [
                    $purchase_channel_goods_id => 'real_num + ' . $day_buy_num
                ];
            } else {
                $purchaseChannelInsertArr[] = [
                    'purchase_sn' => $purchase_sn,
                    'spec_sn' => $spec_sn,
                    'method_sn' => $method_sn,
                    'channels_sn' => $channels_sn,
                    'real_num' => $day_buy_num,
                    'channel_discount' => $channel_discount,
                ];
            }
        }

        $updateDemandCountGoodsSql = '';
        if (!empty($updateDemandCountGoods)) {
            //更新条件
            $where['purchase_sn'] = $purchase_sn;
            //需要判断的字段
            $column = 'spec_sn';
            $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, $where);
        }
        $updatePurchaseChannelGoodsSql = '';
        if (!empty($updatePurchaseChannelGoods)) {
            //需要判断的字段
            $column = 'id';
            $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods', $updatePurchaseChannelGoods, $column);
        }

        $return_info["detail_data"] = $realPurchaseInsertArr;
        $return_info["count_sql"] = $updateDemandCountGoodsSql;
        $return_info["purchase_channels_sql"] = $updatePurchaseChannelGoodsSql;
        $return_info["channel_insert_arr"] = $purchaseChannelInsertArr;
        $return_info["code"] = 1010;
        return $return_info;
    }

    public function createRPAudit($realPurchaseData, $upload_goods_info, $brand_discount_goods_info)
    {
        //采购期单号
        $purchase_sn = $realPurchaseData['purchase_sn'];

        //组装新增数据
        $insertRPDAudit = [];
        foreach ($upload_goods_info as $k => $v) {
            $spec_sn = $k;
            $day_buy_num = intval($v['day_buy_num']);
            $is_match = intval($v['is_match']);
            $parent_spec_sn = $v['parent_spec_sn'];
            $goods_info = $brand_discount_goods_info[$spec_sn];
            $channel_discount = $goods_info['brand_discount'];
            $insertRPDAudit[] = [
                'real_purchase_sn' => $realPurchaseData['real_purchase_sn'],
                'purchase_sn' => $purchase_sn,
                'goods_name' => $goods_info['goods_name'],
                'erp_prd_no' => $goods_info['erp_prd_no'],
                'erp_merchant_no' => $goods_info['erp_merchant_no'],
                'spec_sn' => $spec_sn,
                'day_buy_num' => $day_buy_num,
                'channel_discount' => $channel_discount,
                'is_match' => $is_match,
                'parent_spec_sn' => $parent_spec_sn
            ];
        }
        $return_info['insertRPDAudit'] = $insertRPDAudit;
        return $return_info;
    }

    /**
     * description 组装采购数据详情表数据
     * editor zongxing
     * date 2019.05.17
     * return Array
     */
    public function createBatchAudit($param, $upload_goods_info)
    {
        //组装新增数据
        $insertRPDAudit = [];
        foreach ($upload_goods_info as $k => $v) {
            $insertRPDAudit[] = [
                'real_purchase_sn' => $param['real_purchase_sn'],
                'purchase_sn' =>  $param['sum_demand_sn'],
                'goods_name' => $v['goods_name'],
                'erp_prd_no' => $v['erp_prd_no'],
                'erp_merchant_no' => $v['erp_merchant_no'],
                'spec_sn' => $v['spec_sn'],
                'spec_price' => $v['spec_price'],
                'lvip_price' => $v['lvip_price'],
                'pay_price' => $v['pay_price'],
                'day_buy_num' => intval($v['day_buy_num']),
                'sort_num' => intval($v['day_buy_num']),
                'available_num' => intval($v['day_buy_num']),
                'channel_discount' => floatval($v['channel_discount']),
                'is_match' => intval($v['is_match']),
                'parent_spec_sn' => trim($v['parent_spec_sn']),
                'real_discount' => floatval($v['real_discount']),
            ];
        }
        $return_info['insertRPDAudit'] = $insertRPDAudit;
        return $return_info;
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function createDetailSql($purchase_sn, $real_purchase_sn, $method_sn, $channels_sn, $demand_count_goods_info,
                                    $upload_goods_info, $brand_discount_goods_info)
    {
        //采购期渠道商品表
        $purchase_channel_goods_list = DB::table('purchase_channel_goods')
            ->where('purchase_sn', $purchase_sn)
            ->where('method_sn', $method_sn)
            ->where('channels_sn', $channels_sn)
            ->get(['id', 'real_num', 'spec_sn']);
        $purchase_channel_goods_list = objectToArrayZ($purchase_channel_goods_list);
        $purchase_channel_goods_info = [];
        foreach ($purchase_channel_goods_list as $k => $v) {
            $purchase_channel_goods_info[$v['spec_sn']] = $v;
        }
        $channel_spec_total_info = array_keys($purchase_channel_goods_info);

        //批次详情表数据
        $real_purchase_goods_list = DB::table('real_purchase_detail')
            ->where('real_purchase_sn', $real_purchase_sn)
            ->get(['id', 'day_buy_num', 'spec_sn']);
        $real_purchase_goods_list = objectToArrayZ($real_purchase_goods_list);
        $real_purchase_goods_info = [];
        foreach ($real_purchase_goods_list as $k => $v) {
            $real_purchase_goods_info[$v['spec_sn']] = $v;
        }
        $real_spec_total_info = array_keys($real_purchase_goods_info);

        //组装新增和更新数据
        $realPurchaseInsertArr = [];
        $purchaseChannelInsertArr = [];
        $updateRealPurchaseGoods = [];
        $updateDemandCountGoods = [];
        $updatePurchaseChannelGoods = [];
        foreach ($upload_goods_info as $k => $v) {
            $spec_sn = $k;
            $day_buy_num = intval($v['day_buy_num']);
            $is_match = intval($v['is_match']);
            $goods_info = $brand_discount_goods_info[$spec_sn];
            $channel_discount = $goods_info['brand_discount'];

            //批次详情表数据组装
            if (in_array($spec_sn, $real_spec_total_info)) {
                $real_purchase_goods_id = $real_purchase_goods_info[$spec_sn]['id'];
                $updateRealPurchaseGoods['day_buy_num'][] = [
                    $real_purchase_goods_id => 'day_buy_num + ' . $day_buy_num
                ];
                $updateRealPurchaseGoods['sort_num'][] = [
                    $real_purchase_goods_id => 'sort_num + ' . $day_buy_num
                ];
                $updateRealPurchaseGoods['allot_num'][] = [
                    $real_purchase_goods_id => 'allot_num + ' . $day_buy_num
                ];
            } else {
                $realPurchaseInsertArr[] = [
                    "real_purchase_sn" => $real_purchase_sn,
                    "purchase_sn" => $purchase_sn,
                    "goods_name" => $goods_info["goods_name"],
                    "erp_prd_no" => $goods_info["erp_prd_no"],
                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
                    "spec_sn" => $spec_sn,
                    "day_buy_num" => $day_buy_num,
                    "sort_num" => $day_buy_num,
                    "allot_num" => $day_buy_num,
                    "channel_discount" => $channel_discount,
                    'is_match' => $is_match,
                    'parent_spec_sn' => $v['parent_spec_sn'],
                ];
            }

            //采购期统计表
            if (isset($demand_count_goods_info[$spec_sn])) {
                $updateDemandCountGoods['real_buy_num'][] = [
                    $spec_sn => 'real_buy_num + ' . $day_buy_num
                ];
                $updateDemandCountGoods['upload_buy_num'][] = [
                    $spec_sn => 'upload_buy_num + ' . $day_buy_num
                ];
            }

            //商品采购期渠道统计表
            if (in_array($spec_sn, $channel_spec_total_info)) {
                $purchase_channel_goods_id = $purchase_channel_goods_info[$spec_sn]['id'];
                $updatePurchaseChannelGoods['real_num'][] = [
                    $purchase_channel_goods_id => 'real_num + ' . $day_buy_num
                ];
            } else {
                $purchaseChannelInsertArr[] = [
                    'purchase_sn' => $purchase_sn,
                    'spec_sn' => $spec_sn,
                    'method_sn' => $method_sn,
                    'channels_sn' => $channels_sn,
                    'real_num' => $day_buy_num,
                    'channel_discount' => $channel_discount,
                ];
            }
        }
        $updateRealPurchaseGoodsSql = '';
        if (!empty($updateRealPurchaseGoods)) {
            //更新条件
            $where['real_purchase_sn'] = $real_purchase_sn;
            //需要判断的字段
            $column = 'id';
            $updateRealPurchaseGoodsSql = makeBatchUpdateSql('jms_real_purchase_detail', $updateRealPurchaseGoods, $column, $where);
        }
        $updateDemandCountGoodsSql = '';
        if (!empty($updateDemandCountGoods)) {
            //更新条件
            $where = [];
            $where['purchase_sn'] = $purchase_sn;
            //需要判断的字段
            $column = 'spec_sn';
            $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, $where);
        }
        $updatePurchaseChannelGoodsSql = '';
        if (!empty($updatePurchaseChannelGoods)) {
            //需要判断的字段
            $column = 'id';
            $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods', $updatePurchaseChannelGoods, $column);
        }
        $return_info["detail_sql"] = $updateRealPurchaseGoodsSql;
        $return_info["real_purchase_insert_arr"] = $realPurchaseInsertArr;
        $return_info["count_sql"] = $updateDemandCountGoodsSql;
        $return_info["sql_purchase_channels"] = $updatePurchaseChannelGoodsSql;
        $return_info["channel_insert_arr"] = $purchaseChannelInsertArr;
        return $return_info;
    }

    /**
     * description:预采数据上传
     * editor:zongxing
     * date : 2018.10.09
     * return String
     */
    public function doUploadPredictReal_stop($real_purchase_info, $param_info, $group_sn, $upload_goods_info,
                                             $brand_discount_goods_info)
    {
        $purchase_sn = $param_info['purchase_sn'];
        $demand_sn = $param_info['demand_sn'];
        $method_id = $param_info['method_id'];
        $channels_id = $param_info['channels_id'];
        $path_way = $param_info['path_way'];
        $port_id = $param_info['port_id'];
        $method_sn = $param_info['method_sn'];
        $channels_sn = $param_info['channels_sn'];
        $supplier_id = $param_info['supplier_id'];

        if (empty($real_purchase_info)) {
            //组装添加数据
            $realPurchaseData = [
                'purchase_sn' => $purchase_sn,
                'demand_sn' => $demand_sn,
                'method_id' => $method_id,
                'channels_id' => $channels_id,
                'path_way' => $path_way,
                'port_id' => $port_id,
                'user_id' => $param_info['user_id'],
                'group_sn' => $group_sn,
                'batch_cat' => 2,
                'supplier_id' => $supplier_id,
            ];

            //计算实际采购编号
            $model_field = "real_purchase_sn";
            $now_date = str_replace('-', '', date('Y-m-d', time()));
            $pin_head = "PC-" . $now_date . "-";
            $realPurchaseModel = new RealPurchaseModel();
            $last_purchase_sn = createNoByTime($realPurchaseModel, $model_field, $pin_head);
            $realPurchaseData["real_purchase_sn"] = $last_purchase_sn;

            //组装采购数据详情表数据和统计表sql
//            $detail_and_count_info = $this->createPredictRealData($realPurchaseData, $method_sn, $channels_sn,
//                $demand_info, $upload_goods_info);
//            $updateRes = DB::transaction(function () use ($realPurchaseData, $detail_and_count_info) {
//                //关联采购期和需求单
////                if (!empty($detail_and_count_info["purchase_demand"])) {
////                    DB::table("purchase_demand")->insert($detail_and_count_info["purchase_demand"]);
////                }
////                if (!empty($detail_and_count_info["purchase_demand_detail"])) {
////                    DB::table("purchase_demand_detail")->insert($detail_and_count_info["purchase_demand_detail"]);
////                }
//                //更新采购期需求商品表
////                if (!empty($detail_and_count_info["purchase_demand_detail_sql"])) {
////                    DB::update(DB::raw($detail_and_count_info["purchase_demand_detail_sql"]));
////                }
//
//                //更新采购期渠道商品统计表
//                if (!empty($detail_and_count_info["purchase_channels_sql"])) {
//                    DB::update(DB::raw($detail_and_count_info["purchase_channels_sql"]));
//                }
//                //新增采购期渠道商品统计表
//                if (!empty($detail_and_count_info["channel_insert_arr"])) {
//                    DB::table("purchase_channel_goods")->insert($detail_and_count_info["channel_insert_arr"]);
//                }
//
//                //新增商品统计表
//                if (!empty($detail_and_count_info["insert_count_arr"])) {
//                    DB::table("demand_count")->insert($detail_and_count_info["insert_count_arr"]);
//                }
//
//                //更新商品统计表
//                if (!empty($detail_and_count_info["count_sql"])) {
//                    DB::update(DB::raw($detail_and_count_info["count_sql"]));
//                }
//
//                //实时采购数据增加到实时采购数据表
//                if (!empty($realPurchaseData)) {
//                    DB::table("real_purchase")->insert($realPurchaseData);
//                }
//
//                //实时采购数据增加到实时采购数据详情表
//                if (!empty($detail_and_count_info["real_purchase_detail_insert"])) {
//                    $insert_res = DB::table("real_purchase_detail")->insert($detail_and_count_info["real_purchase_detail_insert"]);
//                }
//                return $insert_res;
//            });
            //组装采购数据详情表数据和统计表sql
            $detail_and_count_info = $this->createPredictRealData($realPurchaseData, $upload_goods_info,
                $brand_discount_goods_info);
            $updateRes = DB::transaction(function () use ($realPurchaseData, $detail_and_count_info) {
                //实时采购数据增加到实时采购数据表
                if (!empty($realPurchaseData)) {
                    DB::table("real_purchase")->insert($realPurchaseData);
                }
                //实时采购数据增加到实时采购数据详情表
                if (!empty($detail_and_count_info["real_purchase_detail_insert"])) {
                    $insert_res = DB::table("real_purchase_detail")->insert($detail_and_count_info["real_purchase_detail_insert"]);
                }
                return $insert_res;
            });

            $return_info = ['code' => '1000', 'msg' => '数据上传成功'];
            if ($updateRes === false) {
                $return_info = ['code' => '1004', 'msg' => '数据上传失败'];
            }
        } else {
            //检查批次是否过期
            if ($real_purchase_info[0]["delivery_time"]) {
                $real_delivery_time = $real_purchase_info[0]["delivery_time"] . "23:59:59";
                $now_time = date("Y-m-d H:m:s");
                if ($now_time > $real_delivery_time) {
                    $return_info = ['code' => '1009', 'msg' => '批次上传已过期'];
                    return $return_info;
                }
            }

            //需要更新的批次单号
            $real_purchase_sn = $real_purchase_info[0]["real_purchase_sn"];
            //组装采购数据详情表sql和统计表sql
//            $detail_and_count_info = $this->createPredictRealSql($purchase_sn, $real_purchase_sn, $method_sn, $method_id,
//                $channels_sn, $channels_id, $demand_info, $upload_goods_info);
//            $updateRes = DB::transaction(function () use ($detail_and_count_info) {
//                //更新采购期需求详情表
////                if (!empty($detail_and_count_info["detail_sql"])) {
////                    DB::update(DB::raw($detail_and_count_info["detail_sql"]));
////                }
//
//
//                //新增采购期渠道商品表
//                if (!empty($detail_and_count_info["channel_insert_arr"])) {
//                    DB::table("purchase_channel_goods")->insert($detail_and_count_info["channel_insert_arr"]);
//                }
//
//                //更新采购期渠道商品表
//                if (!empty($detail_and_count_info["sql_purchase_channels"])) {
//                    DB::update(DB::raw($detail_and_count_info["sql_purchase_channels"]));
//                }
//
//                //新增采购数据统计表
//                if (!empty($detail_and_count_info["insert_count_arr"])) {
//                    $update_res = DB::table("demand_count")->insert($detail_and_count_info["insert_count_arr"]);
//                }
//
//                //更新采购数据统计表
//                if (!empty($detail_and_count_info["count_sql"])) {
//                    $update_res = DB::update(DB::raw($detail_and_count_info["count_sql"]));
//                }
//
//                //新增批次表
//                if (!empty($detail_and_count_info["real_purchase_insert_arr"])) {
//                    $update_res = DB::table("real_purchase_detail")->insert($detail_and_count_info["real_purchase_insert_arr"]);
//                }
//                //更新批次详情表
//                if (!empty($detail_and_count_info["sql_real_purchase_detail"])) {
//                    $update_res = DB::update(DB::raw($detail_and_count_info["sql_real_purchase_detail"]));
//                }
//                return $update_res;
//            });
            //组装采购数据详情表sql和统计表sql
            $detail_and_count_info = $this->createPredictRealSql($purchase_sn, $real_purchase_sn, $upload_goods_info,
                $brand_discount_goods_info);
            $updateRes = DB::transaction(function () use ($detail_and_count_info) {
                //新增批次表
                if (!empty($detail_and_count_info["real_purchase_insert_arr"])) {
                    $update_res = DB::table("real_purchase_detail")->insert($detail_and_count_info["real_purchase_insert_arr"]);
                }
                //更新批次详情表
                if (!empty($detail_and_count_info["sql_real_purchase_detail"])) {
                    $update_res = DB::update(DB::raw($detail_and_count_info["sql_real_purchase_detail"]));
                }
                return $update_res;
            });

            $return_info = ['code' => '1000', 'msg' => '数据上传成功'];
            if ($updateRes === false) {
                $return_info = ['code' => '1008', 'msg' => '数据上传失败'];
            }
        }
        return $return_info;
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function createPredictRealData1($realPurchaseData, $res, $method_sn, $channels_sn, $demand_sn, $demand_info,
                                           $upload_spec_sn)
    {
        //采购期单号、采购方式id、采购渠道id
        $purchase_sn = $realPurchaseData["purchase_sn"];
        $channels_id = $realPurchaseData["channels_id"];
        $method_id = $realPurchaseData["method_id"];

        //对上传的商品数据进行组装
        $upload_goods_info = [];
        foreach ($demand_info as $k => $v) {
            $spec_sn = $v["spec_sn"];
            $upload_goods_info[$spec_sn] = $v;
        }

        //获取采购期需求表数据,后面用于和本次上传的数据作对比
        $purchase_demand_detail_model = new PurchaseDemandDetailModel();
        $where = [
            'pdd.purchase_sn' => $purchase_sn,
            'pdd.demand_sn' => $demand_sn,
        ];
        $purchase_demand_goods_info = $purchase_demand_detail_model->getGoodsByPurchaseAndDemand($where);
        //检查该需求单是否已经和采购期关联
        $purchase_demand = [];
        if (empty($purchase_demand_goods_info)) {
            //没有关联
            $purchase_demand[] = [
                "purchase_sn" => $purchase_sn,
                "demand_sn" => $demand_sn,
                "department" => $demand_info[0]["department"]
            ];
        }
        $purchase_demand_goods_spec = [];
        foreach ($purchase_demand_goods_info as $k => $v) {
            $purchase_demand_goods_spec[] = $v["spec_sn"];
        }
        $purchase_demand_diff_spec = array_diff($upload_spec_sn, $purchase_demand_goods_spec);

        //行数
        $row_num = count($res);
        //列数
        $column_num = count($res[0]);

        //获取采购期下商品统计数据
        $demand_count_info = DB::table("demand_count")
            ->where("purchase_sn", $purchase_sn)
            ->get(["real_buy_num", "spec_sn", "goods_name", "erp_prd_no", "erp_merchant_no"]);
        $demand_count_info = objectToArrayZ($demand_count_info);
        $demand_count_goods_info = [];
        foreach ($demand_count_info as $k => $v) {
            $spec_sn = $v["spec_sn"];
            $demand_count_goods_info[$spec_sn] = $v;
        }
        $count_spec_total_info = array_keys($demand_count_goods_info);

        //获取采购期下各个渠道商品统计数据
        $purchase_channel_goods_info = DB::table("purchase_channel_goods")
            ->where("purchase_sn", $purchase_sn)
            ->where("method_sn", $method_sn)
            ->where("channels_sn", $channels_sn)
            ->get(["real_num", "spec_sn", "may_num"]);
        $purchase_channel_goods_info = objectToArrayZ($purchase_channel_goods_info);
        $purchase_channel_final_goods = [];
        foreach ($purchase_channel_goods_info as $k => $v) {
            $spec_sn = $v["spec_sn"];
            $purchase_channel_final_goods[$spec_sn] = $v;
        }
        $channel_spec_total_info = array_keys($purchase_channel_final_goods);

        //获取商品所属品牌的折扣（后期可以进行Redis优化）
        $channel_discount_info = DB::table("discount as d")
            ->leftJoin("brand as b", "b.brand_id", "=", "d.brand_id")
            ->leftJoin("goods as g", "g.brand_id", "=", "b.brand_id")
            ->leftJoin("goods_spec as gs", "gs.goods_sn", "=", "g.goods_sn")
            ->where("d.method_id", $method_id)
            ->where("d.channels_id", $channels_id)
            ->pluck("brand_discount", "spec_sn");
        $channel_discount_info = objectToArrayZ($channel_discount_info);

        $spec_sn_arr = [];
        $spec_sn_arr2 = [];
        $channel_insert_arr = [];
        $real_purchase_insert_arr = [];
        $purchase_demand_detail = [];
        $insert_count_arr = [];
        $sql_demand_count = "UPDATE jms_demand_count SET real_buy_num = CASE spec_sn ";
        $sql_purchase_channels = "UPDATE jms_purchase_channel_goods SET real_num = CASE spec_sn ";
        $tmp_str2 = "may_buy_num = CASE spec_sn ";
        $tmp_str3 = "may_num = CASE spec_sn ";
        for ($i = 0; $i < $row_num; $i++) {
            if ($i < 1) continue;//第1行数据为标题头
            if ($res[$i][0]) {
                //获取商品规格码
                $goods_spec_sn = (string)($res[$i][3]);
                //获取商品当天采购量
                $day_buy_num = $res[$i][$column_num - 1];
                //上传商品信息
                $goods_info = $upload_goods_info[$goods_spec_sn];

                //检查该需求单是否已经和采购期关联,如果有商品没有关联,则进行关联
                if (empty($purchase_demand_goods_info) || in_array($goods_spec_sn, $purchase_demand_diff_spec)) {
                    //没有关联
                    $purchase_demand_detail[] = [
                        "purchase_sn" => $purchase_sn,
                        "demand_sn" => $demand_sn,
                        "goods_name" => $goods_info["goods_name"],
                        "erp_prd_no" => $goods_info["erp_prd_no"],
                        "erp_merchant_no" => $goods_info["erp_merchant_no"],
                        "sale_discount" => $goods_info["sale_discount"],
                        "spec_sn" => $goods_info["spec_sn"],
                        "goods_num" => $day_buy_num,
                        "may_num" => $day_buy_num,
                    ];
                }

                //检查该商品是否在采购期统计表中,如果不在,进行新增;如果在,进行更新
                if (!in_array($goods_spec_sn, $count_spec_total_info)) {
                    $insert_count_arr[] = [
                        "purchase_sn" => $purchase_sn,
                        "goods_name" => $goods_info["goods_name"],
                        "spec_sn" => $goods_info["spec_sn"],
                        "erp_prd_no" => $goods_info["erp_prd_no"],
                        "erp_merchant_no" => $goods_info["erp_merchant_no"],
                        "may_buy_num" => $day_buy_num,
                        "real_buy_num" => $day_buy_num,
                    ];
                } else {
                    //进行实时数据汇总表数据的更新
                    $goods_after_num = $demand_count_goods_info[$goods_spec_sn]["real_buy_num"] + $day_buy_num;
                    $count_may_num = $demand_count_goods_info[$goods_spec_sn]["may_buy_num"] + $day_buy_num;
                    $spec_sn_arr[] = $goods_spec_sn;
                    $sql_demand_count .= sprintf(" WHEN " . $goods_spec_sn . " THEN " . $goods_after_num);
                    $tmp_str2 .= sprintf(" WHEN " . $goods_spec_sn . " THEN " . $count_may_num);
                }

                //采购期批次详情表数据组装
                $tmp_channel_discount = 1;
                if (isset($channel_discount_info[$goods_spec_sn])) {
                    $tmp_channel_discount = $channel_discount_info[$goods_spec_sn];
                }
                $real_purchase_insert_arr[] = [
                    "real_purchase_sn" => $realPurchaseData["real_purchase_sn"],
                    "purchase_sn" => $purchase_sn,
                    "goods_name" => $goods_info["goods_name"],
                    "erp_prd_no" => $goods_info["erp_prd_no"],
                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
                    "spec_sn" => $goods_spec_sn,
                    "day_buy_num" => $day_buy_num,
                    "allot_num" => $day_buy_num,
                    "channel_discount" => $tmp_channel_discount
                ];

                //商品采购期渠道统计表
                if (in_array($goods_spec_sn, $channel_spec_total_info)) {
                    array_push($spec_sn_arr2, $goods_spec_sn);
                    $channel_may_num = $purchase_channel_final_goods[$goods_spec_sn]["may_num"] + $day_buy_num;
                    $channel_real_num = $purchase_channel_final_goods[$goods_spec_sn]["real_num"] + $day_buy_num;
                    $sql_purchase_channels .= sprintf(" WHEN " . $goods_spec_sn . " THEN " . $channel_real_num);
                    $tmp_str3 .= sprintf(" WHEN " . $goods_spec_sn . " THEN " . $channel_may_num);
                } else {
                    $tmp_channel_insert_arr["purchase_sn"] = $purchase_sn;
                    $tmp_channel_insert_arr["spec_sn"] = $goods_spec_sn;
                    $tmp_channel_insert_arr["method_sn"] = $method_sn;
                    $tmp_channel_insert_arr["channels_sn"] = $channels_sn;
                    $tmp_channel_insert_arr["may_num"] = $day_buy_num;
                    $tmp_channel_insert_arr["real_num"] = $day_buy_num;
                    $tmp_channel_insert_arr["channel_discount"] = 1;
                    if (isset($channel_discount_info[$goods_spec_sn])) {
                        $tmp_channel_insert_arr["channel_discount"] = $channel_discount_info[$goods_spec_sn];
                    }
                    $channel_insert_arr[] = $tmp_channel_insert_arr;
                }
            }
        }

        if (!empty($spec_sn_arr)) {
            $sql_demand_count .= " END, " . $tmp_str2;
            $spec_sn_arr = implode(',', array_values($spec_sn_arr));
            $sql_demand_count .= " END WHERE purchase_sn = '" . $purchase_sn . "' AND spec_sn IN (" . $spec_sn_arr . ")";
        } else {
            $sql_demand_count = '';
        }

        if (!empty($spec_sn_arr2)) {
            $sql_purchase_channels .= " END, " . $tmp_str3;
            $spec_sn_arr2 = implode(',', array_values($spec_sn_arr2));
            $sql_purchase_channels .= " END WHERE purchase_sn = '" . $purchase_sn . "' AND 
                method_sn = '" . $method_sn . "' AND channels_sn = '" . $channels_sn . "' AND 
                spec_sn IN (" . $spec_sn_arr2 . ")";
        } else {
            $sql_purchase_channels = '';
        }

        $return_info["detail_data"] = $real_purchase_insert_arr;
        $return_info["count_sql"] = $sql_demand_count;
        $return_info["purchase_channels_sql"] = $sql_purchase_channels;
        $return_info["channel_insert_arr"] = $channel_insert_arr;
        $return_info["purchase_demand"] = $purchase_demand;
        $return_info["purchase_demand_detail"] = $purchase_demand_detail;
        $return_info["insert_count_arr"] = $insert_count_arr;
        $return_info["code"] = 1010;
        return $return_info;
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
//    public function createPredictRealData($realPurchaseData, $method_sn, $channels_sn, $demand_info, $upload_goods_info)
//    {
//        //采购期单号、采购方式id、采购渠道id
//        $purchase_sn = $realPurchaseData["purchase_sn"];
//        $channels_id = $realPurchaseData["channels_id"];
//        $method_id = $realPurchaseData["method_id"];
//
//        //对上传的商品数据进行组装
//        $demand_goods_info = [];
//        foreach ($demand_info as $k => $v) {
//            $demand_goods_info[$v["spec_sn"]] = $v;
//        }
//
//        //获取采购期需求表数据,后面用于和本次上传的数据作对比
////        $purchase_demand_detail_model = new PurchaseDemandDetailModel();
////        $purchase_demand_goods_info = $purchase_demand_detail_model->getGoodsByPurchaseAndDemand($purchase_sn, $demand_sn);
////        //检查该需求单是否已经和采购期关联
////        $purchase_demand = [];
////        if (empty($purchase_demand_goods_info)) {
////            //没有关联
////            $purchase_demand[] = [
////                "purchase_sn" => $purchase_sn,
////                "demand_sn" => $demand_sn,
////                "department" => $demand_info[0]["department"]
////            ];
////        }
////        $purchase_demand_goods_spec = [];
////        foreach ($purchase_demand_goods_info as $k => $v) {
////            $purchase_demand_goods_spec[] = $v["spec_sn"];
////            $purchase_demand_diff_spec = array_diff($upload_spec_sn, $purchase_demand_goods_spec);
////        }
//
//        //获取采购期下商品统计数据
//        $demand_count_info = DB::table("demand_count")
//            ->where("purchase_sn", $purchase_sn)
//            ->get(["real_buy_num", "spec_sn", "goods_name", "erp_prd_no", "erp_merchant_no"]);
//        $demand_count_info = objectToArrayZ($demand_count_info);
//        $demand_count_goods_info = [];
//        foreach ($demand_count_info as $k => $v) {
//            $demand_count_goods_info[$v["spec_sn"]] = $v;
//        }
//        $count_spec_total_info = array_keys($demand_count_goods_info);
//
//        //获取采购期下各个渠道商品统计数据
//        $purchase_channel_goods_info = DB::table("purchase_channel_goods")
//            ->where("purchase_sn", $purchase_sn)
//            ->where("method_sn", $method_sn)
//            ->where("channels_sn", $channels_sn)
//            ->get(['id', "real_num", "spec_sn", "may_num"]);
//        $purchase_channel_goods_info = objectToArrayZ($purchase_channel_goods_info);
//        $purchase_channel_final_goods = [];
//        foreach ($purchase_channel_goods_info as $k => $v) {
//            $purchase_channel_final_goods[$v["spec_sn"]] = $v;
//        }
//        $channel_spec_total_info = array_keys($purchase_channel_final_goods);
//
//        //获取商品所属品牌的折扣（后期可以进行Redis优化）
//        $channel_discount_info = DB::table("discount as d")
//            ->leftJoin("brand as b", "b.brand_id", "=", "d.brand_id")
//            ->leftJoin("goods as g", "g.brand_id", "=", "b.brand_id")
//            ->leftJoin("goods_spec as gs", "gs.goods_sn", "=", "g.goods_sn")
//            ->where("d.method_id", $method_id)
//            ->where("d.channels_id", $channels_id)
//            ->pluck("brand_discount", "spec_sn");
//        $channel_discount_info = objectToArrayZ($channel_discount_info);
//
//        $channel_insert_arr = [];
//        $real_purchase_insert_arr = [];
////        $purchase_demand_detail = [];
////        $updatePurchaseDemandDetail = [];
//        $updateDemandCountGoods = [];
//        $insert_count_arr = [];
//        foreach ($upload_goods_info as $k => $v) {
//            //获取商品规格码
//            $goods_spec_sn = $k;
//            //获取商品当天采购量
//            $day_buy_num = $v;
//            //上传商品信息
//            $goods_info = $demand_goods_info[$goods_spec_sn];
//            //检查该商品是否在采购期统计表中,如果不在,进行新增;如果在,进行更新
//            if (!in_array($goods_spec_sn, $count_spec_total_info)) {
//                $insert_count_arr[] = [
//                    "purchase_sn" => $purchase_sn,
//                    "goods_name" => $goods_info["goods_name"],
//                    "spec_sn" => $goods_info["spec_sn"],
//                    "erp_prd_no" => $goods_info["erp_prd_no"],
//                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
//                    "goods_num" => $goods_info['goods_num'],
//                    //"may_buy_num" => $day_buy_num,
//                    "real_buy_num" => $day_buy_num,
//                ];
//            } else {
//                //进行实时数据汇总表数据的更新
//                //$update_goods_num = $goods_info['goods_num'] - $goods_info['bd_goods_num'];
//                $update_goods_num = intval($goods_info['goods_num']);
//                $updateDemandCountGoods['goods_num'][] = [
//                    $goods_spec_sn => 'goods_num + ' . $update_goods_num
//                ];
////                $updateDemandCountGoods['may_buy_num'][] = [
////                    $goods_spec_sn => 'may_buy_num + ' . $day_buy_num
////                ];
//                $updateDemandCountGoods['real_buy_num'][] = [
//                    $goods_spec_sn => 'real_buy_num + ' . $day_buy_num
//                ];
//            }
//
//            //检查该需求单是否已经和采购期关联,如果有商品没有关联,则进行关联
////            if (empty($purchase_demand_goods_info) || in_array($goods_spec_sn, $purchase_demand_diff_spec)) {
////                //没有关联
////                $purchase_demand_detail[] = [
////                    "purchase_sn" => $purchase_sn,
////                    "demand_sn" => $demand_sn,
////                    "goods_name" => $goods_info["goods_name"],
////                    "erp_prd_no" => $goods_info["erp_prd_no"],
////                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
////                    "sale_discount" => $goods_info["sale_discount"],
////                    "spec_sn" => $goods_info["spec_sn"],
////                    "goods_num" => $day_buy_num,
////                    "may_num" => $day_buy_num,
////                ];
////            } else {
////                $updatePurchaseDemandDetail['goods_num'][] = [
////                    $goods_spec_sn => 'goods_num + ' . $day_buy_num
////                ];
////                $updatePurchaseDemandDetail['may_num'][] = [
////                    $goods_spec_sn => 'may_num + ' . $day_buy_num
////                ];
////            }
//
//            //采购期批次详情表数据组装
//            $tmp_channel_discount = 1;
//            if (isset($channel_discount_info[$goods_spec_sn])) {
//                $tmp_channel_discount = $channel_discount_info[$goods_spec_sn];
//            }
//            $real_purchase_insert_arr[] = [
//                "real_purchase_sn" => $realPurchaseData["real_purchase_sn"],
//                "purchase_sn" => $purchase_sn,
//                "goods_name" => $goods_info["goods_name"],
//                "erp_prd_no" => $goods_info["erp_prd_no"],
//                "erp_merchant_no" => $goods_info["erp_merchant_no"],
//                "spec_sn" => $goods_spec_sn,
//                "day_buy_num" => $day_buy_num,
//                "allot_num" => $day_buy_num,
//                "channel_discount" => $tmp_channel_discount
//            ];
//
//            //商品采购期渠道统计表
//            if (in_array($goods_spec_sn, $channel_spec_total_info)) {
//                $id = $purchase_channel_final_goods[$goods_spec_sn]['id'];
////                $updatePurchaseChannelGoods['may_num'][] = [
////                    $id => 'may_num + ' . $day_buy_num
////                ];
//                $updatePurchaseChannelGoods['real_num'][] = [
//                    $id => 'real_num + ' . $day_buy_num
//                ];
//            } else {
//                $tmp_channel_insert_arr["purchase_sn"] = $purchase_sn;
//                $tmp_channel_insert_arr["spec_sn"] = $goods_spec_sn;
//                $tmp_channel_insert_arr["method_sn"] = $method_sn;
//                $tmp_channel_insert_arr["channels_sn"] = $channels_sn;
//                //$tmp_channel_insert_arr["may_num"] = $day_buy_num;
//                $tmp_channel_insert_arr["real_num"] = $day_buy_num;
//                $tmp_channel_insert_arr["channel_discount"] = 1;
//                if (isset($channel_discount_info[$goods_spec_sn])) {
//                    $tmp_channel_insert_arr["channel_discount"] = $channel_discount_info[$goods_spec_sn];
//                }
//                $channel_insert_arr[] = $tmp_channel_insert_arr;
//            }
//        }
//        $updateDemandCountSql = '';
//        if (!empty($updateDemandCountGoods)) {
//            //更新条件
//            $where = [
//                'purchase_sn' => $purchase_sn
//            ];
//            //需要判断的字段
//            $column = 'spec_sn';
//            $updateDemandCountSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, $where);
//        }
////        $updatePurchaseDemandDetailSql = '';
////        if (!empty($updatePurchaseDemandDetail)) {
////            //更新条件
////            $where = [
////                'purchase_sn' => $purchase_sn,
////                'demand_sn' => $demand_sn
////            ];
////            //需要判断的字段
////            $column = 'spec_sn';
////            $updatePurchaseDemandDetailSql = makeBatchUpdateSql('jms_purchase_demand_detail', $updatePurchaseDemandDetail, $column, $where);
////        }
//
//        $updatePurchaseChannelGoodsSql = '';
//        if (!empty($updatePurchaseChannelGoods)) {
//            //更新条件
////            $where = [
////                'purchase_sn' => $purchase_sn
////            ];
//            //需要判断的字段
//            $column = 'spec_sn';
//            $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods', $updatePurchaseChannelGoods, $column, $where);
//        }
//
//        $return_info["real_purchase_detail_insert"] = $real_purchase_insert_arr;
//        $return_info["insert_count_arr"] = $insert_count_arr;
//        $return_info["count_sql"] = $updateDemandCountSql;
//        $return_info["purchase_channels_sql"] = $updatePurchaseChannelGoodsSql;
//        $return_info["channel_insert_arr"] = $channel_insert_arr;
//        //$return_info["purchase_demand"] = $purchase_demand;
//        //$return_info["purchase_demand_detail"] = $purchase_demand_detail;
//        //$return_info["purchase_demand_detail_sql"] = $updatePurchaseDemandDetailSql;
//
//        $return_info["code"] = 1010;
//        return $return_info;
//    }
    public function createPredictRealData($realPurchaseData, $upload_goods_info, $brand_discount_goods_info)
    {
        //采购期单号、采购方式id、采购渠道id
        $purchase_sn = $realPurchaseData['purchase_sn'];

        $real_purchase_insert_arr = [];
        foreach ($upload_goods_info as $k => $v) {
            //获取商品规格码
            $goods_spec_sn = $k;
            //获取上传商品信息
            $day_buy_num = $v['predict_num'];
            $is_match = $v['is_match'];
            $parent_spec_sn = '';
            if (isset($v['parent_spec_sn'])) {
                $parent_spec_sn = $v['parent_spec_sn'];
            }

            //商品基础信息
            $goods_info = $brand_discount_goods_info[$goods_spec_sn];
            $tmp_channel_discount = $goods_info['brand_discount'];

            //采购期批次详情表数据组装
            $real_purchase_insert_arr[] = [
                "real_purchase_sn" => $realPurchaseData["real_purchase_sn"],
                "purchase_sn" => $purchase_sn,
                "goods_name" => $goods_info["goods_name"],
                "erp_prd_no" => $goods_info["erp_prd_no"],
                "erp_merchant_no" => $goods_info["erp_merchant_no"],
                "spec_sn" => $goods_spec_sn,
                "day_buy_num" => $day_buy_num,
                "allot_num" => $day_buy_num,
                'channel_discount' => $tmp_channel_discount,
                'is_match' => $is_match,
                'parent_spec_sn' => $parent_spec_sn
            ];
        }
        $return_info["real_purchase_detail_insert"] = $real_purchase_insert_arr;
        return $return_info;
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
//    public function createPredictRealSql($purchase_sn, $real_purchase_sn, $method_sn, $method_id, $channels_sn,
//                                         $channels_id, $demand_info, $upload_goods_info)
//    {
//        //对上传的商品数据进行组装
//        $demand_goods_info = [];
//        foreach ($demand_info as $k => $v) {
//            $demand_goods_info[$v["spec_sn"]] = $v;
//        }
//
//        //获取采购期需求表数据,后面用于和本次上传的数据作对比
////        $purchase_demand_detail_model = new PurchaseDemandDetailModel();
////        $purchase_demand_goods_info = $purchase_demand_detail_model->getGoodsByPurchaseAndDemand($purchase_sn, $demand_sn);
////        //上传数据为“更新”时,需求单一定和采购期已经关联,但是需要检查某些商品是否关联
////        $purchase_demand_goods_spec = [];
////        foreach ($purchase_demand_goods_info as $k => $v) {
////            $purchase_demand_goods_spec[] = $v["spec_sn"];
////        }
////        $purchase_demand_diff_spec = array_diff($upload_spec_sn, $purchase_demand_goods_spec);
//
//        //获取折扣
//        $channel_discount_info = DB::table("discount as d")
//            ->leftJoin("brand as b", "b.brand_id", "=", "d.brand_id")
//            ->leftJoin("goods as g", "g.brand_id", "=", "b.brand_id")
//            ->leftJoin("goods_spec as gs", "gs.goods_sn", "=", "g.goods_sn")
//            ->where("d.method_id", $method_id)
//            ->where("d.channels_id", $channels_id)
//            ->pluck("brand_discount", "spec_sn");
//        $channel_discount_info = objectToArrayZ($channel_discount_info);
//
//        //获取采购期下商品统计数据
//        $demand_count_info = DB::table("demand_count")
//            ->where("purchase_sn", $purchase_sn)
//            ->get(["real_buy_num", "may_buy_num", "spec_sn", "goods_name", "erp_prd_no", "erp_merchant_no"]);
//        $demand_count_info = objectToArrayZ($demand_count_info);
//        $demand_count_goods_info = [];
//        foreach ($demand_count_info as $k => $v) {
//            $spec_sn = $v["spec_sn"];
//            $demand_count_goods_info[$spec_sn] = $v;
//        }
//        $count_spec_total_info = array_keys($demand_count_goods_info);
//
//        //渠道商品统计表数据
//        $purchase_channel_goods_info = DB::table("purchase_channel_goods")
//            ->where("purchase_sn", $purchase_sn)
//            ->where("method_sn", $method_sn)
//            ->where("channels_sn", $channels_sn)
//            ->get(['id', "real_num", "spec_sn", "may_num"]);
//        $purchase_channel_goods_info = objectToArrayZ($purchase_channel_goods_info);
//        $purchase_channel_final_goods = [];
//        foreach ($purchase_channel_goods_info as $k => $v) {
//            $spec_sn = $v["spec_sn"];
//            $purchase_channel_final_goods[$spec_sn] = $v;
//        }
//        $channel_spec_total_info = array_keys($purchase_channel_final_goods);
//
//        //批次详情表数据
//        $real_purchase_goods_info = DB::table("real_purchase_detail")
//            ->where("real_purchase_sn", $real_purchase_sn)
//            ->pluck("day_buy_num", "spec_sn");
//        $real_purchase_goods_info = objectToArrayZ($real_purchase_goods_info);
//        $real_spec_total_info = array_keys($real_purchase_goods_info);
//
//        $channel_insert_arr = [];
//        $real_purchase_insert_arr = [];
//        //$updatePurchaseDemandDetail = [];
//        $updateRealPurchaseDetail = [];
//        $updateDemandCountGoods = [];
//        $updatePurchaseChannelGoods = [];
//        $insert_count_arr = [];
//        foreach ($upload_goods_info as $k => $v) {
//            //获取商品规格码
//            $goods_spec_sn = $k;
//            //获取商品本次上传采购量
//            $day_buy_num = $v;
//            //商品信息
//            $goods_info = $demand_goods_info[$goods_spec_sn];
//
//            //检查该商品是否在采购期统计表中,如果不在,进行新增;如果在,进行更新
//            if (in_array($goods_spec_sn, $count_spec_total_info)) {
//                //进行实时数据汇总表数据的更新,更新时不需要更新统计表的需求量
////                $updateDemandCountGoods['may_buy_num'][] = [
////                    $goods_spec_sn => 'may_buy_num + ' . $day_buy_num
////                ];
//                $updateDemandCountGoods['real_buy_num'][] = [
//                    $goods_spec_sn => 'real_buy_num + ' . $day_buy_num
//                ];
//            } else {
//                $insert_count_arr[] = [
//                    "purchase_sn" => $purchase_sn,
//                    "goods_name" => $goods_info["goods_name"],
//                    "spec_sn" => $goods_info["spec_sn"],
//                    "erp_prd_no" => $goods_info["erp_prd_no"],
//                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
//                    "goods_num" => $goods_info['goods_num'],
//                    "may_buy_num" => $day_buy_num,
//                    "real_buy_num" => $day_buy_num,
//                ];
//            }
//
//            //检查该需求单是否已经和采购期关联,如果有商品没有关联,则进行关联
////            if (in_array($goods_spec_sn, $purchase_demand_diff_spec)) {
////                //没有关联
////                $purchase_demand_detail[] = [
////                    "purchase_sn" => $purchase_sn,
////                    "demand_sn" => $demand_sn,
////                    "goods_name" => $goods_info["goods_name"],
////                    "erp_prd_no" => $goods_info["erp_prd_no"],
////                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
////                    "sale_discount" => $goods_info["sale_discount"],
////                    "spec_sn" => $goods_info["spec_sn"],
////                    "goods_num" => $goods_info["goods_num"],
////                    "may_num" => $day_buy_num,
////                ];
////            } else {
////                $updatePurchaseDemandDetail['goods_num'][] = [
////                    $goods_spec_sn => 'goods_num + ' . $day_buy_num
////                ];
////                $updatePurchaseDemandDetail['may_num'][] = [
////                    $goods_spec_sn => 'may_num + ' . $day_buy_num
////                ];
////            }
//
//            //进行采购期渠道商品表数据的更新
//            if (in_array($goods_spec_sn, $channel_spec_total_info)) {
////                $updatePurchaseChannelGoods['may_num'][] = [
////                    $goods_spec_sn => 'may_num + ' . $day_buy_num
////                ];
//                $id = $purchase_channel_final_goods[$goods_spec_sn]['id'];
//                $updatePurchaseChannelGoods['real_num'][] = [
//                    $id => 'real_num + ' . $day_buy_num
//                ];
//            } else {
//                $tmp_channel_insert_arr["purchase_sn"] = $purchase_sn;
//                $tmp_channel_insert_arr["spec_sn"] = $goods_spec_sn;
//                $tmp_channel_insert_arr["method_sn"] = $method_sn;
//                $tmp_channel_insert_arr["channels_sn"] = $channels_sn;
//                //$tmp_channel_insert_arr["may_num"] = $day_buy_num;
//                $tmp_channel_insert_arr["real_num"] = $day_buy_num;
//
//                if (isset($channel_discount_info[$goods_spec_sn])) {
//                    $tmp_channel_insert_arr["channel_discount"] = $channel_discount_info[$goods_spec_sn];
//                }
//                array_push($channel_insert_arr, $tmp_channel_insert_arr);
//            }
//
//            //批次详情表数据组装
//            if (in_array($goods_spec_sn, $real_spec_total_info)) {
//                $updateRealPurchaseDetail['day_buy_num'][] = [
//                    $goods_spec_sn => 'day_buy_num + ' . $day_buy_num
//                ];
//                $updateRealPurchaseDetail['allot_num'][] = [
//                    $goods_spec_sn => 'allot_num + ' . $day_buy_num
//                ];
//            } else {
//                $real_purchase_insert_arr[] = [
//                    "real_purchase_sn" => $real_purchase_sn,
//                    "purchase_sn" => $purchase_sn,
//                    "goods_name" => $goods_info["goods_name"],
//                    "erp_prd_no" => $goods_info["erp_prd_no"],
//                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
//                    "spec_sn" => $goods_spec_sn,
//                    "day_buy_num" => $day_buy_num,
//                    "allot_num" => $day_buy_num
//                ];
//            }
//        }
//        $updateDemandCountSql = '';
//        if (!empty($updateDemandCountGoods)) {
//            //更新条件
//            $where = [
//                'purchase_sn' => $purchase_sn
//            ];
//            //需要判断的字段
//            $column = 'spec_sn';
//            $updateDemandCountSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, $where);
//        }
////        $updatePurchaseDemandDetailSql = '';
////        if (!empty($updatePurchaseDemandDetail)) {
////            //更新条件
////            $where = [
////                'purchase_sn' => $purchase_sn,
////                'demand_sn' => $demand_sn
////            ];
////            //需要判断的字段
////            $column = 'spec_sn';
////            $updatePurchaseDemandDetailSql = makeBatchUpdateSql('jms_purchase_demand_detail', $updatePurchaseDemandDetail, $column, $where);
////        }
//
//        $updatePurchaseChannelGoodsSql = '';
//        if (!empty($updatePurchaseChannelGoods)) {
//            //更新条件
////            $where = [
////                'purchase_sn' => $purchase_sn
////            ];
//            //需要判断的字段
//            $column = 'id';
//            $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods', $updatePurchaseChannelGoods, $column, $where);
//        }
//
//        $updateRealPurchaseDetailSql = '';
//        if (!empty($updateRealPurchaseDetail)) {
//            //更新条件
//            $where = [
//                'real_purchase_sn' => $real_purchase_sn
//            ];
//            //需要判断的字段
//            $column = 'spec_sn';
//            $updateRealPurchaseDetailSql = makeBatchUpdateSql('jms_real_purchase_detail', $updateRealPurchaseDetail, $column, $where);
//        }
//        $return_info["count_sql"] = $updateDemandCountSql;
//        //$return_info["detail_sql"] = $updatePurchaseDemandDetailSql;
//        $return_info["sql_purchase_channels"] = $updatePurchaseChannelGoodsSql;
//        $return_info["sql_real_purchase_detail"] = $updateRealPurchaseDetailSql;
//        $return_info["channel_insert_arr"] = $channel_insert_arr;
//        $return_info["real_purchase_insert_arr"] = $real_purchase_insert_arr;
//        $return_info["insert_count_arr"] = $insert_count_arr;
//        $return_info["code"] = 1010;
//        return $return_info;
//    }
    public function createPredictRealSql($purchase_sn, $real_purchase_sn, $upload_goods_info, $brand_discount_goods_info)
    {
        //批次详情表数据
        $real_purchase_goods_info = DB::table("real_purchase_detail")
            ->where("real_purchase_sn", $real_purchase_sn)
            ->pluck("day_buy_num", "spec_sn");
        $real_purchase_goods_info = objectToArrayZ($real_purchase_goods_info);
        $real_spec_total_info = array_keys($real_purchase_goods_info);

        $real_purchase_insert_arr = [];
        $updateRealPurchaseDetail = [];
        foreach ($upload_goods_info as $k => $v) {
            //获取商品规格码
            $goods_spec_sn = $k;
            //获取上传商品信息
            $day_buy_num = $v['predict_num'];
            $is_match = $v['is_match'];
            $parent_spec_sn = '';
            if ($is_match) {
                $parent_spec_sn = $v['parent_spec_sn'];
            }
            //商品基础信息
            $goods_info = $brand_discount_goods_info[$goods_spec_sn];
            $tmp_channel_discount = $goods_info['brand_discount'];
            //批次详情表数据组装
            if (in_array($goods_spec_sn, $real_spec_total_info)) {
                $updateRealPurchaseDetail['day_buy_num'][] = [
                    $goods_spec_sn => 'day_buy_num + ' . $day_buy_num
                ];
                $updateRealPurchaseDetail['allot_num'][] = [
                    $goods_spec_sn => 'allot_num + ' . $day_buy_num
                ];
            } else {
                $real_purchase_insert_arr[] = [
                    "real_purchase_sn" => $real_purchase_sn,
                    "purchase_sn" => $purchase_sn,
                    "goods_name" => $goods_info["goods_name"],
                    "erp_prd_no" => $goods_info["erp_prd_no"],
                    "erp_merchant_no" => $goods_info["erp_merchant_no"],
                    "spec_sn" => $goods_spec_sn,
                    "day_buy_num" => $day_buy_num,
                    "sort_num" => $day_buy_num,
                    "allot_num" => $day_buy_num,
                    'channel_discount' => $tmp_channel_discount,
                    'is_match' => $is_match,
                    'parent_spec_sn' => $parent_spec_sn
                ];
            }
        }

        $updateRealPurchaseDetailSql = '';
        if (!empty($updateRealPurchaseDetail)) {
            //更新条件
            $where = [
                'real_purchase_sn' => $real_purchase_sn
            ];
            //需要判断的字段
            $column = 'spec_sn';
            $updateRealPurchaseDetailSql = makeBatchUpdateSql('jms_real_purchase_detail', $updateRealPurchaseDetail, $column, $where);
        }
        $return_info["sql_real_purchase_detail"] = $updateRealPurchaseDetailSql;
        $return_info["real_purchase_insert_arr"] = $real_purchase_insert_arr;
        return $return_info;
    }


}
