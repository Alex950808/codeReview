<?php

namespace App\Model\Vone;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscountModel extends Model
{
    protected $table = 'discount as d';

    //可操作字段
    protected $field = ['d.id', 'd.brand_id', 'd.method_id', 'd.channels_id', 'd.brand_discount', 'd.cost_discount_id',
        'd.vip_discount_id'];

    //修改laravel 自动更新
    const UPDATED_AT = "modify_time";
    const CREATED_AT = "create_time";

    /**
     * description:获取品牌id
     * editor:zongxing
     * date : 2018.06.27
     * params: 1.品牌名称:$brand_name;
     * return String
     */
    public function getBrandId($brand_name)
    {
        $brand_id = DB::table("brand")
            ->where("name", "LIKE", '%' . $brand_name . '%')
            ->orWhere("name_en", "LIKE", '%' . $brand_name . '%')
            ->orWhere("name_cn", "LIKE", '%' . $brand_name . '%')
            ->orWhere("name_alias", "LIKE", '%' . $brand_name . '%')
            ->orWhere("keywords", "LIKE", '%' . $brand_name . '%')
            ->pluck('brand_id');
        $brand_id = $brand_id->toArray();
        if (empty($brand_id)) {
            return false;
        }
        $brand_id = $brand_id[0];
        return $brand_id;
    }

    /**
     * description:获取方式id
     * editor:zongxing
     * date : 2018.06.27
     * params: 1.方式名称:$method_name;
     * return String
     */
    public function getMethodId_stop($method_name)
    {
        $method_id = DB::table("purchase_method")->where("method_name", $method_name)->first(['id']);
        $method_id = objectToArrayZ($method_id);

        if (empty($method_id)) {
            return false;
        }

        $method_id = $method_id["id"];
        return $method_id;
    }

    //获取渠道id

    /**
     * description:获取渠道id
     * editor:zongxing
     * date : 2018.06.27
     * params: 1.渠道名称:$channels_name;
     * return String
     */
    public function getChannelId_stop($channels_name, $method_id)
    {
        $channels_id = DB::table("purchase_channels")
            ->where("channels_name", $channels_name)
            ->where("method_id", $method_id)
            ->pluck('id');
        $channels_id = $channels_id->toArray();

        if (empty($channels_id)) {
            return false;
        }

        $channels_id = $channels_id[0];
        return $channels_id;
    }

    /**
     * description:判断是新增还是更新
     * editor:zongxing
     * date : 2018.06.27
     * params: 1.品牌名称:$brand_name;
     * return String
     */
    public function getFinalInfo($brand_id, $method_id, $channels_id, $discount_list)
    {
        $pin_str = $brand_id . "-" . $method_id . "-" . $channels_id;
        $return_info["action"] = "insert";
        if (isset($discount_list[$pin_str])) {
            $return_info["action"] = "update";
            $return_info["id"] = $discount_list[$pin_str];
        }
        return $return_info;
    }

