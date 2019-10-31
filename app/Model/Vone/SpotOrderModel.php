<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入时间处理包 add by zhangdong on the 2018.12.06
use Carbon\Carbon;

class SpotOrderModel extends Model
{

    protected $table = 'spot_order as so';
    protected $field = [
        'so.sub_order_sn','so.spot_order_sn','so.is_push_erp','so.order_status','so.create_time'
    ];

    //现货单状态
    public $order_status = [
        '1' => '待确认',
        '2' => '待发货',
        '3' => '已发货',
        '4' => '已完成',
        '5' => '已关闭',
        '6' => '已取消',
    ];

    //现货单状态描述
    public $status_int = [
        'WAIT_SURE'  => 1,
        'WAIT_SHIP'  => 2,
        'YET_SHIP'   => 3,
        'YET_FINISH' => 4,
        'YET_CLOSE'  => 5,
        'YET_CANCEL' => 6,
    ];

    //现货单推送状态
    public $is_push_erp = [
        '1' => '未推送',
        '2' => '已推送',
    ];


    /**
     * @description:MIS订单列表-组装现货单数据
     * @editor:zhangdong
     * param $spotGoodsData 现货单商品数据
     * date:2018.12.13
     */
    public function makeSpotOrdData($spotGoodsData, $sub_order_sn)
    {
        //生成现货单号
        $spotSn = self::generalSpotSn();
        //根据商品信息计算总价
        //将需求单号写入商品信息中
        foreach ($spotGoodsData as $key => $value) {
            $spotGoodsData[$key]['spot_order_sn'] = $spotSn;
        }
        $spotOrdData = [
            'sub_order_sn' => $sub_order_sn,
            'spot_order_sn' => $spotSn,
        ];
        return [
            'spotOrdData' => $spotOrdData,
            'spotGoodsData' => $spotGoodsData,
        ];
    }


