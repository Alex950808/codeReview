<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscountTypeModel extends Model
{
    protected $table = 'discount_type as dt';

    //可操作字段
    protected $field = ['dt.id', 'dt.start_date', 'dt.end_date', 'dt.discount_id', 'dt.discount', 'dt.type_id'];

    //免税店编码对照 zhangdong 2019.08.23
    private $DFS_sn = [
        'abk' => 'QD-024-FS-001',
        'lt' => 'QD-002-FS-001',
        'xl' => 'QD-004-FS-001',
    ];


    //修改laravel 自动更新
    const UPDATED_AT = "modify_time";
    const CREATED_AT = "create_time";

    /**
     * description:维护折扣类型对应的折扣
     * editor:zongxing
     * date : 2019.05.05
     * return Array
     */
    public function doUploadDiscountType($upload_info, $discount_type_info, $param_info)
    {
        $method_id = intval($param_info['method_id']);
        $channels_id = intval($param_info['channels_id']);
        $type_id = intval($param_info['type_id']);
        $type_cat = intval($discount_type_info[0]['type_cat']);
        //获取品牌、方式、渠道组合id
        $brand_id = array_keys($upload_info);
        $discount_model = new DiscountModel();
        $discount_id_info = $discount_model->getDiscountIdInfo($brand_id, $method_id, $channels_id);
        //折扣表新增数据
        $cost_discount_id = $type_cat == 1 ? $type_id : 0;
        $vip_discount_id = $type_cat == 2 ? $type_id : 0;
        $insertDiscount = [];
        foreach ($upload_info as $k => $v) {
            $discount = number_format($v, 4);
            //discount表新增数据
            if (!isset($discount_id_info[$k])) {
                $insertDiscount[] = [
                    'brand_id' => $k,
                    'method_id' => $method_id,
                    'channels_id' => $channels_id,
                    'brand_discount' => $discount,
                    'cost_discount_id' => $cost_discount_id,
                    'vip_discount_id' => $vip_discount_id,
                ];
            }
        }
        //新增品牌渠道方式组合
        if (!empty($insertDiscount)) {
            $res = DB::table('discount')->insert($insertDiscount);
            if ($res == false) {
                return $res;
            }
        }

        //获取更新后的品牌、方式、渠道组合id
        $brand_id = array_keys($upload_info);
        $discount_model = new DiscountModel();
        $discount_id_info = $discount_model->getDiscountIdInfo($brand_id, $method_id, $channels_id);

        //获取档位折扣信息
        $field_name = 'discount_id';
        $discount_brand_info = $this->getDiscountTypeInfo($param_info, $field_name);
        //组装档位折扣信息
        $insertDiscountType = [];
        $updateDiscountType = [];
        foreach ($upload_info as $k => $v) {
            //discount_type表新增和更新数据
            $discount_id = intval($discount_id_info[$k]);
            $discount = number_format($v, 4);
            if (isset($discount_brand_info[$discount_id]) && $discount_brand_info[$discount_id] != $discount) {
                $updateDiscountType['discount'][] = [
                    $discount_id => $discount
                ];
            } elseif (!isset($discount_brand_info[$discount_id])) {
                $insertDiscountType[] = [
                    'start_date' => trim($param_info['start_date']),
                    'end_date' => trim($param_info['end_date']),
                    'discount_id' => $discount_id,
                    'discount' => $discount,
                    'type_id' => $type_id,
                ];
            }
        }
        $updateDiscountTypeSql = '';
        if (!empty($updateDiscountType)) {
            //更新条件
            $where = [
                'type_id' => $type_id,
                'start_date' => trim($param_info['start_date']),
                'end_date' => trim($param_info['end_date']),
            ];
            //需要判断的字段
            $column = 'discount_id';
            $updateDiscountTypeSql = makeBatchUpdateSql('jms_discount_type', $updateDiscountType, $column, $where);
        }
        $Res = DB::transaction(function () use ($insertDiscountType, $updateDiscountTypeSql, $param_info) {
            $res = true;
            //档位折扣表新增数据
            if (!empty($insertDiscountType)) {
                $res = DB::table('discount_type')->insert($insertDiscountType);
            }
            //档位折扣表更新数据
            if (!empty($updateDiscountTypeSql)) {
                $res = DB::update(DB::raw($updateDiscountTypeSql));
            }
            $is_exw = intval($param_info['is_exw']);
            if ($is_exw == 1) {
                //获取折扣类型信息
                $dt_model = new DiscountTypeModel();
                $field_name = 'brand_id';
                $brand_info = $dt_model->getDiscountTypeInfo($param_info, $field_name);
                $discount_model = new DiscountModel();
                $res = $discount_model->setExwDiscount($param_info, $brand_info);
            }
            return $res;
        });
        return $Res;
    }

    /**
     * description:获取指定档位折扣信息
     * editor:zongxing
     * date : 2019.05.05
     * return Array
     */
    public function getDiscountTypeInfo($param_info, $field_name)
    {
        $start_date = Carbon::now()->firstOfMonth()->toDateString();
        $end_date = Carbon::now()->endOfMonth()->toDateString();
        if (isset($param_info['start_date'])) {
            $start_date = trim($param_info['start_date']);
        }
        if (isset($param_info['end_date'])) {
            $end_date = trim($param_info['end_date']);
        }
        $where = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'type_id' => trim($param_info['type_id'])
        ];
        $discount_brand_obj = DB::table($this->table)
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->where($where);
        if (!empty($discount_id)) {
            $discount_brand_obj->where('discount_id', $discount_id);
        }
        $discount_brand_info = $discount_brand_obj->pluck('discount', $field_name);
        $discount_brand_info = objectToArrayZ($discount_brand_info);
        return $discount_brand_info;
    }

    /**
     * description 获取指定档位折扣信息
     * editor zongxing
     * date 2019.10.22
     * return Array
     */
    public function getExwDiscountInfo($brand_id_arr)
    {
        $exw_discount_info = DB::table('discount_type_info as dti')
            ->leftJoin('discount_type as dt', 'dt.type_id', '=', 'dti.id')
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->where('exw_type', 1)
            ->whereIn('d.brand_id', $brand_id_arr)
            ->pluck('dt.discount', 'd.brand_id');
        $exw_discount_info = objectToArrayZ($exw_discount_info);
        return $exw_discount_info;
    }

    /**
     * description 获取当月真实的档位对应的各个品牌的折扣
     * author zongxing
     * date 2019.07.25
     */
    public function getMonthGearPoints($type_id, $start_date, $end_date)
    {
        $where = [
            ['dt.start_date', '>=', $start_date],
            ['dt.end_date', '>=', $start_date],
            ['dt.start_date', '<=', $end_date],
            ['dt.end_date', '<=', $end_date],
        ];
        $queryRes = DB::table($this->table)
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->where($where)
            ->whereIn('dt.type_id', $type_id)
            ->pluck('dt.discount', 'd.brand_id');
        $queryRes = objectToArrayZ($queryRes);
        return $queryRes;
    }

    /**
     * description 获取当月真实的档位对应的各个品牌的折扣
     * author zongxing
     * date 2019.07.25
     */
    public function getMonthGearPoints1_stop($channelId, $start_date, $end_date, $type_cat, $performance = 0)
    {
        $where = [
            ['dt.start_date', '<=', $start_date],
            ['dt.end_date', '>=', $end_date],
            ['dti.channels_id', '=', $channelId],
            ['dti.type_cat', '=', $type_cat],
        ];
        if ($performance) {
            $add_where = [
                ['dti.min', '<=', $performance],
                ['dti.max', '>=', $performance],
            ];
            $where = array_merge($where, $add_where);
        }
        $queryRes = DB::table($this->table)
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->where($where)
            ->pluck('discount', 'brand_id');
        $queryRes = objectToArrayZ($queryRes);
        return $queryRes;
    }

    /**
     * description 获取当月品牌活动对应的品牌信息
     * author zongxing
     * date 2019.08.12
     */
    public function getMonthGearBrand($type_id, $start_date, $end_date)
    {
        $where = [
            ['dt.start_date', '<=', $start_date],
            ['dt.end_date', '>=', $end_date],
        ];
        $queryRes = DB::table($this->table)
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->where($where)
            ->whereIn('dt.type_id', $type_id)
            ->get(['discount', 'brand_id', 'type_id'])->groupBy('type_id');
        $queryRes = objectToArrayZ($queryRes);
        return $queryRes;
    }


    /**
     * description 获取当月真实的档位对应的各个品牌的折扣
     * author zongxing
     * date 2019.07.25
     */
    public function getTimeMonthGear($channelId, $type_cat, $start_date, $end_date, $month_type_arr)
    {
        $where = [
            ['dt.start_date', '>=', $start_date],
            ['dt.end_date', '>=', $start_date],
            ['dt.start_date', '<=', $end_date],
            ['dt.end_date', '<=', $end_date],
            ['dti.channels_id', '=', $channelId],
            ['dti.method_id', '=', 34],//线上
            ['dti.type_cat', '=', $type_cat],
        ];
        $field = [
            'dt.type_id', 'dt.start_date', 'dt.end_date', 'dt.discount', 'dti.min', 'dti.max', 'd.brand_id'
        ];
        $queryRes = DB::table($this->table)
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->where($where)
            ->whereIn('dti.id', $month_type_arr)
            ->get($field);
        $queryRes = objectToArrayZ($queryRes);
        return $queryRes;
    }

    /**
     * description:获取品牌采购折扣列表
     * editor:zongxing
     * date : 2019.05.06
     * return Array
     */
    public function discountTotalList($param_info)
    {
        $start_date = Carbon::now()->firstOfMonth()->toDateString();
        $end_date = Carbon::now()->endOfMonth()->toDateString();
        if (isset($param_info['start_date'])) {
            $start_date = trim($param_info['start_date']);
        }
        if (isset($param_info['end_date'])) {
            $end_date = trim($param_info['end_date']);
        }
        $where1 = [
            ['dt.start_date', '<=', $start_date],
            ['dt.end_date', '>=', $end_date],
        ];

        $field = $this->field;
        $add_field = [
            'pm.method_name', 'pc.channels_name', 'dti.type_name',
            'b.name', 'dt.discount', 'dt.start_date', 'dt.end_date', 'cat_name',
            DB::raw('(CASE jms_dti.is_start
            WHEN 1 THEN "是"
            WHEN 0 THEN "否"
            END) is_start'),
        ];
        $field = array_merge($field, $add_field);
        $discount_obj = DB::table($this->table)
            ->leftJoin('discount as d', function ($join) {
                $join->on('d.id', '=', 'dt.discount_id');
            })
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'd.channels_id')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'd.brand_id')
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->leftJoin('discount_cat as dc', 'dc.id', '=', 'dti.type_cat')
            ->where(function ($join) use ($where1) {
                $join->where($where1);
            });
        if (isset($param_info['query_sn'])) {
            $query_sn = trim($param_info['query_sn']);
            $query_sn = '%' . $query_sn . '%';
            $discount_obj->where(function ($join) use ($query_sn) {
                $join->orWhere('pc.channels_name', 'like', $query_sn)
                    ->orWhere('pm.method_name', 'like', $query_sn)
                    ->orWhere('b.name', 'like', $query_sn)
                    ->orWhere('b.keywords', 'like', $query_sn);
            });
        }
        $discount_total_list = $discount_obj->get($field);
        $discount_total_list = objectToArrayZ($discount_total_list);
        return $discount_total_list;
    }

    /**
     * description 获取当前采购最终折扣数据
     * editor zongxing
     * date 2019.05.14
     * return Array
     * param:
     *       $goods_info:array:1 [
     * 'spec_sn' => '1234',
     * 'brand_id' => '1234',
     * ]
     *  $channels_id:渠道id
     */
    public function getFinalDiscount($goods_info, $channels_id = 0, $buy_time = '')
    {
        //收集商品的品牌id和spec_sn信息
        $brand_id_arr = [];
        $spec_sn_arr = [];
        foreach ($goods_info as $k => $v) {
            $brand_id = intval($v['brand_id']);
            $spec_sn = trim($v['spec_sn']);
            if (!in_array($brand_id, $brand_id_arr)) {
                $brand_id_arr[] = $brand_id;
            }
            if (!in_array($spec_sn, $spec_sn_arr)) {
                $spec_sn_arr[] = $spec_sn;
            }
        }
        $start_date = Carbon::now()->firstOfMonth()->toDateString();
        $end_date = Carbon::now()->endOfMonth()->toDateString();
        //获取当月配置表
        $param = [
            'buy_time' => $buy_time,
            'channels_id' => $channels_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
        //获取选择时间对应的生成毛利所需配置
        $dtr_model = new DiscountTypeRecordModel();
        $dtr_info = $dtr_model->getDiscountTypeRecordList($param);
        $dtr_info = $dtr_info['data'];
        $month_type_arr = [];
        $predict_type_arr = [];
        foreach ($dtr_info as $k => $v) {
            $month_type_id = explode(',', $v['month_type_id']);
            $predict_id = explode(',', $v['predict_id']);
            $brand_month_predict_id = explode(',', $v['brand_month_predict_id']);
            $month_type_arr = array_merge($month_type_arr, $month_type_id);
            $predict_type_arr = array_merge($predict_type_arr, $predict_id);
            $predict_type_arr = array_merge($predict_type_arr, $brand_month_predict_id);
        }
        //获取品牌成本折扣
        $param = [
            'channels_id' => $channels_id,
            'type_id' => $month_type_arr,
            'buy_time' => $buy_time,
        ];
        $discountModel = new DiscountModel();
        $discount_list = $discountModel->getTotalDiscount($param, $month_type_arr);
        //获取品牌追加折扣
        $brand_finnal_discount = $this->getBrandFinalDiscount($brand_id_arr, $channels_id, $discount_list,
            $month_type_arr, $predict_type_arr, $buy_time);
        //获取商品最终折扣
        $gmc_model = new GmcDiscountModel();
        $spec_finnal_discount = $gmc_model->getSpecFinalDiscount($spec_sn_arr, $channels_id, $predict_type_arr, $buy_time);
        $return_info = $this->createFinalDiscount($goods_info, $discount_list, $brand_finnal_discount, $spec_finnal_discount);
        return $return_info;
    }

    /**
     * description:组装商品最终折扣
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function createFinalDiscount($goods_info, $discount_list, $brand_finnal_discount, $spec_finnal_discount)
    {
        $spec_high_info = $spec_finnal_discount['spec_high_info'];//高价sku
        $spec_low_info = $spec_finnal_discount['spec_low_info'];//低价sku
        foreach ($goods_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $brand_id = $v['brand_id'];
            if (isset($discount_list[$brand_id])) {
                $goods_info[$k]['channels_info'] = $discount_list[$brand_id];
            }
            if (isset($spec_high_info[$spec_sn])) {
                foreach ($spec_high_info[$spec_sn] as $k2 => $v2) {
                    if (isset($goods_info[$k]['channels_info'][$k2])) {
                        $goods_info[$k]['channels_info'][$k2]['brand_discount'] = $v2['spec_discount'];
                        $goods_info[$k]['channels_info'][$k2]['high_discount'] = $v2['spec_discount'];
                    } else {
                        $v2['brand_discount'] = $v2['spec_discount'];
                        $v2['high_discount'] = $v2['spec_discount'];
                        $goods_info[$k]['channels_info'][$k2] = $v2;
                    }
                }
            }
            if (isset($spec_low_info[$spec_sn])) {
                foreach ($spec_low_info[$spec_sn] as $k1 => $v1) {
                    if (isset($goods_info[$k]['channels_info'][$k1])) {
                        $goods_info[$k]['channels_info'][$k1]['brand_discount'] -= floatval($v1['spec_discount']);
                    }
                }
            }
            if (isset($brand_finnal_discount[$brand_id])) {
                foreach ($brand_finnal_discount[$brand_id] as $k1 => $v1) {
                    $pin_str = $v1['channels_sn'] . '-' . $v1['method_sn'];
                    $brand_discount = floatval($v1['brand_discount']);
                    if (isset($goods_info[$k]['channels_info'][$pin_str]) && !isset($spec_high_info[$spec_sn][$pin_str]) &&
                        !isset($spec_low_info[$spec_sn][$pin_str])
                    ) {
                        $goods_info[$k]['channels_info'][$pin_str]['brand_discount'] = $brand_discount;
                    }
                }
            }
        }

        foreach ($goods_info as $k => $v) {
            if(isset($v['channels_info'])){
                $channel_info = $v['channels_info'];
                $sort_data = [];
                foreach ($v['channels_info'] as $k1 => $v1) {
                    $sort_data[] = $v1['brand_discount'];
                }
                array_multisort($sort_data, SORT_ASC, SORT_NUMERIC, $channel_info);
                $goods_info[$k]['channels_info'] = $channel_info;
            }
        }
        return $goods_info;
    }

    /**
     * description:获取品牌最终折扣
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function getBrandFinalDiscount($brand_id_arr, $channels_id, $discount_list, $month_type_arr,
                                          $predict_type_arr, $buy_time)
    {
        //获取品牌预计完成档位折扣信息,只需要考虑最终完成的档位折扣,不用考虑成本折扣,因为预计最终完成档位折扣已经把成本折扣的追加点包进去了
        $param = [
            'type_cat' => 2,
            'predict_type_arr' => $predict_type_arr,
            'brand_id_arr' => $brand_id_arr,
            'channels_id' => $channels_id,
            'buy_time' => $buy_time,
        ];
        $brand_predict_info = $this->getBrandDiscount($param);
        $bd_format_info = [];
        foreach ($brand_predict_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $bd_format_info[$v['brand_id']][$pin_str] = $v;
        }
        //获取品牌活动追加点
        $param = [
            'type_cat' => 4,
            'month_type_arr' => $month_type_arr,
            'brand_id_arr' => $brand_id_arr,
            'channels_id' => $channels_id,
            'buy_time' => $buy_time,
        ];
        $brand_month_info = $this->getBrandDiscount($param);
        $bm_format_info = [];
        foreach ($brand_month_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $bm_format_info[$v['brand_id']][$pin_str] = $v;
        }
        //获取品牌活动档位追加
        $param = [
            'type_cat' => 9,
            'predict_type_arr' => $predict_type_arr,
            'brand_id_arr' => $brand_id_arr,
            'channels_id' => $channels_id,
            'buy_time' => $buy_time,
        ];
        $brand_gear_info = $this->getBrandDiscount($param);
        $bg_format_info = [];
        foreach ($brand_gear_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $bg_format_info[$v['brand_id']][$pin_str] = $v;
        }
        //获取品牌HDW活动追加
        $param = [
            'type_cat' => 10,
            'month_type_arr' => $month_type_arr,
            'brand_id_arr' => $brand_id_arr,
            'channels_id' => $channels_id,
            'buy_time' => $buy_time,
        ];
        $brand_hdw_info = $this->getBrandDiscount($param);
        $bh_format_info = [];
        foreach ($brand_hdw_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $bh_format_info[$v['brand_id']][$pin_str] = $v;
        }
        $brand_final_discount = $this->createBrandFinalDiscount($bd_format_info, $bm_format_info, $bg_format_info,
            $bh_format_info, $discount_list);
        return $brand_final_discount;
    }

    /**
     * description:组装品牌最终折扣
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function createBrandFinalDiscount($bd_format_info, $bm_format_info, $bg_format_info, $bh_format_info,
                                             $discount_list)
    {
        $total_discount_info = [];
        foreach ($discount_list as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (isset($total_discount_info[$k][$k1])) {
                    $total_discount_info[$k][$k1]['brand_discount'] = floatval($discount_list[$k][$k1]['brand_discount']);
                } else {
                    $total_discount_info[$k][$k1] = $v1;
                }
            }
        }
        foreach ($bm_format_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (isset($total_discount_info[$k][$k1])) {
                    $total_discount_info[$k][$k1]['brand_discount'] -= floatval($bm_format_info[$k][$k1]['brand_discount']);
                } else {
                    $total_discount_info[$k][$k1] = $v1;
                }
            }
        }
        foreach ($bg_format_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (isset($total_discount_info[$k][$k1])) {
                    $total_discount_info[$k][$k1]['brand_discount'] -= floatval($bg_format_info[$k][$k1]['brand_discount']);
                } else {
                    $total_discount_info[$k][$k1] = $v1;
                }
            }
        }
        foreach ($bd_format_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (isset($total_discount_info[$k][$k1])) {
                    $old_discount = floatval($total_discount_info[$k][$k1]);
                    $new_discount = floatval($bd_format_info[$k][$k1]['brand_discount']);
                    if ($new_discount < $old_discount) {
                        $total_discount_info[$k][$k1]['brand_discount'] = floatval($bd_format_info[$k][$k1]['brand_discount']);
                    }
                } else {
                    $total_discount_info[$k][$k1] = $v1;
                }
            }
        }
        foreach ($bh_format_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (isset($total_discount_info[$k][$k1])) {
                    $old_discount = floatval($total_discount_info[$k][$k1]);
                    $new_discount = floatval($bh_format_info[$k][$k1]['brand_discount']);
                    if ($new_discount < $old_discount) {
                        $total_discount_info[$k][$k1]['brand_discount'] = floatval($bh_format_info[$k][$k1]['brand_discount']);
                    }
                    $total_discount_info[$k][$k1]['brand_discount'] = floatval($bh_format_info[$k][$k1]['brand_discount']);
                } else {
                    $total_discount_info[$k][$k1] = $v1;
                }
            }
        }

        return $total_discount_info;
    }

    /**
     * description:获取当前品牌折扣数据
     * editor:zongxing
     * date : 2019.08.22
     * return Array
     */
    public function getBrandDiscount($param)
    {
        $start_date = Carbon::now()->firstOfMonth()->toDateString();
        $end_date = Carbon::now()->endOfMonth()->toDateString();
        $field = [
            'b.name', 'b.brand_id', 'b.name_en', 'pm.method_sn', 'pm.method_name', 'pm.method_property', 'pc.channels_sn',
            'pc.channels_name', 'd.shipment', 'pc.post_discount', 'pc.is_count_wai', 'dt.discount as brand_discount',
            'type_name', 'dti.type_cat'
        ];
        $brand_discount_obj = DB::table('discount_type as dt')
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'd.brand_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'd.channels_id');
        if (!empty($param['buy_time'])) {
            $buy_time = $param['buy_time'];
            $brand_discount_obj->where('dt.start_date', '<=', $buy_time);
            $brand_discount_obj->where('dt.end_date', '>=', $buy_time);
        } else {
            $brand_discount_obj->where('dt.start_date', '=', $start_date);
            $brand_discount_obj->where('dt.end_date', '=', $end_date);
        }
        if (isset($param['predict_type_arr'])) {
            $predict_type_arr = $param['predict_type_arr'];
            $brand_discount_obj->whereIn('dti.id', $predict_type_arr);
        }
        if (isset($param['month_type_arr'])) {
            $month_type_arr = $param['month_type_arr'];
            $brand_discount_obj->whereIn('dti.id', $month_type_arr);
        }
        if (isset($param['total_type_arr'])) {
            $total_type_arr = $param['total_type_arr'];
            $brand_discount_obj->whereIn('dti.id', $total_type_arr);
        }

        if (isset($param['type_cat'])) {
            $type_cat = intval($param['type_cat']);
            $brand_discount_obj->where('dti.type_cat', $type_cat);
        }
        if (isset($param['brand_id_arr'])) {
            $brand_id_arr = $param['brand_id_arr'];
            $brand_discount_obj->whereIn('d.brand_id', $brand_id_arr);
        }
        if (!empty($param['channels_id'])) {
            $channels_id = intval($param['channels_id']);
            $brand_discount_obj->where('dti.channels_id', $channels_id);
        }
        $brand_discount_info = $brand_discount_obj->orderBy('dt.discount', 'ASC')->get($field);
        $brand_discount_info = objectToArrayZ($brand_discount_info);
        return $brand_discount_info;
    }


    /**
     * description 组装免税店（DFS）折扣
     * editor zhangdong
     * date 2019.08.23
     */
    public function makeDiscountDFS($discount)
    {
        $abkIsset = isset($discount['channels_info'][$this->DFS_sn['abk']]['brand_discount']);//爱宝客
        $msg['abk_discount'] = $abkIsset ? $discount['channels_info'][$this->DFS_sn['abk']]['brand_discount'] : 0;
        $ltIsset = isset($discount['channels_info'][$this->DFS_sn['lt']]['brand_discount']);//乐天
        $msg['lt_discount'] = $ltIsset ? $discount['channels_info'][$this->DFS_sn['lt']]['brand_discount'] : 0;
        $xlIsset = isset($discount['channels_info'][$this->DFS_sn['xl']]['brand_discount']);//新罗
        $msg['xl_discount'] = $xlIsset ? $discount['channels_info'][$this->DFS_sn['xl']]['brand_discount'] : 0;
        return $msg;

    }


}//end of class
