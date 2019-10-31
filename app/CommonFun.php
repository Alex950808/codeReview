<?php
//引入日志库文件 add by zhangdong on the 2018.06.28
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/*
 * description：对二维数组进行搜索，返回搜索到的值-精确搜索
 * author：zhangdong
 * date：2018.10.26
 * @param $arrData 要搜索的数组
 * @param $keyValue 要搜索的键值
 * @param $keyName 要搜索的键名
 * @return array
 */
function searchTwoArray($arrData, $keyValue, $keyName)
{
    $searchRes = [];
    foreach ($arrData as $key => $value) {
        if ($value[$keyName] == $keyValue) $searchRes[] = $arrData[$key];
    }
    return $searchRes;
}

/*
 * description：对二维数组进行排序
 * author：zhangdong
 * date：2018.10.26
 * @param $arrData 要排序的数组
 * @param $sortType 排序方式
 * @param $sortField 排序字段
 * @return mixed
 */
function sortTwoArray($arrData, $sortType, $sortField)
{
    $sort = [
        'direction' => $sortType, //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
        'field' => $sortField //排序字段
    ];
    $arrSort = [];
    foreach ($arrData AS $uniqueId => $row) {
        foreach ($row AS $key => $value) {
            $arrSort[$key][$uniqueId] = $value;
        }
    }
    if ($sort['direction']) {
        array_multisort($arrSort[$sort['field']], constant($sort['direction']), $arrData);
    }
    return $arrData;
}

/**
 * description:计算编号
 * editor:zongxing
 * date : 2018.06.26
 * params: 1.模型对象:$model_obj;2.需要更新的字段名:$model_field;3,拼接字符串头:$pin_head;4.是否带年月日:$status;
 * return Object
 */
function createNo($model_obj, $model_field, $pin_head, $status = true)
{
    $last_purchase_info = $model_obj->orderBy('create_time', 'desc')->first();
    $last_purchase_sn = $last_purchase_info["attributes"][$model_field];

    $pin_str = '001';
    if ($last_purchase_sn) {
        $last_three_str = substr($last_purchase_sn, '-3');
        $last_three_str_int = intval($last_three_str);
        $pin_int = $last_three_str_int + 1;
        if ($pin_int >= 100) {
            $pin_str = $pin_int;
        } else if ($pin_int >= 10) {
            $pin_str = '0' . $pin_int;
        } else {
            $pin_str = '00' . $pin_int;
        }
    }

    $now_date = '';
    if ($status) {
        $now_date = str_replace('-', '', date('Y-m-d', time()));
    }

    $return_sn = $pin_head . $now_date . $pin_str;
    return $return_sn;
}

/**
 * description:根据时间计算编号
 * editor:zongxing
 * date : 2018.07.05
 * params: 1.模型对象:$model_obj;2.需要更新的字段名:$model_field;3,拼接字符串头:$pin_head;4.是否检查日期:$status;
 * return Object
 */
function createNoByTime($model_obj, $model_field, $pin_head, $status = true)
{
    $last_purchase_info = $model_obj->orderBy('create_time', 'desc')->first();

    if (empty($last_purchase_info)) {
        $pin_str = '001';
    } else {
        if ($status) {
            $last_purchase_sn = $last_purchase_info["attributes"][$model_field];
            //最后一个时间
            $last_create_time = $last_purchase_info["attributes"]["create_time"];

            $last_day = substr($last_create_time, 0, 10);
            $now_day = date("Y-m-d", time());

            $pin_str = '001';
            if ($last_day == $now_day) {
                if ($last_purchase_sn) {
                    $last_three_str = substr($last_purchase_sn, '-3');
                    $last_three_str_int = intval($last_three_str);
                    $pin_int = $last_three_str_int + 1;
                    if ($pin_int >= 100) {
                        $pin_str = $pin_int;
                    } else if ($pin_int >= 10) {
                        $pin_str = '0' . $pin_int;
                    } else {
                        $pin_str = '00' . $pin_int;
                    }
                }
            }
        }
    }

    $return_sn = $pin_head . $pin_str;
    return $return_sn;
}

/**
 * @description:在二维数组中搜索，返回对应键名
 * @editor:张冬
 * @date : 2018.11.01
 * @param $arrData (数组)
 * @param $columnValue (键值)
 * @param $column (键名)
 * @return int
 */
function twoArraySearch($arrData, $columnValue, $column)
{
    $found_key = array_search($columnValue, array_column($arrData, $column));
    return $found_key;
}

