<?php
  
  namespace app\common\model;
  
  use think\Model;
  use app\common\model\AdminMenu;
  
  class AdminAuthGroupAccess extends Model
  {
    protected $table = 'tp_auth_group_access';
    
    //获取用户所有权限
    public function getUserRules($user_id)
    {
      $where = array('a.uid' => $user_id);
      $rules = $this->alias('a')->where($where)->join('auth_group b', 'b.id=a.group_id', 'LEFT')->field('b.rules')->select();
      if (!$rules) {
        return array();
      }
      
     
      $rules_str = '';
      foreach ($rules as $v) {
        $rules_str .= $v['rules'] . ',';
      }
      
      $rules_str = rtrim($rules_str, ',');
      $rules_arr = array_unique(explode(',', $rules_str));//移除数组中的重复的值，并返回结果数组。
      $admin_menu_model = new AdminMenu();
      $menus = $admin_menu_model->getMenus($rules_arr)->toArray();
     
      $menus = getmenus($menus, 2);
   
      return $menus;
    }
    
  }