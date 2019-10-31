<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入时间处理包 add by zhangdong on the 2018.12.12
use Carbon\Carbon;

//add by zhangdong 2018.12.12
class SpotGoodsModel extends Model
{

    private $field = [
        'sg.spot_order_sn', 'sg.goods_name', 'sg.spec_sn', 'sg.stock_num', 'sg.goods_number',
        'sg.sale_discount', 'sg.spec_price'
    ];
    protected $table = 'spot_goods as sg';

    /**
     * description:组装现货单订单数据
     * editor:zhangdong
     * date : 2018.12.12
     * param $goodsInfo 商品信息
     * param $goodsNum 需求量
     * @return array
     */
    public function createSpotGoodsData($waitLockNum, $goodsInfo)
    {
        if (empty($goodsInfo)) return [];
        $goodsData = [
            'goods_name' => trim($goodsInfo['goods_name']),
            'spec_sn' => trim($goodsInfo['spec_sn']),
            'stock_num' => intval($goodsInfo['stock_num']),
            'goods_number' => $waitLockNum,
            'spec_price' => floatval($goodsInfo['spec_price']),
            'sale_discount' => floatval($goodsInfo['sale_discount']),
        ];
        return $goodsData;

    }

    /**
     * description:获取YD订单下现货单中的商品
     * editor:zongxing
     * date : 2018.12.14
     * @return array
     */
    public function getSpotGoods($sub_order_sn)
    {
        $fields = ['so.spot_order_sn', 'sg.goods_name', 'gs.spec_sn', 'goods_number', 'sg.sale_discount', 'sg.spec_price',
            'spec_weight'];
        $where = [
            ["sub_order_sn", $sub_order_sn]
        ];
        $spot_goods_list = DB::table("spot_goods as sg")
            ->leftJoin("spot_order as so", "so.spot_order_sn", "=", "sg.spot_order_sn")
            ->leftJoin("goods_spec as gs", "gs.spec_sn", "=", "sg.spec_sn")
            ->where($where)->get($fields);
        $spot_goods_list = ObjectToArrayZ($spot_goods_list);
        return $spot_goods_list;
    }

