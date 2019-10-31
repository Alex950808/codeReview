<?php

namespace App\Model\Vone;

use App\Modules\Excel\ExcuteExcel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;

class DeliverGoodsModel extends Model
{
    /**
     * description:配货单详情
     * editor:zongxing
     * date : 2018.12.15
     * return Array
     */
    public function deliverOrderDetail($deliver_order_sn)
    {
        $fields = ['dg.goods_name', 'dg.spec_sn', 'dg.spec_price', 'dg.sale_discount', 'dg.pre_ship_num','erp_merchant_no',
            'dg.real_ship_num', 'real_arrival_num', 'sale_user_id', 'spot_order_sn', 'do.order_amount',
            'do.real_order_amount', 'allot_num'
        ];
        $deliver_order_detail = DB::table("deliver_goods as dg")
            ->leftJoin('deliver_order as do', 'do.deliver_order_sn', '=', 'dg.deliver_order_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->where('do.deliver_order_sn', $deliver_order_sn)
            ->get($fields);
        $deliver_order_detail = objectToArrayZ($deliver_order_detail);
        return $deliver_order_detail;
    }

    /**
     * description:物流模块-MIS订单管理管理-下载配货单
     * editor:zongxing
     * date: 2018.12.15
     */
    public function downloadDistributionOrder($deliver_order_sn)
    {
        //获取预采需求单详情
        $distribution_order_detail = $this->deliverOrderDetail($deliver_order_sn);
        $res = $this->exportDistributionData($distribution_order_detail, $deliver_order_sn);
        return $res;
//        $obpe = new PHPExcel();
//        //设置采购渠道及列宽
//        $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('B1', '商品规格码')->getColumnDimension('B')->setWidth(15);
//        $obpe->getActiveSheet()->setCellValue('C1', '商品价格')->getColumnDimension('C')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('D1', '销售折扣')->getColumnDimension('D')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('E1', '预计发货量')->getColumnDimension('E')->setWidth(20);
//        $obpe->getActiveSheet()->setCellValue('F1', '实际发货量')->getColumnDimension('F')->setWidth(20);
//        $obpe->setActiveSheetIndex(0);
//
//        //获取最大列名称
//        $currentSheet = $obpe->getSheet(0);
//        $column_last_name = $currentSheet->getHighestColumn();
//        $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);
//
//        //获取最大行数
//        $row_last_i = count($distribution_order_detail) + 2;
//
//        for ($i = 0; $i < $row_last_i; $i++) {
//            if ($i < 2) continue;
//            $row_i = $i - 2;
//            for ($j = 0; $j < $column_last_num; $j++) {
//                //获取列名
//                $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
//                $predict_demand_detail[$row_i] = array_values($distribution_order_detail[$row_i]);
//                $obpe->getActiveSheet()->setCellValue($column_name . $i, $predict_demand_detail[$row_i][$j]);
//            }
//        }
//
//        $column_first_name = "A";
//        $row_first_i = 1;
//        $row_end_i = 1;
//        $commonModel = new CommonModel();
//        //改变表格标题样式
//        $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
//        //改变表格内容样式
//        $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);
//
//        $title = $deliver_order_sn . '配货单表';
//        $obpe->getActiveSheet()->setTitle($title);
//
//        //清除缓存
//        ob_end_clean();
//        //写入类容
//        $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');
//
//        $filename = $title . '.xls';
//        //保存文件
//        //$obwrite->save($filename);
//
//        //直接在浏览器输出
//        header('Pragma: public');
//        header('Expires: 0');
//        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
//        header('Content-Type:application/force-download');
//        header('Content-Type:application/vnd.ms-execl');
//        header('Content-Type:application/octet-stream');
//        header('Content-Type:application/download');
//        header("Content-Disposition:attachment;filename=$filename");
//        header('Content-Transfer-Encoding:binary');
//        $obwrite->save('php://output');
    }

    /**
     * description:物流模块-MIS订单管理管理-下载配货单数据-数据组装
     * editor:zongxing
     * date:2018.12.27
     */
    private function exportDistributionData($deliver_order_detail, $deliver_order_sn)
    {
        $title = [['商品名称', '商家编码', '商品规格码', '预计发货数量', '实际发货数量']];
        $goods_list = [];
        foreach ($deliver_order_detail as $k => $v) {
            $goods_list[$k] = [
                $v['goods_name'], $v['erp_merchant_no'], $v['spec_sn'],
                $v['pre_ship_num'], $v['real_ship_num']
            ];
        }
        //配货单单号
        $filename = '配货单表_' . $deliver_order_sn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $excuteExcel = new ExcuteExcel();
        return $excuteExcel->exportZ($exportData, $filename);
    }

    /**
     * description:物流模块-MIS订单管理管理-确认上传配货单
     * editor:zongxing
     * date: 2018.12.15
     */
    public function updateDeliverGoods($res, $deliver_order_sn, $sale_user_id, $spot_goods_info, $deliver_goods_total_info)
    {
        //行数
        $row_num = count($res);
        $updateGoodsData = [];
        $updateGoodsSpecData = [];
        $order_amount = 0;
        for ($i = 0; $i < $row_num; $i++) {
            if ($i < 1) continue;//第1行数据为标题头
            if ($res[$i][0]) {
                //获取商品规格码
                $goods_spec_sn = (string)($res[$i][2]);
                //商品价格
                $spec_price = floatval($deliver_goods_total_info[$goods_spec_sn]['spec_price']);
                //商品折扣
                $sale_discount = floatval($deliver_goods_total_info[$goods_spec_sn]['sale_discount']);
                //获取商品配货量
                $day_buy_num = intval($res[$i][4]);
                $order_amount += $spec_price * $sale_discount * $day_buy_num;
                $updateGoodsData['real_ship_num'][] = [
                    $goods_spec_sn => $day_buy_num
                ];
                $updateGoodsData['real_arrival_num'][] = [
                    $goods_spec_sn => $day_buy_num
                ];
                //存在现货单,需要对商品库存进行校正
                if (!empty($spot_goods_info) && isset($spot_goods_info[$goods_spec_sn])) {
                    //现货单的数量
                    $spot_goods_num = $spot_goods_info[$goods_spec_sn]['goods_number'];
                    if ($day_buy_num >= $spot_goods_num) {
                        $updateGoodsSpecData['lock_stock_num'][] = [
                            $goods_spec_sn => 'lock_stock_num - ' . $spot_goods_num
                        ];
                    } else {
                        $updateGoodsSpecData['lock_stock_num'][] = [
                            $goods_spec_sn => 'lock_stock_num - ' . $day_buy_num
                        ];
                    }
                }
            }
        }
        //订单最终总金额
        $order_amount = [
            'order_amount' => round($order_amount, 2),
            'order_sta' => 2
        ];

        //获取销售客户对应的预计回款金额
        $fund_channel_model = new FundChannelModel();
        $sale_predict_fund = $fund_channel_model->getFundChannelInfo(null, 3, null, $sale_user_id);
        if (empty($sale_predict_fund)) {
            $return_info = ['code' => '1010', 'msg' => '发货单所属的销售客户的回款资金渠道有误,请检查'];
            return $return_info;
        }
        $total_usd = $sale_predict_fund[0]['usd'] + $order_amount['order_amount'];
        $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
        $covert_cny = $order_amount['order_amount'] * $USD_CNY_RATE;
        $covert_cny = round($covert_cny, 2);
        $total_covert_cny = $sale_predict_fund[0]['covert_cny'] + $covert_cny;
        $update_fund = [
            'usd' => $total_usd,
            'covert_cny' => $total_covert_cny,
        ];
        //更新条件
        $where = [
            'deliver_order_sn' => $deliver_order_sn
        ];
        //需要判断的字段
        $column = 'spec_sn';
        if (!empty($updateGoodsData)) {
            $updateDeliverGoodsSql = makeBatchUpdateSql('jms_deliver_goods', $updateGoodsData, $column, $where);
        }
        $updateGoodsSpecSql = '';
        if (!empty($updateGoodsSpecData)) {
            $updateGoodsSpecSql = makeBatchUpdateSql('jms_goods_spec', $updateGoodsSpecData, $column);
        }
        $updateRes = DB::transaction(function () use (
            $updateDeliverGoodsSql, $deliver_order_sn, $order_amount,
            $sale_user_id, $update_fund, $updateGoodsSpecSql
        ) {
            if (!empty($update_fund)) {
                $where = [
                    ['sale_user_id', $sale_user_id],
                    ['fund_cat_id', 3]
                ];
                DB::table('fund_channel')->where($where)->update($update_fund);
            }

            if (!empty($order_amount)) {
                DB::table('deliver_order')->where('deliver_order_sn', $deliver_order_sn)->update($order_amount);
            }

            if (!empty($updateGoodsSpecSql)) {
                $updateRes = DB::update(DB::raw($updateGoodsSpecSql));
            }

            if (!empty($updateDeliverGoodsSql)) {
                $updateRes = DB::update(DB::raw($updateDeliverGoodsSql));
            }
            return $updateRes;
        });
        return $updateRes;
    }

    /**
     * description:销售模块-MIS订单管理管理-发货单列表-下载发货单
     * editor:zongxing
     * date: 2018.12.15
     */
    public function downloadSellDeliverOrder($deliver_order_sn)
    {
        //获取预采需求单详情
        $deliver_order_detail = $this->deliverOrderDetail($deliver_order_sn);

        $obpe = new PHPExcel();
        //设置采购渠道及列宽
        $obpe->getActiveSheet()->setCellValue('A1', '商品名称')->getColumnDimension('A')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('B1', '商品规格码')->getColumnDimension('B')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('C1', '商品价格')->getColumnDimension('C')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('D1', '销售折扣')->getColumnDimension('D')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('E1', '预计发货量')->getColumnDimension('E')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('F1', '实际发货量')->getColumnDimension('F')->setWidth(20);
        $obpe->getActiveSheet()->setCellValue('G1', '实际到货量')->getColumnDimension('G')->setWidth(20);
        $obpe->setActiveSheetIndex(0);

        //获取最大列名称
        $currentSheet = $obpe->getSheet(0);
        $column_last_name = $currentSheet->getHighestColumn();
        $column_last_num = \PHPExcel_Cell::columnIndexFromString($column_last_name);

        //获取最大行数
        $row_last_i = count($deliver_order_detail) + 2;
        for ($i = 0; $i < $row_last_i; $i++) {
            if ($i < 2) continue;
            $row_i = $i - 2;
            for ($j = 0; $j < $column_last_num; $j++) {
                //获取列名
                $column_name = \PHPExcel_Cell::stringFromColumnIndex($j);
                $demand_detail[$row_i] = array_values($deliver_order_detail[$row_i]);
                $obpe->getActiveSheet()->setCellValue($column_name . $i, $demand_detail[$row_i][$j]);
            }
        }

        $column_first_name = "A";
        $row_first_i = 1;
        $row_end_i = 1;
        $commonModel = new CommonModel();
        //改变表格标题样式
        $commonModel->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
        //改变表格内容样式
        $commonModel->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

        $title = $deliver_order_sn . '到货单表';
        $obpe->getActiveSheet()->setTitle($title);

        //清除缓存
        ob_end_clean();
        //写入类容
        $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

        $filename = $title . '.xls';
        //保存文件
        //$obwrite->save($filename);

        //直接在浏览器输出
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Content-Type:application/force-download');
        header('Content-Type:application/vnd.ms-execl');
        header('Content-Type:application/octet-stream');
        header('Content-Type:application/download');
        header("Content-Disposition:attachment;filename=$filename");
        header('Content-Transfer-Encoding:binary');
        $obwrite->save('php://output');
    }

    /**
     * description:erp订单推送-根据发货单号获取订单中商品信息
     * editor:zongxing
     * date : 2018.12.18
     * return Array
     */
    public function getDeliverGoodsInfo($deliver_order_sn)
    {
        $fields = [
            'dg.goods_name', 'dg.spec_sn', 'dg.real_ship_num as goods_number', 'dg.spec_price', 'gs.goods_sn',
            'gs.erp_merchant_no', 'gs.spec_id', 'dg.real_ship_num', 'dg.real_arrival_num','dg.allot_num', 'dg.sale_discount',
        ];
        $deliver_goods_info = DB::table("deliver_goods as dg")
            ->leftJoin('deliver_order as do', 'do.deliver_order_sn', '=', 'dg.deliver_order_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->where('do.deliver_order_sn', $deliver_order_sn)
            ->get($fields);
        $deliver_goods_info = ObjectToArrayZ($deliver_goods_info);
        return $deliver_goods_info;
    }

    /**
     * description:获取YD订单下发货单中已经发货的商品
     * editor:zongxing
     * date : 2018.12.25
     * return Array
     */
    public function getDeliverGoods($sub_order_sn)
    {
        $fields = [
            'dg.goods_name', 'dg.spec_sn', 'dg.pre_ship_num', 'dg.spec_price', 'gs.goods_sn',
            'gs.erp_merchant_no', 'gs.spec_id', 'do.order_sta',
            DB::raw('(jms_dg.pre_ship_num - jms_dg.real_ship_num) as diff_num')
        ];
        $deliver_goods_info = DB::table("deliver_goods as dg")
            ->leftJoin('deliver_order as do', 'do.deliver_order_sn', '=', 'dg.deliver_order_sn')
            ->leftJoin('goods_spec as gs', 'gs.spec_sn', '=', 'dg.spec_sn')
            ->where('do.sub_order_sn', $sub_order_sn)
            ->get($fields);
        $deliver_goods_info = ObjectToArrayZ($deliver_goods_info);
        return $deliver_goods_info;
    }

    /**
     * description:销售模块-MIS订单管理管理-发货单列表-上传理货报告
     * editor:zongxing
     * date: 2018.12.15
     */
    public function updateSellDeliverGoods($res, $deliver_order_sn, $sale_user_id, $deliver_goods_total_info)
    {
        //行数
        $row_num = count($res);
        $updateGoodsData = [];
        $order_amount = 0;
        for ($i = 0; $i < $row_num; $i++) {
            if ($i < 1) continue;//第1行数据为标题头
            if ($res[$i][0]) {
                //获取商品规格码
                $goods_spec_sn = (string)($res[$i][1]);
                //商品价格
                $spec_price = floatval($deliver_goods_total_info[$goods_spec_sn]['spec_price']);
                //商品折扣
                $sale_discount = floatval($deliver_goods_total_info[$goods_spec_sn]['sale_discount']);
                //获取商品实际到货
                $day_buy_num = intval($res[$i][6]);
                $order_amount += $spec_price * $sale_discount * $day_buy_num;
                $updateGoodsData['real_arrival_num'][] = [
                    $goods_spec_sn => $day_buy_num
                ];
            }
        }

        //获取销售客户对应的预计待回款金额
        $fund_channel_model = new FundChannelModel();
        $sale_predict_fund = $fund_channel_model->getFundChannelInfo(null, 3, null, $sale_user_id);
        if (empty($sale_predict_fund)) {
            $return_info = ['code' => '1010', 'msg' => '发货单所属的销售客户的回款资金渠道有误,请检查'];
            return $return_info;
        }
        //计算订单实际待回款金额
        $deliver_goods_total_value = array_values($deliver_goods_total_info);
        $before_order_amount = $deliver_goods_total_value[0]['order_amount'];
        $order_amount = round($order_amount, 2);
        $update_fund = [];

        //更新数据
        $real_order_amount['real_order_amount'] = $order_amount;
        if ($order_amount == $before_order_amount) {
            $real_order_amount['order_sta'] = 4;//没有差异即没有退货,已完成
        }else{
            if ($order_amount < $before_order_amount) {
                $diff_amount = $before_order_amount - $order_amount;
                $total_usd = $sale_predict_fund[0]['usd'] - $diff_amount;
            } else {
                $diff_amount = $order_amount - $before_order_amount;
                $total_usd = $sale_predict_fund[0]['usd'] + $diff_amount;
            }
            $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
            $covert_cny = $total_usd * $USD_CNY_RATE;
            $total_covert_cny = round($covert_cny, 2);
            $update_fund = [
                'usd' => $total_usd,
                'covert_cny' => $total_covert_cny,
            ];
            $real_order_amount['order_sta'] = 6;//待清点入库
        }

        //需要判断的字段
        $column = 'spec_sn';
        if (!empty($updateGoodsData)) {
            $updateDeliverGoodsSql = makeBatchUpdateSql('jms_deliver_goods', $updateGoodsData, $column);
        }
        $updateRes = DB::transaction(function () use (
            $updateDeliverGoodsSql, $deliver_order_sn, $real_order_amount, $sale_user_id, $update_fund
        ) {
            if (!empty($update_fund)) {
                $where = [
                    ['sale_user_id', $sale_user_id],
                    ['fund_cat_id', 3]
                ];
                DB::table('fund_channel')->where($where)->update($update_fund);
            }
            if (!empty($real_order_amount)) {
                //更新条件
                $where = [
                    'deliver_order_sn' => $deliver_order_sn
                ];
                DB::table('deliver_order')->where($where)->update($real_order_amount);
            }
            if (!empty($updateDeliverGoodsSql)) {
                $updateRes = DB::update(DB::raw($updateDeliverGoodsSql));
            }
            return $updateRes;
        });
        return $updateRes;
    }

    /**
     * description:物流模块-MIS订单管理管理-上传退货清点数据
     * editor:zongxing
     * date: 2018.12.27
     */
    public function updateSellReturnGoods($res, $deliver_order_sn, $sale_user_id, $deliver_goods_total_info)
    {
        //行数
        $row_num = count($res);
        $updateGoodsData = [];
        $return_order_amount = 0;
        for ($i = 0; $i < $row_num; $i++) {
            if ($i < 1) continue;//第1行数据为标题头
            if ($res[$i][0]) {
                //获取商品规格码
                $goods_spec_sn = (string)($res[$i][2]);
                //商品价格
                $spec_price = floatval($deliver_goods_total_info[$goods_spec_sn]['spec_price']);
                //商品折扣
                $sale_discount = floatval($deliver_goods_total_info[$goods_spec_sn]['sale_discount']);
                //获取商品清点数量
                $allot_num = intval($res[$i][7]);
                $return_order_amount += $spec_price * $sale_discount * $allot_num;
                $updateGoodsData['allot_num'][] = [
                    $goods_spec_sn => $allot_num
                ];
            }
        }

        //获取销售客户对应的实际待回款金额
        $fund_channel_model = new FundChannelModel();
        $sale_predict_fund = $fund_channel_model->getFundChannelInfo(null, 3, null, $sale_user_id);
        if (empty($sale_predict_fund)) {
            $return_info = ['code' => '1010', 'msg' => '退货单所属的销售客户的回款资金渠道有误,请检查'];
            return $return_info;
        }

        //计算订单最终待回款金额
        $deliver_goods_total_value = array_values($deliver_goods_total_info);
        $before_order_amount = $deliver_goods_total_value[0]['order_amount'];
        $before_real_order_amount = $deliver_goods_total_value[0]['real_order_amount'];
        $diff_order_amount = $before_order_amount - $before_real_order_amount;
        $return_order_amount = round($return_order_amount, 2);
        $update_fund = [];
        if ($diff_order_amount != $return_order_amount) {
            if ($return_order_amount < $diff_order_amount) {
                $diff_amount = $diff_order_amount - $return_order_amount;
                $total_usd = $sale_predict_fund[0]['usd'] + $diff_amount;
            } else {
                $diff_amount = $return_order_amount - $diff_order_amount;
                $total_usd = $sale_predict_fund[0]['usd'] - $diff_amount;
            }

            $USD_CNY_RATE = convertCurrency("USD", "CNY", "1");
            $covert_cny = $total_usd * $USD_CNY_RATE;
            $total_covert_cny = round($covert_cny, 2);
            $update_fund = [
                'usd' => $total_usd,
                'covert_cny' => $total_covert_cny,
            ];
        }

        //需要判断的字段
        $column = 'spec_sn';
        if (!empty($updateGoodsData)) {
            $updateDeliverGoodsSql = makeBatchUpdateSql('jms_deliver_goods', $updateGoodsData, $column);
        }
        $updateRes = DB::transaction(function () use (
            $updateDeliverGoodsSql, $deliver_order_sn, $sale_user_id, $update_fund
        ) {
            if (!empty($update_fund)) {
                $where = [
                    ['sale_user_id', $sale_user_id],
                    ['fund_cat_id', 3]
                ];
                DB::table('fund_channel')->where($where)->update($update_fund);
            }
            if (!empty($order_amount)) {
                //更新条件
                $where = [
                    'order_sta' => 4//已完成
                ];
                DB::table('deliver_order')->where('deliver_order_sn', $deliver_order_sn)->where($where)->update($order_amount);
            }
            if (!empty($updateDeliverGoodsSql)) {
                $updateRes = DB::update(DB::raw($updateDeliverGoodsSql));
            }
            return $updateRes;
        });
        return $updateRes;
    }

}
