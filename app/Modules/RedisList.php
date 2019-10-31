<?php

namespace App\Modules;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

//create zhangdong 2019.10.29
//引用网址： https://learnku.com/articles/32548
class RedisList{

    // 添加一条数据到redis中
    public function zAdd($key,array $value)
    {
        return Redis::zadd($key,$value);
    }

    // 从Redis返回一条数据
    public function zRange($key,$start,$end,$withScores)
    {
        return  Redis::zRange($key,$start,$end,$withScores);
    }

    // Redis 返回一条数据从大小返回数据
    public function zrevRange ($key,$start,$end,$withScores)
    {
        return  Redis::ZREVRANGE($key,$start,$end,$withScores);
    }

    // Redis 返回某个数据 按分数正序排序 //ZRANGEBYSCORE
    public function  zRangByScore($key,$start,$end,$withScores)
    {
        return Redis::ZRANGEBYSCORE($key,$start,$end,$withScores);
    }

    // Redis 返回数据按分数倒叙排序 // ZREVRANGEBYSCORE
    public function zRevRangBySore($key,$start,$end,$withScores)
    {
        return Redis::ZREVRANGEBYSCORE($key,$start,$end,$withScores);
    }

    // 删除某个成员的值
    public function zRem($key,$member)
    {
        return Redis::zRem($key,$member);
    }

    // 返回zset集合中所有元素的个数
    public function zSize($key)
    {
        return Redis::zcard($key);
    }

    // 返回比元素$member分数大的元素的个数
    public function zRevRank($key,$member)
    {
        return Redis::zRevRank($key,$member);
    }

    // 如果在名称为key的zset中已经存在元素member，则该元素的score增加increment；否则向集合中添加该元素，其score的值为increment
    public function zIncrBy($key,$increment,$member)
    {
        return Redis::zIncrBy($key,$increment,$member);
    }

    // 求有序集合的并集
    public function zUnion($output,$zsetkey)
    {
        return Redis::zUnion($output,$zsetkey);
    }

    //求有序集合的交集
    public function zInter($output,$zsetkey)
    {
        return Redis::zInter($output,$zsetkey);
    }

    // redis 分页处理
    public function limitAndPage($pageIndex,$pageNum)
    {
        return array('pageIndex'=>$pageIndex-1,'pageNumber'=>$pageIndex*$pageNum-1);
    }

    // redis 队列操作 入队列
    public function lPush($key,$value)
    {
        return Redis::LPUSH($key,$value);
    }

    // redis 根据索引取数据
    public function lIndex($key,$position)
    {
        return Redis::lIndex($key,$position);
    }

    // redis list维护一个长度为100000的队列
    public function lTrim($key,$start=0,$stop=100000)
    {
        return Redis::lTrim($key,$start,$stop);
    }

    // 设置key的过期时间 现在设置的是一个月的时间 做二级缓存使用
    public function expire($key,$timesec =0)
    {
        if($timesec == 0){
            $expire_time = 0<$timesec? $timesec: strtotime(date('Y-m-d')) + 2592000 - time();
        }else{
            $expire_time = $timesec;
        }
        return Redis::expire($key,$expire_time);
    }

    // 设置zset的长度
    public function SetZsetTime($key,$data,$lenth =4)
    {
        if($this->zSize($key)>$lenth){
            Redis::zremrangebyrank($key,0,count($data)-1);
        }
    }

    public function getListInfoByCondition($key,$start,$end,array $limit,$sort = 'asc')
    {
        if($sort == 'desc'){
            $pageNum = $limit['limit'][0]*$limit['limit'][1];
            $limit['limit'][0] = $this->zSize($key)-$pageNum;
            // 本方法只使用于app分页 上一页 下一页 不支持跳页查询
        }
        return $sort == 'desc' ?
            array_reverse($this->zRangByScore($key,$start,$end,$limit)):
            $this->zRangByScore($key,$start,$end,$limit);
    }

    // 添加一条数据
    public function addOneInfo($key,array $data)
    {
        if(Redis::exists($key)){
            if(count($data)>4){
                return false;
            }
            $ret = $this->zAdd($key,$data);
            $this->SetZsetTime($key,$data);
            return $ret;
        }else{
            $this->expire($key,60);
            return $this->zAdd($key,$data);
        }
    }

    // 更新一条数据
    public function updateOneById($key,$id,$data)
    {
        $this->deleteOneById($key,$id);
        return $this->zAdd($key,$data);
    }

    // 删除一条数据
    public function deleteOneById($key,$id)
    {
        return Redis::zremrangebyscore($key,$id,$id);
    }

    // 获取一条数据
    public function getOneInfoById($key,$id)
    {
        return $this->zRangByScore($key,$id,$id,array('withScores'=>''));
    }

















}//end of class