    /**
     * description:根据订单号查询该订单的商品信息
     * editor:zhangdong
     * date : 2018.12.14
     * @return object
     */
    public function getSpotGoodsInfo($spot_order_sn)
    {
        $field = [
            'sg.goods_name', 'sg.spec_sn', 'sg.goods_number', 'sg.spec_price', 'gs.goods_sn',
            'gs.erp_merchant_no', 'gs.spec_id',
        ];
        $where = [
            ['sg.spot_order_sn', $spot_order_sn]
        ];
        $queryRes = DB::table('spot_goods AS sg')->select($field)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn')
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * @description:子订单列表-获取订单商品信息
     * @editor:zhangdong
     * @param $spot_order_sn 订单号
     * @param $goodsWhere 查询条件
     * date:2018.12.15
     */
    public function getSubOrderGoods($spot_order_sn = '', $goodsWhere = [])
    {
        $field = [
            'sg.spot_order_sn', 'sg.goods_name', 'sg.spec_sn', 'gs.stock_num as gsStockNum',
            'sg.stock_num', 'sg.goods_number', 'sg.spec_price', 'gs.lock_stock_num',
        ];
        $where = [];
        //对查询条件进行处理-如果发现有本函数适用的条件则加入筛选
        foreach ($goodsWhere as $value) {
            $field_name = $value[0];
            $is_exit = strpos($field_name, 'sg');
            /*此处strpos函数如果查到对应的字符则返回出现的位置，因为mosg出现的位置为0
            而0不等于true，所以用false来判断*/
            if ($is_exit !== false) $where[] = $value;
        }
        $where[] = ['sg.spot_order_sn', $spot_order_sn];
        $gs_on = [
            ['gs.spec_sn', '=', 'sg.spec_sn'],
        ];
        $queryRes = DB::table('spot_goods as sg')->select($field)
            ->leftJoin('goods_spec as gs', $gs_on)
            ->where($where)->get();
        return $queryRes;
    }


    /**
     * description:获取现货单详情
     * editor:zhangdong
     * date : 2018.12.15
     * param $spot_order_sn 订单号
     * @return array
     */
    public function getSpotDetail($spot_order_sn)
    {
        $spot_order_sn = trim($spot_order_sn);
        $where[] = ['spot_order_sn', $spot_order_sn];
        $gs_on = [
            ['gs.spec_sn', '=', 'sg.spec_sn'],
        ];
        $this->field[] = 'gs.stock_num as gsStockNum';
        $this->field[] = 'gs.lock_stock_num';
        $queryRes = DB::table($this->table)->select($this->field)
            ->leftJoin('goods_spec as gs', $gs_on)
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description:DD单上传后修改现货单数量和销售折扣
     * editor:zhangdong
     * date : 2018.12.18
     * @return array
     */
    public function modSpotGoodsData(
        $spot_order_sn,
        $spec_sn,
        $sale_discount,
        $spot_num,
        $stock_num
    )
    {
        $where = [
            ['spot_order_sn', $spot_order_sn],
            ['spec_sn', $spec_sn],
        ];
        $update = [
            'sale_discount' => $sale_discount,
            'goods_number' => $spot_num,
            'stock_num' => $stock_num,
        ];
        $updateRes = DB::table('spot_goods')->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:子单根据库存分货-计算最终现货数量
     * editor:zhangdong
     * date : 2018.12.18
     * @param $stock (商品库存)
     * @param $goodsNum (购买数量)
     * @return int
     */
    public function calculateStock($stock, $goodsNum)
    {
        //采购数量 = 需求量 - 商品库存
        $buyNum = intval($goodsNum - $stock);
        $ordGoodsNum = 0;
        //此时说明库存是够的,订单购买数量就是需求数量
        if ($buyNum <= 0) {
            $ordGoodsNum = $goodsNum;
        }
        //此时说明库存不够，订单购买数量就是库存数量
        if ($buyNum > 0) {
            $ordGoodsNum = $stock;
        }
        return $ordGoodsNum;

    }

    /**
     * description:数据统计模块-订单管理-获取现货单统计信息
     * editor:zongxing
     * date: 2019.01.09
     */
    public function getSpotStatistics($sub_order_sn)
    {
        $field = [
            'so.sub_order_sn', 'so.spot_order_sn', 'mo.sale_user_id', 'su.user_name', 'sg.goods_number', 'sg.spec_price',
            'sg.sale_discount', 'mosg.exw_discount', 'sg.goods_number', 'gs.spec_weight', 'gs.estimate_weight',
            'gs.spec_sn','g.brand_id',
        ];
        $spot_order_list = DB::table('spot_goods as sg')
            ->leftJoin('spot_order as so', 'so.spot_order_sn', '=', 'sg.spot_order_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('mis_order_sub as mos', 'mos.sub_order_sn', '=', 'so.sub_order_sn')
            ->leftJoin('mis_order_sub_goods as mosg', function ($join) {
                $join->on('mosg.sub_order_sn', '=', 'so.sub_order_sn');
                $join->on('mosg.spec_sn', '=', 'sg.spec_sn');
            })
            ->leftJoin('mis_order as mo', 'mo.mis_order_sn', '=', 'mos.mis_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->whereIn('so.sub_order_sn', $sub_order_sn)
            ->get($field);
        $spot_order_list = objectToArrayZ($spot_order_list);
        $total_spot_list = [];
        foreach ($spot_order_list as $k => $v) {
            $sub_order_sn = $v['sub_order_sn'];
            $real_discount = $v['exw_discount'];
            $spec_weight = floatval($v['spec_weight']);
            $estimate_weight = floatval($v['estimate_weight']);
            $real_weight = $spec_weight == 0 ? $estimate_weight : $spec_weight;
            $goods_num = intval($v['goods_number']);
            $spec_price = floatval($v['spec_price']);
            $sale_discount = floatval($v['sale_discount']);
            $pst_price = floatval($goods_num * $spec_price * $sale_discount);//需求单销售总额
            if ($spec_price == 0 || $sale_discount == 0) {
                $spot_discount_price = 0;
            } else {
                $spot_discount_price = ($goods_num * $spec_price *
                    (1 - (
                            ($real_discount + ($real_weight / $spec_price / 0.0022 / 100)) / $sale_discount)
                    )
                );//需求单报价毛利金额
            }
            if (isset($total_spot_list[$sub_order_sn])) {
                $total_spot_list[$sub_order_sn][0]['spot_goods_num'] += $goods_num;
                $total_spot_list[$sub_order_sn][0]['pst_price'] += $pst_price;
                $total_spot_list[$sub_order_sn][0]['spot_discount_price'] += $spot_discount_price;
            } else {
                $total_spot_list[$sub_order_sn][0] = [
                    'sub_order_sn' => $sub_order_sn,
                    'spot_order_sn' => $v['spot_order_sn'],
                    'sale_user_id' => $v['sale_user_id'],
                    'user_name' => $v['user_name'],
                    'spot_goods_num' => $goods_num,
                    'pst_price' => $pst_price,
                    'spot_discount_price' => $spot_discount_price,
                ];
            }
        }
        return $total_spot_list;
    }

    /**
     * description:以商品为单位获取指定现货单的数据汇总
     * editor:zongxing
     * date : 2019.03.01
     * return Array
     */
    public function getSpotGoodsList($sub_order_sn)
    {
        $field = [
            'sg.spec_sn',
            DB::raw('SUM(jms_sg.goods_number) as total_spot_goods_num')
        ];
        $spot_order_list = DB::table('spot_goods as sg')
            ->leftJoin('spot_order as so', 'so.spot_order_sn', '=', 'sg.spot_order_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn')
            ->whereIn('so.sub_order_sn', $sub_order_sn)
            ->groupBy('sg.spec_sn')->get($field)->groupBy('spec_sn');
        $spot_order_list = objectToArrayZ($spot_order_list);
        return $spot_order_list;
    }

}//end of class
