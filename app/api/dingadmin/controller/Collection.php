<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//收款设置
class Collection extends Base{

	public function index(){
		$oModel = Db::name('Collection');
		$list = $oModel->select();
		return view('index',[
			'list'=>$list,
        ]);

	}

    
	// public function add(){
	// 	if (request()->isPost()) {
 //            $params['type'] = input('post.type');
 //            $params['bank_info'] = input('post.bank_info');
 //            $params['bank_num'] = input('post.bank_num');
 //            $params['bank_username'] = input('post.bank_username');
 //            $res = Db::name('Collection')->insert($params);
 //            if ($res) {
 //            	return json(['status'=>1,'msg'=>'添加成功']);
 //            }else{
 //            	return json(['status'=>0,'msg'=>'添加失败']);
 //            }
 //        }
	// 	return view('add',[

 //        ]);
	// }


    public function edit(){
        if (request()->isPost()) {
            $id = input('post.id/s');
            $params['type'] = input('post.type/s');
            $params['bank_info'] = input('post.bank_info/s');
            $params['bank_info_en'] = input('post.bank_info_en/s');
            $params['bank_num'] = input('post.bank_num/s');
            $params['bank_username'] = input('post.bank_username/s');
            $res = Db::name('Collection')->where('id',$id)->update($params);
            if ($res) {
                return json(['status'=>1,'msg'=>'操作成功']);
            }else{
                return json(['status'=>0,'msg'=>'操作失败']);
            }
        }
        $id = input('id');
        $one = Db::name('Collection')->find($id);
        return view('edit',[
            'one'=>$one,
        ]);
    }































}