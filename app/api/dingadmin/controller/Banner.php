<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//轮播管理
class Banner extends Base{


	public function index(){
		$list = Db::name('Banner')->order('id Desc')->select();
        return view('index',[
        	'list'=>$list
        ]);
	}


	public function add(){
		if(request()->isPost()){
			$data = input('post.');
			$data['addtime'] = time();
			$result = Db::name('Banner')->insert($data);
			if($result){
				return json(['status'=>1,'msg'=>'添加成功']);
			}else{
				return json(['status'=>1,'msg'=>'添加失败']);
			}
		}
		return view();
	}



	public function edit(){
		$id=input('id');
		if(request()->isPost()){
			$data = input('post.');
			$result = Db::name('Banner')->where('id',$id)->update($data);
			if($result){
				return json(['status'=>1,'msg'=>'编辑成功']);
			}else{
				return json(['status'=>0,'msg'=>'编辑失败']);
			}
		}
		$one = Db::name('Banner')->find($id);
		return view('edit',[
			'one'=>$one
		]);
	}



	public function del(){
		return view();
	}


	public function upload(){
        //获得上传文件
        $file = request()->file('imgfile');
        //判断$file是否为文件对象
        if($file){
            $savename = \think\facade\Filesystem::putFile( 'lunbo', $file);
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