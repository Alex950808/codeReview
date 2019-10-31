<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Model\Vone\GoodsModel;

class DemandGoodsModel extends Model
{
    protected $table = "demand_goods as dg";//商品需求表
    protected $demand_goods = "demand_goods";//商品需求表
    protected $demand = "demand";//需求单表
    protected $demand_count = "demand_count";//商品需求统计表
    protected $user_goods = "user_goods";//销售用户商品数据

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    public $timestamps = true;

    protected $is_mark = [
        '0' => '未标记',
        '1' => '已标记',
    ];

    public $field = [
        'dg.demand_sn', 'dg.goods_name', 'dg.erp_prd_no', 'dg.erp_merchant_no', 'dg.sale_discount',
        'dg.spec_sn', 'dg.goods_num', 'dg.is_mark', 'dg.allot_num', 'dg.is_postpone',
    ];

    /**
     * description:批量插入需求订单表和商品需求表
     * editor:zhangdong
     * date : 2018.06.25
     * update : 2018.07.02
     * return Boolean
     */
    public function batchInsertStop($demandOrdData, $demandGoodsData, $demandCountData, $userGoodsData)
    {
        $insertRes = DB::transaction(
            function () use ($demandOrdData, $demandGoodsData, $demandCountData, $userGoodsData) {
                DB::table($this->demand)->insert($demandOrdData);
                DB::table($this->demand_goods)->insert($demandGoodsData);
                DB::table($this->demand_count)->insert($demandCountData);
                $insertRes = DB::table($this->user_goods)->insert($userGoodsData);
                //修改采购单状态
                $purchase_sn = $demandOrdData['purchase_sn'];
                $where = [
                    ['purchase_sn', $purchase_sn],
                    ['status', 1],
                ];
                $update = ['status' => 2];
                DB::table('purchase_date')->where($where)->update($update);
                return $insertRes;
            }
        );
        return $insertRes;
    }

    /**
     * description:批量插入需求订单表和商品需求表
     * editor:zhangdong
     * date : 2018.09.21
     * return Boolean
     */
    public function batchInsert($demandOrdData, $demandGoodsData, $userGoodsData)
    {
        $insertRes = DB::transaction(
            function () use ($demandOrdData, $demandGoodsData, $userGoodsData) {
                DB::table($this->demand)->insert($demandOrdData);
                DB::table($this->demand_goods)->insert($demandGoodsData);
                $insertRes = DB::table($this->user_goods)->insert($userGoodsData);
                return $insertRes;
            }
        );
        return $insertRes;
    }

    /**
     * description:根据采购单号和规格码更新商品需求数
     * editor:zhangdong
     * date : 2018.07.03
     * return Boolean
     */
    public function updateGoodsNum($purchase_sn, $spec_sn, $goods_num)
    {
        $where = [
            ['purchase_sn', $purchase_sn],
            ['spec_sn', $spec_sn]
        ];
        $updateRes = DB::table($this->demand_count)->where($where)->increment('goods_num', $goods_num);
        return $updateRes;
    }

    /**
     * description:根据采购单号生成需求单号
     * editor:zhangdong
     * date : 2018.06.25
     * return String
     */
    public function getDemandSn($purchase_sn)
    {
        $increaseNum = 0;
        do {
            $increaseNum += 1;
            $strNum = sprintf('%04d', $increaseNum);
            $demand_sn = 'XQ' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table($this->demand)
                ->where([
                    ['purchase_sn', '=', $purchase_sn],
                    ['demand_sn', '=', $demand_sn]
                ])->count();
        } while ($count);
        return $demand_sn;
    }

