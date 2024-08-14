<?php

namespace app\dingadmin\controller;

use think\facade\Db;
use think\facade\View;
use think\facade\Request;


class User extends Base
{
	
	public function index()
	{

		$uModel = Db::name('user');
		$username = input("param.username");
		$is_agent = input("param.is_agent");
		$parent = input("param.parent");
		if ($username != '') {
			$uModel->wherelike('u.username', '%' . $username . '%');
			View::assign('username', $username);
		} else {
			View::assign('username', '');
		}
		
		if ($parent != '') {
			$uModel->wherelike('u.parent', '%' . $parent . '%');
			View::assign('parent', $parent);
		} else {
			View::assign('parent', '');
		}

		$ids_arr = $this->getAgetnIds();
	
		if(!empty($ids_arr)){
          $uModel->whereIn('u.parent_code', $ids_arr);
        }
		
		if ($is_agent != '') {
			if ($is_agent == 3) {
				# code...
			} else {
				$uModel->where('u.is_agent', $is_agent);
			}
			View::assign('is_agent', $is_agent);
		} else {
			View::assign('is_agent', '3');
		}




		
		/*$list = $uModel->alias('a')
			->field("a.*,b.lv_name")
			->join('level b', 'b.level=a.lv_id', 'LEFT')
			->order('reg_time desc')
			->paginate(['list_rows' => 30, 'query' => request()->param()]);*/
		
		$list = $uModel->alias('u')
			->field("u.*,l.lv_name,sum(r.money) as rujin,sum(w.money) as chujin")
			->join('level l', 'l.level=u.lv_id', 'LEFT')
			->join('recharge r', 'r.uid=u.id and r.status=1','LEFT')
			->join('withdrawal w', 'w.uid=u.id and w.status=1','LEFT')
			->order('reg_time desc')
			->group('u.id')
			->paginate(['list_rows' => 30, 'query' => request()->param()]);
		
		
		
		$page = $list->render();
		return view('index', [
			'list' => $list,
			'page' => $page,
		]);
	}
	
	public function add()
	{
		if (request()->isPost()) {
			//检测用户名是否存在
			$user = Db::name('user')->where('username', input('post.username'))->find();
			if ($user) {
				return json(['status' => 0, 'msg' => '该用户名已存在']);
			}
			
			if (input('post.parent_id')) {
				//检测上级ID是否存在
				$info = Db::name('user')->where('id', input('post.parent_id'))->find();
				if (!$info) {
					return json(['status' => 0, 'msg' => '上级ID不存在']);
				}
				$params['parent_id'] = input('post.parent_id');//上级ID
				$params['parent'] = $info['username'];//上级账号
				$params['parentpath'] = trim($info['parentpath'] . $info['id'] . '|');//直推路径
				if (input('post.is_agent')) {
					return json(['status' => 0, 'msg' => '代理必须是顶级的']);
				}
			} else {
				$params['parent_id'] = 0;
			}
			
			$params['username'] = input('post.username');//用户名称
			$params['phone'] = input('post.username');//用户名称
			$params['password'] = md5(input('post.password'));//用户密码
			$params['lv_id'] = input('post.lv_id');//等级所属id
			$params['reg_time'] = time();//注册时间
			$params['lock'] = 1;//用户状态
			$params['is_agent'] = input('post.is_agent');//是否代理
			$res = Db::name('user')->insert($params);
			//上级直推数量加1
			Db::name('user')->where('id', input('post.parent_id'))->inc('parentcount', 1)->update();//递减的话用dec
			
			if ($res) {
				return json(['status' => 1, 'msg' => '操作成功']);
			} else {
				return json(['status' => 0, 'msg' => '操作失败']);
			}
		}
		$lvArr = Db::name('level')->select();
		return view('add', [
			'lvArr' => $lvArr,
		]);
	}
	
	
	public function edit()
	{
		if (request()->isPost()) {
			$id = input('post.id');
			$user = Db::name('user')->where('id', $id)->find();
			//判断密码是否为空
			if (input('post.password')) {
				$params['password'] = md5(input('post.password'));//用户密码
			}
			
			if (input('post.parent_id')) {
				//提交过来的parent_id如果没有改变 则不做如下修改
				if ($user['parent_id'] != input('post.parent_id')) {
					//检测上级ID是否存在
					$info = Db::name('user')->where('id', input('post.parent_id'))->find();
					if (!$info) {
						return json(['status' => 0, 'msg' => '上级ID不存在']);
					}
					$params['parent_id'] = input('post.parent_id');//上级ID
					$params['parent'] = $info['username'];//上级账号
					$params['parentpath'] = trim($info['parentpath'] . $info['id'] . '|');//直推路径
					//旧上级直推人数减一
					$old = Db::name('user')->where('id', $user['parent_id'])->value('parentcount');
					if ($old >= 1) {
						Db::name('user')->where('id', $user['parent_id'])->dec('parentcount', 1)->update();
					}
				}
				if (input('post.is_agent')) {
					return json(['status' => 0, 'msg' => '代理必须是顶级的']);
				}
			} else {
				$params['parent_id'] = 0;
			}
			
			$params['lv_id'] = input('post.lv_id');//等级所属id
			$params['is_agent'] = input('post.is_agent');//是否代理
			$params['lock'] = input('post.lock');//用户状态
      
   
			$res = Db::name('user')->where('id', $id)->update($params);
			//上级直推数量加1
			Db::name('user')->where('id', input('post.parent_id'))->inc('parentcount', 1)->update();//递减的话用dec
			if ($res) {
				return json(['status' => 1, 'msg' => '操作成功']);
			} else {
				return json(['status' => 0, 'msg' => '操作失败']);
			}
		}
		$id = input('id');
		$one = Db::name('user')->find($id);
		$lvArr = Db::name('level')->select();
		return view('edit', [
			'one' => $one,
			'lvArr' => $lvArr,
		]);
	}
	
