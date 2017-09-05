<?php
/**
 * [项目分类控制器]
 * @Author:   WelkinVan<welkinvan@qq.com>
 * @DateTime: 7/14/2017 11:11 PM
 * @Description:用于分类的增减删等操作控制
 */

namespace app\project\controller;

//继承CMF5的AdminBase控制器用于一些后台基础控制
use cmf\controller\AdminBaseController;

use app\admin\model\RouteModel;
use app\project\model\ProjectCategoryModel;
use think\Db;
use app\admin\model\ThemeModel;

class AdminCategoryController extends AdminBaseController
{
    /**
     * 项目分类列表
     */
    public function index()
    {
        //实例化项目分类模型
        $projectCategoryModel = new ProjectCategoryModel();
        //将分类数据生成树结构
        $categoryTree = $projectCategoryModel->adminCategoryTableTree();
        $this->assign('category_tree', $categoryTree);
        return $this->fetch();
    }

    /**
     * 添加项目分类
     */
    public function add()
    {
        //获取模板传递过来的parent参数
        $parentId = $this->request->param('parent', 0, 'intval');
        //实例化项目分类模型
        $projectCategoryModel = new ProjectCategoryModel();
        //将分类数据生成树结构，这边采用的是$parentId，主要用于添加的时候选择在当前栏目下添加子类的时候直接显示上级是子类。配合模型中的生成树方式。
        $categoriesTree = $projectCategoryModel->adminCategoryTree($parentId);
        //实例化CMF的模板模型，用途是去检测后台的模板配置文件，采用这种方法后让你的APP支持CMF的模板变量
        $themeModel = new ThemeModel();
        //这个里面的action，是在模板变量的json中的action参数，程序会去自动检测所有这个action下的模板文件。
        $listThemeFiles = $themeModel->getActionThemeFiles('project/List/index');
        $articleThemeFiles = $themeModel->getActionThemeFiles('project/Article/index');
        //将模板变量的数据赋值到对应模板中
        $this->assign('list_theme_files', $listThemeFiles);
        $this->assign('article_theme_files', $articleThemeFiles);
        //将当前栏目下的子栏目生成树给模板
        $this->assign('categories_tree', $categoriesTree);
        return $this->fetch();
    }

    /**
     * 添加项目分类提交
     */
    public function addPost()
    {
        //实例化项目分类模型
        $projectCategoryModel = new ProjectCategoryModel();
        //获取所有post过来的数据参数
        $data = $this->request->param();
        //将提交的数据进行合法性检测，采用的的是TP5的validate类验证规则https://www.kancloud.cn/manual/thinkphp5/129352
        $result = $this->validate($data, 'ProjectCategory');

        //验证没通过就将输出错误信息
        if ($result !== true) {
            $this->error($result);
        }

        //验证通过了，直接采用projectCategoryModel里的addCategory方法进行数据提交，在这里，我们也可以不使用，模型提交，完全可以在控制器中实现数据的提交。
        $result = $projectCategoryModel->addCategory($data);
        //提交是否正确判断并提示
        if ($result === false) {
            $this->error('添加失败!');
        }
        $this->success('添加成功!', url('AdminCategory/index'));
    }

    /**
     * 修改项目分类
     */
    public function edit()
    {
        //获取当前编辑的分类的ID号
        $id = $this->request->param('id', 0, 'intval');
        //id>0就是简易判断下ID合法不合法，我们建表时候这个ID是>0的自增值，所以默认肯定>0
        if ($id > 0) {
            //将当前ID的数据获取出来
            $category = ProjectCategoryModel::get($id)->toArray();
            //实例化分类模型
            $projectCategoryModel = new ProjectCategoryModel();
            //将分类名字默认到当前分类上后生成选择框的树
            $categoriesTree = $projectCategoryModel->adminCategoryTree($category['parent_id'], $id);
            //实例化CMF的模板模型，用途是去检测后台的模板配置文件，采用这种方法后让你的APP支持CMF的模板变量
            $themeModel = new ThemeModel();
            //这个里面的action，是在模板变量的json中的action参数，程序会去自动检测所有这个action下的模板文件。
            $listThemeFiles = $themeModel->getActionThemeFiles('project/List/index');
            $articleThemeFiles = $themeModel->getActionThemeFiles('project/Article/index');
            //实例化路由模型，用户URL美化
            $routeModel = new RouteModel();
            //查询出路由表里面的美化URL设置数据
            $alias = $routeModel->getUrl('project/List/index', ['id' => $id]);
            //$category数组中增加一个美化URL的值
            $category['alias'] = $alias;
            //下面就是将数据扔进模板
            $this->assign($category);
            $this->assign('list_theme_files', $listThemeFiles);
            $this->assign('article_theme_files', $articleThemeFiles);
            $this->assign('categories_tree', $categoriesTree);
            return $this->fetch();
        } else {
            $this->error('操作错误!');
        }
    }

