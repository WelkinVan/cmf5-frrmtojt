<?php
/**
 * [项目管理列表页控制器]
 * @Author:   WelkinVan<welkinvan@qq.com>
 * @DateTime: 7/14/2017 9:45 PM
 * @Description:项目管理控制器，用户展现所有项目
 */

namespace app\project\controller;

//继承CMF5的AdminBase控制器用于一些后台基础控制
use cmf\controller\AdminBaseController;
//引入该应用的方法库
use app\admin\model\ThemeModel;
use app\project\service\ProjectService;
use app\project\model\ProjectCategoryModel;
use app\project\model\ProjectPostModel;

use think\Db;

class AdminProjectController extends AdminBaseController
{
    /**
     * 所有项目列表
     */
    public function index()
    {
        //获取参数，主要用于检索数据过滤用
        $param = $this->request->param();
        $categoryId = $this->request->param('category', 0, 'intval');

        //实例化本应用函数库，主要用于复用的一些功能函数处理。
        $projectService = new ProjectService();
        $data = $projectService->adminProjectList($param);

        $data->appends($param);

        //这边就是复用了ProjectModel里面的生成树，参看上一章节
        $projectCategoryModel = new ProjectCategoryModel();
        $categoryTree = $projectCategoryModel->adminCategoryTree($categoryId);

        //各个值扔回模板
        $this->assign('start_time', isset($param['start_time']) ? $param['start_time'] : '');
        $this->assign('end_time', isset($param['end_time']) ? $param['end_time'] : '');
        $this->assign('keyword', isset($param['keyword']) ? $param['keyword'] : '');
        $this->assign('articles', $data->items());
        $this->assign('category_tree', $categoryTree);
        $this->assign('category', $categoryId);
        $this->assign('page', $data->render());

        return $this->fetch();
    }

    /**
     * 添加项目文章
     */
    public function add()
    {
        //实例化CMF的模板模型，用途是去检测后台的模板配置文件，采用这种方法后让你的APP支持CMF的模板变量
        $themeModel = new ThemeModel();
        $projectThemeFiles = $themeModel->getActionThemeFiles('project/Article/index');
        $this->assign('project_theme_files', $projectThemeFiles);
        return $this->fetch();
    }

    /**
     * 添加项目文章提交
     */
    public function addPost()
    {
        if ($this->request->isPost()) {
            //获取所有提交的参数
            $data = $this->request->param();

            $post = $data['post'];
            //将提交的数据进行合法性检测，采用的的是TP5的validate类验证规则https://www.kancloud.cn/manual/thinkphp5/129352
            $result = $this->validate($post, 'ProjectPost');
            if ($result !== true) {
                $this->error($result);
            }

            //实例化项目模型
            $projectPostModel = new ProjectPostModel();

            //创建终端设备字符串
            if (!empty($data['post']['post_device'])) {
                $data['post']['post_device'] = implode(',', $data['post']['post_device']);
            } else {
                $data['post']['post_device'] = '';
            }

            //是否有相册，有相册的话在more中创建对应photos数组
            if (!empty($data['photo_names']) && !empty($data['photo_urls'])) {
                $data['post']['more']['photos'] = [];
                foreach ($data['photo_urls'] as $key => $url) {
                    $photoUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['photos'], ["url" => $photoUrl, "name" => $data['photo_names'][$key]]);
                }
            }

            //是否有文件，有附件的话在more中创建对应files数组
            if (!empty($data['file_names']) && !empty($data['file_urls'])) {
                $data['post']['more']['files'] = [];
                foreach ($data['file_urls'] as $key => $url) {
                    $fileUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['files'], ["url" => $fileUrl, "name" => $data['file_names'][$key]]);
                }
            }

            //调用模型中adminProjectPost方法，分类写数据库
            $projectPostModel->adminProjectPost($data['post'], $data['post']['categories']);

