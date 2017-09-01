<?php

namespace app\project\model;

use app\admin\model\RouteModel;
use think\Model;
use tree\Tree;

class ProjectCategoryModel extends Model
{
    protected $type = [
        'more' => 'array',
    ];

    /**
     * 生成分类 select树形结构
     * @param int $selectId 需要选中的分类 id
     * @param int $currentCid 需要隐藏的分类 id
     * @return string
     */
    public function adminCategoryTree($selectId = 0, $currentCid = 0)
    {
        //生成查询条件，删除时间默认为0，即未删除状态
        $where = ['delete_time' => 0];
        //判断是否有当前分类ID传参，如果有，检索的时候忽略当前ID的分类
        if (!empty($currentCid)) {
            $where['id'] = ['neq', $currentCid];
        }
        //将数据查询并存入数组中
        $categories = $this->order("list_order ASC")->where($where)->select()->toArray();
        //CMF5的Tree库，位于simplewind\extend\tree\Tree.php
        $tree = new Tree();
        $tree->icon = ['&nbsp;&nbsp;│', '&nbsp;&nbsp;├─', '&nbsp;&nbsp;└─'];
        $tree->nbsp = '&nbsp;&nbsp;';
        //创建一个新的数组，将查询出来的数据利用三元运算符,标识出当前栏目
        $newCategories = [];
        foreach ($categories as $item) {
            $item['selected'] = $selectId == $item['id'] ? "selected" : "";
            array_push($newCategories, $item);
        }
        //将新的数组进行初始化
        $tree->init($newCategories);
        //定义数的展现结构，这边采用的是option选择框，当前栏目选中
        $str = '<option value=\"{$id}\" {$selected}>{$spacer}{$name}</option>';
        $treeStr = $tree->getTree(0, $str);

        return $treeStr;
    }

    /**
     * 生成分类的table格式的树形结构
     * @param int|array $currentIds
     * @param string $tpl
     * @return string
     */
    public function adminCategoryTableTree($currentIds = 0, $tpl = '')
    {
        //生成查询条件，删除时间默认为0，即未删除状态
        $where = ['delete_time' => 0];
        //将数据查询并存入数组中
        $categories = $this->order("list_order ASC")->where($where)->select()->toArray();
        //CMF5的Tree库，位于simplewind\extend\tree\Tree.php
        $tree = new Tree();
        $tree->icon = ['&nbsp;&nbsp;│', '&nbsp;&nbsp;├─', '&nbsp;&nbsp;└─'];
        $tree->nbsp = '&nbsp;&nbsp;';
        //判断变量类型是否为数组类型，不是的话将其转换成数组形式
        if (!is_array($currentIds)) {
            $currentIds = [$currentIds];
        }
        //创建一个新的数组，将查询出来的数据遍历后加上链接等想要的参数
        $newCategories = [];
        foreach ($categories as $item) {
            //检测是否为当前选中栏目
            $item['checked'] = in_array($item['id'], $currentIds) ? "checked" : "";
            //加上当前栏目的URL链接
            $item['url'] = cmf_url('project/List/index', ['id' => $item['id']]);
            //加上添加，修改文字及链接
            $item['str_action'] = '<a href="' . url("AdminCategory/add", ["parent" => $item['id']]) . '">添加子分类</a> | <a href="' . url("AdminCategory/edit", ["id" => $item['id']]) . '">' . lang('EDIT') . '</a> | <a class="js-ajax-delete" href="' . url("AdminCategory/delete", ["id" => $item['id']]) . '">' . lang('DELETE') . '</a> ';
            //创建新的数组
            array_push($newCategories, $item);
        }
        //将新的数组进行初始化
        $tree->init($newCategories);
        //定义数的展现结构，这边采用的是表格的<tr><td>
        if (empty($tpl)) {
            $tpl = "<tr>
                        <td><input name='list_orders[\$id]' type='text' size='3' value='\$list_order' class='input-order'></td>
                        <td>\$id</td>
                        <td>\$spacer <a href='\$url' target='_blank'>\$name</a></td>
                        <td>\$description</td>
                        <td>\$str_action</td>
                    </tr>";
        }
        $treeStr = $tree->getTree(0, $tpl);
        return $treeStr;
    }

