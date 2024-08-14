<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//账号管理
class Zhanghao extends Base{

	public function index(){
		$zhModel = Db::name('zhanghao');
		$title      = input("param.title");
        $type      = input("param.type");
        $ipweb      = input("param.ipweb");
        $where = '';
        if ($title != '') {
            $zhModel->wherelike('title','%'.$title.'%');
            View::assign('title',$title);
        }else{
            View::assign('title','');
        }

        if ($type != '') {
            $zhModel->wherelike('type',$type);
            View::assign('type',$type);
        }else{
            View::assign('type','');
        }

        if ($ipweb != '') {
            $zhModel->wherelike('ipweb','%'.$ipweb.'%');
            View::assign('ipweb',$ipweb);
        }else{
            View::assign('ipweb','');
        }
        
		$list = $zhModel->order('type asc')->paginate(30);
		$page = $list->render();
		return view('index',[
			'list'=>$list,
			'page'=>$page,
        ]);

	}


	public function add(){
		if (request()->isPost()) {
            $params['title'] = input('post.title');
            $params['account_info'] = input('post.account_info');
            $params['type'] = input('post.type');
            $params['remarks'] = input('post.remarks');
            $params['kefu'] = input('post.kefu');
            $params['ipweb'] = input('post.ipweb');
            $params['endtime'] = strtotime(input('post.endtime'));
            $res = Db::name('zhanghao')->insert($params);
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
            $params['title'] = input('post.title');
            $params['account_info'] = input('post.account_info');
            $params['type'] = input('post.type');
            $params['remarks'] = input('post.remarks');
            $params['kefu'] = input('post.kefu');
            $params['ipweb'] = input('post.ipweb');
            $params['endtime'] = strtotime(input('post.endtime'));
            $res = Db::name('zhanghao')->where('id',$id)->update($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'编辑成功']);
            }else{
            	return json(['status'=>0,'msg'=>'编辑失败']);
            }
        }
        $id = input('id');
		$one = Db::name('zhanghao')->find($id);
		return view('edit',[
        	'one'=>$one
        ]);
	}

	public function del(){
		$id = input('id');
        $res = Db::name('zhanghao')->where('id',$id)->delete();
        if ($res) {
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
	}




































}