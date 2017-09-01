<?php

namespace app\project\validate;

use app\admin\model\RouteModel;
use think\Validate;

class ProjectCategoryValidate extends Validate
{
    //定义验证的规则
    protected $rule = [
        'name'  => 'require',
        //执行自定义的别名规则
        'alias' => 'checkAlias',
    ];
    //出现错误时提示的信息
    protected $message = [
        'name.require' => '分类名称不能为空',
    ];
    //定义验证场景，验证不同场景下的数据，目前本例中没有用到https://www.kancloud.cn/manual/thinkphp5/129322
    protected $scene = [

    ];

    // 自定义验证规则，验证添加路由别名是否可用
    protected function checkAlias($value, $rule, $data)
    {
        if (empty($value)) {
            return true;
        }
        //实例化路由模型\app\admin\model\RouteModel.php，需要先use
        $routeModel = new RouteModel();
        //检测是否获取到了当前提交数据的ID，进行URL美化处理验证，这边是进行修改的时候才验证，目前添加暂未实现。
        if (isset($data['id']) && $data['id'] > 0){
            //生成统一格式的完整的URL
            $fullUrl    = $routeModel->buildFullUrl('project/List/index', ['id' => $data['id']]);
        }else{
            //去数据库检测然后将存在该别名的标准格式完整URL取出
            $fullUrl    = $routeModel->getFullUrlByUrl($data['alias']);
        }
        //判断是否存在别名
        if (!$routeModel->exists($value, $fullUrl)) {
            return true;
        } else {
            return "别名已经存在!";
        }

    }
}