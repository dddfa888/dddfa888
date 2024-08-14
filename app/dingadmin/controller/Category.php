<?php
namespace app\dingadmin\controller;
use think\facade\Request;
use think\facade\Db;
use app\common\model\Category as CateModel;//设置别名
//栏目管理
class Category extends Base{


	public function index(){
		$cateall = Db::name('category')->order('sort desc')->select();
		$cate = CateModel::getcateall($cateall,0,-1);	
		return view('index',[
            'cate'=>$cate
        ]);
	}

	public function add(){
		if(request()->isPost()){
			$data = input('post.');
			unset($data['imgfile']);
			$result = CateModel::create($data);//Model的静态新增方法 返回的是当前模型的对象实例
			if($result){
				return json(['status'=>1,'msg'=>'栏目添加成功']);
			}
		}
		//获取栏目列表
		$cateall = Db::name('category')->select();	
		$cate = CateModel::getcateall($cateall,0,-1);
		return view('add',[
            'cate'=>$cate
        ]);
	}

	public function edit($id){
		if(request()->isPost()){
			$data = input('post.');
			//获取子栏目的id信息和自己本身id
			$cateids = CateModel::getchildids($id);
			$cateids[] = $id;
			//判断更新条件 选择的父级不能是自己的子栏目和自己
			if(in_array($data['pid'], $cateids)){
				return json(['status'=>0,'msg'=>'上级栏目选择错误']);
			}

			//判断通过后进行更新操作
			$res = CateModel::where('id',$id)->update($data);
			if($res){
				return json(['status'=>1,'msg'=>'栏目编辑成功']);
			}else{
				return json(['status'=>0,'msg'=>'更新失败']);
			}
		}

		//获取上级栏目数据
		$cateall = Db::name('category')->order('sort Desc,id Asc')->select();		
		$cate = CateModel::getcateall($cateall,0,-1);
		//获取当前栏目
		$one = Db::name('category')->find($id);
		return view('edit',[
            'cate'=>$cate,
	        'one'=>$one,
        ]);
	}

	public function sort(){
		if(request()->isPost()){
			$data = input('post.');
			$result = CateModel::sort($data);//更新成功返回ture
			if($result){
				return json(['status'=>1,'msg'=>'栏目排序成功']);
			}else{
				return json(['status'=>0,'msg'=>'操作异常']);
			}
		}
	}

	//栏目删除
	public function del(){
		$id=input('id');
		//判断是否有下级栏目
		$res = Db::name('category')->where('pid',$id)->select()->toArray();
		if($res){
			return json(['status'=>0,'msg'=>'有下级栏目不能删除该栏目']);
		}
		//判断该栏目下是否有文章
		$a_res = Db::name('article')->where('cid',$id)->select()->toArray();
		if($a_res){
			return json(['status'=>0,'msg'=>'该栏目下有文章不能直接删除']);
		}
		//进行删除操作
		$result = Db::name('category')->delete($id);
		if($result){
			return json(['status'=>1,'msg'=>'栏目删除成功']);
			$this->success('栏目删除成功','category/index','',1);
		}else{
			return json(['status'=>0,'msg'=>'栏目删除失败']);
		}
	}

	
}