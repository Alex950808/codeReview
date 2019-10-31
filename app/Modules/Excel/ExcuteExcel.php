<?php
//created by zhangdong on the 2018.07.11
namespace App\Modules\Excel;

//引入表格操作类库-简版 add by zhangdong on the 2018.07.11
use Maatwebsite\Excel\Classes\PHPExcel;
use Maatwebsite\Excel\Facades\Excel;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ExcuteExcel
{

    /**
     * description:文件上传-基本通用验证
     * editor:zhangdong
     * date : 2018.07.11
     */
    public function verifyUploadFile($file, $fileName)
    {
        if (!isset($file['upload_file'])) {
            return ['code' => '2005', 'msg' => '上传文件不能为空'];
        }
        //检查表格名称
        $uploadName = $file['upload_file']['name'];
        $matchingRes = strrpos($uploadName, $fileName);
        if ($matchingRes === false) {
            $returnMsg = ['code' => '2007', 'msg' => '请选择本网站提供的模板进行导入'];
            return $returnMsg;
        }
        //检查表格文件格式
        $file_types = explode(".", $uploadName);
        $file_type = $file_types [count($file_types) - 1];
        if (strtolower($file_type) != 'xls' && strtolower($file_type) != 'xlsx') {
            $returnMsg = ['code' => '2008', 'msg' => '请上传xls或xlsx格式的Excel文件'];
            return $returnMsg;
        }
        $excel_file_path = $file['upload_file']['tmp_name'];
        $res = [];
        Excel::load($excel_file_path, function ($reader) use (&$res) {
            $reader = $reader->getSheet(0);
            $res = $reader->toArray();
        });
        //检查上传数据是否为空
        if (count($res) <= 1) {
            $returnMsg = ['code' => '2067', 'msg' => '上传数据不能为空'];
            return $returnMsg;
        }
        //检查列最大值-避免表格数据异常问题 2019.08.20 zhangdong
        if (count($res[0]) > 25) {
            $returnMsg = ['code' => '2067', 'msg' => '上传表格列数超过上限，请检查'];
            return $returnMsg;
        }

        return $res;
    }

    /**
     * description:导出表格
     * editor:zhangdong
     * date : 2018.07.12
     */
    public function export($exportData, $filename, $fileType = 'xls')
    {
        Excel::create($filename, function ($excel) use ($exportData) {
            $excel->sheet('sheet1', function ($sheet) use ($exportData) {
                $sheet->rows($exportData);
            });
        })->export($fileType);
    }

    /**
     * description:导出表格
     * editor:zongxing
     * date : 2018.12.29
     */
    public function exportZ($exportData, $filename, $fileType = 'xls')
    {
        Excel::create(iconv('UTF-8', 'GBK', $filename), function ($excel) use ($exportData) {
            $excel->sheet('sheet1', function ($sheet) use ($exportData) {
                $sheet->rows($exportData);
            });
        })->export($fileType);
    }


    /**
     * description:文件上传-基本通用验证
     * editor:zongxing
     * date : 2018.07.14
     */
    public function verifyUploadFileZ($file, $fileName)
    {
        //检查表格名称
        $uploadName = $file['upload_file']['name'];
        $matchingRes = strrpos($uploadName, $fileName);
        if ($matchingRes === false) {
            $returnMsg = ['code' => '1101', 'msg' => '请选择本网站提供的模板进行导入'];
            return $returnMsg;
        }
        //检查表格文件格式
        $file_types = explode(".", $uploadName);
        $file_type = $file_types [count($file_types) - 1];
        if (strtolower($file_type) != 'xls' && strtolower($file_type) != 'xlsx') {
            $returnMsg = [
                'code' => '1102',
                'msg' => '请上传xls或xlsx格式的Excel文件'
            ];
            return $returnMsg;
        }
        $excel_file_path = $file['upload_file']['tmp_name'];
        $res = [];
        Excel::load($excel_file_path, function ($reader) use (&$res) {
            $reader = $reader->getSheet(0);
            $res = $reader->toArray();
        });
        return $res;
    }


    /**
     * description:总单详情-导出总单信息
     * editor:zhangdong
     * date : 2019.01.02
     * @return
     */
    public function exportMisOrdData($data, $misOrderSn, $subGoodsInfo)
    {
        if (empty($data)) return false;
        $title[] = [
            '商品名称', '商家编码', '商品规格码', '最大供货数量',
            '美金原价', '销售折扣', '美金报价',
        ];
        $subGoodsInfo = objectToArray($subGoodsInfo);
        $goods_list = [];
        foreach ($data as $key => $value) {
            $spec_price = trim($value->spec_price);
            $sale_discount = trim($value->sale_discount);
            $user_price = calculateUserPrice($spec_price, $sale_discount);
            $spec_sn = trim($value->spec_sn);
            //查询子单中的商品信息
            $searchRes = searchTwoArray($subGoodsInfo, $spec_sn, 'spec_sn');
            $waitLockNum = $waitBuyNum = 0;
            if (count($searchRes) > 0) {
                $waitLockNum = intval($searchRes[0]['wait_lock_num']);
                $waitBuyNum = intval($searchRes[0]['wait_buy_num']);
            }
            //最大供货量 = 子单中待锁库数量 + 子单中预判采购数量
            $maxSupplyNum = $waitLockNum + $waitBuyNum;
            $goods_list[$key] = [
                trim($value->goods_name), trim($value->erp_merchant_no),
                trim($value->spec_sn), $maxSupplyNum,
                $spec_price, $sale_discount, $user_price
            ];
        }
        //子单号
        $filename = '总单导出_' . $misOrderSn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $this->export($exportData, $filename);

    }

    /**
     * description:导入表格文件字段验证
     * editor:zhangdong
     * date : 2019.01.08
     * params:$checkField (要检查的字段)
     * params:$fileField (上传文件中的字段)
     * @return
     */
    public function checkImportField($checkField, $fileField)
    {
        foreach ($checkField as $title) {
            if (!in_array(trim($title), $fileField)) {
                return false;
            }
        }
        return true;
    }

    /**
     * description:子单详情-导出订单-数据组装
     * notice:2019.01.22迁移至此
     * editor:zhangdong
     * date : 2018.12.17
     * @return bool
     */
    public function exportSpotOrdData($data)
    {
        if (empty($data)) return false;
        $title[] = [
            '商品名称', '商家编码', '商品规格码', '最大供货数量',
            '美金原价', '销售折扣', '美金报价'
        ];
        $sub_order_sn = trim($data[0]->sub_order_sn);
        $goods_list = [];
        foreach ($data as $key => $value) {
            $spec_price = trim($value->spec_price);
            $sale_discount = trim($value->sale_discount);
            $user_price = calculateUserPrice($spec_price, $sale_discount);
            $waitLockNum = intval($value->wait_lock_num);
            $waitBuyNum = intval($value->wait_buy_num);
            //最大供货量 = 待锁库数量 + 预判采购数量
            $maxSupplyNum = $waitLockNum + $waitBuyNum;
            $goods_list[$key] = [
                trim($value->goods_name), trim($value->erp_merchant_no),
                trim($value->spec_sn), $maxSupplyNum,
                $spec_price, $sale_discount, $user_price
            ];
        }
        //子单号
        $filename = '订单信息_' . $sub_order_sn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $this->export($exportData, $filename);
    }

    /**
     * description:商品模块-ERP商品列表
     * editor:zhangdong
     * date : 2019.01.25
     * @return bool
     */
    public function exportErpGoods($data)
    {
        if (empty($data)) return false;
        ini_set("memory_limit", "500M");
        set_time_limit(0);
        $title[] = [
            '商品名称', '货品编号', '货品简称', '商家编码', '条形码', '批发仓',
            '保税仓-集货街', '保税仓-零售', '香港-折痕仓', '品牌仓', '黑匣子', '共销仓',
            '资源仓', '集货街中转仓', '保税共销仓（欧美）', '香港-电商仓', '保税-日韩开架仓',
        ];
        $goods_list = [];
        foreach ($data as $key => $value) {
            $goods_list[] = [
                trim($value->goods_name), trim($value->goods_no), trim($value->goods_short_name),
                trim($value->spec_no), trim($value->barcode), intval($value->pf),
                intval($value->jhjbs), intval($value->bsls), intval($value->hkzh), intval($value->pp),
                intval($value->hxz), intval($value->gx), intval($value->zy), intval($value->jhjzz),
                intval($value->bsgx), intval($value->hkds), intval($value->bsrhkj)
            ];
        }
        //子单号
        $strDate = date('Ymd');
        $filename = 'ERP商品导出_' . $strDate;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $fileType = 'csv';
        $this->export($exportData, $filename, $fileType);

    }//end of function


    /**
     * description:保存上传文件到指定位置
     * editor:zhangdong
     * date : 2019.03.26
     * @return bool
     */
    public function saveUploadFile($file, $fileFlag)
    {
        //判断是否为上传的文件
        $temp_name = $file['upload_file']['tmp_name'];
        if (is_uploaded_file($temp_name) === false) {
            return false;
        }
        //以访问接口名称为依据创建保存上传文件的二级目录
        $redirectUrl = $_SERVER['REDIRECT_URL'];
        //接口名称
        $apiName = substr($redirectUrl,strrpos($redirectUrl,'/') + 1);
        //创建以接口名称命名的文件夹
        $target_name = $_SERVER['DOCUMENT_ROOT'] . "/uploadFile/$apiName";
        $mkdirRes = true;
        //检查目录是否存在
        if (is_dir($target_name) === false) {
            $mkdirRes = mkdir($target_name);
        };
        if ($mkdirRes === false) {
            return false;
        }
        $file_name = $file['upload_file']['name'];
        $file_types = explode(".", $file_name);
        $file_type = $file_types [count($file_types) - 1];
        $target_name = "$target_name/$fileFlag.$file_type";
        //将tmp文件移动到服务器指定位置
        if (move_uploaded_file($temp_name, $target_name)) {
            $target_name = '..' . substr($target_name, strpos($target_name, 'service')-1, -1);
            return $target_name;
        }
        return false;
    }

    /**
     * description:预判数据导出
     * author:zhangdong
     * date : 2019.04.24
     */
    public function exportAdvanceData($misOrderSn, $data)
    {
        if (empty($data)) return false;
        ini_set("memory_limit", "500M");
        set_time_limit(0);
        $title[] = [
            '商品名称','品牌名称','商品规格码','商家编码','平台条码',
            '参考代码','商品代码','美金原价','需求量','预判采购量','交付日期',
            '新的美金原价',
        ];
        $goods_list = [];
        foreach ($data as $key => $value) {
            $goods_list[] = [
                trim($value->goods_name), trim($value->brand_name), trim($value->spec_sn) . "\t",
                trim($value->erp_merchant_no) . "\t", trim($value->platform_barcode) . "\t",
                trim($value->erp_ref_no) . "\t",trim($value->erp_prd_no) . "\t",
                trim($value->spec_price), intval($value->goods_number),intval($value->wait_buy_num),
                trim($value->entrust_time),
            ];
        }
        //子单号
        $filename = '预判数量_' . $misOrderSn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $fileType = 'xls';
        $this->export($exportData, $filename, $fileType);

    }//end of function

    /**
     * @description:导入数据字段检查
     * @author:zhangdong
     * @date : 2019.04.25
     * @param $importTitle (导入的字段)
     * @param $needTitle (需要的字段)
     * @return mixed
     */
    public function checkTitle( array $importTitle = [], array $needTitle = [])
    {
        if (count($importTitle) == 0 || count($needTitle) == 0) {
            return ['code' => '2068', 'msg' => '非法访问'];;
        }
        foreach ($needTitle as $title) {
            if (!in_array(trim($title), $importTitle)) {
                return ['code' => '2009', 'msg' => '您的标题头有误，请按模板导入'];
            }
        }
        return true;

    }

    /**
     * description:总单导入-重组数据并检查导入数据是否有重复的spec_sn
     * author:zhangdong
     * date : 2019.05.06
     */
    public function checkImportData($res)
    {
        $subOrderGoods = $arrSpecSn = [];
        foreach ($res as $key => $value) {
            if ($key == 0) {
                 continue;
            }
            $spec_sn = isset($value[2]) ? trim($value[2]) : '';
            $arrSpecSn[] = $spec_sn;
            $subOrderGoods[$key]['spec_sn'] = $spec_sn;
            $subOrderGoods[$key]['goods_number'] = isset($value[3]) ? intval($value[3]) : 0;
            $subOrderGoods[$key]['sale_discount'] = isset($value[5]) ? floatval($value[5]) : 0;
        }
        //检查是否有重复的规格码
        $repeatData = fetchRepeatMemberInArray($arrSpecSn);
        return ['repeatData' => $repeatData, 'subOrderGoods' => $subOrderGoods];

    }

    /**
     * description:总单新品导出
     * author:zhangdong
     * date : 2019.04.24
     */
    public function exportOrdNew($misOrderSn, $data)
    {
        if (empty($data)) return false;
        ini_set("memory_limit", "500M");
        set_time_limit(0);
        $title[] = [
            '信息ID','品牌ID','品牌名称','商品名称','商家编码','平台条码',
            '美金原价','商品重量','商品预估重量','EXW折扣','参考代码','商品代码',
        ];
        $goods_list = [];
        foreach ($data as $key => $value) {
            $goods_list[] = [
                intval($value->id),intval($value->brand_id),trim($value->brand_name), trim($value->goods_name),
                trim($value->erp_merchant_no) . "\t",trim($value->platform_barcode) . "\t",
                floatval($value->spec_price),floatval($value->spec_weight),floatval($value->estimate_weight),
                floatval($value->exw_discount),trim($value->erp_ref_no) . "\t",trim($value->erp_prd_no) . "\t",
            ];
        }
        //子单号
        $filename = '总单新品_' . $misOrderSn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $fileType = 'xls';
        $this->export($exportData, $filename, $fileType);
    }//end of function

    /**
     * description:报价数据导出
     * author:zhangdong
     * date : 2019.06.12
     */
    public function exportOffer($misOrderSn, $exportData, $pickMarginRate)
    {
        if (empty($exportData)) return false;
        ini_set("memory_limit", "500M");
        set_time_limit(0);
        $title[] = [
            '品牌','商品规格码','平台条码','ERP条码','参考代码','商品代码','商品名称','需求数量',
            '交期','美金原价','货品简称','单重','重价比折扣','成本折扣','均值毛利' . $pickMarginRate . '%的折扣',
            '均值毛利'. $pickMarginRate .'%的供货价','第一次交付时间','第一次BD折扣','第一次BD美金',
            '第一次BD数量','第一次DD折扣','第一次DD美金','第二次交付时间','第二次BD折扣',
            '第二次BD美金','第二次BD数量','第二次DD折扣',
            '第二次DD美金','第二次DD数量','ERP现货库存','采购预判数量',
            '是否为套装拆单','交货方式',
        ];
        $goods_list = [];
        foreach ($exportData as $key => $value) {
            $goodsCode = trim($value->goodsCode);
            if (!empty($value->platform_barcode)) {
                $goodsCode = trim($value->platform_barcode);
            }
            $goods_list[] = [
                trim($value->brand_name),trim($value->spec_sn) . "\t",$goodsCode . "\t",
                trim($value->erp_merchant_no) . "\t",trim($value->erp_ref_no) . "\t",trim($value->erp_prd_no) . "\t",
                trim($value->goods_name),intval($value->goods_number),trim($value->entrust_time) . "\t",
                floatval($value->spec_price),trim($value->erp_ref_no),floatval($value->spec_weight),
                floatval($value->hprDiscount),floatval($value->costDiscount), floatval($value->pmrDiscount),
                floatval($value->pmrdPrice),trim($value->firstTime),floatval($value->firstBdSaleDiscount),
                floatval($value->firstBdSpecPrice),intval($value->firstBdNum),floatval($value->firstDdSaleDiscount),
                floatval($value->firstDdSpecPrice),trim($value->secondTime),
                floatval($value->secondBdSaleDiscount),floatval($value->secondBdSpecPrice),
                intval($value->secondBdNum),floatval($value->secondDdSaleDiscount),
                floatval($value->secondDdSpecPrice),intval($value->secondDdNum),intval($value->gStockNum),
                intval($value->wait_buy_num),
            ];
        }
        //子单号
        $filename = '商品报价_' . $misOrderSn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $fileType = 'xls';
        $this->export($exportData, $filename, $fileType);

    }

    /**
     * description:BD总单信息导出
     * author:zhangdong
     * date : 2019.06.13
     */
    public function exportMisOrder($misOrderSn, $data)
    {
        if (empty($data)) return false;
        ini_set("memory_limit", "500M");
        set_time_limit(0);
        $title[] = [
            '商品规格码','平台条码','ERP条码','商品名称','供货数量','供货美金','交期','交货方式',
        ];
        $goods_list = [];
        foreach ($data as $key => $value) {
            $goodsCode = trim($value->platform_barcode);
            if(empty($goodsCode)){
                $goodsCode = trim($value->goodsCode);
            }
            $goods_list[] = [
                trim($value->spec_sn) . "\t",$goodsCode . "\t",trim($value->erp_merchant_no) . "\t",
                trim($value->goods_name),intval($value->wait_buy_num),
                floatval($value->spec_price),trim($value->entrust_time) . "\t",
            ];
        }
        $filename = '总单导出_' . $misOrderSn;
        $exportData = array_merge($title, $goods_list);
        //数据导出
        $fileType = 'xls';
        $this->export($exportData, $filename, $fileType);
    }//end of function

    /**
     * description 商品报价导出
     * author zhangdong
     * date 2019.10.22
     */
    public function exportOfferData(array $arrOfferData = [], $reqParams)
    {
        $exportType = trim($reqParams['exportType']);
        ini_set("memory_limit", "500M");
        set_time_limit(0);
        //根据导出文件类型选定导出标题头
        $arrTitle = $this->getOfferTitle($exportType);
        //根据导出文件类型组装计算导出数据
        $offerData = $this->getOfferData($exportType, $arrOfferData, $reqParams);
        //导出文件名称
        $curDay = date('Ymd');
        $filename = $curDay . '_' .$arrTitle['fileName'];
        unset($arrTitle['fileName']);
        $exportData = array_merge($arrTitle, $offerData);
        //数据导出
        $fileType = 'xls';
        $this->exportSkuOffer($exportData, $filename, $fileType);
        return true;
    }//end of function




    /**
     * description 商品最终报价导出-根据导出文件类型选定导出标题头
     * author zhangdong
     * date 2019.10.22
     */
    private function getOfferTitle($exportType)
    {
        //公用信息
        $commonTitle = [
            '品牌','乐天商品代码','乐天参考代码','商品条码','商品名称','规格','美金原价','乐天4档标准折扣率',
            '乐天高价SKU差率','乐天特价SKU','每日最终折扣率',
        ];
        switch ($exportType) {
            //特价活动报价（乐天4档EMS）美金
            case 'EMS_DOLLAR':
                $title = [
                    '产品单重(kg)','产品运费','4档最终到港折扣率','4档最终到港美金价',
                    '客户申请数量','实际审批数量','合计美金','乐天官网美金汇率','人民币货值',
                    '出境日','预计到港日','EMS运费/每KG',
                ];
                $returnMsg = [
                    'fileName' => '特价活动报价（乐天4档EMS）美金',
                    'title' => array_merge($commonTitle,$title),
                ];
                break;
            //特价活动报价（乐天4档EMS）人民币
            case 'EMS_RMB':
                $title = [
                    '乐天官网美金/韩币汇率','人民币兑韩币汇率','人民币付款核算后美金汇率',
                    '产品单重(kg)','产品运费','4档最终到港折扣率','4档最终到港人民币价',
                    '客户申请数量','实际审批数量','合计人民币','出境日','预计到港日',
                    'EMS运费/每KG',
                ];
                $returnMsg = [
                    'fileName' => '特价活动报价（乐天4档EMS）人民币',
                    'title' => array_merge($commonTitle,$title),
                ];
                break;
            //特价活动报价（乐天4档机场交货）美金
            case 'AIRPORT_DOLLAR':
                $title = [
                    '4档最终机场交货折扣率','4档最终机场交货美金价','客户申请数量',
                    '实际审批数量','合计美金','乐天官网美金汇率','人民币货值','出境日',
                    '预计到港日',
                ];
                $returnMsg = [
                    'fileName' => '特价活动报价（乐天4档机场交货）美金',
                    'title' => array_merge($commonTitle,$title),
                ];
                break;
            //特价活动报价（乐天4档机场交货）人民币
            case 'AIRPORT_RMB':
                $title = [
                    '乐天官网美金/韩币汇率','人民币兑韩币汇率','人民币付款核算后美金汇率',
                    '4档最终机场交货折扣率','4档最终机场交货人民币价','客户申请数量',
                    '实际审批数量','合计人民币','出境日','预计到港日',
                ];
                $returnMsg = [
                    'fileName' => '特价活动报价（乐天4档机场交货）人民币',
                    'title' => array_merge($commonTitle,$title),
                ];
                break;
            default:
                $returnMsg = [
                    'fileName' => '商品报价公共信息',
                    'title' => $commonTitle
                ];
        }
        return $returnMsg;
    }

    /**
     * description 商品最终报价导出-根据导出文件类型计算导出数据
     * author zhangdong
     * date 2019.10.22
     */
    private function getOfferData($exportType, $arrOfferData, $reqParams)
    {
        $lastOfferData = [];
        foreach ($arrOfferData as $key => $value) {
            $goodsName = strlen($value->ext_name) > 0 ? trim($value->ext_name) : trim($value->goods_name);
            //品牌成本折扣
            $dt_discount = $value->dt_discount > 0 ? $value->dt_discount : 0;
            //高价sku返点
            $gd_discount = $value->gd_discount > 0 ? $value->gd_discount : 0;
            $append_discount = $value->appendDiscount;
            //乐天4档标准折扣率
            $standard_discount = $dt_discount > 0 ? $dt_discount : '';
            //乐天高价SKU差率
            $diff_discount = '';
            if($gd_discount > 0){
                $diff_discount = 1 - $gd_discount - $dt_discount;
            }
            //乐天特价SKU
            $special_discount = $append_discount > 0 ? $append_discount : '';
            //成本折扣:如果有高价sku折扣则用该折扣减去追加返点，否则用品牌成本折扣减去追加返点
            $costDiscount = $gd_discount > 0 ? 1-$gd_discount : $dt_discount;
            //每日最终折扣率 = 成本折扣-追加折扣
            //追加折扣=品牌追加折扣(新罗和爱宝客有)+月度低价SKU折扣(只有新罗有)+日低价SKU折扣(只有乐天有)
            $lastDiscountDiff = $costDiscount - $append_discount;
            //计算其他信息时用
            $value->lastDiscountDiff = $lastDiscountDiff;
            $lastDiscount = $lastDiscountDiff > 0 ? $lastDiscountDiff : '';
            $commonData = [
                trim($value->brand_name),trim($value->erp_prd_no),trim($value->erp_ref_no),
                trim($value->erp_merchant_no),$goodsName,' ',floatval($value->spec_price),
                $standard_discount,$diff_discount,$special_discount,$lastDiscount
            ];
            //根据不同导出文件组装不同信息
            $specialData = $this->makeSpecialData($exportType, $reqParams, $value);
            //将共有信息和特有信息合并然后将最后处理好的报价数据放入最终数组中
            $lastOfferData[] = array_merge($commonData,$specialData);
        }
        return $lastOfferData;
    }


    /**
     * description 商品最终报价导出-根据不同导出文件组装不同信息
     * author zhangdong
     * date 2019.10.23
     */
    private function makeSpecialData($exportType, $reqParams, $value)
    {
        //运费系数_元/千克
        $shipCost = 3;
        $rmbRate = floatval($reqParams['rmbRate']);
        $koreanRate = floatval($reqParams['koreanRate']);
        $rmbKoreanRate = floatval($reqParams['rmbKoreanRate']);
        //出境日
        $exitDate = trim($reqParams['exitDate']);
        //预计到港日
        $predictDate = trim($reqParams['predictDate']);
        //每日最终折扣
        $lastDiscount = $value->lastDiscountDiff > 0 ? $value->lastDiscountDiff : 0;
        $specWeight = $value->spec_weight;
        $specPrice = $value->spec_price;
        //运费
        $shipFee = $specWeight*$shipCost;
        $dollarPrice = $lastDiscount * $specPrice;
        //人民币付款核算后美金汇率
        $dollarRate = $koreanRate/$rmbKoreanRate;
        switch ($exportType) {
           //特价活动报价（乐天4档EMS）美金
           case 'EMS_DOLLAR':
               //注：此处仅EMS的加运费，机场的不加，所以将这个地方针对性地处理
               $lastDollar = $portDiscount = 'NULL';
               //如果每日最终折扣为0则到港美金价和折扣率设为NULL
               if ($lastDiscount > 0) {
                   //四档最终到港美金价 = 每日最终折扣*美金原价 + 运费
                   $lastDollar = $dollarPrice + $shipFee;
                   //4档最终到港折扣率
                   $portDiscount = $specPrice > 0 ? toPercent($lastDollar/$specPrice) : 'NULL';
               }
               $specialData = [
                   $specWeight,$shipFee,$portDiscount,$lastDollar,
                   '','','',$rmbRate,$lastDollar*$rmbRate,$exitDate,$predictDate,''
               ];
               break;
           //特价活动报价（乐天4档EMS）人民币
           case 'EMS_RMB':
               //注：此处仅EMS的加运费，机场的不加，所以将这个地方针对性地处理
               $portDiscount = $portRmb = 'NULL';
               //如果每日最终折扣为0则到港美金价和折扣率设为NULL
               if ($lastDiscount > 0) {
                   //四档最终到港美金价 = 每日最终折扣*美金原价 + 运费
                   $lastDollar = $dollarPrice + $shipFee;
                   //4档最终到港折扣率
                   $portDiscount = $specPrice > 0 ? toPercent($lastDollar/$specPrice) : 'NULL';
                   //4档最终到港人民币价
                   $portRmb = $lastDollar*$dollarRate;
               }
               $specialData = [
                   $koreanRate,$rmbKoreanRate,$dollarRate,$specWeight,$shipFee,$portDiscount,
                   $portRmb,'','','',$exitDate,$predictDate,''
               ];
               break;
           //特价活动报价（乐天4档机场交货）美金
           case 'AIRPORT_DOLLAR':
               $portDiscount = $airPortDollar = 'NULL';
               if ($lastDiscount > 0) {
                   //4档最终机场交货折扣率 = 每日最终折扣*美金原价/美金原价 = 每日最终折扣
                   $portDiscount = $specPrice > 0 ? toPercent($dollarPrice/$specPrice) : 'NULL';
                   //4档最终机场交货美金价
                   $airPortDollar = $dollarPrice;
               }
               $specialData = [
                   $portDiscount,$airPortDollar,'','','',$rmbRate,$dollarPrice*$rmbRate,
                   $exitDate,$predictDate
               ];
               break;
           //特价活动报价（乐天4档机场交货）人民币
           case 'AIRPORT_RMB':
               $portDiscount = $airPortRmb = 'NULL';
               if ($lastDiscount > 0) {
                   //4档最终机场交货折扣率 = 每日最终折扣*美金原价/美金原价 = 每日最终折扣
                   $portDiscount = $specPrice > 0 ? toPercent($dollarPrice/$specPrice) : 'NULL';
                   //4档最终机场交货美金价
                   $airPortRmb = $dollarPrice*$dollarRate;
               }
               $specialData = [
                   $koreanRate,$rmbKoreanRate,$dollarRate,$portDiscount,
                   $airPortRmb,'','','',$exitDate,$predictDate
               ];
               break;
           default:
               $specialData = [];
       }
       return $specialData;

    }

    /**
     * description:导出表格
     * editor:zhangdong
     * date : 2018.07.12
     */
    public function exportSkuOffer($exportData, $filename, $fileType = 'xls')
    {
        Excel::create($filename, function ($excel) use ($exportData) {
            $excel->sheet('sheet1', function ($sheet) use ($exportData) {
                $sheet->setColumnFormat([
                    'B' => NumberFormat::FORMAT_TEXT,
                    'C' => NumberFormat::FORMAT_TEXT,
                    'D' => NumberFormat::FORMAT_TEXT,
                    'G' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
                    'H' => NumberFormat::FORMAT_PERCENTAGE_00,
                    'I' => NumberFormat::FORMAT_PERCENTAGE_00,
                    'J' => NumberFormat::FORMAT_PERCENTAGE_00,
                    'K' => NumberFormat::FORMAT_PERCENTAGE_00,
                ]);
                $sheet->rows($exportData);
            });
        })->export($fileType);
    }




}//end of class
