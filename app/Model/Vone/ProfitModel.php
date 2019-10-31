<?php

namespace App\Model\Vone;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProfitModel extends Model
{
    public $table = 'profit as p';
    private $field = [
        'p.id', 'p.profit_sn', 'p.settle_date', 'p.settle_date_type',
        'p.channel_id', 'p.is_delete', 'p.create_time',
    ];

    /**
     * description 生成毛利单号
     * author zhangdong
     * date 2019.07.19
     * return String
     */
    public function generalProfitSn()
    {
        do {
            $strNum = date('Ymdhi', time()) . rand(1000, 9999);
            $profit_sn = 'ML' . $strNum;
            //联合采购单号查找当前这个需求单号是否已经存在
            $count = DB::table($this->table)
                ->where([
                    ['profit_sn', '=', $profit_sn]
                ])->count();
        } while ($count);
        return $profit_sn;
    }

    /**
     * description 毛利数据信息
     * author zongxing
     * date 2019.08.05
     */
    public function profitInfo($param_info)
    {
        $profit_sn = trim($param_info['profit_sn']);
        $profit_info = DB::table('profit')->where('profit_sn', $profit_sn)->first();
        $profit_info = objectToArrayZ($profit_info);
        return $profit_info;
    }

    /**
     * description 停用毛利数据
     * author zongxing
     * date 2019.08.05
     */
    public function deleteProfitInfo($param_info)
    {
        $profit_sn = trim($param_info['profit_sn']);
        $res = DB::table('profit')->where('profit_sn', $profit_sn)->delete();
        return $res;
    }


}//end of class
