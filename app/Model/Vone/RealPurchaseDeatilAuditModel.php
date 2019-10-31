<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RealPurchaseDeatilAuditModel extends Model
{
    protected $table = 'real_purchase_detail_audit as rpda';
    //可操作字段
    protected $field = [
        'rpda.id', 'rpda.real_purchase_sn', 'rpda.purchase_sn', 'rpda.erp_prd_no',
        'rpda.erp_merchant_no','rpda.spec_sn', 'rpda.goods_name', 'rpda.pay_price',
        'rpda.spec_price', 'rpda.lvip_price', 'rpda.day_buy_num', 'rpda.sort_num',
        'rpda.channel_discount','rpda.is_match', 'rpda.parent_spec_sn', 'rpda.real_discount',
    ];

    /**
     * description:获取各个批次的详细信息
     * editor:zongxing
     * date : 2019.04.08
     * return Array
     */
    public function getBatchGoodsInfo_stop($param_info)
    {
        $field = ['rpa.purchase_sn', 'rpa.real_purchase_sn', 'rpa.path_way',
            'rpa.group_sn', 'rpa.batch_cat', 'pm.method_name', 'pc.channels_name', 'rpa.port_id', 'rpa.status',
            DB::raw('SUM(day_buy_num) as total_buy_num'), 'pd.delivery_team', 's.sum_demand_name', 'rpa.delivery_time',
            'rpa.arrive_time',
        ];
        $batch_goods_obj = DB::table($this->table)
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('audit as a', 'a.audit_sn', '=', 'rpa.audit_sn');
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = '%' . $query_sn . '%';
            $batch_goods_obj->where(function ($where) use ($query_sn) {
                $where->orWhere('rpa.purchase_sn', 'LIKE', $query_sn);
                $where->orWhere('rpa.real_purchase_sn', 'LIKE', $query_sn);
                $where->orWhere('pm.method_name', 'LIKE', $query_sn);
                $where->orWhere('pc.channels_name', 'LIKE', $query_sn);
            });
        }
        $batch_goods_info = $batch_goods_obj->orderBy('rpa.batch_cat', 'asc')
            ->orderBy('rpa.create_time', 'desc')
            ->groupBy('real_purchase_sn')->get($field)
            ->groupBy('purchase_sn');
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        return $batch_goods_info;
    }

    public function getBatchGoodsInfo($param_info)
    {
        $total_num = $batch_list = DB::table('real_purchase_audit')->count();
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $rpd_obj = DB::table('real_purchase_audit as rpa')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id');
        if (isset($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $rpd_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('rpa.real_purchase_sn', $query_sn);
                $query->orWhere('rpa.purchase_sn', $query_sn);
            });
        }
        $where = [
            'rpa.status' => 3,
            'rpa.is_integral' => 0,
            'rpa.method_id' => 34,
        ];
        $batch_list = $rpd_obj->where($where)
            ->where('pm.method_property', '!=', 2)//外采不返积分
            ->orderBy('rpa.integral_time', 'asc')
            ->distinct()->skip($start_page)->take($page_size)->pluck('purchase_sn');
        $batch_list = objectToArrayZ($batch_list);
        $return_info = [
            'total_num' => $total_num,
            'batch_list' => $batch_list,
        ];
        return $return_info;

        $field = ['rpa.purchase_sn', 'rpa.real_purchase_sn', 'rpa.path_way',
            'rpa.group_sn', 'rpa.batch_cat', 'pm.method_name', 'pc.channels_name', 'rpa.port_id', 'rpa.status',
            DB::raw('SUM(day_buy_num) as total_buy_num'), 'pd.delivery_team', 's.sum_demand_name', 'rpa.delivery_time',
            'rpa.arrive_time',
        ];
        $batch_goods_obj = DB::table($this->table)
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('audit as a', 'a.audit_sn', '=', 'rpa.audit_sn');
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = '%' . $query_sn . '%';
            $batch_goods_obj->where(function ($where) use ($query_sn) {
                $where->orWhere('rpa.purchase_sn', 'LIKE', $query_sn);
                $where->orWhere('rpa.real_purchase_sn', 'LIKE', $query_sn);
                $where->orWhere('pm.method_name', 'LIKE', $query_sn);
                $where->orWhere('pc.channels_name', 'LIKE', $query_sn);
            });
        }
        $batch_goods_info = $batch_goods_obj->orderBy('rpa.batch_cat', 'asc')
            ->orderBy('rpa.create_time', 'desc')
            ->groupBy('real_purchase_sn')->get($field)
            ->groupBy('purchase_sn');
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        return $batch_goods_info;
    }

    /**
     * description:获取批次的详细信息
     * editor:zongxing
     * date : 2019.04.03
     * return Array
     */
    public function getBatchAuditDetail($param_info)
    {
        $real_purchase_sn = trim($param_info['real_purchase_sn']);
        $where[] = ['rpda.real_purchase_sn', '=', $real_purchase_sn];
        $field = $this->field;
        $add_field = ['gs.erp_ref_no', 'g.brand_id', 'rpa.status'];
        $field = array_merge($field, $add_field);
        $batch_goods_info = DB::table($this->table)
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->where($where)->get($field);
        $batch_goods_info = objectToArrayZ($batch_goods_info);
        return $batch_goods_info;
    }

    /**
     * description:获取批次的详细信息
     * editor:zongxing
     * date : 2019.04.03
     * return Array
     */
    public function getBatchAuditDetailInfo($param_info, $where, $channel_goods_spec = [])
    {
        $channel_goods_obj = DB::table('real_purchase_detail_audit as rpda')
            ->select('g.goods_name', 'gs.spec_sn', 'gs.erp_prd_no', 'gs.erp_merchant_no', 'gs.goods_label',
                'gs.spec_price', 'gs.erp_ref_no', 'method_name', 'channels_name',
                DB::raw('sum(jms_rpda.day_buy_num) as day_buy_num'),
                DB::raw('sum(jms_rpda.day_buy_num * jms_gs.spec_price * jms_rpda.channel_discount) as create_total_price'),
                DB::raw('sum(jms_rpda.day_buy_num * jms_gs.spec_price * jms_rpda.channel_discount) as deliver_total_price'),
                DB::raw('sum(jms_rpda.day_buy_num * jms_gs.spec_price * jms_qd.brand_discount) as quote_total_price')
            )
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('quote_discount as qd', 'qd.brand_id', '=', 'g.brand_id');
        if (!empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = '%' . $query_sn . '%';
            $channel_goods_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('g.goods_name', 'LIKE', $query_sn)
                    ->orWhere('gs.spec_sn', 'LIKE', $query_sn)
                    ->orWhere('gs.erp_prd_no', 'LIKE', $query_sn)
                    ->orWhere('gs.erp_merchant_no', 'LIKE', $query_sn)
                    ->orWhere('gs.erp_ref_no', 'LIKE', $query_sn);
            });
        }
        if (!empty($channel_goods_spec)) {
            $channel_goods_obj->whereIn('rpda.spec_sn', $channel_goods_spec);
        }
        $channel_goods_info = $channel_goods_obj->where($where)->groupBy('rpda.spec_sn')->groupBy('channels_method_sn')
            ->get()->groupBy('spec_sn');
        $channel_goods_info = objectToArrayZ($channel_goods_info);
        return $channel_goods_info;
    }

    /**
     * description:获取各个批次的积分信息
     * editor:zongxing
     * date : 2019.04.24
     * return Array
     */
    public function getBatchIntegralInfo_stop($batch_sn_list)
    {
        $field = [
            'pd.id as purchase_id', 'rpda.purchase_sn', 'rpda.real_purchase_sn', 'pm.method_name', 'pc.channels_name',
            'rpa.path_way', 'rpa.delivery_time', 'rpa.batch_cat', 'rpa.is_integral', 'rpa.port_id',
            'rpda.spec_sn', 'rpda.allot_num', 'b.brand_id',
            DB::raw('SUM(jms_rpda.allot_num) as total_buy_num'),
            //待返积分 = 美金原价*商品数量*[(1-成本折扣) - (1-VIP折扣)]
            DB::raw('SUM(jms_gs.spec_price * jms_rpda.allot_num * (jms_dt2.discount - jms_dt.discount)) as total_integral'),
        ];
        $real_goods_obj = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->leftJoin('discount as d', function ($join) {
                $join->on('d.brand_id', '=', 'g.brand_id');
                $join->on('d.method_id', '=', 'rpa.method_id');
                $join->on('d.channels_id', '=', 'rpa.channels_id');
            })
            ->leftJoin('discount_type as dt', function ($join) {
                $join->on('dt.discount_id', '=', 'd.id');
                $join->on('dt.type_id', '=', 'd.cost_discount_id');
            })
            ->leftJoin('discount_type as dt2', function ($join) {
                $join->on('dt2.discount_id', '=', 'd.id');
                $join->on('dt2.type_id', '=', 'd.vip_discount_id');
            })
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rpa.purchase_sn');
        $real_goods_info = $real_goods_obj->whereIn('purchase_sn', $batch_sn_list)->orderBy('rpa.create_time', 'desc')
            ->groupBy('rpa.real_purchase_sn')
            ->get($field);
        $real_goods_info = objectToArrayZ($real_goods_info);
        return $real_goods_info;
    }

    /**
     * description:以汇总需求单为单位，获取汇总需求单商品的采购数据信息
     * editor:zongxing
     * date : 2019.05.31
     * return Array
     */
    public function getSgRealInfo($sum_demand_sn)
    {
        $field = [
            'rpda.purchase_sn', 'rpda.real_purchase_sn', 'pm.method_name', 'pc.channels_name',
            'rpa.path_way', 'rpa.delivery_time', 'rpa.batch_cat', 'rpa.port_id',
            'rpda.spec_sn', 'rpda.day_buy_num', 'b.brand_id',
            DB::raw('SUM(jms_rpda.day_buy_num) as real_num'),
        ];
        $real_goods_obj = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', function ($join) {
                $join->on('rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn');
                $join->on('rpa.purchase_sn', '=', 'rpda.purchase_sn');
            })
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->leftJoin('discount as d', function ($join) {
                $join->on('d.brand_id', '=', 'g.brand_id');
                $join->on('d.method_id', '=', 'rpa.method_id');
                $join->on('d.channels_id', '=', 'rpa.channels_id');
            })
            ->join('sum as s', 's.sum_demand_sn', '=', 'rpa.purchase_sn');
        $real_goods_info = $real_goods_obj->whereIn('s.sum_demand_sn', $sum_demand_sn)->where('rpa.status', 3)
            ->orderBy('rpa.batch_cat', 'ASC')
            ->orderBy('rpa.create_time', 'DESC')
            ->groupBy('rpa.group_sn')
            ->get($field)
            ->groupBy('purchase_sn');
        $real_goods_info = objectToArrayZ($real_goods_info);
        return $real_goods_info;
    }

    /**
     * description:获取各个渠道上传数据信息
     * editor:zongxing
     * type:GET
     * date : 2019.06.22
     * return Array
     */
    public function getBatchDataInfo($param_info)
    {
        $where = [];
        if (!empty($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
        }
        if (!empty($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
        }
        if (isset($param_info['month'])) {
            $month = trim($param_info['month']);
            $start_time = Carbon::parse($month)->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::parse($month)->endOfMonth()->toDateTimeString();
        }
        if (!isset($param_info['start_time']) && !isset($param_info['end_time']) && !isset($param_info['month'])) {
            $start_time = Carbon::now()->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::now()->endOfMonth()->toDateTimeString();
        }
        $where[] = ['rpa.delivery_time', '>=', $start_time];
        $where[] = ['rpa.delivery_time', '<=', $end_time];
        $field = [
            DB::raw('(CASE
                    WHEN jms_rpa.original_or_discount = 0 THEN
                        sum(jms_rpda.day_buy_num * jms_rpda.spec_price)
                    WHEN jms_rpa.original_or_discount = 1 THEN
                        sum(jms_rpda.day_buy_num * jms_rpda.pay_price)
                    ELSE
                        sum(jms_rpda.day_buy_num * jms_rpda.spec_price * jms_rpda.channel_discount)
                    END) as batch_price'),
            DB::raw('sum(day_buy_num) as day_buy_num'),
            DB::raw('concat_ws("-",jms_pc.channels_name,jms_pm.method_name) as channel_method_name'),
        ];
        $last_batch_info = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->where($where)
            ->groupBy('rpa.channels_id', 'rpa.method_id')
            ->orderBy('rpa.create_time', 'DESC')
            ->get($field)
            ->groupBy('channel_method_name');
        $last_batch_info = objectToArrayZ($last_batch_info);
        return $last_batch_info;
    }

    /**
     * description:获取待返积分批次列表
     * editor:zongxing
     * date : 2019.08.05
     * return Array
     */
    public function getBatchIntegralDetail($purchase_sn_arr)
    {
        $field = [
            'rpa.purchase_sn', 'rpa.real_purchase_sn', 'pm.method_name', 'pc.channels_name',
            'rpa.path_way', 'rpa.delivery_time as rp_delivery_time', 'rpa.arrive_time as rp_arrive_time', 'rpa.batch_cat',
            'rpa.is_integral', 'rpa.port_id', 'rpda.spec_sn', 'rpa.integral_time',
            'rpa.create_time', 'rpa.channels_method_sn',
            DB::raw('(if(jms_pd.delivery_team is null, jms_s.sum_demand_name, jms_pd.delivery_team)) title_name'),
            DB::raw('SUM(jms_rpda.day_buy_num) as total_buy_num'),
            //待返积分 = 美金原价*商品数量*[(1-成本折扣) - (1-VIP折扣)]
            //DB::raw('SUM(jms_gs.spec_price * jms_rpda.day_buy_num * (jms_dt2.discount - jms_dt.discount)) as total_integral'),
            //待返积分 = 商品数量*[实付美金-美金原价*成本折扣]
            DB::raw('SUM(jms_rpda.day_buy_num * (jms_rpda.lvip_price - jms_rpda.spec_price * jms_rpda.channel_discount)) as total_integral'),
        ];
        $rpd_obj = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rpa.purchase_sn');
        $batch_integral_info = $rpd_obj
            ->orderBy('rpa.integral_time', 'DESC')
            ->groupBy('rpda.real_purchase_sn')
            ->whereIn('rpa.purchase_sn', $purchase_sn_arr)
            ->get($field);
        $batch_integral_info = objectToArrayZ($batch_integral_info);
        return $batch_integral_info;
    }

    /**
     * description:获取待审核批次列表-
     * editor:zongxing
     * date : 2019.08.05
     * return Array
     */
    public function getWaitAuditBatchDetail($param_info, $purchase_sn_arr)
    {
        $field = [
            'rpa.purchase_sn', 'rpa.real_purchase_sn', 'pm.method_name', 'pc.channels_name',
            'rpa.path_way', 'rpa.delivery_time as rp_delivery_time', 'rpa.arrive_time as rp_arrive_time', 'rpa.batch_cat',
            'rpa.is_integral', 'rpa.port_id', 'rpda.spec_sn', 'rpa.integral_time',
            'rpa.create_time', 'rpa.channels_method_sn',
            DB::raw('(if(jms_pd.delivery_team is null, jms_s.sum_demand_name, jms_pd.delivery_team)) title_name'),
            DB::raw('SUM(jms_rpda.day_buy_num) as total_buy_num'),
            DB::raw('SUM(ROUND(jms_rpda.spec_price * jms_rpda.day_buy_num, 2)) total_price'),
            DB::raw('(CASE jms_rpa.status
                WHEN 0 THEN "待审核"
                WHEN 1 THEN "审核通过"
                WHEN 2 THEN "审核未通过"
                WHEN 3 THEN "数据已提交"
                END ) status'),
            DB::raw('(CASE jms_rpa.port_id
                WHEN 1001 THEN "香港"
                WHEN 1002 THEN "保税"
                WHEN 1003 THEN "西安"
                END ) port_name'),
        ];
        $rpd_obj = DB::table('real_purchase_detail_audit as rpda')
            ->leftJoin('real_purchase_audit as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'rpda.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rpa.purchase_sn');
        if (isset($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $rpd_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('rpa.real_purchase_sn', $query_sn);
                $query->orWhere('rpa.purchase_sn', $query_sn);
                $query->orWhere('pc.channels_name', $query_sn);
                $query->orWhere('pm.method_name', $query_sn);
            });
        }

        if (isset($param_info['status'])) {
            $status = intval($param_info['status']);
            $rpd_obj->where('rpa.status', $status);
        }
        $batch_detail_info = $rpd_obj
            ->orderBy('rpa.create_time', 'DESC')
            ->groupBy('rpda.real_purchase_sn')
            ->whereIn('rpa.purchase_sn', $purchase_sn_arr)
            ->get($field);
        $batch_detail_info = objectToArrayZ($batch_detail_info);
        return $batch_detail_info;
    }

    /**
     * description 查询批次单商品信息
     * author zhangdong
     * date 2019.10.08
     */
    public function queryGoodsSimple($realPurchaseSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
        ];
        $this->field = [
            'id', 'spec_sn', 'sort_num', 'spec_price', 'lvip_price', 'pay_price',
            'channel_discount', 'real_discount',
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }
    /**
     * description 获取批次详细信息
     * editor zongxing
     * date 2019.09.25
     * return Array
     */
    public function getBatchTotalDetail($sum_sn_info)
    {
        $fields = [
            'pd.id as purchase_id', 'rpa.delivery_time', 'rpa.purchase_sn', 'rpa.real_purchase_sn', 'method_name', 'channels_name',
            'path_way', 'rpa.delivery_time', 'rpa.arrive_time', 'group_sn', 'batch_cat', 'rpa.status',
            'day_buy_num', 'port_id', 'is_mother', 'sum_demand_name','rpa.create_time',
            DB::raw('sum(day_buy_num) as total_buy_num')
        ];
        if (isset($param_info['status'])) {
            $status = intval($param_info['status']);
            $expireTime = $this->create_expire_time($status);
            if ($status == 1) {
                $add_field = [DB::raw('if(jms_rpa.arrive_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 2) {
                //待确认差异
                $add_field = [DB::raw('if(jms_rpa.allot_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 3) {
                //待开单
                $add_field = [DB::raw('if(jms_rpa.diff_time > ' . $expireTime . ',0,1) expire_status')];
            } elseif ($status == 4) {
                //待入库
                $add_field = [DB::raw('if(jms_rpa.billing_time > ' . $expireTime . ',0,1) expire_status')];
            }
        }
        if (!empty($add_field)) {
            $fields = array_merge($fields, $add_field);
        }
        $purchase_goods_info = DB::table('real_purchase_detail_audit as rpda')
            ->select($fields)
            ->leftJoin('real_purchase as rpa', 'rpa.real_purchase_sn', '=', 'rpda.real_purchase_sn')
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('sum as s', 's.sum_demand_sn', '=', 'rpa.purchase_sn')
            ->leftJoin("purchase_method as pm", "pm.id", '=', "rpa.method_id")
            ->leftJoin("purchase_channels as pc", "pc.id", '=', "rpa.channels_id")
            ->whereIn('rpa.purchase_sn', $sum_sn_info)
            //->orderBy('rpa.batch_cat', 'asc')
            ->orderBy('rpa.create_time', 'desc')
            ->groupBy('rpda.real_purchase_sn')
            ->get();
        $purchase_goods_info = objectToArrayZ($purchase_goods_info);
        dd($purchase_goods_info);
        return $purchase_goods_info;
    }

    /**
     * description 获取批次单商品信息-迁移
     * author zhangdong
     * date 2019.10.08
     */
    public function getBarchGoods($realPurchaseSn, $specSn)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
            ['spec_sn', $specSn],
        ];

        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description 修改可分货数量-迁移
     * author:zhangdong
     * date : 2019.10.08
     */
    public function modifySortNum($realPurchaseSn, $specSn, $num)
    {
        $where = [
            ['real_purchase_sn', $realPurchaseSn],
            ['spec_sn', $specSn],
        ];
        $update = [
            'sort_num' => DB::raw('sort_num - ' . $num),
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description 统计对应批次单下SKU的可分货数量未分完的条数
     * author zhangdong
     * date 2019.10.10
     */
    public function getRemainSortNum($arrRealSn)
    {
        $where = [
            ['sort_num', '>', 0],
        ];
        $field = [
            DB::raw('COUNT(*) as num'),'real_purchase_sn'
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->where($where)->whereIn('real_purchase_sn', $arrRealSn)
            ->groupBy('real_purchase_sn')->get();
        return $queryRes;
    }








}//end of class
