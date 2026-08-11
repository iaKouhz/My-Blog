<?php
/**
 * 后台视图：文章列表（layui 选项卡筛选 + layui 表格/徽章/按钮）
 */
defined('APP_BOOT') or exit;
$tabs = array('all' => '全部', 'published' => '已发布', 'pending' => '待审核', 'draft' => '草稿', 'trash' => '回收站');
$typeTabs = array('all' => '全部内容', 'post' => '文章', 'page' => '页面');
// 列表链接统一携带状态+类型两个筛选参数
$listUrl = function ($tabStatus, $tabType) {
    $url = 'post/list';
    if ($tabStatus !== 'all') {
        $url .= '&status=' . $tabStatus;
    }
    if ($tabType !== 'all') {
        $url .= '&type=' . $tabType;
    }
    return site_base_admin($url);
};
$statusTags = array(
    'published' => '<span class="layui-badge layui-bg-green">已发布</span>',
    'pending'   => '<span class="layui-badge layui-bg-warn">待审核</span>',
    'draft'     => '<span class="layui-badge layui-bg-gray">草稿</span>',
    'trash'     => '<span class="layui-badge layui-bg-red">回收站</span>',
);
?>
<div class="card">
    <?php // 状态筛选：layui 简明选项卡（点击走整页链接，服务端渲染） ?>
    <div class="layui-tab layui-tab-brief" style="margin-bottom:8px">
        <ul class="layui-tab-title">
            <?php foreach ($tabs as $tabKey => $tabName): ?>
            <li class="<?php echo $status === $tabKey ? 'layui-this' : ''; ?>">
                <a href="<?php echo e($listUrl($tabKey, $type)); ?>"><?php echo e($tabName); ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="filter-bar" style="margin-bottom:14px">
        <?php // 类型筛选：独立页面与文章同表，创建后需可见可管理 ?>
        <?php foreach ($typeTabs as $typeKey => $typeName): ?>
        <a class="layui-btn layui-btn-sm <?php echo $type === $typeKey ? '' : 'layui-btn-primary'; ?>"
           href="<?php echo e($listUrl($status, $typeKey)); ?>"><?php echo e($typeName); ?></a>
        <?php endforeach; ?>
        <?php if (Auth::check_cap('edit_posts')): ?>
        <a class="layui-btn layui-btn-sm" style="margin-left:auto" href="<?php echo e(site_base_admin('post/edit')); ?>">
            <i class="layui-icon layui-icon-add-1"></i> 新增文章
        </a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th>标题</th><th>作者</th><th>分类</th><th>状态</th><th>浏览</th><th>更新时间</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($posts)): ?>
        <tr class="empty-row"><td colspan="8">暂无数据</td></tr>
        <?php endif; ?>
        <?php foreach ($posts as $postItem): ?>
        <tr>
            <td><?php echo (int) $postItem['id']; ?></td>
            <td><?php echo e($postItem['title']); ?><?php if ((int) $postItem['is_page'] === 1): ?> <span class="layui-badge layui-bg-gray">页面</span><?php endif; ?><?php if (!empty($postItem['is_top'])): ?> <span class="layui-badge layui-bg-green">置顶</span><?php endif; ?></td>
            <td><?php echo isset($users[(int) $postItem['author_id']]) ? e($users[(int) $postItem['author_id']]) : '-'; ?></td>
            <td><?php echo isset($cats[(int) $postItem['category_id']]) ? e($cats[(int) $postItem['category_id']]) : '-'; ?></td>
            <td><?php echo $statusTags[$postItem['status']]; ?></td>
            <td><?php echo (int) $postItem['views']; ?></td>
            <td><?php echo e($postItem['updated_at']); ?></td>
            <td>
                <div class="row-actions">
                <?php if ($postItem['status'] === 'pending' && Auth::check_cap('moderate_posts')): ?>
                <form method="post" action="<?php echo e(site_base_admin('post/audit')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal" name="decision" value="approve">通过</button>
                    <button class="layui-btn layui-btn-xs layui-btn-primary" name="decision" value="reject">驳回</button>
                </form>
                <?php endif; ?>
                <?php if ($postItem['status'] !== 'trash'): ?>
                <a class="layui-btn layui-btn-xs" href="<?php echo e(site_base_admin('post/edit&id=' . (int) $postItem['id'])); ?>">编辑</a>
                <?php endif; ?>
                <?php // 置顶仅对文章有意义（独立页面不进前台列表流），页面行不展示该操作 ?>
                <?php if ($postItem['status'] === 'published' && (int) $postItem['is_page'] === 0 && Auth::check_cap('moderate_posts')): ?>
                <form method="post" action="<?php echo e(site_base_admin('post/top')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                    <input type="hidden" name="status" value="<?php echo e($status); ?>">
                    <input type="hidden" name="type" value="<?php echo e($type); ?>">
                    <button class="layui-btn layui-btn-xs <?php echo empty($postItem['is_top']) ? 'layui-btn-primary' : ''; ?>" type="submit"><?php echo empty($postItem['is_top']) ? '置顶' : '取消置顶'; ?></button>
                </form>
                <?php endif; ?>
                <?php if (Auth::check_cap('delete_posts')): ?>
                    <?php if ($postItem['status'] === 'trash'): ?>
                    <form method="post" action="<?php echo e(site_base_admin('post/restore')); ?>">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                        <button class="layui-btn layui-btn-xs layui-btn-primary">恢复</button>
                    </form>
                    <form method="post" action="<?php echo e(site_base_admin('post/destroy')); ?>"
                          data-confirm="彻底删除后不可恢复，确认？">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                        <button class="layui-btn layui-btn-xs layui-btn-danger">彻底删除</button>
                    </form>
                    <?php else: ?>
                    <form method="post" action="<?php echo e(site_base_admin('post/delete')); ?>"
                          data-confirm="确认移入回收站？">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                        <button class="layui-btn layui-btn-xs layui-btn-danger">删除</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php do_action('post_list_row_actions', $postItem); /* 插件注入点：行内追加操作按钮（输出需自带 CSRF 表单并自行转义） */ ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php echo admin_pager($page, $totalPages, 'post/list&status=' . $status . '&type=' . $type . '&'); ?>
</div>
