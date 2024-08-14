<?php

namespace app\index\controller;

use think\facade\Db;
use think\facade\Env;
use think\facade\Session;
use think\facade\View;
use IpApi\IpLocation;

//从扩展文件extend中引入
use think\facade\Lang;
use think\facade\Cookie;

class Login
{

    public function __construct()
    {
        // 控制器初始化
        $this->initialize();
    }

    protected function initialize()
    {
        //获取项目配置文件信息
        $config = Db::name('proconfig')->where('id', 1)->find();
        session('config', $config);

        $parent_code = input('param.parent_code');
        if ($parent_code) {
            session('parent_code', $parent_code);
        }
        $lang = Cookie::get('think_lang');

        $langType = 1;
        if ($lang == 'en-teq') {
            $langType = 1;
            Cookie::set('think_lang', 'en-teq');
        }
        if ($lang == 'en-us') {
            $langType = 2;
            Cookie::set('think_lang', 'en-us');
        }

        if ($lang == null) {
            $defaultLang = Env::get('LANG.default_lang');
            Cookie::set('think_lang', $defaultLang);
        }

        //获取站点logo
        $logo = Db::name('config')->value('web_logo');
        View::assign('logo', $logo);//获取用户登录信息
        View::assign('kefu', $config['kefu']);//获取用户登录信息
        View::assign('langtype', $langType);
    }


    public function index()
    {
        if (request()->isPost()) {
            //验证系统是否为开放状态
            if (session('config.is_lock') == 0) {
                return json(['status' => 0, 'msg' => lang('lo_system_open')]);//系统暂未开放
            }
            //用户和密码都不能为空
            if (input('post.username') == "" || input('post.password') == "") {
                return json(['status' => 0, 'msg' => lang('lo_user_cannot_empty')]);//用户和密码不能为空
            }
            //验证用户的账号密码是否正确
            $user = Db::name('user')->where(['username' => input('post.username'), 'password' => md5(input('post.password'))])->find();
            if (empty($user)) {
                return json(['status' => 0, 'msg' => lang('lo_wrong_user_password')]);//用户名或密码错误
            }
            //禁止锁定会员登录
            if ($user['lock'] == 2) {
                return json(['status' => 0, 'msg' => lang('lo_account_has_locked')]);//您的账号已经被锁定!联系客服
            }
            $params['online_time'] = time();//最后登陆时间
            $res = Db::name('user')->where('id', $user['id'])->update($params);

            if ($res) {
                session('uid', $user['id']);
                session('username', $user['username']);
                return json(['status' => 1, 'msg' => lang('lo_login_successful')]);//登陆成功
            } else {
                return json(['status' => 0, 'msg' => lang('lo_login_failed')]);//登陆失败
            }
        }

        return View('index');
    }


    public function reg()
    {
        if (request()->isPost()) {
            //用户和密码都不能为空
            if (input('post.username') == "" || input('post.password') == "") {
                return json(['status' => 0, 'msg' => lang('lo_user_cannot_empty')]);//用户名和密码不能为空
            }
            //判断用户是否已存在
            if (Db::name('user')->where('username', input('post.username'))->find()) {
                return json(['status' => 0, 'msg' => lang('lo_user_exists')]);//用户已存在
            }
            $params['username'] = input('post.username');//会员账号
            $params['phone'] = input('post.username');//手机号
            $parent = input('post.parent');


            //判断是否存在推荐人
            if (!empty($parent)) {
                $parentinfo = Db::name('user')->where('username', $parent)->find();//获取推荐人信息
                //判断推荐人是否已经存在
                if (!$parentinfo) {
                    return json(['status' => 0, 'msg' => lang('lo_referrer_exists')]);//推荐人不存在
                }
                $params['parent'] = $parentinfo['username'];//推荐人账号
                $params['parent_id'] = $parentinfo['id'];//获取推荐人id
                $params['parentpath'] = trim($parentinfo['parentpath'] . $parentinfo['id'] . '|');//直推路径


            } else {
                return json(['status' => 0, 'msg' => lang('sem_referrer_empty')]);
            }

            $code = input('post.code');
            //判断验证码是否为空
            if (empty($code)) {
                return json(['status' => 0, 'msg' => lang('lo_code_empty')]);//验证码不能为空
            }

            //判断验证码是正确
            if ($code != session('code')) {
                return json(['status' => 0, 'msg' => lang('lo_code_error')]);//验证码错误
            }

            //手机号格式校验
            // if (!preg_match("/^1[34578]{1}\d{9}$/",$params['username'])) {
            // 	return json(['status'=>0,'msg'=>lang('lo_phone_format')]);//手机号码格式不正确
            // }

            //登陆密码不能为空
            if (input('post.password')) {
                $params['password'] = md5(input('post.password'));//用户密码
            }
            $params['money'] = session('config.hongbao');//会员余额
            $Ip = new IpLocation(); // 实例化类 参数表示IP地址库文件
            $location = $Ip->getlocation(); // 获取某个IP地址所在的位置
            $params['regip'] = $location['ip'];//用户注册ip地址
            $params['regaddress'] = $location['country'] . $location['area']; // 所在国家或者地区
            $params['reg_time'] = time();//注册时间
            $params['name'] = '玩家' . $this->getRandChar(5);

            $res = DB::name('user')->insert($params);

            if ($res) {
                if ($parent) {
                    //上级直推数量加1
                    Db::name('user')->where('id', $params['parent_id'])->inc('parentcount', 1)->update();//递减的话用dec
                }
                return json(['status' => 1, 'msg' => lang('lo_reg_successful')]);//注册成功
            } else {
                return json(['status' => 0, 'msg' => lang('lo_reg_failed')]);//注册失败
            }
        }
        return View('reg');
    }


