<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\DeliverGoodsModel;
use App\Model\Vone\DeliverOrderModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\RoleUserModel;
use App\Model\Vone\SpotGoodsModel;
use App\Model\Vone\SpotOrderModel;
use App\Modules\Erp\ErpApi;
use App\Modules\Excel\ExcuteExcel;
use Dingo\Api\Contract\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliverController extends BaseController
{
    /**
     * description:物流模块-MIS订单管理管理-配货单列表
     * editor:zongxing
     * date: 2018.12.15
     */
    public function distributionOrderList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $deliver_order_model = new DeliverOrderModel();
            $status = 2;
            $deliver_order_list = $deliver_order_model->deliverOrderList($param_info, $status);
            if (empty($deliver_order_list['data'])) {
                return response()->json(['code' => '1002', 'msg' => '暂无配货订单']);
            }
            $code = "1000";
            $msg = "获取配货单列表成功";
            $data = $deliver_order_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-配货单详情
     * editor:zongxing
     * date: 2018.12.15
     */
    public function distributionOrderDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["deliver_order_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '配货单单号不能为空']);
            }
            $deliver_order_sn = trim($param_info["deliver_order_sn"]);

            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_order_detail = $deliver_goods_model->deliverOrderDetail($deliver_order_sn);
            if (empty($deliver_order_detail)) {
                return response()->json(['code' => '1002', 'msg' => '配货单单号有误,请检查']);
            }
            $code = "1000";
            $msg = "获取配货单详情成功";
            $data = $deliver_order_detail;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-下载配货单
     * editor:zongxing
     * date: 2018.12.15
     */
    public function downloadDistributionOrder(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["deliver_order_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '配货单单号不能为空']);
            }
            $deliver_order_sn = trim($param_info["deliver_order_sn"]);
            $deliver_goods_model = new DeliverGoodsModel();
            $download_dis_res = $deliver_goods_model->downloadDistributionOrder($deliver_order_sn);
            $return_info = ['code' => '1003', 'msg' => '下载配货单数据失败'];
            if ($download_dis_res !== false) {
                $return_info = ['code' => '1000', 'msg' => '下载配货单数据成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-确认上传配货单
     * editor:zongxing
     * type:POST
     * date : 2018.12.15
     * params: 1.需要上传的excel表格文件:upload_file;2.配货单单号:deliver_order_sn;
     * return Object
     */
    public function doUploadDistributionOrder(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_data_array = $request->toArray();
            if (empty($purchase_data_array['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            } elseif (empty($purchase_data_array['deliver_order_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '配货单单号不能为空']);
            }
            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '配货单表';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($_FILES, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码', '实际发货数量'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1005', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }
            //检测配货单是否有误,同时获取配货单中商品的信息
            $deliver_order_sn = trim($purchase_data_array['deliver_order_sn']);
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_goods_info = $deliver_goods_model->deliverOrderDetail($deliver_order_sn);
            if (empty($deliver_goods_info)) {
                return response()->json(['code' => '1006', 'msg' => '配货单单号有误,请重新确认']);
            }
            //获取发货单是否包含现货单,如果包含,获取现货单商品信息
            $spot_order_sn = $deliver_goods_info[0]['spot_order_sn'];
            $spot_goods_list = [];
            if ($spot_order_sn) {
                $spot_goods_model = new SpotGoodsModel();
                $spot_goods_list = $spot_goods_model->getSpotGoodsInfo($spot_order_sn);
                $spot_goods_list = objectToArrayZ($spot_goods_list);
            }
            $spot_goods_info = [];
            if (!empty($spot_goods_list)) {
                foreach ($spot_goods_list as $k => $v) {
                    $spec_sn = $v['spec_sn'];
                    $spot_goods_info[$spec_sn] = $v;
                }
            }
            //获取发货单对应的销售客户id
            $sale_user_id = $deliver_goods_info[0]['sale_user_id'];
            //获取上传商品的规格码数据
            $upload_spec_sn = [];
            foreach ($res as $k => $v) {
                if ($k == 0) continue;
                if (!empty($v[2])) $upload_spec_sn[] = trim($v[2]);
            }
            $deliver_goods_total_info = [];
            foreach ($deliver_goods_info as $k => $v) {
                $deliver_goods_total_info[$v["spec_sn"]] = $v;
            }
            //对上传的商品和预采需求单中的而商品进行校验
            $deliver_spec_sn = array_keys($deliver_goods_total_info);
            $diff_spec_sn = array_diff($upload_spec_sn, $deliver_spec_sn);
            if (!empty($diff_spec_sn)) {
                $return_str = "商品规格码:";
                foreach ($diff_spec_sn as $k => $v) {
                    $return_str .= $v . ",";
                }
                $return_str = substr($return_str, 0, -1);
                $return_str .= " 在配货单单中不存在,请检查!";
                return response()->json(['code' => '1007', 'msg' => $return_str]);
            }
            //更新商品的实际发货数量,同时更新对应客户的待回款
            $update_deliver_goods = $deliver_goods_model->updateDeliverGoods($res, $deliver_order_sn, $sale_user_id,
                $spot_goods_info, $deliver_goods_total_info);
            if (isset($update_deliver_goods['code']) && $update_deliver_goods['code'] == '1010') {
                return response()->json($update_deliver_goods);
            }
            if ($update_deliver_goods !== false) {
                //推送发货单到erp
                $erp_api_model = new ErpApi();
                $erp_push_res = $erp_api_model->deliver_order_push($deliver_order_sn);
                $return_info = ['code' => '1008', 'msg' => '发货单_erp_推送失败'];
                if ($erp_push_res) {
                    $pushRes = true;
                    //如果存在现货单,需要对现货单进行取消处理
                    if ($spot_order_sn) {
                        $soModel = new SpotOrderModel();
                        //修改现货单状态为已取消
                        $status = 6;
                        $updateRes = $soModel->updateStatus($spot_order_sn, $status);
                        //将取消的订单推送至erp
                        if ($updateRes) {
                            $pushRes = $erp_api_model->spot_order_push($spot_order_sn);
                        }
                    }
                    $return_info = ['code' => '1009', 'msg' => '现货单货单取消_erp_推送失败'];
                    if ($pushRes) {
                        $return_info = ['code' => '1000', 'msg' => '上传配货单成功'];
                    }
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
     * description:销售模块-MIS订单管理管理-发货单列表
     * editor:zongxing
     * date: 2018.12.15
     */
    public function sellDeliverOrderList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $deliver_order_model = new DeliverOrderModel();
            //$status = 3;
            $deliver_order_list = $deliver_order_model->deliverOrderList($param_info);
            if (empty($deliver_order_list['data'])) {
                return response()->json(['code' => '1002', 'msg' => '暂无发货单']);
            }
            $code = "1000";
            $msg = "获取发货单列表成功";
            $data = $deliver_order_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-MIS订单管理管理-发货单列表-下载到货单
     * editor:zongxing
     * date: 2018.12.27
     */
    public function downloadSellDeliverOrder(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["deliver_order_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '到货单单号不能为空']);
            }
            $deliver_order_sn = trim($param_info["deliver_order_sn"]);
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_goods_model->downloadSellDeliverOrder($deliver_order_sn);

            $code = "1000";
            $msg = "获取到货单列表成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:销售模块-MIS订单管理管理-发货单列表-上传理货报告
     * editor:zongxing
     * type:POST
     * date : 2018.12.15
     * params: 1.需要上传的excel表格文件:upload_file;2.到货单单号:deliver_order_sn;
     * return Object
     */
    public function doUploadSellDeliverOrder(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_data_array = $request->toArray();
            if (empty($purchase_data_array['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            } elseif (empty($purchase_data_array['deliver_order_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '到货单单号不能为空']);
            }
            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '到货单表';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($_FILES, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码', '实际到货量'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1005', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }
            //检测到货单是否有误,同时获取到货单中商品的信息
            $deliver_order_sn = trim($purchase_data_array['deliver_order_sn']);
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_goods_info = $deliver_goods_model->deliverOrderDetail($deliver_order_sn);
            if (empty($deliver_goods_info)) {
                return response()->json(['code' => '1006', 'msg' => '到货单单号有误,请重新确认']);
            }
            //获取到货单对应的销售客户id
            $sale_user_id = $deliver_goods_info[0]['sale_user_id'];
            //获取上传商品的规格码数据
            $upload_spec_sn = [];
            foreach ($res as $k => $v) {
                if ($k == 0) continue;
                if (!empty($v[1])) $upload_spec_sn[] = trim($v[1]);
            }
            $deliver_goods_total_info = [];
            foreach ($deliver_goods_info as $k => $v) {
                $deliver_goods_total_info[$v["spec_sn"]] = $v;
            }
            //对上传的商品和预采需求单中的而商品进行校验
            $deliver_spec_sn = array_keys($deliver_goods_total_info);
            $diff_spec_sn = array_diff($upload_spec_sn, $deliver_spec_sn);
            if (!empty($diff_spec_sn)) {
                $return_str = "商品规格码:";
                foreach ($diff_spec_sn as $k => $v) {
                    $return_str .= $v . ",";
                }
                $return_str = substr($return_str, 0, -1);
                $return_str .= " 在到货单单中不存在,请检查!";
                return response()->json(['code' => '1007', 'msg' => $return_str]);
            }
            //更新商品的实际到货数量,同时更新对应客户的实际待回款金额
            $update_deliver_goods = $deliver_goods_model->updateSellDeliverGoods($res, $deliver_order_sn, $sale_user_id,
                $deliver_goods_total_info);
            if (isset($update_deliver_goods['code']) && $update_deliver_goods['code'] == '1010') {
                return response()->json($update_deliver_goods);
            }
            $return_info = ['code' => '1008', 'msg' => '上传理货报告失败'];
            if ($update_deliver_goods !== false) {
                $return_info = ['code' => '1000', 'msg' => '上传理货报告成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-退货单列表
     * editor:zongxing
     * date: 2018.12.27
     */
    public function sellReturnOrderList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $deliver_order_model = new DeliverOrderModel();
            $status = 4;
            $deliver_order_list = $deliver_order_model->deliverOrderList($param_info, $status);
            if (empty($deliver_order_list['data'])) {
                return response()->json(['code' => '1002', 'msg' => '暂无发货单']);
            }
            $code = "1000";
            $msg = "获取发货单列表成功";
            $data = $deliver_order_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-退货单详情
     * editor:zongxing
     * date: 2018.12.27
     */
    public function sellReturnOrderDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["deliver_order_sn"])) {
                return response()->json(['code' => '1003', 'msg' => '退货单单号不能为空']);
            }
            $deliver_order_sn = trim($param_info["deliver_order_sn"]);

            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_order_detail = $deliver_goods_model->deliverOrderDetail($deliver_order_sn);
            if (empty($deliver_order_detail)) {
                return response()->json(['code' => '1004', 'msg' => '退货单单号有误,请检查']);
            }
            foreach ($deliver_order_detail as $k => $v) {
                $real_ship_num = $v['real_ship_num'];
                $real_arrival_num = $v['real_arrival_num'];
                $return_num = $real_ship_num - $real_arrival_num;
                $deliver_order_detail[$k]['return_num'] = $return_num;
            }
            $code = "1000";
            $msg = "获取退货单详情成功";
            $data = $deliver_order_detail;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-下载退货单数据
     * editor:zongxing
     * date:2018.12.27
     */
    public function downloadSellReturnGoods(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["deliver_order_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '退货单单号不能为空']);
            }
            $deliver_order_sn = trim($param_info["deliver_order_sn"]);
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_order_detail = $deliver_goods_model->deliverOrderDetail($deliver_order_sn);
            if (empty($deliver_order_detail)) {
                return response()->json(['code' => '1003', 'msg' => '退货单单号有误,请检查']);
            }
            foreach ($deliver_order_detail as $k => $v) {
                $real_ship_num = $v['real_ship_num'];
                $real_arrival_num = $v['real_arrival_num'];
                $return_num = $real_ship_num - $real_arrival_num;
                $deliver_order_detail[$k]['return_num'] = $return_num;
            }
            $res = $this->exportAllotData($deliver_order_detail, $deliver_order_sn);
            $return_info = ['code' => '1004', 'msg' => '退货单单号不能为空'];
            if ($res !== false) {
                $return_info = ['code' => '1000', 'msg' => '下载退货单数据成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-下载退货单数据-数据组装
     * editor:zongxing
     * date:2018.12.27
     */
    private function exportAllotData($deliver_order_detail, $deliver_order_sn)
    {
        $title = [['商品名称', '商家编码', '商品规格码', '预计发货数量', '实际发货数量', '实际到货数量', '退货数量', '清点数量']];
        $goods_list = [];
        foreach ($deliver_order_detail as $k => $v) {
            $goods_list[$k] = [
                $v['goods_name'], $v['erp_merchant_no'], $v['spec_sn'],
                $v['pre_ship_num'], $v['real_ship_num'], $v['real_arrival_num'], $v['return_num']
            ];
        }
        //实采单号
        $filename = '客户退货单表_' . $deliver_order_sn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $excuteExcel = new ExcuteExcel();
        return $excuteExcel->exportZ($exportData, $filename);
    }

    /**
     * description:物流模块-MIS订单管理管理-上传退货清点数据
     * editor:zongxing
     * type:POST
     * date : 2018.12.27
     * params: 1.需要上传的excel表格文件:upload_file;2.退货单单号:deliver_order_sn;
     * return Array
     */
    public function doUploadSellReturnGoods(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_data_array = $request->toArray();
            if (empty($purchase_data_array['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            } elseif (empty($purchase_data_array['deliver_order_sn'])) {
                return response()->json(['code' => '1003', 'msg' => '退货单单号不能为空']);
            }
            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '客户退货单表_';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($_FILES, $fileName);
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
            //检测到货单是否有误,同时获取到货单中商品的信息
            $deliver_order_sn = trim($purchase_data_array['deliver_order_sn']);
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_goods_info = $deliver_goods_model->deliverOrderDetail($deliver_order_sn);
            if (empty($deliver_goods_info)) {
                return response()->json(['code' => '1006', 'msg' => '退货单单号有误,请重新确认']);
            }
            //获取到货单对应的销售客户id
            $sale_user_id = $deliver_goods_info[0]['sale_user_id'];
            //获取上传商品的规格码数据
            $upload_spec_sn = [];
            foreach ($res as $k => $v) {
                if ($k == 0) continue;
                if (!empty($v[2])) $upload_spec_sn[] = trim($v[2]);
            }
            $deliver_goods_total_info = [];

            foreach ($deliver_goods_info as $k => $v) {
                $deliver_goods_total_info[$v["spec_sn"]] = $v;
            }

            //对上传的商品和预采需求单中的而商品进行校验
            $deliver_spec_sn = array_keys($deliver_goods_total_info);
            $diff_spec_sn = array_diff($upload_spec_sn, $deliver_spec_sn);
            if (!empty($diff_spec_sn)) {
                $return_str = "商品规格码:";
                foreach ($diff_spec_sn as $k => $v) {
                    $return_str .= $v . ",";
                }
                $return_str = substr($return_str, 0, -1);
                $return_str .= " 在退货单单中不存在,请检查!";
                return response()->json(['code' => '1007', 'msg' => $return_str]);
            }

            //更新商品的清点数量,同时更新对应客户的最终待回款金额
            $update_deliver_goods = $deliver_goods_model->updateSellReturnGoods($res, $deliver_order_sn, $sale_user_id,
                $deliver_goods_total_info);
            if (isset($update_deliver_goods['code']) && $update_deliver_goods['code'] == '1010') {
                return response()->json($update_deliver_goods);
            }
            $return_info = ['code' => '1008', 'msg' => '上传退货清点数量失败'];
            if ($update_deliver_goods !== false) {
                $return_info = ['code' => '1000', 'msg' => '上传退货清点数量成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:物流模块-MIS订单管理管理-退货商品清点入库
     * editor:zongxing
     * type:POST
     * date : 2018.12.27
     * params: 1.到货单单号:deliver_order_sn;
     * return Array
     */
    public function returnGoodsAllotStock(Request $request)
    {
        if ($request->isMethod('post')) {
            $purchase_data_array = $request->toArray();
            if (empty($purchase_data_array['deliver_order_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '退货单单号不能为空']);
            }
            //检测到货单是否有误,同时获取到货单中商品的信息
            $deliver_order_sn = trim($purchase_data_array['deliver_order_sn']);
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_goods_info = $deliver_goods_model->deliverOrderDetail($deliver_order_sn);
            if (empty($deliver_goods_info)) {
                return response()->json(['code' => '1003', 'msg' => '到货单单号有误,请重新确认']);
            }

            //更新发货单状态
            $status = 4;
            $deliver_order_model = new DeliverOrderModel();
            $update_res = $deliver_order_model->changeDeliverStatus($deliver_order_sn, $status);
            $return_info = ['code' => '1004', 'msg' => '更新发货单状态失败'];
            if ($update_res !== false) {
                $return_info = ['code' => '1000', 'msg' => '更新发货单状态成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }


}