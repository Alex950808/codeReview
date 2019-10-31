<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

//引入时间处理包 add by zhangdong on the 2018.12.06
use Carbon\Carbon;

use App\Modules\Erp\ErpApi;

class MisOrderSubModel extends Model
{

    //是否分单
    public $is_submenu = [
        '0' => '未分单',
        '1' => '已分单',
    ];

    public $submenu_int = [
        'NO_SUBMENU' => 0,
        'YET_SUBMENU' => 1,
    ];
    //订单状态
    public $status = [
        '1' => 'YD',
        '2' => 'BD',
        '3' => 'DD',
        '4' => '已关闭',
    ];

    //订单状态-整型描述
    public $status_int = [
        'YD' => 1,
        'BD' => 2,
        'DD' => 3,
        'YET_CLOSE' => 4,
    ];

    private $field = [
        'mos.mis_order_sn', 'mos.sub_order_sn', 'mos.status', 'mos.is_submenu',
        'mos.sale_user_account', 'mos.entrust_time', 'mos.create_time', 'mos.remark',
        'mos.external_sn', 'mos.order_id',
    ];

    protected $table = 'mis_order_sub as mos';

    private $sgModel;

    /*
     * @desc:本类构造函数
     * @author:zhangdong
     * @date:2019.01.11
     * */
    public function __construct()
    {
        $this->sgModel = new SpotGoodsModel();
    }


    /**
     * @description:MIS订单列表-组装子订单数据
     * @editor:zhangdong
     * @param $orderInfo
     * date:2018.12.10
     */
    public function makeSubOrdData($submenuData, $mis_order_sn)
    {
        $subOrderData = [];
        $subGoodsData = [];
        $ydTime = date('Y-m-d H:i:s', time());
        foreach ($submenuData as $key => $value) {
            $subOrderSn = $this->generalSubOrdSn();
            //订单基本数据
            $subOrderData[] = [
                'mis_order_sn' => $mis_order_sn,
                'sub_order_sn' => $subOrderSn,
                'sale_user_account' => trim($value[0]->sale_user_account),
                'entrust_time' => trim($value[0]->entrust_time),
                'yd_time' => $ydTime,
            ];
            //组装订单商品数据
            foreach ($value as $item) {
                $goods_num = intval($item->goods_number);
                $stock_num = intval($item->stock_num);
                //计算默认待锁库数量
                $waitLockNum = $this->sgModel->calculateStock($stock_num, $goods_num);
                $subGoodsData[] = [
                    'sub_order_sn' => $subOrderSn,
                    'goods_name' => trim($item->goods_name),
                    'spec_sn' => trim($item->spec_sn),
                    'erp_merchant_no' => trim($item->erp_merchant_no),
                    'goods_number' => $goods_num,
                    'stock_num' => $stock_num,
                    'wait_buy_num' => intval($item->wait_buy_num),
                    'wait_lock_num' => intval($waitLockNum),
                    'spec_price' => trim($item->spec_price),
                    'sale_discount' => trim($item->sale_discount),
                    'exw_discount' => floatval($item->exw_discount),
                ];
            }
        }
        $orderData = [
            'subOrderData' => $subOrderData,
            'subGoodsData' => $subGoodsData,
        ];
        return $orderData;
    }

