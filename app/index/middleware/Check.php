<?php
declare (strict_types=1);

namespace app\index\middleware;

use think\facade\Cookie;
use think\facade\Env;

class Check
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        $lang = Env::get('LANG.default_lang');
        Cookie::set('think_lang', $lang);
        //前置中间件
        if (empty(session('uid')) && !preg_match('/pay/', $request->pathinfo()) && !preg_match('/Pay/', $request->pathinfo())
            && !preg_match('/login/', $request->pathinfo()) && !preg_match('/Login/', $request->pathinfo())
            && !preg_match('/sem/', $request->pathinfo()) && !preg_match('/user/', $request->pathinfo())) {
            return redirect((string)url('login/index'));
        }
        return $next($request);
    }
}