    /**
     * description:生成现货单号
     * editor:zhangdong
     * date : 2018.12.13
     * return String
     */
    private static function generalSpotSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $spot_sn = 'XH' . $strNum;
            //判断现货单号是否已经存在
            $count = DB::table('spot_order')
                ->where([
                    ['spot_order_sn', '=', $spot_sn]
                ])->count();
        } while ($count);
        return $spot_sn;
    }

    /**
     * description:保存现货单信息
     * editor:zhangdong
     * date : 2018.12.13
     */
    public function saveSpotOrdData($spotData)
    {
        $spotOrdData = $spotData['spotOrdData'];
        $spotGoodsData = $spotData['spotGoodsData'];
        if(empty($spotOrdData) || empty($spotGoodsData)) return false;
        $saveRes = DB::transaction(function () use ($spotOrdData, $spotGoodsData){
            DB::table('spot_order')->insert($spotOrdData);
            $execRes = DB::table('spot_goods')->insert($spotGoodsData);
            return $execRes;
        });
        return $saveRes;
    }

    /**
     * description:erp订单推送-根据现货单号获取订单基本信息
     * editor:zhangdong
     * date : 2018.12.13
     * @param $spot_order_sn
     * @return object
     */
    public function getSpotOrderInfo($spot_order_sn)
    {
        $field = [
            'so.spot_order_sn','so.order_status','so.create_time'
        ];
        $where = [
            ['so.spot_order_sn', $spot_order_sn]
        ];
        $queryRes = DB::table('spot_order AS so')->select($field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:修改订单状态
     * editor:zhangdong
     * date : 2018.12.15
     * param $spot_order_sn
     * param $status 现货单状态：1,待确认 2，待发货 3，已发货 4，已完成 5，已关闭 6,已取消
     * return object
     */
    public function updateStatus($spot_order_sn, $status)
    {
        $where = [
            ['spot_order_sn', $spot_order_sn],
        ];
        $update = [
            'order_status' => $status,
        ];
        $updateRes = DB::table('spot_order')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:修改订单是否已推送
     * editor:zhangdong
     * date : 2018.12.15
     * param $spot_order_sn 订单号
     * param $is_push_erp 是否已推送 1，未推送 2，已推送
     */
    public function updateIsPush($spot_order_sn, $is_push_erp)
    {
        $pushDesc = $this->is_push_erp[$is_push_erp];
        if (is_null($pushDesc)) return false;
        $where = [
            ['spot_order_sn', $spot_order_sn],
        ];
        $update = [
            'is_push_erp' => $is_push_erp,
        ];
        $updateRes = DB::table('spot_order')->where($where)->update($update);
        return $updateRes;

    }


    /**
     * @description:查询现货单列表
     * @editor:zhangdong
     * @param $orderInfo
     * date:2018.12.15
     */
    public function getSpotOrderList($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $field = [
            'so.sub_order_sn','so.spot_order_sn','so.is_push_erp','so.order_status',
            'so.create_time',
        ];
        $sg_on = [
            ['so.spot_order_sn', '=', 'sg.spot_order_sn'],
        ];
        $queryRes = DB::table('spot_order as so')->select($field)
            ->leftJoin('spot_goods as sg', $sg_on)
            ->where($where)->groupBy('so.spot_order_sn')->paginate($pageSize);
        //如果查询没有结果则直接返回
        if ($queryRes->count() == 0) return $queryRes;
        $sgModel = new SpotGoodsModel();
        foreach ($queryRes as $key => $value) {
            //根据订单号查询商品信息
            $spot_order_sn = trim($value->spot_order_sn);
            //系统订单状态
            $queryRes[$key]->desc_push = $this->is_push_erp[intval($value->is_push_erp)];
            $queryRes[$key]->desc_status = $this->order_status[intval($value->order_status)];
            $goodsData = $sgModel->getSubOrderGoods($spot_order_sn, $where);
            $queryRes[$key]->goods_data = $goodsData;
        }
        return $queryRes;

    }

    /**
     * description:查询订单-组装查询条件
     * editor:zhangdong
     * date:2018.12.15
     */
    protected function makeWhere($reqParams)
    {
        //时间处理-查询订单列表时默认只查近三个月的
        //开始时间
        $start_time = Carbon::now()->addMonth(-3)->toDateTimeString();
        if(isset($reqParams['start_time'])){
            $start_time = trim($reqParams['start_time']);
        }
        //结束时间
        $end_time = Carbon::now()->toDateTimeString();
        if(isset($reqParams['end_time'])){
            $end_time = trim($reqParams['end_time']);
        }
        //时间筛选
        $where = [
            ['so.create_time','>=',$start_time],
            ['so.create_time','<=',$end_time ],
        ];
        //订单编号
        if(isset($reqParams['spot_order_sn'])){
            $where[] = [
                'so.spot_order_sn', trim($reqParams['spot_order_sn'])
            ];
        }
        //现货单状态：1,待确认 2，待发货 3，已发货 4，已完成 5，已关闭
        if(isset($reqParams['order_status'])){
            $where[] = [
                'so.order_status', trim($reqParams['order_status'])
            ];
        }
        //商品名称
        if(isset($reqParams['goods_name'])){
            $where[] = [
                'sg.goods_name','LIKE', '%' . trim($reqParams['goods_name'] . '%')
            ];
        }
        //规格码
        if(isset($reqParams['spec_sn'])){
            $where[] = [
                'sg.spec_sn', trim($reqParams['spec_sn'])
            ];
        }
        return $where;
    }

    /**
     * description:根据现货单号获取订单基本信息
     * editor:zhangdong
     * date : 2018.12.15
     * @param $value (查找值)
     * @param int $type 查找类型 1，根据现货单号查找 2，根据子订单号查找
     * @return
     */
    public function getSpotOrderMsg($value, $type = 1)
    {
        $field = [
            'so.sub_order_sn','so.spot_order_sn','so.is_push_erp',
            'so.order_status','so.create_time'
        ];
        $where = [];
        if ($type === 1) {
            $where[] = ['so.spot_order_sn', $value];
        }
        if ($type === 2) {
            $where[] = ['so.sub_order_sn', $value];
        }
        $queryRes = DB::table('spot_order AS so')->select($field)
            ->where($where)->first();
        if (!empty($queryRes)) {
            $queryRes->desc_status = $this->order_status[intval($queryRes->order_status)];
        }
        return $queryRes;
    }

    /**
     * description:获取需要取消的现货单
     * author:zhangdong
     * date : 2019.03.13
     */
    public function getNeedCancelSpotOrder()
    {
        //获取超过三天未传DD单的子单
        $mosModel = new MisOrderSubModel();
        $needCancelSubOrder = $mosModel->getCancelSubOrder();
        //组装子单号为数组形式
        $arrSubOrderSn = [];
        foreach ($needCancelSubOrder as $value) {
            $sub_order_sn = trim($value->sub_order_sn);
            $arrSubOrderSn[] = $sub_order_sn;
        }
        //查询子单对应的现货单
        $subToSpotOrder = self::getSpotBySub($arrSubOrderSn);
        if ($subToSpotOrder->count() == 0) {
            return [];
        }
        $arrSpotSn = [];
        foreach ($subToSpotOrder as $value) {
            $spotOrderSn = trim($value->spot_order_sn);
            $arrSpotSn[] = $spotOrderSn;
        }
        return $arrSpotSn;
    }

    /**
     * description:查询子单对应的现货单
     * author:zhangdong
     * date : 2019.03.13
     */
    private function getSpotBySub($arrSubOrderSn)
    {
        $queryRes = DB::table($this->table)->select($this->field)
            ->whereIn('order_status', [1,2])
            ->whereIn('sub_order_sn', $arrSubOrderSn)->get();
        return $queryRes;
    }

    /**
     * descriptio 查询子单对应的现货单
     * author zhangdong
     * date 2019.08.20
     */
    public function getSpotBySubOrder($subOrderSn)
    {
        $where = [
            ['sub_order_sn', $subOrderSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }















}//end of class
