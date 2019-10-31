<?php
namespace App\Api\Vone\Controllers\Sell;

use App\Api\Vone\Controllers\BaseController;
use Dingo\Api\Http\Request;

//引入商品模型 add by zhangdong on the 2018.07.05
use App\Model\Vone\GoodsModel;

//create by zhangdong on the 2018.06.22
class GoodSaleController extends BaseController
{
   /**
     * description : 销售数据管理-商品实时动销率
     * editor : zhangdong
     * date : 2018.07.05
     */
    public function goodsRtMovePercent(Request $request)
    {
        $reqParams = $request -> toArray();
        $keywords = array_key_exists('keywords',$reqParams) && !empty($reqParams['keywords']) ?
                    trim($reqParams['keywords']) : '';
        //获取列表数据
        $goodsModel = new GoodsModel();
        $params['keywords'] = $keywords;
        //组装商品实时动销率数据
        $goodsMovePer = $goodsModel -> createGoodsMovePer($params);
        $returnMsg = [
            'goodsMovePer' => $goodsMovePer,
        ];
        return response() ->json($returnMsg);

    }



}