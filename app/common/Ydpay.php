<?php
namespace app\common;

//印度支付接口
class Ydpay {

    //支付回调
    public function notifyurl(){
        return ok;
        // $resCode = input('resCode');
        // $order = input('tradeId');
        
        // if ($resCode==1000) {
        //     Db::name('recharge')->where('order_num',$order)->update(['status'=>1]);
        //     //更新用户余额
        //     Db::name('user')->where('id',$uid)->inc('money',$money)->update();
        //     //写入充值记录
        //     $params['username'] = $user['username'];
        //     $params['adds'] = $money;
        //     $params['balance'] = $user['money'] + $money;
        //     $params['addtime'] = time();
        //     // $params['desc']    = '客户充值';
        //     $params['desc']    = 'Recharge';
        //     $params['uid']    = $uid;
        //     Db::name('jinbidetail')->insert($params);
        //     return ok;
        // }
    }

    public function callbackurl(){
        return View('callbackurl',[
        ]);
    }

    
}
