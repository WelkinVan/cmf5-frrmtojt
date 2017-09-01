<?php

namespace app\project\service;

use app\project\model\ProjectPostModel;

class ProjectService
{
    /**
     * 文章列表，包含过滤参数$filter
     * @param $filter 过滤参数
     * @return array
     */

    public function adminProjectList($filter)
    {
        //可用性判断，创建时间有，删除时间为0
        $where = [
            'a.create_time' => ['>=', 0],
            'a.delete_time' => 0
        ];

        //关联用户表，主要是记录这条项目内容是哪个用户发的
        $join = [
            ['__USER__ u', 'a.user_id = u.id']
        ];

        //筛选字段，PorjectPost表中的所有字段和用户表的部分数据
        $field = 'a.*,u.user_login,u.user_nickname,u.user_email';

        //过滤字段：分类ID号，增加指定分类的筛选，project_category_post表中的关联字段数据
        $category = empty($filter['category']) ? 0 : intval($filter['category']);
        if (!empty($category)) {
            $where['b.category_id'] = ['eq', $category];
            //有对应分类ID的时候才增加关联表操作，并且修改对应的查询字段内容，目的可以加快处理效率
            array_push($join, [
                '__PROJECT_CATEGORY_POST__ b', 'a.id = b.post_id'
            ]);
            $field = 'a.*,b.id AS post_category_id,b.list_order,b.category_id,u.user_login,u.user_nickname,u.user_email';
        }

        //数据的添加时间和结束时间，用于按照添加时间段来检索数据
        $startTime = empty($filter['start_time']) ? 0 : strtotime($filter['start_time']);
        $endTime = empty($filter['end_time']) ? 0 : strtotime($filter['end_time']);

        if (!empty($startTime) && !empty($endTime)) {
            $where['a.create_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $where['a.create_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $where['a.create_time'] = ['<= time', $endTime];
            }
        }

        //检索关键词，模糊查询
        $keyword = empty($filter['keyword']) ? '' : $filter['keyword'];
        if (!empty($keyword)) {
            $where['a.post_title'] = ['like', "%$keyword%"];
        }

        //实例化项目表模型，将满足检索条件的数据检索出来并返回
        $projectPostModel = new ProjectPostModel();
        $articles = $projectPostModel->alias('a')->field($field)
            ->join($join)
            ->where($where)
            ->order('update_time', 'DESC')
            ->paginate(10);

        return $articles;

    }

    public function publishedArticle($postId, $categoryId = 0)
    {
        $portalPostModel = new PortalPostModel();

        if (empty($categoryId)) {

            $where = [
                'post.post_type' => 1,
                'post.published_time' => [['< time', time()], ['> time', 0]],
                'post.post_status' => 1,
                'post.delete_time' => 0,
                'post.id' => $postId
            ];

            $article = $portalPostModel->alias('post')->field('post.*')
                ->where($where)
                ->find();
        } else {
            $where = [
                'post.post_type' => 1,
                'post.published_time' => [['< time', time()], ['> time', 0]],
                'post.post_status' => 1,
                'post.delete_time' => 0,
                'relation.category_id' => $categoryId,
                'relation.post_id' => $postId
            ];

            $join = [
                ['__PORTAL_CATEGORY_POST__ relation', 'post.id = relation.post_id']
            ];
            $article = $portalPostModel->alias('post')->field('post.*')
                ->join($join)
                ->where($where)
                ->find();
        }


        return $article;
    }

    //上一篇文章
    public function publishedPrevArticle($postId, $categoryId = 0)
    {
        $portalPostModel = new PortalPostModel();

        if (empty($categoryId)) {

            $where = [
                'post.post_type' => 1,
                'post.published_time' => [['< time', time()], ['> time', 0]],
                'post.post_status' => 1,
                'post.delete_time' => 0,
                'post.id ' => ['<', $postId]
            ];

            $article = $portalPostModel->alias('post')->field('post.*')
                ->where($where)
                ->order('id', 'DESC')
                ->find();

        } else {
            $where = [
                'post.post_type' => 1,
                'post.published_time' => [['< time', time()], ['> time', 0]],
                'post.post_status' => 1,
                'post.delete_time' => 0,
                'relation.category_id' => $categoryId,
                'relation.post_id' => ['<', $postId]
            ];

            $join = [
                ['__PORTAL_CATEGORY_POST__ relation', 'post.id = relation.post_id']
            ];
            $article = $portalPostModel->alias('post')->field('post.*')
                ->join($join)
                ->where($where)
                ->order('id', 'DESC')
                ->find();
        }


        return $article;
    }

    //下一篇文章
    public function publishedNextArticle($postId, $categoryId = 0)
    {
        $portalPostModel = new PortalPostModel();

        if (empty($categoryId)) {

            $where = [
                'post.post_type' => 1,
                'post.published_time' => [['< time', time()], ['> time', 0]],
                'post.post_status' => 1,
                'post.delete_time' => 0,
                'post.id' => ['>', $postId]
            ];

            $article = $portalPostModel->alias('post')->field('post.*')
                ->where($where)
                ->order('id', 'ASC')
                ->find();
        } else {
            $where = [
                'post.post_type' => 1,
                'post.published_time' => [['< time', time()], ['> time', 0]],
                'post.post_status' => 1,
                'post.delete_time' => 0,
                'relation.category_id' => $categoryId,
                'relation.post_id' => ['>', $postId]
            ];

            $join = [
                ['__PORTAL_CATEGORY_POST__ relation', 'post.id = relation.post_id']
            ];
            $article = $portalPostModel->alias('post')->field('post.*')
                ->join($join)
                ->where($where)
                ->order('id', 'ASC')
                ->find();
        }


        return $article;
    }


    public function publishedPage($pageId)
    {

        $where = [
            'post_type' => 2,
            'published_time' => [['< time', time()], ['> time', 0]],
            'post_status' => 1,
            'delete_time' => 0,
            'id' => $pageId
        ];

        $portalPostModel = new PortalPostModel();
        $page = $portalPostModel
            ->where($where)
            ->find();

        return $page;
    }

}