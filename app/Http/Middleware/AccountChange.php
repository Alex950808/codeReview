<?php
//created by zhangdong on the 2018.06.22
namespace App\Http\Middleware;

use Closure;

class AccountChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     * @author zhangdong
     * @date 2018.06.22
     */
    public function handle($request, Closure $next)
    {
        config(['jwt.user' => '\App\Account']);    //重要用于指定特定model
        config(['auth.providers.users.model' => \App\Account::class]);//重要用于指定特定model！！！！
        return $next($request);
    }
}