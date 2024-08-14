<?php
namespace app\payment\controller;
use think\facade\Db;
use think\facade\Cache;
//印度支付接口
class Ydpay {

    //支付代收回调  充值
    public function notifyurl(){

        $postData = input('param.');
        //通过从缓存中获取数据来 杜绝订单回调太快导致数据重复添加
        $dingdan2 = Cache::get($postData['orderId']);//订单进来后先查询缓存
        if (!$dingdan2) {
            $dingdan = Cache::set($postData['orderId'], 'cunzai', 30);
            //写入日志文件
            file_put_contents('./app/payment/controller/daishou_log.txt',json_encode($postData).'\r\n',FILE_APPEND);
            if ($postData['resCode']==1000) {
                if ($postData['resMsg']=='success') {
                    //查下充值订单是否存在
                    $order = $postData['orderId'];
                    $one =  Db::name('recharge')->where('order_num',$order)->find();
                    if ($one) {
                        $money = $postData['amount'];
                        $uid = $one['uid'];
                        //获取用户信息
                        $user = Db::name('user')->where('id',$uid)->find();
                        //更新充值表订单
                        Db::name('recharge')->where('order_num',$postData['orderId'])->update(['status'=>1]);
                        //更新用户余额
                        Db::name('user')->where('id',$uid)->inc('money',$money)->update();
                        //判断金额变动表是否已经存在同一笔订单
                        $res = Db::name('jinbidetail')->where('order_num',$postData['orderId'])->find();
                        if ($res) {
                            echo 'ok';exit;
                        }else{
                            //写入充值记录
                            $params['username'] = $user['username'];//充值用户账号
                            $params['adds'] = $money;//充值金额
                            $params['balance'] = $user['money'] + $money;//当钱余额
                            $params['addtime'] = $postData['payTime'];//充值时间
                            $params['desc']    = 'Recharge';//充值描述
                            $params['uid']    = $uid;//充值用户id
                            $params['order_num']    = $postData['orderId'];
                            Db::name('jinbidetail')->insert($params);
                            echo 'ok';exit;
                        }
                    }
                }
            }
        }else{
            echo 'ok';exit;
        }

        
        
    }

    //支付代收 返回地址 交易结果页面
    public function callbackurl(){
        return View('callbackurl',[

        ]);
    }

    //代付回调 提现
    public function daishouurl(){
        $postData = input('param.');

        //通过从缓存中获取数据来 杜绝订单回调太快导致数据重复添加
        $dingdan2 = Cache::get($postData['orderId']);
        if (!$dingdan2) {
            $dingdan = Cache::set($postData['orderId'], 'cunzai', 30);
            //写入日志文件
            file_put_contents('./app/payment/controller/daifu_log.txt',json_encode($postData),FILE_APPEND);
            if ($postData['resCode']==1000) {
                if ($postData['msg']=='success') {
                    //查下提现订单是否存在
                    $order = $postData['orderId'];
                    $one = Db::name('withdrawal')->where('order_num',$order)->find();

                    if ($one) {
                        $money = $one['money'];//提现金额
                        $uid = $one['uid'];
                        //获取用户信息
                        $user = Db::name('user')->where('id',$uid)->find();
                        //更新提现表订单
                        Db::name('withdrawal')->where('order_num',$postData['orderId'])->update(['status'=>1]);
                        
                        //更新用户余额
                        //Db::name('user')->where('id',$uid)->dec('money',$money)->update();
                        
                        //写入充值记录
                        $params['username'] = $user['username'];//提现用户账号
                        $params['reduce'] = $money;//提现金额
                        $params['balance'] = $user['money'] + $money;//当钱余额
                        $params['addtime'] = $postData['payTime'];//提现时间
                        $params['desc']    = 'Recharge';//提现描述
                        $params['uid']    = $uid;//提现用户id
                        $params['order_num']    = $postData['orderId'];
                        Db::name('jinbidetail')->insert($params);
                        echo 'ok';exit;
                    }
                }
            }
        }else{
            echo 'ok';exit;
        }
        
    }

    //代付回调  交易结果页面
    public function daishouback(){
        return View('daishouback',[

        ]);
    }



    
}
