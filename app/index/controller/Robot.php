<?php

namespace app\index\controller;

use jiesuan\Jiesuan;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;
use think\facade\Lang;
use think\Request;

class Robot extends Base
{

    //投资产品
    public function buy()
    {
        $id = input('id');
        //查询产品信息
        $proinfo = Db::name("product")->find($id);
        //查询用户信息
        $uid = session('uid');
        $user = Db::name("user")->where('id', $uid)->find();


        //判断用户账户余额是否充足
        if ($user['money'] < $proinfo['price']) {
            return json(['status' => 22, 'msg' => lang('ro_balance_insufficient')]);//账户余额不足,请先进行充值
        }


        //判断用户积分是否可以购买此类产品
        if ($user['integral'] < $proinfo['integral']) {
            return json(['status' => 0, 'msg' => lang('ro_purchase_integral') . $proinfo['integral']]);//购买该产品需要积分为
        }

        //判断用户等级是否可以购买此类产品
        /*if ($user['lv_id'] < $proinfo['allow_lv']) {
                $lvname = Db::name("level")->where('level', $proinfo['allow_lv'])->value('lv_name');
                return json(['status' => 0, 'msg' => lang('ro_purchase_needs') . $lvname]);//购买该产品需要会员等级为
            }*/

        if (empty($proinfo)) {
            return json(['status' => 0, 'msg' => lang('ro_product_exist')]);//产品不存在
        }

        //判断用户是否达到限购数量
        $my_gounum = Db::name("order")->where(['user_id' => session('uid'), 'sid' => $id])->count();
        if ($my_gounum >= $proinfo['xiangou']) {
            return json(['status' => 0, 'msg' => lang('ro_product_limit')]);//已达到当前设备的租赁上限
        }

        //判断产品库存是否买完
        if ($proinfo['stock'] < 1) {
            return json(['status' => 0, 'msg' => lang('ro_product_out')]);//设备已售罄 ，请等待投放新设备
        }


        //活动期间比例存入数据库
        $cz_arr = Db::name('active')
            ->where('start_time', '<', date('Y-m-d H:i:s', time()))
            ->where('end_time', '>', date('Y-m-d H:i:s', time()))
            ->where('status', 1)
            ->find();
        if ($cz_arr) {
            $map['one_recommend'] = $cz_arr['one_recommend'];
            $map['two_recommend'] = $cz_arr['two_recommend'];
            $map['three_recommend'] = $cz_arr['three_recommend'];
            $map['one_profit'] = $cz_arr['one_profit'];
            $map['two_profit'] = $cz_arr['two_profit'];
            $map['three_profit'] = $cz_arr['three_profit'];
            $map['active_type'] = 1;
        }

        //生成订单
        $map['kjbh'] = date('d') . substr(time(), -5) . sprintf('%02d', rand(0, 99));//订单编号
        $map['user'] = $user['username'];//用户名称
        $map['user_id'] = $user['id'];//用户id
        $map['tuijian'] = $user['parent'];//该订单用户推荐人
        $map['project'] = $proinfo['title'];//产品名称
        $map['sid'] = $proinfo['id'];//产品id
        $map['yxzq'] = $proinfo['yxzq'];//运行周期
        $map['shouyi'] = $proinfo['day_shouyi'];//收益
        $map['sumprice'] = $proinfo['price'];//单价
        $map['addtime'] = time();//订单生成时间
        $map['UG_getTime'] = time();//领取收益时间 第一次与订单时间同步
        $map['imagepath'] = $proinfo['thumb'];//产品图片
        $map['already_profit'] = 0;//累计收益
        $map['order_type'] = $proinfo['type_id']; //产品类型

        $oldOid = Db::name("order")->where(['user_id' => $user['id'], 'state' => 1, 'order_type' => 1])->value('id');
        if ($oid = Db::name("order")->insertGetId($map)) {

//      if($oldOid){
//        Db::name("order")->where(['id' => $oldOid])->update(['state' => 2]);
//      }

            //产品库存减一
            Db::name("product")->where('id', $id)->dec('stock', 1)->update();
            //用户余额减少冻 结金额增加 购买没有冻结资金
            $new['money'] = $user['money'] - $proinfo['price'];
            //$new['dongjie'] = $user['dongjie'] + $proinfo['price'];
            Db::name("user")->where('id', $user['id'])->update($new);
            //资金变动写入
            $params['username'] = $user['username'];
            $params['reduce'] = $proinfo['price'];
            $params['balance'] = $user['money'] - $proinfo['price'];
            $params['addtime'] = time();
            $params['desc'] = 'investment';//产品购入冻结资金
            $params['uid'] = $uid;
            Db::name('jinbidetail')->insert($params);

            if ($user['lv_id'] < $proinfo['allow_lv']) {
                Db::name("user")->where('id', $uid)->update(['lv_id' => $proinfo['allow_lv']]);
                if ($proinfo['allow_lv'] > 1) {
                    self::integral_settlement($uid, $user['parent_id'], $proinfo['id']);
                }
            }
            $this->investReward($user['id'], $user['parent_id'], $proinfo['price']);
            return json(['status' => 1, 'msg' => lang('ro_successful_purchase')]);//购买成功！
        }
    }

