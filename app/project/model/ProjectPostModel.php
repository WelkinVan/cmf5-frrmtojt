<?php

namespace app\project\model;

use think\Model;

class ProjectPostModel extends Model
{

    /**
     * 后台管理添加项目处理
     * @param array $data 文章数据
     * @param array|string $categories 文章分类 id
     * @return $this
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


        if (is_string($categories)) {
            $categories = explode(',', $categories);
        }

        $this->categories()->save($categories);

        $data['post_keywords'] = str_replace('，', ',', $data['post_keywords']);

        $keywords = explode(',', $data['post_keywords']);

        $this->addTags($keywords, $this->id);

        return $this;

    }
}