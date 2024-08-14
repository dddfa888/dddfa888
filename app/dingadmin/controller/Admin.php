<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\Request;

class Admin extends Base{

    public function index(){
        $list = Db::name('admin')->alias('a')->field("a.*,b.title")->join('auth_group b','b.id=a.groupid','LEFT')->order('a.id asc')->paginate(10);
        $page = $list->render();
        return view('index',[
            'list'=>$list,
            'page'=>$page
        ]);
    }

    public function add(Request $request){
        if (request()->isPost()) {
            $data = input("post.");
            if(!$data["account"]){
                return json(['status'=>0,'msg'=>'请输入用户名!']);
            }
            if(!$data["password"]){
                return json(['status'=>0,'msg'=>'请输入密码!']);
            }
            if($data["password"] != $data["reppassword"]){
                return json(['status'=>0,'msg'=>'两次输入密码不一致！']);
            }

          $admin_auth = session('admin_auth');
          $data['pid'] = $admin_auth['id'];
            $data["password"] = md5($data["password"]);
            $data["login_time"]=time();
            if(Db::name('admin')->where('account',$data["account"])->find()){
                return json(['status'=>0,'msg'=>'用户名已存在！']);
            }
            $check = $request->checkToken('__token__');
            if(false === $check) {
                throw new ValidateException('invalid token');
            }
            unset($data['__token__']);
            unset($data['reppassword']);
            $add_id = Db::name('admin')->insertGetId($data);//返回添加的id
            if($add_id){
                //更新权限
                $groupAccess= Db::name("auth_group_access")->where("uid",$add_id)->find();
                if($groupAccess&&$groupAccess["group_id"]!=$data["groupid"]){
                    Db::name("auth_group_access")->where("uid",$add_id)->setField("group_id",$data["groupid"]);
                }else{
                    Db::name("auth_group_access")->insert(["uid"=>$add_id,"group_id"=>$data["groupid"]]);
                }
            }
            return json(['status'=>1,'msg'=>'添加成功!']);
        }
        //用户组
        $groups = Db::name('auth_group')->where(['status' => 1])->field('id,title')->select();
        return view('add',[
            'groups'=>$groups,
        ]);
    }

    public function edit(){
        $id=input('id');
        if(request()->isPost()){
            $data=input('post.');
            if(!empty($data['password'])){
                $data['password']=md5($data['password']);
            }else{
                unset($data['password']);
                unset($data['reppassword']);
            }
            
            $res=Db::name('admin')->update($data);
            if($res){
                return json(['status'=>1,'msg'=>'修改成功!']);
            }else{
                return json(['status'=>0,'msg'=>'修改失败!']);
            }
        }
        $admin_info=Db::name('admin')->find($id);
        $groups=Db::name('auth_group')->select();
        return view('edit',[
            'admin_info'=>$admin_info,
            'groups'=>$groups,
        ]);
    }

    public function del(){
        $id=input('id');
        $res=Db::name('admin')->delete($id);
        if($res){
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
    }





















































}
