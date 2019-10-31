<?php
/*
 * desc: 定时任务-更新采购期状态
 * author:zongxing
 * date:2018.08.09
 * */

//定时任务-更新采购期状态 begin
$url = 'http://120.76.27.42:86/api/purchase_task/countDelayTime';
$ch = curl_init();
curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// 如果需要将结果直接返回到变量里，那加上这句
curl_exec($ch);
echo 'cron-change_purchase_date-success';
//定时任务-更新采购期状态 end