/**
 * @description:组装批量更新sql语句-使用该方法时请事先打印处理结果，确定无误后再执行
 * @author:zhangdong
 * @date : 2018.11.08
 * @param $table (包含前缀的数据表名)
 * @param $multipleData (要更新的字段一一对应的数组)
 * @param $andWhere (并的查询条件)
 * @param $multiply (要做运算的字段 形似 SET a = a - b)
 * @return mixed
 */
function makeUpdateSql($table, $multipleData, $andWhere = [], $multiply = '')
{
    /*
     * @$multipleData 传参举例
     $multipleData = [
        'whereIn' => $whereIn,//要whereIn的字段和值
        'setData_a' => $a,//要set的第一个值
        'setData_b' => $b,//要set的第二个值，可以set同一个表中的多个字段，后面的以此类推
    ];*/
    if (empty($multipleData) || !is_array($multipleData)) {
        return false;
    }
    //如果$multiply参数不为空则必须保证$multipleData[0]中只能有两个元素，否则会导致不需要做运算的
    //字段也参与计算
    if ($multiply != '' && count($multipleData[0]) != 2) {
        return false;
    }
    //输出数组中的当前元素的值
    $firstRow = current($multipleData);
    $updateColumn = array_keys($firstRow);
    // 默认以id为条件更新，如果没有ID则以第一个字段为条件
    $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
    unset($updateColumn[0]);
    // 拼接sql语句
    $updateSql = "UPDATE " . $table . " SET ";
    $sets = [];
    $bindings = [];
    foreach ($updateColumn as $uColumn) {
        $setSql = "`" . $uColumn . "` = CASE ";
        if ($multiply != '') {
            $setSql = "`" . $uColumn . "` = `$multiply` - CASE ";
        }
        foreach ($multipleData as $data) {
            $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
            $bindings[] = $data[$referenceColumn];
            $bindings[] = $data[$uColumn];
        }
        $setSql .= "ELSE `" . $uColumn . "` END ";
        $sets[] = $setSql;
    }
    $updateSql .= implode(', ', $sets);
    $whereIn = collect($multipleData)->pluck($referenceColumn)->values()->all();
    $bindings = array_merge($bindings, $whereIn);
    $whereIn = rtrim(str_repeat('?,', count($whereIn)), ',');
    $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
    if (!empty($andWhere)) {
        foreach ($andWhere as $key => $item) {
            $updateSql .= ' AND `' . $key . '` = ?';
            $bindings[] = $item;
        }
    }
    // 传入预处理sql语句和对应绑定数据
    return [
        'updateSql' => $updateSql,
        'bindings' => $bindings,
    ];
} //end of makeUpdateSql


/**
 * description:日志记录公共方法
 * editor:zhangdong
 * date : 2018.11.16
 */
function logInfo($logName)
{
    $log = new Logger($logName);
    $strDate = date('Ymd');
    $storePath = storage_path('logs/' . $logName . '_' . $strDate . '.log');
    $stream = new StreamHandler($storePath);
    $log->pushHandler($stream, Logger::INFO);
    return $log;
}

/**
 * description:对象转为数组
 * editor:zhangdong
 * date : 2018.11.19
 */
function objectToArray($objectData)
{
    $arrData = [];
    foreach ($objectData as $key => $value) {
        $arrData[] = ((array)$value);
    }
    return $arrData;
}

/**
 * description:对象转为数组
 * editor:zongxing
 * date : 2018.11.20
 */
function objectToArrayZ($objectData)
{
    $arrData = json_decode(json_encode($objectData), true);
    return $arrData;
}

/**
 * description:实时汇率查询接口
 * editor:zongxing
 * * params: 1.$from:需要转换的货币简称;2.$to:转换后的货币简称;3.$amount:金额;
 * date : 2018.11.23
 */
function convertCurrency($from, $to, $amount = 1)
{
    $data = file_get_contents("http://www.baidu.com/s?wd={$from}%20{$to}&rsv_spt={$amount}");
    preg_match("/<div>1\D*=(\d*\.\d*)\D*<\/div>/", $data, $converted);
    $converted = preg_replace("/[^0-9.]/", "", $converted[1]);
    return number_format(round($converted, 3), 1);
}


/**
 * description:根据日期和随机字符串生成编号
 * editor:zongxing
 * date : 2018.12.15
 * params: 1.拼接字符串头:$pin_str;
 * return Object
 */
function makeRandNumber($pin_str)
{
    $date_time = Date('Ymd', time());
    $rand_num = rand(1000, 9999);
    $rand_number = $pin_str . $date_time . $rand_num;
    return $rand_number;
}

