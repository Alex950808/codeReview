<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//采购期表Model  create by zongxing 2018.06.25
class PurchaseDateModel extends Model
{
    protected $table = 'purchase_date';

    //可操作字段
    protected $fillable = [
        'id','purchase_sn','flight_sn','delivery_team','delivery_pop_num',
        'start_time','delivery_time','channels_info',
        'method_info','predict_day','purchase_notice','status','create_time',
    ];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    protected $is_mark = [
        '0' => '未标记',
        '1' => '已标记',
    ];

    //采购期状态：1,准备中（采购单创建，未到开始时间）;2,进行中（开始时间到手动关闭）;3,关闭（手动关闭采购单）;4,已失效
    public $status = [
        '1' => '准备中',
        '2' => '进行中',
        '3' => '关闭',
        '4' => '已失效',
    ];

    //订单状态
    public $sub_status = [
        '1' => 'YD',
        '2' => 'BD',
        '3' => 'DD',
        '4' => '已关闭',
    ];

    /**
     * description:获取采购期列表
     * editor:zongxing
     * date : 2018.06.25
     * return Object
     */
    public function getPurchaseDateList($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;

        $where = [];
        $status = $param_info['status'];
        if ($status == 1) {
            $where[] = ['status', '<', 3];
        }elseif ($status == 2) {
            $where[] = ['status', '>=', 3];
        }

        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $purchase_sn = trim($param_info['query_sn']);
            $purchase_sn = '%' . $purchase_sn . '%';
            $where[] = ['purchase_sn', 'LIKE', $purchase_sn];
        }elseif (isset($param_info['time_option']) && !empty($param_info['time_option'])) {
            $delivery_time = trim($param_info['time_option']);
            $where[] = ['delivery_time', '<=', $delivery_time];
        }

        $purchaseDateLIst = DB::table($this->table)->where($where)->orderBy('create_time', 'DESC')->paginate($page_size);
        $purchaseDateLIst = objectToArrayZ($purchaseDateLIst);
        foreach ($purchaseDateLIst['data'] as $k => $v) {
            $purchaseDateLIst['data'][$k]['channels_list'] = json_decode($v['channels_list']);
        }
        return $purchaseDateLIst;
    }

    /**
     * description:获取采购期信息
     * editor:zongxing
     * date : 2019.01.07
     * return Array
     */
    public function getPurchaseDateDetail($param_info)
    {
        $where = [];
        $status = $param_info['status'];
        if ($status == 1) {
            $where = [
                ['status', '<', 3]
            ];
        }elseif ($status == 2) {
            $where = [
                ['status', '>=', 3]
            ];
        }

        if (isset($param_info['time_option']) && !empty($param_info['time_option'])) {
            $delivery_time = trim($param_info['time_option']);
            $where = [
                ['delivery_time', '<=', $delivery_time]
            ];
        }

        $purchaseDateDetail = DB::table($this->table)->where($where)->first();
        $purchaseDateDetail = objectToArrayZ($purchaseDateDetail);
        if(empty($purchaseDateDetail)){
            return $purchaseDateDetail;
        }
        $purchaseDateDetail['channels_list'] = json_decode($purchaseDateDetail['channels_list']);
        return $purchaseDateDetail;
    }

    /**
     * description:获取首页采购期批次任务列表
     * editor:zongxing
     * date : 2018.08.21
     * return Object
     */
