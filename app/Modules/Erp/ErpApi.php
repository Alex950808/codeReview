<?php
//created by zhangdong on the 2018.06.27
namespace App\Modules\Erp;

//引入订单模型
use App\Model\Vone\DeliverGoodsModel;
use App\Model\Vone\DeliverOrderModel;
use App\Model\Vone\ErpGoodsSpecModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\OrderInfoModel;
//引入商品模型
use App\Model\Vone\GoodsModel;
//引入数据库类库
use App\Model\Vone\RealPurchaseDetailModel;
use App\Model\Vone\RealPurchaseModel;
use App\Model\Vone\SpotGoodsModel;
use App\Model\Vone\SpotOrderModel;
use Illuminate\Support\Facades\DB;
//引入日志库文件 add by zhangdong on the 2018.06.28
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ErpApi
{

    //店铺
    const SHOP_MSG = [
        '01' => '蜜蜂派',
        '02' => '内销',
        '03' => '芭蕾舞后',
        '04' => '麦芽国际专营店',
        '05' => '批发专用店',
        '06' => 'D-VIP专用',
        '07' => '集货街（香港仓）',
        '08' => '集货街APP测试店铺',
        '09' => '洋码头',
        '11' => '英雄臻选海外直邮店',
        '12' => '英雄有赞网',
        '13' => 'WowBox惊喜魔盒会员店',
        '14' => 'Lee享生活',
        '15' => 'fixtyle',
        '16' => '英雄爸爸heropapa',
        '17' => '大鼻子美妆',
        '18' => '集货街（保税仓）',
        '19' => '问问',
        '20' => '全球哒哒（保税）',
        '21' => '全球哒哒（香港）',
        '33' => 'MIS平台',
        '110' => '悦儿家',
    ];

    //erp订单状态
    const TRADE_STATUS = [
        '55' => '已审核',
        '95' => '已发货',
        '110' => '已完成'
    ];

    private $erpRequest;
    private $egsModel;


    /**
     * description:本类构造函数
     * editor:zhangdong
     * date : 2019.01.02
     */
    public function __construct()
    {
        $this->erpRequest = new ErpRequest();
        $this->egsModel = new ErpGoodsSpecModel();
    }


    /**
     * description:获取erp订单管理数据
     * editor:zhangdong
     * date : 2018.06.27
     */
    public function getErpOrder($shopNum, $start_time, $end_time)
    {
        $page_no = 0;
        $erp = new ErpRequest();
        do {
            $postData = [
                'start_time' => strval($start_time),
                'end_time' => strval($end_time),
                'page_no' => $page_no,
                'page_size' => 40,
                'shop_no' => strval($shopNum),
            ];
            $queryRes = $erp->request_method($uri = 'trade_query.php', $postData);
            $erpOrderInfo = $queryRes['trades'];
            //组装订单数据和订单商品数据,并将对应数据写入数据表
            $operateRes = $this->createOrderData($erpOrderInfo);
            ++$page_no;
        } while ($erpOrderInfo);
        return $operateRes;
    }

    /**
     * description:组装订单数据和订单商品数据,并将对应数据写入数据表
     * editor:zhangdong
     * date : 2018.06.27
     */
    public function createOrderData($erpOrderInfo)
    {
        $log = new Logger('erpOrder');
        $log->pushHandler(new StreamHandler(storage_path('logs/erpOrder.log'), Logger::INFO));
        $orderInfoModel = new OrderInfoModel();
        foreach ($erpOrderInfo as $value) {
            //订单数据组装
            $trade_no = trim($value['trade_no']);
            $pay_time = date('Y-m-d H:i:s', strtotime($value['pay_time']));
            $log->addInfo("erp订单号-$trade_no -支付时间-$pay_time");
            $logistics_no = trim($value['logistics_no']);
            $trade_status = intval($value['trade_status']);
            //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
            //erp已审核状态订单没有物流单号视为待发货，有则视为待清关
            if ($trade_status == 55) {
                $order_status = empty($logistics_no) ? 1 : 2;
            } else {
                $order_status = 3;
            }
            $orderInfo = [
                'trade_no' => $trade_no,//erp订单编号
                'src_tids' => trim($value['src_tids']),//原始订单编号
                'trade_status' => $trade_status,//erp订单状态
                'order_status' => $order_status,//平台订单状态
                'trade_time' => trim($value['trade_time']),//下单时间
                'pay_time' => $pay_time,//支付时间
                'buyer_nick' => trim($value['buyer_nick']),//客户网名
                'receiver_name' => trim($value['receiver_name']),//收件人
                'receiver_area' => trim($value['receiver_area']),//省市县
                'receiver_address' => trim($value['receiver_address']),//收货地址
                'receiver_mobile' => trim($value['receiver_mobile']),//收件手机
                'logistics_no' => $logistics_no,//物流单号
                'goods_amount' => trim($value['goods_amount']),//货品总额
                'post_amount' => trim($value['post_amount']),//邮费
                'receivable' => trim($value['receivable']),//应收金额
                'paid' => trim($value['paid']),//已付金额
                'modified' => trim($value['modified']),//最后修改时间
                'created' => trim($value['created']),//订单生成时间
                'shop_no' => trim($value['shop_no']),//店铺id
                'shop_name' => trim($value['shop_name']),//店铺名称
                'logistics_name' => trim($value['logistics_name']),//快递公司
            ];
            //根据erp订单号检查该单是否已经写入数据表
            $queryType = 1;
            $checkRes = $orderInfoModel->getOderInfo($trade_no, $queryType);
            if ($checkRes->count() >= 1) {
                continue;
            }
            //订单商品组装
            $goodsList = $value['goods_list'];
            $orderGoods = [];
            foreach ($goodsList as $item) {
                //根据商家编码获取商品规格码
                $goodsModel = new GoodsModel();
                $queryType = 1;
                $erp_merchant_no = trim($item['spec_no']);
                $goodsInfo = $goodsModel->getGoodsInfo($erp_merchant_no, $queryType);
                $spec_sn = '';
                if (!empty($goodsInfo)) {
                    $spec_sn = $goodsInfo->spec_sn;
                }
                $orderGoods[] = [
                    'trade_no' => $trade_no,//erp订单编号
                    'erp_merchant_no' => $erp_merchant_no,//商家编码
                    'spec_sn' => $spec_sn,//商品规格码
                    'goods_name' => $item['goods_name'],//erp商品名称
                    'api_goods_name' => $item['api_goods_name'],//平台商品名称
                    'num' => $item['num'],//购买数量
                    'actual_num' => $item['actual_num'],//实发数量
                    'price' => $item['price'],//商品标价
                    'order_price' => $item['order_price'],//成交价
                    'spec_name' => $item['spec_name'],//规格名
                    'modified' => $item['modified'],//最后修改时间
                    'created' => $item['created'],//创建时间
                ];
            }
            //开启事务
            DB::beginTransaction();
            //写入订单信息表
            $orderInfoModel->batchInsertOrder($orderInfo);
            //写入订单商品表
            $insertRes = $orderInfoModel->batchInsertGoods($orderGoods);
            if (!$insertRes) {
                DB::rollback();
            }
            DB::commit();

        }//end of foreach one
        return true;
    }

    /**
     * title：同步物流信息
     * description：
     * 1、获取erp待同步物流信息；
     * 2、更新本地库订单物流信息；
     * 3、回写erp物流状态（下次如果物流信息不发生变化，无需同步）；
     * author：zhangdong
     * date：2018.07.14
     */
    public function sycLogisticInfo($shopNum)
    {
        $erp = $erp = new ErpRequest();
        $shopNo = strval($shopNum);
        $log = new Logger('erpLogistics');
        $log->pushHandler(new StreamHandler(storage_path('logs/erpLogistics.log'), Logger::INFO));
        $log->addInfo("店铺名称：" . self::SHOP_MSG[$shopNum]);
        $postData = [
            "shop_no" => $shopNo,
        ];
        $url = "logistics_sync_query.php";
        $erpReturn = $erp->request_method($url, $postData);
        if (!empty($erpReturn['trades'])) {
            foreach ($erpReturn['trades'] as $kk => $vv) {
                $rec_id = $vv['rec_id'];
                $src_tids = $vv['tid']; //原始订单号
                $logistics_no = $vv['logistics_no']; //物流单号
                $consign_time = date('Y-m-d H:i:s', strtotime($vv['consign_time'])); //发货时间
                $shipName = trim($vv['logistics_name_erp']);//物流公司
                //查询本地订单,如果为待发货状态则将物流单号和发货时间写入数据库，并将订单状态改为待清关状态
                $fields = 'order_status';
                $where = [
                    ['src_tids', $src_tids],
                ];
                $log->addInfo('原始订单号：' . $src_tids);
                $local_order = DB::table('order_info')->selectRaw($fields)->where($where)->first();
                if (empty($local_order)) {
                    $log->addInfo('内部管理系统没有该订单');
                    continue;
                }
                //平台订单状态 1，待发货 2，待清关 3，转关中 4，配发中
                $orderStatus = intval($local_order->order_status);
                $log->addInfo('订单状态：' . $orderStatus);
                $log->addInfo('物流单号：' . $logistics_no . '-发货时间：' . $consign_time);
                //如果订单状态不是待发货则直接跳过
                if ($orderStatus !== 1) continue;
                //同步本地订单物流信息
                $update = [
                    'order_status' => 2,
                    'logistics_no' => $logistics_no,
                    'logistics_name' => $shipName,
                    'delivery_time' => $consign_time,
                ];
                $updateRes = DB::table('order_info')->where($where)->update($update);
                if ($updateRes) { //更新数据成功,回写接口状态
                    $log->addInfo('订单状态及物流信息更新成功');
                    $logistics_info = [
                        'logistics_list' => [
                            ['rec_id' => $rec_id, 'status' => 0, 'message' => '同步成功']
                        ]
                    ];
                } else { //更新数据失败
                    $log->addInfo('物流信息更新失败');
                    $logistics_info = [
                        'logistics_list' => [
                            ['rec_id' => $rec_id, 'status' => 1, 'message' => '同步数据失败，订单状态错误或订单编号错误']
                        ]
                    ];
                }
                $erp->request_method($uri = 'logistics_sync_ack.php', $logistics_info);
            }
        }
        return true;
    }//end of function

    /*
     * title：批量创建erp平台货品（平台货品）
     * author：zhangdong
     * date：2018.11.14
     */
    public function createPlatformGoods()
    {
        //设置连接时间为无限长
        set_time_limit(0);
        //查询未同步到erp的商品信息 ---is_erp_push:是否推送到erp对应的项：0，未进行erp推送 1，平台货品货品创建成功 2，货品档案创建成功
        $field = [
            'g.goods_id', 'g.goods_sn', 'g.cat_id', 'g.goods_name',
            'gs.spec_price', 'gs.stock_num', 'gs.spec_id', 'gs.spec_sn',
            'gs.erp_merchant_no'
        ];
        $where = [
            [DB::raw('LENGTH(jms_gs.erp_merchant_no)'), '>', 0],
            ['gs.is_push', 1],
        ];
        $queryRes = DB::table('goods_spec as gs')->select($field)
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->where($where)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
        $erp = new ErpRequest();
        $shop_no = '33';
        //创建erp平台货品（平台货品）
        foreach ($queryRes as $value) {
            $goods_list = [
                'status' => 1,//0删除 1在架 2下架
                'platform_id' => $erp->platform_id,//平台ID
                'shop_no' => $shop_no,//店铺编号(ERP内的店铺信息中)
                'goods_id' => $value['goods_id'],//erp货品id
                'goods_no' => $value['goods_sn'],//erp货品编码
                'cid' => $value['cat_id'],//
                'goods_name' => $this->strFilter($value['goods_name']),//erp货品名称
                'price' => $value['spec_price'],//商品价格
                'stock_num' => $value['stock_num'],//商品在平台上的库存量
                'pic_url' => '',//外部系统图片url
                'spec_id' => $value['spec_sn'],//外部系统规格编码-应业务要求该字段进行标记规格码
                'spec_code' => $value['spec_id'],//外部系统货品规格id
                'spec_name' => '',//外部系统规格名称
                'spec_no' => $value['erp_merchant_no']//erp商家编码（erp规格编码）
            ];
            $pushGoodsData[] = $goods_list;
            $pushData = [
                'api_goods_info' => [
                    'platform_id' => $erp->platform_id,
                    'shop_no' => $shop_no,
                    'goods_list' => $pushGoodsData
                ]
            ];
            $erpReturn = $erp->request_method($uri = 'api_goodsspec_push.php', $pushData);
            $code = intval($erpReturn['code']);
            //如果erp平台货品创建成功则将商品的erp状态改为已推送(0，未进行erp推送 1，平台货品货品创建成功 2，货品档案创建成功)
            $spec_sn = $value['spec_sn'];
            if ($code == 0) {
                $specWhere = [
                    ['spec_sn', $spec_sn]
                ];
                $update = ['is_push' => 2];
                DB::table('goods_spec')->where($specWhere)->update($update);
            }
        }//end of foreach
        return $erpReturn;
    }


    /*
     * title：计划任务-erp库存同步
     * desc: 1、获取erp平台货品库存同步状态；
             2、更新本地库商品库存；
             3、erp平台货品库存同步状态回写（下次如果库存不发生变化，无需同步）；
             4、$shopNum-erp店铺id-07-集货街（香港仓），18-集货街（保税）
     * author：zhangdong
     * date：2018.11.16
     */
    public function sycGoodsStock($shopNum = '33')
    {
        $log = logInfo('erp/sycStock');
        $log->addInfo("库存同步开始");
        $shopName = '';
        if ($shopNum == '33') {
            $shopName = $shopNum . '-IMS平台';
        }
        $log->addInfo('Erp库存同步开始-' . $shopName);
        $erp = new ErpRequest();
        $postData = [
            'shop_no' => strval($shopNum)
        ];
        $erpReturn = $erp->request_method($uri = 'api_goods_stock_change_query.php', $postData);
//        return  $erpReturn;
        $stockChangeList = $erpReturn['stock_change_list'];
        if (empty($stockChangeList)) {
            $log->addInfo('Erp返回数据为空，没有需要同步的库存');
            return true;
        }
        $gsModel = new GoodsSpecModel();
        foreach ($stockChangeList as $k => $v) {
            $rec_id = $v['rec_id'];//Erp内平台货品表主键id
            $sync_stock = intval($v['sync_stock']);//Erp内库存
            //同步本地库
            $erp_merchant_no = '';
            //注：如果$v['spec_no']为空（$v['goods_no']不为空）则说明该商品是单sku，
            //如果$v['spec_no']不为空则说明该商品是多sku
            if (!empty($v['spec_no'])) {
                $erp_merchant_no = $v['spec_no'];
            }
            if (empty($v['spec_no']) && !empty($v['goods_no'])) {
                $erp_merchant_no = $v['goods_no'];
            }
            if (empty($erp_merchant_no)) {
                $log->addInfo('Erp商家编码为空');
                continue;
            }
            $log->addInfo('Erp商家编码:' . $erp_merchant_no . '/Erp库存:' . $sync_stock);
            //查询商品信息
            $type = 2;
            $local_goods_info = $gsModel->getSpecInfo($erp_merchant_no, $type);
            if (is_null($local_goods_info)) {
                $log->addInfo('Erp商家编码为:' . $erp_merchant_no . '的商品平台未找到');
                continue;
            }
//            dd($local_goods_info);
            $spec_sn = $local_goods_info->spec_sn;//平台规格编码（全局唯一）
            $stock = intval($local_goods_info->stock_num);
            $lock_stock_num = intval($local_goods_info->lock_stock_num);
            $totalStockNum = $stock + $lock_stock_num;//平台总库存
            $log->addInfo('商品规格码:' . $spec_sn . '/总库存:' . $totalStockNum);
            //如果erp返回的库存不等于平台中商品库存和锁库库存的和才更新
            $stock_change_count = $v['stock_change_count'];
            //回写库存
            $syncInfo[] = [
                'rec_id' => $rec_id,
                'sync_stock' => $sync_stock,
                'stock_change_count' => $stock_change_count,
            ];
            if ($sync_stock === $totalStockNum) {
                $log->addInfo('Erp库存和集货街库存相等');
                $stock_push = [
                    'stock_sync_list' => $syncInfo
                ];
                //状态回写
                $writeBackRes = $erp->request_method($uri = 'api_goods_stock_change_ack.php', $stock_push);
                $log->addInfo('Erp回写结果：' . json_encode($writeBackRes, JSON_UNESCAPED_UNICODE));
                continue;
            }
            $diffNum = $sync_stock - $totalStockNum;
            $log->addInfo('erp-平台库存差异值' . $diffNum);
            //更新库存
            $updateRes = $gsModel->updateGoodsStock($spec_sn, $sync_stock);
            //如果更新不成功则不回传
            if (!$updateRes) {
                $log->addInfo('集货街库存更新失败');
                continue;
            }
            $newGoodsInfo = $gsModel->getSpecInfo($spec_sn);
            $curStockNum = intval($newGoodsInfo->stock_num);
            $log->addInfo('库存更新成功后平台库存（不包含锁库库存）:' . $curStockNum);
            $log->addInfo('集货街库存更新成功');
            //回写库存
            $stock_push = [
                'stock_sync_list' => $syncInfo
            ];
            //状态回写
            $writeBackRes = $erp->request_method($uri = 'api_goods_stock_change_ack.php', $stock_push);
            $log->addInfo('Erp回写结果：' . json_encode($writeBackRes, JSON_UNESCAPED_UNICODE));

        }//end of foreach
        $log->addInfo('Erp库存同步结束');
        return true;

    }//end of sycGoodsStock


    /*
    * description：过滤参数
    * author：zhangdong
    * date：2018.11.14
 	*/
    private function strFilter($str)
    {
        $str = str_replace('`', '', $str);
        $str = str_replace('·', '', $str);
        $str = str_replace('~', '', $str);
        $str = str_replace('!', '', $str);
        $str = str_replace('！', '', $str);
        //$str = str_replace('@', '', $str);
        $str = str_replace('#', '', $str);
        $str = str_replace('$', '', $str);
        $str = str_replace('￥', '', $str);
        $str = str_replace('%', '', $str);
        $str = str_replace('^', '', $str);
        $str = str_replace('……', '', $str);
        $str = str_replace('&', '', $str);
        $str = str_replace('*', '', $str);
        $str = str_replace('(', '', $str);
        $str = str_replace(')', '', $str);
        $str = str_replace('（', '', $str);
        $str = str_replace('）', '', $str);
        $str = str_replace('-', '', $str);
        $str = str_replace('_', '', $str);
        $str = str_replace('——', '', $str);
        $str = str_replace('+', '', $str);
        $str = str_replace('=', '', $str);
        $str = str_replace('|', '', $str);
        $str = str_replace('\\', '', $str);
        $str = str_replace('[', '', $str);
        $str = str_replace(']', '', $str);
        $str = str_replace('【', '', $str);
        $str = str_replace('】', '', $str);
        $str = str_replace('{', '', $str);
        $str = str_replace('}', '', $str);
        $str = str_replace(';', '', $str);
        $str = str_replace('；', '', $str);
        $str = str_replace(':', '', $str);
        $str = str_replace('：', '', $str);
        $str = str_replace('\'', '', $str);
        $str = str_replace('"', '', $str);
        $str = str_replace('“', '', $str);
        $str = str_replace('”', '', $str);
        $str = str_replace(',', '', $str);
        $str = str_replace('，', '', $str);
        $str = str_replace('<', '', $str);
        $str = str_replace('>', '', $str);
        $str = str_replace('《', '', $str);
        $str = str_replace('》', '', $str);
        $str = str_replace('.', '', $str);
        $str = str_replace('。', '', $str);
        $str = str_replace('/', '', $str);
        $str = str_replace('、', '', $str);
        $str = str_replace('?', '', $str);
        $str = str_replace('？', '', $str);
        $str = str_replace(["\r\n", "\r", "\n"], '', $str);
        return trim($str);
    }

    /*
    * description：现货单-erp订单推送 $shopNum-erp店铺id-33-MIS平台
    * author：zhangdong
    * date：2018.12.14
 	*/
    public function spot_order_push($spot_order_sn, $shopNum = 33)
    {
        $log = logInfo('erp/singleOrderPush');
        $shopName = '';
        if ($shopNum == '33') {
            $shopName = $shopNum . 'MIS平台';
        }
        $log->addInfo($shopName . '- Erp订单推送开始');
        if (empty($spot_order_sn)) {
            $log->addInfo('现货单号为空');
            return true;
        }
        $erp = new ErpRequest();
        //查询现货单订单信息
        $spotOrderModel = new SpotOrderModel();
        $orderInfo = $spotOrderModel->getSpotOrderInfo($spot_order_sn);
        //查询现货单商品信息
        $spotGoodsModel = new SpotGoodsModel();
        $orderGoods = $spotGoodsModel->getSpotGoodsInfo($spot_order_sn);
        //组装订单数据和订单商品数据
        $orderPushData = $this->createPushData($orderInfo, $orderGoods);
        $pushData = [
            'shop_no' => strval($shopNum),
            'trade_list' => $orderPushData
        ];
        //dd($pushData);
        if (empty($orderPushData)) $log->addInfo('没有可推送订单');
        $updateRes = false;
        if (!empty($orderPushData)) {
            $url = "trade_push.php";
            @$erpReturn = $erp->request_method($url, $pushData);
            $log->addInfo(
                'erp推送数据：' .
                json_encode($pushData, JSON_UNESCAPED_UNICODE)
            );
            //dd($erpReturn);
            $log->addInfo(
                '订单推送结果：' .
                json_encode($erpReturn, JSON_UNESCAPED_UNICODE)
            );
            if ($erpReturn['code'] !== 0) $log->addInfo('订单推送失败');
            if ($erpReturn['code'] === 0) { //请求成功后将订单的状态改为已推送
                $log->addInfo('订单' . $spot_order_sn . '推送成功');
                $updateRes = true;
                foreach ($pushData['trade_list'] as $val) {
                    //订单编号
                    $spot_order_sn = trim($val['tid']);
                    //将订单改为已推送状态
                    $is_push_erp = 2;
                    $spotOrderModel->updateIsPush($spot_order_sn, $is_push_erp);
                }
            }
        }
        $log->addInfo('Erp订单推送结束');
        return $updateRes;
    }


    /*
     * description：组装订单推送信息
     * author：zhangdong
     * date：2018.12.14
     */
    public function createPushData($orderInfo, $orderGoods)
    {
        if (empty($orderInfo) || empty($orderGoods)) return false;
        //由于要计算商品总价，所以先处理商品信息
        $orderStatus = intval($orderInfo->order_status);
        $spot_order_sn = trim($orderInfo->spot_order_sn);
        $tradeStatus = $this->getTradeStatus($orderStatus);
        $orderAmount = 0;
        foreach ($orderGoods as $key => $value) {
            $num = intval($value->goods_number);
            $spec_price = trim($value->spec_price);
            $orderGoods[$key]->oid = $spot_order_sn . "_$key";
            $orderGoods[$key]->num = $num;
            $orderGoods[$key]->price = $spec_price;
            $orderGoods[$key]->status = $tradeStatus;
            $orderGoods[$key]->refund_status = 0;
            $orderGoods[$key]->goods_no = trim($value->goods_sn);
            $orderGoods[$key]->spec_id = trim($value->spec_id);
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
            'tid' => trim($spot_order_sn),
            'paid' => $orderAmount,
            'trade_status' => $tradeStatus,
            //付款状态:0未付款1部分付款2已付款
            'pay_status' => 0,
            //发货条件:1款到发货2货到付款(包含部分货到付款)
            'delivery_term' => 1,
            'trade_time' => trim($orderInfo->create_time),
            'pay_id' => trim($orderInfo->spot_order_sn),
            'logistics_type' => -1,
            'invoice_type' => 1,
            'post_amount' => 18,
            'cod_amount' => 0,
            'ext_cod_fee' => 0,
            'seller_memo' => 'MIS测试单，请忽略',
            'order_list' => $orderGoods,
        ];
        return $orderData;
//        return json_encode($orderData, JSON_UNESCAPED_UNICODE);

    }

    /*
    * description：根据系统订单状态获取erp需要的订单状态
    * author：zhangdong
    * date：2018.12.14
 	*/
    private function getTradeStatus($systemStatus)
    {
        //现货单状态：1,待确认 2，待发货 3，已发货 4，已完成 5，已关闭
        switch ($systemStatus) {
            case 1:
                $trade_status = 10;
                break;//等待买家付款
            case 2:
                $trade_status = 30;
                break;//买家已付款，等待卖家发货
            case 3:
                $trade_status = 50;
                break;//卖家已发货
            case 4:
                $trade_status = 70;
                break;//交易成功
            case 6:
                $trade_status = 90;
                break;// 交易已取消
            default:
                $trade_status = 10;
                break;
        }
        return $trade_status;
    }

    /*
    * description：发货单-erp订单推送 $shopNum-erp店铺id-33-MIS平台
    * author：zongxing
    * date：2018.12.18
     */
    public function deliver_order_push($deliver_order_sn, $shopNum = 33)
    {
        $log = logInfo('deliverOrderPush');
        $shopName = '';
        if ($shopNum == '33') {
            $shopName = $shopNum . 'MIS平台';
        }
        $log->addInfo($shopName . '- Erp订单推送开始');
        $erp = new ErpRequest();
        //查询发货单订单信息
        $deliver_order_model = new DeliverOrderModel();
        $deliver_order_info = $deliver_order_model->getDeliverOrderInfo($deliver_order_sn);
        //查询发货单商品信息
        $deliver_goods_model = new DeliverGoodsModel();
        $deliver_goods_info = $deliver_goods_model->getDeliverGoodsInfo($deliver_order_sn);
        //组装发货单数据和发货单商品数据
        $orderPushData = $this->createPushDeliverData($deliver_order_info, $deliver_goods_info);
        $pushData = [
            'shop_no' => strval($shopNum),
            'trade_list' => $orderPushData
        ];
        if (empty($orderPushData)) $log->addInfo('没有可推送订单');
        $updateRes = false;
        if (!empty($orderPushData)) {
            $url = "trade_push.php";
            @$erpReturn = $erp->request_method($url, $pushData);
            $log->addInfo(
                'erp推送数据：' .
                json_encode($pushData, JSON_UNESCAPED_UNICODE)
            );
            $log->addInfo(
                '订单推送结果：' .
                json_encode($erpReturn, JSON_UNESCAPED_UNICODE)
            );
            if ($erpReturn['code'] !== 0) $log->addInfo('订单推送失败');
            if ($erpReturn['code'] === 0) { //请求成功后将订单的状态改为已推送
                foreach ($pushData['trade_list'] as $val) {
                    //订单编号
                    $deliver_order_sn = trim($val['tid']);
                    //将订单改为已推送状态
                    $is_push_erp = 2;
                    $updateRes = $deliver_order_model->updateIsPush($deliver_order_sn, $is_push_erp);
                    if ($updateRes) {
                        $log->addInfo('订单' . $deliver_order_sn . '推送成功');
                    }
                }
            }
        }
        $log->addInfo('Erp订单推送结束');
        return $updateRes;
    }

    /*
     * description：组装发货单推送信息
     * author：zongxing
     * date：2018.12.18
     */
    public function createPushDeliverData($deliver_order_info, $deliver_goods_info)
    {
        if (empty($deliver_order_info) || empty($deliver_goods_info)) return false;
        //由于要计算商品总价，所以先处理商品信息
        $deliver_order_status = intval($deliver_order_info['order_sta']);
        $deliver_order_sn = trim($deliver_order_info['deliver_order_sn']);
        $tradeStatus = $this->getTradeStatus($deliver_order_status);
        $orderAmount = 0;
        foreach ($deliver_goods_info as $k => $v) {
            $num = intval($v['goods_number']);
            $spec_price = trim($v['spec_price']);
            $deliver_goods_info[$k]['oid'] = $deliver_order_sn . "_$k";
            $deliver_goods_info[$k]['num'] = $num;
            $deliver_goods_info[$k]['price'] = $spec_price;
            $deliver_goods_info[$k]['status'] = $tradeStatus;
            $deliver_goods_info[$k]['refund_status'] = 0;
            $deliver_goods_info[$k]['goods_no'] = trim($v['goods_sn']);
            $deliver_goods_info[$k]['spec_id'] = trim($v['spec_id']);
            $deliver_goods_info[$k]['spec_no'] = trim($v['erp_merchant_no']);
            $deliver_goods_info[$k]['goods_name'] = trim($v['goods_name']);
            $deliver_goods_info[$k]['adjust_amount'] = 0;
            $deliver_goods_info[$k]['discount'] = 0;
            $deliver_goods_info[$k]['share_discount'] = 0;
            //计算商品价格
            $orderAmount += $spec_price * $num;
        }
        $orderData[] = [
            'tid' => trim($deliver_order_sn),
            'paid' => $orderAmount,
            'trade_status' => $tradeStatus,
            //付款状态:0未付款1部分付款2已付款
            'pay_status' => 2,
            //发货条件:1款到发货2货到付款(包含部分货到付款)
            'delivery_term' => 1,
            'trade_time' => trim($deliver_order_info['create_time']),
            'pay_id' => trim($deliver_order_info['deliver_order_sn']),
            'logistics_type' => -1,
            'invoice_type' => 1,
            'post_amount' => 0,
            'cod_amount' => 0,
            'ext_cod_fee' => 0,
            'seller_memo' => 'MIS测试单，请忽略',
            'order_list' => $deliver_goods_info,
        ];
        return $orderData;
        // return json_encode($orderData, JSON_UNESCAPED_UNICODE);
    }

    /*
    * description：采购开单-erp采购开单推送 $shopNum-erp店铺id-33-MIS平台
    * author：zongxing
    * date：2019.01.11
    */
    public function batch_order_push($batch_info, $shopNum = 33)
    {
        $log = logInfo('batchPush');
        $shopName = '';
        if ($shopNum == '33') {
            $shopName = $shopNum . 'MIS平台';
        }
        $log->addInfo($shopName . '- Erp采购开单推送开始');
        $erp = new ErpRequest();

        //获取批次信息
        $real_purchase_sn = trim($batch_info['real_purchase_sn']);
        $rp_model = new RealPurchaseModel();
        $real_purchase_info = $rp_model->getBatchInfo($real_purchase_sn);
        //获取批次单中商品信息
        $rpd_model = new RealPurchaseDetailModel();
        $real_purchase_detail = $rpd_model->getBatchGoodsDetail($batch_info);
        $details_list = [];
        foreach ($real_purchase_detail as $k => $v) {
            $allot_num = intval($v['allot_num']);
            if ($allot_num > 0) {
                $tmp_arr['spec_no'] = $v['erp_merchant_no'];
                $tmp_arr['num'] = $v['allot_num'];
                $tmp_arr['price'] = $v['spec_price'];
                $details_list[] = $tmp_arr;
            }
        }

        $rand_num = rand(100,999);
        $purchase_info = [
            'provider_no' => $real_purchase_info['supplier_id'],
            'warehouse_no' => $real_purchase_info['store_number'],
            'outer_no' => $real_purchase_sn.$rand_num,
            'details_list' => $details_list,
            //'is_use_outer_no' => 1,//是否将外部订单号作为erp采购单号
            'is_check' => 1,//是否审核
        ];

        $pushData = [
            'shop_no' => strval($shopNum),
            'purchase_info' => $purchase_info
        ];

        if (empty($real_purchase_info)) $log->addInfo('没有可推送采购开单');
        $updateRes = false;
        if (!empty($real_purchase_info)) {
            $url = "purchase_order_push.php";
            @$erpReturn = $erp->request_method($url, $pushData);

            $log->addInfo(
                'erp推送数据：' .
                json_encode($pushData, JSON_UNESCAPED_UNICODE)
            );
            $log->addInfo(
                '采购开单推送结果：' .
                json_encode($erpReturn, JSON_UNESCAPED_UNICODE)
            );
            if ($erpReturn['code'] !== 0) $log->addInfo('采购开单推送失败');
            if ($erpReturn['code'] === 0) { //请求成功后将订单的状态改为已推送
                $updateRes = true;
                $log->addInfo('采购开单' . $real_purchase_sn . '推送成功');
            }
        }
        $log->addInfo('Erp采购开单推送结束');
        return $updateRes;
    }

    /*
    * description：采购开单-erp入库开单推送 $shopNum-erp店铺id-33-MIS平台
    * author：zongxing
    * date：2019.01.11
    */
    public function batch_store_push($reqParams, $shopNum = 33)
    {
        $real_purchase_sn = $reqParams['real_purchase_sn'];//实采单号单号
        $log = logInfo('batchPush');
        $shopName = '';
        if ($shopNum == '33') {
            $shopName = $shopNum . 'MIS平台';
        }
        $log->addInfo($shopName . '- Erp入库开单推送开始');
        $erp = new ErpRequest();

        //获取批次信息
        $rp_model = new RealPurchaseModel();
        $real_purchase_info = $rp_model->getBatchInfo($real_purchase_sn);

        $outer_no = $real_purchase_info['real_purchase_sn'];
        $billing_time = $real_purchase_info['billing_time'];
        $billing_time = strtotime($billing_time);

        $start_time = strtotime('-5 Minute', $billing_time);
        $start_time = date('Y-m-d H:i:s', $start_time);
        $end_time = date('Y-m-d H:i:s', time());
        $pushData = [
            'shop_no' => strval($shopNum),
            'outer_no' => $outer_no,
            'start_time' => $start_time,
            'end_time' => $end_time
        ];
        if (empty($real_purchase_info)) $log->addInfo('没有可推送采购开单');
        $updateRes = false;
        if (!empty($real_purchase_info)) {
            $url = "purchase_order_query.php";
            @$erpOrderInfo = $erp->request_query_order($url, $pushData);
            if (!empty($erpOrderInfo['purchase_list'])) {
                $purchase_list = $erpOrderInfo['purchase_list'][0];
                $details_list = [];
                foreach ($erpOrderInfo['purchase_list'][0]['details_list'] as $k => $v) {
                    $tmp_arr['spec_no'] = $v['spec_no'];
                    $tmp_arr['stockin_num'] = $v['num'];
                    $tmp_arr['stockin_price'] = $v['price'];
                    $details_list[] = $tmp_arr;
                }

                $purchase_info = [
                    'purchase_no' => $purchase_list['purchase_no'],
                    'warehouse_no' => $purchase_list['warehouse_no'],
                    'outer_no' => $purchase_list['outer_no'],
                    'details_list' => $details_list,
                ];
                $pushData = [
                    'shop_no' => strval($shopNum),
                    'purchase_info' => $purchase_info
                ];

                $url = "stockin_purchase_push.php";
                @$erpReturn = $erp->request_method($url, $pushData);
                $log->addInfo(
                    'erp推送数据：' .
                    json_encode($pushData, JSON_UNESCAPED_UNICODE)
                );
                $log->addInfo(
                    '采购入库开单推送结果：' .
                    json_encode($erpReturn, JSON_UNESCAPED_UNICODE)
                );
                if ($erpReturn['code'] !== 0) $log->addInfo('采购入库开单推送失败');
                if ($erpReturn['code'] === 0) { //请求成功后将订单的状态改为已推送
                    $updateRes = true;
                    $log->addInfo('采购入库开单' . $real_purchase_sn . '推送成功');
                }
            }
        }
        $log->addInfo('Erp采购入库开单推送结束');
        return $updateRes;
    }

    /**
     * description:erp库存查询
     * editor:zhangdong
     * date : 2019.01.02
     * @param $start_time (按最后修改时间增量获取数据)
     * @param $end_time (按最后修改时间增量获取数据)
     * @param $warehouseNo (仓库id)
     */
    public function erpStockQuery($start_time, $end_time, $warehouseNo = 0)
    {
        if (empty($start_time) || empty($end_time)) {
            return false;
        }
        $log = logInfo('erp/stockQuery');
        $log->addInfo('-Erp库存查询开始');
        $warehouseNo = intval($warehouseNo) == 0 ? '' : intval($warehouseNo);
        $start_time = date('Y-m-d H:i:s', strtotime(trim($start_time)));
        $end_time = date('Y-m-d H:i:s', strtotime(trim($end_time)));
        $requestParams = [
            'warehouse_no' => strval($warehouseNo),
            'start_time' => strval($start_time),
            'end_time' => strval($end_time),
            'page_size' => 1,
        ];
        $uri = 'stock_query.php';
        //第一次请求获取总条数，得到页数等信息
        $erpReturn = $this->erpRequest->request_method($uri, $requestParams);
        if (intval($erpReturn['code'] !== 0)) {
            $log->addInfo(json_encode($erpReturn, JSON_UNESCAPED_UNICODE));
            return false;
        }
        //总条数
        $totalNum = intval($erpReturn['total_count']);
        //总页数 = 总条数/分页大小
        $page_size = 50;
        $totalPage = intval(ceil($totalNum / $page_size));
        //第二次请求-分页请求,第0页已经请求完成，直接从第1页开始
        for ($page_no = 0; $page_no < $totalPage; $page_no++) {
            $requestParams['page_no'] = $page_no;
            $requestParams['page_size'] = $page_size;
            $erpReturn = $this->erpRequest->request_method($uri, $requestParams);
            if (intval($erpReturn['code'] !== 0) || empty($erpReturn['stocks'])) {
                $log->addInfo(json_encode($erpReturn, JSON_UNESCAPED_UNICODE));
                continue;
            }
            //根据返回仓库信息循环更新平台商品数据
            $stocksData = $erpReturn['stocks'];
            $this->egsModel->loopUpdateGoodsData($stocksData);
        }
        $log->addInfo('-Erp库存查询结束');
        return true;
    }

    /*
   * description：erp订单-取消erp订单-专用 该方法有危险，请慎用 $shopNum-erp店铺id-33-MIS平台
   * author：zhangdong
   * date：2019.01.29
    */
    public function cancel_erp_order($trade_no, $shopNum = 33)
    {
        $log = logInfo('erp/erpOrderPush');
        $shopName = '';
        if ($shopNum == '33') {
            $shopName = $shopNum . 'MIS平台';
        }
        $log->addInfo($shopName . '- Erp订单推送开始');
        if (empty($trade_no)) {
            $log->addInfo('erp订单编号为空');
            return true;
        }
        $erp = new ErpRequest();
        $oiModel = new OrderInfoModel();
        //先将erp订单状态改为90已取消然后再查订单信息
        $status = 90;
        $oiModel->updateErpOrderStatus($trade_no, $status);
        //查询erp订单信息
        $orderInfo = $oiModel->getErpOrderInfo($trade_no);
        //查询erp订单商品信息
        $orderGoods = $oiModel->getErpGoodsInfo($trade_no);
        //组装订单数据和订单商品数据
        $orderPushData = $oiModel->createErpPushData($orderInfo, $orderGoods);
        $pushData = [
            'shop_no' => strval($shopNum),
            'trade_list' => $orderPushData
        ];
        if (empty($orderPushData)) {
            $log->addInfo('没有可推送订单');
        }
        $updateRes = 'failure';
        if (!empty($orderPushData)) {
            $url = "trade_push.php";
            @$erpReturn = $erp->request_method($url, $pushData);
            $log->addInfo(
                'erp推送数据：' .
                json_encode($pushData, JSON_UNESCAPED_UNICODE)
            );
            //dd($erpReturn);
            $log->addInfo(
                '订单推送结果：' .
                json_encode($erpReturn, JSON_UNESCAPED_UNICODE)
            );
            if ($erpReturn['code'] !== 0) $log->addInfo('订单推送失败');
            if ($erpReturn['code'] === 0) { //请求成功后将订单的状态改为已推送
                $log->addInfo('订单' . $trade_no . '推送成功');
                $updateRes = 'success';
            }
        }
        $log->addInfo('Erp订单推送结束');
        return $updateRes;
    }


}//end of class
