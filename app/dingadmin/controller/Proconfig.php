<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//项目配置
class Proconfig extends Base{


	public function index(){
        //config的表结构设计只要一条数据就可以咯，用config字段记录所有配置信息
        if(request()->isPost()){
            $data = input('post.');
            $data['is_lock'] = isset($data['is_lock']) ? 1 : 0;
            $result = Db::name('proconfig')->where('id',1)->update($data);
            if($result){
            	return json(['status'=>1,'msg'=>'配置修改成功']);
            }else{
            	return json(['status'=>0,'msg'=>'配置修改失败']);
            }
        }
        //读取配信息
        $config = Db::name('proconfig')->where('id',1)->find();
        return view('index',[
        	'config'=>$config
        ]);
    }


































}