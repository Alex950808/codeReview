<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\AuditConfigModel;
use App\Model\Vone\AuditModel;
use App\Model\Vone\BrandModel;
use App\Model\Vone\DiscountAuditModel;
use App\Model\Vone\DiscountCatModel;
use App\Model\Vone\DiscountModel;
use App\Model\Vone\DiscountTypeInfo;
use App\Model\Vone\DiscountTypeInfoModel;
use App\Model\Vone\DiscountTypeModel;
use App\Model\Vone\DiscountTypeRecordModel;
use App\Model\Vone\GmcDiscountModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\PurchaseChannelModel;
use App\Model\Vone\PurchaseMethodModel;
use App\Model\Vone\VipDiscountModel;

use App\Modules\Excel\ExcuteExcel;
use App\Modules\ParamsCheckSingle;
use Illuminate\Support\Facades\Redis;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Classes\PHPExcel;
/**
 * description:采购模块控制器
 * editor:zongxing
 * date : 2018.06.25
 */
class DiscountController extends BaseController
{

    /**
     * description:品牌折扣表上传
     * editor:zongxing
     * type:POST
     * date : 2018.06.27
     * params: 1.需要上传的excel表格文件:upload_file;
     * return Object
     * notice：2019.03.29 由于要加审核流程所以进行一次重构 zhangdong
     */
    public function uploadDiscount(Request $request)
    {
        $brand_discount_array = $request->toArray();
        if (empty($brand_discount_array['upload_file'])) {
            return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
        }
        $file = $_FILES;
        //检查上传文件是否合格
        $excuteExcel = new ExcuteExcel();
        $fileName = '采购折扣上传表';//要上传的文件名，将对上传的文件名做比较
        $res = $excuteExcel->verifyUploadFile($file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['品牌ID', '品牌名称', '采购方式', '采购渠道', '品牌折扣'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                $returnMsg = ['code' => '1005', 'msg' => '您的标题头有误，请按模板导入'];
                return response()->json($returnMsg);
            }
        }
        //检查上传数据是否正常(品牌，采购方式，采购渠道是否存在)
        $discountModel = new DiscountModel();
        $checkRes = $discountModel->checkUploadDisData($res);
        if (!empty($checkRes['none_desc'])) {
            $returnMsg = ['code' => '2067', 'msg' => $checkRes['none_desc']];
            return response()->json($returnMsg);
        }
        //将上传的数据保存到折扣审核表中
        $auditModel = new  AuditModel();
        $writeRes = $auditModel->saveBrandDiscountData($checkRes['disAuditData']);
        $returnMsg = ['code' => '1009', 'msg' => '文件上传失败'];
        if ($writeRes['saveRes'] == true) {
            $returnMsg = [
                'code' => '1000',
                'msg' => '文件上传成功',
                'audit_sn' => trim($writeRes['audit_sn']),
            ];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:品牌折扣表上传
     * editor:zongxing
     * type:POST
     * date : 2018.06.27
     * params: 1.需要上传的excel表格文件:upload_file;
     * return Object
     */
    public function uploadDiscount_stop(Request $request)
    {
        if ($request->isMethod('post')) {
            $brand_discount_array = $request->toArray();

            if (empty($brand_discount_array['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            }

            $file = $_FILES;

            //检查上传文件是否合格
            $excuteExcel = new ExcuteExcel();
            $fileName = '采购需求-商品采购折扣表';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }

            $file_types = explode(".", $file['upload_file']['name']);
            $file_type = $file_types [count($file_types) - 1];

            //保存上传的折扣表
            $savePath = base_path() . '/uploadFile/' . date('Ymd') . '/';
            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }

            $str = date('Ymdhis');
            //$file_name = $str . '.' . $file_type;//iconv('UTF-8', 'GBK', $str . '.' . $file_type);
            //暂时隐藏
            //$request->file('upload_file')->move($savePath, $file_name);

            //保留
//            $res = [];
//            $fullpath = $savePath . $file_name;
//
//            Excel::load($fullpath, function ($reader) use (&$res) {
//                $reader = $reader->getSheet(0);
//                $res = $reader->toArray();
//            });

            //检查字段名称
            $arrTitle = ['品牌', '购买渠道'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    $returnMsg = ['code' => '1005', 'msg' => '您的标题头有误，请按模板导入'];
                    return response()->json($returnMsg);
                }
            }

            //获取系统所有渠道
            $channels_total_info = DB::table("purchase_channels as pc")
                ->leftJoin("purchase_method as pm", "pm.id", "=", "pc.method_id")
                ->get(["channels_name", "method_name", "pc.id"]);
            $channels_total_info = objectToArrayZ($channels_total_info);

            $channels_arr = [];
            $channels_list = [];
            foreach ($channels_total_info as $k => $v) {
                $channels_name = $v["channels_name"];
                $method_name = $v["method_name"];
                if (!in_array($channels_name, $channels_arr)) {
                    array_push($channels_arr, $channels_name);
                }
                $pin_str = $method_name . $channels_name;
                $channels_list[$pin_str] = $v["id"];
            }

            $channels_num = count($res[0]);
            for ($i = 0; $i < $channels_num; $i = $i + 2) {
                if ($i >= 4) {
                    if (empty($res[0][$i])) {
                        return response()->json(['code' => '1006', 'msg' => '您上传的采购渠道为空，请先添加采购渠道']);
                    } else {
                        if (!in_array($res[0][$i], $channels_arr)) {
                            $returnMsg = ['code' => '1006', 'msg' => '您上传的采购渠道:' . $res[0][$i] . '不存在，请先添加采购渠道'];
                            return response()->json($returnMsg);
                        }
                    }
                }
            }

            //获取系统所有方式
            $purchase_method_model = new PurchaseMethodModel();
            $method_list = $purchase_method_model->getMethodList();
            $method_arr = array_keys($method_list);
            foreach ($res as $k => $v) {
                if ($k < 2) continue;
                if (empty($v[3])) {
                    return response()->json(['code' => '1012', 'msg' => '您上传的采购方式为空，请先添加采购方式']);
                } else {
                    if (!in_array($v[3], $method_arr)) {
                        return response()->json(['code' => '1012', 'msg' => '您上传的采购方式:' . $v[3] . '不存在，请先添加采购方式']);
                    }
                }
            }

            //获取系统所有品牌
            $brand_total_info = DB::table("brand")->pluck("name", "brand_id");
            $brand_total_info = objectToArrayZ($brand_total_info);

            //判断品牌是否存在
            $brand_list = [];
            $diff_brand = '';
            $total_brand_str = '';
            foreach ($res as $k => $v) {
                if (!empty($v[1])) {
                    foreach ($brand_total_info as $k1 => $v1) {
                        if (strpos($v1, $v[1]) !== false) {
                            $brand_list[$v[1]] = $k1;
                        }
                        $total_brand_str .= $v1;
                    }
                    if (strpos($total_brand_str, $v[1]) === false) {
                        $diff_brand .= $v[1] . ",";
                    }
                }
            }

            if (!empty($diff_brand)) {
                $diff_brand = substr($diff_brand, 0, -1);
                return response()->json(['code' => '1011', 'msg' => "您上传的品牌:" . $diff_brand . " 在系统中不存在,请先校验"]);
            }

            //获取系统是否已有折扣
            $discount_total_info = DB::table("discount")
                ->get(["brand_id", "method_id", "channels_id", "id"]);
            $discount_total_info = objectToArrayZ($discount_total_info);
            $discount_list = [];
            foreach ($discount_total_info as $k => $v) {
                $pin_str = $v["brand_id"] . "-" . $v["method_id"] . "-" . $v["channels_id"];
                $discount_list[$pin_str] = $v["id"];
            }

            //组装采购折扣详情表添加数据
            $discountModel = new DiscountModel();
            $discountDetailData = $this->createDetailData($res, $brand_list, $method_list, $channels_list, $discount_list);

            if (empty($discountDetailData)) {
                return response()->json(['code' => '1012', 'msg' => '折扣信息组装失败']);
            }

            //采购折扣日志表sn
            $discount_sn = "ZK-" . $str;
            $uploadRes = $discountModel->discountChange($discountDetailData, $discount_sn);
            $return_info = ['code' => '1000', 'msg' => '文件上传成功'];
            if ($uploadRes["error_num"] && $uploadRes["current_num"] == 0) {
                $return_info = [
                    'code' => '1009',
                    'msg' => "文件上传失败",
                ];
            }
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:组装采购折扣详情表添加数据
     * editor:zongxing
     * date : 2018.07.14
     * return Object
     */
    public function createDetailData($res, $brand_list, $method_list, $channels_list, $discount_list)
    {
        $discountModel = new DiscountModel();
        //行数
        $row_num = count($res);
        //获取上传采购渠道数组
        $channelData = $res[0];
        $temp_brand_name = '';
        $discountData = [];

        for ($i = 0; $i < $row_num; $i++) {
            if ($i === 0 || $i === 1) continue;//第1、2行数据为标题头
            //获取品牌id
            if ($res[$i][1]) {
                $brand_name = $res[$i][1];
                $temp_brand_name = $res[$i][1];
            } else {
                $brand_name = $temp_brand_name;
            }
            $brand_id = $brand_list[$brand_name];
            $method_id = $method_list[$res[$i][3]]['id'];

            //列数
            $field_num = count($res[$i]);
            for ($j = 0; $j < $field_num; $j = $j + 2) {
                if ($j < 4) continue;//前4列数据不是渠道，不写入
                //没有折扣信息
                if (empty($res[$i][$j])) continue;
                //获取渠道id
                $channels_name = $channelData[$j];
                $method_name = $res[$i][3];
                $pin_str = $method_name . $channels_name;
                $channels_id = $channels_list[$pin_str];
                //折扣
                $brand_discount = $res[$i][$j];
                //获取出货量
                $shipment = $res[$i][$j + 1];
                //判断是新增还是更新
                $final_info = $discountModel->getFinalInfo($brand_id, $method_id, $channels_id, $discount_list);
                $tmpDiscountData = [
                    'brand_id' => $brand_id,
                    'method_id' => $method_id,
                    'channels_id' => $channels_id,
                    'brand_discount' => $brand_discount,
                    'shipment' => $shipment,
                    'action' => $final_info["action"],
                ];
                if ($final_info["action"] == 'update') {
                    $tmpDiscountData['id'] = $final_info["id"];
                }
                $discountData[] = $tmpDiscountData;
            }
        }
        return $discountData;
    }

    /**
     * description:下载品牌折扣模板
     * editor:zongxing
     * type:GET
     * date : 2018.06.27
     * return Object
     */
    public function downLoadDemo(Request $request)
    {
        if ($request->isMethod("get")) {
            $obpe = new PHPExcel();

            //设置采购渠道及列宽
            $obpe->getActiveSheet()->setCellValue('A1', '品牌')->getColumnDimension('A')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('D1', '购买渠道')->getColumnDimension('B')->setWidth(15);
            $obpe->getActiveSheet()->setCellValue('E1', '乐天线上')->getColumnDimension('C')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('E2', '折扣')->getColumnDimension('D')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('F2', '出货量')->getColumnDimension('E')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G1', '新罗线上')->getColumnDimension('F')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('G2', '折扣')->getColumnDimension('G')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('H2', '出货量')->getColumnDimension('H')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('I1', '东和')->getColumnDimension('I')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('I2', '折扣')->getColumnDimension('I')->setWidth(20);
            $obpe->getActiveSheet()->setCellValue('J2', '出货量')->getColumnDimension('I')->setWidth(20);
            $obpe->setActiveSheetIndex(0);

            //设置品牌内容
            $obpe->getActiveSheet()->setCellValue('A3', '1');
            $obpe->getActiveSheet()->setCellValue('B3', '圣罗兰');
            $obpe->getActiveSheet()->setCellValue('C3', 'YSL');

            //设置采购方式
            $obpe->getActiveSheet()->setCellValue('D3', '线上');
            $obpe->getActiveSheet()->setCellValue('D4', '线下');

            //设置采购渠道对应的采购方式的折扣及出货量
            $obpe->getActiveSheet()->setCellValue('E3', '0.69');
            $obpe->getActiveSheet()->setCellValue('F3', '150');

            $obpe->getActiveSheet()->setCellValue('G3', '0.697');
            $obpe->getActiveSheet()->setCellValue('H3', '单个SQU12个');

            $obpe->getActiveSheet()->setCellValue('I4', '0.68');
            $obpe->getActiveSheet()->setCellValue('J4', '100');


            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            //获取最大行数
            $row_last_i = $currentSheet->getHighestRow();
            //改变表格标题样式
            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 2;
            $this->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);
            //改变表格内容样式
            $column_first_name = "A";
            $row_first_i = 3;
            $this->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            $obpe->getActiveSheet()->mergeCells('A1:C2');
            $obpe->getActiveSheet()->mergeCells('D1:D2');
            $obpe->getActiveSheet()->mergeCells('E1:F1');
            $obpe->getActiveSheet()->mergeCells('G1:H1');
            $obpe->getActiveSheet()->mergeCells('I1:J1');

            $obpe->getActiveSheet()->mergeCells('A3:A4');
            $obpe->getActiveSheet()->mergeCells('B3:B4');
            $obpe->getActiveSheet()->mergeCells('C3:C4');

            $obpe->getActiveSheet()->setTitle('采购折扣模板');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            //$filename = '商品采购折扣_' . $str . '.xls';
            $filename = '采购需求-商品采购折扣表.xls';

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

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }

        return response()->json($return_info);
    }

    /**
     * description:下载当前采购折扣表
     * editor:zongxing
     * type:GET
     * date : 2018.06.28
     * return Object
     */
    public function downLoadCurrent_stop(Request $request)
    {
        if ($request->isMethod("get")) {
            $discountModel = new DiscountModel();
            //获取当前采购折扣数据
            $discount_current_info = $discountModel->getDiscountCurrent();
            //对表内容进行格式化
            $format_current_info = [];
            foreach ($discount_current_info as $current_info) {
                if (isset($format_current_info[$current_info["name"]])) {
                    array_push($format_current_info[$current_info["name"]], $current_info);
                } else {
                    $format_current_info[$current_info["name"]][] = $current_info;
                }
            }
            $format_current_info = array_values($format_current_info);

            $obpe = new PHPExcel();

            //获取商品品牌的个数
            $brand_num = count($format_current_info);

            //获取采购渠道和采购方式
            $channel_info = [];
            $method_info = [];
            $method_sn = [];
            foreach ($discount_current_info as $k => $v) {
                if (!in_array($v["channels_name"], $channel_info)) {
                    array_push($channel_info, $v["channels_name"]);
                }
                if (!in_array($v["method_sn"], $method_sn)) {
                    array_push($method_sn, $v["method_sn"]);
                    array_push($method_info, $v["method_name"]);
                }
            }

            //获取采购方式的数量
            $method_num = count($method_info);

            //行数
            $row_num = $brand_num * $method_num + 2;

            //列数
            $column_num = count($channel_info) * 2 + 4;

            $obpe->getActiveSheet()->setCellValue('A1', '品牌')->getColumnDimension('A')->setWidth(5);
            $obpe->getActiveSheet()->setCellValue('D1', '购买渠道')->getColumnDimension('B')->setWidth(15);

            for ($i = 0; $i < $column_num; $i = $i + 2) {
                if ($i < 4) continue;

                $column_i = $i;
                $index_i = ($i - 4) / 2;

                if (isset($channel_info[$index_i])) {

                    $column_next_i = $column_i + 1;
                    $channel_name = $channel_info[$index_i];

                    // \PHPExcel_Cell::columnIndexFromString("A");
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($column_i);
                    $column_next_name = \PHPExcel_Cell::stringFromColumnIndex($column_next_i);

                    $obpe->getActiveSheet()->setCellValue($column_name . '1', $channel_name)
                        ->getColumnDimension($column_name)->setWidth(5);
                    $obpe->getActiveSheet()->setCellValue($column_name . '2', '折扣')
                        ->getColumnDimension($column_name)->setWidth(20);
                    $obpe->getActiveSheet()->setCellValue($column_next_name . '2', '出货量')
                        ->getColumnDimension($column_next_name)->setWidth(20);
                }
            }

            $number_sn = 0;
            //循环行数
            for ($i = 0; $i < $row_num; $i = $i + $method_num) {
                if ($i < 2) continue;//第1、2行数据为标题头

                $row_i = $i + 1;

                //品牌名和品牌简称
                $brand_sn = $number_sn + 1;
                $brand_name = $format_current_info[$number_sn][0]["name"];
                $brand_name_en = $format_current_info[$number_sn][0]['name_en'];
                $obpe->getActiveSheet()->setCellValue("A" . $row_i, $brand_sn)->getColumnDimension("A")->setWidth(5);
                $obpe->getActiveSheet()->setCellValue("B" . $row_i, $brand_name)->getColumnDimension("B")->setWidth(20);
                $obpe->getActiveSheet()->setCellValue("C" . $row_i, $brand_name_en)->getColumnDimension("C")->setWidth(20);

                //采购方式
                for ($j = 0; $j < count($method_info); $j++) {
                    $method_row_i = $row_i + $j;
                    $obpe->getActiveSheet()->setCellValue("D" . $method_row_i, $method_info[$j])->getColumnDimension("C")->setWidth(20);
                }

                //获取对应的采购方式
                $next_row_i = $row_i + 1;
                $method_name_tmp = $obpe->getActiveSheet()->getCell("D" . $row_i)->getValue();
                $next_method_name_tmp = $obpe->getActiveSheet()->getCell("D" . $next_row_i)->getValue();

                // dd($format_current_info[$number_sn]);
                //采购渠道
                $channel_num = count($format_current_info[$number_sn]);
                for ($m = 0; $m < $channel_num; $m++) {

                    $channel_i = $m * 2 + 4;
                    $next_channel_i = $m * 2 + 5;

                    $discount = $format_current_info[$number_sn][$m]["brand_discount"];
                    $shipment = $format_current_info[$number_sn][$m]["shipment"];
                    $method_name = $format_current_info[$number_sn][$m]["method_name"];
                    $channel_name_tmp = $format_current_info[$number_sn][$m]["channels_name"];

                    $channel_name = \PHPExcel_Cell::stringFromColumnIndex($channel_i);
                    $next_channel_name = \PHPExcel_Cell::stringFromColumnIndex($next_channel_i);

                    $channel_name_com = $obpe->getActiveSheet()->getCell($channel_name . '1')->getValue();

//                    var_dump($method_name);
//                    var_dump($method_name_tmp);
//                    var_dump($channel_name);
//                    var_dump($next_channel_name);
//                    var_dump($channel_name_tmp);
//                    exit;

                    $real_row_i = '';
                    if ($method_name === $method_name_tmp) {
                        $real_row_i = $row_i;
                    } elseif ($method_name === $next_method_name_tmp) {
                        $real_row_i = $next_row_i;
                    }
                    //elseif ($method_name === $method_name_tmp  && $next_channel_name === $channel_name_tmp)
// {
//                        $real_row_i = $next_row_i;
//                    } elseif ($method_name === $next_method_name_tmp && $next_channel_name === $channel_name_tmp) {
//                        $real_row_i = $row_i;
//                    }
                    dd($real_row_i);
                    $obpe->getActiveSheet()->setCellValue($channel_name . $real_row_i, $discount)
                        ->getColumnDimension($channel_name)->setWidth(20);
                    $obpe->getActiveSheet()->setCellValue($next_channel_name . $real_row_i, $shipment)
                        ->getColumnDimension($next_channel_name)->setWidth(20);
                }

                $obpe->getActiveSheet()->mergeCells('A' . $row_i . ':A' . $next_row_i);
                $obpe->getActiveSheet()->mergeCells('B' . $row_i . ':B' . $next_row_i);
                $obpe->getActiveSheet()->mergeCells('C' . $row_i . ':C' . $next_row_i);

                $number_sn++;
            }

            //去掉空白行，待优化
//            if ($method_num >2){
//                $row_dif = $method_num - 2;
//                $obpe->getActiveSheet()->removeRow(2,10);
//            }

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();
            //获取最大行数
            $row_last_i = $currentSheet->getHighestRow();
            //改变表格内容样式
            $column_first_name = "A";
            $row_first_i = 3;
            $this->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            //改变表格样式为加粗和居中
            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 2;
            $this->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);

            $obpe->getActiveSheet()->mergeCells('A1:C2');
            $obpe->getActiveSheet()->mergeCells('D1:D2');
            $obpe->getActiveSheet()->mergeCells('E1:F1');
            $obpe->getActiveSheet()->mergeCells('G1:H1');
            $obpe->getActiveSheet()->mergeCells('I1:J1');

            $obpe->getActiveSheet()->setTitle('当前采购折扣');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '当前采购折扣_' . $str . '.xls';

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

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }

        return response()->json($return_info);

    }

    /**
     * description:下载当前采购折扣表
     * editor:zongxing
     * type:GET
     * date : 2018.06.28
     * return Object
     */
    public function downLoadCurrent(Request $request)
    {
        if ($request->isMethod("get")) {
            $discountModel = new DiscountModel();
            //获取当前采购折扣数据
            $discount_current_info = $discountModel->getDiscountCurrent();

            //对表内容进行格式化
            $format_current_info = [];
            $tmp_current_info = [];
            $tmp_arr = [];
            $group_sn = [];
            foreach ($discount_current_info as $current_info) {
                $pin_str = $current_info["channels_name"] . "-" . $current_info["method_name"];
                $tmp_arr[$pin_str] = $current_info;

                if (!in_array($pin_str, $group_sn)) {
                    array_push($group_sn, $pin_str);
                }

                if (isset($format_current_info[$current_info["name"]])) {
                    if (isset($format_current_info[$current_info["name"]][$pin_str])) {
                        $format_current_info[$current_info["name"]][$pin_str] = $current_info;
                    } else {
                        $format_current_info[$current_info["name"]][$pin_str] = $current_info;
                    }
                } else {
                    $format_current_info[$current_info["name"]] = $tmp_arr;
                }
                $format_current_info[$current_info["name"]] = array_values($format_current_info[$current_info["name"]]);

                if (isset($tmp_current_info[$current_info["name"]])) {
                    if (isset($tmp_current_info[$current_info["name"]][$pin_str])) {
                        $tmp_current_info[$current_info["name"]][$pin_str] = $current_info;
                    } else {
                        $tmp_current_info[$current_info["name"]][$pin_str] = $current_info;
                    }
                } else {
                    $tmp_current_info[$current_info["name"]] = $tmp_arr;
                }
                $tmp_arr = [];
            }
            $format_current_info = array_values($format_current_info);
            $tmp_current_info = array_values($tmp_current_info);

            //获取商品品牌的个数
            $brand_num = count($format_current_info);

            //获取采购渠道和采购方式
            $channel_info = [];
            $method_info = [];
            $method_sn = [];
            foreach ($discount_current_info as $k => $v) {
                if (!in_array($v["channels_name"], $channel_info)) {
                    array_push($channel_info, $v["channels_name"]);
                }
                if (!in_array($v["method_sn"], $method_sn)) {
                    array_push($method_sn, $v["method_sn"]);
                    array_push($method_info, $v["method_name"]);
                }
            }

            foreach ($group_sn as $k => $v) {
                foreach ($tmp_current_info as $k1 => $v1) {
                    if (!isset($v1[$v])) {
                        $tmp_pin_arr = explode("-", $v);

                        $tmp_current_info[$k1][$v]["name"] = $format_current_info[$k1][0]["name"];
                        $tmp_current_info[$k1][$v]["name_en"] = $format_current_info[$k1][0]["name_en"];
                        $tmp_current_info[$k1][$v]["brand_id"] = $format_current_info[$k1][0]["brand_id"];
                        $tmp_current_info[$k1][$v]["method_name"] = $tmp_pin_arr[1];
                        $tmp_current_info[$k1][$v]["channels_name"] = $tmp_pin_arr[0];
                        $tmp_current_info[$k1][$v]["brand_discount"] = 1;
                        $tmp_current_info[$k1][$v]["shipment"] = 0;
                    }
                }
            }

            //获取采购方式的数量
            $method_num = count($method_info);

            //列数
            $column_num = count($channel_info) * 2 + 4;

            $obpe = new PHPExcel();
            $obpe->getActiveSheet()->setCellValue('A1', '品牌')->getColumnDimension('A')->setWidth(5);
            $obpe->getActiveSheet()->setCellValue('D1', '购买渠道')->getColumnDimension('B')->setWidth(15);

            for ($i = 0; $i < $column_num; $i = $i + 2) {
                if ($i < 4) continue;

                $column_i = $i;
                $index_i = ($i - 4) / 2;

                if (isset($channel_info[$index_i])) {

                    $column_next_i = $column_i + 1;
                    $channel_name = $channel_info[$index_i];

                    // \PHPExcel_Cell::columnIndexFromString("A");
                    $column_name = \PHPExcel_Cell::stringFromColumnIndex($column_i);
                    $column_next_name = \PHPExcel_Cell::stringFromColumnIndex($column_next_i);

                    $obpe->getActiveSheet()->setCellValue($column_name . '1', $channel_name)
                        ->getColumnDimension($column_name)->setWidth(5);
                    $obpe->getActiveSheet()->setCellValue($column_name . '2', '折扣')
                        ->getColumnDimension($column_name)->setWidth(20);
                    $obpe->getActiveSheet()->setCellValue($column_next_name . '2', '出货量')
                        ->getColumnDimension($column_next_name)->setWidth(20);
                }
            }

            $number_sn = 0;
            //循环行数
            $row_num = $brand_num * $method_num + 2;
            for ($i = 0; $i < $row_num; $i = $i + $method_num) {
                if ($i < 2) continue;//第1、2行数据为标题头
                $row_i = $i + 1;

                //品牌名和品牌简称
                $brand_sn = $number_sn + 1;
                $brand_name = $format_current_info[$number_sn][0]["name"];
                $brand_name_en = $format_current_info[$number_sn][0]['name_en'];

                $obpe->getActiveSheet()->setCellValue("A" . $row_i, $brand_sn)->getColumnDimension("A")->setWidth(5);
                $obpe->getActiveSheet()->setCellValue("B" . $row_i, $brand_name)->getColumnDimension("B")->setWidth(20);
                $obpe->getActiveSheet()->setCellValue("C" . $row_i, $brand_name_en)->getColumnDimension("C")->setWidth(20);

                //采购方式
                for ($j = 0; $j < count($method_info); $j++) {
                    $method_row_i = $row_i + $j;
                    $obpe->getActiveSheet()->setCellValue("D" . $method_row_i, $method_info[$j])->getColumnDimension("D")->setWidth(20);
                }

                foreach ($format_current_info[$number_sn] as $k1 => $v1) {
                    $discount = $v1["brand_discount"];
                    $shipment = $v1["shipment"];
                    $method_name = $v1["method_name"];
                    $channel_name = $v1["channels_name"];

                    for ($m = 0; $m < count($channel_info) * 2; $m = $m + 2) {
                        $channel_i = $m + 4;
                        $next_channel_i = $m + 5;

                        $channel_name_tmp = \PHPExcel_Cell::stringFromColumnIndex($channel_i);
                        $next_channel_name = \PHPExcel_Cell::stringFromColumnIndex($next_channel_i);

                        $channel_name_com = $obpe->getActiveSheet()->getCell($channel_name_tmp . '1')->getValue();

                        for ($n = 0; $n < count($method_info); $n++) {
                            $method_row_i = $row_i + $n;
                            $method_name_com = $obpe->getActiveSheet()->getCell("D" . $method_row_i)->getValue();

                            if ($method_name === $method_name_com && $channel_name === $channel_name_com) {
                                $obpe->getActiveSheet()->setCellValue($channel_name_tmp . $method_row_i, $discount)
                                    ->getColumnDimension($channel_name_tmp)->setWidth(20);
                                $obpe->getActiveSheet()->setCellValue($next_channel_name . $method_row_i, $shipment)
                                    ->getColumnDimension($next_channel_name)->setWidth(20);
                            }
                        }
                    }
                }

                $end_row_i = $row_i + count($method_info) - 1;

                $obpe->getActiveSheet()->mergeCells('A' . $row_i . ':A' . $end_row_i);
                $obpe->getActiveSheet()->mergeCells('B' . $row_i . ':B' . $end_row_i);
                $obpe->getActiveSheet()->mergeCells('C' . $row_i . ':C' . $end_row_i);

                $number_sn++;
            }

            //去掉空白行，待优化
//            if ($method_num >2){
//                $row_dif = $method_num - 2;
//                $obpe->getActiveSheet()->removeRow(2,10);
//            }

            //获取最大列名称
            $currentSheet = $obpe->getSheet(0);
            $column_last_name = $currentSheet->getHighestColumn();

            //获取最大行数
            $row_last_i = $currentSheet->getHighestRow();
            //改变表格内容样式
            $column_first_name = "A";
            $row_first_i = 3;
            $this->changeTableContent($obpe, $column_first_name, $row_first_i, $column_last_name, $row_last_i);

            //改变表格样式为加粗和居中
            $column_first_name = "A";
            $row_first_i = 1;
            $row_end_i = 2;
            $this->changeTableTitle($obpe, $column_first_name, $row_first_i, $column_last_name, $row_end_i);

            $obpe->getActiveSheet()->mergeCells('A1:C2');
            $obpe->getActiveSheet()->mergeCells('D1:D2');
            $obpe->getActiveSheet()->mergeCells('E1:F1');
            $obpe->getActiveSheet()->mergeCells('G1:H1');
            $obpe->getActiveSheet()->mergeCells('I1:J1');

            $obpe->getActiveSheet()->setTitle('当前采购折扣');

            //清除缓存
            ob_end_clean();
            //写入类容
            $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');

            $str = rand(1000, 9999);
            $filename = '当前采购折扣_' . $str . '.xls';

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

            $code = "1000";
            $msg = "文件下载成功";
            $return_info = compact('code', 'msg');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }

        return response()->json($return_info);

    }

    /**
     * description:获取当前采购折扣列表
     * editor:zongxing
     * type:GET
     * date : 2018.06.29
     * return Object
     */
    public function getDiscountList(Request $request)
    {
        if ($request->isMethod("get")) {
            $search_info = $request->toArray();
            //获取当前采购折扣数据
            $discountModel = new DiscountModel();
            $discount_info = $discountModel->getDiscountCurrent($search_info);

            if (empty($discount_info)) {
                return response()->json(['code' => '1002', 'msg' => '未找到对应的折扣']);
            }

            //对表内容进行格式化
            $format_current_info = [];
            $channel_arr = [];
            foreach ($discount_info as $k => $v) {
                $pin_str = $v["channels_name"] . "-" . $v["method_name"];
                if (isset($format_current_info[$v["name"]])) {
                    $format_current_info[$v["name"]]["discount_info"][$pin_str] = $v["brand_discount"];
                } else {
                    $tmp_arr["name"] = $v["name"];
                    $tmp_arr["discount_info"][$pin_str] = $v["brand_discount"];
                    $format_current_info[$v["name"]] = $tmp_arr;
                }

                if (!in_array($pin_str, $channel_arr)) {
                    array_push($channel_arr, $pin_str);
                }
            }

            $format_current_info = array_values($format_current_info);
            foreach ($channel_arr as $k => $v) {
                foreach ($format_current_info as $k1 => $v1) {
                    if (!isset($v1["discount_info"][$v])) {
                        $format_current_info[$k1]["discount_info"][$v] = "-";
                    }
                }
            }

            $return_info["channel_info"] = $channel_arr;
            $return_info["data_info"] = $format_current_info;
            $code = "1000";
            $msg = "获取采购列表成功";
            $data = $return_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:改变表格标题样式
     * editor:zongxing
     * date : 2018.06.28
     * params: 1.excel对象:$obj_excel;2.最后一列的名称:$column_last_name;
     * return Object
     */
    public function changeTableTitle($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
    {
        //标题居中+加粗
        $obj_excel->getActiveSheet()->getStyle($column_first_name . $row_first_i . ":" . $column_last_name . $row_last_i)
            ->applyFromArray(
                array(
                    'font' => array(
                        'bold' => true
                    ),
                    'alignment' => array(
                        'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                    )
                )
            );
    }

    /**
     * description:改变表格内容样式
     * editor:zongxing
     * date : 2018.06.28
     * params: 1.excel对象:$obj_excel;2.最后一列的名称:$column_last_name;3.最大行号:$row_end;
     * return Object
     */
    public function changeTableContent($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
    {
        //内容只居中
        $obj_excel->getActiveSheet()->getStyle($column_first_name . $row_first_i . ":" . $column_last_name . $row_last_i)->applyFromArray(
            array(
                'alignment' => array(
                    'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                )
            )
        );
    }

    /**
     * description:品牌vip折扣表上传
     * editor:zongxing
     * type:POST
     * date : 2018.12.26
     * params: 1.需要上传的excel表格文件:upload_file;
     * return Object
     */
    public function uploadVipDiscount_stop(Request $request)
    {
        if ($request->isMethod('post')) {
            $brand_discount_array = $request->toArray();
            if (empty($brand_discount_array['upload_file'])) {
                return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
            }

            //检查上传文件是否合格
            $file = $_FILES;
            $excuteExcel = new ExcuteExcel();
            $fileName = '商品采购vip折扣表';//要上传的文件名，将对上传的文件名做比较
            $res = $excuteExcel->verifyUploadFileZ($file, $fileName);
            if (isset($res['code'])) {
                return response()->json($res);
            }

            //检查字段名称
            $arrTitle = ['品牌', '购买渠道'];
            foreach ($arrTitle as $title) {
                if (!in_array(trim($title), $res[0])) {
                    $returnMsg = ['code' => '1005', 'msg' => '您的标题头有误，请按模板导入'];
                    return response()->json($returnMsg);
                }
            }

            //获取系统所有渠道
            $purchase_channel_model = new PurchaseChannelModel();
            $channels_total_info = $purchase_channel_model->getChannelList();
            $channels_name_arr = $channels_total_info['channels_name_arr'];
            $channels_list = $channels_total_info['channels_list'];

            //判断上传渠道信息
            $channels_num = count($res[0]);
            for ($i = 0; $i < $channels_num; $i = $i + 2) {
                if ($i >= 4) {
                    if (empty($res[0][$i])) {
                        return response()->json(['code' => '1006', 'msg' => '您上传的采购渠道为空，请先添加采购渠道']);
                    } else {
                        if (!in_array($res[0][$i], $channels_name_arr)) {
                            $returnMsg = ['code' => '1006', 'msg' => '您上传的采购渠道:' . $res[0][$i] . '不存在，请先添加采购渠道'];
                            return response()->json($returnMsg);
                        }
                    }
                }
            }

            //获取系统所有方式
            $purchase_method_model = new PurchaseMethodModel();
            $method_list = $purchase_method_model->getMethodList();
            $method_arr = array_keys($method_list);
            foreach ($res as $k => $v) {
                if ($k < 2) continue;
                if (empty($v[3])) {
                    return response()->json(['code' => '1012', 'msg' => '您上传的采购方式为空，请先添加采购方式']);
                } else {
                    if (!in_array($v[3], $method_arr)) {
                        return response()->json(['code' => '1012', 'msg' => '您上传的采购方式:' . $v[3] . '不存在，请先添加采购方式']);
                    }
                }
            }

            //检查上传VIP折扣信息中的品牌信息
            $brand_model = new BrandModel();
            $check_res_info = $brand_model->checkVipDiscountBrand($res);
            $brand_list = $check_res_info['brand_list'];
            $diff_brand = $check_res_info['diff_brand'];
            if (!empty($diff_brand)) {
                $diff_brand = substr($diff_brand, 0, -1);
                return response()->json(['code' => '1011', 'msg' => "您上传的品牌:" . $diff_brand . " 在系统中不存在,请先校验"]);
            }

            //获取系统已有折扣
            $vip_discount_model = new VipDiscountModel();
            $discount_list = $vip_discount_model->getVipDiscountList();

            //组装采购折扣详情表添加数据
            $discountDetailData = $this->createDetailData($res, $brand_list, $method_list, $channels_list, $discount_list);
            if (empty($discountDetailData)) {
                return response()->json(['code' => '1012', 'msg' => '折扣信息组装失败']);
            }
            //采购折扣日志表sn
            $str = date('Ymdhis');
            $discount_sn = "ZK-" . $str;
            $vipDiscountModel = new VipDiscountModel();
            $uploadRes = $vipDiscountModel->discountChange($discountDetailData, $discount_sn);
            $return_info = ['code' => '1009', 'msg' => "文件上传失败"];
            if ($uploadRes !== false) {
                $return_info = ['code' => '1000', 'msg' => '文件上传成功'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => "请求错误"];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取当前采购vip折扣列表
     * editor:zongxing
     * type:GET
     * date : 2018.12.26
     * return Array
     */
    public function getVipDiscountList(Request $request)
    {
        if ($request->isMethod("get")) {
            $search_info = $request->toArray();
            //获取当前采购vip折扣数据
            $vipDiscountModel = new VipDiscountModel();
            $discount_info = $vipDiscountModel->getVipDiscountCurrent($search_info);
            if (empty($discount_info)) {
                return response()->json(['code' => '1002', 'msg' => '暂无采购vip折扣信息']);
            }
            //对表内容进行格式化
            $format_current_info = [];
            $channel_arr = [];
            foreach ($discount_info as $k => $v) {
                $pin_str = $v["channels_name"] . "-" . $v["method_name"];
                $tmp_arr["name"] = $v["name"];
                $tmp_arr["discount_info"][$pin_str] = $v["brand_discount"];

                if (isset($format_current_info[$v["name"]])) {
                    if (isset($format_current_info[$v["name"]]["discount_info"][$pin_str])) {
                        $format_current_info[$v["name"]]["discount_info"][$pin_str] = $v["brand_discount"];
                    } else {
                        $format_current_info[$v["name"]]["discount_info"][$pin_str] = $v["brand_discount"];
                    }
                } else {
                    $format_current_info[$v["name"]] = $tmp_arr;
                }

                if (!in_array($pin_str, $channel_arr)) {
                    $channel_arr[] = $pin_str;
                }
            }

            $format_current_info = array_values($format_current_info);
            foreach ($channel_arr as $k => $v) {
                foreach ($format_current_info as $k1 => $v1) {
                    if (!isset($v1["discount_info"][$v])) {
                        $format_current_info[$k1]["discount_info"][$v] = "-";
                    }
                }
            }

            $return_info["channel_info"] = $channel_arr;
            $return_info["data_info"] = $format_current_info;
            $code = "1000";
            $msg = "获取采购vip折扣列表成功";
            $data = $return_info;
            $return_info = compact('code', 'msg', 'data');
        } else {
            $code = "1001";
            $msg = "请求错误";
            $return_info = compact('code', 'msg');
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-优采推荐管理-采购折扣-新增品牌折扣
     * editor:zongxing
     * type:GET
     * date : 2019.02.14
     * return Array
     */
    public function addBrandDiscount(Request $request)
    {
        if ($request->isMethod("get")) {
            //获取品牌列表
            $brand_list_info = DB::table('brand')->get(['name', 'brand_id']);
            $brand_list_info = objectToArrayZ($brand_list_info);
            if (empty($brand_list_info)) {
                $return_info = ['code' => '1002', 'msg' => '品牌信息有误'];
            }
            //获取采购方式信息
            $purchase_method_model = new PurchaseMethodModel();
            $purchase_method_list = $purchase_method_model->getMethodList();
            $purchase_method_list = array_values($purchase_method_list);
            if (empty($purchase_method_list)) {
                $return_info = ['code' => '1003', 'msg' => '采购方式信息有误'];
            }
            //获取采购渠道信息
            $purchase_channels_model = new PurchaseChannelModel();
            $purchase_channels_list = $purchase_channels_model->getChannelList();
            if (empty($purchase_channels_list)) {
                $return_info = ['code' => '1004', 'msg' => '采购渠道信息有误'];
            }

            $return_info['brand_list_info'] = $brand_list_info;
            $return_info['purchase_method_list'] = $purchase_method_list;
            $return_info['purchase_channels_list'] = $purchase_channels_list;
            $return_info = ['code' => '1000', 'msg' => '打开新增品牌折扣成功', 'data' => $return_info];
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-优采推荐管理-采购折扣-提交新增品牌折扣
     * editor:zongxing
     * type:GET
     * date : 2019.02.14
     * return Array
     */
    public function doAddBrandDiscount(Request $request)
    {
        if ($request->isMethod('post')) {
            $param_info = $request->toArray();
            if (empty($param_info["brand_id"])) {
                return response()->json(['code' => '1002', 'msg' => '品牌信息不能为空']);
            } elseif (empty($param_info["method_id"])) {
                return response()->json(['code' => '1003', 'msg' => '采购方式信息不能为空']);
            } elseif (empty($param_info["channels_id"])) {
                return response()->json(['code' => '1004', 'msg' => '采购渠道信息不能为空']);
            } elseif (!isset($param_info["brand_discount"])) {
                return response()->json(['code' => '1005', 'msg' => '品牌折扣信息不能为空']);
            }
            $discount_model = new DiscountModel();
            $brand_discount_info = $discount_model->getBrandDiscountInfo($param_info);
            $res = $discount_model->doAddBrandDiscount($param_info, $brand_discount_info);
            $return_info = ['code' => '1000', 'msg' => '新增品牌折扣成功'];
            if ($res === false) {
                $return_info = ['code' => '1007', 'msg' => '新增品牌折扣失败'];
            }
        } else {
            $return_info = ['code' => '1001', 'msg' => '请求错误'];
        }
        return response()->json($return_info);
    }

    /**
     * description:采购模块-优采规则管理-品牌采购折扣批量新增
     * editor:zongxing
     * type:POST
     * date : 2019.03.21
     * return Array
     */
    public function batchAddBrandDiscount(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['method_id'])) {
            return response()->json(['code' => '1002', 'msg' => '采购方式信息不能为空']);
        } elseif (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1003', 'msg' => '采购渠道信息不能为空']);
        } elseif (empty($param_info['brand_discount'])) {
            return response()->json(['code' => '1004', 'msg' => '品牌折扣信息不能为空']);
        }

        $discount_model = new DiscountModel();
        $batch_add_res = $discount_model->batchAddBrandDiscount($param_info);
        $return_info = ['code' => '1007', 'msg' => '新增品牌折扣失败'];
        if ($batch_add_res !== false) {
            $return_info = ['code' => '1000', 'msg' => '新增品牌折扣成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:导出折扣信息
     * editor:zongxing
     * date : 2019.03.22
     */
    public function discountExport()
    {
        $discount_info = DB::table('discount as d')
            ->leftJoin('brand as b', 'b.brand_id', 'd.brand_id')
            ->leftJoin('purchase_method as pm', 'pm.id', 'd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', 'd.channels_id')
            ->groupBy('d.brand_id', 'd.method_id', 'd.channels_id')
            ->orderBy('d.brand_discount', 'ASC')
            ->get()
            ->groupBy('brand_id');
        $discount_info = objectToArrayZ($discount_info);
        //组装品牌信息
        $channel_list = [];
        $brand_discount_list = [];
        foreach ($discount_info as $k => $v) {
            $brand_id = $v[0]['brand_id'];
            $brand_name = $v[0]['name'];
            $brand_discount_list[$k] = [
                '0' => $brand_id,
                '1' => $brand_name,
            ];
            foreach ($v as $k1 => $v1) {
                $channel_i = $k1 + 2;
                $method_name = $v1['method_name'];
                $channels_name = $v1['channels_name'];
                $brand_discount = $v1['brand_discount'];
                $pin_str = $method_name . '-' . $channels_name;
                if (!in_array($pin_str, $channel_list)) {
                    $channel_list[] = $pin_str;
                }
                $brand_discount_list[$k]['discount_info'][$channel_i] = [
                    'channel_name' => $pin_str,
                    'brand_discount' => $brand_discount,
                ];
            }
        }
        $brand_discount_list = array_values($brand_discount_list);

        $obpe = new PHPExcel();
        $obpe->getActiveSheet()->setCellValue('A1', '品牌id')->getColumnDimension('A')->setWidth(5);
        $obpe->getActiveSheet()->setCellValue('B1', '品牌名称')->getColumnDimension('B')->setWidth(15);
        $obpe->getActiveSheet()->setCellValue('C1', '渠道信息')->getColumnDimension('C')->setWidth(15);
        //合并表头
        $column_num = count($channel_list) + 1;
        $column_name_tmp = \PHPExcel_Cell::stringFromColumnIndex($column_num);
        $obpe->getActiveSheet()->mergeCells('C1' . ':' . $column_name_tmp . 1);

        //改变表格样式为加粗和居中
        $column_first_name = 'A';
        $row_first_i = 1;
        changeTableTitle($obpe, $column_first_name, $row_first_i, $column_name_tmp, 1);

        foreach ($brand_discount_list as $k => $v) {
            //获取品牌id和名称，并插入到excel
            $brand_id = $v[0];
            $brand_name = $v[1];
            $row_i = $k * 2 + 2;
            $obpe->getActiveSheet()->setCellValue('A' . $row_i, $brand_id)->getColumnDimension('A')->setWidth(10);
            $obpe->getActiveSheet()->setCellValue('B' . $row_i, $brand_name)->getColumnDimension('A')->setWidth(15);

            //合并A、B列
            $row_i_next_i = $row_i + 1;
            $obpe->getActiveSheet()->mergeCells('A' . $row_i . ':A' . $row_i_next_i);
            $obpe->getActiveSheet()->mergeCells('B' . $row_i . ':B' . $row_i_next_i);

            //获取品牌折扣信息，并插入到excel
            $brand_discount_info = $v['discount_info'];
            foreach ($brand_discount_info as $k1 => $v1) {
                //获取列名
                $channel_name_tmp = \PHPExcel_Cell::stringFromColumnIndex($k1);
                $channel_name = $v1['channel_name'];
                $brand_discount = $v1['brand_discount'];
                //设置渠道名称
                $obpe->getActiveSheet()->setCellValue($channel_name_tmp . $row_i, $channel_name)
                    ->getColumnDimension($channel_name_tmp)->setWidth(20);
                //设置渠道对应的折扣
                $obpe->getActiveSheet()->setCellValue($channel_name_tmp . $row_i_next_i, $brand_discount)
                    ->getColumnDimension($channel_name_tmp)->setWidth(20);
            }
        }

        //获取最大列名称
        $currentSheet = $obpe->getSheet(0);
        $column_last_name = $currentSheet->getHighestColumn();
        //获取最大行数
        $row_last_i = $currentSheet->getHighestRow();
        changeTableContent($obpe, 'A', 1, $column_last_name, $row_last_i);
        //清除缓存
        ob_end_clean();
        //写入内容
        $obpe->getActiveSheet()->setTitle('品牌采购折扣');
        $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');
        $str = rand(1000, 9999);
        $filename = '品牌采购折扣渠道详情表_' . $str . '.xls';

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
     * description:批量导出商品优采推荐折扣信息
     * editor:zongxing
     * date : 2019.04.16 待优化
     */
    public function discountExportByGoods(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['upload_file'])) {
            return response()->json(['code' => '1002', 'msg' => '上传文件不能为空']);
        }
        //检查上传文件是否合格
        $excuteExcel = new ExcuteExcel();
        $fileName = '批量导出商品优采推荐折扣';//要上传的文件名，将对上传的文件名做比较
        $res = $excuteExcel->verifyUploadFileZ($_FILES, $fileName);

        $field = ['b.brand_id', 'channels_name', 'method_name', 'brand_discount'];
        $discount_info = DB::table('discount as d')
            ->leftJoin('brand as b', 'b.brand_id', 'd.brand_id')
            ->leftJoin('purchase_method as pm', 'pm.id', 'd.method_id')
            ->leftJoin('purchase_channels as pc', 'pc.id', 'd.channels_id')
            ->groupBy('d.brand_id', 'd.method_id', 'd.channels_id')
            ->orderBy('d.brand_discount', 'ASC')
            ->get($field)
            ->groupBy('brand_id');
        $discount_info = objectToArrayZ($discount_info);
        // dd($res, $discount_info);
        //组装品牌信息
        $channel_num = 0;
        foreach ($discount_info as $k => $v) {
            if (count($v) > $channel_num) {
                $channel_num = count($v);
            }
        }

        $upload_info = [];
        foreach ($res as $k => $v) {
            $v_num = count($v);//上传表的列数
            if ($k == 0) {
                $v[$v_num] = '优采推荐';
                $upload_info[$k] = $v;

                for ($i = 0; $i < $channel_num; $i++) {
                    $upload_info[1][$i + $v_num] = $i + 1;
                }
                continue;
            }
            $brand_id = intval($v[0]);
            $brand_info = [];
            if (isset($discount_info[$brand_id])) {
                $brand_info = $discount_info[$brand_id];
            }
            $v[$v_num] = $brand_info;
            $upload_info[$k + 1] = $v;
        }

        $obpe = new PHPExcel();
        foreach ($upload_info as $k => $v) {
            if ($k < 2) {
                $row_i = $k + 1;
                foreach ($v as $k1 => $v1) {
                    $column_name_tmp = \PHPExcel_Cell::stringFromColumnIndex($k1);
                    $obpe->getActiveSheet()->setCellValue($column_name_tmp . $row_i, $v1)
                        ->getColumnDimension($column_name_tmp)->setWidth(15);
                }
            } else {
                $row_i = $k * 2 - 2;
                $row_next_i = $row_i + 1;

                $v_num = count($v);
                $v_last_num = $v_num - 1;
                foreach ($v as $k1 => $v1) {
                    if ($k1 == $v_last_num) {
                        foreach ($v1 as $k2 => $v2) {
                            $column_i = $k2 + $k1;
                            $column_name_tmp = \PHPExcel_Cell::stringFromColumnIndex($column_i);
                            $pin_str = $v2['channels_name'] . $v2['method_name'];
                            $brand_discount = $v2['brand_discount'];
                            $obpe->getActiveSheet()->setCellValue($column_name_tmp . $row_i, $pin_str)
                                ->getColumnDimension($column_name_tmp)->setWidth(15);
                            $obpe->getActiveSheet()->setCellValue($column_name_tmp . $row_next_i, $brand_discount)
                                ->getColumnDimension($column_name_tmp)->setWidth(15);
                        }
                    } else {
                        $column_name_tmp = \PHPExcel_Cell::stringFromColumnIndex($k1);
                        $obpe->getActiveSheet()->setCellValue($column_name_tmp . $row_i, $v1)
                            ->getColumnDimension($column_name_tmp)->setWidth(15);
                    }
                }
            }
        }


//        //合并表头
//        $column_num = count($channel_list) + 1;
//        $column_name_tmp = \PHPExcel_Cell::stringFromColumnIndex($column_num);
//        $obpe->getActiveSheet()->mergeCells('C1' . ':' . $column_name_tmp . 1);
//
//        //改变表格样式为加粗和居中
//        $column_first_name = 'A';
//        $row_first_i = 1;
//        changeTableTitle($obpe, $column_first_name, $row_first_i, $column_name_tmp, 1);

//        //获取最大列名称
//        $currentSheet = $obpe->getSheet(0);
//        $column_last_name = $currentSheet->getHighestColumn();
//        //获取最大行数
//        $row_last_i = $currentSheet->getHighestRow();
//        changeTableContent($obpe, 'A', 1, $column_last_name, $row_last_i);
        //清除缓存
        ob_end_clean();
        //写入内容
        //$obpe->getActiveSheet()->setTitle('采购任务商品及品牌折扣表');

        $str = rand(1000, 9999);
        $savePath = './downTemp/';
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }
        $filename = 'goods_discount/采购任务商品及品牌折扣表_' . $str . '.xls';
        $obwrite = \PHPExcel_IOFactory::createWriter($obpe, 'Excel5');
        //保存文件
        header('Content-Type: application/vnd.ms-excel;charset=UTF-8');
        $save_path = iconv('utf-8', 'GBK', $savePath . $filename);
        $obwrite->save($save_path);

        //$request = objectToArrayZ($request);
        $server_info = $request->server;
//        $host = $request->getHttpHost();
//        //$server_info = objectToArrayZ($server_info);
//        $server_host = $host . '/downTemp/';

        //dd($server_host.$filename);

        //header('location:http://'.$server_host.$filename);
//        $name = '发货订单';
//        $name = iconv('GBK', 'utf-8', $name);
//        header('location:http://192.168.0.39:9999/downTemp/test.xls');


        return $filename;

        //直接在浏览器输出
//        header('Content-Type: application/vnd.ms-excel;charset=UTF-8');
//        header('Pragma: public');
//        header('Expires: 0');
//        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
//        header('Content-Type:application/force-download');
//        header('Content-Type:application/octet-stream');
//        header('Content-Type:application/download');
//        header("Content-Disposition:attachment;filename=$filename");
//        header('Content-Transfer-Encoding:binary');
        //$obwrite->save('php://output');
    }

    /**
     * description:品牌折扣上传数据-审核
     * author:zhangdong
     * type:POST
     * date : 2019.04.03
     */
    public function auditBrandDiscount(Request $request)
    {
        $reqParams = $request->toArray();
        $audit_sn = isset($reqParams['audit_sn']) ? trim($reqParams['audit_sn']) : '';
        $isPass = isset($reqParams['is_pass']) ? intval($reqParams['is_pass']) : '';
        if (empty($audit_sn) || empty($isPass)) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //检查审核单号是否存在
        $auditModel = new AuditModel();
        $auditSnInfo = $auditModel->getAuditInfo($audit_sn);
        if (is_null($auditSnInfo)) {
            $returnMsg = ['code' => '2067', 'msg' => '没找到该审核单信息'];
            return response()->json($returnMsg);
        }
        //是否需要审核 0 不需要 1 需要
        $is_audit = intval($auditSnInfo->is_audit);
        if ($is_audit === 0) {
            $returnMsg = ['code' => '2067', 'msg' => '当前审核单无需审核，请直接提交数据'];
            return response()->json($returnMsg);
        }
        //检查当前操作人是否有权限操作
        $config_sn = trim($auditSnInfo->config_sn);
        //当前审核进度
        $curAuditOrder = intval($auditSnInfo->audit_order);
        $acModel = new AuditConfigModel();
        $checkRes = $acModel->checkHaveRight($config_sn, $curAuditOrder);
        if ($checkRes === false) {
            $returnMsg = ['code' => '2067', 'msg' => '当前审核单您没有权限操作'];
            return response()->json($returnMsg);
        }
        //要修改的下一个审核进度
        $nextAuditOrder = $checkRes;
        //修改审核状态
        $updateRes = $auditModel->updateAuditData($audit_sn, $isPass, $nextAuditOrder);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($updateRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);

    }//end of function

    /**
     * description:品牌折扣上传数据-数据提交
     * author:zhangdong
     * type:POST
     * date : 2019.04.03
     */
    public function submitBrandDiscount(Request $request)
    {
        $reqParams = $request->toArray();
        $audit_sn = isset($reqParams['audit_sn']) ? trim($reqParams['audit_sn']) : '';
        if (empty($audit_sn)) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $auditModel = new AuditModel();
        //根据审核单号查询审核单信息
        $auditInfo = $auditModel->getAuditInfo($audit_sn);
        if (is_null($auditInfo)) {
            $returnMsg = ['code' => '2067', 'msg' => '没有该审核单'];
            return response()->json($returnMsg);
        }
        //检查当前审核单是否已经全部审核通过
        $checkRes = $auditModel->checkAuditIsPass($auditInfo);
        if ($checkRes === false) {
            $returnMsg = ['code' => '2067', 'msg' => '当前订单还未全部审核通过'];
            return response()->json($returnMsg);
        }
        //如果已经全部审核通过则开始提交数据（有则更新无则新增）
        //查询审核单详情数据
        $daModel = new DiscountAuditModel();
        $auditDetail = $daModel->queryAuditDetail($audit_sn);
        if ($auditDetail->count() == 0) {
            $returnMsg = ['code' => '2067', 'msg' => '审核详情为空'];
            return response()->json($returnMsg);
        }
        //提交数据
        $submitRes = $daModel->submitBrandDiscount($auditDetail);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($submitRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:审核列表
     * author:zhangdong
     * type:POST
     * date : 2019.04.08
     */
    public function authList(Request $request)
    {
        $reqParams = $request->toArray();
        $pageSize = isset($reqParams['pageSize']) ? intval($reqParams['pageSize']) : 15;
        $auditModel = new AuditModel();
        $auditList = $auditModel->queryAuditList($reqParams, $pageSize);
        $acModel = new AuditConfigModel();
        foreach ($auditList as $key => $value) {
            $auditList[$key]->status_desc = trim($auditModel->status_desc[intval($value->status)]);
            $auditList[$key]->audit_desc = trim($auditModel->is_audit[intval($value->is_audit)]);
            //获取当前审核人
            $config_sn = trim($value->config_sn);
            $audit_order = intval($value->audit_order);
            $queryRes = $acModel->getCurrentAuditor($config_sn, $audit_order);
            $current_auditor = isset($queryRes->nickname) ? trim($queryRes->nickname) : '暂无审核人';
            $auditList[$key]->current_auditor = $current_auditor;
        }
        $returnMsg = [
            'auditList' => $auditList,
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:品牌折扣上传数据详情
     * author:zhangdong
     * type:POST
     * date : 2019.04.08
     */
    public function discountAuditDetail(Request $request)
    {
        $reqParams = $request->toArray();
        //审核单号
        $audit_sn = isset($reqParams['audit_sn']) ?
            trim($reqParams['audit_sn']) : '';
        if (empty($audit_sn)) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        //根据审核号查看审核单详情
        $auditModel = new AuditModel();
        $auditInfo = $auditModel->getAuditInfo($audit_sn);
        if (is_null($auditInfo)) {
            $returnMsg = ['auditInfo' => [], 'auditDetail' => [],];
            return response()->json($returnMsg);
        }
        $status = isset($auditInfo->status) ? intval($auditInfo->status) : 0;
        $is_audit = isset($auditInfo->is_audit) ? intval($auditInfo->is_audit) : 0;
        $auditInfo->status_desc = trim($auditModel->status_desc[$status]);
        $auditInfo->audit_desc = trim($auditModel->is_audit[$is_audit]);
        //判断当前用户是否可以提交数据
        $acModel = new AuditConfigModel();
        $audit_order = intval($auditInfo->audit_order);
        $config_sn = trim($auditInfo->config_sn);
        $canAudit = $acModel->checkCanAudit($config_sn);
        $auditInfo->canAudit = $canAudit;
        $daModel = new DiscountAuditModel();
        $auditDetail = $daModel->getDiscountAuditDetail($audit_sn);
        //获取当前审核人
        $queryRes = $acModel->getCurrentAuditor($config_sn, $audit_order);
        $auditInfo->current_auditor = isset($queryRes->nickname) ? trim($queryRes->nickname) : '暂无审核人';
        $auditInfo->current_auditId = isset($queryRes->id) ? intval($queryRes->id) : '0';
        $returnMsg = [
            'auditInfo' => $auditInfo,
            'auditDetail' => $auditDetail,
        ];
        return response()->json($returnMsg);
    }

    /**
     * description:品牌折扣上传数据-修改
     * author:zhangdong
     * type:POST
     * date:2019.04.08
     */
    public function modifyBrandDiscount(Request $request)
    {
        $reqParams = $request->toArray();
        //审核单号
        $detail_id = isset($reqParams['detail_id']) ? intval($reqParams['detail_id']) : 0;
        $brand_discount = isset($reqParams['brand_discount']) ? floatval($reqParams['brand_discount']) : 0;
        if ($detail_id == 0 || $brand_discount == 0) {
            $returnMsg = ['code' => '2005', 'msg' => '参数错误'];
            return response()->json($returnMsg);
        }
        $daModel = new DiscountAuditModel();
        $modifyRes = $daModel->modifyBrandDiscount($detail_id, $brand_discount);
        $returnMsg = ['code' => '2023', 'msg' => '操作失败'];
        if ($modifyRes) {
            $returnMsg = ['code' => '2024', 'msg' => '操作成功'];
        }
        return response()->json($returnMsg);
    }

    /**
     * description:打开新增折扣类型页面
     * author:zongxing
     * type:GET
     * date:2019.05.05
     */
    public function addDiscountType()
    {
        //获取采购渠道信息
        $purchase_channels_model = new PurchaseChannelModel();
        $purchase_channels_list = $purchase_channels_model->getChannelList();
        if (empty($purchase_channels_list)) {
            return response()->json(['code' => '1003', 'msg' => '采购渠道信息有误']);
        }
        //获取折扣种类
        $param['is_discount'] = 1;
        $dc_model = new DiscountCatModel();
        $dc_info = $dc_model->getDiscountCatList($param);
        if (empty($dc_info)) {
            return response()->json(['code' => '1004', 'msg' => '暂无折扣种类信息']);
        }
        $data = [
            'purchase_channels_list' => $purchase_channels_list,
            'dc_info' => $dc_info,
        ];
        $return_info = ['code' => '1000', 'msg' => '打开新增折扣类型页面成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:新增折扣类型
     * author:zongxing
     * type:POST
     * date:2019.05.05
     */
    public function doAddDiscountType(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['method_id'])) {
            return response()->json(['code' => '1002', 'msg' => '采购方式id不能为空']);
        } elseif (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1003', 'msg' => '采购渠道id不能为空']);
        } elseif (empty($param_info['type_name'])) {
            return response()->json(['code' => '1004', 'msg' => '折扣类型名称不能为空']);
        } elseif (empty($param_info['type_cat'])) {
            return response()->json(['code' => '1007', 'msg' => '折扣类型种类不能为空']);
        }
//        elseif (empty($param_info['min'])) {
//            return response()->json(['code' => '1008', 'msg' => '折扣类型区间下限不能为空']);
//        } elseif (empty($param_info['max'])) {
//            return response()->json(['code' => '1009', 'msg' => '折扣类型区间上限不能为空']);
//        }

        //获取采购渠道信息
        $type_cat = intval($param_info['type_cat']);
        $dti_model = new DiscountTypeInfoModel();
        $discount_type_info = $dti_model->getDiscountTypeList($param_info, $type_cat);
        if (!empty($discount_type_info)) {
            return response()->json(['code' => '1005', 'msg' => '该档位折扣已经存在']);
        }
        //新增折扣档位
        $discount_add_res = $dti_model->doAddDiscountType($param_info);
        $return_info = ['code' => '1006', 'msg' => '新增折扣类型失败'];
        if ($discount_add_res !== false) {
            //获取折扣类型列表
            $discount_type_list = $dti_model->getDiscountTypeList();
            $return_info = ['code' => '1000', 'msg' => '新增折扣类型成功', 'data' => $discount_type_list];
        }
        return response()->json($return_info);
    }

    /**
     * description:获取折扣类型列表
     * author:zongxing
     * type:GET
     * date:2019.05.05
     */
    public function discountTypeList(Request $request)
    {
        $param_info = $request->toArray();
        //获取折扣类型列表
        $dti_model = new DiscountTypeInfoModel();
        $discount_type_list = $dti_model->getDiscountTypeList($param_info);
        if (empty($discount_type_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无折扣类型']);
        }
        $return_info = ['code' => '1000', 'msg' => '获取折扣类型列表成功', 'data' => $discount_type_list];
        return response()->json($return_info);
    }

    /**
     * description:新增折扣类型记录
     * author:zongxing
     * type:POST
     * date:2019.09.02
     */
    public function doAddDiscountTypeLog(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['start_date'])) {
            return response()->json(['code' => '1002', 'msg' => '折扣类型开始时间不能为空']);
        } elseif (empty($param_info['end_date'])) {
            return response()->json(['code' => '1003', 'msg' => '折扣类型结束时间不能为空']);
        } elseif (empty($param_info['method_id'])) {
            return response()->json(['code' => '1004', 'msg' => '折扣类型方式不能为空']);
        } elseif (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1005', 'msg' => '折扣类型渠道不能为空']);
        } elseif (empty($param_info['cost_id'])) {
            return response()->json(['code' => '1006', 'msg' => '成本折扣档位id不能为空']);
        } elseif (empty($param_info['predict_id'])) {
            return response()->json(['code' => '1007', 'msg' => '品牌预计完成档位id不能为空']);
        } elseif (empty($param_info['month_type_id'])) {
            return response()->json(['code' => '1008', 'msg' => '计算毛利档位id不能为空']);
        }
        //获取折扣类型记录信息
        $dtr_model = new DiscountTypeRecordModel();
        $dtr_info = $dtr_model->getDiscountTypeRecordInfo($param_info);
        if (!empty($dtr_info)) {
            return response()->json(['code' => '1009', 'msg' => '该渠道本月已经存在记录']);
        }
        //新增折扣档位
        $res = $dtr_model->doAddDiscountTypeRecord($param_info);
        $return_info = ['code' => '1010', 'msg' => '新增折扣类型记录失败'];
        if ($res !== false) {
            //获取折扣类型列表
            $dtr_list = $dtr_model->getDiscountTypeRecordList();
            $return_info = ['code' => '1000', 'msg' => '新增折扣类型记录成功', 'data' => $dtr_list];
        }
        return response()->json($return_info);
    }

    /**
     * description 获取折扣类型记录列表
     * author zongxing
     * type GET
     * date 2019.09.02
     */
    public function discountTypeLogList(Request $request)
    {
        //获取折扣类型记录列表
        $param_info = $request->toArray();
        $dtr_model = new DiscountTypeRecordModel();
        $dtr_list = $dtr_model->getDiscountTypeRecordList($param_info);
        if (empty($dtr_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无折扣类型记录列表']);
        }
        //获取折扣档位信息
        $dti_model = new DiscountTypeInfoModel();
        $dti_list = $dti_model->getDiscountTypeList();
        $dtr_total_list = [];
        foreach ($dtr_list['data'] as $k => $v) {
            $tmp_arr = [
                'start_date' => $v['start_date'],
                'end_date' => $v['end_date'],
                'method_name' => $v['method_name'],
                'channels_name' => $v['channels_name'],
            ];
            $tmp_field = ['cost', 'predict', 'month_type', 'brand_month_predict'];
            foreach ($dti_list as $k1 => $v1) {
                $type_id = $v1['id'];
                $type_name = $v1['type_name'];
                foreach ($tmp_field as $k2 => $v2) {
                    $tmp_field_arr = explode(',', $v[$v2 . '_id']);
                    if (in_array($type_id, $tmp_field_arr)) {
                        if (isset($tmp_arr[$v2 . '_name'])) {
                            $tmp_arr[$v2 . '_name'] .= $type_name . ',';
                        } else {
                            $tmp_arr[$v2 . '_name'] = $type_name . ',';
                        }
                    }
                }
            }
            foreach ($tmp_field as $k4 => $v4) {
                $tmp_name = $v4 . '_name';
                if (isset($tmp_arr[$tmp_name])) {
                    $tmp_arr[$tmp_name] = substr($tmp_arr[$tmp_name], 0, -1);
                }
            }
            $dtr_total_list[] = $tmp_arr;
        }
        $dtr_list['data'] = $dtr_total_list;
        $return_info = ['code' => '1000', 'msg' => '获取折扣类型记录列表成功', 'data' => $dtr_list];
        return response()->json($return_info);
    }

    /**
     * description:获取方式、渠道、折扣类型列表
     * author:zongxing
     * type:GET
     * date:2019.05.05
     */
    public function methodChannelsTypeList(Request $request)
    {
        $param_info = $request->toArray();
        //获取折扣类型列表
        $dti_model = new DiscountTypeInfoModel();
        $discount_type_list = $dti_model->getDiscountTypeList($param_info);
        if (empty($discount_type_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无折扣类型']);
        }
        $method_channels_list = [];
        foreach ($discount_type_list as $k => $v) {
            $type_info = [
                'type_id' => $v['id'],
                'type_name' => $v['type_name'],
                'type_cat' => $v['type_cat'],
            ];
            $channels_info = [
                'channels_id' => $v['channels_id'],
                'channels_name' => $v['channels_name'],
            ];
            $method_info = [
                'method_id' => $v['method_id'],
                'method_name' => $v['method_name'],
            ];
            if (!isset($method_channels_list[$v['method_id']])) {
                $method_channels_list[$v['method_id']] = $method_info;
                if (!isset($method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']])) {
                    $method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']] = $channels_info;
                    if (!isset($method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']])) {
                        $method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']] = $type_info;
                    }
                } else {
                    if (!isset($method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']])) {
                        $method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']] = $type_info;
                    }
                }
            } else {
                if (!isset($method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']])) {
                    $method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']] = $channels_info;
                    if (!isset($method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']])) {
                        $method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']] = $type_info;
                    }
                } else {
                    if (!isset($method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']])) {
                        $method_channels_list[$v['method_id']]['channels_info'][$v['channels_id']]['type_info'][$v['id']] = $type_info;
                    }
                }
            }
        }

        $method_channels_list = objectToArrayZ($method_channels_list);
        foreach ($method_channels_list as $k => $v) {
            foreach ($v['channels_info'] as $k1 => $v1) {
                $v['channels_info'][$k1]['type_info'] = array_values($v1['type_info']);
            }
            $method_channels_list[$k]['channels_info'] = array_values($v['channels_info']);
        }
        $method_channels_list = array_values($method_channels_list);
        $return_info = ['code' => '1000', 'msg' => '获取折扣类型列表成功', 'data' => $method_channels_list];
        return response()->json($return_info);
    }

    /**
     * description:维护折扣类型对应的折扣
     * author:zongxing
     * type:GET
     * date:2019.05.05
     */
    public function doUploadDiscountType(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['type_id'])) {
            return response()->json(['code' => '1002', 'msg' => '折扣类型id不能为空']);
        } elseif (empty($param_info['upload_file'])) {
            return response()->json(['code' => '1003', 'msg' => '上传文件不能为空']);
        } elseif (empty($param_info['method_id'])) {
            return response()->json(['code' => '1006', 'msg' => '采购方式id不能为空']);
        } elseif (empty($param_info['channels_id'])) {
            return response()->json(['code' => '1007', 'msg' => '采购渠道id不能为空']);
        } elseif (empty($param_info['start_date'])) {
            return response()->json(['code' => '1008', 'msg' => '折扣开始时间不能为空']);
        } elseif (empty($param_info['end_date'])) {
            return response()->json(['code' => '1009', 'msg' => '折扣结束时间不能为空']);
        } elseif (!isset($param_info['is_exw'])) {
            return response()->json(['code' => '1010', 'msg' => '是否为exw折扣不能为空']);
        }
        //获取折扣类型列表
        $dti_model = new DiscountTypeInfoModel();
        $discount_type_info = $dti_model->getDiscountTypeList($param_info);
        if (empty($discount_type_info)) {
            return response()->json(['code' => '1004', 'msg' => '折扣类型id错误']);
        }
        //对上传的表格进行判断
        $upload_file = $_FILES;
        //检查上传文件是否合格
        $excuteExcel = new ExcuteExcel();
        $fileName = '采购档位折扣上传';//要上传的文件名，将对上传的文件名做比较
        $res = $excuteExcel->verifyUploadFileZ($upload_file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        //检查字段名称
        $arrTitle = ['品牌id', '折扣/返点'];
        foreach ($arrTitle as $title) {
            if (!in_array(trim($title), $res[0])) {
                return response()->json(['code' => '1005', 'msg' => '您的标题头有误，请按模板导入']);
            }
        }
        $upload_info = [];
        foreach ($res as $k => $v) {
            if ($k == 0) continue;
            if (!empty($v[0])) {
                $upload_info[$v[0]] = floatval($v[1]);
            }
        }
        $dt_model = new DiscountTypeModel();
        $res = $dt_model->doUploadDiscountType($upload_info, $discount_type_info, $param_info);
        if ($res == false) {
            return response()->json(['code' => '1005', 'msg' => '采购档位折扣上传失败']);
        }
        $return_info = ['code' => '1000', 'msg' => '采购档位折扣上传成功'];
        return response()->json($return_info);
    }

    /**
     * description:获取品牌采购折扣列表
     * editor:zongxing
     * type:GET
     * date : 2019.05.06
     * return Array
     */
    public function discountTotalList(Request $request)
    {
        $param_info = $request->toArray();
        $dt_model = new DiscountTypeModel();
        $discount_total_list = $dt_model->discountTotalList($param_info);
        if (empty($discount_total_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无采购折扣信息']);
        }

        $discount_total_info = [];
        foreach ($discount_total_list as $k => $v) {
            $brand_name = $v['name'];
            if (!isset($discount_total_info[$brand_name])) {
                $discount_total_info[$brand_name]['brand_name'] = $brand_name;
            }
            $discount_total_info[$brand_name]['discount_info'][] = $v;
        }
        $data = array_values($discount_total_info);
        $return_info = ['code' => '1000', 'msg' => '获取采购折扣列表成功', 'data' => $data];
        return response()->json($return_info);
    }

    /**
     * description:设置折扣档位
     * editor:zongxing
     * type:POST
     * date : 2019.05.10
     * return Array
     */
    public function discountTypeSetting(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['type_id'])) {
            return response()->json(['code' => '1004', 'msg' => '采购档位id不能为空']);
        } elseif (empty($param_info['set_type'])) {
            return response()->json(['code' => '1005', 'msg' => '采购档位种类不能为空']);
        }
        $set_type = intval($param_info['set_type']);
        if ($set_type !== 1 && $set_type !== 2 && $set_type !== 3) {
            return response()->json(['code' => '1006', 'msg' => '采购档位种类错误']);
        }
        //检查档位是否存在
        $dti_model = new DiscountTypeInfoModel();
        $dti_info = $dti_model->getDiscountTypeList($param_info);
        if (empty($dti_info)) {
            return response()->json(['code' => '1007', 'msg' => '采购档位id错误']);
        }
        //设置成本折扣和VIP折扣档位
        $discount_model = new DiscountModel();
        if ($set_type == 1 || $set_type == 2) {
            $is_start = intval($dti_info[0]['is_start']);
            if ($is_start == 1) {
                return response()->json(['code' => '1009', 'msg' => '您选择的已经是成本折扣']);
            }
            $res = $discount_model->discountTypeSetting($param_info, $dti_info);
        } else {
            //获取折扣类型信息
            $dt_model = new DiscountTypeModel();
            $field_name = 'brand_id';
            $brand_info = $dt_model->getDiscountTypeInfo($param_info, $field_name);
            //设置exw折扣档位
            $res = $discount_model->setExwDiscount($param_info, $brand_info);
        }
        $return_info = ['code' => '1008', 'msg' => '设置折扣档位失败'];
        if ($res !== false) {
            $return_info = ['code' => '1000', 'msg' => '设置折扣档位成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:编辑折扣档位
     * editor:zongxing
     * type:POST
     * date : 2019.05.10
     * return Array
     */
    public function editDiscountType(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['type_id'])) {
            return response()->json(['code' => '1001', 'msg' => '折扣类型id不能为空']);
        } elseif (empty($param_info['edit_field'])) {
            return response()->json(['code' => '1002', 'msg' => '编辑字段不能为空']);
        } elseif (!isset($param_info['status'])) {
            return response()->json(['code' => '1003', 'msg' => '折扣类型状态不能为空']);
        }
        //检查编辑字段
        $tmp_arr = ['is_start'];
        $edit_field = trim($param_info['edit_field']);
        if (!in_array($edit_field, $tmp_arr)) {
            return response()->json(['code' => '1004', 'msg' => '编辑字段错误']);
        }
        //检查档位是否存在
        $dti_model = new DiscountTypeInfoModel();
        $dti_info = $dti_model->getDiscountTypeList($param_info);
        if (empty($dti_info)) {
            return response()->json(['code' => '1004', 'msg' => '采购档位id错误']);
        }
        //编辑折扣档位
        $res = $dti_model->editDiscountType($param_info);
        $return_info = ['code' => '1005', 'msg' => '设置折扣档位失败'];
        if ($res !== false) {
            $return_info = ['code' => '1000', 'msg' => '设置折扣档位成功'];
        }
        return response()->json($return_info);
    }

    /**
     * description:商品渠道追加折扣维护
     * editor:zongxing
     * type:POST
     * date : 2019.05.20
     * return Array
     */
    public function uploadGmcDiscount(Request $request)
    {
        $param_info = $request->toArray();
        if (empty($param_info['type_id'])) {
            return response()->json(['code' => '1002', 'msg' => '折扣类型id不能为空']);
        } elseif (empty($param_info['upload_file'])) {
            return response()->json(['code' => '1003', 'msg' => '上传文件不能为空']);
        } elseif (empty($param_info['update_type'])) {
            return response()->json(['code' => '1004', 'msg' => '维护方式不能为空']);
        } elseif (empty($param_info['start_date'])) {
            return response()->json(['code' => '1008', 'msg' => '开始日期不能为空']);
        } elseif (empty($param_info['end_date'])) {
            return response()->json(['code' => '1009', 'msg' => '结束日期不能为空']);
        }
        $dti_model = new DiscountTypeInfoModel();
        $dti_info = $dti_model->getDiscountTypeList($param_info);
        if (empty($dti_info)) {
            return response()->json(['code' => '1010', 'msg' => '折扣类型id错误']);
        }
        $upload_file = $_FILES;
        //检查上传文件是否合格
        $excuteExcel = new ExcuteExcel();
        $update_type = intval($param_info['update_type']);//维护方式，1表示按照商品维护；2表示按照品牌维护
        if ($update_type != 1 && $update_type != 2) {
            return response()->json(['code' => '1005', 'msg' => '维护方式错误']);
        }
        if ($update_type == 1) {
            $fileName = '维护商品追加折扣(返点)模板';//要上传的文件名，将对上传的文件名做比较
            $arrTitle = ['商品名称', '品牌ID', '商品品牌', '商家编码', '参考码', '商品代码', '美金原价', '商品重量', '预估重量', '折扣/返点'];
        } elseif ($update_type == 2) {
            $fileName = '商品渠道追加折扣维护（品牌）';
            $arrTitle = ['品牌id', '折扣/返点'];
        }
        $res = $excuteExcel->verifyUploadFileZ($upload_file, $fileName);
        if (isset($res['code'])) {
            return response()->json($res);
        }
        foreach ($arrTitle as $title) {
            $title = trim($title);
            if (!in_array($title, $res[0])) {
                return response()->json(['code' => '1006', 'msg' => '您的标题头有误，请按模板导入']);
            }
        }
        //整理上传商品信息（维护商品追加折扣）
        $gs_model = new GoodsSpecModel();
        $gs_info = $gs_model->createUploadGoodsInfo($res, $arrTitle);
        if (isset($gs_info['code'])) {
            return response()->json($gs_info);
        }
        $upload_goods_info = $gs_info['upload_goods_info'];
        $diff_goods_info = $gs_info['diff_goods_info'];
        //折扣维护
        if ($update_type == 1) {
            $gmc_model = new GmcDiscountModel();
            $res = $gmc_model->uploadGmcDiscountBySpec($upload_goods_info, $dti_info, $param_info);
            if (isset($res['code'])) {
                return response()->json($res);
            }
        } elseif ($update_type == 2) {
            $gmc_model = new GmcDiscountModel();
            $res = $gmc_model->uploadGmcDiscountByBrand($res, $dti_info);
        }
        $return_info = ['code' => '1007', 'msg' => '商品渠道追加折扣维护失败'];
        if ($res !== false && empty($diff_goods_info)) {
            $return_info = ['code' => '1000', 'msg' => '商品渠道追加折扣维护成功'];
        }
        if (!empty($diff_goods_info)) {
            $return_info = ['code' => '1001', 'msg' => '商品渠道追加折扣维护成功,存在新商品', 'data' => $diff_goods_info];
        }
        return response()->json($return_info);
    }

    /**
     * description 下载特殊商品中的新品列表
     * editor zongxing
     * type GET
     * date 2019.10.22
     * return Array
     */
    public function downloadDiscountDiffGoods(Request $request)
    {
        $title[] = ['商品名称', '商品分类', '品牌ID', '商品品牌', '商家编码', '考拉编码', '小红书编码', '参考代码', '商品代码',
            '美金原价', '商品重量', '预估重量', 'EXW折扣'];
        $param_info = $request->toArray();
        $diff_goods_info = json_decode(json_encode($param_info['diff_goods_info']), true);
        //$diff_goods_info = json_decode($param_info['diff_goods_info'], true);
        $new_goods_list = [];
        foreach ($diff_goods_info as $k => $v) {
            $new_goods_list[] = [
                'goods_name'=>trim($v['goods_name']),
                'cat_id' => '缺失',
                'brand_id'=>intval($v['brand_id']),
                'brand_name'=>trim($v['brand_name']),
                'erp_merchant_no'=>trim($v['erp_merchant_no']),
                'kl_code'=>'',
                'rd_code'=>'',
                'erp_ref_no'=>trim($v['erp_ref_no']),
                'erp_prd_no'=>trim($v['erp_prd_no']),
                'spec_price'=>floatval($v['spec_price']),
                'spec_weight'=>floatval($v['spec_weight']),
                'estimate_weight'=>floatval($v['estimate_weight']),
                'exw_discount'=>floatval($v['exw_discount']),
            ];
        }
        $filename = '特价sku需要新增的商品_' . date('Y-m-d');
        $exportData = array_merge($title, $new_goods_list);
        $excel_obj = new ExcuteExcel();
        $excel_obj->exportZ($exportData, $filename);
    }

    /**
     * description:获取商品特殊折扣列表
     * editor:zongxing
     * type:GET
     * date : 2019.05.21
     * return Array
     */
    public function getGmcDiscountList(Request $request)
    {
        $param_info = $request->toArray();
        $gmc_model = new GmcDiscountModel();
        $gmc_discount_list = $gmc_model->gmcDiscountList(null, $param_info);
        if (empty($gmc_discount_list)) {
            return response()->json(['code' => '1002', 'msg' => '暂无商品特殊折扣信息']);
        }
        $return_info = ['code' => '1000', 'msg' => '获取商品特殊折扣列表成功', 'data' => $gmc_discount_list];
        return response()->json($return_info);
    }

    /**
     * description 导出商品最终报价
     * author zhangdong
     * date 2019.10.18
     */
    public function exportGoodsOffer(Request $request)
    {
        $reqParams = $request->toArray();
        ParamsCheckSingle::paramsCheck()->exportGoodsOfferParams($reqParams);
        $gsModel = new GoodsSpecModel();
        //获取导出数据
        $baseOfferData = Redis::get('baseOfferData');
        //$baseOfferData可能为'[]'字符串也可能为空字符串也可能为空，所以此处直接计算该键的长度
        if(strlen($baseOfferData) <= 3){
            $baseOfferData = $gsModel->goodsOfferData();
        }
        $arrOfferData = json_decode($baseOfferData);
        if(count($arrOfferData) <= 0){
            return response()->json(['code' => '2067', 'msg' => '当天商品报价数据还未生成,请先维护追加折扣']);
        }
        //开始导出数据
        $executeModel = new ExcuteExcel();
        return $executeModel->exportOfferData($arrOfferData, $reqParams);
    }


}//enf of class