//    public function get_batch_task_list($request)
//    {
//        $purchase_info = $request->toArray();
//        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
//        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
//        $start_str = ($start_page - 1) * $page_size;
//
//        $orWhere = [];
//        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
//            $query_sn = trim($purchase_info['query_sn']);
//            $query_sn = "%" . $query_sn . "%";
////            $where = [
////                ["rp.purchase_sn", "LIKE", "$query_sn"]
////            ];
////            $orWhere = [
////                ["rp.real_purchase_sn", "LIKE", "$query_sn"]
////            ];
//
//            $orWhere = function($query)use($query_sn)
//            {
//                $query->orWhere('rp.purchase_sn', 'LIKE', '\''.$query_sn.'\'')
//                    ->orWhere('rp.real_purchase_sn', 'LIKE', '\''.$query_sn.'\'');
//            };
//        }
//
//        //获取总数
//        $real_purchase_total = DB::table("real_purchase as rp")
//            ->select("real_purchase_sn", "rp.purchase_sn", "pd.id")
//            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
//            ->where("is_setting",1)
//            ->orWhere($orWhere)
//            ->paginate($page_size);
//        dd($real_purchase_total);
//        $total_num = count($real_purchase_total);
//
//        //获取批次列表
//        $real_purchase_list = DB::table("real_purchase as rp")
//            ->select("real_purchase_sn", "rp.purchase_sn", "pd.id")
//            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
//            ->where("is_setting",1)
//            ->orWhere($where)
//            ->orWhere($orWhere)
//            ->orderBy("rp.create_time", "desc")
//            ->skip($start_str)->take($page_size)
//            ->get();
//        $real_purchase_list = objectToArrayZ($real_purchase_list);
//
//        $task_total_info = DB::table('batch_task as bt')
//            ->leftJoin("task as t","t.task_sn","=","bt.task_sn")
//            ->get(["bt.id", "bt.task_sn", "task_name", "task_date", "task_time", "task_content", "status", "role_id", "user_list",
//                "delay_time", "is_system", "task_link", "real_purchase_sn"]);
//        $task_total_info = json_decode(json_encode($task_total_info), true);
//
//        $task_group_info = [];
//        foreach ($task_total_info as $k => $v) {
//            $tmp_task["id"] = $v["id"];
//            $tmp_task["task_sn"] = $v["task_sn"];
//            $tmp_task["task_name"] = $v["task_name"];
//            $tmp_task["task_date"] = $v["task_date"];
//            $tmp_task["task_time"] = $v["task_time"];
//            $tmp_task["task_content"] = $v["task_content"];
//            $tmp_task["status"] = $v["status"];
//            $tmp_task["role_id"] = $v["role_id"];
//            $tmp_task["user_list"] = $v["user_list"];
//            $tmp_task["delay_time"] = $v["delay_time"];
//            $tmp_task["is_system"] = $v["is_system"];
//            $tmp_task["task_link"] = $v["task_link"];
//
//            $real_purchase_sn = $v["real_purchase_sn"];
//            if (isset($task_group_info[$real_purchase_sn])) {
//                array_push($task_group_info[$real_purchase_sn], $tmp_task);
//            } else {
//                $task_group_info[$real_purchase_sn]= [];
//                array_push($task_group_info[$real_purchase_sn], $tmp_task);
//            }
//        }
//
//        $user_info = $request->user();
//        $user_id = $user_info->id;
//        $batch_task_info = [];
//        foreach ($real_purchase_list as $k => $v) {
//            $tmp_task_info["id"] = $v["id"];
//            $tmp_task_info["purchase_sn"] = $v["purchase_sn"];
//            $tmp_task_info["real_purchase_sn"] = $v["real_purchase_sn"];
//            $task_info = $task_group_info[$v["real_purchase_sn"]];
//
//            $tmp_task_info["task_info"] = [];
//            if (!empty($task_info)) {
//                foreach ($task_info as $k1 => $v1) {
//                    $task_info[$k1]["is_display"] = 1;
//                    if ($v1["status"] == 0) {
//                        $task_user_list = explode(",", $v1["user_list"]);
//                        if (!in_array($user_id, $task_user_list)) {
//                            $task_info[$k1]["is_display"] = 0;
//                        }
//
//                        $task_time = $v1["task_date"] . " " . $v1["task_time"];
//                        $task_time = strtotime($task_time);
//                        $now_time = time();
//                        if ($now_time > $task_time) {
//                            //计算延误时间
//                            $diff_time = $now_time - $task_time;
//                            $com_model = new CommonModel();
//                            $delay_time = $com_model->secToTime($diff_time);
//                            $task_info[$k1]["delay_time"] = $delay_time;
//                        }
//                    }
//
//                    $task_info[$k1]["sort_time"] = 0;
//                    $tmp_str = $v1["task_date"] . ' ' . $v1["task_time"];
//                    $task_info[$k1]["sort_time"] = strtotime($tmp_str);
//                }
//
//                $key_arrays = [];
//                foreach ($task_info as $val) {
//                    $key_arrays[] = $val['sort_time'];
//                }
//
//                array_multisort($key_arrays, SORT_ASC, SORT_NUMERIC, $task_info);
//                $tmp_task_info["task_info"] = $task_info;
//            }
//            array_push($batch_task_info, $tmp_task_info);
//        }
//
//        $return_info["batch_task_info"] = $batch_task_info;
//        $return_info["total_num"] = $total_num;
//        return $return_info;
//    }
    public function get_batch_task_list($request)
    {
        $param_info = $request->toArray();
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;

        $orWhere = [];
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = "%" . $query_sn . "%";
            $orWhere = function ($query) use ($query_sn) {
                $query->orWhere('rp.purchase_sn', 'LIKE', '\'' . $query_sn . '\'')
                    ->orWhere('rp.real_purchase_sn', 'LIKE', '\'' . $query_sn . '\'');
            };
        }

        //获取批次列表
        $real_purchase_total_list = DB::table("real_purchase as rp")
            ->select("real_purchase_sn", "rp.purchase_sn", "pd.id")
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->where("is_setting", 1)
            ->where("batch_cat", 1)
            ->orWhere($orWhere)
            ->orderBy("rp.create_time", "desc")
            ->paginate($page_size);
        $real_purchase_total_list = objectToArrayZ($real_purchase_total_list);
        if (empty($real_purchase_total_list['data'])) {
            return $real_purchase_total_list;
        }
        $real_purchase_list = $real_purchase_total_list['data'];

        //获取批次任务信息
        $task_total_info = DB::table('batch_task as bt')
            ->leftJoin("task as t", "t.task_sn", "=", "bt.task_sn")
            ->get(["bt.id", "bt.task_sn", "task_name", "task_date", "task_time", "task_content", "status", "role_id", "user_list",
                "delay_time", "is_system", "task_link", "real_purchase_sn"]);
        $task_total_info = objectToArrayZ($task_total_info);

        $task_group_info = [];
        foreach ($task_total_info as $k => $v) {
            $tmp_task["id"] = $v["id"];
            $tmp_task["task_sn"] = $v["task_sn"];
            $tmp_task["task_name"] = $v["task_name"];
            $tmp_task["task_date"] = $v["task_date"];
            $tmp_task["task_time"] = $v["task_time"];
            $tmp_task["task_content"] = $v["task_content"];
            $tmp_task["status"] = $v["status"];
            $tmp_task["role_id"] = $v["role_id"];
            $tmp_task["user_list"] = $v["user_list"];
            $tmp_task["delay_time"] = $v["delay_time"];
            $tmp_task["is_system"] = $v["is_system"];
            $tmp_task["task_link"] = $v["task_link"];

            $real_purchase_sn = $v["real_purchase_sn"];
            $task_group_info[$real_purchase_sn][] = $tmp_task;
        }

        $user_info = $request->user();
        $user_id = $user_info->id;
        $batch_task_info = [];
        foreach ($real_purchase_list as $k => $v) {
            $tmp_task_info["id"] = $v["id"];
            $tmp_task_info["purchase_sn"] = $v["purchase_sn"];
            $tmp_task_info["real_purchase_sn"] = $v["real_purchase_sn"];
            $task_info = $task_group_info[$v["real_purchase_sn"]];
            $tmp_task_info["task_info"] = [];
            if (!empty($task_info)) {
                foreach ($task_info as $k1 => $v1) {
                    $task_info[$k1]["is_display"] = 1;
                    if ($v1["status"] == 0) {
                        $task_user_list = explode(",", $v1["user_list"]);
                        if (!in_array($user_id, $task_user_list)) {
                            $task_info[$k1]["is_display"] = 0;
                        }
                        $task_time = $v1["task_date"] . " " . $v1["task_time"];
                        $task_time = strtotime($task_time);
                        $now_time = time();
                        if ($now_time > $task_time) {
                            //计算延误时间
                            $diff_time = $now_time - $task_time;
                            $com_model = new CommonModel();
                            $delay_time = $com_model->secToTime($diff_time);
                            $task_info[$k1]["delay_time"] = $delay_time;
                        }
                    }
                    $task_info[$k1]["sort_time"] = 0;
                    $tmp_str = $v1["task_date"] . ' ' . $v1["task_time"];
                    $task_info[$k1]["sort_time"] = strtotime($tmp_str);
                }
                $key_arrays = [];
                foreach ($task_info as $val) {
                    $key_arrays[] = $val['sort_time'];
                }
                array_multisort($key_arrays, SORT_ASC, SORT_NUMERIC, $task_info);
                $tmp_task_info["task_info"] = $task_info;
            }
            array_push($batch_task_info, $tmp_task_info);
        }
        $real_purchase_total_list['data'] = $batch_task_info;
        return $real_purchase_total_list;
    }

    /**
     * description:查询采购单信息
     * editor:zhangdong
     * date : 2018.06.27
     * params: $queryType :查询方式 1，按状态查询
     * return Object
     */
    public function getPurchaseMsg($data, $queryType, $pageSize)
    {
        $queryWhere = $this->createPurchaseWhere($data, $queryType);
        $arrWhere = $queryWhere['arrWhere'];
        $selectFiled = 'id,purchase_sn,flight_sn,delivery_team,delivery_pop_num,start_time,end_time,delivery_time,channels_list,purchase_notice';
        $queryRes = DB::table($this->table)->where($arrWhere)->orderBy("create_time", "desc")->selectRaw($selectFiled)->paginate($pageSize);
        return $queryRes;

    }

    /**
     * description:创建查询采购单信息条件
     * editor:zhangdong
     * date : 2018.06.27
     * params: $queryType :查询方式 1，按状态查询
     * return Object
     */
    public function createPurchaseWhere($data, $queryType)
    {
        $queryType = intval($queryType);
        if ($queryType == 1) { //按采购单状态查询
            $status = intval($data);
            $arrWhere = [
                ['status', '=', $status],
            ];
        } else {
            return false;
        }
        $createRes = [
            'arrWhere' => $arrWhere,
        ];
        return $createRes;
    }

    /**
     * description:商品分配预测-获取列表数据
     * editor:zhangdong
     * date : 2018.07.02
     * params: $queryType :查询方式 1，商品分配预测-获取列表数据
     * return Object
     */
    public function getGoodsAllotMsg($params, $queryType)
    {
        $queryWhere = $this->createGoodsAllotWhere($params, $queryType);
        $arrWhere = $queryWhere['arrWhere'];
        $selectFiled = 'id,purchase_sn,flight_sn,delivery_team,delivery_pop_num,start_time,end_time,delivery_time,channels_list,purchase_notice';
        $queryRes = DB::table($this->table)->where($arrWhere)->orderBy("create_time", "desc")->selectRaw($selectFiled)->paginate(15);
        return $queryRes;
    }


    /**
     * description:获取采购期详情
     * editor:zongxing
     * date : 2018.07.02
     * params: 1.采购期编号:$purchase_sn;
     * return Object
     */
    public function openPurchaseDate($purchase_sn)
    {
        $purchase_detail_info = DB::table("purchase_date")
            ->where("purchase_sn", "=", $purchase_sn)
            ->first(["id", "purchase_sn", "start_time", "delivery_team", "channels_list", "method_info",
                "delivery_pop_num", "purchase_notice", "status", "delivery_time", "channels_info"]);
        $purchase_detail_info = objectToArrayZ($purchase_detail_info);
        return $purchase_detail_info;
    }

    /**
     * description:商品分配预测-获取列表数据-创建条件
     * editor:zhangdong
     * date : 2018.06.27
     * params: $queryType :查询方式 1,商品分配管理-商品分配预测-获取列表数据
     * return Object
     */
    public function createGoodsAllotWhere($params, $queryType)
    {
        $queryType = intval($queryType);
        if ($queryType == 1) { //商品分配管理-商品分配预测-获取列表数据
            $arrWhere = [];
            $keywords = $params['keywords'];
            if ($keywords) {
                $arrWhere = [
                    ['purchase_sn', 'LIKE', "%$keywords%"],
                ];
            }
        } else {
            return false;
        }
        $createRes = [
            'arrWhere' => $arrWhere,
        ];
        return $createRes;

    }

    /**
     * description:销售模块-需求管理-获取并组装列表数据
     * editor:zhangdong
     * date : 2018.07.11
     * return Object
     */
    public function getPurchaseListStop($params, $pageSize)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        $where = [];
        if ($keywords) {
            $where = [
                ['purchase_sn', 'LIKE', "%$keywords%"],
            ];
        }
        $fileld = 'id,purchase_sn,flight_sn,delivery_team,delivery_pop_num,start_time,end_time,delivery_time,channels_list,
            purchase_notice';
        //zongxing 修改采购期状态
        $purchaseList = DB::table('purchase_date')->selectRaw($fileld)
            ->where('status', 2)->where($where)->orderBy("create_time", "desc")->paginate($pageSize);
        //根据采购单号统计当前采购单中包含的所有需求单
        foreach ($purchaseList as $key => $value) {
            $purchase_sn = trim($value->purchase_sn);
            $demandWhere = [
                [DB::raw('dg.purchase_sn'), $purchase_sn],
            ];
            $selectField = 'dg.demand_sn,COUNT(DISTINCT dg.spec_sn) AS sku_num,SUM(dg.goods_num) AS goods_num,
                    d.create_time,d.status,d.department';
            $demandData = DB::table(DB::raw('jms_demand_goods AS dg'))->selectRaw($selectField)
                ->join(DB::raw('jms_demand AS d'), function ($join) {
                    $join->on(DB::raw('d.purchase_sn'), '=', DB::raw('dg.purchase_sn'))
                        ->on(DB::raw('d.demand_sn'), '=', DB::raw('dg.demand_sn'));
                })
                ->where($demandWhere)
                ->groupBy(DB::raw('dg.demand_sn'))->get();
            $purchaseList[$key]->demand_data = $demandData;

        }
        return $purchaseList;
    }

    /**
     * description:销售模块-需求管理-获取并组装列表数据
     * editor:zhangdong
     * date : 2018.07.11
     * return Object
     */
    public function getPurchaseList($params, $pageSize)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        $demandWhere = [];
        if ($keywords) {
            $demandWhere = [
                ['demand_sn', 'LIKE', "%$keywords%"],
            ];
        }
        $field = [
            'd.demand_sn','d.department','d.expire_time','d.status','d.create_time','d.is_mark',
            'd.sub_order_sn','d.arrive_store_time','mos.status as sub_status','mos.external_sn',
        ];
        $demandList = DB::table('demand as d')->select($field)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', 'd.sub_order_sn')
            ->where($demandWhere)->orderBy("expire_time", "desc")->paginate($pageSize);
        foreach ($demandList as $key => $value) {
            $demandList[$key]->desc_mark = $this->is_mark[intval($value->is_mark)];
            $demandList[$key]->sub_status_desc = $this->sub_status[intval($value->sub_status)];
        }
        return $demandList;
    }

    /**
     * description:商品模块-商品部分配已清点商品-获取批次单列表
     * editor:zhangdong
     * date : 2018.09.28
     * return Object
     */
    public function getRealPurList($params, $pageSize)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        //根据采购单号或批次单进行搜索
        $selectField = 'rp.real_purchase_sn,rp.purchase_sn,rp.method_id,rp.path_way,rp.port_id,rp.status,rp.create_time';
        $realPurList = DB::table(DB::raw('jms_real_purchase as rp'))->selectRaw($selectField)
            ->where(function ($query) use ($keywords) {
                $query->orWhere(DB::raw('rp.real_purchase_sn'), 'LIKE', "%$keywords%")
                    ->orWhere(DB::raw('rp.purchase_sn'), 'LIKE', "%$keywords%");
            })
            ->orderBy("create_time", "desc")->paginate($pageSize);
        return $realPurList;
    }

    /**
     * description:商品模块-商品部分配已清点商品-根据批次单号查询对应商品信息
     * editor:zhangdong
     * date : 2018.09.29
     * return Object
     */
    public function getRealGoodsInfo($real_purchase_sn)
    {
        $real_purchase_sn = trim($real_purchase_sn);
        //根据批次单号查询对应商品信息
        $selectField = 'rpd.erp_prd_no,rpd.erp_merchant_no,rpd.spec_sn,rpd.goods_name,rpd.day_buy_num,rpd.allot_num,
                        rpd.purchase_remark,rpd.create_time,rpd.diff_num,rpd.real_allot_num';
        $where = [
            [DB::raw('rpd.real_purchase_sn'), '=', $real_purchase_sn]
        ];
        $realPurList = DB::table(DB::raw('jms_real_purchase_detail as rpd'))->selectRaw($selectField)
            ->where($where)
            ->orderBy(DB::raw('rpd.goods_name'), "desc")->get();
        return $realPurList;
    }

    /**
     * description:商品分配管理-商品实际分配（按部门）- 以批次单号为基准对商品数量进行分配
     * editor:zhangdong
     * date : 2018.09.29
     * return Object
     */
    public function getRealPurSnInfo($real_purchase_sn, $spec_sn)
    {
        $real_purchase_sn = trim($real_purchase_sn);
        $spec_sn = trim($spec_sn);
        //根据批次单号查询对应商品信息
        $selectField = 'rpd.erp_prd_no,rpd.erp_merchant_no,rpd.spec_sn,rpd.goods_name,rpd.day_buy_num,rpd.allot_num,
                        rpd.purchase_remark,rpd.create_time,rpd.diff_num,rpd.real_allot_num';
        $where = [
            [DB::raw('rpd.real_purchase_sn'), '=', $real_purchase_sn],
            [DB::raw('rpd.spec_sn'), '=', $spec_sn],
        ];
        $realPurSnInfo = DB::table(DB::raw('jms_real_purchase_detail as rpd'))->selectRaw($selectField)
            ->where($where)->first();
        return $realPurSnInfo;
    }


    /**
     * description:商品分配管理-商品实际分配（按部门）- 根据批次单号和商品规格码更新对应的实际分配数量
     * editor:zhangdong
     * date : 2018.09.29
     * return Object
     */
    public function updateRealNum($real_purchase_sn, $spec_sn, $real_allot_num)
    {
        $real_purchase_sn = trim($real_purchase_sn);
        $spec_sn = trim($spec_sn);
        $real_allot_num = intval($real_allot_num);
        //更新实际分配数量
        $where = [
            ['real_purchase_sn', '=', $real_purchase_sn],
            ['spec_sn', '=', $spec_sn]
        ];
        $updateField = ['real_allot_num' => $real_allot_num];
        $updateRes = DB::table('real_purchase_detail')->where($where)->update($updateField);
        return $updateRes;

    }


    /**
     * description:商品分配管理-商品分配-获取采购单列表(不论是否挂了需求单)
     * editor:zhangdong
     * date : 2018.09.30
     * return Object
     */
    public function getPurOrderList($params, $pageSize)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        //根据采购单号或批次单进行搜索
        $selectField = 'pd.purchase_sn,pd.flight_sn,pd.delivery_team,pd.delivery_pop_num,pd.start_time,
                        pd.delivery_time,pd.channels_list,pd.method_info,pd.predict_day,
                        pd.purchase_notice,pd.status,pd.create_time';
        $where = [
            ['purchase_sn', 'LIKE', "%$keywords%"],
        ];
        $purOrderList = DB::table(DB::raw('jms_purchase_date as pd'))->selectRaw($selectField)
            ->where($where)->orderBy(DB::raw('pd.create_time'), "desc")->paginate($pageSize);
        return $purOrderList;
    }

    /**
     * description:商品分配管理-商品分配-采购单列表-点击采购单号进入对应需求单号
     * editor:zhangdong
     * date : 2018.09.30
     * return Object
     */
    public function getPurDemOrdList($purchase_sn)
    {
        //搜索关键字
        $purchase_sn = trim($purchase_sn);
        //根据采购单号或批次单进行搜索
        $selectField = 'pdm.demand_sn,pdm.department,pdm.create_time';
        $where = [
            [DB::raw('pdm.purchase_sn'), '=', "$purchase_sn"],
        ];
        $purDemOrdList = DB::table(DB::raw('jms_purchase_demand as pdm'))->selectRaw($selectField)
            ->where($where)->orderBy(DB::raw('pdm.create_time'), "desc")->get();
        return $purDemOrdList;
    }

    /**
     * description:商品分配管理-商品分配-采购单列表-需求单列表-点击需求单号进入需求单对应的商品信息
     * editor:zhangdong
     * date : 2018.09.30
     * return Object
     */
    public function getPurDemOrdInfo($purchase_sn, $demand_sn)
    {
        //采购单号
        $purchase_sn = trim($purchase_sn);
        //需求单号
        $demand_sn = trim($demand_sn);
        //根据采购单号和需求单号获取商品信息
        $selectField = 'pdde.purchase_sn,pdde.demand_sn,pdde.goods_name,pdde.erp_prd_no,pdde.erp_merchant_no,
            pdde.spec_sn,pdde.goods_num,pdde.may_num,pdde.real_num,pdde.is_purchase,pdde.create_time';
        $where = [
            [DB::raw('pdde.purchase_sn'), '=', "$purchase_sn"],
            [DB::raw('pdde.demand_sn'), '=', "$demand_sn"],
        ];
        $purDemOrdInfo = DB::table(DB::raw('jms_purchase_demand_detail as pdde'))->selectRaw($selectField)
            ->where($where)->orderBy(DB::raw('pdde.goods_name'), "desc")->get();
        return $purDemOrdInfo;
    }


    /**
     * description:销售模块-需求管理-需求商品报价-获取需求列表
     * editor:zhangdong
     * date : 2018.10.09
     * return Object
     */
    public function getNeedGoodsList($params, $pageSize)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        $demandWhere = [];
        if ($keywords) {
            $demandWhere = [
                ['demand_sn', 'LIKE', "%$keywords%"],
            ];
        }
