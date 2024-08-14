<?php
namespace app\mobile\controller;
use think\Controller;
use think\facade\Db;
use app\common\model\Category as CategoryModel;
class Index extends  Base{

	public function index(){
		$bannerData=Db::name('ad')->where('type_id',1)->select();
		$goodsData5=Db::name('goods')->where('ishot',1)->limit(8)->order('goods_id desc')->order('listorder asc')->where('goods_status',1)->select();
		$category_model=new CategoryModel();
        $indexCate=$category_model->getNavCateData();
        return view('',[
            'goodsData5'=>$goodsData5,
            'indexCate'=>$indexCate,
            'bannerData'=>$bannerData
        ]);
	}


























}