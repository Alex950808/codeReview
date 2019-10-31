<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

//引入日志库文件 zhangdong 2019.09.25
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ProcessDelay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $log = new Logger('queue');
        $log->pushHandler(new StreamHandler(storage_path('logs/queue/queue.log'), Logger::INFO));
        $log->addInfo("队列测试,随机数" . $this->user);

    }
}
