<?php

namespace app\index\controller;

use app\BaseController;
use app\dingadmin\controller\PayConfig;
use gmspay\gmspay;
use think\Env;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use think\facade\Lang;
use app\index\Ydpay;
use think\Log;
use think\Request;
use app\common\Nibpay;
use think\exception\ValidateException;

class Wallet extends Base
{


    public function index()
    {
        $user = Db::name('user')->alias('a')->field("a.*,b.lv_name")->join('level b', 'b.level=a.lv_id', 'LEFT')->where('a.id', session('uid'))->find();

        if (!$user) {
            session(null);
            return $this->redirect('/index/login/index');
        }

        $conf = Db::name('proconfig')->where('id', 1)->find();
        $kefu = $conf['kefu'];
        $tglink = $conf['tg_link'];
        $download = $conf['android_download'];

        return View('index', [
            'user' => $user,
            'kefu' => $kefu,
            'tglink' => $tglink,
            'download' => $download,
        ]);
    }

    //提现页面
    public function withdrawn()
    {
        $charge = Db::name('proconfig')->where('id', 1)->value('charge_ratio');
        //获取提现说明内容
        $content = Db::name('article')->where('id', 101)->value('content');
        //获取通道
        $tong = Db::name('Channel')->where('switch', 1)->select();

        return View('withdrawn', [
            'charge' => $charge,
            'content' => $content,
            'tong' => $tong,
        ]);
    }


    //提现接口
    public function withpost_api(Request $request)
    {

        $qudao = input('post.qudao');
        $money = input('post.money');
        //判断是否有选择渠道
//		$channel = Db::name('channel')->where('mark', $qudao)->find();//获取渠道信息
//		if (empty($channel)) {
//			return json(['status' => 0, 'msg' => 'Channel cannot be empty']);//渠道不能为空
//		}
        if ($money <= 0) {
            return json(['status' => 0, 'msg' => lang('wa_yes_amount')]);//请填写正确金额
        }
        $uid = session('uid');
        $user = Db::name('user')->where('id', $uid)->find();//获取用户信息
        //判断是否满足该渠道的最低提现值和最高提现值
//		if ($channel['withdraw_lowest'] > $money) {
//			return json(['status' => 0, 'msg' => 'The withdrawal amount cannot be less than' . $channel['withdraw_lowest']]);//提现金额不能小于
//		}
//		if ($channel['withdraw_highest'] < $money) {
//			return json(['status' => 0, 'msg' => 'The withdrawal amount cannot be greater than' . $channel['withdraw_highest']]);//提现金额不能大于
//		}
        //判断提现金额是否大于客户余额
        if ($money > $user['money']) {
            return json(['status' => 0, 'msg' => lang('wa_than_balance')]);//提现金额不能大于余额
        }

        //获取用户银行卡信息
        $bank = Db::name('bankcard')->where('userid', $uid)->find();
        if (empty($bank)) {
            return json(['status' => 22, 'msg' => lang('wa_bind_card')]);//请先绑定银行卡号
        }

        //判断用户是否投资项目
        $isBuyOrder = Db::name("order")->where(['user_id' => $uid])->find();
        if (empty($isBuyOrder)) {
            return json(['status' => 0, 'msg' => lang('wa_order_tip')]);
        }

        //获取项目配置
        $proconfig = Db::name('proconfig')->where('id', 1)->find();
        //获取基础配置
        $conf = Db::name('config')->where('id', 1)->find();

        //判断提现的时候 银行卡和ifsc 是否为空
        if (empty($bank['account_no'])) {
            return json(['status' => 0, 'msg' => lang('wa_bind_card')]);//请先绑定银行卡号
        }

        if (empty($bank['account_name'])) {
            return json(['status' => 0, 'msg' => lang('wa_holder_empty')]);//银行卡持有人姓名不得为空
        }

        //判断是否已经购买产品  并且充值
        $order = Db::name('order')->where('user_id', $uid)->find();
        $recharge = Db::name('recharge')->where('uid', $uid)->where('status', 1)->find();

        //buy_product
       if (empty($order)) {
            return json(['status' => 0, 'msg' => lang('buy_product')]);
        }
        if (empty($recharge)) {
            return json(['status' => 0, 'msg' => lang('buy_product')]);
        }


        $charge = $proconfig['charge_ratio'] / 100 * $money;//手续费
        $actual_money = $money - $charge;//实际转账金额
        $oderNum = 'DF' . time() . rand(100, 99999);//代付订单号


        //先插入提现表数据
        $map['uid'] = $uid;
        $map['order_num'] = $oderNum;//提现订单号
        $map['username'] = $user['username'];//用户名
        $map['truename'] = $bank['account_name'];//姓名
        $map['bank'] = $bank['bank_name'];//银行
        $map['card'] = $bank['account_no'];//银行账号
        $map['ifsc'] = $bank['ifsc'] ? $bank['ifsc'] : $bank['account_no'];//IFSC
        $map['upi'] = $bank['upi'];//UPI
        $map['money'] = $money;//提现金额
        $map['payment'] = $actual_money;//实到金额
        $map['charge'] = $charge;//手续费
        $map['money_front'] = $user['money'];//变更前金额
        $map['money_after'] = $user['money'] - $money;//变更后金额
        $map['addtime'] = time();//提现时间
        $map['remark'] = '申请提现' . $money . '元,扣除' . $charge . '作为手续费扣除';//提现说明
        $map['status'] = 0;//提现订单状态
        $map['channel'] = 'gsmpay';//渠道标识
        $map['bank_type'] = $bank['bank_code'];

        $res = Db::name('withdrawal')->insert($map);

        //提现后要减去用户余额里的钱先
        Db::name('user')->where('id', $uid)->dec('money', $money)->update();

        $params['username'] = $user['username'];
        $params['reduce'] = intval($money);
        $params['balance'] = $user['money'] - intval($money);
        $params['addtime'] = time();
        $params['desc'] = 'Withdraw';
        $params['uid'] = $user['id'];
        Db::name('jinbidetail')->insert($params);

        if ($res) {
            return json(['status' => 1, 'msg' => lang('wa_successfully')]);//提现成功
        } else {
            return json(['status' => 0, 'msg' => lang('wa_failed')]);//提现失败
        }
    }

