<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//引入时间处理包 add by zhangdong on the 2018.06.29
use Carbon\Carbon;

class OrderInfoModel extends Model
{
    protected $order_info = "order_info";
    protected $order_goods = "order_goods";
    //平台订单状态
    protected $order_status = [
        '1' => '待发货',
        '2' => '待清关',
        '3' => '转关中',
        '4' => '配发中'
    ];
    //erp订单状态
    protected $trade_status = [
        '5' => '已取消',
        '10' => '待付款',
        '15' => '等未付',
        '16' => '延时审核',
        '19' => '预订单前处理',
        '20' => '前处理',
        '21' => '委外前处理',
        '22' => '抢单前处理',
        '25' => '预订单',
        '27' => '待抢单',
        '30' => '待客审',
        '35' => '待财审',
        '40' => '待递交仓库',
        '45' => '递交仓库中',
        '50' => '已递交仓库',
        '53' => '未确认',
        '55' => '已审核',
        '95' => '已发货',
        '100' => '已签收',
        '105' => '部分打款',
        '110' => '已完成'
    ];


    /**
     * description:订单信息表构造函数
     * editor:zhangdong
     * date : 2018.11.12
     * return Object
     */
    public function __construct()
    {
        $order_status = $this->order_status;
        $trade_status = $this->trade_status;
        return [
            'order_status' => $order_status,
            'trade_status' => $trade_status,
        ];
    }
    /**
     * description:批量写入订单信息表
     * editor:zhangdong
     * date : 2018.06.28
     * return Boolean
     */
    public function batchInsertOrder(Array $data)
    {
        $insertRes = DB::table($this->order_info)->insert($data);
        return $insertRes;
    }

    /**
     * description:批量写入订单商品信息表
     * editor:zhangdong
     * date : 2018.06.28
     * return Boolean
     */
    public function batchInsertGoods(Array $data)
    {
        $insetRes = DB::table($this->order_goods)->insert($data);
        return $insetRes;
    }

