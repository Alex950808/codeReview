<?php

namespace App\Api\Vone\Transformers;

/**该类为dingo api封装好**/
use League\Fractal\TransformerAbstract;
//create by zhangdong on the 2018.06.22
class TestsTransformer extends TransformerAbstract
{
    /***
     * 分开为了解耦
     * 数据字段选择
     * @param $lesson
     * @return array
     */
    public function transform($lesson)
    {
        /******隐藏数据库字段*****/
        return [
            'user_name' => $lesson['user_name'],
            'email' => $lesson['email'],
        ];
    }
}