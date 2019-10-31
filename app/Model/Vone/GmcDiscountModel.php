<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GmcDiscountModel extends Model
{
    protected $table = 'gmc_discount as gd';

    //可操作字段
    protected $field = ['gd.id', 'gd.spec_sn', 'gd.method_id', 'gd.channels_id', 'gd.type_id', 'gd.discount'];

    //修改laravel 自动更新
    const UPDATED_AT = "modify_time";
    const CREATED_AT = "create_time";

    /**
     * description:商品渠道追加折扣维护
     * editor:zongxing
     * date : 2019.05.20
     * return Array
     */
    public function uploadGmcDiscountBySpec($upload_goods_info, $dti_info, $param_info)
    {
        //获取商品规格码信息
        $spec_sn_info = [];
        foreach ($upload_goods_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            if (!in_array($spec_sn, $spec_sn_info)) {
                $spec_sn_info[] = $spec_sn;
            }
        }
        //获取已经存的商品特殊折扣
        $method_id = intval($dti_info[0]['method_id']);
        $channels_id = intval($dti_info[0]['channels_id']);
        $type_id = intval($dti_info[0]['id']);
        $start_date = trim($param_info['start_date']);
        $end_date = trim($param_info['end_date']);
        $param = [
            'method_id' => $method_id,
            'channels_id' => $channels_id,
            'type_id' => $type_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
        $gmc_discount_info = $this->gmcDiscountList($spec_sn_info, $param);
        $gmc_discount_list = [];
        foreach ($gmc_discount_info as $k => $v) {
            $gmc_discount_list[$v['spec_sn']] = $v;
        }
        $insertDiscount = [];
        foreach ($upload_goods_info as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            $discount = floatval($v['discount']);
            if (isset($gmc_discount_list[$spec_sn])) {
                //gmc_discount表更新数据
                $id = $gmc_discount_list[$spec_sn]['id'];
                $updateGmcDiscount['discount'][][$id] = $discount;
            } else {
                //gmc_discount表新增数据
                $insertDiscount[] = [
                    'spec_sn' => $spec_sn,
                    'method_id' => $method_id,
                    'channels_id' => $channels_id,
                    'discount' => $discount,
                    'type_id' => $type_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ];
            }
        }
        $updateGmcDiscountSql = '';
        if (!empty($updateGmcDiscount)) {
            //需要判断的字段
            $column = 'id';
            $updateGmcDiscountSql = makeBatchUpdateSql('jms_gmc_discount', $updateGmcDiscount, $column);
        }
        $res = DB::transaction(function () use ($insertDiscount, $updateGmcDiscountSql) {
            //gmc_discount表更新数据
            if (!empty($updateGmcDiscountSql)) {
                $res = DB::update(DB::raw($updateGmcDiscountSql));
            }
            //gmc_discount表新增数据
            if (!empty($insertDiscount)) {
                $res = DB::table('gmc_discount')->insert($insertDiscount);
            }
            return $res;
        });
        return $res;
    }

    /**
     * description:商品渠道追加折扣维护
     * editor:zongxing
     * date : 2019.05.20
     * return Array
     */
    public function uploadGmcDiscountByBrand($res, $dti_info)
    {
        $upload_info = [];
        foreach ($res as $k => $v) {
            if ($k == 0) continue;
            $brand_id = trim($v[0]);
            $upload_info[$brand_id] = floatval($v[1]);
        }
        //获取商品信息
        $brand_info = array_keys($upload_info);
        $gs_model = new GoodsSpecModel();
        $goods_info = $gs_model->getSpecByBrand($brand_info);
        $spec_info = [];
        foreach ($goods_info as $k => $v) {
            $spec_info[] = $v['spec_sn'];
            $brand_id = $v['brand_id'];
            $goods_info[$k]['discount'] = $upload_info[$brand_id];
        }
        $method_id = intval($dti_info[0]['method_id']);
        $channels_id = intval($dti_info[0]['channels_id']);
        $type_id = intval($dti_info[0]['id']);
        //获取已经存的商品特殊折扣
        $param = [
            'method_id' => $method_id,
            'channels_id' => $channels_id,
            'type_id' => $type_id,
        ];
        $gmc_discount_info = $this->gmcDiscountList($spec_info, $param);
        $gmc_discount_list = [];
        if (!empty($gmc_discount_info)) {
            foreach ($gmc_discount_info as $k => $v) {
                $gmc_discount_list[$v['spec_sn']] = $v;
            }
        }
        $insertDiscount = [];
        foreach ($goods_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $discount = number_format($v['discount'], 4);
            if (isset($gmc_discount_list[$spec_sn])) {
                //gmc_discount表更新数据
                $id = $gmc_discount_list[$spec_sn]['id'];
                $updateGmcDiscount['discount'][][$id] = $discount;
            } else {
                //gmc_discount表新增数据
                $insertDiscount[] = [
                    'spec_sn' => $spec_sn,
                    'method_id' => $method_id,
                    'channels_id' => $channels_id,
                    'discount' => $discount,
                    'type_id' => $type_id,
                ];
            }
        }
        $updateGmcDiscountSql = '';
        if (!empty($updateGmcDiscount)) {
            //需要判断的字段
            $column = 'id';
            $updateGmcDiscountSql = makeBatchUpdateSql('jms_gmc_discount', $updateGmcDiscount, $column);
        }
        $res = DB::transaction(function () use ($insertDiscount, $updateGmcDiscountSql) {
            $res = true;
            //gmc_discount表更新数据
            if (!empty($updateGmcDiscountSql)) {
                $res = DB::update(DB::raw($updateGmcDiscountSql));
            }
            //gmc_discount表新增数据
            if (!empty($insertDiscount)) {
                $res = DB::table('gmc_discount')->insert($insertDiscount);
            }
            return $res;
        });
        return $res;
    }

    /**
     * description:获取商品渠道追加折扣
     * editor:zongxing
     * date : 2019.05.21
     * return Array
     */
    public function gmcDiscountList($sum_spec_info = [], $param = [], $type_id_arr = [], $type_cat_arr = [], $is_cost = 0)
    {
        $field = ['gd.id', 'gd.start_date', 'gd.end_date', 'g.goods_name', 'gs.spec_sn', 'gs.erp_merchant_no',
            'gs.erp_ref_no', 'gd.method_id', 'gd.channels_id', 'gd.type_id', 'gd.discount', 'pm.method_name',
            'pc.channels_name', 'dti.type_name', 'dti.type_cat'];
        $gmc_discount_obj = DB::table($this->table)
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'gd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'gd.channels_id')
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'gd.type_id')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'gd.spec_sn')
            ->leftJoin('goods as g', 'g.goods_sn', '=', 'gs.goods_sn');
        if (!empty($sum_spec_info)) {
            $gmc_discount_obj->whereIn('gd.spec_sn', $sum_spec_info);
        }
        if (isset($param['method_id'])) {
            $method_id = intval($param['method_id']);
            $gmc_discount_obj->where('gd.method_id', $method_id);
        }
        if (isset($param['channels_id'])) {
            $channels_id = intval($param['channels_id']);
            $gmc_discount_obj->where('gd.channels_id', $channels_id);
        }
        if (isset($param['type_id'])) {
            $type_id = intval($param['type_id']);
            $gmc_discount_obj->where('gd.type_id', $type_id);
        }
        if (!empty($type_id_arr)) {
            $gmc_discount_obj->where('gd.type_id', $type_id_arr);
        }
        //获取特殊商品成本折扣的条件---start
        if ($is_cost) {
            $gmc_discount_obj->where('dti.is_start', 1);
        }//---end
        if (!empty($type_cat_arr)) {
            $gmc_discount_obj->whereIn('dti.type_cat', $type_cat_arr);
        }
        if (isset($param['start_date'])) {
            $start_date = date('Y-m-d', strtotime(trim($param['start_date'])));
            $gmc_discount_obj->where('gd.start_date', '>=', $start_date);
            $gmc_discount_obj->where('gd.end_date', '>=', $start_date);
        }
        if (isset($param['end_date'])) {
            $end_date = date('Y-m-d', strtotime(trim($param['end_date'])));
            $gmc_discount_obj->where('gd.start_date', '<=', $end_date);
            $gmc_discount_obj->where('gd.end_date', '<=', $end_date);
        }
        if (isset($param['query_sn'])) {
            $query_sn = '%' . trim($param['query_sn']) . '%';
            $gmc_discount_obj->where(function ($where) use ($query_sn) {
                $where->orWhere('pm.method_name', 'like', $query_sn);
                $where->orWhere('pc.channels_name', 'like', $query_sn);
                $where->orWhere('gs.spec_sn', 'like', $query_sn);
                $where->orWhere('gs.erp_merchant_no', 'like', $query_sn);
                $where->orWhere('gs.erp_ref_no', 'like', $query_sn);
            });
        }
        $gmc_discount_info = $gmc_discount_obj->orderBy('spec_sn')->get($field);
        $gmc_discount_info = objectToArrayZ($gmc_discount_info);
        return $gmc_discount_info;
    }

    /**
     * description 获取商品档位折扣数
     * author zhangdong
     * date 2019.07.23
     */
    public function getGoodsGearNum($param_info)
    {
        $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
        $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
        $channelId = intval($param_info['channels_id']);
        $where = [
            ['start_date', '<=', $start_date],
            ['end_date', '>=', $end_date],
            ['channels_id', '=', $channelId],
            ['type_cat', '=', 2],
        ];
        $gears_num = DB::table('discount_type as dt')
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->where($where)
            ->distinct()->get(['dti.id', 'dti.type_name'])->count();
        return $gears_num;
    }

    /**
     * description 获取当月真实的档位对应商品的折扣
     * author zongxing
     * date 2019.08.19
     */
    public function getMonthGearPoints($type_id, $param_info)
    {
        $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
        $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
        $channelId = intval($param_info['channels_id']);
        $where = [
            ['gd.start_date', '>=', $start_date],
            ['gd.end_date', '>=', $start_date],
            ['gd.start_date', '<=', $end_date],
            ['gd.end_date', '<=', $end_date],
            ['gd.channels_id', '=', $channelId],
        ];
        $queryRes = DB::table($this->table)
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'gd.type_id')
            ->where($where)
            ->whereIn('gd.type_id', $type_id)
            ->get(['gd.start_date', 'gd.end_date', 'discount', 'spec_sn', 'type_cat']);
        $queryRes = objectToArrayZ($queryRes);
        return $queryRes;
    }

    /**
     * description:获取商品最终折扣
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function getSpecFinalDiscount($spec_sn_arr, $channels_id, $predict_type_arr, $buy_time)
    {
        //获取商品预计完成档位折扣信息,只需要考虑最终完成的档位折扣,不用考虑成本折扣,因为预计最终完成档位折扣已经把成本折扣的追加点包进去了
        $param = [
            'type_cat' => 12,
            'predict_type_arr' => $predict_type_arr,
            'spec_sn_arr' => $spec_sn_arr,
            'channels_id' => $channels_id,
            'buy_time' => $buy_time,
        ];
        $spec_gear_info = $this->getSpecDiscount($param);
        $sg_format_info = [];
        foreach ($spec_gear_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $sg_format_info[$v['spec_sn']][$pin_str] = $v;
        }
        //获取高价sku追加点
        $param = [
            'type_cat' => 6,
            'spec_sn_arr' => $spec_sn_arr,
            'channels_id' => $channels_id,
            'buy_time' => $buy_time,
        ];
        $spec_high_info = $this->getSpecDiscount($param);
        $sh_format_info = [];
        foreach ($spec_high_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $sh_format_info[$v['spec_sn']][$pin_str] = $v;
        }
        //获取低价sku追加点
        $param = [
            'type_cat' => 7,
            'spec_sn_arr' => $spec_sn_arr,
            'channels_id' => $channels_id,
            'buy_time' => $buy_time,
        ];
        $spec_low_info = $this->getSpecDiscount($param);
        $sl_format_info = [];
        foreach ($spec_low_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $sl_format_info[$v['spec_sn']][$pin_str] = $v;
        }
        $spec_final_discount = $this->createSpecFinalDiscount($sg_format_info, $sh_format_info, $sl_format_info);
        return $spec_final_discount;
    }

    /**
     * description:组装商品最终折扣
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function createSpecFinalDiscount($sg_format_info, $sh_format_info, $sl_format_info)
    {
        $spec_high_info = [];
        if (!empty($sg_format_info)) {
            foreach ($sg_format_info as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $brand_discount = 1 - floatval($v1['spec_discount']);
                    $v1['spec_discount'] = $brand_discount;
                    $spec_high_info[$k][$k1] = $v1;
                }
            }
        }
        if (!empty($sh_format_info)) {
            foreach ($sh_format_info as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $brand_discount = 1 - floatval($v1['spec_discount']);
                    if (isset($spec_info[$k][$k1])) {
                        $old_brand_discount = floatval($spec_high_info[$k][$k1]['spec_discount']);
                        $final_discount = $old_brand_discount > $brand_discount ? $brand_discount : $old_brand_discount;
                        $spec_high_info[$k][$k1]['spec_discount'] = $final_discount;
                    } else {
                        $v1['spec_discount'] = $brand_discount;
                        $spec_high_info[$k][$k1] = $v1;
                    }
                }
            }
        }
        $spec_low_info = $sl_format_info;
        $spec_info = [
            'spec_high_info' => $spec_high_info,
            'spec_low_info' => $spec_low_info,
        ];
        return $spec_info;
    }

    /**
     * description:获取商品最终折扣
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function getSpecDiscount($param)
    {
        $start_date = Carbon::now()->firstOfMonth()->toDateString();
        $end_date = Carbon::now()->endOfMonth()->toDateString();
        $field = [
            'pm.method_sn', 'pm.method_name', 'pm.method_property', 'pc.channels_sn',
            'pc.channels_name', 'pc.is_count_wai', 'gd.discount as spec_discount',
            'type_name', 'gd.spec_sn'
        ];
        $spec_discount_obj = DB::table('gmc_discount as gd')
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'gd.type_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'gd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'gd.channels_id');
        if (!empty($param['buy_time'])) {
            $buy_time = $param['buy_time'];
            $spec_discount_obj->where('gd.start_date', '<=', $buy_time);
            $spec_discount_obj->where('gd.end_date', '>=', $buy_time);
        } else {
            $spec_discount_obj->where('gd.start_date', '=', $start_date);
            $spec_discount_obj->where('gd.end_date', '=', $end_date);
        }
        if (isset($param['spec_sn_arr'])) {
            $spec_sn_arr = $param['spec_sn_arr'];
            $spec_discount_obj->whereIn('gd.spec_sn', $spec_sn_arr);
        }
        if (isset($param['type_cat'])) {
            $type_cat = intval($param['type_cat']);
            $spec_discount_obj->where('dti.type_cat', $type_cat);
        }
        if (isset($param['predict_type_arr'])) {
            $predict_type_arr = $param['predict_type_arr'];
            $spec_discount_obj->whereIn('dti.id', $predict_type_arr);
        }
        if (!empty($param['channels_id'])) {
            $channels_id = intval($param['channels_id']);
            $spec_discount_obj->where('dti.channels_id', $channels_id);
        }
        $spec_discount_info = $spec_discount_obj->orderBy('gd.discount', 'ASC')->get($field);
        $spec_discount_info = objectToArrayZ($spec_discount_info);
        return $spec_discount_info;
    }


    /**
     * description 获取需要报价的商品规格码
     * editor zhangdong
     * date 2019.10.21
     */
    public function getOfferSpecSn($ltTypeId)
    {
        $field = ['spec_sn'];
        $curDay = date('Y-m-d');
//        $curDay = date('Y-m-d',strtotime('-1 day'));
        $where = [
            ['start_date', $curDay],
            ['end_date', $curDay],
            ['type_id', $ltTypeId],
        ];
        $queryRes = DB::table($this->table)->select($field)->where($where)->pluck('spec_sn');
        return $queryRes;
    }





}//end of class