    //用户密码修改
    public function editpwd()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            $username = input('post.username');
            $password = input('post.password');
            $opassword = input('post.opassword');

            //用户名手机号不能为空
            if (empty($code)) {
                return json(['status' => 0, 'msg' => lang('lo_phone_empty')]);//手机号不能为空
            }

            //新密码不能为空
            if (empty($password)) {
                return json(['status' => 0, 'msg' => lang('lo_new_password')]);//新密码不能为空
            }

            //旧密码不能为空
            if (empty($opassword)) {
                return json(['status' => 0, 'msg' => lang('lo_old_password')]);//旧密码不能为空
            }

            //判断验证码是否为空
            if (empty($code)) {
                return json(['status' => 0, 'msg' => lang('lo_code_empty')]);//验证码不能为空
            }

            //判断验证码是正确
            if ($code != session('code')) {
                return json(['status' => 0, 'msg' => lang('lo_code_error')]);//验证码错误
            }

            //判断新旧密码是否一致
            if ($password != $opassword) {
                return json(['status' => 0, 'msg' => lang('lo_two_password')]);//两次密码输入不一致
            }

            //修改密码
            $params['password'] = md5($password);
            $res = Db::name('user')->where('username', $username)->update($params);
            if ($res) {
                return json(['status' => 1, 'msg' => lang('lo_edit_successful')]);//修改成功
            } else {
                return json(['status' => 0, 'msg' => lang('lo_edit_failed')]);//修改失败
            }
        }
        return View('editpwd');
    }


    //随机生成要求位数个字符
    public function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";//大小写字母以及数字
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[rand(0, $max)];
        }
        return $str;
    }


    //退出操作
    public function logout()
    {
        session('uid', null);
        session('username', null);
        return alert(lang('lo_exiting'), 'index', 5);//正在退出
    }


    //apikey：f0d6522507744e79aa958c70ebd3fc15
    public function code()
    {
        if (request()->isPost()) {
            $phone = input('post.phone/d');
            // //手机号格式校验
            if (strlen($phone) != 10) {
                return json(['status' => 0, 'msg' => lang('sem_yes_format')]);//手机号码格式不正确
            }


            if ($phone) {
                $phone = config('app.country') . $phone;
                $code = rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);//生成验证码
                $appkey = 'tu168';
                $appsecret = 'Rtrk93';
                $appcode = '1000';
                $sign = md5($appkey . $appsecret . time());
                $apiKey = 'f0d6522507744e79aa958c70ebd3fc15';
                $mobile = $phone;

                $result = Db::name('proconfig')->where('id', 1)->find();
                $content = str_replace("{code}", $code, $result['sms_msg']);
                //$content = $sign . $str;

                $content = urlencode($content);
                //$url = 'http://www.beelink8.com:6665/sms/send?apiKey='.$apiKey.'&mobile='.$mobile.'&content='.$content;
                $url = 'http://47.242.85.7:9090/sms/batch/v2?appkey=' . $appkey . '&appcode=' . $appcode . '&appsecret=' . $appsecret . '&phone=' . $mobile . '&msg=' . $content;
                //return json(['status' => 1, 'msg' => $code]);
                $res = $this->getCurl($url);

                $dtTmp = date('Ymd');
                $file = fopen("public/pay/sms$dtTmp.txt", "a+");
                fwrite($file, "返回数据===\n");
                fwrite($file, $res);
                fwrite($file, "\n");


                $res = json_decode($res, true);//json转数组


                // var_dump($res);
                if ($res['code'] == '00000') {
                    if ($res['result'][0]['status'] == '00000') {
                        session('code', $code);
                        session('phone', $phone);
                        return json(['status' => 1, 'msg' => lang('lo_phone_ok')]);
                    } else {
                        return json(['status' => 0, 'msg' => lang('lo_phone_fail')]);
                    }
                } else {
                    return json(['status' => 0, 'msg' => lang('lo_phone_fail')]);
                }

            } else {
                return json(['status' => 0, 'msg' => lang('lo_phone_empty')]);//手机号不得为空
            }
        }
    }


    public function getCurl($url)
    {
        $header = array(
            "Content-type:application/json",
        );

        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 超时设置,以秒为单位
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);
        // 超时设置，以毫秒为单位
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500 * 1000);
        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令

        $data = curl_exec($curl);
        // 显示错误信息
        if (curl_error($curl)) {
            print "Errors: " . curl_error($curl);
        } else {
            return $data;
            curl_close($curl);
        }
    }


    public function langtype()
    {
        $langType = input('post.id');
        $lang = "";
        if ($langType == 1) {
            $lang = 'en-teq';
        }
        if ($langType == 2) {
            $lang = 'en-us';
        }
        Cookie::set('think_lang', $lang);
        return json(['status' => 1]);

    }
    
    public function test(){
        echo date('Y-m-d H:i:s',time());
    }
}

