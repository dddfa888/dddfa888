<?php

namespace app\dingadmin\controller;

use gmspay\gmspay;
use think\facade\Db;
use think\facade\View;
use app\common\NibPayout;
use function Couchbase\defaultDecoder;

//提现管理
class Withdrawal extends Base
{

  public function index()
  {

    $oModel = Db::name('Withdrawal');
    $username = input("param.username");
    $status = input("param.status",-1);

    if ($username != '') {
      $oModel->wherelike('username', '%' . $username . '%');
      View::assign('username', $username);
    } else {
      View::assign('username', '');
    }

    if ($status != -1) {
      $oModel->where('status', $status);
      View::assign('status', $status);
    } else {
      View::assign('status', -1);
    }

    $ids_arr = $this->getAgetnIds();
    if(!empty($ids_arr)){
      $oModel->whereIn('u.parent_code', $ids_arr);
    }



    $banklist = Db::name('Bankcard')->select()->toArray();

    $bank = array_column($banklist, 'email', 'userid');
    $list = $oModel->alias('l')
      ->field('l.*')
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->order('addtime desc')->paginate(30);
    $page = $list->render();


    return view('index', [
      'list' => $list,
      'page' => $page,
      'bank' => $bank
    ]);

  }

  // //提现审核操作
  // public function operate(){
  // 	if (request()->isPost()) {
  // 		$id = input('post.id');
  // 		$uid = input('post.uid');
  // 		$status = input('post.status');
  // 		$money = input('post.money');//申请提现金
  // 		$payment = input('post.payment');//真实提现金额

  // 		$user = Db::name('user')->where('id',$uid)->find();

  // 		//审核通过
  // 		if ($status ==1) {
  // 			$map['status'] = $status;
  // 			$res = Db::name('Withdrawal')->where('id',$id)->update($map);

