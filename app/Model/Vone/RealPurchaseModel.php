<?php

namespace App\Model\Vone;

use App\Modules\Erp\ErpApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入时间类库
use Carbon\Carbon;

class RealPurchaseModel extends Model
{
    protected $table = 'real_purchase as rp';
    protected $real_purchase_detail = 'real_purchase_detail';

    //可操作字段
    protected $fillable = ['method_id', 'channels_id', 'path_way', 'user_id', 'data_name'];

    protected $field = [
        'rp.id', 'rp.real_purchase_sn', 'rp.purchase_sn', 'rp.demand_sn',
        'rp.method_id', 'rp.channels_id', 'rp.path_way', 'rp.port_id',
        'rp.user_id', 'rp.data_name', 'rp.status', 'rp.group_sn',
        'rp.is_mother', 'rp.parent_id', 'rp.delivery_time', 'rp.arrive_time',
        'rp.allot_time', 'rp.diff_time', 'rp.billing_time', 'rp.storage_time',
        'rp.is_setting', 'rp.batch_cat', 'rp.create_time', 'rp.batch_cat'
    ];
    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    //运输方式
    protected $path_way = [
        '0' => '自提',
        '1' => '邮寄'
    ];
    //到货港口编号
    protected $port_id = [
        '1001' => '香港',
        '1002' => '保税仓',
        '1003' => '西安'
    ];
    protected $port_sn = [
        '1001' => 'HK',
        '1002' => 'BS',
        '1003' => 'XA'
    ];

    //实采单状态
    protected $status = [
        '0' => '状态异常',
        '1' => '待清点',
        '2' => '待确认差异',
        '3' => '待开单',
        '4' => '待入库',
        '5' => '已完成',
        '6' => '待核价',
    ];
    //是否设置了模板
    protected $is_setting = [
        '0' => '未设置',
        '1' => '已设置'
    ];

    //是否分货 0 待部门分货 1 待用户分货 2 完成分货
    protected $is_sort = [
        '0' => '待部门分货',
        '1' => '待用户分货',
        '2' => '完成分货',
    ];

    public $is_sort_int = [
        'WAIT_DEPART_SORT' => 0,//待部门分货
        'WAIT_USER_SORT' => 1,//待用户分货
        'FINISH_SORT' => 2,//完成分货
    ];

    //批次类别,1:正常批次;2:预采批次;3:常备批次
    protected $batch_cat = [
        '1' => '正常批次',
        '2' => '预采批次',
        '3' => '常备批次',
    ];


    /**
     * description:组装待清点采购单列表数据
     * editor:zhangdong
     * date : 2018.07.06
     * return Object
     */
    public function createWaitCheckData($arrQueryData, $pageSize)
    {
        $keywords = $arrQueryData['keywords'];
        $purOrdStatus = $arrQueryData['purOrdStatus'];
        //查询采购单是否过期标志 1,带入查询条件 0，不带入查询条件
        $is_expire = $arrQueryData['is_expire'];
        $field = '';
        $field .= 'pd.id,'; //modify by zongxing 增加采购期期数id
        $arrWhere = [];
        $statusWhere = [];
        if (!empty($keywords)) {
            $arrWhere = [
                'orWhere1' => [
                    [DB::raw('rp.purchase_sn'), 'LIKE', "%$keywords%"],
                ]
            ];
        }
        //采购单状态 1,待清点;2,待确认差异3,待开单;4,待入库;5,已完成
        if ($purOrdStatus) {
            $statusWhere = [
                [DB::raw('rp.status'), $purOrdStatus],
            ];
        }
        //查询清点过期数据
        $expireWhere = [];
        if ($is_expire === 1) {
            //提货时间 < 当前时间 - 48h
            $expireHour = config('constants.ALLOT_EXPIRE_TIME');
            $curTime = Carbon::now();
            $expireTime = $curTime->addHour(-$expireHour);
            $expireWhere = [
                [DB::raw('pd.delivery_time'), '<', $expireTime],
            ];
            $field .= 'TIMESTAMPDIFF(HOUR, pd.delivery_time, NOW()) AS diffTime,';
        }
        $field .= 'rp.purchase_sn';
        $queryRes = DB::table(DB::raw('jms_real_purchase as rp'))->selectRaw($field)
            ->leftJoin(DB::raw('jms_purchase_date as pd'), DB::raw('pd.purchase_sn'), '=', DB::raw('rp.purchase_sn'))
            ->where(function ($result) use ($arrWhere, $statusWhere, $expireWhere) {
                if (count($arrWhere) > 0) {
                    $result->where($arrWhere['orWhere1']);
                }
                if (count($statusWhere) > 0) {
                    $result->where($statusWhere);
                }
                if (count($expireWhere) > 0) {
                    $result->where($expireWhere);
                }
            })
            ->groupBy(DB::raw('rp.purchase_sn'))
            ->orderBy(DB::raw('rp.create_time'), "desc")
            ->paginate($pageSize);

        foreach ($queryRes as $key => $value) {
            $purchase_sn = $value->purchase_sn;
            //查询该采购单的商品实采总数
            $realBuyNum = DB::table(DB::raw('jms_real_purchase_detail'))->selectRaw('SUM(day_buy_num) as totalBuyNum')
                ->where('purchase_sn', $purchase_sn)->first();
            $queryRes[$key]->realBuyNum = intval($realBuyNum->totalBuyNum);
            $p_num = DB::table($this->table)->selectRaw('COUNT(1) AS p_num,path_way')
                ->where('purchase_sn', $purchase_sn)->groupBy('path_way')->get();
            $zt_num = 0;
            $yj_num = 0;
            $zt_real_num = 0;
            $yj_real_num = 0;
            foreach ($p_num as $item) {
                //查询该采购单的自提批数和邮寄批数(0,自提;1,邮寄)
                $path_way = intval($item->path_way);
                $path_num = intval($item->p_num);
                if ($path_way == 0) {
                    $zt_num = $path_num;
                    $path_way = 0;
                }
                if ($path_way == 1) {
                    $yj_num = $path_num;
                    $path_way = 1;
                }
                $where = [
                    [DB::raw('rpd.real_purchase_sn'), '=', DB::raw('rp.real_purchase_sn')],
                    [DB::raw('rp.purchase_sn'), $purchase_sn],
                    [DB::raw('rp.path_way'), $path_way],
                ];
                $realNumRes = DB::table(DB::raw('jms_real_purchase AS rp'))
                    ->selectRaw('SUM(rpd.day_buy_num) AS total_real_num')
                    ->leftJoin(DB::raw('jms_real_purchase_detail AS rpd'), DB::raw('rpd.purchase_sn'), '=', DB::raw('rp.purchase_sn'))
                    ->where($where)->first();
                $realNum = intval($realNumRes->total_real_num);
                if ($path_way == 0) {
                    $zt_real_num = $realNum;
                }
                if ($path_way == 1) {
                    $yj_real_num = $realNum;
                }
                $queryRes[$key]->ship_way = [
                    'zt' => [
                        'p_num' => $zt_num,
                        'realNum' => $zt_real_num,
                    ],
                    'yj' => [
                        'p_num' => $yj_num,
                        'realNum' => $yj_real_num,
                    ],
                ];
            }
            //查询并组装实购单号
            $where = [
                [DB::raw('rpd.real_purchase_sn'), '=', DB::raw('rp.real_purchase_sn')],
                [DB::raw('rp.purchase_sn'), '=', $purchase_sn],
            ];
            $field = "rp.real_purchase_sn,SUM(day_buy_num) AS realBuyNum,
            Date(rp.create_time) as create_time,rp.port_id,rp.status,rp.path_way";
            $realBuyInfo = DB::table(DB::raw('jms_real_purchase AS rp'))->selectRaw($field)
                ->leftJoin(DB::raw('jms_real_purchase_detail AS rpd'), DB::raw('rpd.purchase_sn'), '=', DB::raw('rp.purchase_sn'))
                ->where($where)
                ->where(DB::raw('rp.status'), $purOrdStatus)
                ->groupBy(DB::raw('rp.real_purchase_sn'))
                ->get();

            //计算预期到港日期
            foreach ($realBuyInfo as $k => $v) {
                $realBuyInfo[$k]->predict_day = date('Y-m-d', strtotime("{$v->create_time} +{$v->predict_day} day"));
            }
            $queryRes[$key]->realBuyInfo = $realBuyInfo;
        }
        return $queryRes;

    }