            //添加成功后返回到这个添加的编辑模式
            $this->success('添加成功!', url('AdminProject/edit', ['id' => $projectPostModel->id]));
        }
    }

    /**
     * 编辑项目文章
     */
    public function edit()
    {
        //获取需要修改的ID号
        $id = $this->request->param('id', 0, 'intval');

        //实例化项目模型，将对应ID的文章找出来
        $projectPostModel = new ProjectPostModel();
        $post = $projectPostModel->where('id', $id)->find();

        //根据文章表（project_post）查询出来的ID，从关联表文章类别关联表（project_category_post）中获取所有文章所在的名称和ID,并根据查询的键名【array_keys()】生成新的ID字符串
        $postCategories = $post->categories()->alias('a')->column('a.name', 'a.id');
        $postCategoryIds = implode(',', array_keys($postCategories));

        //实例化模板文件，获取对应模板变量参数
        $themeModel = new ThemeModel();
        $articleThemeFiles = $themeModel->getActionThemeFiles('project/Article/index');

        //将各个值扔回前台模板
        $this->assign('article_theme_files', $articleThemeFiles);
        $this->assign('post', $post);
        $this->assign('post_categories', $postCategories);
        $this->assign('post_category_ids', $postCategoryIds);

        return $this->fetch();
    }

    /**
     * 编辑项目文章提交
     */
    public function editPost()
    {
        //think\Request类判断是否是post请求。获取所有提交上来的参数。
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $post = $data['post'];
            //提交数据通过PorjectPost验证规则验证，可以在验证器里面加上多条提交的验证规则
            $result = $this->validate($post, 'ProjectPost');
            if ($result !== true) {
                $this->error($result);
            }

            //时间戳处理，转换成时间戳
            $data['post']['create_time'] = strtotime($post['create_time']);

            //实例化项目文章表
            $projectPostModel = new ProjectPostModel();

            //创建终端设备字符串
            if (!empty($data['post']['post_device'])) {
                $data['post']['post_device'] = implode(',', $data['post']['post_device']);
            } else {
                $data['post']['post_device'] = '';
            }

            //判断是否有提交组图相册，MORE参数是扩展参数，各种自己想加的都能按照json格式存进来
            if (!empty($data['photo_names']) && !empty($data['photo_urls'])) {
                $data['post']['more']['photos'] = [];
                foreach ($data['photo_urls'] as $key => $url) {
                    $photoUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['photos'], ["url" => $photoUrl, "name" => $data['photo_names'][$key]]);
                }
            }

            //判断是否有附件，附件也是放在MORE参数中。还可以对应扩展各种字段，方法和这2个类似
            if (!empty($data['file_names']) && !empty($data['file_urls'])) {
                $data['post']['more']['files'] = [];
                foreach ($data['file_urls'] as $key => $url) {
                    $fileUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['files'], ["url" => $fileUrl, "name" => $data['file_names'][$key]]);
                }
            }
            //dump($data);
            //dump($post);

            //数据提交到模型中进行处理
            $projectPostModel->adminProjectEdit($data['post'], $data['post']['categories']);

            //提交成功提示
            $this->success('保存成功!');
        }
    }

    /**
     * 项目文章删除
     */
    public function delete()
    {
        //获取删除的对应参数
        $param = $this->request->param();

        //实例化模型
        $projectPostModel = new ProjectPostModel();

        //参数中是否存在ID，是的话单个删除
        if (isset($param['id'])) {
            $id = $this->request->param('id', 0, 'intval');

            //找到这个ID的文章，生成出回收表的字段数据（这个是沿用了CMF5原来的删除规则）
            $result = $projectPostModel->where(['id' => $id])->find();
            $data = [
                'object_id' => $result['id'],
                'create_time' => time(),
                'table_name' => 'project_post',
                'name' => $result['post_title']
            ];
            //删除，CMF5默认的删除是将对应的delete_time设置成当前时间，如果是0就是不删除。
            $resultProject = $projectPostModel
                ->where(['id' => $id])
                ->update(['delete_time' => time()]);

            //删除成功后在recycleBin表中记录对应的删除信息，记得use think\Db;
            if ($resultProject) {
                Db::name('recycleBin')->insert($data);
            }
            $this->success("删除成功！", '');
        }
        //参数中是否存在ids，是的话批量删除
        if (isset($param['ids'])) {
            //获取IDS，然后将所有的文章找出来
            $ids = $this->request->param('ids/a');
            $recycle = $projectPostModel->where(['id' => ['in', $ids]])->select();
            //将找出来的文章删除
            $result = $projectPostModel->where(['id' => ['in', $ids]])->update(['delete_time' => time()]);

            //删除成功的话，通过循环将每个ID的数据写入到recycleBin表中记录下
            if ($result) {
                foreach ($recycle as $value) {
                    $data = [
                        'object_id' => $value['id'],
                        'create_time' => time(),
                        'table_name' => 'project_post',
                        'name' => $value['post_title']
                    ];
                    Db::name('recycleBin')->insert($data);
                }
                $this->success("删除成功！", '');
            }
        }
    }
}