<?php
namespace App\Api\Vone\Controllers;

use App\Http\Requests\ProfitDetail;
use App\Model\Vone\CardConsumeModel;
use App\Model\Vone\CatFormulaModel;
use App\Model\Vone\ChannelsIntegralLogModel;
use App\Model\Vone\ChannelsIntegralModel;
use App\Model\Vone\CommonModel;
use App\Model\Vone\DeliverGoodsModel;
use App\Model\Vone\DeliverOrderModel;
use App\Model\Vone\DemandCountModel;
use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\DemandModel;
use App\Model\Vone\DiscountCatModel;
use App\Model\Vone\DiscountTypeInfoModel;
use App\Model\Vone\exchangeRateModel;
use App\Model\Vone\FundCatModel;
use App\Model\Vone\FundChannelModel;
use App\Model\Vone\GmcDiscountModel;
use App\Model\Vone\OperateLogModel;
use App\Model\Vone\ProfitFormulaModel;
use App\Model\Vone\ProfitGoodsModel;
use App\Model\Vone\ProfitModel;
use App\Model\Vone\ProfitParamModel;
use App\Model\Vone\PurchaseChannelGoodsModel;
use App\Model\Vone\PurchaseChannelModel;
use App\Model\Vone\PurchaseDemandDetailModel;
use App\Model\Vone\PurchaseDemandModel;
use App\Model\Vone\PurchaseMethodModel;
use App\Model\Vone\RealPurchaseAuditModel;
use App\Model\Vone\RealPurchaseDeatilAuditModel;
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\RealPurchaseModel;
use App\Model\Vone\SaleUserModel;
use App\Modules\Excel\ExcuteExcel;
use Carbon\Carbon;
use Dingo\Api\Contract\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\ParamsCheckSingle;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Classes\PHPExcel;

/**
 * description:财务模块控制器
 * editor:zongxing
 * date : 2018.12.06
 */
