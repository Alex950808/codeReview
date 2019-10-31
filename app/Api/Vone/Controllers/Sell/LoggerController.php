<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use App\Model\Vone\OperateLogDetailModel;
use App\Model\Vone\OperateLogModel;
use App\Model\Vone\VersionLogModel;
use Dingo\Api\Http\Request;
use App\Modules\ParamsCheckSingle;

//create by zhangdong on the 2019.03.27
class LoggerController extends BaseController
{
    /**
     * description:获取日志列表
     * author:zhangdong
     * date : 2019.03.27
     */
    public function getLoggerList(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $olModel = new OperateLogModel();
        $loggerList = $olModel -> queryLoggerList($reqParams, $pageSize);
        $returnMsg = [
            'loggerList' => $loggerList,
        ];
        return response() ->json($returnMsg);
    }//end of function


    /**
     * description:获取日志详情
     * author:zhangdong
     * date : 2019.03.27
     */
    public function getLoggerDetail(Request $request)
    {
        $reqParams = $request -> toArray();
        $log_id = isset($reqParams['log_id']) ? trim($reqParams['log_id']) : '';
        if (empty($log_id)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //查询日志主信息
        $olModel = new OperateLogModel();
        $loggerInfo = $olModel -> queryLoggerInfo($log_id);
        //查询日志详情
        $oldModel = new OperateLogDetailModel();
        $logDetail = $oldModel->getLogDetail($log_id);
        $returnMsg = [
            'loggerInfo' => $loggerInfo,
            'logDetail' => $logDetail,
        ];
        return response() ->json($returnMsg);

    }

    /**
     * description:获取版本日志记录列表
     * author:zhangdong
     * date : 2019.06.20
     */
    public function getVersionList(Request $request)
    {
        $reqParams = $request -> toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $vlModel = new VersionLogModel();
        $logList = $vlModel -> queryVersionLogList($reqParams, $pageSize);
        $returnMsg = [
            'logList' => $logList,
        ];
        return response() ->json($returnMsg);

    }

    /**
     * description:新增版本日志记录
     * author:zhangdong
     * date : 2019.06.20
     */
    public function addVersionLog(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->addVersionLogParams($reqParams);
        $vlModel = new VersionLogModel();
        //根据版本号检查该版本是否已经添加过，避免重复添加
        $serialNum = trim($reqParams['serial_num']);
        $countNum = $vlModel->countVersionNum($serialNum);
        if ($countNum > 0) {
            return response()->json(['code' => '2067','msg' => '该版本号已经发布，请勿重复发布']);
        }
        $addRes = $vlModel->addVersionLog($reqParams);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($addRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }

    /**
     * description:编辑版本日志记录
     * author:zhangdong
     * date : 2019.06.21
     */
    public function editVersionLog(Request $request)
    {
        $reqParams = $request -> toArray();
        ParamsCheckSingle::paramsCheck()->editVersionLogParams($reqParams);
        $vlModel = new VersionLogModel();
        $logId = intval($reqParams['log_id']);
        //检查logId是否存在
        $countRes = $vlModel->countLogId($logId);
        if ($countRes == 0) {
            return response()->json(['code' => '2067','msg' => '该条日志不存在']);
        }
        $editRes = $vlModel->editData($logId, $reqParams);
        $returnMsg = ['code' => '2023','msg' => '操作失败'];
        if($editRes){
            $returnMsg = ['code' => '2024','msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    








}//end of class