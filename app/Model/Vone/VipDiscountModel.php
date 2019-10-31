<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VipDiscountModel extends Model
{
    protected $table = 'vip_discount';

    //可操作字段
    protected $fillable = ['id', 'brand_id', 'method_id', 'channels_id', 'brand_discount', 'shipment', 'brand_id'];

    //修改laravel 自动更新
    const UPDATED_AT = 'modify_time';
    const CREATED_AT = 'create_time';

    /**
     * description:获取系统现有的vip折扣信息
     * editor:zongxing
     * date : 2018.12.26
     * return Array
     */
    public function getVipDiscountList()
    {
        $vip_discount_list = DB::table('vip_discount')
            ->get(['id', 'brand_id', 'method_id', 'channels_id']);
        $vip_discount_list = objectToArrayZ($vip_discount_list);
        $discount_list = [];
        foreach ($vip_discount_list as $k => $v) {
            $pin_str = $v["brand_id"] . "-" . $v["method_id"] . "-" . $v["channels_id"];
            $discount_list[$pin_str] = $v["id"];
        }
        return $discount_list;
    }

    /**
     * description:插入上传的采购vip折扣数据
     * editor:zongxing
     * date : 2018.12.26
     * params: 1.需要上传的采购折扣数据数组:$discountData;
     * return Array
     */
    public function discountChange($discountData, $discount_sn)
    {
        $insertDiscount = [];
        $updateDiscount = [];
        $insertDiscountLog = [];
        foreach ($discountData as $discountInfo) {
            if ($discountInfo["action"] == "insert") {
                unset($discountInfo["action"]);
                unset($discountInfo["id"]);
                $insertDiscount[] = $discountInfo;
            } elseif ($discountInfo["action"] == "update") {
                unset($discountInfo["action"]);
                $id = $discountInfo["id"];
                $brand_discount = $discountInfo["brand_discount"];
                $updateDiscount['brand_discount'][] = [
                    $id => $brand_discount
                ];
            }
            unset($discountInfo["id"]);
            $discountInfo["discount_sn"] = $discount_sn;
            $insertDiscountLog[] = $discountInfo;
        }
        $updateDiscountSql = '';
        if (!empty($updateDiscount)) {
            //需要判断的字段
            $column = 'id';
            $updateDiscountSql = makeBatchUpdateSql('jms_vip_discount', $updateDiscount, $column);
        }
        $return_info = DB::transaction(function () use ($insertDiscount, $insertDiscountLog, $updateDiscountSql) {
            //更新品牌折扣表数据
            if (!empty($insertDiscount)) {
                DB::table("vip_discount")->insert($insertDiscount);
            }
            //更新品牌折扣表数据
            if (!empty($update_discount_sql)) {
                DB::update(DB::raw($update_discount_sql));
            }
            //更新品牌折扣日志表数据
            $insert_res = DB::table("discount_log")->insert($insertDiscountLog);
            return $insert_res;
        });
        return $return_info;
    }

    /**
     * description:获取当前采购vip折扣数据
     * editor:zongxing
     * date : 2018.12.26
     * return Array
     */
    public function getVipDiscountCurrent($search_info = null)
    {
        $fields = ['b.name', 'name_en', 'method_sn', 'method_name', 'brand_discount', 'channels_sn', 'channels_name',
            'b.brand_id', 'shipment', 'method_property'];
        $vip_discount = DB::table('vip_discount as vd');
        $vip_discount->leftJoin('brand as b', 'b.brand_id', '=','vd.brand_id');
        $vip_discount->leftJoin('purchase_method as pm', 'pm.id', '=','vd.method_id');
        $vip_discount->leftJoin('purchase_channels as pc', 'pc.id', '=','vd.channels_id');
        if (!empty($search_info['brand_name'])) {
            $brand_name = trim($search_info['brand_name']);
            $brand_name = '\'%%' . $brand_name . '%%\'';
            $vip_discount->where('b.name', 'LIKE', $brand_name);
        }
        if (!empty($search_info['method_info'])) {
            $method_info = json_decode($search_info["method_info"]);
            $method_id = '';
            foreach ($method_info as $k => $v) {
                $method_id .= "'" . $v . "',";
            }
            $method_id = substr($method_id, 0, -1);
            $vip_discount->whereIn('d.method_id', $method_id);
        }
        if (!empty($search_info['channels_info'])) {
            $channels_info = json_decode($search_info["channels_info"]);
            $channels_id = '';
            foreach ($channels_info as $k => $v) {
                $channels_id .= "'" . $v . "',";
            }
            $channels_id = substr($channels_id, 0, -1);
            $vip_discount->whereIn('d.channels_id', $channels_id);
        }
        $vip_discount->orderBy(DB::raw('CONVERT(jms_b.name USING gbk)'),'asc');
        $vip_discount_list = $vip_discount->get($fields);
        $vip_discount_list = objectToArrayZ($vip_discount_list);
        return $vip_discount_list;
    }


}