    /**
     * description:插入上传的采购折扣数据
     * editor:zongxing
     * date : 2018.06.27
     * params: 1.需要上传的采购折扣数据数组:$discountData;
     * return Array
     */
    public function discountChange($discountData, $discount_sn)
    {
        $insertDiscount = [];
        $insertDiscountLog = [];
        $ids = [];
        $update_discount_sql = "UPDATE jms_discount SET brand_discount = CASE id ";

        foreach ($discountData as $discountInfo) {
            if ($discountInfo["action"] == "insert") {
                unset($discountInfo["action"]);
                unset($discountInfo["id"]);
                $insertDiscount[] = $discountInfo;
            } elseif ($discountInfo["action"] == "update") {
                unset($discountInfo["action"]);
                $id = $discountInfo["id"];
                $brand_discount = $discountInfo["brand_discount"];
                $update_discount_sql .= sprintf(" WHEN $id THEN " . $brand_discount);
                $ids[] = $id;
            }
            unset($discountInfo["id"]);
            $discountInfo["discount_sn"] = $discount_sn;
            $insertDiscountLog[] = $discountInfo;
        }
        if (!empty($ids)) {
            $ids = implode(',', array_values($ids));
            $update_discount_sql .= " END WHERE id IN ($ids)";
        } else {
            $update_discount_sql = '';
        }

        $return_info["insertDiscount"] = $insertDiscount;
        $return_info["insertDiscountLog"] = $insertDiscountLog;
        $return_info["update_discount_sql"] = $update_discount_sql;

        $return_info = DB::transaction(function () use ($insertDiscount, $insertDiscountLog, $update_discount_sql) {
            //更新品牌折扣表数据
            if (!empty($insertDiscount)) {
                DB::table("discount")->insert($insertDiscount);
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
     * description:获取当前采购折扣数据
     * editor:zongxing
     * date : 2018.06.28
     * return Object
     */
    public function getDiscountCurrent($search_info = null)
    {
        $fields = [
            'b.name', 'b.brand_id', 'b.name_en', 'pm.method_sn', 'pm.method_name', 'pm.method_property', 'd.brand_discount',
            'pc.channels_sn', 'pc.channels_name', 'd.shipment', 'pc.post_discount', 'pc.is_count_wai'
        ];
        $discount_obj = DB::table('discount as d')->select($fields)
            ->leftJoin('brand as b', 'b.brand_id', '=', 'd.brand_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'd.channels_id')
            //->orderBy(DB::raw('CONVERT(name USING gbk)'));
            ->orderBy('d.brand_discount', 'ASC');

        if (!empty($search_info['brand_name'])) {
            $brand_name = trim($search_info['brand_name']);
            $brand_name = "%%" . $brand_name . "%%";
            $discount_obj->where('b.name', 'LIKE', $brand_name);
        }
        if (!empty($search_info['method_info'])) {
            $method_info = json_decode($search_info["method_info"]);
            $discount_obj->whereIn('d.method_id', $method_info);
        }

        if (!empty($search_info['channels_info'])) {
            $channels_info = json_decode($search_info["channels_info"]);
            $discount_obj->whereIn('d.channels_id', $channels_info);
        }

        $discount_info = $discount_obj->get();
        $discount_info = objectToArrayZ($discount_info);
        return $discount_info;
    }

    /**
     * description:获取当前采购成本折扣数据
     * editor:zongxing
     * date : 2019.05.14
     * return Array
     */
    public function getTotalDiscount($search_info = [])
    {
        $start_date = Carbon::now()->firstOfMonth()->toDateString();
        $end_date = Carbon::now()->endOfMonth()->toDateString();
        //获取当月配置表
        $param = [];
        if (!empty($search_info['buy_time'])) {
            $buy_time = trim($search_info['buy_time']);
            $param['buy_time'] = $buy_time;
        } else {
            $param['start_date'] = $start_date;
            $param['end_date'] = $end_date;
        }
        if (!empty($search_info['channels_id'])) {
            $channels_id = intval($search_info['channels_id']);
            $param['channels_id'] = $channels_id;
        }
        //获取选择时间对应的折扣配置
        $dtr_model = new DiscountTypeRecordModel();
        $dtr_info = $dtr_model->getDiscountTypeRecordList($param);
        if (empty($dtr_info)) {
            return ['code' => '1021', 'msg' => '您选择的上传时间未设置对应的成本折扣'];
        }
        $dtr_info = $dtr_info['data'];
        $cost_arr = [];
        foreach ($dtr_info as $k => $v) {
            $cost_id = explode(',', $v['cost_id']);
            $cost_arr = array_merge($cost_arr, $cost_id);
        }
        $fields = [
            'b.name', 'b.brand_id', 'b.name_en', 'pm.method_sn', 'pm.method_name', 'pm.method_property', 'pc.channels_sn',
            'pc.channels_name', 'd.shipment', 'pc.post_discount', 'pc.is_count_wai',
            'type_name','dt.discount as brand_discount'
        ];
        $discount_obj = DB::table('discount_type as dt')->select($fields)
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->leftJoin('discount as d', 'd.id', '=', 'dt.discount_id')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'd.brand_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'd.channels_id')
            ->whereIn('dti.id', $cost_arr)
            ->orderBy('dt.discount', 'ASC');
        if (!empty($search_info['brand_name'])) {
            $brand_name = trim($search_info['brand_name']);
            $brand_name = "%%" . $brand_name . "%%";
            $discount_obj->where('b.name', 'LIKE', $brand_name);
        }
        if (!empty($search_info['buy_time'])) {
            $buy_time = trim($search_info['buy_time']);
            $discount_obj->where('dt.start_date', '<=', $buy_time);
            $discount_obj->where('dt.end_date', '>=', $buy_time);
        } else {
            $discount_obj->where('dt.start_date', '=', $start_date);
            $discount_obj->where('dt.end_date', '=', $end_date);
        }
        if (!empty($search_info['method_id'])) {
            $method_id = intval($search_info['method_id']);
            $discount_obj->where('d.method_id', $method_id);
        }
        if (!empty($search_info['channels_id'])) {
            $channels_id = intval($search_info['channels_id']);
            $discount_obj->where('d.channels_id', $channels_id);
        }
        if (!empty($search_info['type_id'])) {
            $type_id = $search_info['type_id'];
            $discount_obj->whereIn('dti.id', $type_id);
        }
        $discount_info = $discount_obj->get();
        $discount_info = objectToArrayZ($discount_info);
        if (empty($discount_info)) {
            return $discount_info;
        };
        $format_discount_info = [];
        foreach ($discount_info as $k => $v) {
            $pin_str = $v['channels_sn'] . '-' . $v['method_sn'];
            $format_discount_info[$v['brand_id']][$pin_str] = $v;
        }
        return $format_discount_info;
    }

    /**
     * description:获取当前采购Lvip折扣数据
     * editor:zongxing
     * date : 2019.07.29
     * return Array
     */
    public function getLvipDiscount($search_info = [])
    {
        $fields = [
            'b.name', 'b.brand_id', 'b.name_en', 'pm.method_sn', 'pm.method_name', 'pm.method_property', 'pc.channels_sn',
            'pc.channels_name', 'd.shipment', 'pc.post_discount', 'pc.is_count_wai', 'dt.discount as brand_discount',
            'type_name'
        ];
        $start_date = Carbon::now()->firstOfMonth()->toDateString();
        $end_date = Carbon::now()->endOfMonth()->toDateString();
        $where = [
            ['dt.start_date', '=', $start_date],
            ['dt.end_date', '=', $end_date],
        ];
        $discount_obj = DB::table('discount as d')->select($fields)
            ->leftJoin('brand as b', 'b.brand_id', '=', 'd.brand_id')
            ->leftJoin('purchase_method as pm', 'pm.id', '=', 'd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', '=', 'd.channels_id')
            ->leftJoin('discount_type as dt', function ($join) {
                $join->on('dt.discount_id', '=', 'd.id');
                $join->on('dt.type_id', '=', 'd.vip_discount_id');
            })
            ->leftJoin('discount_type_info as dti', 'dti.id', '=', 'dt.type_id')
            ->where($where)
            ->orderBy('dt.discount', 'ASC');
        if (!empty($search_info['brand_name'])) {
            $brand_name = trim($search_info['brand_name']);
            $brand_name = "%%" . $brand_name . "%%";
            $discount_obj->where('b.name', 'LIKE', $brand_name);
        }
        if (!empty($search_info['method_id'])) {
            $method_id = intval($search_info['method_id']);
            $discount_obj->where('d.method_id', $method_id);
        }
        if (!empty($search_info['channels_id'])) {
            $channels_id = intval($search_info['channels_id']);
            $discount_obj->where('d.channels_id', $channels_id);
        }
        $discount_info = $discount_obj->get();
        $discount_info = objectToArrayZ($discount_info);
        if (empty($discount_info)) {
            return ['code' => '1004', 'msg' => '暂无品牌折扣信息,请先维护折扣信息'];
        }
        $format_discount_info = [];
        foreach ($discount_info as $k => $v) {
            $pin_str = $v['channels_name'] . '-' . $v['method_name'];
            $format_discount_info[$v['brand_id']][$pin_str] = $v;
        }
        $discount_list = [];
        foreach ($format_discount_info as $k => $v) {
            $tmp_zi_arr = [];
            $tmp_wai_arr = [];
            foreach ($v as $k1 => $v1) {
                //对自采外采进行判断 method_property,1表示自采,2表示外采
                $brand_discount = floatval($v1['brand_discount']);
                if ($v1['method_property'] == 1) {
                    $tmp_zi_arr[$k1][$v1['type_name']] = $brand_discount;
                } else {
                    $tmp_wai_arr[$k1][$v1['type_name']] = $brand_discount;
                }
            }
            foreach ($tmp_zi_arr as $m => $n) {
                $tmp_wai_arr[$m] = $n;
            }
            $discount_list[$k] = $tmp_wai_arr;
        }
        return $discount_list;
    }

    /**
     * description:组装折扣列表搜索条件
     * editor:zongxing
     * date : 2018.07.20
     * return String
     */
    public function createQueryDiscount_stop($search_info)
    {
        $sql_query_discount = 'SELECT b.name,name_en,method_sn,method_name,brand_discount,channels_sn,channels_name,
            b.brand_id,shipment,method_property,post_discount,is_count_wai  
            FROM jms_discount AS d
            LEFT JOIN jms_brand AS b ON b.brand_id = d.brand_id 
            LEFT JOIN jms_purchase_method AS pm ON pm.id = d.method_id 
            LEFT JOIN jms_purchase_channels AS pc ON pc.id = d.channels_id WHERE 1=1 ';

        if (!empty($search_info['brand_name'])) {
            $brand_name = trim($search_info['brand_name']);
            $brand_name = "%%" . $brand_name . "%%";
            $sql_query_discount .= "AND b.name LIKE '$brand_name'";
        }
        if (!empty($search_info['method_info'])) {
            $method_info = json_decode($search_info["method_info"]);
            $method_id = '';
            foreach ($method_info as $k => $v) {
                $method_id .= "'" . $v . "',";
            }
            $method_id = substr($method_id, 0, -1);
            $sql_query_discount .= "AND d.method_id in ($method_id)";
        }
        if (!empty($search_info['channels_info'])) {
            $channels_info = json_decode($search_info["channels_info"]);
            $channels_id = '';
            foreach ($channels_info as $k => $v) {
                $channels_id .= "'" . $v . "',";
            }
            $channels_id = substr($channels_id, 0, -1);
            $sql_query_discount .= "AND d.channels_id in ($channels_id)";
        }
        $sql_query_discount .= " ORDER BY CONVERT(name USING gbk)";
        return $sql_query_discount;
    }

    /**
     * description:下载采购需求总表
     * editor:zongxing
     * date : 2018.07.11
     * params: 1.采购期编号:purchase_sn;
     * return Object
     */
    public function getDemandTotalDetail($demand_info)
    {
        $purchase_sn = $demand_info["purchase_sn"];

        $demand_recommend_info = DB::table("demand_count")
            ->leftJoin('goods_spec', 'goods_spec.spec_sn', '=', 'demand_count.spec_sn')
            ->leftJoin('goods', 'goods.goods_sn', '=', 'goods_spec.goods_sn')
            ->where('purchase_sn', '=', $purchase_sn)
            ->get(array("demand_count.goods_name", "demand_count.erp_prd_no",
                "demand_count.erp_merchant_no", "demand_count.spec_sn",
                "demand_count.goods_num", "demand_count.may_buy_num"));
        $demand_recommend_info = $demand_recommend_info->toArray();

        return $demand_recommend_info;
    }

    /**
     * description:检查上传商品的品牌是否在所选择的方式渠道存在折扣信息
     * author:zongxing
     * date : 2018.12.28
     * return Array
     */
    public function checkDiscountInfo_stop($upload_goods_info, $purchase_data_array, $method_name, $channels_name)
    {
        $upload_goods_spec_info = array_keys($upload_goods_info);
        $method_id = intval($purchase_data_array['method_id']);
        $channels_id = intval($purchase_data_array['channels_id']);
        $brand_discount_info = DB::table("discount as d")
            ->leftJoin('goods as g', 'g.brand_id', '=', 'd.brand_id')
            ->leftJoin('goods_spec as gs', 'gs.goods_sn', '=', 'g.goods_sn')
            ->where('method_id', $method_id)
            ->where('channels_id', $channels_id)
            ->whereIn("gs.spec_sn", $upload_goods_spec_info)
            ->get();
        $brand_discount_goods_total_info = objectToArrayZ($brand_discount_info);
        $brand_discount_goods_info = [];
        foreach ($brand_discount_goods_total_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $brand_discount_goods_info[$spec_sn] = $v;
        }

        $goods_brand_info = DB::table('brand as b')
            ->leftJoin('goods as g', 'g.brand_id', '=', 'b.brand_id')
            ->leftJoin('goods_spec as gs', 'gs.goods_sn', '=', 'g.goods_sn')
            ->whereIn('gs.spec_sn', $upload_goods_spec_info)
            ->get(['b.brand_id', 'b.name', 'gs.spec_sn']);
        $goods_brand_info = objectToArrayZ($goods_brand_info);
        foreach ($goods_brand_info as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            if (isset($upload_goods_info[$spec_sn])) {
                $brand_id = intval($v['brand_id']);
                $brand_name = trim($v['name']);
                $upload_goods_info[$spec_sn]['brand_id'] = $brand_id;
                $upload_goods_info[$spec_sn]['brand_name'] = $brand_name;
            }
        }
        $diff_spec_discount = array_diff_key($upload_goods_info, $brand_discount_goods_info);
        $return_brand_info = [];
        if (!empty($diff_spec_discount)) {
            foreach ($diff_spec_discount as $k => $v) {
                $brand_id = $v['brand_id'];
                $tmp_brand['brand_id'] = $v['brand_id'];
                $tmp_brand['brand_name'] = $v['brand_name'];
                if (!isset($return_brand_info[$brand_id])) {
                    $return_brand_info[$brand_id] = $tmp_brand;
                }
            }
            $msg = '您选择的' . $method_name . '-' . $channels_name . '以下品牌折扣信息不完整,请完善';
            $data = [
                'brand_info' => array_values($return_brand_info),
                'method_id' => $method_id,
                'channels_id' => $channels_id,
            ];
            return ['code' => '1099', 'msg' => $msg, 'data' => $data];
        }
        return $brand_discount_goods_info;
    }

    public function checkDiscountInfo($upload_goods_info, $param_info, $method_name, $channels_name)
    {
        $upload_goods_spec_info = array_keys($upload_goods_info);
        $method_id = intval($param_info['method_id']);
        $channels_id = intval($param_info['channels_id']);
        //获取上传商品对应的折扣信息
        $field = ['g.goods_name', 'gs.spec_sn', 'gs.erp_prd_no', 'gs.erp_merchant_no', 'dt.discount'];
        $bg_total_discount_info = DB::table('discount as d')
            ->leftJoin('goods as g', 'g.brand_id', '=', 'd.brand_id')
            ->leftJoin('goods_spec as gs', 'gs.goods_sn', '=', 'g.goods_sn')
            ->join('discount_type as dt', function ($join) {
                $join->on('dt.discount_id', '=', 'd.id')
                    ->on('dt.type_id', '=', 'd.cost_discount_id');
            })
            ->where('method_id', $method_id)
            ->where('channels_id', $channels_id)
            ->whereIn('gs.spec_sn', $upload_goods_spec_info)
            ->get($field);
        $bg_total_discount_info = objectToArrayZ($bg_total_discount_info);

        $bg_total_discount_list = [];
        foreach ($bg_total_discount_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            $bg_total_discount_list[$spec_sn] = $v;
        }
        //获取上传商品对应的品牌
        $goods_brand_info = DB::table('brand as b')
            ->leftJoin('goods as g', 'g.brand_id', '=', 'b.brand_id')
            ->leftJoin('goods_spec as gs', 'gs.goods_sn', '=', 'g.goods_sn')
            ->whereIn('gs.spec_sn', $upload_goods_spec_info)
            ->get(['b.brand_id', 'b.name', 'gs.spec_sn']);
        $goods_brand_info = objectToArrayZ($goods_brand_info);
        foreach ($goods_brand_info as $k => $v) {
            $spec_sn = trim($v['spec_sn']);
            if (isset($upload_goods_info[$spec_sn])) {
                $brand_id = intval($v['brand_id']);
                $brand_name = trim($v['name']);
                $upload_goods_info[$spec_sn]['brand_id'] = $brand_id;
                $upload_goods_info[$spec_sn]['brand_name'] = $brand_name;
            }
        }

        $diff_spec_discount = array_diff_key($upload_goods_info, $bg_total_discount_list);
        $error_discount_info = '';
        if (!empty($diff_spec_discount)) {
            foreach ($diff_spec_discount as $k => $v) {
                $error_discount_info .= $k . ',';
            }
            $msg = '您选择的' . $method_name . '-' . $channels_name . '以下商品' . substr($error_discount_info, 0, -1) . '折扣信息不完整,请完善';
            return ['code' => '1120', 'msg' => $msg];
        }
        return $bg_total_discount_list;
    }

    /**
     * description:采购模块-优采推荐管理-采购折扣-提交新增品牌折扣
     * editor:zongxing
     * date : 2019.02.14
     * return Array
     */
    public function getBrandDiscountInfo($param_info)
    {
        $brand_id = intval($param_info['brand_id']);
        $method_id = intval($param_info['method_id']);
        $channels_id = intval($param_info['channels_id']);
        $where = [
            ['brand_id', $brand_id],
            ['method_id', $method_id],
            ['channels_id', $channels_id],
        ];
        $brand_discount_info = DB::table('discount')->where($where)->first();
        return $brand_discount_info;
    }

    /**
     * description:采购模块-优采推荐管理-采购折扣-提交新增品牌折扣
     * editor:zongxing
     * date : 2019.02.14
     * return Array
     */
    public function doAddBrandDiscount($param_info, $brand_discount_info)
    {
        $insert_data = [];
        $where = [];
        $update_data = [];
        if (empty($brand_discount_info)) {
            $insert_data['brand_id'] = intval($param_info['brand_id']);
            $insert_data['method_id'] = intval($param_info['method_id']);
            $insert_data['channels_id'] = intval($param_info['channels_id']);
            $insert_data['brand_discount'] = floatval($param_info['brand_discount']);
            if (isset($param_info['shipment'])) {
                $insert_data['shipment'] = intval($param_info['shipment']);
            }
        } else {
            $brand_id = intval($param_info['brand_id']);
            $method_id = intval($param_info['method_id']);
            $channels_id = intval($param_info['channels_id']);
            $where = [
                ['brand_id', $brand_id],
                ['method_id', $method_id],
                ['channels_id', $channels_id],
            ];
            $update_data['brand_discount'] = floatval($param_info['brand_discount']);
            if (isset($param_info['shipment'])) {
                $update_data['shipment'] = intval($param_info['shipment']);
            }
        }

        $res = DB::transaction(function () use ($insert_data, $update_data, $where) {
            if (!empty($insert_data)) {
                //采购折扣表新增数据
                DB::table('discount')->insert($insert_data);
                //采购折扣日志表新增数据
                $res = DB::table('discount_log')->insert($insert_data);
            } else {
                //更新采购折扣表数据
                DB::table('discount')->where($where)->update($update_data);
                //更新采购折扣日志表数据
                $res = DB::table('discount_log')->where($where)->update($update_data);
            }

            return $res;
        });
        return $res;
    }

    /**
     * description:采购模块-优采规则管理-品牌采购折扣批量新增
     * editor:zongxing
     * type:POST
     * date : 2019.03.21
     * return Array
     */
    public function batchAddBrandDiscount($param_info)
    {
        //$brand_discount = json_decode($param_info['brand_discount'], true);//postman 测试用的，勿删
        $brand_discount = $param_info["brand_discount"];
        $method_id = intval($param_info['method_id']);
        $channels_id = intval($param_info['channels_id']);

        $insert_discount_info = [];
        foreach ($brand_discount as $k => $v) {
            $brand_id = intval($v['brand_id']);
            $brand_discount = floatval($v['brand_discount']);
            $insert_discount_info[] = [
                'method_id' => $method_id,
                'channels_id' => $channels_id,
                'brand_id' => $brand_id,
                'brand_discount' => $brand_discount,
            ];
        }
        $insert_res = DB::table($this->table)->insert($insert_discount_info);
        return $insert_res;
    }


    /**
     * description:采购折扣上传-校验上传数据
     * author:zhangdong
     * date:2019.03.29
     */
    public function checkUploadDisData($res)
    {
        //获取品牌信息
        $brandModel = new BrandModel();
        $brandInfo = $brandModel->getBrandInfoInRedis();
        //获取采购方式信息
        $pmModel = new PurchaseMethodModel();
        $purchaseMethodInfo = $pmModel->purMethodInfo;
        //获取采购渠道信息
        $pcModel = new PurchaseChannelModel();
        $purchaseChannelInfo = $pcModel->getPurChannelInRedis();
        $none_brand = $none_method = $none_channel = $none_discount = $disAuditData = [];
        foreach ($res as $key => $value) {
            //标题头跳过
            if ($key == 0) {
                continue;
            }
            //检查品牌名称是否存在
            $brand_id = intval($value[0]);
            $brandSearch = searchTwoArray($brandInfo, $brand_id, 'brand_id');
            $brandName = isset($brandSearch[0]['name']) ? trim($brandSearch[0]['name']) : '';
            $disAuditData[$key]['brand_name'] = $brandName;
            //如果没找到对应品牌则做记录
            if (empty($brandName)) {
                $none_brand[] = $brand_id;
            }
            //检查采购方式是否存在
            $purchaseMethod = trim($value[2]);
            $methodSearch = searchTwoArray($purchaseMethodInfo, $purchaseMethod, 'method_name');
            $purchase_method = isset($methodSearch[0]['method_name']) ? trim($methodSearch[0]['method_name']) : '';
            $disAuditData[$key]['purchase_method'] = $purchase_method;
            //如果没找到对应采购方式则做记录
            if (empty($purchase_method)) {
                $none_method[] = $purchaseMethod;
            }
            //检查采购渠道是否存在
            $purchaseChannel = trim($value[3]);
            $channelSearch = searchTwoArray($purchaseChannelInfo, $purchaseChannel, 'channels_name');
            $purchase_channel = isset($channelSearch[0]['channels_name']) ? trim($channelSearch[0]['channels_name']) : '';
            $disAuditData[$key]['purchase_channel'] = $purchase_channel;
            //如果没找到对应采购方式则做记录
            if (empty($purchase_channel)) {
                $none_channel[] = $purchaseChannel;
            }
            //检查品牌折扣是否不为0
            $brand_discount = isset($value[4]) ? floatval($value[4]) : 0;
            $disAuditData[$key]['brand_discount'] = $brand_discount;
            if ($brand_discount <= 0) {
                $desc = '第' . ($key + 1) . '条数据品牌折扣有误';
                $none_discount[] = $desc;
            }
            $disAuditData[$key]['shipment'] = intval($value[5]);
        }//end of foreach
        //组装报错信息
        $none_desc = '';
        $none_brand = array_unique($none_brand);
        if (count($none_brand) > 0) {
            $strNoneBrand = implode($none_brand, ',');
            $none_desc .= '品牌ID ' . $strNoneBrand . '未找到 ';
        }
        $none_method = array_unique($none_method);
        if (count($none_method) > 0) {
            $strNoneMethod = implode($none_method, ',');
            $none_desc .= '采购方式' . $strNoneMethod . '未找到 ';
        }
        $none_channel = array_unique($none_channel);
        if (count($none_channel) > 0) {
            $strNoneChannel = implode($none_channel, ',');
            $none_desc .= '采购渠道' . $strNoneChannel . '未找到 ';
        }
        if (count($none_discount) > 0) {
            $strNoneDiscount = implode($none_discount, ',');
            $none_desc .= '采购渠道' . $strNoneDiscount . '未找到 ';
        }
        return ['none_desc' => $none_desc, 'disAuditData' => $disAuditData,];
    }//end of function


    /**
     * description:查询采购折扣中是否已经存在相应折扣
     * author:zhangdong
     * date:2019.04.04
     */
    public function checkBrandDiscount($brand_id, $method_id, $channel_id)
    {
        $where = [
            ['brand_id', $brand_id],
            ['method_id', $method_id],
            ['channels_id', $channel_id],
        ];
        $countRes = DB::table($this->table)->where($where)->count();
        return $countRes;
    }

    /**
     * description:查询采购折扣中是否已经存在相应折扣
     * author:zhangdong
     * date:2019.04.04
     */
    public function updateDiscount($brand_id, $method_id, $channel_id, $discount)
    {
        $where = [
            ['brand_id', $brand_id],
            ['method_id', $method_id],
            ['channels_id', $channel_id],
        ];
        $update = [
            'brand_discount' => $discount,
        ];
        $updateRes = DB::table($this->table)->where($where)->update($update);
        return $updateRes;

    }

    /**
     * description:批量新增数据到品牌折扣表中
     * author:zhangdong
     * date:2019.04.04
     */
    public function batchInsertData($arrBrandDiscount)
    {
        $insertRes = DB::table($this->table)->insert($arrBrandDiscount);
        return $insertRes;
    }

    /**
     * description:获取折扣表中品牌、方式、渠道组合id
     * editor:zongxing
     * date : 2019.05.05
     * return Array
     */
    public function getDiscountIdInfo($brand_id, $method_id, $channels_id)
    {
        $brand_id = DB::table($this->table)
            ->whereIn('brand_id', $brand_id)
            ->where('method_id', $method_id)
            ->where('channels_id', $channels_id)
            ->pluck('id', 'brand_id');
        $brand_id = objectToArrayZ($brand_id);
        return $brand_id;
    }


    /**
     * description:设置成本和VIP折扣档位
     * editor:zongxing
     * date : 2019.05.10
     * modify 2019.07.25
     * return Boolean
     */
    public function discountTypeSetting($param_info, $dti_info)
    {
        $method_id = intval($dti_info[0]['method_id']);
        $channels_id = intval($dti_info[0]['channels_id']);
        $type_cat = intval($param_info['type_cat']);
        $where = [
            'method_id' => $method_id,
            'channels_id' => $channels_id,
            'is_start' => 1,
            'type_cat' => $type_cat,
        ];
        $old_dti_info = DB::table('discount_type_info')->where($where)->first();
        $old_dti_info = objectToArrayZ($old_dti_info);
        $res = DB::transaction(function () use ($param_info, $dti_info, $old_dti_info) {
            $method_id = intval($dti_info[0]['method_id']);
            $channels_id = intval($dti_info[0]['channels_id']);
            $type_id = intval($param_info['type_id']);
            $set_type = intval($param_info['set_type']);
            $dis_where = [
                'method_id' => $method_id,
                'channels_id' => $channels_id,
            ];
            if ($set_type == 1) {
                $dis_update_info = [
                    'cost_discount_id' => $type_id,
                ];
            } elseif ($set_type == 2) {
                $dis_update_info = [
                    'vip_discount_id' => $type_id,
                ];
            }

            if ($set_type == 1) {
                //停用上月成本折扣
                DB::table('discount_type_info')->where('is_start', 1)->update(['is_start' => 0]);
                //启用当月最新的成本折扣
                DB::table('discount_type_info')->where('id', $type_id)->update(['is_start' => 1]);
            }
            //discount表更新成本折扣数据
            $res = DB::table($this->table)->where($dis_where)->update($dis_update_info);
            return $res;
        });
        return $res;
    }

    /**
     * description:设置exw折扣档位
     * editor:zongxing
     * date : 2019.06.06
     * return Boolean
     */
    public function setExwDiscount($param_info, $brand_info)
    {
        $type_id = intval($param_info['type_id']);
        $brand_id = array_keys($brand_info);
        $update_goods_sql = '';
        if (!empty($brand_info)) {
            $update_goods_sql .= 'UPDATE jms_goods_spec as gs
            LEFT JOIN jms_goods as g ON g.goods_sn = gs.goods_sn
            SET exw_discount = CASE brand_id';
            foreach ($brand_info as $k => $v) {
                $update_goods_sql .= ' WHEN \'' . $k . '\' THEN ' . $v;
            }
            $update_goods_sql .= ' END';
            $brand_arr = implode('\',\'', $brand_id);
            $update_goods_sql .= ' WHERE g.brand_id IN (\'' . $brand_arr . '\')';
        }
        $update_where = [
            'method_id' => intval($param_info['method_id']),
            'channels_id' => intval($param_info['channels_id']),
            'id' => $type_id,
        ];
        $updateRes = DB::transaction(function () use ($update_goods_sql, $update_where) {
            //关联采购期和需求单
            if (!empty($update_goods_sql)) {
                DB::update(DB::raw($update_goods_sql));
            }
            $exw_old_info = [
                'exw_type' => 0
            ];
            $update_info = [
                'exw_type' => 1
            ];
            DB::table('discount_type_info')->where($update_info)->update($exw_old_info);
            $res = DB::table('discount_type_info')->where($update_where)->update($update_info);
            return $res;
        });
        return $updateRes;
    }

    /**
     * description:获取当月品牌活动对应的品牌信息
     * editor:zongxing
     * date : 2019.08.12
     * return Array
     */
    public function getMonthBrandInfo($param_info, $month_type_arr)
    {
        $start_date = date('Y-m-d', strtotime(trim($param_info['start_date'])));
        $end_date = date('Y-m-d', strtotime(trim($param_info['end_date'])));
        $channelId = intval($param_info['channels_id']);
        //获取当月品牌活动档位折扣
        $type_cat = 9;
        $dti_model = new DiscountTypeInfoModel();
        $type_info = $dti_model->getRealMonthGear($channelId, $type_cat, 0, $month_type_arr);
        $brand_info = [];
        if (!empty($type_info)) {
            $type_id = [];
            foreach ($type_info as $k => $v) {
                $type_id[] = intval($v['id']);
            }
            $dt_model = new DiscountTypeModel();
            $month_gears_points = $dt_model->getMonthGearBrand($type_id, $start_date, $end_date);
            $brand_info = [];
            foreach ($month_gears_points as $k => $v) {
                $pin_str = '';
                foreach ($v as $k1 => $v1) {
                    $pin_str .= $v1['brand_id'] . ',';
                }
                $brand_info[$pin_str]['type_info'][] = $k;
            }
        }
        return $brand_info;
    }


}//end of class
