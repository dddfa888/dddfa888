<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//站点配置
class Manager extends Base{


	public function index(){
    $user_info = session('admin_auth');
        //config的表结构设计只要一条数据就可以咯，用config字段记录所有配置信息
        if(request()->isPost()){
            $data = input('post.');
            if(!empty($data['password'])){
              $data['password'] = md5($data['password']);
            }
            $result = Db::name('admin')->where('id',$user_info['id'])->update($data);
            if($result){
            	return json(['status'=>1,'msg'=>'修改成功']);
            }else{
            	return json(['status'=>0,'msg'=>'修改失败']);
            }
        }

        return view('index',[
        	'user_info'=>$user_info
        ]);
    }



    public function uploadlogo(){
        //获得上传文件
        $file = request()->file('imgfile');
        //判断$file是否为文件对象
        if($file){
            $savename = \think\facade\Filesystem::putFile( 'logo', $file);
            $savename=str_replace('\\','/',$savename);
            //dump($savename);die;  upload/20201210/f86c0a418e19e02dd973a84ae3de3928.png
            //获取上传到服务器上的文件路径
            $imgpath = "/public/".$savename;
            return json(['code'=>1,'msg'=>'上传成功','img'=>$imgpath]);
        }else{
            return json(['code'=>0,'msg'=>$file->getError()]);
        }
    }






























}
