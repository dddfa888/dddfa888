<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;
class Auth extends Base{

	//角色列表
    public function index(){
        $list = Db::name('auth_group')->paginate(10);
        $page = $list->render();//获取分页显示
        return view('index',[
            'list'=>$list,
            'page'=>$page
        ]);
        return view();
    }

    //添加角色
    public function add(){
        if (request()->isPost()) {
            $is_manager = input('param.is_manager') == 'on' ? 1 :0;
            $params = array(
                'title' => input('param.title','','trim'),
                'is_manager'=>$is_manager,
                'status' => 1,
                'rules' => '',
            );
            if(!$params['title']){
               return json(['status'=>0,'msg'=>'请输入角色名称!']);
            }
            $res = Db::name('auth_group')->insert($params);
            return json(['status'=>$res]);
        }
        return view();
    }

    //编辑角色
    public function edit(){
        if (request()->isPost()) {
            $is_manager = input('param.is_manager') == 'on' ? 1 :0;
            $params = array(
                'id' => input('id', 0, 'intval'),
                'title' => input('title'),
                'is_manager'=>$is_manager,
            );
            // dump($params);die;
            if(!$params['id']){
                return json(['status'=>'error','msg'=>'角色不存在!']);
            }
            if(!$params['title']){
                return json('请输入角色名称!');
            }
            $res = Db::name('auth_group')->where('id',$params['id'])->update(['title'=>$params['title'],'is_manager'=>$params['is_manager']]);
            return json(['status'=>$res,'msg'=>'修改成功!']);
        }
        $id = input('id', 0, 'intval');
        $group_info = Db::name('auth_group')->find($id);
        return view('edit',[
            'group_info'=>$group_info,
        ]);
    }

    //删除角色处理
    public function del(){
        $id = input('id', 0, 'intval');
        if(!$id){
            return json(['status'=>'error','msg'=>'角色不存在!']);
        }
        $res = Db::name('auth_group')->delete($id);
        if ($res) {
            return json(['status'=>$res,'msg'=>'修改成功!']);
        }else{
            return json(['status'=>$res,'msg'=>'修改失败!']);
        }
    }

    //权限分配
    public function rule(){
        if (request()->isPost()) {
            $data = input('post.');
            $rule_ids = implode(",", $data['menu']);
            $role_id = $data['roleid'];
            $res = Db::name('auth_group')->where('id',$role_id)->update(['rules' => $rule_ids]);
            if($res){
                return json(['status'=>1,'msg'=>'分配成功']);
            }else{
                return json(['status'=>0,'msg'=>'分配失败，请检查']);
            }
        }else{
            $role_id = input('param.roleid',0,'intval');
            $list = Db::name('auth_rule')->where('status',1)->select()->toArray();
            $menus = getmenus($list,2);//显示菜单 get_column递归菜单排序 1:顺序菜单 2树状菜单
            $role_info = Db::name('auth_group')->where(['id'=>$role_id,'status' =>1])->find();
            if ($role_info['rules']) {
                $rulesArr = explode(',',$role_info['rules']);
                View::assign('rulesArr',$rulesArr);
            }else{
                View::assign('rulesArr','');
            }
            return view('rule',[
                'menus'=>$menus,//所有权限信息
                'role_id'=>$role_id,//角色id
            ]);
        }
    }















































}