//        客户：王二 品牌数：15 SKU：28 商品数：214
        $selectField = 'demand_sn,department,expire_time,status,create_time';
        $demandList = DB::table('demand')->selectRaw($selectField)
            ->where($demandWhere)->orderBy("create_time", "desc")->paginate($pageSize);
        //组装列表数据
        foreach ($demandList as $key => $value) {
            $demand_sn = $value->demand_sn;
            //获取需求单商品的用户数据
            $arrUserList = $this->getNeedGoodsUser($demand_sn);
            $demandList[$key]->users = implode($arrUserList, ',');
            $goodsInfo = $this->getNeedGoodsInfo($demand_sn);
            $demandList[$key]->brand_num = $goodsInfo['brand_num'];
            $demandList[$key]->goods_num = $goodsInfo['goods_num'];
            $demandList[$key]->sku_num = $goodsInfo['sku_num'];
        }
        return $demandList;
    }

    /**
     * description:销售模块-需求管理-需求商品报价-获取需求列表-根据需求单号获取该需求单有哪些客户
     * editor:zhangdong
     * date : 2018.10.09
     * return Object
     */
    private function getNeedGoodsUser($demand_sn)
    {
        $selectField = 'su.user_name,su.id';
        $userList = DB::table(DB::raw('jms_user_goods as ug'))->selectRaw($selectField)
            ->leftJoin(DB::raw('jms_sale_user AS su'), DB::raw('ug.sale_user_id'), "=", DB::raw('su.id'))
            ->where(DB::raw('ug.demand_sn'), '=', $demand_sn)->groupBy(DB::raw('ug.sale_user_id'))->get();
        $arrUserList = [];
        foreach ($userList as $value) {
            $arrUserList[] = $value->user_name;
        }
        return $arrUserList;
    }

    /**
     * description:销售模块-需求管理-需求商品报价-获取需求列表-根据需求单号获取品牌数，商品数，sku数
     * editor:zhangdong
     * date : 2018.10.09
     * return Object
     */
    private function getNeedGoodsInfo($demand_sn)
    {
        $selectField = 'dg.goods_num,g.brand_id';
        $queryRes = DB::table(DB::raw('jms_demand_goods AS dg'))->selectRaw($selectField)
            ->leftJoin(DB::raw('jms_goods_spec AS gs'), DB::raw('dg.spec_sn'), "=", DB::raw('gs.spec_sn'))
            ->leftJoin(DB::raw('jms_goods AS g'), DB::raw('gs.goods_sn'), "=", DB::raw('g.goods_sn'))
            ->where(DB::raw('dg.demand_sn'), '=', $demand_sn)->get();
        $goodsInfo['sku_num'] = $queryRes->count();
        $goods_num = 0;
        $brand_num = [];
        foreach ($queryRes as $value) {
            $goods_num += intval($value->goods_num);
            $brand_num [] = $value->brand_id;
        }
        $goodsInfo['goods_num'] = $goods_num;
        $goodsInfo['brand_num'] = count(array_unique($brand_num));
        return $goodsInfo;
    }

    /**
     * description:获取符合条件的采购期
     * editor:zongxing
     * date : 2018.09.28
     * return Object
     */
    public function getPurchaseListForDemand($demand_info)
    {
        //获取所有符合条件的采购期
        $demand_time = $demand_info["expire_time"];
        if(!empty($demand_info["arrive_store_time"])){
            $demand_time = $demand_info["arrive_store_time"];
        }
        $purchase_list = DB::table("purchase_date")
            ->where('delivery_time', "<=", $demand_time)
            ->where("status", "<=", 2)
            ->get(["id", "purchase_sn", "delivery_team"]);
        $purchase_list = objectToArrayZ($purchase_list);

        //获取已经挂过的采购期
        $demand_sn = $demand_info['demand_sn'];
        $purchase_demand_list = DB::table('purchase_demand')
            ->where('demand_sn', $demand_sn)
            ->pluck('purchase_sn');
        $purchase_demand_list = objectToArrayZ($purchase_demand_list);
        if(!empty($purchase_demand_list)){
            foreach ($purchase_list as $k=>$v){
                $purchase_sn = $v['purchase_sn'];
                if(in_array($purchase_sn,$purchase_demand_list)){
                    unset($purchase_list[$k]);
                }
            }
        }
        $purchase_list = array_values($purchase_list);
        return $purchase_list;
    }

    /**
     * description:根据采购期单号查询采购期信息
     * author:zhangdong
     * date:2019.04.26
     * return Object
     */
    public function queryPurchaseData(array $arrPurchaseSn = [])
    {
        $queryRes = DB::table($this->table)->select($this->fillable)
            ->whereIn('purchase_sn',$arrPurchaseSn)->get();
        return $queryRes;
    }























}//end of class
