<?php
namespace app\index\controller;
use think\facade\Request;
use think\facade\Db;
use think\facade\View;
use think\facade\Lang;



class Article extends Base{


	public function show(){
		$id = input('get.id');
		$one = Db::name('article')->where('id',$id)->find();
		return view('show',[
			'one'=>$one
		]);
	}






















}