<?php
namespace app\common\model;
use think\Model;

class Category extends Model{



	protected static function initialize(){
		//新增操作成功执行
		self::afterInsert(function($data){
			//会出问题 暂时不用 
		});

		//更新操作成功执行
		// self::afterUpdate(function($data){
		// 	if($data['pic']!=''){
  //               $arr_tmp = explode(',',$data['pic']);
  //               $aid = input('id');
  //               $thum=[];
  //               foreach($arr_tmp as $v){
  //                   $thum[]=['aid'=>$aid,'pic'=>$v];
  //               }
  //               db('pics')->insertAll($thum);
  //           }
		// });

		//删除之后
		self::afterDelete(function($data){
			$aid = $data['id'];
			$res = db('pics')->where('aid',$aid)->select();
			//如果有对应的图片，对图片进行删除
			if($res){
				foreach($res as $v){
					$file = ROOT_PATH."\\public\\".$v['pic'];
					if(file_exists($file)){
						@unlink($file);
					}
				}
				//删除该文章的图片数据
				db('pics')->where('aid',$aid)->delete();
			}
		});
	}































	
}