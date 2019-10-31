<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class RealPurchaseAuditModel extends Model
{
    protected $table = 'real_purchase_audit as rpa';
    //可操作字段
    protected $field = ['rpa.real_purchase_sn', 'rpa.audit_sn', 'rpa.purchase_sn', 'rpa.demand_sn', 'rpa.method_id',
        'rpa.channels_id', 'rpa.path_way', 'rpa.port_id', 'rpa.user_id', 'rpa.data_name', 'rpa.group_sn', 'rpa.parent_id',
        'rpa.delivery_time', 'rpa.arrive_time', 'rpa.batch_cat', 'rpa.supplier_id', 'rpa.user_id',
        'rpa.status', 'rpa.task_id', 'rpa.type', 'rpa.buy_time', 'rpa.original_or_discount'];
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

    //批次类别,1:正常批次;2:预采批次;3:常备批次 2019.09.18 zhnagdong
    public $batch_cat = [
        'GENERAL' => 1,//正常批次
        'PRE_PURCHASE' => 2,//预采批次
        'STANDBY' => 3,//常备批次
    ];

    //实采单状态：0，待审核；1，审核通过；2审核不通过；3数据已提交
    public $status = [
        'WAIT' => 0,//待审核
        'PASS' => 1,//审核通过
        'REFUSE' => 2,//审核不通过
        'YET_SUBMIT' => 3,//数据已提交
    ];

    /**
     * description:获取批次列表
     * editor:zongxing
     * date : 2019.04.08
     * return Array
     */
    public function getBatchIntegralList($param_info)
    {
        $batch_num_obj = DB::table('real_purchase_audit as rpa');

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
            $batch_num_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('rpa.real_purchase_sn', $query_sn);
                $query->orWhere('rpa.purchase_sn', $query_sn);
            });
        }
        if (isset($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $rpd_obj->where(function ($query) use ($start_time) {
                $query->where('rpa.integral_time', '>=', $start_time);
            });
            $batch_num_obj->where(function ($query) use ($start_time) {
                $query->where('rpa.integral_time', '>=', $start_time);
            });
        }
        if (isset($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
            $rpd_obj->where(function ($query) use ($end_time) {
                $query->where('rpa.integral_time', '<=', $end_time);
            });
            $batch_num_obj->where(function ($query) use ($end_time) {
                $query->where('rpa.integral_time', '<=', $end_time);
            });
        }
        if (isset($param_info['month'])) {
            $month = trim($param_info['month']);
            $start_time = Carbon::parse($month)->firstOfMonth()->toDateTimeString();
            $end_time = Carbon::parse($month)->endOfMonth()->toDateTimeString();
            $rpd_obj->where(function ($query) use ($start_time, $end_time) {
                $query->where('rpa.integral_time', '>=', $start_time);
                $query->where('rpa.integral_time', '<=', $end_time);
            });
            $batch_num_obj->where(function ($query) use ($start_time, $end_time) {
                $query->where('rpa.integral_time', '>=', $start_time);
                $query->where('rpa.integral_time', '<=', $end_time);
            });
        }

        $where = [
            'rpa.status' => 3,
            'rpa.is_integral' => 0,
            'rpa.method_id' => 34,
        ];
        $total_num = $batch_num_obj->distinct()->pluck('purchase_sn')->count();
        $batch_sn_list = $rpd_obj->where($where)
            ->where('pm.method_property', '!=', 2)//外采不返积分
            ->orderBy('rpa.integral_time', 'DESC')
            ->distinct()->skip($start_page)->take($page_size)->pluck('purchase_sn');
        $batch_list = objectToArrayZ($batch_sn_list);
        $return_info = [
            'total_num' => $total_num,
            'batch_list' => $batch_list,
        ];
        return $return_info;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * date : 2019.04.08
     * return Array
     */
    public function getBatchList_stop($param_info)
    {
        //获取采购期统计数据
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $batch_goods_info = $rpda_model->getBatchGoodsInfo($param_info);
        $return_info['data_num'] = count($batch_goods_info);
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $batch_goods_info = array_slice($batch_goods_info, $start_page, $page_size);

        $batch_goods_list = [];
        foreach ($batch_goods_info as $k => $v) {
            $batch_goods_list[$k]['title_info'] = [
                'sum_demand_name' => $v[0]['sum_demand_name'],
                'purchase_sn' => $k,
            ];
            foreach ($v as $k1 => $v1) {
                $v1['port_name'] = $this->port_id[$v1['port_id']];
                $batch_goods_list[$k]['batch_info'][] = $v1;
            }
        }
        $total_info = array_values($batch_goods_list);
        $return_info['purchase_info'] = $total_info;
        return $return_info;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * date : 2019.04.08
     * return Array
     */
    public function getBatchAuditList($param_info)
    {
        $batch_num_obj = DB::table('real_purchase_audit as rpa')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id');
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $rpd_obj = DB::table('real_purchase_audit as rpa')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id');
        if (isset($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $rpd_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('rpa.real_purchase_sn', $query_sn);
                $query->orWhere('rpa.purchase_sn', $query_sn);
                $query->orWhere('pc.channels_name', $query_sn);
                $query->orWhere('pm.method_name', $query_sn);
            });
            $batch_num_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('rpa.real_purchase_sn', $query_sn);
                $query->orWhere('rpa.purchase_sn', $query_sn);
                $query->orWhere('pc.channels_name', $query_sn);
                $query->orWhere('pm.method_name', $query_sn);
            });
        }

        if (isset($param_info['status'])) {
            $status = intval($param_info['status']);
            $rpd_obj->where('rpa.status', $status);
            $batch_num_obj->where('rpa.status', $status);
        }

        $total_num = $batch_num_obj->distinct()->pluck('purchase_sn')->count();
        $batch_sn_list = $rpd_obj->orderBy('rpa.create_time', 'DESC')
            ->distinct()->skip($start_page)->take($page_size)->pluck('purchase_sn');
        $batch_list = objectToArrayZ($batch_sn_list);
        $return_info = [
            'total_num' => $total_num,
            'batch_list' => $batch_list,
        ];
        return $return_info;
    }


    /**
     * description:获取根据实采单号获取批次类别的值
     * editor:zongxing
     * date : 2019.04.08
     * return Array
     */
    public function batchAuditInfo($param_info)
    {
        $real_purchase_sn = trim($param_info['real_purchase_sn']);
        $where = [
            'real_purchase_sn' => $real_purchase_sn,
        ];
        $field = $this->field;
        $other_field = [
            'method_sn', 'method_name', 'channels_name', 'channels_sn', 'pd.delivery_time as p_delivery_time'
        ];
        $field = array_merge($field, $other_field);
        $batch_info = DB::table($this->table)
            ->leftJoin('purchase_date as pd', 'pd.purchase_sn', '=', 'rpa.purchase_sn')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->where($where)->first($field);
        $batch_info = objectToArrayZ($batch_info);
        return $batch_info;
    }

    /**
     * description:批次设置时，更新审核表相关提货时间，为统计做准备
     * editor:zongxing
     * date : 2019.04.09
     */
    public function updateRPADeliverTime($real_purchase_sn, $delivery_time)
    {
        //查找设置批次的信息
        $where = [
            ['real_purchase_sn', $real_purchase_sn]
        ];
        $batch_info = DB::table($this->table)->where($where)->first(['id', 'real_purchase_sn']);
        $batch_info = objectToArrayZ($batch_info);

        //查找设置批次的子
        $rp_id = $batch_info['id'];
        $rp_sn_info = DB::table($this->table)->where('parent_id', $rp_id)->pluck('real_purchase_sn');
        $rp_sn_list = objectToArrayZ($rp_sn_info);
        $rp_sn_list[] = $batch_info['real_purchase_sn'];

        //更新审核表中批次的提货时间
        $update_info = [
            'delivery_time' => $delivery_time
        ];
        DB::table($this->table)->whereIn('real_purchase_sn', $rp_sn_list)->update($update_info);
    }


    /**
     * description:提交批次数据
     * editor:zongxing
     * date : 2019.04.11
     * return : boolean
     */
    public function uploadBatchAudit($batch_info, $batch_goods_info)
    {
        $upload_spec_sn = [];
        foreach ($batch_goods_info as $k => $v) {
            $upload_spec_sn[] = $v['spec_sn'];
        }
        $type = intval($batch_info['type']);
        $batch_cat = intval($batch_info['batch_cat']);
        $sdcg_list = [];
        $sdg_list = [];
        $sg_list = [];
        if ($type == 1) {
            //获取采购期统计表数据
            $demand_count_model = new DemandCountModel();
            $demand_count_goods_info = $demand_count_model->get_demand_count_data($batch_info);
            if (empty($demand_count_goods_info)) {
                return ['code' => '1101', 'msg' => '您选择的采购期有误,请重新确认'];
            }
            return ['code' => '1102', 'msg' => '当前审核数据已过期'];
        } elseif ($type == 2 && $batch_cat == 1) {
            //获取汇总单商品数据
            $sd_sn_arr[] = trim($batch_info['purchase_sn']);
            $sg_model = new SumGoodsModel();
            $sum_demand_detail = $sg_model->purchaseTaskDetail($sd_sn_arr, 0, $upload_spec_sn);
            foreach ($sum_demand_detail as $k => $v) {
                $sdg_list[$v['spec_sn']] = $v;
            }
            //获取汇总需求单商品统计表
            $sdcg_model = new SumDemandChannelGoodsModel();
            $batch_info['sd_sn_arr'] = $sd_sn_arr;
            $sdcg_info = $sdcg_model->sumDemandGoodsAllotInfo($batch_info, $upload_spec_sn);
            foreach ($sdcg_info as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $sdcg_list[$k] = $v1;
                }
            }
        } elseif ($type == 2 && $batch_cat == 3) {
            $param_info = [
                'is_purchase' => 1,
            ];
            $sg_model = new StandbyGoodsModel();
            $standby_goods_list = $sg_model->standbyGoodsList($param_info, 0, $upload_spec_sn);
            $sg_list = [];
            foreach ($standby_goods_list as $k => $v) {
                $sg_list[$v['spec_sn']] = $v;
            }
        }
        //检查实采批次表中的数据是否存在
        $rp_model = new RealPurchaseModel();
        $rp_info = $rp_model->isBatchNull($batch_info);
        if (empty($rp_info)) {
            //新增批次
            $res = $rp_model->addBatchInfo($batch_info, $batch_goods_info, $sdg_list, $sdcg_list, $sg_list);
        } else {
            //更新批次
            $res = $rp_model->updateBatchInfo($batch_info, $batch_goods_info, $rp_info, $sdg_list, $sdcg_list, $sg_list);
        }
        return $res;
    }

    /**
     * description:更新审核批次状态
     * editor:zongxing
     * date : 2019.04.12
     * return : boolean
     */
    public function updateAuditBatchInfo($audit_sn)
    {
        $where = [
            'audit_sn' => $audit_sn
        ];
        $update_info = [
            'status' => 1
        ];
        $res = DB::table($this->table)->where($where)->update($update_info);
        return $res;
    }

    /**
     * description:确认积分
     * editor:zongxing
     * date : 2019.04.24
     * return : boolean
     */
    public function submitIntegral($param_info)
    {
        $channels_method_sn = trim($param_info['channels_method_sn']);
        $purchase_sn = trim($param_info['purchase_sn']);
        $where = [
            'purchase_sn' => $purchase_sn,
            'channels_method_sn' => $channels_method_sn,
        ];
        $update_data = [
            'is_integral' => 1
        ];
        $update_res = DB::table('real_purchase_audit')->where($where)->update($update_data);
        return $update_res;
    }

    /**
     * description:获取各个渠道最后一次上传信息
     * editor:zongxing
     * date : 2019.06.21
     * return Array
     */
    public function getLastBatchInfo($param_info)
    {
        $field = [
            'pc.original_or_discount', 'pu.real_name',
            DB::raw('concat_ws("-",jms_pc.channels_name,jms_pm.method_name) as channel_method_name'),
            DB::raw('MAX(jms_rpa.create_time) as last_time'),
        ];
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
        //@@@@@@@@@@ $start_time和$end_time没有值会怎样？？
        $where[] = ['rpa.create_time', '>=', $start_time];
        $where[] = ['rpa.create_time', '<=', $end_time];
        $last_batch_info = DB::table('real_purchase_audit as rpa')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'rpa.channels_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'rpa.method_id')
            ->leftJoin('purchase_user as pu', 'pu.id', '=', 'rpa.user_id')
            ->groupBy('rpa.channels_id', 'rpa.method_id')
            ->orderBy('rpa.create_time', 'DESC')
            ->get($field)
            ->groupBy('channel_method_name');
        $last_batch_info = objectToArrayZ($last_batch_info);
        return $last_batch_info;
    }

    /**
     * description:检查合单是否已经上传采购数据
     * editor:zongxing
     * date : 2019.06.28
     * return Array
     */
    public function isSumUploadData($sum_demand_sn)
    {
        $sum_batch_info = DB::table('real_purchase_audit')->where('purchase_sn', $sum_demand_sn)->first(['id']);
        $sum_batch_info = objectToArrayZ($sum_batch_info);
        return $sum_batch_info;
    }

    /**
     * description:获取待审核批次个数
     * editor:zongxing
     * type:GET
     * date : 2019.06.29
     * return Array
     */
    public function getRpaNum()
    {
        $rpa_num = DB::table('real_purchase_audit')->where('status', 0)->count();
        return $rpa_num;
    }

    /**
     * description 查询批次单中的提货日
     * author zhangdong
     * date 2019.07.11
     * return Array
     */
    public function getBatchDeliveryInfo($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $field = ['rpa.delivery_time',];
        $queryRes = DB::table($this->table)->select($field)->where($where)
            ->groupBy('rpa.delivery_time')
            ->orderBy('rpa.delivery_time', 'DESC')
            ->paginate($pageSize)->pluck('delivery_time');
        return $queryRes;
    }

    /**
     * description 批次审核单查询-组装条件
     * author zhangdong
     * date 2019.07.11
     */
    protected function makeWhere($reqParams)
    {
        //时间处理-查询订单列表时默认只查近三个月的
        //开始时间
        $start_time = Carbon::now()->addMonth(-3)->toDateTimeString();
        if (isset($reqParams['start_time'])) {
            $start_time = trim($reqParams['start_time']);
        }
        //结束时间
        $end_time = Carbon::now()->toDateTimeString();
        if (isset($reqParams['end_time'])) {
            $end_time = trim($reqParams['end_time']);
        }
        //时间筛选
        $where = [
            ['rpa.create_time', '>=', $start_time],
            ['rpa.create_time', '<=', $end_time],
        ];
        //提货时间
        if (isset($reqParams['delivery_time'])) {
            $where[] = [
                'rpa.delivery_time', trim($reqParams['delivery_time'])
            ];
        }
        return $where;
    }

    /**
     * description 根据提货日组装批次单数据
     * author zhangdong
     * date 2019.07.11
     */
    public function makeDateByDelivery($arrDeliveryTime)
    {
        //查询提货日下所有的批次信息
        $realPurchaseInfo = $this->getRpaInfoByDelivery($arrDeliveryTime);
        $realPurchaseInfo = objectToArray($realPurchaseInfo);
        $deliveryBatchInfo = [];
        foreach ($arrDeliveryTime as $key => $value) {
            $batchInfo = [];
            $deliveryTime = trim($value);
            $searchRes = searchTwoArray($realPurchaseInfo, $deliveryTime, 'delivery_time');
            if (count($searchRes) > 0) {
                $batchInfo[] = $searchRes;
            }
            $deliveryBatchInfo[$key]['deliveryTime'] = $deliveryTime;
            $deliveryBatchInfo[$key]['batchInfo'] = $batchInfo;
        }
        return $deliveryBatchInfo;

    }

    /**
     * description 根据提货日查询批次单数据
     * author zhangdong
     * date 2019.07.11
     */
    private function getRpaInfoByDelivery($arrDeliveryTime)
    {
        $field = [
            'rpa.real_purchase_sn', 'rpa.audit_sn', 'rpa.purchase_sn', 'rpa.method_id',
            'rpa.channels_id', 'rpa.path_way', 'rpa.port_id', 'rpa.user_id',
            'rpa.delivery_time', 'rpa.arrive_time', 'rpa.batch_cat', 'rpa.user_id',
            'rpa.status'
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->whereIn('rpa.delivery_time', $arrDeliveryTime)->get();
        return $queryRes;
    }

    /**
     * description 根据相关条件获取毛利商品数据
     * author zhangdong
     * date 2019.07.19
     * params $settleDateType 结算日期类型 1，提货日 2，购买日
     * modify zongxing 2019.07.25
     */
    public function getProfitGoods($param_info, $spec_arr = [])
    {
        $settleDateType = intval($param_info['settle_date_type']);
        $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
        $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
        $channelId = intval($param_info['channels_id']);
        $whereField = 'rpa.delivery_time';
        if ($settleDateType == 2) {
            $whereField = 'rpa.buy_time';
        }
        $field = [
            'rpa.real_purchase_sn', 'rpa.margin_payment', 'rpa.delivery_time', 'rpa.buy_time', 'rpda.spec_sn',
            'rpda.goods_name', 'rpda.pay_price', 'rpda.day_buy_num', 'rpa.margin_currency',
            'g.brand_id', 'rpda.channel_discount', 'rpda.lvip_price', 'rpda.spec_price',
        ];
        $where = [
            [$whereField, '>=', $start_date],
            [$whereField, '<=', $end_date],
            ['rpa.channels_id', $channelId],
            ['rpa.status', 3],
        ];
        $rpda_obj = DB::table($this->table)->select($field)
            ->leftJoin('real_purchase_detail_audit AS rpda', 'rpa.real_purchase_sn', 'rpda.real_purchase_sn')
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', 'rpda.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', 'gs.goods_sn')
            ->where($where);
        if (!empty($spec_arr)) {
            $rpda_obj->whereNotIn('rpda.spec_sn', $spec_arr);
        }
        $rpda_info = $rpda_obj->get();
        $rpda_info = objectToArrayZ($rpda_info);
        return $rpda_info;
    }

    /**
     * description 获取是否超额信息
     * author zhangdong
     * date 2019.07.23
     */
    public function getExcess($keyNum)
    {
        return $this->excess_desc[$keyNum];
    }

    /**
     * description 批次单列表-迁移
     * author zhangdong
     * date 2019.10.08
     */
    public function getBatchOrderList($params, $start_str, $page_size)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        //1 采购期单号 2 合单单号
        $where[] = ['type', 2];
        $where[] = ['status', $this->status['YET_SUBMIT']];
        $where[] = ['batch_cat', $this->batch_cat['GENERAL']];
        $orWhere = [];
        if ($keywords) {
            $where[] = ['real_purchase_sn', 'LIKE', "%$keywords%"];
            $orWhere = [
                ['purchase_sn', 'LIKE', "%$keywords%"],
            ];
        }
        $field = [
            'real_purchase_sn', 'purchase_sn', 'path_way', 'port_id', 'delivery_time',
            'arrive_time', 'create_time', 'batch_cat'
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->where($where)->orWhere($orWhere)->orderBy("create_time", "desc")->get();
        $listData = [];
        $returnMsg = [
            'listData' => $listData,
            'total_num' => $queryRes->count(),
        ];
        if ($queryRes->count() == 0) {
            return $returnMsg;
        }
        $groupData = $arrPurchaseSn = [];
        //查询对应数据的含义描述并组装数据
        $sbModel = new SortBatchModel();
        $rpdModel = new RealPurchaseDetailModel();
        foreach ($queryRes as $key => $value) {
            $value->path_way = $this->path_way[intval($value->path_way)];
            $value->port_id = $this->port_id[intval($value->port_id)];
            $purchase_sn = trim($value->purchase_sn);
            //查询批次分货记录条数
            $realSn = trim($value->real_purchase_sn);
            $value->batchSortCount = $sbModel->countNumByBatch($purchase_sn, $realSn);
            //查询可分货数量
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
        //查询合单信息
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
     * description 获取批次列表
     * editor zongxing
     * type GET
     * date 2019.09.25
     * return Array
     */
    public function getBatchList($param_info, $expire = 1, $request = '', $task_link = '')
    {
        //组装获取批次列表查询条件
        $condition = $this->createBatchListCondition($param_info);
        //获取合单信息
        $sum_sn_info = $this->getBatchListInfo($param_info, $condition);
        //获取合单对应批次信息
        dd($sum_sn_info);
        $rpda_model = new RealPurchaseDeatilAuditModel();
        $sum_sn_info = $rpda_model->getBatchTotalDetail($sum_sn_info);
        //是否检查erp
        $is_check_erp = false;
        if (isset($param_info['is_check_erp']) && $param_info['is_check_erp'] == 1) {
            $is_check_erp = true;
        }


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
     * description 检查批次单是否是当前合单下的数据-迁移
     * author zhangdong
     * date 2019.10.08
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

    /**
     * description 查询批次单基本信息-迁移
     * author zhangdong
     * date 2019.10.08
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
     * description 查询批次分货列表
     * author zhangdong
     * date 2019.10.08
     */
    public function getBatchSortList($reqParams)
    {
        //搜索关键字
        $keywords = isset($reqParams['keywords']) ? trim($reqParams['keywords']) : '';
        $page_size = isset($reqParams['page_size']) ? intval($reqParams['page_size']) : 15;
        //type 1 采购期单号 2 合单单号
        $where[] = ['type', 2];
        $where[] = ['status', $this->status['YET_SUBMIT']];
        $where[] = ['batch_cat', $this->batch_cat['GENERAL']];
        $orWhere = [];
        if ($keywords) {
            $where[] = ['real_purchase_sn', 'LIKE', "%$keywords%"];
            $orWhere = [
                ['purchase_sn', 'LIKE', "%$keywords%"],
            ];
        }
        $queryRes = DB::table($this->table)->select('purchase_sn')
            ->where($where)->orWhere($orWhere)->groupBy('purchase_sn')
            ->paginate($page_size);
        //查询合单号信息
        $arrSumDemandSn = objectToArrayZ($queryRes->pluck('purchase_sn'));
        $sumModel = new SumModel();
        $sumInfo = $sumModel->querySumInfo($arrSumDemandSn);
        $sumInfo = objectToArray($sumInfo);
        //查询批次信息
        $auditRealInfo = $this->getAuditRealInfo($arrSumDemandSn);
        $auditRealInfo = objectToArray($auditRealInfo);
        $arrRealSn = getFieldArrayVaule($auditRealInfo, 'real_purchase_sn');
        //查询批次被分货条数
        $sbModel = new SortBatchModel();
        $sortNum = objectToArray($sbModel->countSortNum($arrSumDemandSn, $arrRealSn));
        //查询批次单可分货总量
        $rpdaModel = new RealPurchaseDeatilAuditModel();
        $canSortRow = objectToArray($rpdaModel->getRemainSortNum($arrRealSn));
        $listData = [];
        foreach ($arrSumDemandSn as $value) {
            //获取合单信息
            $sumMsg = searchTwoArray($sumInfo, $value, 'sum_demand_sn');
            //如果没有合单号则直接跳过，该列表以合单号为基本数据
            if (count($sumMsg) <= 0) {
                continue;
            }
            //获取批次信息
            $realMsg = searchTwoArray($auditRealInfo, $value, 'purchase_sn');
            //获取批次单分货条数信息
            $sumSortMsg = searchTwoArray($sortNum, $value, 'sum_demand_sn');
            foreach ($realMsg as $key => $v) {
                $realSn = $v['real_purchase_sn'];
                $realMsg[$key]['path_way'] = $this->path_way[intval($v['path_way'])];
                $realMsg[$key]['port_id'] = $this->port_id[intval($v['port_id'])];
                //获取批次被分货条数，该值大于0时说明对应批次已经生成了分货数据
                $batchSortCount = 0;
                $realSnCount = searchTwoArray($sumSortMsg, $realSn, 'real_purchase_sn');
                if (count($realSnCount) > 0) {
                    $batchSortCount = intval($realSnCount[0]['num']);
                }
                $realMsg[$key]['batchSortCount'] = $batchSortCount;
                //查询计对应批次单下SKU的可分货数量未分完的条数，前端用来控制是否分完的显示
                $canSortNum = 0;
                $sortRow = searchTwoArray($canSortRow, $realSn, 'real_purchase_sn');
                if (count($sortRow) > 0) {
                    $canSortNum = intval($sortRow[0]['num']);
                }
                $realMsg[$key]['canSortNum'] = $canSortNum;
            }
            //查询合单号是否已经生成了初始分货数据-控制前端生成合单分货数据按钮的切换
            //新流程中由于合单初始分货数据在此方法被调用前就已经生成了数据，所以此处
            //将按钮状态直接设置为查看状态
            $countSortData = 1;
            $listData[] = [
                'purchase_info' => $sumMsg[0],
                'countSortData' => $countSortData,
                'real_data' => $realMsg,
            ];
        }//end of foreach
        $returnMsg = [
            'batchList' => $listData,
            'totalRow' => $queryRes->total()
        ];
        return $returnMsg;
    }

    /**
     * description 根据合单号查询批次信息
     * author zhangdong
     * date 2019.10.08
     */
    public function getAuditRealInfo($arrSumDemandSn)
    {
        $field = [
            'real_purchase_sn', 'purchase_sn', 'path_way', 'port_id', 'delivery_time',
            'arrive_time', 'create_time', 'batch_cat'
        ];
        $queryRes = DB::table($this->table)->select($field)->whereIn('purchase_sn', $arrSumDemandSn)->get();
        return $queryRes;
    }

    /**
     * description 组装获取批次列表条件
     * editor zongxing
     * date 2019.09.25
     * return Array
     */
    public function createBatchListCondition($param_info)
    {
        $where = [];
        //批次状态
        if (isset($param_info['status'])) {
            $status = intval($param_info['status']);
            $where[] = ['rpa.status', '=', $status];
        }
        //提货日
        if (isset($param_info['delivery_time']) && !empty($param_info['delivery_time'])) {
            $delivery_time = trim($param_info['delivery_time']);
            $where[] = ['rpa.delivery_time', '=', $delivery_time];
        }
        //到货日
        if (isset($param_info['arrive_time']) && !empty($param_info['arrive_time'])) {
            $arrive_time = trim($param_info['arrive_time']);
            $where[] = ['rpa.arrive_time', '=', $arrive_time];
        }
        return $where;
    }

    /**
     * description 获取批次列表信息
     * editor zongxing
     * date 2019.09.25
     * return Array
     */
    public function getBatchListInfo($param_info, $where)
    {
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $sum_sn_obj = DB::table('real_purchase_audit as rpa')->where($where);
        //搜索字段
        if (isset($param_info['query_sn']) && !empty($param_info['query_sn'])) {
            $purchase_sn = '%' . trim($param_info['query_sn']) . '%';
            $sum_sn_obj->where('rpa.purchase_sn', 'LIKE', $purchase_sn);
        }
//        if (isset($param_info['status'])) {
//            $status = intval($param_info['status']);
//            $expireTime = $this->create_expire_time($status);
//            if ($status == 3) {
//                $add_field = [DB::raw('if(jms_rp.arrive_time > ' . $expireTime . ',0,1) expire_status')];
//            } elseif ($status == 4) {
//                //待确认差异
//                $add_field = [DB::raw('if(jms_rp.allot_time > ' . $expireTime . ',0,1) expire_status')];
//            } elseif ($status == 5) {
//                //待开单
//                $add_field = [DB::raw('if(jms_rp.diff_time > ' . $expireTime . ',0,1) expire_status')];
//            } elseif ($status == 6) {
//                //待入库
//                $add_field = [DB::raw('if(jms_rp.billing_time > ' . $expireTime . ',0,1) expire_status')];
//            }
//        }
//        if (!empty($add_field)) {
//            $fields = array_merge($fields, $add_field);
//        }


        $sum_sn_info = $sum_sn_obj->orderBy('rpa.create_time', 'desc')->skip($start_page)->take($page_size)->distinct()
            ->pluck('purchase_sn');
        $sum_sn_info = objectToArrayZ($sum_sn_info);
        return $sum_sn_info;
    }


}//end of class
