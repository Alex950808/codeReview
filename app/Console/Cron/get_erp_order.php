<?php
/*
 * desc: erp商品库存同步-18号店铺-集货街（保税仓）
 * author:zhangdong
 * date:2018.05.15
 * */
//定时任务-获取erp订单信息 begin
$url = 'http://120.76.27.42:84/api/order/getErpOrderData';
$ch = curl_init();
curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// 如果需要将结果直接返回到变量里，那加上这句
curl_exec($ch);
echo 'cron-get_erp_order-task-excute-success';
//定时任务-获取erp订单信息 end