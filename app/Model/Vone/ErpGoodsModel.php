<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ErpGoodsModel extends Model
{
    public $table = 'erp_goods as eg';
    private $field = [
        'eg.goods_no','eg.brand_name','eg.goods_name','eg.goods_short_name',
        'eg.create_time',
    ];

    /**
     * description:ERP商品列表
     * editor:zhangdong
     * date:2019.01.07
     */
    public function getErpGoodsList($reqParams, $pageSize)
    {
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $eg_on = [
            ['egs.goods_no', '=', 'eg.goods_no'],
        ];
        $egsField = [
            'egs.stock_num','egs.cost_price','egs.spec_no','egs.spec_name',
            'egs.barcode','egs.warehouse_name',
        ];
        $this->field = array_merge($this->field, $egsField);
        $queryRes = DB::table($this->table)->select($this->field)
            ->leftJoin('erp_goods_spec as egs', $eg_on)
            ->where($where)->orderBy('eg.create_time','desc')
            ->paginate($pageSize);
        //如果查询没有结果则直接返回
        if ($queryRes->count() == 0) {
            return $queryRes;
        }
        return $queryRes;
    }

    /**
     * description:查询商品-组装查询条件
     * editor:zhangdong
     * date:2019.01.07
     */
    protected function makeWhere($reqParams)
    {
        //商品货号
        $where = [];
        if (isset($reqParams['goods_no'])) {
            $where[] = [
                'eg.goods_no', trim($reqParams['goods_no'])
            ];
        }
        //品牌名称
        if (isset($reqParams['brand_name'])) {
            $where[] = [
                'eg.brand_name', 'like', '%' . trim($reqParams['brand_name'] . '%')
            ];
        }
        //商品名称
        if (isset($reqParams['goods_name'])) {
            $where[] = [
                'eg.goods_name', 'like', '%' . trim($reqParams['goods_name'] . '%')
            ];
        }
        //货品简称
        if (isset($reqParams['goods_short_name'])) {
            $where[] = [
                'eg.goods_short_name', 'like', '%' . trim($reqParams['goods_short_name'] . '%')
            ];
        }
        //条码
        if (isset($reqParams['barcode'])) {
            $where[] = [
                'egs.barcode', trim($reqParams['barcode'])
            ];
        }
        //仓库名
        if (isset($reqParams['warehouse_name'])) {
            $where[] = [
                'egs.warehouse_name', trim($reqParams['warehouse_name'])
            ];
        }
        return $where;
    }






}//end of class
