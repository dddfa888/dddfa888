<?php
namespace app\dingadmin\controller;
use think\facade\Request;
use think\facade\Db;
use think\facade\View;

//产品管理
class Product extends Base{

	public function index(){
		$list = Db::name('product')->alias('a')->field("a.*,b.lv_name")->order('a.sort asc')->join('level b','b.level=a.allow_lv','LEFT')->select();

		return view('index',[
			'list'=>$list,
        ]);
	}


	public function add(){
		if (request()->isPost()) {
            $params['title'] = input('post.title');
            $params['zhouqi'] = input('post.zhouqi');
            $params['type_id'] = input('post.type_id');
            $params['price'] = input('post.price');
            $params['yxzq'] = input('post.yxzq');
            $params['day_shouyi'] = input('post.day_shouyi');
            $params['xiangou'] = input('post.xiangou');
            $params['is_on'] = input('post.is_on') ? 1 : 0;
            $params['thumb'] = input('post.thumb');
            $params['content'] = input('post.content');
            $params['stock'] = input('post.stock');
            $params['allow_lv'] = input('post.allow_lv');//允许购买等级
            $params['sort'] = input('post.sort');
            $params['type'] = input('post.type');
            $params['integral'] = input('post.integral');
            $res = Db::name('product')->insert($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'添加成功']);
            }else{
            	return json(['status'=>0,'msg'=>'添加失败']);
            }
        }
        $lvarr = Db::name('level')->order('level acs')->select();
		return view('add',[
            'lvarr'=>$lvarr,
        ]);
	}


	public function edit(){
		if (request()->isPost()) {
			$id = input('post.id');
            $params['title'] = input('post.title');
            $params['zhouqi'] = input('post.zhouqi');
            $params['type_id'] = input('post.type_id');
            $params['price'] = input('post.price');
            $params['yxzq'] = input('post.yxzq');
            $params['day_shouyi'] = input('post.day_shouyi');
            $params['xiangou'] = input('post.xiangou');
            $params['is_on'] = input('post.is_on') ? 1 : 0;
            $params['thumb'] = input('post.thumb'); 
            $params['content'] = input('post.content');
            $params['stock'] = input('post.stock');
            $params['type'] = input('post.type');
            $params['allow_lv'] = input('post.allow_lv');//允许购买等级
            $params['sort'] = input('post.sort');
            $params['integral'] = input('post.integral');
            $res = Db::name('product')->where('id',$id)->update($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'操作成功']);
            }else{
            	return json(['status'=>0,'msg'=>'操作失败']);
            }
        }
		$id = input('id');
		$one = Db::name('product')->find($id);
        $lvarr = Db::name('level')->order('level acs')->select();
		return view('edit',[
            'lvarr'=>$lvarr,
			'one'=>$one
        ]);
	}

	public function del(){
        $id = input('id');
        $res = Db::name('project')->where('id',$id)->delete();
        if ($res) {
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
	}

    public function upload(){
        //获得上传文件
        $file = request()->file('imgfile');
        //判断$file是否为文件对象
        if($file){
            $savename = \think\facade\Filesystem::putFile( 'produt', $file);
            $savename=str_replace('\\','/',$savename);
            //dump($savename);die;  upload/20201210/f86c0a418e19e02dd973a84ae3de3928.png
            //获取上传到服务器上的文件路径
            $imgpath = "/public/".$savename;
            return json(['code'=>1,'msg'=>'上传成功','img'=>$imgpath]);
        }else{
            return json(['code'=>0,'msg'=>$file->getError()]);
        }
    }



















}
