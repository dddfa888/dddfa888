<?php
// 应用公共文件
/**
 * $msg 待提示的消息
 * $url 待跳转的链接
 * $icon 这里主要有两个，5和6，代表两种表情（哭和笑）
 * $time 弹出维持时间（单位秒）
 */
function alert($msg='',$url='',$icon='',$time=3){ 
    $str='<meta name="viewport" content="initial-scale=1, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black" />
    <meta http-equiv="Access-Control-Allow-Origin" content="*" />
    <meta http-equiv="pragma" content="no-cache" />
    <script type="text/javascript" src="/public/static/admin/vendor/js/jquery.js"></script><script type="text/javascript" src="/public/static/admin/vendor/layui/layui.js"></script>';//加载jquery和layer
    $str.='<script>layui.use(["form","layer"], function(){var form = layui.form,layer = layui.layer;layer.msg("'.$msg.'",{icon:'.$icon.',time:'.($time*1000).'});setTimeout(function(){self.location.href="'.$url.'"},2000)});</script>';//主要方法
    return $str;
}

//删除目录及文件，传入目录
function delFileByDir($dir) {
    $dh = opendir($dir);
    
    while ($file = readdir($dh)) {
    	
       if ($file != "." && $file != "..") {
       	
          $fullpath = $dir . "/" . $file;
          // dump($fullpath);die;
          if (is_dir($fullpath)) {
             delFileByDir($fullpath);
          } else {
             unlink($fullpath);
          }
       }
    }
    closedir($dh);
}

//二维数组根据某个元素去重复
function second_array_unique_bykey($arr, $key){
  $tmp_arr = array();  
  foreach($arr as $k => $v){
    if(in_array($v[$key], $tmp_arr)){ //搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true  
      unset($arr[$k]); //销毁一个变量  如果$tmp_arr中已存在相同的值就删除该值  
    }else{
      $tmp_arr[$k] = $v[$key];  //将不同的值放在该数组中保存  
    }
  }
  return $arr; 
}

//删除图片,传递过来图片路径
function delImg($path){
  if (empty($path)) {
    return false;
  }
  $path=app()->getRootPath().$path;
  if(file_exists($path)){
      unlink($path);
      return true;
  }else{
      return false;
  }
}
//递归菜单 $type 1:顺序菜单 2树状菜单
function getmenus($array,$type=1,$fid=0,$level=0){
    
    $column = [];
    if ($type == 2) {
      
        foreach ($array as $key => $vo) {
            if ($vo['pid'] == $fid) {
                $vo['level'] = $level;
                $column[$key] = $vo;
                $column [$key][$vo['id']] = getmenus($array,$type=2,$vo['id'],$level+1);
            }
        }
        
    }else{
        foreach ($array as $key => $vo) {
            if ($vo['pid'] == $fid) {
                $vo['level'] = $level;
                $column[] = $vo;
                $column = array_merge($column, getmenus($array,$type=1,$vo['id'],$level+1));//array_merge把两个或多个数组合并成一个数组
            }
        }
    }

    return $column;
}

//获取支付类型
function get_type($type=1){
    switch ($type) {
        case 1:
            $info = "一次性";
            break;
        case 2:
            $info = "时效性";
            break;
        default:
            $info = "支出类型";
            break;
    }
    return $info;
}

//判断是否是时间戳
function is_time($str){
    if (is_numeric($str)) {
        return date('Y-m-d',$str);
    }else{
        return $str;
    }
}

function two_number($number){
    return number_format($number,2);
}

function four_number($number){
    return number_format($number,4);
}  

function set_number($number,$num){
    return number_format($number,$num);
} 

//加密 解密
function encrypt($string,$operation,$key=''){ 
    $key=md5($key); 
    $key_length=strlen($key); 
      $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string; 
    $string_length=strlen($string); 
    $rndkey=$box=array(); 
    $result=''; 
    for($i=0;$i<=255;$i++){ 
           $rndkey[$i]=ord($key[$i%$key_length]); 
        $box[$i]=$i; 
    } 
    for($j=$i=0;$i<256;$i++){ 
        $j=($j+$box[$i]+$rndkey[$i])%256; 
        $tmp=$box[$i]; 
        $box[$i]=$box[$j]; 
        $box[$j]=$tmp; 
    } 
    for($a=$j=$i=0;$i<$string_length;$i++){ 
        $a=($a+1)%256; 
        $j=($j+$box[$a])%256; 
        $tmp=$box[$a]; 
        $box[$a]=$box[$j]; 
        $box[$j]=$tmp; 
        $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256])); 
    } 
    if($operation=='D'){ 
        if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8)){ 
            return substr($result,8); 
        }else{ 
            return''; 
        } 
    }else{ 
        return str_replace('=','',base64_encode($result)); 
    } 
} 

//生成原始的二维码
function scerweima($url=''){
  // vendor('phpqrcode.phpqrcode');
  require_once app()->getRootPath().'/vendor/phpqrcode/phpqrcode.php';
  $value = $url;  //二维码内容
  $errorLevel = 'L';//容错级别
  $size = 5;//生成图片大小
  $filename = 'public/qrcode/'.time().'.png';//图片路径
  QRcode::png($value,$filename,$errorLevel,$size,2);
  $QR = $filename;
  $QR = imagecreatefromstring(file_get_contents($QR));
  return $filename;//直接输出图片路径
}






















