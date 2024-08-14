<?php

namespace app\index\controller;


use app\BaseController;

class User extends BaseController
{
    public function lang()
    {
        return View('lang', []);
    }

    public function lang_set()
    {
        $lang = input('lang');
        cookie('think_var', $lang);
        return redirect('/index/Login/index');
    }
}