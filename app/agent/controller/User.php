<?php
namespace app\agent\controller;
use think\facade\Db;
use think\facade\View;
use think\facade\Request;


class User extends Base{

	public function index(){
		$uModel = Db::name('user');
		$username      = input("param.username");
        if ($username != '') {
            $uModel->wherelike('a.username','%'.$username.'%');
            View::assign('username',$username);
        }else{
            View::assign('username','');
        }
        // dump(session('agent_auth.id'));die;
        //查询属于该代理下的用户
		$list = $uModel->alias('a')->field("a.*,b.lv_name")->join('level b','b.level=a.lv_id','LEFT')->where('a.id','<>',session('agent_auth.id'))->whereLike('a.parentpath',session('agent_auth.id').'%')->order('a.reg_time asc')->paginate(['list_rows'=>30,'query'=>request()->param()]);//带参数查询语句

		$page = $list->render();
		return view('index',[
			'list'=>$list,
			'page'=>$page,
        ]);

	}


	//下级充值收益
	public function profit(){
		$Model = Db::name('jinbidetail');
		$username      = input("param.username");
        if ($username != '') {
            $Model->wherelike('username','%'.$username.'%');
            View::assign('username',$username);
        }else{
            View::assign('username','');
        }
		$list = $Model->where('type',1)->where('uid',session('agent_auth.id'))->paginate(['list_rows'=>30,'query'=>request()->param()]);
		$page = $list->render();
		return view('profit',[
			'list'=>$list,
			'page'=>$page,
        ]);
	}

	//下级返佣收益
	public function rakeback(){
		$Model = Db::name('jinbidetail');
		$username      = input("param.username");
        if ($username != '') {
            $Model->wherelike('username','%'.$username.'%');
            View::assign('username',$username);
        }else{
            View::assign('username','');
        }
		$list = $Model->where('type',2)->where('uid',session('agent_auth.id'))->paginate(['list_rows'=>30,'query'=>request()->param()]);
		$page = $list->render();
		return view('rakeback',[
			'list'=>$list,
			'page'=>$page,
        ]);
	}

	

























}