<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\TargetModel;
use Dingo\Api\Http\Request;

//create by zhangdong on the 2019.10.28
class SystemController extends BaseController
{
    /**
     * description 获取目标列表
     * author zongxing
     * type GET
     * date 2019.10.28
     * return json
     */
    public function targetList(Request $request)
    {
        $target_model = new TargetModel();
        $target_list = $target_model->targetList();
        return response()->json(['code' => '1000', 'msg' => '获取目标列表成功', 'data' => $target_list]);
    }

    /**
     * description 新增目标
     * author zongxing
     * type GET
     * date 2019.10.28
     * params 1.目标名称:target_name;2.目标内容:target_content;
     * return json
     */
    public function addTarget(Request $request)
    {
        $param_info = $request->toArray();
        $target_model = new TargetModel();
        $target_list = $target_model->targetList($param_info);
        if (!empty($target_list)) {
            return response()->json(['code' => '1001', 'msg' => '该目标已经存在']);
        }
        $res = $target_model->addTarget($param_info);
        $return_info = ['code' => '1002', 'msg' => '新增目标失败'];
        if ($res != false) {
            $return_info = ['code' => '1000', 'msg' => '新增目标成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description 编辑目标
     * author zongxing
     * type GET
     * date 2019.10.28
     * params 1.目标名称:target_name;2.目标内容:target_content;2.目标id:id;
     * return json
     */
    public function editTarget(Request $request)
    {
        $param_info = $request->toArray();
        $target_model = new TargetModel();
        //判断目标是否存在
        $param['id'] = intval($param_info['id']);
        $target_list = $target_model->targetList($param);
        if (empty($target_list)) {
            return response()->json(['code' => '1001', 'msg' => '该目标不存在']);
        }
        //判断编辑的目标名称是否存在
        $param['target_name'] = trim($param_info['target_name']);
        $target_list = $target_model->targetList($param);
        if (!empty($target_list) && $target_list[0]['id'] != $param['id']) {
            return response()->json(['code' => '1002', 'msg' => '该目标已经存在']);
        }
        $res = $target_model->editTarget($param_info);
        $return_info = ['code' => '1003', 'msg' => '编辑目标失败'];
        if ($res == true) {
            $return_info = ['code' => '1000', 'msg' => '编辑目标成功'];
        }
        return response()->json($return_info);
    }


}//end of class
