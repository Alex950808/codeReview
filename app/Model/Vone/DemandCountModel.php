<?php

namespace App\Model\Vone;

use App\Modules\Erp\ErpRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DemandCountModel extends Model
{
    protected $demand_count = "demand_count";//商品需求统计表
    protected $user_goods = "user_goods";//用户商品需求表
    protected $sale_user = "sale_user";//销售用户表


    //批次单港口
    public $port = [
        '1001' => '黑匣子仓',
        '1002' => '保税-日韩开架仓',
        '1003' => '西安仓',
    ];

    /**
     * description:构造实时分配预测数据
     * editor:zhangdong
     * date : 2018.07.05
     * return Object
     */
    public function getGoodsAllot($purchase_sn)
    {
        $selectFields = "erp_merchant_no,spec_sn,goods_name,goods_num,may_buy_num,real_buy_num";
        $where = [
            ['purchase_sn', $purchase_sn],
        ];
        $goodsInfo = DB::table($this->demand_count)->selectRaw($selectFields)->where($where)->get();
        foreach ($goodsInfo as $key => $value) {
            $spec_sn = $value->spec_sn;
            $userWhere = [
                [DB::Raw('ug.purchase_sn'), $purchase_sn],
                [DB::Raw('ug.spec_sn'), $spec_sn],
            ];
            //组装用户商品需求数据
            $userGoodsField = 'ug.erp_merchant_no,ug.spec_sn,su.user_name,SUM(ug.goods_num) AS totalNum';
            $userGoods = DB::table(DB::Raw('jms_user_goods as ug'))->selectRaw(DB::Raw($userGoodsField))
                ->leftJoin(DB::Raw('jms_sale_user AS su'), DB::Raw('su.id'), '=', DB::Raw('ug.sale_user_id'))
                ->groupBy(DB::Raw('ug.sale_user_id'))
                ->where($userWhere)->get();
            //计算预测数
            $real_buy_num = intval($value->real_buy_num);//已采数
            $goods_num = intval($value->goods_num);//商品总需求
            foreach ($userGoods as $i => $item) {
                //预测分配数 = 已采数*用户需求数/商品总需求
                $userNeedNum = intval($item->totalNum);//用户需求数
                $mayAllotNum = intval(round($real_buy_num * $userNeedNum / $goods_num));
                $userName = $item->user_name;
                $goodsInfo[$key]->$userName = $userNeedNum;
                $may_allot_num = '预测数' . $userName;
                $goodsInfo[$key]->$may_allot_num = $mayAllotNum;
            }
        }
        return $goodsInfo;
    }

    /**
     * description:构造实时分配预测数据
     * editor:zhangdong
     * date : 2018.07.05
     * return Object
     */
    public function getGoodsAllotReal($post_purchase_info)
    {
        $real_purchase_sn = trim($post_purchase_info['real_purchase_sn']);
        $purchase_sn = trim($post_purchase_info['purchase_sn']);

        $sql_demand_count = " SELECT erp_merchant_no,spec_sn,goods_name,goods_num,may_buy_num,real_buy_num 
                    FROM jms_demand_count
                    WHERE purchase_sn = '" . $purchase_sn . "'";
        if (!empty($post_purchase_info['query_sn'])) {
            $query_sn = $post_purchase_info['query_sn'];
            $query_sn = "%" . $query_sn . "%";
            $sql_demand_count .= " AND goods_name LIKE '" . $query_sn . "' OR spec_sn LIKE '" . $query_sn . "'
            OR erp_merchant_no LIKE '" . $query_sn . "' OR erp_prd_no LIKE '" . $query_sn . "'";
        }

        $goodsInfo = DB::select(DB::raw($sql_demand_count));
        $goodsInfo = objectToArrayZ($goodsInfo);

        $sql_real_goods = " SELECT spec_sn,day_buy_num,allot_num,rpd.real_purchase_sn,goods_name,erp_merchant_no 
                    FROM jms_real_purchase_detail as rpd 
                    LEFT JOIN jms_real_purchase as rp ON rp.real_purchase_sn = rpd.real_purchase_sn 
                    WHERE rp.purchase_sn = '" . $purchase_sn . "' AND  rp.real_purchase_sn = '" . $real_purchase_sn . "' ";

        $status = intval($post_purchase_info['status']);
        if ($status == 1) {
            $sql_real_goods .= " AND rp.status = 1 ";
        } else if ($status == 2) {
            $sql_real_goods .= " AND rp.status >= 2 ";
        }

        if (!empty($post_purchase_info['query_sn'])) {
            $query_sn = $post_purchase_info['query_sn'];
            $query_sn = "%" . $query_sn . "%";
            $sql_real_goods .= " AND (goods_name LIKE '" . $query_sn . "' OR spec_sn LIKE '" . $query_sn . "'
            OR erp_merchant_no LIKE '" . $query_sn . "' OR erp_prd_no LIKE '" . $query_sn . "')";
        }

        $real_goods_info = DB::select(DB::raw($sql_real_goods));
        $real_goods_info = json_decode(json_encode($real_goods_info), true);

        if (!empty($real_goods_info)) {
            $goods_total_list = [];
            foreach ($goodsInfo as $k => $v) {
                $spec_sn = $v["spec_sn"];
                $goods_total_list[$spec_sn] = $v;
            }

            foreach ($real_goods_info as $k => $v) {
                $spec_sn = $v["spec_sn"];
                $userWhere = [
                    [DB::Raw('ug.purchase_sn'), $purchase_sn],
                    [DB::Raw('ug.spec_sn'), $spec_sn],
                ];
                //组装用户商品需求数据
                $userGoodsField = 'ug.erp_merchant_no,ug.spec_sn,su.user_name,SUM(ug.goods_num) AS totalNum';
                $userGoods = DB::table(DB::Raw('jms_user_goods as ug'))->selectRaw(DB::Raw($userGoodsField))
                    ->leftJoin(DB::Raw('jms_sale_user AS su'), DB::Raw('su.id'), '=', DB::Raw('ug.sale_user_id'))
                    ->groupBy(DB::Raw('ug.sale_user_id'))
                    ->where($userWhere)->get();
                $userGoods = objectToArrayZ($userGoods);

                if ($status == 1) {
                    $real_buy_num = intval($v["day_buy_num"]);//已采数
                } else if ($status == 2) {
                    $real_buy_num = intval($v["allot_num"]);//清点过的已采数
                }

                if ($status == 1) {
                    $real_buy_num = $v["day_buy_num"];//已采数
                } else if ($status == 2) {
                    $real_buy_num = $v["allot_num"];//清点过的已采数
                }


                $goods_num = intval($goods_total_list[$spec_sn]["goods_num"]);//商品总需求
                foreach ($userGoods as $i => $item) {
                    //预测分配数 = 已采数*用户需求数/商品总需求
                    $userNeedNum = intval($item["totalNum"]);//用户需求数
                    $mayAllotNum = intval(round($real_buy_num * $userNeedNum / $goods_num));

                    if ($mayAllotNum < 0) {
                        $mayAllotNum = 0;
                    }
                    $userName = $item["user_name"];
                    $real_goods_info[$k][$userName] = $userNeedNum;
                    $may_allot_num = '预测数,' . $userName;
                    $real_goods_info[$k][$may_allot_num] = $mayAllotNum;
                }
            }
        }
        return $real_goods_info;
    }

    /**
     * description:获取采购数据列表
     * editor:zongxing
     * date : 2018.07.06
     * return Object
     */
    public function getDataList($purchase_info)
    {
        $where = [];
        if (isset($purchase_info['query_sn'])) {
            $query_sn = trim($purchase_info['query_sn']);
            $where[] = ['dc.purchase_sn', '=', $query_sn];
        }
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;

        //获取预采数据
        $where1 = [
            ['rp.batch_cat', 2]
        ];
        $fields = [
            'pd.id as purchase_id', 'pd.delivery_time', 'rp.purchase_sn', DB::raw('SUM(jms_rpd.allot_num) as allot_num')
        ];
        $predict_goods_info = DB::table("real_purchase_detail as rpd")
            ->select($fields)
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rp.purchase_sn')
            ->where($where1)
            ->groupBy('rp.purchase_sn')
            ->paginate($page_size);
        $predict_goods_info = objectToArrayZ($predict_goods_info);

        //获取采购期统计表信息
        $whereIn = [1, 2];
        $fields = [
            'pd.id as purchase_id', 'pd.delivery_time', 'dc.purchase_sn', DB::raw('SUM(jms_dc.goods_num) as goods_num'),
            DB::raw('SUM(jms_dc.may_buy_num) as may_buy_num'), DB::raw('SUM(jms_dc.real_buy_num) as real_buy_num')
        ];
        $purchase_goods_info = DB::table("demand_count as dc")
            ->select($fields)
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "dc.purchase_sn")
            ->where($where)
            ->whereIn('pd.status', $whereIn)
            ->groupBy('dc.purchase_sn')
            ->orderBy('pd.create_time', 'desc')
            ->paginate($page_size);
        $purchase_goods_info = objectToArrayZ($purchase_goods_info);
        $total_goods_info = [];
        if (empty($purchase_goods_info['data'])) {
            foreach ($predict_goods_info['data'] as $k => $v) {
                $purchase_sn = $v['purchase_sn'];
                $total_goods_info[$purchase_sn]['may_buy_num'] = 0;
                $total_goods_info[$purchase_sn]['real_buy_num'] = intval($v['allot_num']);
                $total_goods_info[$purchase_sn]['total_real_buy_num'] = intval($v['allot_num']);
                $total_goods_info[$purchase_sn]['predict_num'] = intval($v['allot_num']);
                //计算实际采满率
                $total_goods_info[$purchase_sn]['real_buy_rate'] = 0;
            }
            $purchase_goods_info['data'] = array_values($total_goods_info);
            return $purchase_goods_info;
        }
        $predict_goods_list = [];
        foreach ($predict_goods_info['data'] as $k => $v) {
            $predict_goods_list[$v['purchase_sn']] = $v;
        }

        foreach ($purchase_goods_info['data'] as $k => $v) {
            $purchase_sn = $v['purchase_sn'];
            //计算预采数
            $predict_goods_num = 0;
            $real_buy_num = $v['real_buy_num'];
            $v['total_real_buy_num'] = $real_buy_num;
            if (isset($predict_goods_list[$purchase_sn])) {
                $predict_goods_num = intval($predict_goods_list[$purchase_sn]['allot_num']);
                $v['total_real_buy_num'] += $predict_goods_num;
            }
            $v['predict_num'] = $predict_goods_num;
            //计算实际采满率
            $real_buy_rate = 0;
            $may_buy_num = intval($v['may_buy_num']);
            if ($may_buy_num == 0 && $real_buy_num > 0) {
                $real_buy_rate = 100;
            } elseif ($may_buy_num > 0 && $real_buy_num > 0) {
                $real_buy_rate = $real_buy_num / $may_buy_num * 100;
            }
            $real_buy_rate = round($real_buy_rate, 2);
            $v['real_buy_rate'] = $real_buy_rate;
            $total_goods_info[$purchase_sn] = $v;
        }
        $purchase_goods_info['data'] = array_values($total_goods_info);
        return $purchase_goods_info;
    }

    /**
     * description:数据管理——获取采购数据列表
     * editor:zongxing
     * date : 2018.08.06
     * return Object
     */
    public function getDataManagementList($purchase_info)
    {
        //计算总数
        $queryPurchaseSql = $this->create_query_data($purchase_info, false, false);
        $queryPurchaseNum = DB::select(DB::raw($queryPurchaseSql));
        $return_info["data_num"] = count($queryPurchaseNum);

        //组装查询条件
        $queryPurchaseRes = $this->create_query_data($purchase_info, false, true);
        $queryPurchaseRes = DB::select(DB::raw($queryPurchaseRes));

        foreach ($queryPurchaseRes as $k => $v) {
            //计算实际采满率
            $real_buy_rate = (((int)$v->real_buy_num) / ((int)$v->may_buy_num)) * 100;
            $real_buy_rate = round($real_buy_rate, 2);
            $queryPurchaseRes[$k]->real_buy_rate = $real_buy_rate;
        }
        $return_info["purchase_info"] = $queryPurchaseRes;
        return $return_info;
    }

    /**
     * description:组装采购期列表搜索条件
     * editor:zongxing
     * date : 2018.07.23
     * return String
     */
    public function create_query_data($purchase_info, $str = false, $sort = false)
    {
        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;

        $sql_query_purchase = "SELECT pd.id as purchase_id, dc.purchase_sn,sum(dc.goods_num) as goods_num,
                sum(dc.may_buy_num) as may_buy_num,sum(dc.real_buy_num) as real_buy_num 
                    FROM jms_demand_count as dc 
                    left JOIN jms_purchase_date as pd ON pd.purchase_sn = dc.purchase_sn
                    WHERE pd.status = 2 ";
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $sql_query_purchase .= "AND purchase_sn LIKE '" . $purchase_sn . "'";
        }
        $sql_query_purchase .= "GROUP BY dc.purchase_sn ";
        if ($sort == true) {
            $sql_query_purchase .= "ORDER BY pd.create_time DESC limit $start_str,$page_size";
        }
        return $sql_query_purchase;
    }

    /**
     * description:数据管理——获取采购数据列表
     * editor:zongxing
     * date : 2018.08.06
     * return Object
     */
    public function getDataGoodsList($goods_info)
    {
        //计算总数
        $queryPurchaseSql = $this->create_query_goods($goods_info, false);
        $queryPurchaseNum = DB::select(DB::raw($queryPurchaseSql));
        $return_info["data_num"] = count($queryPurchaseNum);

        //组装查询条件
        $queryGoods = $this->create_query_goods($goods_info, true);
        $queryPurchaseRes = DB::select(DB::raw($queryGoods));

        foreach ($queryPurchaseRes as $k => $v) {
            //计算实际采满率
            if ((int)$v->real_buy_num == 0 && (int)$v->may_buy_num == 0) {
                $queryPurchaseRes[$k]->real_buy_rate = 0;
            } elseif ((int)$v->real_buy_num == 0) {
                $queryPurchaseRes[$k]->real_buy_rate = 0;
            } elseif ((int)$v->may_buy_num == 0) {
                $queryPurchaseRes[$k]->real_buy_rate = 100;
            } else {
                $real_buy_rate = (((int)$v->real_buy_num) / ((int)$v->may_buy_num)) * 100;
                $real_buy_rate = round($real_buy_rate, 2);
                $queryPurchaseRes[$k]->real_buy_rate = $real_buy_rate;
            }

            //计算缺失率
            $queryPurchaseRes[$k]->miss_buy_rate = 100;
            if ((int)$v->may_buy_num) {
                $miss_buy_rate = (1 - (((int)$v->may_buy_num) / ((int)$v->goods_num))) * 100;
                $miss_buy_rate = round($miss_buy_rate, 2);
                $miss_buy_rate = $miss_buy_rate;
                $queryPurchaseRes[$k]->miss_buy_rate = $miss_buy_rate;
            }
        }
        $return_info["purchase_info"] = $queryPurchaseRes;
        return $return_info;
    }

    /**
     * description:组装采购期列表搜索条件
     * editor:zongxing
     * date : 2018.07.23
     * return String
     */
    public function create_query_goods($goods_info, $sort = false)
    {
        $start_page = isset($goods_info['start_page']) ? intval($goods_info['start_page']) : 1;
        $page_size = isset($goods_info['page_size']) ? intval($goods_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;

        $sql_query_goods = 'SELECT goods_name,spec_sn,erp_prd_no,erp_merchant_no,sum(goods_num) as goods_num,
                sum(may_buy_num) as may_buy_num,sum(real_buy_num) as real_buy_num FROM jms_demand_count WHERE 1=1 ';
        if (isset($goods_info['query_sn'])) {
            $query_sn = trim($goods_info['query_sn']);
            if (preg_match("/[\x7f-\xff]/", $query_sn)) {
                $query_sn = explode(" ", $query_sn);

                foreach ($query_sn as $k => $v) {
                    $sql_query_goods .= "AND goods_name LIKE '%" . $v . "%' ";
                }
            } else {
                $query_sn = "%" . $query_sn . "%";
                $sql_query_goods .= "AND (purchase_sn = '" . $query_sn . "' OR spec_sn LIKE '" . $query_sn . "' 
                            OR erp_prd_no LIKE '" . $query_sn . "' OR erp_merchant_no LIKE '" . $query_sn . "')";
            }
        }
        $sql_query_goods .= "GROUP BY goods_name ";
        if ($sort == true) {
            $sql_query_goods .= "ORDER BY create_time desc limit $start_str,$page_size";
        }
        return $sql_query_goods;
    }

    /**
     * description:获取一个采购单下所有待开单状态的实采单号中实际分配的商品数量（此时采购部已经确认完差异了）
     * editor:zhangdong
     * date : 2018.07.09
     * return Object
     */
    public function getGoodsRealAllot($purchase_sn)
    {
        $selectFields = "erp_merchant_no,spec_sn,goods_name,goods_num";
        $where = [
            ['purchase_sn', $purchase_sn],
        ];
        $realAllotGoodsInfo = DB::table($this->demand_count)->selectRaw($selectFields)->where($where)->get();
        foreach ($realAllotGoodsInfo as $key => $value) {
            $spec_sn = $value->spec_sn;
            $userWhere = [
                [DB::Raw('ug.purchase_sn'), $purchase_sn],
                [DB::Raw('ug.spec_sn'), $spec_sn],
            ];
            //组装用户商品需求数据
            $userGoodsField = 'ug.erp_merchant_no,ug.spec_sn,su.user_name,SUM(ug.goods_num) AS totalNum';
            $userGoods = DB::table(DB::Raw('jms_user_goods as ug'))->selectRaw(DB::Raw($userGoodsField))
                ->leftJoin(DB::Raw('jms_sale_user AS su'), DB::Raw('su.id'), '=', DB::Raw('ug.sale_user_id'))
                ->groupBy(DB::Raw('ug.sale_user_id'))
                ->where($userWhere)->get();
            //计算实际可分配数（采购单下所有待开单状态的商品的清点数之和）
            //根据采购单号和商品规格码查询待开单状态的实采单的商品清点总数
            $realAllotField = 'rpd.purchase_sn,rpd.real_purchase_sn,rpd.spec_sn,SUM(rpd.allot_num) AS totalAllotNum';
            $realAllotWhere = [
                [DB::Raw('rp.status'), 3],//待开单实购单
                [DB::Raw('rpd.purchase_sn'), $purchase_sn],
                [DB::Raw('rpd.spec_sn'), $spec_sn],
            ];
            $realAllotInfo = DB::table(DB::Raw('jms_real_purchase_detail as rpd'))->selectRaw($realAllotField)
                ->leftJoin(DB::Raw('jms_real_purchase AS rp'), DB::Raw('rp.real_purchase_sn'), '=', DB::Raw('rpd.real_purchase_sn'))
                ->where($realAllotWhere)->first();
            $real_allot_num = intval($realAllotInfo->totalAllotNum);//实际可分配数
            $goods_num = intval($value->goods_num);//商品总需求
            foreach ($userGoods as $i => $item) {
                //实际分配数 = 总实际分配数*用户需求数/商品总需求
                $userNeedNum = intval($item->totalNum);//用户需求数
                $realAllotNum = intval(round($real_allot_num * $userNeedNum / $goods_num));
                $userName = $item->user_name;
                $realAllotGoodsInfo[$key]->$userName = $userNeedNum;
                $real_allot_num = '预测数' . $userName;
                $realAllotGoodsInfo[$key]->$real_allot_num = $realAllotNum;
            }
        }
        return $realAllotGoodsInfo;
    }

    /**
     * description:获取批次列表(清点和入库)
     * editor:zongxing
     * type:GET
     * date : 2018.07.09 modify by zongxing 2018.12.12
     * return Object
     */
    public function getBatchList($param_info, $expire = 1, $request = '', $task_link = '')
    {
        //组装获取批次列表查询条件
        //$condition = $this->createBatchListCondition_stop($expire, $param_info);
        $condition = $this->createBatchListCondition($param_info);
        $is_check_erp = $condition['is_check_erp'];
        $where = $condition['where'];
        //获取采购期统计数据
        $rpd_model = new RealPurchaseDetailModel();
        $purchase_goods_info = $rpd_model->getPurchaseTotalDetail($where, $param_info);
        if (empty($purchase_goods_info)) {
            $return_info['purchase_info'] = [];
            return $return_info;
        }
        //组装采购期统计数据
        $purchaseData = $this->createPurchaseData($purchase_goods_info);
        $purchase_real_info = $purchaseData['purchase_real_info'];
        //组装批次统计数据
        $params = [
            'purchase_real_info' => $purchase_real_info,
            'real_goods_info' => $purchase_goods_info,
            'is_check_erp' => $is_check_erp,
        ];
        if (!empty($task_link)) {
            $batch_task_info = DB::table("batch_task as rpd")
                ->where('task_link', $task_link)
                ->where('status', 0)
                ->pluck('user_list', 'real_purchase_sn');
            $batch_task_info = objectToArrayZ($batch_task_info);
            $params['batch_task_info'] = $batch_task_info;

            $user_info = $request->user();
            $user_id = $user_info->id;
            $params['user_id'] = $user_id;
        }

        $batchData = $this->createBatchData($params);
        $total_info = $batchData['total_info'];
        $total_info = array_values($total_info);
        $return_info['purchase_info'] = $total_info;
        $return_info['data_num'] = count($total_info);
        return $return_info;
    }

    /**
     * description:获取批次列表(核价和开单)
     * editor:zongxing
     * type:GET
     * date : 2018.07.09 modify by zongxing 2018.12.12
     * return Object
     */
    public function getPriceBatchList($param_info, $expire = 1, $request = '', $task_link = '')
    {
        //组装获取批次列表查询条件
        //$condition = $this->createBatchListCondition_stop($expire, $param_info);
        $condition = $this->createBatchListCondition($param_info);
        $is_check_erp = $condition['is_check_erp'];
        $where = $condition['where'];
        //获取采购期统计数据
        $rpd_model = new RealPurchaseDetailModel();
        $purchase_goods_info = $rpd_model->getPurchaseTotalDetail($where, $param_info);
        if (empty($purchase_goods_info)) {
            $return_info['purchase_info'] = [];
            return $return_info;
        }
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $purchase_goods_info = array_slice($purchase_goods_info, $start_page, $page_size);

        //组装采购期统计数据
        $purchaseData = $this->createPurchaseData($purchase_goods_info);
        $purchase_real_info = $purchaseData['purchase_real_info'];
        $purchase_sn = $purchaseData['purchase_sn'];

        //获取各个批次的详细信息
        $real_goods_info = $rpd_model->getBatchDetail($purchase_sn, $where, $param_info);

        //组装批次统计数据
        $params = [
            'purchase_real_info' => $purchase_real_info,
            'real_goods_info' => $real_goods_info,
            'is_check_erp' => $is_check_erp,
        ];
        if (!empty($task_link)) {
            $batch_task_info = DB::table("batch_task as rpd")
                ->where('task_link', $task_link)
                ->where('status', 0)
                ->pluck('user_list', 'real_purchase_sn');
            $batch_task_info = objectToArrayZ($batch_task_info);

            $user_info = $request->user();
            $user_id = $user_info->id;
            $params['user_id'] = $user_id;
            $params['batch_task_info'] = $batch_task_info;
        }

        $batchData = $this->createBatchData($params);
        $total_info = $batchData['total_info'];

        $total_info = array_values($total_info);
        $return_info['purchase_info'] = $total_info;
        $return_info['data_num'] = count($total_info);
        return $return_info;
    }

    /**
     * description:组装获取批次列表查询条件
     * editor:zongxing
     * date : 2019.01.25
     * return Array
     */
    public function createBatchListCondition_stop($expire, $param_info)
    {
        $where = [];
        $is_check_erp = false;
        if (!empty($param_info['status'])) {
            $status = intval($param_info['status']);
            $expireTime = $this->create_expire_time($status);
            $where = [
                ['rp.status', '=', $status],
            ];

            //待确认差异
            if ($status == 2) {
                $where_str = ['rp.allot_time', '>', $expireTime];
                if ($expire == 2) {
                    $where_str = ['rp.allot_time', '<=', $expireTime];
                }
                $where[] = $where_str;
            } elseif ($status == 3) {
                //待开单
                $where_str = ['rp.diff_time', '>', $expireTime];
                if ($expire == 2) {
                    $where_str = ['rp.diff_time', '<=', $expireTime];
                }
                $where[] = $where_str;
            } elseif ($status == 4) {
                //待入库
                $where_str = ['rp.billing_time', '>', $expireTime];
                if ($expire == 2) {
                    $where_str = ['rp.billing_time', '<=', $expireTime];
                }
                $where[] = $where_str;
                $is_check_erp = true;
            }
        }

        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $purchase_sn = trim($param_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where[] = ["rpd.purchase_sn", "LIKE", '\'' . $purchase_sn . '\''];
        }

        //待清点
        if (isset($param_info['is_setting']) && !empty($param_info['is_setting'])) {
            $is_setting = intval($param_info['is_setting']);
            $where[] = ['is_setting', '=', $is_setting];
            $where[] = ['is_set_post', '=', 1];
            $where_str = ['rp.arrive_time', '>', $expireTime];
            if ($expire == 2) {
                $where_str = ['rp.arrive_time', '<=', $expireTime];
            }
            $where[] = $where_str;
        }
        //提货日
        if (isset($param_info['delivery_time']) && !empty($param_info['delivery_time'])) {
            $delivery_time = trim($param_info['delivery_time']);
            $where[] = ['rp.delivery_time', '=', $delivery_time];
        }
        //到货日
        if (isset($param_info['arrive_time']) && !empty($param_info['arrive_time'])) {
            $arrive_time = trim($param_info['arrive_time']);
            $where[] = ['rp.arrive_time', '=', $arrive_time];
        }
        $return_info['is_check_erp'] = $is_check_erp;
        $return_info['where'] = $where;
        return $return_info;
    }

    public function createBatchListCondition($param_info)
    {
        $where = [];
        $is_check_erp = false;
        if (!empty($param_info['status'])) {
            $status = intval($param_info['status']);
            $where = [
                ['rp.status', '=', $status],
            ];
            if ($status == 4) {
                $is_check_erp = true;
            }
        }

        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $purchase_sn = trim($param_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where[] = ["rp.purchase_sn", "LIKE", '\'' . $purchase_sn . '\''];
        }

        //提货日
        if (isset($param_info['delivery_time']) && !empty($param_info['delivery_time'])) {
            $delivery_time = trim($param_info['delivery_time']);
            $where[] = ['rp.delivery_time', '=', $delivery_time];
        }
        //到货日
        if (isset($param_info['arrive_time']) && !empty($param_info['arrive_time'])) {
            $arrive_time = trim($param_info['arrive_time']);
            $where[] = ['rp.arrive_time', '=', $arrive_time];
        }
        $return_info['is_check_erp'] = $is_check_erp;
        $return_info['where'] = $where;
        return $return_info;
    }

    /**
     * description:组装采购期统计数据
     * editor:zongxing
     * date : 2019.01.25
     * return Array
     */
    public function createPurchaseData($purchase_goods_info)
    {
        $purchase_real_info = [];
        $purchase_sn = [];
        foreach ($purchase_goods_info as $k => $v) {
            //收集需要查询批次的采购期单号
            $purchase_sn[] = $k;
            //进行自提和邮寄数据的统计
            $zt_num = 0;
            $yj_num = 0;
            $zt_goods_num = 0;
            $yj_goods_num = 0;
            $tmp_group_sn = [];
            foreach ($v as $k1 => $v1) {
                $group_sn = $v1["group_sn"];
                if (intval($v1["path_way"]) == 0) {
                    $zt_goods_num += intval($v1["day_buy_num"]);
                    if (!in_array($group_sn, $tmp_group_sn)) {
                        $tmp_group_sn[] = $group_sn;
                        $zt_num++;
                    }
                } elseif (intval($v1["path_way"]) == 1) {
                    $yj_goods_num += intval($v1["day_buy_num"]);
                    if (!in_array($group_sn, $tmp_group_sn)) {
                        $tmp_group_sn[] = $group_sn;
                        $yj_num++;
                    }
                }
            }
            $purchase_real_info[$k]['title_info'] = [
                'purchase_id' => $v[0]['purchase_id'],
                'delivery_time' => $v[0]['delivery_time'],
                'purchase_sn' => $v[0]['purchase_sn'],
                'sum_demand_name' => $v[0]['sum_demand_name'],
                'zt_num' => $zt_num,
                'yj_num' => $yj_num,
                'zt_goods_num' => $zt_goods_num,
                'yj_goods_num' => $yj_goods_num,
                'real_buy_num' => $zt_goods_num + $yj_goods_num
            ];
        }
        $return_info['purchase_real_info'] = $purchase_real_info;
        $return_info['purchase_sn'] = $purchase_sn;
        return $return_info;
    }

    /**
     * description:组装批次统计数据
     * editor:zongxing
     * date : 2019.01.25
     * return Array
     */
    public function createBatchData_stop($params)
    {
        $purchase_real_info = $params['purchase_real_info'];
        $real_goods_info = $params['real_goods_info'];
        $is_check_erp = $params['is_check_erp'];

        //对各个批次进行统计，计算各个采购期的自采、外采批次以及采购数量
        $total_info = [];
        foreach ($real_goods_info as $k => $v) {
            $real_final_goods = [];
            foreach ($v as $k1 => $v1) {
                $real_purchase_sn = $v1["real_purchase_sn"];
                $group_sn = $v1['group_sn'];
                $port_id = $v1['port_id'];
                $v1['port_name'] = $this->port[$port_id];
                //设置权限
                if (!isset($real_final_goods[$k][$group_sn]) && isset($params['user_id'])) {
                    $tmp_batch_arr = 0;
                    $batch_task_info = $params['batch_task_info'];
                    if (isset($batch_task_info[$real_purchase_sn])) {
                        $user_list = $batch_task_info[$real_purchase_sn];
                        $task_user_list = explode(",", $user_list);
                        $user_id = $params['user_id'];
                        if (in_array($user_id, $task_user_list)) {
                            $tmp_batch_arr = 1;
                        }
                    }
                    $v1['is_display'] = $tmp_batch_arr;
                    //判断是否已经开单成功
                    if ($is_check_erp) {
                        $shopNum = 33;
                        $pushData = [
                            'shop_no' => strval($shopNum),
                            'outer_no' => $real_purchase_sn,
                        ];
                        $erp = new ErpRequest();
                        $url = "purchase_order_query.php";
                        @$erpOrderInfo = $erp->request_query_order($url, $pushData);
                        if (isset($erpOrderInfo['purchase_list']) && $erpOrderInfo['purchase_list'][0]['status'] == 40) {
                            $real_final_goods[$k][$group_sn][] = $v1;
                        }
                    } else {
                        $real_final_goods[$k][$group_sn][] = $v1;
                    }
                } else {
                    $real_final_goods[$k][$group_sn] = $v1;
                }
            }

            if (!empty($real_final_goods)) {
                $purchase_real_info[$k]["real_list"] = array_values($real_final_goods[$k]);
                $total_info[$k] = $purchase_real_info[$k];
            }
        }
        $return_info['total_info'] = $total_info;
        return $return_info;
    }
    public function createBatchData($params)
    {
        $purchase_real_info = $params['purchase_real_info'];
        $real_goods_info = $params['real_goods_info'];
        $is_check_erp = $params['is_check_erp'];
        //对各个批次进行统计，计算各个采购期的自采、外采批次以及采购数量
        $total_info = [];
        foreach ($real_goods_info as $k => $v) {
            $real_final_goods = [];
            foreach ($v as $k1 => $v1) {
                $real_purchase_sn = $v1["real_purchase_sn"];
                $group_sn = $v1['group_sn'];
                $port_id = $v1['port_id'];
                $v1['port_name'] = $this->port[$port_id];
                //设置权限
                if (!isset($real_final_goods[$k][$group_sn]) && isset($params['batch_task_info'])) {
                    $tmp_batch_arr = 0;
                    $batch_task_info = $params['batch_task_info'];
                    if (isset($batch_task_info[$real_purchase_sn])) {
                        $user_list = $batch_task_info[$real_purchase_sn];
                        $task_user_list = explode(",", $user_list);
                        $user_id = $params['user_id'];
                        if (in_array($user_id, $task_user_list)) {
                            $tmp_batch_arr = 1;
                        }
                    }
                    $v1['is_display'] = $tmp_batch_arr;
                    //判断是否已经开单成功
                    if ($is_check_erp) {
                        $shopNum = 33;
                        $pushData = [
                            'shop_no' => strval($shopNum),
                            'outer_no' => $real_purchase_sn,
                        ];
                        $erp = new ErpRequest();
                        $url = "purchase_order_query.php";
                        @$erpOrderInfo = $erp->request_query_order($url, $pushData);
                        if (isset($erpOrderInfo['purchase_list']) && $erpOrderInfo['purchase_list'][0]['status'] == 40) {
                            $real_final_goods[$k][$group_sn][] = $v1;
                        }
                    } else {
                        $real_final_goods[$k][$group_sn][] = $v1;
                    }
                } else {
                    $real_final_goods[$k][$group_sn][] = $v1;
                }
            }
            if (!empty($real_final_goods)) {
                $purchase_real_info[$k]["real_list"] = array_values($real_final_goods[$k]);
                $total_info[$k] = $purchase_real_info[$k];
            }
        }
        $return_info['total_info'] = $total_info;
        return $return_info;
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
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09 modify by zongxing 2018.12.12
     * return Object
     */
//    public function getBatchList($param_info)
//    {
//        $where = [];
//        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
//            $purchase_sn = trim($param_info['query_sn']);
//            $purchase_sn = "%" . $purchase_sn . "%";
//            $where[] = ["rpd.purchase_sn", "LIKE", "$purchase_sn"];
//        }
//        $status = $param_info['status'];
//
//        $fields = ["pd.id as purchase_id", "rp.purchase_sn", "path_way", "group_sn", 'batch_cat', 'day_buy_num'];
//        $purchase_goods_info = DB::table("real_purchase_detail as rpd")
//            ->select($fields)
//            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
//            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
//            ->where($where)
//            ->where('rp.status', $status)
//            ->orderBy('rp.batch_cat', 'asc')
//            ->orderBy('rp.create_time', 'desc')
//            ->get()
//            ->groupBy("purchase_sn");
//        $purchase_goods_info = objectToArrayZ($purchase_goods_info);
//        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
//        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
//        $start_page = ($page - 1) * $page_size;
//        $purchase_goods_info = array_slice($purchase_goods_info, $start_page, $page_size);
//
//        $purchase_real_info = [];
//        $purchase_sn = [];
//        foreach ($purchase_goods_info as $k => $v) {
//            //收集需要查询批次的采购期单号
//            $purchase_sn[] = $k;
//            //进行自提和邮寄数据的统计
//            $zt_num = 0;
//            $yj_num = 0;
//            $zt_goods_num = 0;
//            $yj_goods_num = 0;
//            foreach ($v as $k1 => $v1) {
//                if (intval($v1["path_way"]) == 0) {
//                    $zt_num++;
//                    $zt_goods_num += intval($v1["day_buy_num"]);
//                } elseif (intval($v1["path_way"]) == 1) {
//                    $yj_num++;
//                    $yj_goods_num += intval($v1["day_buy_num"]);
//                }
//            }
//            $purchase_real_info[$k]["title_info"]["purchase_id"] = $v[0]["purchase_id"];
//            $purchase_real_info[$k]["title_info"]["purchase_sn"] = $v[0]["purchase_sn"];
//            $purchase_real_info[$k]["title_info"]["zt_num"] = $zt_num;
//            $purchase_real_info[$k]["title_info"]["yj_num"] = $yj_num;
//            $purchase_real_info[$k]["title_info"]["zt_goods_num"] = $zt_goods_num;
//            $purchase_real_info[$k]["title_info"]["yj_goods_num"] = $yj_goods_num;
//            $purchase_real_info[$k]["title_info"]["real_buy_num"] = $zt_goods_num + $yj_goods_num;
//        }
//
//        //获取各个批次的详细信息
//        $real_goods_info = DB::table("real_purchase_detail as rpd")
//            ->select("pd.id as purchase_id", "rpd.real_purchase_sn", "rp.purchase_sn", "method_name", "channels_name",
//                "path_way", "rp.delivery_time", "rp.arrive_time", "is_setting", "group_sn", "batch_cat", 'rp.status',
//                DB::raw('sum(day_buy_num) as total_buy_num'))
//            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
//            ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
//            ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
//            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
//            ->whereIn("rp.purchase_sn", $purchase_sn)
//            ->where("rp.status", 1)
//            ->orderBy('rp.batch_cat', 'asc')
//            ->orderBy('rp.create_time', 'desc')
//            ->groupBy("rpd.real_purchase_sn")
//            ->get()
//            ->groupBy("purchase_sn");
//        $real_goods_info = objectToArrayZ($real_goods_info);
//
//        //对各个批次进行统计，计算各个采购期的自采、外采批次以及采购数量
//        foreach ($real_goods_info as $k => $v) {
//            $real_final_goods = [];
//            foreach ($v as $k1 => $v1) {
//                $group_sn = $v1["group_sn"];
//                if (isset($real_final_goods[$k][$group_sn])) {
//                    $real_final_goods[$k][$group_sn][] = $v1;
//                    //进行预采批次和正常批次数量汇总
//                    $real_num = intval($real_final_goods[$k][$group_sn][0]["total_buy_num"]);
//                    $predict_num = intval($v1["total_buy_num"]);
//                    $real_final_goods[$k][$group_sn][0]["total_buy_num"] = $real_num + $predict_num;
//                } else {
//                    $real_final_goods[$k][$group_sn][] = $v1;
//                }
//            }
//            if (!empty($real_final_goods)) {
//                $purchase_real_info[$k]["real_list"] = array_values($real_final_goods[$k]);
//            }
//        }
//        $purchase_real_info = array_values($purchase_real_info);
//        $return_info["purchase_info"] = $purchase_real_info;
//        $return_info["data_num"] = count($purchase_real_info);
//        return $return_info;
//    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function batchSettingList($purchase_info)
    {
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rpd.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $purchase_goods_info = DB::table("real_purchase_detail as rpd")
            ->select("pd.id as purchase_id", "rpd.real_purchase_sn", "rp.purchase_sn",
                "path_way",
                DB::raw('sum(day_buy_num) as total_buy_num'))
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->where($where)
            ->orderBy('rp.create_time', 'desc')
            ->groupBy("rpd.real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $purchase_goods_info = objectToArrayZ($purchase_goods_info);

        $purchase_real_info = [];
        foreach ($purchase_goods_info as $k => $v) {
            $zt_num = 0;
            $yj_num = 0;
            $zt_goods_num = 0;
            $yj_goods_num = 0;
            foreach ($v as $k1 => $v1) {
                if (intval($v1["path_way"]) == 0) {
                    $zt_num++;
                    $zt_goods_num += intval($v1["total_buy_num"]);
                } elseif (intval($v1["path_way"]) == 1) {
                    $yj_num++;
                    $yj_goods_num += intval($v1["total_buy_num"]);
                }
            }
            $purchase_real_info[$k]["title_info"]["purchase_id"] = $v[0]["purchase_id"];
            $purchase_real_info[$k]["title_info"]["zt_num"] = $zt_num;
            $purchase_real_info[$k]["title_info"]["yj_num"] = $yj_num;
            $purchase_real_info[$k]["title_info"]["zt_goods_num"] = $zt_goods_num;
            $purchase_real_info[$k]["title_info"]["yj_goods_num"] = $yj_goods_num;
            $purchase_real_info[$k]["title_info"]["real_buy_num"] = $zt_goods_num + $yj_goods_num;
        }

        //获取各个批次的详细信息
        $real_goods_info = DB::table("real_purchase_detail as rpd")
            ->select("pd.id as purchase_id", "rpd.real_purchase_sn", "rp.purchase_sn", "method_name", "channels_name",
                "path_way", "rp.delivery_time", "rp.arrive_time", "is_setting",
                DB::raw('sum(day_buy_num) as total_buy_num'))
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
            ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->where($where)
            ->where("is_setting", 0)
            ->orderBy('rp.create_time', 'desc')
            ->groupBy("rpd.real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $real_goods_info = objectToArrayZ($real_goods_info);

        //对各个批次进行统计，计算各个采购期的自采、外采批次以及采购数量
        foreach ($real_goods_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $purchase_real_info[$k]["list_info"] = $v;
            }
        }

        foreach ($purchase_real_info as $k => $v) {
            if (!isset($v["list_info"])) {
                unset($purchase_real_info[$k]);
            }
        }

        $purchase_real_info = array_values($purchase_real_info);
        $return_info["purchase_info"] = $purchase_real_info;
        $return_info["data_num"] = count($purchase_real_info);
        return $return_info;
    }

    /**
     * description:获取数据修正批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function modifyDataList($purchase_info)
    {
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["rpd.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $purchase_goods_info = DB::table("real_purchase_detail as rpd")
            ->select("pd.id as purchase_id", "rpd.real_purchase_sn", "rp.purchase_sn",
                "path_way",
                DB::raw('sum(day_buy_num) as total_buy_num'))
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->where($where)
            ->orderBy('rp.create_time', 'desc')
            ->groupBy("rpd.real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $purchase_goods_info = objectToArrayZ($purchase_goods_info);

        $purchase_real_info = [];
        foreach ($purchase_goods_info as $k => $v) {
            $zt_num = 0;
            $yj_num = 0;
            $zt_goods_num = 0;
            $yj_goods_num = 0;
            foreach ($v as $k1 => $v1) {
                if (intval($v1["path_way"]) == 0) {
                    $zt_num++;
                    $zt_goods_num += intval($v1["total_buy_num"]);
                } elseif (intval($v1["path_way"]) == 1) {
                    $yj_num++;
                    $yj_goods_num += intval($v1["total_buy_num"]);
                }
            }
            $purchase_real_info[$k]["title_info"]["purchase_id"] = $v[0]["purchase_id"];
            $purchase_real_info[$k]["title_info"]["zt_num"] = $zt_num;
            $purchase_real_info[$k]["title_info"]["yj_num"] = $yj_num;
            $purchase_real_info[$k]["title_info"]["zt_goods_num"] = $zt_goods_num;
            $purchase_real_info[$k]["title_info"]["yj_goods_num"] = $yj_goods_num;
            $purchase_real_info[$k]["title_info"]["real_buy_num"] = $zt_goods_num + $yj_goods_num;
        }

        //获取各个批次的详细信息
        $real_goods_info = DB::table("real_purchase_detail as rpd")
            ->select("pd.id as purchase_id", "rpd.real_purchase_sn", "rp.purchase_sn", "method_name", "channels_name",
                "path_way", "rp.delivery_time", "rp.arrive_time", "is_setting", 'batch_cat',
                DB::raw('sum(day_buy_num) as total_buy_num'))
            ->leftJoin("real_purchase as rp", "rp.real_purchase_sn", "=", "rpd.real_purchase_sn")
            ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
            ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "rp.purchase_sn")
            ->where($where)
            ->where("rp.status", 1)
            ->orderBy('rp.create_time', 'desc')
            ->groupBy("rpd.real_purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $real_goods_info = objectToArrayZ($real_goods_info);

        //对各个批次进行统计，计算各个采购期的自采、外采批次以及采购数量
        foreach ($real_goods_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $purchase_real_info[$k]["list_info"] = $v;
            }
        }

        foreach ($purchase_real_info as $k => $v) {
            if (!isset($v["list_info"])) {
                unset($purchase_real_info[$k]);
            }
        }

        $purchase_real_info = array_values($purchase_real_info);
        $return_info["purchase_info"] = $purchase_real_info;
        $return_info["data_num"] = count($purchase_real_info);
        return $return_info;
    }
//    public function modifyDataList($purchase_info)
//    {
//        $where = [];
//        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
//            $purchase_sn = trim($purchase_info['query_sn']);
//            $purchase_sn = "%" . $purchase_sn . "%";
//            $where = [
//                ["pd.purchase_sn", "LIKE", "$purchase_sn"]
//            ];
//        }
//
//        $demand_goods_info = DB::table("demand_count as dc")
//            ->select("pd.id as purchase_id", "dc.purchase_sn", "pd.predict_day",
//                DB::raw('sum(real_buy_num) as real_buy_num'))
//            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "dc.purchase_sn")
//            ->leftJoin("real_purchase_detail as rpd", "rpd.purchase_sn", "=", "dc.purchase_sn")
//            ->where($where)
//            ->orderBy('pd.create_time', 'desc')
//            ->groupBy("dc.purchase_sn")
//            ->get();
//        $demand_goods_info = json_decode(json_encode($demand_goods_info), true);
//
//        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
//        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
//        $start_str = ($start_page - 1) * $page_size;
//        $demand_goods_info = array_slice($demand_goods_info, $start_str, $page_size);
//
//        $return_info = [];
//
//        $return_info["purchase_info"] = [];
//        foreach ($demand_goods_info as $k => $v) {
//            //计算当前采购期下面的商品自提总数
//            $zt_goods_num = DB::table("real_purchase as rp")
//                ->where("path_way", "0")
//                ->where("rp.purchase_sn", $v["purchase_sn"])
//                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
//                ->sum("day_buy_num");
//            $v["zt_goods_num"] = $zt_goods_num;
//
//            //计算当前采购期下面的自提批次数
//            $v["zt_num"] = 0;
//            if ($zt_goods_num > 0) $v["zt_num"] = 1;
//
//            //计算当前采购期下面的邮寄批次数
//            $yj_num = DB::table("real_purchase")
//                ->where("path_way", "1")
//                ->where("purchase_sn", $v["purchase_sn"])
//                ->count("real_purchase_sn");
//            $v["yj_num"] = $yj_num;
//
//            //计算当前采购期下面的商品邮寄总数
//            $yj_goods_num = DB::table("real_purchase as rp")
//                ->where("path_way", "1")
//                ->where("rp.purchase_sn", $v["purchase_sn"])
//                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
//                ->sum("day_buy_num");
//            $v["yj_goods_num"] = $yj_goods_num;
//
//            //计算实时数据列表信息
//            $goods_list_info = DB::table("real_purchase as rp")
//                ->select("rp.real_purchase_sn", "port_id", "path_way", "rp.is_setting", "rp.delivery_time",
//                    "rp.arrive_time", "method_name", "channels_name",
//                    DB::raw('sum(day_buy_num) as total_buy_num'),
//                    DB::raw("Date(jms_rp.create_time) as create_time"))
//                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
//                ->leftJoin("purchase_method as pm", "pm.id", "=", "rp.method_id")
//                ->leftJoin("purchase_channels as pc", "pc.id", "=", "rp.channels_id")
//                ->where("rp.purchase_sn", $v["purchase_sn"])
//                ->where("rp.status", 1)
//                ->groupBy("real_purchase_sn")
//                ->get();
//            $goods_list_info = objectToArrayZ($goods_list_info);
//
//            if (!empty($goods_list_info[0]["real_purchase_sn"])) {
//                $tmp_info["title_info"] = $v;
//                $tmp_info["list_info"] = $goods_list_info;
//                array_push($return_info["purchase_info"], $tmp_info);
//            }
//        }
//        $return_info["data_num"] = count($return_info["purchase_info"]);
//        return $return_info;
//    }

    /**
     * description:获取采购数据汇总详情
     * editor:zongxing
     * type:POST
     * * params: 1.采购期单号:purchase_sn;
     * date : 2018.07.06
     * return Object
     */
    public function getPurchaseTotalDetail($purchase_data_info)
    {
        $queryPurchase = $this->createQueryPurchaseTotal($purchase_data_info);
        $queryPurchaseRes = DB::select(DB::raw($queryPurchase));
        return $queryPurchaseRes;
    }

    /**
     * description:组装采购数据汇总详情搜索条件
     * editor:zongxing
     * date : 2018.07.23
     * return String
     */
    public function createQueryPurchaseTotal($purchase_data_info)
    {
        $purchase_sn = $purchase_data_info["purchase_sn"];

        $sql_query_purchase_total = "SELECT pd.id as purchase_id,dc.purchase_sn,spec_sn,erp_prd_no,erp_merchant_no,goods_name,
                goods_num,may_buy_num,real_buy_num 
                FROM jms_demand_count as dc
                LEFT JOIN jms_purchase_date as pd ON pd.purchase_sn = dc.purchase_sn
                WHERE dc.purchase_sn = '" . $purchase_sn . "'";
        if (isset($purchase_data_info['query_sn'])) {
            $query_sn = trim($purchase_data_info['query_sn']);
            if (preg_match("/[\x7f-\xff]/", $query_sn)) {
                $query_sn = explode(" ", $query_sn);

                foreach ($query_sn as $k => $v) {
                    $sql_query_purchase_total .= "AND goods_name LIKE '%" . $v . "%' ";
                }
            } else {
                $query_sn = "%" . $query_sn . "%";
                $sql_query_purchase_total .= "AND (spec_sn LIKE '" . $query_sn . "' 
                            OR erp_prd_no LIKE '" . $query_sn . "' OR erp_merchant_no LIKE '" . $query_sn . "')";
            }
        }
        return $sql_query_purchase_total;
    }

    /**
     * description:获取采购数据汇总详情(优采推荐)
     * editor:zongxing
     * type:POST
     * * params: 1.采购期单号:purchase_sn;
     * date : 2018.07.06
     * return Object
     */
    public function getRecommendTotalDetail_stop($purchase_data_info)
    {
        $queryPurchase = $this->createQueryRecTotalDetail_stop($purchase_data_info);
        $queryPurchaseRes = DB::select(DB::raw($queryPurchase));
        $recommend_total_detail = json_decode(json_encode($queryPurchaseRes), true);

        if (empty($recommend_total_detail)) {
            return false;
        }

        $main_discount_info = DB::table("main_discount")->get(["brand_id", "brand_discount"]);
        $main_discount_info = objectToArrayZ($main_discount_info);

        $brand_info = [];
        $brand_id_info = [];
        foreach ($main_discount_info as $k => $v) {
            array_push($brand_id_info, $v["brand_id"]);
            $brand_info[$v["brand_id"]] = $v["brand_discount"];
        }

        //获取品牌折扣信息
        foreach ($recommend_total_detail as $k => $v) {
            $return_arr[$k] = $v;

            //采购折扣小于等于主采折扣的
            $where = [];
            if (in_array($v["brand_id"], $brand_id_info)) {
                $where = [
                    ["d.brand_discount", "<=", $brand_info[$v["brand_id"]]],
                ];
            }

            $demand_discount_info = DB::table("discount as d")
                ->leftJoin('brand as b', 'b.brand_id', '=', 'd.brand_id')
                ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'd.channels_id')
                ->leftJoin('purchase_method as pm', 'pm.id', '=', 'd.method_id')
                ->where($where)
                ->where("d.brand_id", $v["brand_id"])
                ->orderBy("brand_discount", "asc")
                ->get(array("channels_name", "brand_discount"));
            $demand_discount_info = json_decode(json_encode($demand_discount_info), true);

            foreach ($demand_discount_info as $k1 => $v1) {
                $demand_discount_info[$k1] = $v1;
            }
            $return_arr[$k]["discount_info"] = $demand_discount_info;

            //采购折扣大于主采折扣的
            $where = [];
            if (in_array($v["brand_id"], $brand_id_info)) {
                $where = [
                    ["d.brand_discount", ">", $brand_info[$v["brand_id"]]],
                ];
            }

            $demand_discount_info = DB::table("discount as d")
                ->leftJoin('brand as b', 'b.brand_id', '=', 'd.brand_id')
                ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'd.channels_id')
                ->leftJoin('purchase_method as pm', 'pm.id', '=', 'd.method_id')
                ->where($where)
                ->where("d.brand_id", $v["brand_id"])
                ->orderBy("brand_discount", "asc")
                ->get(array("channels_name", "brand_discount"));
            $demand_discount_info = objectToArrayZ($demand_discount_info);

            foreach ($demand_discount_info as $k1 => $v1) {
                $demand_discount_info[$k1] = $v1;
            }
            $return_arr[$k]["big_discount_info"] = $demand_discount_info;
        }

        return $return_arr;
    }

    /**
     * description:组装采购数据汇总详情搜索条件
     * editor:zongxing
     * date : 2018.07.23
     * return String
     */
    public function createQueryRecTotalDetail_stop($purchase_data_info)
    {
        $purchase_sn = $purchase_data_info["purchase_sn"];

        $sql_query_purchase_total = "SELECT pd.id as purchase_id,dc.purchase_sn,dc.spec_sn,dc.erp_prd_no,
                dc.erp_merchant_no,dc.goods_name,
                dc.goods_num,may_buy_num,real_buy_num,g.brand_id,dc.may_buy_num 
                FROM jms_demand_count as dc
                LEFT JOIN jms_purchase_date as pd ON pd.purchase_sn = dc.purchase_sn
                LEFT JOIN jms_goods_spec as gs ON gs.spec_sn = dc.spec_sn
                LEFT JOIN jms_goods as g ON g.goods_sn = gs.goods_sn
                WHERE dc.purchase_sn = '" . $purchase_sn . "'";
        if (isset($purchase_data_info['query_sn'])) {
            $query_sn = trim($purchase_data_info['query_sn']);
            if (preg_match("/[\x7f-\xff]/", $query_sn)) {
                $query_sn = explode(" ", $query_sn);
                foreach ($query_sn as $k => $v) {
                    $sql_query_purchase_total .= "AND (g.goods_name LIKE '%" . $v . "%' OR g.keywords LIKE '" . $query_sn . "')";
                }
            } else {
                $query_sn = "%" . $query_sn . "%";
                $sql_query_purchase_total .= "AND (gs.spec_sn LIKE '" . $query_sn . "' 
                            OR gs.erp_prd_no LIKE '" . $query_sn . "' OR gs.erp_merchant_no LIKE '" . $query_sn . "')";
            }
        }
        return $sql_query_purchase_total;
    }

    /**
     * description:获取客户
     * editor:zhangdong
     * date : 2018.07.11
     * return Object
     */
    public function getUserInfo()
    {
        $field = 'user_name';
        $where = [];
        $userInfo = DB::table('sale_user')->selectRaw($field)->where($where)->get();
        return $userInfo;
    }

    /**
     * description:采购部分配完可采数后，更新商品统计表数据
     * editor:zongxing
     * date : 2018.10.09
     * return String
     */
