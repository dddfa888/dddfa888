<?php
namespace app\agent\controller;
use app\BaseController;
use think\facade\Db;
use think\facade\View;
use think\facade\Request;
use Auth\Auth;
use app\common\model\AdminAuthGroupAccess;

class Base extends BaseController{


	public function initialize(){
		$agent_info = session('agent_auth');
        if (empty($agent_info)) {
            $this->redirect('/agent/login/index');
        }
		View::assign('agent_info',$agent_info);//获取用户登录信息
	}

















    
}

