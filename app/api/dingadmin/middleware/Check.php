<?php
declare (strict_types = 1);

namespace app\dingadmin\middleware;

class Check
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next){
    
        //前置中间件
        if(empty(session('admin_auth')) && !preg_match('/login/',$request->pathinfo())){
            return redirect((string) url('login/index'));
        }
        return $next($request);
    }
}
