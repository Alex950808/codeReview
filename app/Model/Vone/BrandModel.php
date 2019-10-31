<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Redis;

class BrandModel extends Model
{

    protected $table = 'brand as b';

    //可操作字段
    protected $field = [
        'b.brand_id', 'b.name', 'b.name_en', 'b.name_cn', 'b.name_alias', 'b.discount',
        'b.keywords',
    ];

    /**
     * description:检查上传VIP折扣信息中的品牌信息
     * author:zongxing
     * date : 2018.12.26
     * return Array
     */
    public function checkVipDiscountBrand($res)
    {
        $brand_total_info = DB::table('brand')->pluck('name', 'brand_id');
        $brand_total_info = objectToArrayZ($brand_total_info);
        //判断品牌是否存在
        $brand_list = [];
        $diff_brand = '';
        $total_brand_str = '';
        foreach ($res as $k => $v) {
            if (!empty($v[1])) {
                $brand_name = trim($v[1]);
                foreach ($brand_total_info as $k1 => $v1) {
                    if (strpos($v1, $brand_name) !== false) {
                        $brand_list[$brand_name] = $k1;
                    }
                    $total_brand_str .= $v1;
                }
                if (strpos($total_brand_str, $v[1]) === false) {
                    $diff_brand .= $v[1] . ",";
                }
            }
        }
        $return_info['diff_brand'] = $diff_brand;
        $return_info['brand_list'] = $brand_list;
        return $return_info;
    }


    /**
     * description:获取品牌信息
     * author：zhangdong
     * date : 2019.02.14
     */
    public function getBrandInfo($value = '', $type = '')
    {
        $where = [];
        //根据品牌id查询
        if ($type == 1) {
            $value = intval($value);
            $where = [
                ['brand_id',$value],
            ];
        }
        //根据品牌关键字查询
        if ($type == 2) {
            $value = trim($value);
            $where = [
                ['keywords','like','%' . $value . '%'],
            ];
        }
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->get();
        return $queryRes;
    }


    /**
     * @description:从redis中获取品牌信息
     * @editor:张冬
     * @date : 2019.03.29
     * @return object
     */
    public function getBrandInfoInRedis()
    {
        //从redis中获取品牌信息，如果没有则对其设置
        $brandInfo = Redis::get('brandInfo');
        if (empty($brandInfo)) {
            $field = ['brand_id','name'];
            $brandInfo = DB::table($this->table) -> select($field)-> get()
                -> map(function ($value){
                    return (array) $value;
                }) -> toArray();
            Redis::set('brandInfo', json_encode($brandInfo, JSON_UNESCAPED_UNICODE));
            $brandInfo = Redis::get('brandInfo');
        }
        $brandInfo = objectToArray(json_decode($brandInfo));
        return $brandInfo;
    }


