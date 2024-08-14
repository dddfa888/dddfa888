<?php
namespace app\common\model;
use think\Model;

class Category extends Model{



	public static function getcateall($data,$pid=0,$level=-1){
		static $arr = array(); //保存需要返回到前台展示的数据
		$level+=1;	//等同于$level = $level + 1
		if($level==0){
			$str = "";
		}else{
			$str = "|";
		}
		foreach ($data as $v) {
			if($pid==$v['pid']){	
				$tmp_arr = array();
				$tmp_arr['id'] = $v['id'];
				$tmp_arr['name'] = $str.str_repeat("------", $level).$v['name'];
				$tmp_arr['pid'] = $v['pid'];
				$tmp_arr['sort'] = $v['sort'];
				$tmp_arr['isshow'] = $v['isshow'];
				$arr[] = $tmp_arr;
				self::getcateall($data,$v['id'],$level);//再次调用自己的时候pid = 1
				unset($v);//删除没有用的数组
			}
		}
		return $arr;
	}

	//栏目排序
	public static function sort($data){
		// 键值对对应更新 self代表Category数据表
		foreach ($data as $id => $sort) {
			self::where('id',$id)->update(['sort'=>$sort]);
		}
		return true;
	}

	//获取子栏目id
	public static function getchildids($id){
		$res = self::where('pid',$id)->field('id')->select();
		static $arr = array();
		foreach ($res as $v) {
			$arr[] = $v['id'];
			self::getchildids($v['id']);
		}
		return $arr;
	}

















}