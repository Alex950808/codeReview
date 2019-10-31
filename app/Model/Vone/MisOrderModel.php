<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JWTAuth;

//引入时间处理包 add by zhangdong on the 2018.12.06
use Carbon\Carbon;

class MisOrderModel extends Model
{
    protected $table = 'mis_order as mo';

    //是否分单
    public $is_split = [
        '0' => '未拆分',
        '1' => '已拆分',
    ];
    //订单状态
    public $status = [
        '1' => '待挂靠',
        '2' => '待拆分',
        '3' => '已结束',
    ];

    private $field = [
        'mo.mis_order_sn', 'mo.status', 'mo.is_offer','mo.is_advance',
        'mo.sale_user_id', 'mo.depart_id', 'mo.create_time',
    ];
    //是否报价
    public $is_offer = [
        '0' => '未报价',
        '1' => '已报价',
    ];
    public $int_offer = [
        'NO_OFFER' => 0,
        'YET_OFFER' => 1,
    ];
    //是否已预判
    public $is_advance = [
        '0' => '未预判',
        '1' => '已预判',
    ];
    public $int_advance = [
        'NO_ADVANCE' => 0,
        'YET_ADVANCE' => 1,
    ];

    //订单类型
    public $order_type = [
        '1' => '线上',
        '2' => '线下',
    ];
    public $int_order_type = [
        'ONLINE' => 1,
        'OFFLINE' => 2,
    ];

    //操作模块id 1,物流模块；2,采购模块；3,商品模块；4,任务模块；5,权限模块；6,销售模块；7,财务模块
    private static $module_id = 6;


    /**
     * description:订单列表
     * editor:zhangdong
     * date:2018.12.08
     */
    public function getMisOrderList($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $field = [
            'mo.mis_order_sn', 'mo.sale_user_id', 'mo.depart_id', 'mo.status',
            'mo.create_time', 'su.user_name', 'd.de_name', 'mo.is_split', 'mo.is_offer',
            'mo.is_advance','mo.order_type','mo.mark',
        ];
        $mog_on = [
            ['mog.mis_order_sn', '=', 'mo.mis_order_sn'],
        ];
        $d_on = [
            ['d.department_id', '=', 'mo.depart_id']
        ];
        $su_on = [
            ['su.id', '=', 'mo.sale_user_id']
        ];
        $queryRes = DB::table('mis_order as mo')->select($field)
            ->leftJoin('mis_order_goods as mog', $mog_on)
            ->leftJoin('department as d', $d_on)
            ->leftJoin('sale_user as su', $su_on)
            ->where($where)->groupBy('mo.mis_order_sn')->orderBy('mo.create_time','desc')
            ->paginate($pageSize);
        //如果查询没有结果则直接返回
        if ($queryRes->count() == 0) return $queryRes;
        $mogModel = new MisOrderGoodsModel();
        $ongModel = new OrdNewGoodsModel();
        foreach ($queryRes as $key => $value) {
            //根据mis_order_sn查询商品信息
            $mis_order_sn = trim($value->mis_order_sn);
            //系统订单状态
            $queryRes[$key]->is_split = $this->is_split[intval($value->is_split)];
            $queryRes[$key]->status = $this->status[intval($value->status)];
            $queryRes[$key]->offer_desc = $this->is_offer[intval($value->is_offer)];
            $queryRes[$key]->advance_desc = $this->is_advance[intval($value->is_advance)];
            $goodsData = $mogModel->getMisOrderGoods($mis_order_sn, $where);
            $queryRes[$key]->goods_data = $goodsData;
            $queryRes[$key]->type_desc = $this->order_type[intval($value->order_type)];
            $queryRes[$key]->goods_num = $mogModel->countMisGoodsNum($mis_order_sn);
            $isReplenish = $ongModel->checkIsReplenish($mis_order_sn);
            $queryRes[$key]->isReplenish = $isReplenish;
        }
        return $queryRes;
    }