    /**
     * description:组装待清点采购单列表数据
     * editor:zhangdong
     * date : 2018.07.06
     * return Object
     */
    public function getRealPurInfo(array $reqParams)
    {
        $real_purchase_sn = $reqParams['real_purchase_sn'];
        $where = [
            ['real_purchase_sn', $real_purchase_sn],
        ];
        $field = 'purchase_sn,real_purchase_sn,spec_sn,goods_name,erp_merchant_no,day_buy_num,allot_num,(day_buy_num - allot_num) AS diff_num,remark';
        $realPurInfoRes = DB::table($this->real_purchase_detail)->selectRaw($field)->where($where)->get();
        $realPurInfo = [];
        $goods_list = [];
        foreach ($realPurInfoRes as $key => $value) {
            $purchase_sn = trim($value->purchase_sn);
            $real_purchase_sn = trim($value->real_purchase_sn);
            $spec_sn = trim($value->spec_sn);
            $goods_list[$key] = [
                'spec_sn' => $spec_sn,
                'goods_name' => trim($value->goods_name),
                'erp_merchant_no' => trim($value->erp_merchant_no),
                'day_buy_num' => trim($value->day_buy_num),
                'allot_num' => trim($value->allot_num),
                'diff_num' => trim($value->diff_num),
                'remark' => trim($value->remark),
            ];
            $realPurInfo = [
                'purchase_sn' => $purchase_sn,
                'real_purchase_sn' => $real_purchase_sn,
                'goods_list' => $goods_list,
            ];
        }
        return $realPurInfo;

    }

    /**
     * description:批次详情
     * editor:zongxing
     * date : 2019.01.14
     * return Array
     */
    public function getBatchDetail($param_info)
    {
        $real_purchase_sn = trim($param_info['real_purchase_sn']);
        $purchase_sn = trim($param_info['purchase_sn']);
        $where = [['rp.real_purchase_sn', $real_purchase_sn]];
        $field = ['rp.purchase_sn', 'rp.real_purchase_sn', 'rpd.spec_sn', 'rpd.goods_name', 'rpd.erp_merchant_no',
            'rpd.erp_prd_no', 'rpd.day_buy_num', 'rpd.allot_num', 'rpd.diff_num', 'rpd.remark', 'gs.goods_label'];
        $batch_detail_info = DB::table('real_purchase_detail as rpd')->select($field)
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpd.spec_sn')
            ->where($where)
            ->orderBy('rp.create_time', 'DESC')->get();
        $batch_detail_info = objectToArrayZ($batch_detail_info);
        if (empty($batch_detail_info)) {
            return $batch_detail_info;
        }
        //获取商品标签列表
        $goods_label_model = new GoodsLabelModel();
        $goods_label_info = $goods_label_model->getAllGoodsLabelList();
        foreach ($batch_detail_info as $k => $v) {
            $goods_label = explode(',', $v['goods_label']);
            $tmp_goods_label = [];
            foreach ($goods_label_info as $k1 => $v1) {
                $label_id = intval($v1['id']);
                if (in_array($label_id, $goods_label)) {
                    $tmp_goods_label[] = $v1;
                }
            }
            $batch_detail_info[$k]['goods_label_list'] = $tmp_goods_label;
        }
        $batch_list = [
            'purchase_sn' => $purchase_sn,
            'real_purchase_sn' => $real_purchase_sn,
            'goods_list' => $batch_detail_info,
        ];
        return $batch_list;
    }