    /**首充投资产品奖励计算*/
    public function investReward($uid, $parent_id, $money)
    {
        $config = Db::name("proconfig")->where('id', 1)->find();//获取产品配置

        //判断上一级是否有投资
        $parentRecharge = Db::name('order')->where(['user_id' => $parent_id])->find();
        $isExist = Db::name('exist')->where(['user_id' => $uid, 'parent_id' => $parent_id])->find();
        if (empty($parentRecharge) && empty($isExist)) {
            $parentInfo = Db::name("user")->where('id', $parent_id)->find();//直推上级信息
            $profit = $config['parent_profit'];
            Db::name("user")->where('id', $parent_id)->inc('money', $profit)->update();
            //更新首充上级资金变动表
            $one['username'] = $parentInfo['username'];
            $one['adds'] = $profit;
            $one['balance'] = $parentInfo['money'] + $profit;
            $one['addtime'] = time();
            $one['desc'] = lang('ro_shouchong1_jiangli') . $profit;//直推用户收益奖励金额
            $one['uid'] = $parentInfo['id'];
            $one['type'] = 2;//收支类型 下级返佣
            Db::name('jinbidetail')->insert($one);
            Db::name('exist')->insert(['user_id' => $uid, 'parent_id' => $parent_id]);
            return true;
        }


        //三级返佣
        $recharge = Db::name('order')->where(['user_id' => $uid])->count();

        //如果是首充并且有上级
        //、…、。
        if ($recharge == 1 && $parent_id > 0) {

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

    /**
     * 积分奖励
     * @param $uid
     * @param $parent_id
     * @param $pid
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException\
     */
    public function integral_settlement($uid, $parent_id, $pid)
    {

        $order = Db::name('order')->where(['user_id' => $uid, 'sid' => $pid])->count();

        if ($order <= 1 && $parent_id > 0) {

            $oneinfo = Db::name("user")->where('id', $parent_id)->find();//获取直推上级信息
            //直推升级积分
            $upgrade = 10;
            $gift = 3;

            Db::name("user")->where('id', $parent_id)->inc('integral', $upgrade)->inc('gift', $gift)->update();
            //更新首充上级资金变动表
            $one['username'] = $oneinfo['username'];
            $one['upgrade'] = $upgrade;
            $one['gift'] = $gift;
            $one['addtime'] = time();
            $one['desc'] = lang('ro_jf1') . $upgrade . ',' . lang('ro_lpjf1') . $gift;//直推用户推荐积分及礼品积分
            $one['uid'] = $oneinfo['id'];
            $one['type'] = 2;//收支类型 下级返佣
            Db::name('integral')->insert($one);
            $twopid = Db::name("user")->where('id', $parent_id)->value('parent_id');

            if ($twopid) {

                $twoinfo = Db::name("user")->where('id', $twopid)->find();//获取二代上级信息
                //二级收益
                $upgrade = 5;
                $gift = 2;

                Db::name("user")->where('id', $twopid)->inc('integral', $upgrade)->inc('gift', $gift)->update();
                //更新首充上级资金变动表
                $two['username'] = $twoinfo['username'];
                $two['upgrade'] = $upgrade;
                $two['gift'] = $gift;
                $two['addtime'] = time();
                $two['desc'] = lang('ro_jf1') . $upgrade . ',' . lang('ro_lpjf1') . $gift;//直推用户推荐积分及礼品积分
                $two['uid'] = $twoinfo['id'];
                $two['type'] = 2;//收支类型 下级返佣
                Db::name('integral')->insert($two);
                //判断是否存在三代上级
                $threepid = Db::name("user")->where('id', $twopid)->value('parent_id');
                if ($threepid) {
                    $threeinfo = Db::name("user")->where('id', $threepid)->find();//获取三代上级信息
                    //三级收益
                    $upgrade = 3;
                    $gift = 1;

                    Db::name("user")->where('id', $threepid)->inc('integral', $upgrade)->inc('gift', $gift)->update();
                    //更新首充上级资金变动表
                    $three['username'] = $threeinfo['username'];
                    $three['upgrade'] = $upgrade;
                    $three['gift'] = $gift;
                    $three['addtime'] = time();
                    $three['desc'] = lang('ro_jf1') . $upgrade . ',' . lang('ro_lpjf1') . $gift;//直推用户推荐积分及礼品积分
                    $three['uid'] = $threeinfo['id'];
                    $three['type'] = 2;//收支类型 下级返佣
                    Db::name('integral')->insert($two);

                }
            }
        }
    }


    //投资
    public function invest()
    {
        $id = input('id/s');
        $money = input('money/s');
        //查询产品信息
        $proinfo = Db::name("product")->find($id);
        $money = $proinfo['price'];

        //查询用户信息
        $uid = session('uid');
        $user = Db::name("user")->where('id', $uid)->find();

        //判断用户等级是否可以购买此类产品
        if ($user['lv_id'] < $proinfo['allow_lv']) {
            $lvname = Db::name("level")->where('level', $proinfo['allow_lv'])->value('lv_name');
            return json(['status' => 0, 'msg' => lang('ro_purchase_needs') . $lvname]);//购买该产品需要会员等级为
        }

        if (empty($proinfo)) {
            return json(['status' => 0, 'msg' => lang('ro_product_exist')]);//产品不存在
        }

        //判断用户是否达到限购数量
        $my_gounum = Db::name("order")->where(['user_id' => session('uid'), 'sid' => $id])->count();
        if ($my_gounum >= $proinfo['xiangou']) {
            return json(['status' => 0, 'msg' => lang('ro_product_limit')]);//已达到当前设备的租赁上限
        }

        //判断产品库存是否买完
        if ($proinfo['stock'] < 1) {
            return json(['status' => 0, 'msg' => lang('ro_product_out')]);//设备已售罄 ，请等待投放新设备
        }


        //判断用户账户余额是否充足
        if ($user['money'] < $money) {
            return json(['status' => 0, 'msg' => lang('ro_balance_insufficient')]);//账户余额不足,请先进行充值
        }


        //生成订单
        $map['kjbh'] = date('d') . substr(time(), -5) . sprintf('%02d', rand(0, 99));//订单编号
        $map['user'] = $user['username'];//用户名称
        $map['user_id'] = $user['id'];//用户id
        $map['tuijian'] = $user['parent'];//该订单用户推荐人
        $map['project'] = $proinfo['title'];//产品名称
        $map['sid'] = $proinfo['id'];//产品id
        $map['yxzq'] = $proinfo['yxzq'];//运行周期
        $map['shouyi'] = $proinfo['day_shouyi'];//收益
        $map['sumprice'] = $money;//单价
        $map['addtime'] = time();//订单生成时间
        $map['UG_getTime'] = time();//领取收益时间 第一次与订单时间同步
        $map['imagepath'] = $proinfo['thumb'];//产品图片
        $map['already_profit'] = 0;//累计收益
        $map['order_type'] = $proinfo['type_id']; //产品类型
        Db::name("order")->insert($map);


        //产品库存减一
        Db::name("product")->where('id', $id)->dec('stock', 1)->update();

        //用户余额减少冻结金额增加
        $new['money'] = $user['money'] - $money;
        $new['dongjie'] = $user['dongjie'] + $money;
        Db::name("user")->where('id', $user['id'])->update($new);
        //资金变动写入
        $params['username'] = $user['username'];
        $params['reduce'] = $proinfo['price'];
        $params['balance'] = $user['money'] - $proinfo['price'];
        $params['addtime'] = time();
        $params['desc'] = 'financing';//理财产品
        $params['uid'] = $uid;
        $params['type'] = 5;
        Db::name('jinbidetail')->insert($params);
        return json(['status' => 1, 'msg' => lang('ro_successful_purchase')]);//购买成功！

    }


    //我的产品订单
    public function robot()
    {

        $user_id = session('uid');
        //打开已购页面的时候执行收益逻辑计算
        $orders = Db::name("order")->where('user_id', $user_id)->where('state', 1)->order('addtime desc')->select()->toArray();
        $list = array();

        foreach ($orders as $k => $v) {
            //日收益
            $day_income = $v['shouyi'];
            $list[$k]['day_income'] = $day_income;
            $list[$k]['order_type'] = $v['order_type'];

//			if ($v['order_type'] == 2) {
//				$list[$k]['day_income'] = intval($v['shouyi']) . '%';
//			}
            //累积总收益
            $already_profit = $v['already_profit'];

            $today = strtotime(date('Ymd', time()));
            $before = strtotime(date('Ymd', $v['addtime'])); //天数要取整数
            $dayNum = intval(($today - $before) / 86400) + 1;//天数要取整数

            if ($dayNum > $v['yxzq']) {
                $dayNum = $v['yxzq'];
            }

            //$yuer = $day_income * $dayNum - $already_profit;
            $yuer = $day_income * $dayNum;

            if ($yuer > 0) {
                $list[$k]['leiji_income'] = $yuer;
            } else {
//				$list[$k]['leiji_income'] = $day_income .'*'. $dayNum .'-' .$already_profit;
                $list[$k]['leiji_income'] = 0;
            }


            //周期
            $list[$k]['yxzq'] = $v['yxzq'];

            //总收益
            if (!$already_profit > 0) {
                $list[$k]['already_profit'] = $yuer * $dayNum;
            } else {
                $list[$k]['already_profit'] = $yuer;
            }
            //配置金额
            $list[$k]['sumprice'] = $v['sumprice'];
            //产品名称
            $list[$k]['project'] = $v['project'];
            //产品图片
            $list[$k]['imagepath'] = $v['imagepath'];
            //订单id
            $list[$k]['id'] = $v['id'];
            //剩余天数
            $list[$k]['dayNum'] = $dayNum;

        }

        return View('robot', [
            'orders' => $list,
        ]);
    }

    /*	public function robot(){

    $user_id = session('uid');
    $overflow = Db::name("proconfig")->where('id',1)->value('overflow');//获取溢出倍数
    //如果溢出倍数忘记设置了则默认为1倍
    if (empty($overflow)) {
        $overflow = 1;
    }
    //打开已购页面的时候执行收益逻辑计算
    $orders = Db::name("order")->where('user_id',$user_id)->where('state',1)->order('addtime desc')->select()->toArray();
    $list = array();

    foreach ($orders as $k => $v) {
        //日收益
        $day_income = $v['shouyi'];
        $list[$k]['day_income'] = $day_income;
        //累积总收益
        $already_profit = $v['already_profit'];
        //$already_profit = $day_income*$v['yxzq'];
        //产品收益极限
        $out_income = $v['sumprice']*$overflow;

        //累积收益这里还需要做一个判断 判断是否最后一笔收益是否已经超出 总收益的两倍
        if ($day_income>($out_income - $already_profit)) {
            $list[$k]['leiji_income'] = $out_income - $already_profit;
        }else{
            //累积收益 即当日可领取的收益  累计收益 = 日收益*天数 - 总收益(即累积总收益)
            $dayNum = intval((time()-$v['addtime'])/86400);//天数要取整数
            if ($dayNum>$v['yxzq']){
                $dayNum = $v['yxzq'];
            }
            $yuer = $day_income*$dayNum - $already_profit;
            if ($yuer > 0) {
                if ($yuer>$out_income) {
                    $list[$k]['leiji_income'] = $out_income;
                }else{
                    $list[$k]['leiji_income'] = $yuer;
                }

            }else{
                $list[$k]['leiji_income'] = 0;
            }
        }
        //总收益
        $list[$k]['already_profit'] = $already_profit;
        //配置金额
        $list[$k]['sumprice'] = $v['sumprice'];
        //产品名称
        $list[$k]['project'] = $v['project'];
        //产品图片
        $list[$k]['imagepath'] = $v['imagepath'];
        //订单id
        $list[$k]['id'] = $v['id'];

    }
    return View('robot',[
        'orders'=>$list,
    ]);
}*/


    //领取分红收益
    public function jiesuan(Request $request)
    {
        if (request()->isPost()) {
            $check = $request->checkToken('__token__');
            if (false === $check) {
                throw new ValidateException('invalid token');
            }

            $oid = input('id');
            $shouyi = input('shouyi');//产品收益
            $uid = session('uid');
            $username = session('username');


            //判断领取的金额是否等于0
            if ($shouyi == 0) {
                return json(['status' => 0, 'msg' => lang('ro_wu_shouyi_lingqu')]);//暂无累积收益可领取！
            }
            if (empty($oid)) {
                return json(['status' => 0, 'msg' => lang('ro_parameter_loss')]);//参数丢失
            }
            $order = Db::name("order")->where(['user_id' => $uid, 'id' => $oid])->find();
            if (empty($order)) {
                return json(['status' => 0, 'msg' => lang('ro_order_exist')]);//订单不存在！
            }
            $already_profit = $order['already_profit'] + $shouyi;//累计收益
            $data['already_profit'] = $already_profit;
            $data['UG_getTime'] = time();//更新领取收益时间
            //判断订单累积收益是否已经等于产品收益的极限 如果相等则产品将停止收益
            /*
            $out_income = $order['sumprice']*2;//产品收益极限
            if ($already_profit == $out_income) {
                    $data['state'] = 2;
                    Db::name("order")->where(['user_id'=>$uid,'id'=>$oid])->update($data);//订单更新
            }*/

            $user = Db::name("user")->where('id', $uid)->find();//获取用户信息
            Db::name("order")->where(['user_id' => $uid, 'id' => $oid])->update($data);//订单更新
            Db::name("user")->where('id', $uid)->inc('money', $shouyi)->update();//用户余额更新
            //更新资金变动表
            $params['username'] = $username;
            $params['adds'] = $shouyi;
            $params['balance'] = $user['money'] + $shouyi;
            $params['addtime'] = time();
            $params['desc'] = lang('ro_shouyi_lingqu');//收益领取
            $params['uid'] = $uid;
            Db::name('jinbidetail')->insert($params);
            //判断是否返回本金
            $total = $order['yxzq'] * $order['shouyi'];

            //判断订单累积收益是否已经等于产品收益的极限 如果相等则产品将停止收益
            /*if ($total>$out_income){
                    $total = $out_income;
            }*/

            if ($already_profit >= $total) {
                Db::name("order")->where(['user_id' => $uid, 'id' => $oid])->update(['state' => 2]);//订单更新
//				Db::name("user")->where('id', $uid)->inc('money', $order['sumprice'])->update();//反还用户本金
                //记录返回本金
                $sumprice['username'] = $username;
                $sumprice['adds'] = $order['sumprice'];
//				$sumprice['balance'] = $user['money'] + $order['sumprice'];
                $sumprice['balance'] = $user['money'];
                $sumprice['addtime'] = time();
//				$sumprice['desc'] = lang('Retornar principal');//返回本金
                $sumprice['uid'] = $uid;
                Db::name('jinbidetail')->insert($sumprice);
            }
            //用户上级挣取的收益
            $parent_id = $user['parent_id'];
            //判断用户是否有上级
            if ($parent_id) {
                $config = Db::name("proconfig")->where('id', 1)->find();//获取产品配置
                $oneinfo = Db::name("user")->where('id', $parent_id)->find();//获取直推上级信息

                //TODO   收益比例计算
                if ($order['active_type'] == 1) {
                    $one_profit = $order['one_profit'];//直推收益奖励
                    $two_profit = $order['two_profit'];//二代收益奖励
                    $three_profit = $order['three_profit'];//三代收益奖励
                } else {
                    $one_profit = $config['one_profit'];//直推收益奖励
                    $two_profit = $config['two_profit'];//二代收益奖励
                    $three_profit = $config['three_profit'];//三代收益奖励
                }


                if ($oneinfo['lv_id'] >= $user['lv_id']) {
                    //直推收益
                    $profit = $one_profit / 100 * $shouyi;
                    Db::name("user")->where('id', $parent_id)->inc('money', $profit)->update();
                    //更新直推上级资金变动表
                    $one['username'] = $oneinfo['username'];
                    $one['adds'] = $profit;
                    $one['balance'] = $oneinfo['money'] + $profit;
                    $one['addtime'] = time();
                    $one['desc'] = lang('ro_zhitui_jiangli') . $profit;//直推用户收益奖励金额
                    $one['uid'] = $oneinfo['id'];
                    $one['type'] = 2;//收支类型 下级返佣
                    Db::name('jinbidetail')->insert($one);
                }

                //判断是否存在二代上级
                $twopid = Db::name("user")->where('id', $parent_id)->value('parent_id');
                if ($twopid) {
                    $twoinfo = Db::name("user")->where('id', $twopid)->find();//获取二代上级信息

                    if ($twoinfo['lv_id'] >= $user['lv_id']) {
                        //二级收益
                        $two_profit = $two_profit / 100 * $shouyi;

                        Db::name("user")->where('id', $twopid)->inc('money', $two_profit)->update();
                        //更新资金变动表
                        $two['username'] = $twoinfo['username'];
                        $two['adds'] = $two_profit;
                        $two['balance'] = $twoinfo['money'] + $two_profit;
                        $two['addtime'] = time();
                        $two['desc'] = lang('ro_erdai_jiangli') . $two_profit;//二代用户分红收益奖励金额
                        $two['uid'] = $twoinfo['id'];
                        $two['type'] = 2;//收支类型 下级返佣
                        Db::name('jinbidetail')->insert($two);
                    }

                    //判断是否存在三代上级
                    $threepid = Db::name("user")->where('id', $twopid)->value('parent_id');
                    if ($threepid) {
                        $threeinfo = Db::name("user")->where('id', $threepid)->find();//获取三代上级信息

                        if ($threeinfo['lv_id'] >= $user['lv_id']) {
                            //三级收益
                            $three_profit = $three_profit / 100 * $shouyi;
                            Db::name("user")->where('id', $threepid)->inc('money', $three_profit)->update();
                            //更新资金变动表
                            $three['username'] = $threeinfo['username'];
                            $three['adds'] = $three_profit;
                            $three['balance'] = $threeinfo['money'] + $three_profit;
                            $three['addtime'] = time();
                            $three['desc'] = lang('ro_san_jiangli') . $three_profit;//三代用户收益奖励金额
                            $three['uid'] = $threeinfo['id'];
                            $three['type'] = 2;//收支类型 下级返佣
                            Db::name('jinbidetail')->insert($three);
                        }

                    }
                }
            }
            return json(['status' => 1, 'msg' => lang('ro_income_successt')]);
        }
    }


}

