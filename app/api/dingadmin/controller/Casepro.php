<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;

//案例管理
class Casepro extends Base{
	public function index(){
		$caseModel = Db::name('Casepro');
		$proname      = input("param.proname");

        $where = '';
        if ($proname != '') {
            $caseModel->wherelike('title','%'.$proname.'%');
            View::assign('proname',$proname);
        }else{
            View::assign('proname','');
        }

		$list = $caseModel->paginate(30);
		$page = $list->render();
		return view('index',[
			'list'=>$list,
			'page'=>$page,
        ]);

	}


	public function add(){
		if (request()->isPost()) {
            $params['proname'] = input('post.proname');
            $params['info'] = input('post.info');
            $params['remarks'] = input('post.remarks');
            $params['baota'] = input('post.baota');
            $params['aid'] = input('post.aid');
            $res = Db::name('casepro')->insert($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'添加成功']);
            }else{
            	return json(['status'=>0,'msg'=>'添加失败']);
            }
        }
        //获取网站域名
		return view('add',[

        ]);
	}


	public function edit(){
		if (request()->isPost()) {
			$id = input('post.id');
            $params['proname'] = input('post.proname');
            $params['info'] = input('post.info');
            $params['remarks'] = input('post.remarks');
            $params['baota'] = input('post.baota');
            $params['aid'] = input('post.aid');
            $res = Db::name('casepro')->where('id',$id)->update($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'编辑成功']);
            }else{
            	return json(['status'=>0,'msg'=>'编辑失败']);
            }
        }
        $id = input('id');
		$one = Db::name('casepro')->find($id);
		return view('edit',[
        	'one'=>$one
        ]);
	}

	public function del(){
		$id = input('id');
        $res = Db::name('casepro')->where('id',$id)->delete();
        if ($res) {
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
	}
}