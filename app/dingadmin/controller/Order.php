<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//订单管理
class Order extends Base{

	public function index(){
		$oModel = Db::name('order')->alias('l');
		$user      = input("param.user");
        if ($user != '') {
            $oModel->wherelike('user','%'.$user.'%');
            View::assign('user',$user);
        }else{
            View::assign('user','');
        }

    $ids_arr = $this->getIds();

    if(!empty($ids_arr)){
      $oModel->whereIn('u.admin_id', $ids_arr);
    }

		$list = $oModel ->join('user u', 'l.user_id=u.id', 'LEFT')->order('addtime desc')->paginate(30);
		$page = $list->render();
		return view('index',[
			'list'=>$list,
			'page'=>$page,
        ]);

	}





































}
