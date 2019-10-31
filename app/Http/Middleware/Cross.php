<?php
namespace App\Http\Middleware;

use Closure;

class Cross {

    /**
     * 处理请求-解决跨域问题
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     * @author zhangdong
     * @date 2018.06.22
     */
    public function handle($request, Closure $next)
    {
        //检查接口是否被停用 create zhangdong 2019.05.20
        // move from api.php zhangdong 2019.08.21
        $apiName = getApiName();
        $arrStopApi = config('constants.STOP_API');
        if (in_array($apiName, $arrStopApi)) {
            return response() -> json(['code' => '2050', 'msg' => '该功能暂时停用']);
        }
        //设置允许来源头信息：Access-COntrol-Allow-Origin
        header("Access-Control-Allow-Origin: *");
        // 设置允许请求的方法
        $headers = [
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin, Authorization'
        ];

        if ($request->getMethod() == "OPTIONS") {
            // 对于预请求返回200
            return \Response::make('OK', 200, $headers);
        }
        $response = $next($request);
        foreach ($headers as $key => $value)
            $response->header($key, $value);
        return $response;
    }





}//end of class