<?php
namespace app\dingadmin\controller;
use think\facade\Db;
use think\facade\View;
use PHPExcel;
use PHPExcel_Writer_Excel2007;

//支出管理
class Expenditure extends Base{

	public function index(){
		$zhModel   = Db::name('expenditure');
		$proname   = input("param.proname");
        $type      = input("param.type");
        $time      = input("param.time");
        $where = '';
        if ($proname != '') {
            $zhModel->wherelike('proname','%'.$proname.'%');
            View::assign('proname',$proname);
        }else{
            View::assign('proname','');
        }

        if ($type != '') {
            $zhModel->wherelike('type',$type);
            View::assign('type',$type);
        }else{
            View::assign('type','');
        }

        if ($time) {
            $arr = explode('~', $time);
            $begin = strtotime($arr[0]);
            $end = strtotime($arr[1]);
            $zhModel->where('addtime','between',[$begin,$end]);
            View::assign('time',$time);
        }else{
        	View::assign('time','');
        }
        
		$list = $zhModel->order('addtime', 'desc')->paginate(30);
		$page = $list->render();

		//总支出
        $total_expenditure = $zhModel->sum('money');
        //月支出
        $month_expenditure = $zhModel->whereTime('addtime', 'month')->sum('money');
        //天支出
        $day_expenditure = $zhModel->whereTime('addtime', 'today')->sum('money');

		return view('index',[
			'list'=>$list,
			'page'=>$page,
			'total_expenditure' => $total_expenditure,
            'month_expenditure' => $month_expenditure,
            'day_expenditure' => $day_expenditure,
        ]);

	}


	public function add(){
		if (request()->isPost()) {
            $params['type'] = input('post.type');
            $params['endtime'] = strtotime(input('post.endtime'));
            $params['proname'] = input('post.proname');
            $params['money'] = input('post.money');
            $params['addtime'] = strtotime(input('post.addtime'));
            $params['describe'] = input('post.describe');
            $params['uid'] = session('admin_auth.id');
            $res = Db::name('expenditure')->insert($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'添加成功']);
            }else{
            	return json(['status'=>0,'msg'=>'添加失败']);
            }
        }
        $time = time();//当前时间获取
		return view('add',[
            'time' => $time,
        ]);
	}


	public function edit(){
		if (request()->isPost()) {
			$id = input('post.id');
            $params['type'] = input('post.type');
            $params['endtime'] = strtotime(input('post.endtime'));
            $params['proname'] = input('post.proname');
            $params['money'] = input('post.money');
            $params['addtime'] = strtotime(input('post.addtime'));
            $params['describe'] = input('post.describe');
            $params['uid'] = session('admin_auth.id');
            $res = Db::name('expenditure')->where('id',$id)->update($params);
            if ($res) {
            	return json(['status'=>1,'msg'=>'编辑成功']);
            }else{
            	return json(['status'=>0,'msg'=>'编辑失败']);
            }
        }
        $id = input('id');
		$one = Db::name('expenditure')->find($id);
		return view('edit',[
        	'one'=>$one
        ]);
	}

	public function del(){
		$id = input('id');
        $res = Db::name('expenditure')->where('id',$id)->delete();
        if ($res) {
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
	}

    //数据导出页面
    public function export(){
        return view('export',[

        ]);
    }

    //导出操作
    public function export_operation(){
        $comModel = Db::name('expenditure');
        $proname   = input("param.proname");
        $type      = input("param.type");
        $time      = input("param.time");
        $timearr = '';
        if ($proname != '') {
            $comModel->wherelike('proname','%'.$proname.'%');
            View::assign('proname',$proname);
        }
        if ($type != '') {
            $comModel->where('type',$type);
            View::assign('type',$type);
        }else{
            View::assign('type','');
        }
        if ($time) {
            $timearr = explode('~', $time);
            $begin = strtotime($timearr[0]);
            $end = strtotime($timearr[1]);
            $comModel->where('addtime','between',[$begin,$end]);
            View::assign('time',$time);
        }

        $phpexcel = new PHPExcel();
        $phpexcel->setActiveSheetIndex(0);
        $sheet = $phpexcel->getActiveSheet();
        // 获取要导出的内容
        $res = $comModel->field('proname,money,describe,addtime,type')->select()->toArray();
        // dump($res);die;
        //设置表头
        $arr = [
            'proname'=>"项目说明",
            'money'=>"支出金额",
            'describe'=>"备注",
            'addtime'=>"支出时间",
            'type'=>"支出类型",
        ];
        array_unshift($res, $arr);//将传入的数据插入到数组$res的开头
        $currow = 1;
        foreach ($res as $key => $v) {
            $currow = $key+1;
            $sheet->setCellValue('A'.$currow,$v['proname'])
                  ->setCellValue('B'.$currow,$v['money'])
                  ->setCellValue('C'.$currow,is_time($v['addtime']))
                  ->setCellValue('D'.$currow,get_type($v['type']))
                  ->setCellValue('E'.$currow,$v['describe']);
        }
        //设置单元格 可有可无
        $phpexcel->getActiveSheet()->getStyle('A1:E'.$currow)->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
        //导出到浏览器
        header('Content-Type:application/vnd.ms-excel');//设置文档类型
        header('Content-Disposition:attachment;filename="公司支出表.xlsx"');
        header('Cache-Control:max-age=0');
        $phpwriter = new PHPExcel_Writer_Excel2007($phpexcel);
        $phpwriter->save('php://output');
        return; 
    }






































}