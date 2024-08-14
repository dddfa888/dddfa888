<?php
namespace app\dingadmin\controller;
use app\BaseController;
use think\facade\Db;
use think\facade\View;
use think\facade\Request;
use Auth\Auth;
use app\common\model\AdminAuthGroupAccess;

class Base extends BaseController{


	public function initialize(){
	  
		$user_info = session('admin_auth');
        if (empty($user_info)) {
            $this->redirect('/dingadmin/login/index');
        }


		View::assign('user_info',$user_info);//获取用户登录信息
    
        $controller = Request::controller();//当前控制器
        $action = Request::action();//当前方法
        //权限检查
        $name = $controller . '/' . $action;

        if($controller != 'Login'  || $controller != 'manager'){
            $auth = new Auth(); // 实例化类 参数表示IP地址库文件
            if($name != 'Index/main'){
                // dump($name);die;
                $auth_result = $auth->check($name, $user_info['id']);

                if($auth_result === false){
                    
                    echo '没有权限';die;
                }else{
                    //左侧菜单栏
                    $admin_auth_group_access_model = new AdminAuthGroupAccess();
                    $navmenus = $admin_auth_group_access_model->getUserRules($user_info['id']);
      
                    View::assign('navmenus',$navmenus);//获取菜单
                }
            }
        }



        //代理商和业务员只显示他发展的用户

	}

    //图片上传
    public function uploadimg(){
        //获得上传文件
        $file = request()->file('imgfile');
        //判断$file是否为文件对象
        if($file){
            $savename = \think\facade\Filesystem::putFile('upload', $file);
            $savename=str_replace('\\','/',$savename);
            //dump($savename);die;  upload/20201210/f86c0a418e19e02dd973a84ae3de3928.png
            //获取上传到服务器上的文件路径
            $imgpath = "/public/".$savename;
            return json(['code'=>1,'msg'=>'上传成功','img'=>$imgpath]);
        }else{
            return json(['code'=>0,'msg'=>$file->getError()]);
        }
    }

    //图片删除
    public function delimg($url=""){
        if($url!==""){
            $file = $url;//获取图片路径
            //判断文件是否存在
            if(file_exists($file)){
                $res = unlink($file);//删除文件
                if($res){
                    //删除本地图片成功 后要删除数据表中的数据
                    $this->delpic($url);
                    return json(['code'=>1,'msg'=>'删除成功']);
                }
                return json(['code'=>0,'msg'=>'删除失败']);
            }
            $this->delpic($url);
            return json(['code'=>2,'msg'=>'文件不存在']);
        }
    }

    //删除数据表中图片记录
    protected function delpic($url){
        $pic = Db::name('pics')->where('pic',$url)->field('id')->find();
        if($pic){
            Db::name('pics')->delete($pic['id']);
        }
    }


  /**
   * 登录账号下面发展了哪些用户
   */
    public  function getAgetnIds(){
      $user_info = session('admin_auth');
      //6代理商  7业务员
      $admin = Db::name('admin')->where('id',$user_info['id'])->find();
      $arr = [];
      if($user_info['groupid'] == 6 ){
        $p_admin = Db::name('admin')->where('pid',$admin['id'])->column('code');
        $arr = $p_admin;
        array_push($arr,123);

      }

      if($user_info['groupid'] == 7 ){
        $arr[] = $admin['code'];
      }
      return $arr;
    }
















    
}