    /**
     * description:查询订单-组装查询条件
     * editor:zhangdong
     * date:2018.12.08
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
            ['mo.create_time', '>=', $start_time],
            ['mo.create_time', '<=', $end_time],
        ];
        //MIS订单编号
        if (isset($reqParams['mis_order_sn'])) {
            $where[] = [
                'mo.mis_order_sn', trim($reqParams['mis_order_sn'])
            ];
        }
        //订单状态 1,待挂靠 2,待拆分 3,已结束
        if (isset($reqParams['status'])) {
            $where[] = [
                'mo.status', trim($reqParams['status'])
            ];
        }
        //销售用户  add 2019.04.28 zhangdong
        if (isset($reqParams['sale_user_id'])) {
            $where[] = [
                'mo.sale_user_id', trim($reqParams['sale_user_id'])
            ];
        }
        //商品名称
        if (isset($reqParams['goods_name'])) {
            $where[] = [
                'mog.goods_name', 'like', '%' . trim($reqParams['goods_name'] . '%')
            ];
        }
        //商家编码
        if (isset($reqParams['erp_merchant_no'])) {
            $where[] = [
                'mog.erp_merchant_no', trim($reqParams['erp_merchant_no'])
            ];
        }
        //规格码
        if (isset($reqParams['spec_sn'])) {
            $where[] = [
                'mog.spec_sn', trim($reqParams['spec_sn'])
            ];
        }
        return $where;
    }


    /**
     * description:根据上传数据组装订单数据
     * editor:zhangdong
     * date:2018.12.08
     */
    public function createOrderData(
        $goodsInfo,
        $depart_id,
        $sale_user_id,
        $mis_order_sn = '',
        $orderType = 1,
        $mark = ''
    ) {
        //mis订单商品信息
        $mis_order_goods = [];
        //生成MIS订单号
        //总单新品补单加入后做出调整 2019.04.18 zhangdong
        if (empty($mis_order_sn)) {
            $mis_order_sn = $this->generalMisOrdSn();
        }
        $suaModel = new SaleUserAccountModel();
        //通过销售用户id获取对应销售账户
        $sugMsg = $suaModel->listSaleAccounts($sale_user_id);
        $arrSugMsg = objectToArray($sugMsg);
        foreach ($goodsInfo as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            //需求量
            $goods_num = intval($value->goods_num);
            $stock_num = intval($value->stock_num);
            //待采量 = 需求量 - 库存
            //如果当前库存大于需求量则无需采购
            $brand_id = strval($value->brand_id);
            //根据品牌id查询出对应的销售账号
            $searchRes = twoArrayFuzzySearch($arrSugMsg, 'brand_id', $brand_id);
            $saleUserAccount = '';
            if (count($searchRes) > 0) {
                $saleUserAccount = $searchRes[0]['user_name'];
            }
            $mis_order_goods[] = [
                'mis_order_sn' => $mis_order_sn,
                'goods_name' => trim($value->goods_name),
                'spec_sn' => $spec_sn,
                'erp_merchant_no' => trim($value->erp_merchant_no),
                'stock_num' => $stock_num,
                'goods_number' => $goods_num,
                'wait_buy_num' => intval($value->wait_buy_num),
                'spec_price' => trim($value->spec_price),
                'sale_discount' => floatval($value->sale_discount),
                'exw_discount' => floatval($value->exw_discount),
                'entrust_time' => trim($value->entrust_time),
                'platform_barcode' => trim($value->platform_barcode),
                'sale_user_account' => $saleUserAccount,
            ];

        }
        $misOrderData = [
            'mis_order_sn' => $mis_order_sn,
            'sale_user_id' => $sale_user_id,
            'order_type' => $orderType,
            'mark' => $mark,
            'depart_id' => $depart_id,
        ];
        $misOrderData = [
            'orderData' => $misOrderData,
            'orderGoods' => $mis_order_goods,
        ];
        return $misOrderData;
    }//end of function