    //隐藏提交表单
    public function withpostfrom()
    {
        $order = input('order');
        $uid = session('uid');
        $one = Db::name('ydpay_tixian')->where('pay_orderid', $order)->where('uid', $uid)->find();
        if ($one) {
            return View('withpostfrom', [
                'one' => $one,
            ]);
        }
    }

    public function serpayWallet($proconfig, $bank, $money, $user)
    {
        $charge = $proconfig['charge_ratio'] / 100 * $money;//手续费
        $actual_money = $money - $charge;//实际转账金额
        $oderNum = 'DF' . time() . rand(100, 99999);//代付订单号
        $data = [
            'uid' => $user['id'],
            'username' => $bank['receive_name'],
            'money' => $money,
            'card' => $bank['receive_account'],
            'addtime' => time(),
            'status' => '0',
            'charge' => $charge,
            'payment' => $actual_money,
            'order_num' => $oderNum,
            'remark' => '申请提现' . $money . '元,扣除' . $charge . '作为手续费扣除',//提现说明
            'money_front' => $user['money'],
            'money_after' => $user['money'] - $money,
            'truename' => $bank['receive_name'],
        ];
//        Db::startTrans();
//        $res = Db::name('withdrawal')->insertGetId($data);
        $transferPayUrl = 'https://pay.sepropay.com/pay/transfer';
        $mch_id = '944944111';//测式
//        $mch_id = '600005061';//正式
        $merchant_key = "c439ef5bbaf643f982701790ec6ab37e";//测试
//        $merchant_key ="ILAM5RXVF0LKCEMLYJXZJTVC3PG0Z5WX";//正式
        $back_url = 'https://www.easypowe.com/payment/SpayNotify/withDrawnNotify';
        $sginStr = [
            'mch_id' => $mch_id,
            'mch_transferId' => $oderNum,
            'transfer_amount' => $actual_money,
            'apply_date' => date('Y-m-d H:i:s', time()),
            'bank_code' => $bank['bank_code'],
            'receive_name' => $bank['bank_branch_name'],
            'receive_account' => $bank['receive_account'],
            'back_url' => $back_url,
            'receiver_telephone' => $bank['receiver_telephone'],
            'account_digit' => $bank['account_digit'],
            'document_id' => $bank['document_id'],
            'document_type' => $bank['document_type'],
            'account_type' => $bank['account_type'],
        ];

        $sginStr = $this->ASCII($sginStr);


    }

    //提现记录
    public function withdrawnlog()
    {
        $uid = session('uid');
        $list = Db::name('withdrawal')->where('uid', $uid)->select();
        $tixian_sum = Db::name('withdrawal')->where(['uid' => $uid, 'status' => 1])->sum('payment');
        $no_tixian_sum = Db::name('withdrawal')->where(['uid' => $uid, 'status' => 0])->sum('payment');
        return View('withdrawnlog', [
            'list' => $list,
            'tixian_sum' => $tixian_sum,
            'no_tixian_sum' => $no_tixian_sum,
        ]);
    }

