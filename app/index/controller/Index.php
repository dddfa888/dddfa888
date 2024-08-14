<?php

namespace app\index\controller;

use gmspay\gmspay;
use think\facade\Db;
use think\facade\View;
use think\facade\Lang;


class Index extends Base
{

    //首页
    public function index()
    {

        return redirect('index/News/index');
    }

    //立即邀请
    public function tgm()
    {
        header("Content-type: text/html; charset=utf-8");
//		$e_keyid = encrypt(session('uid'), 'E', 'xyb8888');
//		$e_keyid = str_replace('/', 'AAABBB', $e_keyid);
//
        $uid = session('uid');
        $info = Db::name('user')->where('id', $uid)->find();
        $tuiguangma = $this->request->domain() . url('/index/sem/regsem', ['parent_code' => $info['code']]);
        
        //dump(app()->getRootPath().'public/encard.jpg');die; E:\phpstudy_pro\WWW\n
        //\public/encard.jpg
        $erwei = '';
        if (!$info['erwei']) {
            $fileName = scerweima($tuiguangma);//获取二维码
            $image = \think\Image::open($fileName);
            $image->water(app()->getRootPath() . '/public/encard.jpg', 4, 100);
            $erwei = '/' . $fileName;
            Db::name('user')->where('id', $uid)->update(['erwei' => $erwei]);
        }else{
           $erwei = $info['erwei'];
        }
  
        //直推下级投资总额
        $directOrder = 0;
        $lowLevel = Db::name('user')->where('parent_id', $uid)->field('id')->select()->toArray();
        if ($lowLevel){
            $lowLevelIds = array_column($lowLevel,'id');
            $lowOrderPrice = Db::name('order')->whereIn('user_id', $lowLevelIds)->sum('sumprice');
            if ($lowOrderPrice){
                $directOrder = $lowOrderPrice;
            }
        }

        return View('tgm', [
            'erwei' => $erwei,
            'url' => $tuiguangma,
            'direct_order' => $directOrder,
            'code' => $info['code']
        ]);
    }

    public function personal()
    {
        $user = Db::name('user')->where('id', session('uid'))->find();
        return View('personal', [
            'user' => $user
        ]);
    }

    //实名认证
    public function shiming()
    {
        $user = Db::name('user')->where('id', session('uid'))->find();
        $image = $user['front_card'] ? $user['front_card'] : '/public/dianyun/img/sc.png';
        $image2 = $user['back_card'] ? $user['back_card'] : '/public/dianyun/img/sc.png';
        return View('shiming', [
            'user' => $user,
            'image' => $image,
            'image2' => $image2,
        ]);
    }

    //绑定银行卡
    public function card()
    {
        $uid = session('uid');
        $list = Db::name('bankcard')->where('userid', $uid)->find();
        return View('card', [
            'list' => $list
        ]);
    }


    public function Posts($url, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);//不抓取头部信息。只返回数据
        curl_setopt($curl, CURLOPT_TIMEOUT, 1000);//超时设置
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);//1表示不返回bool值
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));//重点
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        return $response;
    }

    //添加银行卡操作
    public function addcardpost()
    {
        if (request()->isPost()) {
            if (empty(input('post.account_name'))) {
                return json(['status' => 0, 'msg' => lang('account_name')]);//收款人姓名
            }
            if (empty(input('post.account_no'))) {
                return json(['status' => 0, 'msg' => lang('account_no')]);//收款卡号
            }
            //   if (empty(input('post.ifsc'))) {
            //     return json(['status' => 0, 'msg' => lang('ifsc')]);//请输入银行代码ifsc

            //   }
//      if (empty(input('post.receiver_telephone'))) {
//        return json(['status' => 0, 'msg' => lang('receiver_telephone')]);//手机号码
//      }
//      if (empty(input('post.bankCode'))) {
//        return json(['status' => 0, 'msg' => lang('bank_name')]);//银行名称
//      }


//            $regex="/([a-z0-9]*[-_.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[.][a-z]{2,3}([.][a-z]{2})?/i";
//            if (!preg_match($regex,input('post.email'))) {
//                return json(['status'=>0,'msg'=>'Formato de caixa de correio ilegal']);//请输入正确的邮箱
//            }

            $uid = session('uid');
            $map = $_POST;
            $map['userid'] = $uid;

//            $map['bank_name'] = $map['bankCode'];


            //判断是更新还是添加
            if (Db::name('bankcard')->where('userid', $uid)->find()) {
                $res = Db::name('bankcard')->where('userid', $uid)->update($map);

            } else {
                $res = Db::name('bankcard')->insert($map);
            }
            if ($res) {
                return json(['status' => 1, 'msg' => lang('in_sub_success')]);//提交成功！
            } else {
                return json(['status' => 0, 'msg' => lang('in_sub_failed')]);//提交失败！
            }
        }
    }

    //我的团队
    public function zhitui()
    {
        $user = Db::name('user');
        $uid = session('uid');
        $list = $user->where('parent_id', $uid)->field('username,id,money,truename,parentcount')->select()->toArray();
        foreach ($list as $k => $v) {
            $list[$k]['zhitui'] = $v['parentcount'];
            $order = Db::name('order');
            $list[$k]['money'] = $order->where('user_id', $v['id'])->sum('already_profit');
        }
        return View('zhitui', [
            'list' => $list,
        ]);
    }

    //非直推
    public function feizhitui()
    {
        $user = Db::name('user');
        $uid = session('uid');
        $con = '|' . $uid . '|';
        $list = $user->where('parentpath', 'like', '%' . $con . '%')->field('username,id,money,truename,parentcount,parentpath')->select()->toArray();
        foreach ($list as $k => $v) {

            $list[$k]['zhitui'] = $v['parentcount'];
            $order = Db::name('order');
            $list[$k]['money'] = $order->where('user_id', $v['id'])->sum('already_profit');
            if ($v['parentpath'] == $con) {
                unset($list[$k]);
            }
        }
        return View('feizhitui', [
            'list' => $list,
        ]);
    }

    //修改密码
    public function updatepass()
    {
        return View('updatepass', [
        ]);
    }

    //修改密码操作
    public function updatepasspost()
    {
        if (empty(input('post.old_password'))) {
            return json(['status' => 0, 'msg' => lang('in_opassword_empty')]);//原密码不能为空！

        }
        if (empty(input('post.newpwd'))) {
            return json(['status' => 0, 'msg' => lang('in_newpassword_empty')]);//新密码不能为空！

        }
        if (empty(input('post.newpwd1'))) {
            return json(['status' => 0, 'msg' => lang('in_confirm_empty')]);//确认密码不能为空！
        }
        $old_password = input('post.old_password');
        $newpwd = input('post.newpwd');
        $newpwd1 = input('post.newpwd1');
        if ($newpwd != $newpwd1) {
            return json(['status' => 0, 'msg' => lang('in_two_different')]);//两次密码输入不一样！
        }
        $uid = session('uid');
        //检测原密码是否正确
        if (!Db::name('user')->where(['id' => $uid, 'password' => md5($old_password)])->find()) {
            return json(['status' => 0, 'msg' => lang('in_opassword_incorrect')]);//原密码不正确！
        }
        if (Db::name('user')->where('id', $uid)->update(['password' => md5($newpwd)])) {
            return json(['status' => 1, 'msg' => lang('in_modify_success')]);//修改成功！
        } else {
            return json(['status' => 0, 'msg' => lang('in_modify_failure')]);//修改失败！
        }
    }


}

