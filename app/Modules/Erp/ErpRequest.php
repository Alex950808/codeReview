<?php
/*
 * desc:erp推送订单需要的一些方法
 * author:zhangdong
 * date:2017.10.23
ERP同步接口
---旺店通2.0接口测试环境配置信息--------
sid: apidev2
appkey: mifengpai2test
appsecret:  12345
平台id：127
测试shop_no：jihuojie
测试shop_name：jihuojie测试
测试warehouse_no:jihuojie
测试warehouse_name:jihuojie测试
shop_id:43
测试环境地址:http://121.41.177.115
测试账号：（同appkey）
密码：12345
服务器设置：
卖家账号：apidev2
服务器:erp.wangdian.cn
*/

/*---旺店通2.0接口正式环境配置信息-------- 
sid: mifengpai2
appkey: mifengpai2-gw
appsecret:  3ac8e617ffc01ae68a220dc86c617172
平台id：127
正式shop_no：07
正式shop_name：集货街
正式warehouse_no:jihuojie
正式warehouse_name:jihuojie测试
shop_id:44
正式环境地址:http://api.wangdian.cn
正式账号：（同appkey）
密码：12345
服务器设置：
卖家账号：apidev2
服务器:erp.wangdian.cn
*/
namespace App\Modules\Erp;

class ErpRequest
{

//系统及参数配置
    var $sid = 'mifengpai2';//sid
    var $appkey = 'mifengpai2-gw';//appkey
    var $appsecret = '3ac8e617ffc01ae68a220dc86c617172';//appsecret
    var $service_url = 'http://api.wangdian.cn/openapi2/';//请求地址
//店铺级参数配置
    var $shop_no = '07';//店铺编号-集货街（香港仓）
    var $shop_no_bw = '18';//店铺编号-集货街（保税仓）
    var $shop_name = '集货街';//店铺名称
    var $warehouse_no = '0010';//仓库编号
    var $warehouse_name = '集货街仓';//仓库名称
    var $shop_id = 8;//店铺id
    var $platform_id = 127;//平台类型ID, (1淘宝, 2淘宝分销, 3京东, 4拍拍, 5亚马逊, 6一号店, 7当当网, 8库巴, 9阿里巴巴, 10ECShop, 11麦考林, 12V+, 13苏宁, 14唯品会, 15易迅, 16聚美, 17口袋通, 18Hishop, 19微铺宝, 20美丽说, 21蘑菇街, 22贝贝网, 23Ecstroe, 127其它)

    /*
     * description：请求数据打包
     * author：zhangdong
     * date：2017.10.23
     */
    private function packData(&$req)
    {
        ksort($req);
        $arr = [];
        //print_r($req);exit;
        foreach ($req as $key => $val) {
            if ($key == 'sign') continue;
            if (count($arr))
                $arr[] = ';';
            $arr[] = sprintf("%02d", iconv_strlen($key, 'UTF-8'));
            $arr[] = '-';
            $arr[] = $key;
            $arr[] = ':';
            $arr[] = sprintf("%04d", iconv_strlen($val, 'UTF-8'));
            $arr[] = '-';
            $arr[] = $val;
        }
        //echo implode('', $arr);exit;
        return implode('', $arr);
    }//end of function


    /*
     * description：生成签名
     * author：zhangdong
     * date：2017.10.23
     */
    private function makeSign(&$req, $appsecret)
    {
        $sign = md5($this->packData($req) . $appsecret);
        $req['sign'] = $sign;
    }

    /*
     * description：访问请求地址，获取数据
     * author：zhangdong
     * date：2017.10.23
     */
    private function wdtOpenApi($req, $sid, $appkey, $appsecret, $url)
    {
        $this->makeSign($req, $appsecret);
        $o = "";
        foreach ($req as $k => $v) {
            $o .= "$k=" . $v . "&";
        }
        $post_data = substr($o, 0, -1);
        $res = $this->request_post($url, $post_data);
        return $res;
    }

    /*
     * description：模拟post进行url请求
     * author：zhangdong
     * date：2017.10.23
     */
    private function request_post($url = '', $param = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }
        $postUrl = $url;
        $curlPost = $param;
        $length = strlen($curlPost);
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
//        curl_setopt($ch, CURLOPT_TIMEOUT, 600);//请求超时时间
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);
        return $data;
    }

    /*
     * description：请求方法
     * author：zhangdong
     * date：2017.10.23
     */
    public function request_method($url, $postData = array())
    {
        $req = [
            //系统级参数
            'sid' => $this->sid,
            'appkey' => $this->appkey,
            'timestamp' => time(),
            'limit' => 1000,
        ];
        //应用级参数处理
        if (!empty($postData)) {
            foreach ($postData as $k => $v) {
                if (is_array($v)) {
                    $postData[$k] = json_encode($postData[$k], true);
                }
            }
            $post_add = $postData;
            $req = array_merge($req, $post_add);
        }
        //构造请求url
        $service_url = $this->service_url . $url;
        //模拟post请求获取数据
        $json = $this->wdtOpenApi($req, $this->sid, $this->appkey, $this->appsecret, $service_url);
        $result = json_decode($json, true);
        return $result;
    }

    /*
     * description：获取erp采购开单的订单信息
     * author：zongxing
     * date：2019.01.16
     */
    public function request_query_order($url, $postData = array())
    {
        $req = [
            //系统级参数
            'sid' => $this->sid,
            'appkey' => $this->appkey,
            'timestamp' => time(),
            'limit' => 1000,
        ];
        $req = array_merge($req, $postData);
        //构造请求url
        $service_url = $this->service_url . $url;
        //模拟post请求获取数据
        $json = $this->wdtOpenApi($req, $this->sid, $this->appkey, $this->appsecret, $service_url);
        $result = json_decode($json, true);
        return $result;
    }


}//end of class


?>