    /**
     * description:根据上传数据组装订单数据
     * editor:zhangdong
     * date:2018.12.08
     */
    public function createOrderData_stop($goodsInfo, $depart_id, $sale_user_id, $mis_order_sn = '')
    {
        //$sugModel = new SaleUserGoodsModel();
        //mis订单商品信息
        $mis_order_goods = [];
        //生成MIS订单号
        //总单新品补单加入后做出调整 2019.04.18 zhangdong
        if (empty($mis_order_sn)) {
            $mis_order_sn = $this->generalMisOrdSn();
        }
        //获取重价系数-默认为香港仓
        //$esModel = new ErpStorehouseModel();
        //$esInfo = $esModel->getErpStoreInfo(STORE_FACTOR);
        //$storeFactor = trim($esInfo->store_factor);
        //$gsModel = new GoodsSpecModel();
        $suaModel = new SaleUserAccountModel();
        //通过销售用户id获取对应销售账户
        $sugMsg = $suaModel->listSaleAccounts($sale_user_id);
        $arrSugMsg = objectToArray($sugMsg);
        foreach ($goodsInfo as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            //根据销售用户，部门id和规格码获取销售折扣-由于此处程序在实际中几乎没用，故暂时停用 zhangdong 20190527
            /*$userGoodsInfo = $sugModel->getSugInfo(
                $depart_id, $sale_user_id, $spec_sn, 2
            );
            $sale_discount = isset($userGoodsInfo->sale_discount) ?
                trim($userGoodsInfo->sale_discount) : '';
            if($sale_discount <= 0){
                $sale_discount = $gsModel->calculateSaleDiscount($spec_sn, $storeFactor);
            }*/
            //需求量
            $goods_num = intval($value->goods_num);
            $stock_num = intval($value->stock_num);
            //待采量 = 需求量 - 库存
            //$difference = intval($goods_num - $stock_num);
            //如果当前库存大于需求量则无需采购
            //$wait_buy_num = $difference > 0 ? $difference : 0;
            $brand_id = strval($value->brand_id);
            //根据品牌id查询出对应的销售账号
            $searchRes = twoArrayFuzzySearch($arrSugMsg, 'brand_id', $brand_id);
            $saleUserAccount = '';
            if (count($searchRes) > 0) {
                $saleUserAccount = $searchRes[0]['user_name'];
            }
            $mis_order_goods[] = [
                'mis_order_sn' => $mis_order_sn,
                'goods_name' => trim($value->goods_name),
                'spec_sn' => $spec_sn,
                'erp_merchant_no' => trim($value->erp_merchant_no),
                'stock_num' => $stock_num,
                'goods_number' => $goods_num,
                'wait_buy_num' => intval($value->wait_buy_num),
                'spec_price' => trim($value->spec_price),
                'sale_discount' => floatval($value->sale_discount),
                'entrust_time' => trim($value->entrust_time),
                'sale_user_account' => $saleUserAccount,
            ];

        }
        $misOrderData = [
            'mis_order_sn' => $mis_order_sn,
            'sale_user_id' => $sale_user_id,
            'depart_id' => $depart_id,
        ];
        $misOrderData = [
            'orderData' => $misOrderData,
            'orderGoods' => $mis_order_goods,
        ];
        return $misOrderData;
    }//end of function

