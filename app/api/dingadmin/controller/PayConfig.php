<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//站点配置
class PayConfig extends Base{


	public function index(){
        //config的表结构设计只要一条数据就可以咯，用config字段记录所有配置信息
        if(request()->isPost()){
            $data = input('post.');
            $result = Db::name('pay_config')->where('id',1)->update($data);
            if($result){
            	return json(['status'=>1,'msg'=>'修改成功']);
            }else{
            	return json(['status'=>0,'msg'=>'修改失败']);
            }
        }
        //读取配信息
        $config = Db::name('pay_config')->where('id',1)->find();
        return view('index',[
        	'config'=>$config
        ]);
    }





    public  static  function getConfig(){
      $pay_config = Db::name('pay_config')->where('id',1)->find();

      if(self::getPayType() == 1){
        $mch_id = $pay_config['a_mch_id'];
        $pay_type = $pay_config['a_pay_type'];
        $token = $pay_config['a_token'];
        $out_notify_url = $pay_config['a_out_notify_url'];
        $pay_notify_url = $pay_config['a_pay_notify_url'];
        $pay_out_type = $pay_config['a_out_pay_type'];
      }else{
        $mch_id = $pay_config['b_mch_id'];
        $pay_type = $pay_config['b_pay_type'];
        $token = $pay_config['b_token'];
        $out_notify_url = $pay_config['b_out_notify_url'];
        $pay_notify_url = $pay_config['b_pay_notify_url'];
        $pay_out_type = $pay_config['b_out_pay_type'];
      }


      $data['mch_id'] = $mch_id;
      $data['pay_type'] = $pay_type;
      $data['token'] = $token;
      $data['out_notify_url'] = $out_notify_url;
      $data['pay_notify_url'] = $pay_notify_url;
      $data['pay_out_type'] = $pay_out_type;


      return $data;
    }


    //获取支付类型
    public static function  getPayType(){
      $pay_config = Db::name('pay_config')->where('id',1)->find();
     return $pay_config['type'];

    }































}