	// 查看用户银行卡
	public function setbank()
	{
		$id = input('get.id');
	
		if (request()->isPost()) {
			
			$map['bank_name'] = input('post.bank_name');//银行名称
			$map['account_no'] = input('post.account_no');//银行卡号
			$map['account_name'] = input('post.account_name');//银行卡持有人姓名
			$res = Db::name('bankcard')->where('userid', $id)->update($map);
			if ($res) {
				return json(['status' => 1, 'msg' => '操作成功']);
			} else {
				return json(['status' => 0, 'msg' => '操作失败']);
			}
		}
		$id = input('id');
		//获取用户收款账户信息
		$one = Db::name('bankcard')->where('userid', $id)->find();
		return view('setbank', [
			'one' => $one,
		]);
	}
	
	
	public function addjinbi()
	{
		if (request()->isPost()) {
			$uid = input('post.id');//用户id
			$jinbi = input('post.money');//添加金额
			$integral = input('post.integral');//添加金额
			$user = Db::name('user')->find($uid);
			$res = [];
		
			$aid = session('admin_auth')['id'];
			if (!empty($jinbi)) {
			
				if ($jinbi > 0) {
					Db::name('user')->where('id', $uid)->inc('money', $jinbi)->update();
					//写入充值记录
					$params['username'] = $user['username'];
					$params['adds'] = $jinbi;
					$params['balance'] = $user['money'] + $jinbi;
					$params['addtime'] = time();
					$params['desc'] = lang('Platform recharge');
					$params['uid'] = $uid;
					$params['type'] = 3;
					$params['aid'] = $aid;
					Db::name('jinbidetail')->insert($params);
					$res = ['status' => 1, 'msg' => '充值成功！'];
				} else{
					$jinbi = abs($jinbi);//负数转正数
					Db::name('user')->where('id', $uid)->dec('money', $jinbi)->update();
					//写入充值记录
					$params['username'] = $user['username'];
					$params['reduce'] = $jinbi;
					$params['balance'] = $user['money'] - $jinbi;
					$params['addtime'] = time();
					$params['desc'] = lang('Platform recharge');
					$params['type'] = 3;
					$params['aid'] = $aid;
					$params['uid'] = $uid;
					Db::name('jinbidetail')->insert($params);
					$res = ['status' => 1, 'msg' => '扣除成功！'];
				}
			}
			
			if(!empty($integral)){

				if ($integral > 0) {
					
					Db::name('user')->where('id', $uid)->inc('integral', $integral)->update();
					//写入积分记录
					$params['username'] = $user['username'];
					$params['upgrade'] = $integral;
					$params['addtime'] = time();
					$params['desc'] = lang('Platform recharge');
					$params['uid'] = $uid;
					$params['type'] = 3;
					$params['aid'] = $aid;
					Db::name('integral')->insert($params);
					$res = ['status' => 1, 'msg' => '增加成功！'];
				} else{
					$integral = abs($integral);//负数转正数
					Db::name('user')->where('id', $uid)->dec('integral', $integral)->update();
					//写入充值记录
					$params['username'] = $user['username'];
					$params['upgrade'] = -$integral;
					$params['addtime'] = time();
					$params['desc'] = lang('Platform recharge');
					$params['uid'] = $uid;
					$params['type'] = 3;
					$params['aid'] = $aid;
					Db::name('integral')->insert($params);
					$res = ['status' => 1, 'msg' => '扣除成功！'];
				}
			}
			
			return json($res);
			
			
		}
		$id = input('id');
		$one = Db::name('user')->find($id);
		return view('addjinbi', [
			'one' => $one,
		]);
	}
	
