<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use App\Modules\Erp\ErpApi;

//create by zhangdong on the 2018.11.14
class ErpController extends BaseController
{
    /**
     * description:erp-将商品推送到erp平台货品
     * editor:zhangdong
     * date : 2018.11.14
     */
    public function erpPlatformGoodsPush()
    {
        $erpModel = new ErpApi();
        $returnMsg = $erpModel->createPlatformGoods();
        return response() -> json($returnMsg);
    }

    /**
     * description:erp-库存同步
     * editor:zhangdong
     * date : 2018.12.03
     */
    public function sycGoodsStock()
    {
        $erpModel = new ErpApi();
        $returnMsg = $erpModel->sycGoodsStock();
        return response() -> json($returnMsg);
    }

    








}//end of class