<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StandbyGoodsModel extends Model
{
    public $table = 'standby_goods as sg';
    private $field = [
        'sg.id', 'sg.goods_name', 'sg.spec_sn', 'sg.platform_barcode',
        'sg.max_num', 'sg.available_num', 'sg.is_purchase',
    ];

    /**
     * description 组装写入数据
     * author zhangdong
     * date 2019.09.16
     */
    public function makeInsertData($params)
    {
        $insertData = [
            'goods_name' => trim($params['goods_name']),
            'spec_sn' => trim($params['spec_sn']),
            'platform_barcode' => trim($params['platform_barcode']),
            'max_num' => trim($params['max_num']),
        ];
        return $insertData;
    }

    /**
     * description 保存数据
     * author zhangdong
     * date 2019.09.16
     */
    public function insertData($insertData)
    {
        $sgTable = cutString($this->table, 0, 'as');
        $insertRes = DB::table($sgTable)->insert($insertData);
        return $insertRes;
    }

    /**
     * description 根据规格码统计常备商品条数
     * author zhangdong
     * date 2019.09.16
     */
    public function countStandbyGoods($specSn)
    {
        $where = [
            ['spec_sn', $specSn],
        ];
        $count = DB::table($this->table)->where($where)->count();
        return $count;
    }

    /**
     * description 校验上传数据
     * author zhangdong
     * date 2019.09.16
     */
    public function checkUploadData($data)
    {
        $newGoods = $existGoods = $errorMaxNum = $standbyGoods = $errorGoodsName = [];
        $gcModel = new GoodsCodeModel();
        foreach ($data as $key => $value) {
            if ($key == 0) {
                continue;
            }
            //检查上传商品数据中是否有新品，如果有则禁止上传
            $platformBarcode = trim($value[1]);
            $specSn = $gcModel->getSpecSn($platformBarcode);
            if (empty($specSn)) {
                $newGoods[] = $key + 1;
                continue;
            }
            //检查上传商品是否已经存在于常备商品中
            $count = $this->countStandbyGoods($specSn);
            if ($count > 0) {
                $existGoods[] = $key + 1;
                continue;
            }
            //检查上传商品数据中最大采购量是否正常，如果有小于或等于0的禁止上传
            $maxNum = intval($value[2]);
            if ($maxNum <= 0) {
                $errorMaxNum[] = $key + 1;
                continue;
            }

            //检查商品名称
            $goodsName = trim($value[0]);
            if (empty($goodsName)) {
                $errorGoodsName[] = $key + 1;
                continue;
            }
            $standbyGoods[] = [
                'goods_name' => trim($value[0]),
                'spec_sn' => $specSn,
                'platform_barcode' => trim($value[1]),
                'max_num' => intval($value[2]),
            ];
        }
        return [
            'newGoods' => $newGoods,
            'existGoods' => $existGoods,
            'errorMaxNum' => $errorMaxNum,
            'standbyGoods' => $standbyGoods,
            'errorGoodsName' => $errorGoodsName,
        ];

    }//end of function checkUploadData

    /**
     * description:获取常备商品列表
     * editor:zongxing
     * date : 2019.09.17
     * return Array
     */
    public function standbyGoodsList($param_info, $is_page = 0, $spec_arr = [])
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $field = $this->field;
        $add_field = [
            'gs.spec_price', 'b.name as brand_name', 'gs.erp_merchant_no', 'gs.erp_ref_no', 'gs.stock_num',
            'gs.erp_prd_no', 'b.brand_id',
        ];
        $field = array_merge($field, $add_field);
        $sg_obj = DB::table('standby_goods as sg')->select($field)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id');
        if (!empty($param_info['query_sn'])) {
            $query_sn = $param_info['query_sn'];
            $query_like_sn = '%' . $query_sn . '%';
            $sg_obj->where(function ($query) use ($query_sn, $query_like_sn) {
                $query->orWhere('g.goods_name', 'like', $query_like_sn);
                $query->orWhere('g.keywords', 'like', $query_like_sn);
                $query->orWhere('g.goods_sn', $query_sn);
                $query->orWhere('gs.spec_sn', $query_sn);
                $query->orWhere('gs.erp_merchant_no', $query_sn);
                $query->orWhere('gs.erp_prd_no', $query_sn);
                $query->orWhere('gs.erp_ref_no', $query_sn);
                $query->orWhere('platform_barcode', $query_sn);
            });
        }
        if (isset($param_info['is_purchase'])) {
            $is_purchase = intval($param_info['is_purchase']);
            $sg_obj->where('sg.is_purchase', $is_purchase);
        }
        if (isset($param_info['no_zero'])) {
            $sg_obj->where('sg.max_num', '!=', 0)
                ->where('sg.max_num', '>', DB::raw('jms_sg.available_num'));
        }
        if (!empty($spec_arr)) {
            $sg_obj->whereIn('sg.spec_sn', $spec_arr);
        }
        if ($is_page) {
            $standby_goods_list = $sg_obj->orderBy('sg.create_time', 'DESC')->paginate($page_size);
        } else {
            $standby_goods_list = $sg_obj->orderBy('sg.create_time', 'DESC')->get();
        }
        $standby_goods_list = objectToArrayZ($standby_goods_list);
        return $standby_goods_list;
    }

    /**
     * description:获取常备商品详情
     * editor:zongxing
     * date : 2019.09.17
     * return Array
     */
    public function standbyGoodsInfo($param_info)
    {
        $id = $param_info['id'];
        $field = $this->field;
        $add_field = [
            'gs.spec_price', 'b.name as brand_name', 'gs.erp_merchant_no', 'gs.erp_ref_no', 'gs.stock_num',
        ];
        $field = array_merge($field, $add_field);
        $sg_obj = DB::table('standby_goods as sg')->select($field)
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'sg.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id');
        $standby_goods_info = $sg_obj->where('id', $id)->first();
        $standby_goods_info = objectToArrayZ($standby_goods_info);
        return $standby_goods_info;
    }

    /**
     * description:编辑常备商品
     * editor:zongxing
     * date : 2019.09.17
     * return Array
     */
    public function doEditStandbyGoods($param_info, $standby_goods_info)
    {
        $id = $param_info['id'];
        $update_data = [];
        if (isset($param_info['max_num'])) {
            $max_num = intval($param_info['max_num']);
            $available_num = intval($standby_goods_info['available_num']);
            $is_purchase = 1;
            if ($max_num <= $available_num) {
                $is_purchase = 0;
            }
            $update_data = [
                'max_num' => $max_num,
                'is_purchase' => $is_purchase,
            ];
        }
        if (isset($param_info['is_purchase'])) {
            $is_purchase = intval($param_info['is_purchase']);
            $update_data['is_purchase'] = $is_purchase;
        }
        $res = DB::table('standby_goods')->where('id', $id)->update($update_data);
        return $res;
    }


}//end of class