    /**
     * description:生成需求单号
     * editor:zhangdong
     * date : 2018.09.21
     * return String
     */
    public function generalDemandSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $demand_sn = 'XQ' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table($this->demand)
                ->where([
                    ['demand_sn', '=', $demand_sn]
                ])->count();
        } while ($count);
        return $demand_sn;
    }

    /**
     * description:根据采购单号获取采购单对应用户的商品数据
     * editor:zhangdong
     * date : 2018.06.26
     * params: $queryType（拓展参数） :查询方式 1，根据采购单查询
     * return Object
     */
    public function getUserGoods($purchase_sn)
    {
        $selectFields = "su.user_name,COUNT(DISTINCT ug.spec_sn) AS skuNum,SUM(ug.goods_num) AS goodsNum,
            ug.create_time,COUNT(DISTINCT ug.demand_sn) AS demandNum";
        $where = [
            [DB::raw('ug.purchase_sn'), $purchase_sn]
        ];
        $userGoods = DB::table(DB::raw('jms_user_goods AS ug'))->selectRaw($selectFields)
            ->leftJoin(DB::raw('jms_sale_user AS su'), DB::raw('ug.sale_user_id'), '=', DB::raw('su.id'))->where($where)->groupBy(DB::raw('ug.sale_user_id'))->get();
        return $userGoods;
    }

    /**
     * description:获取已审核采购需求列表
     * editor:zongxing
     * type:GET
     * create_date : 2018.06.29
     * update_date : 2018.09.27
     * return Object
     */
    public function getDemandPassList($purchase_info)
    {
        if (empty($purchase_info['query_sn'])) {
            $demandInfoList = DB::table('purchase_demand_detail as pdd')
                ->select('pd.id', 'pdd.purchase_sn', 'pdd.demand_sn', 'd.create_time', 'delivery_time',
                    DB::raw('SUM(jms_pdd.goods_num) as goods_num'),
                    DB::raw('SUM(jms_pdd.may_num) as total_may_num'),
                    DB::raw('COUNT(jms_pdd.spec_sn) as sku_num'),
                    "d.status")
                ->join('demand as d', function ($join) {
                    $join->on('d.demand_sn', '=', 'pdd.demand_sn')
                        ->where("d.status", "3");
                })
                ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'pdd.purchase_sn')
                //->where('pd.status', '2')
                ->orderBy('pd.create_time', 'desc')
                ->groupBy("demand_sn")
                ->groupBy("purchase_sn")
                ->get();
        } else {
            $query_sn = "%" . trim($purchase_info['query_sn']) . "%";
            $demandInfoList = DB::table('purchase_demand_detail as pdd')
                ->select('pd.id', 'pdd.purchase_sn', 'pdd.demand_sn', 'd.create_time', 'delivery_time',
                    DB::raw('sum(jms_pdd.goods_num) as goods_num'),
                    DB::raw('count(jms_pdd.spec_sn) as sku_num'),
                    "d.status")
                ->join('demand as d', function ($join) {
                    $join->on('d.demand_sn', '=', 'pdd.demand_sn')
                        ->where("d.status", "3");
                })
                ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'pdd.purchase_sn')
                //->where('pd.status', '2')
                ->where(function ($query) use ($query_sn) {
                    $query->where('pd.purchase_sn', 'LIKE', $query_sn)
                        ->orWhere('pd.delivery_team', 'LIKE', $query_sn);
                })
                ->orderBy('pd.create_time', 'desc')
                ->groupBy("demand_sn")
                ->groupBy("purchase_sn")
                ->get();
        }
        $demandInfoList = objectToArrayZ($demandInfoList);

        $tempTotalInfo = [];
        foreach ($demandInfoList as $k => $v) {
            $purchase_sn = $v["purchase_sn"];
            if (isset($tempTotalInfo[$purchase_sn])) {
                array_push($tempTotalInfo[$purchase_sn], $v);
            } else {
                $tempTotalInfo[$purchase_sn] = [];
                array_push($tempTotalInfo[$purchase_sn], $v);
            }
        }

        $demandTotalInfo = [];
        $demandTotalInfo["data_num"] = count($tempTotalInfo);
        $demandTotalInfo["purchase_info"] = [];

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $demandInfo = array_slice($tempTotalInfo, $start_str, $page_size);

        foreach ($demandInfo as $k => $v) {
            $demand_tmp_info['number_sn'] = $v[0]['id'];
            $demand_tmp_info['purchase_sn'] = $v[0]['purchase_sn'];
            $demand_tmp_info['delivery_time'] = $v[0]['delivery_time'];
            $demand_tmp_info["demand_info"] = $v;
            array_push($demandTotalInfo["purchase_info"], $demand_tmp_info);
        }
        return $demandTotalInfo;
    }


    /**
     * description:获取待审核采购需求列表
     * editor:zongxing
     * type:GET
     * date : 2018.06.29
     * return Object
     */
    public function getDemandWaitList_stop($purchase_info)
    {
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $query_sn = trim($purchase_info['query_sn']);
            $query_sn = "%" . $query_sn . "%";
            $where = [
                ["d.purchase_sn", "LIKE", "$query_sn"]
            ];
        }

        $demandInfo = DB::table('demand_goods as dg')
            ->select("pd.id", "d.purchase_sn", "d.demand_sn", "d.create_time",
                DB::raw('sum(goods_num) as goods_num'),
                DB::raw('count(spec_sn) as sku_num'),
                "d.status")
            ->join('demand as d', function ($join) {
                $join->on('d.demand_sn', '=', 'dg.demand_sn')
                    ->on('d.purchase_sn', '=', 'dg.purchase_sn')
                    ->where("d.status", "1");
            })
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'd.purchase_sn')
            ->where('pd.status', '2')
            ->where($where)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("demand_sn")
            ->groupBy("purchase_sn")
            ->get();
        $demandInfo = json_decode(json_encode($demandInfo), true);

        $tempTotalInfo = [];
        foreach ($demandInfo as $k => $v) {
            $purchase_sn = $v["purchase_sn"];
            if (isset($tempTotalInfo[$purchase_sn])) {
                array_push($tempTotalInfo[$purchase_sn], $v);
            } else {
                $tempTotalInfo[$purchase_sn] = [];
                array_push($tempTotalInfo[$purchase_sn], $v);
            }
        }

        $demandTotalInfo = [];
        $demandTotalInfo["data_num"] = count($tempTotalInfo);
        $demandTotalInfo["purchase_info"] = [];

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $demandInfo = array_slice($tempTotalInfo, $start_str, $page_size);

        foreach ($demandInfo as $k => $v) {
            $demand_tmp_info["number_sn"] = $v[0]["id"];
            $demand_tmp_info["purchase_sn"] = $v[0]["purchase_sn"];
            $demand_tmp_info["demand_info"] = $v;
            array_push($demandTotalInfo["purchase_info"], $demand_tmp_info);
        }
        return $demandTotalInfo;
    }

    /**
     * description:改变采购需求中商品的信息
     * editor:zongxing
     * type:POST
     * date : 2018.07.12
     * params: 1.采购期编号:purchase_sn;2.采购需求编号:demand_sn;3.商品规格码:spec_sn;4.要更改的字段名:filed_name;
     *          5.要更改的字段值:filed_value;
     * return bool
     */
    public function changeDemandGoods_stop($demand_goods_info)
    {
        $purchase_sn = $demand_goods_info["purchase_sn"];
        $demand_sn = $demand_goods_info["demand_sn"];
        $spec_sn = $demand_goods_info["spec_sn"];
        $filed_name = $demand_goods_info["filed_name"];

        if ($filed_name == "recover_num") {
            $update_arr["is_purchase"] = 1;
        }

        $demand_goods_update = 0;
        if (!isset($demand_goods_info["sale_user_id"]) || empty($demand_goods_info["sale_user_id"])) {
            $update_arr[$filed_name] = $demand_goods_info["filed_value"];
            $demand_goods_update = DemandGoodsModel::
            where('purchase_sn', $purchase_sn)
                ->where('demand_sn', $demand_sn)
                ->where('spec_sn', $spec_sn)
                ->update($update_arr);
        } else {
            $sale_user_id = $demand_goods_info["sale_user_id"];

            //获取更新前用户需求信息
            $user_goods_info = DB::table("user_goods")
                ->where('purchase_sn', $purchase_sn)
                ->where('demand_sn', $demand_sn)
                ->where('spec_sn', $spec_sn)
                ->where('sale_user_id', $sale_user_id)
                ->first();
            $user_goods_info = objectToArrayZ($user_goods_info);

            //获取更新前商品需求信息
            $before_goods_info = DB::table("demand_goods")
                ->where('purchase_sn', $purchase_sn)
                ->where('demand_sn', $demand_sn)
                ->where('spec_sn', $spec_sn)
                ->first();
            $before_goods_info = json_decode(json_encode($before_goods_info), true);

            if ($filed_name == "recover_num") {
                if ($user_goods_info["recover_num"] < $demand_goods_info["filed_value"]) {
                    //增加
                    $add_goods_num = $demand_goods_info["filed_value"] - $user_goods_info["recover_num"];
                    $goods_total_num = $before_goods_info["recover_num"] + $add_goods_num;
                    $update_demand_arr[$filed_name] = $goods_total_num;

                    $update_user_arr[$filed_name] = $demand_goods_info["filed_value"];
                } else {
                    //减少
                    $del_goods_num = $user_goods_info["recover_num"] - $demand_goods_info["filed_value"];
                    $goods_total_num = $before_goods_info["recover_num"] - $del_goods_num;
                    $update_demand_arr[$filed_name] = $goods_total_num;

                    $update_user_arr[$filed_name] = $demand_goods_info["filed_value"];
                }
                $user_goods_update = DB::table("user_goods")
                    ->where('purchase_sn', $purchase_sn)
                    ->where('demand_sn', $demand_sn)
                    ->where('spec_sn', $spec_sn)
                    ->where('sale_user_id', $sale_user_id)
                    ->update($update_user_arr);

                if ($user_goods_update) {
                    $demand_goods_update = DB::table("demand_goods")
                        ->where('purchase_sn', $purchase_sn)
                        ->where('demand_sn', $demand_sn)
                        ->where('spec_sn', $spec_sn)
                        ->update($update_demand_arr);
                }
            }
        }

        return $demand_goods_update;
    }

    /**
     * description:依据采购单号查询需求统计表中是否已经有对应商品
     * editor:zhangdong
     * date : 2018.07.03
     * return Object
     */
    public function checkGoods($purchase_sn, $spec_sn)
    {
        $where = [
            ['purchase_sn', $purchase_sn],
            ['spec_sn', $spec_sn],
        ];
        $checkRes = DB::table($this->demand_count)->where($where)->count();
        return $checkRes;
    }


    /**
     * description:更新采购期商品统计信息和采购需求单状态
     * editor:zongxing
     * date : 2018.07.03
     * params:1.采购需求表数据:goods_list;
     * return Object
     */
    public function changeDemandStatus_stop($demand_info, $purchase_sn, $demand_sn)
    {
        $demand_status = $demand_info["status"];

        //要更新的采购需求单的状态
        $update_arr["status"] = $demand_status;

        if ($demand_status == 2) {
            //更新采购需求单状态为审核未通过
            $update_res = DB::table("demand")
                ->where('purchase_sn', '=', $purchase_sn)
                ->where('demand_sn', '=', $demand_sn)
                ->update($update_arr);
            return $update_res;
        }

        //拼接要更新的数据
        $changeData = $this->createChangeData_stop($purchase_sn, $demand_sn);
        if (!$changeData) {
            return false;
        }
        $updateRes = DB::transaction(function () use ($changeData, $purchase_sn, $demand_sn, $update_arr) {
            //更新采购期商品统计信息
            DB::update(DB::raw($changeData));

            //更新采购需求单状态为审核通过
            $update_res = DB::table("demand")
                ->where('purchase_sn', '=', $purchase_sn)
                ->where('demand_sn', '=', $demand_sn)
                ->update($update_arr);
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
    public function createChangeData_stop($purchase_sn, $demand_sn)
    {
        $demand_goods_info = DB::table("demand_goods")
            ->where("purchase_sn", $purchase_sn)
            ->where("demand_sn", $demand_sn)
            ->get(["spec_sn", "recover_num"]);
        $demand_goods_info = objectToArrayZ($demand_goods_info);

        $sql_demand_count = "UPDATE jms_demand_count SET may_buy_num = CASE spec_sn ";
        foreach ($demand_goods_info as $k => $v) {
            $spec_sn = $v["spec_sn"];
            $may_buy_num = $v["recover_num"];//获取可采数量

            if ($may_buy_num) {
                $goods_count_info = DB::table("demand_count")
                    ->where("purchase_sn", $purchase_sn)
                    ->where('spec_sn', '=', $spec_sn)
                    ->first(["id", "goods_num", "may_buy_num"]);
                $goods_count_info = json_decode(json_encode($goods_count_info), true);
                $may_buy_num = $goods_count_info["may_buy_num"] + $may_buy_num;

                $sql_demand_count .= sprintf(" WHEN " . $spec_sn . " THEN " . $may_buy_num);
                $spec_sn_arr[] = $spec_sn;
            }
        }
        if (empty($spec_sn_arr)) {
            return false;
        }
        $spec_sn_arr = implode(',', array_values($spec_sn_arr));
        $sql_demand_count .= " END WHERE purchase_sn = '" . $purchase_sn . "' AND spec_sn IN (" . $spec_sn_arr . ")";
        return $sql_demand_count;
    }

    /**
     * description:采购需求对应的优采推荐详情页
     * editor:zongxing
     * type:POST
     * date : 2018.07.03
     * params: 1.需求单编号:demand_sn;2.采购期编号:purchase_sn;
     * return Object
     */
    public function getDemandRecommendDetail_stop($demand_info)
    {
        $queryRecommendDetail = $this->createQueryRecommendDetail_stop($demand_info);
        $queryRecommendDetailRes = DB::select(DB::raw($queryRecommendDetail));
        $queryRecommendDetailRes = objectToArrayZ($queryRecommendDetailRes);

        if (empty($queryRecommendDetailRes)) {
            return false;
        }

        $main_discount_info = DB::table("main_discount")->get(["brand_id", "brand_discount"]);
        $main_discount_info = json_decode(json_encode($main_discount_info), true);

        $brand_info = [];
        $brand_id_info = [];
        foreach ($main_discount_info as $k => $v) {
            array_push($brand_id_info, $v["brand_id"]);
            $brand_info[$v["brand_id"]] = $v["brand_discount"];
        }

        //获取品牌折扣信息
        foreach ($queryRecommendDetailRes as $k => $v) {
            $demand_recommend_info[$k] = $v;

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
                ->orderBy("d.brand_discount", "asc")
                ->get(["channels_name", "method_name", "d.brand_discount"]);
            $demand_discount_info = objectToArrayZ($demand_discount_info);

            foreach ($demand_discount_info as $k1 => $v1) {
                $demand_discount_info[$k1] = $v1;
            }
            $demand_recommend_info[$k]["discount_info"] = $demand_discount_info;

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
                ->where("d.brand_id", $v["brand_id"])
                ->where($where)
                ->orderBy("d.brand_discount", "asc")
                ->get(["channels_name", "method_name", "d.brand_discount"]);
            $demand_discount_info = json_decode(json_encode($demand_discount_info), true);

            foreach ($demand_discount_info as $k1 => $v1) {
                $demand_discount_info[$k1] = $v1;
            }
            $demand_recommend_info[$k]["big_discount_info"] = $demand_discount_info;
        }
        return $demand_recommend_info;
    }

    /**
     * description:组装优采推荐详情页搜索条件
     * editor:zongxing
     * date : 2018.07.23
     * return String
     */
    public function createQueryRecommendDetail_stop($demand_info)
    {
        $demand_sn = $demand_info["demand_sn"];
        $purchase_sn = $demand_info["purchase_sn"];

        $sql_query_rec_detail = "SELECT dg.goods_name,dg.erp_prd_no,dg.erp_merchant_no,
                dg.spec_sn,dg.goods_num,g.brand_id,dg.recover_num 
                FROM jms_demand_goods as dg
                LEFT JOIN jms_goods_spec as gs ON gs.spec_sn = dg.spec_sn
                LEFT JOIN jms_goods as g ON g.goods_sn = gs.goods_sn
                WHERE  demand_sn = '" . $demand_sn . "' AND purchase_sn = '" . $purchase_sn . "'";
        if (isset($demand_info['query_sn'])) {
            $query_sn = trim($demand_info['query_sn']);
            if (preg_match("/[\x7f-\xff]/", $query_sn)) {
                $query_sn = explode(" ", $query_sn);

                foreach ($query_sn as $k => $v) {
                    $sql_query_rec_detail .= " AND goods_name LIKE '%" . $v . "%' ";
                }
            } else {
                $query_sn = "%" . $query_sn . "%";
                $sql_query_rec_detail .= " AND (spec_sn LIKE '" . $query_sn . "' 
                            OR erp_prd_no LIKE '" . $query_sn . "' OR erp_merchant_no LIKE '" . $query_sn . "')";
            }
        }
        return $sql_query_rec_detail;
    }

    /**
     * description:获取合单中需求单对应的商品数据
     * editor:zongxing
     * type:POST
     * date : 2019.05.22
     * params: 1.需求单数组:$demand_sn_info;
     * return Object
     */
    public function getDemandDetail($demand_sn_info)
    {
        $field = ['goods_name', 'dg.spec_sn', 'erp_prd_no', 'erp_merchant_no',
            DB::raw('(jms_dg.goods_num - sum(
                        CASE jms_sd.yet_num
                        WHEN jms_sd.yet_num THEN
                            jms_sd.yet_num
                        ELSE
                           0
                        END
                    )) as goods_num'),
        ];
        //延期商品
        $demand_goods_info1 = DB::table('demand_goods as dg')
            ->select($field)
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sort_data as sd', function ($join) {
                $join->on('sd.demand_sn', '=', 'dg.demand_sn');
                $join->on('sd.spec_sn', '=', 'dg.spec_sn');
            })
            ->whereIn('dg.demand_sn', $demand_sn_info)
            ->where('d.status', 7)
            ->where('dg.goods_num', '>', 0)
            ->where('dg.is_postpone', 1)->groupBy('dg.spec_sn');
        $field = ['goods_name', 'dg.spec_sn', 'erp_prd_no', 'erp_merchant_no',
            DB::raw('sum(jms_dg.goods_num) as goods_num'),
        ];
        $demand_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d1', 'd1.demand_sn', '=', 'dg.demand_sn')
            ->whereIn('d1.demand_sn', $demand_sn_info)->where('d1.status', 1)
            ->where('dg.goods_num', '>', 0)->where('dg.is_postpone', 2)
            ->union($demand_goods_info1)
            ->groupBy('dg.spec_sn')
            ->get($field);
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        return $demand_goods_info;
    }

    /**
     * description:获取合单中需求单对应的商品数据
     * editor:zongxing
     * type:POST
     * date : 2019.05.22
     * params: 1.需求单数组:$demand_sn_info;
     * return Object
     */
    public function getGoodsByDemandSn_stop($demand_sn_info)
    {
        $field = ['dg.demand_sn', 'goods_name', 'dg.spec_sn', 'erp_prd_no', 'erp_merchant_no',
            DB::raw('jms_dg.goods_num - (
                        CASE jms_sd.yet_num
                        WHEN jms_sd.yet_num THEN
                            jms_sd.yet_num
                        ELSE
                           0
                        END
                    ) as goods_num'),
        ];
        //延期商品
        $demand_goods_info1 = DB::table('demand_goods as dg')
            ->select($field)
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sort_data as sd', function ($join) {
                $join->on('sd.demand_sn', '=', 'dg.demand_sn');
                $join->on('sd.spec_sn', '=', 'dg.spec_sn');
            })
            ->whereIn('dg.demand_sn', $demand_sn_info)
            ->where('d.status', 7)
            ->where('dg.goods_num', '>', 0)
            ->where('dg.is_postpone', 1);
        $field = ['dg.demand_sn', 'goods_name', 'dg.spec_sn', 'erp_prd_no', 'erp_merchant_no', 'goods_num'];
        $demand_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d1', 'd1.demand_sn', '=', 'dg.demand_sn')
            ->whereIn('d1.demand_sn', $demand_sn_info)
            ->where('d1.status', 1)
            ->where('dg.goods_num', '>', 0)
            ->where('dg.is_postpone', 2)
            //->union($demand_goods_info1)
            ->get($field);
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        dd($demand_goods_info);
        return $demand_goods_info;
    }

    public function getGoodsByDemandSn($demand_sn_info)
    {
        $sd_goods_info = DB::table('sort_data as sd')->whereIn('sd.demand_sn', $demand_sn_info)
            ->groupBy('sd.demand_sn')->groupBy('sd.spec_sn')
            ->pluck(DB::raw('sum(jms_sd.yet_num) as yet_num'), DB::raw("concat_ws('-',jms_sd.demand_sn,jms_sd.spec_sn) as pin_str"));
        $sd_goods_info = objectToArrayZ($sd_goods_info);
        $field = ['dg.demand_sn', 'goods_name', 'dg.spec_sn', 'erp_prd_no', 'erp_merchant_no', 'goods_num'];
        $demand_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->whereIn('d.demand_sn', $demand_sn_info)
            ->where('dg.goods_num', '>', 0)
            ->where('dg.is_postpone', DB::raw('(
                        CASE jms_d.status
                        WHEN 1 THEN
                            2
                        ELSE
                           1
                        END
                    )'))
            ->get($field);
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        foreach ($demand_goods_info as $k => $v) {
            $pin_str = $v['demand_sn'] . '-' . $v['spec_sn'];
            if (isset($sd_goods_info[$pin_str])) {
                $demand_goods_info[$k]['goods_num'] -= intval($sd_goods_info[$pin_str]);
            }
        }
        return $demand_goods_info;
    }

    /**
     * description:组装审核需求详情页搜索条件
     * editor:zongxing
     * date : 2018.07.20
     * return String
     */
    public function createQueryDemandDetail_stop($demand_info)
    {
        $demand_sn = $demand_info["demand_sn"];
        $purchase_sn = $demand_info["purchase_sn"];

        if (!isset($demand_info["sale_user_id"]) || empty($demand_info["sale_user_id"])) {
            $sql_query_demand = "SELECT dg.goods_name,gs.erp_prd_no,ug.erp_merchant_no,ug.spec_sn,dg.goods_num,
                  dg.recover_num,user_name,is_hot  
                  FROM jms_user_goods as ug
                  LEFT JOIN jms_goods_spec as gs ON gs.spec_sn = ug.spec_sn 
                  LEFT JOIN jms_goods as g ON g.goods_sn = gs.goods_sn
                  LEFT JOIN jms_sale_user as su ON su.id = ug.sale_user_id
                  LEFT JOIN jms_demand_goods as dg ON dg.spec_sn = ug.spec_sn
                  WHERE dg.demand_sn = '" . $demand_sn . "' AND dg.purchase_sn = '" . $purchase_sn . "'";
            if (isset($demand_info['query_sn'])) {
                $query_sn = trim($demand_info['query_sn']);
                if (preg_match("/[\x7f-\xff]/", $query_sn)) {
                    $query_sn = explode(" ", $query_sn);
                    foreach ($query_sn as $k => $v) {
                        $sql_query_demand .= "AND dg.goods_name LIKE '%" . $v . "%' ";
                    }
                } else {
                    $query_sn = "%" . $query_sn . "%";
                    $sql_query_demand .= "AND (gs.spec_sn LIKE '" . $query_sn . "' OR gs.erp_prd_no LIKE '" . $query_sn . "' 
                            OR gs.erp_merchant_no LIKE '" . $query_sn . "')";
                }
            }
            $sql_query_demand .= " GROUP BY spec_sn";
        } else {
            $sql_query_demand = "SELECT dg.goods_name,gs.erp_prd_no,ug.erp_merchant_no,ug.spec_sn,ug.goods_num,ug.recover_num,
                        user_name,is_hot  
                  FROM jms_user_goods as ug
                  LEFT JOIN jms_goods_spec as gs ON gs.spec_sn = ug.spec_sn 
                  LEFT JOIN jms_goods as g ON g.goods_sn = gs.goods_sn
                  LEFT JOIN jms_sale_user as su ON su.id = ug.sale_user_id
                  LEFT JOIN jms_demand_goods as dg ON dg.spec_sn = ug.spec_sn
                  WHERE ug.demand_sn = '" . $demand_sn . "' AND ug.purchase_sn = '" . $purchase_sn . "' 
                        AND sale_user_id = '" . $demand_info["sale_user_id"] . "'";
            if (isset($demand_info['query_sn'])) {
                $query_sn = trim($demand_info['query_sn']);
                if (preg_match("/[\x7f-\xff]/", $query_sn)) {
                    $query_sn = explode(" ", $query_sn);

                    foreach ($query_sn as $k => $v) {
                        $sql_query_demand .= "AND dg.goods_name LIKE '%" . $v . "%' ";
                    }
                } else {
                    $query_sn = "%" . $query_sn . "%";
                    $sql_query_demand .= "AND (gs.spec_sn LIKE '" . $query_sn . "' OR gs.erp_prd_no LIKE '" . $query_sn . "' 
                            OR gs.erp_merchant_no LIKE '" . $query_sn . "')";
                }
            }
            $sql_query_demand .= " GROUP BY spec_sn";
        }
        return $sql_query_demand;
    }

    /**
     * description:根据采购单号获取采购单明细
     * editor:zhangdong
     * date : 2018.07.16
     * return Object
     */
    public function getPurchaseInfo($purchase_sn)
    {
        $where = [
            'purchase_sn' => $purchase_sn,
        ];
        $field = 'purchase_sn,flight_sn,delivery_team,delivery_pop_num,start_time,end_time,delivery_time,channels_list,predict_day,status';
        $purchaseInfo = DB::table('purchase_date')->selectRaw($field)->where($where)->first();
        $purchaseInfo = objectToArrayZ($purchaseInfo);
        return $purchaseInfo;
    }

    /**
     * description:根据需求单号获取商品需求信息
     * editor:zhangdong
     * date : 2018.09.27
     * return Object
     */
    public function getDemandGoodsData($demand_sn)
    {
        $demand_sn = trim($demand_sn);
        $where = [
            'demand_sn' => $demand_sn,
        ];
        $field = 'goods_name,erp_prd_no,erp_merchant_no,spec_sn,goods_num';
        $demandGoodsData = DB::table('demand_goods')->selectRaw($field)->where($where)->get();
        return $demandGoodsData;
    }

    /**
     * description:根据需求单号获取商品需求信息
     * editor:zhangdong
     * date : 2018.10.10
     * return Object
     * @param $demand_sn 需求单号
     * @param $pickMarginRate 自采毛利率(array)
     * @param $chargeInfo 费用(array)
     * @return array
     */
    public function queryNeedGoodsInfo($demand_sn, $pickMarginRate, $chargeInfo, $store_id)
    {
        $goodsBaseInfo = $this->getDemGoodsInfo($demand_sn);
        if ($goodsBaseInfo->count() == 0) {
            return false;
        }
        $goodsModel = new GoodsModel();
        //获取erp仓库信息-重价系数（默认为香港仓）
        $goodsHouseInfo = $goodsModel->getErpStoreInfo();
        //查找对应仓库id的键名(在仓库信息的二维数组中查找对应的仓库id)-避免循环查询数据库
        $found_key = $goodsModel->twoArraySearch($goodsHouseInfo, $store_id, 'store_id');
        $store_name = trim($goodsHouseInfo[$found_key]['store_name']);
        $store_factor = trim($goodsHouseInfo[$found_key]['store_factor']);//重价系数
        //查询当前需求单的定价折扣信息,如果有则直接拿该数据
        $arr_pric_rate = $this->getDemPriRateData($demand_sn);
        //定价折扣信息-将写入定价折扣表中
        $arrPricingRate = [];
        //计算其他商品信息
        foreach ($goodsBaseInfo as $key => $value) {
            $brand_name = trim($value->brand_name);
            $brand_id = trim($value->brand_id);
            $brandInfo[] = [
                'brand_name' => $brand_name,
                'brand_id' => $brand_id,
            ];
            $spec_price = trim($value->spec_price);
            $spec_sn = trim($value->spec_sn);
            $gold_discount = trim($value->gold_discount);
            $black_discount = trim($value->black_discount);
            $goodsPrice = $goodsModel->calculateGoodsPrice($spec_price, $gold_discount, $black_discount);
            //金卡价=美金原价*金卡折扣
            $goodsBaseInfo[$key]->gold_price = $goodsPrice['goldPrice'];
            //黑卡价=美金原价*黑卡折扣
            $goodsBaseInfo[$key]->black_price = $goodsPrice['blackPrice'];
            //计算erp仓库相关的数据（仓库名称，重价比，重价比折扣，ERP成本价￥，ERP成本折扣=重价比折扣）
            //仓库名称
            $goodsBaseInfo[$key]->store_name = $store_name;
            $spec_weight = trim($value->spec_weight);//商品重量
            $exw_discount = trim($value->exw_discount); //exw折扣
            //获取有关erp的所有商品数据
            $erpGoodsData = $goodsModel->getErpGoodsData($spec_weight, $spec_price, $store_factor, $exw_discount);
            //重价比=重量/美金原价/重价系数/100
            $goodsBaseInfo[$key]->high_price_ratio = $erpGoodsData['highPriceRatio'];
            //重价比折扣 = exw折扣+重价比
            $hrp_discount = $erpGoodsData['hprDiscount'];
            $goodsBaseInfo[$key]->hpr_discount = $hrp_discount;
            //erp成本价=美金原价*重价比折扣*汇率
            $goodsBaseInfo[$key]->erp_cost_price = $erpGoodsData['erpCostPrice'];
            //计算自采毛利率相关数据
            $pricing_rate = 1;//定价折扣缺省值为0
            //如果定价折扣之前已经保存则直接取该数据
            if (count($arr_pric_rate) >= 1) {
                //找到当前规格码对应定价折扣的键名
                $found_key = $goodsModel->twoArraySearch($arr_pric_rate, $spec_sn, 'spec_sn');
                $pricing_rate = trim($arr_pric_rate[$found_key]['pricing_rate']);
            }
            //自采毛利率=重价比折扣/（1-对应档位利率）
            $arrMarginRate = [];
            foreach ($pickMarginRate as $item) {
                $marginRate = sprintf('%.0f%%', $item['pick_margin_rate']);//自采毛利率当前档位
                $rateData = round($erpGoodsData['hprDiscount'] / (1 - $item['pick_margin_rate'] / 100), 2);
                $arrMarginRate[] = [$marginRate => $rateData];
                $goodsBaseInfo[$key]->arrMarginRate = $arrMarginRate;
                //定价折扣默认档位
                $margin_rate = config('constants.MARGIN_RATE_PERCENT');
                //如果定价折扣之前未保存则按默认值来计算
                if ($marginRate == $margin_rate && count($arr_pric_rate) == 0) {
                    //计算定价折扣相关的数据
                    $pricing_rate = $rateData;
                    //将定价折扣信息保存到数组中
                    $arrPricingRate[$key] = [
                        'demand_sn' => $demand_sn,
                        'spec_sn' => $spec_sn,
                        'pricing_rate' => $pricing_rate,
                    ];
                }
            }
            $pricingRateInfo = $goodsModel->calculPricingInfo($spec_price, $pricing_rate, $hrp_discount, $chargeInfo);
            $goodsBaseInfo[$key]->pricing_rate = $pricing_rate;//定价折扣
            $goodsBaseInfo[$key]->salePrice = $pricingRateInfo['salePrice'];//销售价
            $goodsBaseInfo[$key]->saleMarRate = $pricingRateInfo['saleMarRate'];//销售毛利率
            $goodsBaseInfo[$key]->runMarRate = $pricingRateInfo['runMarRate'];//运营毛利率
            $goodsBaseInfo[$key]->arrChargeRate = $pricingRateInfo['arrChargeRate'];//费用项
        }
        $returnData = [
            'goodsBaseInfo' => $goodsBaseInfo,
            'brandInfo' => $brandInfo,
            'arrPricingRate' => $arrPricingRate,
            'erpInfo' => $goodsHouseInfo,
        ];
        return $returnData;
    }

    /**
     * description:将定价折扣存入表中
     * editor:zhangdong
     * date : 2018.10.12
     * @param $arrPricingRate 定价折扣数组
     * @return bool
     */
    public function savePricingRate($arrPricingRate)
    {
        $saveRes = DB::transaction(function () use ($arrPricingRate) {
            $executeRes = DB::table('pricing_rate')->insert($arrPricingRate);
            return $executeRes;
        });
        return $saveRes;
    }

    /**
     * description:查询定价折扣
     * editor:zhangdong
     * date : 2018.10.12
     * @param $demand_sn 需求单号
     * @return object
     */
    public function getPricingInfo($demand_sn)
    {
        $demand_sn = trim($demand_sn);
        $where = [
            ['demand_sn', $demand_sn]
        ];
        $field = ['demand_sn', 'spec_sn', 'pricing_rate'];
        $pricingInfo = DB::table('pricing_rate')->select($field)->where($where)->get();
        return $pricingInfo;
    }

    /**
     * description:获取需求商品信息
     * editor:zhangdong
     * date : 2018.10.13
     * @param $demand_sn 需求单号
     * @return object
     */
    public function getDemGoodsInfo($demand_sn, $spec_sn = '')
    {
        $arrField = [
            'b.name as brand_name', 'b.brand_id', 'g.goods_name', 'gs.spec_price', 'gs.spec_weight', 'gs.gold_discount',
            'gs.black_discount', 'gs.exw_discount', 'gs.foreign_discount', 'gs.spec_sn', 'dg.goods_num', 'dg.sale_discount'
        ];
        $where[] = ['dg.demand_sn', $demand_sn];
        if (!empty($spec_sn)) {
            $where[] = ['dg.spec_sn', $spec_sn];
        }
        $goodsBaseInfo = DB::table('demand_goods AS dg')->select($arrField)
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->where($where)->get();
        return $goodsBaseInfo;
    }

    /**
     * description:根据需求单号获取定价折扣
     * editor:zhangdong
     * date : 2018.10.13
     * @param $demand_sn 需求单号
     * @return object
     */
    private function getDemPriRateData($demand_sn)
    {
        $field = ['spec_sn', 'pricing_rate'];
        $where = [
            ['demand_sn', $demand_sn],
        ];
        $demPriRate = DB::table('pricing_rate')->select($field)->where($where)->get()
            ->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $demPriRate;
    }

    /**
     * description:批量插入需求订单表和商品需求表_第三次改版
     * editor:zhangdong
     * date : 2018.10.18
     * return Boolean
     */
    public function batchInsert_b($demandOrdData, $demandGoodsData)
    {
        $insertRes = DB::transaction(
            function () use ($demandOrdData, $demandGoodsData) {
                DB::table($this->demand)->insert($demandOrdData);
                $insertRes = DB::table($this->demand_goods)->insert($demandGoodsData);
                return $insertRes;
            }
        );
        return $insertRes;
    }

    /**
     * description:根据需求单号获取商品需求信息
     * editor:zhangdong
     * date : 2018.10.10
     * return Object
     * @param $demand_sn 需求单号
     * @param $chargeInfo 费用
     * @return array
     */
    public function getDemDetail($demand_sn, $chargeInfo)
    {
        $goodsBaseInfo = $this->getDemDetGoods($demand_sn);
        if ($goodsBaseInfo->count() == 0) {
            return false;
        }
        $goodsModel = new GoodsModel();
        //获取erp仓库信息-重价系数（默认为香港仓）
        $store_id = 1002;
        $goodsHouseInfo = $goodsModel->getErpStoreInfo($store_id);
        $store_factor = trim($goodsHouseInfo[0]['store_factor']);//重价系数
        //计算其他商品信息
        foreach ($goodsBaseInfo as $key => $value) {
            $spec_price = trim($value->spec_price);
            $spec_sn = trim($value->spec_sn);
            //计算erp仓库相关的数据（仓库名称，重价比，重价比折扣，ERP成本价￥，ERP成本折扣=重价比折扣）
            $spec_weight = trim($value->spec_weight);//商品重量
            $exw_discount = trim($value->exw_discount); //exw折扣
            //获取有关erp的所有商品数据
            $erpGoodsData = $goodsModel->getErpGoodsData($spec_weight, $spec_price, $store_factor, $exw_discount);
            //重价比折扣 = exw折扣+重价比
            $hrp_discount = $erpGoodsData['hprDiscount'];
            $sale_discount = trim($value->sale_discount);
            $pricingRateInfo = $goodsModel->calculPricingInfo($spec_price, $sale_discount, $hrp_discount, $chargeInfo);
            $goodsBaseInfo[$key]->sale_discount = $sale_discount;//定价折扣
            $goodsBaseInfo[$key]->salePrice = $pricingRateInfo['salePrice'];//销售价
            $goodsBaseInfo[$key]->saleMarRate = $pricingRateInfo['saleMarRate'];//销售毛利率
            $goodsBaseInfo[$key]->runMarRate = $pricingRateInfo['runMarRate'];//运营毛利率
            $goodsBaseInfo[$key]->arrChargeRate = $pricingRateInfo['arrChargeRate'];//费用项
        }
        $returnData = [
            'goodsBaseInfo' => $goodsBaseInfo,
            'erpInfo' => $goodsHouseInfo,
        ];
        return $returnData;
    }


    /**
     * description:需求详情-获取需求商品信息
     * editor:zhangdong
     * date : 2018.10.19
     * @param $demand_sn 需求单号
     * @return object
     */
    public function getDemDetGoods($demand_sn, $spec_sn = '')
    {
        $arrField = [
            'g.goods_name', 'gs.spec_price', 'gs.spec_weight', 'gs.gold_discount',
            'gs.erp_merchant_no', 'gs.erp_prd_no', 'gs.black_discount', 'gs.exw_discount',
            'gs.foreign_discount', 'gs.spec_sn', 'dg.goods_num', 'dg.sale_discount',
            'dg.is_mark', 'dg.is_postpone'
        ];
        $where[] = ['dg.demand_sn', $demand_sn];
        if (!empty($spec_sn)) {
            $where[] = ['dg.spec_sn', $spec_sn];
        }
        $goodsBaseInfo = DB::table('demand_goods AS dg')->select($arrField)
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->where($where)->get();
        foreach ($goodsBaseInfo as $key => $value) {
            $goodsBaseInfo[$key]->desc_mark = $this->is_mark[intval($value->is_mark)];
        }
        return $goodsBaseInfo;
    }

    /**
     * description:组装采购单（需求单）数据
     * editor:zhangdong
     * date : 2018.12.13
     * param $goodsInfo 商品信息
     * param $goodsNum 需求量
     * @return array
     */
    public function createDemandGoodsData($goodsNum, $goodsInfo)
    {
        if (empty($goodsInfo)) return [];
        $goodsData = [
            'goods_name' => trim($goodsInfo['goods_name']),
            'erp_merchant_no' => trim($goodsInfo['erp_merchant_no']),
            'sale_discount' => floatval($goodsInfo['sale_discount']),
            'spec_sn' => trim($goodsInfo['spec_sn']),
            'spec_price' => floatval($goodsInfo['spec_price']),
            'goods_num' => intval($goodsNum),
            'allot_num' => intval($goodsNum),
        ];
        return $goodsData;

    }

    /**
     * description:获取YD单对应的需求单中的商品
     * editor:zongxing
     * date : 2018.12.15
     * @return Array
     */
    public function getDemandGoodsOfYd($sub_order_sn)
    {
        $where = [
            ['sub_order_sn', $sub_order_sn]
        ];
        $demand_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->where($where)
            ->get();
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        return $demand_goods_info;
    }

    /**
     * description:获取预采中需求单的需求资金
     * editor:zongxing
     * date : 2018.12.17
     * @return Array
     */
    public function getFundOfDemandPredict()
    {
        $where = [
            ['d.status', 1],
            ['d.is_mark', 1],
            ['dg.is_mark', 1]
        ];
        $predict_demand_goods_info = DB::table("demand_goods as dg")
            ->select("d.demand_sn",
                DB::raw("COUNT(jms_dg.spec_sn) as sku_num"),
                DB::raw("SUM(jms_dg.goods_num) as goods_num"),
                DB::raw("SUM(jms_dg.goods_num * jms_gs.spec_price) as total_price")
            )
            ->leftJoin("demand as d", "d.demand_sn", "=", "dg.demand_sn")
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "dg.spec_sn")
            ->where($where)
            ->groupBy("d.demand_sn")
            ->get()
            ->groupBy("demand_sn");
        $predict_demand_goods_info = objectToArrayZ($predict_demand_goods_info);
        return $predict_demand_goods_info;
    }

    /**
     * description:计算需求数量
     * editor:zhangdong
     * date : 2018.12.19
     * @param $willBuyNum (待采购数量--去除现货单数量后的需求数量)
     * @param $purchasedNum (已经预采的数量-预采批次单中的数量)
     */
    public function calculateDemNum($willBuyNum, $purchasedNum)
    {
        $willBuyNum = intval($willBuyNum);
        $purchasedNum = intval($purchasedNum);
        $waitNum = 0;//待采数量
        $overflowNum = 0;//溢出数量
        //超采情况
        if ($willBuyNum <= 0 && $purchasedNum > 0) {
            //超采数量
            $overflowNum = $purchasedNum;
            $waitNum = 0;
        }
        //无需再次采购,也未超采情况
        if ($willBuyNum <= 0 && $purchasedNum == 0) {
            $waitNum = 0;
        }
        //需要采购且没有预采数据
        if ($willBuyNum > 0 && $purchasedNum == 0) {
            $waitNum = $willBuyNum;
        }
        //需要采购并且已经有预采数据了
        if ($willBuyNum > 0 && $purchasedNum > 0) {
            $diff = intval($willBuyNum - $purchasedNum);
            $waitNum = $diff;
            //预采数据已经大于待采数据了，此时有溢采数量
            if ($diff < 0) {
                $overflowNum = intval($purchasedNum - $willBuyNum);
                $waitNum = 0;
            }
        }

        return [
            'waitNum' => $waitNum,
            'overflowNum' => $overflowNum,
        ];
    }

    /**
     * description:采购模块-预采需求管理-获取预采需求单详情
     * editor:zongxing
     * date: 2018.12.20
     */
    public function getDemandGoods_stop($demand_sn)
    {
        $where = [
            ["d.demand_sn", $demand_sn],
            ["dg.is_mark", 1]
        ];
        $predict_demand_detail = DB::table('demand_goods as dg')
            ->select(
                "goods_name", "erp_prd_no", "erp_merchant_no", "spec_sn", "goods_num", 'bd_goods_num', "dg.is_mark",
                'sale_discount', 'department'
            )
            ->leftJoin('demand as d', "d.demand_sn", "=", "dg.demand_sn")
            ->where($where)
            ->get()
            ->groupBy('spec_sn');
        $predict_demand_detail = objectToArrayZ($predict_demand_detail);
        return $predict_demand_detail;
    }

    public function getDemandGoodsDetail($demand_sn, $spec_sn)
    {

        $where = [
            ['d.demand_sn', $demand_sn],
            ['dg.is_mark', 1]
        ];

        $predict_demand_detail = DB::table('demand_goods as dg')
            ->select(
                "goods_name", "erp_prd_no", "erp_merchant_no", "spec_sn", "goods_num", 'bd_goods_num', "dg.is_mark",
                'sale_discount', 'department'
            )
            ->leftJoin('demand as d', "d.demand_sn", "=", "dg.demand_sn")
            ->where($where)
            ->whereIn('dg.spec_sn', $spec_sn)
            ->get()
            ->groupBy('spec_sn');
        $predict_demand_detail = objectToArrayZ($predict_demand_detail);
        return $predict_demand_detail;
    }

    /**
     * description:商品标记
     * editor:zhangdong
     * date : 2018.12.20
     */
    public function updateGoodsMark($demand_sn, $spec_sn, $is_mark)
    {
        $demand_sn = trim($demand_sn);
        $spec_sn = trim($spec_sn);
        $is_mark = intval($is_mark);
        if (!isset($this->is_mark[$is_mark])) return false;
        $where = [
            ['demand_sn', $demand_sn],
            ['spec_sn', $spec_sn],
        ];
        $update = [
            'is_mark' => $is_mark,
        ];
        $updateRes = DB::table('demand_goods')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:DD单上传后更新需求单数据
     * author:zhangdong
     * params: $willBuyNum 去掉现货单商品数量的需求量
     * params: $num 最终的采购数量（$willBuyNum去掉预采批次中已采到的商品数量）
     * date : 2018.12.19
     */
    public function modDemGoodsData($demand_sn, $spec_sn, $sale_discount, $num, $willBuyNum)
    {
        $where = [
            ['demand_sn', $demand_sn],
            ['spec_sn', $spec_sn],
        ];
        $update = [
            'sale_discount' => $sale_discount,
            'goods_num' => $willBuyNum,
            'allot_num' => $num,
        ];
        $updateRes = DB::table('demand_goods')->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:检查需求商品标记情况
     * author:zhangdong
     * date : 2018.12.27
     */
    public function checkDemGoodsMark($demandGoods)
    {
        $is_mark = [];
        foreach ($demandGoods as $value) {
            $is_mark[] = intval($value->is_mark);
        }
        $uniqueIsMark = array_unique($is_mark);
        return [
            'is_mark' => $is_mark,
            'uniqueIsMark' => $uniqueIsMark,
        ];
    }

    /**
     * description:获取需求商品信息
     * author:zhangdong
     * date : 2018.12.27
     */
    public function getDemandGoodsInfo($demand_sn)
    {
        $demand_sn = trim($demand_sn);
        $where = [
            ['dg.demand_sn', $demand_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:数据统计模块-订单管理-获取需求单统计信息
     * editor:zongxing
     * date: 2019.01.08
     */
    public function getDemandStatistics($sub_order_sn)
    {
        $field = [
            'd.sub_order_sn', 'd.demand_sn',
            DB::raw('SUM(jms_dg.goods_num) as demand_goods_num'),
            DB::raw('SUM(jms_dg.goods_num * jms_gs.spec_price) as demand_total_price'),
            DB::raw('SUM(jms_dg.goods_num * jms_gs.spec_price) as demand_purchase_total_price'),
            DB::raw('COUNT(jms_dg.spec_sn) as sku_num')
        ];
        $demand_list = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->whereIn('sub_order_sn', $sub_order_sn)
            ->groupBy('d.demand_sn')->get($field)->groupBy('demand_sn');
        $demand_list = objectToArrayZ($demand_list);
        return $demand_list;
    }

    /**
     * description:数据统计模块-订单管理-获取需求单分货信息
     * editor:zongxing
     * date: 2019.01.08
     */
    public function getDemandSortStatistics($sub_order_sn)
    {
        $field = [
            'd.sub_order_sn', 'd.demand_sn', 'handle_num',
            DB::raw('SUM(jms_dg.goods_num) as demand_goods_num'),
            DB::raw('COUNT(jms_dg.spec_sn) as sku_num')
        ];
        $demand_sort_list = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('user_sort_goods as usg', 'usg.demand_sn', '=', 'dg.demand_sn')
            ->whereIn('sub_order_sn', $sub_order_sn)
            ->groupBy('d.sub_order_sn')->get($field)->groupBy('demand_sn');
        $demand_sort_list = objectToArrayZ($demand_sort_list);
        return $demand_sort_list;
    }

    /**
     * description:获取需求单商品信息
     * author:zhangdong
     * date : 2018.12.27
     */
    public function getDemandGoodsList($demand_sn)
    {
        $demand_goods_info = DB::table('demand_goods')->where('demand_sn', $demand_sn)->get()->groupBy('spec_sn');
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        return $demand_goods_info;
    }

    /**
     * description:财务模块-需求资金管理-通过需求单号获取需求商品信息
     * editor:zongxing
     * date : 2019.01.22
     * return Array
     */
    public function getDemandGoodsBySn($demand_sn_arr)
    {
        $field = [
            'dg.demand_sn', 'dg.goods_num', 'gs.spec_price', 'dg.spec_sn'
        ];
        $demand_goods_info = DB::table('demand_goods as dg')
            ->select($field)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->whereIn('dg.demand_sn', $demand_sn_arr)
            ->get();
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        return $demand_goods_info;
    }

    /**
     * description:获取需求单中商品的可分配数
     * editor:zongxing
     * date: 2019.02.19
     */
    public function getGoodsAllotNum_stop($where)
    {
        $demand_goods_info = DB::table("demand_goods as dg")
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->where($where)->get(['spec_sn', 'allot_num', 'd.status']);
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        return $demand_goods_info;
    }

    /**
     * description:获取需求商品可分配数信息信息
     * author:zongxing
     * date : 2019.03.04
     */
    public function getDemandGoodsDetailInfo($demand_sn, $spec_sn = '')
    {
        $where[] = ['dg.demand_sn', '=', $demand_sn];
        if ($spec_sn) {
            $where[] = ['dg.spec_sn', '=', $spec_sn];
        }
        $demand_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            //->where('dg.demand_sn', $demand_sn)
            ->where($where)
            ->get(['dg.id', 'g.brand_id', 'gs.spec_sn', 'dg.allot_num', 'dg.goods_num', 'd.status']);
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        $demand_goods_list = [];
        foreach ($demand_goods_info as $k => $v) {
            $demand_goods_list[$v['spec_sn']] = $v;
        }
        return $demand_goods_list;
    }

    /**
     * description:获取需求单商品信息
     * author:zongxing
     * date : 2019.05.27
     */
    public function demandGoodsInfo($param_info)
    {
        $demand_sn = trim($param_info['demand_sn']);
        $demand_info = DB::table('demand')->where('demand_sn', $demand_sn)->first(['demand_type']);
        $demand_info = objectToArrayZ($demand_info);
        if (empty($demand_info)) {
            return $demand_info;
        }
        $where[] = ['dg.demand_sn', '=', $demand_sn];
        if (isset($param_info['sum_demand_sn'])) {
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            $where[] = ['sd.sum_demand_sn', '=', $sum_demand_sn];
            $field = [
                'dg.id', 'g.brand_id', 'gs.spec_sn', 'sd.goods_num', 'g.goods_name', 'gs.erp_prd_no',
                'gs.erp_merchant_no', 'sd.yet_num', 'sd.is_postpone',
                DB::raw('jms_sd.yet_num as yet_num'),
                DB::raw('(jms_sd.goods_num - jms_sd.yet_num) as diff_num'),
            ];
            $demand_goods_info = DB::table('sort_data as sd')
                ->leftJoin('demand_goods as dg', function ($join) {
                    $join->on('dg.demand_sn', '=', 'sd.demand_sn');
                    $join->on('dg.spec_sn', '=', 'sd.spec_sn');
                })
                ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
                ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
                ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
                ->where($where)
                ->groupBy('dg.spec_sn')
                ->get($field);
            $demand_goods_info = objectToArrayZ($demand_goods_info);
        } else {
            if ($demand_info['demand_type'] == 1) {
                $field = [
                    'dg.id', 'g.brand_id', 'gs.spec_sn', 'dg.goods_num', 'dg.goods_name', 'gs.erp_prd_no',
                    'gs.erp_merchant_no', 'dg.is_postpone',
                    DB::raw('0 as yet_num'),
                    DB::raw('jms_dg.goods_num as diff_num')
                ];
            } else {
                $field = [
                    'dg.id', 'g.brand_id', 'gs.spec_sn', 'dg.goods_name', 'gs.erp_prd_no',
                    'gs.erp_merchant_no', 'dg.is_postpone', 'sd.yet_num', 'dg.is_postpone',
                    DB::raw('(jms_sd.goods_num - jms_sd.yet_num) as goods_num'),
                    DB::raw('0 as yet_num'),
                    DB::raw('(jms_dg.goods_num - jms_sd.yet_num) as diff_num'),
                ];
                $where[] = ['dg.is_postpone', '=', 1];
            }
            $demand_goods_info = DB::table('demand_goods as dg')
                ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
                ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
                ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
                ->leftJoin('sort_data as sd', function ($join) {
                    $join->on('sd.demand_sn', '=', 'dg.demand_sn');
                    $join->on('sd.spec_sn', '=', 'dg.spec_sn');
                })
                ->where($where)
                ->groupBy('dg.spec_sn')
                ->get($field);
            $demand_goods_info = objectToArrayZ($demand_goods_info);
        }
        return $demand_goods_info;
    }

    /**
     * description:获取需求商品信息
     * editor:zongxing
     * date : 2018.10.13
     */
    public function getDemandGoodsBasicInfo($param_info)
    {
        $where = [];
        if (!empty($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $where[] = ['mos.entrust_time', '>=', $start_time];
        }
        if (!empty($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
            $where[] = ['mos.entrust_time', '<=', $end_time];
        }

        $field = [
            'b.name as brand_name', 'b.brand_id', 'g.goods_name', 'gs.spec_price', 'gs.spec_weight', 'gs.lvip_discount',
            'gs.spec_sn', 'gs.erp_merchant_no', 'dg.demand_sn',
            //'dg.goods_num', 'dg.goods_num as wait_buy_num',
            DB::raw('DATE(jms_mos.entrust_time) as entrust_time'),
            DB::raw('SUM(jms_dg.goods_num) as goods_num'),
            DB::raw('SUM(jms_dg.goods_num) as wait_buy_num')
            //DB::raw('SUM(jms_dg.goods_num * jms_gs.spec_weight) as total_weight')
        ];
        $goods_basic_obj = DB::table('demand_goods AS dg')->select($field)
            ->leftJoin('demand AS d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('mis_order_sub AS mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->where($where)
            ->orderBy('mos.entrust_time', 'ASC')
            ->groupBy('dg.spec_sn');
        if (!empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = '%' . $query_sn . '%';
            $goods_basic_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('dg.goods_name', 'like', $query_sn)
                    ->orWhere('dg.spec_sn', 'like', $query_sn)
                    ->orWhere('dg.erp_merchant_no', 'like', $query_sn)
                    ->orWhere('b.name', 'like', $query_sn);
            });
        }
        $goods_basic_info = $goods_basic_obj->get();
        $goods_basic_info = objectToArrayZ($goods_basic_info);
        if (empty($goods_basic_info)) {
            return $goods_basic_info;
        }
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $total_goods_num = COUNT($goods_basic_info);
        $goods_basic_list = array_slice($goods_basic_info, $start_page, $page_size);
        $return_info['total_goods_num'] = $total_goods_num;
        $return_info['goods_basic_list'] = $goods_basic_list;
        return $return_info;
    }

    /**
     * description:获取需求单实采信息
     * editor:zongxing
     * date : 2019.04.26
     */
    public static function demandBatchInfo($demand_sn)
    {
        $field = [
            'd.demand_sn',
            DB::raw('SUM(jms_rpd.day_buy_num) as handle_goods_num'),
            DB::raw('SUM(jms_rpd.day_buy_num * jms_gs.spec_price) as sort_purchase_total_price'),//采购
            DB::raw('SUM(jms_rpd.day_buy_num * jms_rpd.spec_price * 
                (1 - (jms_rpd.real_discount + 
                    ((
                    CASE
                    WHEN jms_gs.spec_weight != 0 THEN
                        jms_gs.spec_weight
                    WHEN jms_gs.estimate_weight != 0 THEN
                        jms_gs.estimate_weight
                    ELSE
                        0.00
                    END) /(jms_rpd.spec_price * 0.0022 * 100))
                )/jms_dg.sale_discount)
            )
            as psrt_price'),//实采毛利金额
            DB::raw('SUM(jms_rpd.day_buy_num * jms_rpd.spec_price * jms_dg.sale_discount) as psst_price'),//实采报价金额
        ];
        $demand_goods_obj = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('purchase_demand as pd', 'pd.demand_sn', '=', 'd.demand_sn')
            ->leftJoin('sum_demand as sd', 'sd.demand_sn', '=', 'd.demand_sn')
            ->leftJoin('real_purchase as rp', function ($join) {
                $join->orOn('rp.purchase_sn', '=', 'pd.purchase_sn')
                    ->orOn('rp.purchase_sn', '=', 'sd.sum_demand_sn');
            })
            ->leftJoin('real_purchase_detail as rpd', function ($join) {
                $join->on('rpd.real_purchase_sn', '=', 'rp.real_purchase_sn')
                    ->on('rpd.spec_sn', '=', 'dg.spec_sn');
            })
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpd.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn');
        $demand_goods_info = $demand_goods_obj->whereIn('d.demand_sn', $demand_sn)
            ->groupBy('d.demand_sn')
            ->get($field);
        $demand_goods_info = objectToArrayZ($demand_goods_info);
        return $demand_goods_info;
    }

    /**
     * description:获取子单商品实采信息
     * editor:zongxing
     * date : 2019.02.28
     */
    public static function subOrderRealGoodsInfo($sub_order_sn)
    {
        //获取需求单中的商品规格码
        $dg_spec_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->where('d.sub_order_sn', $sub_order_sn)->pluck('spec_sn');
        $dg_spec_info = objectToArrayZ($dg_spec_info);

        //获取需求单对应的采购期单号
        $dg_purchase_info = DB::table('demand as d')
            ->leftJoin('purchase_demand as pd', 'pd.demand_sn', '=', 'd.demand_sn')
            ->where('d.sub_order_sn', $sub_order_sn)->distinct()->pluck('purchase_sn');
        $dg_purchase_info = objectToArrayZ($dg_purchase_info);
        //获取需求单对应的汇总需求单单号
        $sd_sn_info = DB::table('demand as d')
            ->leftJoin('sum_demand as sd', 'sd.demand_sn', '=', 'd.demand_sn')
            ->where('d.sub_order_sn', $sub_order_sn)->distinct()->pluck('sum_demand_sn');
        $sd_sn_info = objectToArrayZ($sd_sn_info);
        $dg_purchase_info = array_merge($dg_purchase_info, $sd_sn_info);
        //获取需求单对应的采购期各个批次的实采情况
        $field = [
            'rp.real_purchase_sn', 'rpd.goods_name', 'rpd.spec_sn', 'gs.spec_price',
            'rp.delivery_time', 'rp.path_way', 'rpd.channel_discount', 'pc.channels_name',
            'rpd.day_buy_num as handle_num',
            //DB::raw('SUM(jms_rpd.day_buy_num) as handle_num'),
            DB::raw('SUM(jms_rpd.day_buy_num * jms_gs.spec_price * jms_dg.sale_discount *
                (1 - (jms_rpd.channel_discount +
                    (jms_gs.spec_weight/(jms_gs.spec_price * 0.0022 * 100))
                )/jms_dg.sale_discount)
            )
            as psrt_price'),//实采毛利金额
            DB::raw('SUM(jms_rpd.day_buy_num * jms_gs.spec_price * jms_dg.sale_discount) as psst_price'),//实采报价金额
        ];
        $demand_sort_goods_info = DB::table('real_purchase_detail as rpd')
            ->leftJoin('real_purchase as rp', 'rp.real_purchase_sn', '=', 'rpd.real_purchase_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpd.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rp.channels_id')
            ->leftJoin('demand_goods as dg', 'dg.spec_sn', '=', 'rpd.spec_sn')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->where('d.sub_order_sn', '=', $sub_order_sn)
            ->whereIn('rpd.purchase_sn', $dg_purchase_info)
            ->whereIn('rpd.spec_sn', $dg_spec_info)
            ->orderBy('rp.delivery_time', 'DESC')
            ->groupBy('rp.real_purchase_sn', 'rpd.spec_sn')->get($field)->groupBy('spec_sn');
        $demand_sort_goods_info = objectToArrayZ($demand_sort_goods_info);
        return $demand_sort_goods_info;
    }

    /**
     * description:获取汇总需求单对应的商品数据
     * editor:zongxing
     * date: 2019.05.22
     */
    public function sumDemandGoods_stop($sum_demand_sn)
    {
        $field = [
            'sd.demand_sn', 'dg.spec_sn', 'dg.goods_num', 'd.expire_time', 'su.user_name', 'mos.sale_user_account',
            'sda.yet_num'
        ];
        $sum_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('sum_demand as sd', 'sd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sort_data as sda', function ($join) {
                $join->on('sda.sum_demand_sn', '=', 'sd.sum_demand_sn');
                $join->on('sda.demand_sn', '=', 'dg.demand_sn');
                $join->on('sda.spec_sn', '=', 'dg.spec_sn');
            })
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->where('sd.sum_demand_sn', $sum_demand_sn)
            ->get($field);
        $sum_goods_info = objectToArrayZ($sum_goods_info);
        return $sum_goods_info;
    }

    public function sumDemandGoods($sd_sn_arr, $sum_spec_info)
    {
        $sd_goods_info = DB::table('sort_data as sda')->where('sda.sum_demand_sn', $sd_sn_arr)
            ->pluck('sda.goods_num as goods_num', DB::raw("concat_ws('-',jms_sda.demand_sn,jms_sda.spec_sn) as pin_str"));
        $sd_goods_info = objectToArrayZ($sd_goods_info);

        $field = [
            'sd.demand_sn', 'dg.spec_sn', 'gs.spec_price', 'd.expire_time', 'su.user_name', 'mos.sale_user_account',
            'sd.sort', 'mos.external_sn'
        ];
        $sum_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sum_demand as sd', 'sd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->whereIn('sd.sum_demand_sn', $sd_sn_arr)
            ->whereIn('sd.status', [1, 3])
            ->whereIn('dg.spec_sn', $sum_spec_info)
            ->orderBy('sd.sort', 'ASC')
            ->get($field);
        $sum_goods_info = objectToArrayZ($sum_goods_info);
        foreach ($sum_goods_info as $k => $v) {
            $pin_str = $v['demand_sn'] . '-' . $v['spec_sn'];
            $sum_goods_info[$k]['goods_num'] = intval($sd_goods_info[$pin_str]);
        }
        return $sum_goods_info;
    }

    /**
     * description:商品标记
     * editor:zongxing
     * date : 2018.12.20
     */
    public function markGoodsPostpone($param_info, $demand_info = [])
    {
        //dd($param_info, $demand_info);
        $demand_sn = trim($param_info['demand_sn']);
        $spec_sn = trim($param_info['spec_sn']);
        $is_postpone = intval($param_info['is_postpone']);
        if (isset($param_info['sum_demand_sn'])) {
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            if ($is_postpone == 1) {//标记延期数据
                $res = DB::transaction(function () use ($demand_sn, $spec_sn, $sum_demand_sn) {
                    //更新合单需求单关系表
                    $update_where = [
                        ['sum_demand_sn', $sum_demand_sn],
                        ['demand_sn', $demand_sn],
                    ];
                    $update_data = ['status' => 3];
                    DB::table('sum_demand')->where($update_where)->update($update_data);
                    //更新需求单商品表
                    $dg_where = [
                        ['demand_sn', $demand_sn],
                        ['spec_sn', $spec_sn],
                    ];
                    $update_goods = ['is_postpone' => 1];
                    DB::table('demand_goods')->where($dg_where)->update($update_goods);
                    //更新合单需求单分货表
                    $update_where = [
                        ['sum_demand_sn', $sum_demand_sn],
                        ['demand_sn', $demand_sn],
                        ['spec_sn', $spec_sn],
                    ];
                    $update_data = ['is_postpone' => 1];
                    DB::table('sort_data')->where($update_where)->update($update_data);
                    //更新需求单表
                    $update_demand = [
                        'status' => 7,
                        'demand_type' => 2,
                    ];
                    $res = DB::table('demand')->where('demand_sn', $demand_sn)->update($update_demand);
                    return $res;
                });
            } elseif ($is_postpone == 2) {//取消标记延期数据
                $sd_num = DB::table('sum_demand')->where('demand_sn', $demand_sn)->count();
                $res = DB::transaction(function () use ($demand_sn, $spec_sn, $sum_demand_sn, $demand_info, $sd_num) {
                    $sd_goods_num = intval($demand_info['goods_num']);//当前合单中需求单对应商品的需求数
                    if ($sd_num > 1) {
                        //首先删除分货表中的原始数据,如果需求单对应的延期商品全部删完,则需求单在合单中的状态应为2(关闭),否则不更新还为1(采购中)
                        $sd_where = [
                            ['sum_demand_sn', $sum_demand_sn],
                            ['demand_sn', $demand_sn],
                            ['spec_sn', $spec_sn],
                        ];
                        DB::table('sort_data')->where($sd_where)->delete();
                        //更新合单商品表
                        $update_where = [
                            ['sum_demand_sn', $sum_demand_sn],
                            ['spec_sn', $spec_sn],
                        ];
                        DB::table('sum_goods')->where($update_where)->decrement('goods_num', $sd_goods_num);
                    } else {
                        //更新合单需求单分货表
                        $update_where = [
                            ['sum_demand_sn', $sum_demand_sn],
                            ['demand_sn', $demand_sn],
                            ['spec_sn', $spec_sn],
                        ];
                        $update_data = ['is_postpone' => 2];
                        DB::table('sort_data')->where($update_where)->update($update_data);
                    }

                    $where = [
                        ['sum_demand_sn', $sum_demand_sn],
                        ['demand_sn', $demand_sn],
                    ];
                    $sort_sdg_num = DB::table('sort_data')->where($where)->count();
                    if ($sort_sdg_num == 0) {
                        //更新合单需求单关系表
                        $update_where = [
                            ['sum_demand_sn', $sum_demand_sn],
                            ['demand_sn', $demand_sn],
                        ];
                        $update_data = ['status' => 2];
                        DB::table('sum_demand')->where($update_where)->update($update_data);
                    }

                    //更新需求单商品表
                    $dg_where = [
                        ['demand_sn', $demand_sn],
                        ['spec_sn', $spec_sn],
                    ];
                    $update_goods = ['is_postpone' => 2];
                    DB::table('demand_goods')->where($dg_where)->update($update_goods);//取消延期,直接更新商品状态为正常的

                    //更新需求单状态
                    $where = [
                        ['demand_sn', $demand_sn],
                        ['is_postpone', 1],
                    ];
                    $dg_num = DB::table('demand_goods')->where($where)->count();
                    $update_demand = ['status' => 7];
                    if ($dg_num == 0) {
                        $update_demand = ['status' => 5];
                    }
                    //更新需求单表
                    $res = DB::table('demand')->where('demand_sn', $demand_sn)->update($update_demand);
                    return $res;
                });
            }
        } else {
            $select_where = [
                ['demand_sn', $demand_sn],
            ];
            $sum_demand_sn = DB::table('sort_data')->where($select_where)->orderBy('create_time', 'DESC')->first(['sum_demand_sn']);
            $sum_demand_sn = objectToArrayZ($sum_demand_sn);
            $res = DB::transaction(function () use ($demand_sn, $spec_sn, $demand_info, $is_postpone, $sum_demand_sn) {
                //更新需求单分货表
                $dg_where = [
                    ['sum_demand_sn', $sum_demand_sn],
                    ['demand_sn', $demand_sn],
                    ['spec_sn', $spec_sn],
                ];
                $update_goods = ['is_postpone' => 2];
                DB::table('sort_data')->where($dg_where)->update($update_goods);
                //更新需求单商品表
                $dg_where = [
                    ['demand_sn', $demand_sn],
                    ['spec_sn', $spec_sn],
                ];
                $update_goods = ['is_postpone' => $is_postpone];
                DB::table('demand_goods')->where($dg_where)->update($update_goods);
                //更新需求单状态
                $where = [
                    ['demand_sn', $demand_sn],
                    ['is_postpone', 1],
                ];
                $dg_num = DB::table('demand_goods')->where($where)->count();
                $update_demand = ['status' => 7];
                if ($dg_num == 0) {
                    $update_demand = ['status' => 5];
                }
                //更新需求单表
                $res = DB::table('demand')->where('demand_sn', $demand_sn)->update($update_demand);
                return $res;
            });
        }
        return $res;
    }


    /**
     * description:获取需求单某个商品的信息
     * author:zhangdong
     * date : 2019.06.03
     */
    public function getDemandGoods($demand_sn, $spec_sn)
    {
        $where = [
            ['demand_sn', $demand_sn],
            ['spec_sn', $spec_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description 获取DD需求单的信息
     * author zongxing
     * date 2019.06.20
     */
    public function getDdDemandList($sub_order_sn)
    {
        $field = [
            'd.demand_sn', 'd.sub_order_sn', 'd.expire_time', 'd.sale_user_id', 'su.user_name', 'dg.goods_num',
            'mosg.spec_price', 'dg.sale_discount', 'mosg.exw_discount', 'gs.spec_weight', 'gs.estimate_weight',
            'g.brand_id', 'dg.spec_sn',
        ];
        $dg_list = DB::table($this->table)
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->leftJoin('mis_order_sub_goods as mosg', function ($join) {
                $join->on('mosg.sub_order_sn', '=', 'mos.sub_order_sn');
                $join->on('mosg.spec_sn', '=', 'dg.spec_sn');
            })
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->whereIn('d.sub_order_sn', $sub_order_sn)
            ->get($field);
        $dg_list = objectToArrayZ($dg_list);
        return $dg_list;
    }

    /**
     * description 获取DD需求单的信息
     * author zongxing
     * date 2019.06.20
     */
    public function createDdDemandList($dg_info, $sd_info)
    {
        foreach ($dg_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $goods_num = $diff_num = intval($v['goods_num']);
            $spec_price = floatval($v['spec_price']);
            $demand_diff_price = $spec_price * $goods_num;
            if (isset($sd_info[$spec_sn])) {
                $sort_num = intval($sd_info[$spec_sn]);
                $diff_num = $goods_num - $sort_num;
                $sort_diff_price = $spec_price * $sort_num;
                $demand_diff_price = $demand_diff_price - $sort_diff_price;
            }
            $dg_info[$k]['diff_num'] = $diff_num;
            $dg_info[$k]['demand_diff_price'] = number_format($demand_diff_price, 2, '.', '');
        }

        $total_demand_list = [];
        foreach ($dg_info as $k => $v) {
            $demand_sn = $v['demand_sn'];
            $real_discount = $v['exw_discount'];
            $spec_weight = floatval($v['spec_weight']);
            $estimate_weight = floatval($v['estimate_weight']);
            $real_weight = $spec_weight == 0 ? $estimate_weight : $spec_weight;
            $goods_num = intval($v['goods_num']);
            $diff_num = intval($v['diff_num']);
            $demand_diff_price = floatval($v['demand_diff_price']);
            $spec_price = floatval($v['spec_price']);
            $sale_discount = floatval($v['sale_discount']);
            $demand_pur_price = $goods_num * $spec_price;//采购需求总额
            $demand_sale_price = $goods_num * $spec_price * $sale_discount;//需求单销售总额
            if ($spec_price == 0 || $sale_discount == 0) {
                $demand_discount_price = 0;
            } else {
                $demand_discount_price = ($goods_num * $spec_price *
                    (1 - (
                            ($real_discount + ($real_weight / $spec_price / 0.0022 / 100)) / $sale_discount)
                    )
                );//需求单报价毛利金额
            }
            if (isset($total_demand_list[$demand_sn])) {
                $total_demand_list[$demand_sn][0]['goods_num'] += $goods_num;
                $total_demand_list[$demand_sn][0]['diff_num'] += $diff_num;
                $total_demand_list[$demand_sn][0]['demand_pur_price'] += $demand_pur_price;
                $total_demand_list[$demand_sn][0]['demand_sale_price'] += $demand_sale_price;
                $total_demand_list[$demand_sn][0]['demand_discount_price'] += $demand_discount_price;
                $total_demand_list[$demand_sn][0]['demand_diff_price'] += $demand_diff_price;
            } else {
                $total_demand_list[$demand_sn][0] = [
                    'demand_sn' => $demand_sn,
                    'sub_order_sn' => $v['sub_order_sn'],
                    'expire_time' => $v['expire_time'],
                    'sale_user_id' => $v['sale_user_id'],
                    'user_name' => $v['user_name'],
                    'goods_num' => $goods_num,
                    'diff_num' => $diff_num,
                    'demand_pur_price' => $demand_pur_price,
                    'demand_sale_price' => $demand_sale_price,
                    'demand_discount_price' => $demand_discount_price,
                    'demand_diff_price' => $demand_diff_price,
                ];
            }
        }
        return $total_demand_list;
    }

    /**
     * description 获取合单下需求单的信息
     * author zongxing
     * date 2019.06.20
     */
    public function sumDemandInfo($sum_demand_sn)
    {
        $sd_goods_info = DB::table('sort_data as sda')
            ->where('sda.sum_demand_sn', $sum_demand_sn)
            ->groupBy('sda.demand_sn')
            ->pluck(DB::raw('sum(jms_sda.goods_num) as goods_num'), 'sda.demand_sn');
        $sd_goods_info = objectToArrayZ($sd_goods_info);

        $field = [
            'sd.demand_sn', 'd.expire_time', 'su.user_name', 'mos.sale_user_account',
            'sd.sort', 'mos.external_sn', 'd.demand_type',
            DB::raw('(CASE jms_d.status
                WHEN 5 THEN "采购中"
                WHEN 7 THEN "延期"
                END
                ) as status'),
            DB::raw('(CASE jms_d.demand_type
                WHEN 1 THEN "正常订单"
                WHEN 2 THEN "延期订单"
                END
                ) as demand_type'),
            DB::raw('count(jms_dg.spec_sn) as sku_num')
        ];
        $sum_goods_info = DB::table('demand_goods as dg')
            ->leftJoin('demand as d', 'd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sum_demand as sd', 'sd.demand_sn', '=', 'dg.demand_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'd.sale_user_id')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'd.sub_order_sn')
            ->where('sd.sum_demand_sn', $sum_demand_sn)
//            ->where('dg.is_postpone', DB::raw('(
//                CASE jms_d.status
//                    WHEN 5 THEN 2
//                    ELSE 1
//                    END
//                )'))
            ->whereIn('sd.status', [1, 3])
            ->orderBy('sd.sort', 'ASC')
            ->groupBy('dg.demand_sn')
            ->get($field);
        $sum_goods_info = objectToArrayZ($sum_goods_info);
        foreach ($sum_goods_info as $k => $v) {
            $sum_goods_info[$k]['goods_num'] = intval($sd_goods_info[$v['demand_sn']]);
        }
        return $sum_goods_info;
    }

    /**
     * description 获取合单下需求单商品的信息
     * author zongxing
     * date 2019.06.27
     */
    public function sumDemandGoodsInfo($sd_sn_arr)
    {
        $field = ['d.demand_sn', 'd.expire_time'];
        $demand_info = DB::table('demand as d')
            ->leftJoin('sum_demand as sd', 'sd.demand_sn', '=', 'd.demand_sn')
            ->whereIn('sd.sum_demand_sn', $sd_sn_arr)
            ->whereIn('sd.status', [1, 3])->distinct()
            ->get($field);
        $demand_info = objectToArrayZ($demand_info);

        $field = [
            'sd.demand_sn', 'sd.spec_sn',
            DB::raw('max(jms_sd.goods_num) as goods_num'),
            DB::raw('max(jms_sd.goods_num) - sum(jms_sd.yet_num) as diff_num'),
            DB::raw('max(jms_sd.goods_num) * jms_gs.spec_price as dg_price'),
            DB::raw('(max(jms_sd.goods_num) - sum(jms_sd.yet_num)) * jms_gs.spec_price as dg_diff_price')
        ];
        $sd_goods_info = DB::table('sort_data as sd')->whereIn('sd.sum_demand_sn', $sd_sn_arr)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sd.spec_sn')
            ->groupBy('sd.demand_sn')->groupBy('sd.spec_sn')
            ->get($field);
        $sd_goods_info = objectToArrayZ($sd_goods_info);

        $sd_demand_info = [];
        foreach ($sd_goods_info as $k => $v) {
            $demand_sn = $v['demand_sn'];
            $goods_num = intval($v['goods_num']);
            $diff_num = intval($v['diff_num']);
            $dg_price = floatval($v['dg_price']);
            $dg_diff_price = floatval($v['dg_diff_price']);
            if (isset($sd_demand_info[$demand_sn])) {
                $sd_demand_info[$demand_sn]['goods_num'] += $goods_num;
                $sd_demand_info[$demand_sn]['diff_num'] += $diff_num;
                $sd_demand_info[$demand_sn]['dg_price'] += $dg_price;
                $sd_demand_info[$demand_sn]['dg_diff_price'] += $dg_diff_price;
            } else {
                $sd_demand_info[$demand_sn]['goods_num'] = $goods_num;
                $sd_demand_info[$demand_sn]['diff_num'] = $diff_num;
                $sd_demand_info[$demand_sn]['dg_price'] = $dg_price;
                $sd_demand_info[$demand_sn]['dg_diff_price'] = $dg_diff_price;
            }
        }

        $total_demand_info = [];
        foreach ($demand_info as $k => $v) {
            $demand_sn = $v['demand_sn'];
            if (isset($sd_demand_info[$demand_sn])) {
                $total_demand_info[] = array_merge($demand_info[$k], $sd_demand_info[$demand_sn]);
            }
        }
        return $total_demand_info;
    }

    /**
     * description 获取指定需求单对应商品的信息
     * author zongxing
     * date 2019.06.28
     */
    public function getDgTotalInfo($demand_sn_arr)
    {
        $dg_total_info = DB::table('demand_goods as dg')
            ->whereIn('demand_sn', $demand_sn_arr)->groupBy('dg.spec_sn')
            ->pluck(DB::raw('sum(jms_dg.goods_num) as goods_num'), 'dg.spec_sn');
        $dg_total_info = objectToArrayZ($dg_total_info);
        return $dg_total_info;
    }

}//end of class