	//资金变动详情
	public function details()
	{
		$id = input('id');
		$twoArr = Db::name('jinbidetail')->where('uid', $id)->order('addtime desc')->select();
		return view('details', [
			'twoArr' => $twoArr,
		]);
	}
	
	public function del()
	{
		$id = input('id');
		$res = Db::name('user')->where('id', $id)->delete();
		if ($res) {
			return json(['status' => 1, 'msg' => '删除成功']);
		} else {
			return json(['status' => 0, 'msg' => '删除失败']);
		}
	}
	
	public function delAll()
	{
		$ids = $this->request->param();
		$res = Db::name('user')->where('id', 'IN', $ids['id'])->delete();
		if ($res) {
			return json(['status' => 1, 'msg' => '成功删除' . $res . '条']);
		} else {
			return json(['status' => 0, 'msg' => '删除失败']);
		}
	}
	
	//团队
	public function lowlv()
	{
		$id = input('id');
		$list2 = [];
		$list3 = [];
		
		$list1 = Db::name('user')
			->alias('a')
			->field("a.*,b.lv_name")
			->join('level b', 'b.level=a.lv_id', 'LEFT')
			->where('a.parent_id', $id )
			->order('a.reg_time asc')
			->select();
		
		$parent_ids1 = Db::name('user')
        ->where('parent_id', $id )
        ->order('reg_time asc')
        ->column('id');
    
		if(count($parent_ids1)){
      $list2 = Db::name('user')
        ->alias('a')
        ->field("a.*,b.lv_name")
        ->join('level b', 'b.level=a.lv_id', 'LEFT')
        ->where('a.parent_id','in' ,$parent_ids1)
        ->order('a.reg_time asc')
        ->select();
      
      $parent_ids2 = Db::name('user')
        ->where('parent_id','in' ,$parent_ids1)
        ->order('reg_time asc')
        ->column('id');
      
      if(count($parent_ids2)){
        $list3 = Db::name('user')
          ->alias('a')
          ->field("a.*,b.lv_name")
          ->join('level b', 'b.level=a.lv_id', 'LEFT')
          ->where('a.parent_id','in' ,$parent_ids2)
          ->order('a.reg_time asc')
          ->select();
      }
    }
		
    
    
    
		
		return view('lowlv', [
			'list1' => $list1,
			'list2' => $list2,
			'list3' => $list3,
		]);
	}
	
	public function achievement (){
    $id = input('id');
    $username = input('username/d');
    if($username){$where[] = ['username','=',input('username/d')];}
    $rechargeList = [];
    $withdrawalList = [];
    $rechargeSum = 0;
    $withdrawalSum = 0;

    $uids  = Db::name('user')
      ->where('parentpath','like','%|'.$id.'|%' )
      ->column('id');
   
      
      $where[] = ['uid','in',$uids];
      $where[] = ['status','=',1];
      
      //入金===============================
      $rechargeList = Db::name('recharge')
          ->where($where)
          ->order('addtime desc')
          ->select();
      $rechargeSum= Db::name('recharge')
        ->where($where)
        ->sum('money');
      
     // 出金 =================================
      $withdrawalList = Db::name('withdrawal')
        ->where($where)
        ->order('addtime desc')
        ->select();
      $withdrawalSum= Db::name('withdrawal')
        ->where($where)
        ->sum('money');
    

    return view('achievement', [
      'rechargeList' => $rechargeList,
      'withdrawalList' => $withdrawalList,
      'rechargeSum'=>$rechargeSum,
      'withdrawalSum'=>$withdrawalSum,
      'username'=>$username,
      'id'=>$id,
      
    ]);
   
  }
	
	//删除资金变动信息
	public function zijindel()
	{
		$id = input('id');
		$res = Db::name('jinbidetail')->where('id', $id)->delete();
		if ($res) {
			return json(['status' => 1, 'msg' => '删除成功']);
		} else {
			return json(['status' => 0, 'msg' => '删除失败']);
		}
	}
	
	
}
