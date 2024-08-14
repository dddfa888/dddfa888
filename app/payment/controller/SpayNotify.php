<?php


namespace app\payment\controller;

use think\facade\Db;

class SpayNotify
{
    //代收回调
    public function notify(){
        exit('11');
     $data = $_REQUEST;
     file_put_contents('./runtime/log/sepro_pay.txt','回调'.json_encode($data)."\r\n",FILE_APPEND);
     $merchant_key = "ILAM5RXVF0LKCEMLYJXZJTVC3PG0Z5WX";//正式
//   $merchant_key = "9a99a8f74b82405e91a93f597231603e"; //测试

     $amount = $_POST["amount"]; //金额
     $mchId = $_POST["mchId"]; //商户号
     $mchOrderNo = $_POST["mchOrderNo"]; //商家订单号
     $orderDate = $_POST["orderDate"];   //订单时间
     $orderNo = $_POST["orderNo"];       //平台支付订单号
     $oriAmount = $_POST["oriAmount"];   //原始订单金额
     $tradeResult = $_POST["tradeResult"]; //订单状态  1支付成功
     $signType = $_POST["signType"];     //签名规则
     $sign = $_POST["sign"];  //签名

     $signStr = [
         'amount' => $amount,
         'mchId'  => $mchId,
         'mchOrderNo' => $mchOrderNo,
         'orderNo' => $orderNo,
         'orderDate' => $orderDate,
         'oriAmount' => $oriAmount,
         'tradeResult' => $tradeResult
     ];
//     $flag = $this->ASCII($signStr);

     if($tradeResult == 1){
         if($this->notify_payok($mchOrderNo,$oriAmount)){
             echo "success";
         }else{
             echo "Signature error";
         }
     }else{
         echo "Signature error";
     }
 }

    public function notify_payok($mchOrderNo){
         $data = Db::name('recharge')->where('order_num',$mchOrderNo)->find();
         if($data['status'] == '0'){
             Db::startTrans();
             $rs = Db::name('recharge')->where('order_num',$mchOrderNo)->update(['status'=>2]);
             $rsa = Db::name('user')->where('id',$data['uid'])->update('money',$data['money_after']);
             if($rs || $rsa){
                 Db::commit();
                 return true;
             }else{
                 Db::rollback();
                 return false;
             }
         }else{
             return true;
         }
     }

//代付回调Pay C
    public function withDrawnNotify(){
        $data = $_REQUEST;
        file_put_contents('./runtime/log/sepro_pay.txt','代付回调'.json_encode($data)."\r\n",FILE_APPEND);
        $res =Db::name('withdrawal')->where('order_num',$data['merTransferId'])->find();
        if($res || $res['status'] == 0) {
            if ($data['respCode'] == 'SUCCESS' || $data['tradeResult'] == 1) {
                $aa = Db::name('withdrawal')->where('id',$res['id'])->update(['status'=>1]);
                if($aa){
                    echo 'SUCCESS';
                }else{
                    echo '111';
                }
            }
        }else{
            echo '';
        }
    }

    public function PayNotify(){
        $data = $_REQUEST;
        file_put_contents('./runtime/log/sepro_pay.txt','Pay A代付回调'.json_encode($data)."\r\n",FILE_APPEND);

    }

    //日禾回调
    public function rhNotify(){
        $data = file_get_contents('php://input');
        $data = urldecode(urldecode($data));
        payLog('日禾支付异步回调:'.$data);
        $data = substr($data,8);
        $res = json_decode($data,true);
        Db::startTrans();
        if($res['ret_code'] == '00'){
            $rech = Db::name('recharge')->where('order_num',$res['out_trade_no'])->find();
            payLog('日禾支付订单数据:'.json_encode($rech));
            $user = Db::name('user')->where('id',$rech['uid'])->find();
            payLog('日禾支付用户信息:'.json_encode($user));
            if($rech['status'] < 1){
                $upData = ['status'=>1,'order_no'=>$res['order_no']];
                $rs = Db::name('recharge')->where('id',$rech['id'])->save($upData);
                $udata = ['money'=>$user['money'] + $res['trans_amt']];
                $rss = Db::name('user')->where('id',$rech['uid'])->save($udata);
                if($rs || $rss){
                    Db::commit();
                    echo 'SUCCESS';
                }else{
                    echo '';
                    Db::rollback();
                }
            }
            if($rech['status'] == 1){
                echo 'SUCCESS';
                Db::rollback();
            }
        }




    }

    public function notifyB(){
        $data = file_get_contents('php://input');
        payLog('Pay B支付回调数据:'.$data);
        $res = explode('&',$data);
        $ress['code'] = substr($res['0'],5);
        $ress['data'] = json_decode(substr($res['2'],5),true);
        Db::startTrans();
        if($ress['code'] == 'success'){
            $rech = Db::name('recharge')->where('order_num',$ress['data']['orderid'])->find();
            payLog('PayB订单数据:'.json_encode($rech));
            $user = Db::name('user')->where('id',$rech['uid'])->find();
            payLog('PayB用户信息:'.json_encode($user));

            if($rech['status'] == 1){
                payLog('PayB次订单已经更改过:'.json_encode($rech));
                Db::rollback();
                return 'success';
            }

            if($rech['status'] < 1){
                $upData = ['status'=>1,'order_no'=>$ress['data']['tradeid']];
                $rs = Db::name('recharge')->where('id',$rech['id'])->save($upData);
                $udata = ['money'=>$user['money'] + $ress['data']['money']];
                $rss = Db::name('user')->where('id',$rech['uid'])->save($udata);
                if($rs || $rss){
                    payLog('PayB更改用户金额成功:'.json_encode($rech));
                    Db::commit();
                    return 'success';
                }else{
                    payLog('PayB更改用户数据失败:'.json_encode($rech));
                    Db::rollback();
                    return 'file';

                }
            }
            
        }


    }

