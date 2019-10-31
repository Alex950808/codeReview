<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ErpGoodsSpecModel extends Model
{
    public $table = 'erp_goods_spec as egs';
    private $field = [
        'egs.spec_id','egs.erp_spec_id','egs.goods_no','egs.stock_num','egs.stock_send_num',
        'egs.lock_num','egs.unpay_num','egs.subscribe_num','egs.order_num','egs.sending_num',
        'egs.purchase_num','egs.transfer_num','egs.to_purchase_num','egs.purchase_arrive_num',
        'egs.spec_wh_no','egs.wms_sync_stock','egs.wms_preempty_stock','egs.refund_onway_num',
        'egs.to_transfer_num','egs.wms_stock_diff','egs.wms_sync_time','egs.cost_price','egs.spec_no',
        'egs.spec_name','egs.spec_code','egs.barcode','egs.weight','egs.warehouse_no','egs.warehouse_name',
        'egs.create_time',
    ];

    /**
     * description:查询商品信息
     * author:zhangdong
     * date : 2019.01.07
     * @return
     */
    public function queryGoodsInfo($warehouse_no, $spec_no)
    {
        $where = [
            ['warehouse_no', $warehouse_no],
            ['spec_no', $spec_no],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * description:循环商品信息
     * author:zhangdong
     * date : 2019.01.07
     * @return
     */
    public function loopUpdateGoodsData($stocksData)
    {
        if (empty($stocksData)) {
            return false;
        }
        $log = logInfo('erp/stockQuery');
        $updateRes = false;
        foreach ($stocksData as $value) {
            $warehouseNo = trim($value['warehouse_no']);
            $warehouseName = trim($value['warehouse_name']);
            $specNo = trim($value['spec_no']);
            $stockNum = intval($value['stock_num']);
            $costPrice = trim($value['cost_price']);
            $updateRes = $this->updateGoodsData($warehouseNo,$specNo,$stockNum,$costPrice);
            $msg = '仓库编码-' . $warehouseNo . '-仓库名称-' . $warehouseName .
                '-商家编码-' . $specNo . '-库存（非可发库存）-' . $stockNum .
                '-成本价-' . $costPrice . '-更新结果-' . $updateRes;
            $log->addInfo($msg);
        }
        return $updateRes;
    }

    /**
     * description:更新商品信息
     * author:zhangdong
     * date : 2019.01.07
     * @return
     */
    public function updateGoodsData($warehouse_no, $spec_no, $stock_num, $cost_price)
    {
        $where = [
            ['spec_no',$spec_no],
            ['warehouse_no',$warehouse_no],
        ];
        $update = [
            'stock_num' => $stock_num,
            'cost_price' => $cost_price,
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:更新商品信息
     * author:zhangdong
     * date : 2019.01.07
     * @return
     */
    public function getExportGoodsData()
    {
        $field = [
            'eg.goods_no','eg.goods_name','eg.goods_short_name',
            'egs.spec_no','egs.barcode',
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0012" THEN stock_num ELSE 0 END) "pf"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0014" THEN stock_num ELSE 0 END) "jhjbs"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0015" THEN stock_num ELSE 0 END) "bsls"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0017" THEN stock_num ELSE 0 END) "hkzh"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0020" THEN stock_num ELSE 0 END) "pp"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0100" THEN stock_num ELSE 0 END) "hxz"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0101" THEN stock_num ELSE 0 END) "gx"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0102" THEN stock_num ELSE 0 END) "zy"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "0103" THEN stock_num ELSE 0 END) "jhjzz"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "018" THEN stock_num ELSE 0 END) "bsgx"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "08" THEN stock_num ELSE 0 END) "hkds"'),
            DB::raw('SUM(CASE jms_egs.warehouse_no WHEN "19" THEN stock_num ELSE 0 END) "bsrhkj"'),
        ];
        $where = [];
        $eg_on = [
            ['eg.goods_no', '=', 'egs.goods_no']
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('erp_goods as eg',$eg_on)
            ->where($where)->groupBy('egs.spec_no')->get();
        return $queryRes;

    }







}//end of class