//    public function updateDataByAllot($param_info)
//    {
//        $updateRes = DB::transaction(function () use ($param_info) {
//            $demand_sn = trim($param_info["demand_sn"]);
//            $demand_goods_info = DB::table("purchase_demand_detail")
//                ->where("demand_sn", $demand_sn)
//                ->get(["purchase_sn", "spec_sn", "may_num"]);
//            $demand_goods_info = objectToArrayZ($demand_goods_info);
//
//            $purchase_sn_info = [];
//            foreach ($demand_goods_info as $k => $v) {
//                $purchase_sn = $v['purchase_sn'];
//                if (!in_array($purchase_sn, $purchase_sn_info)) {
//                    $purchase_sn_info[] = $purchase_sn;
//                }
//            }
//
//            $demand_count_goods_info = DB::table("demand_count")
//                ->whereIn('purchase_sn', $purchase_sn_info)->get(['id', "purchase_sn", "may_buy_num", "spec_sn"]);
//            $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
//
//
//            $demand_count_total_info = [];
////            foreach ($demand_count_goods_info as $k => $v) {
////                if (!isset($demand_count_total_info[$v["purchase_sn"]][$v["spec_sn"]]["may_buy_num"])) {
////                    $demand_count_total_info[$v["purchase_sn"]][$v["spec_sn"]]["may_buy_num"] = $v["may_buy_num"];
////                }
////            }
//
//            foreach ($demand_count_goods_info as $k => $v) {
//                $spec_sn = $v['spec_sn'];
//                $purchase_sn = $v['purchase_sn'];
//                $demand_count_total_info[$spec_sn][$purchase_sn] = $v;
//            }
//            $purchase_spec_total_info = array_keys($demand_count_total_info);
//
//            //采购期渠道商品统计表
//            $purchase_channel_goods_info = DB::table("purchase_channel_goods")->get();
//            $purchase_channel_goods_info = objectToArrayZ($purchase_channel_goods_info);
//            $purchase_channel_goods = [];
//            foreach ($purchase_channel_goods_info as $k => $v) {
//                $channels_method_sn = $v["channels_sn"] . '-' . $v["method_sn"];
//                if (isset($purchase_channel_goods[$v["purchase_sn"]])) {
//                    $purchase_channel_goods[$v["purchase_sn"]][$v["spec_sn"]][$channels_method_sn] = $v;
//                } else {
//                    $purchase_channel_goods[$v["purchase_sn"]] = [];
//                    $purchase_channel_goods[$v["purchase_sn"]][$v["spec_sn"]][$channels_method_sn] = $v;
//                }
//            }
//
//            //需求渠道商品明细表
//            $demand_channel_goods_info = DB::table("demand_channel_goods")
//                ->where("demand_sn", $demand_sn)
//                ->get();
//            $demand_channel_goods_info = objectToArrayZ($demand_channel_goods_info);
//            $demand_channel_goods = [];
//            foreach ($demand_channel_goods_info as $k => $v) {
//                if (isset($demand_channel_goods[$v["purchase_sn"]])) {
//                    array_push($demand_channel_goods[$v["purchase_sn"]], $v);
//                } else {
//                    $demand_channel_goods[$v["purchase_sn"]] = [];
//                    array_push($demand_channel_goods[$v["purchase_sn"]], $v);
//                }
//            }
//
//            $sql_demand_count = "UPDATE jms_demand_count SET may_buy_num = CASE spec_sn ";
//            $sql_purchase_channels = "UPDATE jms_purchase_channel_goods SET may_num = CASE id ";
//            $tmp_purchase_channels = "channel_discount = CASE id ";
//            $insert_purchase_channel = [];
//            $update_demand_count_spec = [];
//            $updatePurchaseChannelGoods = [];
//            foreach ($purchase_sn_info as $k => $v) {
//                $purchase_sn = $v;
//                $update_demand_count_purchase[] = $purchase_sn;
//                $tmp_purchase_channel["purchase_sn"] = $v;
//                foreach ($demand_goods_info as $k1 => $v1) {
//                    $spec_sn = $v1["spec_sn"];
//                    if (!in_array($spec_sn, $purchase_spec_total_info)) {
//                        if (isset($demand_count_total_info[$spec_sn][$purchase_sn])) {
////                            $tmp_count_goods_num = $demand_count_total_info[$purchase_sn][$spec_sn]["may_buy_num"] + $v1["may_num"];
////                            $sql_demand_count .= sprintf(" WHEN " . $spec_sn . " THEN " . $tmp_count_goods_num);
//                            $id = $demand_count_total_info[$spec_sn][$purchase_sn]['id'];
//                            $updatePurchaseChannelGoods['real_num'][] = [
//                                $id => 'real_num + ' . $v1["may_num"]
//                            ];
//                        }
//                    }
//                }
//
//                foreach ($demand_channel_goods[$purchase_sn] as $k2 => $v2) {
//                    $goods_spec_sn = $v2["spec_sn"];
//                    $channels_method_sn = $v2["channels_sn"] . '-' . $v2["method_sn"];
//                    if (isset($purchase_channel_goods[$purchase_sn][$goods_spec_sn][$channels_method_sn])) {
//                        $pcg_id = $purchase_channel_goods[$purchase_sn][$goods_spec_sn][$channels_method_sn]["id"];
//                        $update_purchase_channels_id[] = $pcg_id;
//
//                        $purchase_channel_may_num = $purchase_channel_goods[$purchase_sn][$goods_spec_sn][$channels_method_sn]["may_num"] + $v2["may_num"];
//                        $sql_purchase_channels .= sprintf(" WHEN " . $pcg_id . " THEN " . $purchase_channel_may_num);
//
//                        $channel_discount = $v2["channel_discount"];
//                        $tmp_purchase_channels .= sprintf(" WHEN " . $pcg_id . " THEN " . $channel_discount);
//                    } else {
//                        $tmp_purchase_channel["spec_sn"] = $v2["spec_sn"];
//                        $tmp_purchase_channel["method_sn"] = $v2["method_sn"];
//                        $tmp_purchase_channel["channels_sn"] = $v2["channels_sn"];
//                        $tmp_purchase_channel["channel_discount"] = $v2["channel_discount"];
//                        $tmp_purchase_channel["may_num"] = $v2["may_num"];
//                        array_push($insert_purchase_channel, $tmp_purchase_channel);
//                    }
//                }
//            }
//
//            if ($insert_purchase_channel) {
//                DB::table("purchase_channel_goods")->insert($insert_purchase_channel);
//            }
//
//            if (!empty($update_demand_count_spec)) {
//                $update_spec = implode(',', array_values($update_demand_count_spec));
//
//                $update_purchase_str = '';
//                foreach ($update_demand_count_purchase as $k => $v) {
//                    $update_purchase_str .= "'" . $v . "',";
//                }
//                $update_purchase_str = substr($update_purchase_str, 0, -1);
//                $sql_demand_count .= " END WHERE spec_sn IN (" . $update_spec . ") AND purchase_sn IN (" . $update_purchase_str . ")";
//                DB::update(DB::raw($sql_demand_count));
//            }
//
//            if (!empty($update_purchase_channels_id)) {
//                $sql_purchase_channels .= " END, " . $tmp_purchase_channels;
//                $update_purchase_channels_id = implode(',', array_values($update_purchase_channels_id));
//                $sql_purchase_channels .= " END WHERE id IN (" . $update_purchase_channels_id . ")";
//                DB::update(DB::raw($sql_purchase_channels));
//            }
//
//            $update_data["status"] = 3;
//            $update_res = DB::table("demand")->where("demand_sn", $demand_sn)->update($update_data);
//            return $update_res;
//        });
//        return $updateRes;
//    }

    public function updateDataByAllot($param_info)
    {
        //获取需求商品表数据
        $demand_sn = trim($param_info["demand_sn"]);
        $demand_goods_info = DB::table("purchase_demand_detail")
            ->where("demand_sn", $demand_sn)
            ->get(["purchase_sn", "spec_sn", "may_num"]);
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        $purchase_sn_info = [];
        foreach ($demand_goods_info as $k => $v) {
            $purchase_sn = $v['purchase_sn'];
            if (!in_array($purchase_sn, $purchase_sn_info)) {
                $purchase_sn_info[] = $purchase_sn;
            }
        }
        //获取采购期统计表数据
        $demand_count_goods_info = DB::table("demand_count")
            ->whereIn('purchase_sn', $purchase_sn_info)->get(['id', "purchase_sn", "may_buy_num", "spec_sn"]);
        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
        $demand_count_total_info = [];
        foreach ($demand_count_goods_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $purchase_sn = $v['purchase_sn'];
            $demand_count_total_info[$spec_sn][$purchase_sn] = $v;
        }
        $purchase_spec_total_info = array_keys($demand_count_total_info);

        //采购期渠道商品统计表
//        $purchase_channel_goods_info = DB::table("purchase_channel_goods")->get();
//        $purchase_channel_goods_info = objectToArrayZ($purchase_channel_goods_info);
//        $purchase_channel_goods = [];
//        foreach ($purchase_channel_goods_info as $k => $v) {
//            $channels_method_sn = $v["channels_sn"] . '-' . $v["method_sn"];
//            if (isset($purchase_channel_goods[$v["purchase_sn"]])) {
//                $purchase_channel_goods[$v["purchase_sn"]][$v["spec_sn"]][$channels_method_sn] = $v;
//            } else {
//                $purchase_channel_goods[$v["purchase_sn"]] = [];
//                $purchase_channel_goods[$v["purchase_sn"]][$v["spec_sn"]][$channels_method_sn] = $v;
//            }
//        }
        //需求渠道商品明细表
        $demand_channel_goods_info = DB::table("demand_channel_goods")
            ->where("demand_sn", $demand_sn)
            ->get();
        $demand_channel_goods_info = objectToArrayZ($demand_channel_goods_info);
        $demand_channel_goods = [];
        foreach ($demand_channel_goods_info as $k => $v) {
            if (isset($demand_channel_goods[$v["purchase_sn"]])) {
                array_push($demand_channel_goods[$v["purchase_sn"]], $v);
            } else {
                $demand_channel_goods[$v["purchase_sn"]] = [];
                array_push($demand_channel_goods[$v["purchase_sn"]], $v);
            }
        }

        $total_data = [
            'demand_sn' => $demand_sn,
            'demand_channel_goods' => $demand_channel_goods,
            //'purchase_channel_goods' => $purchase_channel_goods,
            'demand_goods_info' => $demand_goods_info,
            'purchase_spec_total_info' => $purchase_spec_total_info,
            'demand_count_total_info' => $demand_count_total_info,
        ];
        $total_info = $this->createDemandAllotData($total_data);
        $updateRes = DB::transaction(function () use ($total_info) {
            //更新采购期统计表数据
            $updateDemandCountGoodsSql = $total_info['updateDemandCountGoodsSql'];
            if (!empty($updateDemandCountGoodsSql)) {
                DB::update(DB::raw($updateDemandCountGoodsSql));
            }
            //插入采购期渠道商品表数据
//            $insert_purchase_channel = $total_info['insert_purchase_channel'];
//            if (!empty($insert_purchase_channel)) {
//                DB::table("purchase_channel_goods")->insert($insert_purchase_channel);
//            }
//            //更新采购期渠道商品表数据
//            $updatePurchaseChannelGoodsSql = $total_info['updatePurchaseChannelGoodsSql'];
//            if (!empty($updatePurchaseChannelGoodsSql)) {
//                DB::update(DB::raw($updatePurchaseChannelGoodsSql));
//            }

            $update_data["status"] = 3;
            $demand_sn = $total_info['demand_sn'];
            $update_res = DB::table("demand")->where("demand_sn", $demand_sn)->update($update_data);
            return $update_res;
        });
        return $updateRes;
    }

    /**
     * description:获取采购期采购需求资金列表
     * editor:zongxing
     * date : 2018.12.20
     * return Array
     */
    public function createDemandAllotData($total_data)
    {
        $demand_sn = $total_data['demand_sn'];
        $demand_status = DB::table('demand')->where('demand_sn', $demand_sn)->first(['status']);
        $demand_status = objectToArrayZ($demand_status)['status'];
        $updateDemandCountGoodsSql = '';
        if ($demand_status == 3) {
            $total_info = [
                'demand_sn' => $demand_sn,
                'updateDemandCountGoodsSql' => $updateDemandCountGoodsSql,
            ];
            return $total_info;
        }
        //$demand_channel_goods = $total_data['demand_channel_goods'];
        //$purchase_channel_goods = $total_data['purchase_channel_goods'];
        $demand_goods_info = $total_data['demand_goods_info'];
        $purchase_spec_total_info = $total_data['purchase_spec_total_info'];
        $demand_count_total_info = $total_data['demand_count_total_info'];
        //$insert_purchase_channel = [];
        //$updatePurchaseChannelGoods = [];

//        foreach ($demand_channel_goods as $k1 => $v1) {
//            $purchase_sn = $k1;
//            foreach ($v1 as $k2 => $v2) {
//                $goods_spec_sn = $v2["spec_sn"];
//                $channels_method_sn = $v2["channels_sn"] . '-' . $v2["method_sn"];
//                if (isset($purchase_channel_goods[$purchase_sn][$goods_spec_sn][$channels_method_sn])) {
//                    $pcg_id = $purchase_channel_goods[$purchase_sn][$goods_spec_sn][$channels_method_sn]["id"];
//                    $channel_discount = $v2["channel_discount"];
//                    $updatePurchaseChannelGoods['may_num'][] = [
//                        $pcg_id => 'may_num + ' . $v2["may_num"]
//                    ];
//                    $updatePurchaseChannelGoods['channel_discount'][] = [
//                        $pcg_id => $channel_discount
//                    ];
//                } else {
//                    $tmp_purchase_channel["purchase_sn"] = $purchase_sn;
//                    $tmp_purchase_channel["spec_sn"] = $v2["spec_sn"];
//                    $tmp_purchase_channel["method_sn"] = $v2["method_sn"];
//                    $tmp_purchase_channel["channels_sn"] = $v2["channels_sn"];
//                    $tmp_purchase_channel["channel_discount"] = $v2["channel_discount"];
//                    $tmp_purchase_channel["may_num"] = $v2["may_num"];
//                    $insert_purchase_channel[] = $tmp_purchase_channel;
//                }
//            }
//        }

        foreach ($demand_goods_info as $k1 => $v1) {
            $purchase_sn = $v1["purchase_sn"];
            $spec_sn = $v1["spec_sn"];
            if (in_array($spec_sn, $purchase_spec_total_info)) {
                if (isset($demand_count_total_info[$spec_sn][$purchase_sn])) {
                    $id = $demand_count_total_info[$spec_sn][$purchase_sn]['id'];
                    $updateDemandCountGoods['may_buy_num'][] = [
                        $id => 'may_buy_num + ' . $v1["may_num"]
                    ];
                }
            }
        }
        if (!empty($updateDemandCountGoods)) {
            //需要判断的字段
            $column = 'id';
            $updateDemandCountGoodsSql = makeBatchUpdateSql('jms_demand_count', $updateDemandCountGoods, $column);
        }

//        $updatePurchaseChannelGoodsSql = '';
//        if (!empty($updatePurchaseChannelGoods)) {
//            //需要判断的字段
//            $column = 'id';
//            $updatePurchaseChannelGoodsSql = makeBatchUpdateSql('jms_purchase_channel_goods', $updatePurchaseChannelGoods, $column);
//        }

        $total_info = [
            'demand_sn' => $demand_sn,
            'updateDemandCountGoodsSql' => $updateDemandCountGoodsSql,
            //'updatePurchaseChannelGoodsSql' => $updatePurchaseChannelGoodsSql,
            //'insert_purchase_channel' => $insert_purchase_channel,
        ];
        return $total_info;
    }

    /**
     * description:获取采购期采购需求资金列表
     * editor:zongxing
     * date : 2018.12.20
     * return Array
     */
    public function getDemandCountList()
    {
        $fields = ['purchase_sn', 'dc.spec_sn', 'dc.goods_num', 'dc.goods_num as final_goods_num', 'may_buy_num',
            'dc.real_buy_num', 'gs.spec_price',
        ];
        $demand_fund = DB::table('demand_count as dc')->select($fields)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dc.spec_sn')
            ->get($fields)->groupBy('purchase_sn');
        $demand_fund = objectToArrayZ($demand_fund);
        return $demand_fund;
    }

    /**
     * description:获取采购期采购统计信息
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function getDemandCountDetail($purchase_sn_arr)
    {
        $purchase_count_list = DB::table("demand_count as dc")
            ->select("dc.purchase_sn",
                DB::raw("sum(jms_dc.goods_num) as goods_num"),
                DB::raw("sum(jms_dc.goods_num) as final_goods_num"),
                DB::raw("sum(jms_dc.may_buy_num) as may_buy_num"),
                DB::raw("sum(jms_dc.real_buy_num) as real_buy_num"),
                DB::raw("sum(jms_dc.real_buy_num) as final_buy_num"),
                DB::raw("round(sum(jms_dc.real_buy_num)/sum(jms_dc.goods_num) * 100,2) as real_buy_rate"),
                DB::raw("round((sum(jms_dc.goods_num)- sum(jms_dc.real_buy_num))/sum(jms_dc.goods_num) * 100,2) as miss_buy_rate")
            )
            ->whereIn('purchase_sn', $purchase_sn_arr)
            ->groupBy("dc.purchase_sn")
            ->get()
            ->groupBy("purchase_sn");
        $purchase_count_list = objectToArrayZ($purchase_count_list);
        return $purchase_count_list;
    }

    /**
     * description:检查上传的商品是否在采购期商品统计表
     * author:zongxing
     * date : 2018.12.28
     * return Array
     */