    /**
     * 添加分类写数据可处理
     * @param $data
     * @return bool
     */
    public function addCategory($data)
    {
        $result = true;
        //数据库的事务操作，启动事务
        self::startTrans();
        try {
            //检测提交数据中是否有缩略图，有的话进行获取这个图片的相对路径
            if (!empty($data['more']['thumbnail'])) {
                $data['more']['thumbnail'] = cmf_asset_relative_url($data['more']['thumbnail']);
            }
            //控制器外部提交赋值给模型，过滤非数据表字段的数据，并写入数据库
            $this->allowField(true)->save($data);
            //获取新增数据的那个自增ID字段
            $id = $this->id;

            //判断是否是属于哪个栏目，也就是是否存在父栏目ID号
            if (empty($data['parent_id'])) {
                // 过滤post数组中的非数据表字段数据，并将path字段按照0-id号形式写入
                $this->where(['id' => $id])->update(['path' => '0-' . $id]);
            } else {
                //如果本身是存在父栏目的，先将父节点的path值查询出来，然后再加上当前节点ID并写入数据库
                $parentPath = $this->where('id', intval($data['parent_id']))->value('path');
                $this->where(['id' => $id])->update(['path' => "$parentPath-$id"]);
            }

            //处理后的事务提交，写入数据库
            self::commit();
        } catch (\Exception $e) {
            //异常回滚事务
            self::rollback();
            $result = false;
        }
        return $result;
    }

    public function editCategory($data)
    {
        $result = true;

        //获取修改分类的id，parent_id，并将当前这个ID的原始数据获取出来
        $id = intval($data['id']);
        $parentId = intval($data['parent_id']);
        $oldCategory = $this->where('id', $id)->find();

        //判断修改的分类是否为一级还是子级栏目，两种方式不同，一级直接0-id，子级的话先将原来的当前ID的父节点的path找出来，再加上当前id
        if (empty($parentId)) {
            $newPath = '0-' . $id;
        } else {
            $parentPath = $this->where('id', intval($data['parent_id']))->value('path');
            if ($parentPath === false) {
                $newPath = false;
            } else {
                $newPath = "$parentPath-$id";
            }
        }

        //如果找不到原来的数据，或者生成的path出错，则报错，不然就写数据库
        if (empty($oldCategory) || empty($newPath)) {
            $result = false;
        } else {

            //重新赋值path值
            $data['path'] = $newPath;

            //处理上传的图片，将图片转成相对路径
            if (!empty($data['more']['thumbnail'])) {
                $data['more']['thumbnail'] = cmf_asset_relative_url($data['more']['thumbnail']);
            }

            //过滤非数据库字段内容后保存数据，写入当前id数据库记录
            $this->isUpdate(true)->allowField(true)->save($data, ['id' => $id]);


            //处理这个修改的栏目下所有子栏目的path
            //将原来这个id的所有子栏目全部查询出来
            $children = $this->field('id,path')->where('path', 'like', "%-$id-%")->select();

            //查询出来后对数组进行遍历替换修改，再写入数据库
            if (!empty($children)) {
                foreach ($children as $child) {
                    $childPath = str_replace($oldCategory['path'] . '-', $newPath . '-', $child['path']);
                    $this->isUpdate(true)->save(['path' => $childPath], ['id' => $child['id']]);
                }
            }

            //处理URL美化，设置了别名就写数据，没设置删除原来的。
            $routeModel = new RouteModel();
            if (!empty($data['alias'])) {
                //写数据的时候一次性写2条，一条是列表的页的美化路由，另外一条是该栏目下的文章的路由
                //如果你不喜欢原来的路由规范你也可以在这个地方进行修改
                $routeModel->setRoute($data['alias'], 'project/List/index', ['id' => $data['id']], 2, 8000);
                $routeModel->setRoute($data['alias'] . '/:id', 'project/Article/index', ['cid' => $data['id']], 2, 7999);
            } else {
                $routeModel->deleteRoute('project/List/index', ['id' => $data['id']]);
                $routeModel->deleteRoute('project/Article/index', ['cid' => $data['id']]);
            }
            //使路由生效，其实就是写到路由文件里
            $routeModel->getRoutes(true);
        }

        return $result;
    }

}