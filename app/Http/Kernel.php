<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        // \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        //将空字符串转为null，在前端传参为空时可以过滤该参数使其不参与查询或者其他业务 zhangdong 2019.08.22
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        //注册中间件-解决跨域问题 add by zhangdong on the 2018.06.22
        \App\Http\Middleware\Cross::class
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            // \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            //显式和隐式地根据请求参数绑定对应数据模型 注释于 2019.08.21 zhangdong
            // \Illuminate\Routing\Middleware\SubstituteBindings::class,

        ],

        'api' => [
            'throttle:60,1',
            'bindings',
        ],

        'jwt_and_permission' => [
            'jwt.auth' => \Tymon\JWTAuth\Middleware\GetUserFromToken::class,
            'checkPermission' => \App\Http\Middleware\CheckPermission::class,
        ]
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        //将路由参数转化为特定对象的组件 注释于 2019.08.22 zhangdong
        //'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        //身份验证通过则跳转  注释于 2019.08.21 zhangdong
        //'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        //访问频次限制  注释于 2019.08.21 zhangdong
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,

        //add by zongxing 06.23
        'admin.user' => \App\Http\Middleware\AdminUser::class,

        'jwt.auth' => \Tymon\JWTAuth\Middleware\GetUserFromToken::class,
        'jwt.refresh' => \Tymon\JWTAuth\Middleware\RefreshToken::class,
        //注册中间件-解决跨域问题 add by zhangdong on the 2018.06.22
        'cors' => \App\Http\Middleware\Cross::class,
        //entrustRole中间件注册 add by zhangdong on the 2018.07.18
        'role' => \Zizaco\Entrust\Middleware\EntrustRole::class,
        'permission' => \Zizaco\Entrust\Middleware\EntrustPermission::class,
        'ability' => \Zizaco\Entrust\Middleware\EntrustAbility::class,

        //add by zongxing 08.06
        'checkPermission' => \App\Http\Middleware\CheckPermission::class,


    ];
}
