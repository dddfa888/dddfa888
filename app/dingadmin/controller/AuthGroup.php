<?php
namespace app\dingadmin\controller;
use think\facade\Db;
class AuthGroup extends Base{

	public function index(){
		$authGroupData=Db::name('authGroup')->select();
		return view('admin/group',[
            'authGroupData'=>$authGroupData,
            'left_menu'=>3
        ]);
	}

	public function add(){
        if(request()->isPost()){
            $data=input('post.');
            $add=Db::name('authGroup')->insert($data);
            if($add){
                return alert('操作成功！','index',6,3);
            }else{
                return alert('操作失败！','index',5,3);
            }
            return;
        }
        return view('admin/group_add',[
            'left_menu'=>3
        ]);
    }

    public function edit(){
        if(request()->isPost()){
            $data=input('post.');

            $save=Db::name('auth_group')->update($data);
            if($save!==false){
                return alert('操作成功！','index',6,3);
            }else{
                return alert('操作失败！','index',6,3);
            }
            return;
        }
        $id=input('id');
        $authGroupData=Db::name('auth_group')->find($id);
        return view('admin/group_edit',[
            'authGroupData'=>$authGroupData,
            'left_menu'=>3
        ]);
    }

    //删除
    public function del(){
        $id=input('id');
        $res=Db::name('auth_group')->delete($id);
        if($res){
            return alert('删除成功！','index',6,3);
        }else{
            return alert('删除失败！','index',5,3);
        }
    }

    //分配权限
    public function power(){
        $id=input('id');
        $authGroupData=Db::name('auth_group')->find($id);
       
        $authRuleData=Db::name('auth_rule')->where('parent_id',0)->where('status',1)->select()->toArray();
        foreach($authRuleData as $k=>$v){
            $authRuleData[$k]['children']=Db::name('auth_rule')->where('parent_id',$v['id'])->where('status',1)->select()->toArray();
        }
        
        $rulesArr=explode(',',$authGroupData['rules']);
        if(request()->isPost()){
            $data=input('post.');
            $data['rules']=implode(',',$data['rules']);
            $res=Db::name('auth_group')->update($data);
            if($res){
                return alert('操作成功！','index',6,3);
            }else{
                return alert('操作失败！','index',5,3);
            }
        }
        return view('admin/group_power',[
            'authGroupData'=>$authGroupData,
            'authRuleData'=>$authRuleData,
            'rulesArr'=>$rulesArr
        ]);
    }


















































}