<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class DepartSortModel extends Model
{
    protected $table = "depart_sort as ds";

    //可操作字段
    protected $field = [
        'ds.id', 'ds.sort_sn','ds.purchase_sn','ds.real_pur_sn','ds.status','ds.create_time',
    ];

    /**
     * description:根据采购单和实采单统计条数
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function getDepartSortCount($purchase_sn, $real_purchase_sn, $realPurchaseStatus)
    {
        $where = [
            ['purchase_sn',$purchase_sn],
            ['real_pur_sn',$real_purchase_sn],
            ['is_use',1],
        ];
        if ($realPurchaseStatus > 2) {
            $where[] = ['status', $realPurchaseStatus];
        }
        $queryRes = DB::table($this->table)->where($where)->count();
        return $queryRes;
    }

    /**
     * description:将分配数据写入商品部按部门分货数据表
     * editor:zhangdong
     * date : 2019.02.19
     * @return bool
     */
    public function insertData($depart_sort, $corDepGoods, $real_purchase_sn)
    {
        if(count($depart_sort) == 0 || count($corDepGoods) == 0){
            return false;
        }
        $where = [
            ['real_purchase_sn', $real_purchase_sn],
        ];
        $insertRes = DB::transaction(function () use ($depart_sort,$corDepGoods,$real_purchase_sn) {
            DB::table('depart_sort')->insert($depart_sort);
            DB::table('depart_sort_goods')->insert($corDepGoods);
            //update zhangdong 2019.03.06
            //修改实采单状态为待用户分货
            $rpModel = new RealPurchaseModel();
            $is_sort_num = $rpModel->is_sort_int['WAIT_USER_SORT'];
            $executeRes = $rpModel->updateIsSort($real_purchase_sn,$is_sort_num);
            return $executeRes;
        });
        return $insertRes;
    }



    /**
     * description:查询分货数据
     * editor:zhangdong
     * params:$query_type 1， 查询实时分货数据 2，查询最终分货数据
     * date : 2019.02.19
     */
    public function getSortData($purchase_sn, $real_purchase_sn, $query_type)
    {
        //实采单状态：1,待清点(上传实时数据后);2,待确认差异(物流部清点完成后);\\r\\n3,待开单(采购部确认差异后);
        //4,待入库(采购部开单后);5,已完成(物流部进行入库后);6,待核价(财务部进行开单前的核价)
        $statusWhere = ['status', '<=', 2];
        if($query_type == 2){
            //最终分货数据指2状态以后生成的数据
            $statusWhere = ['status', '>=', 3];
        }
        $where = [
            ['purchase_sn',$purchase_sn],
            ['real_pur_sn',$real_purchase_sn],
            ['is_use',1],
            $statusWhere
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;

    }

    /**
     * description:生成分货单号
     * editor:zhangdong
     * date : 2019.02.19
     * return String
     */
    public function generalSortSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $sort_sn = 'SORT' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table($this->table)
                ->where([
                    ['sort_sn', '=', $sort_sn]
                ])->count();
        } while ($count);
        return $sort_sn;
    }

    /**
     * description:查询分货数据通过分货单号
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function getSortMsg($sort_sn)
    {
        $where = [
            ['sort_sn',$sort_sn],
            ['is_use',1],
        ];
        $queryRes = DB::table($this->table)->select($this->field)->where($where)->first();
        return $queryRes;
    }


    /**
     * description:获取需求单分货数据列表
     * editor:zhangdong
     * date : 2019.02.19
     */
    public function getSortDemandData($sort_sn)
    {
        $where = [
            ['ds.sort_sn',$sort_sn],
            ['ds.is_use',1],
        ];
        $field = [
            'ds.purchase_sn','dsg.depart_id','d.de_name',
        ];
        $queryRes = DB::table($this->table)->select($field)
            ->leftJoin('depart_sort_goods AS dsg','ds.sort_sn','dsg.sort_sn')
            ->leftJoin('department AS d','d.department_id','dsg.depart_id')
            ->where($where)->groupBy('ds.purchase_sn','dsg.depart_id')->get();
        return $queryRes;
    }

    /**
     * description:停用分货数据
     * author:zhangdong
     * date : 2019.03.06
     */
    public function stopUseDepartSort($sort_sn)
    {
        $where = [
            ['sort_sn',$sort_sn],
        ];
        $update = [
            'is_use'=>0,
        ];
        $executeRes = DB::table($this->table)->where($where)->update($update);
        return $executeRes;

    }





















}//end of class
