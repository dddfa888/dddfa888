<?php
namespace app\index\controller;
use think\facade\Db;
use think\facade\View;
use think\facade\Lang;


class Notify {
     /****异步回调代收订单****/
    public function pay_notify()
    {
        // $fxid = $_REQUEST['memberid'];
        $fxddh = $_POST['mer_order_no']; 
        // $fxorder = $_REQUEST['transaction_id']; 
        // $fxdesc = $_REQUEST['attach']; //商品名称
        $fxfee = $_POST['pay_amount']; //交易金额
        // $fxattch = $_REQUEST['attach']; //附加信息
        $fxstatus = $_POST['status']; //订单状态
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
        $uid = Db::name('recharge')->where('id',$fxddh)->value('uid');
        $status = Db::name('recharge')->where('order_num',$fxddh)->value('status');
        
    //     // return json(['code'=>1,'info'=>strval($uid)]);
    //     if ($fxsign == $mysign) {
            if ($fxstatus =='SUCCESS' && $status=='0') {
                Db::name('recharge')->where('order_num',$fxddh)->update(['status'=>2]);
                
                    $res1 = Db::name('user')->where('id',$uid)->setInc('money',$fxfee);
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

    
    
}