<?php
namespace app\dingadmin\controller;
use think\facade\Request;
use think\facade\Db;
use app\common\model\Category as CateModel;//设置别名
use think\facade\View;

//文章管理
class Article extends Base{

	public function index($cid=null){
		//栏目筛选条件 
        if($cid){
            $map = ['a.cid'=>$cid];
        }else{
            $map = [];
        }
		View::assign('cid',$cid);
		$list = Db::name('article')
            ->alias('a')
            ->join('category b','b.id=a.cid')
            ->join('pics c','c.aid=a.id','LEFT') //LEFT即使右表中没有匹配，也从左表返回所有的行
            ->order('a.istop Desc,a.toptime Asc,a.addtime Asc')
            ->field('a.*,b.name,count(c.pic) as pic')
            ->where($map)
            ->group('a.id')
            ->paginate(10,false,['query'=>['cid'=>$cid]]);
        $page = $list->render();
		//获取栏目列表
		$cateall = Db::name('category')->order('sort Desc,id Asc')->select()->toArray();
        $cate = CateModel::getcateall($cateall,0,-1);//优化后的方法
		return view('index',[
			'cate'=>$cate,
			'list'=>$list,
            'page'=>$page,
		]);
	}

    //文章添加
    public function add(){
        if(request()->isPost()){
            $data = input('post.');
            $data['addtime'] = strtotime($data['addtime']);//转时间戳
            //判断是否有置顶信息传递过来
            if(isset($data['istop'])){
                $data['toptime'] = time();//添加置顶时间数据
            }

            $new['cid'] = $data['cid'];
            $new['title'] = $data['title'];
            $new['keyword'] = $data['keyword'];
            $new['desc'] = $data['desc'];
            $new['remark'] = $data['remark'];
            $new['author'] = $data['author'];
            $new['views'] = $data['views'];
            $new['addtime'] = $data['addtime'];
            $new['istop'] = isset($data['istop']) ? 1 : 0;
            $new['content'] = $data['content']; 
            $getID = Db::name('Article')->insertGetId($new);

            if(!$getID){
                return json(['status'=>0,'msg'=>'文章添加失败']);
            }
            //判断是否有图片传递过来  一篇文章配多个图片
            if($data['pic']!=''){
                $arr_tmp = explode(',',$data['pic']);
                $aid = $getID;
                $thum=[];
                foreach($arr_tmp as $v){
                    $thum[]=['aid'=>$aid,'pic'=>$v];
                }
                Db::name('pics')->insertAll($thum);
            }
            return json(['status'=>1,'msg'=>'文章添加成功']);
        }
        //获取栏目列表
        $cateall = Db::name('category')->order('sort Desc,id Asc')->select();
        $cate = CateModel::getcateall($cateall,0,-1);//优化后的方法
        return view('add',[
            'cate'=>$cate,
        ]);
    }

    //文章编辑
    public function edit(){
        $id=input('id');
        if(request()->isPost()){
            $data = input('post.');
            $data['addtime'] = strtotime($data['addtime']);
            if(isset($data['istop'])){
                $data['toptime'] = time();
            }
            $new['cid'] = $data['cid'];
            $new['title'] = $data['title'];
            $new['keyword'] = $data['keyword'];
            $new['desc'] = $data['desc'];
            $new['remark'] = $data['remark'];
            $new['author'] = $data['author'];
            $new['views'] = $data['views'];
            $new['addtime'] = $data['addtime'];
            $new['istop'] = isset($data['istop']) ? 1 : 0;
            $new['content'] = $data['content'];

            Db::name('Article')->where('id',$id)->update($new);
            if($data['pic']!=''){
                $arr_tmp = explode(',',$data['pic']);
                $aid = input('id');
                $thum=[];
                foreach($arr_tmp as $v){
                    $thum[]=['aid'=>$aid,'pic'=>$v];
                }
                Db::name('pics')->insertAll($thum);
            }
            return json(['status'=>1,'msg'=>'文章更新成功']);
        }
        //获取文章信息
        $article = Db::name('article')->find($id);
        $article['pic'] = Db::name('pics')->where('aid',$article['id'])->field('pic')->select()->toArray();

        //获取栏目列表
        $cateall = Db::name('category')->order('sort Desc,id Asc')->select();
        $cate = CateModel::getcateall($cateall,0,-1);
        return view('edit',[
            'article'=>$article,
            'cate'=>$cate
        ]);
    }

    //文章删除
    public function del(){
        $id=input('id');
        $result = Db::name('Article')->delete($id);
        if ($result) {
            $res = Db::name('pics')->where('aid',$id)->select();
            //如果有对应的图片，对图片进行删除
            if($res){
                foreach($res as $v){
                    $file = app()->getRootPath().$v['pic'];
                    if(file_exists($file)){
                        @unlink($file);
                    }
                }
                //删除该文章的图片数据
                Db::name('pics')->where('aid',$id)->delete();
            }
            return json(['status'=>1,'msg'=>'文章删除成功']);
        }else{
        
            return json(['status'=>0,'msg'=>'文章删除失败']);
        }
    }

    //置顶
    public function istop(){
        if(request()->isAjax()){
            $data = input('post.');
            $value = []; 

            $value['id'] = $data['id'];
            $value['toptime'] = time();
            if($data['istop']==="true"){
                $value['istop'] = 1;
                if (Db::name('Article')->where('id',$value['id'])->update($value)) {
                    return json(['code'=>1,'msg'=>"置顶成功"]);
                }
                return json(['code'=>0,'msg'=>"操作失败"]);
            }else{
                $value['istop'] = 0;
                if(Db::name('Article')->where('id',$value['id'])->update($value)){
                    return json(['code'=>1,'msg'=>"取消置顶成功"]);
                }
                return json(['code'=>0,'msg'=>"操作失败"]);
            }
        }
    }

    //图片列表
    public function pics($aid){
        $pics = Db::name('pics')->where('aid',$aid)->order('sort Desc,id Desc')->select();
        return view('pics',[
            'pics'=>$pics,
        ]);
    }

    //图片排序
    public function picsort(){
        if(request()->isAjax()){
            $data = input('post.');
            $result = Db::name('pics')->update($data);
            if($result){
                return json(['code'=>1,'msg'=>"排序成功"]);
            }
            return json(['code'=>0,'msg'=>"排序失败"]);
        }
    }

























}