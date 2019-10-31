<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Event;

//引入日志库文件 add by zhangdong on the 2018.06.28
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

//系统sql运行日志监听 add by zhangdong on the 2018.10.25
class QueryListener
{
    /*
     * Handle the event.
     * author:zhangdong
     * date:2018.10.25
     * @param  QueryExecuted $event
     * @return void
     */
    public function handle($event)
    {
        $log = new Logger('sqlRecord');
        $log->pushHandler(new StreamHandler(storage_path('logs/sqlRecord.log'), Logger::INFO));
        $sql = str_replace("?", "'%s'", $event->sql);
        $sqlStr = vsprintf($sql, $event->bindings);
        $log->addInfo($sqlStr);
    }


}//end of class
