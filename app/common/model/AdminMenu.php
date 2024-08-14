<?php
namespace app\common\model;
use think\Model;
//菜单页面的只显示一二级的 三级之后的不显示 用字段is_menu来判断是否显示
class AdminMenu extends Model{
	protected $table = 'tp_auth_rule';

	//显示菜单
	public function selectAllMenu($type=1){
		$where = array(
			'status'=>1,//是否开启 1是 0否
			'is_menu'=>1,//是否主菜单 1是 0否 
		);
		//当type=2时 去掉是否为主菜单这个条件
		if ($type==2) {
			unset($where['is_menu']);
		}
		return $this->where($where)->order('id asc')->select();
	}

	//是否为二级菜单
	public function isSecondaryMenu($id){
		$where = array('id' => $id,);
		return $this->where($where)->value('pid') ? true : false;
	}

	//添加菜单
	public function addAdminMenu($data){
		$menu_info = array(
            'menu_name' => $data['controller'] ? ucfirst($data['controller']).'/'.$data['action'] : '',
            'icon'      => $data['menuicon'],
            'title'     => $data['menuname'],
            'pid'       => $data['pid'] ? $data['pid'] : 0,
            'is_menu'   => $data['is_menu'],
        );
        return $this->save($menu_info);
	}

	//编辑菜单
	public function editAdminMenu($data){
		$where = array('id' => $data['id'],);
		$menu_info = array(
            'menu_name' => $data['controller'] ? $data['controller'].'/'.$data['action'] : '',
            'icon'      => $data['menuicon'],
            'title'     => $data['menuname'],
        );
        unset($data['id']);
        return $this->save($menu_info,$where);
	}

	//根据id查询菜单信息
	public function selectMenuById($id){
		$where = array('id' => $id,);
		return $this->where($where)->find();
	}

	//查询是否已存在的opt
	public function isExistOpt($controller,$action,$id=null){
		$where = array(
            'menu_name' => $controller.'/'.$action,
            'status'    => 1,
        );
        if($id){
            $where['id'] = array('neq',$id);
        }
        return $this->where($where)->find();
	}

	//是否存在子菜单
	public function isExistSonMenu($id){
		$where = array(
            'pid' => $id,
            'status' => 1,
        );
        return $this->where($where)->find();
	}

	//根据规则id数组获取菜单
	public function getMenus($rules_arr = []){
        $res = $this->whereIn('id',$rules_arr)->where([
            'is_menu' => 1,
            'status' => 1,
        ])->select();
        return $res;
	}

}
