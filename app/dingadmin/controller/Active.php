<?php
namespace app\dingadmin\controller;
use think\facade\Request;
use think\facade\Db;
use app\common\model\Category as CateModel;//设置别名
use think\facade\View;

//活动管理
class Active extends Base{

	public function index($cid=null){
		//栏目筛选条件
		$list = Db::name('active')
            ->alias('a')
            ->order('addtime desc')
            ->paginate(10,false);
        $page = $list->render();

		return view('index',[
			'list'=>$list,
            'page'=>$page,
		]);
	}

    //添加
    public function add(){
        if(request()->isPost()){
            $data = input('post.');
            $data['addtime'] = strtotime($data['addtime']);//转时间戳
            if(!isset($data['status'])){
                $data['status'] = 0;
            }

            $getID = Db::name('Active')->insertGetId($data);

            if(!$getID){
                return json(['status'=>0,'msg'=>'添加失败']);
            }

            return json(['status'=>1,'msg'=>'添加成功']);
        }
        return view('add');
    }

    //编辑
    public function edit(){
        $id=input('id');
        if(request()->isPost()){
          $data = input('post.');
          $data['addtime'] = strtotime($data['addtime']);//转时间戳
          if(!isset($data['status'])){
            $data['status'] = 0;
          }

            Db::name('Active')->where('id',$id)->update($data);

            return json(['status'=>1,'msg'=>'更新成功']);
        }
        //获取文章信息
        $article = Db::name('Active')->find($id);

        return view('edit',[
            'article'=>$article
        ]);
    }

    //文章删除
    public function del(){
        $id=input('id');
        $result = Db::name('Active')->delete($id);
        if ($result) {
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
        
            return json(['status'=>0,'msg'=>'删除失败']);
        }
    }

    //置顶
    public function status(){
        if(request()->isAjax()){
            $data = input('post.');
            $value = []; 

            $value['id'] = $data['id'];
            if($data['status']==="true"){
                $value['status'] = 1;
                if (Db::name('Active')->where('id',$value['id'])->update($value)) {
                    return json(['code'=>1,'msg'=>"设置成功"]);
                }
                return json(['code'=>0,'msg'=>"操作失败"]);
            }else{
                $value['status'] = 0;
                if(Db::name('Active')->where('id',$value['id'])->update($value)){
                    return json(['code'=>1,'msg'=>"取消成功"]);
                }
                return json(['code'=>0,'msg'=>"操作失败"]);
            }
        }
    }

























}
