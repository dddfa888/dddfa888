<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//通道设置
class Channel extends Base{

	public function index(){
		$oModel = Db::name('Channel');
		$list = $oModel->select();
		return view('index',[
			'list'=>$list,
        ]);

	}

    
	public function add(){
		if (request()->isPost()) {
            $params['channel_name'] = input('post.channel_name');
            $params['switch'] = input('post.switch')?1:0;
            $params['withdraw_lowest'] = input('post.withdraw_lowest');
            $params['withdraw_highest'] = input('post.withdraw_highest');
            $params['mark'] = input('post.mark');
            $params['recharge_lowest'] = input('post.recharge_lowest');
            $params['recharge_highest'] = input('post.recharge_highest');
            $res = Db::name('Channel')->insert($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'添加成功']);
            }else{
            	return json(['status'=>0,'msg'=>'添加失败']);
            }
        }
		return view('add',[

        ]);
	}


    public function edit(){
        if (request()->isPost()) {
            $id = input('post.id');
            $params['channel_name'] = input('post.channel_name');
            $params['switch'] = input('post.switch');
            $params['withdraw_lowest'] = input('post.withdraw_lowest');
            $params['withdraw_highest'] = input('post.withdraw_highest');
            $params['mark'] = input('post.mark');
            $params['recharge_lowest'] = input('post.recharge_lowest');
            $params['recharge_highest'] = input('post.recharge_highest');
            $res = Db::name('Channel')->where('id',$id)->update($params);
            if ($res) {
                return json(['status'=>1,'msg'=>'操作成功']);
            }else{
                return json(['status'=>0,'msg'=>'操作失败']);
            }
        }
        $id = input('id');
        $one = Db::name('Channel')->find($id);
        return view('edit',[
            'one'=>$one,
        ]);
    }































}