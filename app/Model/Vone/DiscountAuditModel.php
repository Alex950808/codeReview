<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscountAuditModel extends Model
{
    public $table = 'discount_audit as da';
    private $field = [
        'da.id','da.audit_sn','da.brand_name','da.purchase_method',
        'da.purchase_channel','da.brand_discount','da.shipment',
        'da.create_time',
    ];

    /**
     * description:循环组装discount_audit表的写入数据
     * notice:品牌折扣上传接口专用，请谨慎使用
     * author:zhangdong
     * date:2019.04.03
     */
    public function createArrSaveData($audit_sn, $disAuditData)
    {
        foreach($disAuditData as $key => $value){
            $disAuditData[$key]['audit_sn'] = $audit_sn;
        }
        return $disAuditData;
    }

    /**
     * description:组装单条discount_audit表的写入数据
     * author：zhangdong
     * date : 2019.04.03
     */
    public function createSaveData($audit_sn, $data = [])
    {
        $saveData = [
            'audit_sn' => $audit_sn,
            'brand_name' => trim($data['brand_name']),
            'purchase_method' => trim($data['purchase_method']),
            'purchase_channel' => trim($data['purchase_channel']),
            'brand_discount' => floatval($data['brand_discount']),
            'shipment' => intval($data['shipment']),
        ];
        return $saveData;
    }

    /**
     * description:查询审核单详情数据
     * author：zhangdong
     * date : 2019.04.04
     */
    public function queryAuditDetail($audit_sn)
    {
        $where = [
            ['audit_sn', $audit_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->get();
        return $queryRes;

    }

    /**
     * description:提交品牌折扣数据
     * author：zhangdong
     * date : 2019.04.04
     */
    public function submitBrandDiscount($auditDetail)
    {
        $insertData = [];
        $brandModel = new BrandModel();
        $brandInfo = $brandModel->getBrandInfoInRedis();
        $pmModel = new PurchaseMethodModel();
        $purchaseMethodInfo = $pmModel->purMethodInfo;
        $pcModel = new PurchaseChannelModel();
        $purchaseChannelInfo = $pcModel->getPurChannelInRedis();
        foreach($auditDetail as $value){
            $brand_name = trim($value->brand_name);
            $brandSearch = twoArrayFuzzySearch($brandInfo, 'name', $brand_name);
            $brand_id = isset($brandSearch[0]['brand_id']) ? intval($brandSearch[0]['brand_id']) : 0;

            $purchase_method = trim($value->purchase_method);
            $methodSearch = searchTwoArray($purchaseMethodInfo, $purchase_method, 'method_name');
            $method_id = isset($methodSearch[0]['id']) ? intval($methodSearch[0]['id']) : 0;

            $purchase_channel = trim($value->purchase_channel);
            $channelSearch = searchTwoArray($purchaseChannelInfo, $purchase_channel, 'channels_name');
            $channel_id = isset($channelSearch[0]['id']) ? intval($channelSearch[0]['id']) : 0;
            //查询采购折扣中是否已经存在相应折扣
            $discountModel = new DiscountModel();
            $checkBrandDiscount = $discountModel->checkBrandDiscount($brand_id, $method_id, $channel_id);
            $discount = floatval($value->brand_discount);
            if ($checkBrandDiscount > 0) {
                //更新品牌折扣
                $discountModel->updateDiscount($brand_id, $method_id, $channel_id, $discount);
                continue;
            }
            //组装要新增的品牌折扣数据
            $insertData[] = [
                'brand_id' => $brand_id,
                'method_id' => $method_id,
                'channels_id' => $channel_id,
                'brand_discount' => $discount,
                'shipment' => intval($value->shipment),
            ];
        }//end of foreach
        //保存新增信息
        $insertRes = $discountModel->batchInsertData($insertData);
        return $insertRes;

    }//end of function

    /**
     * description:获取品牌折扣审核详情
     * author：zhangdong
     * date : 2019.04.08
     */
    public function getDiscountAuditDetail($audit_sn)
    {
        $where = [
            ['audit_sn', $audit_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description:修改品牌折扣
     * author：zhangdong
     * date : 2019.04.08
     */
    public function modifyBrandDiscount($detail_id, $brand_discount)
    {
        $where = [
            ['id', $detail_id],
        ];
        $update = [
            'brand_discount' => $brand_discount,
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;
    }



}//end of class