    public function notifyCC(){
        $data = file_get_contents('php://input');
        payLog('CCpay支付回调数据:'.$data);
        parse_str($data,$ress);
        Db::startTrans();
        $rech = Db::name('recharge')->where('order_num',$ress['mer_order_no'])->find();
            payLog('PayB订单数据:'.json_encode($rech));
        $user = Db::name('user')->where('id',$rech['uid'])->find();
            payLog('PayB用户信息:'.json_encode($user));
        if($ress['status'] == 'Success'){
            if($rech['status'] == 1){
                payLog('PayB次订单已经更改过:'.json_encode($rech));
                Db::rollback();
                return 'success';
            }
            if($rech['status'] < 1){
                $upData = ['status'=>1,'order_no'=>$ress['order_no']];
                    $rs = Db::name('recharge')->where('id',$rech['id'])->save($upData);
                $udata = ['money'=>$user['money'] + $ress['amount']];
                    $rss = Db::name('user')->where('id',$rech['uid'])->save($udata);
                if($rs || $rss){
                    payLog('ccpay更改用户金额成功:'.json_encode($rech));
                    Db::commit();
                    return 'success';
                }else{
                    payLog('ccpay更改用户数据失败:'.json_encode($rech));
                    Db::rollback();
                    return 'file';

                }
            }

        }else if($ress['status'] == 'Error'){
            if($rech['status'] == 1){
                payLog('PayB次订单已经更改过:'.json_encode($rech));
                Db::rollback();
                return 'success';
            }

            if($rech['status'] < 1){
                $upData = ['status'=>2,'order_no'=>$ress['mer_order_no']];
                $rs = Db::name('recharge')->where('id',$rech['id'])->save($upData);
                if($rs){
                    payLog('ccpay更改订单状态为失败:'.json_encode($rech));
                    Db::commit();
                    return 'success';
                }else{
                    payLog('ccpay更改用户数据失败:'.json_encode($rech));
                    Db::rollback();
                    return 'file';
                }
            }
        }else if($ress['status'] == 'Pending'){
            return 'success';
        }

    }

    public function ccpay_d(){
        $data = file_get_contents('php://input');
        payLog('CCpay代付回调数据:'.$data);
        parse_str($data,$ress);
        $res =Db::name('withdrawal')->where('order_num',$ress['mer_order_no'])->find();
        $user = Db::name('user')->where('id',$res['uid'])->find();
        if($res || $ress['status'] =='Success'){
            $aa = Db::name('withdrawal')->where('id',$res['id'])->update(['status'=>1]);
//            $udata = ['money'=>$user['money'] - $ress['amount']];
//            payLog('CCpay代付金额减去用户金额:'.$ress['amount'].'总金额'.$user['money']);
//            $rss = Db::name('user')->where('id',$res['uid'])->save($udata);
            if($aa){
                return 'success';
            }else{
                return 'fall';
            }
        }

        if($res ||　ress['status'] =='Error'){
            $aa = Db::name('withdrawal')->where('id',$res['id'])->update(['status'=>4]);
            if($aa){
                return 'success';
            }else{
                return 'fall';
            }
        }


    }

    public function dukNotify(){
        $data = file_get_contents('php://input');
        payLog('dukpay代收调数据:'.$data);
        $ress = json_decode($data,true);
        Db::startTrans();
        //$rech = Db::name('recharge')->where('order_num',$ress['trade_no'])->find();
        $rech = Db::name('recharge')->where('order_num',$ress['order_no'])->find();
        $user = Db::name('user')->where('id',$rech['uid'])->find();
        if($ress['status'] == '4'){
            if($rech['status'] == 1){
                Db::rollback();
                return 'SUCCESS';
            }
            if($rech['status'] < 1){
                $upData = ['status'=>1,'order_no'=>$ress['order_no']];
                $rs = Db::name('recharge')->where('id',$rech['id'])->save($upData);
                $udata = ['money'=>$user['money'] + $ress['money']];
                $rss = Db::name('user')->where('id',$rech['uid'])->save($udata);
                if($rs || $rss){
                    payLog('dukpay更改用户金额成功:'.json_encode($rech));
                    Db::commit();
                    return 'SUCCESS';
                }else{
                    payLog('dukpay更改用户数据失败:'.json_encode($rech));
                    Db::rollback();
                    return 'file';
                }
            }
        }
    }

    public function duk_dNotify(){
        $data = file_get_contents('php://input');
        payLog('dukpay代付回调数据:'.$data);
        $ress = json_decode($data,true);
        $res = Db::name('withdrawal')->where('order_num',$ress['mer_order_no'])->find();
        $user = Db::name('user')->where('id',$res['uid'])->find();
        if($res || $ress['status'] == 4){
            $aa = Db::name('withdrawal')->where('id',$res['id'])->update(['status'=>1]);
            if($aa){
                return 'SUCCESS';
            }else{
                return 'fall';
            }
        }

        if($ress['status'] == '2'){
            $aa = Db::name('withdrawal')->where('id',$res['id'])->update(['status'=>4]);
            if($aa){
                return 'SUCCESS';
            }else{
                return 'fall';
            }
        }
    }

}