/*
 * description:异步返回函数-返回的是json
 * editor:zhangdong
 * date : 2018.12.15
 */
function jsonReturn($returnMsg)
{
    header('Content-Type:application/json; charset=utf-8');
    exit(json_encode($returnMsg, JSON_UNESCAPED_UNICODE));
}


/**
 * description:组装批量更新sql
 * editor:zongxing
 * date : 2018.12.15
 * params: 1.需要更新的表:$table;2.需要更新的数据:$batchData;3.需要判断的字段:$column;4.更新条件:$where;5.额外条件:$other_option
 * return Object
 */
function makeBatchUpdateSql($table, $batchData, $column, $where = [], $other_option = [])
{
    if (empty($batchData) || !is_array($batchData)) {
        return false;
    }
    $batch_sql = 'UPDATE ' . $table . ' SET ';
    $total_spec_sn = [];
    foreach ($batchData as $k => $v) {
        $batch_sql .= $k . ' = CASE ' . $column;
        foreach ($v as $k1 => $v1) {
            foreach ($v1 as $k2 => $v2) {
                if (!in_array($k2, $total_spec_sn)) {
                    $total_spec_sn [] = $k2;
                }
                $batch_sql .= ' WHEN \'' . $k2 . '\' THEN ' . $v2;
            }
        }
        $batch_sql .= ' END,';
    }
    $batch_sql = substr($batch_sql, 0, -1);
    $total_spec_sn = implode('\',\'', array_values($total_spec_sn));
    $batch_sql .= ' WHERE ' . $column . ' IN (\'' . $total_spec_sn . '\')';
    if ($other_option) {
        $other_column = $other_option['column'];
        $total_other_sn = implode('\',\'', array_values($other_option['data']));
        $batch_sql .= ' AND ' . $other_column . ' IN (\'' . $total_other_sn . '\')';
    }
    if ($where) {
        foreach ($where as $k => $v) {
            $batch_sql .= ' AND ' . $k . ' = \'' . $v . '\'';
        }
    }
    return $batch_sql;
}

/**
 * description:计算美金报价 = 美金原价 * 销售折扣
 * editor:zhangdong
 * date : 2018.12.17
 * @param $spec_price (美金原价)
 * @param $sale_discount (销售折扣)
 * @return double
 */
function calculateUserPrice($spec_price, $sale_discount)
{
    //计算美金报价 = 美金原价 * 销售折扣
    $userPrice = trim($spec_price) * trim($sale_discount);
    //将结果保留两位小数
    $userPrice = round($userPrice, DECIMAL_DIGIT);
    return $userPrice;
}


/**
 * description:二维数组模糊搜索
 * editor:zhangdong
 * date : 2019.01.17
 * notice:该函数是否使用请根据测试结果来定
 */
function twoArrayFuzzySearch($arrData, $field, $keywords)
{
    $result = [];
    if (empty($keywords)) {
        return [];
    }
    foreach ($arrData as $key => $v) {

        $searchData = $v[$field];
        if (strstr($searchData, $keywords) !== false) {
            $result[] = $arrData[$key];
        }
    }
    return $result;
}

/**
 * description：将某个小数转化成百分数
 * editor:zhangdong
 * date : 2019.01.23
 */
function toPercent($floatData)
{
    $percentData = sprintf('%.2f%%', $floatData * 100);
    return $percentData;
}

/**
 * description：筛选数组中重复的值并返回不重复的一维数组（只返回重复数据的值）
 * editor:zhangdong
 * date : 2019.01.29
 */
function filter_duplicate($array, $filter_field)
{
    //将有可能是对象的数据转为数组
    $arrayData = objectToArray($array);
    $result = [];
    $duplicateData = [];
    foreach ($arrayData as $key => $value) {
        if (empty($value[$filter_field])) {
            continue;
        }
        $has = false;
        foreach ($result as $val) {
            if ($val[$filter_field] == $value[$filter_field]) {
                $has = true;
                $duplicateData[] = $value[$filter_field];
                break;
            }
        }
        if (!$has) {
            $result[] = $value;
        }
    }
    return $duplicateData;
}

/**
 * description：将二维数组中的某个键组装成数组形式并返回
 * author:zhangdong
 * date : 2019.03.12
 * return array
 */
function makeArray($arrData, $keyName)
{
    //判断$arrData是否为数组
    if (!is_array($arrData) || count($arrData) == 0) {
        return [];
    }
    $arrKeyValue = [];
    foreach ($arrData as $value) {
        $strKeyValue = $value[$keyName];
        $arrKeyValue[] = $strKeyValue;
    }
    return $arrKeyValue;

}

