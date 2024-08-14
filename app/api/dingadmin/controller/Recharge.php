<?php

namespace app\dingadmin\controller;

use think\facade\Db;
use think\facade\View;

//充值管理
class Recharge extends Base
{
	
	public function index()
	{
		$oModel = Db::name('Recharge');
		$username = input("param.username");
		if ($username != '') {
			$oModel->wherelike('username', '%' . $username . '%');
			View::assign('username', $username);
		} else {
			View::assign('username', '');
		}
		$order = input("param.order");
		if ($order != '') {
			$oModel->wherelike('order_num', '%' . $order . '%');
			View::assign('order', $order);
		} else {
			View::assign('order', '');
		}

    $ids_arr = $this->getAgetnIds();
    if(!empty($ids_arr)){
      $oModel->whereIn('u.parent_code', $ids_arr);
    }

		
		// $list = $oModel->order('addtime desc')->paginate(30);
		$list = $oModel->alias('l')
    ->join('user u', 'l.uid=u.id', 'LEFT')
    ->order('addtime desc')->paginate(['list_rows' => 30, 'query' => request()->param()]);
		$page = $list->render();//分页
		return view('index', [
			'list' => $list,
			'page' => $page,
		]);
		
	}
	
	//充值审核操作
	public function operate()
	{
		if (request()->isPost()) {
			$id = input('post.id');
			$uid = input('post.uid');
			$status = input('post.status');
			$money = input('post.money');
			
			$user = Db::name('user')->where('id', $uid)->find();
			
			//审核通过
			if ($status == 1) {
				$map['status'] = $status;
				self::Recommendation_reward($uid, $user['parent_id'], $money);
				$res = Db::name('Recharge')->where('id', $id)->update($map);
				if ($res) {
					//更新用户余额
					Db::name('user')->where('id', $uid)->inc('money', $money)->update();
					//写入充值记录
					$params['username'] = $user['username'];
					$params['adds'] = $money;
					$params['balance'] = $user['money'] + $money;
					$params['addtime'] = time();
					$params['desc'] = lang('cz_recharge');
					$params['uid'] = $uid;
					Db::name('jinbidetail')->insert($params);
					
					return json(['status' => 1, 'msg' => '操作成功']);
				} else {
					return json(['status' => 0, 'msg' => '操作失败']);
				}
				//审核驳回
			} else {
				$map['status'] = $status;
				$res = Db::name('Recharge')->where('uid', $uid)->update($map);
				if ($res) {
					return json(['status' => 1, 'msg' => '操作成功']);
				} else {
					return json(['status' => 0, 'msg' => '操作失败']);
				}
			}
		}
		$id = input('id');
		$one = Db::name('Recharge')->find($id);
		return view('operate', [
			'one' => $one,
		]);
	}
	
	/**
	 * 首充奖励计算
	 * @param $uid
	 * @param $parent_id
	 * @param $money
	 * @throws \think\db\exception\DataNotFoundException
	 * @throws \think\db\exception\DbException
	 * @throws \think\db\exception\ModelNotFoundException
	 */
	public static function Recommendation_reward($uid, $parent_id, $money)
	{
		
		$recharge = Db::name('Recharge')->where(['uid' => $uid, 'status' => 1])->count();

		//首充积分计算
		if(!$recharge){
			Db::name('user')->where(['id'=>$uid])->inc('integral',10)->inc('gift',10)->update();
			$user = Db::name('user')->where(['id'=>$uid])->find();
			$data['username'] = $user['username'];
			$data['upgrade'] = 10;
			$data['gift'] = 10;
			$data['addtime'] = time();
			$data['desc'] = lang('ro_jfone') . '10' .','.lang('ro_lpjfone'). '10';//用户首充积分及礼品积分
			$data['uid'] = $uid;
			$data['type'] = 5;//获得类型 首充
			if(!Db::table('tp_integral')->insert($data)){
				return false;
			}
		}
		
		//如果是首充并且有上级
    //、…、。
		if (!$recharge && $parent_id > 0) {
			
			$config = Db::name("proconfig")->where('id', 1)->find();//获取产品配置
			$oneinfo = Db::name("user")->where('id', $parent_id)->find();//获取直推上级信息

      //TODO  推荐比例计算
			$one_recommend = $config['one_recommend'];//直推收益奖励
			$two_recommend = $config['two_recommend'];//二代收益奖励
			$three_recommend = $config['three_recommend'];//三代收益奖励
			
			//直推收益
			$profit = $one_recommend / 100 * $money;
			Db::name("user")->where('id', $parent_id)->inc('money', $profit)->update();
			//更新首充上级资金变动表
			$one['username'] = $oneinfo['username'];
			$one['adds'] = $profit;
			$one['balance'] = $oneinfo['money'] + $profit;
			$one['addtime'] = time();
			$one['desc'] = lang('ro_shouchong1_jiangli') . $profit;//直推用户收益奖励金额
			$one['uid'] = $oneinfo['id'];
			$one['type'] = 2;//收支类型 下级返佣
			Db::name('jinbidetail')->insert($one);
			$twopid = Db::name("user")->where('id', $parent_id)->value('parent_id');
			if ($twopid) {
				$twoinfo = Db::name("user")->where('id', $twopid)->find();//获取二代上级信息
				//二级收益
				$two_profit = $two_recommend / 100 * $money;
				Db::name("user")->where('id', $twopid)->inc('money', $two_profit)->update();
				//更新资金变动表
				$two['username'] = $twoinfo['username'];
				$two['adds'] = $two_profit;
				$two['balance'] = $twoinfo['money'] + $two_profit;
				$two['addtime'] = time();
				$two['desc'] = lang('ro_shouchong2_jiangli') . $two_profit;//二代用户分红收益奖励金额
				$two['uid'] = $twoinfo['id'];
				$two['type'] = 2;//收支类型 下级返佣
				Db::name('jinbidetail')->insert($two);
				
				//判断是否存在三代上级
				$threepid = Db::name("user")->where('id', $twopid)->value('parent_id');
				if ($threepid) {
					$threeinfo = Db::name("user")->where('id', $threepid)->find();//获取三代上级信息
					//三级收益
					$three_profit = $three_recommend / 100 * $money;
					Db::name("user")->where('id', $threepid)->inc('money', $three_profit)->update();
					//更新资金变动表
					$three['username'] = $threeinfo['username'];
					$three['adds'] = $three_profit;
					$three['balance'] = $threeinfo['money'] + $three_profit;
					$three['addtime'] = time();
					$three['desc'] = lang('ro_shouchong3_jiangli') . $three_profit;//三代用户收益奖励金额
					$three['uid'] = $threeinfo['id'];
					$three['type'] = 2;//收支类型 下级返佣
					Db::name('jinbidetail')->insert($three);
					
				}
			}
		}
	}
}
