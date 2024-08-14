<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;


class Level extends Base{

	public function index(){
		$list = Db::name('level')->select();
		return view('index',[
			'list'=>$list,
        ]);
	}

	public function add(){
		if (request()->isPost()) {
            $params['lv_name'] = input('post.lv_name');
            $params['price'] = input('post.price');
            $params['level'] = input('post.level');
            $params['integral'] = input('post.integral');
            $res = Db::name('level')->insert($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'添加成功']);
            }else{
            	return json(['status'=>0,'msg'=>'添加失败']);
            }
        }
		return view('add',[

        ]);
	}


	public function edit(){
		if (request()->isPost()) {
			$id = input('post.id');
            $params['lv_name'] = input('post.lv_name');
            $params['price'] = input('post.price');
            $params['level'] = input('post.level');
						$params['integral'] = input('post.integral');
            $res = Db::name('level')->where('id',$id)->update($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'编辑成功']);
            }else{
            	return json(['status'=>0,'msg'=>'编辑失败']);
            }
        }
		$id = input('id');
		$one = Db::name('level')->find($id);
		return view('edit',[
			'one'=>$one
        ]);
	}



























}