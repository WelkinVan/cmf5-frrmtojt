<?php

namespace app\project\model;

use think\Model;
use think\Db;

class ProjectPostModel extends Model
{
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    /**
     * post_content 自动转化
     * @param $value
     * @return string
     */
    public function getPostContentAttr($value)
    {
        return cmf_replace_content_file_url(htmlspecialchars_decode($value));
    }

    /**
     * post_content 自动转化
     * @param $value
     * @return string
     */
    public function setPostContentAttr($value)
    {
        return htmlspecialchars(cmf_replace_content_file_url(htmlspecialchars_decode($value), true));
    }

    /**
     * 关联分类表
     */
    public function categories()
    {
        //多对多关联，主要用于一篇文章发布与多个栏目，这个是参照cmf5原文章系统
        return $this->belongsToMany('ProjectCategoryModel', 'project_category_post', 'category_id', 'post_id');
    }


    /**
     * 后台管理添加项目处理
     */

    public function adminProjectPost($data, $categories)
    {
        //获取后台当前登录的管理员信息
        $data['user_id'] = cmf_get_current_admin_id();

        //缩略图处理，相对路径处理
        if (!empty($data['more']['thumbnail'])) {
            $data['more']['thumbnail'] = cmf_asset_relative_url($data['more']['thumbnail']);
        }

        //控制器外部提交赋值给模型，过滤非数据表字段的数据，并写入数据库
        $this->allowField(true)->data($data, true)->isUpdate(false)->save();


        //判断提交的分类是不是多个，检测是否字符串，是的话将其按照逗号分割成数组
        if (is_string($categories)) {
            $categories = explode(',', $categories);
        }

        //将分类和文章ID关联后写入到project_category_post表
        $this->categories()->save($categories);

        //人性化处理，将关键字中的中文逗号改成英文逗号，因为在搜索引擎关键词检索是以英文逗号来进行分割的。改完后转存数组
        $data['post_keywords'] = str_replace('，', ',', $data['post_keywords']);
        $keywords = explode(',', $data['post_keywords']);

        //将本文章的关键词写入到Tag表，标签这边也可以单独字段写
        $this->addTags($keywords, $this->id);

        return $this;
    }

    /**
     * 后台管理编辑项目文章
     */
    public function adminProjectEdit($data, $categories)
    {

        //销毁修改文章的用户信息，默认保存时第一次发布的人，如果有需要可以加个表，记录每次修改是哪个管理员
        unset($data['user_id']);

        //缩略图地址处理
        if (!empty($data['more']['thumbnail'])) {
            $data['more']['thumbnail'] = cmf_asset_relative_url($data['more']['thumbnail']);
        }

        //检测发布状态，没勾选则0，勾选了就1
        $data['post_status'] = empty($data['post_status']) ? 0 : 1;

        //字段合法性验证，验证完后写数据库
        $this->allowField(true)->isUpdate(true)->data($data, true)->save();


        //新的分类处理,处理机制是：对比新旧2个ID序列，找出相同部分，新增部分和变化部分，然后删除变化部分，插入新增部分
        if (is_string($categories)) {
            $categories = explode(',', $categories);
        }

        $oldCategoryIds = $this->categories()->column('category_id');
        $sameCategoryIds = array_intersect($categories, $oldCategoryIds);
        $needDeleteCategoryIds = array_diff($oldCategoryIds, $sameCategoryIds);
        $newCategoryIds = array_diff($categories, $sameCategoryIds);


        //数据库操作部分，采用SplObjectStorage类，http://php.net/manual/en/class.splobjectstorage.php
        //变化部分的id节点全部删除
        if (!empty($needDeleteCategoryIds)) {
            $this->categories()->detach($needDeleteCategoryIds);
        }

        //插入新增的id节点
        if (!empty($newCategoryIds)) {
            $this->categories()->attach(array_values($newCategoryIds));
        }

        //处理下输入的关键词中文逗号，然后将关键词同步写入到标签云
        $data['post_keywords'] = str_replace('，', ',', $data['post_keywords']);
        $keywords = explode(',', $data['post_keywords']);
        $this->addTags($keywords, $data['id']);

        return $this;
    }

    /**
     * 添加到标签
     */

    public function addTags($keywords, $articleId)
    {
        //实例化标签云模型
        $projectTagModel = new ProjectTagModel();

        //定义2个数组，数据和编号
        $tagIds = [];
        $data = [];

        //传参过来的标签关键词处理
        if (!empty($keywords)) {
            //将对应这片文章的所有标签检索出来，采用的方法是数据库sql查询https://www.kancloud.cn/manual/thinkphp5/135176
            $oldTagIds = Db::name('project_tag_post')->where('post_id', $articleId)->column('tag_id');
            //这边也可以用模型方式来处理
            //$projectTagPostModel = new ProjectTagPostModel();
            //$oldTagIds = $projectTagPostModel->where('post_id',$articleId)->select();


            foreach ($keywords as $keyword) {
                //使用trim()移除传递过来的$keywords关键词两端的空白字符
                $keyword = trim($keyword);

                //比对原来project_tag表里面是否存在对应的标签，没有则创建数据并返回ID，存在则直接返回ID
                if (!empty($keyword)) {
                    $findTag = $projectTagModel->where('name', $keyword)->find();
                    if (empty($findTag)) {
                        $tagId = $projectTagModel->insertGetId([
                            'name' => $keyword
                        ]);
                    } else {
                        $tagId = $findTag['id'];
                    }

                    //检测新的标签ID是否都在原来的project_tag_post里，不在则追加标签云数组
                    if (!in_array($tagId, $oldTagIds)) {
                        array_push($data, ['tag_id' => $tagId, 'post_id' => $articleId]);
                    }
                    //生成这个文章的新的标签云ID数组
                    array_push($tagIds, $tagId);
                }
            }

            //如果新的标签云不存在，则清空原来这个文章的标签云数据
            if (empty($tagIds) && !empty($oldTagIds)) {
                Db::name('project_tag_post')->where('post_id', $articleId)->delete();
            }

            //找出新生成和原来的相同的文章标签ID，这部分是要保留的
            $sameTagIds = array_intersect($oldTagIds, $tagIds);

            //找出交集与原来的不相同的那部分标签ID，新增的已经加进数组，不需要再比较
            $shouldDeleteTagIds = array_diff($oldTagIds, $sameTagIds);

            //将找出不同的那部分进行删除
            if (!empty($shouldDeleteTagIds)) {
                Db::name('project_tag_post')->where(['post_id' => $articleId, 'tag_id' => ['in', $shouldDeleteTagIds]])->delete();
            }

            //将所有数据写入project_tag_post表
            if (!empty($data)) {
                Db::name('project_tag_post')->insertAll($data);
            }
        } else {
            Db::name('project_tag_post')->where('post_id', $articleId)->delete();
        }
    }
}