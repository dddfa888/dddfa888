<?php
namespace app\common\model;
use think\Model;

class AuthRule extends Model{

	public function sort($ruleRes,$parent_id=0,$level=0){
        static $arr=array();
        foreach ($ruleRes as $k => $v) {
            if($v['parent_id']==$parent_id){
                $v['level']=$level;
                $arr[]=$v;
                $this->sort($ruleRes,$v['id'],$level+1);
            }
        }
        return $arr;
    }
    
}