  //             if ($res) {
  //             	//这里不用扣余额 因为用户前端申请提现时已经扣除
  //             	//写入充值记录
  //                 $params['username'] = $user['username'];
  //                 $params['reduce'] = $money;
  //                 $params['balance'] = $user['money'] - $money;
  //                 $params['addtime'] = time();
  //                 // $params['desc']    = '客户提现';
  //                 $params['desc']    = 'Withdraw';
  //                 $params['uid']    = $uid;
  //                 Db::name('jinbidetail')->insert($params);
  //             	return json(['status'=>1,'msg'=>'操作成功']);
  //             }else{
  //             	return json(['status'=>0,'msg'=>'操作失败']);
  //             }
  //         //审核驳回
  // 		}else{
  // 			$map['status'] = $status;
  // 			$res = Db::name('Withdrawal')->where('uid',$uid)->update($map);
  // 			if ($res) {
  // 				//用户提现金额返回到用户余额中
  // 				Db::name('user')->where('id',$uid)->dec('money',$money)->update();
  // 				return json(['status'=>1,'msg'=>'操作成功']);
  // 			}else{
  // 				return json(['status'=>0,'msg'=>'操作失败']);
  // 			}
  // 		}
  //        }
  // 	$id = input('id');
  // 	$one = Db::name('Withdrawal')->find($id);
  // 	return view('operate',[
  // 		'one'=>$one,
  //        ]);
  // }
  //pix提现审核操作
  public function review()
  {
    $id = input('param.id');//获取提现表id
    if (empty($id)) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }
    $oinfo = Db::name('withdrawal')->where('id', $id)->find();
    $bkinfo = Db::name('bankcard')->where('userid', $oinfo['uid'])->find();
    //判断是否存在改条信息
    if ($oinfo) {
      $store_id = '9zzbaxi';
//                $mch_id    = '9zzbaxi90529591'; //代付专线
      $mch_id = '9zzbaxi14743199';   //代付代收
      $notifyUrl = 'http://easypowetest.com/payment/SpayNotify/PayNotify';
      $post_data = array(
        'store_id' => $store_id,//机构号
        'mch_id' => $mch_id,//交易商户号
        'notify_url' => $notifyUrl,//异步通知,post
        'withdraw_no' => $oinfo['order_num'],//订单号
        'bank_province' => $bkinfo['bank_province'] ? $bkinfo['bank_province'] : 1, //开户行省
        'bank_city' => $bkinfo['bank_city'] ? $bkinfo['bank_city'] : 1,//开户行市
        'bank_name' => $bkinfo['bank_name'] ? $bkinfo['bank_name'] : 1,//开户支行
        'bank_type' => (string)$bkinfo['bank_type'],
        'account_name' => $bkinfo['account_name'],
        'account_no' => $bkinfo['account_no'],
        'withdraw_amt' => $oinfo['money'],
        'bank_no' => (string)$bkinfo['bank_code'],
        'bank_p_name' => $bkinfo['bank_p_name'],
        'bank_english_name' => $bkinfo['bank_english_name'],
        'bank_num' => $bkinfo['bank_num'],
        'memo2' => $bkinfo['bank_type'] == 2 ? 'TIX' : 'TED'
      );
//            dd($post_data);
//            $post_data = array_filter($post_data);
      $sign_data = $this->getSignString($post_data);
      $publicKen = public_path() . 'key/pivkey.pem';
      $sign = $this->getSign($sign_data, $publicKen);
      $post_data['sign'] = $sign;
      $info = $this->send_post('http://43.255.31.21:8089/zhifpops/accountWithdraw', $post_data);
      $info = json_decode(urldecode($info), true);
      if ($info['ret_code'] === '00') {
        if ($oinfo['status'] == '0') {
          Db::name('withdrawal')->where('id', $id)->update(['status' => '3']);
        }
        return json(['status' => 1, 'msg' => '申请成功！']);
      } else {
        return json(['status' => 0, 'msg' => $info['ret_msg']]);
      }
    } else {
      return json(['status' => 0, 'msg' => '信息不存在']);
    }
  }

  public function chick_withdrawal()
  {
    $id = input('param.id');
    if (empty($id)) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }
    $oinfo = Db::name('withdrawal')->where('id', $id)->find();
    $post_data = [
      'store_id' => '9zzbaxi',
      'mch_id' => '9zzbaxi90529591',
      'withdraw_no' => $oinfo['order_num'],
    ];
    $post_data = array_filter($post_data);
    $sign_data = $this->getSignString($post_data);
    $url = 'http://43.255.31.21:8089/zhifpops/accountWithdrawQuery';
    $publicKen = public_path() . 'key/pivkey.pem';
    $post_data['sign'] = $this->getSign($sign_data, $publicKen);
    $info = $this->send_post($url, $post_data);
    $info = json_decode(urldecode($info), true);
    if ($info['ret_code'] == '00') {
      if ($oinfo['status'] == '3') {
        Db::name('withdrawal')->where('id', $id)->update(['status' => '1']);
        return json(['status' => 1, 'msg' => '查询成功！']);
      }
    } else {
      return json(['status' => 0, 'msg' => $info['ret_msg']]);
    }

  }

  public function reviewC()
  {
    exit(11);
    $res = $this->request->param();
    if (empty($res['id'])) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }
    $oinfo = Db::name('withdrawal')->where('id', $res['id'])->find();
    $bkinfo = Db::name('bankcard')->where('userid', $oinfo['uid'])->find();
    if ($oinfo) {
      $apply_date = date('Y-m-d H:i:s', time());
      $bank_code = $oinfo['bank_type'];
//          $mch_id = '944944111';//测式
      $mch_id = '600005061'; //正式
      $mch_transferId = $oinfo["order_num"];
      $receive_name = $bkinfo["truename"];
      $transfer_amount = $oinfo["money"];
      $back_url = 'https://www.easypowe.com/payment/SpayNotify/withDrawnNotify';
      $sign_type = 'MD5';
      if ($bank_code == 'PIX') {
        $signStr = [
          'bank_code' => $bank_code,
          'identity_no' => $bkinfo['identity_no'],
          'identity_type' => $bkinfo['identity_type'],
          'mch_id' => $mch_id,
          'mch_transferId' => $mch_transferId,
          'transfer_amount' => $transfer_amount,
          'apply_date' => $apply_date,
          'back_url' => $back_url,
        ];
      } else {
        $signStr = [
          'bank_code' => $bank_code,
          'back_url' => $back_url,
          'mch_id' => $mch_id,
          'mch_transferId' => $mch_transferId,
          'transfer_amount' => $transfer_amount,
          'account_type' => $bkinfo['account_type'],
          'account_digit' => $bkinfo['account_digit'],
        ];
        $signStr = $this->ASCII($signStr);
      }
      $reqUrl = "https://pay.sepropay.com/pay/transfer";
//          $merchant_key ="c439ef5bbaf643f982701790ec6ab37e";//测试
      $merchant_key = "ILAM5RXVF0LKCEMLYJXZJTVC3PG0Z5WX";  //正式
      $sign = md5($signStr . '&key=' . $merchant_key);
      if ($bank_code == 'PIX') {
        $postdata = array(
          'bank_code' => $bank_code,
          'identity_no' => $bkinfo['identity_no'],
          'identity_type' => $bkinfo['identity_type'],
          'mch_id' => $mch_id,
          'mch_transferId' => $mch_transferId,
          'transfer_amount' => $transfer_amount,
          'apply_date' => $apply_date,
          'back_url' => $back_url,
          'sign_type' => $sign_type,
          'sign' => $sign
        );
      } else {
        $postdata = array(
          'bank_code' => $bank_code,
          'back_url' => $back_url,
          'mch_id' => $mch_id,
          'mch_transferId' => $mch_transferId,
          'transfer_amount' => $transfer_amount,
          'account_type' => $bkinfo['account_type'],
          'account_digit' => $bkinfo['account_digit'],
          'sign_type' => $sign_type,
          'sign' => $sign
        );
      }
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $reqUrl);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);
      curl_close($ch);
      echo $response;
    } else {
      return json(['status' => 0, 'msg' => '信息不存在']);
    }
  }

  public function reviewA()
  {
    $res = $this->request->param();
    if (empty($res['id'])) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }
    $oinfo = Db::name('withdrawal')->where('id', $res['id'])->find();
    $bkinfo = Db::name('bankcard')->where('userid', $oinfo['uid'])->find();
    if ($oinfo) {
      Db::name('withdrawal')->where('id', $res['id'])->update(['status' => 1]);
      return json(['status' => 1, 'msg' => '已修改']);;
    } else {
      return json(['status' => 0, 'msg' => '信息不存在']);
    }
  }

  public function CCpay()
  {
    $res = $this->request->param();
    if (empty($res['id'])) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }
    $oinfo = Db::name('withdrawal')->where('id', $res['id'])->find();
    $bkinfo = Db::name('bankcard')->where('userid', $oinfo['uid'])->find();
    if ($oinfo) {
      $appid = 's76VRt9a5ZTH3yGeldID';
      $f_key = 'shsIwZPQL31K2';
      $data = [
        "appid" => $appid,
        "mer_order_no" => $oinfo["order_num"],
        "currency" => "BRL",
        "amount" => $oinfo["payment"],
        "notifyUrl" => 'https://www.easypowe.com/payment/SpayNotify/ccpay_d',
        "name" => $bkinfo['account_name'],
        "phone" => $bkinfo['mobile'],
        "account" => $bkinfo['account_no'],
        "bank" => $bkinfo['bank_type'],
        "card" => $bkinfo['id_number']
      ];
      $data['sign'] = md5($data['mer_order_no'] . $data['amount'] . $f_key);
      $url = 'https://5.haoz.in/api/withdraw';
      $res = $this->Posts($url, $data);
      $info = json_decode($res, true);
      if ($info['status'] == 'success') {
        Db::name('withdrawal')->where('id', $res['id'])->update(['status' => '3']);
        return json(['status' => 1, 'msg' => '申请成功！']);
      } else {
        return json(['status' => 0, 'msg' => $info['msg']]);
      }
    } else {
      return json(['status' => 0, 'msg' => '信息不存在']);
    }
  }


  /**
   * 发起提现需求
   * @return \think\response\Json
   * @throws \think\db\exception\DataNotFoundException
   * @throws \think\db\exception\DbException
   * @throws \think\db\exception\ModelNotFoundException
   */
  public function rgmspay()
  {
    $res = $this->request->param();

    if (empty($res['id'])) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }



    $oinfo = Db::name('withdrawal')->where('id', $res['id'])->find();
    //调取支付接口  需要即时到账
    $order['order_sn'] = $oinfo['order_num'];
    $order['money'] = $oinfo['money'];
    $order['payer_ifsc'] = 'ifsc1001';
    $order['payer_account'] =  $oinfo['card'];
    $order['payer_name'] = $oinfo['truename'];
    $order['bank'] = $oinfo['bank'];
    $order['uid'] = $oinfo['uid'];


    $pay_type = PayConfig::getPayType();
    if($pay_type == 1){
      $res = $this->make_xf($order);
      if ($res['code'] == 20000) {
        return json(['status' => 1, 'msg' => '处理成功！']);
      } else {
        return json(['status' => 0, 'msg' => '没有可用的数据']);
      }
    }else{
      $res = $this->make_xf2($order);
      if ($res['code'] == 000) {
        return json(['status' => 1, 'msg' => '处理成功！']);
      } else {
        return json(['status' => 0, 'msg' => '没有可用的数据']);
      }
    }

  }


  /**
   * 发起提现需求
   * @return \think\response\Json
   * @throws \think\db\exception\DataNotFoundException
   * @throws \think\db\exception\DbException
   * @throws \think\db\exception\ModelNotFoundException
   */
  public function rgmspayB()
  {
    $res = $this->request->param();

    if (empty($res['id'])) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }



    $oinfo = Db::name('withdrawal')->where('id', $res['id'])->find();
    //调取支付接口  需要即时到账
    $order['order_sn'] = $oinfo['order_num'];
    $order['money'] = $oinfo['money'];
    $order['payer_ifsc'] = 'ifsc1001';
    $order['payer_account'] =  $oinfo['card'];
    $order['payer_name'] = $oinfo['truename'];

    $res = Db::name('withdrawal')->where('id', $res['id'])->update(['status' => 4]);
    if ($res) {
      return json(['status' => 1, 'msg' => '处理成功！']);
    } else {
      return json(['status' => 0, 'msg' => '没有可用的数据']);
    }
  }



  public function authAll(){
    $data = $this->request->param();

    if (empty($data['ids'])) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }

    $ids_arr = explode(',',$data['ids']);
    foreach ($ids_arr as $id){
      $oinfo = Db::name('withdrawal')->where('id', $id)->find();
      if(!$oinfo || $oinfo['status'] == 0){
        continue;
      }
      //调取支付接口  需要即时到账
      $order['order_sn'] = $oinfo['order_num'];
      $order['money'] = $oinfo['money'];
      $order['payer_ifsc'] = 'ifsc1001';
      $order['payer_account'] =  $oinfo['card'];
      $order['bank'] =  $oinfo['bank'];
      $order['payer_name'] = $oinfo['truename'];
      $order['uid'] = $oinfo['uid'];

      $pay_type = PayConfig::getPayType();
      if($pay_type == 1){
        $res = $this->make_xf($order);

      }else{
        $res = $this->make_xf2($order);

      }
    }
    return json(['status' => 1, 'msg' => '处理成功！']);


  }


  //提现驳回操作
  public function reject()
  {
    $id = input('param.id');//获取提现表id
    if (empty($id)) {
      return json(['status' => 0, 'msg' => '参数错误']);
    }
    $one = Db::name('withdrawal')->where('id', $id)->find();

    if ($one) {
      $res = Db::name('withdrawal')->where('id', $id)->update(['status' => 2]);
      if ($res) {
        //将驳回的金额退给客户
        $tuifee = $one['money'];
        $uid = $one['uid'];
        Db::name('user')->where('id', $uid)->inc('money', $tuifee)->update();
        return json(['status' => 1, 'msg' => '操作成功']);
      } else {
        return json(['status' => 0, 'msg' => '操作失败']);
      }
    } else {
      return json(['status' => 0, 'msg' => '信息不存在']);
    }
  }

  //提现删除操作
  public function del()
  {
    $id = input('param.id');
    $res = Db::name('withdrawal')->where('id', $id)->delete();
    if ($res) {
      return json(['status' => 1, 'msg' => '删除成功']);
    } else {
      return json(['status' => 0, 'msg' => '删除失败']);
    }
  }

  public function NibPayout($uid, $amount, $mer_order_no)
  {
    $data = Db::name('bankcard')->where('userid', $uid)->find();
    $post_data['amount'] = $amount; // 金额 单位：分
    $post_data['first_name'] = $data['first_name']; // 收款人名字
    if (empty($post_data['first_name'])) {
      return json(['status' => 0, 'msg' => '提现人名字必填']);
    }
    $post_data['last_name'] = $data['last_name']; // 收款人姓氏
    if (empty($post_data['last_name'])) {
      return json(['status' => 0, 'msg' => '提现人姓氏必填']);
    }
    $post_data['sex'] = $data['sex']; // 收款人性别
    if (empty($post_data['sex'])) {
      return json(['status' => 0, 'msg' => '提现人性别必填']);
    }
    $post_data['city'] = $data['city']; // 收款人所在城市
    if (empty($post_data['city'])) {
      return json(['status' => 0, 'msg' => '提现人所在城市必填']);
    }
    $post_data['address'] = $data['address']; // 收款人联系地址
    if (empty($post_data['address'])) {
      return json(['status' => 0, 'msg' => '提现人联系地址必填']);
    }
    $post_data['mobile_area'] = $data['mobile_area']; // 收款人电话区号
    if (empty($post_data['mobile_area'])) {
      return json(['status' => 0, 'msg' => '提现人电话区号必填']);
    }
    $post_data['mobile'] = $data['mobile']; // 收款人电话
    if (empty($post_data['mobile'])) {
      return json(['status' => 0, 'msg' => '提现人电话必填']);
    }
    $post_data['id_type'] = $data['id_type']; // 收款人证件类型
    if (empty($post_data['id_type'])) {
      return json(['status' => 0, 'msg' => '提现人证件类型必填']);
    }
    $post_data['id_number'] = $data['id_number']; // 收款人证件号码
    if (empty($post_data['id_number'])) {
      return json(['status' => 0, 'msg' => '提现人证件号码必填']);
    }
    $post_data['cpf_number'] = $data['cpf_number']; // 收款人CPF号码
    if (empty($post_data['cpf_number'])) {
      return json(['status' => 0, 'msg' => '提现人CPF号码必填']);
    }
    $post_data['id_issue_date'] = $data['id_issue_date']; // 收款人证件签发日期
    if (empty($post_data['id_issue_date'])) {
      return json(['status' => 0, 'msg' => '提现人证件签发日期必填']);
    }
    $post_data['id_expire_date'] = $data['id_expire_date']; // 收款人证件有效日期
    if (empty($post_data['id_expire_date'])) {
      return json(['status' => 0, 'msg' => '提现人证件有效日期必填']);
    }
    $post_data['birth_date'] = $data['birth_date']; // 收款人生日
    if (empty($post_data['birth_date'])) {
      return json(['status' => 0, 'msg' => '提现人生日必填']);
    }
    $post_data['location_id'] = $data['location_id']; // 收款银行识别号
    if (empty($post_data['location_id'])) {
      return json(['status' => 0, 'msg' => '提现人银行识别号必填']);
    }
    $post_data['bank_id'] = $data['bank_id']; // 收款银行编码
    if (empty($post_data['bank_id'])) {
      return json(['status' => 0, 'msg' => '提现人银行编码必填']);
    }
    $post_data['bank_name'] = $data['bank_name']; // 收款银行网点
    if (empty($post_data['bank_name'])) {
      return json(['status' => 0, 'msg' => '提现人银行网点必填']);
    }
    $post_data['bank_account_number'] = $data['bank_account_number']; // 收款银行账户
    if (empty($post_data['bank_account_number'])) {
      return json(['status' => 0, 'msg' => '提现人银行账户必填']);
    }
    $post_data['bank_branch_name'] = $data['bank_branch_name']; // 收款分行名称
    if (empty($post_data['bank_branch_name'])) {
      return json(['status' => 0, 'msg' => '提现人分行名称必填']);
    }
    $post_data['bank_code'] = $data['bank_code']; // 分行代码
    if (empty($post_data['bank_code'])) {
      return json(['status' => 0, 'msg' => '提现人分行代码必填']);
    }
    $post_data['email'] = $data['email']; // 邮箱
    if (empty($post_data['email'])) {
      return json(['status' => 0, 'msg' => '提现人邮箱必填']);
    }
    $nibpay = new NibPayout('https://api.nibpay.com');
    $rs = $nibpay->gatewayPay($post_data);
    $post_data['out_order_id'] = $mer_order_no;
    $post_data['merchant_id'] = '100520000001'; // 商户号
    if ($rs['order_status'] == 1) {
      echo('success');
      Db::name('withdrawal')->where('order_num', $mer_order_no)->update(['status' => 1]);
      exit;
    }
  }

  /**
   * 发送post请求
   * @param string $url 请求地址
   * @param array $post_data post键值对数据
   * @return string
   */
  public function send_post($url, $post_data)
  {
    ksort($post_data);
    $postdata = json_encode($post_data);
    $postdata = "reqData=" . $postdata;
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
  //  加密

  /**
   * 生成签名
   * @param string $signString 待签名字符串
   * @param    [type]     $priKey     私钥
   * @return   string     base64结果值
   */
  public function getSign($signString, $priKey)
  {
    $privKeyId = openssl_pkey_get_private('file://' . $priKey);
    $signature = '';
    openssl_sign($signString, $signature, $privKeyId);
    openssl_free_key($privKeyId);
    return base64_encode($signature);
  }

  public function getSignString($params)
  {
    unset($params['sign']);
    ksort($params);
    reset($params);
    $pairs = array();
    foreach ($params as $k => $v) {
      $pairs[] = "$k=$v";
    }
    return implode('&', $pairs);
  }

  /**
   * 校验签名
   * @param string $pubKey 公钥
   * @param string $sign 签名
   * @param string $toSign 待签名字符串
   * @param string $signature_alg 签名方式 比如 sha1WithRSAEncryption 或者sha512
   * @return   bool
   */
  public function checkSign($pubKey, $sign, $toSign, $signature_alg = OPENSSL_ALGO_SHA1)
  {
    $publicKeyId = openssl_pkey_get_public($pubKey);
    $result = openssl_verify($toSign, base64_decode($sign), $publicKeyId, $signature_alg);
    openssl_free_key($publicKeyId);
    return $result === 1 ? true : false;
  }

  /**
   *私钥解密
   * @param string $sign_str 待加密字符串
   * @param string $sign sign
   * @param string $private_key 私钥
   * @param string $signature_alg 加密方式
   * @return     bool
   */

  public function private_verify($sign_str, $sign, $private_key, $signature_alg = OPENSSL_ALGO_SHA1)
  {
    $private_key = openssl_get_privatekey($private_key);
    $verify = openssl_verify($sign_str, base64_decode($sign), $private_key, $signature_alg);
    openssl_free_key($private_key);
    return $verify == 1;//false or true
  }

  public function getphone()
  {
    static $seed = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
    $str = '';
    for ($i = 0; $i < 9; $i++) {
      $rand = rand(0, count($seed) - 1);
      $temp = $seed[$rand];
      $str .= $temp;
      unset($seed[$rand]);
      $seed = array_values($seed);
    }
    return rand(7, 9) . $str;
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











  // 代收处理
  public function make_xf($order)
  {
    $pay_config = PayConfig::getConfig();

    $mch_id = $pay_config['mch_id'];
    $pay_type = $pay_config['pay_out_type'];
    $token = $pay_config['token'];
    $notify_url = $pay_config['out_notify_url'];


   $brank_info = Db::name('bankcard')->where('userid', $order['uid'])->find();


    $dtTmp = date('Ymd');
    $file = fopen("public/pay/sevenPay_request$dtTmp.txt","a+");
    $dt = date('Y-m-d H:i:s');
    $data = [
      'mch_id' => $mch_id,
      'channel_id' => $pay_type,
      'order_sn' => $order['order_sn'],
      'amount' => intval($order['money']),
      'payer_ifsc' => $brank_info['ifsc'],
      'payer_account' => $brank_info['account_no'],
      'payer_name' => $brank_info['account_name'],
      'notify_url' => $notify_url,
    ];


    //按字典正序排序传入的参数
    ksort($data);
    $sign_str='';
    foreach($data as $pk=>$pv){
      $sign_str.="{$pk}={$pv}&";
    }
    $sign_str.="key={$token}";
    $data['sign'] = md5($sign_str);


    fwrite($file,"\n");
    fwrite($file,json_encode($data));
    fwrite($file,"\n");

    $params_string = json_encode($data);
    $url = 'https://api.ruspay.net/api/Pay/addPayOut'; // Gateway

    fwrite($file, "返回数据new==000=\n");
    fwrite($file, $url);
    fwrite($file, "\n");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($params_string))
    );
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    //execute post
    $request = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $dtTmp = date('Ymd');
    $file = fopen("public/pay/SXF_Globalpay$dtTmp.txt", "a+");
    fwrite($file, "返回数据new==AA=\n");
    fwrite($file, json_encode($request));
    fwrite($file, "\n");
    if($httpCode == 200)
    {
      $result = json_decode($request, true);
      $dtTmp = date('Ymd');
      $file = fopen("SXF_Globalpay$dtTmp.txt", "a+");
      return $result;
    }
  }





  public  function make_xf2($order){

    $url = 'https://api.eggoout.com/payout/unifiedorder.do';
    $pay_config = PayConfig::getConfig();

    $amount = intval($order['money'])*100;
    $uid = session('uid');
    $username = session('username');
    $user = Db::name('user')->where('id', $uid)->find();

    $mch_id = $pay_config['mch_id'];
    $pay_type = $pay_config['pay_out_type'];
    $token = $pay_config['token'];
    $notify_url = $pay_config['out_notify_url'];


    $brank_info = Db::name('bankcard')->where('userid', $order['uid'])->find();


    $dataTmp = [
      'merchantNo' => $mch_id,
      'channelCode' => $pay_type,
      'merchantOrderId' => $order['order_sn'],
      'amount' => $amount,
      'currency' => 'TRY',
      'expireTime' => 60,
      'notifyUrl' => $notify_url,
      'email' => 'abc@gmail.com',
      'userName' => $brank_info['account_name'],
      'mobileNo' => $brank_info['receiver_telephone'],
    ];

    //按字典正序排序传入的参数
    ksort($dataTmp);
    $sign_str='';
    foreach($dataTmp as $pk=>$pv){
      $sign_str.= $pk."=".$pv."&";
    }

    $sign_str.="key={$token}";

    $dataTmp['remark'] = '提现';
    $dataTmp['version'] = '1.0';
    $bankInfo['bankCode'] = $brank_info['ifsc'];// 银行代码，必填
    $bankInfo['bankName'] = $brank_info['bank_name'];// 银行名称，必填
    $bankInfo['cardNumber'] = $brank_info['account_no'];// 银行卡的卡号，必填
    $dataTmp['bankInfo'] = json_encode($bankInfo);



    $hash = hash_hmac('sha256', $sign_str, $token);
    $dataTmp['sign'] = $hash;






    $res = $this->json_post($url,$dataTmp);



    $dtTmp = date('Ymd');
    $file = fopen("public/pay/SXF2_Globalpay$dtTmp.txt", "a+");
    fwrite($file, "返回数据new==AA=\n");
    fwrite($file, $res);
    fwrite($file, "\n");
    $result = json_decode($res, true);
    $dtTmp = date('Ymd');
    $file = fopen("SXF2_Globalpay$dtTmp.txt", "a+");
    return $result;
  }





  public  function json_post($url, $data = NULL)
  {

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    if(!$data){
      return 'data is null';
    }
    if(is_array($data))
    {
      $data = json_encode($data);
    }
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER,array(
      'Content-Type: application/json; charset=utf-8',
      'Content-Length:' . strlen($data),
      'Cache-Control: no-cache',
      'Pragma: no-cache'
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_exec($curl);
    $errorno = curl_errno($curl);
    if ($errorno) {
      return $errorno;
    }
    curl_close($curl);
    return $res;

  }


}
