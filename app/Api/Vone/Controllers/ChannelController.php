<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\OperateLogModel;
use App\Model\Vone\PurchaseChannelModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class ChannelController extends BaseController
{
    /**
     * description:添加采购渠道
     * editor:zongxing
     * type:POST
     * date : 2018.06.26
     * params: 1.采购渠道名称:channels_name;
     * return Object
     */
    public function createChannels(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["channels_name"])) {
                return response()->json(['code' => '1002', 'msg' => '采购渠道名称不能为空']);
            } elseif (empty($param_info["method_id"])) {
                return response()->json(['code' => '1003', 'msg' => '采购渠道所属的方式不能为空']);
            } elseif (!isset($param_info['original_or_discount'])) {
                return response()->json(['code' => '1004', 'msg' => '采购渠道所属的结算方式不能为空']);
            }

            //计算采购期编号
            $model_obj = new PurchaseChannelModel();
            $model_field = "channels_sn";
            $pin_head = "QD-";
            $last_channel_sn = createNo($model_obj, $model_field, $pin_head, false);

            $channels_name = trim($param_info["channels_name"]);
            $method_id = intval($param_info["method_id"]);
            $is_already = DB::table("purchase_channels")
                ->where("channels_name", $channels_name)
                ->where("method_id", $method_id)
                ->first();
            $is_already = objectToArrayZ($is_already);
            if (!empty($is_already)) {
                return response()->json(['code' => '1005', 'msg' => '该采购渠道已经存在']);
            }

            $insert_purchase_channel = [
                'channels_sn' => $last_channel_sn,
                'channels_name' => $channels_name,
                'method_id' => $method_id,
                'post_discount' => floatval($param_info['post_discount']),
                'is_count_wai' => intval($param_info['is_count_wai']),
                'original_or_discount' => intval($param_info['original_or_discount']),
                'team_add_points' => floatval($param_info['team_add_points']),
                'recharge_points' => floatval($param_info['recharge_points']),
            ];
            $res = DB::table('purchase_channels')->insert($insert_purchase_channel);
            $return_info = ['code' => '1006', 'msg' => '采购渠道添加失败'];
            if ($res != false) {
                $return_info = ['code' => '1000', 'msg' => '采购渠道添加成功'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取采购渠道列表
     * editor:zongxing
     * type:GET
     * date : 2018.06.26
     * return Object
     */
    public function getChannelsList(Request $request)
    {
        $param_info = $request->toArray();
        $method_id = '';
        if (isset($param_info['method_id'])) {
            $method_id = intval($param_info['method_id']);
        }
        $pc_model = new PurchaseChannelModel();
        $pc_info = $pc_model->getChannelList(null, $method_id);
        $return_info = ['code' => '1000', 'msg' => '获取采购渠道列表成功', 'data' => $pc_info];
        return response()->json($return_info);
    }

    /**
     * description:打开编辑采购渠道详情页
     * editor:zongxing
     * type:POST
     * date : 2019.01.25
     * params: 1.采购渠道id:id;
     * return Array
     */
    public function editChannel(Request $request)
    {
        if ($request->isMethod('post')) {
            $params_info = $request->toArray();
            $channel_id = $params_info["id"];
            //获取指定采购方式信息
            $purchase_channel_info = PurchaseChannelModel::where("id", $channel_id)->get();
            if ($purchase_channel_info->isEmpty()) {
                return response()->json(['code' => '1002', 'msg' => '采购渠道不存在']);
            }
            $code = "1000";
            $msg = "获取指定采购渠道信息成功";
            $data = $purchase_channel_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:确认提交编辑采购渠道
     * editor:zongxing
     * type:POST
     * date : 2019.01.25
     * return Object
     */
    public function doEditChannel(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            $channel_id = $param_info["id"];

            //获取指定采购方式信息
            $purchase_channel_info = PurchaseChannelModel::where("id", $channel_id)->first();
            $purchase_channel_info = objectToArrayZ($purchase_channel_info);
            if (empty($purchase_channel_info)) {
                return response()->json(['code' => '1002', 'msg' => '采购渠道不存在']);
            }

            if (empty($param_info['channels_name'])) {
                return response()->json(['code' => '1003', 'msg' => '采购渠道名称不能为空']);
            } else if (empty($param_info['method_id'])) {
                return response()->json(['code' => '1004', 'msg' => '采购方式id不能为空']);
            } else if (!isset($param_info['post_discount'])) {
                return response()->json(['code' => '1005', 'msg' => '采购渠道运费折扣不能为空']);
            } else if (!isset($param_info["is_count_wai"])) {
                return response()->json(['code' => '1006', 'msg' => '采购渠道是否计算外采临界点不能为空']);
            } else if (!isset($param_info['original_or_discount'])) {
                return response()->json(['code' => '1008', 'msg' => '采购渠道结算方式不能为空']);
            } else if (!isset($param_info['is_gears'])) {
                return response()->json(['code' => '1009', 'msg' => '采购渠道是否存在多个档位不能为空']);
            }

            $channel_name = trim($param_info["channels_name"]);
            $purchase_channel = PurchaseChannelModel::where("channels_name", $channel_name)->first();
            $purchase_channel = objectToArrayZ($purchase_channel);
            if (!empty($purchase_channel) && $purchase_channel['id'] != $channel_id) {
                return response()->json(['code' => '1007', 'msg' => '该采购渠道名称已经存在']);
            }

            $update_channel = [
                'channels_name' => $channel_name,
                'method_id' => intval($param_info['method_id']),
                'post_discount' => floatval($param_info['post_discount']),
                'is_count_wai' => intval($param_info['is_count_wai']),
                'original_or_discount' => intval($param_info['original_or_discount']),
                'team_add_points' => floatval($param_info['team_add_points']),
            ];
            $updateRes = DB::table('purchase_channels')->where('id', $channel_id)->update($update_channel);

            $code = "1005";
            $msg = "采购渠道编辑失败";
            $return_info = compact('code', 'msg');

            if ($updateRes !== false) {
                $code = "1000";
                $msg = "采购渠道编辑成功";
                $return_info = compact('code', 'msg');
                //记录日志
                $operateLogModel = new OperateLogModel();
                $loginUserInfo = $request->user();
                $logData = [
                    'table_name' => 'jms_purchase_method',
                    'bus_desc' => '采购渠道-确认提交编辑采购渠道-采购渠道代码：' . $purchase_channel_info["channels_sn"],
                    'bus_value' => $purchase_channel_info["channels_sn"],
                    'admin_name' => trim($loginUserInfo->user_name),
                    'admin_id' => trim($loginUserInfo->id),
                    'ope_module_name' => '采购渠道-确认提交编辑采购渠道',
                    'module_id' => 2,
                    'have_detail' => 1,
                ];

                $logDetailData["table_name"] = 'operate_log_detail';
                foreach ($update_channel as $k => $v) {
                    if (isset($purchase_channel_info[$k]) && $purchase_channel_info[$k] != $v) {
                        $logDetailData["update_info"][] = [
                            'table_field_name' => $k,
                            'field_old_value' => $purchase_channel_info[$k],
                            'field_new_value' => $v,
                        ];
                    }
                }
                if (isset($logDetailData["update_info"])) {
                    $operateLogModel->insertMoreLog($logData, $logDetailData);
                }
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:改变表格标题样式
     * editor:zongxing
     * date : 2018.06.28
     * params: 1.excel对象:$obj_excel;2.最后一列的名称:$column_last_name;
     * return Object
     */
    public function changeTableTitle($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
    {
        //标题居中+加粗
        $obj_excel->getActiveSheet()->getStyle($column_first_name . $row_first_i . ":" . $column_last_name . $row_last_i)
            ->applyFromArray(
                array(
                    'font' => array(
                        'bold' => true
                    ),
                    'alignment' => array(
                        'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                    )
                )
            );
    }

    /**
     * description:改变表格内容样式
     * editor:zongxing
     * date : 2018.06.28
     * params: 1.excel对象:$obj_excel;2.最后一列的名称:$column_last_name;3.最大行号:$row_end;
     * return Object
     */
    public function changeTableContent($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
    {
        //内容只居中
        $obj_excel->getActiveSheet()->getStyle($column_first_name . $row_first_i . ":" . $column_last_name . $row_last_i)->applyFromArray(
            array(
                'alignment' => array(
                    'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                )
            )
        );
    }


}