    //充值管理
    public function onlinerecharge()
    {
        $uid = session('uid');

        $username = session('username');
        $user = Db::name('user')->where('id', $uid)->find();
        $one = Db::name('collection')->where('id', 1)->find();
        $money = $user['money'];
        $new_collect = '';
        //获取提现说明内容
        $content = Db::name('proconfig')->where('id', 1)->value('content');


        //获取支付数据
        $tt = date('Y-m-d H:i:s', time());
        $cz_arr = Db::name('topup_active')
            ->where('start_time', '<', date('Y-m-d H:i:s', time()))
            ->where('end_time', '>', date('Y-m-d H:i:s', time()))
            ->where('status', 1)
            ->find();

        if (!$cz_arr) {
            return json(['code' => 1, 'msg' => '充值配置不存在']);
        }
        $chongzhi = json_decode($cz_arr['content'], true);

        $chongzhi_arr = [];
        foreach ($chongzhi as $key => $row) {
            $chongzhi_arr[] = array(
                'amount' => $key,
                'js_amount' => $row,
            );

        }


        //获取通道
        $tong = Db::name('Channel')->where('switch', 1)->select()->toArray();
        return View('onlinerecharge', [
            'money' => $money,
            'username' => $username,
            'one' => $one,
            'content' => $content,
            'tong' => $tong,
            'chongzhi' => $chongzhi_arr,
        ]);
    }


    //充值
    public function addrechargelog_api($money, $type = null)
    {

        $store_id = "9zzbaxi";
        $mch_id = '9zzbaxi14743199';
        $Md5key = "E285BFAB4EE34304C707A5BACB576B8E";
        $pay_type = '68';
        $out_trade_no = $this->getSn('A');
        $trans_amt = $money;
        $notify_url = 'https://www.easypowe.com/payment/SpayNotify/rhNotify';
        $native = array(
            "store_id" => $store_id,
            "mch_id" => $mch_id,
            "pay_type" => $pay_type,
            "out_trade_no" => $out_trade_no,
            "trans_amt" => $trans_amt,
            "notify_url" => $notify_url,
        );
        ksort($native);
        $sign_data = $native;
        $str = '';
        foreach ($sign_data as $i => $v) {
            $str .= $i . '=' . $v . '&';
        }
        $str .= 'key=' . $Md5key;
        $sign = strtoupper(md5($str));
        $native['body'] = '';
        $native['sign'] = $sign;
        $info = $this->send_post('http://43.255.31.21:8089/zhifpops/qrCodePay', $native);
        $info = urldecode($info);
        $info = json_decode($info, true);
        $info['ret_msg'] = urldecode($info['ret_msg']);
        payLog($out_trade_no . '日禾支付请求返回' . json_encode($info));
        if ($info['ret_code'] == '00') {
            $uid = session('uid');
            $uinfo = Db::name('user')->where('id', $uid)->find();
            Db::startTrans();
            $res = Db::name('recharge')
                ->insertGetId([
                    'order_num' => $out_trade_no,
                    'uid' => $uid,
                    'username' => $uinfo['username'],
                    'money_front' => $uinfo['money'],
                    'money_after' => $uinfo['money'] + $money,
                    'type' => 1,
                    'money' => $money,
                    'addtime' => time(),
                    'channel' => 'pix'
                ]);
            if ($res) {
                Db::commit();
                echo $info['pay_url'];
            } else {
                Db::rollback();
            }

        }

    }

    public function dukpay($money, $type = null)
    {
        $uid = session('uid');
        $bank = Db::name('bankcard')->where('userid', '=', $uid)->find();
        $key = '9caGHi59Db2uhV2Ha8Xtta9aLXdWh4WB';
        $url = 'https://gateway.dukpay.com/api/shopApi/order/createorder';
        $data = [
            'type' => 'eb',
            'shop_no' => $this->getSn('A'),
            'notify_url' => 'https://wetbc.cc/payment/SpayNotify/dukNotify',
            'return_url' => 'https://wetbc.cc/index/Wallet/index.html',
            'shop_id' => '40308',
            'country' => '3',
            'currency_code' => 'BRL',
            'api_payment_code' => 'pix',
            'money' => $money,
            'name' => $bank['account_name'],
            'email' => $bank['email'],
            'document' => '70189770457',
            'zipcode' => '83331000'
        ];
        $data['sign'] = md5($data['shop_id'] . $data['money'] . $data['type'] . $key);
        $res = $this->Posts($url, $data);

        payLog($data['shop_no'] . 'duk支付请求返回' . json_encode($res));
        $info = json_decode($res, true);
        if ($info['code'] == 1) {
            $uinfo = Db::name('user')->where('id', $uid)->find();
            Db::startTrans();
            $res = Db::name('recharge')
                ->insertGetId([
                    'order_num' => $data['shop_no'],
                    'uid' => $uid,
                    'username' => $uinfo['username'],
                    'money_front' => $uinfo['money'],
                    'money_after' => $uinfo['money'] + $money,
                    'type' => 1,
                    'money' => $money,
                    'addtime' => time(),
                    'channel' => $type
                ]);
            if ($res) {
                Db::commit();
                echo $info['pay_url'];
            } else {
                Db::rollback();

            }
        } else {
            return $info['msg'];
        }
    }