//    public function checkDemandCountGoodsInfo($param_info, $res)
//    {
//        $purchase_sn = trim($param_info['purchase_sn']);
//        $demand_count_goods_info = DB::table("demand_count as dc")
//            ->leftJoin('purchase_demand as pd', 'pd.purchase_sn', '=', 'dc.purchase_sn')
//            ->where("pd.purchase_sn", $purchase_sn)
//            ->pluck("real_buy_num", "spec_sn");
//        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
//
//        $upload_goods_info = [];
//        $match_spec_sn = [];
//        $error_info = '';
//        foreach ($res as $k => $v) {
//            if ($k < 1 || !isset($v[0])) continue;
//            $day_buy_num = intval($v[count($v) - 3]);
//            if (isset($v[0]) && !empty($v[0]) && $day_buy_num == 0) {
//                $error_info .= $k . ',';
//                continue;
//            }
//            $spec_sn = trim($v[0]);
//            $is_match_str = trim($v[count($v) - 2]);
//            $is_match = 0;
//            if ($is_match_str == '是') {
//                $is_match = 1;
//                $match_spec_sn[$spec_sn] = '1';
//            }
//            $upload_goods_info[$spec_sn]['day_buy_num'] = $day_buy_num;
//            $upload_goods_info[$spec_sn]['is_match'] = $is_match;
//            $upload_goods_info[$spec_sn]['parent_spec_sn'] = trim($v[count($v) - 1]);;
//        }
//        if (!empty($error_info)) {
//            $error_info = '您上传的商品中: 第' . substr($error_info, 0, -1) . '条商品的采购数量有误';
//            return ['code' => '1013', 'msg' => $error_info];
//        }
//        //对上传的商品进行校验
//        $upload_spec_sn = array_keys($upload_goods_info);
//        $gs_model = new GoodsSpecModel();
//        $upload_goods_total_info = $gs_model->get_goods_info($upload_spec_sn);
//        if (empty($upload_goods_total_info)) {
//            return ['code' => '1018', 'msg' => '您上传的商品,规格码有误,请重新确认'];
//        }
//        $error_info = '';
//        foreach ($upload_goods_info as $k => $v) {
//            $spec_sn = $k;
//            if (!array_key_exists($spec_sn, $upload_goods_total_info)) {
//                $error_info .= $spec_sn . ',';
//            }
//        }
//        if (!empty($error_info)) {
//            $error_info = '您上传的商品中: ' . substr($error_info, 0, -1) . '商品规格码有误';
//            return ['code' => '1019', 'msg' => $error_info];
//        }
//
//        $tmp_spec_key = array_diff_key($upload_goods_info, $demand_count_goods_info);
//        $diff_spec_key = array_diff_key($tmp_spec_key, $match_spec_sn);
//        if (!empty($diff_spec_key)) {
//            $error_str = '您上传的商品中规格码为:';
//            foreach ($diff_spec_key as $k => $v) {
//                $error_str .= $k . ',';
//            }
//            $error_str = substr($error_str, 0, -1);
//            $error_str .= ' 的,不存在需求,请检查';
//            return ['code' => '1008', 'msg' => $error_str];
//        }
//        $return_info['demand_count_goods_info'] = $demand_count_goods_info;
//        $return_info['upload_goods_info'] = $upload_goods_info;
//        return $return_info;
//    }
    public function get_demand_count_data($param_info)
    {
        $purchase_sn = trim($param_info['purchase_sn']);
        $demand_count_goods_info = DB::table("demand_count as dc")
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'dc.purchase_sn')
            ->where("dc.purchase_sn", $purchase_sn)
            ->whereIn("pd.status", [1, 2])
            ->pluck('real_buy_num', 'spec_sn');
        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
        return $demand_count_goods_info;
    }

    /**
     * description:获取采购期采购需求资金列表
     * editor:zongxing
     * date : 2018.12.20
     * return Array
     */
    public function getDemandCountGroupSn($purchase_sn, $goods_spec_sn)
    {
        $demand_count_goods_info = DB::table('demand_count')
            ->where('purchase_sn', $purchase_sn)
            ->whereIn('spec_sn', $goods_spec_sn)
            ->get()->groupBy('spec_sn');
        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
        return $demand_count_goods_info;
    }

    /**
     * description:获取指定需求单对应的额商品的采购情况
     * editor:zongxing
     * date : 2019.01.22
     * return Array
     */
    public function getPurchaseGoodsInfo($demand_sn_arr)
    {
        $fields = ['dc.purchase_sn', 'pd.demand_sn', 'dc.spec_sn', 'dc.goods_num', 'real_buy_num', 'dc.goods_num as 
            final_goods_num'
        ];
        $demand_count_info = DB::table('demand_count as dc')
            ->leftJoin('purchase_demand as pd', 'pd.purchase_sn', '=', 'dc.purchase_sn')
            ->whereIn('pd.demand_sn', $demand_sn_arr)
            ->get($fields);
        $demand_count_info = objectToArrayZ($demand_count_info);
        return $demand_count_info;
    }

    /**
     * description:获取采购期对应的商品数据信息
     * editor:zongxing
     * date : 2019.02.12
     * return Array
     */
    public function getDemandCountGoodsInfo($purchase_sn, $purchase_goods_spec = [])
    {
        $demand_sn_info = DB::table('purchase_demand as pd')
            ->whereIn('pd.purchase_sn', $purchase_sn)->distinct()->pluck('demand_sn');
        $demand_sn_info = objectToArrayZ($demand_sn_info);

        $purchase_sn_info = DB::table('purchase_demand as pd')
            ->whereIn('pd.demand_sn', $demand_sn_info)->distinct()->pluck('purchase_sn');
        $purchase_sn_info = objectToArrayZ($purchase_sn_info);

        $field = ['dc.spec_sn',
            DB::raw('SUM(jms_dc.may_buy_num) as total_may_buy_num'),
            DB::raw('SUM(jms_dc.real_buy_num) as total_real_buy_num')
        ];
        $demand_count_goods_obj = DB::table('demand_count as dc')
            ->whereIn('dc.purchase_sn', $purchase_sn_info);
        if (!empty($purchase_goods_spec)) {
            $demand_count_goods_obj->whereIn('dc.spec_sn', $purchase_goods_spec);
        }
        $demand_count_goods_info = $demand_count_goods_obj->groupBy('dc.spec_sn')->get($field)->groupBy('spec_sn');
        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
        return $demand_count_goods_info;
    }

    /**
     * description:获取采购期对应的商品数据信息
     * editor:zongxing
     * date : 2019.02.15
     * return Array
     */
    public function getGoodsByPurchaseSn($purchase_sn)
    {
        $demand_count_goods_info = DB::table("demand_count")
            ->where("purchase_sn", $purchase_sn)
            ->pluck("real_buy_num", "spec_sn");
        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);
        return $demand_count_goods_info;
    }

    /**
     * description:获取采购期采购需求资金列表
     * editor:zongxing
     * date : 2019.03.04
     * return Array
     */
    public function getDemandCountGoodsList($purchase_sn, $spec_sn = [])
    {
        $where[] = ['purchase_sn', '=', $purchase_sn];
        if ($spec_sn) {
            $where[] = ['spec_sn', '=', $spec_sn];
        }
        $fields = ['id', 'purchase_sn', 'dc.spec_sn', 'dc.goods_num', 'may_buy_num'];
        $demand_count_goods_info = DB::table('demand_count as dc')->select($fields)->where($where)
            ->get($fields);
        $demand_count_goods_info = objectToArrayZ($demand_count_goods_info);

        $demand_count_goods_list = [];
        foreach ($demand_count_goods_info as $k => $v) {
            $demand_count_goods_list[$v['spec_sn']] = $v;
        }
        return $demand_count_goods_list;
    }

}
