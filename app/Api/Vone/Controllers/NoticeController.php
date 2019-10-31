<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\NoticeModel;
use Dingo\Api\Contract\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticeController extends BaseController
{

    /**
     * description:新增公告
     * editor:zongxing
     * type:POST
     * date : 2018.09.04
     * params: 1.公告日期:notice_date;2.公告时间:notice_time;3.公告内容:notice_content;
     * return Object
     */
    public function addNotice(Request $request)
    {
        if ($request->isMethod('post')) {
            $notice_info = $request->toArray();

            if (empty($notice_info['notice_content'])) {
                return response()->json(['code' => '1004', 'msg' => '公告内容不能为空']);
            }

            //计算公告编号
            $model_obj = new NoticeModel();
            $model_field = "notice_sn";
            $pin_head = "GG-";
            $last_notice_sn = createNo($model_obj, $model_field, $pin_head, true);
            $notice_info["notice_sn"] = $last_notice_sn;

            $loginUserInfo = $request -> user();
            $depart_id = intval($loginUserInfo -> department_id);
            $user_id = intval($loginUserInfo -> id);
            $notice_info["depart_id"] = $depart_id;
            $notice_info["user_id"] = $user_id;

            $notice_add_res = DB::table("notice")->insert($notice_info);

            if (!$notice_add_res) {
                return response()->json(['code' => '1005', 'msg' => '公告添加失败']);
            }

            $code = "1000";
            $msg = "公告添加成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:获取公告列表
     * editor:zongxing
     * type:GET
     * date : 2018.09.04
     * return Object
     */
    public function noticeList(Request $request)
    {
        if ($request->isMethod('get')) {
            $params = $request->toArray();

            if (!empty($params["query_sn"]) && $params["query_sn"] == "index"){
                $now_mouth = date("Y-m", time());
                $now_mouth = "%".$now_mouth."%";

                $notice_list = DB::table("notice as n")
                    ->leftJoin("admin_user as au","au.id","=","n.user_id")
                    ->leftJoin("department as d","d.department_id","=","n.depart_id")
                    ->where(DB::raw('Date(jms_n.create_time)'), "LIKE", $now_mouth)
                    ->orderBy("n.create_time", "desc")->get(["notice_content","user_name","de_name"]);
                $return_list["notice_list"] = $notice_list;
            }else{
                //搜索关键字
                $keywords = isset($params['query_sn']) ? trim($params['query_sn']) : '';
                $page_size = isset($params['page_size']) ? intval($params['page_size']) : 15;
                $start_page = isset($params['start_page']) ? intval($params['start_page']) : 1;
                $start_page = ($start_page - 1) * $page_size;

                $where = [];
                if ($keywords) {
                    $where = [
                        ['notice_content', 'LIKE', "%$keywords%"],
                    ];
                }

                $notice_list = DB::table("notice as n")->where($where)
                    ->leftJoin("admin_user as au","au.id","=","n.user_id")
                    ->leftJoin("department as d","d.department_id","=","n.depart_id")
                    ->skip($start_page)->take($page_size)
                    ->orderBy("n.create_time", "desc")->get(["notice_content","user_name","de_name"]);
                $notice_list = objectToArrayZ($notice_list);
                $return_list["notice_list"] = $notice_list;

                $notice_total_num = DB::table('notice')->where($where)->count();
                $return_list["total_num"] = $notice_total_num;
            }

            $code = "1002";
            $msg = "暂无公告";
            $return_info = compact('code', 'msg');

            if (!empty($return_list["notice_list"])) {
                $code = "1000";
                $msg = "获取公告列表成功";
                $data = $return_list;
                $return_info = compact('code', 'msg', 'data');
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

}