    public function dukpayB($money, $type = null)
    {
        $uid = session('uid');
        $bank = Db::name('bankcard')->where('userid', '=', $uid)->find();
        $key = '9caGHi59Db2uhV2Ha8Xtta9aLXdWh4WB';
        $url = 'https://gateway.dukpay.com/api/shopApi/order/createorder';
        $data = [
            'type' => 'eb',
            'shop_no' => $this->getSn('A'),
            'notify_url' => 'https://wetbc.cc/payment/SpayNotify/dukNotify',
            'return_url' => 'https://wetbc.cc/index/Wallet/index.html',
            'shop_id' => '40308',
            'country' => '3',
            'currency_code' => 'BRL',
            'api_payment_code' => 'picpay',
            'money' => $money,
            'name' => $bank['account_name'],
            'email' => $bank['email'],
            'document' => '70189770457',
            'zipcode' => '83331000'
        ];

        $data['sign'] = md5($data['shop_id'] . $data['money'] . $data['type'] . $key);
        $res = $this->Posts($url, $data);
        payLog($data['shop_no'] . 'duk支付请求返回' . json_encode($res));
        $info = json_decode($res, true);
        if ($info['code'] == 1) {
            $uinfo = Db::name('user')->where('id', $uid)->find();
            Db::startTrans();
            $res = Db::name('recharge')
                ->insertGetId([
                    'order_num' => $data['shop_no'],
                    'uid' => $uid,
                    'username' => $uinfo['username'],
                    'money_front' => $uinfo['money'],
                    'money_after' => $uinfo['money'] + $money,
                    'type' => 1,
                    'money' => $money,
                    'addtime' => time(),
                    'channel' => $type
                ]);
            if ($res) {
                Db::commit();
                echo $info['pay_url'];
            } else {
                Db::rollback();

            }
        } else {
            return $info['msg'];
        }
    }

    public function chickcpf()
    {
        $uid = session('uid');
        $bkinfo = Db::name('bankcard')->where('userid', $uid)->find();
        if (empty($bkinfo['email'])) {
            return json(['code' => 0, 'url' => 'https://easypowe.com/index/Index/card.html']);
        } else {
            return json(['code' => 1]);
        }

    }

    public function getSn($head = '')
    {

        $order_id_main = date('YmdHis') . mt_rand(1000, 9999);
        //唯一订单号码（YYMMDDHHIISSNNN）
        $osn = $head . substr($order_id_main, 2); //生成订单号
        return $osn;
    }