    /**
     * description:生成订单号
     * editor:zhangdong
     * date : 2018.12.08
     * return String
     */
    private function generalMisOrdSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $mis_order_sn = 'MIS' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table('mis_order')
                ->where([
                    ['mis_order_sn', '=', $mis_order_sn]
                ])->count();
        } while ($count);
        return $mis_order_sn;
    }


    /**
     * description:保存订单信息
     * editor:zhangdong
     * date : 2018.12.08
     * return Boolean
     */
    public function saveOrderData(Array $orderData, array $arrNewGoods = [])
    {
        $misOrderData = $orderData['orderData'];
        $misGoodsData = $orderData['orderGoods'];
        if (count($misOrderData) == 0 || count($misGoodsData) == 0 ) {
           return false;
        }
        $saveRes = DB::transaction(function () use ($misOrderData, $misGoodsData, $arrNewGoods) {
            //订单表和订单商品表有外键关系，所以必须先写订单表后写订单商品表
            //订单基本数据保存
            DB::table("mis_order")->insert($misOrderData);
            //订单商品数据保存
           $insertRes =  DB::table("mis_order_goods")->insert($misGoodsData);
            //新品保存
            if (count($arrNewGoods) > 0) {
                $ongModel = new OrdNewGoodsModel();
                $insertRes = $ongModel->insertData($arrNewGoods);
            }
            return $insertRes;
        });
        return $saveRes;
    }

    /**
     * description:获取订单商品信息
     * editor:zhangdong
     * date : 2018.12.07
     * @return object
     */
    public function getOrderGoodsInfo($misOrderSn, $reqParams = '')
    {
        //创建总单详情筛选条件
        if (!empty($reqParams)) {
            $where = $this->createWhere($reqParams);
        }
        $arrField = [
            'b.name as brand_name', 'b.brand_id', 'mog.goods_name', 'mog.spec_price',
            'gs.spec_weight', 'gs.gold_discount', 'gs.black_discount', 'mog.exw_discount',
            'gs.foreign_discount', 'gs.spec_sn', 'gs.erp_merchant_no', 'mog.sale_discount',
            'gs.stock_num as gStockNum', 'mog.goods_number', 'mog.wait_buy_num','mog.entrust_time',
            'mog.sale_user_account','mog.stock_num','gs.lock_stock_num','mog.is_modify',
            DB::raw('(CAST(jms_mog.goods_number as signed) - CAST(jms_gs.stock_num as signed)) as buy_num'),
            DB::raw('SUBSTR(sub_order_sn,2) as sub_order_sn'),'mog.new_spec_price','gs.erp_ref_no',
            'gs.erp_prd_no','gs.is_suit','gs.suit_sn','gs.suit_price','gs.is_search','mog.platform_barcode',
            'gs.estimate_weight',
        ];
        $where[] = ['mog.mis_order_sn', '=', $misOrderSn];
        $goodsBaseInfo = DB::table('mis_order_goods AS mog')->select($arrField)
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'mog.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->where($where)->get();
        return $goodsBaseInfo;
    }


    /**
     * description:根据订单号获取订单基本信息
     * editor:zhangdong
     * date : 2018.12.07
     * @param $misOrderSn (订单号)
     * @return object
     */
    public function getOrderInfo($misOrderSn = '')
    {
        $misOrderSn = trim($misOrderSn);
        $addField = ['su.user_name', 'd.de_name'];
        $this->field = array_merge($this->field, $addField);
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->leftJoin('department as d', 'd.department_id', '=', 'mo.depart_id')
            ->where($where)->get();
        foreach ($queryRes as $key => $value) {
            $queryRes[$key]->status_desc = $this->status[intval($value->status)];
            $queryRes[$key]->offer_desc = $this->is_offer[intval($value->is_offer)];
            $queryRes[$key]->advance_desc = $this->is_advance[intval($value->is_advance)];
        }
        return $queryRes;

    }

    /**
     * description:修改订单状态
     * editor:zhangdong
     * date : 2018.12.10
     * param $mis_order_sn 订单号
     * param $status 订单状态 1，待挂靠2，待拆分3，已结束
     * @return object
     */
    public function updateStatus($mis_order_sn, $status)
    {
        $where = [
            ['mis_order_sn', $mis_order_sn],
        ];
        $update = [
            'status' => $status,
        ];
        $updateRes = DB::table('mis_order')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:根据订单号检查挂靠情况
     * editor:zhangdong
     * date : 2018.12.10
     * @param $mis_order_sn 订单号
     * @return object
     */
    public function checkAffiliate($misOrderSn)
    {
        $field = [
            DB::raw('COUNT(1) AS num')
        ];
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $queryRes = DB::table('mis_order_goods')->select($field)->where($where)
            ->where(function ($query) {
                $query->orWhere('entrust_time', null)
                    ->orWhere(DB::raw('LENGTH(sale_user_account)'), 0);
            })->first();
        return $queryRes;

    }

    /**
     * description:获取总单详情
     * editor:zhangdong
     * date : 2018.12.11
     * @param $mis_order_sn(订单号)
     * @return array
     */
    public function getOrderDetail($mis_order_sn, $reqParams)
    {
        $mis_order_sn = trim($mis_order_sn);
        //获取订单基本信息
        $orderInfo = $this->getOrderInfo($mis_order_sn);
        //获取订单商品信息
        $goodsInfo = $this->getOrderGoodsInfo($mis_order_sn, $reqParams);
        //组装品牌信息
        $arrBrandName= $this->getOrderBrandInfo($mis_order_sn);
        //查询总单补单相关信息
        $ongModel = new OrdNewGoodsModel();
        $isReplenish = $ongModel->checkIsReplenish($mis_order_sn);
        //统计总单有价格变动商品的条数
        $mogModel = new MisOrderGoodsModel();
        $newPriceNum = $mogModel->countNewPrice($mis_order_sn);
        return [
            'orderInfo' => $orderInfo,
            'isReplenish' => $isReplenish,
            'brand' => $arrBrandName,
            'newPriceNum' => $newPriceNum,
            'goodsNum' => $goodsInfo->count(),
            'goodsInfo' => $goodsInfo,
        ];
    }

    /**
     * description:获取总单拆分详情
     * editor:zhangdong
     * date : 2018.12.11
     * @param $mis_order_sn (订单号)
     * @return object
     */
    public function getOrderSplitDetail($mis_order_sn, $orderInfo)
    {
        //根据总单号查询拆分信息
        $orderSubModel = new MisOrderSubModel();
        $splitInfo = $orderSubModel->getSubInfo($mis_order_sn);
        //组装拆分信息
        $groupData = [];
        foreach ($splitInfo as $key => $value) {
            $sub_order_sn = trim($value->sub_order_sn);
            $subOrderSn = session('sub_order_sn');
            if (is_null($subOrderSn)) {
                session(['sub_order_sn' => $sub_order_sn]);
                $subOrderSn = session('sub_order_sn');
            }
            if ($subOrderSn == $sub_order_sn) {
                $groupData[$subOrderSn][] = $value;
            } else {
                session(['sub_order_sn' => $sub_order_sn]);
                $groupData[$sub_order_sn][] = $value;
            }
        }
        $orderInfo[0]->splitData = $this->createSplitData($groupData);
        return $orderInfo;
    }

    /**
     * description:组装订单信息和商品信息-专用
     * editor:zhangdong
     * date : 2018.12.11
     * @return array
     */
    private function createSplitData($orderInfo)
    {
        $splitData = [];
        foreach ($orderInfo as $key => $value) {
            //订单信息
            $subOrderData = [
                'sub_order_sn' => trim($value[0]->sub_order_sn),
                'status' => trim($value[0]->status),
                'is_submenu' => trim($value[0]->is_submenu),
                'sale_user_account' => trim($value[0]->sale_user_account),
                'entrust_time' => trim($value[0]->entrust_time),
                'create_time' => trim($value[0]->create_time),
            ];
            //订单商品信息
            $goodsData = [];
            foreach ($value as $item) {
                $goodsData[] = [
                    'goods_name' => trim($item->goods_name),
                    'spec_price' => trim($item->spec_price),
                    'sale_discount' => trim($item->sale_discount),
                    'spec_sn' => trim($item->spec_sn),
                    'erp_merchant_no' => trim($item->erp_merchant_no),
                    'goods_number' => intval($item->goods_number),
                    'stock_num' => intval($item->stock_num),
                    'wait_buy_num' => intval($item->wait_buy_num),
                ];
            }
            $splitData[] = [
                'subOrderData' => $subOrderData,
                'goodsData' => $goodsData,
            ];

        }
        return $splitData;
    }

    /**
     * description:通过总单号获取总单信息
     * editor:zongxing
     * date : 2018.12.14
     * @return array
     */
    public function getMisOrderInfo($mis_order_sn)
    {
        $mo_goods_info = DB::table($this->table)->select($this->field)
            ->where('mis_order_sn', $mis_order_sn)->first();
        $mo_goods_info = ObjectToArrayZ($mo_goods_info);
        return $mo_goods_info;
    }

    /**
     * description:获取发货单所属的销售用户id
     * editor:zongxing
     * date : 2018.12.17
     * return Array
     */
    public function getSaleUserOfDeliver($sub_order_sn)
    {
        $sale_user_info = DB::table('mis_order as mo')
            ->leftJoin('mis_order_sub as mos', 'mos.mis_order_sn', '=', 'mo.mis_order_sn')
            ->where('sub_order_sn', $sub_order_sn)
            ->first(['sale_user_id']);
        $sale_user_info = objectToArrayZ($sale_user_info);
        return $sale_user_info;
    }

    /**
     * description:获取总单列表
     * editor:zongxing
     * date : 2019.01.08
     * return Array
     */
    public function getMisOrderListByPage($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $where = [];
        if (isset($param_info['start_time'])) {
            $start_time = trim($param_info['start_time']);
            $where = [
                ['mos.entrust_time','>=',$start_time]
            ];
        }
        if (isset($param_info['end_time'])) {
            $end_time = trim($param_info['end_time']);
            $where = [
                ['mos.entrust_time','<=',$end_time],
            ];
        }
        $field = [
            'mo.order_id', 'mo.mis_order_sn', 'mo.status', 'is_split', 'user_name', 'mo.depart_id',
            DB::raw('SUM(jms_mog.wait_buy_num) as mis_order_wait_buy_num'),
            DB::raw('SUM(jms_mog.goods_number) as mis_order_total_num'),
            DB::raw('COUNT(jms_mog.spec_sn) as sku_num'),
            DB::raw('SUM(jms_mog.sale_discount) as sale_discount')
        ];
        $mis_order_list = DB::table('mis_order_goods as mog')
            ->select($field)
            ->leftJoin('mis_order as mo', 'mo.mis_order_sn', '=', 'mog.mis_order_sn')
            ->leftJoin('mis_order_sub as mos', 'mos.mis_order_sn', '=', 'mo.mis_order_sn')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->groupBy('mog.mis_order_sn')
            ->where('mo.status', 3)
            ->where('mos.status', 3)
            ->where($where)
            ->orderBy('mo.create_time', 'DESC')->paginate($page_size);
        $mis_order_list = objectToArrayZ($mis_order_list);
        return $mis_order_list;
    }

    /**
     * description:修改总单报价状态
     * editor:zhangdong
     * date : 2019.01.09
     * return bool
     */
    public function updateIsOffer($mis_order_sn, $is_offer)
    {
        //0，未报价 1，已报价
        $is_offer = intval($is_offer);
        $where = [
            ['mis_order_sn', $mis_order_sn],
        ];
        $update = [
            'is_offer' => $is_offer,
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:创建总单详情筛选条件
     * editor:zhangdong
     * date : 2019.01.21
     */
    private function createWhere($reqParams)
    {
        $where = [];
        //商品名称
        if (isset($reqParams['goods_name'])) {
            $where[] = [
                'g.goods_name', 'like', '%' . trim($reqParams['goods_name'] . '%')
            ];
        }
        //商家编码
        if (isset($reqParams['erp_merchant_no'])) {
            $where[] = [
                'gs.erp_merchant_no', 'like', '%' . trim($reqParams['erp_merchant_no'] . '%')
            ];
        }
        //规格码
        if (isset($reqParams['spec_sn'])) {
            $where[] = [
                'gs.spec_sn',trim($reqParams['spec_sn'])
            ];
        }
        //品牌名称
        if (isset($reqParams['brand_name'])) {
            $where[] = [
                'b.name', 'like', '%' . trim($reqParams['brand_name'] . '%')
            ];
        }
        //交付时间
        if (isset($reqParams['entrust_time'])) {
            $strWhere = [
                'mog.entrust_time', 'like', trim($reqParams['entrust_time'] . '%')
            ];
            if (trim($reqParams['entrust_time']) == '未挂靠') {
                $strWhere = [
                    DB::raw('LENGTH(jms_mog.entrust_time)'), 0
                ];
            }
            $where[] = $strWhere;
        }
        //销售账号
        if (isset($reqParams['sale_user_account'])) {
            $strWhere = [
                'mog.sale_user_account', 'like', '%' . trim($reqParams['sale_user_account'] . '%')
            ];
            if (trim($reqParams['sale_user_account']) == '未挂靠') {
                $strWhere = [
                    DB::raw('LENGTH(jms_mog.sale_user_account)'), 0
                ];
            }
            $where[] = $strWhere;
        }
        //查询有新价格的商品
        if (isset($reqParams['new_spec_price']) && $reqParams['new_spec_price'] == true) {
            $where[] = [
                'mog.new_spec_price', '>', 0
            ];
        }

        return $where;
    }

    /**
     * description:总单统计-该方法停用
     * editor:zhangdong
     * date : 2019.01.23
     */
    public function orderStatistics_stop()
    {
        //统计商品总数
        $mogModel = new MisOrderGoodsModel();
        $totalGoodsNum = $mogModel->countMisGoodsNum();
        //统计YD,BD,DD状态下的商品总数
        $mosModel = new MisOrderSubModel();
        $countRes = $mosModel->subGoodsStatistics();
        $ydNum = $bdNum = $ddNum = 0;
        foreach ($countRes as $value) {
            $status = intval($value->status);
            if ($status == 1) {
                $ydNum = intval($value->num);
            }
            if ($status == 2) {
                $bdNum = intval($value->num);
            }
            if ($status == 3) {
                $ddNum = intval($value->num);
            }
        }
        //YD到BD转换率 = BD总数/商品总数
        $yd_bd_Rate = $bd_dd_Rate = 0;
        if ($totalGoodsNum > 0) {
            $yd_bd_Rate = round($bdNum/$totalGoodsNum, DECIMAL_DIGIT);
            //BD到DD转换率 = DD总数/商品总数
            $bd_dd_Rate = round($ddNum/$totalGoodsNum, DECIMAL_DIGIT);
        }
        $returnMsg = [
            'totalGoodsNum'=>$totalGoodsNum,
            'ydNum'=>$ydNum,
            'bdNum'=>$bdNum,
            'ddNum'=>$ddNum,
            'yd_bd_Rate'=>toPercent($yd_bd_Rate),
            'bd_dd_Rate'=>toPercent($bd_dd_Rate),
        ];
        return $returnMsg;

    }

    /**
     * description:获取订单转化率数据
     * editor:zhangdong
     * date : 2019.01.29
     */
    public function orderStatistics()
    {
        //统计商品总数
        $csModel = new ConversionStatisticsModel();
        $date = date('Y-m-d');
        $conversionData = $csModel->getDataByTime($date);
        $ydNum = isset($conversionData->yd_num) ? intval($conversionData->yd_num) : 0;
        $bdNum = isset($conversionData->bd_num) ? intval($conversionData->bd_num) : 0;
        $ddNum = isset($conversionData->dd_num) ? intval($conversionData->dd_num) : 0;
        $yd_bd_Rate = $bd_dd_Rate = 0;
        if ($ydNum > 0) {
            //YD到BD转换率 = BD总数/商品总数
            $yd_bd_Rate = round($bdNum/$ydNum, DECIMAL_DIGIT);
        }
        if ($bdNum > 0) {
            //BD到DD转换率 = DD总数/商品总数
            $bd_dd_Rate = round($ddNum/$bdNum, DECIMAL_DIGIT);
        }
        $returnMsg = [
            'totalGoodsNum'=>$ydNum,
            'ydNum'=>$ydNum,
            'bdNum'=>$bdNum,
            'ddNum'=>$ddNum,
            'yd_bd_Rate'=>toPercent($yd_bd_Rate),
            'bd_dd_Rate'=>toPercent($bd_dd_Rate),
        ];
        return $returnMsg;

    }

    /**
     * description:修改总单预判状态
     * editor:zhangdong
     * date : 2019.03.04
     * return bool
     */
    public function updateIsAdvance($mis_order_sn, $is_advance)
    {
        //0，未预判 1，已预判
        $is_advance = intval($is_advance);
        $where = [
            ['mis_order_sn', $mis_order_sn],
        ];
        $update = [
            'is_advance' => $is_advance,
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:获取订单品牌信息
     * editor:zhangdong
     * date : 2019.04.11
     * @return object
     */
    public function getOrderBrandInfo($misOrderSn)
    {
        $arrField = [
            'b.name as brand_name', 'b.brand_id'
        ];
        $where[] = ['mog.mis_order_sn', '=', $misOrderSn];
        $goodsBaseInfo = DB::table('mis_order_goods AS mog')->select($arrField)
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'mog.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->where($where)->groupBy('b.brand_id')->get();
        return $goodsBaseInfo;
    }

    /**
     * @description:参数校验--总单号
     * @editor:zhangdong
     * @date : 2019.04.15
     * @return mixed
     */
    public function checkMisOrderSn($reqParams)
    {
        //检查总单号
        $misOrderSn = isset($reqParams['mis_order_sn']) ? trim($reqParams['mis_order_sn']) : '';
        if (empty($misOrderSn)) {
            $returnMsg = ['code' => '2005','msg' => '参数错误'];
            return $returnMsg;
        }
        return $misOrderSn;

    }

    /**
     * description:获取订单中所有客户的信息
     * editor:zongxing
     * date : 2019.04.19
     */
    public function getSaleInfo()
    {
        $field = [
            'mo.sale_user_id', 'su.user_name'
        ];
        $sale_user_info = DB::table('mis_order as mo')
            ->leftJoin('sale_user as su', 'su.id', '=', 'mo.sale_user_id')
            ->distinct('mo.sale_user_id')->get($field);
        $sale_user_info = objectToArrayZ($sale_user_info);
        return $sale_user_info;
    }

    /**
     * description:根据订单号获取订单基本信息
     * editor:zhangdong
     * date : 2019.04.24
     * @param $misOrderSn (订单号)
     */
    public function getOrderMsg($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:组装导出数据
     * author:zhangdong
     * date : 2019.04.24
     */
    public function makeExportData($goodsInfo)
    {
        //组装spec_sn
        $arrSpecSn = [];
        foreach ($goodsInfo as $key => $value) {
            $arrSpecSn[] = trim($value->spec_sn);
        }
        //通过spec_sn查询出其对应的商品编码
        $gcModel = new GoodsCodeModel();
        $goodsCodeList = $gcModel->getCodeBySpecSn($arrSpecSn);
        $arrData = objectToArray($goodsCodeList);
        foreach ($goodsInfo as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            //通过规格码搜索对应的商品编码
            $searchRes = searchTwoArray($arrData, $spec_sn, 'spec_sn');
            $klCode = [];
            $xhsCode = '';
            foreach ($searchRes as $item) {
                $code_type = intval($item['code_type']);
                if ($code_type == $gcModel->code_type['KL_CODE']) {
                    $klCode[] = trim($item['goods_code']);
                }
                if ($code_type == $gcModel->code_type['XHS_CODE']) {
                    $xhsCode = trim($item['goods_code']);
                }
            }//end of foreach
            $goodsInfo[$key]->klCode = isset($klCode[0]) ? trim($klCode[0]) : '';
            if (count($klCode) > 1) {
                $goodsInfo[$key]->klCode = implode($klCode,',');
            }
            $goodsInfo[$key]->xhsCode = $xhsCode;
        }
        return $goodsInfo;

    }

    /**
     * description:根据总单号统计总单条数
     * author:zhangdong
     * date : 2019.06.10
     */
    public function countNum($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }

    /**
     * description:根据总单号统计总单条数
     * author:zhangdong
     * date : 2019.06.10
     */
    public function packageExportData($orderGoodsInfo, $misOrderSn, $pickMarginRate)
    {
        //查询总单信息
        $misOrderInfo = $this->getMisOrderInfo($misOrderSn);
        $sale_user_id = intval($misOrderInfo['sale_user_id']);
        $gcModel = new GoodsCodeModel();
        //获取平台类型
        $code_type = $gcModel->getCodeType($sale_user_id);
        $mosModel = new MisOrderSubModel();
        $mosGoodsInfo = $mosModel->getMosGoodsInfo($sale_user_id);
        $mosGoodsInfo = objectToArray($mosGoodsInfo);
        foreach ($orderGoodsInfo as $key => $value) {
            //组装商品编码-根据销售用户id展示对应编码
            $specSn = trim($value->spec_sn);
            $where = [
                ['spec_sn',$specSn],
                ['code_type',$code_type],
            ];
            $goodsCode = $gcModel->getStrCodeByWhere($where);
            if (empty($goodsCode)) {
                $goodsCode = $value->erp_merchant_no;
            }
            //转为字符串
            $orderGoodsInfo[$key]->goodsCode = $goodsCode;
            $exwDiscount = floatval($value->exw_discount);
            $spec_weight = floatval($value->spec_weight);
            $spec_price = floatval($value->spec_price);
            //重价比 = 重量/美金原价/重价系数/100
            $highPriceRate = $spec_weight > 0 && $spec_price > 0 ?
                $spec_weight/$spec_price/HIGH_PRICE_FACTOR/100 : 0;
            //重价比折扣 = EXW折扣 + 重价比
            $hprDiscount = $exwDiscount + $highPriceRate;
            $orderGoodsInfo[$key]->hprDiscount = round($hprDiscount, PRICE_DIGIT);
            //计算成本折扣 = 重价比折扣
            $orderGoodsInfo[$key]->costDiscount = round($hprDiscount, PRICE_DIGIT);
            //自采毛利率对应的折扣 = 重价比折扣/(1-当前自采毛利率)
            $pmrDiscount = round($hprDiscount/(1 - $pickMarginRate/100), PRICE_DIGIT);
            $orderGoodsInfo[$key]->pmrDiscount = $pmrDiscount;
            //自采毛利率对应的供货价 = 自采毛利率对应的折扣 * 美金原价
            $orderGoodsInfo[$key]->pmrdPrice = round($pmrDiscount * $spec_price, 2);
            //获取近期供货信息
            $mosModel->getRecentSupplyInfo($orderGoodsInfo[$key], $mosGoodsInfo, $specSn);
        }
        return $orderGoodsInfo;
    }

    /**
     * description 根据销售用户id和子单的创建时间获取sku的分货数量
     * author zhangdong
     * date 2019.09.06
     */
    public function getSkuSortNum($reqParams)
    {
        //组装查询条件
        $where = $this->sortWhere($reqParams);
        $field = [
            DB::raw('SUM(jms_sod.yet_num) AS sortNum'),'sod.spec_sn',
        ];
        $mos_on = [
            ['mo.mis_order_sn', 'mos.mis_order_sn'],
        ];
        $d_on = [
            ['d.sub_order_sn', 'mos.sub_order_sn'],
        ];
        $sd_on = [
            ['sod.demand_sn', 'd.demand_sn'],
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin((new MisOrderSubModel())->getTable(), $mos_on)
            ->leftJoin((new DemandModel())->getTable(), $d_on)
            ->leftJoin((new SortDataModel())->getTable(), $sd_on)
            ->where($where)->whereNotNull('sod.spec_sn')
            ->groupBy('sod.spec_sn')->get();
        return $queryRes;
    }//end of function



    //----------private专区----------
    /**
     * description 获取分货数量-条件组装
     * author zhangdong
     * date 2019.09.06
     */
    private function sortWhere($reqParams)
    {
        //时间处理-查询时默认只查近三个月的
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
            ['mos.create_time', '>=', $start_time],
            ['mos.create_time', '<=', $end_time],
        ];
        //销售用户
        if (isset($reqParams['sale_user_id'])) {
            $where[] = [
                'mo.sale_user_id', intval($reqParams['sale_user_id'])
            ];
        }
        return $where;

    }//end of function rankListWhere






}//end of class
