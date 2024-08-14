<?php

namespace app\dingadmin\controller;

use think\facade\Db;
use app\BaseController;

class Index extends Base
{
	
	public function index()
	{
		return view();
	}
	
	
	public function main()
	{
		//获取服务器信息,超全局变量:$_SERVER
		$system = [
			'ip' => $_SERVER['SERVER_ADDR'],            //获取服务器IP地址
			'host' => $_SERVER['SERVER_NAME'],          //服务器域名/主机名
			'os' => PHP_OS,                             //服务器操作系统/php_uname
			'server' => $_SERVER['SERVER_SOFTWARE'],    //运行环境
			'port' => $_SERVER['SERVER_PORT'],          //服务器端口
			'php_ver' => PHP_VERSION,                   //php版本
			'mysql_ver' => Db::query('select version() as ver')[0]['ver'],//数据库版本
			'database' => config('database')['connections']['mysql']['database'],//数据库名称
		];
		//获取登录日志信息
		$logRes = Db::name('login_record')->order('logintime desc')->where('uid', session('admin_auth.id'))->limit(10)->select();

    $ids_arr = $this->getAgetnIds();


	
		return view('main', [
			'system' => $system,
			'log' => $logRes,
			'register' => self::countRegister($ids_arr),
			'rujin'=>self::sumRujin($ids_arr),
			'statistical'=>self::statistical($ids_arr),
			'chujin'=>self::sumChujin($ids_arr)
		]);
		
	}
	
	
	/**
	 * 统计入金
	 * @return array
	 */
	public function countRegister($ids = [])
	{
		//总会员
    if(empty($ids)){
      $where_str = '';
    }else{
      $comma_separated = implode("','", $ids);
      $comma_separated = "'".$comma_separated."'";
      $where_str = ' parent_code in ('.$comma_separated.')';
    }
		$data['count'] = Db::name('user')->where($where_str)->count();
		
		//今日
		$start = strtotime(date("Y-m-d"),time());
		$end = strtotime('+1 day',$start-1);
		$data['today'] = Db::name('user')->where($where_str)->whereBetweenTime('reg_time',$start,$end)->count();
	
		//昨天
		$start= strtotime('-1 day',strtotime(date("Y-m-d"),time()));
		$end = strtotime(date("Y-m-d"),time()) - 1;
		$data['yesterday'] = Db::name('user')->where($where_str)->whereBetweenTime('reg_time',$start,$end)->count();
		return $data;
	}
	
	/**
	 * 统计入金
	 * @return array
	 */
	public function sumRujin($ids = []){
    if(empty($ids)){
      $where_str = '';
    }else{
      $comma_separated = implode("','", $ids);
      $comma_separated = "'".$comma_separated."'";
      $where_str = ' u.parent_code in ('.$comma_separated.')';
    }
		//总入金

		$data['sum'] =  Db::name('recharge')->alias('l')->where($where_str) ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->sum('l.money');
		//今日
		$start = strtotime(date("Y-m-d"),time());
		$end = strtotime('+1 day',$start-1);
		$data['today'] =  Db::name('recharge')->alias('l')->where($where_str) ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->whereBetweenTime('addtime',$start,$end)->sum('l.money');

		//昨天
		$start= strtotime('-1 day',strtotime(date("Y-m-d"),time()));
		$end = strtotime(date("Y-m-d"),time()) - 1;
		$data['yesterday'] = Db::name('recharge')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->whereBetweenTime('addtime',$start,$end)->sum('l.money');
		return $data;
	}
	
	
	/**
	 * 统计出金
	 * @return array
	 */
	public function sumChujin($ids = []){
    if(empty($ids)){
      $where_str = '';
    }else{
      $comma_separated = implode("','", $ids);
      $comma_separated = "'".$comma_separated."'";
      $where_str = ' u.parent_code in ('.$comma_separated.')';
    }


		//总会员
		$data['sum'] = Db::name('withdrawal')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->sum('l.money');
		
		//今日
		$start = strtotime(date("Y-m-d"),time());
		$end = strtotime('+1 day',$start-1);
		$data['today'] = Db::name('withdrawal')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->whereBetweenTime('addtime',$start,$end)->sum('l.money');
		
		//昨天
		$start= strtotime('-1 day',strtotime(date("Y-m-d"),time()));
		$end = strtotime(date("Y-m-d"),time()) - 1;
		$data['yesterday'] = Db::name('withdrawal')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->whereBetweenTime('addtime',$start,$end)->sum('l.money');
		return $data;
	}



	public function    statistical($ids = []){
    if(empty($ids)){
      $where_str = '';
    }else{
      $comma_separated = implode("','", $ids);
      $comma_separated = "'".$comma_separated."'";
      $where_str = ' u.parent_code in ('.$comma_separated.')';
    }
    $proconfig = Db::name('proconfig')->where('id',1)->find();
	  //实际如今总额




   //总入金
    $ru_sum = Db::name('recharge')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->sum('l.money');
    $data['into_amount'] = $ru_sum - $ru_sum*($proconfig['top_up_ratio']/100);

    $chu_sum = Db::name('withdrawal')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->sum('l.money');
    $data['out_amount'] = $chu_sum + $chu_sum*($proconfig['charge_ratio']/100);


    $data['actual_amount'] = $data['into_amount']-$data['out_amount'];


    //昨天总充值人数
    $start= strtotime('-1 day',strtotime(date("Y-m-d"),time()));
    $end = strtotime(date("Y-m-d"),time()) - 1;
    $data['recharge_count'] =  Db::name('recharge')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])
      ->whereBetweenTime('addtime',$start,$end)
      ->group('uid')
      ->count();


    //昨天首冲人数
    $recharge_uids =  Db::name('recharge')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->whereBetweenTime('addtime',$start,$end)
      ->group('uid')
      ->column('uid');

    $recharge_uids_oo =  Db::name('recharge')->alias('l')->where($where_str)
      ->join('user u', 'l.uid=u.id', 'LEFT')
      ->where(['l.status'=>1])->where('addtime','<',$start)
      ->group('uid')
      ->column('uid');

    $result=array_intersect($recharge_uids,$recharge_uids_oo);
    $data['recharge_count_oo'] = count($recharge_uids) - count($result);

    return $data;

  }
}
