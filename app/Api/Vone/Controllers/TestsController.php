<?php
namespace App\Api\Vone\Controllers;

use App\Model\Vone\AdminUserModel;
use App\Model\Vone\GoodsCodeModel;
use App\Model\Vone\GoodsModel;
use App\Model\Vone\planModel;
use App\Model\Vone\RealPurchaseModel;
use App\Model\Vone\SaleUserAccountModel;
use App\Model\Vone\SortDataModel;
use App\Model\Vone\UserSortGoodsModel;
use App\Model\Vone\GoodsSpecModel;
use App\Model\Vone\MisOrderSubModel;
use App\Model\Vone\MisOrderGoodsModel;
use App\Model\Vone\SpotGoodsModel;
use App\Model\Vone\DemandGoodsModel;
use App\Model\Vone\MisOrderSubGoodsModel;
use App\Model\Vone\ConversionStatisticsModel;
use App\Model\Vone\OrderInfoModel;
use App\Model\Vone\GoodsIntegralModel;
use App\Model\Vone\DepartSortGoodsModel;
use App\Model\Vone\SpotOrderModel;
use App\Model\Vone\MisOrderModel;
use App\Model\Vone\PurchaseChannelModel;
use App\Model\Vone\OrdNewGoodsModel;
use App\Model\Vone\DemandModel;
use App\Model\Design\SingleModel;
use App\Model\Vone\ExchangeRateModel;
use App\Model\Vone\SubPurchaseModel;
use App\Modules\Erp\ErpApi;
use App\Api\Vone\Transformers\TestsTransformer;
use App\Jobs\ProcessDelay;
use Dingo\Api\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use JWTAuth;

use Symfony\Component\HttpFoundation\Response;
use think\Queue;

//create by zhangdong on the 2018.06.22
class TestsController extends BaseController
{

    /*
     * @desc:测试
     * @author:zhangdong
     * @date:2019.01.02
     * */
    public function index()
    {
       /* $name='学院君';
        $flag= Mail::raw('你好，我是PHP程序！',function($message) {
            $to='495997793@qq.com';
            $message->to($to)->subject('纯文本信息邮件测试');
        });
        if(!$flag){
            echo '发送邮件成功，请查收！';
        }else{
            echo '发送邮件失败，请重试！';
        }
        die;*/
        $randNum = rand(0,10000);
        $job = (new ProcessDelay($randNum))
            ->delay(Carbon::now()->addSeconds(10));
        $this->dispatch($job);
        $returnMsg = [
            'orderStatistics' => 123,
        ];
        return response() ->json($returnMsg);


    }






    /*
     * @desc:测试
     * @author:zhangdong
     * @date:2019.01.02
     * */
    public function index_stop(Request $request)
    {
        $filePath = 'D:\wamp64\www\sjcj\test.php';
        $fileText = file_get_contents($filePath,'',null,770900,12800);
        $pattern = '#<img[^>]+>#';
        //去掉图片
        $html = preg_replace ($pattern , "" , $fileText);
        //去除js脚本
        $pattern = '/<script[^>]*?>.*?<\/script>/si';
        $html = preg_replace ($pattern , "" , $html);
        //读取商品名称
        $goodsName = strstr('h3',$html);
        echo($goodsName);die;
    }






















}//end of class
