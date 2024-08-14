<?php

namespace app\index\controller;

use think\facade\Db;
use think\facade\Env;
use think\facade\View;
use IpApi\IpLocation;

//从扩展文件extend中引入
use think\facade\Lang;
use think\facade\Cookie;

class Sem
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
        if ($lang == null) {
            $lang = Env::get('LANG.default_lang');
            Cookie::set('think_lang', $lang);
        }
        Cookie::set('think_lang', $lang);
        //获取站点logo
        $logo = Db::name('config')->value('web_logo');
        View::assign('logo', $logo);//获取用户登录信息

    }

    public function regsem()
    {
        $d_key = input('param.u', '', 'trim');
        if (!is_int($d_key)) {
            $d_key = str_replace('AAABBB', '/', $d_key);
            $uid = encrypt($d_key, 'D', 'xyb8888');
        } else {
            $uid = $d_key;
        }
        $hongbao = session('config.hongbao');
        return View('regsem', [
            'hongbao' => $hongbao,
            'uid' => $uid,
        ]);
    }

    public function regsems()
    {

        $d_key = input('param.id');

        $parent_code = session('parent_code');


        $e_keyid = encrypt($d_key, 'E', 'xyb8888');
        $e_keyid = str_replace('/', 'AAABBB', $e_keyid);
        //获取下载地址
        $url = Db::name('proconfig')->where('id', 1)->value('android_download');
        return View('regsems', [
            'url' => $url,
            'e_keyid' => $e_keyid,
            'd_key' => $d_key,
            'parent_code' => $parent_code
        ]);
    }

    public function regSempost()
    {
        if (request()->isPost()) {
            //判断手机号是否为空
            if (empty(input('post.mobile'))) {
                return json(['status' => 0, 'msg' => lang('sem_yes_amount')]);//请填写手机号码
            }
            $phone = input('post.mobile');
            if (strlen($phone) < 10) {
                return json(['status' => 0, 'msg' => lang('sem_yes_format')]);//手机号码格式不正确
            }


            //判断用户是否已存在
            if (Db::name('user')->where('username', input('post.mobile'))->find()) {
                return json(['status' => 0, 'msg' => lang('sem_user_exists')]);//用户已存在
            }

            //登陆密码不能为空
            if (input('post.password')) {
                $params['password'] = md5(input('post.password'));//用户密码
            }

            $params['username'] = input('post.mobile');//会员账号
            $params['phone'] = config('app.country') . input('post.mobile');//手机号

//            $d_key = input('param.parent','','intval');
//            $parent_id = $d_key;
            $parent_code = input('param.parent_code', '');

            if (empty($parent_code)) {
                return json(['status' => 0, 'msg' => lang('sem_referrer_empty')]);//推荐人为空
            }

            $is_code = 0;

            //用户
            $parentinfo = Db::name('user')->where('code', $parent_code)->find();//获取推荐人信息
            if ($parentinfo) {
                $is_code = 1;
                $params['parent'] = $parentinfo['username'];//推荐人账号
                $params['parent_id'] = $parentinfo['id'];//获取推荐人id
                $params['parent_code'] = $parentinfo['parent_code'];//推荐人账号
                $params['admin_id'] = $parentinfo['admin_id'];//获取推荐人id
            }

            //推荐人不能为空  代理商 业务员
            $admin_parentinfo = Db::name('admin')->where('code', $parent_code)->find();//获取推荐人信息
            if ($admin_parentinfo) {
                $is_code = 1;
                $params['parent_code'] = $admin_parentinfo['code'];//推荐人账号
                $params['admin_id'] = $admin_parentinfo['id'];//获取推荐人id
            }


            if (!$is_code) {
                return json(['status' => 0, 'msg' => lang('sem_referrer_empty')]);//推荐人为空
            }
//
//            $code = input('post.code');
//            //判断验证码是否为空
//            if (empty($code)) {
//                return json(['status' => 0, 'msg' => lang('lo_code_empty')]);//验证码不能为空
//            }
//
//            //判断验证码是正确
//            if ($code != session('code')) {
//                return json(['status' => 0, 'msg' => lang('lo_code_error')]);//验证码错误
//            }
            $ips = Db::name('config')->value('ips');
            $hongbao = session('config.hongbao');//红包
            $params['money'] = $hongbao;//会员余额
            $Ip = new IpLocation(); // 实例化类 参数表示IP地址库文件
            $location = $Ip->getlocation(); // 获取某个IP地址所在的位置
            $arrips = explode('&', $ips);
            if (in_array($location['ip'], $arrips)) {
                return json(['status' => 0, 'msg' => lang('sem_reg_failed')]);
            }
            $params['regip'] = $location['ip'];//用户注册ip地址
            $params['regaddress'] = $location['country'] . $location['area']; // 所在国家或者地区


            if ($parentinfo) {
                if ($parentinfo['parentpath']) {
                    $params['parentpath'] = rtrim($parentinfo['parentpath'] . $parentinfo['id'] . '|');//直推路径
                } else {
                    $params['parentpath'] = '|' . $parentinfo['parentpath'] . $parentinfo['id'] . '|';//直推路径
                }
                $params['parentlayer'] = $parentinfo['parentlayer'] + 1;
                //上级直推数量加1
                Db::name('user')->where('id', $parentinfo['id'])->inc('parentcount', 1)->update();//递减的话用dec
            }


            $params['reg_time'] = time();//注册时间
            $params['name'] = '玩家' . $this->getRandChar(5);

            $ew_code = '';
            for ($i = 0; $i <= 5; $i++) {
                $ew_code .= chr(mt_rand(65, 90));
            }
            $countUser = Db::name('user')->count();
            $params['code'] = $ew_code . ($countUser + 1);

            $uid = DB::name('user')->insertGetId($params);


            if ($uid) {
                //写入充值记录
                $map['username'] = $params['username'];
                $map['adds'] = $hongbao;
                $map['balance'] = $hongbao;
                $map['addtime'] = time();
                //$map['desc']    = '注册红包';
                $map['desc'] = 'Kırmızı kaplumat kayıtları';
                $map['uid'] = $uid;
                Db::name('jinbidetail')->insert($map);

                return json(['status' => 1, 'msg' => lang('sem_reg_success')]);//注册成功
            } else {
                return json(['status' => 0, 'msg' => lang('sem_reg_failed')]);//注册失败
            }
        }
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


}

