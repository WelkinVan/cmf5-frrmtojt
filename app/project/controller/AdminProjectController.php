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
use app\project\service\ProjectService;
use app\project\model\ProjectCategoryModel;

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
            $projectPostModel = new PorjectPostModel();

            //是否有相册，有相册的话在more中撞见对应photos数组
            if (!empty($data['photo_names']) && !empty($data['photo_urls'])) {
                $data['post']['more']['photos'] = [];
                foreach ($data['photo_urls'] as $key => $url) {
                    $photoUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['photos'], ["url" => $photoUrl, "name" => $data['photo_names'][$key]]);
                }
            }

            //是否有文件，有附件的话在more中撞见对应files数组
            if (!empty($data['file_names']) && !empty($data['file_urls'])) {
                $data['post']['more']['files'] = [];
                foreach ($data['file_urls'] as $key => $url) {
                    $fileUrl = cmf_asset_relative_url($url);
                    array_push($data['post']['more']['files'], ["url" => $fileUrl, "name" => $data['file_names'][$key]]);
                }
            }

            //调用模型中adminAddProject方法，写数据库
            $projectPostModel->adminAddProjcet($data['post'], $data['post']['categories']);

            //添加成功后返回到这个添加的编辑模式
            $this->success('添加成功!', url('AdminProject/edit', ['id' => $projectPostModel->id]));
        }
    }

    /**
     * 编辑文章
     * @adminMenu(
     *     'name'   => '编辑文章',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑文章',
     *     'param'  => ''
     * )
     */
    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');

        $portalPostModel = new PortalPostModel();
        $post = $portalPostModel->where('id', $id)->find();
        $postCategories = $post->categories()->alias('a')->column('a.name', 'a.id');
        $postCategoryIds = implode(',', array_keys($postCategories));

        $themeModel = new ThemeModel();
        $articleThemeFiles = $themeModel->getActionThemeFiles('portal/Article/index');
        $this->assign('article_theme_files', $articleThemeFiles);
        $this->assign('post', $post);
        $this->assign('post_categories', $postCategories);
        $this->assign('post_category_ids', $postCategoryIds);

        return $this->fetch();
    }

    /**
     * 编辑文章提交
     * @adminMenu(
     *     'name'   => '编辑文章提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑文章提交',
     *     'param'  => ''
     * )
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

            //实例化项目文章表
            $projectPostModel = new ProjectPostModel();

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

            //数据提交到模型中进行处理
            $projectPostModel->adminProjectPost($data['post'], $data['post']['categories']);

            //提交成功提示
            $this->success('保存成功!');

        }
    }

    /**
     * 文章删除
     * @adminMenu(
     *     'name'   => '文章删除',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章删除',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $param = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['id'])) {
            $id = $this->request->param('id', 0, 'intval');
            $result = $portalPostModel->where(['id' => $id])->find();
            $data = [
                'object_id' => $result['id'],
                'create_time' => time(),
                'table_name' => 'portal_post',
                'name' => $result['post_title']
            ];
            $resultPortal = $portalPostModel
                ->where(['id' => $id])
                ->update(['delete_time' => time()]);
            if ($resultPortal) {
                Db::name('recycleBin')->insert($data);
            }
            $this->success("删除成功！", '');

        }

        if (isset($param['ids'])) {
            $ids = $this->request->param('ids/a');
            $recycle = $portalPostModel->where(['id' => ['in', $ids]])->select();
            $result = $portalPostModel->where(['id' => ['in', $ids]])->update(['delete_time' => time()]);
            if ($result) {
                foreach ($recycle as $value) {
                    $data = [
                        'object_id' => $value['id'],
                        'create_time' => time(),
                        'table_name' => 'portal_post',
                        'name' => $value['post_title']
                    ];
                    Db::name('recycleBin')->insert($data);
                }
                $this->success("删除成功！", '');
            }
        }
    }

    /**
     * 文章发布
     * @adminMenu(
     *     'name'   => '文章发布',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章发布',
     *     'param'  => ''
     * )
     */
    public function publish()
    {
        $param = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['ids']) && isset($param["yes"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['post_status' => 1, 'published_time' => time()]);

            $this->success("发布成功！", '');
        }

        if (isset($param['ids']) && isset($param["no"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['post_status' => 0]);

            $this->success("取消发布成功！", '');
        }

    }

    /**
     * 文章置顶
     * @adminMenu(
     *     'name'   => '文章置顶',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章置顶',
     *     'param'  => ''
     * )
     */
    public function top()
    {
        $param = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['ids']) && isset($param["yes"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['is_top' => 1]);

            $this->success("置顶成功！", '');

        }

        if (isset($_POST['ids']) && isset($param["no"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['is_top' => 0]);

            $this->success("取消置顶成功！", '');
        }
    }

    /**
     * 文章推荐
     * @adminMenu(
     *     'name'   => '文章推荐',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章推荐',
     *     'param'  => ''
     * )
     */
    public function recommend()
    {
        $param = $this->request->param();
        $portalPostModel = new PortalPostModel();

        if (isset($param['ids']) && isset($param["yes"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['recommended' => 1]);

            $this->success("推荐成功！", '');

        }
        if (isset($param['ids']) && isset($param["no"])) {
            $ids = $this->request->param('ids/a');

            $portalPostModel->where(['id' => ['in', $ids]])->update(['recommended' => 0]);

            $this->success("取消推荐成功！", '');

        }
    }

    /**
     * 文章排序
     * @adminMenu(
     *     'name'   => '文章排序',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章排序',
     *     'param'  => ''
     * )
     */
    public function listOrder()
    {
        parent::listOrders(Db::name('portal_category_post'));
        $this->success("排序更新成功！", '');
    }

    public function move()
    {

    }

    public function copy()
    {

    }


}