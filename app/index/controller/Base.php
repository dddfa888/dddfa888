<?php

namespace app\index\controller;

use app\BaseController;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Env;
use think\facade\View;
use think\facade\Request;

// use app\common\lib\Alicms;
// use think\exception\HttpResponseException;
class Base extends BaseController
{
	
	public function initialize()
	{
        $lang = Cookie::get('think_lang');
        if ($lang == null) {
            $lang = Env::get('LANG.default_lang');
            Cookie::set('think_lang', $lang);
        }
        Cookie::set('think_lang', $lang);
		//判断用户是否登录
		if (empty(session('uid'))) {

			$this->redirect('index/Login/index');
		}
		//获取客服列表
		$kefu1 = Db::name('proconfig')->where('id', 1)->value('kefu');
		$kefu2 = Db::name('proconfig')->where('id', 1)->value('kefu2');
		$kefu3 = Db::name('proconfig')->where('id', 1)->value('kefu3');
		View::assign('kefu1', $kefu1);
		View::assign('kefu2', $kefu2);
		View::assign('kefu3', $kefu3);

		$uid = session('uid');
    $user_info = Db::name('user')->where('id', $uid)->find();
    $admin_info = Db::name('admin')->where('id', $user_info['admin_id'])->find();

    $customer = '';
    if($admin_info){
      $customer = $admin_info['server_code'];
    }

    View::assign('customer', $customer);



	}
	
	public function isLogin()
	{
		$sessionUserData = session('uid');
		if (empty($sessionUserData)) {
			$this->redirect('/index/login/index');
		}
		if ($sessionUserData['status'] != 1) {
			session('uid', null);
			$this->redirect('/index/login/index');
		} else {
			return $sessionUserData;
		}
	}
	
	//自动升级为VIP
	public function chickVip($id)
	{
		$vipConfig = Db::name('level')->select();
		$user = Db::name('user')->where('id', $id)->find();
		$congzhi = Db::name('recharge')->where('uid', $id)->where('status', 1)->sum('money');
		$vip = 0;
		
		for ($i = 0; !($i >= count($vipConfig)); $i++) {
			if ((int)$congzhi > (int)$vipConfig[$i]['price'] && (int)$congzhi < (int)$vipConfig[$i + 1]['price'] && $user['integral'] > $vipConfig[$i]['integral'] && $user['integral'] < (int)$vipConfig[$i + 1]['integral']) {
				$vip = $vipConfig[$i]['id'];
				break;
			}
		}
		
		if ($user['lv_id'] < (int)$vip) {
			Db::name('user')->where('id', $id)->update(['lv_id' => $vip]);
			payLog($user['id'] . '自动提升等级为' . $vip);
		}
	}
	
	
}
