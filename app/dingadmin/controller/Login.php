<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use app\BaseController;
use IpApi\IpLocation;//从扩展文件extend中引入

class Login extends BaseController{


	//登录页
    public function index () {
        return view();
    }


    //登录检查
    public function checkLogin () {
    	if (request()->isPost()) {
    		//将数据两侧的空格去除
            $account      = trim(input('post.account'));
            $password = trim(input('post.password'));
            $captcha = trim(input('post.captcha'));
            //判断是否有为空的数据
            if (empty($account) || empty($password)) {
                return json(['status' => 1, 'msg' => '账号和密码不能为空！', 'url' => '']);
            }
            //验证码校验
            if(!captcha_check($captcha)){
                return json(['status' => 1, 'msg' => '验证码错误！', 'url' => '']);
            };
            $info = DB::name('admin')->where(['account'=>$account,'password'=>md5($password)])->find();
            if ($info) {
                //添加登录的记录信息
                $rows['uid'] = $info['id'];//登录用户id
                //获取和ip有关的信息
                $Ip                    = new IpLocation(); // 实例化类 参数表示IP地址库文件
                $location              = $Ip->getlocation(); // 获取某个IP地址所在的位置
                $rows['login_ip']      = $location['ip'];
                $rows['login_address'] = $location['country'];
                $rows['logintime'] = time();//登录时间
                $rows['type'] = 1;//登录类型 0：前台用户 1：后台用户
                $res = DB::name('login_record')->insert($rows);

                //保存登录信息到session中
                $admin_indo = [
                    'id'       => $info['id'],
                    'account'  => $info['account'],
                    'groupid'  => $info['groupid'],
                    'password' => $info['password'],
                ];
                session('admin_auth', $admin_indo);
                //更新登录时间
                $res = DB::name('admin')->where('account',$account)->update(['login_time'=>time()]);
                if ($res) {
                    return json(['status' => 0, 'msg' => '登录成功!', 'url' =>  '/dingadmin/index/index']);
                }
            }else{
                return json(['status' => 1, 'msg' => '你的帐号或密码不正确！', 'url' => '']);
            }
    	}
    }


    //退出登录
    public function loginout(){
        session('admin_auth', null);
        return alert('正在退出...','index',5);
    }












}