    /**
     * 修改项目分类提交
     */
    public function editPost()
    {
        //获取所有POST的参数
        $data = $this->request->param();
        //将提交的数据进行规则验证，通新增提交
        $result = $this->validate($data, 'ProjectCategory');

        //验证没通过就将输出错误信息
        if ($result !== true) {
            $this->error($result);
        }

        //实例化ProjectCategory模型
        $projectCategoryModel = new ProjectCategoryModel();
        //验证通过了，直接采用projectCategoryModel里的editCategory方法进行数据提交。
        $result = $projectCategoryModel->editCategory($data);
        //提交是否正确判断并提示
        if ($result === false) {
            $this->error('保存失败!');
        }

        $this->success('保存成功!');
    }

    /**
     * 删除项目分类
     */
    public function delete()
    {
        //实例化模型
        $projectCategoryModel = new ProjectCategoryModel();
        $id = $this->request->param('id');

        //根据ID查询要删除的内容
        $findCategory = $projectCategoryModel->where('id', $id)->find();

        //找不到时候的报错
        if (empty($findCategory)) {
            $this->error('分类不存在!');
        }

        //统计下一共有多少条栏目，目的是怕误删把下面的子栏目没有父节点导致出错，如果有子栏目禁止删除。
        $categoryChildrenCount = $projectCategoryModel->where('parent_id', $id)->count();
        if ($categoryChildrenCount > 0) {
            $this->error('此分类有子类无法删除!');
        }

        //检索下对应栏目下是否有对应的文章，有内容也一样禁止删除，主要也是防止误操作。
        $categoryPostCount = Db::name('project_category_post')->where('category_id', $id)->count();

        if ($categoryPostCount > 0) {
            $this->error('此分类有文章无法删除!');
        }

        //构建删除数据，就是将删除的内容写入到回收站表recycleBin里面
        $data = [
            'object_id' => $findCategory['id'],
            'create_time' => time(),
            'table_name' => 'project_category',
            'name' => $findCategory['name']
        ];

        //记录删除时间，标记为删除，这边删除时间有记录，默认就是删除了，并不是真正的彻底删除
        $result = $projectCategoryModel
            ->where('id', $id)
            ->update(['delete_time' => time()]);

        //删除成功后的操作，记录到回收站表recycleBin里面
        if ($result) {
            Db::name('recycleBin')->insert($data);
            $this->success('删除成功!');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 分类选择对话框
     */
    public function select()
    {
        //获取参数ID，并转成数组
        $ids = $this->request->param('ids');
        $selectedIds = explode(',', $ids);

        //实例化项目分类模型
        $projectCategoryModel = new ProjectCategoryModel();

        //生成对应的选择列表
        //长字符串定义  百度“php 长字符串 Heredoc”
        //如：http://www.php100.com/html/webkaifa/PHP/PHPyingyong/2010/1229/7164.html
        $tpl = <<<tpl
<tr class='data-item-tr'>
    <td>
        <input type='checkbox' class='js-check' data-yid='js-check-y' data-xid='js-check-x' name='ids[]'
               value='\$id' data-name='\$name' \$checked>
    </td>
    <td>\$id</td>
    <td>\$spacer \$name</td>
</tr>
tpl;
        //调用模型中生成分类的table格式的树形结构的方法
        $categoryTree = $projectCategoryModel->adminCategoryTableTree($selectedIds, $tpl);
        $where = ['delete_time' => 0];
        $categories = $projectCategoryModel->where($where)->select();

        //变量扔模板去
        $this->assign('categories', $categories);
        $this->assign('selectedIds', $selectedIds);
        $this->assign('categories_tree', $categoryTree);
        return $this->fetch();
    }
}