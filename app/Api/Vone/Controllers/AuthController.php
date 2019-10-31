<?php

namespace App\Api\Vone\Controllers;

use App\Model\Vone\AdminUserModel;
use App\Model\Vone\DepartmentModel;
use App\Model\Vone\Permission;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

//认证模块分为登录、注册、获取用户信息 create by zhangdong on the 2018.06.22
class AuthController extends BaseController
{
    /**
     * The authentication guard that should be used.
     *
     * @var string
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * description:用户登录
     * editor:zongxing
     * date : 2018.06.23
     * params: 1.用户名:user_name;2.密码:password
     * return Object
     */
    public function authenticate(Request $request)
    {
        $payload = [
            'user_name' => $request->get('user_name'),
            'password' => $request->get('password'),
            'status' => 1
        ];

        try {
            if (empty($payload["user_name"])) {
                return response()->json(['error' => '1001', 'msg' => '用户名不能为空']);
            } else if (empty($payload["password"])) {
                return response()->json(['error' => '1002', 'msg' => '密码不能为空']);
            }

            if (!$token = JWTAuth::attempt($payload)) {
                return response()->json(['error' => '1003', 'msg' => '用户名或密码错误']);
                //return response()->json(['error' => 'token_not_provided'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => '不能创建token'], 500);
        }

        //更新IP
        $admin_user = new AdminUserModel();
        $admin_user->upload_login_ip($payload["user_name"]);

        //获取权限
        $loginUserInfo = $request->user();
        $permissionModel = new Permission();
        $user_permission_info = $permissionModel->get_user_permission($loginUserInfo);

        //获取部门列表
        $department_id = $loginUserInfo->department_id;
        $department_model = new DepartmentModel();
        $department_info = $department_model->getDepartmentInfo($department_id);

        $loginUserInfo->department_name = $department_info[0]['de_name'];
        $user_info = $loginUserInfo;
        //$user_info['department_name'] = $department_info[0]['de_name'];
        $code = "1000";
        $msg = "登陆成功";
        $return_info = compact('code', 'msg', 'token', 'user_permission_info', 'user_info');
        return response()->json($return_info);
    }
    /**
     * description:app登录
     * editor:zongxing
     * date : 2019.08.08
     * params: 1.用户名:user_name;2.密码:password
     * return Object
     */
    public function AuthLogin(Request $request)
    {
        $payload = [
            'user_name' => $request->get('user_name'),
            'password' => $request->get('password'),
            'status' => 1
        ];

        try {
            if (empty($payload["user_name"])) {
                return response()->json(['error' => '1001', 'msg' => '用户名不能为空']);
            } else if (empty($payload["password"])) {
                return response()->json(['error' => '1002', 'msg' => '密码不能为空']);
            }

            if (!$token = JWTAuth::attempt($payload)) {
                return response()->json(['error' => '1003', 'msg' => '用户名或密码错误']);
                //return response()->json(['error' => 'token_not_provided'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => '不能创建token'], 500);
        }

        //更新IP
        $admin_user = new AdminUserModel();
        $admin_user->upload_login_ip($payload["user_name"]);

        //获取用户信息
        $loginUserInfo = $request->user();

        //获取部门列表
        $department_id = $loginUserInfo->department_id;
        $department_model = new DepartmentModel();
        $department_info = $department_model->getDepartmentInfo($department_id);
        $loginUserInfo->department_name = $department_info[0]['de_name'];
        $user_info = [
            'user_name'=>$loginUserInfo->user_name,
            'nickname'=>$loginUserInfo->nickname,
            'department_name'=>$loginUserInfo->department_name,
        ];
        $code = "1000";
        $msg = "登陆成功";
        $return_info = compact('code', 'msg', 'token', 'user_permission_info', 'user_info');
        return response()->json($return_info);
    }

    public function messages()
    {
        return [
            'user_name.required' => 'A user_name is required',
            'password.required' => 'A password is required',
        ];
    }

    /**
     * description:用户登录
     * editor:zongxing
     * date : 2018.06.23
     * params: 1.用户名:user_name;2.密码:password;3,邮箱:email
     * return Object
     */
    public function register(Request $request)
    {
        $newUser = [
            'user_name' => $request->get('user_name'),
            'password' => bcrypt($request->get('password'))
        ];

        //add by zongxing 06.23
        if (empty($newUser["user_name"])) {
            return response()->json(['error' => '1001', 'msg' => '用户名不能为空']);
        } else if (empty($newUser["password"])) {
            return response()->json(['error' => '1002', 'msg' => '密码不能为空']);
        }

        $user = AdminUserModel::create($newUser);
        $token = JWTAuth::fromUser($user);

        $code = "1000";
        $msg = "注册成功";
        $return_info = compact('code', 'msg', 'token');

        return response()->json($return_info);
    }

    /****
     * 获取用户的信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function AuthenticatedUser()
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['code' => '1001', 'msg' => '未找到该用户'], 404);
                //return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], $e->getStatusCode());
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], $e->getStatusCode());
        } catch (JWTException $e) {
            return response()->json(['token_absent'], $e->getStatusCode());
        }
        // the token is valid and we have found the user via the sub claim
        return response()->json(compact('user'));
    }


}  