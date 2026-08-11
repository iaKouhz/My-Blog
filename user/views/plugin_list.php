<?php
/**
 * 后台视图：插件管理（layui 表格/徽章/按钮）
 */
defined('APP_BOOT') or exit;
?>
<?php if (!empty($orphans)): ?>
<div class="card">
    <p class="tip">以下插件的目录已不存在（可能被直接删除而非经后台卸载），但其配置与数据仍残留在数据库中：</p>
    <div class="row-actions">
    <?php foreach ($orphans as $orphanSlug): ?>
    <form method="post" action="<?php echo e(site_base_admin('plugin/cleanup_orphan')); ?>"
          data-confirm="确认清理该插件的全部残留数据？此操作不可恢复">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="slug" value="<?php echo e($orphanSlug); ?>">
        <span><?php echo e($orphanSlug); ?></span>
        <button class="layui-btn layui-btn-xs layui-btn-danger">清理残留数据</button>
    </form>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<div class="card">
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>插件</th><th>描述</th><th>版本</th><th>作者</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($plugins)): ?>
        <tr class="empty-row"><td colspan="6">未发现任何插件</td></tr>
        <?php endif; ?>
        <?php foreach ($plugins as $slug => $meta): ?>
        <?php $active = in_array($slug, $actives, true); ?>
        <tr>
            <td><?php echo e($meta['name']); ?><br><small><?php echo e($slug); ?></small></td>
            <td><?php echo e($meta['description']); ?></td>
            <td><?php echo e($meta['version']); ?></td>
            <td><?php echo e($meta['author']); ?></td>
            <td><?php echo $active ? '<span class="layui-badge layui-bg-green">已启用</span>' : '<span class="layui-badge layui-bg-gray">已禁用</span>'; ?></td>
            <td>
                <div class="row-actions">
                <?php if ($active): ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/deactivate')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary">禁用</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/activate')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal">启用</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/uninstall')); ?>"
                      data-confirm="确认删除该插件？此操作不可恢复">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-danger">删除</button>
                </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
