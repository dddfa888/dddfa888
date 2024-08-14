<?php
namespace app\index\controller;
use think\facade\Db;
use think\facade\View;
use think\facade\Lang;

class Task extends Base{

    public function index(){
	    $uid = session('uid');
	    $list = Db::name('product')->order('sort asc')->where(['is_on'=>1,'type_id'=>2])->select()->toArray();
	    $investmentAmount = 0;
	    $yesterdaysEarnings = 0;
	    $profitAll = Db::name('order')->where(['order_type'=>2,'user_id'=>$uid])->sum('already_profit');
	    $orders = Db::name('order')->where(['order_type'=>2,'state'=>1,'user_id'=>$uid])->select();
	    $balance = Db::name('user')->where(['id'=>$uid])->value('money');
	    foreach ($orders as $k=>$v){
		    $investmentAmount+=$v['sumprice'];
		    $yesterdaysEarnings+=$v['sumprice'] * ($v['shouyi']/100);
	    }
	    
	    
      return View('index',compact('list','investmentAmount','yesterdaysEarnings','profitAll','balance'));
    }

    //文章详情
    public function details(){
        $aid = input('id');
        $one = Db::name('article')->where('id',$aid)->find();
        return View('details',[
            'one'=>$one,
        ]);
    }


    public function uploads(){
    	  //获得上传文件
        $file = request()->file('file');
        //判断$file是否为文件对象
        if($file){
            $savename = \think\facade\Filesystem::putFile('photoimg', $file);
            $savename=str_replace('\\','/',$savename);
            //dump($savename);die;  upload/20201210/f86c0a418e19e02dd973a84ae3de3928.png
            //获取上传到服务器上的文件路径
            $imgpath = "/public/".$savename;
            return json(['code'=>1,'msg'=>lang('upload_success'),'img'=>$imgpath]);
        }else{
            return json(['code'=>0,'msg'=>$file->getError()]);
        }
    }



}

