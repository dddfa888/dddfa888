<?php


namespace app\payment\controller;


use gmspay\gmspay;
use think\facade\Db;

class Notify
{
	
	/**
	 * 充值回调
	 * @return string
	 */
	public function gmspay_notify()
	{
		$data = $_POST;
		file_put_contents('./runtime/log/gmspay.txt', '支付回调' . json_encode($data) . "\r\n", FILE_APPEND);
		$model = new gmspay();
		$res = $model->pay_notify($data, 'p');
		if ($res) {
			$mchOrderNo = $data['mchOrderNo'];
			$status = $data['tradeResult'];
			$data = Db::name('recharge')->where('order_num', $mchOrderNo)->find();
			
			Db::name('recharge')->where('order_num', $mchOrderNo)->update(['status' => $status]);
			if ($status == 1) {
				Db::name('user')->where('id', $data['uid'])->update(['money' => $data['money_after']]);
			}
		}
		return 'success';
	}
	
	
	/**
	 * 提现回调
	 */
	public function gmspay_withdrawal_notify()
	{
		$data = $_POST;
		file_put_contents('./runtime/log/gmspay.txt', '代付回调' . json_encode($data) . "\r\n", FILE_APPEND);
		$model = new gmspay();
		$res = $model->pay_notify($data, 'w');
		if ($res && $data['tradeResult'] == 1) {
			Db::name('withdrawal')->where('order_num', $data['merTransferId'])->update(['status' => 1]);
		} else {
			Db::name('withdrawal')->where('order_num', $data['merTransferId'])->update(['status' => 4]);
		}
		return 'success';
	}
	
	
}