    /**
     * description:根据采购单号和实采单号查询是否有对应信息
     * editor:zhangdong
     * date : 2018.07.09
     * return Object
     */
    public function getPurchaseOrdInfo($purchase_sn, $real_purchase_sn, $spec_sn)
    {
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_purchase_sn', $real_purchase_sn],
            ['spec_sn', $spec_sn],
        ];
        $purchaseInfo = DB::table('real_purchase_detail')->where($where)->get();
        if ($purchaseInfo->count() != 1) return false;
        return $purchaseInfo[0];

    }

    /**
     * description:查询批次详情中是否有对应的商品
     * editor:zongxing
     * date : 2018.07.09
     * return Array
     */
    public function getBatchGoodsInfo_stop($reqParams, $spec_sn = null)
    {
        $purchase_sn = trim($reqParams['purchase_sn']);
        $group_sn = trim($reqParams['group_sn']);
        $is_mother = intval($reqParams['is_mother']);
        $where = [
            ['rp.purchase_sn', '=', $purchase_sn],
            ['rp.group_sn', '=', $group_sn],
            ['rp.is_mother', '=', $is_mother],
        ];
        if ($spec_sn) {
            $where[] = [
                'rpd.spec_sn', '=', $spec_sn
            ];
        }

        $filed = ['rp.real_purchase_sn', 'rp.purchase_sn', 'spec_sn', 'goods_name', 'erp_merchant_no', 'day_buy_num',
            'allot_num', 'remark', 'group_sn', 'batch_cat'];
        $batch_goods_info = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->orderBy('rp.batch_cat', 'desc')->where($where)->get($filed);
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        if (empty($batch_goods_info)) return false;
        return $batch_goods_info;
    }

    public function getBatchGoodsInfo($param_info, $spec_sn = null)
    {
        $real_purchase_sn = trim($param_info['real_purchase_sn']);//实采单号
        $where = [['rp.real_purchase_sn', '=', $real_purchase_sn]];
        if ($spec_sn) {
            $where[] = ['rpd.spec_sn', '=', $spec_sn];
        }
        $filed = ['rp.real_purchase_sn', 'rp.purchase_sn', 'spec_sn', 'goods_name', 'erp_merchant_no', 'day_buy_num',
            'allot_num', 'remark', 'group_sn', 'batch_cat'];
        $batch_goods_info = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->where($where)->get($filed);
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        return $batch_goods_info;
    }

    /**
     * description:获取批次对应的商品
     * editor:zongxing
     * date : 2019.07.24
     * return Array
     */
    public function getBatchGoodsDetail($real_purchase_sn)
    {
        $where = [['rpd.real_purchase_sn', '=', $real_purchase_sn]];
        $batch_goods_info = DB::table('real_purchase_detail as rpd')->where($where)->pluck('id', 'spec_sn');
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        return $batch_goods_info;
    }

    /**
     * description:根据采购单号，实采单号，商品规格码更新对应商品实采数量
     * editor:zhangdong
     * date : 2018.07.09
     * return Boolean
     */
    public function updateAllotNum($purchase_sn, $real_purchase_sn, $spec_sn, $updateParams)
    {
        $allot_num = intval($updateParams['allot_num']);
        $where = [
            ['purchase_sn', $purchase_sn],
            ['real_purchase_sn', $real_purchase_sn],
            ['spec_sn', $spec_sn],
            ['day_buy_num', '>=', $allot_num],
        ];
        $udpate = [
            'allot_num' => $allot_num,
            'remark' => $updateParams['remark'],
        ];
        $updateRes = DB::table('real_purchase_detail')->where($where)->update($udpate);
        return $updateRes;

    }

    /**
     * description:根据实采单号和采购单号修改实采单号的状态为待确认差异
     * editor:zhangdong
     * date : 2018.07.10
     * return Boolean
     */
    public function updateRealSnStatus($purchase_sn, $group_sn, $status)
    {
        $currentTime = Carbon::now();
        if ($status === 2) {
            $field = 'allot_time';
        } elseif ($status === 5) {
            $field = 'storage_time';
        } else {
            return false;
        }
        $where = [
            ['purchase_sn', $purchase_sn],
            ['group_sn', $group_sn]
        ];
        $udpate = [
            'status' => $status,
            $field => $currentTime,
        ];
        $updateRes = DB::table('real_purchase')->where($where)->update($udpate);
        return $updateRes;

    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getDiffList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);

        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ['rp.purchase_sn', "LIKE", $purchase_sn]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "allot_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.status", $status)
            ->where("rp.is_setting", 1)
            ->where("bt.task_link", "confirmDifference")
            ->where("rp.allot_time", ">", $expireTime)
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = objectToArrayZ($real_purchase_info);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];
                $tmp_batch_arr["allot_time"] = $v1["allot_time"];

                $expireTime_tmp = strtotime($expireTime);
                $allot_time = strtotime($v1["allot_time"]);
                $diff_time = $allot_time - $expireTime_tmp;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;
                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }
        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getDiffExtireList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);

        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ['rp.purchase_sn', "LIKE", $purchase_sn]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "allot_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.is_setting", 1)
            ->where("rp.status", $status)
            ->where("bt.task_link", "confirmDifference")
            ->where("rp.allot_time", "<=", $expireTime)
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = json_decode(json_encode($real_purchase_info), true);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];
                $tmp_batch_arr["allot_time"] = $v1["allot_time"];

                $expireTime_tmp = strtotime($expireTime);
                $allot_time = strtotime($v1["allot_time"]);
                $diff_time = $expireTime_tmp - $allot_time;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;

                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }
        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getAllotList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rp.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "allot_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.status", $status)
            ->where("bt.task_link", "pendingPurchaseOrder")
            ->where("rp.arrive_time", ">", $expireTime)
            ->where($where)
            ->where("rp.is_setting", 1)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = json_decode(json_encode($real_purchase_info), true);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];

                $expireTime_tmp = strtotime($expireTime);
                $arrive_time = strtotime($v1["arrive_time"]);
                $diff_time = $arrive_time - $expireTime_tmp;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;

                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }

        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getAllotExtireList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);

        //计算实时数据列表信息
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rp.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "allot_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.status", $status)
            ->where("rp.arrive_time", "<=", $expireTime)
            ->where("rp.is_setting", 1)
            ->where("bt.task_link", "pendingPurchaseOrder")
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = json_decode(json_encode($real_purchase_info), true);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];

                $expireTime_tmp = strtotime($expireTime);
                $arrive_time = strtotime($v1["arrive_time"]);
                $diff_time = $expireTime_tmp - $arrive_time;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;

                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }

        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function goodsStockList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);

        //计算实时数据列表信息
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rp.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "billing_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.is_setting", 1)
            ->where("rp.status", $status)
            ->where("rp.billing_time", ">", $expireTime)
            ->where("bt.task_link", "purchaseOrder")
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = json_decode(json_encode($real_purchase_info), true);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];
                $tmp_batch_arr["billing_time"] = $v1["billing_time"];

                $expireTime_tmp = strtotime($expireTime);
                $billing_time = strtotime($v1["billing_time"]);
                $diff_time = $billing_time - $expireTime_tmp;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;

                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }

        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function goodsStockExtireList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);

        //计算实时数据列表信息
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rp.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "billing_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.is_setting", 1)
            ->where("rp.status", $status)
            ->where("rp.billing_time", "<=", $expireTime)
            ->where("bt.task_link", "purchaseOrder")
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = json_decode(json_encode($real_purchase_info), true);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];
                $tmp_batch_arr["billing_time"] = $v1["billing_time"];

                $expireTime_tmp = strtotime($expireTime);
                $billing_time = strtotime($v1["billing_time"]);
                $diff_time = $expireTime_tmp - $billing_time;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;

                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }

        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getBillList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);

        //计算实时数据列表信息
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rp.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "diff_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.is_setting", 1)
            ->where("rp.status", $status)
            ->where("bt.task_link", "stayOpenBill")
            ->where("rp.diff_time", ">", $expireTime)
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = json_decode(json_encode($real_purchase_info), true);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];
                $tmp_batch_arr["diff_time"] = $v1["diff_time"];

                $expireTime_tmp = strtotime($expireTime);
                $real_diff_time = strtotime($v1["diff_time"]);
                $diff_time = $real_diff_time - $expireTime_tmp;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;

                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }

        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.10
     * return Object
     */
    public function getBillExtireList($status = 1, $purchase_info, $request)
    {
        $expireTime = $this->create_expire_time($status);

        //计算实时数据列表信息
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rp.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $goods_list_info = DB::table("real_purchase as rp")
            ->select("pd.id", "rp.purchase_sn", "rp.real_purchase_sn", "diff_time", "bt.user_list", "bt.status",
                "rp.delivery_time", "rp.arrive_time"
            )
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->leftJoin("batch_task as bt", "bt.real_purchase_sn", "=", "rp.real_purchase_sn")
            ->where("rp.is_setting", 1)
            ->where("rp.status", $status)
            ->where("bt.task_link", "stayOpenBill")
            ->where("rp.diff_time", "<", $expireTime)
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $goods_list_info = objectToArrayZ($goods_list_info);

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $goods_list_info = array_slice($goods_list_info, $start_str, $page_size);

        //计算批次sku数量和采购总数
        $real_purchase_info = DB::table("real_purchase_detail as rpd")
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->select("rp.real_purchase_sn",
                DB::raw('count(spec_sn) as sku_num'),
                DB::raw('sum(day_buy_num) as total_buy_num')
            )
            ->where("rp.status", $status)
            ->groupBy("real_purchase_sn")->get()->groupBy("real_purchase_sn");
        $real_purchase_info = json_decode(json_encode($real_purchase_info), true);

        $tmp_id_arr = [];
        $return_arr = [];
        $return_arr["purchase_info"] = [];
        foreach ($goods_list_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (!in_array($v1["id"], $tmp_id_arr)) {
                    array_push($tmp_id_arr, $v1["id"]);

                    $tmp_arr["id"] = $v1["id"];
                    $tmp_arr["purchase_sn"] = $v1["purchase_sn"];
                    array_push($return_arr["purchase_info"], $tmp_arr);
                }

                $id_key = array_keys($tmp_id_arr, $v1["id"]);
                $id_key = $id_key[0];

                //判断权限
                $tmp_batch_arr["is_display"] = 1;
                if ($v1["status"] == 0) {
                    $user_info = $request->user();
                    $user_id = $user_info->id;

                    $task_user_list = explode(",", $v1["user_list"]);
                    if (!in_array($user_id, $task_user_list)) {
                        $tmp_batch_arr["is_display"] = 0;
                    }
                }

                //批次单数量信息
                $real_purchase_sn = $v1["real_purchase_sn"];
                $num_info = $real_purchase_info[$real_purchase_sn][0];

                $tmp_batch_arr["real_purchase_sn"] = $v1["real_purchase_sn"];
                $tmp_batch_arr["sku_num"] = $num_info["sku_num"];
                $tmp_batch_arr["total_buy_num"] = $num_info["total_buy_num"];
                $tmp_batch_arr["delivery_time"] = $v1["delivery_time"];
                $tmp_batch_arr["arrive_time"] = $v1["arrive_time"];
                $tmp_batch_arr["diff_time"] = $v1["diff_time"];

                $expireTime_tmp = strtotime($expireTime);
                $diff_time = strtotime($v1["diff_time"]);
                $diff_time = $expireTime_tmp - $diff_time;
                $diff_hour = $diff_time / (60 * 60);
                $tmp_batch_arr["diff_hour"] = (int)$diff_hour;

                $return_arr["purchase_info"][$id_key]["batch_info"][] = $tmp_batch_arr;
            }
        }

        $return_arr["data_num"] = count($return_arr["purchase_info"]);
        return $return_arr;
    }

    /**
     * description:创建过期对比时间
     * editor:zongxing
     * params: 1.实采批次单号:real_purchase_sn;2.要修改的状态:status;
     * date : 2018.07.16
     * return String
     */
    public function create_expire_time($status)
    {
        if ($status == 1) {
            $expireHour = config('constants.ALLOT_EXPIRE_TIME');
        } elseif ($status == 2) {
            $expireHour = config('constants.DIFF_EXPIRE_TIME');
        } elseif ($status == 3) {
            $expireHour = config('constants.BILLING_EXPIRE_TIME');
        } elseif ($status == 4) {
            $expireHour = config('constants.STORAGE_EXPIRE_TIME');
        } elseif ($status == 6) {
            $expireHour = config('constants.PRICEING_EXPIRE_TIME');
        }
        $curTime = Carbon::now();
        $expireTime = $curTime->addHour(-$expireHour);
        $expireTime = $expireTime->toDateTimeString();
        return $expireTime;
    }

    /**
     * description:改变批次状态（确认差异或拒绝）
     * editor:zongxing
     * params: 1.实采批次单号:real_purchase_sn;2.要修改的状态:status;3.是否生成子批次单:create_child_sn
     * date : 2018.07.10
     * return Object
     */
    public function changeRealPurStatus($loginUserInfo, $batch_info, $update_info, $erp_push = false)
    {
        $real_purchase_sn = trim($batch_info["real_purchase_sn"]);
        $purchase_sn = trim($batch_info['purchase_sn']);
        $status = $batch_info["status"];
        $where = [
            ['real_purchase_sn', '=', $real_purchase_sn]
        ];
        $real_purchase_info = DB::table("real_purchase")->where($where)->first();
        $real_purchase_info = objectToArrayZ($real_purchase_info);

        //获取批次详情数据
        $rp_model = new RealPurchaseModel();
        $batch_detail_info = $rp_model->getBatchDetail($batch_info);

        //如果没有选择生成子单,则需要对清点数量进行校正
        $updateDemandCountGoodsSql = '';
        if ($status == 6 && !isset($batch_info["create_child_sn"]) || (isset($batch_info["create_child_sn"]) &&
                $batch_info["create_child_sn"] == 0)
        ) {
            $goods_list = $batch_detail_info['goods_list'];
            $updateDemandCountGoods = [];
            foreach ($goods_list as $k => $v) {
                $spec_sn = $v['spec_sn'];
                $diff_num = $v['diff_num'];
                if ($diff_num) {
                    $updateDemandCountGoods['real_buy_num'][] = [
                        $spec_sn => 'real_buy_num - ' . $diff_num
                    ];
//                    $updatePurchaseChannelGoods['real_num'][] = [
//                        $spec_sn => 'real_num - ' . $diff_num
//                    ];
                }
            }

            if (!empty($updateDemandCountGoods)) {
                //更新条件
                $where = [];
                $where['purchase_sn'] = $batch_detail_info['purchase_sn'];
                //需要判断的字段
                $column = 'spec_sn';
                $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column, $where);
            }
        }

        //生成子批次单
        $realPurchaseData = [];
        $detail_info = [];
        $insert_batch_task = [];
        if ($status == 6 && isset($batch_info["create_child_sn"]) && $batch_info["create_child_sn"] == 1) {
            unset($batch_info["create_child_sn"]);
            //组装添加数据
            $realPurchaseData['group_sn'] = $real_purchase_info['group_sn'];
            $realPurchaseData['purchase_sn'] = $real_purchase_info['purchase_sn'];
            $realPurchaseData['method_id'] = $real_purchase_info['method_id'];
            $realPurchaseData['channels_id'] = $real_purchase_info['channels_id'];
            $realPurchaseData['path_way'] = $real_purchase_info['path_way'];
            $realPurchaseData['port_id'] = $real_purchase_info['port_id'];
            $realPurchaseData['user_id'] = $real_purchase_info['user_id'];
            $realPurchaseData['status'] = 1;
            $realPurchaseData['is_mother'] = 2;
            $realPurchaseData['is_setting'] = 0;
            $realPurchaseData['delivery_time'] = '';
            $realPurchaseData['arrive_time'] = '';

            //计算实际采购编号
            $model_field = "real_purchase_sn";
            $now_date = str_replace('-', '', date('Y-m-d', time()));
            $pin_head = "PC-" . $now_date . "-";
            $realPurchaseModel = new RealPurchaseModel();
            $last_purchase_sn = createNoByTime($realPurchaseModel, $model_field, $pin_head);
            $realPurchaseData["real_purchase_sn"] = $last_purchase_sn;

            //组装采购数据详情表数据
            $detail_info = $this->createDetailData($realPurchaseData, $batch_detail_info);

            //批次任务
            $batch_task_info = DB::table("batch_task")->where("real_purchase_sn", $real_purchase_sn)->get();
            $batch_task_info = objectToArrayZ($batch_task_info);
            foreach ($batch_task_info as $k => $v) {
                $tmp_task["real_purchase_sn"] = $last_purchase_sn;
                $tmp_task["purchase_sn"] = $v["purchase_sn"];
                $tmp_task["task_sn"] = $v["task_sn"];
                $tmp_task["task_date"] = $v["task_date"];
                $tmp_task["task_time"] = $v["task_time"];
                $tmp_task["task_content"] = $v["task_content"];
                $tmp_task["status"] = 0;
                $tmp_task["role_id"] = $v["role_id"];
                $tmp_task["user_list"] = $v["user_list"];
                $tmp_task["delay_time"] = $v["delay_time"];
                $tmp_task["is_system"] = $v["is_system"];
                $tmp_task["task_link"] = $v["task_link"];
                array_push($insert_batch_task, $tmp_task);
            }
        }

        if ($erp_push) {
            //进行erp开单操作
            $erp_api_model = new ErpApi();
            $erp_push_res = $erp_api_model->batch_order_push($batch_info);
            if ($erp_push_res === false) {
                return false;
            }
        }

        //进行批次操作
        $res = $this->changeBatchInfo($realPurchaseData, $detail_info, $insert_batch_task, $real_purchase_sn,
            $batch_info, $loginUserInfo, $status, $update_info, $updateDemandCountGoodsSql);
        return $res;
    }

    /**
     * description:改变批次信息操作
     * editor:zongxing
     * date : 2019.01.15
     * return Bool
     */
    public function changeBatchInfo($realPurchaseData, $detail_info, $insert_batch_task, $real_purchase_sn,
                                    $batch_info, $loginUserInfo, $status, $update_info, $updateDemandCountGoodsSql)
    {
        $updateRes = DB::transaction(function () use (
            $realPurchaseData, $detail_info, $insert_batch_task, $real_purchase_sn,
            $batch_info, $loginUserInfo, $status, $update_info, $updateDemandCountGoodsSql
        ) {
            //实时采购数据增加到实时采购数据表
            if (!empty($realPurchaseData)) {
                DB::table("real_purchase")->insert($realPurchaseData);
            }
            //实时采购数据增加到实时采购数据详情表
            if (!empty($detail_info)) {
                DB::table("real_purchase_detail")->insert($detail_info);
            }

            //新增批次任务
            if (!empty($insert_batch_task)) {
                DB::table("batch_task")->insert($insert_batch_task);
            }

            //如果存在差异,需要更新采购期统计表
            if (!empty($updateDemandCountGoodsSql)) {
                DB::update(DB::raw($updateDemandCountGoodsSql));
            }

            //生成子单完成，更新前需清除掉参数create_child_sn
            unset($batch_info["create_child_sn"]);
            $purchase_sn = trim($batch_info["purchase_sn"]);
            $group_sn = trim($batch_info["group_sn"]);
            $is_mother = intval($batch_info["is_mother"]);
            $where = [
                ['group_sn', '=', $group_sn],
                ['purchase_sn', '=', $purchase_sn],
                ['is_mother', '=', $is_mother]
            ];
            $update_res = DB::table("real_purchase")->where($where)->update($update_info);

            //更新任务状态
            if ($status == 6) {
                $title_str = "差异管理";
                $task_str = "confirmDifference";
            } elseif ($status == 4) {
                $title_str = "erp开单";
                $task_str = "stayOpenBill";
            } elseif ($status == 3) {
                $title_str = "核价管理";
                $task_str = "alreadyPricing";
            }
            $user_id = $loginUserInfo->id;
            $common_model = new CommonModel();
            $common_model->updateSysTaskStatus($real_purchase_sn, $user_id, $task_str);

            //记录日志
            if ($status == 6 || $status == 1) {
                $title_str = "差异管理";
            } elseif ($status == 2 || $status == 4) {
                $title_str = "erp开单";
            } elseif ($status == 3) {
                $title_str = "核价管理";
            }
            $operateLogModel = new OperateLogModel();
            $logData = [
                'table_name' => 'jms_operate_log',
                'bus_desc' => $title_str . '-改变批次状态-实采批次单号：' . $real_purchase_sn . '-status改为：' . $status,
                'bus_value' => $real_purchase_sn,
                'admin_name' => trim($loginUserInfo->user_name),
                'admin_id' => trim($loginUserInfo->id),
                'ope_module_name' => $title_str . '-改变批次状态',
                'module_id' => 2,
                'have_detail' => 0,
            ];
            $operateLogModel->insertMoreLog($logData);
            return $update_res;
        });
        return $updateRes;
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function createDetailData($realPurchaseData, $batch_detail_info)
    {
        $real_purchase_sn = $realPurchaseData["real_purchase_sn"];
        $real_purchase_detail_info = $batch_detail_info['goods_list'];
        foreach ($real_purchase_detail_info as $k => $v) {
            $diff_num = $v['diff_num'];
            $purchaseDetailData[] = [
                "real_purchase_sn" => $real_purchase_sn,
                "purchase_sn" => $v['purchase_sn'],
                "goods_name" => $v['goods_name'],
                "erp_prd_no" => $v['erp_prd_no'],
                "erp_merchant_no" => $v['erp_merchant_no'],
                "spec_sn" => $v['spec_sn'],
                "day_buy_num" => $diff_num,
                "allot_num" => $diff_num
            ];
        }
        return $purchaseDetailData;
    }

    /**
     * description:增加商品备注
     * editor:zongxing
     * params: 1.实采批次单号:real_purchase_sn;2.商品规格码:spec_sn;3.商品备注:purchase_remark;
     * date : 2018.07.10
     * return Object
     */
    public function addDiffRemark($request, $batch_info)
    {
        $real_purchase_sn = $batch_info["real_purchase_sn"];
        $spec_sn = $batch_info["spec_sn"];
        $updateRes = DB::table("real_purchase_detail")
            ->where("real_purchase_sn", $real_purchase_sn)
            ->where("spec_sn", $spec_sn)
            ->update($batch_info);

        if (!$updateRes) {
            return false;
        }
        //记录日志
        $purchase_remark = $batch_info["purchase_remark"];
        $operateLogModel = new OperateLogModel();
        $loginUserInfo = $request->user();
        $logData = [
            'table_name' => 'jms_operate_log',
            'bus_desc' => '确认差异-增加商品备注-实采批次单号：' . $real_purchase_sn . '商品规格码：' . $spec_sn .
                '-采购部备注改为：' . $purchase_remark,
            'bus_value' => $real_purchase_sn,
            'admin_name' => trim($loginUserInfo->user_name),
            'admin_id' => trim($loginUserInfo->id),
            'ope_module_name' => '确认差异-增加商品备注',
            'module_id' => 2,
            'have_detail' => 0,
        ];
        $operateLogModel->insertMoreLog($logData);
        return true;
    }


    /**
     * description:采购单-批次单-分货列表
     * editor:zhangdong
     * date : 2018.10.27
     * return Object
     */
    public function pur_real_list($params, $start_str, $page_size)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        $where = [];
        $orWhere = [];
        if ($keywords) {
            $where = [
                ['real_purchase_sn', 'LIKE', "%$keywords%"],
            ];
            $orWhere = [
                ['purchase_sn', 'LIKE', "%$keywords%"],
            ];
        }
        $field = [
            'real_purchase_sn', 'purchase_sn', 'path_way', 'port_id', 'status', 'delivery_time',
            'arrive_time', 'allot_time', 'diff_time', 'billing_time', 'storage_time', 'is_setting',
            'is_sort', 'create_time', 'batch_cat'
        ];
        $queryRes = DB::table('real_purchase')->select($field)
            ->where($where)->orWhere($orWhere)->orderBy("create_time", "desc")->get();
        $listData = [];
        $returnMsg = [
            'listData' => $listData,
            'total_num' => $queryRes->count(),
        ];
        if ($queryRes->count() == 0) return $returnMsg;
        $groupData = $arrPurchaseSn = [];
        //查询对应数据的含义描述并组装数据
        foreach ($queryRes as $key => $value) {
            $value->path_way = $this->path_way[intval($value->path_way)];
            $value->port_id = $this->port_id[intval($value->port_id)];
            $value->status = intval($value->status);
            $value->status_desc = $this->status[intval($value->status)];
            $value->sort_desc = $this->is_sort[intval($value->is_sort)];
            $value->is_setting = $this->is_setting[intval($value->is_setting)];
            $value->batch_cat_desc = $this->batch_cat[intval($value->batch_cat)];
            $purchase_sn = trim($value->purchase_sn);
            unset($value->purchase_sn);
            $pur_sn = session('pur_sn');
            if (is_null($pur_sn)) {
                session(['pur_sn' => $purchase_sn]);
                $pur_sn = session('pur_sn');
            }
            if ($pur_sn == $purchase_sn) {
                $groupData[$pur_sn][] = $value;
            } else {
                session(['pur_sn' => $purchase_sn]);
                $groupData[$purchase_sn][] = $value;
            }
            $arrPurchaseSn[] = $purchase_sn;
        }
        $pdModel = new PurchaseDateModel();
        //根据一组采购期单号查询其采购单信息
        $purchaseDataInfo = $pdModel->queryPurchaseData(array_unique($arrPurchaseSn));
        $arrData = objectToArray($purchaseDataInfo);
        $purchaseInfo = [];
        foreach ($groupData as $key => $v) {
            $purchaseSn = $key;
            //查询采购期信息
            $searchRes = searchTwoArray($arrData, $purchaseSn, 'purchase_sn');
            if (count($searchRes) > 0) {
                $purchaseInfo = $searchRes[0];
            }
            $listData[] = [
                'purchase_info' => $purchaseInfo,
                'real_data' => $v,
            ];
        }
        $total_num = count($listData);
        $listData = array_slice($listData, $start_str, $page_size);
        $returnMsg = [
            'listData' => $listData,
            'total_num' => $total_num,
        ];
        return $returnMsg;
    }


    /**
     * description:根据需求单号获取实采批次号
     * editor:zhangdong
     * date : 2018.12.19
     */
    public function queryDemand($value, $type = 1)
    {
        if ($type == 1) {
            $where = [
                ['rp.demand_sn', $value],
                ['rp.batch_cat', 2],
            ];
        }
        if ($type == 2) $where[] = ['rp.real_purchase_sn', $value];
        if ($type == 3) $where[] = ['rp.purchase_sn', $value];
        $queryRes = DB::table('real_purchase as rp')->select($this->field)
            ->where($where)->first();
        return $queryRes;
    }

    /**
     * description:根据采购单号和实采单号查询是否有对应信息
     * editor:zongxing
     * date : 2018.12.28
     * return Object
     */
    public function isBatchNull($batch_info)
    {
        $batch_cat = intval($batch_info['batch_cat']);
        $where = [
            ['group_sn', '=', trim($batch_info['group_sn'])],
            ['batch_cat', '=', $batch_cat],
            ['status', '=', 1],
        ];
        //针对自提的批次，查询条件有区别，只用提货日和到货日
//        if ($path_way == 0 && $method_name != '外采') {
//            $where = [
//                ['purchase_sn', '=', trim($batch_info['purchase_sn'])],
//                ['delivery_time', '=', trim($batch_info['delivery_time'])],
//                ['arrive_time', '=', trim($batch_info['arrive_time'])],
//                ['status', '=', 1],
//            ];
//        }
        $real_purchase_info = DB::table('real_purchase')->where($where)
            ->first(['real_purchase_sn']);
        $real_purchase_info = objectToArrayZ($real_purchase_info);
        return $real_purchase_info;
    }

    /**
     * description:根据批次单号查询是否已经设置了批次
     * editor:zongxing
     * date : 2018.12.28
     * return Object
     */
    public function getRealPurchaseByRealSn($real_purchase_sn, $group_sn, $is_mother)
    {
        $where = [
            'real_purchase_sn' => $real_purchase_sn,
            'group_sn' => $group_sn,
            'is_mother' => $is_mother,
        ];
        $real_purchase_info = DB::table('real_purchase')
            ->where($where)
            ->first(['real_purchase_sn', 'group_sn', 'is_setting', 'is_set_post']);
        $real_purchase_info = objectToArrayZ($real_purchase_info);
        return $real_purchase_info;
    }

    /**
     * description:设置批次运费
     * editor:zongxing
     * date : 2019.01.03
     * return Object
     */
    public function updateRealPurchasePost($where, $post_info)
    {
        $updateRes = DB::table("real_purchase")->where($where)->update($post_info);
        return $updateRes;
    }

    /**
     * description:获取采购批次信息
     * editor:zongxing
     * type:POST
     * params: 1.实采批次单号:real_purchase_sn;
     * date : 2019.01.11
     * return Array
     */
    public function getBatchInfo($real_purchase_sn)
    {
        $real_purchase_info = DB::table('real_purchase as rp')
            ->leftJoin('erp_house as eh', 'eh.store_id', '=', 'rp.port_id')
            ->where('real_purchase_sn', $real_purchase_sn)->first();
        $real_purchase_info = objectToArrayZ($real_purchase_info);
        return $real_purchase_info;
    }

    /**
     * description:获取采购批次港口信息
     * editor:zongxing
     * params: 1.实采批次港口id:$port_id;
     * date : 2019.02.18
     * return String
     */
    public function getPortSn($port_id)
    {
        $port_sn_info = $this->port_sn;
        if (!isset($port_sn_info[$port_id])) {
            return false;
        }
        $port_sn = $port_sn_info[$port_id];
        return $port_sn;
    }

    /**
     * description:获取根据实采单号获取实采单状态
     * author:zhangdong
     * date : 2019.02.19
     */
    public function getRealPurchaseStatus($real_purchase_sn)
    {
        $where = [
            ['real_purchase_sn', $real_purchase_sn],
        ];
        $field = ['status'];
        $queryRes = DB::table($this->table)->select($field)->where($where)->first();
        if (is_null($queryRes)) {
            return false;
        }
        $status = intval($queryRes->status);
        return $status;

    }

    /**
     * description:回滚可分货数据
     * author:zhangdong
     * date : 2019.03.06
     */
    public function rollbackIsSort($realPurchaseSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $update = [
            'is_sort' => 0,
        ];
        $executeRes = DB::table($this->table)->where($where)->update($update);
        return $executeRes;
    }

    /**
     * description:获取根据实采单号获取实采单信息
     * author:zhangdong
     * date : 2019.03.12
     */
    public function getRealPurchaseInfo($real_purchase_sn)
    {
        $where = [
            ['real_purchase_sn', $real_purchase_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;

    }

    /**
     * description:获取根据实采单号获取批次类别的值
     * author:zhangdong
     * date : 2019.03.20
     */
    public function getBatchCatValue($real_purchase_sn)
    {
        $where = [
            ['real_purchase_sn', $real_purchase_sn],
        ];
        $field = ['batch_cat'];
        $queryRes = DB::table($this->table)->select($field)->where($where)->first();
        $batch_cat = intval($queryRes->batch_cat);
        return $batch_cat;

    }

    /**
     * description:修改实采单分货状态
     * author:zhangdong
     * date : 2019.04.09
     */
    public function updateIsSort($real_purchase_sn, $is_sort_num)
    {
        $where = [
            ['real_purchase_sn', $real_purchase_sn],
        ];
        //修改实采单状态为已分货
        $realUpdate = ['is_sort' => $is_sort_num];
        $executeRes = DB::table('real_purchase')->where($where)->update($realUpdate);
        return $executeRes;

    }

    /**
     * description:新增批次
     * author:zongxing
     * date : 2019.04.11
     */
    public function addBatchInfo($batch_info, $batch_goods_info, $sdg_list, $sdcg_list, $sg_list)
    {
        $real_purchase_sn = $this->realPurchaseSn();//新批次单号
        $batch_cat = intval($batch_info['batch_cat']);
        $realPurchaseData = [
            'group_sn' => trim($batch_info['group_sn']),
            'real_purchase_sn' => $real_purchase_sn,
            'purchase_sn' => trim($batch_info['purchase_sn']),
            'method_id' => intval($batch_info['method_id']),
            'channels_id' => intval($batch_info['channels_id']),
            'path_way' => intval($batch_info['path_way']),
            'port_id' => intval($batch_info['port_id']),
            'user_id' => intval($batch_info['user_id']),
            'supplier_id' => intval($batch_info['supplier_id']),
            'batch_cat' => $batch_cat,
            'delivery_time' => trim($batch_info['delivery_time']),
            'arrive_time' => trim($batch_info['arrive_time']),
            'buy_time' => trim($batch_info['buy_time']),
            'task_id' => intval($batch_info['task_id']),
            'original_or_discount' => intval($batch_info['original_or_discount']),
        ];
        //组装采购数据详情表数据和统计表sql
        $detail_and_count_info = $this->createBatchDetailData($batch_goods_info, $sdg_list, $real_purchase_sn,
            $sdcg_list, $sg_list);
        $real_purchase_sn = $batch_info['real_purchase_sn'];//待审核批次单号
        $res = DB::transaction(function () use (
            $realPurchaseData, $detail_and_count_info, $real_purchase_sn,
            $batch_info, $batch_cat
        ) {
            //实时采购数据增加到实时采购数据表
            if (!empty($realPurchaseData)) {
                DB::table('real_purchase')->insert($realPurchaseData);
            }
            //实时采购数据增加到实时采购数据详情表
            if (!empty($detail_and_count_info["detail_data"])) {
                DB::table('real_purchase_detail')->insert($detail_and_count_info["detail_data"]);
            }
            //新增批次任务
            $data_model = new DataModel();
            $data_model->addTaskInfo($realPurchaseData);
            //更新汇总需求单商品表
            if (!empty($detail_and_count_info['sdg_sql'])) {
                DB::update(DB::raw($detail_and_count_info['sdg_sql']));
            }
            //更新汇总商品任务分配表
            if (!empty($detail_and_count_info['sdcg_sql'])) {
                DB::update(DB::raw($detail_and_count_info['sdcg_sql']));
            }
            //更新常备商品表
            if (!empty($detail_and_count_info['sg_sql'])) {
                DB::update(DB::raw($detail_and_count_info['sg_sql']));
            }
            $update_ba_info = ['status' => 3];
            //更新批次审核表提货时间
            $res = DB::table('real_purchase_audit')->where('real_purchase_sn', $real_purchase_sn)->update($update_ba_info);
            return $res;
        });
        return $res;
    }

    /**
     * description 生成批次单号
     * author zongxing
     * date 2019.08.29
     * return String
     */
    public function realPurchaseSn()
    {
        do {
            $strNum = date('Ymd', time()) . '-' . rand(100, 999);
            $real_purchase_sn = 'PC-' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table($this->table)
                ->where([
                    ['real_purchase_sn', '=', $real_purchase_sn]
                ])->count();
        } while ($count);
        return $real_purchase_sn;
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function createBatchDetailData($batch_goods_info, $sdg_list, $real_purchase_sn, $sdcg_list, $sg_list)
    {
        $realPurchaseInsertArr = [];
        $purchaseChannelInsertArr = [];
        $updateSumDemandGoods = [];
        foreach ($batch_goods_info as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            $purchase_sn = trim($v['purchase_sn']);
            $channel_discount = floatval($v['channel_discount']);
            $real_discount = floatval($v['real_discount']);
            $spec_price = floatval($v['spec_price']);
            $lvip_price = floatval($v['lvip_price']);
            $pay_price = floatval($v['pay_price']);
            $day_buy_num = intval($v['day_buy_num']);
            $realPurchaseInsertArr[] = [
                'real_purchase_sn' => $real_purchase_sn,
                'purchase_sn' => $purchase_sn,
                'goods_name' => trim($v['goods_name']),
                'erp_prd_no' => trim($v['erp_prd_no']),
                'erp_merchant_no' => trim($v['erp_merchant_no']),
                'spec_sn' => $spec_sn,
                'spec_price' => $spec_price,
                'lvip_price' => $lvip_price,
                'pay_price' => $pay_price,
                'day_buy_num' => $day_buy_num,
                'sort_num' => $day_buy_num,
                'allot_num' => $day_buy_num,
                'channel_discount' => $channel_discount,
                'real_discount' => $real_discount,
                'is_match' => intval($v['is_match']),
                'parent_spec_sn' => trim($v['parent_spec_sn'])
            ];
            //汇总需求单商品表
            if (isset($sdg_list[$spec_sn])) {
                $id = $sdg_list[$spec_sn]['id'];
                $updateSumDemandGoods['real_num'][] = [
                    $id => 'real_num + ' . $day_buy_num
                ];
            }
            //汇总商品任务分配表
            if (isset($sdcg_list[$spec_sn])) {
                $id = $sdcg_list[$spec_sn]['id'];
                $updateSdcGoods['real_num'][] = [
                    $id => 'real_num + ' . $day_buy_num
                ];
            }
            //常备商品表
            if (isset($sg_list[$spec_sn])) {
                $id = $sg_list[$spec_sn]['id'];
                $updateSgGoods['available_num'][] = [
                    $id => 'available_num + ' . $day_buy_num
                ];
                //判断是否超采
                $max_num = intval($sg_list[$spec_sn]['max_num']);
                $available_num = intval($sg_list[$spec_sn]['available_num']);
                $final_num = $available_num + $day_buy_num;
                $is_purchase = 1;
                if ($final_num >= $max_num) {
                    $is_purchase = 0;
                }
                $updateSgGoods['is_purchase'][] = [
                    $id => $is_purchase
                ];
            }
        }

        $updateSumDemandGoodsSql = '';
        if (!empty($updateSumDemandGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateSumDemandGoodsSql = makeBatchUpdateSql('jms_sum_goods', $updateSumDemandGoods, $column);
        }
        $updateSdcGoodsSql = '';
        if (!empty($updateSdcGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateSdcGoodsSql = makeBatchUpdateSql('jms_sum_demand_channel_goods', $updateSdcGoods, $column);
        }
        $updateSgGoodsSql = '';
        if (!empty($updateSgGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateSgGoodsSql = makeBatchUpdateSql('jms_standby_goods', $updateSgGoods, $column);
        }
        $return_info["detail_data"] = $realPurchaseInsertArr;
        $return_info["channel_insert_arr"] = $purchaseChannelInsertArr;
        $return_info['sdg_sql'] = $updateSumDemandGoodsSql;
        $return_info['sdcg_sql'] = $updateSdcGoodsSql;
        $return_info['sg_sql'] = $updateSgGoodsSql;
        return $return_info;
    }

    /**
     * description:更新批次
     * author:zongxing
     * date : 2019.04.11
     */
    public function updateBatchInfo($batch_info, $batch_goods_info, $rp_info, $sdg_list, $sdcg_list, $sg_list)
    {
        //需要更新的批次单号
        $purchase_sn = $batch_info['purchase_sn'];
        $real_purchase_sn = $rp_info['real_purchase_sn'];
        //组装采购数据详情表sql和统计表sql
        $detail_and_count_info = $this->createDetailSql($purchase_sn, $real_purchase_sn, $batch_goods_info, $sdg_list,
            $sdcg_list, $sg_list);
        $real_purchase_audit_sn = $batch_info['real_purchase_sn'];
        $updateRes = DB::transaction(function () use (
            $detail_and_count_info, $real_purchase_sn, $real_purchase_audit_sn, $purchase_sn
        ) {
            //更新批次详情表
            if (!empty($detail_and_count_info["detail_sql"])) {
                DB::update(DB::raw($detail_and_count_info["detail_sql"]));
            }
            //新增批次详情表
            if (!empty($detail_and_count_info["real_purchase_insert_arr"])) {
                DB::table("real_purchase_detail")->insert($detail_and_count_info["real_purchase_insert_arr"]);
            }
            //更新汇总需求单商品表
            if (!empty($detail_and_count_info['sdg_sql'])) {
                DB::update(DB::raw($detail_and_count_info['sdg_sql']));
            }
            //更新汇总商品任务分配表
            if (!empty($detail_and_count_info['sdcg_sql'])) {
                DB::update(DB::raw($detail_and_count_info['sdcg_sql']));
            }
            //更新常备商品表
            if (!empty($detail_and_count_info['sg_sql'])) {
                DB::update(DB::raw($detail_and_count_info['sg_sql']));
            }
            //更新批次审核表状态
            $update_ba_info = ['status' => 3];
            $update_res = DB::table('real_purchase_audit')
                ->where('real_purchase_sn', $real_purchase_audit_sn)->update($update_ba_info);
            return $update_res;
        });
        return $updateRes;
    }

    /**
     * description:组装采购数据详情表数据和统计表sql
     * editor:zongxing
     * date : 2018.07.12
     * return Object
     */
    public function createDetailSql($purchase_sn, $real_purchase_sn, $batch_goods_info, $sdg_list, $sdcg_list, $sg_list)
    {
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
        $updateRealPurchaseGoods = [];
        $updateSumDemandGoods = [];
        foreach ($batch_goods_info as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            $day_buy_num = intval($v['day_buy_num']);
            $channel_discount = floatval($v['channel_discount']);
            $real_discount = floatval($v['real_discount']);
            $pay_price = floatval($v['pay_price']);
            $spec_price = floatval($v['spec_price']);
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
                $updateRealPurchaseGoods['channel_discount'][] = [
                    $real_purchase_goods_id => $channel_discount
                ];
                $updateRealPurchaseGoods['spec_price'][] = [
                    $real_purchase_goods_id => $spec_price
                ];
                $updateRealPurchaseGoods['real_discount'][] = [
                    $real_purchase_goods_id => $real_discount
                ];
            } else {
                $realPurchaseInsertArr[] = [
                    'real_purchase_sn' => $real_purchase_sn,
                    'purchase_sn' => $purchase_sn,
                    'goods_name' => trim($v['goods_name']),
                    'erp_prd_no' => trim($v['erp_prd_no']),
                    'erp_merchant_no' => trim($v['erp_merchant_no']),
                    'spec_sn' => $spec_sn,
                    'spec_price' => $spec_price,
                    'pay_price' => $pay_price,
                    'day_buy_num' => $day_buy_num,
                    'sort_num' => $day_buy_num,
                    'allot_num' => $day_buy_num,
                    'channel_discount' => $channel_discount,
                    'real_discount' => $real_discount,
                    'is_match' => intval($v['is_match']),
                    'parent_spec_sn' => trim($v['parent_spec_sn'])
                ];
            }
            //汇总需求单商品表
            if (isset($sdg_list[$spec_sn])) {
                $id = $sdg_list[$spec_sn]['id'];
                $updateSumDemandGoods['real_num'][] = [
                    $id => 'real_num + ' . $day_buy_num
                ];
            }
            //汇总商品任务分配表
            if (isset($sdcg_list[$spec_sn])) {
                $id = $sdcg_list[$spec_sn]['id'];
                $updateSdcGoods['real_num'][] = [
                    $id => 'real_num + ' . $day_buy_num
                ];
            }
            //常备商品表
            if (isset($sg_list[$spec_sn])) {
                $id = $sg_list[$spec_sn]['id'];
                $updateSgGoods['available_num'][] = [
                    $id => 'available_num + ' . $day_buy_num
                ];
                //判断是否超采
                $max_num = intval($sg_list[$spec_sn]['max_num']);
                $available_num = intval($sg_list[$spec_sn]['available_num']);
                $final_num = $available_num + $day_buy_num;
                $is_purchase = 1;
                if ($final_num >= $max_num) {
                    $is_purchase = 0;
                }
                $updateSgGoods['is_purchase'][] = [
                    $id => $is_purchase
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
        $updateSumDemandGoodsSql = '';
        if (!empty($updateSumDemandGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateSumDemandGoodsSql = makeBatchUpdateSql('jms_sum_goods', $updateSumDemandGoods, $column);
        }
        $updateSdcGoodsSql = '';
        if (!empty($updateSdcGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateSdcGoodsSql = makeBatchUpdateSql('jms_sum_demand_channel_goods', $updateSdcGoods, $column);
        }
        $updateSgGoodsSql = '';
        if (!empty($updateSgGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateSgGoodsSql = makeBatchUpdateSql('jms_standby_goods', $updateSgGoods, $column);
        }

        $return_info['detail_sql'] = $updateRealPurchaseGoodsSql;
        $return_info['real_purchase_insert_arr'] = $realPurchaseInsertArr;
        $return_info['sdg_sql'] = $updateSumDemandGoodsSql;
        $return_info['sdcg_sql'] = $updateSdcGoodsSql;
        $return_info['sg_sql'] = $updateSgGoodsSql;
        return $return_info;
    }

    /**
     * description:批次单列表
     * author:zhangdong
     * date : 2019.05.30
     */
    public function getBatchOrderList($params, $start_str, $page_size)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        $where[] = ['type', 2];
        $orWhere = [];
        if ($keywords) {
            $where[] = ['real_purchase_sn', 'LIKE', "%$keywords%"];
            $orWhere = [
                ['purchase_sn', 'LIKE', "%$keywords%"],
            ];
        }
        $field = [
            'real_purchase_sn', 'purchase_sn', 'path_way', 'port_id', 'status', 'delivery_time',
            'arrive_time', 'allot_time', 'diff_time', 'billing_time', 'storage_time', 'is_setting',
            'is_sort', 'create_time', 'batch_cat'
        ];
        $queryRes = DB::table('real_purchase')->select($field)
            ->where($where)->orWhere($orWhere)->orderBy("create_time", "desc")->get();
        $listData = [];
        $returnMsg = [
            'listData' => $listData,
            'total_num' => $queryRes->count(),
        ];
        if ($queryRes->count() == 0) return $returnMsg;
        $groupData = $arrPurchaseSn = [];
        //查询对应数据的含义描述并组装数据
        $sbModel = new SortBatchModel();
        $rpdModel = new RealPurchaseDetailModel();
        foreach ($queryRes as $key => $value) {
            $value->path_way = $this->path_way[intval($value->path_way)];
            $value->port_id = $this->port_id[intval($value->port_id)];
            $value->status = intval($value->status);
            $value->status_desc = $this->status[intval($value->status)];
            $value->sort_desc = $this->is_sort[intval($value->is_sort)];
            $value->is_setting = $this->is_setting[intval($value->is_setting)];
            $value->batch_cat_desc = $this->batch_cat[intval($value->batch_cat)];
            $purchase_sn = trim($value->purchase_sn);
            //查询批次分货记录条数
            $realSn = trim($value->real_purchase_sn);
            $value->batchSortCount = $sbModel->countNumByBatch($purchase_sn, $realSn);
            $value->canSortNum = $rpdModel->countSortNum($realSn);
            unset($value->purchase_sn);
            $pur_sn = session('pur_sn');
            if (is_null($pur_sn)) {
                session(['pur_sn' => $purchase_sn]);
                $pur_sn = session('pur_sn');
            }
            if ($pur_sn == $purchase_sn) {
                $groupData[$pur_sn][] = $value;
            } else {
                session(['pur_sn' => $purchase_sn]);
                $groupData[$purchase_sn][] = $value;
            }
            $arrPurchaseSn[] = $purchase_sn;
        }
        $sumModel = new SumModel();
        //根据一组采购期单号查询其采购单信息
        $purchaseDataInfo = $sumModel->querySumInfo(array_unique($arrPurchaseSn));
        $sodModel = new SortDataModel();
        $arrData = objectToArray($purchaseDataInfo);
        foreach ($groupData as $key => $v) {
            $purchaseSn = $key;
            //查询采购期信息
            $searchRes = searchTwoArray($arrData, $purchaseSn, 'sum_demand_sn');
            if (count($searchRes) <= 0) {
                continue;
            }
            //查询分货数据是否生成，控制前端按钮的切换
            $countSortData = $sodModel->countSortData($purchaseSn);
            $purchaseInfo = $searchRes[0];
            $listData[] = [
                'purchase_info' => $purchaseInfo,
                'countSortData' => $countSortData,
                'real_data' => $v,
            ];
        }
        $total_num = count($listData);
        $listData = array_slice($listData, $start_str, $page_size);
        $returnMsg = [
            'listData' => $listData,
            'total_num' => $total_num,
        ];
        return $returnMsg;
    }

    /**
     * description:查询批次单基本信息
     * author:zhangdong
     * date : 2019.05.31
     */
    public function queryBatchInfo($realPurchaseSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description 检查批次单是否是当前合单下的数据
     * author zhangdong
     * date 2019.07.03
     */
    public function countSumRealSn($sumDemandSn, $realPurchaseSn)
    {
        $where = [
            ['purchase_sn', $sumDemandSn],
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }


}//end of class
