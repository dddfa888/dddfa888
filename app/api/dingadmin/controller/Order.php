<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//订单管理
class Order extends Base{

	public function index(){
		$oModel = Db::name('order');
		$user      = input("param.user");
        if ($user != '') {
            $oModel->wherelike('user','%'.$user.'%');
            View::assign('user',$user);
        }else{
            View::assign('user','');
        }
        
		$list = $oModel->order('addtime desc')->paginate(30);
		$page = $list->render();
		return view('index',[
			'list'=>$list,
			'page'=>$page,
        ]);

	}





































}