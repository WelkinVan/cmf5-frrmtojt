<?php

namespace app\project\validate;

use think\Validate;

class ProjectPostValidate extends Validate
{
    //定义验证的规则
    protected $rule = [
        'post_title'  => 'require',
    ];
    //出现错误时提示的信息
    protected $message = [
        'post_title.require' => '项目名称不能为空',
    ];
    //定义验证场景，验证不同场景下的数据，目前本例中没有用到https://www.kancloud.cn/manual/thinkphp5/129322
    protected $scene = [

    ];

}