class FinanceController extends BaseController
{
    /**
     * description:财务模块-财务渠道管理-新增资金渠道
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * params: 1.资金渠道所属类别id:fund_cat_id;2.资金渠道名称:fund_channel_name;
     * return Object
     */
    public function addFundChannel(Request $request)
    {
        //获取资金渠道类别列表
        $fund_cat_model = new FundCatModel();
        $fund_cat_list = $fund_cat_model->getFundCatList();
        if (empty($fund_cat_list)) {
            return response()->json(['code' => '1002', 'msg' => '请先新增资金渠道类别']);
        }
        $data['fund_cat_list'] = $fund_cat_list;
        //获取销售客户列表
        $sale_user_model = new SaleUserModel();
        $sale_user_list = $sale_user_model->getSaleUserList();
        if (empty($fund_cat_list)) {
            return response()->json(['code' => '1003', 'msg' => '请先新增销售客户']);
        }
        $data['sale_user_list'] = $sale_user_list;
        $code = "1000";
        $msg = "获取资金渠道类别成功";
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:财务模块-财务渠道管理-新增资金渠道
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * params: 1.资金渠道所属类别id:fund_cat_id;2.资金渠道名称:fund_channel_name;
     * return Object
     */
    public function doAddFundChannel(Request $request)
    {
        $reqParams = $request->toArray();
        if (empty($reqParams["fund_channel_name"]) && empty($reqParams["sale_user_id"])) {
            return response()->json(['code' => '1002', 'msg' => '资金渠道名称和销售客户不能都为空']);
        } else if (!empty($reqParams["fund_channel_name"]) && !empty($reqParams["sale_user_id"])) {
            return response()->json(['code' => '1006', 'msg' => '资金渠道名称和销售客户只能选择一个']);
        } else if (empty($reqParams["fund_cat_id"])) {
            return response()->json(['code' => '1003', 'msg' => '资金渠道所属类别id不能为空']);
        }

        //检查指定类别下该资金渠道是否存在
        if (!empty($reqParams["fund_channel_name"])) $fund_channel_name = trim($reqParams["fund_channel_name"]);
        if (!empty($reqParams["sale_user_id"])) {
            $sale_user_id = intval($reqParams["sale_user_id"]);
            $sale_user_model = new SaleUserModel();
            $sale_user_info = $sale_user_model->getSaleUserInfo($sale_user_id);
            $fund_channel_name = $sale_user_info['user_name'];
            $insert_fund_channel["sale_user_id"] = $sale_user_id;
        }

        $fund_cat_id = intval($reqParams["fund_cat_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_info = $fundChannelModel->getFundChannelInfo($fund_channel_name, $fund_cat_id, null);
        if (!empty($fund_channel_info)) {
            return response()->json(['code' => '1004', 'msg' => '指定类别下该资金渠道已经存在']);
        }
        $insert_fund_channel["fund_channel_name"] = $fund_channel_name;
        $insert_fund_channel["fund_cat_id"] = $fund_cat_id;
        $insert_res = DB::table("fund_channel")->insertGetId($insert_fund_channel);
        $code = "1005";
        $msg = "新增资金渠道失败";
        $return_info = compact('code', 'msg');
        if ($insert_res) {
            $code = "1000";
            $msg = "新增资金渠道成功";
            $return_info = compact('code', 'msg');

            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'jms_fund_channel',
                'bus_desc' => '新增资金渠道，资金渠道ID：' . $insert_res,
                'bus_value' => $insert_res,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '财务模块-财务渠道管理-新增资金渠道',
                'module_id' => 7,
                'have_detail' => 0,
            ];
            $operateLogModel->insertLog($logData);
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-财务渠道管理-编辑资金渠道
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * params: 1.资金渠道id:fund_channel_id;
     * return Object
     */
    public function editFundChannel(Request $request)
    {
        $reqParams = $request->toArray();
        if (empty($reqParams["fund_channel_id"])) {
            return response()->json(['code' => '1002', 'msg' => '资金渠道id不能为空']);
        }
        //检查指定类别下该资金渠道是否存在
        $fund_channel_id = intval($reqParams["fund_channel_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_info = $fundChannelModel->getFundChannelInfo(null, null, $fund_channel_id);
        if (empty($fund_channel_info)) {
            return response()->json(['code' => '1003', 'msg' => '参数有误,该资金渠道不存在']);
        }
        //获取资金渠道类别列表
        $fund_cat_model = new FundCatModel();
        $fund_cat_list = $fund_cat_model->getFundCatList();
        if (empty($fund_cat_list)) {
            return response()->json(['code' => '1004', 'msg' => '请先新增资金渠道类别']);
        }
        //获取销售客户列表
        $sale_user_model = new SaleUserModel();
        $sale_user_list = $sale_user_model->getSaleUserList();
        if (empty($fund_cat_list)) {
            return response()->json(['code' => '1005', 'msg' => '请先新增销售客户']);
        }
        $code = "1000";
        $msg = "获取资金渠道信息成功";
        $data["fund_channel_info"] = $fund_channel_info;
        $data["fund_cat_list"] = $fund_cat_list;
        $data["sale_user_list"] = $sale_user_list;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:财务模块-财务渠道管理-确认编辑资金渠道
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * params: 1.资金渠道所属类别id:fund_cat_id;2.资金渠道名称:fund_channel_name;
     * return Object
     */
    public function doEditFundChannel(Request $request)
    {
        $reqParams = $request->toArray();
        if (empty($reqParams["fund_channel_id"])) {
            return response()->json(['code' => '1002', 'msg' => '资金渠道id不能为空']);
        } else if (empty($reqParams["fund_channel_name"]) && empty($reqParams["sale_user_id"])) {
            return response()->json(['code' => '1003', 'msg' => '资金渠道名称和销售客户不能都为空']);
        } else if (!empty($reqParams["fund_channel_name"]) && !empty($reqParams["sale_user_id"])) {
            return response()->json(['code' => '1008', 'msg' => '资金渠道名称和销售客户只能选择一个']);
        } else if (empty($reqParams["fund_cat_id"])) {
            return response()->json(['code' => '1004', 'msg' => '资金渠道所属类别id不能为空']);
        }
        //检查该资金渠道是否存在
        $fund_channel_id = intval($reqParams["fund_channel_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_info = $fundChannelModel->getFundChannelInfo(null, null, $fund_channel_id);
        if (empty($fund_channel_info)) {
            return response()->json(['code' => '1005', 'msg' => '参数有误,该资金渠道不存在']);
        }
        //检查指定类别下该资金渠道是否存在
        if (!empty($reqParams["fund_channel_name"])) $fund_channel_name = trim($reqParams["fund_channel_name"]);
        if (!empty($reqParams["sale_user_id"])) {
            $sale_user_id = intval($reqParams["sale_user_id"]);
            $sale_user_model = new SaleUserModel();
            $sale_user_info = $sale_user_model->getSaleUserInfo($sale_user_id);
            $fund_channel_name = $sale_user_info['user_name'];
            $update_fund_channel["sale_user_id"] = $sale_user_id;
        }
        $fund_cat_id = intval($reqParams["fund_cat_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_isset = $fundChannelModel->getFundChannelInfo($fund_channel_name, $fund_cat_id, null);
        if (!empty($fund_channel_isset) && $fund_channel_isset[0]['id'] != $fund_channel_id) {
            return response()->json(['code' => '1006', 'msg' => '指定类别下该资金渠道已经存在']);
        }
        $update_fund_channel["fund_channel_name"] = $fund_channel_name;
        $update_fund_channel["fund_cat_id"] = $fund_cat_id;
        $update_res = DB::table("fund_channel")->where("id", $fund_channel_id)->update($update_fund_channel);
        $code = "1007";
        $msg = "编辑资金渠道失败";
        $return_info = compact('code', 'msg');
        if ($update_res !== false) {
            $code = "1000";
            $msg = "编辑资金渠道成功";
            $return_info = compact('code', 'msg');
            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'jms_fund_channel',
                'bus_desc' => '编辑资金渠道，资金渠道ID：' . $fund_channel_id,
                'bus_value' => $fund_channel_id,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '财务模块-财务渠道管理-新增资金渠道',
                'module_id' => 7,
                'have_detail' => 0,
            ];
            $logDetailData["table_name"] = 'operate_log_detail';
            $fund_channel_info = $fund_channel_info[0];
            foreach ($update_fund_channel as $k => $v) {
                if (isset($fund_channel_info[$k]) && $fund_channel_info[$k] != $v) {
                    $logDetailData["update_info"][] = [
                        'table_field_name' => $k,
                        'field_old_value' => $fund_channel_info[$k],
                        'field_new_value' => $v,
                    ];
                }
            }
            if (isset($logDetailData["update_info"])) {
                $operateLogModel->insertMoreLog($logData, $logDetailData);
            }
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-财务渠道管理-资金渠道列表
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * return Object
     */
    public function getFundChannelList()
    {
        $fund_channel_list = DB::table("fund_channel as fc1")
            ->leftJoin("fund_cat as fc2", "fc2.id", "=", "fc1.fund_cat_id")
            ->get(["fc1.id", "fund_channel_name", "fund_cat_id", "fund_cat_name"]);
        $fund_channel_list = objectToArrayZ($fund_channel_list);
        if (empty($fund_channel_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无资金渠道']);
        }
        $code = "1000";
        $msg = "获取资金渠道列表成功";
        $data = $fund_channel_list;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:财务模块-自有可支配资金管理-自有可支配资金列表
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * return Object
     */
    public function discretionaryFundList()
    {
        $fund_channel_list = DB::table("fund_channel as fc")
            ->leftJoin("fund_cat as fc2", "fc2.id", "=", "fc.fund_cat_id")
            ->where("fund_cat_id", 1)
            ->get(["fc.id", "fund_channel_name", "fund_cat_name", "usd", "cny", "krw", "covert_cny"]);
        $fund_channel_list = objectToArrayZ($fund_channel_list);
        if (empty($fund_channel_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无自有可支配资金']);
        }
        $code = "1000";
        $msg = "获取自有可支配资金列表成功";
        $data = $fund_channel_list;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:财务模块-自有可支配资金管理-编辑自有可支配资金
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * return Object
     */
    public function editDiscretionaryFund(Request $request)
    {
        $reqParams = $request->toArray();
        if (empty($reqParams["fund_channel_id"])) {
            return response()->json(['code' => '1002', 'msg' => '资金渠道id不能为空']);
        }
        //检查指定类别下该资金渠道是否存在
        $fund_channel_id = intval($reqParams["fund_channel_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_info = $fundChannelModel->getFundChannelInfo(null, null, $fund_channel_id);
        if (empty($fund_channel_info)) {
            return response()->json(['code' => '1003', 'msg' => '参数有误,该资金渠道不存在']);
        }

        $code = "1000";
        $msg = "获取资金渠道信息成功";
        $data["fund_channel_info"] = $fund_channel_info;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:财务模块-自有可支配资金管理-确认编辑自有可支配资金
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * params: 1.资金渠道id:fund_channel_id;2.美金:usd;3.人民币:cny;4.韩币:krw;
     * return Object
     */
    public function doEditDiscretionaryFund(Request $request)
    {
        $reqParams = $request->toArray();
        if (empty($reqParams["fund_channel_id"])) {
            return response()->json(['code' => '1002', 'msg' => '资金渠道id不能为空']);
        } else if (empty($reqParams["usd"]) && empty($reqParams["cny"]) && empty($reqParams["krw"])) {
            return response()->json(['code' => '1003', 'msg' => '美金/人民币/韩币必选其一']);
        }
        //检查该资金渠道是否存在
        $fund_channel_id = intval($reqParams["fund_channel_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_info = $fundChannelModel->getFundChannelInfo(null, null, $fund_channel_id);
        if (empty($fund_channel_info)) {
            return response()->json(['code' => '1004', 'msg' => '参数有误,该资金渠道不存在']);
        }
        //计算折合人民币
        $update_fund_channel["covert_cny"] = round($reqParams["cny"], 2);
        $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
        $KRW_CNY_RATE = convertCurrency("KRW", "CNY", "1");
        if ($reqParams["usd"]) {
            $usd = round($reqParams["usd"], 2);
            $update_fund_channel["covert_cny"] = round($usd * $USD_CNY_RATE, 2);
        }
        if ($reqParams["krw"]) {
            $usd = round($reqParams["krw"], 2);
            $update_fund_channel["covert_cny"] = round($usd * $KRW_CNY_RATE, 2);
        }
        //更新资金渠道信息
        $update_fund_channel["usd"] = round($reqParams["usd"], 2);
        $update_fund_channel["cny"] = round($reqParams["cny"], 2);
        $update_fund_channel["krw"] = round($reqParams["krw"], 2);

        $update_res = DB::table("fund_channel")->where("id", $fund_channel_id)->update($update_fund_channel);

        $code = "1005";
        $msg = "编辑资金渠道失败";
        $return_info = compact('code', 'msg');
        if ($update_res !== false) {
            $code = "1000";
            $msg = "编辑资金渠道成功";
            $return_info = compact('code', 'msg');

            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'jms_fund_channel',
                'bus_desc' => '编辑资金渠道，资金渠道ID：' . $fund_channel_id,
                'bus_value' => $fund_channel_id,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '财务模块-财务渠道管理-新增资金渠道',
                'module_id' => 7,
                'have_detail' => 0,
            ];

            $logDetailData["table_name"] = 'operate_log_detail';
            $fund_channel_info = $fund_channel_info[0];
            foreach ($update_fund_channel as $k => $v) {
                if (isset($fund_channel_info[$k]) && $fund_channel_info[$k] != $v) {
                    $logDetailData["update_info"][] = [
                        'table_field_name' => $k,
                        'field_old_value' => $fund_channel_info[$k],
                        'field_new_value' => $v,
                    ];
                }
            }
            if (isset($logDetailData["update_info"])) {
                $operateLogModel->insertMoreLog($logData, $logDetailData);
            }
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-可融资金管理-可融资金列表
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * return Object
     */
    public function rongFundList()
    {
        $fund_channel_list = DB::table("fund_channel as fc")
            ->leftJoin("fund_cat as fc2", "fc2.id", "=", "fc.fund_cat_id")
            ->where("fund_cat_id", 2)
            ->get(["fc.id", "fund_channel_name", "fund_cat_name", "usd", "cny", "krw", "covert_cny"]);
        $fund_channel_list = objectToArrayZ($fund_channel_list);
        if (empty($fund_channel_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无可融资金']);
        }
        $code = "1000";
        $msg = "获取可融资金列表成功";
        $data = $fund_channel_list;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:财务模块-可融资金管理-编辑可融资金
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * params: 1.资金渠道id:fund_channel_id;
     * return Object
     */
    public function editRongFund(Request $request)
    {
        $reqParams = $request->toArray();
        if (empty($reqParams["fund_channel_id"])) {
            return response()->json(['code' => '1002', 'msg' => '资金渠道id不能为空']);
        }
        //检查指定类别下该资金渠道是否存在
        $fund_channel_id = intval($reqParams["fund_channel_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_info = $fundChannelModel->getFundChannelInfo(null, null, $fund_channel_id);
        if (empty($fund_channel_info)) {
            return response()->json(['code' => '1003', 'msg' => '参数有误,该资金渠道不存在']);
        }

        $code = "1000";
        $msg = "获取资金渠道信息成功";
        $data["fund_channel_info"] = $fund_channel_info;
        $return_info = compact('code', 'msg', 'data');
        return response()->json($return_info);
    }

    /**
     * description:财务模块-可融资金管理-确认编辑可融资金
     * editor:zongxing
     * type:POST
     * date : 2018.12.06
     * params: 1.资金渠道id:fund_channel_id;2.美金:usd;3.人民币:cny;4.韩币:krw;
     * return Object
     */
    public function doEditRongFund(Request $request)
    {
        $reqParams = $request->toArray();
        if (empty($reqParams["fund_channel_id"])) {
            return response()->json(['code' => '1002', 'msg' => '资金渠道id不能为空']);
        } elseif (empty($reqParams["usd"]) && empty($reqParams["cny"]) && empty($reqParams["krw"])) {
            return response()->json(['code' => '1003', 'msg' => '美金/人民币/韩币必选其一']);
        }
        //检查该资金渠道是否存在
        $fund_channel_id = intval($reqParams["fund_channel_id"]);
        $fundChannelModel = new FundChannelModel();
        $fund_channel_info = $fundChannelModel->getFundChannelInfo(null, null, $fund_channel_id);
        if (empty($fund_channel_info)) {
            return response()->json(['code' => '1004', 'msg' => '参数有误,该资金渠道不存在']);
        }
        //计算折合人民币
        $update_fund_channel["covert_cny"] = round($reqParams["cny"], 2);
        $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
        $KRW_CNY_RATE = convertCurrency("KRW", "CNY", "1");
        if ($reqParams["usd"]) {
            $usd = round($reqParams["usd"], 2);
            $update_fund_channel["covert_cny"] = round($usd * $USD_CNY_RATE, 2);
        }
        if ($reqParams["krw"]) {
            $usd = round($reqParams["krw"], 2);
            $update_fund_channel["covert_cny"] = round($usd * $KRW_CNY_RATE, 2);
        }
        //更新资金渠道信息
        $update_fund_channel["usd"] = round($reqParams["usd"], 2);
        $update_fund_channel["cny"] = round($reqParams["cny"], 2);
        $update_fund_channel["krw"] = round($reqParams["krw"], 2);
        $update_res = DB::table("fund_channel")->where("id", $fund_channel_id)->update($update_fund_channel);

        $code = "1005";
        $msg = "编辑资金渠道失败";
        $return_info = compact('code', 'msg');
        if ($update_res !== false) {
            $code = "1000";
            $msg = "编辑资金渠道成功";
            $return_info = compact('code', 'msg');

            //记录日志
            $operateLogModel = new OperateLogModel();
            $loginUserInfo = $request->user();
            $logData = [
                'table_name' => 'jms_fund_channel',
                'bus_desc' => '编辑资金渠道，资金渠道ID：' . $fund_channel_id,
                'bus_value' => $fund_channel_id,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => '财务模块-财务渠道管理-新增资金渠道',
                'module_id' => 7,
                'have_detail' => 0,
            ];

            $logDetailData["table_name"] = 'operate_log_detail';
            $fund_channel_info = $fund_channel_info[0];
            foreach ($update_fund_channel as $k => $v) {
                if (isset($fund_channel_info[$k]) && $fund_channel_info[$k] != $v) {
                    $logDetailData["update_info"][] = [
                        'table_field_name' => $k,
                        'field_old_value' => $fund_channel_info[$k],
                        'field_new_value' => $v,
                    ];
                }
            }
            if (isset($logDetailData["update_info"])) {
                $operateLogModel->insertMoreLog($logData, $logDetailData);
            }
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-待回款资金管理-待回款资金列表
     * editor:zongxing
     * date: 2018.12.17
     */
    public function waitRefundList(Request $request)
    {
        if ($request->isMethod('get')) {
            $fund_channel_model = new FundChannelModel();
            $wait_refund_list = $fund_channel_model->getFundChannelInfo(null, 3);
            if (empty($wait_refund_list)) {
                return response()->json(['code' => '1002', 'msg' => '暂无待回款信息']);
            }
            $code = "1000";
            $msg = "获取待回款列表成功";
            $data = $wait_refund_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-待回款资金管理-待回款资金需求单列表
     * editor:zongxing
     * date: 2019.01.30
     */
    public function waitRefundDemandList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $deliver_order_model = new DeliverOrderModel();
            $status = 4;
            $deliver_order_list = $deliver_order_model->deliverOrderList($param_info, $status, true);
            if (empty($deliver_order_list)) {
                return response()->json(['code' => '1002', 'msg' => '暂无待回款资金需求单列表']);
            }
            $code = "1000";
            $msg = "获取待回款资金需求单列表成功";
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
     * description:财务模块-待回款资金管理-待回款资金需求详情
     * editor:zongxing
     * date: 2019.01.30
     */
    public function waitRefundDemandDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $deliver_order_sn = $param_info['deliver_order_sn'];
            $deliver_goods_model = new DeliverGoodsModel();
            $deliver_goods_info = $deliver_goods_model->getDeliverGoodsInfo($deliver_order_sn);
            if (empty($deliver_goods_info)) {
                return response()->json(['code' => '1002', 'msg' => '发货单号有误']);
            }
            $code = "1000";
            $msg = "获取待回款资金需求详情成功";
            $data = $deliver_goods_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-需求资金管理-需求资金列表
     * editor:zongxing
     * date: 2018.12.17
     */
    public function demandFundList(Request $request)
    {
        if ($request->isMethod('get')) {
            //获取渠道需求资金
            $fund_channel_model = new FundChannelModel();
            $channel_fund_list = $fund_channel_model->getFundChannelInfo(null, 4);

            //获取采购期待采的需求资金
//            $pcg_model = new PurchaseChannelGoodsModel();
//            $pcg_list = $pcg_model->getPurchseFund();
//            if (empty($channel_fund_list) && empty($pcg_list)) {
//                return response()->json(['code' => '1002', 'msg' => '暂无需求资金']);
//            }

            $param_info = $request->toArray();
            $demand_count_model = new DemandCountModel();
            $demand_count_goods = $demand_count_model->getDemandCountList($param_info);

            //获取采购期预采信息
            $purchase_sn_arr = [];
            foreach ($demand_count_goods as $k => $v) {
                $purchase_sn_arr[] = $k;
            }
            $rpd_model = new RealPurchaseDetailModel();
            $predict_goods_info = $rpd_model->getBatchPredictInfo($purchase_sn_arr);

//            $error_info = '商品:';
//            $purchase_channel_goods = [];
//            foreach ($pcg_list as $k => $v) {
//                $goods_list = [];
//                foreach ($v as $k1 => $v1) {
//                    $spec_sn = $v1['spec_sn'];
//                    if ($v1['brand_discount'] == null) {
//                        $method_name = $v1['method_name'];
//                        $channels_name = $v1['channels_name'];
//                        $error_info .= $spec_sn . ' 在 ' . $method_name . '-' . $channels_name . ',';
//                    }
//                    $goods_list[$spec_sn][] = $v1;
//                }
//                $purchase_channel_goods[$k]['total_goods_num'] = $v[0]['goods_num'];
//                $purchase_channel_goods[$k]['goods_list'] = $goods_list;
//            }
//            if ($error_info !== '商品:') {
//                $error_info = substr($error_info, 0, -1);
//                $error_info .= ' 不存在采购折扣信息,请维护';
//                return response()->json(['code' => '1003', 'msg' => $error_info]);
//            }

//            $purchase_channel_goods_list = [];
//            foreach ($purchase_channel_goods as $k => $v) {
//                $sku_num = count($v['goods_list']);
//                $purchase_channel_goods_list[$k]['purchase_sn'] = $k;
//                $purchase_channel_goods_list[$k]['sku_num'] = $sku_num;
//                $purchase_may_num = 0;
//                $purchase_real_num = 0;
//                $purchase_total_amount = 0;
//                foreach ($v['goods_list'] as $k1 => $v1) {
//                    $goods_may_num = 0;
//                    $goods_real_num = 0;
//                    $goods_total_amount = 0;
//                    foreach ($v1 as $k2 => $v2) {
//                        $goods_may_num += intval($v2['may_num']);
//                        $goods_real_num += intval($v2['real_num']);
//                        $diff_num = intval($v2['diff_num']);
//                        $spec_price = floatval($v2['spec_price']);
//                        $brand_discount = floatval($v2['brand_discount']);
//                        $original_or_discount = intval($v2['original_or_discount']);
//                        if ($original_or_discount == 1) {
//                            $spec_price = $spec_price * $brand_discount;
//                        }
//                        $goods_total_amount += round(($spec_price * $diff_num), 2);
//                    }
//                    $purchase_may_num += $goods_may_num;
//                    $purchase_real_num += $goods_real_num;
//                    if ($goods_total_amount > 0) {
//                        $purchase_total_amount += $goods_total_amount;
//                    }
//                }
//                $purchase_channel_goods_list[$k]['total_goods_num'] = $v['total_goods_num'];
//                $purchase_channel_goods_list[$k]['purchase_may_num'] = $purchase_may_num;
//                $purchase_channel_goods_list[$k]['purchase_real_num'] = $purchase_real_num;
//                $purchase_channel_goods_list[$k]['purchase_total_amount'] = $purchase_total_amount;
//            }
//
//            $purchase_fund_list = [];
//            $sku_num = 0;
//            $purchase_goods_num = 0;
//            $purchase_may_num = 0;
//            $purchase_real_num = 0;
//            $purchase_total_amount = 0;
//            foreach ($purchase_channel_goods_list as $k => $v) {
//                $sku_num += $v['sku_num'];
//                $purchase_goods_num += $v['total_goods_num'];
//                $purchase_may_num += $v['purchase_may_num'];
//                $purchase_real_num += $v['purchase_real_num'];
//                $purchase_total_amount += $v['purchase_total_amount'];
//            }
//            $purchase_fund_list['sku_num'] = $sku_num;
//            $purchase_fund_list['purchase_goods_num'] = $purchase_goods_num;
//            $purchase_fund_list['purchase_may_num'] = $purchase_may_num;
//            $purchase_fund_list['purchase_real_num'] = $purchase_real_num;
//            $purchase_fund_list['purchase_total_amount'] = $purchase_total_amount;

            $purchase_fund_list = $this->createPurchaseFundList($demand_count_goods, $predict_goods_info);
            $code = "1000";
            $msg = "获取需求资金列表成功";
            $data['channel_fund_list'] = $channel_fund_list;

            $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
            $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
            $start_page = ($page - 1) * $page_size;
            $data['purchase_fund_list']['total_num'] = count($purchase_fund_list);
            $purchase_fund_list = array_values(array_slice($purchase_fund_list, $start_page, $page_size));
            $data['purchase_fund_list']['data'] = $purchase_fund_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-需求资金管理-组装采购期需求资金列表
     * editor:zongxing
     * date: 2019.01.31
     */
    public function createPurchaseFundList($demand_count_goods, $predict_goods_info)
    {
        $demand_count_goods_list = [];
        foreach ($demand_count_goods as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $v1['predict_num'] = 0;
                $final_goods_num = $v1['final_goods_num'];
                $real_buy_num = $v1['real_buy_num'];
                $diff_num = $final_goods_num - $real_buy_num;
                $need_price = 0;
                if ($diff_num < 0) {
                    $v1['diff_num'] = 0;
                    $v1['overflow_num'] = abs($diff_num);
                } elseif ($diff_num >= 0) {
                    $v1['diff_num'] = $diff_num;
                    $v1['overflow_num'] = 0;
                    $spec_price = $v1['spec_price'];
                    $need_price = $spec_price * $diff_num;
                }
                $v1['need_price'] = $need_price;
                $demand_count_goods_list[$k][$v1['spec_sn']] = $v1;
            }
        }

        if (!empty($predict_goods_info)) {
            foreach ($predict_goods_info as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    if (isset($demand_count_goods_list[$k][$v1['spec_sn']])) {
                        $demand_count_goods_list[$k][$v1['spec_sn']]['predict_num'] += intval($v1['day_buy_num']);
                        $demand_count_goods_list[$k][$v1['spec_sn']]['final_goods_num'] -= intval($v1['day_buy_num']);
                        $demand_count_goods_list[$k][$v1['spec_sn']]['real_buy_num'] -= intval($v1['day_buy_num']);
                        $final_goods_num = $demand_count_goods_list[$k][$v1['spec_sn']]['final_goods_num'];
                        $real_buy_num = $demand_count_goods_list[$k][$v1['spec_sn']]['real_buy_num'];

                        $diff_num = $final_goods_num - $real_buy_num;
                        $need_price = 0;
                        if ($diff_num < 0) {
                            $demand_count_goods_list[$k][$v1['spec_sn']]['overflow_num'] = abs($diff_num);
                        } elseif ($diff_num >= 0) {
                            $demand_count_goods_list[$k][$v1['spec_sn']]['diff_num'] = $diff_num;
                            $demand_count_goods_list[$k][$v1['spec_sn']]['overflow_num'] = 0;

                            $spec_price = $demand_count_goods_list[$k][$v1['spec_sn']]['spec_price'];
                            $need_price = $spec_price * $diff_num;
                        }
                        $demand_count_goods_list[$k][$v1['spec_sn']]['need_price'] = $need_price;
                    }
                }
            }
        }

        $purchase_fund_list = [];
        foreach ($demand_count_goods_list as $k => $v) {
            $purchase_fund_list[$k]['goods_num'] = 0;
            $purchase_fund_list[$k]['may_buy_num'] = 0;
            $purchase_fund_list[$k]['real_buy_num'] = 0;
            $purchase_fund_list[$k]['predict_num'] = 0;
            $purchase_fund_list[$k]['diff_num'] = 0;
            $purchase_fund_list[$k]['overflow_num'] = 0;
            $purchase_fund_list[$k]['need_price'] = 0;
            $purchase_fund_list[$k]['sku_num'] = count($v);
            foreach ($v as $k1 => $v1) {
                $purchase_fund_list[$k]['goods_num'] += intval($v1['goods_num']);
                $purchase_fund_list[$k]['may_buy_num'] += intval($v1['may_buy_num']);
                $purchase_fund_list[$k]['real_buy_num'] += intval($v1['real_buy_num']);
                $purchase_fund_list[$k]['predict_num'] += intval($v1['predict_num']);
                $purchase_fund_list[$k]['diff_num'] += intval($v1['diff_num']);
                $purchase_fund_list[$k]['overflow_num'] += intval($v1['overflow_num']);
                $purchase_fund_list[$k]['need_price'] += intval($v1['need_price']);
            }
        }
        return $purchase_fund_list;
    }

    /**
     * description:财务模块-需求资金管理-需求单剩余需求资金列表
     * editor:zongxing
     * date: 2018.12.17
     */
    public function demandGoodsFundList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $demand_model = new DemandModel();
            $demand_list = $demand_model->getDdDemandList($param_info);
            if (empty($demand_list['data'])) {
                return response()->json(['code' => '1002', 'msg' => '暂无需求资金信息']);
            }
            $demand_sn_arr = [];
            foreach ($demand_list['data'] as $k => $v) {
                $demand_sn_arr[] = $v['demand_sn'];
            }

            $demand_goods_model = new DemandGoodsModel();
            $demand_goods_info = $demand_goods_model->getDemandGoodsBySn($demand_sn_arr);

            $demand_goods_list = [];
            foreach ($demand_goods_info as $k => $v) {
                $v['wait_num'] = intval($v['goods_num']);
                $v['predict_num'] = 0;
                $demand_goods_list[$v['demand_sn']][$v['spec_sn']] = $v;
            }

            //获取预采数据
            $rpd_model = new RealPurchaseDetailModel();
            $predict_goods_info = $rpd_model->getPredictByDemand($demand_sn_arr);

            //获取采购期需求资金
//            $demand_count_model = new DemandCountModel();
//            $demand_count_info = $demand_count_model->getPurchaseGoodsInfo($demand_sn_arr);
            //获取采购期渠道商品统计表数据
//            $pcg_model = new PurchaseChannelGoodsModel();
//            $pcg_list = $pcg_model->getPurchseFund();

            //获取采购期统计表数据
            $demand_count_model = new DemandCountModel();
            $demand_count_goods = $demand_count_model->getDemandCountList();

            //获取采购期需求表数据
            $purchase_demand_model = new PurchaseDemandModel();
            $purchase_demand_info = $purchase_demand_model->getPurchaseDemandList($demand_sn_arr);

            //进行数据组装
            $reqData = [
                'demand_count_goods' => $demand_count_goods,
                'predict_goods_info' => $predict_goods_info,
                'demand_goods_list' => $demand_goods_list,
                'demand_list' => $demand_list,
                'purchase_demand_info' => $purchase_demand_info,
            ];
            $return_info = $this->createDemandFundList($reqData);
            $code = "1000";
            $msg = "获取需求单需求资金列表成功";
            $data = $return_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-需求资金管理-组装需求单需求资金
     * editor:zongxing
     * date: 2019.01.22
     */
    public function createDemandFundList($reqData)
    {
        $demand_count_goods = $reqData['demand_count_goods'];
        $predict_goods_info = $reqData['predict_goods_info'];
        $demand_goods_list = $reqData['demand_goods_list'];
        $demand_list = $reqData['demand_list'];
        $purchase_demand_info = $reqData['purchase_demand_info'];

        $purchase_demand_list = [];
        foreach ($purchase_demand_info as $k => $v) {
            $purchase_sn = $v['purchase_sn'];
            $demand_sn = $v['demand_sn'];
            if (isset($purchase_demand_list[$purchase_sn])) {
                if (!in_array($demand_sn, $purchase_demand_list[$purchase_sn])) {
                    $purchase_demand_list[$purchase_sn][] = $demand_sn;
                }
            } else {
                $purchase_demand_list[$purchase_sn][] = $demand_sn;
            }
        }

        $demand_count_goods_list = [];
        foreach ($demand_count_goods as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $demand_count_goods_list[$k][$v1['spec_sn']] = $v1;
            }
        }

        if (!empty($predict_goods_info)) {
            foreach ($predict_goods_info as $k => $v) {
                $demand_goods_list[$v['demand_sn']][$v['spec_sn']]['wait_num'] -= intval($v['day_buy_num']);
                $demand_goods_list[$v['demand_sn']][$v['spec_sn']]['predict_num'] += intval($v['day_buy_num']);
                $demand_count_goods_list[$v['purchase_sn']][$v['spec_sn']]['final_goods_num'] -= intval($v['day_buy_num']);
                $demand_count_goods_list[$v['purchase_sn']][$v['spec_sn']]['real_buy_num'] -= intval($v['day_buy_num']);
            }
        }

        foreach ($demand_count_goods_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $spec_sn = trim($v1['spec_sn']);
                $final_goods_num = intval($v1['final_goods_num']);
                $final_real_buy_num = intval($v1['real_buy_num']);
                foreach ($demand_goods_list as $k2 => $v2) {
                    if (in_array($k2, $purchase_demand_list[$k]) && isset($v2[$spec_sn])) {
                        $wait_num = $v2[$spec_sn]['wait_num'];
                        $may_allot_num = $wait_num / $final_goods_num * $final_real_buy_num;
                        $demand_goods_list[$k2][$spec_sn]['may_allot_num'] = $may_allot_num;
                        $diff_num = $wait_num - $may_allot_num;
                        $need_price = 0;
                        if ($diff_num < 0) {
                            $demand_goods_list[$k2][$spec_sn]['diff_num'] = 0;
                            $demand_goods_list[$k2][$spec_sn]['overflow_num'] = abs($diff_num);
                        } elseif ($diff_num >= 0) {
                            $demand_goods_list[$k2][$spec_sn]['diff_num'] = $diff_num;
                            $demand_goods_list[$k2][$spec_sn]['overflow_num'] = 0;

                            $spec_price = $demand_goods_list[$k2][$spec_sn]['spec_price'];
                            $need_price = $spec_price * $diff_num;
                        }
                        $demand_goods_list[$k2][$spec_sn]['need_price'] = $need_price;
                    }
                }
            }
        }

        foreach ($demand_list['data'] as $k => $v) {
            $demand_sn = $v['demand_sn'];
            $demand_info = $demand_goods_list[$demand_sn];
            $sku_num = count($demand_info);
            $demand_list['data'][$k]['sku_num'] = $sku_num;
            $demand_list['data'][$k]['goods_num'] = 0;
            $demand_list['data'][$k]['predict_num'] = 0;
            $demand_list['data'][$k]['may_allot_num'] = 0;
            $demand_list['data'][$k]['diff_num'] = 0;
            $demand_list['data'][$k]['overflow_num'] = 0;
            $demand_list['data'][$k]['need_price'] = 0;
            foreach ($demand_info as $k1 => $v1) {
                $demand_list['data'][$k]['goods_num'] += intval($v1['goods_num']);
                $demand_list['data'][$k]['predict_num'] += intval($v1['predict_num']);
                $demand_list['data'][$k]['may_allot_num'] += intval($v1['may_allot_num']);
                $demand_list['data'][$k]['diff_num'] += intval($v1['diff_num']);
                $demand_list['data'][$k]['overflow_num'] += intval($v1['overflow_num']);
                $demand_list['data'][$k]['need_price'] += floatval($v1['need_price']);
            }
        }
        return $demand_list;
    }

    /**
     * description:财务模块-需求资金管理-需求资金详情列表
     * editor:zongxing
     * date: 2018.12.17
     */
    public function fundListDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $fund_channel_model = new FundChannelModel();
            $demand_fund_list = $fund_channel_model->getFundListDetail($param_info);
            if (isset($demand_fund_list['code'])) {
                return response()->json(['code' => '1002', 'msg' => '暂无需求资金']);
            }
            $code = "1000";
            $msg = "获取采购期需求资金列表成功";
            $data = $demand_fund_list;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-MIS订单管理管理-发货单列表
     * editor:zongxing
     * date: 2018.12.19
     */
    public function financeDeliverOrderList(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            $deliver_order_model = new DeliverOrderModel();
            $status = 3;
            $deliver_order_list = $deliver_order_model->deliverOrderList($param_info, $status);
            if (empty($deliver_order_list['data'])) {
                return response()->json(['code' => '1002', 'msg' => '暂无已发货订单']);
            }
            $code = "1000";
            $msg = "获取已发货单列表成功";
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
     * description:财务模块-MIS订单管理管理-确认收款
     * editor:zongxing
     * date: 2018.12.19
     */
    public function financeChangeOrderStatus(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info['deliver_order_sn'])) {
                return response()->json(['code' => '1002', 'msg' => '发货单单号不能为空']);
            }
            $deliver_order_sn = trim($param_info['deliver_order_sn']);
            $deliver_order_model = new DeliverOrderModel();
            $status = 4;
            $updateRes = $deliver_order_model->changeDeliverStatus($deliver_order_sn, $status);
            $return_info = ['code' => '1003', 'msg' => '确认收款失败'];
            if ($updateRes !== false) {
                $return_info = ['code' => '1000', 'msg' => '确认收款成功'];
            }
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
            $param_info['is_cost'] = 0;
            $task_link = 'alreadyPricing';
            $demandCountModel = new DemandCountModel();
            $purchase_data_list = $demandCountModel->getBatchList($param_info, null, $request, $task_link);
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
     * description:获取采购批次详情
     * editor:zongxing
     * type:POST
     * * params: 1.实采批次单号:real_purchase_sn;
     * date : 2019.01.03
     * return Object
     */
    public function getBatchDetail(Request $request)
    {
        if ($request->isMethod("get")) {
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
                $return_info = ['code' => '1006', 'msg' => '暂无采购数据批次'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:财务模块-核价管理-下载待核价批次数据
     * editor:zongxing
     * date:2019.01.03
     */
    public function downloadPricingBatchDetail(Request $request)
    {
        if ($request->isMethod('get')) {
            $param_info = $request->toArray();
            if (empty($param_info["real_purchase_sn"])) {
                return response()->json(['code' => '1002', 'msg' => '批次单号不能为空']);
            } elseif (empty($param_info['purchase_sn'])) {
                return response()->json(['code' => '1005', 'msg' => '采购期单号不能为空']);
            } elseif (empty($param_info['group_sn'])) {
                return response()->json(['code' => '1006', 'msg' => '批次组合方式不能为空']);
            } elseif (empty($param_info['is_mother'])) {
                return response()->json(['code' => '1007', 'msg' => '批次父子代码不能为空']);
            }

            $real_purchase_detail_model = new RealPurchaseDetailModel();
            $real_purchase_detail = $real_purchase_detail_model->getBatchGoodsDetail($param_info);
            if (empty($real_purchase_detail)) {
                return response()->json(['code' => '1003', 'msg' => '批次单号有误,请检查']);
            }
            $real_purchase_sn = trim($param_info["real_purchase_sn"]);
            $res = $this->exportPricingBatchData($real_purchase_detail, $real_purchase_sn);
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
     * description:财务模块-核价管理-下载待核价批次数据-数据组装
     * editor:zongxing
     * date:2019.01.03
     */
    private function exportPricingBatchData($real_purchase_detail, $real_purchase_sn)
    {
        $title = [['商品名称', '商家编码', '商品规格码', '采购数量', '清点数量', '差异数量', '美金原价', '渠道折扣', '商品重量', '成本价']];
        $goods_list = [];
        foreach ($real_purchase_detail as $k => $v) {
            $goods_list[$k] = [
                $v['goods_name'], $v['erp_merchant_no'], $v['spec_sn'], $v['day_buy_num'], $v['allot_num'],
                $v['diff_num'], $v['spec_price'], $v['channel_discount'], $v['spec_weight'], $v['cost_amount']
            ];
        }

        $goods_list_num = count($goods_list);
        $post_amount = $real_purchase_detail[0]['post_amount'];
        $goods_list[$goods_list_num + 5] = ['运费:', $post_amount];

        //实采单号
        $filename = '核价批次单表_' . $real_purchase_sn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $excuteExcel = new ExcuteExcel();
        return $excuteExcel->exportZ($exportData, $filename);
    }

    /**
     * description:财务模块-核价管理-上传核价批次数据
     * editor:zongxing
     * type:POST
     * date:2019.01.03
     * params:1.需要上传的excel表格文件:upload_file;2.批次单单号:$real_purchase_sn;
     * return Array
     */
    public function doUploadPricingBatch(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            } elseif (empty($param_info['real_purchase_sn'])) {
                return response()->json(['code' => '1005', 'msg' => '批次单单号不能为空']);
            } elseif (empty($param_info['purchase_sn'])) {
                return response()->json(['code' => '1009', 'msg' => '采购期单号不能为空']);
            } elseif (empty($param_info['group_sn'])) {
                return response()->json(['code' => '1010', 'msg' => '批次组合方式不能为空']);
            } elseif (empty($param_info['is_mother'])) {
                return response()->json(['code' => '1011', 'msg' => '批次父子代码不能为空']);
            }
            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '核价批次单表_';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($_FILES, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }
            //检查字段名称
            $arrTitle = ['商品规格码', '成本价'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    return response()->json(['code' => '1006', 'msg' => '您的标题头有误，请按模板导入']);
                }
            }
            //检测批次单是否有误,同时获取批次单中商品的信息
            $real_purchase_sn["real_purchase_sn"] = trim($param_info["real_purchase_sn"]);
            $rpd_model = new RealPurchaseDetailModel();
            $real_purchase_detail = $rpd_model->getBatchGoodsDetail($param_info);
            if (empty($real_purchase_detail)) {
                return response()->json(['code' => '1007', 'msg' => '批次单号有误,请检查']);
            }

            //获取上传商品的规格码数据
            $upload_goods_info = [];
            foreach ($res as $k => $v) {
                if ($k == 0) continue;
                if (!empty($v[2])) {
                    $spec_sn = trim($v[2]);
                    $cost_amount = floatval($v[9]);
                    $upload_goods_info[$spec_sn] = $cost_amount;
                }
            }
            $real_purchase_detail_info = [];
            foreach ($real_purchase_detail as $k => $v) {
                $real_purchase_detail_info[$v["spec_sn"]] = $v;
            }

            //对上传的商品和预采需求单中的而商品进行校验
            $deliver_spec_sn = array_keys($real_purchase_detail_info);
            $upload_spec_sn = array_keys($upload_goods_info);
            $diff_spec_sn = array_diff($upload_spec_sn, $deliver_spec_sn);
            if (!empty($diff_spec_sn)) {
                $return_str = "商品规格码:";
                foreach ($diff_spec_sn as $k => $v) {
                    $return_str .= $v . ",";
                }
                $return_str = substr($return_str, 0, -1);
                $return_str .= " 在批次单中不存在,请检查!";
                return response()->json(['code' => '1008', 'msg' => $return_str]);
            }

            //更新商品的清点数量,同时更新对应客户的最终待回款金额
            $update_real_purchase_detail = $rpd_model->updateBatchCostPrice($upload_goods_info, $param_info);
            $return_info = ['code' => '1008', 'msg' => '上传核价批次数据失败'];
            if ($update_real_purchase_detail !== false) {
                $return_info = ['code' => '1000', 'msg' => '上传核价批次数据成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:改变批次状态
     * editor:zongxing
     * type:POST
     * params: 1.实采批次单号:real_purchase_sn;2.要修改的状态:status;
     * date : 2019.01.03
     * return Object
     */
    public function changeStatus(Request $request)
    {
        if ($request->isMethod("post")) {
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
            //获取要更改的实采单状态
            $status = $batch_info["status"];
            if ($status != 3) {
                return response()->json(['code' => '1006', 'msg' => '您操作的采购需求状态有误，请重新提交']);
            }

            //更新实采批次的状态
            $pricing_time = date("Y-m-d H:i:s");
            $update_info = [
                'status' => $status,
                'pricing_time' => $pricing_time
            ];
            $realPurchaseModel = new RealPurchaseModel();
            $purchase_goods_info = $realPurchaseModel->changeRealPurStatus($request, $batch_info, $update_info);
            $return_info = ['code' => '1007', 'msg' => '更新实采批次的状态失败'];
            if ($purchase_goods_info !== false) {
                $return_info = ['code' => '1000', 'msg' => '更新实采批次的状态成功'];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:提交批次核价
     * editor:zongxing
     * type:POST
     * params: 1.实采批次单号:real_purchase_sn;
     * date : 2019.01.21
     * return Object
     */
    public function doPricingBatch(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '批次单号不能为空']);
        } elseif (empty($param_info['post_amount'])) {
            return response()->json(['code' => '1003', 'msg' => '运费不能为空']);
        }
        //批次校验
        $real_purchase_sn = trim($param_info['real_purchase_sn']);
        $rp_model = new RealPurchaseModel();
        $rp_info = $rp_model->getBatchInfo($real_purchase_sn);
        if (empty($rp_info)) {
            return response()->json(['code' => '1004', 'msg' => '批次单号错误']);
        }
        //批次核价
        $realPurchaseModel = new RealPurchaseDetailModel();
        $cost_res = $realPurchaseModel->changeBatchCost($param_info, $rp_info);
        if (isset($cost_res['code'])) {
            return response()->json($cost_res);
        }
        $return_info = ['code' => '1005', 'msg' => '批次核价失败'];
        if ($cost_res !== false) {
            $return_info = ['code' => '1000', 'msg' => '批次核价成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:新增渠道积分
     * editor:zongxing
     * type:GET
     * date : 2019.04.23
     * return Array
     */
    public function addChannelIntegral()
    {
        $pc_model = new PurchaseChannelModel();
        $purchase_channels_info = $pc_model->getChannelList();
        $return_info = ['code' => '1000', 'msg' => '打开新增渠道积分成功', 'data' => $purchase_channels_info];
        if (empty($purchase_channels_info)) {
            $return_info = ['code' => '1002', 'msg' => '打开新增渠道积分失败'];
        }
        return response()->json($return_info);
    }

    /**
     * description:提交新增渠道积分
     * editor:zongxing
     * type:POST
     * date : 2019.04.23
     * return Array
     */
    public function doAddChannelIntegral(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1002', 'msg' => '渠道id不能为空']);
        } elseif (!isset($param_info['integral_balance'])) {
            return response()->json(['code' => '1003', 'msg' => '渠道积分不能为空']);
        } elseif (!isset($param_info['money_balance'])) {
            return response()->json(['code' => '1004', 'msg' => '渠道余款不能为空']);
        }
        $ci_model = new ChannelsIntegralModel();
        $channel_integral_info = $ci_model->getChannelIntegral($param_info);
        if (!empty($channel_integral_info)) {
            return response()->json(['code' => '1004', 'msg' => '该渠道积分已经存在']);
        }
        $insert_res = $ci_model->doAddChannelIntegral($param_info);
        $return_info = ['code' => '1005', 'msg' => '新增渠道积分失败'];
        if ($insert_res !== false) {
            //获取渠道积分及余款
            $channel_integral_list = $ci_model->getChannelIntegral();
            //新增渠道积分及余款记录表
            $cil_model = new ChannelsIntegralLogModel();
            $add_cil_res = $cil_model->doAddChannelIntegralLog($param_info, $channel_integral_info);
            $return_info = ['code' => '1006', 'msg' => '更新渠道积分余款记录表失败'];
            if ($add_cil_res !== false) {
                $param['channels_id'] = $channel_integral_list[0]['channels_id'];
                $channel_integral_log_list = $cil_model->getChannelIntegralLog($param);
                $data = [
                    'channel_integral_list' => $channel_integral_list,
                    'integral_log' => $channel_integral_log_list['integral_log'],
                    'money_log' => $channel_integral_log_list['money_log'],
                ];
                $return_info = ['code' => '1000', 'msg' => '新增渠道积分成功', 'data' => $data];
            }
        }
        return response()->json($return_info);
    }

    /**
     * description:编辑渠道积分
     * editor:zongxing
     * type:POST
     * date : 2019.04.23
     * return Array
     */
    public function editChannelIntegral(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1002', 'msg' => '渠道id不能为空']);
        }
        $ci_model = new ChannelsIntegralModel();
        $channel_integral_info = $ci_model->getChannelIntegral($param_info);
        if (empty($channel_integral_info)) {
            return response()->json(['code' => '1003', 'msg' => '该渠道积分不存在']);
        }

        $now_time = Carbon::now()->toDateString();
        $modify_time = Carbon::parse($channel_integral_info[0]['modify_time'])->toDateString();
        if ($now_time == $modify_time) {
            return response()->json(['code' => '1004', 'msg' => '该渠道积分今天已经编辑']);
        }
        $return_info = ['code' => '1000', 'msg' => '打开编辑渠道积分成功', 'data' => $channel_integral_info];
        return response()->json($return_info);
    }

    /**
     * description:提交编辑渠道积分
     * editor:zongxing
     * type:POST
     * date : 2019.04.23
     * return Array
     */
    public function doEditChannelIntegral(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1002', 'msg' => '渠道id不能为空']);
        } elseif (!isset($param_info['integral_balance'])) {
            return response()->json(['code' => '1003', 'msg' => '渠道积分不能为空']);
        } elseif (!isset($param_info['money_balance'])) {
            return response()->json(['code' => '1004', 'msg' => '渠道余款不能为空']);
        }
        $ci_model = new ChannelsIntegralModel();
        $channel_integral_info = $ci_model->getChannelIntegral($param_info);
        if (empty($channel_integral_info)) {
            return response()->json(['code' => '1004', 'msg' => '该渠道积分不存在']);
        }
        $update_res = $ci_model->doEditChannelIntegral($param_info);
        $return_info = ['code' => '1002', 'msg' => '编辑渠道积分失败'];
        if ($update_res !== false) {
            $channel_integral_list = $ci_model->getChannelIntegral();

            //新增渠道积分及余款记录表
            $cil_model = new ChannelsIntegralLogModel();
            $add_cil_res = $cil_model->doAddChannelIntegralLog($param_info, $channel_integral_info);
            $return_info = ['code' => '1006', 'msg' => '更新渠道积分余款记录表失败'];
            if ($add_cil_res !== false) {
                $param['channels_id'] = $channel_integral_list[0]['channels_id'];
                $channel_integral_log_list = $cil_model->getChannelIntegralLog($param);
                $data = [
                    'channel_integral_list' => $channel_integral_list,
                    'integral_log' => $channel_integral_log_list['integral_log'],
                    'money_log' => $channel_integral_log_list['money_log'],
                ];
                $return_info = ['code' => '1000', 'msg' => '编辑渠道积分成功', 'data' => $data];
            }
        }
        return response()->json($return_info);
    }

    /**
     * description:渠道积分列表
     * editor:zongxing
     * type:GET
     * date : 2019.04.24
     * return Array
     */
    public function accountList()
    {
        //获取积分余额和渠道余款
        $ci_model = new ChannelsIntegralModel();
        $channel_integral_info = $ci_model->getChannelIntegral();
        if (empty($channel_integral_info)) {
            return response()->json(['code' => '1002', 'msg' => '暂无渠道积分']);
        }

        //获取待返积分
        $rpd_model = new RealPurchaseDetailModel();
        $batch_integral_info = $rpd_model->getBatchIntegralInfo();
        $batch_integral_list = [];
        foreach ($batch_integral_info as $k => $v) {
            $pin_str = $v['channels_name'] . $v['method_name'];
            $integral = floatval($v['total_integral']);
            if (isset($batch_integral_list[$pin_str])) {
                $total_integral = floatval($batch_integral_list[$pin_str]['total_integral']);
                $total_integral += $integral;
                $batch_integral_list[$pin_str]['total_integral'] = number_format($total_integral, 2, '.', '');
            } else {
                $batch_integral_list[$pin_str] = [
                    'channels_name' => $pin_str,
                    'total_integral' => $integral
                ];
            }
        }

        $channel_integral_list = [];
        foreach ($channel_integral_info as $k => $v) {
            $pin_str = $v['channels_name'] . $v['method_name'];
            $total_integral = number_format(0.00, 2, '.', '');
            if (isset($batch_integral_list[$pin_str])) {
                $total_integral = $batch_integral_list[$pin_str]['total_integral'];
                $total_integral = number_format($total_integral, 2, '.', '');
            }
            $v['total_integral'] = $total_integral;
            $channel_integral_list[$pin_str] = $v;
        }
        $channel_integral_list = array_values($channel_integral_list);

        $return_info = ['code' => '1002', 'msg' => '暂无渠道积分'];
        if (!empty($channel_integral_list)) {
            $param['channels_id'] = $channel_integral_list[0]['channels_id'];
            $cil_model = new ChannelsIntegralLogModel();
            $channel_integral_log_list = $cil_model->getChannelIntegralLog($param);
            $data = [
                'channel_integral_list' => $channel_integral_list,
                'integral_log' => $channel_integral_log_list['integral_log'],
                'money_log' => $channel_integral_log_list['money_log'],
            ];
            $return_info = ['code' => '1000', 'msg' => '获取渠道积分成功', 'data' => $data];
        }
        return response()->json($return_info);
    }

    /**
     * description:渠道积分和余款详情
     * editor:zongxing
     * type:GET
     * date : 2019.05.08
     * return Array
     */
    public function accountDetail(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1002', 'msg' => '渠道id不能为空']);
        }
        $cil_model = new ChannelsIntegralLogModel();
        $cil_list = $cil_model->getChannelIntegralLog($param_info);

        if (empty($cil_list['integral_log']) && empty($cil_list['money_log'])) {
            return response()->json(['code' => '1003', 'msg' => '渠道id错误']);
        }
        $data = [
            'integral_log' => $cil_list['integral_log'],
            'money_log' => $cil_list['money_log'],
        ];
        $return_info = ['code' => '1000', 'msg' => '获取渠道积分成功', 'data' => $data];
        return response()->json($return_info);

    }

    /**
     * description:待返积分批次列表
     * editor:zongxing
     * type:GET
     * date : 2019.04.24
     * return Array
     */
    public function waitIntegralList(Request $request)
    {
        $param_info = $request->toArray();
        //获取合单信息
        $rpa_model = new RealPurchaseAuditModel();
        $rpa_info = $rpa_model->getBatchIntegralList($param_info);
        if (empty($rpa_info['batch_list'])) {
            return response()->json(['code' => '1001', 'msg' => '暂无采购数据批次']);
        }
        $total_num = $rpa_info['total_num'];
        $purchase_sn_arr = $rpa_info['batch_list'];

        //获取合单对应的批次信息
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $batch_integral_info = $rpda_model->getBatchIntegralDetail($purchase_sn_arr);

        //组装数据
        $batch_total_list = [];
        foreach ($batch_integral_info as $k => $v) {
            $purchase_sn = $v['purchase_sn'];
            $title_name = $v['title_name'];
            if (!isset($batch_total_list[$purchase_sn])) {
                $batch_total_list[$purchase_sn]['title_info'] = [
                    'purchase_sn' => $purchase_sn,
                    'title_name' => $title_name,
                ];
            }
            $v['total_integral'] = number_format($v['total_integral'], 2, '.', '');
            $batch_total_list[$purchase_sn]['batch_list'][] = $v;
        }
        $data = [
            'total_num' => $total_num,
            'batch_total_list' => array_values($batch_total_list),
        ];
        $return_info = ['code' => '1000', 'msg' => '获取待返积分批次列表成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:待返积分批次详情
     * editor:zongxing
     * type:GET
     * date : 2019.04.24
     * return Array
     */
    public function waitIntegralDetail(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['real_purchase_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '批次单号不能为空']);
        } elseif (empty($param_info['purchase_sn'])) {
            return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
        }
        $rpd_model = new RealPurchaseDetailModel();
        $batch_detail_info = $rpd_model->getBatchDetailIntegralInfo($param_info);
        $return_info = ['code' => '1000', 'msg' => '获取渠道积分成功', 'data' => $batch_detail_info];
        if (empty($batch_detail_info)) {
            $return_info = ['code' => '1004', 'msg' => '批次单号错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:确认提交积分
     * editor:zongxing
     * type:GET
     * date : 2019.04.24
     * return Array
     */
    public function submitIntegral(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['channels_method_sn'])) {
            return response()->json(['code' => '1002', 'msg' => '批次代码不能为空']);
        } elseif (empty($param_info['purchase_sn'])) {
            return response()->json(['code' => '1003', 'msg' => '采购期单号不能为空']);
        }
        $rpa_model = new RealPurchaseAuditModel();
        $update_info = $rpa_model->submitIntegral($param_info);
        $return_info = ['code' => '1004', 'msg' => '确认提交积分失败'];
        if ($update_info !== false) {
            $return_info = ['code' => '1000', 'msg' => '确认提交积分成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:待返积分详情
     * editor:zongxing
     * type:GET
     * date : 2019.04.24
     * return Array
     */
    public function waitIntegralInfo(Request $request)
    {
        $rpd_model = new RealPurchaseDetailModel();
        $batch_integral_info = $rpd_model->getBatchIntegralInfo();
        $batch_integral_list = [];
        foreach ($batch_integral_info as $k => $v) {
            $pin_str = $v['channels_name'] . $v['method_name'];
            $integral = floatval($v['total_integral']);
            if (isset($batch_integral_list[$pin_str])) {
                $total_integral = floatval($batch_integral_list[$pin_str]['total_integral']);
                $total_integral += $integral;
                $batch_integral_list[$pin_str]['total_integral'] = number_format($total_integral, 2, '.', '');
            } else {
                $batch_integral_list[$pin_str] = [
                    'channels_name' => $pin_str,
                    'total_integral' => $integral
                ];
            }
        }
        $batch_integral_list = array_values($batch_integral_list);
        $return_info = ['code' => '1000', 'msg' => '获取渠道积分成功', 'data' => $batch_integral_list];
        if (empty($batch_integral_list)) {
            $return_info = ['code' => '1002', 'msg' => '批次单号错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:查看渠道统计情况
     * editor:zongxing
     * type:GET
     * date : 2019.04.30
     * return Array
     */
    public function ChannelsStatisticsInfo(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1002', 'msg' => '渠道id不能为空']);
        }
        $cil_model = new ChannelsIntegralLogModel();
        $param['channels_id'] = intval($param_info['channels_id']);
        $channel_integral_log_list = $cil_model->getChannelIntegralLog($param);
        $data = [
            'integral_log' => $channel_integral_log_list['integral_log'],
            'money_log' => $channel_integral_log_list['money_log'],
        ];
        $return_info = ['code' => '1000', 'msg' => '获取渠道统计信息成功', 'data' => $data];
        if (empty($channel_integral_log_list)) {
            $return_info = ['code' => '1003', 'msg' => '暂无该渠道统计信息'];
        }
        return response()->json($return_info);
    }

    /**
     * description:批次核价列表
     * author zhangdong
     * date : 2019.07.11
     */
    public function batchCorePriceList(Request $request)
    {
        $reqParams = $request->toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $rpaModel = new RealPurchaseAuditModel();
        //获取提货日，此处要将批次信息组装成一个提货日对应多个批次单的数据结构
        $queryRes = $rpaModel->getBatchDeliveryInfo($reqParams, $pageSize);
        //根据提货日组装批次单数据
        $batchCorePriceList = $rpaModel->makeDateByDelivery($queryRes);
        $returnMsg = [
            'batchCorePriceList' => $batchCorePriceList
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:汇率列表
     * editor:zongxing
     * type:GET
     * date : 2019.07.23
     * return Array
     */
    public function exchangeRateList(Request $request)
    {
        $param_info = $request->toArray();
        $er_model = new exchangeRateModel();
        $er_info = $er_model->exchangeRateList($param_info, 1);
        $return_info = ['code' => '1000', 'msg' => '获取汇率列表成功', 'data' => $er_info];
        if (empty($er_info)) {
            $return_info = ['code' => '1002', 'msg' => '暂无汇率列表'];
        }
        return response()->json($return_info);
    }

    /**
     * description:上传汇率
     * editor:zongxing
     * type:GET
     * date : 2019.07.23
     * return Array
     */
    public function uploadExchangeRate(Request $request)
    {
        //检查上传文件是否合格
        $file = $_FILES;
        $fileName = '核价汇率维护';
        $excuteExcel = new ExcuteExcel();
        $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['日期', '美金对人民币汇率', '美金对韩币汇率', '韩币对人民币汇率'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                return response()->json(['code' => '1006', 'msg' => '您的标题头有误，请按模板导入']);
            }
        }
        $er_model = new exchangeRateModel();
        $upload_res = $er_model->uploadExchangeRate($res);
        if (isset($upload_res['code'])) {
            return response()->json($upload_res);
        }
        $return_info = ['code' => '1002', 'msg' => '上传汇率失败'];
        if ($upload_res != false) {
            $er_model = new exchangeRateModel();
            $er_info = $er_model->exchangeRateList();
            $return_info = ['code' => '1000', 'msg' => '上传汇率成功', 'data' => $er_info];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取折扣类型和毛利公式列表
     * author:zongxing
     * type:GET
     * date:2019.05.05
     */
    public function discountTypeList()
    {
        //获取折扣类型列表
        $param_info['method_id'] = '34';
        $dti_model = new DiscountTypeInfoModel();
        $discount_type_list = $dti_model->getDiscountTypeList($param_info);
        if (empty($discount_type_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无折扣类型']);
        }
        $type_list = [];
        foreach ($discount_type_list as $k => $v) {
            $type_cat = intval($v['type_cat']);
            if (!in_array($type_cat, [2, 12, 14])) continue;//2,表示品牌档位折扣;12,表示商品档位折扣;14,表示档位追加(新罗)
            $channels_name = $v['channels_name'];
            if (!isset($type_list[$channels_name])) {
                $type_list[$channels_name] = [
                    'channels_id' => $v['channels_id'],
                    'channels_name' => $channels_name,
                ];
            }
            $type_str = $type_cat == 12 ? 'goods_gears' : 'brand_gears';
            $type_list[$channels_name][$type_str][] = $v;
        }

        $param['is_profit'] = 1;
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param);
        if (empty($dc_list)) {
            return response()->json(['code' => '1003', 'msg' => '暂无折扣类型种类信息']);
        }
        $pf_model = new ProfitFormulaModel();
        $pf_list = $pf_model->getProfitFormulaList(null);
        if (empty($dc_list)) {
            return response()->json(['code' => '1004', 'msg' => '暂无毛利计算公式信息']);
        }
        foreach ($pf_list as $k => $v) {
            $cat_info = explode(',', $v['cat_info']);
            $pf_list[$k]['formula'] = '';
            foreach ($dc_list as $k1 => $v1) {
                $dc_id = $v1['id'];
                $cat_name = $v1['cat_name'];
                if (in_array($dc_id, $cat_info)) {
                    $pf_list[$k]['formula'] .= $cat_name . ' + ';
                }
            }
            $pf_list[$k]['formula'] = substr($pf_list[$k]['formula'], 0, -3);
        }

        $data = [
            'type_list' => array_values($type_list),
            'pf_list' => $pf_list,
        ];
        $return_info = ['code' => '1000', 'msg' => '获取折扣类型列表成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:生成毛利数据
     * author zhangdong
     * date : 2019.07.18
     * modify zongxing 2019.07.25
     */
    public function generateProfit(Request $request)
    {
        //参数校验
        $param_info = $request->toArray();
        ParamsCheckSingle::paramsCheck()->generateProfitParams($param_info);

        //通过结算日期类型，结算日期，渠道id获取相关批次信息
        $rpaModel = new RealPurchaseAuditModel();
        $profitGoodsInfo = $rpaModel->getProfitGoods($param_info);
        if (empty($profitGoodsInfo)) {
            return response()->json(['code' => '1001', 'msg' => '您选择的月份中该渠道暂无采购数据']);
        }
        //生成毛利数据-计算各个追加点
        $pgModel = new ProfitGoodsModel();
        $res = $pgModel->generalProfit($profitGoodsInfo, $param_info);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        $returnMsg = ['code' => '1002', 'msg' => '生成毛利数据失败'];
        if ($res != false) {
            $returnMsg = ['code' => '1000', 'msg' => '生成毛利数据成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description 获取折扣类型列表
     * author zongxing
     * date 2019.07.31
     */
    public function getDiscountCatList(Request $request)
    {
        $param_info = $request->toArray();
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param_info);
        if (empty($dc_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无折扣类型信息']);
        }
        $returnMsg = ['code' => '1000', 'msg' => '获取折扣类型列表成功', 'data' => $dc_list];
        return response()->json($returnMsg);
    }

    /**
     * description 新增毛利公式
     * author zongxing
     * date 2019.07.31
     */
    public function doAddProfitFormula(Request $request)
    {
        $param_info = $request->toArray();
        $pf_model = new ProfitFormulaModel();
        $dc_res = $pf_model->doAddProfitFormula($param_info);
        $returnMsg = ['code' => '1002', 'msg' => '新增毛利公式失败'];
        if ($dc_res != false) {
            $returnMsg = ['code' => '1000', 'msg' => '新增毛利公式成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description 获取毛利公式列表
     * author zongxing
     * date 2019.07.31
     */
    public function getProfitFormulaList(Request $request)
    {
        $param_info = $request->toArray();
        $param['is_profit'] = 1;
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param);
        $pf_model = new ProfitFormulaModel();
        $pf_list = $pf_model->getProfitFormulaList($param_info);
        foreach ($pf_list as $k => $v) {
            $cat_info = explode(',', $v['cat_info']);
            $pf_list[$k]['formula'] = '';
            foreach ($dc_list as $k1 => $v1) {
                $dc_id = $v1['id'];
                $cat_name = $v1['cat_name'];
                if (in_array($dc_id, $cat_info)) {
                    $pf_list[$k]['formula'] .= $cat_name . ' + ';
                }
            }
            $pf_list[$k]['formula'] = substr($pf_list[$k]['formula'], 0, -3);
        }
        if (empty($pf_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无毛利公式信息']);
        }
        $returnMsg = ['code' => '1000', 'msg' => '获取毛利公式列表成功', 'data' => $pf_list];
        return response()->json($returnMsg);
    }

    /**
     * description 打开新增折扣种类公式页面
     * author zongxing
     * date 2019.08.07
     */
    public function addCatFormula(Request $request)
    {
        $param_info = $request->toArray();
        //获取折扣类型
        $param_info['is_profit'] = 1;
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param_info);
        if (empty($dc_list)) {
            return response()->json(['code' => '1001', 'msg' => '暂无折扣种类信息']);
        }
        //获取渠道信息
        $pc_model = new PurchaseChannelModel();
        $pc_info = $pc_model->getChannelList(null, 34);
        if (empty($pc_info)) {
            return response()->json(['code' => '1002', 'msg' => '暂无渠道信息']);
        }
        //获取毛利公式参数信息
        $pp_model = new ProfitParamModel();
        $pp_info = $pp_model->getProfitParamList();
        if (empty($pp_info)) {
            return response()->json(['code' => '1003', 'msg' => '暂无毛利公式参数信息']);
        }
        $add_arr = [1, '+', '-', '*', '/', '(', ')'];
        $total_add_arr = [];
        foreach ($add_arr as $k => $v) {
            $total_add_arr[] = [
                'param_name' => $v,
                'param_code' => $v
            ];
        }
        $pp_info = array_merge($pp_info, $total_add_arr);
        $data = [
            'dc_list' => $dc_list,
            'pc_info' => $pc_info,
            'pp_info' => $pp_info,
        ];
        $returnMsg = ['code' => '1000', 'msg' => '新增折扣种类公式成功', 'data' => $data];
        return response()->json($returnMsg);
    }

    /**
     * description 新增折扣种类公式
     * author zongxing
     * date 2019.08.07
     */
    public function doAddCatFormula(Request $request)
    {
        $param_info = $request->toArray();
        if (!isset($param_info['cat_code'])) {
            return response()->json(['code' => '1001', 'msg' => '折扣种类代码不能为空']);
        } elseif (!isset($param_info['method_id'])) {
            return response()->json(['code' => '1002', 'msg' => '方式id不能为空']);
        } elseif (!isset($param_info['channels_id'])) {
            return response()->json(['code' => '1003', 'msg' => '渠道id不能为空']);
        } elseif (!isset($param_info['param_code_info'])) {
            return response()->json(['code' => '1004', 'msg' => '折扣种类公式不能为空']);
        }
        $cf_model = new CatFormulaModel();
        $cf_res = $cf_model->doAddCatFormula($param_info);
        $returnMsg = ['code' => '1005', 'msg' => '新增折扣种类公式失败'];
        if ($cf_res != false) {
            $returnMsg = ['code' => '1000', 'msg' => '新增折扣种类公式成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description 获取折扣种类公式列表
     * author zongxing
     * date 2019.08.08
     */
    public function getCatFormulaList(Request $request)
    {
        $param_info = $request->toArray();
        $pp_model = new ProfitParamModel();
        $pp_list = $pp_model->getProfitParamList();
        if (empty($pp_list)) {
            return response()->json(['code' => '1001', 'msg' => '类型公式参数不存在']);
        }
        $cf_model = new CatFormulaModel();
        $cf_list = $cf_model->getCatFormulaList($param_info);
        if (empty($cf_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无种类公式信息']);
        }
        foreach ($cf_list as $k => $v) {
            $param_code_info = str_replace('/', '', $v['param_code_info']);
            foreach ($pp_list as $k1 => $v1) {
                $param_code = $v1['param_code'];
                $param_name = $v1['param_name'];
                if (strpos($param_code_info, $param_code) !== false) {
                    $param_code_info = str_replace($param_code, $param_name, $param_code_info);
                }
            }
            $cf_list[$k]['param_code_info'] = $param_code_info;
        }
        $returnMsg = ['code' => '1000', 'msg' => '获取折扣种类公式列表成功', 'data' => $cf_list];
        return response()->json($returnMsg);
    }

    /**
     * description 毛利数据列表
     * author zongxing
     * date 2019.07.31
     */
    public function profitList(Request $request)
    {
        $param_info = $request->toArray();
        $pg_model = new ProfitGoodsModel();
        $profit_list = $pg_model->profitList($param_info);
        if (empty($profit_list['profit_list']['data'])) {
            return response()->json(['code' => '1002', 'msg' => '暂无毛利数据列表']);
        }
        $returnMsg = ['code' => '1000', 'msg' => '获取毛利数据列表成功', 'data' => $profit_list];
        return response()->json($returnMsg);
    }

    /**
     * description 毛利数据详情
     * author zongxing
     * date 2019.08.01
     * params 1.毛利单号:profit_sn_arr;
     */
    public function profitDetail(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['profit_sn_arr'])) {
            return response()->json(['code' => '1001', 'msg' => '毛利单号不能为空']);
        }
        //获取毛利数据详情
        $profit_sn_arr = $param_info['profit_sn_arr'];
        //$profit_sn_arr = json_decode($profit_sn_arr, true);
        $pg_model = new ProfitGoodsModel();
        $profit_detail = $pg_model->profitDetail($profit_sn_arr);
        if (empty($profit_detail['profit_detail'])) {
            return response()->json(['code' => '1002', 'msg' => '毛利单号错误']);
        }
        $returnMsg = ['code' => '1000', 'msg' => '获取毛利数据详情成功', 'data' => $profit_detail];
        return response()->json($returnMsg);
    }

    /**
     * description 下载毛利数据
     * author zongxing
     * type GET
     * date 2019.08.02
     * params 1.毛利单号:profit_sn_arr;
     * return excel
     */
    public function downLoadProfitInfo(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['profit_sn_arr'])) {
            return response()->json(['code' => '1001', 'msg' => '毛利单号不能为空']);
        }

        $profit_sn_arr = explode(',', $param_info['profit_sn_arr']);
        //$profit_sn_arr = json_decode($profit_sn_arr, true);
        //获取毛利数据公式信息
        $pf_model = new ProfitFormulaModel();
        $pf_info = $pf_model->getProfitFormulaInfo($profit_sn_arr);
        if (empty($pf_info)) {
            return response()->json(['code' => '1002', 'msg' => '毛利单号错误']);
        }

        //获取折扣类型种类信息
        $param['is_profit'] = 1;
        $dc_model = new DiscountCatModel();
        $dc_list = $dc_model->getDiscountCatList($param);
        $profit_field = [];
        foreach ($pf_info as $k => $v) {
            $cat_info = explode(',', $v['cat_info']);
            foreach ($dc_list as $k1 => $v1) {
                $dc_id = $v1['id'];
                $cat_code = $v1['cat_code'];
                if (in_array($dc_id, $cat_info) && !in_array($cat_code, $profit_field)) {
                    $profit_field[] = $cat_code;
                }
            }
        }
        //获取毛利数据详情
        $pg_model = new ProfitGoodsModel();
        $profit_detail = $pg_model->profitDetail($profit_sn_arr);
        if (empty($profit_detail['profit_detail'])) {
            return response()->json(['code' => '1003', 'msg' => '毛利单号错误']);
        }
        $profit_detail = $profit_detail['profit_detail'];

        $obpe = new PHPExcel();
        $obpe->setActiveSheetIndex(0);
        //设置采购渠道及列宽
        $obpe->getActiveSheet()->setCellValue('A1', '毛利单号')->getColumnDimension('A')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('B1', '批次单号')->getColumnDimension('B')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('C1', '渠道名称')->getColumnDimension('C')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('D1', '商品名称')->getColumnDimension('D')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('E1', '商品规格码')->getColumnDimension('E')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('F1', '商家编码')->getColumnDimension('F')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('G1', '商品参考码')->getColumnDimension('G')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('H1', '美金原价')->getColumnDimension('H')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('I1', 'Livp价')->getColumnDimension('I')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('J1', '渠道结算方式')->getColumnDimension('J')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('K1', '实采数')->getColumnDimension('J')->setWidth(15);

        $dc_key_list = [];
        foreach ($dc_list as $k => $v) {
            $cat_code = $v['cat_code'];
            $dc_key_list[$cat_code] = $v;
        }

        //返点列赋值
        $profit_key = array_keys($profit_detail[0]);
        $key_i = 12;
        foreach ($profit_key as $k => $v) {
            if (!in_array($v, $profit_field)) continue;
            $column_name = \PHPExcel_Cell::stringFromColumnIndex($key_i - 1);
            $column_value = $dc_key_list[$v]['cat_name'];
            $obpe->getActiveSheet()->setCellValue($column_name . '1', $column_value)
                ->getColumnDimension($column_name)->setWidth(15);
            $key_i++;
        }

        $row_total_i = count($profit_detail) + 1;
        for ($i = 0; $i < $row_total_i; $i++) {
            if ($i == 0) continue;
            $row_i = $i + 1;
            $real_i = $i - 1;

            $day_buy_num = intval($profit_detail[$real_i]['day_buy_num']);
            $spec_price = floatval($profit_detail[$real_i]['spec_price']);
            $lvip_price = floatval($profit_detail[$real_i]['lvip_price']);
            $margin_payment = trim($profit_detail[$real_i]['margin_payment']);
            $obpe->getActiveSheet()->setCellValue('A' . $row_i, $profit_detail[$real_i]['profit_sn']);
            $obpe->getActiveSheet()->setCellValue('B' . $row_i, $profit_detail[$real_i]['real_purchase_sn']);
            $obpe->getActiveSheet()->setCellValue('C' . $row_i, $profit_detail[$real_i]['channels_name']);
            $obpe->getActiveSheet()->setCellValue('D' . $row_i, $profit_detail[$real_i]['goods_name']);
            $obpe->getActiveSheet()->setCellValue('E' . $row_i, $profit_detail[$real_i]['spec_sn']);
            $obpe->getActiveSheet()->setCellValue('F' . $row_i, $profit_detail[$real_i]['erp_merchant_no']);
            $obpe->getActiveSheet()->setCellValue('G' . $row_i, $profit_detail[$real_i]['erp_ref_no']);
            $obpe->getActiveSheet()->setCellValue('H' . $row_i, $spec_price);
            $obpe->getActiveSheet()->setCellValue('I' . $row_i, $lvip_price);
            $obpe->getActiveSheet()->setCellValue('J' . $row_i, $margin_payment);
            $obpe->getActiveSheet()->setCellValue('K' . $row_i, $day_buy_num);

            $key_i = 12;
            foreach ($profit_key as $m => $n) {
                if (!in_array($n, $profit_field)) continue;
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($key_i - 1);
                $column_value = floatval($profit_detail[$real_i][$n]);
                $obpe->getActiveSheet()->setCellValue($column_name . $row_i, $column_value);
                $key_i++;
            }
        }

        //改变表格标题样式
        $currentSheet = $obpe->getActiveSheet();
        $column_last_name = $currentSheet->getHighestColumn();
        $currentSheet->getStyle('A1:' . $column_last_name . '1')->getAlignment()->setWrapText(TRUE);//字符串自动换行
        $row_last_num = $currentSheet->getHighestRow();
        $commonModel = new CommonModel();
        $commonModel->changeTableTitle($obpe, 'A', 1, $column_last_name, 1);
        $commonModel->changeTableContent($obpe, 'A', 2, $column_last_name, $row_last_num);
        $currentSheet->setTitle('毛利数据');

        //清除缓存
        ob_end_clean();
        //写入内容

        $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel2007');

        $str = rand(1000, 9999);
        $filename = '毛利数据';
        $time = date('Y_m_d');
        $filename = $filename . '_' . $time . '_' . $str . '.xls';
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
        return response()->json($return_info);
    }

    /**
     * description 新增折扣类型种类
     * author zongxing
     * date 2019.08.02
     */
    public function doAddDiscountCat(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['cat_name'])) {
            return response()->json(['code' => '1001', 'msg' => '折扣类型种类名称不能为空']);
        } elseif (empty($param_info['cat_code'])) {
            return response()->json(['code' => '1002', 'msg' => '折扣类型种类代码不能为空']);
        } elseif (!isset($param_info['is_profit'])) {
            return response()->json(['code' => '1003', 'msg' => '是否参与毛利计算不能为空']);
        }
        $dc_model = new DiscountCatModel();
        $dc_info = $dc_model->getDiscountCatInfo($param_info);
        if (!empty($dc_info)) {
            return response()->json(['code' => '1004', 'msg' => '该折扣类型种类代码已经存在']);
        }
        $res = $dc_model->doAddDiscountCat($param_info);
        $return_info = ['code' => '1005', 'msg' => '新增折扣类型种类失败'];
        if ($res != false) {
            $return_info = ['code' => '1000', 'msg' => '新增折扣类型种类成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description 停用毛利数据
     * author zongxing
     * date 2019.08.05
     */
    public function deleteProfitInfo(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['profit_sn'])) {
            return response()->json(['code' => '1001', 'msg' => '毛利单号不能为空']);
        }
        $p_model = new ProfitModel();
        $profit_info = $p_model->profitInfo($param_info);
        if (empty($profit_info)) {
            return response()->json(['code' => '1002', 'msg' => '毛利单号错误']);
        }

        $del_res = $p_model->deleteProfitInfo($param_info);
        $return_info = ['code' => '1003', 'msg' => '停用毛利数据失败'];
        if ($del_res != false) {
            $return_info = ['code' => '1000', 'msg' => '停用毛利数据成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description 采购金额列表
     * author zongxing
     * date 2019.08.13
     */
    public function getCardConsumeList(Request $request)
    {
        $param_info = $request->toArray();
        $cc_model = new CardConsumeModel();
        $cc_list = $cc_model->getCardConsumeList($param_info);
        if (empty($cc_list)) {
            return response()->json(['code' => '1001', 'msg' => '暂无采购金额列表信息']);
        }
        $return_info = ['code' => '1000', 'msg' => '获取采购金额列表成功', 'data' => $cc_list];
        return response()->json($return_info);
    }

    /**
     * description 打开维护结账卡消费记页面
     * author zongxing
     * date 2019.08.13
     */
    public function addCardConsume()
    {
        //获取渠道信息
        $pc_model = new PurchaseChannelModel();
        $pc_info = $pc_model->getChannelList(null, 34);
        if (empty($pc_info)) {
            return response()->json(['code' => '1001', 'msg' => '暂无渠道信息']);
        }
        $return_info = ['code' => '1000', 'msg' => '打开维护采购金额页面成功', 'data' => $pc_info];
        return response()->json($return_info);
    }

    /**
     * description 维护采购金额
     * author zongxing
     * date 2019.08.13
     */
    public function doAddCardConsume(Request $request)
    {
        $param_info = $request->toArray();
        if (!isset($param_info['method_id'])) {
            return response()->json(['code' => '1001', 'msg' => '方式id不能为空']);
        } elseif (!isset($param_info['channels_id'])) {
            return response()->json(['code' => '1002', 'msg' => '渠道id不能为空']);
        } elseif (!isset($param_info['consume_money'])) {
            return response()->json(['code' => '1003', 'msg' => '消费金额不能为空']);
        } elseif (!isset($param_info['day_time'])) {
            return response()->json(['code' => '1004', 'msg' => '消费日期不能为空']);
        } elseif (!isset($param_info['consume_type'])) {
            return response()->json(['code' => '1004', 'msg' => '消费类型不能为空']);
        }
        $cc_model = new CardConsumeModel();
        $cc_res = $cc_model->doAddCardConsume($param_info);
        $return_info = ['code' => '1005', 'msg' => '维护采购金额失败'];
        if ($cc_res != false) {
            $return_info = ['code' => '1000', 'msg' => '维护采购金额成功'];
        }
        return response()->json($return_info);
    }


}//end of class