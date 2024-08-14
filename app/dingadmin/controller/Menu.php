<?php
namespace app\dingadmin\controller;
use think\facade\Request;
use think\facade\Db;
//菜单页面的只显示一二级的 三级之后的不显示 用字段is_menu来判断是否显示
class Menu extends Base{
	//列表页
    public function index () {
        $where = [
            'status'=>1,
            'is_menu'=>1,
        ];
        $menu = Db::name('auth_rule')->where($where)->select()->toArray();
        $menu = getmenus($menu);//递归菜单
        foreach ($menu as $key=>$item){
            list($controller,$action) = explode('/',$item['menu_name']);
            $menu[$key]['controller'] = $controller;
            $menu[$key]['action'] = $action;
        }
        return view('index',[
            'menu'=>$menu
        ]);
    }

    //查询是否已存在的控制器和方法
    public function isExistOpt($controller,$action,$id=null){
        $where = array(
            'menu_name' => $controller.'/'.$action,
            'status'    => 1,
        );
        if($id){
            $where['id'] = array('neq',$id);
        }
        return Db::name('auth_rule')->where($where)->find();
    }

    //添加菜单
    public function add(){
        if (Request::isAjax()) {
            $pid = input('param.pid',0,'intval');
            $rows = input('m/a');
            //判断添加的菜单是否为三级菜单
            if ($pid) {
                $pidvalue = Db::name('auth_rule')->where('id',$pid)->value('pid');
                $rows['is_menu'] = $pidvalue ? 0 : 1;
                $rows['pid'] = $pid;
            }else{
                $rows['is_menu'] = 1;
                $rows['pid'] = 0;
                //判断添加的菜单是否已经存在
                if($this->isExistOpt($rows['controller'], $rows['action'])){
                    return json(['status'=>0,'msg'=>'该菜单已存在']);
                }
            }
            
            //需添加的数据
            $data = [
                'menu_name' => $rows['controller'] ? ucfirst($rows['controller']).'/'.$rows['action'] : '', 
                'icon' => $rows['menuicon'],
                'title' => $rows['menuname'],
                'pid' => $rows['pid'],
                'is_menu' => $rows['is_menu'],
            ];
            //添加
            $res = Db::name('auth_rule')->insert($data);
            if ($res) {
                return json(['status'=>1,'msg'=>'添加成功']);
            }else{
                return json(['status'=>0,'msg'=>'添加失败']);
            }
        }
        return view();
    }

    //编辑菜单
    public function edit(){
        if (request()->isPost()) {
            $rows = input('post.');
            //判断添加的菜单是否已经存在
            if($this->isExistOpt($rows['controller'], $rows['action'],$rows['id'])){
                return json(['status'=>0,'msg'=>'该菜单已存在']);
            }
            //需更新的数据
            $data = [
                'menu_name' => $rows['controller'] ? ucfirst($rows['controller']).'/'.$rows['action'] : '', 
                'icon' => $rows['menuicon'],
                'title' => $rows['menuname'],
            ];
            //更新
            $res = Db::name('auth_rule')->where('id',$rows['id'])->update($data);
            if($res){
                return json(['status'=>1,'msg'=>'更新成功']);
            }else{
                return json(['status'=>0,'msg'=>'更新失败']);
            }
        }
        $id = input('param.id','','intval');
        $menu_info = Db::name('auth_rule')->find($id);
        return view('edit',[
            'menu_info'=>$menu_info,
            'opt'=>explode('/',$menu_info['menu_name'])
        ]);
    }

    //删除菜单
    public function del(){
        $id = input('param.id','','intval');
        //判断是否存在子菜单
        $one = Db::name('auth_rule')->where(['pid'=>$id,'status' => 1])->find();
        if ($one) {
            return json(['status'=>0,'msg'=>'存在子菜单未删除']);
        }
        $res = Db::name('auth_rule')->where(['id'=>$id])->delete();
        return json(['status'=>$res]);
    }

}