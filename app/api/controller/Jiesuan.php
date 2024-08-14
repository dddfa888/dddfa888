<?php
  
  
  namespace app\api\controller;
  
  
  use think\facade\Db;
  use think\facade\Log;

  class Jiesuan
  {
  
    /**
     * 定时任务计算收益
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
      Log::write(date('Y-m-d H:i:s',time()).'开始执行结算任务');
      //升级订单
      $order1 = Db::name('Order')->where(['order_type'=>1,'state'=>1])->select();
      if(count($order1) > 0){
        foreach ($order1 as $k => $v) {
          $res = \jiesuan\Jiesuan::jiesuan1($v['id'], $v['user_id']);
          if(!isset($res) || !$res['code']){
            $msg = !isset($res)?'':$res['message'];
            Log::write('第'.$k.'次升级订单结算错误'.$msg);
            continue;
          }
        }
      }
      
      //投资订单
		$order2 = Db::name('Order')->where(['order_type' => 2, 'state' => 1])->select();
		foreach ($order2 as $k => $v) {
			$res1 = \jiesuan\Jiesuan::jiesuan2($v['id'], $v['user_id']);
			if(!isset($res1) || !$res1['code']){
				$msg = !isset($res1)?'':$res1['message'];
				Log::write('第'.$k.'次投资订单结算错误'.$msg);
				continue;
			}
		}
    }
  
    
  }
