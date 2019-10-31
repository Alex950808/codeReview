<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class GoodsModel extends Model
{
    //商品表
    protected $table = "goods as g";
    //商品规格表
    protected $spec_table = "goods_spec";
    //商品平台信息
    public $platform = [
        1 => 'ERP商家编码',
        2 => '考拉',
        3 => '小红书',
    ];

    public $field = [
        'g.goods_id', 'g.goods_sn', 'g.goods_name', 'g.cat_id', 'g.brand_id', 'g.create_time',
    ];


    /**
     * description:获取商品信息
     * editor:zhangdong
     * date : 2018.06.26
     * params: $queryType :查询方式 1，根据商家编码查询 2，根据规格码查询
     * return Object
     */
    public function getGoodsInfo($queryData, $queryType)
    {
        $queryType = intval($queryType);
        if ($queryType == 1) {
            $field = 'erp_merchant_no';
        } elseif ($queryType == 2) {
            $field = 'spec_sn';
        } else {
            return false;
        }
        $selectFields = 'gs.goods_sn,gs.spec_sn,g.goods_name,gs.erp_prd_no,gs.erp_merchant_no,
        gs.spec_price,gs.spec_weight,gs.exw_discount,gs.stock_num,g.brand_id';
        $queryRes = DB::table(DB::raw('jms_goods_spec AS gs'))->selectRaw($selectFields)
            ->leftJoin(DB::raw('jms_goods AS g'), DB::raw('gs.goods_sn'), '=', DB::raw('g.goods_sn'))
            ->where($field, $queryData)->first();
        return $queryRes;
    }

    /**
     * description:根据关键字获取商品信息
     * editor:zhangdong
     * date : 2018.06.26
     * params: $queryType :查询方式 1，根据商家编码查询 2，根据规格码查询
     * return Object
     */
    public function getGoodsByKeywords($keywords)
    {
        $keywords = trim($keywords);
        $selectFields = 'gs.goods_sn,gs.spec_sn,g.goods_name,gs.erp_prd_no';
        $queryRes = DB::table(DB::raw('jms_goods_spec AS gs'))->selectRaw($selectFields)
            ->leftJoin(DB::raw('jms_goods AS g'), DB::raw('gs.goods_sn'), '=', DB::raw('g.goods_sn'))
            ->where(DB::raw('gs.storehouse_id'), 2001)
            ->where(function ($query) use ($keywords) {
                $query->orWhere(DB::raw('gs.erp_merchant_no'), 'LIKE', "%$keywords%")
                    ->orWhere(DB::raw('g.goods_name'), 'LIKE', "%$keywords%")
                    ->orWhere(DB::raw('gs.erp_prd_no'), 'LIKE', "%$keywords%");
            })->get();
        return $queryRes;

    }

    /**
     * description:组装商品实时动销率数据
     * editor:zhangdong
     * date : 2018.07.05
     * return Object
     */
    public function createGoodsMovePer(array $arrParams)
    {
        //搜索关键字
        $keywords = $arrParams['keywords'];
        $orWhere = [];
        if ($keywords) {
            $orWhere = [
                'orWhere1' => [
                    ['erp_merchant_no', 'LIKE', "%$keywords%"],
                ],
                'orWhere2' => [
                    ['spec_sn', 'LIKE', "%$keywords%"],
                ],
                'orWhere3' => [
                    ['goods_name', 'LIKE', "%$keywords%"],
                ],
            ];
        }
        $selectFields = 'g.goods_name,gs.erp_merchant_no,gs.spec_sn,SUM(dc.goods_num) AS total_need_num,SUM(rpd.allot_num) AS realAllotNum';
        $queryRes = DB::table(DB::raw('jms_goods_spec AS gs'))->selectRaw($selectFields)
            ->leftJoin(DB::raw('jms_goods AS g'), DB::raw('gs.goods_sn'), '=', DB::raw('g.goods_sn'))
            ->join(DB::raw('jms_demand_count AS dc'), DB::raw('dc.spec_sn'), '=', DB::raw('gs.spec_sn'))
            ->join(DB::raw('jms_real_purchase_detail AS rpd'), DB::raw('rpd.spec_sn'), '=', DB::raw('gs.spec_sn'))
            ->where(function ($result) use ($orWhere) {
                if (count($orWhere) >= 1) {
                    $result->orWhere($orWhere['orWhere1'])
                        ->orWhere($orWhere['orWhere2'])
                        ->orWhere($orWhere['orWhere3']);
                }
            })
            ->groupBy(DB::raw('dc.spec_sn'))
            ->paginate(15);
        //计算订单销售量
        foreach ($queryRes as $key => $value) {
            $spec_sn = $value->spec_sn;
            $where = [
                ['spec_sn', $spec_sn]
            ];
            $field = 'SUM(num) AS total_sale_num';
            $orderGoods = DB::table('order_goods')->selectRaw($field)->where($where)->first();
            $total_sale_num = intval($orderGoods->total_sale_num);
            $queryRes[$key]->total_sale_num = $total_sale_num;
            $realAllotNum = intval($value->realAllotNum);
            //计算商品动销率（订单销售数量/商品实际分配数量）
            $rate_of_pin = $realAllotNum > 0 ? round($total_sale_num / $realAllotNum, DECIMAL_DIGIT) : 0;
            $queryRes[$key]->rate_of_pin = sprintf('%.2f%%', $rate_of_pin * 100);
        }
        return $queryRes;

    }

    /**
     * description:获取商品分类信息
     * editor:zongxing
     * date : 2018.08.30
     * return Object
     */
    public function getGoodsCategory()
    {
        $category_total_info = DB::table("category")->orderBy("cat_name", "ASC")->get(["cat_id", "cat_name", "parent_id"]);
        $category_total_info = objectToArrayZ($category_total_info);
        $category_arr = $this->get_all_child($category_total_info, 0);

        return $category_arr;
    }

    /**
     * description:递归获取所有的子分类的信息
     * editor:zongxing
     * date : 2018.08.30
     * return Object
     */
    public function get_all_child($arr, $parent_id)
    {
        $list = array();
        foreach ($arr as $val) {
            if ($val["parent_id"] == $parent_id) {
                $tmp = $this->get_all_child($arr, $val["cat_id"]);
                if ($tmp) {
                    $val['child'] = $tmp;
                }
                $list[] = $val;
            }
        }
        return $list;
    }

    /**
     * description:获取商品分品牌信息
     * editor:zhangdong
     * date : 2018.08.17
     * return Object
     */
    public function getGoodsBrand()
    {
        $field = 'brand_id,name';
        $goodsBrand = DB::table('brand')->selectRaw($field)->orderBy("name", "ASC")->get();
        return $goodsBrand;
    }

    /**
     * description:获取仓库信息
     * editor:zongxing
     * date : 2018.08.23
     * return Object
     */
    public function getStorehouseInfo()
    {
        $goodsStorehouse = DB::table('storehouse')->get(["store_id", "store_location"]);
        $goodsStorehouse = json_decode(json_encode($goodsStorehouse), true);
        return $goodsStorehouse;
    }

    /**
     * description:通过名称获取商品信息
     * editor:zongxing
     * date : 2018.11.16
     * return Object
     */
    public function getGoodsByName($goods_name)
    {
        $goods_info = DB::table("goods")->where("goods_name", $goods_name)->first();
        return $goods_info;
    }

    /**
     * description:通过商品货号获取商品规格信息
     * editor:zongxing
     * date : 2018.11.16
     * return Object
     */
    public function getGoodsByGoodsSn($goods_sn)
    {
        $goods_info = DB::table('goods')->where('goods_sn', $goods_sn)->first(['goods_sn']);
        $goods_info = objectToArrayZ($goods_info);
        return $goods_info;
    }

    /**
     * description:新增单个商品
     * editor:zongxing
     * date : 2018.08.24
     * return Object
     */
    public function doAddGoods($resParams)
    {
        $goods_info["goods_name"] = trim($resParams["goods_name"]);
        $goods_info["brand_id"] = intval($resParams["brand_id"]);
        $goods_info["cat_id"] = intval($resParams["cat_id"]);

        //生成商品goods_sn
        $goods_sn = $this->get_goods_sn();
        $goods_info["goods_sn"] = $goods_sn;

        $insertRes = DB::transaction(function () use ($goods_info, $goods_sn) {
            //添加商品信息
            $add_goods_res = DB::table("goods")->insertGetId($goods_info);
            //更新商品关键字
            $this->updateKeywords($goods_sn);
            return $add_goods_res;
        });

        $return_info = false;
        if ($insertRes) {
            $return_info["goods_id"] = $insertRes;
            $return_info["goods_info"] = $goods_info;
        }
        return $return_info;
    }

    /**
     * description:新增单个商品规格
     * editor:zongxing
     * date : 2018.08.24
     * update: 2018.11.15
     * return Object
     */
    public function doAddGoodsSpec($param_info)
    {
        //生成商品spec_sn
        $goods_sn = trim($param_info["goods_sn"]);
        $spec_sn = $this->get_spec_sn($goods_sn);
        //组装新增商品规格数据
        $goods_spec_info = [
            'goods_sn' => $goods_sn,
            'spec_sn' => $spec_sn,
            'spec_price' => floatval($param_info["spec_price"]),
            'exw_discount' => floatval($param_info["exw_discount"]),
        ];
        //组装商品规格信息
        $tmp_spec_arr = ['erp_merchant_no', 'erp_prd_no', 'erp_ref_no', 'goods_label', 'spec_weight', 'estimate_weight'];
        foreach ($tmp_spec_arr as $k => $v) {
            if (isset($param_info[$v])) {
                $tmp_value = trim($param_info[$v]);
                if ($k > 3) {
                    $tmp_value = floatval($param_info[$v]);
                }
                $goods_spec_info[$v] = $tmp_value;
            }
        }
        //组装商品码信息
        $tmp_code_arr = ['erp_merchant_no', 'kl_code', 'red_code'];
        $goods_code_info = [];
        foreach ($tmp_code_arr as $k => $v) {
            if (isset($param_info[$v])) {
                $tmp_value = trim($param_info[$v]);
                $tmp_arr = [
                    'code_type' => $k + 1,
                    'goods_code' => $tmp_value,
                    'spec_sn' => $spec_sn,
                ];
                $goods_code_info[] = $tmp_arr;
            }
        }
        $insertRes = DB::transaction(function () use ($goods_spec_info, $goods_code_info) {
            if (!empty($goods_code_info)) {
                DB::table('goods_code')->insert($goods_code_info);
            }
            $insertRes = DB::table('goods_spec')->insertGetId($goods_spec_info);
            return $insertRes;
        });
        return $insertRes;
    }

    /**
     * description:提交编辑商品
     * editor:zongxing
     * date : 2018.08.25
     * return Object
     */
    public function doEditGoods($param_info)
    {
        //获取分类名称
        $cat_id = intval($param_info['cat_id']);
        $cat_model = new CategoryModel();
        $cat_name = $cat_model->getCategoryInfo($cat_id, 1);
        $cat_name = objectToArrayZ($cat_name)[0]['cat_name'];
        //获取品牌名称
        $brand_id = intval($param_info['brand_id']);
        $brand_model = new BrandModel();
        $brand_info = $brand_model->getBrandInfo($brand_id, 1);
        $brand_keywords = objectToArrayZ($brand_info)[0]['keywords'];

        $goods_name = trim($param_info['goods_name']);
        $keywords = $goods_name . '--' . $cat_name . '--' . $brand_keywords;
        $edit_goods_info = [
            'goods_name' => trim($param_info['goods_name']),
            'cat_id' => intval($param_info['cat_id']),
            'brand_id' => intval($param_info['brand_id']),
            'keywords' => $keywords
        ];
        $goods_id = intval($param_info["goods_id"]);
        $updateRes = DB::transaction(function () use ($edit_goods_info, $goods_id) {
            //更新商品信息
            $update_res = DB::table("goods")->where("goods_id", $goods_id)->update($edit_goods_info);
            return $update_res;
        });
        return $updateRes;
    }


    /**
     * description:拼装批量增加商品的信息
     * editor:zongxing
     * date : 2018.08.24
     * return Object
     */
    public function create_goods_info($res)
    {
        $return_goods_info = [];
        $return_goods_spec_info = [];
        $add_erp_merchant_no = [];
        foreach ($res as $k => $v) {
            if ($k === 0) continue;
            if (empty($v[0])) {
                $return_info = ['code' => '1006', 'msg' => '商品名称不能为空'];
                return $return_info;
            }
            //创建商品goods_sn
            $goods_sn = $this->get_goods_sn();
            //创建商品spec_sn
            $spec_sn = $this->get_spec_sn($goods_sn);

            $cat_id = intval($v[4]);
            $brand_id = intval($v[5]);
            $cat_name = trim($v[8]);
            $brand_name = trim($v[9]);
            $tmp_goods["goods_name"] = trim($v[0]);
            $tmp_goods["cat_id"] = $cat_id;
            $tmp_goods["brand_id"] = $brand_id;
            $tmp_goods["goods_sn"] = $goods_sn;
            $tmp_goods["keywords"] = $tmp_goods["goods_name"] . '--' . $cat_name . '--' . $brand_name;
            $return_goods_info[] = $tmp_goods;

            $tmp_goods_spec["goods_sn"] = $goods_sn;
            $tmp_goods_spec["spec_sn"] = $spec_sn;

            $tmp_goods_spec["erp_merchant_no"] = '';
            if (isset($v[1]) && !empty($v[1])) {
                $erp_merchant_no = trim($v[1]);
                array_push($add_erp_merchant_no, $erp_merchant_no);
                $tmp_goods_spec["erp_merchant_no"] = $v[1];
            }
            $tmp_goods_spec["erp_prd_no"] = '';
            if (isset($v[2]) && !empty($v[2])) {
                $tmp_goods_spec["erp_prd_no"] = trim($v[2]);
            }
            $tmp_goods_spec["erp_ref_no"] = '';
            if (isset($v[3]) && !empty($v[3])) {
                $tmp_goods_spec["erp_ref_no"] = trim($v[3]);
            }
            $tmp_goods_spec["spec_price"] = floatval($v[6]);
            $tmp_goods_spec["spec_weight"] = floatval($v[7]);
            $return_goods_spec_info[] = $tmp_goods_spec;
        }

        $diff_erp_merchant_no = '';
        if (!empty($add_erp_merchant_no)) {
            $erp_merchant_no_info = DB::table("goods_spec")->whereIn("erp_merchant_no", $add_erp_merchant_no)->pluck("erp_merchant_no");
            $erp_merchant_no_info = objectToArrayZ($erp_merchant_no_info);

            if (!empty($erp_merchant_no_info)) {
                $diff_erp_merchant_no = implode(",", $erp_merchant_no_info);
            }
        }

        $return_info["goods_info"] = $return_goods_info;
        $return_info["goods_spec_info"] = $return_goods_spec_info;
        $return_info["diff_erp_merchant_no"] = $diff_erp_merchant_no;
        return $return_info;
    }

    /**
     * description:新增商品-生成goods_sn
     * editor:zongxing
     * date : 2018.08.24
     */
    private function get_goods_sn($min = 0, $max = 1)
    {
        $randomcode = "";
        // 用字符数组的方式随机
        $str = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $str_arr = str_split($str);
        for ($j = 0; $j < 6; $j++) {
            $rand_num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
            $rand_str = $str_arr[(int)($rand_num * 36)];
            if (strpos($randomcode, $rand_str) !== false) {
                $j--;
                continue;
            }
            $randomcode = $randomcode . $rand_str;
        }

        $sn = rand(10000, 99999);
        $goods_sn = $randomcode . $sn;
        $goods_info = $this->getGoodsByGoodsSn($goods_sn);
        if (!empty($goods_info)) {
            $this->get_goods_sn();
        }
        return $goods_sn;
    }

    /**
     * description:检查商品信息
     * editor:zongxing
     * date : 2018.08.24
     */
    public function check_goods_info($goods_id)
    {
        $goods_info = DB::table("goods")->where("goods_id", $goods_id)->first(["goods_id"]);
        $goods_info = json_decode(json_encode($goods_info), true);
        return $goods_info;
    }

    /**
     * description:获取商品信息
     * editor:zongxing
     * date : 2018.08.30
     */
    public function getGoodsDetailInfo($goods_id)
    {
        $goods_info = DB::table("goods as g")
            ->select('goods_id', 'goods_name', 'g.brand_id', 'g.cat_id', 'goods_sn', 'g.keywords',
                DB::raw("CONCAT(jms_g.goods_name,'--',jms_c.cat_name,'--',jms_b.keywords) AS keywords"))
            ->leftJoin("category as c", "c.cat_id", "=", "g.cat_id")
            ->leftJoin("brand as b", "b.brand_id", "=", "g.brand_id")
            ->where('goods_id', $goods_id)
            ->first();
        $goods_info = objectToArrayZ($goods_info);
        if (!empty($goods_info)) {
            //过滤关键字的特殊字符
            $common_model = new CommonModel();
            $goods_info['keywords'] = $common_model->strFilter($goods_info['keywords']);
        }
        return $goods_info;
    }

    /**
     * description:获取商品规格信息
     * editor:zongxing
     * date : 2018.08.30
     */
    public function get_goods_spec_info($spec_id)
    {
        $goods_spec_info = DB::table("goods_spec")->where("spec_id", $spec_id)->first();
        $goods_spec_info = objectToArrayZ($goods_spec_info);
        $goods_spec_info['goods_label'] = explode(',', $goods_spec_info['goods_label']);
        return $goods_spec_info;
    }

    /**
     * description:新增商品-生成spec_sn
     * editor:zongxing
     * date : 2018.08.24
     */
    private function get_spec_sn_stop($goods_sn)
    {
        $sn = rand(100, 999);
        $spec_sn = $goods_sn . $sn;
        $spec_sn_search = DB::table("goods_spec")->where("spec_sn", $spec_sn)->first();
        $spec_sn_search = objectToArrayZ($spec_sn_search);

        if ($spec_sn_search) {
            return $this->get_spec_sn($goods_sn);
        } else {
            return $spec_sn;
        }
    }

    public function get_spec_sn($goods_sn)
    {
        $sn = rand(100, 999);
        $spec_sn = $goods_sn . $sn;
        return $spec_sn;
    }

    /**
     * description:获取商品仓位id
     * editor:zongxing
     * date : 2018.08.24
     */
    private function get_storehouse_sn($storehouse_name)
    {
        $str = "%" . $storehouse_name . "%";
        $store_info_search = DB::table("storehouse")
            ->where("store_name", "like", $str)
            ->orWhere("store_location", "like", $str)
            ->first();
        $store_info_search = json_decode(json_encode($store_info_search), true);

        $store_id = "";
        if ($store_info_search) {
            $store_id = $store_info_search["store_id"];
        }
        return $store_id;
    }

    /**
     * description:更新商品关键字
     * editor:zongxing
     * params:商品goods_sn:$goods_sn
     * date : 2018.08.24
     */
    public function updateKeywords($goods_sn)
    {
        $keywords_search = DB::table("goods as g")
            ->select(DB::raw("CONCAT(jms_g.goods_name,'--',jms_c.cat_name,'--',jms_b.keywords) AS keywords"))
            ->leftJoin("category as c", "c.cat_id", "=", "g.cat_id")
            ->leftJoin("brand as b", "b.brand_id", "=", "g.brand_id")
            ->where("goods_sn", $goods_sn)
            ->first(['keywords']);
        $keywordsRes = objectToArrayZ($keywords_search);

        //过滤关键字的特殊字符
        $common_model = new CommonModel();
        $keywords['keywords'] = $common_model->strFilter($keywordsRes['keywords']);

        $updateRes = DB::table("goods")->where("goods_sn", $goods_sn)->update($keywords);
        return $updateRes;
    }

    /**
     * description:获取商品列表
     * editor:zongxing
     * date : 2018.08.25
     * return object
     */
    public function goodsList($param_info)
    {
        $page_size = isset($param_info['page_size']) ? intval($param_info['page_size']) : 15;
        $field = [
            'g.goods_id', 'g.goods_name', 'b.name as brand_name', 'c.cat_name', 'g.goods_sn', 'gs.goods_label'
        ];
        $goods_obj = DB::table($this->table)->select($field)
            ->leftJoin('goods_spec as gs', 'gs.goods_sn', '=', 'g.goods_sn')
            ->leftJoin('goods_code as gc', 'gc.spec_sn', '=', 'gs.spec_sn')
            ->leftJoin('brand as b', 'b.brand_id', '=', 'g.brand_id')
            ->leftJoin('category as c', 'c.cat_id', '=', 'g.cat_id');
        if (!empty($param_info['query_sn'])) {
            $query_sn = $param_info['query_sn'];
            $query_like_sn = '%' . $query_sn . '%';
            $goods_obj->where(function ($query) use ($query_sn, $query_like_sn) {
                $query->orWhere('g.goods_name', 'like', $query_like_sn);
                $query->orWhere('g.keywords', 'like', $query_like_sn);
                $query->orWhere('g.goods_sn', $query_sn);
                $query->orWhere('gs.spec_sn', $query_sn);
                $query->orWhere('gs.erp_merchant_no', $query_sn);
                $query->orWhere('gs.erp_prd_no', $query_sn);
                $query->orWhere('gs.erp_ref_no', $query_sn);
                $query->orWhere('goods_code', $query_sn);
            });
        }
        $goods_info = $goods_obj->orderBy('g.create_time', 'DESC')->paginate($page_size);
        $goods_info = objectToArrayZ($goods_info);
        if (empty($goods_info['data'])) {
            return false;
        }

        //获取商品标签信息
        $goods_label_info = DB::table('goods_label')->get(['id', 'label_name', 'label_color']);
        $goods_label_info = objectToArrayZ($goods_label_info);
        $goods_label_list = [];
        foreach ($goods_label_info as $k => $v) {
            $goods_label_list[$v['id']] = $v;
        }

        $goods_list = [];
        $goods_sn_arr = [];
        foreach ($goods_info['data'] as $k => $v) {
            $tmp_label = [];
            if (!empty($v['goods_label'])) {
                $goods_label = explode(',', $v['goods_label']);
                foreach ($goods_label as $k1 => $v1) {
                    $goods_sn = $v['goods_sn'];
                    if (array_key_exists($goods_sn, $goods_list)) {
                        if (array_key_exists($v1, $goods_label_list) && !in_array($goods_label_list[$v1], $tmp_label)) {
                            $tmp_label[] = $goods_label_list[$v1];
                        }
                    } else {
                        if (array_key_exists($v1, $goods_label_list)) {
                            $tmp_label[] = $goods_label_list[$v1];
                        }
                    }

                }
            }
            $v['goods_label_list'] = $tmp_label;
            $goods_list[$v['goods_sn']] = $v;
            $goods_sn_arr[] = $v['goods_sn'];
        }

        //获取商品规格信息
        $field = [
            'gs.spec_id', 'gs.spec_sn', 'gs.erp_merchant_no', 'gs.erp_prd_no', 'gs.erp_ref_no', 'gs.goods_label', 'gs.goods_sn',
            'gs.spec_price', 'gs.spec_weight', 'gs.stock_num', 'gs.gold_discount', 'gs.foreign_discount', 'gs.exw_discount',
        ];
        $goods_spec_info = DB::table('goods_spec as gs')->whereIn('goods_sn', $goods_sn_arr)->get($field);
        $goods_spec_info = objectToArrayZ($goods_spec_info);
        $spec_list = [];
        $spec_sn = [];
        foreach ($goods_spec_info as $k => $v) {
            $spec_list[$v['spec_sn']] = $v;
            $spec_sn[] = $v['spec_sn'];
        }

        //获取商品编码
        $field = [
            'gc.id', 'gc.spec_sn', 'gc.goods_code', 'gc.code_type'
        ];
        $goods_code_info = DB::table('goods_code as gc')->whereIn('spec_sn', $spec_sn)->get($field);
        $goods_code_info = objectToArrayZ($goods_code_info);
        foreach ($goods_code_info as $k => $v) {
            $spec_sn = $v['spec_sn'];
            if (isset($spec_list[$spec_sn])) {
                $code_type = $this->platform;
                $v['code_type_name'] = $code_type[$v['code_type']];
                $spec_list[$spec_sn]['goods_code_info'][] = $v;
            }
        }
        foreach ($spec_list as $k => $v) {
            $goods_sn = $v['goods_sn'];
            if (isset($goods_list[$goods_sn])) {
                $goods_list[$goods_sn]['goods_spec_info'][] = $v;
            }
        }
        $goods_info['data'] = array_values($goods_list);
        return $goods_info;
    }

    /**
     * description:获取商品列表
     * editor:zongxing
     * date : 2018.08.25
     * return object
     */
    public function updateGoodsList_stop()
    {
        $goods_list_info = $this->goodsList();
        $goods_list_info = json_encode($goods_list_info);
        Redis::set("goods_list_info", $goods_list_info);
    }


    /**
     * description:获取商品规格列表
     * editor:zongxing
     * date : 2018.08.25
     * return object
     */
    public function goodsSpecList($req_params)
    {
        $goods_sn = $req_params["goods_sn"];
        $total_spec_info = DB::table("goods_spec")->where("goods_sn", $goods_sn)->get();
        return $total_spec_info;
    }

    /**
     * description:组装采购批次详情搜索条件
     * editor:zongxing
     * date : 2018.07.20
     * return String
     */
    public function createQueryGoodsList($get_goods_info)
    {
        $start_page = isset($get_goods_info['start_page']) ? intval($get_goods_info['start_page']) : 1;
        $page_size = isset($get_goods_info['page_size']) ? intval($get_goods_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;

        $sql_goods_list = "SELECT goods_id,goods_name,is_on_sale,b.name AS brand_name,c.cat_name,g.goods_sn FROM jms_goods AS g 
            LEFT JOIN jms_goods_spec AS gs ON gs.goods_sn = g.goods_sn 
            LEFT JOIN jms_brand AS b ON b.brand_id = g.brand_id 
            LEFT JOIN jms_category AS c ON c.cat_id = g.cat_id WHERE 1=1 ";
        $sql_goods_total = "SELECT COUNT(goods_id)AS data_num FROM jms_goods AS g 
            LEFT JOIN jms_goods_spec AS gs ON gs.goods_sn = g.goods_sn WHERE 1=1 ";
        if (isset($get_goods_info['query_sn'])) {
            $query_sn = trim($get_goods_info['query_sn']);
            $query_sn = "%%" . $query_sn . "%%";
            $sql_goods_list .= "AND (spec_sn LIKE '" . $query_sn . "' OR goods_name LIKE '"
                . $query_sn . "' OR erp_prd_no LIKE '" . $query_sn . "' OR erp_merchant_no LIKE '" . $query_sn . "')";
            $sql_goods_total .= "AND (spec_sn LIKE '" . $query_sn . "' OR goods_name LIKE '"
                . $query_sn . "' OR erp_prd_no LIKE '" . $query_sn . "' OR erp_merchant_no LIKE '" . $query_sn . "')";
        }
        $sql_goods_list .= " ORDER BY g.create_time DESC LIMIT $start_str,$page_size";

        $return_info["sql_goods_total"] = $sql_goods_total;
        $return_info["sql_goods_list"] = $sql_goods_list;
        return $return_info;
    }


    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function getbatchListPredict($purchase_info)
    {
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["pd.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $demand_goods_info = DB::table("demand_count as dc")
            ->where(function ($query) {
                $query->where("pd.status", "2")
                    ->orWhere('pd.status', '3');
            })
            ->where($where)
            ->select("pd.id as purchase_id", "dc.purchase_sn", "pd.predict_day",
                DB::raw('sum(real_buy_num) as real_buy_num'), DB::raw("Date(delivery_time) as delivery_time"))
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "dc.purchase_sn")
            ->leftJoin("real_purchase_detail as rpd", "rpd.purchase_sn", "=", "dc.purchase_sn")
            ->where("rpd.day_buy_num", ">", 0)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("dc.purchase_sn")
            ->get();
        $demand_goods_info = json_decode(json_encode($demand_goods_info), true);

        $return_info = [];
        $return_info["data_num"] = count($demand_goods_info);
        $return_info["purchase_info"] = [];

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $demand_goods_info = array_slice($demand_goods_info, $start_str, $page_size);

        foreach ($demand_goods_info as $k => $v) {
            //计算当前采购期下面的自提批次数
            $v["zt_num"] = 1; //自提总数一直都为:1

            //计算当前采购期下面的商品自提总数
            $zt_goods_num = DB::table("real_purchase as rp")
                ->where("path_way", "0")
                ->where("rp.purchase_sn", $v["purchase_sn"])
                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
                ->sum("day_buy_num");
            $v["zt_goods_num"] = $zt_goods_num;

            //计算当前采购期下面的邮寄批次数
            $yj_num = DB::table("real_purchase")
                ->where("path_way", "1")
                ->where("purchase_sn", $v["purchase_sn"])
                ->count("real_purchase_sn");
            $v["yj_num"] = $yj_num;

            //计算当前采购期下面的商品邮寄总数
            $yj_goods_num = DB::table("real_purchase as rp")
                ->where("path_way", "1")
                ->where("rp.purchase_sn", $v["purchase_sn"])
                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
                ->sum("day_buy_num");
            $v["yj_goods_num"] = $yj_goods_num;

            //计算实时数据列表信息
            $goods_list_info = DB::table("real_purchase as rp")
                ->where("rp.purchase_sn", $v["purchase_sn"])
                ->where("rp.status", 1)
                ->select("rp.real_purchase_sn", "port_id", "path_way", "rp.is_setting",
                    DB::raw('sum(day_buy_num) as total_buy_num'),
                    DB::raw("Date(jms_rp.create_time) as create_time"))
                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
                ->groupBy("rp.real_purchase_sn")
                ->get();
            $goods_list_info = $goods_list_info->toArray();

            $tmp_info["title_info"] = $v;
            if ($goods_list_info) {
                $tmp_info["list_info"] = $goods_list_info;
            }
            array_push($return_info["purchase_info"], $tmp_info);
        }
        return $return_info;
    }

    /**
     * description:获取批次列表
     * editor:zongxing
     * type:GET
     * date : 2018.07.09
     * return Object
     */
    public function getbatchListReal($purchase_info)
    {
        $where = [];
        if (isset($purchase_info['query_sn']) && !empty($purchase_info['query_sn'])) {
            $purchase_sn = trim($purchase_info['query_sn']);
            $purchase_sn = "%" . $purchase_sn . "%";
            $where = [
                ["pd.purchase_sn", "LIKE", "$purchase_sn"]
            ];
        }

        $demand_goods_info = DB::table("demand_count as dc")
            ->where(function ($query) {
                $query->where("pd.status", "2")
                    ->orWhere('pd.status', '3');
            })
            ->where($where)
            ->select("pd.id as purchase_id", "dc.purchase_sn", "pd.predict_day",
                DB::raw('sum(real_buy_num) as real_buy_num'), DB::raw("Date(delivery_time) as delivery_time"))
            ->leftJoin("purchase_date as pd", "pd.purchase_sn", "=", "dc.purchase_sn")
            ->leftJoin("real_purchase_detail as rpd", "rpd.purchase_sn", "=", "dc.purchase_sn")
            ->where("rpd.day_buy_num", ">", 0)
            ->orderBy('pd.create_time', 'desc')
            ->groupBy("dc.purchase_sn")
            ->get();
        $demand_goods_info = objectToArrayZ($demand_goods_info);

        $return_info = [];
        $return_info["data_num"] = count($demand_goods_info);
        $return_info["purchase_info"] = [];

        $start_page = isset($purchase_info['start_page']) ? intval($purchase_info['start_page']) : 1;
        $page_size = isset($purchase_info['page_size']) ? intval($purchase_info['page_size']) : 15;
        $start_str = ($start_page - 1) * $page_size;
        $demand_goods_info = array_slice($demand_goods_info, $start_str, $page_size);

        foreach ($demand_goods_info as $k => $v) {
            //计算当前采购期下面的自提批次数
            $v["zt_num"] = 1; //自提总数一直都为:1

            //计算当前采购期下面的商品自提总数
            $zt_goods_num = DB::table("real_purchase as rp")
                ->where("path_way", "0")
                ->where("rp.purchase_sn", $v["purchase_sn"])
                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
                ->sum("day_buy_num");
            $v["zt_goods_num"] = $zt_goods_num;

            //计算当前采购期下面的邮寄批次数
            $yj_num = DB::table("real_purchase")
                ->where("path_way", "1")
                ->where("purchase_sn", $v["purchase_sn"])
                ->count("real_purchase_sn");
            $v["yj_num"] = $yj_num;

            //计算当前采购期下面的商品邮寄总数
            $yj_goods_num = DB::table("real_purchase as rp")
                ->where("path_way", "1")
                ->where("rp.purchase_sn", $v["purchase_sn"])
                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
                ->sum("day_buy_num");
            $v["yj_goods_num"] = $yj_goods_num;

            //计算实时数据列表信息
            $goods_list_info = DB::table("real_purchase as rp")
                ->where("rp.purchase_sn", $v["purchase_sn"])
                ->where("rp.status", ">=", 2)
                ->select("rp.real_purchase_sn", "port_id", "path_way", "rp.is_setting",
                    DB::raw('sum(day_buy_num) as total_buy_num'),
                    DB::raw("Date(jms_rp.create_time) as create_time"))
                ->leftJoin("real_purchase_detail as rpd", "rpd.real_purchase_sn", "=", "rp.real_purchase_sn")
                ->groupBy("rp.real_purchase_sn")
                ->get();
            $goods_list_info = $goods_list_info->toArray();

            $tmp_info["title_info"] = $v;
            if ($goods_list_info) {
                $tmp_info["list_info"] = $goods_list_info;
            }
            array_push($return_info["purchase_info"], $tmp_info);
        }
        return $return_info;
    }


    /**
     * @description:计算商品价格（结果四舍五入保留两位小数）
     * @editor:张冬
     * @date : 2018.10.10
     * @param $spec_price (美金原价)
     * @param $discount (折扣)
     * @return array
     */
    public function calculateGoodsPrice($spec_price, $gold_discount, $black_discount)
    {
        $goldPrice = round($spec_price * $gold_discount, 2);
        $blackPrice = round($spec_price * $black_discount, 2);
        $arrPrice = [
            'goldPrice' => $goldPrice,
            'blackPrice' => $blackPrice,
        ];
        return $arrPrice;

    }

    /**
     * @description:获取erp仓库信息
     * @editor:张冬
     * @date : 2018.10.10
     * @param $store_id
     * @return object
     */
    public function getErpStoreInfo($store_id = '')
    {
        $store_id = intval($store_id);
        $where = $store_id == 0 ? [] : [['store_id', $store_id]];
        $field = ['store_name', 'store_factor', 'store_id'];
        $storehouseInfo = DB::table('erp_storehouse')->select($field)->where($where)->get()
            ->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $storehouseInfo;
    }

    /**
     * @description:在二维数组中搜索，返回对应键名
     * @editor:张冬
     * @date : 2018.10.10
     * @param $arrData (数组)
     * @param $columnValue (键值)
     * @param $column (键名)
     * @return int
     */
    public function twoArraySearch($arrData, $columnValue, $column)
    {
        $found_key = array_search($columnValue, array_column($arrData, $column));
        return $found_key;
    }


    /**
     * @description:计算有关erp的所有商品数据
     * @editor:张冬
     * @date : 2018.10.10
     * @param $spec_weight
     * @param $spec_price
     * @param $store_factor
     * @param $exw_discount
     * @return array
     */
    public function getErpGoodsData($spec_weight, $spec_price, $store_factor, $exw_discount)
    {
        //美元汇率
        $dollar_rate = config('constants.DOLLAR_RATE');
        //重价比=重量/美金原价/重价系数/100
        $highPriceRatio = 0;
        if ($spec_price > 0 && $store_factor > 0) {
            $highPriceRatio = round($spec_weight / $spec_price / $store_factor / 100, DECIMAL_DIGIT);
        }
        //重价比折扣 = exw折扣+重价比
        $hprDiscount = $exw_discount + $highPriceRatio;
        //erp成本价 = 美金原价*重价比折扣*汇率
        $erpCostPrice = round($spec_price * $hprDiscount * $dollar_rate, DECIMAL_DIGIT);
        $erpGoodsData = [
            'highPriceRatio' => $highPriceRatio,
            'hprDiscount' => $hprDiscount,
            'erpCostPrice' => $erpCostPrice,
        ];
        return $erpGoodsData;
    }


    /**
     * @description:获取自采毛利率档位信息
     * @editor:张冬
     * @date : 2018.10.11
     * @return array
     */
    public function getPickMarginInfo($pick_margin_rate = '')
    {
        $where = empty($pick_margin_rate) ? [] : [['pick_margin_rate', trim($pick_margin_rate)]];
        $field = ['pick_margin_rate'];
        $marginRateInfo = DB::table('margin_rate')->select($field)->where($where)->orderBy('pick_margin_rate', 'ASC')->get()
            ->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $marginRateInfo;
    }

    /**
     * @description:计算定价折扣相关的数据
     * @editor:张冬
     * @date : 2018.10.11
     * @param $spec_price (美金原价)
     * @param $pricing_rate (定价折扣)
     * @param $hrp_discount (重价比折扣)
     * @return array
     */
    public function calculPricingInfo($spec_price, $pricing_rate, $hrp_discount, $chargeInfo)
    {
        //美元汇率
        $dollar_rate = config('constants.DOLLAR_RATE');
        //销售价(人民币) = 美金原价*定价折扣*汇率
        $salePrice = round($spec_price * $pricing_rate * $dollar_rate, DECIMAL_DIGIT);
        //销售毛利率 = 1-重价比折扣/定价折扣
        $saleMarRate = 0;
        if ($pricing_rate > 0) {
            $saleMarRate = round(1 - $hrp_discount / $pricing_rate, DECIMAL_DIGIT);
        }
        //获取费用百分比 = $pricing_rate * 各费用比率
        $arrChargeRate = [];
        $totalChaRate = 0;
        $totalGoodsChaRate = 0;
        foreach ($chargeInfo as $item) {
            $chargeRate = sprintf('%.0f%%', $item['charge_rate']);//当前费用比率
            $goodsChaRate = round($pricing_rate * $item['charge_rate'] / 100, DECIMAL_DIGIT);
            $arrChargeRate[] = [$chargeRate => $goodsChaRate];
            $totalChaRate += $chargeRate;
            $totalGoodsChaRate += $goodsChaRate;
        }
        $totalRate = [sprintf('%.0f%%', $totalChaRate) => $totalGoodsChaRate];
        //运营毛利 = 销售毛利率 - 费用合计
        $runMarRate = ($saleMarRate - $totalGoodsChaRate) * 100;
//        $runMarRate = sprintf('%.2f%%', $a);
        $arrChargeRate[] = $totalRate;
        $pricingInfo = [
            'salePrice' => $salePrice,
            'saleMarRate' => $saleMarRate,
            'runMarRate' => round($runMarRate, 2),
            'arrChargeRate' => $arrChargeRate,
        ];
        return $pricingInfo;
    }


    /**
     * @description:获取费用项
     * @editor:张冬
     * @date : 2018.10.11
     * @return array
     */
    public function getChargeInfo($department_id)
    {
        $department_id = intval($department_id);
        $field = ['charge_rate', 'charge_name', 'create_time'];
        $where = [
            ['department_id', $department_id]
        ];
        $chargeInfo = DB::table('charge')->select($field)->where($where)->orderBy('charge_rate', 'ASC')->get()
            ->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $chargeInfo;
    }

    /**
     * @description:二维数组去重
     * @editor:zhangdong
     * @date : 2018.10.11
     * @param $array2D
     * @return array
     */
    public function array_unique_fb($array2D)
    {
        foreach ($array2D as $k => $v) {
            $v = join(",", $v); //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
            $temp[$k] = $v;
        }
        $temp = array_unique($temp);//去掉重复的字符串,也就是重复的一维数组
        foreach ($temp as $k => $v) {
            $result[$k] = $array2D[$k];//再将拆开的数组重新组装
        }
        return $result;
    }

    /**
     * @description:获取毛利率信息
     * @editor:zhangdong
     * @date : 2018.10.12
     * @param $mar_rate 要查询的毛利率
     * @return array
     */
    public function getMarRate($mar_rate)
    {
        $mar_rate = trim($mar_rate);
        $field = ['pick_margin_rate'];
        $where = [
            ['pick_margin_rate', $mar_rate]
        ];
        $marginRateInfo = DB::table('margin_rate')->select($field)->where($where)->get();
        return $marginRateInfo;
    }

    /**
     * @description:新增毛利率
     * @editor:zhangdong
     * @date : 2018.10.12
     * @param $mar_rate 要新增的毛利率
     * @return array
     */
    public function addNewMarRate($mar_rate)
    {
        $mar_rate = intval($mar_rate);
        $addData = ['pick_margin_rate' => $mar_rate];
        $addRes = DB::table('margin_rate')->insert($addData);
        return $addRes;
    }

    /**
     * @description:单个修改需求商品定价折扣（有则更新无则新增）
     * @editor:zhangdong
     * @date : 2018.10.12
     * @param $demand_sn 需求单号
     * @param $spec_sn 规格码
     * @param $pricing_rate 定价折扣
     * @return bool
     */
    public function updatePricRate($demand_sn, $spec_sn, $pricing_rate)
    {
        $demand_sn = trim($demand_sn);
        $spec_sn = trim($spec_sn);
        $pricing_rate = trim($pricing_rate);
        $where = [
            ['demand_sn', $demand_sn],
            ['spec_sn', $spec_sn],
        ];
        $updateData = ['pricing_rate' => $pricing_rate];
        $updateRes = DB::table('pricing_rate')->where($where)->update($updateData);
        return $updateRes;
    }

    /**
     * @description:批量修改需求商品定价折扣（有则更新无则新增）
     * @editor:zhangdong
     * @date : 2018.10.12
     * @param $demand_sn 需求单号
     * @param $pricing_rate 定价折扣
     * @return bool
     */
    public function batchOpdatePricRate($demand_sn, $pricing_rate)
    {
        $demand_sn = trim($demand_sn);
        $pricing_rate = trim($pricing_rate);
        $where = [
            ['demand_sn', $demand_sn],
        ];
        $updateData = ['pricing_rate' => $pricing_rate];
        $updateRes = DB::table('pricing_rate')->where($where)->update($updateData);
        return $updateRes;
    }

    /**
     * @description:新增费用项
     * @editor:zhangdong
     * @date : 2018.10.15
     * @param $charge_rate 费用比例
     * @param $charge_name 费用名称
     * @return boolean
     */
    public function addCharge($charge_rate, $charge_name, $department_id)
    {
        $charge_rate = trim($charge_rate);
        $charge_name = trim($charge_name);
        $addData = ['charge_rate' => $charge_rate, 'charge_name' => $charge_name, 'department_id' => $department_id];
        $addRes = DB::table('charge')->insert($addData);
        return $addRes;
    }

    /**
     * @description:检查是否已经存在该费用
     * @editor:zhangdong
     * @date : 2018.10.15
     * @param $charge_name 要查询的费用项
     * @return array
     */
    public function getCharMsg($charge_name, $department_id)
    {
        $charge_name = trim($charge_name);
        $field = ['charge_rate', 'charge_name', 'department_id'];
        $where = [
            ['charge_name', $charge_name],
            ['department_id', $department_id],
        ];
        $charMsg = DB::table('charge')->select($field)->where($where)->get();
        return $charMsg;
    }


    /**
     * @description:修改费用比例
     * @editor:zhangdong
     * @date : 2018.10.16
     * @param $charge_name 要查询的费用项
     * @param $charge_rate 费用比例
     * @return boolean
     */
    public function modifyCharge($charge_rate, $charge_name, $department_id)
    {
        $where = [
            ['charge_name', $charge_name],
            ['department_id', $department_id],
        ];
        $update = ['charge_rate' => $charge_rate];
        $modRes = DB::table('charge')->where($where)->update($update);
        return $modRes;
    }

    /**
     * description:商品模块-销售用户列表
     * editor:zhangdong
     * date : 2018.10.31
     * return Object
     */
    public function saleUserList($params, $pageSize)
    {
        //搜索关键字
        $keywords = $params['keywords'];
        $where = [];
        if ($keywords) {
            $where = [
                ['su.user_name', 'LIKE', "%$keywords%"],
            ];
        }

        $field = [
            'su.id', 'su.user_name', 'su.depart_id', 'su.min_profit', "sale_short", "payment_cycle", "group_sn",
            DB::raw('COUNT(DISTINCT jms_sug.spec_sn) AS sku_num'),
            DB::raw('(CASE jms_su.depart_id WHEN 1 THEN "批发部" WHEN 2 THEN "零售部" END) AS depart_name'),
            DB::raw("(CASE jms_su.sale_user_cat WHEN 'Z' THEN '账期' WHEN 'XY' THEN '现结' END) AS sale_user_cat"),
            DB::raw("(CASE jms_su.money_cat WHEN 'D' THEN '美金' WHEN 'C' THEN '人民币' END) AS money_cat"),//modify by zongxing 2018.12.06
        ];
        $sug_on = [
            ['su.id', '=', 'sug.sale_user_id'],
            [DB::raw('LENGTH(jms_sug.spec_sn)'), '>', DB::raw(0)]
        ];
        $saleUserList = DB::table('sale_user AS su')->select($field)
            ->leftJoin('sale_user_goods AS sug', $sug_on)
            ->where($where)->groupBy('su.id')->paginate($pageSize);
        return $saleUserList;
    }

    /**
     * description:商品模块-获取销售用户商品列表
     * editor:zhangdong
     * date : 2018.10.31
     * return Object
     * @param $demand_sn (需求单号)
     * @param $pickMarginRate (自采毛利率(array))
     * @param $chargeInfo (费用(array))
     * @return array
     */
    public function userGoodsList($goodsBaseInfo, $pickMarginRate, $chargeInfo, $goodsHouseInfo, $store_id)
    {
        //查找对应仓库id的键名(在仓库信息的二维数组中查找对应的仓库id)-避免循环查询数据库
        $found_key = $this->twoArraySearch($goodsHouseInfo, $store_id, 'store_id');
        $store_name = trim($goodsHouseInfo[$found_key]['store_name']);
        $store_factor = trim($goodsHouseInfo[$found_key]['store_factor']);//重价系数
        //计算其他商品信息
        $brandInfo = [];
        foreach ($goodsBaseInfo as $key => $value) {
            $brand_name = trim($value->brand_name);
            $brand_id = trim($value->brand_id);
            $brandInfo[] = [
                'brand_name' => $brand_name,
                'brand_id' => $brand_id,
            ];
            $spec_price = trim($value->spec_price);
            if (empty($spec_price) || $spec_price <= 0) continue;
            $gold_discount = trim($value->gold_discount);
            $black_discount = trim($value->black_discount);
            $goodsPrice = $this->calculateGoodsPrice($spec_price, $gold_discount, $black_discount);
            //金卡价=美金原价*金卡折扣
            $goodsBaseInfo[$key]->gold_price = $goodsPrice['goldPrice'];
            //黑卡价=美金原价*黑卡折扣
            $goodsBaseInfo[$key]->black_price = $goodsPrice['blackPrice'];
            //计算erp仓库相关的数据（仓库名称，重价比，重价比折扣，ERP成本价￥，ERP成本折扣=重价比折扣）
            //仓库名称
            $goodsBaseInfo[$key]->store_name = $store_name;
            //如果没有真实重量则取预估重量，正常情况下这两个重量必定有一个不为空
            $goods_weight = floatval($value->spec_weight);
            if ($goods_weight <= 0) {
                $goods_weight = floatval($value->estimate_weight);
            }
            $exw_discount = trim($value->exw_discount); //exw折扣
            //获取有关erp的所有商品数据
            $erpGoodsData = $this->getErpGoodsData($goods_weight, $spec_price, $store_factor, $exw_discount);
            //重价比=重量/美金原价/重价系数/100
            $goodsBaseInfo[$key]->high_price_ratio = $erpGoodsData['highPriceRatio'];
            //重价比折扣 = exw折扣+重价比
            $hrp_discount = $erpGoodsData['hprDiscount'];
            $goodsBaseInfo[$key]->hpr_discount = $hrp_discount;
            //erp成本价=美金原价*重价比折扣*汇率
            $goodsBaseInfo[$key]->erp_cost_price = $erpGoodsData['erpCostPrice'];
            //计算自采毛利率相关数据
            //自采毛利率=重价比折扣/（1-对应档位利率）
            $arrMarginRate = [];
            $pricing_rate = 0;
            $margin_rate = config('constants.MARGIN_RATE_PERCENT');
            foreach ($pickMarginRate as $item) {
                $marginRate = sprintf('%.0f%%', $item['pick_margin_rate']);//自采毛利率当前档位
                $rateData = round($erpGoodsData['hprDiscount'] / (1 - $item['pick_margin_rate'] / 100), DECIMAL_DIGIT);
                $arrMarginRate[] = [$marginRate => $rateData];
                $goodsBaseInfo[$key]->arrMarginRate = $arrMarginRate;
                //定价折扣默认档位
                if ($marginRate == $margin_rate) {
                    $pricing_rate = $rateData;
                };
            }
            $pricing_rate = isset($value->sale_discount) ? trim($value->sale_discount) : $pricing_rate;
            $pricingRateInfo = $this->calculPricingInfo($spec_price, $pricing_rate, $hrp_discount, $chargeInfo);
            $goodsBaseInfo[$key]->sale_discount = $pricing_rate;//销售折扣
            $goodsBaseInfo[$key]->salePrice = $pricingRateInfo['salePrice'];//销售价
            $goodsBaseInfo[$key]->saleMarRate = $pricingRateInfo['saleMarRate'];//销售毛利率
            $goodsBaseInfo[$key]->runMarRate = $pricingRateInfo['runMarRate'];//运营毛利率
            $goodsBaseInfo[$key]->arrChargeRate = $pricingRateInfo['arrChargeRate'];//费用项
        }
        $returnData = [
            'goodsBaseInfo' => $goodsBaseInfo,
            'brandInfo' => $brandInfo,
        ];
        return $returnData;
    }

    /**
     * description:获取销售用户商品信息
     * editor:zhangdong
     * date : 2018.10.31
     * @param $sale_user_id 销售用户id
     * @return object
     */
    public function getUserGoodsInfo($sale_user_id, $keywords, $pageSize, $arrErpNo)
    {
        $arrField = [
            'b.name as brand_name', 'b.brand_id', 'g.goods_name', 'gs.spec_price',
            'gs.spec_weight', 'gs.gold_discount', 'gs.black_discount', 'gs.exw_discount',
            'gs.foreign_discount', 'gs.spec_sn', 'gs.erp_merchant_no', 'sug.sale_discount'
        ];
        $where = [
            ['sug.sale_user_id', '=', $sale_user_id],
            [DB::raw('LENGTH(jms_sug.spec_sn)'), '>', 0],
        ];
        if (!empty($keywords)) {
            $where[] = ['gs.erp_merchant_no', 'LIKE', "%$keywords%"];
        }
        $whereIn = [];
        if (count($arrErpNo) > 0) {
            $whereIn = $arrErpNo;
        }
        $goodsBaseInfo = DB::table('sale_user_goods AS sug')->select($arrField)
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'sug.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->where(function ($query) use ($where, $whereIn) {
                $query->where($where);
                if (count($whereIn) > 0) {
                    $query->whereIn('sug.erp_merchant_no', $whereIn);
                }
            })->paginate($pageSize);
        return $goodsBaseInfo;
    }

    /**
     * description:根据用户id获取销售折扣
     * editor:zhangdong
     * date : 2018.10.31
     * @param $sale_user_id 销售用户id
     * @return object
     */
    public function getUserSaleDiscount($sale_user_id)
    {
        $field = ['spec_sn', 'sale_discount'];
        $where = [
            ['sale_user_id', $sale_user_id],
            [DB::raw('LENGTH(spec_sn)'), '>', 0],
        ];
        $queryRes = DB::table('sale_user_goods')->select($field)->where($where)->get()
            ->map(function ($value) {
                return (array)$value;
            })->toArray();
        return $queryRes;
    }

    /**
     * description:根据上传的商品数据组装商品信息
     * editor:zhangdong
     * date : 2018.11.01
     * @return object
     */
    public function getUpGoodsData($arr_erp_no)
    {
        //查询商品数据
        $field = [
            'b.name as brand_name', 'b.brand_id', 'g.goods_name', 'gs.spec_price', 'gs.spec_weight',
            'gs.gold_discount', 'gs.black_discount', 'gs.exw_discount', 'gs.foreign_discount',
            'gs.spec_sn', 'gs.erp_merchant_no'
        ];
        $goodsBaseInfo = DB::table('goods_spec AS gs')->select($field)
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->whereIn('gs.erp_merchant_no', $arr_erp_no)
            ->get();
        $data = [
            'goodsBaseInfo' => $goodsBaseInfo,
            'erp_merchant_no' => $arr_erp_no
        ];
        return $data;

    }

    /**
     * description:更新或者新增销售用户商品数据
     * editor:zhangdong
     * date : 2018.11.01
     * @return object
     */
    public function saveUserGoods($userGoodsData, $sale_user_id, $arrErpNo, $department_id)
    {
        //查询销售用户的商品数据
        $field = ['sale_user_id', 'erp_merchant_no', 'spec_sn', 'depart_id'];
        $where = [
            ['sale_user_id', $sale_user_id]
        ];
        $goods = DB::table('sale_user_goods')->select($field)
            ->where($where)
            ->whereIn('erp_merchant_no', $arrErpNo)
            ->get()->map(function ($v) {
                return (array)$v;
            })->toArray();
        //将销售用户没有的数据保存
        $saveData = [];
        foreach ($userGoodsData as $key => $value) {
            $erpNo = trim($value->erp_merchant_no);
            $spec_sn = trim($value->spec_sn);
            $pricing_rate = trim($value->sale_discount);
            //在已经查到的销售用户商品数据中搜索，如果未搜到则保存
            $fundKey = twoArraySearch($goods, $erpNo, 'erp_merchant_no');
            if ($fundKey !== false) continue;
            $saveData[] = [
                'depart_id' => $department_id,
                'sale_user_id' => $sale_user_id,
                'erp_merchant_no' => $erpNo,
                'spec_sn' => $spec_sn,
                'sale_discount' => $pricing_rate,
            ];

        }
        if (count($saveData) > 0) {
            DB::table('sale_user_goods')->insert($saveData);
        }
        return true;

    }

    /**
     * @description:单个修改需求商品销售折扣
     * @editor:zhangdong
     * @date : 2018.11.01
     * @param $sale_user_id 销售用户id
     * @param $spec_sn 规格码
     * @param $pricing_rate 定价折扣
     * @return bool
     */
    public function modUsrSaleDiscount($sale_user_id, $spec_sn, $pricing_rate)
    {
        $where = [
            ['sale_user_id', $sale_user_id],
            ['spec_sn', $spec_sn],
        ];
        $updateData = ['sale_discount' => $pricing_rate];
        $updateRes = DB::table('sale_user_goods')->where($where)->update($updateData);
        return $updateRes;
    }

    /**
     * description:获取销售用户商品信息
     * editor:zhangdong
     * date : 2018.11.01
     * @return object
     */
    public function getSaleGoodsInfo($sale_user_id, $spec_sn = '')
    {
        $arrField = [
            'b.name as brand_name', 'b.brand_id', 'g.goods_name', 'gs.spec_price', 'gs.spec_weight', 'gs.gold_discount',
            'gs.black_discount', 'gs.exw_discount', 'gs.foreign_discount', 'gs.erp_merchant_no', 'gs.spec_sn'
        ];
        $where[] = ['sug.sale_user_id', $sale_user_id];
        if (!empty($spec_sn)) {
            $where[] = ['sug.spec_sn', $spec_sn];
        }
        $queryRes = DB::table('sale_user_goods AS sug')->select($arrField)
            ->leftJoin('goods_spec AS gs', 'gs.spec_sn', '=', 'sug.spec_sn')
            ->leftJoin('goods AS g', 'g.goods_sn', '=', 'gs.goods_sn')
            ->leftJoin('brand AS b', 'b.brand_id', '=', 'g.brand_id')
            ->where($where)->get();
        return $queryRes;
    }

    /**
     * description:更新需求商品定价折扣
     * editor:zhangdong
     * date : 2018.11.01
     * @return object
     */
    public function updateSaleDis($spec_sn, $sale_user_id, $pricing_rate)
    {
        $where = [
            ['spec_sn', $spec_sn],
            ['sale_user_id', $sale_user_id]
        ];
        $update = [
            'sale_discount' => $pricing_rate
        ];
        $updateRes = DB::table('sale_user_goods')->where($where)->update($update);
        return $updateRes;
    }

    /**
     * description:批量更新语句执行
     * editor:zhangdong
     * date : 2018.11.01
     * @return object
     */
    public function executeSql($strSql, $bindData)
    {
        $executeRes = DB::update($strSql, $bindData);
        return $executeRes;
    }

    /**
     * description:获取自采毛利率档位信息,根据部门获取费用项,获取部门信息,
     * 获取费用信息,获取erp仓库信息-重价系数（默认为香港仓）等报价必须信息
     * editor:zhangdong
     * date : 2018.11.01
     * @return array
     */
    public function getOfferInfo($depart_id)
    {
        //获取自采毛利率档位信息
        $pickMarginRate = $this->getPickMarginInfoInRedis();
        $arrPickRate = [];
        foreach ($pickMarginRate as $item) {
            $arrPickRate[] = sprintf('%.0f%%', trim($item['pick_margin_rate']));
        }
        //获取部门信息
        $departmentModel = new DepartmentModel();
        $departmentInfo = $departmentModel->getDepartmentInfoInRedis();
        //部门id
        $depart_id = intval($depart_id);
        //获取费用信息
        $chargeModel = new ChargeModel();
        $chargeInfo = $chargeModel->getChargeInfoInRedis();
        //查询当前部门的费用信息
        $departChargeInfo = searchTwoArray($chargeInfo, $depart_id, 'department_id');
        $arrCharge = [];
        $totalCharge = 0;
        foreach ($departChargeInfo as $item) {
            $formatChargeRate = sprintf(
                '%.0f%%', trim($item['charge_rate'])
            );
            $arrCharge[] = [
                $formatChargeRate => trim($item['charge_name']),
            ];
            $totalCharge += $item['charge_rate'];
        }
        $arrCharge[] = [
            sprintf('%.0f%%', $totalCharge) => '费用合计'
        ];
        //获取erp仓库信息-重价系数（默认为香港仓）
        $eshModel = new ErpStorehouseModel();
        $goodsHouseInfo = $eshModel->getErpStoreInfoInRedis();
        $offerInfo = [
            'departmentInfo' => $departmentInfo,
            'arrCharge' => $arrCharge,
            'chargeInfo' => $departChargeInfo,
            'goodsHouseInfo' => $goodsHouseInfo,
            'arrPickRate' => $arrPickRate,
            'pickMarginRate' => $pickMarginRate,
        ];
        return $offerInfo;

    }

    /*
     * @description 对上传的数据进行校验，如果有新品则做临时保存
     * @author zhangdong
     * @date 2018.11.01
     * @version 2 zhangdong 2019.06.25
     * @param $goodsData
     */
    public function checkGoods($goodsData)
    {
        $arrNewGoods = $arrGoodsInfo = [];
        $gcModel = new GoodsCodeModel();
        $gsModel = new GoodsSpecModel();
        foreach ($goodsData as $key => $value) {
            if ($key == 0) continue;
            //将商品编码组装成为数组
            $strGoodsCode = trim($value[3]);
            $arrGoodsCode = $this->createGoodsCode($strGoodsCode);
            //检查品牌ID，品牌，商品名称，平台条码，需求量等必填信息
            if (
                empty($value[0]) ||//品牌ID
                empty($value[1]) ||//品牌
                empty($value[2]) ||//商品名称
                empty($value[7]) ||//需求量
                count($arrGoodsCode) == 0
            ) {
                return false;
            }
            //在商品规格码对照表中查询商品规格码
            $getSpecRes = $gcModel->getSpec($arrGoodsCode);
            $spec_sn = '';
            if (!is_null($getSpecRes)) {
                $spec_sn = trim($getSpecRes->spec_sn);
            }
            //查询商品信息
            $goodsInfo = '';
            if (!empty($spec_sn)) {
                $goodsInfo = $gsModel->getGoodsMsg($spec_sn);
            }
            //将新品筛选出来，后续将将会写入到ord_new_goods表中
            if (empty($goodsInfo)) {
                $arrNewGoods[] = $value;
                continue;
            };
            $goodsInfo->goods_num = !empty($value[7]) ? intval($value[7]) : 0;
            $goodsInfo->entrust_time = !empty($value[8]) ? trim($value[8]) : '';
            $goodsInfo->sale_discount = !empty($value[9]) ? floatval($value[9]) : 0;
            $goodsInfo->wait_buy_num = !empty($value[10]) ? intval($value[10]) : 0;
            $spec_price = floatval($goodsInfo->spec_price);
            $goodsInfo->spec_price = !empty($value[4]) ? floatval($value[4]) : $spec_price;
            $spec_weight = floatval($goodsInfo->spec_weight);
            $goodsInfo->spec_weight = !empty($value[5]) ? floatval($value[5]) : $spec_weight;
            $exw_discount = floatval($goodsInfo->exw_discount);
            $goodsInfo->exw_discount = !empty($value[6]) ? floatval($value[6]) : $exw_discount;
            $goodsInfo->platform_barcode = $strGoodsCode;
            //如果可以查到商品信息则将该条信息保存
            $arrGoodsInfo[] = $goodsInfo;
        }//end of foreach
        $returnMsg = [
            'newGoods' => $arrNewGoods,
            'arrGoodsInfo' => $arrGoodsInfo,
        ];
        return $returnMsg;
    }

    /**
     * description:组装商品编码-上传总单专用
     * editor:zhangdong
     * date : 2019.01.31
     * @return array
     */
    private function createGoodsCode($strGoodsCode)
    {
        //处理不规则编码-去商品编码中所有的空格并将中文逗号转为英文逗号
        $strGoodsCode = $this->ruleStr($strGoodsCode);
        //将平台条码分割为数组
        $arrGoodsCode = explode(',', $strGoodsCode);
        //去除数组中值为空的元素
        $arrGoodsCode = array_filter($arrGoodsCode);
        return $arrGoodsCode;
    }

    /**
     * description：对字符串按要求进行处理使其规范化,最终返回数组-上传总单专用
     * editor:zhangdong
     * date : 2019.02.11
     * @return array
     */
    private function ruleStr($str)
    {
        //去空格
        $str = str_replace(' ', '', $str);
        //将中文逗号转为英文逗号
        $str = str_replace('，', ',', $str);
        return $str;
    }

    /**
     * description:组装商品信息
     * author：zhangdong
     * date : 2019.02.14
     */
    public function createGoodsInfo($res)
    {
        $goodsData = $specData = $codeData = [];
        $categoryModel = new CategoryModel();
        //查询三级分类信息
        $categoryInfo = $categoryModel->getCategoryInfo(3, 3);
        //查询品牌信息
        $brandModel = new BrandModel();
        $brandInfo = $brandModel->getBrandInfo();
        $arrBrandInfo = objectToArray($brandInfo);
        //将分类信息转换为数组
        $arrCategoryInfo = objectToArray($categoryInfo);
        $gcModel = new GoodsCodeModel();
        foreach ($res as $key => $v) {
            if ($key == 0) continue;
            //商品名称，商品分类，商品品牌等信息检查
            $excelNum = $key + 1;
            $goods_name = trim($v[0]);
            $category_name = trim($v[1]);
            $brand_id = intval($v[2]);
            if (empty($goods_name) || empty($category_name) || $brand_id == 0) {
                return ['code' => '2064', 'msg' => '第' . $excelNum . '条商品名称，分类，品牌ID等信息有误'];
            }
            //商品编码检查
            $erp_merchant_no = trim($v[4]);
            $kl_code = trim($v[5]);
            $xhs_code = trim($v[6]);
            if (empty($erp_merchant_no) && empty($kl_code) && empty($xhs_code)) {
                return ['code' => '2064', 'msg' => '第' . $excelNum . '条商品编码信息有误'];
            }
            //商品重量，EXW折扣信息检查
            $spec_weight = floatval($v[10]);
            $estimate_weight = floatval($v[11]);
            if ($spec_weight <= 0 && $estimate_weight <= 0) {
                return ['code' => '2064', 'msg' => '第' . $excelNum . '条商品重量和预估重量至少有一个'];
            }
            $exw_discount = floatval($v[12]);
            if ($exw_discount <= 0) {
                return ['code' => '2064', 'msg' => '第' . $excelNum . '条EXW折扣不可为空'];
            }
            //查询分类id
            $categoryInfo = twoArrayFuzzySearch($arrCategoryInfo, 'cat_name', $category_name);
            if (count($categoryInfo) == 0) {
                return ['code' => '2064', 'msg' => '第' . $excelNum . '条商品分类信息有误'];
            }
            $cat_id = intval($categoryInfo[0]['cat_id']);
            $cat_name = trim($categoryInfo[0]['cat_name']);
            //查询品牌id
            $brandInfo = searchTwoArray($arrBrandInfo, $brand_id, 'brand_id');
            if (count($brandInfo) == 0) {
                return ['code' => '2064', 'msg' => '第' . $excelNum . '条商品品牌信息有误'];
            }
            $brand_id = intval($brandInfo[0]['brand_id']);
            $brand_keywords = trim($brandInfo[0]['keywords']);
            //组装商品基本信息
            $goods_sn = $this->get_goods_sn();
            $goodsKeywords = $brand_keywords . '--' . $cat_name . '--' . $goods_name;
            $goodsData[] = [
                'goods_sn' => $goods_sn,
                'goods_name' => $goods_name,
                'keywords' => $goodsKeywords,
                'cat_id' => $cat_id,
                'brand_id' => $brand_id,
            ];
            //组装规格基本信息
            $spec_sn = $this->get_spec_sn($goods_sn);
            $specData[] = [
                'goods_sn' => $goods_sn,
                'spec_sn' => $spec_sn,
                'erp_merchant_no' => $erp_merchant_no,
                'erp_ref_no' => trim($v[7]),
                'erp_prd_no' => trim($v[8]),
                'spec_price' => floatval($v[9]),
                'spec_weight' => $spec_weight,
                'estimate_weight' => $estimate_weight,
                'exw_discount' => $exw_discount,
            ];
            //组装商品规格码对照信息
            if (!empty($erp_merchant_no)) {
                $codeData[] = [
                    'spec_sn' => $spec_sn,
                    'goods_code' => $erp_merchant_no,
                    'code_type' => $gcModel->code_type['ERP_MERCHANT_NO'],
                ];
            }

            if (!empty($xhs_code)) {
                $codeData[] = [
                    'spec_sn' => $spec_sn,
                    'goods_code' => $xhs_code,
                    'code_type' => $gcModel->code_type['XHS_CODE'],
                ];
            }

            $suid = 1;//考拉ID
            $klCode = $gcModel->makeGoodsCode($kl_code, $spec_sn, $suid);
            if (count($klCode) > 0) {
                $codeData = array_merge($klCode, $codeData);
            }
            //根据商品编码检查商品是否已经被创建过
            $arrGoodsCode = getFieldArrayVaule($codeData, 'goods_code');
            $codeSpecSn = $gcModel->getSpec($arrGoodsCode);
            if (!empty($codeSpecSn)) {
                return ['code' => '2064', 'msg' => '第' . $excelNum . '条商品已被创建'];
            }
        }//end of foreach
        $goodsInfo = [
            'goodsData' => $goodsData,
            'specData' => $specData,
            'codeData' => $codeData,
        ];
        return $goodsInfo;

    }//end of function


    /**
     * description:批量新增商品
     * author：zhangdong
     * date : 2019.02.14
     */
    public function batchInsertGoods($goodsInfoData)
    {
        $insertRes = DB::transaction(function () use ($goodsInfoData) {
            $goodsData = $goodsInfoData['goodsData'];
            DB::table('goods')->insert($goodsData);
            $specData = $goodsInfoData['specData'];
            DB::table('goods_spec')->insert($specData);
            $codeData = $goodsInfoData['codeData'];
            $insertRes = DB::table('goods_code')->insert($codeData);
            return $insertRes;
        });
        return $insertRes;

    }

    /**
     * @description:从redis中获取自采毛利率档位信息
     * @editor:张冬
     * @date : 2019.02.28
     * @return array
     */
    public function getPickMarginInfoInRedis()
    {
        //从redis中获取自采毛利率档位信息，如果没有则对其设置
        $marginRateInfo = Redis::get('marginRateInfo');
        if (empty($marginRateInfo)) {
            $field = ['pick_margin_rate'];
            $marginRateInfo = DB::table('margin_rate')->select($field)->orderBy('pick_margin_rate', 'ASC')
                ->get()->map(function ($value) {
                    return (array)$value;
                })->toArray();
            Redis::set('marginRateInfo', json_encode($marginRateInfo, JSON_UNESCAPED_UNICODE));
            $marginRateInfo = Redis::get('marginRateInfo');
        }
        $marginRateInfo = objectToArray(json_decode($marginRateInfo));
        return $marginRateInfo;

    }

    /**
     * description:通过规格码获取商品信息
     * author:zhangdong
     * date : 2019.03.12
     * return Object
     */
    public function getGoodsMsgBySpecSn(array $arrSpecSn)
    {
        $field = [
            'gs.goods_sn', 'gs.spec_sn', 'g.goods_name', 'gs.erp_prd_no', 'gs.erp_merchant_no',
            'gs.spec_price', 'gs.spec_weight', 'gs.exw_discount', 'gs.stock_num', 'g.brand_id',
        ];
        $queryRes = DB::table('goods_spec AS gs')->select($field)
            ->leftJoin('goods AS g', 'gs.goods_sn', 'g.goods_sn')
            ->whereIn('spec_sn', $arrSpecSn)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();;
        return $queryRes;
    }

    /**
     * description:创建新商品-导入总单专用
     * author:zhangdong
     * date : 2019.04.16
     * @param $newGoodsInfo
     * @return mixed
     */
    public function createNewGoods($newGoodsInfo, $suid)
    {
        //品牌信息
        $brandModel = new BrandModel();
        $brandInfo = $brandModel->getBrandInfo();
        $arrBrandInfo = objectToArray($brandInfo);
        //分类信息
        $categoryModel = new CategoryModel();
        $categoryInfo = $categoryModel->getCategoryInfoInRedis();
        //组装商品基本信息
        $goodsInfo = $this->generalGoodsInfo($newGoodsInfo, $arrBrandInfo, $categoryInfo);
        //组装商品规格信息
        $gsModel = new GoodsSpecModel();
        $goods_sn = $goodsInfo['goods_sn'];
        $goodsSpecInfo = $gsModel->generalSpecInfo($newGoodsInfo, $goods_sn);
        //组装商品编码信息
        $gcModel = new GoodsCodeModel();
        $spec_sn = $goodsSpecInfo['spec_sn'];
        $strGoodsCode = trim($newGoodsInfo['platform_barcode']);
        $goodsCodeInfo = $gcModel->makeGoodsCode($strGoodsCode, $spec_sn, $suid);
        if (count($goodsCodeInfo) == 0) {
            return false;
        }
        //写入数据
        $insertRes = $this->insertNewGoods($goodsInfo, $goodsSpecInfo, $goodsCodeInfo);
        return [
            'insertRes' => $insertRes,
            'spec_sn' => $spec_sn,
        ];
    }

    /**
     * description:组装商品基本信息
     * author:zhangdong
     * date : 2019.04.16
     * @return array
     */
    public function generalGoodsInfo($newGoodsInfo, array $arrBrandInfo = [], array $categoryInfo = [])
    {
        $goods_sn = $this->get_goods_sn();
        //品牌信息
        $brand_id = isset($newGoodsInfo['brand_id']) ? intval($newGoodsInfo['brand_id']) : 0;
        $brandInfo = searchTwoArray($arrBrandInfo, $brand_id, 'brand_id');
        $brand_id = isset($brandInfo[0]['brand_id']) ? intval($brandInfo[0]['brand_id']) : 0;
        $brand_keywords = isset($brandInfo[0]['keywords']) ? trim($brandInfo[0]['keywords']) : '';

        //分类信息
        $cat_name = isset($newGoodsInfo['cat_name']) ? trim($newGoodsInfo['cat_name']) : '缺失';
        $cateInfo = searchTwoArray($categoryInfo, $cat_name, 'cat_name');
        $cat_id = isset($cateInfo[0]['cat_id']) ? intval($cateInfo[0]['cat_id']) : 0;

        $goods_name = isset($newGoodsInfo['goods_name']) ? trim($newGoodsInfo['goods_name']) : '';
        $goodsKeywords = $brand_keywords . '--' . $cat_name . '--' . $goods_name;
        $goodsData = [
            'goods_sn' => $goods_sn,
            'goods_name' => $goods_name,
            'keywords' => $goodsKeywords,
            'cat_id' => $cat_id,
            'brand_id' => $brand_id,
        ];
        return $goodsData;
    }

    /**
     * description:总单导入-写入新品数据
     * author:zhangdong
     * date : 2019.04.16
     * @return bool
     */
    public function insertNewGoods($goodsInfo, $goodsSpecInfo, $goodsCodeInfo)
    {
        $saveRes = DB::transaction(function () use ($goodsInfo, $goodsSpecInfo, $goodsCodeInfo) {
            //商品基本数据保存
            $goodsModel = new GoodsModel();
            $goods = cutString($goodsModel->table, 0, 'as');
            DB::table($goods)->insert($goodsInfo);
            //规格信息保存
            $gsModel = new GoodsSpecModel();
            $spec = cutString($gsModel->table, 0, 'as');
            DB::table($spec)->insert($goodsSpecInfo);
            //商品编码信息保存
            $gcModel = new GoodsCodeModel();
            $gcTable = cutString($gcModel->table, 0, 'as');
            $insertRes = DB::table($gcTable)->insert($goodsCodeInfo);
            return $insertRes;
        });
        return $saveRes;

    }

    /**
     * description:总单新品-批量创建新品
     * author:zhangdong
     * date : 2019.04.17
     * @return mixed
     */
    public function batchCreateGoods($newGoodsInfo, $suid)
    {
        $goodsInfoList = $specInfoList = $codeInfoList = $arr_update = $arrPlatformBarcode = [];
        $newGoodsInfo = objectToArray($newGoodsInfo);
        //品牌信息
        $brandModel = new BrandModel();
        $brandInfo = $brandModel->getBrandInfo();
        $arrBrandInfo = objectToArray($brandInfo);
        $gsModel = new GoodsSpecModel();
        $gcModel = new GoodsCodeModel();
        foreach ($newGoodsInfo as $value) {
            //组装商品基本信息
            $goodsInfo = $this->generalGoodsInfo($value, $arrBrandInfo);
            $goodsInfoList[] = $goodsInfo;
            //组装商品规格信息
            $goods_sn = $goodsInfo['goods_sn'];
            $goodsSpecInfo = $gsModel->generalSpecInfo($value, $goods_sn);
            $specInfoList[] = $goodsSpecInfo;
            $platform_barcode = trim($value['platform_barcode']);
            $arrPlatformBarcode [] = $platform_barcode;
            //组装商品编码信息
            $spec_sn = $goodsSpecInfo['spec_sn'];
            $strGoodsCode = trim($value['platform_barcode']);
            $goodsCodeInfo = $gcModel->makeGoodsCode($strGoodsCode, $spec_sn, $suid);
            if (count($goodsCodeInfo) <= 0) {
                return false;
            }
            //将商品编码信息转为二维数组
            foreach ($goodsCodeInfo as $item) {
                $codeInfoList[] = $item;
            }
            $arr_update[] = [
                'platform_barcode' => $platform_barcode,
                'spec_sn' => $spec_sn,
            ];
        }
        //写入相应数据表
        $insertRes = $this->insertNewGoods($goodsInfoList, $specInfoList, $codeInfoList);
        //组装新品更新spec_sn的语句
        $table = 'jms_ord_new_goods';
        $arrSql = makeUpdateSql($table, $arr_update);
        return ['insertRes' => $insertRes, 'arrPlatformBarcode' => $arrPlatformBarcode, 'arrSql' => $arrSql];

    }


    /**
     * @description:总单新品补充-获取新品信息
     * @author:zhangdong
     * @date : 2019.04.18
     */
    public function getNewGoodsInfo($goodsData)
    {
        $arrGoodsInfo = [];
        foreach ($goodsData as $key => $value) {
            //查询方式 1，根据商家编码查询 2，根据规格码查询
            $queryType = 2;
            //查询商品信息
            $spec_sn = trim($value->spec_sn);
            $goodsInfo = '';
            if (!empty($spec_sn)) {
                $goodsInfo = $this->getGoodsInfo($spec_sn, $queryType);
            }
            $goodsInfo->goods_num = intval($value->goods_number);
            $goodsInfo->entrust_time = trim($value->entrust_time);
            $goodsInfo->sale_discount = trim($value->sale_discount);
            $goodsInfo->wait_buy_num = trim($value->wait_buy_num);
            $goodsInfo->platform_barcode = trim($value->platform_barcode);
            //如果可以查到商品信息则将该条信息保存
            $arrGoodsInfo[] = $goodsInfo;
        }//end of foreach
        return $arrGoodsInfo;
    }

    /**
     * @description:总单新品-新增规格-品名搜索
     * @author:zhangdong
     * @date:2019.04.22
     */
    public function searchGoodsName($goods_name)
    {
        $where = [
            ['goods_name', 'LIKE', '%' . $goods_name . '%'],
        ];
        $field = ['g.goods_sn', 'g.goods_name', 'b.name AS brand_name'];
        $brandModel = new BrandModel();
        $queryRes = DB::table($this->table)->select($field)->where($where)
            ->leftJoin($brandModel->getTable(), 'b.brand_id', 'g.brand_id')
            ->limit(20)->get();
        return $queryRes;
    }

    /**
     * @description:总单新品-通过商品货号获取商品信息
     * @author:zhangdong
     * @date:2019.04.22
     */
    public function getGoodsBySn($goods_sn)
    {
        $where = [
            ['goods_sn', $goods_sn],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }

    /**
     * @description:总单新品-通过商品货号等获取商品信息
     * @author:zongxing
     * @date:2019.10.19
     */
    public function searchGoodsInfo($param_info)
    {
        $field = ['g.goods_name', 'g.brand_id', 'gs.spec_sn', 'gs.erp_merchant_no', 'gs.erp_prd_no', 'gs.erp_ref_no',
            'gs.spec_price'];
        $query_sn = '%' . trim($param_info['query_sn']) . '%';
        $goods_info = DB::table($this->table)
            ->leftJoin('goods_spec as gs', 'gs.goods_sn', '=', 'g.goods_sn')
            ->where(function ($where) use ($query_sn) {
                $where->orWhere('gs.goods_sn', 'LIKE', $query_sn);
                $where->orWhere('gs.spec_sn', 'LIKE', $query_sn);
                $where->orWhere('gs.erp_merchant_no', 'LIKE', $query_sn);
                $where->orWhere('gs.erp_prd_no', 'LIKE', $query_sn);
                $where->orWhere('gs.erp_ref_no', 'LIKE', $query_sn);
                $where->orWhere('g.goods_name', 'LIKE', $query_sn);
                $where->orWhere('g.ext_name', 'LIKE', $query_sn);
                $where->orWhere('g.keywords', 'LIKE', $query_sn);
            })
            ->get($field);
        $goods_info = ObjectToArrayZ($goods_info);
        return $goods_info;
    }


}//end of class
