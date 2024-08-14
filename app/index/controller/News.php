<?php
namespace app\index\controller;
use app\index\middleware\Check;
use think\facade\Config;
use think\facade\Db;
use think\facade\View;
use think\facade\Lang;


class News extends Base{


    public function index(){
        $conf = Db::name('proconfig')->where('id',1)->find();
        $gonggao = $conf['gonggao'];
        $download = $conf['android_download'];
        $banner = Db::name('banner')->where('isshow',1)->order('addtime desc')->select();
        $mai_log = Db::name('order')->field('user,addtime')->order('id desc')->select();
        $robot = Db::name('product')->order('sort asc')->where(['is_on'=>1,'type_id'=>1])->select()->toArray();
        foreach ($robot as $k => $v) {
            $robot[$k]['Month_income'] = $v['day_shouyi']*30;
        }
   
//        dd(Config::get('app.currency'));
        $username = session('username');
        if ($username) {
            $status = 0;
        }else{
            $status = 1;
        }
        return View('index',[
            'gonggao'=>$gonggao,
            'download'=>$download,
            'robot'=>$robot,
            'status'=>$status,
            'mai_log'=>$mai_log,
            'banner'=>$banner,
        ]);
    }

    //平台公告
    public function help(){
        $list = Db::name('article')->alias('a')->field("a.id,a.title,b.pic")->join('pics b','b.aid=a.id','LEFT')->where('a.cid',38)->order('a.addtime desc')->select();
        return View('help',[
            'list'=>$list,
        ]);
    }

    public function xiangqing(){
        $id = input('id');
        $new = Db::name('article')->where('id',$id)->find();
        return View('xiangqing',[
            'new'=>$new,
        ]);
    }

    //关于我们
    public function xiangmu(){
        $new = Db::name('article')->where('id',98)->find();
        return View('xiangmu',[
            'new'=>$new,
        ]);
    }
    













}

