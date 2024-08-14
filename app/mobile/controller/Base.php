<?php
namespace app\mobile\controller;
use app\BaseController;
use think\facade\Db;
use think\facade\View;
use app\common\lib\Alicms;
use think\exception\HttpResponseException;
class Base extends BaseController
{
    
    public function initialize() {

      $this->getConfig();//获取配置信息
    
      $sessionUserData=session('sessionUserData');
      View::assign('sessionUserData',$sessionUserData);

      $searchData=$this->getSearch();//获取关键字
      $cartCount=$this->getCartCount();//获取购物车数量

      View::assign('searchData',$searchData);
      View::assign('cartCount',$cartCount);

    }



    //配置信息
   public function getConfig(){

      $configData=Db::name('config')->field('value')->select();

      foreach($configData as $k=>$v){

        if($k==0){

         View::assign('web_title', $v['value']);//首页title

        }

        if($k==2){

            View::assign('web_keywords', $v['value']);//首页关键字

        }

        if($k==12){

            View::assign('web_description', $v['value']);//首页描述

        }

        if($k==3){

            View::assign('copyright', $v['value']);//版权信息

        }

        if($k==4){

            View::assign('beian', $v['value']);//备案号

        }

        if($k==6){

            View::assign('address', $v['value']);//地址

        }

        if($k==10){

            View::assign('email', $v['value']);//邮箱

        }

        if($k==7){

            View::assign('tel1', $v['value']);//电话

        }

        if($k==11){

            View::assign('fax', $v['value']);//传真

        }

      }
   }

   //获取关键字
   public function getSearch(){
       $searchData=Db::name('search')->order('id desc')->limit(10)->select()->toArray();
       return $searchData;
   }


   //前台密码加密盐
   public function password_salt($str){
        $salt='zxc69vbn';
        return md5($salt.$str);
    }

    //获取购物车数量
    public function getCartCount(){
        $sessionUserData=session('sessionUserData');
        $cartCount=Db::name('cart')->where('user_id',$sessionUserData['id'])->count();
        return $cartCount;
    }

    //发送验证码
    public function SendMobileCode(){
       $mobile=input('post.mobile');
    
       //自行判断手机号是否合法
       $sendcode=new Alicms();
       $res=$sendcode->index($mobile);
       if($res){
           return json(['status'=>1]);
       }else{
            return json(['status'=>0]);
       }

    }

    //通过sku获取属性值  3,5,23
    public function getAttrBySku($sku){
        $skuStr='';
        if(empty($sku)){
            return $skuStr;
        }
        $skuArr=explode(',',$sku);
        foreach($skuArr as $k=>$v){
            $str=Db::name('standard_value')->where('id',$v)->value('standard_value');
            if($k==0){
                $skuStr=$str;
            }else{
                $skuStr=$skuStr.','.$str;
            }
            
        }
        return $skuStr;
    }

    public function isLogin(){
        $sessionUserData=session('sessionUserData');
        if(empty($sessionUserData)){
            $this->redirect('/index/user/login');
        }if($sessionUserData['status']!=1){
            session('sessionUserData',null);
            $this->redirect('/index/user/login');
        }else{
            return $sessionUserData;
        }
    }

    public function redirect(...$args){
        throw new HttpResponseException(redirect(...$args));
    }
  
    //获取当前的位置信息
    public function getPositionByCatId($cateId){

        // $x=5;
        // while(0<=$x && $x<=5) {
        //     echo "数字是：$x <br>";
        //     $x--;
        //  } 

        $positionData=array();
          while($cateId){
                $cates=Db::name('category')->where('id='.$cateId)->find();  
                $positionData[]=array(
                    'id'=>$cates['id'],
                    'cate_name'=>$cates['cate_name'],
                    'parent_id'=>$cates['parent_id'],
                );
                $cateId=$cates['parent_id'];
           }

          //将取出的当前位置信息数组 倒序
          $positionData=array_reverse($positionData);
          return $positionData;
    }

    
}
