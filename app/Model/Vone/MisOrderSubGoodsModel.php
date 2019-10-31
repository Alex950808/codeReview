<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入时间处理包 add by zhangdong on the 2018.06.29
use Carbon\Carbon;


class MisOrderSubGoodsModel extends Model
{

    public $table = 'mis_order_sub_goods as mosg';

    private $field = [
        'mosg.id', 'mosg.sub_order_sn', 'mosg.goods_name', 'mosg.spec_sn',
        'mosg.erp_merchant_no', 'mosg.spec_price', 'mosg.goods_number','mosg.standby_num',
        'mosg.dd_num', 'mosg.stock_num', 'mosg.cash_num', 'mosg.wait_buy_num',
        'mosg.wait_lock_num', 'mosg.sale_discount', 'mosg.bd_sale_discount',
        'mosg.dd_sale_discount', 'mosg.exw_discount', 'mosg.create_time',
    ];

    /**
     * @description:子订单列表-获取订单商品信息
     * @editor:zhangdong
     * @param $sub_order_sn 子订单号
     * @param $goodsWhere 查询条件
     * date:2018.12.11
     */
    public function getSubOrderGoods($sub_order_sn = '', $goodsWhere = [])
    {
        $field = [
            'mosg.sub_order_sn', 'mosg.goods_name', 'mosg.spec_sn', 'mosg.erp_merchant_no',
            'mosg.goods_number', 'mosg.spec_price', 'mosg.sale_discount', 'mosg.wait_lock_num'
        ];
        $where = [];
        //对查询条件进行处理-如果发现有本函数适用的条件则加入筛选
        foreach ($goodsWhere as $value) {
            $field_name = $value[0];
            $is_exit = strpos($field_name, 'mosg');
            /*此处strpos函数如果查到对应的字符则返回出现的位置，因为mosg出现的位置为0
            而0不等于true，所以用false来判断*/
            if ($is_exit !== false) $where[] = $value;
        }
        $where[] = ['mosg.sub_order_sn', $sub_order_sn];
        $queryRes = DB::table('mis_order_sub_goods as mosg')->select($field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:获取子单详情
     * editor:zhangdong
     * date : 2018.12.12
     * @param $sub_order_sn (子单号)
     * @return
     */
    public function getSubDetail($sub_order_sn)
    {
        $sub_order_sn = trim($sub_order_sn);
        $where = [
            ['mosg.sub_order_sn', $sub_order_sn]
        ];
        $gs_on = [
            ['gs.spec_sn', '=', 'mosg.spec_sn'],
        ];
        //加入商品规格表中的库存字段
        $otherField = [
            'gs.stock_num as gStockNum',
            DB::raw('(CAST(jms_mosg.goods_number as signed) - CAST(jms_gs.stock_num as signed)) as buy_num'),
            'gs.erp_ref_no', 'gs.erp_prd_no', 'gs.is_suit', 'gs.suit_sn', 'gs.suit_price', 'gs.is_search',
            'mosg.platform_barcode',
        ];
        $this->field = array_merge($this->field, $otherField);
        $queryRes = DB::table($this->table)->select($this->field)
            ->leftJoin('goods_spec as gs', $gs_on)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:对子单按照当前库存进行分单
     * editor:zhangdong
     * date : 2018.12.12
     * @param $orderGoodsInfo 订单商品信息
     * @return array
     */
    public function submenuGoods_stop($orderGoodsInfo)
    {
        $goodsModel = new GoodsModel();
        $spotGoodsModel = new SpotGoodsModel();
        $demandGoodsModel = new DemandGoodsModel();
        //将$orderGoodsInfo中的规格码组装成数组形式
        $arrData = objectToArray($orderGoodsInfo);
        $arrSpecSn = makeArray($arrData, 'spec_sn');
        //查询商品数据
        $goodsInfo = $goodsModel->getGoodsMsgBySpecSn($arrSpecSn);
        $spotGoodsData = $demandGoodsData = [];
        foreach ($orderGoodsInfo as $value) {
            //根据规格码查询当前商品库存
            $spec_sn = trim($value->spec_sn);
            $sale_discount = trim($value->sale_discount);
            //查询商品信息
            $searchRes = searchTwoArray($goodsInfo, $spec_sn, 'spec_sn');
            $goodsSpecMsg = $searchRes[0];
            if (empty($goodsSpecMsg)) continue;
            //将当前用户对应的销售折扣加入商品信息中
            $goodsSpecMsg['sale_discount'] = $sale_discount;
            //商品库存
            $stock = intval($goodsSpecMsg['stock_num']);
            //子单需求量-预判采购量
            $goodsNum = intval($value->wait_buy_num);
            //只要有库存就去生成现货单
            if ($stock > 0) {//组装现货单订单数据
                //现货购买数量=待锁库数量（可人工手动填写）
                $waitLockNum = intval($value->wait_lock_num);
                //如果锁库数为0则不需要生成现货数据
                if ($waitLockNum <= 0) {
                    continue;
                }
                $spotGoodsData[] = $spotGoodsModel->createSpotGoodsData($waitLockNum, $goodsSpecMsg);
            }
            //如果预判采购量大于0则生成采购需求单
            if ($goodsNum > 0) {//组装采购单数据(需求单)
                $demandGoodsData[] = $demandGoodsModel->createDemandGoodsData($goodsNum, $goodsSpecMsg);
            }
        }//end of foreach

        return [
            'spotGoodsData' => $spotGoodsData,
            'demGoodsData' => $demandGoodsData,
        ];

    }//end of function


    /**
     * description:DD单上传后修改子单数量和销售折扣
     * editor:zhangdong
     * date : 2018.12.18
     * @return array
     */
    public function modSubGoodsData(
        $sub_order_sn,
        $spec_sn,
        $sale_discount,
        $goods_num,
        $waitNum
    )
    {
        $where = [
            ['sub_order_sn', $sub_order_sn],
            ['spec_sn', $spec_sn],
        ];
        $update = [
            'dd_sale_discount' => $sale_discount,
            'goods_number' => $goods_num,
            'wait_buy_num' => $waitNum,
        ];
        $updateRes = DB::table('mis_order_sub_goods')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:修改子单待采数量
     * editor:zhangdong
     * date : 2018.12.26
     * @return boolean
     */
    public function updateWaitNum($subOrderSn, $spec_sn, $waitNum)
    {
        $where = [
            ['sub_order_sn', $subOrderSn],
            ['spec_sn', $spec_sn],
        ];
        $update = [
            'wait_buy_num' => $waitNum
        ];

        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }


    /**
     * description:获取子单对应的商品需求数量
     * editor:zhangdong
     * date : 2018.12.26
     * @param $sub_order_sn (子单号)
     * @return
     */
    public function getGoodsNum($sub_order_sn, $spec_sn)
    {
        $sub_order_sn = trim($sub_order_sn);
        $spec_sn = trim($spec_sn);
        $where = [
            ['sub_order_sn', $sub_order_sn],
            ['spec_sn', $spec_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:修改子单商品各类折扣
     * editor:zhangdong
     * date : 2018.12.29
     * @return boolean
     */
    public function updateSaleDiscount($sub_order_sn)
    {
        $where = [
            ['sub_order_sn', $sub_order_sn],
        ];
        $update = [
            'bd_sale_discount' => DB::raw('sale_discount'),
        ];

        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:获取子单信息
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function getMisSubOrderGoods($mis_order_sn)
    {
        $field = [
            'order_id', 'mos.sub_order_sn', 'mos.mis_order_sn', 'status', 'is_submenu', 'sale_user_account',
            DB::raw('SUM(jms_mosg.goods_number) as mis_order_sub_total_num'),
            DB::raw('SUM(jms_mosg.wait_buy_num) as mis_order_sub_wait_buy_num'),
            DB::raw('COUNT(jms_mosg.spec_sn) as sku_num'),
            DB::raw('DATE(jms_mos.entrust_time) as entrust_time')
        ];

        $mis_order_sub_list = DB::table('mis_order_sub_goods as mosg')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->whereIn('mis_order_sn', $mis_order_sn)
            ->groupBy('mosg.sub_order_sn')
            ->get($field)
            ->groupBy('sub_order_sn');
        $mis_order_sub_list = objectToArrayZ($mis_order_sub_list);
        return $mis_order_sub_list;
    }

    /**
     * description:获取子单列表分页信息
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function getMisSubOrderListByPage($param_info)
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
        if (!empty($param_info['sale_user_id'])) {
            $sale_user_id = intval($param_info['sale_user_id']);
            $where[] = ['mo.sale_user_id', '=', $sale_user_id];
        }
        $mos_status = ['mos.status', '=', 3];
        if (!empty($param_info['order_type'])) {
            $order_type = intval($param_info['order_type']);
            $mos_status = ['mos.status', '=', $order_type];
        }
        $where[] = $mos_status;

        $field = [
            'mos.order_id', 'mos.sub_order_sn', 'mos.sale_user_account', 'su.user_name', 'mos.remark', 'mos.external_sn',
            //'mosg.wait_buy_num', 'mosg.goods_number', 'mosg.spec_sn', 'mosg.sale_discount',
            DB::raw('DATE(jms_mos.entrust_time) as entrust_time'),
            DB::raw('SUM(jms_mosg.wait_buy_num) as mis_order_sub_wait_buy_num'),
            DB::raw('SUM(jms_mosg.dd_num) as mis_order_sub_total_num'),
            DB::raw('COUNT(jms_mosg.spec_sn) as sku_num'),
            DB::raw('SUM(jms_mosg.dd_sale_discount) as sub_total_sale_discount'),
            //重价比折扣=单重/美金原价/0.0022/100
            //erp逻辑毛利 = （1-（EXW折扣+重价折扣）/报价折扣） * 100%
            DB::raw('SUM(jms_mosg.dd_num * jms_mosg.spec_price * 
                (1 - (jms_mosg.exw_discount + 
                    ((
                    CASE
                    WHEN jms_gs.spec_weight != 0 THEN
                        jms_gs.spec_weight
                    WHEN jms_gs.estimate_weight != 0 THEN
                        jms_gs.estimate_weight
                    ELSE
                        0.00
                    END) / (jms_mosg.spec_price * 0.0022 * 100))
                )/jms_mosg.dd_sale_discount)
            )
            as sqrt_price'),//报价毛利金额
            DB::raw('SUM(jms_mosg.dd_num * jms_mosg.spec_price * jms_mosg.dd_sale_discount) as sqt_price'),//销售金额
            DB::raw('SUM(jms_mosg.dd_num * jms_mosg.spec_price * jms_mosg.dd_sale_discount) as sdt_price'),//销售缺口金额
        ];
        $mis_order_sub_obj = DB::table('mis_order_sub_goods as mosg')
            ->select($field)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->leftJoin('demand as d', 'd.sub_order_sn', '=', 'mos.sub_order_sn')
            ->leftJoin('sum_demand as sd', 'sd.demand_sn', '=', 'd.demand_sn')
            ->leftJoin('mis_order as mo', 'mo.mis_order_sn', '=', 'mos.mis_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'mosg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->where($where);
        if (!empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $mis_order_sub_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('mos.sub_order_sn', '=', $query_sn)
                    ->orWhere('mos.external_sn', '=', $query_sn);
            });
        }
        if (!empty($param_info['sum_demand_sn'])) {
            $sum_demand_sn = trim($param_info['sum_demand_sn']);
            $mis_order_sub_obj->where('sd.sum_demand_sn', '=', $sum_demand_sn);
        }
        $is_page = $param_info['is_page'];
        if ($is_page) {
            $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
            $mis_order_sub_list = $mis_order_sub_obj->groupBy('mosg.sub_order_sn')->orderBy('mos.entrust_time', 'DESC')->paginate($page_size);
        } else {
            $mis_order_sub_list['data'] = $mis_order_sub_obj->groupBy('mosg.sub_order_sn')->orderBy('mos.entrust_time', 'DESC')->get();
        }
        $mis_order_sub_list = objectToArrayZ($mis_order_sub_list);
        return $mis_order_sub_list;
    }

    public function getMisSubOrderListByPage_stop($param_info)
    {
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $where = [];

        if (!empty($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $end_time = trim($param_info['end_time']);
            $where[] = ['mos.entrust_time', '>=', $start_time];
            $where[] = ['mos.entrust_time', '<=', $end_time];
        }
        if (!empty($param_info['sale_user_id'])) {
            $sale_user_id = intval($param_info['sale_user_id']);
            $where[] = ['mo.sale_user_id', '=', $sale_user_id];
        }
        $mos_status = ['mos.status', '=', 3];
        if (!empty($param_info['order_type'])) {
            $order_type = intval($param_info['order_type']);
            $mos_status = ['mos.status', '=', $order_type];
        }
        $where[] = $mos_status;

        $field = [
            'mos.order_id', 'mos.sub_order_sn', 'mos.sale_user_account', 'su.user_name', 'mos.remark',
            'mosg.wait_buy_num as mosg_wait_num', 'mosg.goods_number as mosg_num', 'mosg.spec_price', 'mosg.spec_sn',
            'mosg.dd_sale_discount as sub_dd_sale_discount',
            DB::raw('DATE(jms_mos.entrust_time) as entrust_time'),
            //DB::raw('SUM(jms_mosg.wait_buy_num) as mis_order_sub_wait_buy_num'),
            //DB::raw('jms_mosg.goods_number as mis_order_sub_total_num'),
            //DB::raw('COUNT(jms_mosg.spec_sn) as sku_num'),
//            DB::raw('SUM(jms_mosg.dd_sale_discount) as sub_total_sale_discount'),
//            //重价比折扣=单重/美金原价/0.0022/100
//            //erp逻辑毛利 = （1-（EXW折扣+重价折扣）/报价折扣） * 100%
//            DB::raw('SUM(jms_mosg.goods_number * jms_mosg.spec_price * jms_mosg.dd_sale_discount *
//                (1 - (jms_mosg.exw_discount +
//                    (jms_gs.spec_weight/(jms_mosg.spec_price * 0.0022 * 100))
//                )/jms_mosg.dd_sale_discount)
//            )
//            as sqrt_price'),//报价毛利金额
            DB::raw('jms_mosg.goods_number * jms_mosg.spec_price * jms_mosg.dd_sale_discount as sqt_price'),//销售金额
            DB::raw('jms_mosg.goods_number * jms_mosg.spec_price * jms_mosg.dd_sale_discount as sdt_price'),//销售缺口金额
        ];
        $mis_order_sub_obj = DB::table('mis_order_sub_goods as mosg')
            ->select($field)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->leftJoin('mis_order as mo', 'mo.mis_order_sn', '=', 'mos.mis_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'mosg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            //->groupBy('mosg.sub_order_sn')
            ->where($where);
        if (!empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $mis_order_sub_obj->where(function ($query) use ($query_sn) {
                $query->orWhere('mos.sub_order_sn', '=', $query_sn)
                    ->orWhere('mos.remark', '=', $query_sn);
            });
        }
        $mis_order_sub_list = $mis_order_sub_obj->orderBy('mos.entrust_time', 'ASC')->get();
        $mis_order_sub_list = objectToArrayZ($mis_order_sub_list);
        dd($mis_order_sub_list);
        return $mis_order_sub_list;
    }

    /**
     * description:获取子单列表分页信息
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function getMisSubOrderListDetail($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $where = [];
        if (!empty($param_info['sub_order_sn'])) {
            $sub_order_sn = trim($param_info['sub_order_sn']);
            $where[] = ['mos.sub_order_sn', '=', $sub_order_sn];
        }
        if (!empty($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $end_time = trim($param_info['end_time']);
            $where[] = ['mos.entrust_time', '>=', $start_time];
            $where[] = ['mos.entrust_time', '<=', $end_time];
        }

        $field = [
            'mos.order_id', 'mos.sub_order_sn', 'mos.sale_user_account', 'su.user_name',
            //'mosg.wait_buy_num', 'mosg.goods_number', 'mosg.spec_sn', 'mosg.sale_discount',
            DB::raw('DATE(jms_mos.entrust_time) as entrust_time'),
            DB::raw('SUM(jms_mosg.wait_buy_num) as mis_order_sub_wait_buy_num'),
            DB::raw('SUM(jms_mosg.goods_number) as mis_order_sub_total_num'),
            DB::raw('COUNT(jms_mosg.spec_sn) as sku_num'),
            DB::raw('SUM(jms_mosg.dd_sale_discount) as sub_total_sale_discount')
        ];
        $mis_order_sub_list = DB::table('mis_order_sub_goods as mosg')
            ->select($field)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->leftJoin('mis_order as mo', 'mo.mis_order_sn', '=', 'mos.mis_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->groupBy('mosg.sub_order_sn')
            ->where('mos.status', 3)
            ->where('mos.is_submenu', 1)
            ->where($where)
            ->orderBy('mos.entrust_time', 'DESC')->paginate($page_size);
        $mis_order_sub_list = objectToArrayZ($mis_order_sub_list);
        dd($mis_order_sub_list);
        return $mis_order_sub_list;
    }

    /**
     * description:修改子单锁库数量
     * editor:zhangdong
     * date : 2019.01.11
     * @return boolean
     */
    public function updateWaitLockNum($sub_order_sn, $spec_sn, $waitLockNum)
    {
        $where = [
            ['sub_order_sn', $sub_order_sn],
            ['spec_sn', $spec_sn],
        ];
        $update = [
            'wait_lock_num' => intval($waitLockNum),
        ];

        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:根据子单号获取子单下的对应商品-DD单导入专用，请勿修改
     * editor:zhangdong
     * date : 2019.01.24
     * @param $sub_order_sn (子单号)
     * @return
     */
    public function getSubGoods($sub_order_sn)
    {
        $field = [
            'gs.stock_num', 'gs.spec_sn', 'gs.erp_merchant_no', 'gs.lock_stock_num',
            'mosg.goods_number', 'mosg.dd_sale_discount',
        ];
        $where = [
            ['mosg.sub_order_sn', $sub_order_sn]
        ];
        $gs_on = [
            ['mosg.spec_sn', '=', 'gs.spec_sn']
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('goods_spec as gs', $gs_on)
            ->where($where)->get();
        return $queryRes;

    }

    /**
     * description:根据子单号获取子单下的对应商品数量
     * editor:zhangdong
     * date : 2019.01.29
     * @param $sub_order_sn (子单号)
     * @return int
     */
    public function getSubGoodsNum($sub_order_sn)
    {
        $where = [
            ['mosg.sub_order_sn', $sub_order_sn]
        ];
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * description:获取子单商品信息
     * editor:zongxing
     * date : 2019.02.28
     */
    public function getSubGoodsInfo($sub_order_sn = '')
    {
        $field = [
            'mosg.sub_order_sn', 'mosg.goods_name', 'mosg.spec_sn', 'mosg.erp_merchant_no', 'mosg.spec_price',
            'mosg.goods_number', 'mosg.wait_buy_num', 'mosg.dd_sale_discount', 'goods_label',
            DB::raw('ROUND(jms_gs.spec_weight, 3) as spec_weight'),//商品重量
            DB::raw('ROUND(jms_gs.exw_discount, 3) as purchase_exw_discount'),//采购exw折扣
            DB::raw('ROUND(jms_mosg.exw_discount, 3) as cost_exw_discount'),//报价exw折扣
            DB::raw('ROUND(jms_gs.spec_weight/(jms_mosg.spec_price * 0.0022 * 100), 3) as weight_rate'),//商品重价率
            //重价比折扣=单重/美金原价/0.0022/100
            //erp逻辑毛利 = （1-（EXW折扣+重价折扣）/报价折扣） * 100%
            DB::raw('jms_mosg.dd_num * jms_mosg.spec_price *
                (1 - (jms_mosg.exw_discount + 
                    ((
                    CASE
                    WHEN jms_gs.spec_weight != 0 THEN
                        jms_gs.spec_weight
                    WHEN jms_gs.estimate_weight != 0 THEN
                        jms_gs.estimate_weight
                    ELSE
                        0.00
                    END) /(jms_mosg.spec_price * 0.0022 * 100))
                )/jms_mosg.dd_sale_discount) as sqrt_price'),//报价毛利金额
            DB::raw('jms_mosg.dd_num * jms_mosg.spec_price * jms_mosg.dd_sale_discount as sqt_price'),//销售金额
            DB::raw(
                '(1 - (jms_mosg.exw_discount +
                    ((
                    CASE
                    WHEN jms_gs.spec_weight != 0 THEN
                        jms_gs.spec_weight
                    WHEN jms_gs.estimate_weight != 0 THEN
                        jms_gs.estimate_weight
                    ELSE
                        0.00
                    END)/(jms_gs.spec_price * 0.0022 * 100))
                )/jms_mosg.dd_sale_discount) * 100 as sub_quote_rate'),//报价逻辑毛利率
        ];

        if (!empty($sub_order_sn)) {
            $where[] = ['mosg.sub_order_sn', '=', $sub_order_sn];
        }
        $sub_order_goods_info = DB::table('mis_order_sub_goods as mosg')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'mosg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->where($where)
            ->get($field)->groupBy('spec_sn');
        $sub_order_goods_info = objectToArrayZ($sub_order_goods_info);
        return $sub_order_goods_info;
    }

    /**
     * description:获取子单商品统计信息
     * editor:zongxing
     * date : 2019.02.28
     */
//    public function getSubGoodsStatisticsInfo($param_info)
//    {
//        $field = [
//            'mosg.sub_order_sn', 'mosg.goods_name', 'mosg.spec_sn', 'mosg.erp_merchant_no', 'gs.spec_price',
//            'mosg.dd_sale_discount', 'mosg.goods_number', 'mosg.wait_buy_num as sub_wait_buy_num',
//            'mos.sale_user_account', 'mos.entrust_time', 'gs.goods_label'
//        ];
//
//        $where[] = ['mos.status', '=', 3];
////        if (!empty($param_info['sub_order_sn'])) {
////            $sub_order_sn = trim($param_info['sub_order_sn']);
////            $where[] = ['mos.sub_order_sn', '=', $sub_order_sn];
////        }
//        if (!empty($param_info['start_time'])) {
//            $start_time = trim($param_info['start_time']);
//            $where[] = ['mos.entrust_time', '>=', $start_time];
//        }
//        if (!empty($param_info['end_time'])) {
//            $end_time = trim($param_info['end_time']);
//            $where[] = ['mos.entrust_time', '<=', $end_time];
//        }
//        $sub_order_goods_info = DB::table('mis_order_sub_goods as mosg')
//            ->select($field)
//            ->leftJoin('mis_order_sub as mos','mos.sub_order_sn','=','mosg.sub_order_sn')
//            ->leftJoin('goods_spec as gs','gs.spec_sn','=','mosg.spec_sn')
//            ->where($where)
//            ->get();
//        $sub_order_goods_info = objectToArrayZ($sub_order_goods_info);
//        $sub_order_sn_arr = [];
//        $sub_goods_info = [];
//        $sub_order_goods_list = $sub_order_goods_info['data'];
//        foreach ($sub_order_goods_list as $k => $v) {
//            $tmp_sub_order_sn = $v['sub_order_sn'];
//            $spec_sn = $v['spec_sn'];
//            if (!in_array($tmp_sub_order_sn,$sub_order_sn_arr)){
//                $sub_order_sn_arr[] = $tmp_sub_order_sn;
//            }
//            if (isset($sub_goods_info[$spec_sn])){
//                $sub_goods_info[$spec_sn]['goods_number'] += intval($v['goods_number']);
//                $sub_goods_info[$spec_sn]['sub_wait_buy_num'] += intval($v['sub_wait_buy_num']);
//            }else{
//                $sub_goods_info[$spec_sn] = $v;
//            }
//        }
//        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
//        $return_info['sub_goods_list'] = $sub_order_goods_info;
//        $return_info['sub_order_sn_arr'] = $sub_order_sn_arr;
//        $return_info['sub_goods_info'] = $sub_goods_info;
//        return $return_info;
//    }
    public function getSubGoodsStatisticsInfo($param_info)
    {
        $field = [
            'mosg.sub_order_sn', 'mosg.goods_name', 'mosg.spec_sn', 'mosg.erp_merchant_no', 'gs.spec_price',
            'mosg.dd_sale_discount', 'mosg.goods_number', 'mosg.wait_buy_num as sub_wait_buy_num',
            'mos.sale_user_account', 'mos.entrust_time', 'gs.goods_label'
        ];

        $where[] = ['mos.status', '=', 3];
        $start_time = Carbon::now()->firstOfMonth()->toDateTimeString();
        $end_time = Carbon::parse('+1 day')->toDateString();
        if (!empty($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
        }
        if (!empty($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
        }
        $where = [
            ['mos.status', '=', 3],
            ['mos.entrust_time', '>=', $start_time],
            ['mos.entrust_time', '<=', $end_time]
        ];

        $sub_order_goods_obj = DB::table('mis_order_sub_goods as mosg')
            ->select($field)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'mosg.spec_sn')
            ->where($where)
            ->orderBy('mos.entrust_time', 'DESC');
        if (!empty($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_like_sn = '%' . $query_sn . '%';
            $sub_order_goods_obj->where(function ($query) use ($query_sn, $query_like_sn) {
                $query->where('mos.sub_order_sn', '=', $query_sn)
                    ->orWhere('mosg.goods_name', 'LIKE', $query_like_sn)
                    ->orWhere('mosg.spec_sn', 'LIKE', $query_like_sn)
                    ->orWhere('mosg.erp_merchant_no', 'LIKE', $query_like_sn);
            });
        }
        $sub_order_goods_info = $sub_order_goods_obj->get();
        $sub_order_goods_list = objectToArrayZ($sub_order_goods_info);
        $sub_order_sn_arr = [];
        $sub_goods_info = [];
        foreach ($sub_order_goods_list as $k => $v) {
            $tmp_sub_order_sn = $v['sub_order_sn'];
            $spec_sn = $v['spec_sn'];
            if (!in_array($tmp_sub_order_sn, $sub_order_sn_arr)) {
                $sub_order_sn_arr[] = $tmp_sub_order_sn;
            }
            if (isset($sub_goods_info[$spec_sn])) {
                $sub_goods_info[$spec_sn]['goods_number'] += intval($v['goods_number']);
                $sub_goods_info[$spec_sn]['sub_wait_buy_num'] += intval($v['sub_wait_buy_num']);
            } else {
                $sub_goods_info[$spec_sn] = $v;
            }
        }
        $page = isset($param_info['page']) ? intval($param_info['page']) : 1;
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $start_page = ($page - 1) * $page_size;
        $total_goods_num = COUNT($sub_goods_info);
        $sub_goods_list = array_slice($sub_goods_info, $start_page, $page_size);
        $return_info['total_goods_num'] = $total_goods_num;
        $return_info['sub_order_sn_arr'] = $sub_order_sn_arr;
        $return_info['sub_goods_info'] = array_values($sub_goods_list);
        return $return_info;
    }

    /**
     * description:修改DD子单数据
     * author:zhangdong
     * params:$type 修改类型 1,修改现货数量 2，修改dd数量 3，修改销售折扣
     * date : 2019.05.21
     */
    public function modifySubData($subOrderSn, $spec_sn, $type = 0, $value = '')
    {
        $where = [
            'sub_order_sn' => $subOrderSn,
            'spec_sn' => $spec_sn,
        ];
        if ($type == 0 || $value == '') {
            return false;
        }
        $update = [];
        if ($type == 1) {
            $update = ['cash_num' => intval($value)];
        }
        if ($type == 2) {
            $update = ['dd_num' => intval($value)];
        }
        if ($type == 3) {
            $update = ['dd_sale_discount' => floatval($value)];
        }
        if ($type == 4) {
            $update = ['wait_buy_num' => intval($value)];
        }
        $modifyRes = DB::table($this->table)->where($where)->update($update);
        return $modifyRes;

    }


    /**
     * description:子单分单
     * editor:zhangdong
     * date : 2019.05.22
     */
    public function submenuGoods($orderGoodsInfo)
    {
        $spotGoodsModel = new SpotGoodsModel();
        $demandGoodsModel = new DemandGoodsModel();
        $spotGoodsData = $demandGoodsData = [];
        foreach ($orderGoodsInfo as $value) {
            //根据规格码查询当前商品库存
            $sale_discount = trim($value->dd_sale_discount);
            //将当前用户对应的销售折扣加入商品信息中
            $goodsSpecMsg = (array)$value;
            $goodsSpecMsg['sale_discount'] = $sale_discount;
            //现货购买数量=待锁库数量（可人工手动填写）
            $waitLockNum = intval($value->wait_lock_num);
            //如果锁库数大于0则生成现货数据
            if ($waitLockNum > 0) {
                $spotGoodsData[] = $spotGoodsModel->createSpotGoodsData($waitLockNum, $goodsSpecMsg);
            }
            //如果预判采购量大于0则生成采购需求单
            //子单需求量 = 预判采购量
            $goodsNum = intval($value->wait_buy_num);
            if ($goodsNum > 0) {//组装采购单数据(需求单)
                $demandGoodsData[] = $demandGoodsModel->createDemandGoodsData($goodsNum, $goodsSpecMsg);
            }
        }//end of foreach

        return [
            'spotGoodsData' => $spotGoodsData,
            'demGoodsData' => $demandGoodsData,
        ];

    }//end of function


    /**
     * description 获取DD子单信息
     * author zongxing
     * date 2019.06.20
     */
    public function getDdMosList($param_info)
    {
        if (isset($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $end_time = Carbon::parse($start_time)->startOfDay()->toDateTimeString();
        }
        if (isset($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
            $end_time = Carbon::parse($end_time)->endOfDay()->toDateTimeString();
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
        if (!empty($start_time)) {
            $where[] = ['mos.entrust_time', '>=', $start_time];
        }
        if (!empty($end_time)) {
            $where[] = ['mos.entrust_time', '<=', $end_time];
        }
        if (!empty($param_info['sale_user_id'])) {
            $sale_user_id = intval($param_info['sale_user_id']);
            $where[] = ['su.id', '=', $sale_user_id];
        }
        $field = [
            'mos.sub_order_sn', 'mos.external_sn', 'mos.sale_user_account', 'mos.entrust_time', 'mos.remark',
            'mo.sale_user_id', 'su.user_name',
            DB::raw('sum(jms_mosg.goods_number) as goods_number')
        ];
        $mos_list = DB::table($this->table)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->leftJoin('mis_order as mo', 'mo.mis_order_sn', '=', 'mos.mis_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->where('mos.status', 3)->where($where)
            ->groupBy('mosg.sub_order_sn')
            ->get($field)->groupBy('sub_order_sn');
        $mos_list = objectToArrayZ($mos_list);
        if (empty($mos_list)) {
            return $mos_list;
        }
        $return_info['mos_list'] = $mos_list;
        $field = [
            DB::raw('count(distinct jms_mosg.spec_sn) as sku_num')
        ];
        $sku_num = DB::table($this->table)
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->leftJoin('mis_order as mo', 'mo.mis_order_sn', '=', 'mos.mis_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->where('mos.status', 3)
            ->where($where)
            ->first($field);
        $return_info['sku_num'] = objectToArrayZ($sku_num)['sku_num'];
        return $return_info;
    }

    /**
     * description 检查表格中的商品数据是否和已经导入的子单数据一模一样，如果一样则提示警告信息，
     * 避免某几个商品不断重复提交DD子单
     * author zhangdong
     * date 2019.06.24
     */
    public function checkRepeatGoodsData($arrSpecSn, $misOrderSn)
    {
        //根据$arrSpecSn查询子单数据
        $subData = $this->querySubBySpecSn($arrSpecSn, $misOrderSn);
        //将子单号的查询结果转为一维数组(有去重效果)
        $arrSubSn = getFieldArrayVaule(objectToArray($subData), 'sub_order_sn');
        if (count($arrSubSn) > 0) {
            return implode($arrSubSn, ',');
        }
        return true;
    }

    /**
     * description 根据$arrSpecSn查询子单数据
     * author zhangdong
     * date 2019.06.24
     */
    public function querySubBySpecSn($arrSpecSn, $misOrderSn)
    {
        $field = ['mosg.sub_order_sn'];
        $where = [
            ['mos.mis_order_sn', $misOrderSn],
        ];
        $misOrderSub = (new MisOrderSubModel())->getTable();
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin($misOrderSub, 'mos.sub_order_sn', 'mosg.sub_order_sn')
            ->where($where)->whereIn('mosg.spec_sn', $arrSpecSn)->get();
        return $queryRes;
    }

    /**
     * description 统计报价折扣异常的SKU数量
     * author zhangdong
     * date 2019.09.09
     */
    public function checkDiscountNum($sub_order_sn)
    {
        $where = [
            ['sub_order_sn', $sub_order_sn]
        ];
        $countRes = DB::table($this->table)->where($where)->where(function ($query){
            $query->orWhere('dd_sale_discount', '<=', 0);
            $query->orWhere('dd_sale_discount', '>', 1);
        })->count();
        return $countRes;
    }
















}//end of class
