<?php

namespace App\Http\Middleware;

use Closure;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //获取访问路由
        $path = $request->path();
        $str_pos = strrpos($path,'/')+1;
        $check_info = substr($path,$str_pos);

        //检查用户权限
        $loginUserInfo = $request->user();
        $e = $loginUserInfo->can($check_info);
        if (!$e){
            return response()->json(['error' => '1020', 'msg' => '您没有权限,请联系管理员']);
        }
        return $next($request);
    }
}