    private function post($url, $post_data, $timeout = 10)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($post_data)) {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (parse_url($url, PHP_URL_SCHEME) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $file_contents = curl_exec($ch);
        curl_close($ch);

        return json_decode($file_contents, true);
    }

    /****异步回调代收订单****/
    public function pay_notify()
    {
        // $fxid = $_REQUEST['memberid'];
        $fxddh = $_REQUEST['mer_order_no'];
        // $fxorder = $_REQUEST['transaction_id'];
        // $fxdesc = $_REQUEST['attach']; //商品名称
        $fxfee = $_REQUEST['pay_amount']; //交易金额
        // $fxattch = $_REQUEST['attach']; //附加信息
        $fxstatus = $_REQUEST['status']; //订单状态
        // $fxtime = $_REQUEST['datetime']; //支付时间
        // $fxsign = $_REQUEST['sign']; //md5验证签名串
        // $native = array(
        //         'memberid'=>$fxid,
        //         'orderid'=>$fxddh,
        //         'transaction_id'=>$fxorder,
        //         'amount'=>$fxfee,
        //         'returncode'=>$fxstatus,
        //         'datetime'=>$fxtime,
        //     );
        // ksort($native);
        // $md5str = "";
        // foreach ($native as $key => $val) {
        //     $md5str = $md5str . $key . "=" . $val . "&";
        // }
        // $fxkey='i1cf8dq0dw6sqsa81qjvftoepwh2wtv1';
        // $mysign = strtoupper(md5($md5str . "key=" . $fxkey));
        $uid = Db::name('recharge')->where('id', $fxddh)->value('uid');
        $status = Db::name('recharge')->where('order_num', $fxddh)->value('status');

        //     // return json(['code'=>1,'info'=>strval($uid)]);
        //     if ($fxsign == $mysign) {
        if ($fxstatus == 'SUCCESS' && $status == '0') {
            Db::name('recharge')->where('order_num', $fxddh)->update(['status' => 2]);

            $res1 = Db::name('user')->where('id', $uid)->setInc('money', $fxfee);
            // $res2 = Db::name('xy_balance_log')->insert([
            //     'uid'=> $uid,
            //     'oid'=>$fxddh,
            //     'num'=>$fxfee,
            //     'type'=>1,
            //     'status'=>1,
            //     'addtime'=>time()
            // ]);
            echo('SUCCESS');
            // $data=json(['code'=>1,'info'=>'OK!Payment successful!']);

        } else { //支付失败
            echo('fail');
        }

    }

    //充值记录
    public function addrechargelog_api2()
    {
        if (request()->isPost()) {
            //判断是否有选择渠道
            $qudao = input('post.qudao');
            $money = input('post.money');
            if (empty($money)) {
                return json(['status' => 0, 'msg' => 'The recharge amount cannot be empty']);//充值金额不能为空
            }
            //判断是否有选择渠道
            $channel = Db::name('channel')->where('mark', $qudao)->find();//获取渠道信息
            if (empty($channel)) {
                return json(['status' => 0, 'msg' => 'Channel cannot be empty']);//渠道不能为空
            }
            //判断是否满足该渠道的最低充值额度和最高充值额度
            if ($channel['recharge_lowest'] > $money) {
                return json(['status' => 0, 'msg' => 'The recharge amount cannot be less than' . $channel['recharge_lowest']]);//充值金额不能小于
            }

            if ($channel['recharge_highest'] < $money) {
                return json(['status' => 0, 'msg' => 'The recharge amount cannot be greater than' . $channel['recharge_highest']]);//充值金额不能大于
            }

            $conf = Db::name('config')->where('id', 1)->find();
            $uid = session('uid');
            $uinfo = Db::name('user')->where('id', $uid)->find();
            $oderNum = 'DD' . time() . rand(100, 99999);

            //判断使用哪个通道
            if ($channel['mark'] == 'Ydpay') {
                // echo(print_r('xx'));
                // die();
                $mer_no = 'gm761100000033104';
                $mer_order_no = $oderNum;
                $url = 'http://gyials.gdsua.com/ty/orderPay';
                $key = 'EE7DBB17BF1B237973FAAD1EAF6A5AA6';
                $data = [
                    "mer_no" => $mer_no,
                    "mer_order_no" => $mer_order_no,
                    "pname" => "easypowe",
                    "pemail" => "test@gmail.com",
                    "phone" => "13122336688",
                    "order_amount" => $money,
                    "countryCode" => "",
                    "ccy_no" => "",
                    "timeout_express" => "",
                    "busi_code" => "",
                    "goods" => "",
                    "notifyUrl" => "",
                    "bankCode" => "",
                    "pageUrl" => "",
                    "sign" => ""
                ];

                $data2 = [
                    "pay_memberid" => 'yBV3fJRRNuHQ',//商户号
                    "pay_orderid" => $oderNum,//商户订单号
                    "pay_amount" => $money,//金额
                    "pay_applytime" => time(),//交易时间，时间戳
                    "pay_type" => 'UPI',//支付类型
                    "pay_notifyurl" => 'http://' . $conf['web_link'] . '/payment/Ydpay/notifyurl.html',//回调地址
                    "pay_returnurl" => 'http://' . $conf['web_link'] . '/payment/Ydpay/callbackurl.html',//返回地址
                    "pay_name" => $uinfo['truename'] ? $uinfo['truename'] : $uinfo['username'],//姓名
                    "pay_mobile" => $uinfo['username'],//手机号
                    "pay_email" => 'cheshi@163.com',//邮箱
                ];
                //签名获得方式
                ksort($data);
                $md5str = '';
                foreach ($data as $key => $val) {
                    if (!empty($val)) {
                        $md5str .= $key . '=' . $val . '&';
                    }
                }
                $apiKey = "mmjrt4fpz7gfosee25dog3jcr48qlayk";
                $data['pay_sign'] = strtoupper(md5($md5str . "key=" . $apiKey));
                $data['uid'] = session('uid');
                $data['url'] = 'https://apipay213.ybbpay.net/pay';
                Db::name('ydpay_chongzhi')->insert($data);
            }

            $map['uid'] = $uid;//用户id
            $map['username'] = $uinfo['username'];//充值用户账号
            $map['money'] = $money;//充值金额
            $map['upi'] = 'UPI';//充值UPI
            $map['addtime'] = time();//充值提交时间
            $map['status'] = 0;//待审核
            $map['order_num'] = $oderNum;//订单号
            $map['money_front'] = $uinfo['money'];//充值前金额
            $map['money_after'] = $uinfo['money'] + $money;//充值后金额
            $map['channel'] = $channel['mark'];//通道标签
            Db::name('recharge')->insert($map);
            return json(['status' => 1, 'order' => $oderNum]);
        }
    }

    function http_post_json($url, $jsonStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response;
    }

    //隐藏提交表单
    public function postfrom()
    {
        $order = input('order');
        $uid = session('uid');
        $one = Db::name('ydpay_chongzhi')->where('pay_orderid', $order)->where('uid', $uid)->find();
        if ($one) {
            return View('postfrom', [
                'one' => $one,
            ]);
        }
    }

    //我的收益
    public function award()
    {
        $uid = session('uid');
        $user = Db::name('user')->where('id', $uid)->find();
        $list = Db::name('jinbidetail')->where('uid', $uid)->order('addtime desc')->select();
        $yue = $user['money'];//用户余额
        $shouyi = Db::name('order')->where('user_id', $uid)->sum('already_profit');//收益
        $yiti = Db::name('withdrawal')->where(['uid' => $uid, 'status' => 1])->sum('money');//提现
        return View('award', [
            'list' => $list,
            'yue' => $yue,
            'shouyi' => $shouyi,
            'yiti' => $yiti,
        ]);
    }

    //下级充值
    public function xiajichongzhi()
    {
        $type = input('param.type') ? input('param.type') : 1;
        $uid = session('uid');
        //直推充值
        $map_str = '';
        $son1_username = Db::name('user')->where('parent_id', $uid)->field('username,id')->select()->toArray();
        foreach ($son1_username as $li) {
            $map_str .= $li['id'] . ',';
        }

        //二级充值
        if ($type == 2) {
            $map_str = '';
            foreach ($son1_username as $lis) {
                $son2_username = Db::name('user')->where('parent_id', $lis['id'])->field('username,id')->select()->toArray();
                $map_str .= $li['id'] . ',';
            }
        }

        //三级充值
        if ($type == 3) {
            $map_str = '';
            foreach ($son1_username as $lis) {
                $son2_username = Db::name('user')->where('parent_id', $lis['id'])->field('username,id')->select()->toArray();
                foreach ($son2_username as $liss) {
                    $son3_username = Db::name('user')->where('parent_id', $liss['id'])->field('username,id')->select()->toArray();
                    foreach ($son3_username as $li) {
                        $map_str .= $li['id'] . ',';
                    }
                }
            }
        }
        $map_str = substr($map_str, 0, -1);
        $map['uid'] = ['in', $map_str];
        $map['status'] = 1;
        $list = Db::name('recharge')->where($map)->select();
        return View('xiajichongzhi', [
            'type' => $type,
            'list' => $list,
        ]);
    }

    //下载地址获取
    public function downurl()
    {
        $phoneSys = input('post.phoneSys');
        if ($phoneSys == 'IOS') {
            $url = Db::name('proconfig')->value('apple_download');
        } else {
            $url = Db::name('proconfig')->value('android_download');
        }
        return json(['status' => 1, 'url' => $url]);
    }

    //vip购买
    public function vip_upgrade()
    {

        if (request()->isPost()) {
            $level = input('param.level');
            $one = Db::name('level')->where('level', $level)->find();//等级信息
            $price = $one['price'];//会员价格
            $lvname = $one['lv_name'];//会员等级名称
            $uid = session('uid');
            $userinfo = Db::name('user')->where('id', $uid)->find();
            //判断用户余额是否够买会员
            if ($userinfo['money'] < $price) {
                return json(['status' => 0, 'msg' => lang('wa_balance_insufficient')]);//余额不足，请充值后再购买
            }
            //不能购买比当前等级还小的会员等级
            if ($userinfo['lv_id'] >= $level) {
                return json(['status' => 0, 'msg' => lang('wa_membership_failed')]);//不能购买小于当前的会员等级
            }

            //更新用户等级以及购买会员后减少的余额
            $param['lv_id'] = $level;
            $param['money'] = $userinfo['money'] - $price;
            Db::name('user')->where('id', $uid)->update($param);
            //写入记录
            $map['username'] = $userinfo['username'];
            $map['reduce'] = $price;
            $map['balance'] = $userinfo['money'] - $price;
            $map['addtime'] = time();
            $map['desc'] = lang('wa_membership_failed') . $lvname;//升级为
            $map['uid'] = $uid;
            Db::name('jinbidetail')->insert($map);

            //判断该用户顶级是否为代理
            if ($userinfo['parentpath']) {
                $arr = explode("|", $userinfo['parentpath']);//字符串转数组
                $tid = $arr[0];//获取代理id
                $res = Db::name('user')->where(['id' => $tid, 'is_agent' => 1])->find();
                //更新代理的收益
                if ($res) {
                    //获取项目配置信息
                    $config = Db::name('proconfig')->where('id', 1)->find();
                    $shouyi = $price * $config['agent_profit'] / 100;
                    Db::name("user")->where('id', $tid)->inc('money', $shouyi)->update();
                    //写入记录
                    $tmap['username'] = $res['username'];
                    $tmap['adds'] = $shouyi;
                    $tmap['balance'] = $res['money'] + $shouyi;
                    $tmap['addtime'] = time();
                    $tmap['desc'] = lang('wa_huiyuan_fee');//下线会员充值奖励
                    $tmap['uid'] = $res['id'];
                    $tmap['type'] = 1; //收益类型为 代理收益
                    Db::name('jinbidetail')->insert($tmap);
                }
            }
            return json(['status' => 1, 'msg' => lang('wa_success_purchase')]);//购买成功
        }
        $one = Db::name('user')->where('id', session('uid'))->find();
        $lvarr = Db::name('level')->where('level', '>', 1)->order('level asc')->select();
        return View('vip_upgrade', [
            'lvarr' => $lvarr,
            'one' => $one,
        ]);
    }

    public function pay()
    {
        $uid = session('uid');
        $chanel = $_POST;

        $pay_on = $this->getSn('bank');

        $uinfo = Db::name('user')->where('id', $uid)->find();

        $res = Db::name('recharge')
            ->insertGetId([
                'order_num' => $pay_on,
                'uid' => $uid,
                'username' => $uinfo['username'],
                'money_front' => $uinfo['money'],
                'money_after' => $uinfo['money'] + $chanel['money'],
                'type' => 1,
                'money' => $chanel['money'],
                'addtime' => time(),
                'bank_info' => $chanel['bank_info'],
                'bank_username' => $chanel['bank_username'],
                'pay_voucher' => $chanel['pay_voucher'],
                'channel' => 'bank',
            ]);
    }

    /**
     * NibPay
     */
    public function NibPay($money, $chanel)
    {
        $data['pay_memberid'] = '10491'; // 商户号
        $data['pay_orderid'] = $this->getSn('B'); // 商户订单号
        $data['pay_bankcode'] = '955';               //通道类型
        $data['pay_amount'] = $money; // 金额
        $data['pay_notifyurl'] = 'https://www.easypowe.com/payment/SpayNotify/notifyB';
        $data['pay_getip'] = '127.0.0.1';
        $data['pay_attach'] = 'ceshi';
        $Md5key = 'w483z3ecz7kitmi7t5eaan7josy3zakm';
        $url = 'https://pro.payscheap.com/api.html';
        ksort($data);
        $sign_data = $data;
        $str = '';
        foreach ($sign_data as $i => $v) {
            $str .= $i . '=' . $v . '&';
        }
        $str .= 'key=' . $Md5key;
        $data['sign'] = md5($str);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $info = json_decode(curl_exec($ch), true);
        curl_close($ch);
        payLog('订单' . $data['pay_orderid'] . '支付请求会返回值:' . json_encode($info));
        if ($info['code'] == 'success') {
            $uid = session('uid');
            $uinfo = Db::name('user')->where('id', $uid)->find();
            Db::startTrans();
            $res = Db::name('recharge')
                ->insertGetId([
                    'order_num' => $data['pay_orderid'],
                    'uid' => $uid,
                    'username' => $uinfo['username'],
                    'money_front' => $uinfo['money'],
                    'money_after' => $uinfo['money'] + $money,
                    'type' => 1,
                    'money' => $money,
                    'addtime' => time(),
                    'channel' => $chanel
                ]);
            if ($res) {
                Db::commit();
                echo $info['data']['payurl'];
            } else {
                Db::rollback();
            }
        }

    }

    public function sepro_pay($data)
    {
        exit;
        $mch_id = '944944111';//测式
//        $mch_id = '600005061';//正式
        $merchant_key = "c439ef5bbaf643f982701790ec6ab37e";//测试
//        $merchant_key ="ILAM5RXVF0LKCEMLYJXZJTVC3PG0Z5WX";//正式
        $notify_url = 'https://www.easypowe.com/payment/SpayNotify/notify';
        $mch_order_no = $this->getSn('C');
        $pay_type = '620';
        $trade_amount = $data['money'];
        $order_date = date('Y-m-d H:i:s', time());
        $goods_name = 'good' . $mch_order_no;
        $sign_type = 'MD5';
        $signStr = [
            'version' => '1.0',
            'mch_id' => $mch_id,
            'notify_url' => $notify_url,
            'mch_order_no' => $mch_order_no,
            'pay_type' => $pay_type,
            'trade_amount' => $trade_amount,
            'order_date' => $order_date,
            'goods_name' => $goods_name,
        ];
        $md5str = $this->ASCII($signStr);
        $sign = md5($md5str . '&key=' . $merchant_key);
        $reqUrl = 'https://pay.sepropay.com/sepro/pay/web';
        $signStr['sign_type'] = $sign_type;
        $signStr['sign'] = $sign;
        $time = date('Y-m-d H:i:s', time());
        $str = $time . '支付通道C发起请求：' . json_encode($signStr);
        payLog($str);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $reqUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($signStr));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($ch), true);
        payLog('通道C支付请求返回的值：' . json_encode($response));
        dd($response);
        curl_close($ch);
        $uid = session('uid');
        $uinfo = Db::name('user')->where('id', $uid)->find();
        Db::startTrans();
        $res = Db::name('recharge')
            ->insert([
                'order_num' => $mch_order_no,
                'uid' => $uid,
                'username' => $uinfo['username'],
                'money_front' => $uinfo['money'],
                'money_after' => $uinfo['money'] + $trade_amount,
                'type' => 1,
                'money' => $trade_amount,
                'addtime' => time(),
                'channel' => 'pix'
            ]);
        if ($response['respCode'] == 'SUCCESS' || $res) {
            Db::commit();
            echo $response['payInfo'];
        } else {
            Db::rollback();
            echo 'Canal retorna mensagem de erro：' . $response['tradeMsg'];
        }
    }

    public function serpaySing($signSource, $key)
    {
        if (!empty($key)) {
            $signSource = $signSource . "&key=" . $key;
        }
        return md5($signSource);
    }

    public function http_post_res($url, $data)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 5.1; zh-CN) AppleWebKit/535.12 (KHTML, like Gecko) Chrome/22.0.1229.79 Safari/535.12");
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function http_post($url, $data)
    {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
//                'header' => 'Content-Encoding : gzip',
                'content' => http_build_query($data),
                'timeout' => 15 * 60
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }

    function ASCII($params = array())
    {
        if (!empty($params)) {
            $p = ksort($params);
            if ($p) {
                $str = '';
                foreach ($params as $k => $val) {
                    $str .= $k . '=' . $val . '&';
                }
                $strs = rtrim($str, '&');
                return $strs;
            }
        }
        return '参数错误';
    }

    public function send_post($url, $post_data)
    {
        ksort($post_data);
        $postdata = json_encode($post_data);
//        $postdata = "reqData=" . $postdata;
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type:application/x-www-form-urlencoded",
                'content' => $postdata
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }

    public function redirect()
    {
        if (isset($_GET['url'])) {
            $url = $_GET['url'];
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec($ch);
            curl_close($ch);
            $data = str_replace('/pay_generateqrcode.html', '/index/Wallet/redirecta.html?url=http://47.243.97.237:9090/pay_generateqrcode.html', $data);
            echo $data;
        }

    }

    public function redirecta()
    {
        if (isset($_GET['url'])) {
            $url = $_GET['url'];
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec($ch);
            curl_close($ch);

            echo $data;
        }
    }

    public function CCpay($money, $chanel)
    {
        $appid = 's76VRt9a5ZTH3yGeldID';
        $s_key = '59fb74554b2bb1a66616e9b06b09d9b6';
        $url = 'https://5.haoz.in/api/recharge';
        $post_data = [
            'appid' => $appid,
            'mer_order_no' => $this->getSn('B'),
            'amount' => $money,
            'currency' => 'BRL',
            'notifyUrl' => 'https://www.easypowe.com/payment/SpayNotify/notifyCC',
            'returnUrl' => 'https://www.easypowe.com/index/Wallet/index.html',
        ];
        $post_data['sign'] = md5($post_data['mer_order_no'] . $post_data['amount'] . $s_key);

        $res = $this->http_post($url, $post_data);
        $info = json_decode($res, true);
        if ($info['status'] == 'success') {
            $uid = session('uid');
            $uinfo = Db::name('user')->where('id', $uid)->find();
            Db::startTrans();
            $res = Db::name('recharge')
                ->insertGetId([
                    'order_num' => $post_data['mer_order_no'],
                    'uid' => $uid,
                    'username' => $uinfo['username'],
                    'money_front' => $uinfo['money'],
                    'money_after' => $uinfo['money'] + $money,
                    'type' => 1,
                    'money' => $money,
                    'addtime' => time(),
                    'channel' => $chanel
                ]);

            if ($res) {
                Db::commit();
                echo $info['url'];
            } else {
                Db::rollback();
            }
        }

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

//	==============================================  新支付通道

    /**
     * 新支付通道
     */
    public function rusPay()
    {
        $amount = input('amount');
        $zsamount = input('zsamount');
        $pay_type = PayConfig::getPayType();
        if ($pay_type == 1) {
            Pay::pay_one($amount, $zsamount);
        } else {
            Pay::pay_two($amount, $zsamount);
        }
    }
}