    /**
     * description:
     * 1,定时任务-获取订单信息
     * 2,清关管理-待清关订单-上传清关订单-检查对应订单是否已经是待清关状态
     * editor:zhangdong
     * date:2018.06.28
     * params:$queryType 1,按订单号查询 2,按订单号和订单状态组合查询
     * return Object
     */
    public function getOderInfo($data, $queryType)
    {
        if ($queryType == 1) {
            $where = [
                ['trade_no', $data]
            ];
        } elseif ($queryType == 2) {
            $where = [
                ['trade_no', $data],
                ['order_status', 2],//平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            ];
        } elseif ($queryType == 3) {
            $where = [
                ['trade_no', $data],
                ['order_status', 3],//平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            ];
        } else {
            return false;
        }
        $selectField = 'shop_no,shop_name,trade_no,src_tids,trade_status,order_status,buyer_nick,receiver_name,receiver_area,
                        receiver_address,receiver_mobile,logistics_no,logistics_name,goods_amount,post_amount,receivable,
                        paid,pay_time,trade_time,modified,created,create_time,modify_time';
        $queryRes = DB::table($this->order_info)->selectRaw($selectField)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:获取订单信息
     * editor:zhangdong
     * date:2018.06.29
     * params:$queryType 1,获取待发货订单 2,获取待清关订单
     * return Object
     */
    public function getOrderMsg($data, $queryType)
    {
        $createWhere = $this->createOrdWhere($data, $queryType);
//        print_r($createWhere);die;
        $where = $createWhere['where'];
        $orWhere = $createWhere['orWhere'];
        $selectField = 'shop_no,shop_name,trade_no,src_tids,trade_status,order_status,buyer_nick,receiver_name,receiver_area,
                        receiver_address,receiver_mobile,logistics_no,logistics_name,goods_amount,post_amount,                             
                        receivable,paid,pay_time,trade_time,modified,delivery_time,custom_time,created,create_time';
        $queryRes = DB::table($this->order_info)->selectRaw($selectField)
            ->where($where)
            ->where(function ($result) use ($orWhere) {
                if (count($orWhere) >= 1) {
                    $result->orWhere($orWhere['orWhere1'])
                        ->orWhere($orWhere['orWhere2'])
                        ->orWhere($orWhere['orWhere3']);
                }
            })->orderBy('created','desc')->paginate(15);
//        print_r($queryRes);die;
        return $queryRes;
    }

    /**
     * description:订单查询-组装查询条件
     * editor:zhangdong
     * date:2018.07.02
     * params:$queryType 1,获取待发货订单 2,获取待清关订单 3,获取转关中订单
     * return array
     */
    public function createOrdWhere($data, $queryType)
    {
        //搜索关键字
        $keywords = $data['keywords'];
        $orWhere = [];
        if ($keywords) {
            $orWhere = [
                'orWhere1' => [
                    ['trade_no', 'LIKE', "%$keywords%"],
                ],
                'orWhere2' => [
                    ['shop_name', 'LIKE', "%$keywords%"],
                ],
                'orWhere3' => [
                    ['src_tids', 'LIKE', "%$keywords%"],
                ],
            ];
        }
        if ($queryType == 1) {//获取待发货订单
            //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            $order_status = intval($data['order_status']);
            $where = [
                ['order_status', $order_status],
            ];
            //查询超时未发货订单标记
            $expire_mark = intval($data['expire_mark']);
            if ($expire_mark == 1) {
                //erp已审核订单超过24小时未发货（没有物流单号）的订单视为超时未发货
                //当前时间-审核时间>24小时 => 审核时间<当前时间-24小时
                $expire_time = Carbon::now()->modify('- 1 days')->toDateTimeString();
                $expireWhere = [
                    ['modified', '<', $expire_time]
                ];
                $where = array_merge($where, $expireWhere);
            }
        } elseif ($queryType == 2) {//获取待清关订单
            //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            $order_status = intval($data['order_status']);
            $where = [
                ['order_status', $order_status],
            ];
            //查询超时未清关订单标记
            $expire_custom = intval($data['expire_custom']);
            if ($expire_custom == 1) {
                //已审核订单超过96小时（4天）未清关的订单视为超时未清关
                //当前时间-发货时间>96小时 => 发货时间<当前时间-96小时
                $expire_time = Carbon::now()->modify('- 4 days')->toDateTimeString();
                $expireWhere = [
                    ['delivery_time', '<', $expire_time]
                ];
                $where = array_merge($where, $expireWhere);
            }
        } elseif ($queryType == 3) {//获取转关中订单
            //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            $order_status = intval($data['order_status']);
            $where = [
                ['order_status', $order_status],
            ];
        } else {
            return false;
        }
        $createRes = [
            'orWhere' => $orWhere,
            'where' => $where,
        ];
        return $createRes;
    }

    /**
     * description:批量更新订单状态-将待清关状态改为转关中
     * editor:zhangdong
     * date:2018.07.02
     * params:$queryType 1,获取待发货订单 2,获取待清关订单
     * return array
     */
    public function updateOrderStatus(array $trade_sn, $status)
    {
        $updateRes = DB::transaction(function () use ($trade_sn, $status) {
            $nowTime = Carbon::now();
            //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            $field = '';
            if ($status == 3) {
                $field = 'custom_time';//清关时间（上传清关订单时更新）
            } elseif ($status == 4) {
                $field = 'arrive_store_time';//到仓时间（即到达深圳保税仓的时间，又称转关结束时间）
            }
            $updateRes = DB::table($this->order_info)->whereIn('trade_no', $trade_sn)
                ->update([
                    'order_status' => $status,
                    $field => $nowTime,
                ]);
            return $updateRes;
        });
        return $updateRes;
    }

    /**
     * description:更新订单物流单号和订单状态
     * editor:zhangdong
     * date:2018.11.07
     */
    public function updateShipNo($res)
    {
        $curTime = date('Y-m-d H:i:s');
        foreach($res as $key => $value){
            if($key == 0) continue;
            $order_sn = $value['0'];
            $logistic = $value['1'];
            $logis_name = $value['2'];
            $where = [
                ['trade_no',$order_sn],
            ];
            $update = [
                'logistics_no' => $logistic,
                'logistics_name' => $logis_name,
                'order_status' => 2,
                'delivery_time' => $curTime,
            ];
            $updateRes = DB::table('order_info') -> where($where) -> update($update);
        }
        return $updateRes;
    }

    /**
     * description:上传清关订单和上传转关订单
     * editor:zhangdong
     * date:2018.11.07
     * @param $up_type 1,上传已发货订单 2，上传清关订单 3，上传转关订单
     */
    public function updateOrderData($res, $up_type)
    {
        $curTime = date('Y-m-d H:i:s');
        if($up_type == 2) {
            $order_status = 3;//清关订单上传成功后订单状态变为转关中（3）
            $update = [
                'order_status' => $order_status,
                'custom_time' => $curTime,
            ];
        }
        if($up_type == 3){
            $order_status = 4;//转关订单上传成功后订单状态变为配发中（4）
            $update = [
                'order_status' => $order_status,
                'arrive_store_time' => $curTime,
            ];
        }
        $order_sn = [];
        foreach($res as $key => $value){
            if($key == 0) continue;
            $order_sn[] = $value['0'];
        }
        $updateRes = DB::table('order_info') -> whereIn('trade_no', $order_sn) -> update($update);
        return $updateRes;
    }

    /**
     * description:订单列表_虽然只进行了一次查询，但是由于差的数据量比较大导致渲染速度变慢_stop
     * editor:zhangdong
     * date:2018.11.08
     */
    public function getOrderListStop($reqParams, $startStr, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $field = [
            'oi.shop_no', 'oi.shop_name', 'oi.trade_no', 'oi.trade_status', 'oi.order_status',
            'oi.receiver_name', 'oi.receiver_mobile', 'oi.trade_time', 'oi.pay_time',
            'og.goods_name', 'og.erp_merchant_no', 'og.spec_sn', 'og.num', 'og.price',
            'og.order_price'
        ];
        $queryRes = DB::table('order_info as oi')->select($field)
            ->leftJoin('order_goods as og', 'og.trade_no', '=', 'oi.trade_no')
            ->where($where)->get();
        $listData = [];
        $returnMsg = [
            'listData' => $listData,
            'total_num' => $queryRes -> count(),
        ];
        if($queryRes -> count() == 0) return $returnMsg;
        //组装页面数据
        foreach($queryRes as $key => $value){
            $trade_no = trim($value -> trade_no);
            $tra_no = session('tra_no');
            if (is_null($tra_no)) {
                session(['tra_no' => $trade_no]);
                $tra_no = session('tra_no');
            }
            if ($tra_no == $trade_no) {
                $groupData[$tra_no][] = $value;
            } else {
                session(['pur_sn' => $trade_no]);
                $groupData[$trade_no][] = $value;
            }
        }
//        var_dump($groupData);die;
        foreach($groupData as $v_key => $v){
            $listData[] = [
                'trade_no' => $v_key,
                'goods_data' => $v,
            ];
        }
        $total_num = count($listData);
        $listData = array_slice($listData, $startStr, $pageSize);
        $returnMsg = [
            'listData' => $listData,
            'total_num' => $total_num,
        ];
        return $returnMsg;

    }

    /**
     * description:订单列表
     * editor:zhangdong
     * date:2018.11.08
     */
    public function getOrderList($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $field = [
            'oi.shop_no', 'oi.shop_name', 'oi.trade_no', 'oi.trade_status', 'oi.order_status',
            'oi.receiver_name', 'oi.receiver_mobile', 'oi.trade_time', 'oi.pay_time'
        ];
        $queryRes = DB::table('order_info as oi')->select($field)
            ->leftJoin('order_goods as og', 'og.trade_no', '=', 'oi.trade_no')
            ->where($where)->groupBy('oi.trade_no')->paginate($pageSize);

        foreach ($queryRes as $key => $value) {
            //根据trade_no查询商品信息
            $trade_no = trim($value->trade_no);
            //系统订单状态
            $queryRes[$key]->order_status = $this->order_status[intval($value->order_status)];
            //erp订单状态
            $queryRes[$key]->trade_status = $this->trade_status[intval($value->trade_status)];
            $queryRes[$key]->goods_data = $this->getOrderGoods($trade_no, $where);
        }
        return $queryRes;
    }

    /**
     * description:查询订单-组装查询条件
     * editor:zhangdong
     * date:2018.11.08
     */
    protected function makeWhere($reqParams)
    {
        //时间处理-查询订单列表时默认只查近三个月的
        //开始时间
        $start_time = Carbon::now()->addMonth(-3)->toDateTimeString();
        if(isset($reqParams['start_time'])){
            $start_time = trim($reqParams['start_time']);
        }
        ////结束时间
        $end_time = Carbon::now()->toDateTimeString();
        if(isset($reqParams['end_time'])){
            $end_time = trim($reqParams['end_time']);
        }
        $where = [
            ['oi.trade_time','>=',$start_time],
            ['oi.trade_time','<=',$end_time ],
        ];
        if(isset($reqParams['shop_no'])){
            $where[] = [
                'oi.shop_no', trim($reqParams['shop_no'])
            ];
        }
        //erp订单编号
        if(isset($reqParams['trade_no'])){
            $where[] = [
                'oi.trade_no', trim($reqParams['trade_no'])
            ];
        }
        //台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
        if(isset($reqParams['order_status'])){
            $where[] = [
                'oi.order_status', trim($reqParams['order_status'])
            ];
        }
        //erp订单状态：5已取消，10待付款，15等未付，16延时审核，19预订单前处理，20前处理，21委外前处理，22抢单前处理，25预订单，27待抢单，30待客审，35待财审，40待递交仓库，45递交仓库中，\r\n 50已递交仓库，53未确认，55已审核，95已发货，100已签收，105部分打款，110已完成
        if(isset($reqParams['trade_status'])){
            $where[] = [
                'oi.trade_status', trim($reqParams['trade_status'])
            ];
        }
        //收件人
        if(isset($reqParams['receiver_name'])){
            $where[] = [
                'oi.receiver_name', trim($reqParams['receiver_name'])
            ];
        }
        //收件手机
        if(isset($reqParams['receiver_mobile'])){
            $where[] = [
                'oi.receiver_mobile', trim($reqParams['receiver_mobile'])
            ];
        }
        //商品名称
        if(isset($reqParams['goods_name'])){
            $where[] = [
                'og.goods_name', trim($reqParams['goods_name'])
            ];
        }
        //商家编码
        if(isset($reqParams['erp_merchant_no'])){
            $where[] = [
                'og.erp_merchant_no', trim($reqParams['erp_merchant_no'])
            ];
        }
        //规格码
        if(isset($reqParams['spec_sn'])){
            $where[] = [
                'og.spec_sn', trim($reqParams['spec_sn'])
            ];
        }
        return $where;
    }


    /**
     * @description:订单列表-获取订单商品信息
     * @notice:涉及函数：$this -> getOrderList
     * @editor:zhangdong
     * @param $trade_no 订单号
     * @param $goodsWhere 查询条件
     * date:2018.11.09
     */
    protected function getOrderGoods($trade_no, $goodsWhere)
    {
        $field = [
            'og.erp_merchant_no','og.spec_sn','og.goods_name','og.num',
            'og.price','og.order_price'
        ];
        $where = [];
        //对查询条件进行处理-如果发现有本函数适用的条件则加入筛选
        foreach($goodsWhere as $value){
            $field_name = $value[0];
            $is_exit = strpos($field_name,'og');
            /*此处strpos函数如果查到对应的字符则返回出现的位置，因为og出现的位置为0
            而0不等于true，所以用false来判断*/
            if($is_exit !== false) $where[] = $value;
        }
        $where[] = ['og.trade_no',$trade_no];
        $queryRes = DB::table('order_goods as og')->select($field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:订单列表-获取订单详情
     * editor:zhangdong
     * date:2018.11.12
     */
    public function getOrderDetail($trade_no)
    {
        $field = [
            'oi.shop_no', 'oi.shop_name', 'oi.trade_no', 'oi.trade_status', 'oi.order_status',
            'oi.receiver_name', 'oi.receiver_mobile', 'oi.trade_time', 'oi.pay_time',
            'og.erp_merchant_no','og.spec_sn','og.goods_name','og.num',
            'og.price','og.order_price'
        ];
        $where = [
            ['oi.trade_no',$trade_no]
        ];
        $queryRes = DB::table('order_info as oi')->select($field)
            ->leftJoin('order_goods as og', 'og.trade_no', '=', 'oi.trade_no')
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description:订单列表-组装订单详情数据
     * editor:zhangdong
     * date:2018.11.12
     */
    public function makeDetailData($arrData)
    {
        $orderData = [];
        $goodsData = [];
        foreach ($arrData as $value) {
            $orderData = [
                'shop_no' => intval($value->shop_no),
                'shop_name' => trim($value->shop_name),
                'trade_no' => trim($value->trade_no),
                'trade_status' => intval($value->trade_status),
                'order_status' => intval($value->order_status),
                'receiver_name' => trim($value->receiver_name),
                'receiver_mobile' => trim($value->receiver_mobile),
                'trade_time' => trim($value->trade_time),
                'pay_time' => trim($value->pay_time),
            ];
            $goodsData[] = [
                'erp_merchant_no' => trim($value->erp_merchant_no),
                'spec_sn' => trim($value->spec_sn),
                'goods_name' => trim($value->goods_name),
                'num' => intval($value->num),
                'price' => trim($value->price),
                'order_price' => trim($value->order_price),
            ];
        }

        return [
            'orderData' => $orderData,
            'goodsData' => $goodsData,
        ];

    }

    /**
     * description:erp订单推送-根据erp订单号获取订单基本信息
     * editor:zhangdong
     * date : 2019.01.29
     * @return object
     */
    public function getErpOrderInfo($trade_no)
    {
        $field = [
            'src_tids','trade_status','create_time'
        ];
        $where = [
            ['trade_no', $trade_no]
        ];
        $queryRes = DB::table($this->order_info)->select($field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:根据订单号查询该订单的商品信息
     * editor:zhangdong
     * date : 2018.12.14
     * @return object
     */
    public function getErpGoodsInfo($trade_no)
    {
        $field = [
            'goods_name', 'spec_sn', 'actual_num', 'order_price',
            'erp_merchant_no',
        ];
        $where = [
            ['trade_no', $trade_no]
        ];
        $queryRes = DB::table($this->order_goods)->select($field)
            ->where($where)->get();
        return $queryRes;
    }

    /*
     * description：组装erp订单推送信息
     * author：zhangdong
     * date：2019.01.29
     */
    public function createErpPushData($orderInfo, $orderGoods)
    {
        if (empty($orderInfo) || empty($orderGoods)) return false;
        //由于要计算商品总价，所以先处理商品信息
        //erp中对应的原始订单编号
        $src_tids = trim($orderInfo->src_tids);
        $tradeStatus = intval($orderInfo->trade_status);
        $orderAmount = 0;
        foreach ($orderGoods as $key => $value) {
            $num = intval($value->actual_num);
            $spec_price = trim($value->order_price);
            $orderGoods[$key]->oid = $src_tids . "_$key";
            $orderGoods[$key]->num = $num;
            $orderGoods[$key]->price = $spec_price;
            $orderGoods[$key]->status = $tradeStatus;
            $orderGoods[$key]->refund_status = 0;
            $orderGoods[$key]->goods_no = trim($value->spec_sn);
            $orderGoods[$key]->spec_id = trim($value->spec_sn);
            $orderGoods[$key]->spec_no = trim($value->erp_merchant_no);
            $orderGoods[$key]->goods_name = trim($value->goods_name);
            $orderGoods[$key]->adjust_amount = 0;
            $orderGoods[$key]->discount = 0;
            $orderGoods[$key]->share_discount = 0;
            //计算商品价格
            $orderAmount += $spec_price * $num;
        }
        //将$orderGoods转为数组
        $orderGoods = objectToArray($orderGoods);
        $orderData[] = [
            'tid' => trim($src_tids),
            'paid' => $orderAmount,
            'trade_status' => $tradeStatus,
            //付款状态:0未付款1部分付款2已付款
            'pay_status' => 0,
            //发货条件:1款到发货2货到付款(包含部分货到付款)
            'delivery_term' => 1,
            'trade_time' => trim($orderInfo->create_time),
            'pay_id' => trim($src_tids),
            'logistics_type' => -1,
            'invoice_type' => 1,
            'post_amount' => 18,
            'cod_amount' => 0,
            'ext_cod_fee' => 0,
            'seller_memo' => 'MIS测试单，请忽略',
            'order_list' => $orderGoods,
        ];
        return $orderData;
    }

    /**
     * description:修改erp订单状态
     * editor:zhangdong
     * date:2019.01.29
     */
    public function updateErpOrderStatus($trade_no,$status)
    {
        $where = [
            ['trade_no',$trade_no],
        ];
        $update = [
            'trade_status'=>intval($status),
        ];
        $updateRes = DB::table($this->order_info)->where($where)->update($update);
        return $updateRes;

    }











}//end of class
