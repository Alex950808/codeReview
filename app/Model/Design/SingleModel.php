<?php

namespace App\Model\Design;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//单例类不能再其它类中直接实例化，只能被其自身实例化。
//它不会创建实例副本，而是会向单例类内部存储的实例返回一个引用。
class SingleModel
{

    private $name;//声明一个私有的实例变量

    //它们必须拥有一个构造函数，并且必须被标记为private
    private function __construct()
    {

    }
    //它们拥有一个保存类的实例的静态成员变量
    static public $instance;
    //它们拥有一个访问这个实例的公共的静态方法
    static public function  getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;

    }

    public  function setName($n)
    {
        $this->name = $n;
    }

    public function getName()
    {
        return $this->name;
    }




}//end of class