/**
 * description:改变表格标题样式
 * editor:zongxing
 * date : 2018.06.28
 * params: 1.excel对象:$obj_excel;2.最后一列的名称:$column_last_name;
 * return Object
 */
function changeTableTitle($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
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

function changeTableContent($obj_excel, $column_first_name, $row_first_i, $column_last_name, $row_last_i)
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
 * description：截取字符串
 * author:zhangdong
 * date : 2019.03.23
 * params : $value 截取的字符串, $start 开始位置, $posStr 搜索字符, $skew 偏移量
 * return bool/string
 */
function cutString($value, $start, $posStr, $skew = 1)
{
    $operateRes = substr($value, $start, strrpos($value, $posStr) - $skew);
    return $operateRes;
}

/**
 * description：获取接口名称
 * author:zhangdong
 * date : 2019.04.02
 * return string
 */
function getApiName()
{
    //请求地址
    $redirectUrl = $_SERVER['REDIRECT_URL'];
    //接口名称
    $apiName = substr($redirectUrl, strrpos($redirectUrl, '/') + 1);
    return $apiName;
}


/**
 * description：从一维数组中获取重复的数据
 * author:zhangdong
 * date : 2019.05.06
 * return string
 */
function fetchRepeatMemberInArray(array $array = [])
{
    // 获取去掉重复数据的数组
    $unique_arr = array_unique($array);
    // 获取重复数据的数组
    $repeat_arr = array_diff_assoc($array, $unique_arr);
    return $repeat_arr;
}

/**
 * description：从二维数组中组装指定的字段为一维数组并返回
 * author:zhangdong
 * date : 2019.05.21
 * return array
 */
function getFieldArrayVaule(array $arrayData = [], $fieldName)
{
    $arrFieldValue = [];
    foreach ($arrayData as $value) {
        $fieldValue = trim($value[$fieldName]);
        $arrFieldValue[] = $fieldValue;
    }
    return array_unique(array_filter($arrFieldValue));
}


/**
 * description：获取去除别名的数据表名
 * author:zhangdong
 * date : 2019.05.30
 * params : $tableAsName 带有别名的数据表名 类似 table as t
 * return bool/string
 */
function getTableName($tableAsName)
{
    $tableName = substr($tableAsName, 0, strrpos($tableAsName, 'as') - 1);
    return $tableName;
}

/**
 * description：更新二维数组中某个键的值
 * author:zhangdong
 * date : 2019.06.01
 */
function updateTwoArrayValue($arrData, $searchKey, $searchKeyValue, $updateKey, $updateValue)
{
    foreach ($arrData as $key => $value) {
        //如果搜索键的值和搜索值相等则更新要更新键的值
        if ($value[$searchKey] == $searchKeyValue) {
            $arrData[$key][$updateKey] = $updateValue;
            //如果更新成功则直接结束循环（仅更新一条数据）
            break;
        }
    }
    return $arrData;

}

/**
 * description：对字符串按要求进行处理使其规范化
 * editor:zhangdong
 * date : 2019.06.28
 * @return string
 */
function ruleStr($str)
{
    //去空格
    $str = str_replace(' ', '', $str);
    //将中文逗号转为英文逗号
    $str = str_replace('，', ',', $str);
    return $str;
}

/**
 * description 聚合汇率请求方法
 * author zongxing
 * @param  string $url [请求的URL地址]
 * @param  string $params [请求的参数]
 * @param  int $ipost [是否采用POST形式]
 * @return  string
 */
function rateCurl($url, $params = false, $ispost = 0)
{
    $httpInfo = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'JuheData');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($ispost) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);
    } else {
        if ($params) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    }
    $response = curl_exec($ch);
    if ($response === false) {
        return $response;
    }
//    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//    $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
    curl_close($ch);
    return $response;
}


/*
 * description：对二维数组进行搜索，返回搜索到的值-精确搜索，
 * 搜到值后将该条记录从原数据中删除
 * author：zhangdong
 * date：2019.10.31
 * @param $arrData 要搜索的数组
 * @param $keyValue 要搜索的键值
 * @param $keyName 要搜索的键名
 * @return array
 */
function searchArray($arrData, $keyValue, $keyName)
{
    $searchRes = [];
    foreach ($arrData as $key => $value) {
        if ($value[$keyName] == $keyValue) {
            $searchRes[] = $arrData[$key];
            unset($arrData[$key]);
        }
    }
    return $searchRes;
}










