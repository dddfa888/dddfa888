<?php
namespace app\agent\controller;
use think\facade\Db;
use think\facade\View;
use think\facade\Request;


class Index extends Base{

	public function index(){
		return view('index',[
        ]);
	}

	public function main(){
		//会员总数
		$mtotal = Db::name('user')->where('id','<>',session('agent_auth.id'))->whereLike('parentpath',session('agent_auth.id').'%')->count();
		//会员充值收益总数
		$shouyi_total = Db::name('jinbidetail')->where('type',1)->where('uid',session('agent_auth.id'))->sum('adds');
		//下级收益返佣总数
		$rakeback_total = Db::name('jinbidetail')->where('type',2)->where('uid',session('agent_auth.id'))->sum('adds');

		//下级总充值
		$recharge_total = Db::name('user')->alias('a')->join('recharge b','b.uid=a.id')->where('a.id','<>',session('agent_auth.id'))->where('b.status',1)->whereLike('a.parentpath',session('agent_auth.id').'%')->sum('b.money');
		//下级总提现
		$withdrawal_total = Db::name('user')->alias('a')->join('withdrawal b','b.uid=a.id')->where('a.id','<>',session('agent_auth.id'))->where('b.status',1)->whereLike('a.parentpath',session('agent_auth.id').'%')->sum('b.payment');

		$today=strtotime(date('Y-m-d 00:00:00'));
		$today_end=strtotime(date('Y-m-d 23:59:59'));

		//今日新增人
		$day_m_num = Db::name('user')->where('id','<>',session('agent_auth.id'))->whereLike('parentpath',session('agent_auth.id').'%')->whereTime('reg_time','between',[$today, $today_end])->count();
		//今日充值
		$day_withdrawal_num = Db::name('user')->alias('a')->join('recharge b','b.uid=a.id')->where('a.id','<>',session('agent_auth.id'))->where('b.status',1)->whereLike('a.parentpath',session('agent_auth.id').'%')->whereTime('b.addtime','between',[$today, $today_end])->sum('b.money');
		return view('main',[
			'mtotal'=>$mtotal,
			'shouyi_total'=>$shouyi_total,
			'rakeback_total'=>$rakeback_total,
			'recharge_total'=>$recharge_total,
			'withdrawal_total'=>$withdrawal_total,
			'day_m_num'=>$day_m_num,
			'day_withdrawal_num'=>$day_withdrawal_num,
        ]);

	}






























}