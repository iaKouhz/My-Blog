<?php
/**
 * 后台视图：评论列表与审核（layui 选项卡 + 筛选栏 + 表格/徽章/按钮）
 * 日期范围经 laydate 选择，选中后由 admin.js 拆回隐藏域 from/to 提交
 */
defined('APP_BOOT') or exit;
$tabs = array('all' => '全部', 'pending' => '待审核', 'published' => '已公开', 'trash' => '回收站');
// 范围框回显文本：双侧均有值时拼为范围，否则回显已有单侧值
if ($fFrom !== '' && $fTo !== '') {
    $rangeText = $fFrom . ' ~ ' . $fTo;
} elseif ($fFrom !== '') {
    $rangeText = $fFrom;
} else {
    $rangeText = $fTo;
}
// 页签与分页链接携带当前筛选条件，切换时不丢失
$extraQuery = 'author=' . urlencode($fAuthor) . '&post_id=' . (int) $fPostId
    . '&from=' . $fFrom . '&to=' . $fTo;
?>
<div class="card">
    <?php // 状态筛选：layui 简明选项卡（点击走整页链接，服务端渲染） ?>
    <div class="layui-tab layui-tab-brief">
        <ul class="layui-tab-title">
            <?php foreach ($tabs as $tabKey => $tabName): ?>
            <li class="<?php echo $status === $tabKey ? 'layui-this' : ''; ?>">
                <a href="<?php echo e(site_base_admin('comment/list&status=' . $tabKey . '&' . $extraQuery)); ?>"><?php echo e($tabName); ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php // 细粒度筛选：作者（用户名/昵称或 ID）、文章 ID、发表时间范围 ?>
    <form method="get" action="<?php echo e(site_base_admin('comment/list')); ?>" class="layui-form filter-bar">
        <input type="hidden" name="m" value="comment/list">
        <input type="hidden" name="status" value="<?php echo e($status); ?>">
        <input type="text" name="author" placeholder="作者用户名/昵称或 ID" value="<?php echo e($fAuthor); ?>" class="layui-input" style="width:180px">
        <input type="text" name="post_id" placeholder="文章 ID" value="<?php echo $fPostId > 0 ? (string) (int) $fPostId : ''; ?>" class="layui-input" style="width:110px">
        <input type="hidden" name="from" id="cmtDateFrom" value="<?php echo e($fFrom); ?>">
        <input type="hidden" name="to" id="cmtDateTo" value="<?php echo e($fTo); ?>">
        <input type="text" class="layui-input cb-date-range" data-from="#cmtDateFrom" data-to="#cmtDateTo"
               placeholder="发表时间范围" autocomplete="off" style="width:230px"
               value="<?php echo e($rangeText); ?>">
        <button class="layui-btn layui-btn-sm" type="submit"><i class="layui-icon layui-icon-search"></i> 筛选</button>
        <a class="layui-btn layui-btn-sm layui-btn-primary" href="<?php echo e(site_base_admin('comment/list&status=' . $status)); ?>">重置</a>
    </form>
    <p class="tip">共 <?php echo (int) $total; ?> 条评论。</p>
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th>作者</th><th>内容</th><th>所属文章</th><th>状态</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($comments)): ?>
        <tr class="empty-row"><td colspan="7">暂无数据</td></tr>
        <?php endif; ?>
        <?php foreach ($comments as $commentItem): ?>
        <tr>
            <td><?php echo (int) $commentItem['id']; ?></td>
            <td><?php echo isset($users[(int) $commentItem['user_id']]) ? e($users[(int) $commentItem['user_id']]) : '-'; ?></td>
            <td style="max-width:340px"><?php echo e(mb_substr($commentItem['content'], 0, 80)); ?></td>
            <td><?php echo isset($posts[(int) $commentItem['post_id']]) ? e($posts[(int) $commentItem['post_id']]) : '-'; ?></td>
            <td>
                <?php if ($commentItem['status'] === 'published'): ?><span class="layui-badge layui-bg-green">已公开</span>
                <?php elseif ($commentItem['status'] === 'pending'): ?><span class="layui-badge layui-bg-warn">待审核</span>
                <?php else: ?><span class="layui-badge layui-bg-red">回收站</span><?php endif; ?>
            </td>
            <td><?php echo e($commentItem['created_at']); ?></td>
            <td>
                <div class="row-actions">
                <?php if ($commentItem['status'] === 'pending'): ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/audit')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal" name="decision" value="approve">通过</button>
                    <button class="layui-btn layui-btn-xs layui-btn-danger" name="decision" value="reject">驳回</button>
                </form>
                <?php elseif ($commentItem['status'] === 'published'): ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/delete')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-danger">删除</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/restore')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary">恢复</button>
                </form>
                <?php endif; ?>
                <?php do_action('comment_list_row_actions', $commentItem); /* 插件注入点：行内追加操作按钮（输出需自带 CSRF 表单并自行转义） */ ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php echo admin_pager($page, $totalPages, 'comment/list&status=' . $status . '&' . $extraQuery . '&'); ?>
</div>