    /**
     * @description 获取品牌成本折扣
     * @editor zhangdong
     * @date 2019.10.29
     */
    public function getCostDiscount()
    {
        $startDate = date('Y-m');
        $field = [
            'b.brand_id','b.name','pc.channels_name',
            'd.channels_id','dt.discount'
        ];
        $where = [
            ['dti.is_start', 1],
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('discount AS d', 'd.brand_id', 'b.brand_id')
            ->leftJoin('discount_type as dt', function($join) use ($startDate) {
                $dt_on_where = [
                    ['dt.start_date', 'like', $startDate . '%']
                ];
                $join->on('d.id','dt.discount_id')->where($dt_on_where);
            })
            ->leftJoin('discount_type_info as dti', 'dti.id', 'dt.type_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', 'd.channels_id')
            ->where($where)
            ->orderBy('b.brand_id','asc')->orderBy('dt.discount', 'asc')
            ->get();
        return $queryRes;
    }

    /**
     * @description 获取品牌追加折扣
     * @editor zhangdong
     * @date 2019.10.29
     */
    public function getAppendDiscount()
    {
        $startDate = date('Y-m');
        $field = [
            'b.brand_id','d.channels_id','dt.discount'
        ];
        $where = [
            ['dti.', 1],
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('discount AS d', 'd.brand_id', 'b.brand_id')
            ->leftJoin('discount_type as dt', function($join) use ($startDate) {
                $dt_on_where = [
                    ['dt.start_date', 'like', $startDate . '%']
                ];
                $join->on('d.id','dt.discount_id')->where($dt_on_where);
            })
            ->leftJoin('discount_type_info as dti', 'dti.id', 'dt.type_id')
            ->whereIn('dti.type_cat',[4,5])
            ->get();
        return $queryRes;
    }

    /**
     * @description 获取品牌折扣
     * @editor zhangdong
     * @date 2019.10.29
     */
    public function getBrandDiscount()
    {
        //获取品牌成本折扣
        $costDiscount = $this->getCostDiscount();
        //获取品牌追加折扣
        $appendDiscount = $this->getAppendDiscount();
        //将追加折扣推入品牌折扣数据中
        foreach ($costDiscount as $key => $value) {
            $brandId = intval($value->brand_id);
            $channelId = intval($value->channels_id);
            $costDiscount[$key]->ext_no = $brandId . $channelId;
            foreach ($appendDiscount as $adKey => $adValue) {
                $adBrandId = intval($adValue->brand_id);
                $adChannelId = intval($adValue->channels_id);
                $costDiscount[$key]->appendDiscount = 0;
                if ($brandId == $adBrandId && $channelId == $adChannelId) {
                    $costDiscount[$key]->appendDiscount = $adValue->discount;
                    unset($appendDiscount[$adKey]);
                    break;
                }
            }
        }
        //将最终数据写入Redis中
        $brandDiscount = objectToArray($costDiscount);
        Redis::set('brandDiscount', json_encode($brandDiscount, JSON_UNESCAPED_UNICODE));
        return json_encode($brandDiscount, JSON_UNESCAPED_UNICODE);

    }

    /**
     * @description 获取品牌折扣
     * @editor zhangdong
     * @date 2019.10.29
     */
    public function getBrandList($reqParams)
    {
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        //组装查询条件
        $where = $this->makeWhere($reqParams);
        $where[] = ['dti.is_start', 1];
        $startDate = date('Y-m');
        $dt_on_where = [
            ['dt.start_date', 'like', $startDate . '%']
        ];
        $field = ['b.brand_id','b.name'];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('discount AS d', 'd.brand_id', 'b.brand_id')
            ->leftJoin('discount_type as dt', function($join) use ($dt_on_where) {
                $join->on('d.id','dt.discount_id')->where($dt_on_where);
            })
            ->leftJoin('discount_type_info as dti', 'dti.id', 'dt.type_id')
            ->where($where)->groupBy('b.brand_id')
            ->paginate($pageSize);
        return $queryRes;
    }

    /**
     * @description 组装品牌折扣数据
     * @editor zhangdong
     * @date 2019.10.30
     */
    public function makeBrandDiscount($brandList)
    {
        //从redis中获取品牌的折扣信息
        $brandDiscount = Redis::get('brandDiscount');
        if(strlen($brandDiscount) <= 3){
            //获取品牌折扣：成本折扣和追加折扣,并将其存入redis
            $brandDiscount = $this->getBrandDiscount();
        }
        $arrBrandDiscount = json_decode($brandDiscount);
        foreach ($brandList as $key => $value) {
            $brandId = intval($value->brand_id);
            $discountInfo = [];
            foreach ($arrBrandDiscount as $bdKey => $bdValue) {
                $bdBrandId = intval($bdValue->brand_id);
                if($brandId == $bdBrandId){
                    //删除折扣信息中没用的字段
                    unset($bdValue->brand_id,$bdValue->name,$bdValue->channels_id);
                    $discountInfo[] = $bdValue;
                    //如果查到数据则将该条数据从总数据中删除
                    unset($arrBrandDiscount[$bdKey]);
                }
            }
            $brandList[$key]->discountInfo = $discountInfo;
        }
        return $brandList;
    }

    /**
     * description:查询订单-组装查询条件
     * editor:zhangdong
     * date:2018.12.08
     */
    protected function makeWhere($reqParams)
    {
        $where = [];
        //MIS订单编号
        if (isset($reqParams['brand_name'])) {
            $where[] = [
                'b.name', 'like', '%'.trim($reqParams['brand_name'] . '%')
            ];
        }
        return $where;
    }






















}//end of class