    /**
     * description:生成子订单号
     * editor:zhangdong
     * date : 2018.12.10
     * return String
     */
    private function generalSubOrdSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $mis_order_sn = 'SUB' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table('mis_order')
                ->where([
                    ['mis_order_sn', '=', $mis_order_sn]
                ])->count();
        } while ($count);
        return $mis_order_sn;
    }

    /**
     * @description:保存子单数据
     * @editor:zhangdong
     * @param $orderInfo
     * date:2018.12.10
     */
    public function saveSubOrderData($makeSubOrdData)
    {
        $saveRes = DB::transaction(function () use ($makeSubOrdData) {
            //订单表和订单商品表有外键关系，所以必须先写订单表后写订单商品表
            //订单基本数据保存
            DB::table("mis_order_sub")
                ->insert($makeSubOrdData['subOrderData']);
            //订单商品数据保存
            DB::table("mis_order_sub_goods")
                ->insert($makeSubOrdData['subGoodsData']);
            //记录YD状态下的商品数量（此时生成的子单为YD状态，
            //当天如果没有数据则新增，否则更新，第二天新增且数值从0开始重新递增）
            $csModel = new ConversionStatisticsModel();
            $arrData['bd_num'] = count($makeSubOrdData['subGoodsData']);
            $writeRes = $csModel->writeData($arrData);
            return $writeRes;
        });
        return $saveRes;
    }

    /**
     * @description:根据总单号查询子单信息
     * @editor:zhangdong
     * @param $orderInfo
     * date:2018.12.10
     */
    public function querySubOrder($mis_order_sn)
    {
        $mis_order_sn = trim($mis_order_sn);
        $field = [
            'mos.mis_order_sn', 'mos.sub_order_sn', 'mos.status', 'mos.is_submenu',
            'mos.sale_user_account', 'mos.entrust_time',
        ];
        $where = [
            ['mos.mis_order_sn', $mis_order_sn]
        ];
        $queryRes = DB::table('mis_order_sub as mos')->select($field)->where($where)->get();
        return $queryRes;

    }

    /**
     * @description:查询子单列表
     * @editor:zhangdong
     * @param $orderInfo
     * date:2018.12.11
     */
    public function getSubOrderList($reqParams, $pageSize, $orderType, $orderNum)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $field = [
            'mos.mis_order_sn', 'mos.sub_order_sn', 'mos.status', 'mos.is_submenu', 'mos.sale_user_account',
            'mos.entrust_time', 'mos.create_time', 'mos.yd_time', 'mos.bd_time', 'mos.dd_time', 'mos.remark',
            'mos.external_sn', 'mo.sale_user_id'
        ];
        $mosg_on = [
            ['mosg.sub_order_sn', '=', 'mos.sub_order_sn'],
        ];
        $mo_on = [
            ['mo.mis_order_sn', '=', 'mos.mis_order_sn'],
        ];
        //排序规则 1，创建时间 2，交付日期
        $orderStr = $orderType == 2 ? 'mos.entrust_time' : 'mos.create_time';
        $orderDesc = $orderNum == 2 ? 'ASC' : 'DESC';
        $queryRes = DB::table('mis_order_sub as mos')->select($field)
            ->leftJoin('mis_order_sub_goods as mosg', $mosg_on)
            ->leftJoin('mis_order as mo', $mo_on)
            ->where($where)->groupBy('mos.sub_order_sn')->orderBy($orderStr, $orderDesc)
            ->paginate($pageSize);
        //如果查询没有结果则直接返回
        if ($queryRes->count() == 0) return $queryRes;
        $mosgModel = new MisOrderSubGoodsModel();
        foreach ($queryRes as $key => $value) {
            //根据sub_order_sn查询商品信息
            $sub_order_sn = trim($value->sub_order_sn);
            //系统订单状态
            $queryRes[$key]->is_submenu = $this->is_submenu[intval($value->is_submenu)];
            $queryRes[$key]->status = $this->status[intval($value->status)];
            $goodsData = $mosgModel->getSubOrderGoods($sub_order_sn, $where);
            $queryRes[$key]->sku_num = count($goodsData);
            $queryRes[$key]->goods_data = $goodsData;
        }
        return $queryRes;

    }

    /**
     * description:查询订单-组装查询条件
     * editor:zhangdong
     * date:2018.12.11
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
            ['mos.create_time', '>=', $start_time],
            ['mos.create_time', '<=', $end_time],
        ];
        //总订单编号
        if (isset($reqParams['mis_order_sn'])) {
            $where[] = [
                'mos.mis_order_sn', trim($reqParams['mis_order_sn'])
            ];
        }
        //子订单编号
        if (isset($reqParams['sub_order_sn'])) {
            $where[] = [
                'mos.sub_order_sn', trim($reqParams['sub_order_sn'])
            ];
        }
        //订单状态 1,YD 2,BD 3,DD 4，已关闭
        $status = ['mos.status', '!=', 4];
        if (isset($reqParams['status'])) {
            $status = [
                'mos.status', trim($reqParams['status'])
            ];
        }
        $where[] = $status;
        //商品名称
        if (isset($reqParams['goods_name'])) {
            $where[] = [
                'mosg.goods_name', 'LIKE', '%' . trim($reqParams['goods_name'] . '%')
            ];
        }
        //商家编码
        if (isset($reqParams['erp_merchant_no'])) {
            $where[] = [
                'mosg.erp_merchant_no', trim($reqParams['erp_merchant_no'])
            ];
        }
        //规格码
        if (isset($reqParams['spec_sn'])) {
            $where[] = [
                'mosg.spec_sn', trim($reqParams['spec_sn'])
            ];
        }
        //销售用户
        if (isset($reqParams['sale_user_id'])) {
            $where[] = [
                'mo.sale_user_id', trim($reqParams['sale_user_id'])
            ];
        }
        //交付日期搜索-开始时间 zhangdong 2019.08.14
        if (isset($reqParams['entrust_start'])) {
            $where[] = [
                'mos.entrust_time', '>=', trim($reqParams['entrust_start']),
            ];
        }

        //交付日期搜索-结束时间 zhangdong 2019.08.14
        if (isset($reqParams['entrust_end'])) {
            $where[] = [
                'mos.entrust_time', '<=', trim($reqParams['entrust_end']),
            ];
        }

        //外部单号 zhangdong 2019.08.14
        if (isset($reqParams['external_sn'])) {
            $where[] = [
                'mos.external_sn', trim($reqParams['external_sn'])
            ];
        }

        return $where;
    }


    /**
     * description:获取总单拆分信息
     * editor:zhangdong
     * date : 2018.12.11
     * @param $mis_order_sn (总单号)
     * @return array
     */
    public function getSubInfo($mis_order_sn)
    {
        $mis_order_sn = trim($mis_order_sn);
        $otherField = [
            'mosg.goods_name', 'mosg.spec_price', 'mosg.sale_discount',
            'mosg.spec_sn', 'mosg.erp_merchant_no', 'mosg.goods_number',
            'mosg.stock_num', 'mosg.wait_buy_num', 'mosg.wait_lock_num'
        ];
        $field = array_merge($this->field, $otherField);
        $where = [
            ['mos.mis_order_sn', $mis_order_sn]
        ];
        $mosg_on = [
            ['mosg.sub_order_sn', '=', 'mos.sub_order_sn'],
        ];
        $queryRes = DB::table('mis_order_sub AS mos')->select($field)
            ->leftJoin('mis_order_sub_goods AS mosg', $mosg_on)
            ->where($where)->get();
        return $queryRes;
    }


    /**
     * description:根据子单号获取子单信息
     * editor:zhangdong
     * date : 2018.12.12
     * @param $sub_order_sn (订单号)
     * @return
     */
    public function getSubOrderInfo($sub_order_sn)
    {
        $sub_order_sn = trim($sub_order_sn);
        $where[] = ['mos.sub_order_sn', $sub_order_sn];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        if (!empty($queryRes)) {
            $queryRes->int_status = intval($queryRes->status);
            $queryRes->status = $this->status[intval($queryRes->status)];
            $queryRes->submenu_desc = $this->is_submenu[intval($queryRes->is_submenu)];
        }
        return $queryRes;
    }


    /**
     * description:通过MIS总单号获取YD订单列表
     * editor:zongxing
     * date : 2018.12.14
     * @return array
     */
    public function misSubOrderList($mis_order_sn)
    {
        $fields = ['order_id', 'mis_order_sn', 'sub_order_sn', 'status', 'is_submenu', 'sale_user_account',
            DB::raw('DATE(entrust_time) AS entrust_time')];
        $where = [
            ["mis_order_sn", $mis_order_sn]
        ];
        $mis_sub_order_list = DB::table("mis_order_sub")->where($where)->get($fields);
        $mis_sub_order_list = ObjectToArrayZ($mis_sub_order_list);
        foreach ($mis_sub_order_list as $k => $v) {
            $is_submenu_key = $v['is_submenu'];
            $total_is_submenu = $this->is_submenu;
            $mis_sub_order_list[$k]["is_submenu"] = $total_is_submenu[$is_submenu_key];
            $status_key = $v['status'];
            $total_status = $this->status;
            $mis_sub_order_list[$k]["status"] = $total_status[$status_key];
        }
        return $mis_sub_order_list;
    }

    /**
     * description:修改订单是否已分单
     * editor:zhangdong
     * date : 2018.12.15
     * param $spot_order_sn 订单号
     * param $is_push_erp 是否已推送 1，未推送 2，已推送
     */
    public function updateIsSubmenu($sub_order_sn, $is_submenu)
    {
        $submenuDesc = $this->is_submenu[$is_submenu];
        if (is_null($submenuDesc)) return false;
        $where = [
            ['sub_order_sn', $sub_order_sn],
        ];
        $update = [
            'is_submenu' => $is_submenu,
            'bd_time' => date('Y-m-d H:i:s'),
            'status' => 2,//订单状态 1,YD 2,BD 3,DD 4,已关闭
        ];
        $updateRes = DB::table('mis_order_sub')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:修改子订单状态
     * editor:zhangdong
     * date : 2018.12.18
     * param $spot_order_sn 订单号
     * param $status 订单状态 1,YD 2,BD 3,DD 4,已关闭
     */
    public function updateStatus($sub_order_sn, $status, $remark = '', $external_sn = '')
    {
        $statusDesc = $this->status[$status];
        if (is_null($statusDesc)) return false;
        $time = date('Y-m-d H:i:s');
        $where = [
            ['sub_order_sn', $sub_order_sn],
        ];
        $field = 'yd_time';
        if ($status == 1) {
            $field = 'yd_time';
        }
        if ($status == 2) {
            $field = 'bd_time';
        }
        if ($status == 3) {
            $field = 'dd_time';
        }
        $update = [
            'status' => $status,//订单状态 1,YD 2,BD 3,DD 4,已关闭
            $field => $time,
        ];
        if (!empty($remark)) {
            $update['remark'] = $remark;
        }
        if (!empty($external_sn)) {
            $update['external_sn'] = $external_sn;
        }
        $updateRes = DB::table('mis_order_sub')->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:上传BD单后开始操作子订单（修改数量和销售折扣，并根据当前库存更新对应子订单数量）
     * author:zhangdong
     * date : 2018.12.18
     * @return bool
     */
    public function operateOrd($subOrderGoods, $sub_order_sn, $spot_order_sn, $demand_sn)
    {
        //现货单和需求单必须要有一个
        if (empty($spot_order_sn) && empty($demand_sn)) {
            return false;
        }
        $sub_order_sn = trim($sub_order_sn);
        $mosgModel = new MisOrderSubGoodsModel();
        $sgModel = new SpotGoodsModel();
        $gsModel = new GoodsSpecModel();
        $dgModel = new DemandGoodsModel();
        //通过需求单号查找实采批次号
        $rpModel = new RealPurchaseModel();
        //需求单中的商品也许没有被预采，所以此处有查询为空的可能，但这种情况
        //属于正常，不能阻止程序运行
        $real_purchase_sn = '';
        if (!empty($demand_sn)) {
            $rpInfo = $rpModel->queryDemand($demand_sn);
            $isSet = isset($rpInfo->real_purchase_sn);
            $real_purchase_sn = $isSet ? trim($rpInfo->real_purchase_sn) : '';
        }
        $rpdModel = new RealPurchaseDetailModel();
        //如果现货单存在的话则获取现货单商品数据
        $arrSpotGoodsInfo = [];
        if (!empty($spot_order_sn)) {
            $spotGoodsInfo = $sgModel->getSpotGoodsInfo($spot_order_sn);
            $arrSpotGoodsInfo = objectToArray($spotGoodsInfo);
        }
        //根据子单号查询该单下面的商品
        $subGoodsInfo = $mosgModel->getSubGoods($sub_order_sn);
        $arrSubGoods = objectToArray($subGoodsInfo);
        $modifyRes = false;
        foreach ($subOrderGoods as $key => $value) {
            $spec_sn = $value['spec_sn'];
            if (empty($spec_sn)) {
                continue;
            }
            $sale_discount = $value['sale_discount'];
            $goods_num = $value['goods_number'];
            //根据上传表格中的规格码查找商品当前库存以计算得出要更新的商品数量，
            //如果表格中的商品不在子单商品中则跳过，但不中断执行
            $searchRes = searchTwoArray($arrSubGoods, $spec_sn, 'spec_sn');
            if (count($searchRes) == 0) {
                continue;
            }
            //检查旧数据和新数据是否相等，如果相等则直接跳过
            $goodsNumOld = intval($searchRes[0]['goods_number']);
            $saleDiscountOld = floatval($searchRes[0]['dd_sale_discount']);
            if ($goodsNumOld == $goods_num && $saleDiscountOld == $sale_discount) {
                continue;
            }
            //总库存 = 实时库存 + 当前锁库库存
            $stock_num = intval($searchRes[0]['stock_num']);
            $lock_stock_num = intval($searchRes[0]['lock_stock_num']);
            $total_num = $stock_num + $lock_stock_num;
            //根据库存计算现货单要更新的数量
            $spot_num = $sgModel->calculateStock($total_num, $goods_num);
            //预判采购量待采量 = 总需求数量 - 现货数量
            $waitNumOne = intval($goods_num - $spot_num);
            //根据子单号修改子单数量和销售折扣-第一次更新子单待采量
            $mosgModel->modSubGoodsData(
                $sub_order_sn, $spec_sn, $sale_discount, $goods_num, $waitNumOne
            );
            //根据现货单号修改现货单数量和销售折扣
            //当子单对应的所有商品库存为0时，按库存分单时不会分出现货单
            if (!empty($spot_order_sn)) {
                $modifyRes = $sgModel->modSpotGoodsData(
                    $spot_order_sn, $spec_sn, $sale_discount, $spot_num, $stock_num
                );
                //处理锁库及商品库存
                //原来的sku购买数量
                $oldSpecSnInfo = searchTwoArray($arrSpotGoodsInfo, $spec_sn, 'spec_sn');
                $oldGoodsNum = 0;
                if (count($oldSpecSnInfo) >= 1) {
                    $oldGoodsNum = intval($oldSpecSnInfo[0]['goods_number']);
                }
                //新旧购买数量差异值 $curDiffer = $oldGoodsNum - $spot_num
                $curDiffer = intval($oldGoodsNum - $spot_num);
                //根据当前新旧数量差异值处理对应商品库存和锁库
                $gsModel->operateGoodsStock($curDiffer, $spec_sn);
            }
            //下面的业务都是基于$demand_sn的，如果其为空则无需走下去
            if (empty($demand_sn)) {
                continue;
            }
            //根据需求单号修改采购单（需求单）数量
            //待采购数量（去除现货单数量后的需求数量）
            $willBuyNum = intval($goods_num - $spot_num);
            //预采单中的已采数量
            //如果为空说明需求单未被预采，此时需求量无需扣除已预采数
            $purchasedNum = 0;
            if (!empty($real_purchase_sn)) {
                $rpdInfo = $rpdModel->getRealPruDetail($real_purchase_sn, $spec_sn);
                $purchasedNum = $rpdInfo->count() > 0 ? intval($rpdInfo[0]->day_buy_num) : 0;
            }
            //计算去掉预采数量后的需求数量
            $calculateRes = $dgModel->calculateDemNum($willBuyNum, $purchasedNum);
            $waitNum = intval($calculateRes['waitNum']);
            //第二次更新子单待采量-去除了预采数量
            $mosgModel->updateWaitNum($sub_order_sn, $spec_sn, $waitNum);
            $modifyRes = $dgModel->modDemGoodsData($demand_sn, $spec_sn, $sale_discount, $waitNum, $willBuyNum);
        }//end of foreach

        return $modifyRes;

    }

    /**
     * description:修改子单备注
     * author:zhangdong
     * params: $type 修改类型 1 备注 2 外部订单号
     * date : 2019.01.11
     */
    public function updateRemark($sub_order_sn, $value, $type = 1)
    {
        $where = [
            ['sub_order_sn', $sub_order_sn],
        ];
        $field = '';
        //1 备注
        if ($type == 1) {
            $field = 'remark';
        }
        //2 外部订单号
        if ($type == 2) {
            $field = 'external_sn';
        }
        //3 修改交期
        if ($type == 3) {
            $field = 'entrust_time';
        }
        if (empty($field)) {
            return false;
        }
        $update = [$field => trim($value)];
        $updateRes = DB::table('mis_order_sub')->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:获取销售用户最近的BD和DD折扣-总单详情和报价页面专用
     * editor:zhangdong
     * date : 2019.01.18
     * @param $mis_order_sn (总单号)
     * @return array
     */
    public function getMosGoodsInfo($sale_user_id, $spec_sn = '')
    {
        //时间处理-查询订单列表时默认只查近三个月的
        //开始时间
        $start_time = Carbon::now()->addDays(-15)->toDateTimeString();
        //结束时间
        $end_time = Carbon::now()->toDateTimeString();
        //时间筛选
        $where = [
            ['mo.sale_user_id', $sale_user_id],
            ['mos.create_time', '>=', $start_time],
            ['mos.create_time', '<=', $end_time],
        ];
        if (!empty($spec_sn)) {
            $where[] = ['mosg.spec_sn', $spec_sn];
        }
        $field = [
            'mosg.spec_sn', 'mosg.bd_sale_discount', 'mosg.dd_sale_discount',
            'mosg.goods_number', 'mos.entrust_time', 'mosg.spec_price', 'mosg.dd_num',
            'mos.bd_time', 'mos.dd_time',
        ];
        $mosg_on = [
            ['mos.sub_order_sn', 'mosg.sub_order_sn'],
        ];
        $mo_on = [
            ['mo.mis_order_sn', 'mos.mis_order_sn'],
        ];
        $queryRes = DB::table('mis_order_sub_goods AS mosg')->select($field)
            ->leftJoin('mis_order_sub AS mos', $mosg_on)
            ->leftJoin('mis_order AS mo', $mo_on)
            ->where($where)->groupBy('mosg.spec_sn')->orderBy('mosg.create_time', 'desc')->get();
        return $queryRes;
    }

    /**
     * description:获取总单下子单的状态-专用，请勿修改
     * editor:zhangdong
     * date : 2019.01.21
     * @param $misOrderSn (总单号)
     * @return
     */
    public function getSubStatus($misOrderSn)
    {
        $where = [
            ['mis_order_sn', $misOrderSn],
        ];
        $field = ['status'];
        $queryRes = DB::table($this->table)->select($field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description:根据总单号组装总单下对应所有子单的状态
     * editor:zhangdong
     * date : 2019.01.22
     * @param $misOrderSn (总单号)
     * @return array
     */
    public function makeSubStatus($misOrderSn)
    {
        $subInfo = $this->getSubStatus($misOrderSn);
        $arrStatus = [];
        foreach ($subInfo as $value) {
            $status = intval($value->status);
            $arrStatus[] = $status;
        }
        $arrStatus = array_unique($arrStatus);
        return $arrStatus;
    }

    /**
     * description:统计YD,BD,DD等状态下的商品总数
     * editor:zhangdong
     * date : 2019.01.23
     */
    public function subGoodsStatistics()
    {
        $field = [DB::raw('COUNT(1) AS num'), 'mos.status'];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('mis_order_sub_goods as mosg', 'mos.sub_order_sn', '=', 'mosg.sub_order_sn')
            ->groupBy('mos.status')->get();
        return $queryRes;
    }

    /**
     * description:检查子单是否已经分单
     * author:zhangdong
     * date:2019.03.11
     */
    public function checkIsSubmenu($sub_order_sn)
    {
        $where = [
            ['sub_order_sn', $sub_order_sn],
        ];
        //检查需求单中是否已经有子单数据
        $countRes = DB::table('demand')->where($where)->count();
        //如果需求单中已经有了数据则说明当前子单已被分单，直接返回
        if ($countRes > 0) {
            return true;
        }
        //检查现货单中是否已经有子单数据
        $countRes = DB::table('spot_order')->where($where)->count();
        if ($countRes > 0) {
            return true;
        }
        return false;
    }

    /**
     * description:获取需要取消的子单-非DD状态的子单如果停留时间超过3天则要去取消
     * author:zhangdong
     * date:2019.03.13
     */
    public function getCancelSubOrder()
    {
        $expireTime = Carbon::now()->addDays('-3');
        $where = [
            ['create_time', '<', $expireTime]
        ];
        //检查需求单中是否已经有子单数据
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->whereIn('status', [1, 2])->get();
        return $queryRes;
    }

    /**
     * description:获取子单的数量统计信息
     * author:zongxing
     * date:2019.04.15
     */
    public function getMisOrderSubSum($order_type)
    {
        $where = [
            ['status', '=', $order_type]
        ];
        $order_num = DB::table($this->table)->where($where)->count();
        $order_num = objectToArrayZ($order_num);
        return $order_num;
    }

    /**
     * description:检查子单的数据是否正常（现货单和需求单至少有一个）
     * author:zhangdong
     * date:2019.04.29
     */
    public function checkSubOrderData($sub_order_sn)
    {
        //查询现货单号
        $soModel = new SpotOrderModel();
        $spotData = $soModel->getSpotOrderMsg($sub_order_sn, 2);
        $spot_sn = isset($spotData->spot_order_sn) ? trim($spotData->spot_order_sn) : '';
        $spotStatus = isset($spotData->order_status) ? intval($spotData->order_status) : 0;
        if ($spotStatus === 0 || $spotStatus === $soModel->status_int['YET_CANCEL']) {
            $spot_sn = '';
        }
        //查询需求单号
        $demModel = new DemandModel();
        $demData = $demModel->getDemOrderMsg($sub_order_sn, 2);
        $demandSnIsset = isset($demData->demand_sn);
        $demand_sn = $demandSnIsset ? trim($demData->demand_sn) : '';
        //子单分单后需求单和现货单至少有一个是存在的，如果不存在数据必然异常
        if (empty($spot_sn) && empty($demand_sn)) {
            $returnMsg = ['code' => '2056', 'msg' => '子单分单信息数据异常'];
            return $returnMsg;
        }
        return ['spot_sn' => $spot_sn, 'demand_sn' => $demand_sn];
    }


    /*
     * @description:MIS订单列表-组装子订单数据-新业务
     * @editor:zhangdong
     * @param $orderInfo
     * date:2019.05.21
     */
    public function makeSubData(
        $dd_data,
        $mis_order_sn,
        $sale_user_account,
        $entrust_time,
        $external_sn,
        $remark,
        $expectTime
    )
    {
        $subOrderData = [];
        $subGoodsData = [];
        $bdTime = date('Y-m-d H:i:s', time());
        $subOrderSn = $this->generalSubOrdSn();
        //订单基本数据
        $subOrderData[] = [
            'mis_order_sn' => $mis_order_sn,
            'sub_order_sn' => $subOrderSn,
            'sale_user_account' => trim($sale_user_account),
            'entrust_time' => trim($entrust_time),
            'external_sn' => trim($external_sn),
            'remark' => trim($remark),
            'bd_time' => $bdTime,
            'status' => $this->status_int['BD'],
            'expect_time' => $expectTime,
        ];
        foreach ($dd_data as $key => $item) {
            //组装订单商品数据
            $dd_num = intval($item['dd_num']);
            $cash_num = intval($item['cash_num']);
            //计算默认待锁库数量
            $waitLockNum = $this->sgModel->calculateStock($cash_num, $dd_num);
            //待采量 = DD数量 - 现货数量
            $diff = $dd_num - $cash_num;
            $wait_buy_num = $diff <= 0 ? 0 : intval($diff);
            $sale_discount = floatval($item['sale_discount']);
            $subGoodsData[] = [
                'sub_order_sn' => $subOrderSn,
                'goods_name' => trim($item['goods_name']),
                'spec_sn' => trim($item['spec_sn']),
                'erp_merchant_no' => trim($item['erp_merchant_no']),
                'goods_number' => intval($item['goods_number']),
                'stock_num' => intval($item['stock_num']),
                'wait_buy_num' => intval($wait_buy_num),
                'wait_lock_num' => intval($waitLockNum),
                'spec_price' => trim($item['spec_price']),
                'sale_discount' => $sale_discount,
                'platform_barcode' => trim($item['platform_barcode']),
                'bd_sale_discount' => $sale_discount,
                'dd_sale_discount' => $sale_discount,
                'exw_discount' => floatval($item['exw_discount']),
                'dd_num' => $dd_num,
                'cash_num' => $cash_num,
            ];
        }
        $orderData = [
            'subOrderData' => $subOrderData,
            'subGoodsData' => $subGoodsData,
        ];
        return $orderData;
    }

    /*
     * @description:根据子单号，销售账号，交付日期查询子单是否已经生成
     * @author:zhangdong
     * date:2019.05.21
     */
    public function checkSubExist($mis_order_sn, $externalSn)
    {
        $where = [
            'mis_order_sn' => $mis_order_sn,
            'external_sn' => $externalSn,
        ];
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * @description:参数校验--子单号
     * @editor:zhangdong
     * @date : 2019.05.21
     * @return mixed
     */
    public function checkSubOrderSn($reqParams)
    {
        //检查总单号
        $subOrderSn = isset($reqParams['sub_order_sn']) ? trim($reqParams['sub_order_sn']) : '';
        if (empty($subOrderSn)) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return $returnMsg;
        }
        return $subOrderSn;

    }

    /**
     * description:修改订单是否已分单
     * author:zhangdong
     * date : 2019.05.22
     */
    public function modifySubmenu($sub_order_sn, $is_submenu)
    {
        $submenuDesc = $this->is_submenu[$is_submenu];
        if (is_null($submenuDesc)) return false;
        $where = [
            ['sub_order_sn', $sub_order_sn],
        ];
        $update = [
            'is_submenu' => $is_submenu,
        ];
        $updateRes = DB::table('mis_order_sub')->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:获取近期供货信息
     * editor:zhangdong
     * date : 2019.06.12
     * @return array
     */
    public function getRecentSupplyInfo($orderGoodsInfo, $mosGoodsInfo, $specSn)
    {
        //在总单对应的子单商品信息中查出BD和DD折扣
        $searchRes = searchTwoArray($mosGoodsInfo, $specSn, 'spec_sn');
        $firstRes = isset($searchRes[0]) ? $searchRes[0] : [];
        $secondRes = isset($searchRes[1]) ? $searchRes[1] : [];
        //第一次交付时间
        $first_time = isset($firstRes['entrust_time']) ? trim($firstRes['entrust_time']) : '';
        $orderGoodsInfo->firstTime = $first_time;
        //第一次BD供货折扣
        $bd_sale_discount = isset($firstRes['bd_sale_discount']) ? floatval($firstRes['bd_sale_discount']) : 0;
        $orderGoodsInfo->firstBdSaleDiscount = $bd_sale_discount;
        //第一次BD供货美金
        $specPrice = isset($firstRes['spec_price']) ? floatval($firstRes['spec_price']) : 0;
        $orderGoodsInfo->firstBdSpecPrice = $specPrice;
        //第一次BD供货数量
        $num = isset($firstRes['goods_number']) ? intval($firstRes['goods_number']) : 0;
        $orderGoodsInfo->firstBdNum = $num;
        //第一次DD供货折扣
        $dd_sale_discount = isset($firstRes['dd_sale_discount']) ? floatval($firstRes['dd_sale_discount']) : 0;
        $orderGoodsInfo->firstDdSaleDiscount = $dd_sale_discount;
        //第一次DD供货美金
        $orderGoodsInfo->firstDdSpecPrice = $specPrice;
        //第一次DD供货数量
        $ddNum = isset($firstRes['dd_num']) ? intval($firstRes['dd_num']) : 0;
        $orderGoodsInfo->firstDdNum = $ddNum;


        //第二次交付时间
        $secondTime = isset($secondRes['entrust_time']) ? trim($secondRes['entrust_time']) : '';
        $orderGoodsInfo->secondTime = $secondTime;
        //第二次BD供货折扣
        $bd_sale_discount = isset($secondRes['bd_sale_discount']) ? floatval($secondRes['bd_sale_discount']) : 0;
        $orderGoodsInfo->secondBdSaleDiscount = $bd_sale_discount;
        //第二次BD供货美金
        $specPrice = isset($secondRes['spec_price']) ? floatval($secondRes['spec_price']) : 0;
        $orderGoodsInfo->secondBdSpecPrice = $specPrice;
        //第二次BD供货数量
        $num = isset($secondRes['goods_number']) ? intval($secondRes['goods_number']) : 0;
        $orderGoodsInfo->secondBdNum = $num;
        //第二次DD供货折扣
        $dd_sale_discount = isset($secondRes['dd_sale_discount']) ? floatval($secondRes['dd_sale_discount']) : 0;
        $orderGoodsInfo->secondDdSaleDiscount = $dd_sale_discount;
        //第二次DD供货美金
        $orderGoodsInfo->secondDdSpecPrice = $specPrice;
        //第二次DD供货数量
        $ddNum = isset($secondRes['dd_num']) ? intval($secondRes['dd_num']) : 0;
        $orderGoodsInfo->secondDdNum = $ddNum;
        return $orderGoodsInfo;

    }

    /**
     * description 查询子单列表信息
     * author zhangdong
     * date 2019.06.19
     */
    public function querySubOrderList($mis_order_sn)
    {
        $subMsg = $this->getSubMsg($mis_order_sn);
        $demandModel = new DemandModel();
        $soModel = new SpotOrderModel();
        foreach ($subMsg as $k => $value) {
            $subMsg[$k]->is_submenu = $this->is_submenu[$value->is_submenu];
            $subMsg[$k]->status = $this->status[$value->status];
            $subOrderSn = $value->sub_order_sn;
            //根据子单号查询现货单和需求单
            //需求单
            $demandInfo = $demandModel->getDemandBySubOrder($subOrderSn);
            if(is_null($demandInfo)){
                $demandInfo = '';
            }
            $subMsg[$k]->demandInfo = $demandInfo;
            //现货单
            $spotInfo = $soModel->getSpotBySubOrder($subOrderSn);
            if(is_null($spotInfo)){
                $spotInfo = '';
            }
            $subMsg[$k]->spotInfo = $spotInfo;
        }
        return $subMsg;
    }

    /**
     * description 获取子单信息
     * author zhangdong
     * date 2019.06.19
     */
    public function getSubMsg($mis_order_sn)
    {
        $where = [
            ['mis_order_sn', $mis_order_sn]
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }

    /**
     * description DD单品排行
     * author zhangdong
     * date 2019.09.02
     */
    public function getGoodsRankList($reqParams, $pageSize)
    {
        //统计开始时间-默认为八月份
        $reqParams['start_time'] = COUNT_START_TIME;
        //组装查询条件
        $where = $this->rankListWhere($reqParams);
        $field = [
            'mo.sale_user_id', 'mosg.spec_sn', 'mosg.goods_name','mosg.erp_merchant_no',
            'mosg.platform_barcode','mosg.spec_price',DB::raw('COUNT(jms_mosg.spec_sn) AS sku_num'),
            DB::raw('SUM(jms_mosg.goods_number) AS order_num'),
        ];
        $mosg_on = [
            ['mos.sub_order_sn', 'mosg.sub_order_sn'],
        ];
        $mo_on = [
            ['mo.mis_order_sn', '=', 'mos.mis_order_sn']
        ];
        //组装排序规则
        $orderRule['field'] = 'sku_num';
        $orderRule['type'] = 'desc';
        if(isset($reqParams['orderField']) && isset($reqParams['orderType'])){
            $orderRule = $this->makeOrder($reqParams['orderField'], $reqParams['orderType']);
        }
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin((new MisOrderSubGoodsModel())->getTable(), $mosg_on)
            ->leftJoin((new MisOrderModel())->getTable(), $mo_on)
            ->where($where)->groupBy('mosg.spec_sn')
            ->orderBy($orderRule['field'], $orderRule['type'])
            ->orderBy('mosg.goods_name','desc')
            ->paginate($pageSize);
        //获取分货数据
        $moModel = new MisOrderModel();
        $sortData = $moModel->getSkuSortNum($reqParams);
        $arrSortData = objectToArray($sortData);
        //为查询结果加入分货信息
        foreach ($queryRes as $key => $value) {
            $spec_sn = trim($value->spec_sn);
            $searchRes = searchTwoArray($arrSortData, $spec_sn, 'spec_sn');
            $sortNum = 0;
            if (count($searchRes) > 0) {
                $sortNum = intval($searchRes[0]['sortNum']);
            }
            $queryRes[$key]->sortNum = $sortNum;
        }
        return $queryRes;
    }



    //----------private专区----------
    /**
     * description DD单品排行-组装查询条件
     * author zhangdong
     * date 2019.09.02
     */
    private function rankListWhere($reqParams)
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
        //规格码
        if (isset($reqParams['spec_sn'])) {
            $where[] = [
                'mosg.spec_sn', trim($reqParams['spec_sn'])
            ];
        }
        //销售用户
        if (isset($reqParams['sale_user_id'])) {
            $where[] = [
                'mo.sale_user_id', intval($reqParams['sale_user_id'])
            ];
        }
        //交付日期搜索-开始时间
        if (isset($reqParams['entrust_start'])) {
            $where[] = [
                'mos.entrust_time', '>=', trim($reqParams['entrust_start']),
            ];
        }
        //交付日期搜索-结束时间
        if (isset($reqParams['entrust_end'])) {
            $where[] = [
                'mos.entrust_time', '<=', trim($reqParams['entrust_end']),
            ];
        }
        return $where;

    }//end of function rankListWhere


    /**
     * description DD单品排行-组装排序规则
     * author zhangdong
     * params $field 排序字段
     * params $type 排序规则 asc, desc
     * return array
     * date 2019.09.02
     */
    private function makeOrder($field, $type)
    {
        switch(true){
            case $field == 'sku_num' && $type == 'asc':
                $orderRule = [
                    'field' => 'sku_num',
                    'type' => 'asc',
                ];
                break;
            case $field == 'sku_num' && $type == 'desc':
                $orderRule = [
                    'field' => 'sku_num',
                    'type' => 'desc',
                ];
                break;
            case $field == 'order_num' && $type == 'asc':
                $orderRule = [
                    'field' => 'order_num',
                    'type' => 'asc',
                ];
                break;
            case $field == 'order_num' && $type == 'desc':
                $orderRule = [
                    'field' => 'order_num',
                    'type' => 'desc',
                ];
                break;
            default:
                $orderRule = [
                    'field' => 'sku_num',
                    'type' => 'desc',
                ];
                break;
        }
        return $orderRule;

    }



}//end of class
